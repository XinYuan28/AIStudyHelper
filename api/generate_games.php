<?php
require_once '../config_ai.php';
require_once '../auth.php';
require_once '../ai.php';
set_time_limit(150);
$go = isset($_REQUEST['go']);

function fail($code, $msg) {
    global $go;
    http_response_code($code);
    if ($go) { echo htmlspecialchars($msg) . ' <a href="../homepage.php">Back</a>'; }
    else { header('Content-Type: application/json'); echo json_encode(["error" => $msg]); }
    exit;
}

if (!isset($_SESSION[SESSION_USER_KEY])) fail(401, 'Not logged in');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) fail(403, 'Your session expired. Please refresh the page.');
$uid = (int)$_SESSION[SESSION_USER_KEY];

$template = ($_REQUEST['t'] ?? 'A') === 'B' ? 'B' : 'A';

$notes = trim($_POST['notes'] ?? '');
if ($notes === '' && isset($_REQUEST['note_id'])) {
    $stmt = $pdo->prepare("SELECT content FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_REQUEST['note_id'], $uid]);
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

try {
    [$data, $attempts] = ai_run(
        [['text' => buildPrompt($notes, $template)]],
        fn($d) => validateGame($d, $template) ? $d : null,
        8000,
        60
    );
} catch (AiException $e) {
    fail(422, 'Game generation failed, please try again.');
}

$_SESSION['game_data'] = $data;

if ($go) { header("Location: ../games/play.php?t=$template"); exit; }
header('Content-Type: application/json');
echo json_encode(["ok" => true, "redirect" => "games/play.php?t=$template"]);