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

// Handle table status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: tables.php');
        exit();
    }

    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($table_id && in_array($new_status, ['Available', 'Occupied', 'Reserved', 'Maintenance'])) {
        $stmt = $conn->prepare("UPDATE restaurant_tables SET status = ? WHERE table_id = ?");
        $stmt->bind_param("si", $new_status, $table_id);
        if ($stmt->execute()) {
            set_flash_message('Table status updated successfully', 'success');
        } else {
            set_flash_message('Failed to update table status', 'error');
        }
    } else {
        set_flash_message('Invalid table or status', 'error');
    }
    header('Location: tables.php');
    exit();
}

// Filter tables by status
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? 'all';
$where_clause = $status_filter !== 'all' ? "WHERE status = ?" : "";
$query = "SELECT table_id, table_number, capacity, location, status 
          FROM restaurant_tables 
          $where_clause 
          ORDER BY table_number";
$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$tables = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Tables";
$current_page = "tables";

include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Management | Resto Cafe</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
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

        .status-available {
            background-color: #f0fdf4;
            color: #16a34a;
        }

        .status-occupied {
            background-color: #fff7ed;
            color: #ea580c;
        }

        .status-reserved {
            background-color: #eff6ff;
            color: #2563eb;
        }

        .status-maintenance {
            background-color: #fef2f2;
            color: #dc2626;
        }

        /* Modal styles */
        .modal {
            transition: opacity 0.25s ease;
        }

        .modal-body {
            transition: all 0.25s ease;
            transform: translateY(-20px);
        }

        .modal.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal.active .modal-body {
            transform: translateY(0);
        }

        /* Toast notification */
        .toast {
            transform: translateY(1rem);
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .toast.active {
            transform: translateY(0);
            opacity: 1;
            pointer-events: all;
        }

        .status-badge {
            @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
        }

        .ts-dropdown {
            @apply rounded-lg shadow-lg border border-amber-100;
        }

        .ts-control {
            @apply border-amber-200 focus:ring-amber-500 focus:border-amber-600;
        }
    </style>
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
                }
            }
        }
    </script>
</head>


<body class="bg-gray-50 font-sans min-h-screen">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10 border-b border-gray-200">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-amber-600">Manage Tables</h1>
                        <div class="ml-4 hidden md:block">
                            <nav class="flex space-x-4" aria-label="Breadcrumb">
                                <ol class="flex items-center space-x-2 text-sm">
                                    <li>
                                        <a href="/dashboard.php" class="text-gray-500 hover:text-amber-600">Dashboard</a>
                                    </li>
                                    <li>
                                        <span class="text-gray-400">/</span>
                                    </li>
                                    <li class="text-gray-700">Tables</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="p-2 rounded-full hover:bg-amber-50 transition-colors">
                                <i class="fas fa-bell text-amber-600"></i>
                                <span class="absolute top-0 right-0 h-2 w-2 rounded-full bg-red-500"></span>
                            </button>
                        </div>
                        <div class="relative">
                            <button class="flex items-center space-x-2 focus:outline-none group" id="userMenuButton">
                                <div class="h-8 w-8 rounded-full bg-amber-600 flex items-center justify-center text-white font-medium group-hover:bg-amber-700 transition-colors">
                                    <?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?>
                                </div>
                                <span class="hidden md:inline text-gray-700 font-medium"><?= htmlspecialchars($_SESSION['first_name']) ?></span>
                                <i class="fas fa-chevron-down hidden md:inline text-gray-500 group-hover:text-gray-700 transition-colors"></i>
                            </button>
                            <div class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-gray-200 divide-y divide-gray-100" id="userMenu">
                                <div class="py-1">
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"><i class="fas fa-user-circle mr-2"></i>Your Profile</a>
                                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"><i class="fas fa-cog mr-2"></i>Settings</a>
                                </div>
                                <div class="py-1">
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"><i class="fas fa-sign-out-alt mr-2"></i>Sign out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                            <div class="mb-4 md:mb-0">
                                <h1 class="text-2xl font-bold text-gray-900">Table Management</h1>
                                <p class="mt-1 text-sm text-gray-500">View and manage all restaurant tables</p>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="openAddTableModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors">
                                    <i class="fas fa-plus mr-2"></i> Add New Table
                                </button>
                            </div>
                        </div>

                        <?php display_flash_message(); ?>

                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="mb-4 sm:mb-0">
                                    <h2 class="text-lg font-medium text-gray-900">Tables List</h2>
                                    <p class="text-sm text-gray-500">Showing <?= count($tables) ?> tables</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="relative w-48">
                                        <label for="status_filter" class="sr-only">Filter by Status</label>
                                        <select id="status_filter" onchange="window.location.href='tables.php?status='+this.value" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-amber-500 focus:border-amber-600 sm:text-sm rounded-md">
                                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                            <option value="Available" <?= $status_filter === 'Available' ? 'selected' : '' ?>>Available</option>
                                            <option value="Occupied" <?= $status_filter === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                                            <option value="Reserved" <?= $status_filter === 'Reserved' ? 'selected' : '' ?>>Reserved</option>
                                            <option value="Maintenance" <?= $status_filter === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        </select>
                                    </div>
                                    <button class="p-2 text-gray-500 hover:text-amber-600 rounded-full hover:bg-amber-50 transition-colors" title="Refresh">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if (empty($tables)): ?>
                                <div class="px-6 py-12 text-center">
                                    <i class="fas fa-table fa-3x text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900">No tables found</h3>
                                    <p class="mt-1 text-sm text-gray-500">Get started by adding a new table.</p>
                                    <div class="mt-6">
                                        <a href="add_table.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors">
                                            <i class="fas fa-plus mr-2"></i> Add Table
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table #</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($tables as $table): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                                                                <i class="fas fa-table"></i>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($table['table_number']) ?></div>
                                                                <div class="text-sm text-gray-500">ID: <?= $table['table_id'] ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?= $table['capacity'] ?></div>
                                                        <div class="text-sm text-gray-500">Seats</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($table['location'] ?? 'N/A') ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_class = strtolower($table['status']);
                                                        echo '<span class="status-badge status-' . $status_class . '">' . $table['status'] . '</span>';
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex items-center justify-end space-x-4">
                                                            <form method="POST" class="flex items-center">
                                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                                <input type="hidden" name="table_id" value="<?= $table['table_id'] ?>">
                                                                <select name="status" class="block w-32 pl-3 pr-8 py-1 text-sm border-gray-300 focus:outline-none focus:ring-amber-500 focus:border-amber-600 rounded-md">
                                                                    <option value="Available" <?= $table['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                                                    <option value="Occupied" <?= $table['status'] === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                                                                    <option value="Reserved" <?= $table['status'] === 'Reserved' ? 'selected' : '' ?>>Reserved</option>
                                                                    <option value="Maintenance" <?= $table['status'] === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                                </select>
                                                                <button type="submit" name="update_status" class="ml-2 text-amber-600 hover:text-amber-700 transition-colors" title="Update Status">
                                                                    <i class="fas fa-save"></i>
                                                                </button>
                                                            </form>
                                                            <a href="edit_table.php?id=<?= $table['table_id'] ?>" class="text-blue-600 hover:text-blue-900 transition-colors" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="#" class="text-red-600 hover:text-red-900 transition-colors" title="Delete" onclick="confirmDelete(<?= $table['table_id'] ?>)">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                                    <div class="text-sm text-gray-500">
                                        Showing <span class="font-medium">1</span> to <span class="font-medium"><?= count($tables) ?></span> of <span class="font-medium"><?= count($tables) ?></span> results
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                            Previous
                                        </button>
                                        <button class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Table Modal -->
    <div id="addTableModal" class="modal fixed inset-0 w-full h-full flex items-center justify-center z-50 opacity-0 pointer-events-none">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>

        <div class="modal-body relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add New Table</h3>
                    <button onclick="closeAddTableModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="addTableForm" method="POST" action="add_table.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="table_number" class="block text-sm font-medium text-gray-700">Table Number *</label>
                            <input type="text" id="table_number" name="table_number" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="capacity" class="block text-sm font-medium text-gray-700">Capacity *</label>
                            <input type="number" id="capacity" name="capacity" min="1" max="20" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input type="text" id="location" name="location"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status *</label>
                            <select id="status" name="status" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                                <option value="Available">Available</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Reserved">Reserved</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeAddTableModal()" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            Add Table
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editTableModal" class="modal fixed inset-0 w-full h-full flex items-center justify-center z-50 opacity-0 pointer-events-none">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>

        <div class="modal-body relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Table</h3>
                    <button onclick="closeEditTableModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="editTableForm" method="POST" action="update_table.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" id="edit_table_id" name="table_id" value="">

                    <div class="space-y-4">
                        <div>
                            <label for="edit_table_number" class="block text-sm font-medium text-gray-700">Table Number *</label>
                            <input type="text" id="edit_table_number" name="table_number" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_capacity" class="block text-sm font-medium text-gray-700">Capacity *</label>
                            <input type="number" id="edit_capacity" name="capacity" min="1" max="20" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input type="text" id="edit_location" name="location"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="edit_status" class="block text-sm font-medium text-gray-700">Status *</label>
                            <select id="edit_status" name="status" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                                <option value="Available">Available</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Reserved">Reserved</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeEditTableModal()" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast notification (Add this right before closing </body> tag) -->
    <div id="toast" class="toast fixed bottom-4 right-4 z-50 bg-white shadow-lg rounded-lg p-4 w-80 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p id="toastMessage" class="text-sm font-medium text-gray-900">Table updated successfully</p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button onclick="closeToast()" class="inline-flex text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
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

        // Confirm before deleting
        function confirmDelete(tableId) {
            if (confirm('Are you sure you want to delete this table? This action cannot be undone.')) {
                // In a real implementation, you would redirect to delete handler
                // window.location.href = 'delete_table.php?id=' + tableId;
                console.log('Table deletion requested for ID:', tableId);
            }
        }

        // Initialize enhanced select elements
        document.addEventListener('DOMContentLoaded', function() {
            new TomSelect('#status_filter', {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });
        });

        // Modal functions
        function openAddTableModal() {
            const modal = document.getElementById('addTableModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddTableModal() {
            const modal = document.getElementById('addTableModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Handle form submission with AJAX
        document.getElementById('addTableForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Adding...';

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and refresh the table
                        setFlashMessage(data.message, 'success');
                        closeAddTableModal();
                        window.location.reload();
                    } else {
                        // Show error message
                        setFlashMessage(data.message || 'Error adding table', 'error');
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'Add Table';
                    }
                })
                .catch(error => {
                    setFlashMessage('Network error occurred', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Add Table';
                    console.error('Error:', error);
                });
        });

        // Helper function to show flash messages (you may already have this)
        function setFlashMessage(message, type) {
            // Implement your flash message display logic here
            // This should match how your existing flash messages work
            console.log(`[${type}] ${message}`);
        }

        function updateEditLinks() {
            // Update the edit links to use our new modal instead of redirecting
            document.querySelectorAll('a[href^="edit_table.php"]').forEach(link => {
                const tableId = link.getAttribute('href').split('=')[1];
                link.setAttribute('href', '#');
                link.setAttribute('onclick', `openEditTableModal(${tableId}); return false;`);
            });
        }

        // Call this when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            updateEditLinks();

            // Initialize Tom Select for edit form if needed
            if (document.getElementById('edit_status')) {
                new TomSelect('#edit_status', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    }
                });
            }
        });

        // Edit modal functions
        function openEditTableModal(tableId) {
            const modal = document.getElementById('editTableModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Set the table ID in the form
            document.getElementById('edit_table_id').value = tableId;

            // Fetch table data
            fetch(`get_table.php?id=${tableId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the form with the table data
                        document.getElementById('edit_table_number').value = data.table.table_number;
                        document.getElementById('edit_capacity').value = data.table.capacity;
                        document.getElementById('edit_location').value = data.table.location || '';

                        // For TomSelect, we need to use its API
                        const statusSelect = document.querySelector('#edit_status');
                        if (statusSelect.tomselect) {
                            statusSelect.tomselect.setValue(data.table.status);
                        } else {
                            document.getElementById('edit_status').value = data.table.status;
                        }
                    } else {
                        showToast('Failed to load table data', 'error');
                        closeEditTableModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error loading table data', 'error');
                    closeEditTableModal();
                });
        }

        function closeEditTableModal() {
            const modal = document.getElementById('editTableModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Handle edit form submission with AJAX
        document.getElementById('editTableForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and refresh the table
                        showToast(data.message || 'Table updated successfully', 'success');
                        closeEditTableModal();
                        window.location.reload();
                    } else {
                        // Show error message
                        showToast(data.message || 'Error updating table', 'error');
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'Save Changes';
                    }
                })
                .catch(error => {
                    showToast('Network error occurred', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Save Changes';
                    console.error('Error:', error);
                });
        });

        // Toast notification functions
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');

            // Set message and style based on type
            toastMessage.textContent = message;

            if (type === 'success') {
                toast.classList.remove('border-red-500');
                toast.classList.add('border-green-500');
                toast.querySelector('.fa-check-circle').classList.remove('text-red-500');
                toast.querySelector('.fa-check-circle').classList.add('text-green-500');
            } else {
                toast.classList.remove('border-green-500');
                toast.classList.add('border-red-500');
                toast.querySelector('.fa-check-circle').classList.remove('text-green-500');
                toast.querySelector('.fa-check-circle').classList.add('text-red-500');
                // Change icon to exclamation for error
                toast.querySelector('.fa-check-circle').classList.remove('fa-check-circle');
                toast.querySelector('i').classList.add('fa-exclamation-circle');
            }

            // Show toast
            toast.classList.add('active');

            // Auto hide after 5 seconds
            setTimeout(() => {
                closeToast();
            }, 5000);
        }

        function closeToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('active');

            // Reset icon if needed
            if (toast.querySelector('.fa-exclamation-circle')) {
                toast.querySelector('.fa-exclamation-circle').classList.remove('fa-exclamation-circle');
                toast.querySelector('i').classList.add('fa-check-circle');
            }
        }
    </script>
</body>

</html>