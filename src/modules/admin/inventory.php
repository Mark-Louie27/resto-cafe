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

$page_title = "Inventory";
$current_page = "inventory";
include __DIR__ . '/include/header.php';

if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $unit = trim($_POST['unit']);
    $cost_per_unit = filter_input(INPUT_POST, 'cost_per_unit', FILTER_VALIDATE_FLOAT);
    $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_FLOAT);
    $supplier_id = !empty($_POST['supplier_id']) ? filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT) : null;
    $storage_location = trim($_POST['storage_location']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if ($item_name && $quantity !== false && $unit && $cost_per_unit !== false && $reorder_level !== false) {
        $conn = db_connect();
        $stmt = $conn->prepare("INSERT INTO inventory (item_name, quantity, unit, cost_per_unit, reorder_level, supplier_id, storage_location, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssdiss", $item_name, $quantity, $unit, $cost_per_unit, $reorder_level, $supplier_id, $storage_location, $expiry_date);

        if ($stmt->execute()) {
            $inventory_id = $conn->insert_id;
            log_event($_SESSION['user_id'], 'inventory_add', "Added new inventory item #$inventory_id ($item_name)");
            set_flash_message("Inventory item added successfully", 'success');
        } else {
            set_flash_message("Failed to add inventory item", 'error');
        }
    } else {
        set_flash_message("Invalid input data", 'error');
    }
}

// Handle inventory updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: inventory.php');
        exit();
    }

    if (isset($_POST['update_item'])) {
        $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
        $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_FLOAT);

        if ($inventory_id && $quantity !== false && $reorder_level !== false) {
            $conn = db_connect();
            $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, reorder_level = ? WHERE inventory_id = ?");
            $stmt->bind_param("ddi", $quantity, $reorder_level, $inventory_id);

            if ($stmt->execute()) {
                log_event($_SESSION['user_id'], 'inventory_update', "Updated inventory item #$inventory_id");
                set_flash_message("Inventory item updated successfully", 'success');
            } else {
                set_flash_message("Failed to update inventory item", 'error');
            }
        }
    } elseif (isset($_POST['restock_item'])) {
        $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);
        $restock_amount = filter_input(INPUT_POST, 'restock_amount', FILTER_VALIDATE_FLOAT);

        if ($inventory_id && $restock_amount !== false && $restock_amount > 0) {
            $conn = db_connect();
            $conn->begin_transaction();

            try {
                // Update inventory
                $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ?, last_restock_date = CURDATE() WHERE inventory_id = ?");
                $stmt->bind_param("di", $restock_amount, $inventory_id);
                $stmt->execute();

                // Log the restock
                $item_name = fetch_value("SELECT item_name FROM inventory WHERE inventory_id = ?", [$inventory_id]);
                $stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, user_id, action, amount, notes) VALUES (?, ?, 'restock', ?, 'Manual restock')");
                $stmt->bind_param("iid", $inventory_id, $_SESSION['user_id'], $restock_amount);
                $stmt->execute();

                $conn->commit();
                log_event($_SESSION['user_id'], 'inventory_restock', "Restocked $restock_amount of item #$inventory_id ($item_name)");
                set_flash_message("Successfully restocked inventory item", 'success');
            } catch (Exception $e) {
                $conn->rollback();
                set_flash_message("Failed to restock inventory item: " . $e->getMessage(), 'error');
            }
        }
    } elseif (isset($_POST['delete_item'])) {
        $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);

        if ($inventory_id) {
            $conn = db_connect();
            $conn->begin_transaction();

            try {
                $item_name = fetch_value("SELECT item_name FROM inventory WHERE inventory_id = ?", [$inventory_id]);
                $stmt = $conn->prepare("DELETE FROM inventory_log WHERE inventory_id = ?");
                $stmt->bind_param("i", $inventory_id);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
                $stmt->bind_param("i", $inventory_id);
                $stmt->execute();

                $conn->commit();
                log_event($_SESSION['user_id'], 'inventory_delete', "Deleted inventory item #$inventory_id ($item_name)");
                set_flash_message("Inventory item deleted successfully", 'success');
            } catch (Exception $e) {
                $conn->rollback();
                set_flash_message("Failed to delete inventory item: " . $e->getMessage(), 'error');
            }
        }
    }
}

// Get inventory items with low stock
$low_stock_items = fetch_all("SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC");

// Prepare query for filtering inventory items
$query = "SELECT i.*, s.name as supplier_name FROM inventory i LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id WHERE 1=1";
$params = [];
$types = "";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $query .= " AND (i.item_name LIKE ? OR i.storage_location LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    if ($_GET['status'] === 'low') {
        $query .= " AND i.quantity <= i.reorder_level";
    } elseif ($_GET['status'] === 'out') {
        $query .= " AND i.quantity = 0";
    } elseif ($_GET['status'] === 'expiring') {
        $query .= " AND i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
}

$query .= " ORDER BY i.item_name";
$inventory_items = fetch_all($query, $params, $types);
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
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

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
                    <h1 class="text-2xl font-bold text-amber-900">Inventory Management</h1>
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
                                <a href="settings.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-white-50">
                <?php display_flash_message(); ?>

                <!-- Low Stock Alerts -->
                <?php if (!empty($low_stock_items)): ?>
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-amber-800">Low Stock Alert</h3>
                                <div class="mt-2 text-sm text-amber-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($low_stock_items as $item): ?>
                                            <li>
                                                <?= htmlspecialchars($item['item_name']) ?> -
                                                <?= number_format($item['quantity'], 2) ?> <?= htmlspecialchars($item['unit']) ?> remaining
                                                (reorder level: <?= number_format($item['reorder_level'], 2) ?>)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Inventory Filters -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-amber-900 mb-1">Search</label>
                            <input type="text" id="search" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" placeholder="Item name or location"
                                class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-amber-900 mb-1">Stock Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="" <?= !isset($_GET['status']) || $_GET['status'] === '' ? 'selected' : '' ?>>All Items</option>
                                <option value="low" <?= isset($_GET['status']) && $_GET['status'] === 'low' ? 'selected' : '' ?>>Low Stock</option>
                                <option value="out" <?= isset($_GET['status']) && $_GET['status'] === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                                <option value="expiring" <?= isset($_GET['status']) && $_GET['status'] === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Inventory Actions -->
                <div class="flex justify-end mb-6 space-x-2">
                    <button onclick="openAddModal()" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add Item
                    </button>
                    <a href="inventory_log.php" class="bg-amber-200 hover:bg-amber-300 text-amber-800 py-2 px-4 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        View Logs
                    </a>
                </div>

                <!-- Inventory Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Item Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Quantity</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Supplier</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Location</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Last Restock</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($inventory_items as $item): ?>
                                    <tr class="hover:bg-amber-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-amber-900"><?= htmlspecialchars($item['item_name']) ?></div>
                                            <div class="text-sm text-amber-700"><?= htmlspecialchars($item['unit']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium <?= $item['quantity'] <= $item['reorder_level'] ? 'text-red-600' : 'text-amber-900' ?>">
                                                <?= number_format($item['quantity'], 2) ?>
                                            </div>
                                            <div class="text-xs text-amber-700">Reorder: <?= number_format($item['reorder_level'], 2) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $item['supplier_name'] ? htmlspecialchars($item['supplier_name']) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= htmlspecialchars($item['storage_location']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $item['last_restock_date'] ? date('M j, Y', strtotime($item['last_restock_date'])) : 'Never' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)"
                                                class="text-amber-600 hover:text-amber-900 mr-3">Edit</button>
                                            <button onclick="openRestockModal(<?= htmlspecialchars(json_encode($item)) ?>)"
                                                class="text-green-600 hover:text-green-900 mr-3">Restock</button>
                                            <button onclick="openDeleteModal(<?= $item['inventory_id'] ?>)"
                                                class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State for Inventory -->
                <?php if (empty($inventory_items)): ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-6 0h6m-9-5h12a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v7a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-amber-900">No inventory items found</h3>
                        <p class="mt-1 text-sm text-amber-700">Add an item to get started.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Inventory Modal -->
    <div id="addModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="closeAddModal()" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Add Inventory Item</h2>
            <form id="addForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="add_item" value="1">
                <div class="space-y-4">
                    <div>
                        <label for="addItemName" class="block text-sm font-medium text-amber-900">Item Name</label>
                        <input type="text" name="item_name" id="addItemName" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="addQuantity" class="block text-sm font-medium text-amber-900">Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="addQuantity" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="addUnit" class="block text-sm font-medium text-amber-900">Unit</label>
                        <input type="text" name="unit" id="addUnit" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="addCostPerUnit" class="block text-sm font-medium text-amber-900">Cost Per Unit</label>
                        <input type="number" step="0.01" name="cost_per_unit" id="addCostPerUnit" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="addReorderLevel" class="block text-sm font-medium text-amber-900">Reorder Level</label>
                        <input type="number" step="0.01" name="reorder_level" id="addReorderLevel" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="addSupplier" class="block text-sm font-medium text-amber-900">Supplier (Optional)</label>
                        <select name="supplier_id" id="addSupplier" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Select a supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="addStorageLocation" class="block text-sm font-medium text-amber-900">Storage Location (Optional)</label>
                        <input type="text" name="storage_location" id="addStorageLocation" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="addExpiryDate" class="block text-sm font-medium text-amber-900">Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date" id="addExpiryDate" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div id="editModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="closeEditModal()" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Edit Inventory Item</h2>
            <form id="editForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="inventory_id" id="editInventoryId">
                <input type="hidden" name="update_item" value="1">
                <div class="space-y-4">
                    <div>
                        <label for="editItemName" class="block text-sm font-medium text-amber-900">Item Name</label>
                        <input type="text" id="editItemName" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" readonly>
                    </div>
                    <div>
                        <label for="editQuantity" class="block text-sm font-medium text-amber-900">Current Quantity</label>
                        <input type="number" step="0.01" name="quantity" id="editQuantity" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                    <div>
                        <label for="editReorderLevel" class="block text-sm font-medium text-amber-900">Reorder Level</label>
                        <input type="number" step="0.01" name="reorder_level" id="editReorderLevel" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restock Modal -->
    <div id="restockModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="closeRestockModal()" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Restock Inventory Item</h2>
            <form id="restockForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="inventory_id" id="restockInventoryId">
                <input type="hidden" name="restock_item" value="1">
                <div class="space-y-4">
                    <div>
                        <label for="restockItemName" class="block text-sm font-medium text-amber-900">Item Name</label>
                        <input type="text" id="restockItemName" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" readonly>
                    </div>
                    <div>
                        <label for="restockCurrentQuantity" class="block text-sm font-medium text-amber-900">Current Quantity</label>
                        <input type="text" id="restockCurrentQuantity" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" readonly>
                    </div>
                    <div>
                        <label for="restockAmount" class="block text-sm font-medium text-amber-900">Amount to Add</label>
                        <input type="number" step="0.01" name="restock_amount" id="restockAmount" min="0.01" class="mt-1 block w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeRestockModal()" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Confirm Restock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative">
            <button onclick="closeDeleteModal()" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Delete Inventory Item</h2>
            <p class="text-amber-700 mb-6">Are you sure you want to delete this inventory item? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="delete_item" value="1">
                <input type="hidden" name="inventory_id" id="deleteInventoryId">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });

        function openEditModal(item) {
            document.getElementById('editInventoryId').value = item.inventory_id;
            document.getElementById('editItemName').value = item.item_name;
            document.getElementById('editQuantity').value = item.quantity;
            document.getElementById('editReorderLevel').value = item.reorder_level;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openRestockModal(item) {
            document.getElementById('restockInventoryId').value = item.inventory_id;
            document.getElementById('restockItemName').value = item.item_name;
            document.getElementById('restockCurrentQuantity').value = item.quantity + ' ' + item.unit;
            document.getElementById('restockAmount').value = '';
            document.getElementById('restockModal').classList.remove('hidden');
        }

        function closeRestockModal() {
            document.getElementById('restockModal').classList.add('hidden');
        }

        function openDeleteModal(inventoryId) {
            document.getElementById('deleteInventoryId').value = inventoryId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            const modals = ['addModal', 'editModal', 'restockModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
</body>

</html>