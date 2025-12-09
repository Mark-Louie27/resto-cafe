<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'])) {
    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    $valid_statuses = ['Available', 'Occupied', 'Reserved', 'Maintenance'];
    if ($table_id && in_array($status, $valid_statuses)) {
        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE restaurant_tables SET status = ? WHERE table_id = ?");
        $stmt->bind_param("si", $status, $table_id);
        if ($stmt->execute()) {
            set_flash_message('Table status updated successfully', 'success');
        } else {
            set_flash_message('Failed to update table status', 'error');
        }
    } else {
        set_flash_message('Invalid input', 'error');
    }
}
header('Location: /modules/staff/dashboard.php');
exit();
