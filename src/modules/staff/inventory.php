<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Check if user is manager
$user_roles = [];
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

$is_manager = in_array(2, $user_roles); // Manager role_id = 2
if (!$is_manager) {
    set_flash_message('Access denied. You must be a manager to view this page.', 'error');
    header('Location: /dashboard.php');
    exit();
}

// Handle inventory updates and additions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: inventory.php');
        exit();
    }

    $conn->begin_transaction();
    try {
        if (isset($_POST['update_quantity'])) {
            $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);

            if ($inventory_id && $quantity !== false && $quantity >= 0) {
                $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, last_restock_date = CURDATE() WHERE inventory_id = ?");
                $stmt->bind_param("di", $quantity, $inventory_id);
                $stmt->execute();
                set_flash_message('Inventory updated successfully', 'success');
            } else {
                throw new Exception('Invalid quantity or inventory ID');
            }
        } elseif (isset($_POST['add_item'])) {
            $item_name = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
            $unit = filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING);
            $cost_per_unit = filter_input(INPUT_POST, 'cost_per_unit', FILTER_VALIDATE_FLOAT);
            $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_FLOAT);
            $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT) ?: null;

            if ($item_name && $quantity !== false && $unit && $cost_per_unit !== false && $reorder_level !== false) {
                $stmt = $conn->prepare("INSERT INTO inventory (item_name, quantity, unit, cost_per_unit, reorder_level, supplier_id, last_restock_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
                $stmt->bind_param("sdssdi", $item_name, $quantity, $unit, $cost_per_unit, $reorder_level, $supplier_id);
                $stmt->execute();
                set_flash_message('Inventory item added successfully', 'success');
            } else {
                throw new Exception('Invalid input data');
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        set_flash_message('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: inventory.php');
    exit();
}

// Filter inventory
$low_stock_filter = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';
$where_clause = $low_stock_filter ? "WHERE quantity <= reorder_level" : "";
$query = "SELECT i.*, s.name as supplier_name 
          FROM inventory i 
          LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
          $where_clause 
          ORDER BY i.item_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get suppliers for the add form
$suppliers = [];
$stmt = $conn->prepare("SELECT supplier_id, name FROM suppliers ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

$page_title = "Manage Inventory";
$current_page = "inventory";

include __DIR__ . '/includes/header.php';
?>

<?php
// [Keep your existing PHP code at the top]
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Casa Baraka</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
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
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            },
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(10px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            },
                        },
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .badge {
            transition: all 0.2s ease;
        }

        .badge:hover {
            transform: scale(1.05);
        }

        .sidebar-item {
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            background-color: rgba(234, 179, 8, 0.1);
            transform: translateX(3px);
        }

        .sidebar-item.active {
            background-color: rgba(234, 179, 8, 0.2);
            border-left: 3px solid #eab308;
        }

        .inventory-card {
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .inventory-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .low-stock {
            background-color: #fef3c7;
            border-left: 4px solid #d97706;
        }

        .critical-stock {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
        }

        .scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .scrollbar::-webkit-scrollbar-track {
            background: rgba(234, 179, 8, 0.1);
        }

        .scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(234, 179, 8, 0.4);
            border-radius: 3px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge i {
            margin-right: 0.25rem;
            font-size: 0.625rem;
        }
    </style>
</head>

<body class="h-full">
    <div class="flex h-full">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <button id="sidebar-toggle" class="text-gray-500 focus:outline-none lg:hidden">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">Inventory Management</h1>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button class="p-2 text-gray-500 hover:text-primary-600 relative">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span>
                        </button>

                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center space-x-2 focus:outline-none">
                                <div class="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-gray-400 text-xs"></i>
                            </button>

                            <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 border border-gray-200">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-200">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Inventory Dashboard</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Manage and track all inventory items
                            </p>
                        </div>

                        <div class="flex items-center space-x-3 mt-4 md:mt-0">
                            <a href="inventory.php<?= $low_stock_filter ? '' : '?low_stock=1' ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white <?= $low_stock_filter ? 'bg-secondary-600 hover:bg-secondary-700' : 'bg-primary-600 hover:bg-primary-700' ?> focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-filter mr-2"></i>
                                <?= $low_stock_filter ? 'Show All Items' : 'Show Low Stock Only' ?>
                            </a>
                        </div>
                    </div>

                    <?php display_flash_message(); ?>

                    <!-- Add New Item Card -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6 inventory-card animate-fade-in">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-plus-circle mr-2 text-primary-600"></i>
                                Add New Inventory Item
                            </h3>
                        </div>

                        <div class="px-4 py-5 sm:p-6">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label for="item_name" class="block text-sm font-medium text-gray-700">Item Name *</label>
                                        <input type="text" id="item_name" name="item_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700">Initial Quantity *</label>
                                        <input type="number" step="0.01" id="quantity" name="quantity" required min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="unit" class="block text-sm font-medium text-gray-700">Unit of Measure *</label>
                                        <input type="text" id="unit" name="unit" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="cost_per_unit" class="block text-sm font-medium text-gray-700">Cost per Unit (₱) *</label>
                                        <input type="number" step="0.01" id="cost_per_unit" name="cost_per_unit" required min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="reorder_level" class="block text-sm font-medium text-gray-700">Reorder Level *</label>
                                        <input type="number" step="0.01" id="reorder_level" name="reorder_level" required min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                                        <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="">Select Supplier</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <button type="submit" name="add_item" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        <i class="fas fa-save mr-2"></i> Add Inventory Item
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Inventory List -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg inventory-card animate-fade-in">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    <i class="fas fa-boxes mr-2 text-primary-600"></i>
                                    Inventory Items
                                </h3>
                                <p class="text-sm text-gray-500">
                                    <?= count($inventory) ?> item<?= count($inventory) !== 1 ? 's' : '' ?>
                                    <?= $low_stock_filter ? '(Low Stock Only)' : '' ?>
                                </p>
                            </div>
                        </div>

                        <?php if (empty($inventory)): ?>
                            <div class="px-4 py-12 sm:px-6 text-center">
                                <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900">No inventory items found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    <?= $low_stock_filter ? 'No low stock items at this time.' : 'Add your first inventory item using the form above.' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto scrollbar">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Level</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($inventory as $item): ?>
                                            <tr class="<?= $item['quantity'] <= 0 ? 'critical-stock' : ($item['quantity'] <= $item['reorder_level'] ? 'low-stock' : '') ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['item_name']) ?></div>
                                                    <div class="text-sm text-gray-500">Reorder: <?= number_format($item['reorder_level'], 2) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium <?= $item['quantity'] <= 0 ? 'text-red-600' : ($item['quantity'] <= $item['reorder_level'] ? 'text-yellow-600' : 'text-gray-900') ?>">
                                                        <?= number_format($item['quantity'], 2) ?>
                                                    </div>
                                                    <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                                                        <div class="mt-1">
                                                            <span class="badge <?= $item['quantity'] <= 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                                <i class="fas fa-exclamation-circle"></i>
                                                                <?= $item['quantity'] <= 0 ? 'Out of Stock' : 'Low Stock' ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($item['unit']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ₱<?= number_format($item['cost_per_unit'], 2) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($item['supplier_name'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $item['last_restock_date'] ? date('M j, Y', strtotime($item['last_restock_date'])) : '—' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <form method="POST" class="inline-flex items-center space-x-2">
                                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                        <input type="hidden" name="inventory_id" value="<?= $item['inventory_id'] ?>">
                                                        <input type="number" step="0.01" name="quantity" value="<?= number_format($item['quantity'], 2) ?>" required min="0" class="w-24 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                        <button type="submit" name="update_quantity" class="text-primary-600 hover:text-primary-700">
                                                            <i class="fas fa-sync-alt"></i> Update
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });

        // Toggle user menu
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('#user-menu-button') && !event.target.closest('#user-menu')) {
                document.getElementById('user-menu').classList.add('hidden');
            }
        });
    </script>
</body>

</html>