<?php
// Turns the notes text into a downloadable WAV file with Gemini text-to-speech.
// Called by studyhub.js ("Download audio" in the Text-to-Speech panel): POST csrf + text, returns audio/wav.
function tts_fail(int $code, string $msg): void
{
    while (ob_get_level()) {
        ob_end_clean();   // drop stray warnings so only the JSON message is sent
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// any PHP error becomes a readable message in the page instead of a blank "something went wrong"
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    tts_fail(500, 'Server error: ' . $e->getMessage());
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        tts_fail(500, 'Server error: ' . $err['message'] . ' (' . basename($err['file']) . ' line ' . $err['line'] . ')');
    }
});
ob_start();   // keeps stray output (warnings, notices) out of the audio file

require_once __DIR__ . '/../auth.php';   // starts the session, gives is_logged_in() and csrf_ok()
require_once __DIR__ . '/../ai.php';     // AI_API_KEY (via config_ai.php) and ai_ca_bundle()

const TTS_MODEL     = 'gemini-3.8-flash-tts';
const TTS_VOICE     = 'Kore';
const TTS_CHUNK     = 3000;    // characters sent to Gemini per request (long notes are split)
const TTS_MAX_CHARS = 12000;   // longer notes are refused: 4 chunks x 45 s stays inside the 200 s time limit
const TTS_TIMEOUT   = 45;      // seconds per Gemini call
const TTS_RATE      = 24000;   // Gemini returns 24 kHz, mono, 16-bit audio
const TTS_COOLDOWN  = 10;      // seconds a user must wait between audio requests


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tts_fail(405, 'Wrong request type.');
}
if (!is_logged_in()) {
    tts_fail(401, 'Please log in again.');
}
if (!csrf_ok()) {
    tts_fail(403, 'Your session expired. Please refresh the page and try again.');
}

// stops one user from firing audio requests back to back
$now = time();
if ($now - (int) ($_SESSION['tts_last'] ?? 0) < TTS_COOLDOWN) {
    tts_fail(429, 'Please wait a few seconds before making another audio file.');
}
$text = trim(is_string($_POST['text'] ?? null) ? $_POST['text'] : '');
if ($text === '') {
    tts_fail(400, 'There is no text to turn into audio.');
}
if (mb_strlen($text) > TTS_MAX_CHARS * 2) {
    tts_fail(413, 'These notes are too long to turn into one audio file.');
}

if (AI_API_KEY === '') {
    tts_fail(503, 'Gemini speech is not configured. Set GEMINI_API_KEY on the server.');
}

$_SESSION['tts_last'] = $now;
session_write_close();
set_time_limit(300);   // one extra Gemini call (table narration) + up to 4 audio chunks

/* split long notes at paragraph / word boundaries */
function tts_chunks(string $text): array
{
    $out = [];
    $cur = '';
    foreach (preg_split('/\n\s*\n/', $text) as $para) {
        $para = trim($para);
        if ($para === '') {
            continue;
        }
        while (mb_strlen($para) > TTS_CHUNK) {
            $cut = mb_strrpos(mb_substr($para, 0, TTS_CHUNK), ' ');
            if ($cut === false || $cut < 1) {
                $cut = TTS_CHUNK;
            }
            if ($cur !== '') {
                $out[] = $cur;
                $cur = '';
            }
            $out[] = mb_substr($para, 0, $cut);
            $para = ltrim(mb_substr($para, $cut));
        }
        if ($cur !== '' && mb_strlen($cur) + mb_strlen($para) + 2 > TTS_CHUNK) {
            $out[] = $cur;
            $cur = '';
        }
        $cur = ($cur === '') ? $para : $cur . "\n\n" . $para;
    }
    if ($cur !== '') {
        $out[] = $cur;
    }
    return $out;
}

/* one call to Gemini; returns [http status, decoded json or null] */
function tts_call(string $chunk, array $speechConfig): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . TTS_MODEL . ':generateContent';
    $body = json_encode([
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' => $chunk]],
        ]],
        'generationConfig' => [
            'responseModalities' => ['AUDIO'],
            'speechConfig'       => $speechConfig,
        ],
    ]);

    $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . AI_API_KEY];

    if (function_exists('curl_init')) {
        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => TTS_TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($ca = ai_ca_bundle()) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr   = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            tts_fail(502, 'Could not reach the audio service. curl says: ' . ($cerr !== '' ? $cerr : 'no details'));
        }
        return [$status, json_decode($raw, true)];
    }

    // no curl on this server: use PHP's built-in https streams instead (needs allow_url_fopen and the openssl extension)
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => TTS_TIMEOUT,
        'ignore_errors' => true,   // still read the body when Google answers with an error code
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        $why = error_get_last();
        tts_fail(500, 'Could not reach the audio service. PHP says: ' . ($why['message'] ?? 'no details')
            . ' [curl: off'
            . ', openssl: ' . (extension_loaded('openssl') ? 'on' : 'off')
            . ', allow_url_fopen: ' . (ini_get('allow_url_fopen') ? 'on' : 'off')
            . ', php.ini: ' . (php_ini_loaded_file() ?: 'none') . ']');
    }
    $status = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return [$status, json_decode($raw, true)];
}

/* the raw 16-bit samples, whether Gemini sent a WAV file or headerless PCM */
function tts_pcm(string $bin): string
{
    if (substr($bin, 0, 4) !== 'RIFF') {
        return $bin;
    }
    $pos = 12;
    $len = strlen($bin);
    while ($pos + 8 <= $len) {
        $id   = substr($bin, $pos, 4);
        $size = unpack('V', substr($bin, $pos + 4, 4))[1];
        if ($id === 'data') {
            return ($size > 0 && $pos + 8 + $size <= $len) ? substr($bin, $pos + 8, $size) : substr($bin, $pos + 8);
        }
        $pos += 8 + $size + ($size & 1);
    }
    return substr($bin, 44);
}

/* two spellings of the single-voice setting; the first that Gemini accepts is kept.
   The documented spelling (prebuiltVoiceConfig) goes first so it normally works on the first call. */
$shapes = [
    ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => TTS_VOICE]]],
    ['voiceConfig' => ['voice' => TTS_VOICE]],
];
$shape = 0;
$pcm   = '';

/* Asks Gemini to turn the tables into spoken narration. Returns [table number => narration]; [] if anything fails. */
function tts_narrate(array $tables): array
{
    $keys = [];
    $items = [];
    foreach ($tables as $k => $t) {
        if (!is_string($t) || trim($t) === '') continue;
        $keys[] = $k;
        $items[] = mb_substr($t, 0, 2500);
        if (count($keys) >= 10) break;   // the rest use the simple version
    }
    if (!$keys) return [];

    $list = '';
    foreach ($items as $j => $t) {
        $list .= "\nTable " . ($j + 1) . ":\n" . $t . "\n";
    }
    $prompt = <<<'TXT'
Convert each numbered table below into natural, student-friendly spoken narration for a Text-to-Speech system.

Rules:
1. Introduce what the table is about.
2. Do not read column headers repeatedly.
3. Explain each row naturally.
4. For comparison tables, highlight differences and similarities.
5. For numerical tables, explain important values and trends without omitting essential information.
6. Use transitions such as "first", "next", "whereas", and "in comparison".
7. Preserve all important facts and relationships.
8. Do not invent or alter information.
9. Use short sentences suitable for listening.
10. Plain narration only: no Markdown, no symbols, no emoji.
11. Start directly with the content. Never say "Here is the narration".

Return ONLY JSON: {"narrations": ["narration for table 1", "narration for table 2"]}
Keep the same order and the same number of items as the tables.
TXT;

    $n = count($keys);
    $validate = function (array $d) use ($n): ?array {
        $a = $d['narrations'] ?? null;
        if (!is_array($a) || count($a) !== $n) return null;
        $clean = [];
        foreach ($a as $s) {
            $s = trim(preg_replace('/[*`|#]/', '', (string) $s));
            if ($s === '') return null;
            $clean[] = $s;
        }
        return ['narrations' => $clean];
    };

    try {
        [$data] = ai_run([['text' => $prompt . "\n" . $list]], $validate, 8000, 40);
    } catch (Throwable $e) {
        return [];   // 429, timeout, bad JSON... the simple version is read instead
    }
    $out = [];
    foreach ($keys as $j => $k) {
        $out[$k] = $data['narrations'][$j];
    }
    return $out;
}

/* swaps each [[T0]]...[[/T0]] block for its narration, or for the simple version when there is none */
function tts_apply_tables(string $text, array $narr): string
{
    $text = preg_replace_callback('/\[\[T(\d+)\]\](.*?)\[\[\/T\1\]\]/s', function ($m) use ($narr) {
        $i = (int) $m[1];
        return isset($narr[$i]) ? "\n\n" . $narr[$i] . "\n\n" : "\n\n" . trim($m[2]) . "\n\n";
    }, $text);
    return trim(preg_replace('/\n{3,}/', "\n\n", $text));
}

$original = $text;
$tables = json_decode((string) ($_POST['tables'] ?? ''), true);
$narr = is_array($tables) && $tables ? tts_narrate($tables) : [];
$text = tts_apply_tables($text, $narr);
if (mb_strlen($text) > TTS_MAX_CHARS) {
    $text = tts_apply_tables($original, []);   // too long with narration: use the simple version
}
if (mb_strlen($text) > TTS_MAX_CHARS) {
    tts_fail(413, 'These notes are too long to turn into one audio file.');
}

foreach (tts_chunks($text) as $chunk) {
    $audio = null;
    $error = 'The audio service did not answer. Please try again.';

    for (; $shape < count($shapes); $shape++) {
        [$status, $data] = tts_call($chunk, $shapes[$shape]);

        if ($status === 200 && is_array($data)) {
            foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
                if (!empty($part['inlineData']['data'])) {
                    $audio = base64_decode($part['inlineData']['data'], true);
                    break;
                }
            }
            if ($audio !== null && $audio !== false) {
                break;   // this voice setting works
            }
            $error = 'The audio service returned no audio.';
            break;
        }

        $error = is_array($data) && !empty($data['error']['message'])
            ? 'Audio service error: ' . $data['error']['message']
            : 'The audio service returned an error (' . $status . ').';

        if ($status !== 400) {
            break;   // only a 400 means "try the other voice setting"
        }
    }

    if ($audio === null || $audio === false) {
        tts_fail(502, $error);
    }
    $pcm .= tts_pcm($audio);
}

$n = strlen($pcm);
$wav = 'RIFF' . pack('V', 36 + $n) . 'WAVE'
     . 'fmt ' . pack('VvvVVvv', 16, 1, 1, TTS_RATE, TTS_RATE * 2, 2, 16)
     . 'data' . pack('V', $n) . $pcm;

ob_end_clean();
header('Content-Type: audio/wav');
header('Content-Length: ' . strlen($wav));
header('Cache-Control: no-store');
echo $wav;