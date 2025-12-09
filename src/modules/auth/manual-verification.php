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

// CSRF protection
if (!isset($_SESSION['verify_csrf_token'])) {
    $_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['verify_csrf_token']) {
        set_flash_message('Invalid request', 'error');
        header('Location: /manual-verification.php');
        exit();
    }

    if (isset($_POST['resend'])) {
        // Try resending the email
        $email_sent = send_verification_email(
            $_SESSION['manual_verification_email'],
            '', // First name not available in session
            $_SESSION['manual_verification_token']
        );

        if ($email_sent) {
            set_flash_message('Verification email sent! Please check your inbox.', 'success');
            unset($_SESSION['manual_verification_token']);
            unset($_SESSION['manual_verification_email']);
            header('Location: /login.php');
            exit();
        } else {
            set_flash_message('Failed to send verification email. Please try manual verification below.', 'error');
        }
    } elseif (isset($_POST['verify_manually'])) {
        // Manual verification
        $entered_token = trim($_POST['verification_token'] ?? '');

        if ($entered_token === $_SESSION['manual_verification_token']) {
            // Mark email as verified
            $conn = db_connect();
            $stmt = $conn->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL, verification_token_expiry = NULL WHERE email = ?");
            $stmt->bind_param("s", $_SESSION['manual_verification_email']);
            $stmt->execute();

            set_flash_message('Email verified successfully! You can now log in.', 'success');
            unset($_SESSION['manual_verification_token']);
            unset($_SESSION['manual_verification_email']);
            header('Location: login.php');
            exit();
        } else {
            set_flash_message('Invalid verification token. Please try again.', 'error');
        }
    }
}

// If no token in session, redirect
if (!isset($_SESSION['manual_verification_token'])) {
    header('Location: /register.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Verification - CaféDelight</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-6">
            <i class="fas fa-envelope-circle-check text-amber-600 text-4xl mb-2"></i>
            <h1 class="text-2xl font-bold text-gray-800">Email Verification</h1>
        </div>

        <?php display_flash_message(); ?>

        <div class="mb-6">
            <p class="text-gray-600 mb-4">We encountered an issue sending your verification email. Please choose an option below:</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['verify_csrf_token']); ?>">

                <div>
                    <button type="submit" name="resend"
                        class="w-full bg-amber-600 hover:bg-amber-500 text-white font-medium py-2 px-4 rounded-md transition duration-300">
                        <i class="fas fa-paper-plane mr-2"></i> Resend Verification Email
                    </button>
                </div>

                <div class="relative flex py-4 items-center">
                    <div class="flex-grow border-t border-gray-300"></div>
                    <span class="flex-shrink mx-4 text-gray-500">OR</span>
                    <div class="flex-grow border-t border-gray-300"></div>
                </div>

                <div>
                    <label for="verification_token" class="block text-gray-700 mb-2">Enter Verification Token Manually</label>
                    <input type="text" id="verification_token" name="verification_token" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="Paste your verification token here">
                    <p class="text-xs text-gray-500 mt-1">Check your email spam folder if you don't see our message.</p>
                </div>

                <div>
                    <button type="submit" name="verify_manually"
                        class="w-full bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition duration-300">
                        <i class="fas fa-check-circle mr-2"></i> Verify Manually
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center">
            <a href="login.php" class="text-amber-600 hover:text-amber-500 font-medium">Return to Login</a>
        </div>
    </div>
</body>

</html>