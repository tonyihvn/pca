<?php
declare(strict_types=1);

/**
 * Session + admin auth helpers.
 */

function start_admin_session(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $s = $config['session'];
    session_name($s['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool)$s['secure'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Idle timeout
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > (int)$s['lifetime']) {
        admin_logout();
    }
    $_SESSION['last_activity'] = $now;
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_user']);
}

function admin_require_login(string $loginUrl = 'login.php'): void
{
    if (!admin_is_logged_in()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

function admin_login_attempt(array $config, string $username, string $password): bool
{
    $admin    = $config['admin'];
    $userOk   = hash_equals((string)$admin['username'], $username);
    $passOk   = password_verify($password, (string)$admin['password_hash']);
    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_user']     = $admin['username'];
        $_SESSION['login_time']     = time();
        $_SESSION['last_activity']  = time();
        $_SESSION['csrf']           = bin2hex(random_bytes(32));
        return true;
    }
    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
