<?php
require 'db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit;
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $email    = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
        $errors[] = 'Username must be 2-50 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
            );
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $errors[] = 'An account with this email already exists.';
            } else {
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f4f5f7;display:flex;justify-content:center;padding:40px 16px}
  .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);width:100%;max-width:380px}
  label{display:block;margin-top:14px;font-size:14px}
  input{width:100%;padding:10px;margin-top:4px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
  button{margin-top:20px;width:100%;padding:11px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-size:15px;cursor:pointer}
  .err{background:#fde8e8;color:#b00020;padding:10px;border-radius:8px;margin-top:12px;font-size:14px}
  p{font-size:14px;text-align:center}
</style>
</head>
<body>
<div class="card">
  <h2>Create account</h2>
  <?php foreach ($errors as $e): ?>
    <div class="err"><?= h($e) ?></div>
  <?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" value="<?= h($username) ?>" required maxlength="50">
    </label>
    <label>Email
      <input type="email" name="email" value="<?= h($email) ?>" required maxlength="255">
    </label>
    <label>Password (min 8 characters)
      <input type="password" name="password" required>
    </label>
    <label>Confirm password
      <input type="password" name="confirm" required>
    </label>
    <button type="submit">Register</button>
  </form>
  <p>Already have an account? <a href="login.php">Log in</a></p>
</div>
</body>
</html>