<?php
require 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$quiz = $_SESSION['quiz'] ?? null;
if (!$quiz) { header('Location: upload.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quiz</title>
<style>
  body { font-family: sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; }
  fieldset { margin: 14px 0; } button { padding: 10px 16px; cursor: pointer; }
</style></head><body>
<p><a href="homepage.php">&larr; Homepage</a></p>
<h2>Quiz: <?= e($quiz['topic']) ?></h2>
<form method="post" action="save_quiz.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <?php foreach ($quiz['questions'] as $i => $q): ?>
    <fieldset>
      <legend><?= ($i + 1) . '. ' . e($q['question']) ?></legend>
      <?php foreach ($q['options'] as $j => $opt): ?>
        <label><input type="radio" name="a[<?= $i ?>]" value="<?= $j ?>" required> <?= e($opt) ?></label><br>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <button type="submit">Submit answers</button>
</form>
</body></html>