<?php
require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// Get user data
$user = get_user_by_id($user_id);
$customer = get_customer_data($user_id);

    $page_title = "Dashboard";
    $current_page = "dashboard";

    $is_home = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $preferences = trim($_POST['preferences'] ?? '');

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email)) {
        set_flash_message('Please fill in all required fields', 'error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('Please enter a valid email address', 'error');
    } else {
        // Update user data
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);

        if ($stmt->execute()) {
            // Update customer data
            $stmt = $conn->prepare("UPDATE customers SET birth_date = ?, preferences = ? WHERE user_id = ?");
            $birth_date = !empty($birth_date) ? $birth_date : null;
            $stmt->bind_param("ssi", $birth_date, $preferences, $user_id);
            $stmt->execute();

            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;

            set_flash_message('Profile updated successfully!', 'success');
            header('Location: profile.php');
            exit();
        } else {
            set_flash_message('Failed to update profile. Please try again.', 'error');
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Casa Baraka</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .profile-section {
            transition: all 0.3s ease;
        }

        .profile-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .loyalty-progress {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #f59e0b, #fcd34d);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar -->
            <div class="md:w-1/4">
                <div class="bg-white rounded-xl shadow-md p-6 sticky top-24">
                    <div class="text-center mb-6">
                        <div class="w-24 h-24 bg-gradient-to-br from-amber-100 to-amber-200 rounded-full mx-auto mb-4 flex items-center justify-center shadow-inner">
                            <i class="fas fa-user text-amber-600 text-3xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['first_name'] . ' ' . htmlspecialchars($user['last_name'])) ?></h2>
                        <p class="text-gray-500 text-sm">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                        <div class="mt-2 bg-amber-100 text-amber-800 text-xs font-medium px-2.5 py-0.5 rounded-full inline-block">
                            <?= $customer['membership_level'] ?> member
                        </div>
                    </div>

                    <nav class="space-y-1">
                        <a href="dashboard.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-tachometer-alt mr-3 text-gray-500"></i> Dashboard
                        </a>
                        <a href="profile.php" class="flex items-center sidebar-link py-2 px-4 bg-amber-50 text-amber-700 rounded-lg font-medium">
                            <i class="fas fa-calendar-alt mr-3 text-amber-600"></i> My Profile
                        </a>
                        <a href="orders.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-receipt mr-3 text-gray-500"></i> My Orders
                        </a>
                        <a href="reservation.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-receipt mr-3 text-gray-500"></i> Reservations
                        </a>
                        <a href="favorites.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-heart mr-3 text-gray-500"></i> Favorites
                        </a>
                        <a href="modules/auth/logout.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-red-600">
                            <i class="fas fa-sign-out-alt mr-3"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="md:w-3/4">
                <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white p-6">
                        <h1 class="text-2xl font-bold">My Profile</h1>
                        <p class="opacity-90">Manage your account information</p>
                    </div>

                    <div class="p-6 md:p-8">
                        <?php display_flash_message(); ?>

                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- First Name -->
                                <div>
                                    <label for="first_name" class="block text-gray-700 font-medium mb-2">First Name*</label>
                                    <input type="text" id="first_name" name="first_name" required
                                        value="<?= htmlspecialchars($user['first_name']) ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label for="last_name" class="block text-gray-700 font-medium mb-2">Last Name*</label>
                                    <input type="text" id="last_name" name="last_name" required
                                        value="<?= htmlspecialchars($user['last_name']) ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-gray-700 font-medium mb-2">Email*</label>
                                    <input type="email" id="email" name="email" required
                                        value="<?= htmlspecialchars($user['email']) ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-gray-700 font-medium mb-2">Phone</label>
                                    <input type="tel" id="phone" name="phone"
                                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Birth Date -->
                                <div>
                                    <label for="birth_date" class="block text-gray-700 font-medium mb-2">Birth Date</label>
                                    <input type="text" id="birth_date" name="birth_date"
                                        value="<?= !empty($customer['birth_date']) ? htmlspecialchars($customer['birth_date']) : '' ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        placeholder="YYYY-MM-DD">
                                </div>

                                <!-- Membership Level -->
                                <div>
                                    <label class="block text-gray-700 font-medium mb-2">Membership Level</label>
                                    <div class="px-4 py-2 bg-gray-100 rounded-lg">
                                        <span class="font-medium text-amber-600"><?= htmlspecialchars($customer['membership_level']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Preferences -->
                            <div>
                                <label for="preferences" class="block text-gray-700 font-medium mb-2">Preferences</label>
                                <textarea id="preferences" name="preferences" rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    placeholder="Any dietary preferences or special requirements?"><?= htmlspecialchars($customer['preferences'] ?? '') ?></textarea>
                            </div>

                            <!-- Loyalty Points -->
                            <div class="bg-amber-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium text-gray-700">Loyalty Points</span>
                                    <span class="font-bold text-amber-600"><?= $customer['loyalty_points'] ?? 0 ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="loyalty-progress h-2.5 rounded-full"
                                        style="width: <?= min(($customer['loyalty_points'] ?? 0) / 100 * 100, 100) ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Earn more points to reach <?= next_membership_level($customer['membership_level']) ?> status</p>
                            </div>

                            <div class="pt-4">
                                <button type="submit" name="update_profile"
                                    class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-medium py-3 px-6 rounded-lg transition shadow-md hover:shadow-lg">
                                    <i class="fas fa-save mr-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden profile-section">
                    <div class="bg-gray-50 p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
                    </div>
                    <div class="p-6 md:p-8">
                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="current_password" class="block text-gray-700 font-medium mb-2">Current Password*</label>
                                <input type="password" id="current_password" name="current_password" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div>
                                <label for="new_password" class="block text-gray-700 font-medium mb-2">New Password*</label>
                                <input type="password" id="new_password" name="new_password" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters with at least one uppercase letter and number</p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm New Password*</label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>

                            <div class="pt-2">
                                <button type="submit" name="change_password"
                                    class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-medium py-3 px-6 rounded-lg transition shadow-md hover:shadow-lg">
                                    <i class="fas fa-key mr-2"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize date picker for birth date
        flatpickr("#birth_date", {
            dateFormat: "Y-m-d",
            maxDate: new Date().fp_incr(-18 * 365), // At least 18 years old
            allowInput: true
        });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>

    <?php
    function next_membership_level($current_level)
    {
        $levels = ['Regular', 'Bronze', 'Silver', 'Gold', 'Platinum'];
        $current_index = array_search($current_level, $levels);
        return $current_index < count($levels) - 1 ? $levels[$current_index + 1] : 'Platinum+';
    }
    ?>
</body>

</html>