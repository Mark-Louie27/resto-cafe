<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

require_once __DIR__ . '/../../includes/functions.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        // Validate and sanitize email
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }

        $conn = db_connect();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        // Begin transaction
        $conn->begin_transaction();

        // Find unverified user
        $stmt = $conn->prepare("SELECT user_id, first_name, verification_token, verification_token_expiry 
                              FROM users 
                              WHERE email = ? 
                              AND email_verified = 0");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows !== 1) {
            throw new Exception('No unverified account found with that email or account is already verified');
        }

        $user = $result->fetch_assoc();

        // Generate new tokens
        $verification_token = bin2hex(random_bytes(32));
        $login_token = bin2hex(random_bytes(32));
        $login_token_hash = password_hash($login_token, PASSWORD_DEFAULT);
        $verification_expiry = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        $login_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        // Update user record with new tokens
        $update = $conn->prepare("UPDATE users 
                                SET verification_token = ?,
                                    verification_token_expiry = ?,
                                    login_token = ?,
                                    login_token_expiry = ?
                                WHERE user_id = ?");
        if (!$update) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $update->bind_param(
            "ssssi",
            $verification_token,
            $verification_expiry,
            $login_token_hash,
            $login_expiry,
            $user['user_id']
        );

        if (!$update->execute()) {
            throw new Exception("Update failed: " . $update->error);
        }

        // Commit transaction
        $conn->commit();

        // Send verification email
        if (!send_verification_email($email, $user['first_name'], $verification_token, $login_token)) {
            throw new Exception('Failed to send verification email');
        }

        set_flash_message('Verification email resent. Please check your inbox.', 'success');
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }

        error_log("Resend verification error: " . $e->getMessage());
        set_flash_message($e->getMessage(), 'error');
    }

    header('Location: login.php');
    exit();
}

// If not POST request, redirect
header('Location: ../index.php');
exit();
