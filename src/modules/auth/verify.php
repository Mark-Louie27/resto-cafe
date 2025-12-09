<?php
include_once __DIR__ . '/../../includes/security.php';
include_once __DIR__ . '/../../includes/functions.php';


// Debug session and token state
error_log("=== Starting Verification ===");
error_log("Session ID: " . session_id());
error_log("Received Token: " . ($_GET['token'] ?? 'NULL'));
error_log("Received Login Token: " . ($_GET['login_token'] ?? 'NULL'));

// Validate token presence and format
if (
    !isset($_GET['token'], $_GET['login_token']) ||
    !is_string($_GET['token']) ||
    !is_string($_GET['login_token']) ||
    strlen($_GET['token']) !== 64 ||
    strlen($_GET['login_token']) < 32
) {
    error_log("Error: Invalid token format or missing tokens");
    set_flash_message('Invalid verification link. Please request a new one.', 'error');
    header('Location: login.php');
    exit();
}

// Sanitize tokens
$verification_token = trim($_GET['token']);
$login_token = trim($_GET['login_token']);

$conn = db_connect();
if (!$conn) {
    error_log("Error: Database connection failed");
    set_flash_message('System error. Please try again later.', 'error');
    header('Location: login.php');
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Find matching unverified user with role name only (no permissions)
    $stmt = $conn->prepare("SELECT u.user_id, u.username, u.login_token, 
                          u.verification_token_expiry, r.role_name
                          FROM users u
                          JOIN user_roles ur ON u.user_id = ur.user_id
                          JOIN roles r ON ur.role_id = r.role_id
                          WHERE u.verification_token = ? 
                          AND u.email_verified = 0
                          LIMIT 1 FOR UPDATE");

    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $verification_token);
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    // No user found with this token
    if ($result->num_rows === 0) {
        error_log("Error: No unverified user found with this verification token");
        throw new Exception("Invalid verification link or already verified");
    }

    $user = $result->fetch_assoc();
    error_log("Found user for verification: " . print_r($user, true));

    // 2. Check token expiry
    $current_time = time();
    $expiry_time = strtotime($user['verification_token_expiry']);

    if ($current_time > $expiry_time) {
        error_log("Error: Token expired (Current: $current_time, Expiry: $expiry_time)");
        throw new Exception("Verification link has expired. Please request a new one.");
    }

    // 3. Verify login token
    if (!password_verify($login_token, $user['login_token'])) {
        error_log("Error: Login token verification failed");
        log_security_event($user['user_id'], 'failed_verification', $_SERVER['REMOTE_ADDR']);
        throw new Exception("Invalid verification link. Please request a new one.");
    }

    // 4. Mark as verified and clear tokens
    $update = $conn->prepare("UPDATE users 
                            SET email_verified = 1,
                                verification_token = NULL,
                                verification_token_expiry = NULL,
                                login_token = NULL,
                                login_token_expiry = NULL,
                                last_verified_at = NOW(),
                                updated_at = NOW()
                            WHERE user_id = ?");
    if (!$update) {
        throw new Exception("Update prepare failed: " . $conn->error);
    }

    $update->bind_param("i", $user['user_id']);
    if (!$update->execute()) {
        throw new Exception("Update execute failed: " . $update->error);
    }

    // Commit transaction
    $conn->commit();
    error_log("Successfully verified user ID: " . $user['user_id']);

    // 5. Log the user in with role information
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email_verified'] = true;
    $_SESSION['role'] = $user['role_name'];

    // Generate new CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Set last login time
    $_SESSION['last_login'] = time();

    // Regenerate session ID
    session_regenerate_id(true);

    // Set secure session cookie
    setcookie(session_name(), session_id(), [
        'expires' => time() + 86400,
        'path' => '/',
        'domain' => 'yourdomain.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    error_log("User successfully logged in with role: " . $user['role_name']);
    log_security_event($user['user_id'], 'email_verified', $_SERVER['REMOTE_ADDR']);

    // Set appropriate flash message based on role
    $welcomeMessage = match ($user['role_name']) {
        'admin' => 'Welcome Administrator! Email verified successfully.',
        'manager' => 'Welcome Manager! Your account is now active.',
        'staff' => 'Welcome Staff Member! You can now access your dashboard.',
        default => 'Email verified successfully! You are now logged in.'
    };

    set_flash_message($welcomeMessage, 'success');

    // Redirect to appropriate dashboard based on role
    $redirectPath = match ($user['role_name']) {
        'admin' => '/admin/dashboard.php',
        'manager' => 'staff/dashboard.php',
        'staff' => 'staff/dashboard.php',
        default => '../../index.php'
    };

    header("Location: $redirectPath");
    exit();
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    error_log("Verification ERROR: " . $e->getMessage());
    set_flash_message($e->getMessage(), 'error');
    header('Location: login.php');
    exit();
}
