<div class="md:w-1/4">
    <div class="bg-white rounded-xl shadow-md p-6 sticky top-24">
        <div class="text-center mb-6">
            <div class="w-24 h-24 bg-gradient-to-br from-amber-100 to-amber-200 rounded-full mx-auto mb-4 flex items-center justify-center shadow-inner">
                <i class="fas fa-user text-amber-600 text-3xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></h2>
            <p class="text-gray-500 text-sm"><?= htmlspecialchars($_SESSION['email']) ?></p>
        </div>

        <nav class="space-y-1">
            <a href="./dashboard.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                <i class="fas fa-tachometer-alt mr-3 text-gray-500"></i> Dashboard
            </a>
            <a href="./profile.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                <i class="fas fa-user-edit mr-3 text-gray-500"></i> My Profile
            </a>
            <a href="./orders.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                <i class="fas fa-receipt mr-3 text-amber-600"></i> My Orders
            </a>
            <a href="./reservation.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                <i class="fas fa-calendar-alt mr-3 text-gray-500"></i> Reservations
            </a>
            <a href="./favorites.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-gray-700">
                <i class="fas fa-heart mr-3 text-gray-500"></i> Favorites
            </a>
            <a href="../auth/logout.php" class="flex items-center sidebar-link py-2 px-4 hover:bg-gray-100 rounded-lg text-red-600">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
            </a>
        </nav>
    </div>
</div>