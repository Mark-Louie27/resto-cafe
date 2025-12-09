<?php
require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// Get available tables
$tables = get_available_tables();

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
            $user_id, 
            $table_id, 
            $reservation_date, 
            $start_time, 
            $end_time, 
            $party_size, 
            $special_requests
        );
        
        if ($stmt->execute()) {
            // Update table status
            $conn->query("UPDATE restaurant_tables SET status = 'Reserved' WHERE table_id = $table_id");
            
            set_flash_message('Reservation confirmed!', 'success');
            header('Location: reservations.php');
            exit();
        } else {
            set_flash_message('Failed to make reservation. Please try again.', 'error');
        }
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
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-amber-600 text-white p-6">
                <h1 class="text-2xl font-bold">Make a Reservation</h1>
                <p class="opacity-90">Book your table at Casa Baraka</p>
            </div>
            
            <div class="p-6">
                <?php display_flash_message(); ?>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Date Picker -->
                        <div>
                            <label for="reservation_date" class="block text-gray-700 mb-2">Date*</label>
                            <input type="text" id="reservation_date" name="reservation_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                                   placeholder="Select date">
                        </div>
                        
                        <!-- Time Picker -->
                        <div>
                            <label for="reservation_time" class="block text-gray-700 mb-2">Time*</label>
                            <select id="reservation_time" name="reservation_time" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
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
                        </div>
                        
                        <!-- Party Size -->
                        <div>
                            <label for="party_size" class="block text-gray-700 mb-2">Party Size*</label>
                            <select id="party_size" name="party_size" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="">Select number of guests</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= isset($_POST['party_size']) && $_POST['party_size'] == $i ? 'selected' : '' ?>>
                                        <?= $i ?> person<?= $i > 1 ? 's' : '' ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <!-- Table Selection -->
                        <div>
                            <label for="table_id" class="block text-gray-700 mb-2">Table*</label>
                            <select id="table_id" name="table_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option value="">Select a table</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= $table['table_id'] ?>" 
                                        data-capacity="<?= $table['capacity'] ?>"
                                        <?= isset($_POST['table_id']) && $_POST['table_id'] == $table['table_id'] ? 'selected' : '' ?>>
                                        Table #<?= $table['table_number'] ?> (<?= $table['capacity'] ?> seats, <?= $table['location'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Special Requests -->
                    <div>
                        <label for="special_requests" class="block text-gray-700 mb-2">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                                  placeholder="Any special requirements?"><?= htmlspecialchars($_POST['special_requests'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full bg-amber-600 hover:bg-amber-500 text-white font-medium py-3 px-4 rounded-md transition">
                            Confirm Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize date picker
        flatpickr("#reservation_date", {
            minDate: "today",
            maxDate: new Date().fp_incr(30), // 30 days from now
            disable: [
                function(date) {
                    // Disable Mondays
                    return (date.getDay() === 1);
                }
            ]
        });
        
        // Filter tables based on party size
        document.getElementById('party_size').addEventListener('change', function() {
            const partySize = parseInt(this.value);
            const tableSelect = document.getElementById('table_id');
            
            if (partySize > 0) {
                // Enable all options first
                Array.from(tableSelect.options).forEach(option => {
                    option.disabled = false;
                });
                
                // Disable tables with capacity less than party size
                Array.from(tableSelect.options).forEach(option => {
                    if (option.value && parseInt(option.dataset.capacity) < partySize) {
                        option.disabled = true;
                        if (option.selected) option.selected = false;
                    }
                });
            }
        });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>