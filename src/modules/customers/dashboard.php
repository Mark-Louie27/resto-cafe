<?php
// Secure session and authentication
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php';
require_once __DIR__ . '/../../controller/MenuController.php';
require_once __DIR__ . '/../../controller/CustomerController.php';

require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// Get user data
$user = get_user_by_id($user_id);
$customer = get_customer_data($user_id);
$orders = get_recent_orders($user_id, 5);

// Handle reservation actions (edit and cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: dashboard.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // Edit Reservation
        if ($action === 'edit_reservation') {
            $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
            $reservation_date = filter_input(INPUT_POST, 'reservation_date', FILTER_SANITIZE_STRING);
            $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
            $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
            $party_size = filter_input(INPUT_POST, 'party_size', FILTER_VALIDATE_INT);
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

            // Validate inputs
            if ($reservation_id && $reservation_date && $start_time && $end_time && $party_size) {
                // Ensure reservation date is in the future
                $today = date('Y-m-d');
                if ($reservation_date < $today) {
                    set_flash_message('Reservation date must be in the future.', 'error');
                    header('Location: dashboard.php');
                    exit();
                }

                // Validate time slot against business hours
                $day_of_week = strtolower(date('l', strtotime($reservation_date)));
                $stmt = $conn->prepare("SELECT open_time, close_time, is_closed FROM business_hours WHERE day_of_week = ?");
                $stmt->bind_param("s", $day_of_week);
                $stmt->execute();
                $business_hours = $stmt->get_result()->fetch_assoc();

                if ($business_hours['is_closed']) {
                    set_flash_message('The restaurant is closed on ' . ucfirst($day_of_week) . '.', 'error');
                    header('Location: dashboard.php');
                    exit();
                }

                $open_time = $business_hours['open_time'];
                $close_time = $business_hours['close_time'];
                if ($start_time < $open_time || $end_time > $close_time || $start_time >= $end_time) {
                    set_flash_message("Reservation time must be between $open_time and $close_time, and start time must be before end time.", 'error');
                    header('Location: dashboard.php');
                    exit();
                }

                // Update reservation
                $stmt = $conn->prepare("UPDATE reservations SET reservation_date = ?, start_time = ?, end_time = ?, party_size = ?, notes = ? WHERE reservation_id = ? AND customer_id = ?");
                $customer_id = get_customer_id_from_user_id($user_id);
                $stmt->bind_param("sssisis", $reservation_date, $start_time, $end_time, $party_size, $notes, $reservation_id, $customer_id);

                if ($stmt->execute()) {
                    log_event($user_id, 'reservation_update', "Customer updated reservation #$reservation_id");
                    set_flash_message('Reservation updated successfully.', 'success');
                } else {
                    set_flash_message('Failed to update reservation: ' . $stmt->error, 'error');
                }
            } else {
                set_flash_message('Invalid input data.', 'error');
            }
        }

        // Cancel Reservation
        if ($action === 'cancel_reservation') {
            $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
            if ($reservation_id) {
                $customer_id = get_customer_id_from_user_id($user_id);
                $stmt = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ? AND customer_id = ? AND reservation_date >= CURDATE()");
                $stmt->bind_param("ii", $reservation_id, $customer_id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        log_event($user_id, 'reservation_cancel', "Customer cancelled reservation #$reservation_id");
                        set_flash_message('Reservation cancelled successfully.', 'success');
                    } else {
                        set_flash_message('Cannot cancel past reservations or reservation not found.', 'error');
                    }
                } else {
                    set_flash_message('Failed to cancel reservation: ' . $stmt->error, 'error');
                }
            } else {
                set_flash_message('Invalid reservation ID.', 'error');
            }
        }

        header('Location: dashboard.php');
        exit();
    }
}

$reservations = get_upcoming_reservations($user_id);

$page_title = "Dashboard";
$current_page = "dashboard";

$is_home = false;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Dashboard - Casa Baraka</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Modal backdrop */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                z-index: 40;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                overflow-y: auto;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 30;
                display: none;
            }

            .sidebar-overlay.open {
                display: block;
            }
        }

        @media (max-width: 640px) {
            .quick-actions a {
                padding: 0.75rem;
            }

            .dashboard-card {
                padding: 1rem;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <?php display_flash_message(); ?>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Sidebar - moves to top on mobile -->
            <div class="lg:w-1/4 order-1 lg:order-none">
                <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 sticky top-24">
                    <!-- Profile section -->
                    <div class="text-center mb-4 lg:mb-6">
                        <div class="w-16 h-16 lg:w-24 lg:h-24 bg-gradient-to-br from-amber-100 to-amber-200 rounded-full mx-auto mb-2 lg:mb-4 flex items-center justify-center shadow-inner">
                            <i class="fas fa-user text-amber-600 text-xl lg:text-3xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                        <p class="text-gray-500 text-sm">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                        <div class="mt-2 bg-amber-100 text-amber-800 text-xs font-medium px-2.5 py-0.5 rounded-full inline-block">
                            <?= htmlspecialchars($customer['membership_level']) ?> member
                        </div>
                    </div>

                    <nav class="space-y-1">
                        <a href="dashboard.php" class="flex items-center sidebar-link py-2 px-4 bg-amber-50 text-amber-700 rounded-lg font-medium">
                            <i class="fas fa-tachometer-alt mr-3 text-amber-600"></i> Dashboard
                        </a>
                        <a href="profile.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-user mr-3 text-gray-500"></i> My Profile
                        </a>
                        <a href="orders.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-receipt mr-3 text-gray-500"></i> My Orders
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
            <div class="lg:w-3/4 order-2 lg:order-none">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 rounded-lg shadow p-6 text-white mb-6">
                    <h1 class="text-2xl font-bold mb-2">Welcome back, <?= htmlspecialchars($user['first_name']) ?>!</h1>
                    <p class="mb-4">You have <?= $customer['loyalty_points'] ?> loyalty points</p>
                    <a href="order.php" class="inline-block bg-white text-amber-600 px-4 py-2 rounded-md font-medium hover:bg-gray-100">
                        Place New Order
                    </a>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <a href="orders.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition">
                        <div class="text-amber-600 text-3xl mb-2"><i class="fas fa-utensils"></i></div>
                        <h3 class="font-medium">Order Food</h3>
                    </a>
                    <a href="reservation.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition">
                        <div class="text-amber-600 text-3xl mb-2"><i class="fas fa-calendar-plus"></i></div>
                        <h3 class="font-medium">Make Reservation</h3>
                    </a>
                    <a href="favorites.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition">
                        <div class="text-amber-600 text-3xl mb-2"><i class="fas fa-heart"></i></div>
                        <h3 class="font-medium">My Favorites</h3>
                    </a>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Recent Orders</h2>
                        <a href="orders.php" class="text-amber-600 hover:text-amber-500 text-sm">View All</a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <p class="text-gray-600">You haven't placed any orders yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">Order #</th>
                                        <th class="text-left py-2">Date</th>
                                        <th class="text-left py-2">Status</th>
                                        <th class="text-right py-2">Total</th>
                                        <th class="text-right py-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3">#<?= $order['order_id'] ?></td>
                                            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                            <td>
                                                <span class="px-2 py-1 rounded-full text-xs 
                                                <?= $order['status'] === 'Completed' ? 'bg-green-100 text-green-800' : ($order['status'] === 'Processing' ? 'bg-blue-100 text-blue-800' :
                                                    'bg-yellow-100 text-yellow-800') ?>">
                                                    <?= $order['status'] ?>
                                                </span>
                                            </td>
                                            <td class="text-right">$<?= number_format($order['total'], 2) ?></td>
                                            <td class="text-right">
                                                <a href="/modules/customers/orders.php?id=<?= $order['order_id'] ?>" class="text-amber-600 hover:text-amber-500">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cart Modal (only for logged-in users) -->
                <?php if (is_logged_in()): ?>
                    <div id="cartModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
                        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
                            <button onclick="toggleModal('cartModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                                <i class="fas fa-shopping-cart mr-2"></i> Your Cart
                            </h2>
                            <?php
                            $cart = get_cart();
                            if (empty($cart)):
                            ?>
                                <p class="text-gray-600">Your cart is empty.</p>
                            <?php else: ?>
                                <div class="space-y-4 max-h-96 overflow-y-auto">
                                    <?php
                                    $total = 0;
                                    foreach ($cart as $item_id => $item):
                                        $subtotal = $item['price'] * $item['quantity'];
                                        $total += $subtotal;
                                    ?>
                                        <div class="flex justify-between items-center p-3 bg-amber-50 rounded-lg">
                                            <div>
                                                <p class="text-sm font-medium text-amber-600"><?php echo htmlspecialchars($item['name']); ?></p>
                                                <p class="text-xs text-gray-500">$<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></p>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <form method="POST" action="index.php" class="flex items-center">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="update_cart">
                                                    <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="w-16 px-2 py-1 border border-amber-200 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                                    <button type="submit" class="ml-2 text-amber-600 hover:text-amber-700">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="index.php">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="remove_from_cart">
                                                    <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-700">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 border-t border-amber-100 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-semibold text-amber-600">Total:</span>
                                        <span class="text-lg font-bold text-amber-600">$<?php echo number_format($total, 2); ?></span>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <form method="POST" action="modules/customers/checkout.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-full transition duration-300">Proceed to Checkout</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Upcoming Reservations -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Upcoming Reservations</h2>
                        <a href="reservations.php" class="text-amber-600 hover:text-amber-500 text-sm">View All</a>
                    </div>

                    <?php if (empty($reservations)): ?>
                        <p class="text-gray-600">You don't have any upcoming reservations.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($reservations as $reservation): ?>
                                <div class="border rounded-lg p-4 hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium">Table #<?= htmlspecialchars($reservation['table_number']) ?></h3>
                                            <p class="text-gray-600">
                                                <?= date('D, M j', strtotime($reservation['reservation_date'])) ?>
                                                at <?= date('g:i A', strtotime($reservation['start_time'])) ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                Party of <?= $reservation['party_size'] ?> •
                                                <span class="<?= $reservation['status'] === 'Confirmed' ? 'text-green-600' : ($reservation['status'] === 'Pending' ? 'text-yellow-600' : 'text-blue-600') ?>">
                                                    <?= htmlspecialchars($reservation['status']) ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button onclick='openViewModal(<?= json_encode($reservation) ?>)' class="text-amber-600 hover:text-amber-500">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode($reservation) ?>)' class="text-blue-600 hover:text-blue-500">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick='openCancelModal(<?= $reservation['reservation_id'] ?>)' class="text-red-600 hover:text-red-500">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Reservation Modal -->
    <div id="viewReservationModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('viewReservationModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-calendar-alt mr-2"></i> Reservation Details
            </h2>
            <div class="space-y-3 text-gray-700">
                <p><strong>Table:</strong> <span id="viewTable"></span></p>
                <p><strong>Date:</strong> <span id="viewDate"></span></p>
                <p><strong>Time:</strong> <span id="viewTime"></span></p>
                <p><strong>Party Size:</strong> <span id="viewPartySize"></span></p>
                <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                <p><strong>Notes:</strong> <span id="viewNotes"></span></p>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="toggleModal('viewReservationModal')" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Reservation Modal -->
    <div id="editReservationModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('editReservationModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit Reservation
            </h2>
            <form id="editReservationForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="edit_reservation">
                <input type="hidden" name="reservation_id" id="editReservationId">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="edit_reservation_date" class="block text-sm font-medium text-gray-700">Reservation Date</label>
                        <input type="date" id="edit_reservation_date" name="reservation_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_start_time" class="block text-sm font-medium text-gray-700">Start Time</label>
                            <input type="time" id="edit_start_time" name="start_time" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="edit_end_time" class="block text-sm font-medium text-gray-700">End Time</label>
                            <input type="time" id="edit_end_time" name="end_time" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit_party_size" class="block text-sm font-medium text-gray-700">Party Size</label>
                        <input type="number" id="edit_party_size" name="party_size" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="edit_notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('editReservationModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Reservation Modal -->
    <div id="cancelReservationModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative">
            <button onclick="toggleModal('cancelReservationModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> Cancel Reservation
            </h2>
            <p class="text-gray-700 mb-6">Are you sure you want to cancel this reservation? This action cannot be undone.</p>
            <form id="cancelReservationForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="cancel_reservation">
                <input type="hidden" name="reservation_id" id="cancelReservationId">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('cancelReservationModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md transition duration-300">
                        No, Keep It
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                        Yes, Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        });

        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('open');
            this.classList.remove('open');
        });

        // Keep your existing modal toggle functions
        window.toggleModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.toggle('hidden');
            }
        };

        // Mobile menu toggle
        (function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // Modal toggle function
            window.toggleModal = function(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.toggle('hidden');
                } else {
                    console.warn(`Modal with ID ${modalId} not found.`);
                }
            };

            // Open Cart Modal (only available for logged-in users)
            window.openCartModal = function() {
                <?php if (is_logged_in()): ?>
                    toggleModal('cartModal');
                <?php else: ?>
                    window.location.href = 'modules/auth/login.php';
                <?php endif; ?>
            };

            // Open View Reservation Modal
            window.openViewModal = function(reservation) {
                document.getElementById('viewTable').textContent = reservation.table_number;
                document.getElementById('viewDate').textContent = new Date(reservation.reservation_date).toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });
                document.getElementById('viewTime').textContent = `${new Date('1970-01-01T' + reservation.start_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true })} - ${new Date('1970-01-01T' + reservation.end_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true })}`;
                document.getElementById('viewPartySize').textContent = reservation.party_size;
                document.getElementById('viewStatus').textContent = reservation.status;
                document.getElementById('viewNotes').textContent = reservation.notes || 'None';
                toggleModal('viewReservationModal');
            };

            // Open Edit Reservation Modal
            window.openEditModal = function(reservation) {
                document.getElementById('editReservationId').value = reservation.reservation_id;
                document.getElementById('edit_reservation_date').value = reservation.reservation_date;
                document.getElementById('edit_start_time').value = reservation.start_time;
                document.getElementById('edit_end_time').value = reservation.end_time;
                document.getElementById('edit_party_size').value = reservation.party_size;
                document.getElementById('edit_notes').value = reservation.notes || '';
                toggleModal('editReservationModal');
            };

            // Open Cancel Reservation Modal
            window.openCancelModal = function(reservationId) {
                document.getElementById('cancelReservationId').value = reservationId;
                toggleModal('cancelReservationModal');
            };

            // Category filter functionality (not used in dashboard, but keeping for consistency)
            document.addEventListener('DOMContentLoaded', function() {
                const categoryButtons = document.querySelectorAll('.category-btn');
                const menuItems = document.querySelectorAll('.menu-card');
                const loadingSpinner = document.getElementById('loadingSpinner');

                if (categoryButtons && menuItems && loadingSpinner) {
                    categoryButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const category = button.dataset.category;

                            // Update button styles
                            categoryButtons.forEach(btn => {
                                btn.classList.remove('bg-amber-600', 'text-white');
                                btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');
                            });
                            button.classList.add('bg-amber-600', 'text-white');
                            button.classList.remove('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');

                            // Show loading spinner
                            loadingSpinner.classList.remove('hidden');
                            menuItems.forEach(item => item.classList.add('opacity-50'));

                            // Simulate loading delay
                            setTimeout(() => {
                                menuItems.forEach(item => {
                                    const itemCategory = item.dataset.category;
                                    if (category === 'all' || (category === itemCategory) || (category !== 'uncategorized' && itemCategory === 'uncategorized' && !itemCategory)) {
                                        item.style.display = 'block';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                    item.classList.remove('opacity-50');
                                });
                                loadingSpinner.classList.add('hidden');
                            }, 300);
                        });
                    });
                }

                // Back to top button
                const backToTopButton = document.getElementById('backToTop');
                if (backToTopButton) {
                    window.addEventListener('scroll', () => {
                        if (window.pageYOffset > 300) {
                            backToTopButton.classList.remove('opacity-0', 'invisible');
                            backToTopButton.classList.add('opacity-100', 'visible');
                        } else {
                            backToTopButton.classList.remove('opacity-100', 'visible');
                            backToTopButton.classList.add('opacity-0', 'invisible');
                        }
                    });

                    backToTopButton.addEventListener('click', () => {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    });
                }

                // Smooth scrolling for anchor links
                document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        if (targetId === '#') return;
                        const targetElement = document.querySelector(targetId);
                        if (targetElement) {
                            targetElement.scrollIntoView({
                                behavior: 'smooth'
                            });
                        }
                    });
                });

                // Close modals when clicking outside
                document.addEventListener('click', (e) => {
                    const modals = ['cartModal', 'viewReservationModal', 'editReservationModal', 'cancelReservationModal'];
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && e.target === modal) {
                            modal.classList.add('hidden');
                        }
                    });
                });
            });
        })();
    </script>
</body>

</html>