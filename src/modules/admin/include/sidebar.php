<div class="sidebar bg-gradient-to-b from-amber-50 to-white w-72 flex flex-col shadow-lg rounded-r-xl overflow-hidden" id="sidebar">
    <!-- Logo & Brand -->
    <div class="p-6 flex items-center justify-between border-b border-amber-100">
        <div class="flex items-center space-x-3">
            <div class="bg-amber-500 text-white p-2 rounded-lg shadow-sm">
                <i class="fas fa-utensils text-xl"></i>
            </div>
            <span class="logo-text text-xl font-bold text-amber-800">Casa Baraka</span>
        </div>
        <button id="toggleSidebar" class="text-amber-600 hover:text-amber-800 focus:outline-none lg:hidden" aria-label="Toggle sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- User Profile -->
    <div class="p-5 flex items-center space-x-4 border-b border-amber-100 bg-amber-50/50">
        <div class="bg-gradient-to-br from-amber-400 to-amber-600 p-3 rounded-full w-12 h-12 flex items-center justify-center text-white shadow-sm">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="min-w-0">
            <div class="font-semibold truncate text-gray-800 text-base"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></div>
            <div class="text-xs font-medium text-amber-600 bg-amber-100 rounded-full px-2 py-0.5 inline-block mt-1">Administrator</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-3">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 mb-2">Main Menu</div>
        <ul class="space-y-1.5">
            <li>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-tachometer-alt w-5 text-center mr-3 <?= $current_page == 'dashboard' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="staff.php" class="<?= $current_page == 'staff' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-users w-5 text-center mr-3 <?= $current_page == 'staff' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Staff Management</span>
                </a>
            </li>
            <li>
                <a href="customers.php" class="<?= $current_page == 'customers' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-user-friends w-5 text-center mr-3 <?= $current_page == 'customers' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Customers</span>
                </a>
            </li>

            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 mb-2 mt-6">Restaurant Operations</div>
            <li>
                <a href="menu.php" class="<?= $current_page == 'menu' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-utensils w-5 text-center mr-3 <?= $current_page == 'menu' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Menu Management</span>
                </a>
            </li>
            <li>
                <a href="orders.php" class="<?= $current_page == 'orders' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-shopping-bag w-5 text-center mr-3 <?= $current_page == 'orders' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Orders</span>
                    <?php if (isset($pending_orders) && $pending_orders > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?= $pending_orders ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="reservations.php" class="<?= $current_page == 'reservations' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-calendar-alt w-5 text-center mr-3 <?= $current_page == 'reservations' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Reservations</span>
                    <?php if (isset($new_reservations) && $new_reservations > 0): ?>
                        <span class="ml-auto bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?= $new_reservations ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="inventory.php" class="<?= $current_page == 'inventory' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-boxes w-5 text-center mr-3 <?= $current_page == 'inventory' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Inventory</span>
                </a>
            </li>

            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 mb-2 mt-6">Analytics & System</div>
            <li>
                <a href="reports.php" class="<?= $current_page == 'reports' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-chart-bar w-5 text-center mr-3 <?= $current_page == 'reports' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?= $current_page == 'settings' ? 'bg-gradient-to-r from-amber-100 to-amber-50 text-amber-800 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-amber-50 hover:text-amber-700 border-l-4 border-transparent' ?> flex items-center px-4 py-3 text-sm font-medium rounded-r-lg transition-all duration-200">
                    <i class="fas fa-cog w-5 text-center mr-3 <?= $current_page == 'settings' ? 'text-amber-600' : 'text-gray-500' ?>"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer -->
    <div class="p-4 border-t border-amber-100 mt-auto bg-amber-50/30">
        <a href="/modules/auth/logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg text-gray-700 hover:bg-amber-100 hover:text-amber-800 transition-all duration-200">
            <i class="fas fa-sign-out-alt w-5 text-center mr-3 text-gray-500"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- JavaScript for toggle functionality -->
<script>
    document.getElementById('toggleSidebar').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('lg:translate-x-0');

        // Change icon based on state
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('-translate-x-full')) {
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        } else {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-left');
        }

        // Save state in localStorage
        const isHidden = sidebar.classList.contains('-translate-x-full');
        localStorage.setItem('sidebarHidden', isHidden);
    });

    // Initialize sidebar state
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        const icon = toggleBtn.querySelector('i');
        const isHidden = localStorage.getItem('sidebarHidden') === 'true';

        if (isHidden) {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('lg:translate-x-0');
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        }
    });
</script>