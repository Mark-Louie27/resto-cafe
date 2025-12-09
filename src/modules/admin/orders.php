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

$page_title = "Orders";
$current_page = "orders";
include __DIR__ . '/include/header.php';

// Handle order status updates and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: orders.php');
        exit();
    }

    if (isset($_POST['update_status'])) {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

        if ($order_id && $new_status) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);

            if ($stmt->execute()) {
                log_event($_SESSION['user_id'], 'order_update', "Updated order #$order_id to $new_status");
                set_flash_message("Order #$order_id status updated successfully", 'success');
            } else {
                set_flash_message("Failed to update order status", 'error');
            }
        } else {
            set_flash_message("Invalid order ID or status", 'error');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_order') {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

        if ($order_id) {
            // Delete order items first (assuming foreign key constraints)
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            // Delete the order
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);

            if ($stmt->execute()) {
                log_event($_SESSION['user_id'], 'order_delete', "Deleted order #$order_id");
                set_flash_message("Order #$order_id deleted successfully", 'success');
            } else {
                set_flash_message("Failed to delete order", 'error');
            }
        } else {
            set_flash_message("Invalid order ID", 'error');
        }
    }
    header('Location: orders.php');
    exit();
}

// Get all orders with pagination and filters
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Build the query with filters
$status_filter = isset($_GET['status']) ? filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) : '';
$order_type_filter = isset($_GET['order_type']) ? filter_input(INPUT_GET, 'order_type', FILTER_SANITIZE_STRING) : '';
$date_from_filter = isset($_GET['date_from']) ? filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_STRING) : '';

$query = "SELECT o.*, 
          u.first_name, u.last_name, 
          s.first_name as staff_first, s.last_name as staff_last,
          t.table_number
          FROM orders o
          LEFT JOIN customers c ON o.customer_id = c.customer_id
          LEFT JOIN users u ON c.user_id = u.user_id
          LEFT JOIN staff ON o.staff_id = staff.staff_id
          LEFT JOIN users s ON staff.user_id = s.user_id
          LEFT JOIN restaurant_tables t ON o.table_id = t.table_id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($status_filter)) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($order_type_filter)) {
    $query .= " AND o.order_type = ?";
    $params[] = $order_type_filter;
    $types .= 's';
}

if (!empty($date_from_filter)) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from_filter;
    $types .= 's';
}

$total_orders_query = "SELECT COUNT(*) FROM orders WHERE 1=1";
$total_orders_params = [];
$total_orders_types = '';

if (!empty($status_filter)) {
    $total_orders_query .= " AND status = ?";
    $total_orders_params[] = $status_filter;
    $total_orders_types .= 's';
}

if (!empty($order_type_filter)) {
    $total_orders_query .= " AND order_type = ?";
    $total_orders_params[] = $order_type_filter;
    $total_orders_types .= 's';
}

if (!empty($date_from_filter)) {
    $total_orders_query .= " AND DATE(created_at) >= ?";
    $total_orders_params[] = $date_from_filter;
    $total_orders_types .= 's';
}

$stmt = $conn->prepare($total_orders_query);
if (!empty($total_orders_params)) {
    $stmt->bind_param($total_orders_types, ...$total_orders_params);
}
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_orders / $items_per_page);

$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
$order_items = [];

// Fetch orders and their totals, and preload order items
while ($order = $orders_result->fetch_assoc()) {
    // Calculate total for each order
    $total = fetch_value("SELECT SUM(oi.quantity * oi.unit_price) FROM order_items oi WHERE oi.order_id = ?", [$order['order_id']]) ?? 0;
    $order['total_calculated'] = $total;

    // Fetch order items for each order
    $stmt = $conn->prepare("SELECT oi.*, m.name 
                           FROM order_items oi 
                           LEFT JOIN items m ON oi.item_id = m.item_id 
                           WHERE oi.order_id = ?");
    $stmt->bind_param("i", $order['order_id']);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $order_items[$order['order_id']] = $items;

    $orders[] = $order;
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

        /* Modal backdrop */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body class="bg-white-50 font-sans h-full">
    <div class="flex h-full">
        <!-- Sidebar -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-amber-900">Order Management</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-amber-100">
                                <i class="fas fa-bell text-amber-600"></i>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-500 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-amber-900 font-medium"><?= htmlspecialchars($admin['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-amber-600"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-amber-100" id="userMenu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-white-50">
                <?php display_flash_message(); ?>

                <!-- Order Filters -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-amber-900 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Processing" <?= $status_filter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="Ready" <?= $status_filter === 'Ready' ? 'selected' : '' ?>>Ready</option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="order_type" class="block text-sm font-medium text-amber-900 mb-1">Order Type</label>
                            <select id="order_type" name="order_type" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="">All Types</option>
                                <option value="Dine-in" <?= $order_type_filter === 'Dine-in' ? 'selected' : '' ?>>Dine-in</option>
                                <option value="Takeout" <?= $order_type_filter === 'Takeout' ? 'selected' : '' ?>>Takeout</option>
                                <option value="Delivery" <?= $order_type_filter === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                            </select>
                        </div>
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-amber-900 mb-1">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from_filter) ?>" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end mb-6 space-x-2">
                    <button onclick="window.print()" class="bg-amber-200 hover:bg-amber-300 text-amber-900 py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                        </svg>
                        Print
                    </button>
                    <a href="order_export.php" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Export
                    </a>
                </div>

                <!-- Orders Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Order ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Staff</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Table</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Date/Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Total</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-amber-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-amber-900">#<?= htmlspecialchars($order['order_id']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $order['staff_first'] ? htmlspecialchars($order['staff_first'] . ' ' . $order['staff_last']) : 'Not Assigned' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $order['order_type'] === 'Dine-in' ? 'bg-green-100 text-green-800' : ($order['order_type'] === 'Takeout' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') ?>">
                                                <?= htmlspecialchars($order['order_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $order['table_number'] ? htmlspecialchars($order['table_number']) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <select name="new_status" onchange="this.form.submit()"
                                                    class="text-sm border rounded p-1 
                                                        <?= $order['status'] === 'Completed' ? 'bg-green-100 text-green-800' : ($order['status'] === 'Cancelled' ? 'bg-red-100 text-red-800' : ($order['status'] === 'Processing' ? 'bg-yellow-100 text-yellow-800' : ($order['status'] === 'Ready' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                                    <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Processing" <?= $order['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                    <option value="Ready" <?= $order['status'] === 'Ready' ? 'selected' : '' ?>>Ready</option>
                                                    <option value="Completed" <?= $order['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            $<?= number_format($order['total_calculated'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick='openViewModal(<?= json_encode($order) ?>)' class="text-amber-600 hover:text-amber-900 mr-3">View</button>
                                            <button onclick="openDeleteModal(<?= $order['order_id'] ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <?php if (empty($orders)): ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-amber-900">No orders found</h3>
                        <p class="mt-1 text-sm text-amber-700">There are no orders matching the selected filters.</p>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between mt-6">
                        <div>
                            <p class="text-sm text-amber-700">
                                Showing <span class="font-medium"><?= $offset + 1 ?></span> to
                                <span class="font-medium"><?= min($offset + $items_per_page, $total_orders) ?></span> of
                                <span class="font-medium"><?= $total_orders ?></span> orders
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?= $current_page - 1 ?>&status=<?= urlencode($status_filter) ?>&order_type=<?= urlencode($order_type_filter) ?>&date_from=<?= urlencode($date_from_filter) ?>" class="px-4 py-2 border border-amber-200 rounded-md text-sm font-medium text-amber-600 hover:bg-amber-50">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&order_type=<?= urlencode($order_type_filter) ?>&date_from=<?= urlencode($date_from_filter) ?>" class="<?= $i === $current_page ? 'bg-amber-100 text-amber-900' : 'border border-amber-200 text-amber-600 hover:bg-amber-50' ?> px-4 py-2 rounded-md text-sm font-medium">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page + 1 ?>&status=<?= urlencode($status_filter) ?>&order_type=<?= urlencode($order_type_filter) ?>&date_from=<?= urlencode($date_from_filter) ?>" class="px-4 py-2 border border-amber-200 rounded-md text-sm font-medium text-amber-600 hover:bg-amber-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="viewModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button id="closeViewModal" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Order Details</h2>
            <div class="space-y-3 text-amber-900">
                <p><strong>Order ID:</strong> <span id="viewOrderId"></span></p>
                <p><strong>Customer:</strong> <span id="viewCustomer"></span></p>
                <p><strong>Staff:</strong> <span id="viewStaff"></span></p>
                <p><strong>Order Type:</strong> <span id="viewOrderType"></span></p>
                <p><strong>Table:</strong> <span id="viewTable"></span></p>
                <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                <p><strong>Date/Time:</strong> <span id="viewDateTime"></span></p>
                <p><strong>Total:</strong> <span id="viewTotal"></span></p>
                <p><strong>Items:</strong></p>
                <ul id="viewItems" class="list-disc pl-5 text-amber-700"></ul>
            </div>
            <div class="mt-6 flex justify-end">
                <button id="closeViewModalBtn" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative">
            <button id="closeDeleteModal" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Confirm Deletion</h2>
            <p class="text-amber-700 mb-6">Are you sure you want to delete this order? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete_order">
                <input type="hidden" name="order_id" id="deleteOrderId">
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelDeleteModal" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preload order items data
        const orderItems = <?= json_encode($order_items) ?>;

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

        // View Modal
        const viewModal = document.getElementById('viewModal');
        const closeViewModal = document.getElementById('closeViewModal');
        const closeViewModalBtn = document.getElementById('closeViewModalBtn');

        function openViewModal(order) {
            document.getElementById('viewOrderId').textContent = `#${order.order_id}`;
            document.getElementById('viewCustomer').textContent = `${order.first_name} ${order.last_name}`;
            document.getElementById('viewStaff').textContent = order.staff_first ? `${order.staff_first} ${order.staff_last}` : 'Not Assigned';
            document.getElementById('viewOrderType').textContent = order.order_type;
            document.getElementById('viewTable').textContent = order.table_number || 'N/A';
            document.getElementById('viewStatus').textContent = order.status;
            document.getElementById('viewDateTime').textContent = new Date(order.created_at).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
            document.getElementById('viewTotal').textContent = `$${Number(order.total_calculated).toFixed(2)}`;

            // Display order items
            const itemsList = document.getElementById('viewItems');
            itemsList.innerHTML = '';
            const items = orderItems[order.order_id] || [];
            if (items.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'No items found for this order.';
                itemsList.appendChild(li);
            } else {
                items.forEach(item => {
                    const li = document.createElement('li');
                    li.textContent = `${item.name || 'Unknown Item'} (Qty: ${item.quantity}, $${Number(item.unit_price).toFixed(2)})`;
                    itemsList.appendChild(li);
                });
            }

            viewModal.classList.remove('hidden');
        }

        closeViewModal.addEventListener('click', () => viewModal.classList.add('hidden'));
        closeViewModalBtn.addEventListener('click', () => viewModal.classList.add('hidden'));

        // Delete Modal
        const deleteModal = document.getElementById('deleteModal');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelDeleteModal = document.getElementById('cancelDeleteModal');

        function openDeleteModal(orderId) {
            document.getElementById('deleteOrderId').value = orderId;
            deleteModal.classList.remove('hidden');
        }

        closeDeleteModal.addEventListener('click', () => deleteModal.classList.add('hidden'));
        cancelDeleteModal.addEventListener('click', () => deleteModal.classList.add('hidden'));

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target === viewModal) viewModal.classList.add('hidden');
            if (e.target === deleteModal) deleteModal.classList.add('hidden');
        });
    </script>
</body>

</html>