<?php
require_once __DIR__ . '/../includes/functions.php';

function handle_image_upload($file, $prefix = 'menu')
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash_message('Error uploading file', 'error');
        return null;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        set_flash_message('Invalid file type. Only JPEG, PNG, and GIF are allowed.', 'error');
        return null;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        set_flash_message('File size exceeds 10MB limit.', 'error');
        return null;
    }

    $upload_dir = __DIR__ . '/../assets/Uploads/menu/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '.' . $extension;
    $destination = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return '/../assets/Uploads/menu/' . $filename;
    } else {
        set_flash_message('Failed to move uploaded file.', 'error');
        return null;
    }
}

function handle_add_category()
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description'] ?? '');
    $image_url = handle_image_upload($_FILES['menu_image'], 'category');

    $sql = "INSERT INTO categories (name, description, image_url) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $name, $description, $image_url);

    if ($stmt->execute()) {
        set_flash_message('Category added successfully', 'success');
    } else {
        set_flash_message('Failed to add category', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_update_category()
{
    $conn = db_connect();
    $category_id = sanitize_input($_POST['category_id']);
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description'] ?? '');
    $image_url = handle_image_upload($_FILES['menu_image'], 'category');

    if ($image_url === null) {
        $sql = "SELECT image_url FROM categories WHERE category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $image_url = $row['image_url'];
        $stmt->close();
    }

    $sql = "UPDATE categories SET name = ?, description = ?, image_url = ? WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $name, $description, $image_url, $category_id);

    if ($stmt->execute()) {
        set_flash_message('Category updated successfully', 'success');
    } else {
        set_flash_message('Failed to update category', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_delete_category()
{
    $conn = db_connect();
    $category_id = sanitize_input($_POST['category_id']);

    $sql = "UPDATE items SET category_id = NULL WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $stmt->close();

    $sql = "DELETE FROM categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $category_id);

    if ($stmt->execute()) {
        set_flash_message('Category deleted successfully', 'success');
    } else {
        set_flash_message('Failed to delete category', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_add_item()
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? sanitize_input($_POST['category_id']) : null;
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost']);
    $calories = !empty($_POST['calories']) ? intval($_POST['calories']) : null;
    $allergens = sanitize_input($_POST['allergens'] ?? '');
    $prep_time = !empty($_POST['prep_time']) ? intval($_POST['prep_time']) : null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $image_url = handle_image_upload($_FILES['menu_image'], 'item');

    $sql = "INSERT INTO items (name, description, category_id, price, cost, calories, allergens, prep_time, is_available, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssidiiisds', $name, $description, $category_id, $price, $cost, $calories, $allergens, $prep_time, $is_available, $image_url);

    if ($stmt->execute()) {
        set_flash_message('Menu item added successfully', 'success');
    } else {
        set_flash_message('Failed to add menu item', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_update_item()
{
    $conn = db_connect();
    $item_id = sanitize_input($_POST['item_id']);
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? sanitize_input($_POST['category_id']) : null;
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost']);
    $calories = !empty($_POST['calories']) ? intval($_POST['calories']) : null;
    $allergens = sanitize_input($_POST['allergens'] ?? '');
    $prep_time = !empty($_POST['prep_time']) ? intval($_POST['prep_time']) : null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $image_url = handle_image_upload($_FILES['menu_image'], 'item');

    if ($image_url === null) {
        $sql = "SELECT image_url FROM items WHERE item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $image_url = $row['image_url'];
        $stmt->close();
    }

    $sql = "UPDATE items SET name = ?, description = ?, category_id = ?, price = ?, cost = ?, calories = ?, allergens = ?, prep_time = ?, is_available = ?, image_url = ? WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssidiiisisi', $name, $description, $category_id, $price, $cost, $calories, $allergens, $prep_time, $is_available, $image_url, $item_id);

    if ($stmt->execute()) {
        set_flash_message('Menu item updated successfully', 'success');
    } else {
        set_flash_message('Failed to update menu item', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_delete_item()
{
    $conn = db_connect();
    $item_id = sanitize_input($_POST['item_id']);

    $sql = "DELETE FROM items WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $item_id);

    if ($stmt->execute()) {
        set_flash_message('Menu item deleted successfully', 'success');
    } else {
        set_flash_message('Failed to delete menu item', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function handle_toggle_availability()
{
    $conn = db_connect();
    $item_id = sanitize_input($_POST['item_id']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    $sql = "UPDATE items SET is_available = ? WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $is_available, $item_id);

    if ($stmt->execute()) {
        set_flash_message('Availability updated successfully', 'success');
    } else {
        set_flash_message('Failed to update availability', 'error');
    }

    $stmt->close();
    header('Location: menu.php');
    exit();
}

function get_categories($with_item_count = false)
{
    $conn = db_connect();
    $sql = $with_item_count
        ? "SELECT c.*, COUNT(i.item_id) as item_count FROM categories c LEFT JOIN items i ON c.category_id = i.category_id GROUP BY c.category_id"
        : "SELECT * FROM categories";
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

function get_menu_items($category_id = null, $available_only = false)
{
    $conn = db_connect();
    $sql = "SELECT i.*, c.name as category_name 
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.category_id 
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($category_id !== null) {
        $sql .= " AND i.category_id = ?";
        $types .= 'i';
        $params[] = $category_id;
    }

    if ($available_only) {
        $sql .= " AND i.is_available = 1";
    }

    $sql .= " ORDER BY i.name";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function add_to_cart($item_id, $quantity = 1)
{
    if (is_system_down()) {
        error_log("Attempt to create order blocked due to system downtime");
        return ['error' => 'System is currently down for maintenance. You cannot place orders at this time.'];
    }
    $conn = db_connect();
    $sql = "SELECT item_id, name, price, is_available FROM items WHERE item_id = ? AND is_available = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        return ['success' => false, 'message' => 'Item not found or unavailable'];
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$item_id] = [
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $quantity
        ];
    }

    return ['success' => true, 'message' => 'Item added to cart'];
}

function update_cart_item($item_id, $quantity)
{
    if (!isset($_SESSION['cart'][$item_id])) {
        return ['success' => false, 'message' => 'Item not in cart'];
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$item_id]);
        return ['success' => true, 'message' => 'Item removed from cart'];
    }

    $_SESSION['cart'][$item_id]['quantity'] = $quantity;
    return ['success' => true, 'message' => 'Cart updated'];
}

function remove_from_cart($item_id)
{
    if (isset($_SESSION['cart'][$item_id])) {
        unset($_SESSION['cart'][$item_id]);
        return ['success' => true, 'message' => 'Item removed from cart'];
    }
    return ['success' => false, 'message' => 'Item not in cart'];
}

function get_cart()
{
    return $_SESSION['cart'] ?? [];
}

function get_cart_item_count()
{
    $cart = get_cart();
    return array_sum(array_column($cart, 'quantity'));
}

function clear_cart()
{
    $_SESSION['cart'] = [];
}

// New function to get cart items with full details
function get_cart_items()
{
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return [];
    }

    $conn = db_connect();
    if ($conn === null) {
        error_log("Database connection failed in get_cart_items");
        return [];
    }

    // Extract item IDs from the cart
    $item_ids = array_keys($_SESSION['cart']);
    if (empty($item_ids)) {
        return [];
    }

    // Prepare placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $query = "SELECT item_id, name, image_url, price FROM items WHERE item_id IN ($placeholders) AND is_available = 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in get_cart_items: " . $conn->error);
        return [];
    }

    // Bind parameters dynamically
    $types = str_repeat('i', count($item_ids));
    $stmt->bind_param($types, ...$item_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $item_id = $row['item_id'];
        $items[$item_id] = $row;
        // Add cart-specific details
        $items[$item_id]['quantity'] = $_SESSION['cart'][$item_id]['quantity'];
        $items[$item_id]['price'] = $_SESSION['cart'][$item_id]['price'];
    }
    $stmt->close();

    // Convert to indexed array and filter out items that weren't found or aren't available
    $cart_items = [];
    foreach ($_SESSION['cart'] as $item_id => $cart_data) {
        if (isset($items[$item_id])) {
            $cart_items[] = $items[$item_id];
        }
    }

    return $cart_items;
}
