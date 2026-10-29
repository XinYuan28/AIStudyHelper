<?php
require 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: quiz.php'); exit; }
csrf_check();

$quiz = $_SESSION['quiz'] ?? null;
if (!$quiz) { header('Location: upload.php'); exit; }

$answers = $_POST['a'] ?? [];
$total   = count($quiz['questions']);
$correct = 0;
$weak    = [];

foreach ($quiz['questions'] as $i => $q) {
    if (isset($answers[$i]) && (int)$answers[$i] === $q['answer']) {
        $correct++;
    } else {
        $weak[$q['topic']] = true;
    }
}
$weakAreas = implode(', ', array_keys($weak));

$stmt = $pdo->prepare(
    'INSERT INTO quiz_results (user_id, note_id, topic, total_questions, correct_answers, weak_areas)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$_SESSION['user_id'], $quiz['note_id'], $quiz['topic'], $total, $correct, $weakAreas]);

unset($_SESSION['quiz']);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Quiz result</title>
<style>body { font-family: sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; }</style>
</head><body>
<h2>You scored <?= $correct ?> / <?= $total ?></h2>
<?php if ($weakAreas): ?>
  <p>Topics to review: <?= e($weakAreas) ?></p>
<?php else: ?>
  <p>Perfect score, no weak topics.</p>
<?php endif; ?>
<p><a href="upload.php">Upload more material</a> &middot; <a href="homepage.php">Homepage</a></p>
</body></html>