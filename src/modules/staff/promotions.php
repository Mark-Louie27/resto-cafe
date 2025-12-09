<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Check if user is staff
$user_roles = [];
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

$is_staff = in_array(3, $user_roles); // Staff role_id = 3
if (!$is_staff) {
    set_flash_message('Access denied. You must be a staff member to view this page.', 'error');
    header('Location: /dashboard.php');
    exit();
}

// Handle POST requests (Add/Edit/Delete/Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token.', 'error');
        header('Location: promotions.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_promotion' || $action === 'edit_promotion') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $discount_type = filter_input(INPUT_POST, 'discount_type', FILTER_SANITIZE_STRING);
        $discount_value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
        $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
        $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
        $min_purchase = filter_input(INPUT_POST, 'min_purchase', FILTER_VALIDATE_FLOAT) ?? 0.00;
        $items = isset($_POST['items']) ? array_map('intval', $_POST['items']) : [];

        // Validation
        if (!$name || !$discount_type || !$discount_value || !$start_date || !$end_date) {
            set_flash_message('All required fields must be filled.', 'error');
            header('Location: promotions.php');
            exit();
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            set_flash_message('Start date must be before end date.', 'error');
            header('Location: promotions.php');
            exit();
        }

        if ($discount_value <= 0 || ($discount_type === 'Percentage' && $discount_value > 100)) {
            set_flash_message('Invalid discount value.', 'error');
            header('Location: promotions.php');
            exit();
        }

        if ($action === 'add_promotion') {
            $stmt = $conn->prepare("INSERT INTO promotions (name, description, discount_type, discount_value, start_date, end_date, min_purchase, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, (SELECT staff_id FROM staff WHERE user_id = ?))");
            $stmt->bind_param("sssdssdi", $name, $description, $discount_type, $discount_value, $start_date, $end_date, $min_purchase, $user_id);
            if ($stmt->execute()) {
                $promotion_id = $stmt->insert_id;
                // Add items to promotion_items
                if (!empty($items)) {
                    $stmt_items = $conn->prepare("INSERT INTO promotion_items (promotion_id, item_id) VALUES (?, ?)");
                    foreach ($items as $item_id) {
                        $stmt_items->bind_param("ii", $promotion_id, $item_id);
                        $stmt_items->execute();
                    }
                }
                set_flash_message('Promotion added successfully.', 'success');
            } else {
                set_flash_message('Failed to add promotion: ' . $stmt->error, 'error');
            }
        } elseif ($action === 'edit_promotion') {
            $promotion_id = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
            if (!$promotion_id) {
                set_flash_message('Invalid promotion ID.', 'error');
                header('Location: promotions.php');
                exit();
            }

            $stmt = $conn->prepare("UPDATE promotions SET name = ?, description = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ?, min_purchase = ? WHERE promotion_id = ?");
            $stmt->bind_param("sssdssdi", $name, $description, $discount_type, $discount_value, $start_date, $end_date, $min_purchase, $promotion_id);
            if ($stmt->execute()) {
                // Update promotion_items
                $conn->query("DELETE FROM promotion_items WHERE promotion_id = $promotion_id");
                if (!empty($items)) {
                    $stmt_items = $conn->prepare("INSERT INTO promotion_items (promotion_id, item_id) VALUES (?, ?)");
                    foreach ($items as $item_id) {
                        $stmt_items->bind_param("ii", $promotion_id, $item_id);
                        $stmt_items->execute();
                    }
                }
                set_flash_message('Promotion updated successfully.', 'success');
            } else {
                set_flash_message('Failed to update promotion: ' . $stmt->error, 'error');
            }
        }
    } elseif ($action === 'delete_promotion') {
        $promotion_id = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
        if ($promotion_id) {
            $stmt = $conn->prepare("UPDATE promotions SET is_active = 0 WHERE promotion_id = ?");
            $stmt->bind_param("i", $promotion_id);
            if ($stmt->execute()) {
                set_flash_message('Promotion deleted successfully.', 'success');
            } else {
                set_flash_message('Failed to delete promotion: ' . $stmt->error, 'error');
            }
        } else {
            set_flash_message('Invalid promotion ID.', 'error');
        }
    } elseif ($action === 'toggle_promotion') {
        $promotion_id = filter_input(INPUT_POST, 'promotion_id', FILTER_VALIDATE_INT);
        $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);
        if ($promotion_id && ($is_active === 0 || $is_active === 1)) {
            $stmt = $conn->prepare("UPDATE promotions SET is_active = ? WHERE promotion_id = ?");
            $stmt->bind_param("ii", $is_active, $promotion_id);
            if ($stmt->execute()) {
                set_flash_message('Promotion status updated successfully.', 'success');
            } else {
                set_flash_message('Failed to update promotion status: ' . $stmt->error, 'error');
            }
        } else {
            set_flash_message('Invalid request.', 'error');
        }
    }

    header('Location: promotions.php');
    exit();
}

// Fetch all items for the add/edit form
$items = $conn->query("SELECT item_id, name FROM items WHERE is_available = 1")->fetch_all(MYSQLI_ASSOC);

// Fetch promotions
$active_filter = filter_input(INPUT_GET, 'active', FILTER_SANITIZE_STRING) ?? 'all';
$where_clause = "";
if ($active_filter === '1') {
    $where_clause = "WHERE p.is_active = 1 AND p.end_date >= CURDATE()";
} elseif ($active_filter === '0') {
    $where_clause = "WHERE p.is_active = 0 OR p.end_date < CURDATE()";
}
$query = "SELECT p.*, u.first_name, u.last_name, GROUP_CONCAT(i.name) as item_names
          FROM promotions p 
          LEFT JOIN staff s ON p.created_by = s.staff_id 
          LEFT JOIN users u ON s.user_id = u.user_id
          LEFT JOIN promotion_items pi ON p.promotion_id = pi.promotion_id
          LEFT JOIN items i ON pi.item_id = i.item_id
          $where_clause 
          GROUP BY p.promotion_id
          ORDER BY p.start_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Promotions";
$current_page = "promotions";

include __DIR__ . '/includes/header.php';
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
                        slate: {
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

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #eab308;
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }

        .sort-icon {
            transition: transform 0.3s ease;
        }

        .sort-icon.asc {
            transform: rotate(180deg);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-amber-50 to-slate-100 font-sans min-h-screen">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10 border-b border-amber-100">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-amber-600">Promotions</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-amber-100 transition-all">
                                <i class="fas fa-bell text-amber-600"></i>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-600 flex items-center justify-center text-white font-medium">
                                    <?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-amber-600 font-medium"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-amber-600"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-amber-100" id="userMenu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition-colors">Your Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition-colors">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 pb-8">
                <div class="bg-white shadow">
                    <div class="px-4 sm:px-6 lg:mx-auto lg:max-w-7xl lg:px-8">
                        <div class="py-6 md:flex md:items-center md:justify-between lg:border-t lg:border-amber-100">
                            <div class="min-w-0 flex-1">
                                <h1 class="text-2xl font-bold leading-7 text-amber-600 sm:truncate sm:text-3xl sm:tracking-tight">
                                    Promotions
                                </h1>
                            </div>
                            <div class="mt-4 flex md:mt-0 md:ml-4">
                                <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                    <i class="fas fa-plus mr-2"></i> Add Promotion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <?php display_flash_message(); ?>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-lg font-medium text-amber-600">All Promotions</h2>
                                <div>
                                    <label for="active_filter" class="sr-only">Filter by Active Status</label>
                                    <select id="active_filter" class="block pl-3 pr-8 py-2 text-sm border-amber-200 focus:outline-none focus:ring-amber-500 focus:border-amber-600 rounded-md">
                                        <option value="all" <?= $active_filter === 'all' ? 'selected' : '' ?>>All Promotions</option>
                                        <option value="1" <?= $active_filter === '1' ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= $active_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <?php if (empty($promotions)): ?>
                                <p class="text-sm text-gray-500 text-center py-4">No promotions found.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-amber-100" id="promotionsTable">
                                        <thead>
                                            <tr class="bg-amber-50">
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" data-sort="name">
                                                    Name <i class="fas fa-sort sort-icon ml-1"></i>
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount Type</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount Value</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min Purchase</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" data-sort="start_date">
                                                    Dates <i class="fas fa-sort sort-icon ml-1"></i>
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-amber-50">
                                            <?php foreach ($promotions as $promo): ?>
                                                <tr class="hover:bg-amber-50 transition-all">
                                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($promo['name']) ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($promo['discount_type']) ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">
                                                        <?php
                                                        $discount = $promo['discount_type'] === 'Percentage'
                                                            ? $promo['discount_value'] . '%'
                                                            : '₱' . number_format($promo['discount_value'], 2);
                                                        echo $discount;
                                                        ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">₱<?= number_format($promo['min_purchase'], 2) ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">
                                                        <?= date('M d, Y', strtotime($promo['start_date'])) ?> -
                                                        <?= date('M d, Y', strtotime($promo['end_date'])) ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">
                                                        <?= htmlspecialchars($promo['item_names'] ?? 'All Items') ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-600">
                                                        <?= htmlspecialchars($promo['first_name'] . ' ' . $promo['last_name'] ?? 'N/A') ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <label class="toggle-switch">
                                                            <input type="checkbox" <?= $promo['is_active'] && strtotime($promo['end_date']) >= time() ? 'checked' : '' ?> onchange="togglePromotion(<?= $promo['promotion_id'] ?>, this.checked)">
                                                            <span class="slider"></span>
                                                        </label>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <button onclick='openEditModal(<?= json_encode($promo) ?>)' class="text-amber-600 hover:text-amber-700 mr-3">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="openDeleteModal(<?= $promo['promotion_id'] ?>)" class="text-red-600 hover:text-red-700">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Promotion Modal -->
    <div id="addPromotionModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative animate-slide-up">
            <button onclick="toggleModal('addPromotionModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Promotion
            </h2>
            <form id="addPromotionForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="add_promotion">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="add_name" class="block text-sm font-medium text-gray-700">Promotion Name *</label>
                        <input type="text" id="add_name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="add_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="add_description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="add_discount_type" class="block text-sm font-medium text-gray-700">Discount Type *</label>
                            <select id="add_discount_type" name="discount_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="Percentage">Percentage</option>
                                <option value="Fixed Amount">Fixed Amount</option>
                                <option value="Buy One Get One">Buy One Get One</option>
                                <option value="Free Item">Free Item</option>
                            </select>
                        </div>
                        <div>
                            <label for="add_discount_value" class="block text-sm font-medium text-gray-700">Discount Value *</label>
                            <input type="number" id="add_discount_value" name="discount_value" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="add_start_date" class="block text-sm font-medium text-gray-700">Start Date *</label>
                            <input type="date" id="add_start_date" name="start_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="add_end_date" class="block text-sm font-medium text-gray-700">End Date *</label>
                            <input type="date" id="add_end_date" name="end_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="add_min_purchase" class="block text-sm font-medium text-gray-700">Minimum Purchase</label>
                        <input type="number" id="add_min_purchase" name="min_purchase" step="0.01" value="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Applicable Items</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-2">
                            <?php foreach ($items as $item): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="items[]" value="<?= $item['item_id'] ?>" class="rounded text-amber-600 focus:ring-amber-500">
                                    <span class="text-sm text-gray-600"><?= htmlspecialchars($item['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('addPromotionModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                        Add Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Promotion Modal -->
    <div id="editPromotionModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative animate-slide-up">
            <button onclick="toggleModal('editPromotionModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit Promotion
            </h2>
            <form id="editPromotionForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="edit_promotion">
                <input type="hidden" name="promotion_id" id="edit_promotion_id">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700">Promotion Name *</label>
                        <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="edit_description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_discount_type" class="block text-sm font-medium text-gray-700">Discount Type *</label>
                            <select id="edit_discount_type" name="discount_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="Percentage">Percentage</option>
                                <option value="Fixed Amount">Fixed Amount</option>
                                <option value="Buy One Get One">Buy One Get One</option>
                                <option value="Free Item">Free Item</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_discount_value" class="block text-sm font-medium text-gray-700">Discount Value *</label>
                            <input type="number" id="edit_discount_value" name="discount_value" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_start_date" class="block text-sm font-medium text-gray-700">Start Date *</label>
                            <input type="date" id="edit_start_date" name="start_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="edit_end_date" class="block text-sm font-medium text-gray-700">End Date *</label>
                            <input type="date" id="edit_end_date" name="end_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit_min_purchase" class="block text-sm font-medium text-gray-700">Minimum Purchase</label>
                        <input type="number" id="edit_min_purchase" name="min_purchase" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Applicable Items</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-2">
                            <?php foreach ($items as $item): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="items[]" value="<?= $item['item_id'] ?>" class="rounded text-amber-600 focus:ring-amber-500 edit-item-checkbox">
                                    <span class="text-sm text-gray-600"><?= htmlspecialchars($item['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('editPromotionModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Promotion Modal -->
    <div id="deletePromotionModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative animate-slide-up">
            <button onclick="toggleModal('deletePromotionModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> Delete Promotion
            </h2>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this promotion? This action cannot be undone.</p>
            <form id="deletePromotionForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete_promotion">
                <input type="hidden" name="promotion_id" id="delete_promotion_id">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('deletePromotionModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-6 rounded-md transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold py-2 px-6 rounded-md transition duration-300">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // User menu toggle
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Modal toggle function
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.toggle('hidden');
        }

        // Open Add Promotion Modal
        function openAddModal() {
            document.getElementById('addPromotionForm').reset();
            toggleModal('addPromotionModal');
        }

        // Open Edit Promotion Modal
        function openEditModal(promotion) {
            document.getElementById('edit_promotion_id').value = promotion.promotion_id;
            document.getElementById('edit_name').value = promotion.name;
            document.getElementById('edit_description').value = promotion.description || '';
            document.getElementById('edit_discount_type').value = promotion.discount_type;
            document.getElementById('edit_discount_value').value = promotion.discount_value;
            document.getElementById('edit_start_date').value = promotion.start_date;
            document.getElementById('edit_end_date').value = promotion.end_date;
            document.getElementById('edit_min_purchase').value = promotion.min_purchase;

            // Handle applicable items
            const itemNames = (promotion.item_names || '').split(',').map(name => name.trim());
            const checkboxes = document.querySelectorAll('.edit-item-checkbox');
            checkboxes.forEach(checkbox => {
                const itemName = checkbox.nextElementSibling.textContent.trim();
                checkbox.checked = itemNames.includes(itemName);
            });

            toggleModal('editPromotionModal');
        }

        // Open Delete Promotion Modal
        function openDeleteModal(promotionId) {
            document.getElementById('delete_promotion_id').value = promotionId;
            toggleModal('deletePromotionModal');
        }

        // Toggle Promotion Status
        function togglePromotion(promotionId, isActive) {
            const formData = new FormData();
            formData.append('action', 'toggle_promotion');
            formData.append('promotion_id', promotionId);
            formData.append('is_active', isActive ? 1 : 0);
            formData.append('csrf_token', '<?= generate_csrf_token() ?>');

            fetch('promotions.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    alert('Failed to update promotion status.');
                }
            });
        }

        // Filter and Sort Functionality
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.getElementById('promotionsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const activeFilter = document.getElementById('active_filter');
            let sortDirection = {};

            // Filter
            activeFilter.addEventListener('change', () => {
                const filter = activeFilter.value;
                window.location.href = `promotions.php?active=${filter}`;
            });

            // Sort
            table.querySelectorAll('th[data-sort]').forEach(header => {
                header.addEventListener('click', () => {
                    const column = header.dataset.sort;
                    const isAsc = sortDirection[column] !== 'asc';
                    sortDirection[column] = isAsc ? 'asc' : 'desc';

                    // Update sort icon
                    header.querySelector('.sort-icon').classList.toggle('asc', isAsc);

                    rows.sort((a, b) => {
                        let aValue = a.querySelector(`td:nth-child(${Array.from(header.parentElement.children).indexOf(header) + 1})`).textContent.trim();
                        let bValue = b.querySelector(`td:nth-child(${Array.from(header.parentElement.children).indexOf(header) + 1})`).textContent.trim();

                        if (column === 'start_date') {
                            aValue = new Date(aValue.split(' - ')[0]);
                            bValue = new Date(bValue.split(' - ')[0]);
                        }

                        if (isAsc) {
                            return aValue > bValue ? 1 : -1;
                        } else {
                            return aValue < bValue ? 1 : -1;
                        }
                    });

                    tbody.innerHTML = '';
                    rows.forEach(row => tbody.appendChild(row));
                });
            });

            // Close modals when clicking outside
            document.addEventListener('click', (e) => {
                const modals = ['addPromotionModal', 'editPromotionModal', 'deletePromotionModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal && e.target === modal) {
                        modal.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</body>

</html>