<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php';
require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

$user = get_user_by_id($user_id);

// Get customer ID
$customer_id = get_customer_id($user_id);

$customer = get_customer_data($user_id);

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token.', 'error');
        header('Location: orders.php');
        exit();
    }

    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if (!$order_id) {
        set_flash_message('Invalid order ID.', 'error');
        header('Location: orders.php');
        exit();
    }

    // Check if the order belongs to the customer and is cancellable
    $stmt = $conn->prepare("SELECT status, table_id FROM orders WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $order_id, $customer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        set_flash_message('Order not found or you do not have permission to cancel it.', 'error');
        header('Location: orders.php');
        exit();
    }

    if ($order['status'] !== 'Pending' && $order['status'] !== 'Processing') {
        set_flash_message('Only orders in Pending or Processing status can be cancelled.', 'error');
        header('Location: orders.php');
        exit();
    }

    // Update order status to Cancelled
    $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        error_log('Failed to cancel order: ' . $stmt->error);
        set_flash_message('Failed to cancel order. Please try again.', 'error');
        $stmt->close();
        header('Location: orders.php');
        exit();
    }
    $stmt->close();

    // If the order has a table_id (Dine-in), set the table status back to Available
    if ($order['table_id'] !== null) {
        $stmt = $conn->prepare("UPDATE restaurant_tables SET status = 'Available' WHERE table_id = ?");
        $stmt->bind_param("i", $order['table_id']);
        $stmt->execute();
        $stmt->close();
    }

    set_flash_message('Order #' . $order_id . ' has been cancelled.', 'success');
    header('Location: orders.php');
    exit();
}

$orders = get_customer_orders($customer_id);


$page_title = "Orders";
$current_page = "orders";

$is_home = false;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Casa Baraka</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-card {
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-processing {
            background-color: #bfdbfe;
            color: #1e40af;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .details-section {
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
            overflow: hidden;
        }

        .details-section[aria-hidden="true"] {
            max-height: 0;
            opacity: 0;
        }

        .details-section[aria-hidden="false"] {
            max-height: 1000px;
            /* Adjust based on content size */
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar -->
            <!-- Sidebar -->
            <div class="md:w-1/4">
                <div class="bg-white rounded-xl shadow-md p-6 sticky top-24">
                    <div class="text-center mb-6">
                        <div class="w-24 h-24 bg-gradient-to-br from-amber-100 to-amber-200 rounded-full mx-auto mb-4 flex items-center justify-center shadow-inner">
                            <i class="fas fa-user text-amber-600 text-3xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                        <p class="text-gray-500 text-sm">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                        <div class="mt-2 bg-amber-100 text-amber-800 text-xs font-medium px-2.5 py-0.5 rounded-full inline-block">
                            <?= htmlspecialchars($customer['membership_level']) ?> member
                        </div>
                    </div>

                    <nav class="space-y-1">
                        <a href="dashboard.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-tachometer-alt mr-3 text-gray-500"></i> Dashboard
                        </a>
                        <a href="profile.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-user mr-3 text-gray-500"></i> My Profile
                        </a>
                        <a href="orders.php" class="flex items-center sidebar-link py-2 px-4 bg-amber-50 text-amber-700 rounded-lg font-medium">
                            <i class="fas fa-receipt mr-3 text-amber-600"></i> My Orders
                        </a>
                        <a href="reservation.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-calendar-alt mr-3 text-gray-500"></i> Reservations
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
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-2xl font-bold">My Orders</h1>
                                <p class="opacity-90">View your order history</p>
                            </div>
                            <a href="../pages/menu.php" class="bg-white text-amber-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition">
                                <i class="fas fa-plus mr-2"></i> New Order
                            </a>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">
                        <?php display_flash_message(); ?>

                        <?php if (empty($orders)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 mb-2">No orders yet</h3>
                                <p class="text-gray-500 mb-4">You haven't placed any orders yet.</p>
                                <a href="../pages/menu.php" class="inline-block bg-amber-600 hover:bg-amber-500 text-white font-medium py-2 px-6 rounded-lg transition">
                                    Browse Menu
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($orders as $order): ?>
                                    <?php $items = get_order_items($order['order_id']); ?>
                                    <div class="order-card bg-white border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="p-4 border-b flex justify-between items-center">
                                            <div>
                                                <div class="flex items-center space-x-4">
                                                    <h3 class="font-bold text-gray-800">Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                                                    <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                                        <?= htmlspecialchars($order['status']) ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                                                    • <?= htmlspecialchars($order['order_type']) ?>
                                                    • <?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?>
                                                </p>
                                            </div>
                                            <div class="text-right flex items-center space-x-3">
                                                <p class="font-bold text-gray-800">$<?= number_format($order['order_total'], 2) ?></p>
                                                <button onclick="toggleDetails('order-details-<?= $order['order_id'] ?>')" class="text-sm text-amber-600 hover:text-amber-500 flex items-center">
                                                    <span>Details</span>
                                                    <i class="fas fa-chevron-down ml-1 transition-transform" id="chevron-<?= $order['order_id'] ?>"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Order Details Section -->
                                        <div id="order-details-<?= $order['order_id'] ?>" class="details-section bg-gray-50 p-4" aria-hidden="true">
                                            <!-- Order Items -->
                                            <div class="mb-4">
                                                <h4 class="font-semibold text-gray-700 mb-2">Items</h4>
                                                <div class="space-y-3">
                                                    <?php foreach ($items as $item): ?>
                                                        <div class="flex items-center space-x-3">
                                                            <div class="w-12 h-12 bg-gray-100 rounded-lg overflow-hidden">
                                                                <?php if ($item['image_url']): ?>
                                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                                                                <?php else: ?>
                                                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                        <i class="fas fa-utensils"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex-1">
                                                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($item['name'] ?? 'Unknown Item') ?></p>
                                                                <p class="text-sm text-gray-500">
                                                                    Quantity: <?= $item['quantity'] ?> • $<?= number_format($item['unit_price'], 2) ?> each
                                                                </p>
                                                            </div>
                                                            <p class="text-sm font-medium text-gray-800">
                                                                $<?= number_format($item['quantity'] * $item['unit_price'], 2) ?>
                                                            </p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Order Summary -->
                                            <div class="border-t pt-4">
                                                <h4 class="font-semibold text-gray-700 mb-2">Order Summary</h4>
                                                <div class="space-y-2 text-sm text-gray-700">
                                                    <div class="flex justify-between">
                                                        <span>Items Subtotal:</span>
                                                        <span>$<?= number_format($order['items_subtotal'], 2) ?></span>
                                                    </div>
                                                    <?php if ($order['order_type'] === 'Delivery' && $order['delivery_fee'] > 0): ?>
                                                        <div class="flex justify-between">
                                                            <span>Delivery Fee:</span>
                                                            <span>$<?= number_format($order['delivery_fee'], 2) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php
                                                    $discount = $order['items_subtotal'] + ($order['delivery_fee'] ?? 0) - $order['order_total'];
                                                    if ($discount > 0):
                                                    ?>
                                                        <div class="flex justify-between text-green-600">
                                                            <span>Discount (Loyalty Points):</span>
                                                            <span>-$<?= number_format($discount, 2) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex justify-between font-semibold text-gray-800">
                                                        <span>Total:</span>
                                                        <span>$<?= number_format($order['order_total'], 2) ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Additional Details -->
                                            <div class="mt-4">
                                                <h4 class="font-semibold text-gray-700 mb-2">Additional Details</h4>
                                                <div class="space-y-2 text-sm text-gray-700">
                                                    <?php if ($order['order_type'] === 'Dine-in' && $order['table_number']): ?>
                                                        <p><strong>Table:</strong> #<?= htmlspecialchars($order['table_number']) ?></p>
                                                    <?php elseif ($order['order_type'] === 'Delivery' && $order['delivery_address']): ?>
                                                        <p><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($order['payment_status']): ?>
                                                        <p><strong>Payment Status:</strong>
                                                            <span class="capitalize <?= $order['payment_status'] === 'Completed' ? 'text-green-600' : 'text-yellow-600' ?>">
                                                                <?= htmlspecialchars($order['payment_status']) ?>
                                                            </span>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Order Items Preview -->
                                        <div class="p-4 border-t">
                                            <div class="flex overflow-x-auto space-x-4 pb-2">
                                                <?php foreach (array_slice($items, 0, 5) as $item): ?>
                                                    <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded-lg overflow-hidden">
                                                        <?php if ($item['image_url']): ?>
                                                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                                <i class="fas fa-utensils"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (count($items) > 5): ?>
                                                    <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500">
                                                        +<?= count($items) - 5 ?> more
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Order Actions -->
                                        <div class="bg-gray-50 px-4 py-3 flex justify-between items-center border-t">
                                            <?php if ($order['status'] === 'Pending' || $order['status'] === 'Processing'): ?>
                                                <form method="POST" action="orders.php" onsubmit="return confirm('Are you sure you want to cancel Order #<?= $order['order_id'] ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="cancel_order">
                                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                    <button type="submit" class="text-sm text-red-600 hover:text-red-500">
                                                        <i class="fas fa-times mr-1"></i> Cancel Order
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div></div>
                                            <?php endif; ?>

                                            <a href="reorder.php?id=<?= $order['order_id'] ?>" class="text-sm bg-amber-100 text-amber-700 px-3 py-1 rounded-lg hover:bg-amber-200 transition">
                                                <i class="fas fa-redo mr-1"></i> Reorder
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    <script>
        function toggleDetails(sectionId) {
            const section = document.getElementById(sectionId);
            const chevron = document.getElementById(`chevron-${sectionId.split('-')[2]}`);
            const isHidden = section.getAttribute('aria-hidden') === 'true';

            section.setAttribute('aria-hidden', !isHidden);
            chevron.classList.toggle('rotate-180', !isHidden);
        }
    </script>
</body>

</html>