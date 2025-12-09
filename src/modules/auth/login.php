<?php
require_once __DIR__ . '/../../includes/security.php';

require_once __DIR__ . '/../../includes/functions.php';


// Redirect if already logged in
if (is_logged_in()) {
    $redirect_to = '/../index.php'; // Default redirect for customers

    if (isset($_SESSION['is_staff']) || isset($_SESSION['is_manager'])) {
        $redirect_to = 'staff/dashboard.php'; // Staff dashboard
    } elseif (isset($_SESSION['is_admin'])) {
        $redirect_to = 'admin/dashboard.php'; // Admin dashboard
    }

    header('Location: ' . $redirect_to);
    exit();
}

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verification CSRF token
if (!isset($_SESSION['verify_csrf_token'])) {
    $_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));
}

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('Invalid request', 'error');
        header('Location: login.php');
        exit();
    }

    // Input validation
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        set_flash_message('Please fill in all fields', 'error');
    } else {
        // Rate limiting
        if (isset($_SESSION['login_attempts'])) {
            if ($_SESSION['login_attempts'] >= 5) {
                set_flash_message('Too many attempts. Please try again later.', 'error');
                header('Location: /../../../index.php');
                exit();
            }
        }

        $result = login_user($username, $password);

        if ($result['success']) {
            // Check if email is verified
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT email_verified FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $verification_result = $stmt->get_result();

            if ($verification_result->num_rows === 1) {
                $user = $verification_result->fetch_assoc();

                if (!$user['email_verified']) {
                    // Log out the user if not verified
                    logout_user();
                    set_flash_message('Your email is not verified. Please check your inbox for the verification link.', 'error');
                    header('Location: login.php');
                    exit();
                }
            }

            // Reset attempts on success
            unset($_SESSION['login_attempts']);

            // Remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + 60 * 60 * 24 * 30; // 30 days

                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => $expiry,
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );

                // Store token in database
                $conn = db_connect();
                $stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_token_expiry = ? WHERE username = ?");
                $stmt->bind_param("sis", $token, $expiry, $username);
                $stmt->execute();
            }

            set_flash_message('Login successful!', 'success');

            // Determine redirect based on user role
            $redirect_to = '/../index.php'; // Default for customers

            if (isset($_SESSION['is_staff']) || isset($_SESSION['is_manager'])) {
                // Staff or manager dashboard
                $redirect_to = '/modules/staff/dashboard.php'; // Staff dashboard
            } elseif (isset($_SESSION['is_admin'])) {
                $redirect_to = '/modules/admin/dashboard.php'; // Admin dashboard
            }

            // Check for stored redirect URL
            if (isset($_SESSION['redirect_url'])) {
                $redirect_to = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
            }

            header('Location: ' . $redirect_to);
            exit();
        } else {
            // Track failed attempts
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

            // Check if the failure might be due to unverified email
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT email_verified FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $verification_result = $stmt->get_result();

            if ($verification_result->num_rows === 1) {
                $user = $verification_result->fetch_assoc();
                if (!$user['email_verified']) {
                    set_flash_message('Your email is not verified. Please check your inbox for the verification link.', 'error');
                    header('Location: login.php');
                    exit();
                }
            }

            set_flash_message($result['message'], 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- Enhanced viewport meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Casa Baraka</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        amber: {
                            light: '#FDE68A',
                            DEFAULT: '#D97706',
                            dark: '#B45309',
                            darkest: '#78350F'
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    boxShadow: {
                        'inner-lg': 'inset 0 4px 8px 0 rgba(0, 0, 0, 0.12)'
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('assets/images/cafe-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .amber-gradient {
            background: linear-gradient(135deg, #F59E0B 0%, #B45309 100%);
        }

        .login-container {
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: 1rem;
            overflow: hidden;
            width: 95%;
            max-width: 28rem;
            margin: 1rem auto;
        }

        .input-field:focus {
            border-color: #D97706;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3);
        }

        .login-btn {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }

        .admin-notice {
            border-left: 4px solid #D97706;
        }

        .switch-panel {
            transition: opacity 0.3s ease;
        }

        .password-toggle {
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #D97706;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .login-container {
                border-radius: 0.75rem;
            }

            .amber-gradient {
                padding: 1.5rem;
            }

            .login-form-container {
                padding: 1.5rem;
            }

            h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 400px) {
            .login-container {
                width: 100%;
                border-radius: 0;
                margin: 0;
                min-height: 100vh;
            }

            body {
                display: block;
            }
        }
    </style>
</head>

<body class="flex items-center justify-center p-4 md:p-8">
    <div class="login-container shadow-2xl flex flex-col">
        <!-- Logo Header -->
        <div class="amber-gradient p-6 text-center">
            <div class="bg-white bg-opacity-20 p-4 inline-block rounded-full shadow-inner-lg mb-2">
                <i class="fas fa-mug-hot text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mt-2">Casa Baraka</h1>
            <p class="text-amber-light text-sm mt-1">Brewing moments, serving memories</p>
        </div>

        <!-- Login Form Container -->
        <div class="p-6 sm:p-8">
            <h2 class="text-2xl font-semibold text-amber-darkest mb-1">Welcome Back</h2>
            <p class="text-gray-600 text-sm mb-4 sm:mb-6">Sign in to your account to continue</p>

            <?php display_flash_message(); ?>

            <!-- Admin login notice -->
            <?php if (isset($_GET['admin']) && $_GET['admin'] == 1): ?>
                <div class="admin-notice bg-amber-50 p-3 sm:p-4 rounded-lg mb-4 sm:mb-6 text-sm flex items-start">
                    <i class="fas fa-shield-alt text-amber-dark mt-0.5 mr-3"></i>
                    <p class="text-amber-darkest">You are accessing the admin login. Staff members should use their regular credentials.</p>
                </div>
            <?php endif; ?>

            <form action="login.php<?php echo isset($_GET['admin']) ? '?admin=1' : ''; ?>" method="POST" class="space-y-4 sm:space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" required
                            class="input-field pl-10 w-full px-3 py-2 sm:px-4 sm:py-2.5 border border-gray-300 rounded-lg focus:outline-none transition-colors duration-200"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgot-password.php" class="text-amber-dark hover:text-amber-darkest text-xs font-medium transition-colors duration-200">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                            class="input-field pl-10 w-full px-3 py-2 sm:px-4 sm:py-2.5 border border-gray-300 rounded-lg focus:outline-none transition-colors duration-200">
                        <button type="button" class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"
                            onclick="togglePasswordVisibility('password')">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                </div>

                <button type="submit"
                    class="login-btn w-full amber-gradient text-white font-medium py-2 sm:py-2.5 px-4 rounded-lg">
                    Sign In
                </button>
            </form>

            <!-- Verification Link Section -->
            <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-gray-50 rounded-lg border border-gray-100">
                <h3 class="text-sm font-medium text-amber-darkest mb-2">Need a verification link?</h3>
                <form action="resend-verification.php" method="POST" id="resendVerificationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="relative mb-2 sm:mb-3">
                        <input type="email" id="resend_email" name="email" required placeholder="Enter your email address"
                            class="input-field w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none">
                    </div>

                    <button type="submit"
                        class="w-full py-2 px-4 border border-amber-500 bg-white text-amber-600 text-sm font-medium rounded-lg hover:bg-amber-50 transition-colors duration-200">
                        Resend Verification Email
                    </button>
                </form>
            </div>

            <!-- Footer Links -->
            <div class="mt-4 sm:mt-6 text-center">
                <?php if (isset($_GET['admin']) && $_GET['admin'] == 1): ?>
                    <p class="text-gray-600 text-sm">Not an admin? <a href="login.php" class="text-amber-600 hover:text-amber-700 font-medium">Regular login</a></p>
                <?php else: ?>
                    <p class="text-gray-600 text-sm">Don't have an account? <a href="register.php" class="text-amber-600 hover:text-amber-700 font-medium">Sign up</a></p>
                    <p class="text-gray-600 text-sm mt-1 sm:mt-2">Staff member? <a href="login.php?admin=1" class="text-amber-600 hover:text-amber-700 font-medium">Admin login</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Focus the username field on page load
        document.getElementById('username').focus();
    </script>
</body>

</html>