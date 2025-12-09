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

$page_title = "Inventory Logs";
$current_page = "inventory";
include __DIR__ . '/include/header.php';

// Prepare query for filtering inventory logs
$query = "SELECT il.*, i.item_name, u.first_name, u.last_name 
          FROM inventory_log il
          LEFT JOIN inventory i ON il.inventory_id = i.inventory_id
          LEFT JOIN users u ON il.user_id = u.user_id
          WHERE 1=1";
$params = [];
$types = "";

if (isset($_GET['action']) && !empty($_GET['action'])) {
    $query .= " AND il.action = ?";
    $params[] = $_GET['action'];
    $types .= "s";
}

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $query .= " AND il.created_at >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $query .= " AND il.created_at <= ?";
    $params[] = $_GET['end_date'] . " 23:59:59";
    $types .= "s";
}

$query .= " ORDER BY il.created_at DESC";
$logs = fetch_all($query, $params, $types);
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
                    <h1 class="text-2xl font-bold text-amber-900">Inventory Logs</h1>
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

                <!-- Log Filters -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="action" class="block text-sm font-medium text-amber-900 mb-1">Action Type</label>
                            <select id="action" name="action" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                <option value="" <?= !isset($_GET['action']) || $_GET['action'] === '' ? 'selected' : '' ?>>All Actions</option>
                                <option value="restock" <?= isset($_GET['action']) && $_GET['action'] === 'restock' ? 'selected' : '' ?>>Restock</option>
                                <option value="update" <?= isset($_GET['action']) && $_GET['action'] === 'update' ? 'selected' : '' ?>>Update</option>
                                <option value="delete" <?= isset($_GET['action']) && $_GET['action'] === 'delete' ? 'selected' : '' ?>>Delete</option>
                            </select>
                        </div>
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-amber-900 mb-1">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div class="flex items-end space-x-2">
                            <div class="w-full">
                                <label for="end_date" class="block text-sm font-medium text-amber-900 mb-1">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-md">Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Inventory Logs Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Item</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-amber-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= date('M j, Y H:i', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $log['item_name'] ? htmlspecialchars($log['item_name']) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= ucfirst($log['action']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $log['amount'] ? number_format($log['amount'], 2) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= $log['first_name'] ? htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) : 'N/A' ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-amber-700">
                                            <?= htmlspecialchars($log['notes'] ?? 'N/A') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State for Logs -->
                <?php if (empty($logs)): ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-6 0h6m-9-5h12a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v7a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-amber-900">No inventory logs found</h3>
                        <p class="mt-1 text-sm text-amber-700">Logs will appear here once inventory actions are performed.</p>
                    </div>
                <?php endif; ?>
            </main>
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
    </script>
</body>

</html>