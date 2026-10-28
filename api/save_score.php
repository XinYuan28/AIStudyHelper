<?php
require_once '../config_ai.php';
require_once '../db.php';   // starts the session and defines $pdo
if (!isset($_SESSION[SESSION_USER_KEY])) { http_response_code(401); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$template = ($data['template'] ?? '') === 'B' ? 'B' : 'A';
$topic = substr($data['topic'] ?? '', 0, 255);
$score = (int)($data['score'] ?? 0);

$stmt = $pdo->prepare("INSERT INTO game_scores (user_id, template, topic, score) VALUES (?, ?, ?, ?)");
$stmt->execute([(int)$_SESSION[SESSION_USER_KEY], $template, $topic, $score]);
echo json_encode(["ok" => true]);