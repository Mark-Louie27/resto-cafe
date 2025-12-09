<?php
require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// Get user data
$user = get_user_by_id($user_id);
$customer = get_customer_data($user_id);

    // Get customer ID
    $customer_id = get_customer_id($user_id);
    if (!$customer_id) {
        set_flash_message('Customer ID not found. Please ensure your account is properly set up.', 'error');
        header('Location: reservation.php');
        exit();
    }
// Get available table
$table = get_available_tables();

$page_title = "Reservations";
$current_page = "reservations";

$is_home = false;

    // Handle reservation submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reservation_date = $_POST['reservation_date'];
        $reservation_time = $_POST['reservation_time'];
        $party_size = (int)$_POST['party_size'];
        $table_id = $_POST['table_id'];
        $special_requests = trim($_POST['special_requests'] ?? '');

        // Validate inputs
        if (empty($reservation_date)) {
            set_flash_message('Please select a date', 'error');
        } elseif (empty($reservation_time)) {
            set_flash_message('Please select a time', 'error');
        } elseif ($party_size < 1) {
            set_flash_message('Please enter a valid party size', 'error');
        } elseif (empty($table_id)) {
            set_flash_message('Please select a table', 'error');
        } else {
            // Combine date and time
            $start_time = date('Y-m-d H:i:s', strtotime("$reservation_date $reservation_time"));
            $end_time = date('Y-m-d H:i:s', strtotime("$start_time +2 hours"));

            // Create reservation
            $stmt = $conn->prepare("
            INSERT INTO reservations 
            (customer_id, table_id, reservation_date, start_time, end_time, party_size, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed')
        ");
            $stmt->bind_param(
                "iisssis",
                $customer_id,  // Use customer_id instead of user_id
                $table_id,
                $reservation_date,
                $start_time,
                $end_time,
                $party_size,
                $special_requests
            );

            if ($stmt->execute()) {
                // Update table status
                $stmt = $conn->prepare("UPDATE restaurant_tables SET status = 'Reserved' WHERE table_id = ?");
                $stmt->bind_param("i", $table_id);
                $stmt->execute();
                $stmt->close();

                set_flash_message('Reservation confirmed!', 'success');
                header('Location: reservation.php');
                exit();
            } else {
                set_flash_message('Failed to make reservation. Please try again.', 'error');
            }
            $stmt->close();
        }
    }


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Reservation - Casa Baraka</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .sidebar-link {
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            transform: translateX(4px);
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        .table-option {
            padding: 8px 12px;
            margin: 4px 0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .table-option:hover {
            background-color: #fef3c7;
        }

        .table-option.selected {
            background-color: #fcd34d;
            font-weight: 500;
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
                        <a href="profile.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-user-edit mr-3 text-gray-500"></i> My Profile
                        </a>
                        <a href="orders.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                            <i class="fas fa-receipt mr-3 text-gray-500"></i> My Orders
                        </a>
                        <a href="reservation.php" class="flex items-center sidebar-link py-2 px-4 bg-amber-50 text-amber-700 rounded-lg font-medium">
                            <i class="fas fa-calendar-alt mr-3 text-amber-600"></i> Reservations
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
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white p-6">
                        <div class="flex items-center">
                            <div class="bg-white/20 p-3 rounded-lg mr-4">
                                <i class="fas fa-calendar-plus text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold">Make a Reservation</h1>
                                <p class="opacity-90">Book your table at Casa Baraka</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">
                        <?php display_flash_message(); ?>

                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Date Picker -->
                                <div>
                                    <label for="reservation_date" class="block text-gray-700 font-medium mb-2">Date*</label>
                                    <div class="relative">
                                        <input type="text" id="reservation_date" name="reservation_date" required
                                            class="form-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                            placeholder="Select date">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-calendar text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Time Picker -->
                                <div>
                                    <label for="reservation_time" class="block text-gray-700 font-medium mb-2">Time*</label>
                                    <div class="relative">
                                        <select id="reservation_time" name="reservation_time" required
                                            class="form-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 appearance-none">
                                            <option value="">Select time</option>
                                            <?php
                                            $start = strtotime('11:00');
                                            $end = strtotime('21:00');
                                            $interval = 30 * 60; // 30 minutes

                                            for ($i = $start; $i <= $end; $i += $interval) {
                                                $time = date('g:i A', $i);
                                                echo "<option value=\"$time\">$time</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-clock text-gray-400"></i>
                                        </div>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Party Size -->
                                <div>
                                    <label for="party_size" class="block text-gray-700 font-medium mb-2">Party Size*</label>
                                    <div class="relative">
                                        <select id="party_size" name="party_size" required
                                            class="form-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 appearance-none">
                                            <option value="">Select number of guests</option>
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?= $i ?>" <?= isset($_POST['party_size']) && $_POST['party_size'] == $i ? 'selected' : '' ?>>
                                                    <?= $i ?> person<?= $i > 1 ? 's' : '' ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-users text-gray-400"></i>
                                        </div>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Table Selection -->
                                <div>
                                    <label for="table_id" class="block text-gray-700 font-medium mb-2">Table*</label>
                                    <div class="relative">
                                        <select id="table_id" name="table_id" required
                                            class="form-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 appearance-none">
                                            <option value="">Select a table</option>
                                            <?php foreach ($table as $t): ?>
                                                <option value="<?= $t['table_id'] ?>"
                                                    data-capacity="<?= isset($t['capacity']) ? $t['capacity'] : 0 ?>"
                                                    <?= isset($_POST['table_id']) && $_POST['table_id'] == $t['table_id'] ? 'selected' : '' ?>>
                                                    Table #<?= $t['table_number'] ?> (<?= isset($t['capacity']) ? $t['capacity'] : 'Unknown' ?> seats, <?= isset($t['location']) ? $t['location'] : 'Unknown' ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-utensils text-gray-400"></i>
                                        </div>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Requests -->
                            <div>
                                <label for="special_requests" class="block text-gray-700 font-medium mb-2">Special Requests</label>
                                <div class="relative">
                                    <textarea id="special_requests" name="special_requests" rows="3"
                                        class="form-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        placeholder="Any special requirements?"><?= htmlspecialchars($_POST['special_requests'] ?? '') ?></textarea>
                                    <div class="absolute top-3 left-3">
                                        <i class="fas fa-comment-alt text-gray-400"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">We'll do our best to accommodate your requests</p>
                            </div>

                            <div class="pt-2">
                                <button type="submit"
                                    class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-medium py-3 px-4 rounded-lg transition shadow-md hover:shadow-lg">
                                    <i class="fas fa-calendar-check mr-2"></i> Confirm Reservation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize date picker with custom styling
        flatpickr("#reservation_date", {
            minDate: "today",
            maxDate: new Date().fp_incr(30), // 30 days from now
            disable: [
                function(date) {
                    // Disable Mondays
                    return (date.getDay() === 1);
                }
            ],
            dateFormat: "Y-m-d",
            theme: "light" // or "dark" if you prefer
        });

        // Filter tables based on party size
        document.getElementById('party_size').addEventListener('change', function() {
            const partySize = parseInt(this.value);
            const tableSelect = document.getElementById('table_id');

            if (partySize > 0) {
                // Enable all options first
                Array.from(tableSelect.options).forEach(option => {
                    option.disabled = false;
                    option.classList.remove('bg-gray-100', 'text-gray-400');
                });

                // Disable tables with capacity less than party size
                Array.from(tableSelect.options).forEach(option => {
                    if (option.value) {
                        const capacity = parseInt(option.dataset.capacity) || 0;
                        if (capacity < partySize) {
                            option.disabled = true;
                            option.classList.add('bg-gray-100', 'text-gray-400');
                            if (option.selected) option.selected = false;
                        }
                    }
                });
            }
        });

        // Add visual feedback for selected table
        document.getElementById('table_id').addEventListener('change', function() {
            const options = this.options;
            Array.from(options).forEach(option => {
                option.classList.remove('selected');
                if (option.selected && option.value) {
                    option.classList.add('selected');
                }
            });
        });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>