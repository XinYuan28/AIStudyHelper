<?php
require 'db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit;
}

$error = '';
$info  = '';
if (isset($_GET['registered'])) $info = 'Account created. Please log in.';
if (isset($_GET['deleted']))    $info = 'Your account has been deleted.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$email]);
    $emailFails = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);
    $ipFails = (int)$stmt->fetchColumn();

    if ($emailFails >= 5 || $ipFails >= 20) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            header('Location: homepage.php');
            exit;
        }

        $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')
            ->execute([$email, $ip]);
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f4f5f7;display:flex;justify-content:center;padding:40px 16px}
  .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);width:100%;max-width:380px}
  label{display:block;margin-top:14px;font-size:14px}
  input{width:100%;padding:10px;margin-top:4px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
  button{margin-top:20px;width:100%;padding:11px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-size:15px;cursor:pointer}
  .err{background:#fde8e8;color:#b00020;padding:10px;border-radius:8px;margin-top:12px;font-size:14px}
  .ok{background:#e6f6ec;color:#1b6b35;padding:10px;border-radius:8px;margin-top:12px;font-size:14px}
  p{font-size:14px;text-align:center}
</style>
</head>
<body>
<div class="card">
  <h2>Log in</h2>
  <?php if ($info): ?><div class="ok"><?= h($info) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" required>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit">Log in</button>
  </form>
  <p>No account? <a href="register.php">Register</a></p>
</div>
</body>
</html>