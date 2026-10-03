<?php
// JUST FOR DEMO
session_start();

const SHOW_DEMO_HINT = true;

const DEMO_EMAIL    = 'demo@aisnh.com';
const DEMO_PASSWORD = 'demo1234';
const DEMO_NAME     = 'Demo User';

define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');

function users_load(): array
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $ht = DATA_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    if (!file_exists(USERS_FILE)) {
        users_save([
            DEMO_EMAIL => [
                'name'     => DEMO_NAME,
                'hash'     => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
                'created'  => time(),
            ],
        ]);
    }
    $data = json_decode((string) file_get_contents(USERS_FILE), true);
    return is_array($data) ? $data : [];
}

function users_save(array $users): void
{
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
}

function find_user(string $email): ?array
{
    $users = users_load();
    return $users[$email] ?? null;
}

function create_user(string $email, string $password): array
{
    $users = users_load();
    $local = explode('@', $email)[0];
    $name  = ucfirst(preg_replace('/[._-]+/', ' ', $local));
    $users[$email] = [
        'name'    => $name,
        'hash'    => password_hash($password, PASSWORD_DEFAULT),
        'created' => time(),
    ];
    users_save($users);
    return $users[$email];
}

function login_user(string $email, array $user): void
{
    session_regenerate_id(true);
    $_SESSION['email'] = $email;
    $_SESSION['name']  = $user['name'];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['email']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
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