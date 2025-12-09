<?php
require_once __DIR__ . '/../../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    set_flash_message('Invalid authentication link', 'error');
    header('Location: login.php');
    exit();
}

$conn = db_connect();

// Check token validity
$stmt = $conn->prepare("SELECT t.*, u.user_id, u.username, u.email_verified 
                       FROM verification_tokens t
                       LEFT JOIN users u ON t.email = u.email
                       WHERE t.token = ? 
                       AND t.expiry > UNIX_TIMESTAMP() 
                       AND t.used = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Invalid or expired link', 'error');
    header('Location: login.php');
    exit();
}

$token_data = $result->fetch_assoc();

// Mark token as used
$stmt = $conn->prepare("UPDATE verification_tokens SET used = 1 WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();

// Handle verification tokens
if ($token_data['purpose'] === 'verification') {
    // Mark email as verified
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
    $stmt->bind_param("s", $token_data['email']);
    $stmt->execute();

    set_flash_message('Email verified successfully!', 'success');
}

// Log the user in
$_SESSION['user_id'] = $token_data['user_id'];
$_SESSION['username'] = $token_data['username'];
$_SESSION['email'] = $token_data['email'];

// Set roles (from your existing login code)
// ... [your role setting code] ...

// Redirect based on user role
if (isset($_SESSION['is_admin'])) {
    header('Location: /admin/dashboard.php');
} elseif (isset($_SESSION['is_staff'])) {
    header('Location: /staff/dashboard.php');
} else {
    header('Location: /index.php');
}
exit();
