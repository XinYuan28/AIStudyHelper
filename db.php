<?php
/* Database connection and shared request helpers. */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'ai_study_note');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'ai_study_note');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            if (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
                $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
            }
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                $options
            );
        }
        return $pdo;
    }
}

// Legacy uploaded pages use $pdo directly; keep one lazy connection for both APIs.
if (!isset($pdo)) {
    $pdo = db();
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return h($value);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(): void
    {
        if (!isset($_POST['csrf'], $_SESSION['csrf'])
            || !hash_equals((string) $_SESSION['csrf'], (string) $_POST['csrf'])) {
            http_response_code(400);
            exit('Invalid request.');
        }
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
    }
}

if (!function_exists('normalize_email')) {
    function normalize_email($email): string
    {
        return strtolower(trim((string) $email));
    }
}

/* The id (users.id) of whoever is logged in.
   notes.user_id and quiz_results.user_id need this number, but the session only has to store ONE of
   user_id / uid / id, or the email. Once auth.php is connected to the users table, keep just the
   branch that matches what login_user() saves. */
if (!function_exists('current_user_id')) {
    function current_user_id(): int
    {
        foreach (['user_id', 'uid', 'id'] as $key) {
            if (!empty($_SESSION[$key])) {
                return (int) $_SESSION[$key];
            }
        }
        if (!empty($_SESSION['email'])) {
            $st = db()->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([strtolower((string) $_SESSION['email'])]);
            $id = $st->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }
        return 0;
    }
}