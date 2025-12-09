<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/StaffController.php';

// Check if the user has the 'admin' role
$user_id = $_SESSION['user_id'] ?? null;
$admin = get_user_by_id($user_id);
if (!$admin) {
    set_flash_message('User not found', 'error');
    header('Location: /auth/login.php');
    exit();
}

$conn = db_connect();

$page_title = "Staff Management";
$current_page = "staff";

include __DIR__ . '/include/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: staff.php');
        exit();
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'convert_to_staff':
                handle_convert_to_staff();
                break;
            case 'update_staff':
                handle_update_staff();
                break;
            case 'delete_staff':
                handle_delete_staff();
                break;
            case 'update_schedule':
                handle_update_schedule();
                break;
        }
    }
}

// Get all staff members and customers
$staff_members = get_all_staff();
$all_roles = get_all_roles();
// Get customers who are not staff
$customers = get_all_customers_not_staff();
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
                    <h1 class="text-2xl font-bold text-amber-900">Staff Management</h1>
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

                <!-- Staff List -->
                <div class="mb-12">
                    <h1 class="text-3xl font-bold text-amber-900 mb-8">Staff Members</h1>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-100">
                                <thead class="bg-amber-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Position</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Employment Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Hire Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Roles</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-amber-100">
                                    <?php foreach ($staff_members as $staff): ?>
                                        <?php
                                        $user = get_user_by_id($staff['user_id']);
                                        $roles = get_user_roles($staff['user_id']);
                                        $schedule = get_staff_schedule($staff['staff_id']);
                                        ?>
                                        <tr class="hover:bg-amber-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-amber-200 flex items-center justify-center">
                                                        <span class="text-amber-600 font-medium"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-amber-900"><?= htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']) ?></div>
                                                        <div class="text-sm text-amber-700"><?= htmlspecialchars($user['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                                <?= htmlspecialchars($staff['position']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $staff['employment_status'] === 'Full-time' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= htmlspecialchars($staff['employment_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                                <?= date('M j, Y', strtotime($staff['hire_date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                                <?= implode(', ', array_map('htmlspecialchars', $roles)) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($staff)) ?>, <?= htmlspecialchars(json_encode($user)) ?>, <?= htmlspecialchars(json_encode($roles)) ?>)" class="text-amber-600 hover:text-amber-900 mr-3">Edit</button>
                                                <button onclick="openScheduleModal(<?= $staff['staff_id'] ?>, <?= htmlspecialchars(json_encode($schedule)) ?>)" class="text-purple-600 hover:text-purple-900 mr-3">Schedule</button>
                                                <button onclick="openDeleteModal(<?= $staff['staff_id'] ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Empty State for Staff -->
                    <?php if (empty($staff_members)): ?>
                        <div class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <h3 class="mt-2 text-lg font-medium text-amber-900">No staff found</h3>
                            <p class="mt-1 text-sm text-amber-700">Convert a customer to a staff member to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Customer List -->
                <div>
                    <h1 class="text-3xl font-bold text-amber-900 mb-8">Customers</h1>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-100">
                                <thead class="bg-amber-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Membership Level</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-amber-100">
                                    <?php foreach ($customers as $customer): ?>
                                        <?php
                                        $user = get_user_by_id($customer['user_id']);
                                        ?>
                                        <tr class="hover:bg-amber-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-amber-200 flex items-center justify-center">
                                                        <span class="text-amber-600 font-medium"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-amber-900"><?= htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                                <?= htmlspecialchars($user['email']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                                <?= htmlspecialchars($customer['membership_level']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openConvertModal(<?= htmlspecialchars(json_encode($customer)) ?>, <?= htmlspecialchars(json_encode($user)) ?>)" class="text-green-600 hover:text-green-900">Convert to Staff</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Empty State for Customers -->
                    <?php if (empty($customers)): ?>
                        <div class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <h3 class="mt-2 text-lg font-medium text-amber-900">No customers found</h3>
                            <p class="mt-1 text-sm text-amber-700">Customers need to register to appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Convert to Staff Modal -->
    <div id="convertToStaffModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('convertToStaffModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Convert to Staff</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="convert_to_staff">
                <input type="hidden" name="user_id" id="convert_user_id">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="convert_position" class="block text-sm font-medium text-amber-900">Position</label>
                        <input type="text" name="position" id="convert_position" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="convert_employment_status" class="block text-sm font-medium text-amber-900">Employment Status</label>
                        <select id="convert_employment_status" name="employment_status" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-amber-900">Additional Roles</label>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($all_roles as $role): ?>
                                <?php if ($role['role_name'] !== 'customer' && $role['role_name'] !== 'staff'): ?>
                                    <div class="flex items-center">
                                        <input id="convert_role_<?= $role['role_id'] ?>" name="roles[]" type="checkbox" value="<?= $role['role_id'] ?>" class="focus:ring-amber-500 h-4 w-4 text-amber-600 border-amber-300 rounded">
                                        <label for="convert_role_<?= $role['role_id'] ?>" class="ml-2 block text-sm text-amber-700"><?= htmlspecialchars(ucfirst($role['role_name'])) ?></label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('convertToStaffModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Convert to Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('editStaffModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Edit Staff Member</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_staff">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="grid grid-cols-1 gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_first_name" class="block text-sm font-medium text-amber-900">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="edit_last_name" class="block text-sm font-medium text-amber-900">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-amber-900">Email</label>
                        <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_username" class="block text-sm font-medium text-amber-900">Username</label>
                        <input type="text" name="username" id="edit_username" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_phone" class="block text-sm font-medium text-amber-900">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_position" class="block text-sm font-medium text-amber-900">Position</label>
                        <input type="text" name="position" id="edit_position" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_employment_status" class="block text-sm font-medium text-amber-900">Employment Status</label>
                        <select id="edit_employment_status" name="employment_status" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-amber-900">Roles</label>
                        <div class="mt-2 space-y-2">
                            <?php foreach ($all_roles as $role): ?>
                                <?php if ($role['role_name'] !== 'customer'): ?>
                                    <div class="flex items-center">
                                        <input id="edit_role_<?= $role['role_id'] ?>" name="roles[]" type="checkbox" value="<?= $role['role_id'] ?>" class="focus:ring-amber-500 h-4 w-4 text-amber-600 border-amber-300 rounded">
                                        <label for="edit_role_<?= $role['role_id'] ?>" class="ml-2 block text-sm text-amber-700"><?= htmlspecialchars(ucfirst($role['role_name'])) ?></label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('editStaffModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Update Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full p-6 relative">
            <button onclick="toggleModal('scheduleModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Staff Schedule</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="staff_id" id="schedule_staff_id">
                <div id="scheduleContainer" class="space-y-4">
                    <!-- Schedule inputs will be added here by JavaScript -->
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('scheduleModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative">
            <button onclick="toggleModal('deleteModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Delete Staff Member</h2>
            <p class="text-amber-700 mb-6">Are you sure you want to delete this staff member? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete_staff">
                <input type="hidden" name="staff_id" id="delete_staff_id">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('deleteModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
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

        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        function openConvertModal(customer, user) {
            document.getElementById('convert_user_id').value = customer.user_id;
            toggleModal('convertToStaffModal');
        }

        function openEditModal(staff, user, roles) {
            document.getElementById('edit_staff_id').value = staff.staff_id;
            document.getElementById('edit_user_id').value = staff.user_id;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_position').value = staff.position;
            document.getElementById('edit_employment_status').value = staff.employment_status;

            document.querySelectorAll('#editStaffModal input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });

            roles.forEach(role => {
                const checkbox = document.querySelector(`#editStaffModal input[value="${role}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });

            toggleModal('editStaffModal');
        }

        function openScheduleModal(staffId, schedule) {
            document.getElementById('schedule_staff_id').value = staffId;

            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const container = document.getElementById('scheduleContainer');
            container.innerHTML = '';

            days.forEach(day => {
                const daySchedule = schedule.find(s => s.day_of_week === day) || {
                    day_of_week: day,
                    start_time: '',
                    end_time: ''
                };

                const dayDiv = document.createElement('div');
                dayDiv.className = 'p-4 border border-amber-200 rounded-lg';
                dayDiv.innerHTML = `
                    <h4 class="text-md font-medium text-amber-900 mb-2">${day}</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="start_${day}" class="block text-sm font-medium text-amber-900">Start Time</label>
                            <input type="time" id="start_${day}" name="schedule[${day}][start_time]" value="${daySchedule.start_time}" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="end_${day}" class="block text-sm font-medium text-amber-900">End Time</label>
                            <input type="time" id="end_${day}" name="schedule[${day}][end_time]" value="${daySchedule.end_time}" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <input type="hidden" name="schedule[${day}][day_of_week]" value="${day}">
                `;

                container.appendChild(dayDiv);
            });

            toggleModal('scheduleModal');
        }

        function openDeleteModal(staffId) {
            document.getElementById('delete_staff_id').value = staffId;
            toggleModal('deleteModal');
        }

        document.addEventListener('click', (e) => {
            const modals = ['convertToStaffModal', 'editStaffModal', 'scheduleModal', 'deleteModal'];
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