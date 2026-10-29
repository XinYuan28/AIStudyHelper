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
    error_log('[api/tts.php] ' . $e->getMessage());
    tts_fail(500, 'The audio service is temporarily unavailable.');
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        error_log('[api/tts.php] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        tts_fail(500, 'The audio service is temporarily unavailable.');
    }
});
ob_start();   // keeps stray output (warnings, notices) out of the audio file

require __DIR__ . '/../auth.php';          // starts the session, gives is_logged_in() and csrf_ok()
require_once __DIR__ . '/../config_ai.php'; // AI_API_KEY

const TTS_MODEL     = 'gemini-3.8-flash-tts';
const TTS_VOICE     = 'Kore';
const TTS_CHUNK     = 3000;    // characters sent to Gemini per request (long notes are split)
const TTS_MAX_CHARS = 24000;   // longer notes are refused so one request cannot run for ages
const TTS_RATE      = 24000;   // Gemini returns 24 kHz, mono, 16-bit audio


if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tts_fail(405, 'Wrong request type.');
}
if (!is_logged_in()) {
    tts_fail(401, 'Please log in again.');
}
if (!csrf_ok()) {
    tts_fail(403, 'Your session expired. Please refresh the page and try again.');
}
session_write_close();   // do not block other pages while the audio is being made
@set_time_limit(180);

$text = trim((string) ($_POST['text'] ?? ''));
if ($text === '') {
    tts_fail(400, 'There is no text to turn into audio.');
}
if (mb_strlen($text) > TTS_MAX_CHARS) {
    tts_fail(413, 'These notes are too long to turn into one audio file.');
}

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
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr   = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            error_log('[api/tts.php] curl: ' . ($cerr !== '' ? $cerr : 'no details'));
            tts_fail(502, 'Could not reach the audio service. Please try again.');
        }
        return [$status, $raw === false ? null : json_decode($raw, true)];
    }

    // no curl on this server: use PHP's built-in https streams instead (needs allow_url_fopen and the openssl extension)
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => 120,
        'ignore_errors' => true,   // still read the body when Google answers with an error code
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        $why = error_get_last();
        error_log('[api/tts.php] stream request: ' . ($why['message'] ?? 'no details'));
        tts_fail(502, 'Could not reach the audio service. Please try again.');
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

/* two spellings of the single-voice setting; the first that Gemini accepts is kept */
$shapes = [
    ['voiceConfig' => ['voice' => TTS_VOICE]],
    ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => TTS_VOICE]]],
];
$shape = 0;
$pcm   = '';

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