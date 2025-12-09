<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/CustomerController.php';
require_admin(); // Only admins can access this page

$conn = db_connect();
$user_id = $_SESSION['user_id'];
// Get admin data
$admin = get_user_by_id($user_id);
if (!$admin) {
    set_flash_message('User not found', 'error');
    header('Location: /auth/login.php');
    exit();
}

$page_title = "Customers";
$current_page = "customers";

include __DIR__ . '/include/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: customers.php');
        exit();
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_customer':
                handle_update_customer();
                break;
            case 'toggle_status':
                handle_toggle_status();
                break;
            case 'update_loyalty':
                handle_update_loyalty();
                break;
        }
    }
}

// Get all customers with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$conn = db_connect();
$total_customers = fetch_value("SELECT COUNT(*) FROM customers");
$total_pages = ceil($total_customers / $per_page);

$customers = fetch_all(
    "
    SELECT c.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at, u.status 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    ORDER BY c.customer_id DESC 
    LIMIT ? OFFSET ?",
    [$per_page, $offset]
);
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
        /* Smooth transitions for hover effects */
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Fade-in animation for elements */
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

        /* Modal backdrop */
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
                    <h1 class="text-2xl font-bold text-amber-900">Customer Management</h1>
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
                                <a href="/profile.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">Your Profile</a>
                                <a href="/settings.php" class="block px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">Settings</a>
                                <a href="/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-white-50">
                <?php display_flash_message(); ?>

                <!-- Search Bar -->
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold text-amber-900">Customer Management</h1>
                    <div class="relative w-64">
                        <input type="text" placeholder="Search customers..." class="pl-10 pr-4 py-2 border border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 w-full">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>

                <!-- Customer List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead class="bg-amber-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Contact</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Loyalty</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Member Since</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-amber-900 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-amber-900 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($customers as $customer): ?>
                                    <tr class="hover:bg-amber-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-amber-200 flex items-center justify-center">
                                                    <span class="text-amber-600 font-medium"><?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?></span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-amber-900"><?= htmlspecialchars($customer['first_name']) . ' ' . htmlspecialchars($customer['last_name']) ?></div>
                                                    <div class="text-sm text-amber-700"><?= htmlspecialchars($customer['username']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-amber-900"><?= htmlspecialchars($customer['email']) ?></div>
                                            <div class="text-sm text-amber-700"><?= htmlspecialchars($customer['phone']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?=
                                                                                                            $customer['membership_level'] === 'Platinum' ? 'bg-purple-100 text-purple-800' : ($customer['membership_level'] === 'Gold' ? 'bg-yellow-100 text-yellow-800' : ($customer['membership_level'] === 'Silver' ? 'bg-gray-100 text-gray-800' : ($customer['membership_level'] === 'Bronze' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')))
                                                                                                            ?>">
                                                    <?= htmlspecialchars($customer['membership_level']) ?>
                                                </span>
                                                <span class="ml-2 text-sm text-amber-700">(<?= $customer['loyalty_points'] ?> pts)</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-amber-700">
                                            <?= date('M j, Y', strtotime($customer['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $customer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ucfirst($customer['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="openLoyaltyModal(<?= $customer['customer_id'] ?>, <?= $customer['loyalty_points'] ?>, '<?= $customer['membership_level'] ?>')" class="text-green-600 hover:text-green-900 mr-3">Loyalty</button>
                                            <button onclick="openStatusModal(<?= $customer['customer_id'] ?>, '<?= $customer['status'] ?>')" class="text-<?= $customer['status'] === 'active' ? 'red-600 hover:text-red-900' : 'green-600 hover:text-green-900' ?>">
                                                <?= $customer['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-amber-100 sm:px-6">
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-amber-700">
                                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $per_page, $total_customers) ?></span> of <span class="font-medium"><?= $total_customers ?></span> customers
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-amber-200 bg-white text-sm font-medium text-amber-600 hover:bg-amber-50">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'bg-amber-100 border-amber-500 text-amber-900' : 'bg-white border-amber-200 text-amber-600 hover:bg-amber-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?= $page + 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-amber-200 bg-white text-sm font-medium text-amber-600 hover:bg-amber-50">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('editCustomerModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Edit Customer</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <input type="hidden" name="user_id" id="edit_customer_user_id">
                <div class="grid grid-cols-1 gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_customer_first_name" class="block text-sm font-medium text-amber-900">First Name</label>
                            <input type="text" name="first_name" id="edit_customer_first_name" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="edit_customer_last_name" class="block text-sm font-medium text-amber-900">Last Name</label>
                            <input type="text" name="last_name" id="edit_customer_last_name" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit_customer_email" class="block text-sm font-medium text-amber-900">Email</label>
                        <input type="email" name="email" id="edit_customer_email" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_customer_username" class="block text-sm font-medium text-amber-900">Username</label>
                        <input type="text" name="username" id="edit_customer_username" required class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_customer_phone" class="block text-sm font-medium text-amber-900">Phone</label>
                        <input type="tel" name="phone" id="edit_customer_phone" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_customer_birth_date" class="block text-sm font-medium text-amber-900">Birth Date</label>
                        <input type="date" name="birth_date" id="edit_customer_birth_date" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="edit_customer_preferences" class="block text-sm font-medium text-amber-900">Preferences</label>
                        <textarea id="edit_customer_preferences" name="preferences" rows="3" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('editCustomerModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Update Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loyalty Program Modal -->
    <div id="loyaltyModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
            <button onclick="toggleModal('loyaltyModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4">Update Loyalty Program</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_loyalty">
                <input type="hidden" name="customer_id" id="loyalty_customer_id">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="loyalty_points" class="block text-sm font-medium text-amber-900">Loyalty Points</label>
                        <input type="number" name="loyalty_points" id="loyalty_points" min="0" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label for="membership_level" class="block text-sm font-medium text-amber-900">Membership Level</label>
                        <select id="membership_level" name="membership_level" class="w-full px-3 py-2 border border-amber-200 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                            <option value="Regular">Regular</option>
                            <option value="Bronze">Bronze</option>
                            <option value="Silver">Silver</option>
                            <option value="Gold">Gold</option>
                            <option value="Platinum">Platinum</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('loyaltyModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Update Loyalty</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Confirmation Modal -->
    <div id="statusModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 relative">
            <button onclick="toggleModal('statusModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-900">
                <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-semibold text-amber-900 mb-4" id="statusModalTitle">Deactivate Account</h2>
            <p class="text-amber-700 mb-6" id="statusModalMessage">Are you sure you want to deactivate this customer account? This will disable their access to the system.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="customer_id" id="status_customer_id">
                <input type="hidden" name="new_status" id="status_new_status">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleModal('statusModal')" class="px-4 py-2 bg-white-200 text-amber-900 rounded-md hover:bg-amber-100">Cancel</button>
                    <button type="submit" id="statusModalButton" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Deactivate</button>
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

        // Close user menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });

        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        function openEditModal(customer) {
            document.getElementById('edit_customer_id').value = customer.customer_id;
            document.getElementById('edit_customer_user_id').value = customer.user_id;
            document.getElementById('edit_customer_first_name').value = customer.first_name;
            document.getElementById('edit_customer_last_name').value = customer.last_name;
            document.getElementById('edit_customer_email').value = customer.email;
            document.getElementById('edit_customer_username').value = customer.username;
            document.getElementById('edit_customer_phone').value = customer.phone || '';
            document.getElementById('edit_customer_birth_date').value = customer.birth_date || '';
            document.getElementById('edit_customer_preferences').value = customer.preferences || '';
            toggleModal('editCustomerModal');
        }

        function openLoyaltyModal(customerId, points, level) {
            document.getElementById('loyalty_customer_id').value = customerId;
            document.getElementById('loyalty_points').value = points;
            document.getElementById('membership_level').value = level;
            toggleModal('loyaltyModal');
        }

        function openStatusModal(customerId, currentStatus) {
            document.getElementById('status_customer_id').value = customerId;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            document.getElementById('status_new_status').value = newStatus;
            document.getElementById('statusModalTitle').textContent = currentStatus === 'active' ? 'Deactivate Account' : 'Activate Account';
            document.getElementById('statusModalMessage').textContent = currentStatus === 'active' ?
                'Are you sure you want to deactivate this customer account? This will disable their access to the system.' :
                'Are you sure you want to activate this customer account? This will restore their access to the system.';
            const button = document.getElementById('statusModalButton');
            button.textContent = currentStatus === 'active' ? 'Deactivate' : 'Activate';
            button.className = currentStatus === 'active' ?
                'px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700' :
                'px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700';
            toggleModal('statusModal');
        }

        // Close modals when clicking outside
        document.addEventListener('click', (e) => {
            const modals = ['editCustomerModal', 'loyaltyModal', 'statusModal'];
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