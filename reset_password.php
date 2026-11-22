<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/password_recovery.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$error = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $error = 'Your session expired. Refresh and try again.';
    elseif (!is_string($_POST['password'] ?? null) || !is_string($_POST['confirm_password'] ?? null) || $_POST['password'] !== $_POST['confirm_password']) $error = 'Passwords do not match.';
    else {
        try {
            reset_password_with_token(is_string($_POST['token'] ?? null) ? $_POST['token'] : '', $_POST['password']);
            $_SESSION = []; session_regenerate_id(true); $done = true;
        } catch (DomainException $e) { $error = $e->getMessage(); }
        catch (Throwable $e) { $error = 'Could not reset the password. Please try again later.'; error_log('[password_reset] Request failed.'); }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password recovery · Study Helper</title><link rel="stylesheet" href="studyhub.css"><link rel="stylesheet" href="recovery.css"><script src="reset_password.js" defer></script></head>
<body><main class="recovery card"><h1><?= $done ? 'Password updated' : 'Reset password' ?></h1>
<?php if ($done): ?><p>Your password has been changed. Sign in again on your devices.</p><a href="index.php#signup">Back to login</a>
<?php else: ?>
<p>Already linked Telegram? Send <strong>/resetpassword</strong> to your bot, then open the link it sends. Links expire after 15 minutes.</p>
<?php $bot = telegram_settings()['username']; if (preg_match('/^[a-zA-Z0-9_]+$/D', $bot)): ?><p><a href="https://t.me/<?= h($bot) ?>" rel="noopener noreferrer">Open @<?= h($bot) ?> in Telegram</a></p><?php endif; ?>
<p>You must have connected Telegram before losing access to your account.</p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" action="reset_password.php" id="resetForm" <?= $error && preg_match('/^[a-f0-9]{64}$/D', (string) ($_POST['token'] ?? '')) ? '' : 'hidden' ?>><?= csrf_field() ?><input type="hidden" name="token" id="resetToken" value="<?= h(is_string($_POST['token'] ?? null) ? $_POST['token'] : '') ?>">
<label for="newPassword">New password</label><input id="newPassword" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
<label for="confirmPassword">Confirm password</label><input id="confirmPassword" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
<button class="btn" type="submit">Reset password</button></form>
<noscript>Enable JavaScript to open your Telegram reset link.</noscript>
<a href="index.php#signup">Back to login</a>
<?php endif; ?></main></body></html>
