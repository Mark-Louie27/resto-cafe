<?php
// modules/admin/include/topbar.php
?>
<header class="bg-white shadow">
    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <div class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900"><?= $page_title ?? 'Admin Dashboard' ?></h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none">
                    <span class="sr-only">Notifications</span>
                    <i class="fas fa-bell"></i>
                </button>
            </div>
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <div class="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center text-white">
                        <?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?>
                    </div>
                    <span class="hidden md:inline"><?= htmlspecialchars($admin['first_name']) ?></span>
                </div>
            </div>
        </div>
    </div>
</header>