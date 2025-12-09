<?php
require_once __DIR__ . '/../includes/functions.php';

function get_customer_id_from_user_id($user_id)
{
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in get_customer_id_from_user_id");
        return null;
    }
    $stmt = mysqli_prepare($conn, "SELECT customer_id FROM customers WHERE user_id = ?");
    if (!$stmt) {
        error_log('Failed to prepare statement in get_customer_id_from_user_id: ' . mysqli_error($conn));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in get_customer_id_from_user_id: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['customer_id'] : null;
}

// Function to fetch applicable promotions for an order
function get_applicable_promotions($order_items, $order_total)
{
    if (empty($order_items) || !is_array($order_items)) {
        return [];
    }

    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in get_applicable_promotions");
        return [];
    }

    // Get all active promotions that are currently valid
    $query = "SELECT p.*, GROUP_CONCAT(pi.item_id) as applicable_items
              FROM promotions p
              LEFT JOIN promotion_items pi ON p.promotion_id = pi.promotion_id
              WHERE p.is_active = 1 AND p.start_date <= CURDATE() AND p.end_date >= CURDATE()
              GROUP BY p.promotion_id";
    $result = $conn->query($query);
    if (!$result) {
        error_log("Failed to fetch promotions: " . $conn->error);
        return [];
    }

    $promotions = $result->fetch_all(MYSQLI_ASSOC);
    $applicable_promotions = [];

    // Extract item IDs from order_items
    $item_ids = array_column($order_items, 'item_id');

    foreach ($promotions as $promo) {
        $promo_items = $promo['applicable_items'] ? explode(',', $promo['applicable_items']) : [];
        $promo_items = array_map('intval', $promo_items);

        // Check if the promotion applies (based on items and min_purchase)
        $items_match = empty($promo_items) || array_intersect($item_ids, $promo_items);
        $meets_min_purchase = $order_total >= $promo['min_purchase'];

        if ($items_match && $meets_min_purchase) {
            $applicable_promotions[] = $promo;
        }
    }

    return $applicable_promotions;
}

// Function to calculate discount based on promotion
function calculate_discount($promotion, $order_items, $subtotal)
{
    $discount = 0.0;
    $promo_items = $promotion['applicable_items'] ? explode(',', $promotion['applicable_items']) : [];
    $promo_items = array_map('intval', $promo_items);

    // Calculate subtotal of applicable items
    $applicable_subtotal = 0.0;
    foreach ($order_items as $item) {
        if (empty($promo_items) || in_array($item['item_id'], $promo_items)) {
            $applicable_subtotal += $item['unit_price'] * $item['quantity'];
        }
    }

    switch ($promotion['discount_type']) {
        case 'Percentage':
            $discount = ($applicable_subtotal * $promotion['discount_value']) / 100;
            break;
        case 'Fixed Amount':
            $discount = min($promotion['discount_value'], $applicable_subtotal);
            break;
        case 'Buy One Get One':
            // For BOGO, find pairs of applicable items
            $quantities = 0;
            foreach ($order_items as $item) {
                if (empty($promo_items) || in_array($item['item_id'], $promo_items)) {
                    $quantities += $item['quantity'];
                }
            }
            $pairs = floor($quantities / 2);
            $cheapest_item_price = PHP_FLOAT_MAX;
            foreach ($order_items as $item) {
                if (empty($promo_items) || in_array($item['item_id'], $promo_items)) {
                    $cheapest_item_price = min($cheapest_item_price, $item['unit_price']);
                }
            }
            $discount = $pairs * $cheapest_item_price;
            break;
        case 'Free Item':
            // For simplicity, assume Free Item reduces the price of one item to 0
            $cheapest_item_price = PHP_FLOAT_MAX;
            foreach ($order_items as $item) {
                if (empty($promo_items) || in_array($item['item_id'], $promo_items)) {
                    $cheapest_item_price = min($cheapest_item_price, $item['unit_price']);
                }
            }
            $discount = $cheapest_item_price;
            break;
    }

    return $discount;
}

function create_order(
    $customer_id,
    $order_type,
    $total,
    $order_items,
    $delivery_address = null,
    $delivery_fee = 50.00,
    $estimated_delivery_time = null,
    $notes = null,
    $staff_id = null,
    $table_id = null
) {
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in create_order");
        return false;
    }

    // Ensure correct data types
    $customer_id = (int) $customer_id;
    $order_type = (string) $order_type;
    $total = (float) $total;
    $delivery_fee = (float) $delivery_fee;
    $delivery_address = $delivery_address === null ? null : (string) $delivery_address;
    $estimated_delivery_time = $estimated_delivery_time === null ? null : (string) $estimated_delivery_time;
    $notes = $notes === null ? null : (string) $notes;
    $staff_id = $staff_id === null ? null : (int) $staff_id;
    $table_id = $table_id === null ? null : (int) $table_id;

    error_log("create_order: customer_id=$customer_id, order_type=$order_type, total=$total, delivery_address=" . ($delivery_address ?? 'NULL') . ", delivery_fee=$delivery_fee, estimated_delivery_time=" . ($estimated_delivery_time ?? 'NULL') . ", notes=" . ($notes ?? 'NULL') . ", staff_id=" . ($staff_id ?? 'NULL') . ", table_id=" . ($table_id ?? 'NULL'));

    // Apply promotions
    $applicable_promotions = get_applicable_promotions($order_items, $total);
    $best_discount = 0.0;

    foreach ($applicable_promotions as $promo) {
        $discount = calculate_discount($promo, $order_items, $total);
        if ($discount > $best_discount) {
            $best_discount = $discount;
        }
    }

    // Adjust total based on the best promotion
    $total_after_discount = $total - $best_discount;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO orders (
        customer_id, order_type, 
        status, total, discount_applied,
        delivery_address, delivery_fee, 
        estimated_delivery_time, notes, 
        staff_id, table_id, 
        created_at, updated_at) VALUES (
            ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    if (!$stmt) {
        error_log('Failed to prepare statement in create_order: ' . mysqli_error($conn));
        return false;
    }

    error_log("create_order: Statement prepared successfully");

    // Fixed type definition string to match 10 parameters
    mysqli_stmt_bind_param($stmt, "isiddssiii", $customer_id, $order_type, $total_after_discount, $best_discount, $delivery_address, $delivery_fee, $estimated_delivery_time, $notes, $staff_id, $table_id);

    error_log("create_order: Parameters bound successfully");

    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in create_order: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    error_log("create_order: Statement executed successfully");

    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    error_log("create_order: Order ID $order_id created with total after discount: $total_after_discount, discount applied: $best_discount");

    return $order_id;
}

function add_order_item($order_id, $item_id, $quantity, $unit_price)
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in add_order_item");
        return ['success' => false, 'message' => 'Database connection not initialized'];
    }
    $unit_price = (float) $unit_price;
    $stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, item_id, quantity, unit_price, status) VALUES (?, ?, ?, ?, 'Pending')");
    if (!$stmt) {
        error_log('Failed to prepare statement in add_order_item: ' . mysqli_error($conn));
        return ['success' => false, 'message' => 'Failed to prepare statement: ' . mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($stmt, "iiid", $order_id, $item_id, $quantity, $unit_price);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log('Failed to execute statement in add_order_item: ' . $error);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to add item to order: ' . $error];
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Item added to order.'];
}

function create_payment($order_id, $amount, $payment_method)
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in create_payment");
        return ['success' => false, 'message' => 'Database connection not initialized'];
    }
    $amount = (float) $amount;
    $stmt = mysqli_prepare($conn, "INSERT INTO payments (order_id, payment_date, amount, payment_method, status) VALUES (?, NOW(), ?, ?, 'Pending')");
    if (!$stmt) {
        error_log('Failed to prepare statement in create_payment: ' . mysqli_error($conn));
        return ['success' => false, 'message' => 'Failed to prepare statement: ' . mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($stmt, "ids", $order_id, $amount, $payment_method);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log('Failed to execute statement in create_payment: ' . $error);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to create payment record: ' . $error];
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Payment record created.'];
}

function get_staff_id_from_user_id($user_id)
{
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in get_staff_id_from_user_id");
        return null;
    }
    $stmt = mysqli_prepare($conn, "SELECT staff_id FROM staff WHERE user_id = ?");
    if (!$stmt) {
        error_log('Failed to prepare statement in get_staff_id_from_user_id: ' . mysqli_error($conn));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in get_staff_id_from_user_id: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['staff_id'] : null;
}

function get_recent_orders($user_id, $limit)
{
    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT o.order_id, o.created_at, o.status, SUM(oi.quantity * oi.unit_price) as total
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Helper function to update loyalty points
function update_loyalty_points($customer_id, $points_to_add)
{
    $conn = db_connect();
    $stmt = mysqli_prepare($conn, "UPDATE customers SET loyalty_points = loyalty_points + ? WHERE customer_id = ?");
    if (!$stmt) {
        error_log('Failed to prepare statement in update_loyalty_points: ' . mysqli_error($conn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ii", $points_to_add, $customer_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in update_loyalty_points: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

// Helper function to update payment status
function update_payment_status($order_id, $new_status)
{
    $conn = db_connect();
    $stmt = mysqli_prepare($conn, "UPDATE payments SET status = ?, payment_date = NOW() WHERE order_id = ?");
    if (!$stmt) {
        error_log('Failed to prepare statement in update_payment_status: ' . mysqli_error($conn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in update_payment_status: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

function process_order($order_id, $new_status, $staff_id)
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection is not initialized in process_order");
        return ['success' => false, 'message' => 'Database connection not initialized'];
    }

    // Validate order_id and new_status
    $order_id = (int) $order_id;
    $staff_id = (int) $staff_id;
    $valid_statuses = ['Pending', 'Processing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }

    // Check if the order exists and get current status, staff_id, and customer_id
    $stmt = mysqli_prepare($conn, "SELECT status, staff_id, customer_id, total FROM orders WHERE order_id = ?");
    if (!$stmt) {
        error_log('Failed to prepare statement in process_order: ' . mysqli_error($conn));
        return ['success' => false, 'message' => 'Failed to prepare statement: ' . mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Failed to execute statement in process_order: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to retrieve order: ' . mysqli_stmt_error($stmt)];
    }
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $current_status = $order['status'];
    $current_staff_id = $order['staff_id'];
    $customer_id = $order['customer_id'];
    $order_total = $order['total'];

    // If the new status is 'Processing' and staff_id is not yet set, assign the staff_id
    if ($new_status === 'Processing' && $current_staff_id === null) {
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, staff_id = ?, updated_at = NOW() WHERE order_id = ?");
        if (!$stmt) {
            error_log('Failed to prepare update statement in process_order: ' . mysqli_error($conn));
            return ['success' => false, 'message' => 'Failed to prepare update statement: ' . mysqli_error($conn)];
        }
        mysqli_stmt_bind_param($stmt, "sii", $new_status, $staff_id, $order_id);
    } else {
        // Otherwise, just update the status
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
        if (!$stmt) {
            error_log('Failed to prepare update statement in process_order: ' . mysqli_error($conn));
            return ['success' => false, 'message' => 'Failed to prepare update statement: ' . mysqli_error($conn)];
        }
        mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log('Failed to execute update statement in process_order: ' . $error);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to update order: ' . $error];
    }

    mysqli_stmt_close($stmt);

    // Award loyalty points if the order is marked as Completed
    if ($new_status === 'Completed' && $current_status !== 'Completed') {
        // Calculate points: 1 point per $1 spent (rounded down)
        $points_to_add = floor($order_total);
        if ($points_to_add > 0) {
            if (update_loyalty_points($customer_id, $points_to_add)) {
                error_log("Awarded $points_to_add loyalty points to customer_id $customer_id for order_id $order_id");
            } else {
                error_log("Failed to award loyalty points to customer_id $customer_id for order_id $order_id");
            }
        }

        // Update payment status to Completed
        if (update_payment_status($order_id, 'Completed')) {
            error_log("Payment status updated to Completed for order_id $order_id");
        } else {
            error_log("Failed to update payment status for order_id $order_id");
            // Note: We don't fail the order update here, but log the issue
        }
    }

    // If the status is 'Completed' and a table_id exists, set the table status back to 'Available'
    if ($new_status === 'Completed') {
        $stmt = mysqli_prepare($conn, "SELECT table_id FROM orders WHERE order_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($order && $order['table_id'] !== null) {
            $stmt = mysqli_prepare($conn, "UPDATE restaurant_tables SET status = 'Available' WHERE table_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $order['table_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    return ['success' => true, 'message' => 'Order status updated successfully.'];
}

function get_order_items($order_id)
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name as name, mi.image_url 
        FROM order_items oi
        LEFT JOIN items mi ON oi.item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get orders with additional details
function get_customer_orders($customer_id)
{
    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT o.order_id, o.created_at, o.status, o.order_type, o.total as order_total,
               o.discount_applied, o.delivery_address, o.delivery_fee, rt.table_number,
               p.status as payment_status,
               COUNT(oi.order_item_id) as item_count,
               SUM(oi.quantity * oi.unit_price) as items_subtotal
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN restaurant_tables rt ON o.table_id = rt.table_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.customer_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
