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

$page_title = "Dashboard";
$current_page = "dashboard";

// Get statistics
try {
    $stats = [
        'total_staff' => fetch_value("SELECT COUNT(*) FROM staff"),
        'total_customers' => fetch_value("SELECT COUNT(*) FROM customers"),
        'total_orders' => fetch_value("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"),
        'total_sales' => fetch_value("SELECT SUM(amount) FROM payments WHERE status = 'Completed' AND DATE(payment_date) = CURDATE()") ?? 0,
        'active_reservations' => fetch_value("SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE() AND status IN ('Confirmed', 'Pending')"),
        'low_inventory' => fetch_value("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level")
    ];
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = array_fill_keys(['total_staff', 'total_customers', 'total_orders', 'total_sales', 'active_reservations', 'low_inventory'], 0);
    set_flash_message('Could not load statistics', 'error');
}

// Get recent activities
$activities = fetch_all("SELECT el.*, u.username 
                       FROM event_log el 
                       LEFT JOIN users u ON el.user_id = u.user_id 
                       ORDER BY event_time DESC 
                       LIMIT 10");

// Get recent orders
$recent_orders = fetch_all("SELECT o.order_id, o.created_at, o.status, 
                          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                          COUNT(oi.order_item_id) as item_count,
                          SUM(oi.quantity * oi.unit_price) as total
                          FROM orders o
                          LEFT JOIN customers c ON o.customer_id = c.customer_id
                          LEFT JOIN users u ON c.user_id = u.user_id
                          LEFT JOIN order_items oi ON o.order_id = oi.order_id
                          GROUP BY o.order_id
                          ORDER BY o.created_at DESC
                          LIMIT 5");

// Get weekly sales data for chart (last 7 days)
$weekly_sales = [];
$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date));
    $sales = fetch_value("SELECT SUM(amount) FROM payments WHERE status = 'Completed' AND DATE(payment_date) = '$date'") ?? 0;
    $weekly_sales[] = $sales;
}

// Get popular items data for today
$popular_items = fetch_all("SELECT i.name, SUM(oi.quantity) as total_sold 
                           FROM order_items oi 
                           JOIN items i ON oi.item_id = i.item_id 
                           JOIN orders o ON oi.order_id = o.order_id
                           WHERE DATE(o.created_at) = CURDATE()
                           GROUP BY i.item_id, i.name 
                           ORDER BY total_sold DESC 
                           LIMIT 5");

$popular_items_labels = array_column($popular_items, 'name');
$popular_items_data = array_column($popular_items, 'total_sold');
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Resto Cafe</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                    },
                }
            }
        }
    </script>
    <style>
        /* Smooth transitions for hover effects */
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Fade-in animation for elements */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans h-full">
    <div class="flex h-full">
        <!-- Sidebar -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard Overview</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-gray-100 relative" id="notificationBell">
                                <i class="fas fa-bell text-gray-500"></i>
                                <?php if ($stats['low_inventory'] > 0): ?>
                                    <span class="absolute top-0 right-0 h-5 w-5 rounded-full bg-red-500 text-white text-xs flex items-center justify-center">
                                        <?= $stats['low_inventory'] ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-gray-700 font-medium"><?= htmlspecialchars($admin['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-gray-500"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-gray-100" id="userMenu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-gray-50">
                <?php display_flash_message(); ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl p-6 card-hover fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Staff</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= $stats['total_staff'] ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-primary-100 text-primary-600">
                                <i class="fas fa-users text-lg"></i>
                            </div>
                        </div>
                        <a href="staff.php" class="mt-4 inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500 transition-colors">
                            View all staff
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl p-6 card-hover fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Today's Orders</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= $stats['total_orders'] ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-shopping-bag text-lg"></i>
                            </div>
                        </div>
                        <a href="orders.php" class="mt-4 inline-flex items-center text-sm font-medium text-green-600 hover:text-green-500 transition-colors">
                            View all orders
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl p-6 card-hover fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Today's Sales</p>
                                <h3 class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_sales'], 2) ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-secondary-100 text-secondary-600">
                                <i class="fas fa-dollar-sign text-lg"></i>
                            </div>
                        </div>
                        <a href="reports.php" class="mt-4 inline-flex items-center text-sm font-medium text-secondary-600 hover:text-secondary-500 transition-colors">
                            View reports
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl p-6 card-hover fade-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Active Reservations</p>
                                <h3 class="text-2xl font-bold text-gray-900"><?= $stats['active_reservations'] ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-calendar-alt text-lg"></i>
                            </div>
                        </div>
                        <a href="reservations.php" class="mt-4 inline-flex items-center text-sm font-medium text-yellow-600 hover:text-yellow-500 transition-colors">
                            View reservations
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Sales Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 fade-in" style="animation-delay: 0.5s;">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Weekly Sales</h2>
                            <select id="salesChartFilter" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                            </select>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Popular Items Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 fade-in" style="animation-delay: 0.6s;">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Popular Menu Items</h2>
                            <select id="popularItemsFilter" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="this_month">This Month</option>
                            </select>
                        </div>
                        <div class="chart-container">
                            <canvas id="popularItemsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Orders -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm fade-in" style="animation-delay: 0.7s;">
                        <div class="p-4 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        </div>
                        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            <?php foreach ($activities as $index => $activity): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($activity['event_type']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($activity['username'] ?? 'System') ?></p>
                                        </div>
                                        <span class="text-sm text-gray-500">
                                            <?= date('H:i', strtotime($activity['event_time'])) ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600"><?= htmlspecialchars($activity['event_details']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-4 border-t border-gray-100 text-center">
                            <a href="activity_log.php" class="text-sm font-medium text-primary-600 hover:text-primary-500 transition-colors">
                                View all activity
                            </a>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="bg-white rounded-xl shadow-sm fade-in" style="animation-delay: 0.8s;">
                        <div class="p-4 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900">Recent Orders</h2>
                        </div>
                        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            <?php foreach ($recent_orders as $index => $order): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium text-gray-800">Order #<?= $order['order_id'] ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($order['customer_name']) ?></p>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">$<?= number_format($order['total'], 2) ?></span>
                                    </div>
                                    <div class="flex justify-between items-center mt-2">
                                        <span class="text-xs px-2.5 py-1 rounded-full 
                                            <?= $order['status'] === 'Completed' ? 'bg-green-100 text-green-800' : ($order['status'] === 'Processing' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= $order['status'] ?>
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <?= date('H:i', strtotime($order['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-4 border-t border-gray-100 text-center">
                            <a href="orders.php" class="text-sm font-medium text-primary-600 hover:text-primary-500 transition-colors">
                                View all orders
                            </a>
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

        // Close user menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Sales Chart
        const salesChartCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Sales ($)',
                    data: <?= json_encode($weekly_sales) ?>,
                    borderColor: '#14b8a6',
                    backgroundColor: 'rgba(20, 184, 166, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#14b8a6',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#14b8a6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales Amount ($)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Day'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#14b8a6',
                        borderWidth: 1
                    }
                }
            }
        });

        // Popular Items Chart
        const popularItemsChartCtx = document.getElementById('popularItemsChart').getContext('2d');
        const popularItemsChart = new Chart(popularItemsChartCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($popular_items_labels) ?>,
                datasets: [{
                    label: 'Items Sold',
                    data: <?= json_encode($popular_items_data) ?>,
                    backgroundColor: [
                        '#14b8a6',
                        '#0ea5e9',
                        '#f97316',
                        '#10b981',
                        '#8b5cf6'
                    ],
                    borderColor: [
                        '#14b8a6',
                        '#0ea5e9',
                        '#f97316',
                        '#10b981',
                        '#8b5cf6'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity Sold'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Menu Item'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#14b8a6',
                        borderWidth: 1
                    }
                }
            }
        });

        // Sales Chart Filter (Dynamic Update)
        document.getElementById('salesChartFilter').addEventListener('change', async (e) => {
            const filter = e.target.value;
            let days, startDate, endDate;

            if (filter === 'this_week') {
                days = 7;
                startDate = new Date();
                startDate.setDate(startDate.getDate() - 6);
                endDate = new Date();
            } else if (filter === 'last_week') {
                days = 7;
                endDate = new Date();
                endDate.setDate(endDate.getDate() - 7);
                startDate = new Date(endDate);
                startDate.setDate(startDate.getDate() - 6);
            } else if (filter === 'this_month') {
                startDate = new Date();
                startDate.setDate(1);
                endDate = new Date();
                days = (endDate - startDate) / (1000 * 60 * 60 * 24) + 1;
            }

            const labels = [];
            const data = [];
            for (let i = 0; i < days; i++) {
                const date = new Date(startDate);
                date.setDate(date.getDate() + i);
                labels.push(date.toLocaleDateString('en-US', {
                    weekday: 'short'
                }));
                const dateStr = date.toISOString().split('T')[0];
                const response = await fetch(`/../api/sales.php?date=${dateStr}`);
                const sales = await response.json();
                data.push(sales.amount || 0);
            }

            salesChart.data.labels = labels;
            salesChart.data.datasets[0].data = data;
            salesChart.update();
        });

        // Popular Items Chart Filter (Dynamic Update)
        document.getElementById('popularItemsFilter').addEventListener('change', async (e) => {
            const filter = e.target.value;
            let startDate;

            if (filter === 'today') {
                startDate = 'CURDATE()';
            } else if (filter === 'this_week') {
                startDate = 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            } else if (filter === 'this_month') {
                startDate = 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)';
            }

            const response = await fetch(`/../api/popular-items.php?start_date=${filter}`);
            const items = await response.json();

            popularItemsChart.data.labels = items.map(item => item.name);
            popularItemsChart.data.datasets[0].data = items.map(item => item.total_sold);
            popularItemsChart.update();
        });
    </script>
</body>

</html>