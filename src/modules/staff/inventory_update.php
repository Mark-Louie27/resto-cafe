<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'])) {
    $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);

    if ($inventory_id && $quantity >= 0) {
        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
        $stmt->bind_param("di", $quantity, $inventory_id);
        if ($stmt->execute()) {
            set_flash_message('Inventory updated successfully', 'success');
        } else {
            set_flash_message('Failed to update inventory', 'error');
        }
    } else {
        set_flash_message('Invalid input', 'error');
    }
}
header('Location: /modules/staff/dashboard.php');
exit();
