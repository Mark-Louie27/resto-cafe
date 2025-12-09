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

$page_title = "Reports";
$current_page = "reports";

include __DIR__ . '/include/header.php';

// Default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get sales summary
$sales_summary = fetch_all("SELECT 
                          DATE(payment_date) as date,
                          COUNT(DISTINCT order_id) as order_count,
                          SUM(amount) as total_sales,
                          AVG(amount) as avg_order_value
                          FROM payments
                          WHERE payment_date BETWEEN ? AND ?
                          AND status = 'Completed'
                          GROUP BY DATE(payment_date)
                          ORDER BY date", [$start_date, $end_date]);

// Get popular items
$popular_items = fetch_all("SELECT 
                          i.name,
                          SUM(oi.quantity) as total_quantity,
                          SUM(oi.quantity * oi.unit_price) as total_revenue
                          FROM order_items oi
                          JOIN items i ON oi.item_id = i.item_id
                          JOIN orders o ON oi.order_id = o.order_id
                          WHERE o.created_at BETWEEN ? AND ?
                          GROUP BY i.name
                          ORDER BY total_quantity DESC
                          LIMIT 10", [$start_date, $end_date]);

// Get customer counts
$new_customers = fetch_value("SELECT COUNT(*) 
                            FROM customers c
                            JOIN users u ON c.user_id = u.user_id
                            WHERE DATE(u.created_at) BETWEEN ? AND ?", [$start_date, $end_date]);

$returning_customers = fetch_value("SELECT COUNT(DISTINCT o.customer_id)
                                  FROM orders o
                                  WHERE o.created_at BETWEEN ? AND ?
                                  AND o.customer_id IN (
                                      SELECT customer_id 
                                      FROM orders 
                                      WHERE created_at < ?
                                  )", [$start_date, $end_date, $start_date]);

// Get reservation stats
$reservation_stats = fetch_all("SELECT 
                              status,
                              COUNT(*) as count
                              FROM reservations
                              WHERE reservation_date BETWEEN ? AND ?
                              GROUP BY status", [$start_date, $end_date]);
?>

<body class="bg-gray-100 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden x-auto">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="flex items-center justify-between p-4">
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard Overview</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-bell text-gray-500"></i>
                                <span class="absolute top-0 right-0 h-2 w-2 rounded-full bg-red-500"></span>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-500 flex items-center justify-center text-white">
                                    <?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline"><?= htmlspecialchars($admin['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20" id="userMenu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="container mx-auto px-4 py-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Reports Dashboard</h1>
                    <button onclick="window.print()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                        </svg>
                        Print Report
                    </button>
                </div>

                <!-- Date Range Selector -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                Generate Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <!-- Total Sales -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 truncate">Total Sales</p>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    $<?= number_format(fetch_value("SELECT SUM(amount) FROM payments WHERE payment_date BETWEEN ? AND ? AND status = 'Completed'", [$start_date, $end_date]) ?? 0, 2) ?>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500">
                                <?= count($sales_summary) ?> days with sales between <?= date('M j', strtotime($start_date)) ?> and <?= date('M j', strtotime($end_date)) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Total Orders -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 truncate">Total Orders</p>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?= number_format(fetch_value("SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?", [$start_date, $end_date]) ?? 0) ?>
                                </p>
                            </div>
                            <div class="bg-amber-100 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500">
                                <?= number_format(fetch_value("SELECT AVG(total) FROM (SELECT SUM(oi.quantity * oi.unit_price) as total FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE o.created_at BETWEEN ? AND ? GROUP BY o.order_id) as totals", [$start_date, $end_date]) ?? 0, 2) ?> average order value
                            </p>
                        </div>
                    </div>

                    <!-- New Customers -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 truncate">New Customers</p>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?= number_format($new_customers) ?>
                                </p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500">
                                <?= number_format($returning_customers) ?> returning customers
                            </p>
                        </div>
                    </div>

                    <!-- Reservations -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 truncate">Reservations</p>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?= number_format(fetch_value("SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN ? AND ?", [$start_date, $end_date]) ?? 0) ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500">
                                <?= number_format(fetch_value("SELECT AVG(party_size) FROM reservations WHERE reservation_date BETWEEN ? AND ?", [$start_date, $end_date]) ?? 0, 1) ?> average party size
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Daily Sales</h2>
                    <div class="h-64">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Popular Items -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Top Selling Items</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($popular_items as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($item['total_quantity']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?= number_format($item['total_revenue'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Reservation Status -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Reservation Status</h2>
                        <div class="h-64">
                            <canvas id="reservationChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sales by Category -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Sales by Category</h2>
                    <div class="h-64">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <!-- Chart.js -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                // Sales Chart
                const salesCtx = document.getElementById('salesChart').getContext('2d');
                const salesChart = new Chart(salesCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($sales_summary, 'date')) ?>,
                        datasets: [{
                            label: 'Daily Sales',
                            data: <?= json_encode(array_column($sales_summary, 'total_sales')) ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.raw.toLocaleString(undefined, {
                                            minimumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                });

                // Reservation Chart
                const reservationCtx = document.getElementById('reservationChart').getContext('2d');
                const reservationChart = new Chart(reservationCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_column($reservation_stats, 'status')) ?>,
                        datasets: [{
                            data: <?= json_encode(array_column($reservation_stats, 'count')) ?>,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(153, 102, 255, 0.5)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            }
                        }
                    }
                });

                // Category Sales Chart (would need to fetch this data in PHP)
                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                const categoryChart = new Chart(categoryCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Food', 'Beverages', 'Desserts', 'Alcohol', 'Other'],
                        datasets: [{
                            label: 'Sales by Category',
                            data: [1200, 800, 400, 600, 200],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(153, 102, 255, 0.5)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
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
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.raw.toLocaleString(undefined, {
                                            minimumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                });
            </script>
</body>