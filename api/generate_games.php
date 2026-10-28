<?php
require_once '../config_ai.php';
require_once '../db.php';
set_time_limit(120);
$go = isset($_REQUEST['go']);

function fail($code, $msg) {
    global $go;
    http_response_code($code);
    if ($go) { echo htmlspecialchars($msg) . ' <a href="../homepage.php">Back</a>'; }
    else { header('Content-Type: application/json'); echo json_encode(["error" => $msg]); }
    exit;
}

if (!isset($_SESSION[SESSION_USER_KEY])) fail(401, 'Not logged in');

$template = ($_REQUEST['t'] ?? 'A') === 'B' ? 'B' : 'A';


$notes = trim($_POST['notes'] ?? '');
if ($notes === '' && isset($_REQUEST['note_id'])) {
    $stmt = $pdo->prepare("SELECT content FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_REQUEST['note_id'], (int)$_SESSION[SESSION_USER_KEY]]);
    $row = $stmt->fetch();
    $notes = trim($row['content'] ?? '');
}
if ($notes === '') fail(400, 'No notes found');
$notes = mb_substr($notes, 0, 12000);

function buildPrompt($notes, $template) {
    if ($template === 'A') {
        $schema = '{"template":"A","title":"","pairs":[{"term":"","definition":""}]}';
        $rule = "Create 12 term-definition pairs. Each term must be 1-3 words. Each definition must be 1-6 words. Every term and definition must be unique.";
    } else {
        $schema = '{"template":"B","title":"","sentences":[""]}';
        $rule = "Create 10 factual sentences that summarise key points of the notes. Each sentence is 8-20 words, plain text for a typing game: only letters, digits, spaces and basic punctuation (. , ; : ' ? ! -). No quotes, no symbols, no line breaks.";
    }
    return "Using ONLY the study notes below, $rule\nReturn ONLY valid JSON matching this exact structure, no markdown, no extra text:\n$schema\n\nNOTES:\n$notes";
}

function callAI($prompt) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADERS => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . AI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ]),
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

function validateGame($d, $t) {
    if (!is_array($d) || ($d['template'] ?? '') !== $t) return false;
    if ($t === 'A') {
        if (empty($d['pairs']) || !is_array($d['pairs']) || count($d['pairs']) < 6) return false;
        foreach ($d['pairs'] as $p) {
            if (!is_array($p) || empty($p['term']) || empty($p['definition'])) return false;
            if (!is_string($p['term']) || !is_string($p['definition'])) return false;
        }
    } else {
        if (empty($d['sentences']) || !is_array($d['sentences']) || count($d['sentences']) < 4) return false;
        foreach ($d['sentences'] as $s) if (!is_string($s) || strlen(trim($s)) < 10) return false;
    }
    return true;
}

$data = null;
for ($i = 0; $i < 3; $i++) {
    $raw = callAI(buildPrompt($notes, $template));
    $raw = trim(preg_replace('/^```json|```$/m', '', $raw));
    $data = json_decode($raw, true);
    if (validateGame($data, $template)) break;
    $data = null;
}
if (!$data) fail(422, 'Game generation failed, please try again');

$_SESSION['game_data'] = $data;

if ($go) { header("Location: ../games/play.php?t=$template"); exit; }
header('Content-Type: application/json');
echo json_encode(["ok" => true, "redirect" => "games/play.php?t=$template"]);