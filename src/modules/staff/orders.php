<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Check if user is staff
$user_roles = [];
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

$is_staff = in_array(3, $user_roles); // Staff role_id = 3
if (!$is_staff) {
    set_flash_message('Access denied. You must be a staff member to view this page.', 'error');
    header('Location: /dashboard.php');
    exit();
}

// Get staff_id
$stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff_id = $stmt->get_result()->fetch_assoc()['staff_id'];

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: orders.php');
        exit();
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($order_id && in_array($new_status, ['Pending', 'Processing', 'Ready', 'Completed', 'Cancelled'])) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        if ($stmt->execute()) {
            set_flash_message('Order status updated successfully', 'success');
        } else {
            set_flash_message('Failed to update order status', 'error');
        }
    } else {
        set_flash_message('Invalid order or status', 'error');
    }
    header('Location: orders.php');
    exit();
}

// Filter orders by status
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? 'all';
$where_clause = $status_filter !== 'all' ? "WHERE o.status = ?" : "";
$query = "SELECT o.order_id, o.order_type, o.status, o.created_at, o.total, u.first_name, u.last_name
          FROM orders o
          LEFT JOIN customers c ON o.customer_id = c.customer_id
          LEFT JOIN users u ON c.user_id = u.user_id
          $where_clause
          ORDER BY o.created_at DESC";
$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order details if order_id is provided
$order_details = null;
$order_items = [];
if (isset($_GET['id'])) {
    $order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($order_id) {
        $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name
                               FROM orders o
                               LEFT JOIN customers c ON o.customer_id = c.customer_id
                               LEFT JOIN users u ON c.user_id = u.user_id
                               WHERE o.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_details = $stmt->get_result()->fetch_assoc();

        if ($order_details) {
            $stmt = $conn->prepare("SELECT oi.*, i.name
                                   FROM order_items oi
                                   JOIN items i ON oi.item_id = i.item_id
                                   WHERE oi.order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$page_title = "Manage Orders";
$current_page = "orders";

include __DIR__ . '/includes/header.php';
?>

<?php
// [Keep your existing PHP code at the top]
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge i {
            margin-right: 0.25rem;
            font-size: 0.625rem;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-processing {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-ready {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-completed {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
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

        .order-card {
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
                        <h1 class="text-xl font-semibold text-gray-900">Order Management</h1>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button class="p-2 text-gray-500 hover:text-primary-600 relative">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span>
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
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900"><?= $order_details ? 'Order Details' : 'Order Management' ?></h2>
                            <p class="mt-1 text-sm text-gray-600">
                                <?= $order_details ? 'View and update order details' : 'Manage and track all customer orders' ?>
                            </p>
                        </div>

                        <?php if ($order_details): ?>
                            <a href="orders.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mt-4 md:mt-0">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                            </a>
                        <?php else: ?>
                            <div class="flex items-center space-x-3 mt-4 md:mt-0">
                                <div class="relative">
                                    <label for="status_filter" class="sr-only">Filter</label>
                                    <select id="status_filter" onchange="window.location.href='orders.php?status='+this.value" class="block w-full pl-3 pr-8 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Orders</option>
                                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Processing" <?= $status_filter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="Ready" <?= $status_filter === 'Ready' ? 'selected' : '' ?>>Ready</option>
                                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php display_flash_message(); ?>

                    <?php if ($order_details): ?>
                        <!-- Order Details View -->
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg animate-fade-in">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Order #<?= $order_details['order_id'] ?>
                                    </h3>
                                    <span class="status-badge status-<?= strtolower($order_details['status']) ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= $order_details['status'] ?>
                                    </span>
                                </div>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                    Placed on <?= date('F j, Y \a\t g:i A', strtotime($order_details['created_at'])) ?>
                                </p>
                            </div>

                            <div class="px-4 py-5 sm:p-6">
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Customer Information</h4>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-900">
                                                <?= htmlspecialchars($order_details['first_name'] . ' ' . $order_details['last_name']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Order Information</h4>
                                        <div class="mt-2 grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-xs text-gray-500">Order Type</p>
                                                <p class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($order_details['order_type']) ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Total Amount</p>
                                                <p class="text-sm text-gray-900">
                                                    ₱<?= number_format($order_details['total'], 2) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($order_details['notes'])): ?>
                                        <div class="sm:col-span-2">
                                            <h4 class="text-sm font-medium text-gray-500">Customer Notes</h4>
                                            <div class="mt-2 p-3 bg-gray-50 rounded-md">
                                                <p class="text-sm text-gray-700">
                                                    <?= htmlspecialchars($order_details['notes']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Order Items -->
                                <div class="mt-8">
                                    <h4 class="text-sm font-medium text-gray-500">Order Items</h4>
                                    <div class="mt-4 overflow-hidden border border-gray-200 rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($order_items as $item): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            ₱<?= number_format($item['unit_price'], 2) ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?= $item['quantity'] ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            ₱<?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm text-gray-500">
                                                            <?= !empty($item['notes']) ? htmlspecialchars($item['notes']) : '—' ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="bg-gray-50">
                                                <tr>
                                                    <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-500">Total</td>
                                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                                        ₱<?= number_format($order_details['total'], 2) ?>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- Status Update Form -->
                                <div class="mt-8 pt-5 border-t border-gray-200">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="order_id" value="<?= $order_details['order_id'] ?>">

                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                            <div class="w-full sm:w-64">
                                                <label for="status" class="block text-sm font-medium text-gray-700">Update Order Status</label>
                                                <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                                    <option value="Pending" <?= $order_details['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Processing" <?= $order_details['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                    <option value="Ready" <?= $order_details['status'] === 'Ready' ? 'selected' : '' ?>>Ready</option>
                                                    <option value="Completed" <?= $order_details['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="Cancelled" <?= $order_details['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                            </div>
                                            <div class="mt-4 sm:mt-6 sm:ml-4">
                                                <button type="submit" name="update_status" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    <i class="fas fa-save mr-2"></i> Update Status
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Orders List View -->
                        <?php if (empty($orders)): ?>
                            <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6 text-center">
                                <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900">No orders found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    <?= $status_filter === 'all' ? 'There are currently no orders.' : "No orders with status '{$status_filter}'." ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="bg-white shadow overflow-hidden sm:rounded-lg animate-fade-in">
                                <div class="overflow-x-auto scrollbar">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($orders as $order): ?>
                                                <tr class="hover:bg-gray-50 transition">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        #<?= $order['order_id'] ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?= htmlspecialchars($order['order_type']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                                            <i class="fas fa-circle"></i>
                                                            <?= $order['status'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        ₱<?= number_format($order['total'], 2) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <a href="orders.php?id=<?= $order['order_id'] ?>" class="text-primary-600 hover:text-primary-700">
                                                            <i class="fas fa-eye mr-1"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
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
    </script>
</body>

</html>