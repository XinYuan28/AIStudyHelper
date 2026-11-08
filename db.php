<?php
/* Database connection and shared request helpers. */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // only sent over HTTPS when the site is on HTTPS
        'httponly' => true,     // JavaScript can not read the session cookie
        'samesite' => 'Lax',    // not sent on cross-site POSTs
    ]);
    session_start();
}

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'ai_study_note');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

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
                'mysql:host=' . DB_HOST . ';port=' . (getenv('AI_STUDY_DB_PORT') ?: '3306') . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                $options
            );
        }
        return $pdo;
    }
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
        if (!isset($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null)
            || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
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
   notes.user_id and quiz_results.user_id need this number, but the session only has to store one of
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
function php_size_bytes(string $value): int
{
    return match (strtolower(substr($value, -1))) {
        'g' => (int) $value * 1024 ** 3,
        'm' => (int) $value * 1024 ** 2,
        'k' => (int) $value * 1024,
        default => (int) $value,
    };
}

function upload_file_limit(): int
{
    $configured = php_size_bytes((string) ini_get('upload_max_filesize'));
    return $configured > 0 ? min($configured, 100 * 1024 ** 2) : 100 * 1024 ** 2;
}
