<?php
// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

require_once __DIR__ . '/../../includes/functions.php';

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie(
        'remember_token',
        '',
        time() - 42000,
        '/',
        '',
        true,
        true
    );
}

set_flash_message('You have been logged out successfully.', 'success');
header('Location: /../../index.php');
exit();
