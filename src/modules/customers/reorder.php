<?php
// reorder.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php';
require_auth();

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$items = get_order_items($order_id);
foreach ($items as $item) {
    $_SESSION['cart'][$item['item_id']] = [
        'name' => $item['name'],
        'price' => $item['unit_price'],
        'quantity' => $item['quantity']
    ];
}
header('Location: orders.php');
exit();

?>