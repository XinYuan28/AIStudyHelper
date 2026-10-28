<?php
/* Database connection for AI Study Note Helper (database: ai_study_note).
   XAMPP default is user "root" with no password. Change these if your teammate's setup is different.
   Everything is wrapped in "if not already defined" so it will not clash with auth.php. */

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'ai_study_note');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // rowCount() then means "rows that matched", even when the new value equals the old one
                    PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                ]
            );
        }
        return $pdo;
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