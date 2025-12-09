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

$page_title = "Reservations";
$current_page = "reservations";

// Handle reservation CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: reservations.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add_reservation' || $action === 'edit_reservation') {
            $reservation_id = isset($_POST['reservation_id']) ? filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT) : null;
            $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
            $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT) ?: null;
            $reservation_date = filter_input(INPUT_POST, 'reservation_date', FILTER_SANITIZE_STRING);
            $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
            $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
            $party_size = filter_input(INPUT_POST, 'party_size', FILTER_VALIDATE_INT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

            if ($customer_id && $reservation_date && $start_time && $end_time && $party_size && $status) {
                if ($action === 'add_reservation') {
                    $stmt = $conn->prepare("INSERT INTO reservations (customer_id, table_id, reservation_date, start_time, end_time, party_size, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("iisssiss", $customer_id, $table_id, $reservation_date, $start_time, $end_time, $party_size, $status, $notes);
                    $success_message = "Reservation added successfully";
                    $event_type = 'reservation_add';
                    $event_details = "Added new reservation for customer #$customer_id";
                } else {
                    $stmt = $conn->prepare("UPDATE reservations SET customer_id = ?, table_id = ?, reservation_date = ?, start_time = ?, end_time = ?, party_size = ?, status = ?, notes = ? WHERE reservation_id = ?");
                    $stmt->bind_param("iisssissi", $customer_id, $table_id, $reservation_date, $start_time, $end_time, $party_size, $status, $notes, $reservation_id);
                    $success_message = "Reservation updated successfully";
                    $event_type = 'reservation_update';
                    $event_details = "Updated reservation #$reservation_id";
                }

                if ($stmt->execute()) {
                    log_event($_SESSION['user_id'], $event_type, $event_details);
                    set_flash_message($success_message, 'success');
                } else {
                    set_flash_message("Failed to save reservation: " . $stmt->error, 'error');
                }
            } else {
                set_flash_message("Invalid input data", 'error');
            }
        } elseif ($action === 'delete_reservation') {
            $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
            if ($reservation_id) {
                $stmt = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
                $stmt->bind_param("i", $reservation_id);
                if ($stmt->execute()) {
                    log_event($_SESSION['user_id'], 'reservation_delete', "Deleted reservation #$reservation_id");
                    set_flash_message("Reservation deleted successfully", 'success');
                } else {
                    set_flash_message("Failed to delete reservation: " . $stmt->error, 'error');
                }
            }
        }
        header('Location: reservations.php');
        exit();
    }
}

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$time_slot = isset($_GET['time_slot']) ? $_GET['time_slot'] : '';

// Build the query to fetch reservations
$query = "SELECT r.*, 
          u.first_name, u.last_name, u.phone, u.email,
          t.table_number, t.capacity
          FROM reservations r
          JOIN customers c ON r.customer_id = c.customer_id
          JOIN users u ON c.user_id = u.user_id
          LEFT JOIN restaurant_tables t ON r.table_id = t.table_id
          WHERE 1=1";

// Apply date filter: Show upcoming reservations by default (from today onward)
$today = date('Y-m-d');
if (!empty($date_filter)) {
    $query .= " AND DATE(r.reservation_date) = ?";
    $params[] = $date_filter;
    $types = "s";
} else {
    $query .= " AND DATE(r.reservation_date) >= ?";
    $params[] = $today;
    $types = "s";
}

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add time slot filter
if (!empty($time_slot)) {
    if ($time_slot === 'breakfast') {
        $query .= " AND TIME(r.start_time) BETWEEN '07:00:00' AND '11:00:00'";
    } elseif ($time_slot === 'lunch') {
        $query .= " AND TIME(r.start_time) BETWEEN '11:00:00' AND '15:00:00'";
    } elseif ($time_slot === 'dinner') {
        $query .= " AND TIME(r.start_time) BETWEEN '17:00:00' AND '22:00:00'";
    }
}

$query .= " ORDER BY r.reservation_date, r.start_time ASC";

// Execute the query for reservations
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all tables for assignment
$tables = fetch_all("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE status != 'Maintenance' ORDER BY table_number");

// Get all customers for selection
$customers = fetch_all("SELECT c.customer_id, u.first_name, u.last_name, u.email FROM customers c JOIN users u ON c.user_id = u.user_id ORDER BY u.first_name, u.last_name");

include __DIR__ . '/include/header.php';
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
                    <h1 class="text-2xl font-bold text-amber-900">Reservation Management</h1>
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
                                <a href="settings.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-50 hover:text-amber-900 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-white-50">
                <?php display_flash_message(); ?>

                <!-- Reservation Filters -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="date" class="block text-sm font-medium text-amber-900 mb-1">Date</label>
                            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>"
                                class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-amber-900 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Confirmed" <?= $status_filter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="Seated" <?= $status_filter === 'Seated' ? 'selected' : '' ?>>Seated</option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="time_slot" class="block text-sm font-medium text-amber-900 mb-1">Time Slot</label>
                            <select id="time_slot" name="time_slot" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="">All Day</option>
                                <option value="breakfast" <?= $time_slot === 'breakfast' ? 'selected' : '' ?>>Breakfast (7AM-11AM)</option>
                                <option value="lunch" <?= $time_slot === 'lunch' ? 'selected' : '' ?>>Lunch (11AM-3PM)</option>
                                <option value="dinner" <?= $time_slot === 'dinner' ? 'selected' : '' ?>>Dinner (5PM-10PM)</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Add Reservation Button -->
                <div class="flex justify-end mb-6">
                    <button id="addReservationBtn" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        New Reservation
                    </button>
                </div>

                <!-- Reservations Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Party Size</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Table</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="hover:bg-amber-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-amber-900">
                                            <?= htmlspecialchars($reservation['reservation_date']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-amber-900">
                                            <?= date('g:i A', strtotime($reservation['start_time'])) ?> - <?= date('g:i A', strtotime($reservation['end_time'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-amber-900"><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?></div>
                                            <div class="text-sm text-amber-700"><?= htmlspecialchars($reservation['phone']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $reservation['party_size'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($reservation['table_id']): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                                    <?= htmlspecialchars($reservation['table_number']) ?> (<?= $reservation['capacity'] ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Not Assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $reservation['status'] === 'Confirmed' ? 'bg-green-100 text-green-800' : ($reservation['status'] === 'Cancelled' ? 'bg-red-100 text-red-800' : ($reservation['status'] === 'Seated' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>">
                                                <?= $reservation['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick='openViewModal(<?= json_encode($reservation) ?>)' class="text-amber-600 hover:text-amber-900 mr-3">View</button>
                                            <button onclick='openEditModal(<?= json_encode($reservation) ?>)' class="text-amber-600 hover:text-amber-900 mr-3">Edit</button>
                                            <button onclick='openDeleteModal(<?= $reservation['reservation_id'] ?>)' class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <?php if (empty($reservations)): ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-amber-900">No reservations found</h3>
                        <p class="mt-1 text-sm text-amber-700">There are no reservations for the selected date and filters.</p>
                        <div class="mt-6">
                            <button id="addReservationBtnEmpty" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                New Reservation
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add/Edit Reservation Modal -->
    <div id="reservationModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button id="closeReservationModal" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 id="reservationModalTitle" class="text-xl font-semibold text-amber-900 mb-4">Add New Reservation</h2>
            <form id="reservationForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="modalAction" value="add_reservation">
                <input type="hidden" name="reservation_id" id="reservationId">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="customer_id" class="block text-sm font-medium text-amber-900">Customer</label>
                        <select id="customer_id" name="customer_id" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['customer_id'] ?>">
                                    <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' (' . htmlspecialchars($customer['email']) . ')' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="reservation_date" class="block text-sm font-medium text-amber-900">Reservation Date</label>
                        <input type="date" id="reservation_date" name="reservation_date" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-amber-900">Start Time</label>
                            <input type="time" id="start_time" name="start_time" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-amber-900">End Time</label>
                            <input type="time" id="end_time" name="end_time" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="party_size" class="block text-sm font-medium text-amber-900">Party Size</label>
                        <input type="number" id="party_size" name="party_size" min="1" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="table_id" class="block text-sm font-medium text-amber-900">Table</label>
                        <select id="table_id" name="table_id" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Not Assigned</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= $table['table_id'] ?>">
                                    <?= htmlspecialchars($table['table_number']) . ' (Capacity: ' . $table['capacity'] . ')' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-amber-900">Status</label>
                        <select id="status" name="status" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Seated">Seated</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-amber-900">Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelReservationModal" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Reservation Modal -->
    <div id="viewModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button id="closeViewModal" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Reservation Details</h2>
            <div class="space-y-3 text-amber-900">
                <p><strong>Customer:</strong> <span id="viewCustomer"></span></p>
                <p><strong>Date:</strong> <span id="viewDate"></span></p>
                <p><strong>Time:</strong> <span id="viewTime"></span></p>
                <p><strong>Party Size:</strong> <span id="viewPartySize"></span></p>
                <p><strong>Table:</strong> <span id="viewTable"></span></p>
                <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                <p><strong>Notes:</strong> <span id="viewNotes"></span></p>
                <p><strong>Contact:</strong> <span id="viewContact"></span></p>
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
            <p class="text-amber-700 mb-6">Are you sure you want to delete this reservation?</p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete_reservation">
                <input type="hidden" name="reservation_id" id="deleteReservationId">
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelDeleteModal" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </form>
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

        // Reservation Modal
        const reservationModal = document.getElementById('reservationModal');
        const addReservationBtn = document.getElementById('addReservationBtn');
        const addReservationBtnEmpty = document.getElementById('addReservationBtnEmpty');
        const closeReservationModal = document.getElementById('closeReservationModal');
        const cancelReservationModal = document.getElementById('cancelReservationModal');

        function openAddModal() {
            document.getElementById('reservationModalTitle').textContent = 'Add New Reservation';
            document.getElementById('modalAction').value = 'add_reservation';
            document.getElementById('reservationForm').reset();
            document.getElementById('reservationId').value = '';
            reservationModal.classList.remove('hidden');
        }

        function openEditModal(reservation) {
            document.getElementById('reservationModalTitle').textContent = 'Edit Reservation';
            document.getElementById('modalAction').value = 'edit_reservation';
            document.getElementById('reservationId').value = reservation.reservation_id;
            document.getElementById('customer_id').value = reservation.customer_id;
            document.getElementById('reservation_date').value = reservation.reservation_date;
            document.getElementById('start_time').value = reservation.start_time;
            document.getElementById('end_time').value = reservation.end_time;
            document.getElementById('party_size').value = reservation.party_size;
            document.getElementById('table_id').value = reservation.table_id || '';
            document.getElementById('status').value = reservation.status;
            document.getElementById('notes').value = reservation.notes || '';
            reservationModal.classList.remove('hidden');
        }

        addReservationBtn?.addEventListener('click', openAddModal);
        addReservationBtnEmpty?.addEventListener('click', openAddModal);
        closeReservationModal.addEventListener('click', () => reservationModal.classList.add('hidden'));
        cancelReservationModal.addEventListener('click', () => reservationModal.classList.add('hidden'));

        // View Modal
        const viewModal = document.getElementById('viewModal');
        const closeViewModal = document.getElementById('closeViewModal');
        const closeViewModalBtn = document.getElementById('closeViewModalBtn');

        function openViewModal(reservation) {
            document.getElementById('viewCustomer').textContent = `${reservation.first_name} ${reservation.last_name}`;
            document.getElementById('viewDate').textContent = reservation.reservation_date;
            document.getElementById('viewTime').textContent = `${formatTime(reservation.start_time)} - ${formatTime(reservation.end_time)}`;
            document.getElementById('viewPartySize').textContent = reservation.party_size;
            document.getElementById('viewTable').textContent = reservation.table_id ? `${reservation.table_number} (Capacity: ${reservation.capacity})` : 'Not Assigned';
            document.getElementById('viewStatus').textContent = reservation.status;
            document.getElementById('viewNotes').textContent = reservation.notes || 'None';
            document.getElementById('viewContact').textContent = `${reservation.email} | ${reservation.phone}`;
            viewModal.classList.remove('hidden');
        }

        function formatTime(time) {
            const [hours, minutes] = time.split(':');
            const date = new Date();
            date.setHours(hours, minutes);
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
        }

        closeViewModal.addEventListener('click', () => viewModal.classList.add('hidden'));
        closeViewModalBtn.addEventListener('click', () => viewModal.classList.add('hidden'));

        // Delete Modal
        const deleteModal = document.getElementById('deleteModal');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelDeleteModal = document.getElementById('cancelDeleteModal');

        function openDeleteModal(reservationId) {
            document.getElementById('deleteReservationId').value = reservationId;
            deleteModal.classList.remove('hidden');
        }

        closeDeleteModal.addEventListener('click', () => deleteModal.classList.add('hidden'));
        cancelDeleteModal.addEventListener('click', () => deleteModal.classList.add('hidden'));

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target === reservationModal) reservationModal.classList.add('hidden');
            if (e.target === viewModal) viewModal.classList.add('hidden');
            if (e.target === deleteModal) deleteModal.classList.add('hidden');
        });
    </script>
</body>

</html>