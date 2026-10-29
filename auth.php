<?php
// Login helpers, now backed by the `users` table of the ai_study_note database.
// The function names and what they return are the same as the old JSON-file demo,
// so index.php and studyhub.php keep working. Accounts used to live in data/users.json;
// that file is no longer read.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/db.php';

const SHOW_DEMO_HINT = true;

const DEMO_EMAIL    = 'demo@aisnh.com';
const DEMO_PASSWORD = 'demo1234';
const DEMO_NAME     = 'Demo User';
const SEED_DEMO_USER = true;   // true: the demo account above is created in `users` the first time it logs in. Set false to switch it off.

const LOGIN_MAX_FAILS  = 5;    // wrong passwords allowed ...
const LOGIN_WINDOW_MIN = 15;   // ... within this many minutes, then login is paused for that email (table: login_attempts)

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
// so it is made from the part of the email before the @ (as before).
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
    // needs user_id, so anyone still holding an old (pre-database) session is asked to log in again
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

/* LOGIN ATTEMPTS (table login_attempts: email, ip, attempted_at) */
function login_blocked(string $email): bool
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND attempted_at > (NOW() - INTERVAL ' . (int) LOGIN_WINDOW_MIN . ' MINUTE)'
    );
    $st->execute([strtolower(trim($email))]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FAILS;
}

function record_failed_login(string $email): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')
        ->execute([strtolower(trim($email)), substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'); // keep the table small
}

function clear_login_attempts(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([strtolower(trim($email))]);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}