<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';

function recovery_base_url(): string
{
    $local = is_file(__DIR__ . '/telegram.local.php') ? require __DIR__ . '/telegram.local.php' : [];
    $url = rtrim(getenv('APP_BASE_URL') ?: ($local['app_base_url'] ?? 'http://localhost/AIStudyHelper'), '/');
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
        || !(($parts['scheme'] ?? '') === 'https' || (($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1'], true)))) {
        throw new RuntimeException('Configure APP_BASE_URL with the HTTPS website address.');
    }
    return $url;
}

// Called only after the worker has authenticated the previously linked private chat.
function issue_telegram_password_reset(int $uid): string
{
    $base = recovery_base_url();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ? FOR UPDATE');
        $st->execute([$uid]);
        $password = $st->fetchColumn();
        if (!$password) throw new RuntimeException('Account unavailable.');
        $st = $pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE user_id = ? AND created_at_utc > UTC_TIMESTAMP() - INTERVAL 2 MINUTE');
        $st->execute([$uid]);
        if ($st->fetchColumn()) {
            $pdo->commit();
            return 'Please wait two minutes before requesting another password reset link.';
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('REPLACE INTO password_reset_tokens (user_id, token_hash, password_hash_at_issue, created_at_utc, expires_at_utc)
            VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 15 MINUTE)')
            ->execute([$uid, hash('sha256', $token), $password]);
        $pdo->commit();
        return "Reset your Study Helper password (expires in 15 minutes, works once):\n"
            . $base . '/reset_password.php#' . $token
            . "\nIf you did not request this, ignore it. Never forward this link."
            . (str_starts_with($base, 'http://localhost') ? "\nLocal development: open this link on the computer running XAMPP." : '');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function reset_password_with_token(string $token, string $password): void
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new DomainException('This link is invalid or expired. Request a new link in Telegram.');
    if (mb_strlen($password) < 8 || strlen($password) > 72) throw new DomainException('Use at least 8 characters and no more than 72 bytes.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Use the same user-first lock order as issuance, including concurrent requests.
        $st = $pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash = ?');
        $st->execute([hash('sha256', $token)]);
        $uid = $st->fetchColumn();
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ? FOR UPDATE');
        $st->execute([$uid ?: 0]);
        $current = $st->fetchColumn();
        $st = $pdo->prepare('SELECT password_hash_at_issue FROM password_reset_tokens WHERE user_id = ? AND token_hash = ? AND expires_at_utc > UTC_TIMESTAMP() FOR UPDATE');
        $st->execute([$uid ?: 0, hash('sha256', $token)]);
        $issued = $st->fetchColumn();
        if (!$current || !$issued || !hash_equals($current, $issued)) throw new DomainException('This link is invalid or expired. Request a new link in Telegram.');
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM telegram_link_tokens WHERE user_id = ?')->execute([$uid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
