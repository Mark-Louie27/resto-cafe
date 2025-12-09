<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php';
require_once __DIR__ . '/../../controller/MenuController.php';

require_auth();

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// Get customer data
$customer = get_customer_data($user_id);
$customer_id = $customer['customer_id'];
$loyalty_points = $customer['loyalty_points'];

// Get cart
$cart = get_cart();
if (empty($cart)) {
    set_flash_message('Your cart is empty.', 'error');
    header('Location: order.php');
    exit();
}

// Calculate total and prepare order_items
$total = 0;
$order_items = [];
foreach ($cart as $item_id => $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $total += $subtotal;
    // Prepare order_items in the format expected by create_order
    $order_items[] = [
        'item_id' => (int)$item_id,
        'quantity' => (int)$item['quantity'],
        'unit_price' => (float)$item['price']
    ];
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token.', 'error');
        header('Location: checkout.php');
        exit();
    }

    $order_type = filter_input(INPUT_POST, 'order_type', FILTER_SANITIZE_STRING);
    $delivery_address = $order_type === 'Delivery' ? filter_input(INPUT_POST, 'delivery_address', FILTER_SANITIZE_STRING) : null;
    $delivery_fee = $order_type === 'Delivery' ? 5.00 : 0.00; // Example delivery fee
    $table_id = $order_type === 'Dine-in' ? filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT) : null;
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $redeem_points = isset($_POST['redeem_points']) ? (int)$_POST['redeem_points'] : 0;

    // Validate inputs
    if (!$order_type || ($order_type === 'Delivery' && empty($delivery_address)) || ($order_type === 'Dine-in' && !$table_id) || !$payment_method) {
        set_flash_message('Please fill in all required fields.', 'error');
        header('Location: checkout.php');
        exit();
    }

    // Calculate discount from loyalty points (10 points = $1)
    $max_points_usable = min($loyalty_points, floor($total * 10)); // Can't use more points than the total allows
    $points_to_use = min($redeem_points, $max_points_usable);
    $discount = $points_to_use / 10; // 10 points = $1 discount
    $adjusted_total = $total - $discount;

    if ($adjusted_total < 0) {
        $adjusted_total = 0;
    }

    // Add delivery fee to adjusted total
    $adjusted_total += $delivery_fee;

    // Create the order with the adjusted total
    $order_id = create_order(
        $customer_id,
        $order_type,
        $adjusted_total,
        $order_items, // Pass the prepared order_items
        $delivery_address,
        $delivery_fee,
        null, // estimated_delivery_time
        $notes,
        null, // staff_id
        $table_id
    );

    if ($order_id) {
        // Add cart items to order
        foreach ($cart as $item_id => $item) {
            $result = add_order_item($order_id, $item_id, $item['quantity'], $item['price']);
            if (!$result['success']) {
                set_flash_message($result['message'], 'error');
                header('Location: checkout.php');
                exit();
            }
        }

        // Create payment record
        $payment_result = create_payment($order_id, $adjusted_total, $payment_method);
        if (!$payment_result['success']) {
            set_flash_message($payment_result['message'], 'error');
            header('Location: checkout.php');
            exit();
        }

        // Deduct loyalty points if used
        if ($points_to_use > 0) {
            $stmt = $conn->prepare("UPDATE customers SET loyalty_points = loyalty_points - ? WHERE customer_id = ?");
            $stmt->bind_param("ii", $points_to_use, $customer_id);
            if (!$stmt->execute()) {
                error_log('Failed to deduct loyalty points: ' . $stmt->error);
            }
            $stmt->close();
        }

        // Clear the cart
        unset($_SESSION['cart']);
        set_flash_message('Order placed successfully! You used ' . $points_to_use . ' loyalty points and saved $' . number_format($discount, 2) . '.', 'success');
        header('Location: orders.php?id=' . $order_id);
        exit();
    } else {
        set_flash_message('Failed to place order.', 'error');
        header('Location: checkout.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Casa Baraka</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .error-text {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Checkout</h1>
        <?php display_flash_message(); ?>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
            <div class="space-y-2">
                <?php foreach ($cart as $item): ?>
                    <div class="flex justify-between">
                        <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="border-t pt-2">
                    <div class="flex justify-between font-semibold">
                        <span>Subtotal:</span>
                        <span>₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>

            <form method="POST" class="mt-6" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Order Type -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Order Type</label>
                    <select name="order_type" id="order_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500" onchange="toggleOrderFields()" required>
                        <option value="Dine-in">Dine-in</option>
                        <option value="Takeout">Takeout</option>
                        <option value="Delivery">Delivery</option>
                    </select>
                </div>

                <!-- Table Selection (for Dine-in) -->
                <div id="table_field" class="mb-4 hidden">
                    <label class="block text-gray-700 mb-2">Select Table</label>
                    <select name="table_id" id="table_id" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="">Select a table</option>
                        <?php
                        $tables = $conn->query("SELECT table_id, table_number FROM restaurant_tables WHERE status = 'Available'");
                        while ($table = $tables->fetch_assoc()):
                        ?>
                            <option value="<?php echo $table['table_id']; ?>">Table #<?php echo $table['table_number']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <p id="table_error" class="error-text hidden">Please select a table for Dine-in orders.</p>
                </div>

                <!-- Delivery Address (for Delivery) -->
                <div id="delivery_field" class="mb-4 hidden">
                    <label class="block text-gray-700 mb-2">Delivery Address</label>
                    <input type="text" name="delivery_address" id="delivery_address" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500" placeholder="Enter your address">
                    <p id="delivery_error" class="error-text hidden">Please provide a delivery address.</p>
                </div>

                <!-- Loyalty Points Redemption -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Redeem Loyalty Points (10 points = $1)</label>
                    <p class="text-sm text-gray-500 mb-2">You have <?php echo $loyalty_points; ?> points available.</p>
                    <input type="number" name="redeem_points" id="redeem_points" min="0" max="<?php echo $loyalty_points; ?>" value="0" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500" onchange="updateTotal()">
                    <p class="text-sm text-gray-500 mt-1">Max points usable for this order: <?php echo floor($total * 10); ?></p>
                </div>

                <!-- Display Updated Total -->
                <div class="mb-4">
                    <div class="flex justify-between font-semibold">
                        <span>Delivery Fee:</span>
                        <span id="delivery_fee">₱0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span>Discount (from points):</span>
                        <span id="discount">₱0.00</span>
                    </div>
                    <div class="flex justify-between font-semibold text-lg">
                        <span>Total:</span>
                        <span id="adjusted_total">₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Payment Method</label>
                    <select name="payment_method" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Credit/Debit Card</option>
                    </select>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Additional Notes (Optional)</label>
                    <textarea name="notes" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500" rows="3" placeholder="Any special requests?"></textarea>
                </div>

                <button type="submit" class="w-full bg-amber-600 text-white py-3 rounded-lg hover:bg-amber-500 transition duration-300">Place Order</button>
            </form>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    <script>
        function toggleOrderFields() {
            const orderType = document.getElementById('order_type').value;
            const tableField = document.getElementById('table_field');
            const deliveryField = document.getElementById('delivery_field');
            const deliveryFeeElement = document.getElementById('delivery_fee');
            const adjustedTotalElement = document.getElementById('adjusted_total');
            const tableIdSelect = document.getElementById('table_id');
            const deliveryAddressInput = document.getElementById('delivery_address');

            let deliveryFee = 0;
            if (orderType === 'Delivery') {
                tableField.classList.add('hidden');
                deliveryField.classList.remove('hidden');
                deliveryAddressInput.setAttribute('required', 'required');
                tableIdSelect.removeAttribute('required');
                deliveryFee = 5.00; // Example delivery fee
            } else if (orderType === 'Dine-in') {
                tableField.classList.remove('hidden');
                deliveryField.classList.add('hidden');
                tableIdSelect.setAttribute('required', 'required');
                deliveryAddressInput.removeAttribute('required');
                deliveryFee = 0;
            } else {
                tableField.classList.add('hidden');
                deliveryField.classList.add('hidden');
                tableIdSelect.removeAttribute('required');
                deliveryAddressInput.removeAttribute('required');
                deliveryFee = 0;
            }

            deliveryFeeElement.textContent = `₱${deliveryFee.toFixed(2)}`;
            updateTotal();
        }

        function updateTotal() {
            const orderType = document.getElementById('order_type').value;
            const redeemPoints = parseInt(document.getElementById('redeem_points').value) || 0;
            const subtotal = <?php echo $total; ?>;
            const deliveryFee = orderType === 'Delivery' ? 5.00 : 0;

            // Calculate discount (10 points = $1)
            const discount = redeemPoints / 10;
            let adjustedTotal = subtotal - discount + deliveryFee;
            if (adjustedTotal < 0) adjustedTotal = 0;

            document.getElementById('discount').textContent = `₱${discount.toFixed(2)}`;
            document.getElementById('adjusted_total').textContent = `₱${adjustedTotal.toFixed(2)}`;
        }

        function validateForm() {
            const orderType = document.getElementById('order_type').value;
            const tableId = document.getElementById('table_id');
            const deliveryAddress = document.getElementById('delivery_address');
            const tableError = document.getElementById('table_error');
            const deliveryError = document.getElementById('delivery_error');

            let isValid = true;

            // Reset error messages
            tableError.classList.add('hidden');
            deliveryError.classList.add('hidden');

            if (orderType === 'Dine-in' && (!tableId.value || tableId.value === '')) {
                tableError.classList.remove('hidden');
                isValid = false;
            }

            if (orderType === 'Delivery' && (!deliveryAddress.value || deliveryAddress.value.trim() === '')) {
                deliveryError.classList.remove('hidden');
                isValid = false;
            }

            return isValid;
        }

        // Initialize fields on page load
        toggleOrderFields();
    </script>
</body>

</html>