<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php'; // Add this line
require_login();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Get user roles
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
    set_flash_message('Access denied. You must be a manager or staff to view the dashboard.', 'error');
    header('Location: /index.php');
    exit();
}

// Handle order status update for staff
if ($is_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'])) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $staff_id = null;

    // Get staff_id for the logged-in user
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_id = $stmt->get_result()->fetch_assoc()['staff_id'];
    $stmt->close();

    if ($staff_id && $order_id && $new_status) {
        $result = process_order($order_id, $new_status, $staff_id);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: dashboard.php');
        exit();
    } else {
        set_flash_message('Invalid request to update order status.', 'error');
        header('Location: dashboard.php');
        exit();
    }
}

// Manager-specific data
if ($is_manager) {
    // Sales Overview
    $sales_data = [
        'total_revenue' => 0,
        'orders_by_status' => []
    ];

    $stmt = $conn->prepare("SELECT SUM(total) as total_revenue FROM orders WHERE status = 'Completed'");
    $stmt->execute();
    $sales_data['total_revenue'] = $stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0;

    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sales_data['orders_by_status'][$row['status']] = $row['count'];
    }

    // Inventory Low Stock
    $low_stock = [];
    $stmt = $conn->prepare("SELECT inventory_id, item_name, quantity, reorder_level, unit FROM inventory WHERE quantity <= reorder_level LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $low_stock[] = $row;
    }

    // Recent Feedback
    $recent_feedback = [];
    $stmt = $conn->prepare("SELECT f.rating, f.comment, f.feedback_date, u.first_name, u.last_name 
                           FROM feedback f 
                           LEFT JOIN customers c ON f.customer_id = c.customer_id
                           LEFT JOIN users u ON c.user_id = u.user_id 
                           ORDER BY f.feedback_date DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_feedback[] = $row;
    }
}

// Staff-specific data
if ($is_staff) {
    // Get staff_id
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_id = $stmt->get_result()->fetch_assoc()['staff_id'];

    // Pending Orders
    $pending_orders = [];
    $stmt = $conn->prepare("SELECT o.order_id, o.order_type, o.status, o.created_at, u.first_name, u.last_name
                           FROM orders o
                           LEFT JOIN customers c ON o.customer_id = c.customer_id
                           LEFT JOIN users u ON c.user_id = u.user_id
                           WHERE o.status IN ('Pending', 'Processing')
                           ORDER BY o.created_at DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_orders[] = $row;
    }

    // Table Status
    $table_status = [];
    $stmt = $conn->prepare("SELECT table_id, table_number, capacity, status FROM restaurant_tables ORDER BY table_number LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $table_status[] = $row;
    }

    // Active Promotions
    $active_promotions = [];
    $stmt = $conn->prepare("SELECT name, discount_type, discount_value, end_date 
                           FROM promotions 
                           WHERE is_active = 1 AND end_date >= CURDATE() 
                           LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $active_promotions[] = $row;
    }
}

// Common data: Staff Schedule
$schedule = [];
if ($is_staff || $is_manager) {
    $query = $is_staff
        ? "SELECT day_of_week, start_time, end_time FROM staff_schedules WHERE staff_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')"
        : "SELECT ss.day_of_week, ss.start_time, ss.end_time, u.first_name, u.last_name 
           FROM staff_schedules ss 
           JOIN staff s ON ss.staff_id = s.staff_id 
           JOIN users u ON s.user_id = u.user_id 
           ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') LIMIT 5";

    $stmt = $conn->prepare($query);
    if ($is_staff) {
        $stmt->bind_param("i", $staff_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
}

$page_title = "Dashboard";
$current_page = "dashboard";

include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Casa Baraka</title>
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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            },
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(10px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            },
                        },
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

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

        .notification-dot {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .scrollbar::-webkit-scrollbar-track {
            background: rgba(234, 179, 8, 0.1);
        }

        .scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(234, 179, 8, 0.4);
            border-radius: 3px;
        }
    </style>
</head>

<body class="h-full">
    <div class="flex h-full">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <button id="sidebar-toggle" class="text-gray-500 focus:outline-none lg:hidden">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button class="p-2 text-gray-500 hover:text-primary-600 relative">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 notification-dot"></span>
                        </button>

                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center space-x-2 focus:outline-none">
                                <div class="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-gray-400 text-xs"></i>
                            </button>

                            <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 border border-gray-200">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-200">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Welcome Banner -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?= htmlspecialchars($_SESSION['first_name']) ?>!</h2>
                                <p class="text-gray-600 mt-1">
                                    <?= date('l, F j, Y') ?> •
                                    <span class="font-medium text-primary-600"><?= $is_manager ? 'Manager' : 'Staff' ?> Dashboard</span>
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <button onclick="openQuickActions()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php display_flash_message(); ?>

                    <!-- Dashboard Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if ($is_manager): ?>
                            <!-- Manager: Revenue Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                                        <p class="mt-1 text-3xl font-semibold text-gray-900">₱<?= number_format($sales_data['total_revenue'], 2) ?></p>
                                    </div>
                                    <div class="h-12 w-12 rounded-full bg-primary-50 flex items-center justify-center">
                                        <i class="fas fa-dollar-sign text-primary-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <div class="flex items-center justify-between text-sm">
                                        <p class="text-gray-500">Orders Today</p>
                                        <p class="font-medium text-primary-600"><?= array_sum($sales_data['orders_by_status']) ?></p>
                                    </div>
                                    <div class="mt-4">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-medium text-gray-500">Completed</span>
                                            <span class="text-xs font-medium text-gray-500"><?= $sales_data['orders_by_status']['Completed'] ?? 0 ?></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-primary-600 h-2 rounded-full" style="width: <?= ($sales_data['orders_by_status']['Completed'] ?? 0) / max(1, array_sum($sales_data['orders_by_status'])) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manager: Low Stock Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Low Stock Items</p>
                                        <p class="mt-1 text-3xl font-semibold text-gray-900"><?= count($low_stock) ?></p>
                                    </div>
                                    <div class="h-12 w-12 rounded-full bg-red-50 flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-6 space-y-3">
                                    <?php if (empty($low_stock)): ?>
                                        <p class="text-sm text-gray-500 text-center py-2">All items are well stocked</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($low_stock, 0, 3) as $item): ?>
                                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['item_name']) ?></p>
                                                    <p class="text-xs text-gray-500"><?= number_format($item['quantity'], 2) ?> <?= htmlspecialchars($item['unit']) ?></p>
                                                </div>
                                                <button onclick='openManageInventoryModal(<?= json_encode($item) ?>)' class="text-xs px-2 py-1 rounded-md bg-primary-50 text-primary-600 hover:bg-primary-100">
                                                    Manage
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($low_stock) > 3): ?>
                                            <a href="/modules/staff/inventory.php" class="block text-center text-xs text-primary-600 hover:text-primary-700 mt-2">
                                                View all <?= count($low_stock) ?> items
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_staff): ?>
                            <!-- Staff: Pending Orders Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Pending Orders</p>
                                        <p class="mt-1 text-3xl font-semibold text-gray-900"><?= count($pending_orders) ?></p>
                                    </div>
                                    <div class="h-12 w-12 rounded-full bg-yellow-50 flex items-center justify-center">
                                        <i class="fas fa-clipboard-list text-yellow-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-6 space-y-3">
                                    <?php if (empty($pending_orders)): ?>
                                        <p class="text-sm text-gray-500 text-center py-2">No pending orders</p>
                                    <?php else: ?>
                                        <?php foreach (array_slice($pending_orders, 0, 3) as $order): ?>
                                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">Order #<?= $order['order_id'] ?></p>
                                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
                                                </div>
                                                <span class="text-xs px-2 py-1 rounded-full <?= $order['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= htmlspecialchars($order['status']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="/modules/staff/orders.php" class="block text-center text-xs text-primary-600 hover:text-primary-700 mt-2">
                                            View all orders
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Common: Schedule Card -->
                        <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500"><?= $is_manager ? 'Staff Schedules' : 'My Schedule' ?></p>
                                    <p class="mt-1 text-xl font-semibold text-gray-900">This Week</p>
                                </div>
                                <div class="h-12 w-12 rounded-full bg-blue-50 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-6 space-y-3">
                                <?php if (empty($schedule)): ?>
                                    <p class="text-sm text-gray-500 text-center py-2">No scheduled shifts</p>
                                <?php else: ?>
                                    <?php foreach ($schedule as $shift): ?>
                                        <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($shift['day_of_week']) ?></p>
                                                <?php if ($is_manager): ?>
                                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs font-medium text-gray-700">
                                                <?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="/modules/staff/schedules.php" class="block text-center text-xs text-primary-600 hover:text-primary-700 mt-2">
                                        View full schedule
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($is_manager): ?>
                            <!-- Manager: Recent Feedback Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Recent Customer Feedback</p>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">Last 5 Reviews</p>
                                    </div>
                                    <a href="/modules/staff/feedback.php" class="text-xs text-primary-600 hover:text-primary-700">
                                        View all
                                    </a>
                                </div>
                                <div class="mt-6">
                                    <?php if (empty($recent_feedback)): ?>
                                        <p class="text-sm text-gray-500 text-center py-4">No recent feedback</p>
                                    <?php else: ?>
                                        <div class="space-y-4">
                                            <?php foreach ($recent_feedback as $feedback): ?>
                                                <div class="flex items-start space-x-4 p-3 hover:bg-gray-50 rounded-lg">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-full bg-primary-50 flex items-center justify-center">
                                                            <i class="fas fa-user text-primary-600"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']) ?>
                                                        </p>
                                                        <div class="flex items-center mt-1">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star text-xs <?= $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                            <?php endfor; ?>
                                                            <span class="ml-2 text-xs text-gray-500">
                                                                <?= date('M j, Y', strtotime($feedback['feedback_date'])) ?>
                                                            </span>
                                                        </div>
                                                        <p class="mt-1 text-sm text-gray-600">
                                                            <?= htmlspecialchars($feedback['comment'] ?? 'No comment provided') ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_staff): ?>
                            <!-- Staff: Table Status Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Table Status</p>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">Restaurant Floor</p>
                                    </div>
                                    <a href="/modules/staff/tables.php" class="text-xs text-primary-600 hover:text-primary-700">
                                        View all
                                    </a>
                                </div>
                                <div class="mt-6">
                                    <?php if (empty($table_status)): ?>
                                        <p class="text-sm text-gray-500 text-center py-2">No tables available</p>
                                    <?php else: ?>
                                        <div class="grid grid-cols-2 gap-3">
                                            <?php foreach ($table_status as $table): ?>
                                                <div class="p-3 rounded-lg border <?= $table['status'] === 'Available' ? 'border-green-200 bg-green-50' : ($table['status'] === 'Occupied' ? 'border-red-200 bg-red-50' : 'border-yellow-200 bg-yellow-50') ?>">
                                                    <div class="flex items-center justify-between">
                                                        <p class="text-sm font-medium text-gray-900">Table <?= $table['table_number'] ?></p>
                                                        <span class="text-xs px-2 py-1 rounded-full <?= $table['status'] === 'Available' ? 'bg-green-100 text-green-800' : ($table['status'] === 'Occupied' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                            <?= htmlspecialchars($table['status']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-xs text-gray-500">Capacity: <?= $table['capacity'] ?> people</p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Staff: Promotions Card -->
                            <div class="bg-white rounded-xl shadow-sm p-6 card animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Active Promotions</p>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">Special Offers</p>
                                    </div>
                                    <a href="/modules/staff/promotions.php" class="text-xs text-primary-600 hover:text-primary-700">
                                        View all
                                    </a>
                                </div>
                                <div class="mt-6 space-y-3">
                                    <?php if (empty($active_promotions)): ?>
                                        <p class="text-sm text-gray-500 text-center py-2">No active promotions</p>
                                    <?php else: ?>
                                        <?php foreach ($active_promotions as $promo): ?>
                                            <div class="p-3 rounded-lg bg-gradient-to-r from-primary-50 to-amber-50 border border-primary-100">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($promo['name']) ?></p>
                                                <p class="mt-1 text-xs text-gray-600">
                                                    <?php
                                                    $discount = $promo['discount_type'] === 'Percentage'
                                                        ? $promo['discount_value'] . '% off'
                                                        : '₱' . number_format($promo['discount_value'], 2) . ' off';
                                                    ?>
                                                    <?= $discount ?> • Ends <?= date('M j', strtotime($promo['end_date'])) ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quick Actions Modal -->
    <div id="quickActionsModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">
            <button onclick="toggleModal('quickActionsModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-bolt mr-2"></i> Quick Actions
            </h2>
            <div class="space-y-3">
                <?php if ($is_manager): ?>
                    <a href="/modules/staff/orders.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-shopping-cart mr-2"></i> View All Orders
                    </a>
                    <a href="/modules/staff/inventory.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-boxes mr-2"></i> Manage Inventory
                    </a>
                    <a href="/modules/staff/schedules.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i> Manage Schedules
                    </a>
                <?php endif; ?>
                <?php if ($is_staff): ?>
                    <a href="/modules/staff/orders.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-shopping-cart mr-2"></i> View Orders
                    </a>
                    <a href="/modules/staff/tables.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-chair mr-2"></i> Manage Tables
                    </a>
                    <a href="/modules/staff/promotions.php" class="block w-full px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition flex items-center">
                        <i class="fas fa-tags mr-2"></i> View Promotions
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manage Inventory Modal (Manager) -->
    <div id="manageInventoryModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('manageInventoryModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-boxes mr-2"></i> Manage Inventory
            </h2>
            <form method="POST" action="/modules/staff/inventory_update.php">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="inventory_id" id="inventory_id">
                <div class="space-y-4">
                    <div>
                        <label for="item_name" class="block text-sm font-medium text-amber-600">Item Name</label>
                        <input type="text" id="item_name" readonly class="w-full px-3 py-2 bg-amber-50 border border-amber-200 rounded-md shadow-sm focus:outline-none">
                    </div>
                    <div>
                        <label for="current_quantity" class="block text-sm font-medium text-amber-600">Current Quantity</label>
                        <input type="text" id="current_quantity" readonly class="w-full px-3 py-2 bg-amber-50 border border-amber-200 rounded-md shadow-sm focus:outline-none">
                    </div>
                    <div>
                        <label for="new_quantity" class="block text-sm font-medium text-amber-600">New Quantity</label>
                        <input type="number" id="new_quantity" name="quantity" required step="0.01" min="0" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('manageInventoryModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Table Modal (Staff) -->
    <div id="manageTableModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('manageTableModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-chair mr-2"></i> Manage Table
            </h2>
            <form method="POST" action="/modules/staff/table_update.php">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="table_id" id="table_id">
                <div class="space-y-4">
                    <div>
                        <label for="table_number" class="block text-sm font-medium text-amber-600">Table Number</label>
                        <input type="text" id="table_number" readonly class="w-full px-3 py-2 bg-amber-50 border border-amber-200 rounded-md shadow-sm focus:outline-none">
                    </div>
                    <div>
                        <label for="current_status" class="block text-sm font-medium text-amber-600">Current Status</label>
                        <input type="text" id="current_status" readonly class="w-full px-3 py-2 bg-amber-50 border border-amber-200 rounded-md shadow-sm focus:outline-none">
                    </div>
                    <div>
                        <label for="new_status" class="block text-sm font-medium text-amber-600">New Status</label>
                        <select id="new_status" name="status" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="Available">Available</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Reserved">Reserved</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('manageTableModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });

        // Toggle user menu
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('#user-menu-button') && !event.target.closest('#user-menu')) {
                document.getElementById('user-menu').classList.add('hidden');
            }
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

        // Modal toggle function
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.toggle('hidden');
        }

        // Open Quick Actions modal
        function openQuickActions() {
            toggleModal('quickActionsModal');
        }

        // Open Manage Inventory modal
        function openManageInventoryModal(item) {
            document.getElementById('inventory_id').value = item.inventory_id;
            document.getElementById('item_name').value = item.item_name;
            document.getElementById('current_quantity').value = `${item.quantity} ${item.unit}`;
            document.getElementById('new_quantity').value = item.quantity;
            toggleModal('manageInventoryModal');
        }

        // Open Manage Table modal
        function openManageTableModal(table) {
            document.getElementById('table_id').value = table.table_id;
            document.getElementById('table_number').value = table.table_number;
            document.getElementById('current_status').value = table.status;
            document.getElementById('new_status').value = table.status;
            toggleModal('manageTableModal');
        }

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            const modals = ['quickActionsModal', 'manageInventoryModal', 'manageTableModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
</body>

</html>