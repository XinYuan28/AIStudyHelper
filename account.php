<?php
require 'db.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

function password_ok(PDO $pdo, int $uid, string $pw): bool {
    $s = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $s->execute([$uid]);
    $row = $s->fetch();
    return $row && password_verify($pw, $row['password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $current = $_POST['current_password'] ?? '';

    if (!password_ok($pdo, $uid, $current)) {
        $err = 'Current password is incorrect.';
    } elseif ($action === 'profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = normalize_email($_POST['email'] ?? '');

        if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
            $err = 'Username must be 2-50 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $err = 'Enter a valid email address.';
        } else {
            $s = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $s->execute([$email, $uid]);
            if ($s->fetch()) {
                $err = 'That email is already used by another account.';
            } else {
                try {
                    $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?')
                        ->execute([$username, $email, $uid]);
                    $msg = 'Profile updated.';
                } catch (PDOException $e) {
                    $err = ($e->getCode() === '23000')
                        ? 'That email is already used by another account.'
                        : 'Something went wrong. Please try again.';
                }
            }
        }
    } elseif ($action === 'password') {
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $err = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $err = 'New passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            $msg = 'Password changed.';
        }
    } elseif ($action === 'delete') {
        if (($_POST['confirm_text'] ?? '') !== 'DELETE') {
            $err = 'Type DELETE to confirm.';
        } else {

            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);

            $_SESSION = [];
            session_destroy();
            header('Location: login.php?deleted=1');
            exit;
        }
    }
}

$s = $pdo->prepare('SELECT username, email, created_at FROM users WHERE id = ?');
$s->execute([$uid]);
$user = $s->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My account</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f4f5f7;display:flex;justify-content:center;padding:32px 16px}
  .wrap{width:100%;max-width:480px}
  .card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);margin-bottom:20px}
  label{display:block;margin-top:12px;font-size:14px}
  input{width:100%;padding:10px;margin-top:4px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
  button{margin-top:18px;padding:10px 18px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-size:14px;cursor:pointer}
  .danger{border:1px solid #f1b0b7}
  .danger button{background:#c62828}
  .err{background:#fde8e8;color:#b00020;padding:10px;border-radius:8px;margin-bottom:16px;font-size:14px}
  .ok{background:#e6f6ec;color:#1b6b35;padding:10px;border-radius:8px;margin-bottom:16px;font-size:14px}
  a{font-size:14px}
</style>
</head>
<body>
<div class="wrap">
  <p><a href="homepage.php">&larr; Homepage</a></p>
  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h3>Profile</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label>Username
        <input type="text" name="username" value="<?= h($user['username']) ?>" required maxlength="50">
      </label>
      <label>Email
        <input type="email" name="email" value="<?= h($user['email']) ?>" required maxlength="255">
      </label>
      <label>Current password (required)
        <input type="password" name="current_password" required>
      </label>
      <button type="submit">Save changes</button>
    </form>
  </div>

  <div class="card">
    <h3>Change password</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <label>Current password
        <input type="password" name="current_password" required>
      </label>
      <label>New password (min 8 characters)
        <input type="password" name="new_password" required>
      </label>
      <label>Confirm new password
        <input type="password" name="confirm_password" required>
      </label>
      <button type="submit">Change password</button>
    </form>
  </div>

  <div class="card danger">
    <h3>Delete account</h3>
    <p style="font-size:14px">This permanently deletes your account, all notes, folders and trash. It cannot be undone.</p>
    <form method="post" onsubmit="return confirm('Delete your account permanently?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <label>Current password
        <input type="password" name="current_password" required>
      </label>
      <label>Type DELETE to confirm
        <input type="text" name="confirm_text" required autocomplete="off">
      </label>
      <button type="submit">Delete my account</button>
    </form>
  </div>
</div>
</body>
</html>