<?php
require_once __DIR__ . '/config_ai.php';

const AI_MAX_ATTEMPTS = 2;

class AiException extends RuntimeException {
    public int $attempts = 0;
}

function ai_log(string $msg): void {
    @file_put_contents(__DIR__ . '/ai_debug.log', date('c') . "\n" . $msg . "\n\n", FILE_APPEND);
}

function ai_http(string $url, string $body, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . AI_API_KEY],
        CURLOPT_POSTFIELDS     => $body,
    ]);
    $raw  = curl_exec($ch);
    $err  = $raw === false ? curl_error($ch) : '';
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['raw' => $raw, 'code' => $code, 'err' => $err];
}

function ai_text_from(?array $resp): string {
    $text = '';
    foreach ($resp['candidates'][0]['content']['parts'] ?? [] as $p) {
        if (!empty($p['thought'])) continue;
        $text .= $p['text'] ?? '';
    }
    return $text;
}

function ai_json_from(string $text): ?array {
    $text  = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $d = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($d) ? $d : null;
}

function ai_run(array $parts, callable $validate, int $maxTokens, int $timeout = 120): array {
    if (AI_API_KEY === '') {
        throw new AiException('AI is not configured. Set GEMINI_API_KEY or add config_ai.local.php.');
    }

    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent';
    $body = json_encode([
        'contents'         => [['parts' => $parts]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'maxOutputTokens'  => $maxTokens,
        ],
    ], JSON_INVALID_UTF8_SUBSTITUTE);

    $lastMsg = 'Unknown AI error.';

    for ($attempt = 1; $attempt <= AI_MAX_ATTEMPTS; $attempt++) {
        $r     = ai_http($url, $body, $timeout);
        $retry = true;

        if ($r['raw'] === false) {
            $lastMsg = 'Could not reach the AI: ' . $r['err'];
        } elseif ($r['code'] !== 200) {
            $resp    = json_decode($r['raw'], true);
            $lastMsg = 'AI error: ' . ($resp['error']['message'] ?? 'HTTP ' . $r['code']);
            ai_log("HTTP {$r['code']} (attempt $attempt)\n" . substr($r['raw'], 0, 3000));

            if (!in_array($r['code'], [429, 500, 502, 503, 504], true)) $retry = false;
        } else {
            $resp   = json_decode($r['raw'], true);
            $block  = $resp['promptFeedback']['blockReason'] ?? '';
            $finish = $resp['candidates'][0]['finishReason'] ?? 'none';

            $data = ai_json_from(ai_text_from($resp));
            $data = $data !== null ? $validate($data) : null;
            if ($data !== null) return [$data, $attempt];

            ai_log("Unusable response (attempt $attempt) finishReason=$finish blockReason=$block\n" . substr($r['raw'], 0, 4000));
            if ($block) {
                $lastMsg = "The AI blocked this request ($block).";
                $retry   = false;
            } else {
                $lastMsg = "The AI returned an unexpected response (finish reason: $finish). Details saved in ai_debug.log.";
            }
        }

        if (!$retry || $attempt === AI_MAX_ATTEMPTS) {
            $e = new AiException($lastMsg);
            $e->attempts = $attempt;
            throw $e;
        }
        sleep(2);
    }

    $e = new AiException($lastMsg);
    $e->attempts = AI_MAX_ATTEMPTS;
    throw $e;
}

function ai_generate(array $files, array $folderNames = []): array {
    $folderHint = '';
    $folderNames = array_slice(array_values(array_unique(array_filter($folderNames))), 0, 50);
    if ($folderNames) {
        $list = implode(', ', array_map(
            fn($n) => mb_substr(preg_replace('/\s+/', ' ', (string)$n), 0, 60),
            $folderNames
        ));
        $folderHint = "\nExisting subjects: $list. If the material fits one of them, use that exact name as \"subject\".";
    }

    $prompt = <<<TXT
You are a study assistant. Read the attached study material and return ONLY JSON in this shape:
{
  "title": "short title for these notes",
  "subject": "one to three word subject name, e.g. Biology",
  "notes": "clear, well-organised study notes in plain text, with short headings",
  "questions": [
    {"question": "...", "options": ["A","B","C","D"], "answer": 0, "topic": "short topic name"}
  ]
}
Rules: exactly 8 multiple-choice questions, exactly 4 options each, "answer" is the index (0-3) of the correct option as a number, "topic" names the concept being tested.
TXT;
    $prompt .= $folderHint;

    $parts = [['text' => $prompt]];
    foreach ($files as $f) {
        $parts[] = ['inline_data' => [
            'mime_type' => $f['mime'],
            'data'      => base64_encode($f['data']),
        ]];
    }

    $validate = function (array $d): ?array {
        if (empty($d['notes']) || empty($d['questions']) || !is_array($d['questions'])) return null;

        $clean = [];
        foreach ($d['questions'] as $q) {
            if (!is_array($q) || !isset($q['question'], $q['options'], $q['answer'])) continue;
            if (!is_array($q['options']) || count($q['options']) !== 4) continue;
            if (!is_numeric($q['answer'])) continue;
            $ans = (int)$q['answer'];
            if ($ans < 0 || $ans > 3) continue;
            $clean[] = [
                'question' => (string)$q['question'],
                'options'  => array_map('strval', array_values($q['options'])),
                'answer'   => $ans,
                'topic'    => (string)($q['topic'] ?? 'General'),
            ];
        }
        if (count($clean) < 4) return null;
        $clean = array_slice($clean, 0, 8);

        $subject = mb_substr(trim(strip_tags((string)($d['subject'] ?? ''))), 0, 100);

        return [
            'title'     => (string)($d['title'] ?? 'Untitled notes'),
            'subject'   => $subject !== '' ? $subject : 'General',
            'notes'     => (string)$d['notes'],
            'questions' => $clean,
        ];
    };

    [$result, $attempts] = ai_run($parts, $validate, 16000, 120);
    $result['_attempts'] = $attempts;
    return $result;
}