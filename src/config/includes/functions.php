<?php
include_once __DIR__ . '/../config/database.php';

require __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libs/PHPMailer/src/SMTP.php';
require __DIR__ . '/../libs/PHPMailer/src/Exception.php';


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

// Check if the system is down
function is_system_down()
{
    return file_exists('system_down.txt');
}

// Set the system to down state
function set_system_down()
{
    if (!file_exists('system_down.txt')) {
        file_put_contents('system_down.txt', 'System is down for maintenance as of ' . date('Y-m-d H:i:s'));
        error_log("System set to down state");
    }
}

// Set the system to up state
function set_system_up()
{
    if (file_exists('system_down.txt')) {
        unlink('system_down.txt');
        error_log("System set to up state");
    }
}

// Function to export table data to CSV
function export_table_to_csv($table_name, $columns, $backup_dir = 'system_backup')
{
    try {
        $conn = db_connect();

        // Create backup directory if it doesn't exist
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Generate CSV filename with timestamp
        $timestamp = date('Ymd_His');
        $filename = "$backup_dir/{$table_name}_backup_{$timestamp}.csv";

        // Open file for writing
        $file = fopen($filename, 'w');
        if ($file === false) {
            error_log("Failed to open file for writing: $filename");
            return false;
        }

        // Write CSV header
        fputcsv($file, $columns);

        // Fetch and write data
        $query = "SELECT " . implode(',', $columns) . " FROM $table_name";
        $result = $conn->query($query);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($file, $row);
            }
        } else {
            error_log("Failed to query table $table_name: " . $conn->error);
            fclose($file);
            return false;
        }

        fclose($file);
        return $filename;
    } catch (Exception $e) {
        error_log("Error exporting table $table_name to CSV: " . $e->getMessage());
        return false;
    }
}

// Function to notify all users about system downtime
function notify_users_system_down()
{
    $conn = db_connect();
    $mail_config = include __DIR__ . '/../config/email.php';

    // Fetch all users
    $users = fetch_all("SELECT email, first_name FROM users WHERE email_verified = 1");
    if (empty($users)) {
        error_log("No verified users found to notify about system downtime");
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $mail_config['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_config['smtp']['username'];
        $mail->Password   = $mail_config['smtp']['password'];
        $mail->SMTPSecure = $mail_config['smtp']['encryption'];
        $mail->Port       = $mail_config['smtp']['port'];
        $mail->Timeout    = 10;

        $mail->setFrom($mail_config['smtp']['from'], $mail_config['smtp']['from_name']);

        $success_count = 0;
        foreach ($users as $user) {
            $mail->clearAddresses();
            $mail->addAddress($user['email'], $user['first_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Casa Baraka System Downtime Notification';
            $mail->Body = "
                <h1 style='color: #D97706;'>System Downtime Alert</h1>
                <p>Dear {$user['first_name']},</p>
                <p>We wanted to inform you that Casa Baraka's system is currently down for maintenance. You can still browse the site, but transactions such as placing orders or making reservations are disabled.</p>
                <p>We apologize for any inconvenience this may cause. Thank you for your patience and understanding.</p>
                <p>Best regards,<br>The Casa Baraka Team</p>
            ";
            $mail->AltBody = "System Downtime Alert\n\nDear {$user['first_name']},\n\nCasa Baraka's system is currently down for maintenance. You can still browse the site, but transactions are disabled.\n\nBest regards,\nThe Casa Baraka Team";

            if ($mail->send()) {
                $success_count++;
                error_log("System down notification sent to: {$user['email']}");
            } else {
                error_log("Failed to send system down notification to: {$user['email']} - " . $mail->ErrorInfo);
            }
        }

        error_log("System down notifications sent to $success_count out of " . count($users) . " users");
        return true;
    } catch (Exception $e) {
        error_log("Error sending system down notifications: " . $e->getMessage());
        return false;
    }
}

// Main function to handle system downtime
function system_down_handler()
{
    // Set the system down flag
    set_system_down();

    $backup_dir = 'system_backup';
    $backup_files = [];

    try {
        $conn = db_connect();

        // Get all tables in the database
        $tables_result = $conn->query("SHOW TABLES");
        if (!$tables_result) {
            error_log("Failed to fetch tables: " . $conn->error);
            return [
                'backup_files' => [],
                'notification_sent' => false
            ];
        }

        // Fetch table names
        $tables = [];
        while ($row = $tables_result->fetch_array()) {
            $tables[] = $row[0];
        }

        // For each table, get its columns and export to CSV
        foreach ($tables as $table) {
            // Get column names for the table
            $columns_result = $conn->query("SHOW COLUMNS FROM `$table`");
            if (!$columns_result) {
                error_log("Failed to fetch columns for table $table: " . $conn->error);
                continue;
            }

            $columns = [];
            while ($column = $columns_result->fetch_assoc()) {
                $columns[] = $column['Field'];
            }

            // Export the table to CSV
            $result = export_table_to_csv($table, $columns, $backup_dir);
            if ($result) {
                $backup_files[$table] = $result;
                error_log("Successfully backed up $table to $result");
            } else {
                error_log("Failed to backup $table");
            }
        }
    } catch (Exception $e) {
        error_log("Error in system_down_handler: " . $e->getMessage());
    }

    // Notify users
    $notification_result = notify_users_system_down();

    // Log the system down event
    $backup_status = !empty($backup_files) ? 'Success' : 'Failed';
    $notification_status = $notification_result ? 'Success' : 'Failed';
    error_log("System down handler completed. Backup: $backup_status, Notification: $notification_status");

    return [
        'backup_files' => $backup_files,
        'notification_sent' => $notification_result
    ];
}

function send_verification_email($email, $name, $verification_token, $login_token)
{
    error_log("Attempting to send verification email to: $email");
    $mail_config = include __DIR__ . '/../config/email.php';

    if (empty($mail_config['smtp']['host'])) {
        error_log("SMTP Configuration Error: Host not configured");
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Enable verbose debugging
        $mail->SMTPDebug = 4; // Maximum verbosity
        $debugOutput = "";
        $mail->Debugoutput = function ($str, $level) use (&$debugOutput) {
            error_log("PHPMailer: $str");
            $debugOutput .= "$level: $str\n";
        };

        // Server settings
        error_log("Configuring SMTP with: " . print_r($mail_config['smtp'], true));
        $mail->isSMTP();
        $mail->Host       = $mail_config['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_config['smtp']['username'];
        $mail->Password   = $mail_config['smtp']['password'];
        $mail->SMTPSecure = $mail_config['smtp']['encryption'];
        $mail->Port       = $mail_config['smtp']['port'];
        $mail->Timeout    = 10; // 10 seconds timeout

        // Recipients
        $mail->setFrom($mail_config['smtp']['from'], $mail_config['smtp']['from_name']);
        $mail->addAddress($email, $name);
        error_log("Recipient set: $email");

        // Generate login token for auto-login after verification
        $login_token = bin2hex(random_bytes(32));
        $login_token_hash = password_hash($login_token, PASSWORD_DEFAULT);
        $login_token_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry

        // Store login token in database
        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE users SET login_token = ?, login_token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $login_token_hash, $login_token_expiry, $email);
        $stmt->execute();

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your Casa Baraka account';

        $verification_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" .
            $_SERVER['HTTP_HOST'] .
            "/modules/auth/verify.php?" . http_build_query([
                'token' => $verification_token,
                'login_token' => $login_token
            ]);
        $mail->Body = "
            <h1 style='color: #D97706;'>Welcome to Casa Baraka, $name!</h1>
            <p>Please click the button below to verify your email address and automatically log in:</p>
            
            <div style='margin: 25px 0; text-align: center;'>
                <a href='$verification_url' style='
                    background-color: #D97706;
                    color: white;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-block;
                '>Verify & Log In</a>
            </div>
            
            <p style='font-size: 0.9em; color: #666;'>
                If the button doesn't work, copy this link to your browser:<br>
                <code style='background: #f0f0f0; padding: 2px 4px; border-radius: 3px; word-break: break-all;'>$verification_url</code>
            </p>
            
            <p style='font-size: 0.9em; color: #666;'>
                <strong>Note:</strong> This link will expire in 5 minutes for security reasons.
            </p>
        ";

        $mail->AltBody = "Welcome to Casa Baraka!\n\nHello $name,\n\nPlease click the following link to verify your email and log in:\n$verification_url\n\nThis link expires in 5 minutes.";

        error_log("Attempting to send email...");
        if (!$mail->send()) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            error_log("Full debug output:\n$debugOutput");
            return false;
        }

        error_log("Email sent successfully to $email");
        return true;
    } catch (Exception $e) {
        error_log("Exception while sending email: " . $e->getMessage());
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        error_log("Full debug output:\n$debugOutput");
        return false;
    }
}

function resend_verification_email($email)
{
    $conn = db_connect();

    // Check if user exists and isn't already verified
    $stmt = $conn->prepare("SELECT user_id, first_name, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        return ['success' => false, 'message' => 'Email not found'];
    }

    $user = $result->fetch_assoc();

    if ($user['email_verified']) {
        return ['success' => false, 'message' => 'Email already verified'];
    }

    // Generate new tokens
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 86400); // 24 hours
    $csrf_token = bin2hex(random_bytes(32));

    // Update tokens in database
    $stmt = $conn->prepare("UPDATE users SET 
                          email_verification_token = ?,
                          email_verification_expiry = ?,
                          email_verification_csrf = ?
                          WHERE user_id = ?");
    $stmt->bind_param("sssi", $token, $expiry, $csrf_token, $user['user_id']);

    if (!$stmt->execute()) {
        error_log("Failed to update verification tokens for $email");
        return ['success' => false, 'message' => 'Failed to resend verification'];
    }

    // Send new verification email
    $verification_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" .
        $_SERVER['HTTP_HOST'] .
        "/../modules/auth/verify.php";

    $subject = "Verify Your Casa Baraka Account";
    $message = '
    <html>
    <body style="font-family: Arial, sans-serif;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
            <h1 style="color: #d97706;">Welcome to Casa Baraka, ' . htmlspecialchars($user['first_name']) . '!</h1>
            <p>Please verify your email address to complete your registration:</p>
            
            <form action="' . $verification_url . '" method="POST" style="margin: 20px 0;">
                <input type="hidden" name="token" value="' . $token . '">
                <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                <input type="hidden" name="email" value="' . htmlspecialchars($email) . '">
                <button type="submit" style="background-color: #d97706; color: white; 
                        padding: 12px 24px; border: none; border-radius: 4px; 
                        cursor: pointer; font-size: 16px;">
                    Verify My Email Address
                </button>
            </form>
            
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                <strong>Note:</strong> This verification link will expire in 24 hours.
                If you didn\'t create this account, please ignore this email.
            </p>
        </div>
    </body>
    </html>
    ';

    if (!send_verification_email($email, htmlspecialchars($user['first_name']), $token, $csrf_token)) {
        return ['success' => false, 'message' => 'Failed to send verification email'];
    }

    return ['success' => true, 'message' => 'Verification email resent'];
}

function log_email_verification($email, $token, $status)
{
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO email_logs (email, token, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $token, $status);
    $stmt->execute();
}

function is_verified_user($user_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        return (bool)$user['email_verified'];
    }
    return false;
}

// Usage in your auth check:
if (is_logged_in() && !is_verified_user($_SESSION['user_id'])) {
    // Force verification
    header('Location: /../../modules/auth/verify.php');
    exit();
}

// Check if user is logged in
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Require authentication - redirect to login if not logged in
function require_auth()
{
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        set_flash_message('Please login to access this page', 'error');
        header('Location: login.php');
        exit();
    }
}

function get_csrf_token()
{
    return generate_csrf_token();
}


function has_flash_message()
{
    return isset($_SESSION['flash_message']);
}

function get_flash_message()
{
    if (has_flash_message()) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}


// Check if user has admin role
function is_admin()
{
    if (!is_logged_in()) return false;

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_roles ur 
                          JOIN roles r ON ur.role_id = r.role_id 
                          WHERE ur.user_id = ? AND r.role_name = 'admin'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0] > 0;
}

// Require admin privileges
function require_admin()
{
    require_auth();
    if (!is_admin()) {
        set_flash_message('You do not have permission to access this page', 'error');
        header('Location: index.php');
        exit();
    }
}


function is_manager()
{
    if (!is_logged_in()) return false;

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_roles ur 
                          JOIN roles r ON ur.role_id = r.role_id 
                          WHERE ur.user_id = ? AND r.role_name = 'manager'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0] > 0;
}

function require_manager()
{
    require_auth();
    if (!is_manager()) {
        set_flash_message('You do not have permission to access this page', 'error');
        header('Location: index.php');
        exit();
    }
}

// Check if user has staff role
function is_staff()
{
    if (!is_logged_in()) return false;

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_roles ur 
                          JOIN roles r ON ur.role_id = r.role_id 
                          WHERE ur.user_id = ? AND r.role_name = 'staff'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0] > 0;
}

// Require staff privileges
function require_staff()
{
    require_auth();
    if (!is_staff()) {
        set_flash_message('You do not have permission to access this page', 'error');
        header('Location: index.php');
        exit();
    }
}


function has_role($role_name)
{
    if (!is_logged_in() || !isset($_SESSION['user_id'])) {
        return false;
    }

    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ? AND r.role_name = ?
    ");
    $stmt->bind_param("is", $_SESSION['user_id'], $role_name);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_row();

    return $result[0] > 0;
}

/**
 * Get status badge class for styling
 */
function get_status_badge_class($status)
{
    switch (strtolower($status)) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'processing':
            return 'bg-blue-100 text-blue-800';
        case 'ready':
            return 'bg-green-100 text-green-800';
        case 'completed':
            return 'bg-purple-100 text-purple-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Redirect if not logged in
 */
function require_login()
{
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /../../index.php');
        exit();
    }
}

/**
 * Redirect if doesn't have required role
 */
function require_role($role_name)
{
    require_login();

    if (!has_role($role_name)) {
        header('HTTP/1.0 403 Forbidden');
        echo "You don't have permission to access this page.";
        exit();
    }
}

// Display flash messages with Tailwind CSS classes
function display_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];

        // Map message types to Tailwind classes
        $classes = [
            'success' => 'bg-green-100 border-green-400 text-green-700',
            'error' => 'bg-red-100 border-red-400 text-red-700',
            'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
            'info' => 'bg-blue-100 border-blue-400 text-blue-700'
        ];

        $class = $classes[$type] ?? $classes['info'];

        echo "<div class='$class border px-4 py-3 rounded relative mb-4' role='alert'>
                <span class='block sm:inline'>$message</span>
              </div>";
        unset($_SESSION['flash_message']);
    }
}

// Set flash message
function set_flash_message($message, $type = 'success')
{
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Generate CSRF token
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function register_user($username, $email, $password, $first_name, $last_name, $phone = null, $role = 'customer')
{
    $conn = db_connect();
    $conn->begin_transaction();

    try {
        // Check if username or email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Username or email already exists");
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Create user
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, email, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password_hash, $email, $first_name, $last_name, $phone);
        $stmt->execute();
        $user_id = $stmt->insert_id;

        // Handle role-specific registration
        switch ($role) {
            case 'customer':
                register_customer($conn, $user_id);
                break;

            case 'staff':
                register_staff($conn, $user_id);
                break;

            case 'manager':
                register_manager($conn, $user_id);
                break;

            case 'admin':
                register_admin($conn, $user_id);
                break;

            default:
                throw new Exception("Invalid role specified");
        }

        $conn->commit();
        return ['success' => true, 'user_id' => $user_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function register_customer($conn, $user_id)
{
    // Insert into customers table
    $stmt = $conn->prepare("INSERT INTO customers (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Assign customer role
    assign_role($conn, $user_id, 'customer');
}

function register_staff($conn, $user_id)
{
    // Check if user is already staff
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("User is already registered as staff");
    }

    // Insert into staff table with default values
    $stmt = $conn->prepare("INSERT INTO staff (user_id, position, hire_date, employment_status) VALUES (?, 'Staff', CURDATE(), 'Full-time')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Assign staff role
    assign_role($conn, $user_id, 'staff');
}

function register_manager($conn, $user_id)
{
    // Similar to staff but with manager role
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("User is already registered as staff/manager");
    }

    $stmt = $conn->prepare("INSERT INTO staff (user_id, position, hire_date, employment_status) VALUES (?, 'Manager', CURDATE(), 'Full-time')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    assign_role($conn, $user_id, 'staff');
    assign_role($conn, $user_id, 'manager');
}

function register_admin($conn, $user_id)
{
    // Admin registration - only allow one admin per user
    $stmt = $conn->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id = r.role_id WHERE ur.user_id = ? AND r.role_name = 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("User is already an admin");
    }

    assign_role($conn, $user_id, 'admin');
}

function assign_role($conn, $user_id, $role_name)
{
    // Check if role already assigned
    $stmt = $conn->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id = r.role_id WHERE ur.user_id = ? AND r.role_name = ?");
    $stmt->bind_param("is", $user_id, $role_name);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) SELECT ?, role_id FROM roles WHERE role_name = ?");
        $stmt->bind_param("is", $user_id, $role_name);
        $stmt->execute();
    }
}

function hire_staff($user_id, $position, $role = 'staff')
{
    $conn = db_connect();
    $conn->begin_transaction();

    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("User does not exist");
        }

        // Check if already staff
        $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("User is already staff");
        }

        // Add to staff table
        $stmt = $conn->prepare("INSERT INTO staff (user_id, position, hire_date, employment_status) VALUES (?, ?, CURDATE(), 'Full-time')");
        $stmt->bind_param("is", $user_id, $position);
        $stmt->execute();

        // Assign role
        assign_role($conn, $user_id, $role);

        // If manager, add manager role
        if ($role === 'manager') {
            assign_role($conn, $user_id, 'manager');
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Hire failed: " . $e->getMessage());
        return false;
    }
}


// Enhanced login with role checking
function login_user($username, $password)
{
    $conn = db_connect();

    $stmt = $conn->prepare("SELECT u.user_id, u.password_hash, u.username, u.email, u.first_name, u.last_name 
                           FROM users u 
                           WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password_hash'])) {
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Set basic session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        // Check user roles
        $stmt = $conn->prepare("SELECT r.role_name FROM user_roles ur 
                              JOIN roles r ON ur.role_id = r.role_id 
                              WHERE ur.user_id = ?");
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        $roles_result = $stmt->get_result();
        $roles = $roles_result->fetch_all(MYSQLI_ASSOC);

        foreach ($roles as $role) {
            if ($role['role_name'] === 'admin') {
                $_SESSION['is_admin'] = true;
            }
            if ($role['role_name'] === 'staff') {
                $_SESSION['is_staff'] = true;

                // Get staff details if available
                $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();
                $staff_result = $stmt->get_result();

                if ($staff_result->num_rows > 0) {
                    $staff = $staff_result->fetch_assoc();
                    $_SESSION['staff_id'] = $staff['staff_id'];
                }
            }
        }

        // Log the login event
        log_event($user['user_id'], 'login', 'User logged in');

        // Redirect to originally requested page if exists
        if (isset($_SESSION['redirect_url'])) {
            $redirect_url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
            header("Location: $redirect_url");
            exit();
        }

        return ['success' => true, 'message' => 'Login successful'];
    } else {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
}

// Enhanced logout function
function logout_user()
{
    // Log the logout event if user was logged in
    if (isset($_SESSION['user_id'])) {
        log_event($_SESSION['user_id'], 'logout', 'User logged out');
    }

    // Unset all session variables
    $_SESSION = [];

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
}

// Log events for auditing
function log_event($user_id, $event_type, $event_details)
{
    $conn = db_connect();
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("INSERT INTO event_log (user_id, event_type, event_details, ip_address) 
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $event_type, $event_details, $ip_address);
    $stmt->execute();
}

// Get user roles
function get_user_roles($user_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT r.role_name FROM user_roles ur 
                          JOIN roles r ON ur.role_id = r.role_id 
                          WHERE ur.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
    return $roles;
}

// Add a role to a user
function add_user_role($user_id, $role_name)
{
    $conn = db_connect();

    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) 
                          SELECT ?, role_id FROM roles WHERE role_name = ?");
    $stmt->bind_param("is", $user_id, $role_name);
    return $stmt->execute();
}

// Remove a role from a user
function remove_user_role($user_id, $role_name)
{
    $conn = db_connect();

    $stmt = $conn->prepare("DELETE ur FROM user_roles ur 
                          JOIN roles r ON ur.role_id = r.role_id 
                          WHERE ur.user_id = ? AND r.role_name = ?");
    $stmt->bind_param("is", $user_id, $role_name);
    return $stmt->execute();
}

// Get all available roles
function get_all_roles()
{
    $conn = db_connect();
    $result = $conn->query("SELECT * FROM roles ORDER BY role_name");
    return $result->fetch_all(MYSQLI_ASSOC);
}


// Get user by ID with role information
function get_user_by_id($user_id)
{
    $conn = db_connect();

    // Get basic user info
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) return null;

    // Get roles
    $user['roles'] = get_user_roles($user_id);

    // Check if staff
    $stmt = $conn->prepare("SELECT * FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_result = $stmt->get_result();

    if ($staff_result->num_rows > 0) {
        $user['staff_info'] = $staff_result->fetch_assoc();
    }

    // Check if customer
    $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $customer_result = $stmt->get_result();

    if ($customer_result->num_rows > 0) {
        $user['customer_info'] = $customer_result->fetch_assoc();
    }

    return $user;
}

// Define get_customer_data function
function get_customer_data($user_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT c.* FROM customers c WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if (!$result) {
        // Default values if customer data not found
        return [
            'membership_level' => 'Standard',
            'loyalty_points' => 0
        ];
    }
    return $result;
}

// Get customer ID from user ID
function get_customer_id($user_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['customer_id'];
}

// Calculate cart total with optional tax
function calculate_cart_total($include_tax = false)
{
    if (empty($_SESSION['cart'])) return 0;

    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    if ($include_tax) {
        // Get tax rate from settings (you would typically store this in a database)
        $tax_rate = 0.075; // 7.5%
        $total += $total * $tax_rate;
    }

    return $total;
}

// Get all staff members with user information
function get_all_staff()
{
    $conn = db_connect();
    $query = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.phone 
              FROM staff s 
              JOIN users u ON s.user_id = u.user_id 
              ORDER BY s.hire_date DESC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get staff member by ID
function get_staff_by_id($staff_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.phone 
                           FROM staff s 
                           JOIN users u ON s.user_id = u.user_id 
                           WHERE s.staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get staff schedule
function get_staff_schedule($staff_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT * FROM staff_schedules 
                           WHERE staff_id = ? 
                           ORDER BY 
                             FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                             start_time");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


// Fetch a single value from database
function fetch_value($query, $params = [])
{
    $conn = db_connect();
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    return $row ? $row[0] : null;
}

// Fetch all rows from database
function fetch_all($query, $params = [])
{
    $conn = db_connect();
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

// Get weekly sales data for chart
function get_weekly_sales()
{
    $conn = db_connect();

    $query = "SELECT DAYNAME(payment_date) as day, 
              SUM(amount) as total 
              FROM payments 
              WHERE payment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              AND status = 'Completed'
              GROUP BY DAYNAME(payment_date)
              ORDER BY payment_date";

    $result = $conn->query($query);
    $sales = [
        'Monday' => 0,
        'Tuesday' => 0,
        'Wednesday' => 0,
        'Thursday' => 0,
        'Friday' => 0,
        'Saturday' => 0,
        'Sunday' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        $sales[$row['day']] = (float)$row['total'];
    }

    return array_values($sales);
}

// Get popular menu items for chart
function get_popular_items($limit = 5)
{
    $conn = db_connect();
    
    return fetch_all("SELECT i.name, SUM(oi.quantity) as total 
                     FROM order_items oi
                     JOIN items i ON oi.item_id = i.item_id
                     JOIN orders o ON oi.order_id = o.order_id
                     WHERE DATE(o.created_at) = CURDATE()
                     GROUP BY i.name
                     ORDER BY total DESC
                     LIMIT ?", [$limit]);
}

function get_available_tables()
{
    $conn = db_connect();

    $stmt = $conn->prepare("
        SELECT table_id, table_number, capacity, location, status 
        FROM restaurant_tables 
        WHERE status = 'Available'
    ");
    if (!$stmt) {
        error_log('Failed to prepare statement in get_available_tables: ' . mysqli_error($conn));
        return [];
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tables = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tables[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $tables;
}

// Get upcoming reservations
function get_upcoming_reservations($user_id)
{
    global $conn;
    $customer_id = get_customer_id_from_user_id($user_id);
    if (!$customer_id) {
        return [];
    }
    $stmt = $conn->prepare("
        SELECT r.*, rt.table_number
        FROM reservations r
        JOIN restaurant_tables rt ON r.table_id = rt.table_id
        WHERE r.customer_id = ? AND r.reservation_date >= CURDATE()
        ORDER BY r.reservation_date, r.start_time
        LIMIT 5
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

