<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Check user roles
$user_roles = [];
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

$is_manager = in_array(2, $user_roles); // Manager role_id = 2
$is_staff = in_array(3, $user_roles);   // Staff role_id = 3
if (!$is_manager && !$is_staff) {
    set_flash_message('Access denied. You must be a manager or staff to view this page.', 'error');
    header('Location: /dashboard.php');
    exit();
}

// Get staff_id if staff
$staff_id = null;
if ($is_staff) {
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_id = $stmt->get_result()->fetch_assoc()['staff_id'];
}

// Handle schedule addition (managers only)
if ($is_manager && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: schedules.php');
        exit();
    }

    $staff_id_input = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $day_of_week = filter_input(INPUT_POST, 'day_of_week', FILTER_SANITIZE_STRING);
    $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);

    if ($staff_id_input && in_array($day_of_week, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) && $start_time && $end_time) {
        $stmt = $conn->prepare("INSERT INTO staff_schedules (staff_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $staff_id_input, $day_of_week, $start_time, $end_time);
        if ($stmt->execute()) {
            set_flash_message('Schedule added successfully', 'success');
        } else {
            set_flash_message('Failed to add schedule', 'error');
        }
    } else {
        set_flash_message('Invalid schedule data', 'error');
    }
    header('Location: schedules.php');
    exit();
}

// Fetch schedules
$query = $is_staff
    ? "SELECT day_of_week, start_time, end_time 
       FROM staff_schedules 
       WHERE staff_id = ? 
       ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')"
    : "SELECT ss.*, u.first_name, u.last_name 
       FROM staff_schedules ss 
       JOIN staff s ON ss.staff_id = s.staff_id 
       JOIN users u ON s.user_id = u.user_id 
       ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$stmt = $conn->prepare($query);
if ($is_staff) {
    $stmt->bind_param("i", $staff_id);
}
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all staff for manager's add form
$staff_list = [];
if ($is_manager) {
    $stmt = $conn->prepare("SELECT s.staff_id, u.first_name, u.last_name 
                           FROM staff s 
                           JOIN users u ON s.user_id = u.user_id 
                           ORDER BY u.first_name");
    $stmt->execute();
    $staff_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = "Schedules";
$current_page = "schedules";

include __DIR__ . '/includes/header.php';
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
                        primary: {
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
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                }
            }
        }
    </script>
    <style>
        .badge {
            transition: all 0.2s ease;
        }

        .badge:hover {
            transform: scale(1.05);
        }

        .sidebar-item {
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            background-color: rgba(234, 179, 8, 0.1);
            transform: translateX(3px);
        }

        .sidebar-item.active {
            background-color: rgba(234, 179, 8, 0.2);
            border-left: 3px solid #eab308;
        }
    </style>
</head>

<body class="bg-white font-sans min-h-screen">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10 border-b border-amber-100">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-amber-600">Schedules</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-amber-100">
                                <i class="fas fa-bell text-amber-600"></i>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-600 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-amber-600 font-medium"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
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
                                    Schedules
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <?php display_flash_message(); ?>

                        <?php if ($is_manager): ?>
                            <!-- Add New Schedule Form (Managers Only) -->
                            <div class="bg-white rounded-lg shadow p-6 mb-6">
                                <h2 class="text-lg font-medium text-amber-600 mb-4">Add New Schedule</h2>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                        <div>
                                            <label for="staff_id" class="block text-sm font-medium text-amber-600">Staff</label>
                                            <select id="staff_id" name="staff_id" required class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                                <option value="">Select Staff</option>
                                                <?php foreach ($staff_list as $staff): ?>
                                                    <option value="<?= $staff['staff_id'] ?>"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="day_of_week" class="block text-sm font-medium text-amber-600">Day</label>
                                            <select id="day_of_week" name="day_of_week" required class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                                <option value="Monday">Monday</option>
                                                <option value="Tuesday">Tuesday</option>
                                                <option value="Wednesday">Wednesday</option>
                                                <option value="Thursday">Thursday</option>
                                                <option value="Friday">Friday</option>
                                                <option value="Saturday">Saturday</option>
                                                <option value="Sunday">Sunday</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="start_time" class="block text-sm font-medium text-amber-600">Start Time</label>
                                            <input type="time" id="start_time" name="start_time" required class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                        <div>
                                            <label for="end_time" class="block text-sm font-medium text-amber-600">End Time</label>
                                            <input type="time" id="end_time" name="end_time" required class="mt-1 block w-full rounded-lg border border-amber-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600 transition-colors">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <button type="submit" name="add_schedule" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                            Add Schedule
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Schedules List -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-amber-600 mb-4"><?= $is_manager ? 'All Schedules' : 'My Schedule' ?></h2>
                            <?php if (empty($schedules)): ?>
                                <p class="text-sm text-gray-500">No schedules found.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-amber-100">
                                        <thead>
                                            <tr>
                                                <?php if ($is_manager): ?>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                                                <?php endif; ?>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Day</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start Time</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">End Time</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-amber-50">
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr>
                                                    <?php if ($is_manager): ?>
                                                        <td class="px-4 py-2 text-sm text-gray-600">
                                                            <?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-600"><?= date('h:i A', strtotime($schedule['start_time'])) ?></td>
                                                    <td class="px-4 py-2 text-sm text-gray-600"><?= date('h:i A', strtotime($schedule['end_time'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
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