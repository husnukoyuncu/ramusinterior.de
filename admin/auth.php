<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_config(): array {
    return require __DIR__ . '/config.php';
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

/**
 * Absolute /admin/... URL for a given file, so redirects resolve correctly
 * regardless of whether the browser requested /admin or /admin/.
 */
function admin_url(string $file): string {
    return '/admin/' . $file;
}

/** Call at the top of any page/endpoint that must be behind login. */
function require_login(): void {
    if (is_logged_in()) return;

    $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'api.php');
    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Oturum süresi doldu, lütfen tekrar giriş yapın.']);
        exit;
    }

    header('Location: ' . admin_url('login.php'));
    exit;
}
