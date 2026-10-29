<?php
require 'db.php';
require_login();

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$username = $stmt->fetchColumn();

if ($username === false) {
    header('Location: logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard</title>
</head>
<body style="font-family: sans-serif; max-width: 480px; margin: 60px auto;">
<h2>Welcome, <?= h($username) ?>!</h2>

<p>
  <a href="upload.php">Upload study material</a> |
  <a href="play_menu.php">🎮 Play games</a> |
  <a href="account.php">My account</a> |
  <a href="logout.php">Log out</a>
</p>
</body>
</html>