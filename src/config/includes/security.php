<?php
// Enable error reporting in development, disable in production
define('APP_ENV', 'development'); // Change to 'production' in live environment

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Start secure session with strict settings
// Enhanced session handling with more secure defaults
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure'   => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'gc_maxlifetime'  => 86400  // 1 day
    ]);
}

require_once __DIR__ . '/functions.php';

/**
 * Rate limiting function
 */
function rate_limit($key, $max_attempts, $time_window)
{
    $rate_limit_dir = __DIR__ . '/../../data/rate_limits';
    if (!file_exists($rate_limit_dir)) {
        mkdir($rate_limit_dir, 0700, true);
    }

    $identifier = $key . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file_path = $rate_limit_dir . '/' . md5($identifier) . '.ratelimit';
    $now = time();

    if (file_exists($file_path)) {
        $data = json_decode(file_get_contents($file_path), true);
        if ($data['expiry'] > $now && $data['attempts'] >= $max_attempts) {
            throw new Exception("Too many attempts. Please try again later.");
        }

        if ($data['expiry'] <= $now) {
            $data = ['attempts' => 1, 'expiry' => $now + $time_window];
        } else {
            $data['attempts']++;
        }
    } else {
        $data = ['attempts' => 1, 'expiry' => $now + $time_window];
    }

    file_put_contents($file_path, json_encode($data), LOCK_EX);
}

/**
 * Simplified security logging
 */
function log_security_event($user_id, $event_type, $ip_address)
{
    $conn = db_connect();
    if (!$conn) return;

    $stmt = $conn->prepare("INSERT INTO security_logs 
                          (user_id, event_type, ip_address, user_agent, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");

    if ($stmt) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param("isss", $user_id, $event_type, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}


// Implement rate limiting
try {
    rate_limit('verify_attempt', 5, 60);
} catch (Exception $e) {
    set_flash_message($e->getMessage(), 'error');
    header('Location: login.php');
    exit();
}