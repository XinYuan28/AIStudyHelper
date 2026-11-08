<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_logged_in()) { http_response_code(401); exit; }

if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    http_response_code(415);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !in_array($data['template'] ?? null, ['A', 'B'], true)
    || !is_string($data['topic'] ?? null) || !is_int($data['score'] ?? null)
    || $data['score'] < 0 || $data['score'] > 2147483647) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid score data.']);
    exit;
}
$template = $data['template'];
$topic = mb_substr(trim($data['topic']), 0, 255) ?: 'General';
$score = $data['score'];

$stmt = db()->prepare("INSERT INTO game_scores (user_id, template, topic, score) VALUES (?, ?, ?, ?)");
$stmt->execute([(int)$_SESSION['user_id'], $template, $topic, $score]);
echo json_encode(["ok" => true]);