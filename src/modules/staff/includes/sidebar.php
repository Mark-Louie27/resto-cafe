<?php
require_once __DIR__ . '/../../../includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get user roles
$user_roles = [];
$conn = db_connect();
$stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}
$stmt->close();

$is_manager = in_array(2, $user_roles); // Manager role_id = 2
$is_staff = in_array(3, $user_roles);   // Staff role_id = 3

// Define navigation items with proper URLs
$nav_items = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => '/modules/staff/dashboard.php',
        'manager' => true,
        'staff' => true
    ],
    'orders' => [
        'label' => 'Orders',
        'icon' => 'fas fa-shopping-cart',
        'url' => '/modules/staff/orders.php',
        'manager' => true,
        'staff' => true
    ],
    'inventory' => [
        'label' => 'Inventory',
        'icon' => 'fas fa-boxes',
        'url' => '/modules/staff/inventory.php',
        'manager' => true,
        'staff' => false
    ],
    'tables' => [
        'label' => 'Tables',
        'icon' => 'fas fa-chair',
        'url' => '/modules/staff/tables.php',
        'manager' => true,
        'staff' => true
    ],
    'promotions' => [
        'label' => 'Promotions',
        'icon' => 'fas fa-percentage',
        'url' => '/modules/staff/promotions.php',
        'manager' => true,
        'staff' => false
    ],
    'schedules' => [
        'label' => 'Schedules',
        'icon' => 'fas fa-calendar-alt',
        'url' => '/modules/staff/schedules.php',
        'manager' => true,
        'staff' => true
    ],
];

// Get user info for display
$user_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data ? $user_data['first_name'] . ' ' . $user_data['last_name'] : 'User';
$user_stmt->close();
$conn->close();
?>

<style>
    /* Custom sidebar styles - add this to your CSS file */

    /* Sidebar animation */
    @media (max-width: 768px) {
        #sidebar {
            transform: translateX(-100%);
        }

        #sidebar.open {
            transform: translateX(0);
        }

        .ml-64 {
            margin-left: 0 !important;
        }
    }

    /* Custom scrollbar for sidebar */
    #sidebar::-webkit-scrollbar {
        width: 4px;
    }

    #sidebar::-webkit-scrollbar-track {
        background: rgba(255, 248, 230, 0.1);
    }

    #sidebar::-webkit-scrollbar-thumb {
        background-color: #d97706;
        border-radius: 6px;
    }

    /* Active item accent */
    .nav-item.active {
        border-left: 4px solid #d97706;
    }

    /* Smooth hover transitions */
    .nav-item {
        transition: all 0.2s ease;
    }

    .nav-item:hover {
        transform: translateX(3px);
    }

    /* Icon pulse animation for notifications */
    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    .notification-badge {
        animation: pulse 2s infinite;
    }

    /* Custom shadows */
    .sidebar-shadow {
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }

    /* Logo container */
    .logo-container {
        position: relative;
        overflow: hidden;
    }

    .logo-container::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(to right, transparent, #d97706, transparent);
    }

    /* User profile hover effect */
    .user-profile {
        transition: all 0.3s ease;
    }

    .user-profile:hover {
        background-color: rgba(217, 119, 6, 0.05);
        border-radius: 8px;
    }

    /* Mobile menu overlay */
    .sidebar-overlay {
        background-color: rgba(0, 0, 0, 0.5);
        position: fixed;
        inset: 0;
        z-index: 40;
        display: none;
    }

    .sidebar-overlay.active {
        display: block;
    }
</style>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 transform transition-transform duration-300 ease-in-out bg-white shadow-lg -translate-x-full lg:translate-x-0">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div class="flex items-center">
            <div class="h-8 w-8 rounded-md bg-primary-600 flex items-center justify-center mr-3">
                <i class="fas fa-utensils text-white text-sm"></i>
            </div>
            <span class="text-xl font-bold text-gray-900">Casa Baraka</span>
        </div>
        <button id="sidebar-close" class="text-gray-500 hover:text-gray-700 lg:hidden">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- User Profile -->
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center">
            <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-bold">
                <?= substr($user_name, 0, 1) ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user_name) ?></p>
                <p class="text-xs text-gray-500">
                    <?= $is_manager ? 'Manager' : ($is_staff ? 'Staff' : 'User') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="px-2 py-4 overflow-y-auto h-[calc(100vh-180px)] scrollbar">
        <div class="space-y-1">
            <?php foreach ($nav_items as $page => $item): ?>
                <?php if (($is_manager && $item['manager']) || ($is_staff && $item['staff'])): ?>
                    <a href="<?= $item['url'] ?>" class="sidebar-item flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 <?= strpos($current_page, $page) !== false ? 'active bg-primary-50 text-primary-700' : 'text-gray-600 hover:text-gray-900' ?>">
                        <i class="<?= $item['icon'] ?> w-5 text-center mr-3 <?= strpos($current_page, $page) !== false ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                        <?= $item['label'] ?>
                        <?php if ($page === 'orders' && isset($_SESSION['pending_orders']) && $_SESSION['pending_orders'] > 0): ?>
                            <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                <?= $_SESSION['pending_orders'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="absolute bottom-0 left-0 right-0 px-6 py-4 border-t border-gray-200">
        <a href="/modules/auth/logout.php" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
            <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
            Logout
        </a>
    </div>
</aside>

<!-- Mobile overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-40 bg-black bg-opacity-50 hidden lg:hidden"></div>

<script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarClose = document.getElementById('sidebar-close');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInside = sidebar.contains(event.target) ||
                (sidebarToggle && sidebarToggle.contains(event.target));

            if (!isClickInside && window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        });
    });
</script>

<!-- Main Layout Structure -->
<div class="flex h-screen bg-gray-100">
    <!-- Sidebar above is included here -->
    <div class="flex-1 ml-64 overflow-x-hidden">
        <!-- Main Content goes here -->
        <div class="p-6">
            <!-- Your page content -->
        </div>
    </div>
</div>