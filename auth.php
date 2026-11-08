<?php
// Login helpers, backed by the `users` table of the ai_study_note database.
// db.php starts the session (with safe cookie settings) and provides db(), h(),
// csrf_token(), csrf_field(), csrf_check() and require_login().
require_once __DIR__ . '/db.php';

const DEMO_EMAIL    = 'demo@aisnh.com';
const DEMO_PASSWORD = 'demo1234';
const DEMO_NAME     = 'Demo User';
// The demo account is only created when the site is opened on your own computer,
// so it can never exist on a deployed server.
define('SEED_DEMO_USER', in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));

const LOGIN_MAX_FAILS    = 5;    // wrong passwords allowed per email
const LOGIN_MAX_FAILS_IP = 20;   // or per IP address
const LOGIN_WINDOW_MIN   = 15;   // within this many minutes, then login is paused (table: login_attempts)

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: '0.0.0.0';
}

// users row -> the array the rest of the site expects
function user_from_row(array $r): array
{
    return [
        'id'    => (int) $r['id'],
        'name'  => $r['username'],
        'email' => $r['email'],
        'hash'  => $r['password'],
    ];
}

function find_user(string $email): ?array
{
    $email = strtolower(trim($email));
    $st = db()->prepare('SELECT id, username, email, password FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row) {
        return user_from_row($row);
    }
    if (SEED_DEMO_USER && $email === strtolower(DEMO_EMAIL)) {
        try {
            return create_user(DEMO_EMAIL, DEMO_PASSWORD, DEMO_NAME);
        } catch (PDOException $e) {
            // two requests created it at the same moment: just read it back
            $st->execute([$email]);
            $row = $st->fetch();
            return $row ? user_from_row($row) : null;
        }
    }
    return null;
}

// users.username is required (max 50 characters); the sign-up form has no username box,
// so it is made from the part of the email before the @.
function create_user(string $email, string $password, ?string $name = null): array
{
    $email = strtolower(trim($email));
    if ($name === null) {
        $local = explode('@', $email)[0];
        $name  = ucfirst(preg_replace('/[._-]+/', ' ', $local));
    }
    $name = mb_substr($name, 0, 50);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo = db();
    $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)')->execute([$name, $email, $hash]);

    return ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'hash' => $hash];
}

function login_user(string $email, array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];   // users.id: notes and quiz results are saved against this
    $_SESSION['email']   = $email;
    $_SESSION['name']    = $user['name'];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/* LOGIN ATTEMPTS (table login_attempts: email, ip, attempted_at) */
function login_blocked(string $email): bool
{
    $pdo    = db();
    $window = '(NOW() - INTERVAL ' . (int) LOGIN_WINDOW_MIN . ' MINUTE)';

    $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > $window");
    $st->execute([strtolower(trim($email))]);
    if ((int) $st->fetchColumn() >= LOGIN_MAX_FAILS) {
        return true;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > $window");
    $st->execute([client_ip()]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FAILS_IP;
}

function record_failed_login(string $email): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')
        ->execute([strtolower(trim($email)), client_ip()]);
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'); // keep the table small
}

function clear_login_attempts(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([strtolower(trim($email))]);
}

function csrf_ok(): bool
{
    return isset($_SESSION['csrf']) && is_string($_POST['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}