<?php
require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Get admin data
$admin = get_user_by_id($user_id);
if (!$admin) {
    set_flash_message('User not found', 'error');
    header('Location: /auth/login.php');
    exit();
}

$page_title = "Settings";
$current_page = "settings";

include __DIR__ . '/include/header.php';

// Get general settings from database
$stmt = $conn->prepare("SELECT * FROM settings LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

// Get business hours from database
$business_hours = [];
$stmt = $conn->prepare("SELECT * FROM business_hours");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $business_hours[$row['day_of_week']] = [
        'open' => $row['open_time'] ? date('H:i', strtotime($row['open_time'])) : '',
        'close' => $row['close_time'] ? date('H:i', strtotime($row['close_time'])) : '',
        'closed' => $row['is_closed']
    ];
}

// Get user security settings
$user = get_user_by_id($user_id);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: settings.php');
        exit();
    }

    $conn->begin_transaction();

    try {
        if (isset($_POST['update_general'])) {
            // Validate general settings
            $restaurant_name = filter_input(INPUT_POST, 'restaurant_name', FILTER_SANITIZE_STRING);
            $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_SANITIZE_EMAIL);
            $contact_phone = filter_input(INPUT_POST, 'contact_phone', FILTER_SANITIZE_STRING);
            $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
            $tax_rate = filter_input(INPUT_POST, 'tax_rate', FILTER_VALIDATE_FLOAT);

            if (!$restaurant_name || !$contact_email || !filter_var($contact_email, FILTER_VALIDATE_EMAIL) || !$tax_rate || $tax_rate < 0) {
                throw new Exception("Invalid input data");
            }

            // Update general settings
            $stmt = $conn->prepare("UPDATE settings SET restaurant_name = ?, contact_email = ?, contact_phone = ?, address = ?, tax_rate = ? WHERE setting_id = ?");
            $stmt->bind_param("ssssdi", $restaurant_name, $contact_email, $contact_phone, $address, $tax_rate, $settings['setting_id']);
            $stmt->execute();

            set_flash_message('General settings updated successfully', 'success');
        } elseif (isset($_POST['update_hours'])) {
            // Update business hours
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            foreach ($days as $day) {
                $open_time = $_POST[$day . '_open'] ?? null;
                $close_time = $_POST[$day . '_close'] ?? null;
                $is_closed = isset($_POST[$day . '_closed']) ? 1 : 0;

                $open_time_sql = $is_closed ? null : $open_time;
                $close_time_sql = $is_closed ? null : $close_time;

                $stmt = $conn->prepare("UPDATE business_hours SET open_time = ?, close_time = ?, is_closed = ? WHERE day_of_week = ?");
                $stmt->bind_param("ssis", $open_time_sql, $close_time_sql, $is_closed, $day);
                $stmt->execute();
            }

            set_flash_message('Business hours updated successfully', 'success');
        } elseif (isset($_POST['update_user'])) {
            // Update user profile
            $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

            if (!$first_name || !$last_name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid user data");
            }

            // Check if email is already in use by another user
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Email is already in use");
            }

            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
            $stmt->execute();

            // Update session
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;

            set_flash_message('Profile updated successfully', 'success');
        } elseif (isset($_POST['change_password'])) {
            // Change password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }

            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();

            if (!password_verify($current_password, $user_data['password_hash'])) {
                throw new Exception("Current password is incorrect");
            }

            // Update password
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);
            $stmt->execute();

            set_flash_message('Password changed successfully', 'success');
        } elseif (isset($_POST['update_security'])) {
            // Update security settings
            $two_factor = isset($_POST['two_factor']) ? 1 : 0;
            $session_timeout = isset($_POST['session_timeout']) ? 1 : 0;
            $login_alerts = isset($_POST['login_alerts']) ? 1 : 0;

            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ?, session_timeout_enabled = ?, login_alerts_enabled = ? WHERE user_id = ?");
            $stmt->bind_param("iiii", $two_factor, $session_timeout, $login_alerts, $user_id);
            $stmt->execute();

            set_flash_message('Security settings updated successfully', 'success');
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        set_flash_message('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: settings.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Resto Cafe</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        amber: {
                            50: '#fefce8',
                            100: '#fef9c3',
                            200: '#fef08a',
                            300: '#fde047',
                            400: '#facc15',
                            500: '#eab308',
                            600: '#ca8a04',
                            700: '#a16207',
                            800: '#854d0e',
                            900: '#713f12',
                        },
                        white: {
                            50: '#fafafa',
                            100: '#f5f5f5',
                            200: '#e5e5e5',
                            300: '#d4d4d4',
                            400: '#a3a3a3',
                            500: '#737373',
                            600: '#525252',
                            700: '#404040',
                            800: '#262626',
                            900: '#171717',
                        },
                    },
                }
            }
        }
    </script>
</head>

<body class="bg-white font-sans min-h-screen">
    <div class="flex h-screen">
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10 border-b border-amber-100">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-amber-600">Settings</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-amber-100">
                                <i class="fas fa-bell text-amber-600"></i>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-600 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-amber-600 font-medium"><?= htmlspecialchars($admin['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-amber-600"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-amber-100" id="userMenu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition-colors">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 pb-8">
                <div class="bg-white shadow">
                    <div class="px-4 sm:px-6 lg:mx-auto lg:max-w-7xl lg:px-8">
                        <div class="py-6 md:flex md:items-center md:justify-between lg:border-t lg:border-amber-100">
                            <div class="min-w-0 flex-1">
                                <h1 class="text-2xl font-bold leading-7 text-amber-600 sm:truncate sm:text-3xl sm:tracking-tight">
                                    System Settings
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <?php display_flash_message(); ?>

                        <!-- Settings Tabs -->
                        <div class="mb-6">
                            <div class="border-b border-amber-200">
                                <nav class="-mb-px flex space-x-8">
                                    <button id="general-tab" class="border-amber-600 text-amber-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        General Settings
                                    </button>
                                    <button id="hours-tab" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Business Hours
                                    </button>
                                    <button id="user-tab" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        User Profile
                                    </button>
                                    <button id="security-tab" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Security
                                    </button>
                                </nav>
                            </div>
                        </div>

                        <!-- General Settings Tab -->
                        <div id="general-content" class="settings-tab-content">
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">General Settings</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="restaurant_name" class="block text-sm font-medium text-amber-600">Restaurant Name</label>
                                            <input type="text" id="restaurant_name" name="restaurant_name" value="<?= htmlspecialchars($settings['restaurant_name']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="tax_rate" class="block text-sm font-medium text-amber-600">Tax Rate (%)</label>
                                            <input type="number" step="0.01" id="tax_rate" name="tax_rate" value="<?= htmlspecialchars($settings['tax_rate']) ?>" required min="0"
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="contact_email" class="block text-sm font-medium text-amber-600">Contact Email</label>
                                            <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="contact_phone" class="block text-sm font-medium text-amber-600">Contact Phone</label>
                                            <input type="tel" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label for="address" class="block text-sm font-medium text-amber-600">Address</label>
                                            <textarea id="address" name="address" rows="3" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors"><?= htmlspecialchars($settings['address']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_general" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Save General Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Business Hours Tab -->
                        <div id="hours-content" class="settings-tab-content hidden">
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">Business Hours</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="space-y-4">
                                        <?php foreach ($business_hours as $day => $hours): ?>
                                            <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <input id="<?= $day ?>_closed" name="<?= $day ?>_closed" type="checkbox" <?= $hours['closed'] ? 'checked' : '' ?>
                                                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-amber-300 rounded">
                                                    <label for="<?= $day ?>_closed" class="ml-2 block text-sm font-medium text-amber-600 capitalize">
                                                        <?= $day ?>
                                                    </label>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <div>
                                                        <label for="<?= $day ?>_open" class="sr-only">Open Time</label>
                                                        <select id="<?= $day ?>_open" name="<?= $day ?>_open" <?= $hours['closed'] ? 'disabled' : '' ?>
                                                            class="block w-32 pl-3 pr-8 py-2 text-sm border-amber-200 focus:outline-none focus:ring-amber-500 focus:border-amber-600 rounded-md">
                                                            <?php for ($h = 0; $h < 24; $h++): ?>
                                                                <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                                    <?php $time = sprintf('%02d:%02d', $h, $m); ?>
                                                                    <option value="<?= $time ?>" <?= $time === $hours['open'] ? 'selected' : '' ?>><?= date('g:i A', strtotime($time)) ?></option>
                                                                <?php endfor; ?>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <span class="text-sm text-gray-500">to</span>
                                                    <div>
                                                        <label for="<?= $day ?>_close" class="sr-only">Close Time</label>
                                                        <select id="<?= $day ?>_close" name="<?= $day ?>_close" <?= $hours['closed'] ? 'disabled' : '' ?>
                                                            class="block w-32 pl-3 pr-8 py-2 text-sm border-amber-200 focus:outline-none focus:ring-amber-500 focus:border-amber-600 rounded-md">
                                                            <?php for ($h = 0; $h < 24; $h++): ?>
                                                                <?php for ($m = 0; $m < 60; $m += 30): ?>
                                                                    <?php $time = sprintf('%02d:%02d', $h, $m); ?>
                                                                    <option value="<?= $time ?>" <?= $time === $hours['close'] ? 'selected' : '' ?>><?= date('g:i A', strtotime($time)) ?></option>
                                                                <?php endfor; ?>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_hours" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Save Business Hours
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- User Profile Tab -->
                        <div id="user-content" class="settings-tab-content hidden">
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">User Profile</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="first_name" class="block text-sm font-medium text-amber-600">First Name</label>
                                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="last_name" class="block text-sm font-medium text-amber-600">Last Name</label>
                                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-amber-600">Email</label>
                                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="phone" class="block text-sm font-medium text-amber-600">Phone</label>
                                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_user" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Security Tab -->
                        <div id="security-content" class="settings-tab-content hidden">
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">Change Password</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="space-y-4">
                                        <div>
                                            <label for="current_password" class="block text-sm font-medium text-amber-600">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" required
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-amber-600">New Password</label>
                                            <input type="password" id="new_password" name="new_password" required minlength="8"
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                            <p class="mt-1 text-sm text-gray-500">Password must be at least 8 characters long</p>
                                        </div>
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-amber-600">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                                class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="change_password" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">Security Settings</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="space-y-4">
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="two_factor" name="two_factor" type="checkbox" <?= $user['two_factor_enabled'] ? 'checked' : '' ?>
                                                    class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-amber-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="two_factor" class="font-medium text-amber-600">Two-Factor Authentication</label>
                                                <p class="text-gray-500">Require a second form of authentication when logging in</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="session_timeout" name="session_timeout" type="checkbox" <?= $user['session_timeout_enabled'] ? 'checked' : '' ?>
                                                    class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-amber-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="session_timeout" class="font-medium text-amber-600">Session Timeout</label>
                                                <p class="text-gray-500">Automatically log out after 30 minutes of inactivity</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="login_alerts" name="login_alerts" type="checkbox" <?= $user['login_alerts_enabled'] ? 'checked' : '' ?>
                                                    class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-amber-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="login_alerts" class="font-medium text-amber-600">Login Alerts</label>
                                                <p class="text-gray-500">Receive email notifications for new logins</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="update_security" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Update Security Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('[id$="-tab"]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.settings-tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.querySelectorAll('[id$="-tab"]').forEach(t => {
                    t.classList.remove('border-amber-600', 'text-amber-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });

                const contentId = tab.id.replace('-tab', '-content');
                document.getElementById(contentId).classList.remove('hidden');
                tab.classList.remove('border-transparent', 'text-gray-500');
                tab.classList.add('border-amber-600', 'text-amber-600');
            });
        });

        // Enable/disable time selects when closed checkbox is toggled
        document.querySelectorAll('[id$="_closed"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const day = this.id.replace('_closed', '');
                const openSelect = document.getElementById(day + '_open');
                const closeSelect = document.getElementById(day + '_close');

                openSelect.disabled = this.checked;
                closeSelect.disabled = this.checked;
            });
        });

        // User menu toggle
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>

</html>