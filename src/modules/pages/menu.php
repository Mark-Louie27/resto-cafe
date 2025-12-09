<?php
require_once __DIR__ . '/../../controller/MenuController.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/OrderController.php'; // Include OrderController for discount functions

// Get customer ID and favorites if logged in
$customer_id = null;
$user_favorites = [];
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $customer_id = get_customer_id($user_id);
    if ($customer_id) {
        // Fetch user's current favorites
        $stmt = db_connect()->prepare("SELECT item_id FROM favorites WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_favorites[] = $row['item_id'];
        }
        $stmt->close();
    }
}

// Handle cart and favorites actions (only for logged-in users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check if user is logged in
    if (!is_logged_in()) {
        set_flash_message('Please log in to perform this action.', 'error');
        header('Location: ../auth/login.php');
        exit();
    }

    if (!$customer_id) {
        set_flash_message('Customer ID not found. Please ensure your account is properly set up.', 'error');
        header('Location: menu.php#menu');
        exit();
    }

    $action = $_POST['action'];
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    // Handle favorites actions
    if ($action === 'toggle_favorite' && validate_csrf_token($_POST['csrf_token'])) {
        if (!$item_id) {
            set_flash_message('Invalid item ID.', 'error');
            header('Location: menu.php#menu');
            exit();
        }

        // Check if the item is already a favorite
        $is_favorite = in_array($item_id, $user_favorites);
        $conn = db_connect();

        if ($is_favorite) {
            // Remove from favorites
            $stmt = $conn->prepare("DELETE FROM favorites WHERE customer_id = ? AND item_id = ?");
            $stmt->bind_param("ii", $customer_id, $item_id);
            if ($stmt->execute()) {
                set_flash_message('Item removed from favorites.', 'success');
            } else {
                set_flash_message('Failed to remove item from favorites.', 'error');
            }
        } else {
            // Add to favorites
            $stmt = $conn->prepare("INSERT INTO favorites (customer_id, item_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $customer_id, $item_id);
            if ($stmt->execute()) {
                set_flash_message('Item added to favorites!', 'success');
            } else {
                set_flash_message('Failed to add item to favorites.', 'error');
            }
        }
        $stmt->close();
        header('Location: menu.php#menu');
        exit();
    }

    // Existing cart actions
    if ($action === 'add_to_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
        $result = add_to_cart($item_id, $quantity);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: menu.php#menu');
        exit();
    }

    if ($action === 'update_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $result = update_cart_item($item_id, $quantity);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: menu.php#menu');
        exit();
    }

    if ($action === 'remove_from_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $result = remove_from_cart($item_id);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: menu.php#menu');
        exit();
    }
}

// Get cart item count (only for logged-in users)
$cart_item_count = is_logged_in() ? get_cart_item_count() : 0;

// Fetch all menu items (available and unavailable)
$menuItems = get_menu_items();

// Prepare cart items for discount calculation
$cart = get_cart();
$cart_items_for_discount = [];
$cart_subtotal = 0.0;
foreach ($cart as $item_id => $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $cart_subtotal += $subtotal;
    $cart_items_for_discount[] = [
        'item_id' => (int)$item_id,
        'quantity' => (int)$item['quantity'],
        'unit_price' => (float)$item['price']
    ];
}

// Calculate discounts
$best_discount = 0.0;
$applicable_promotions = get_applicable_promotions($cart_items_for_discount, $cart_subtotal);
foreach ($applicable_promotions as $promo) {
    $discount = calculate_discount($promo, $cart_items_for_discount, $cart_subtotal);
    if ($discount > $best_discount) {
        $best_discount = $discount;
    }
}

// Calculate final total after discount
$cart_total = $cart_subtotal - $best_discount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu - Casa Baraka</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            scroll-behavior: smooth;
        }

        .menu-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #ca8a04;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .favorite-btn i {
            transition: color 0.3s ease;
        }

        .favorite-btn.favorited i {
            color: #ef4444;
            /* Red for filled heart */
        }
    </style>
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <!-- Logo -->
                        <a href="../../index.php" class="flex items-center py-4 px-2">
                            <i class="fas fa-mug-hot text-amber-600 text-2xl mr-1"></i>
                            <span class="font-semibold text-amber-600 text-lg">Casa Baraka</span>
                        </a>
                    </div>
                    <!-- Primary Navigation -->
                    <div class="hidden md:flex items-center space-x-1">
                        <a href="../../index.php" class="py-4 px-2 text-gray-500 font-semibold hover:text-amber-600 transition duration-300">Home</a>
                        <a href="#menu" class="py-4 px-2 text-amber-600 border-b-4 border-amber-600 font-semibold">Menu</a>
                        <a href="../../index.php#about" class="py-4 px-2 text-gray-500 font-semibold hover:text-amber-600 transition duration-300">About</a>
                        <a href="../../index.php#contact" class="py-4 px-2 text-gray-500 font-semibold hover:text-amber-600 transition duration-300">Contact</a>
                    </div>
                </div>
                <!-- Auth Navigation -->
                <div class="hidden md:flex items-center space-x-3">
                    <?php if (is_logged_in()): ?>
                        <button onclick="openCartModal()" class="relative py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_item_count > 0): ?>
                                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-amber-600 rounded-full"><?php echo $cart_item_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <a href="../customers/dashboard.php" class="py-4 px-2 text-gray-500 font-semibold hover:text-amber-600 transition duration-300">Dashboard</a>
                        <a href="../auth/logout.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">Log In</a>
                        <a href="../auth/register.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Sign Up</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile button -->
                <div class="md:hidden flex items-center">
                    <button class="outline-none mobile-menu-button">
                        <i class="fas fa-bars text-amber-600 text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div class="hidden mobile-menu">
            <ul>
                <li><a href="../../index.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Home</a></li>
                <li><a href="#menu" class="block py-2 px-4 text-sm hover:bg-amber-50">Menu</a></li>
                <li><a href="../../index.php#about" class="block py-2 px-4 text-sm hover:bg-amber-50">About</a></li>
                <li><a href="../../index.php#contact" class="block py-2 px-4 text-sm hover:bg-amber-50">Contact</a></li>
                <?php if (is_logged_in()): ?>
                    <li><button onclick="openCartModal()" class="block py-2 px-4 text-sm hover:bg-amber-50 w-full text-left">Cart (<?php echo $cart_item_count; ?>)</button></li>
                    <li><a href="../customers/dashboard.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Dashboard</a></li>
                    <li><a href="../auth/logout.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Logout</a></li>
                <?php else: ?>
                    <li><a href="../auth/login.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Log In</a></li>
                    <li><a href="../auth/register.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container mx-auto px-4 pt-24">
        <?php display_flash_message(); ?>
    </div>

    <!-- Menu Section -->
    <section id="menu" class="py-16 bg-gradient-to-b from-amber-50 to-white">
        <div class="container mx-auto px-4 max-w-7xl">
            <!-- Header with decorative elements -->
            <div class="relative mb-12 text-center">
                <div class="absolute left-1/2 transform -translate-x-1/2 -top-10 w-24 h-1 bg-amber-600 rounded-full opacity-50"></div>
                <h2 class="text-4xl font-bold text-gray-800 mb-4 relative inline-block">
                    <span class="relative z-10">Our Menu</span>
                    <svg class="absolute -bottom-2 left-0 w-full h-3 text-amber-200 z-0" viewBox="0 0 200 8">
                        <path d="M0,5 C50,0 150,0 200,5" fill="none" stroke="currentColor" stroke-width="3"></path>
                    </svg>
                </h2>
                <p class="text-gray-600 max-w-2xl mx-auto mb-6 text-lg">Discover our artisanal coffees, freshly baked pastries, and seasonal delights crafted with locally sourced ingredients.</p>
            </div>

            <!-- Search and Filter Section - Enhanced with animations and better spacing -->
            <div class="mb-10 bg-white rounded-2xl shadow-lg p-6 transition-all duration-300 hover:shadow-xl">
                <!-- Search Bar -->
                <div class="flex justify-center mb-8">
                    <div class="relative w-full max-w-lg">
                        <input type="text" id="menuSearch" placeholder="Search our menu..." class="w-full px-5 py-3 border-2 border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 pl-12 text-gray-700">
                        <span class="absolute left-4 top-3.5 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Filters with better styling -->
                <div class="space-y-6">
                    <!-- Category Tabs - Horizontal scrollable on mobile -->
                    <div class="overflow-x-auto pb-2 -mx-2 px-2">
                        <div class="flex gap-2 min-w-max">
                            <button class="category-btn bg-amber-600 text-white py-2.5 px-6 rounded-full text-sm font-medium shadow-sm" data-category="all">All Items</button>
                            <?php
                            $categories = get_categories();
                            foreach ($categories as $category) {
                                echo '<button class="category-btn bg-gray-100 text-gray-700 hover:bg-amber-500 hover:text-white py-2.5 px-6 rounded-full text-sm font-medium transition duration-300 shadow-sm" data-category="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</button>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Advanced Filters - Responsive grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Sort by Price - Enhanced dropdown -->
                        <div class="relative">
                            <select id="sortPrice" class="appearance-none w-full bg-gray-100 px-4 py-3 rounded-full text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 border-0">
                                <option value="">Sort by Price</option>
                                <option value="asc">Price: Low to High</option>
                                <option value="desc">Price: High to Low</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Filter by Availability - Enhanced dropdown -->
                        <div class="relative">
                            <select id="filterAvailability" class="appearance-none w-full bg-gray-100 px-4 py-3 rounded-full text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 border-0">
                                <option value="">Availability</option>
                                <option value="available">Available Now</option>
                                <option value="unavailable">Coming Soon</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Filter by Allergens - Enhanced dropdown -->
                        <div class="relative">
                            <select id="filterAllergens" class="appearance-none w-full bg-gray-100 px-4 py-3 rounded-full text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 border-0">
                                <option value="">Dietary Needs</option>
                                <option value="none">Allergen Free</option>
                                <option value="nuts">Contains Nuts</option>
                                <option value="dairy">Contains Dairy</option>
                                <option value="gluten">Contains Gluten</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner - Enhanced -->
            <div id="loadingSpinner" class="hidden flex justify-center items-center my-12">
                <div class="spinner relative w-12 h-12">
                    <div class="absolute top-0 left-0 w-full h-full border-4 border-amber-200 rounded-full"></div>
                    <div class="absolute top-0 left-0 w-full h-full border-4 border-amber-600 rounded-full border-t-transparent animate-spin"></div>
                </div>
            </div>

            <!-- Empty State Message -->
            <div id="emptyState" class="hidden py-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 3.104c-.734-.588-1.826-.588-2.56 0C5.954 4.129 3 6.371 3 9.475c0 3.813 3.8 6.471 8.5 10.686a.75.75 0 001 0c4.7-4.215 8.5-6.873 8.5-10.686 0-3.104-2.954-5.346-4.19-6.371-.734-.588-1.826-.588-2.56 0l-.5.4-.5-.4z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No items found</h3>
                <p class="mt-2 text-gray-500">Try adjusting your search or filter criteria</p>
                <button id="resetFilters" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                    Reset All Filters
                </button>
            </div>

            <!-- Menu Items Grid - Enhanced cards with hover effects -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="menu-items-container">
                <?php
                if (empty($menuItems)) {
                    echo '<p class="text-center text-gray-600 col-span-full">No menu items available at the moment.</p>';
                } else {
                    foreach ($menuItems as $item) {
                        $is_favorited = is_logged_in() && in_array($item['item_id'], $user_favorites);
                        $categories = explode(',', $item['category_name'] ?? 'Uncategorized');
                ?>
                        <div class="bg-white rounded-xl shadow-md overflow-hidden menu-card group transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1"
                            data-category="<?php echo $item['category_id'] ?: 'uncategorized'; ?>"
                            data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>"
                            data-description="<?php echo htmlspecialchars(strtolower($item['description'])); ?>"
                            data-price="<?php echo $item['price']; ?>"
                            data-available="<?php echo $item['is_available'] ? 'true' : 'false'; ?>"
                            data-allergens="<?php echo htmlspecialchars(strtolower($item['allergens'] ?? '')); ?>">
                            <div class="relative overflow-hidden h-56">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/400x300'); ?>"
                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                                <!-- Color overlay on top of image for better text contrast -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-30"></div>

                                <?php if (is_logged_in()): ?>
                                    <form method="POST" action="menu.php" class="absolute top-3 right-3 z-10">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_favorite">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <button type="submit" class="favorite-btn flex items-center justify-center w-10 h-10 rounded-full bg-white bg-opacity-70 backdrop-blur-sm hover:bg-opacity-100 transition-all duration-300" title="<?php echo $is_favorited ? 'Remove from Favorites' : 'Add to Favorites'; ?>">
                                            <svg class="w-5 h-5 <?php echo $is_favorited ? 'text-red-500' : 'text-gray-400'; ?>" fill="<?php echo $is_favorited ? 'currentColor' : 'none'; ?>" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Price Tag -->
                                <div class="absolute bottom-3 right-3 bg-white bg-opacity-90 backdrop-blur-sm px-3 py-1 rounded-full text-amber-600 font-bold shadow-md">
                                    ₱<?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>

                            <div class="p-6">
                                <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($item['name']); ?></h3>

                                <p class="text-gray-600 mb-4 line-clamp-2 group-hover:line-clamp-none transition-all duration-300"><?php echo htmlspecialchars($item['description']); ?></p>

                                <!-- Tags Section -->
                                <div class="flex flex-wrap gap-2 mb-5">
                                    <?php foreach ($categories as $category): ?>
                                        <span class="inline-block bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-xs font-medium"><?php echo trim(htmlspecialchars($category)); ?></span>
                                    <?php endforeach; ?>

                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?php echo $item['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $item['is_available'] ? 'Available Now' : 'Unavailable'; ?>
                                    </span>

                                    <?php if ($item['calories'] !== null): ?>
                                        <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium"><?php echo $item['calories']; ?> calories</span>
                                    <?php endif; ?>

                                    <?php if ($item['prep_time'] !== null): ?>
                                        <span class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-xs font-medium"><?php echo $item['prep_time']; ?> min prep</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Allergen Warning if applicable -->
                                <?php if ($item['allergens']): ?>
                                    <div class="flex items-center gap-2 mb-4 p-2 rounded-lg bg-red-50 border border-red-100">
                                        <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span class="text-xs text-red-700">Contains: <?php echo htmlspecialchars($item['allergens']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <?php if (is_logged_in()): ?>
                                    <div class="flex gap-2">
                                        <form method="POST" action="menu.php" class="w-full">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold py-3 px-4 rounded-full transition duration-300 flex items-center justify-center <?php echo !$item['is_available'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo !$item['is_available'] ? 'disabled' : ''; ?>>
                                                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                <?php echo $item['is_available'] ? 'Add to Cart' : 'Currently Unavailable'; ?>
                                            </button>
                                        </form>

                                        <!-- Quick View Button -->
                                        <button type="button" class="quick-view-btn bg-gray-100 hover:bg-gray-200 text-gray-700 p-3 rounded-full transition duration-300" data-item-id="<?php echo $item['item_id']; ?>">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="../auth/login.php" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold py-3 px-4 rounded-full transition duration-300 flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                        </svg>
                                        Login to Order
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                <?php
                    }
                }
                ?>
            </div>

            <!-- Pagination -->
            <div class="mt-12 flex justify-center">
                <nav class="flex items-center space-x-2" aria-label="Pagination">
                    <a href="#" class="px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-amber-100">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <a href="#" class="px-3 py-1 rounded-md bg-amber-600 text-white">1</a>
                    <a href="#" class="px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-amber-100">2</a>
                    <a href="#" class="px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-amber-100">3</a>
                    <span class="px-3 py-1 text-gray-500">...</span>
                    <a href="#" class="px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-amber-100">8</a>
                    <a href="#" class="px-3 py-1 rounded-md bg-gray-100 text-gray-700 hover:bg-amber-100">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </nav>
            </div>

            <!-- Quick View Modal -->
            <div id="quickViewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-800" id="modalItemName">Item Name</h3>
                            <button id="closeModal" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="modal-content">
                            <!-- Dynamic content loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Home Button - Enhanced -->
            <div class="text-center mt-16">
                <a href="../../index.php" class="inline-flex items-center px-6 py-3 border-2 border-amber-600 text-amber-600 hover:bg-amber-600 hover:text-white font-semibold rounded-full transition duration-300 group">
                    <svg class="w-5 h-5 mr-2 transform group-hover:-translate-x-1 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </section>

    <!-- Cart Modal (only for logged-in users) -->
    <?php if (is_logged_in()): ?>
        <div id="cartModal" class="fixed inset-0 hidden modal-backdrop z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
                <button onclick="toggleModal('cartModal')" class="absolute top-3 right-3 text-amber-600 hover:text-amber-700">
                    <i class="fas fa-times text-lg"></i>
                </button>
                <h2 class="text-xl font-semibold text-amber-600 mb-4 flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i> Your Cart
                </h2>
                <?php
                $cart = get_cart();
                if (empty($cart)):
                ?>
                    <p class="text-gray-600">Your cart is empty.</p>
                <?php else: ?>
                    <div class="space-y-4 max-h-48 overflow-y-auto">
                        <?php
                        $total = 0;
                        foreach ($cart as $item_id => $item):
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                        ?>
                            <div class="flex justify-between items-center p-3 bg-amber-50 rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-amber-600"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <p class="text-xs text-gray-500">₱<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <form method="POST" action="menu.php" class="flex items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="update_cart">
                                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="w-16 px-2 py-1 border border-amber-200 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                        <button type="submit" class="ml-2 text-amber-600 hover:text-amber-700">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="menu.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 border-t border-amber-100 pt-4">
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-semibold text-amber-600">Subtotal:</span>
                                <span class="text-lg font-bold text-amber-600">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                            <?php if ($best_discount > 0): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-md font-semibold text-green-600">Discount:</span>
                                    <span class="text-md font-bold text-green-600">-₱<?php echo number_format($best_discount, 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-semibold text-amber-600">Total:</span>
                                <span class="text-lg font-bold text-amber-600">₱<?php echo number_format($cart_total, 2); ?></span>
                            </div>
                        </div>
                        <form method="POST" action="../customers/checkout.php" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <!-- Order Type -->
                            <div class="mt-4">
                                <label for="order_type" class="block text-sm font-medium text-gray-700">Order Type</label>
                                <select name="order_type" id="order_type" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                                    <option value="Dine-in">Dine-in</option>
                                    <option value="Takeout">Takeout</option>
                                    <option value="Delivery">Delivery</option>
                                </select>
                            </div>
                            <!-- Table Selection (for Dine-in) -->
                            <div id="tableSelectionField" class="mt-4 hidden">
                                <label for="table_id" class="block text-sm font-medium text-gray-700">Select Table</label>
                                <select name="table_id" id="table_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">Select a table</option>
                                    <?php
                                    $tables = get_available_tables();
                                    foreach ($tables as $table) {
                                        echo "<option value='{$table['table_id']}'>{$table['table_number']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- Delivery Address (for Delivery) -->
                            <div id="deliveryAddressField" class="mt-4 hidden">
                                <label for="delivery_address" class="block text-sm font-medium text-gray-700">Delivery Address</label>
                                <textarea name="delivery_address" id="delivery_address" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500" rows="3"></textarea>
                            </div>
                            <!-- Payment Method -->
                            <div class="mt-4">
                                <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                <select name="payment_method" id="payment_method" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Mobile Payment">Mobile Payment</option>
                                    <option value="Gift Card">Gift Card</option>
                                </select>
                            </div>
                            <!-- Notes -->
                            <div class="mt-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                                <textarea name="notes" id="notes" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500" rows="3"></textarea>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-full transition duration-300">Proceed to Checkout</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php include '../../includes/footer.php' ?>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-6 right-6 bg-amber-600 text-white p-3 rounded-full shadow-lg opacity-0 invisible transition-all duration-300">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script src="/assets/js/menu.js"></script>

    <script>
        // Modal toggle function
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.toggle('hidden');
                console.log(`Toggled modal ${modalId}: ${modal.classList.contains('hidden') ? 'hidden' : 'visible'}`);
            } else {
                console.warn(`Modal with ID ${modalId} not found.`);
            }
        }

        // Open Cart Modal (only available for logged-in users)
        window.openCartModal = function() {
            <?php if (is_logged_in()): ?>
                toggleModal('cartModal');
            <?php else: ?>
                window.location.href = '../auth/login.php';
            <?php endif; ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                    console.log(`Mobile menu toggled: ${mobileMenu.classList.contains('hidden') ? 'hidden' : 'visible'}`);
                });
            } else {
                console.warn('Mobile menu button or menu not found.');
            }

            // Show/hide delivery address and table selection based on order type
            const orderTypeSelect = document.getElementById('order_type');
            const deliveryAddressField = document.getElementById('deliveryAddressField');
            const deliveryAddressInput = document.getElementById('delivery_address');
            const tableSelectionField = document.getElementById('tableSelectionField');
            const tableIdSelect = document.getElementById('table_id');

            if (orderTypeSelect && deliveryAddressField && tableSelectionField) {
                function updateFormFields() {
                    const orderType = orderTypeSelect.value;
                    if (orderType === 'Delivery') {
                        deliveryAddressField.classList.remove('hidden');
                        deliveryAddressInput.setAttribute('required', 'required');
                        tableSelectionField.classList.add('hidden');
                        tableIdSelect.removeAttribute('required');
                    } else if (orderType === 'Dine-in') {
                        tableSelectionField.classList.remove('hidden');
                        tableIdSelect.setAttribute('required', 'required');
                        deliveryAddressField.classList.add('hidden');
                        deliveryAddressInput.removeAttribute('required');
                    } else {
                        deliveryAddressField.classList.add('hidden');
                        deliveryAddressInput.removeAttribute('required');
                        tableSelectionField.classList.add('hidden');
                        tableIdSelect.removeAttribute('required');
                    }
                }

                orderTypeSelect.addEventListener('change', updateFormFields);
                updateFormFields(); // Run on page load
            }

            // Search and Filter functionality
            const categoryButtons = document.querySelectorAll('.category-btn');
            const menuItems = document.querySelectorAll('.menu-card');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const searchInput = document.getElementById('menuSearch');
            const sortPrice = document.getElementById('sortPrice');
            const filterAvailability = document.getElementById('filterAvailability');
            const filterAllergens = document.getElementById('filterAllergens');

            // Function to apply all filters and search
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const selectedCategory = document.querySelector('.category-btn.bg-amber-600')?.dataset.category || 'all';
                const sortOrder = sortPrice.value;
                const availabilityFilter = filterAvailability.value;
                const allergenFilter = filterAllergens.value;

                // Show loading spinner
                loadingSpinner.classList.remove('hidden');
                menuItems.forEach(item => item.classList.add('opacity-50'));

                // Simulate loading delay
                setTimeout(() => {
                    // Collect all items into an array for sorting
                    let filteredItems = Array.from(menuItems);

                    // Apply search filter
                    if (searchTerm) {
                        filteredItems = filteredItems.filter(item => {
                            const name = item.dataset.name;
                            const description = item.dataset.description;
                            return name.includes(searchTerm) || description.includes(searchTerm);
                        });
                    }

                    // Apply category filter
                    if (selectedCategory !== 'all') {
                        filteredItems = filteredItems.filter(item => {
                            const itemCategory = item.dataset.category;
                            return selectedCategory === itemCategory || (selectedCategory !== 'uncategorized' && itemCategory === 'uncategorized');
                        });
                    }

                    // Apply availability filter
                    if (availabilityFilter) {
                        filteredItems = filteredItems.filter(item => {
                            const isAvailable = item.dataset.available === 'true';
                            return (availabilityFilter === 'available' && isAvailable) || (availabilityFilter === 'unavailable' && !isAvailable);
                        });
                    }

                    // Apply allergen filter
                    if (allergenFilter) {
                        filteredItems = filteredItems.filter(item => {
                            const allergens = item.dataset.allergens;
                            if (allergenFilter === 'none') {
                                return !allergens;
                            }
                            return allergens.includes(allergenFilter);
                        });
                    }

                    // Apply sorting
                    if (sortOrder) {
                        filteredItems.sort((a, b) => {
                            const priceA = parseFloat(a.dataset.price);
                            const priceB = parseFloat(b.dataset.price);
                            return sortOrder === 'asc' ? priceA - priceB : priceB - priceA;
                        });
                    }

                    // Hide all items first
                    menuItems.forEach(item => {
                        item.style.display = 'none';
                        item.classList.remove('opacity-50');
                    });

                    // Show filtered and sorted items
                    filteredItems.forEach(item => {
                        item.style.display = 'block';
                    });

                    // Show message if no items match
                    const container = document.getElementById('menu-items-container');
                    if (filteredItems.length === 0) {
                        container.innerHTML = '<p class="text-center text-gray-600 col-span-full">No menu items match your search or filters.</p>';
                    } else if (!container.querySelector('.menu-card')) {
                        // Re-append the filtered items if the container was cleared
                        container.innerHTML = '';
                        filteredItems.forEach(item => container.appendChild(item));
                    }

                    loadingSpinner.classList.add('hidden');
                }, 300);
            }

            // Event listeners for category buttons
            if (categoryButtons && menuItems && loadingSpinner) {
                categoryButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        // Update button styles
                        categoryButtons.forEach(btn => {
                            btn.classList.remove('bg-amber-600', 'text-white');
                            btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');
                        });
                        button.classList.add('bg-amber-600', 'text-white');
                        button.classList.remove('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');

                        applyFilters();
                    });
                });
            }

            // Event listeners for search and filters
            searchInput.addEventListener('input', applyFilters);
            sortPrice.addEventListener('change', applyFilters);
            filterAvailability.addEventListener('change', applyFilters);
            filterAllergens.addEventListener('change', applyFilters);

            // Back to top button
            const backToTopButton = document.getElementById('backToTop');
            if (backToTopButton) {
                window.addEventListener('scroll', () => {
                    if (window.pageYOffset > 300) {
                        backToTopButton.classList.remove('opacity-0', 'invisible');
                        backToTopButton.classList.add('opacity-100', 'visible');
                    } else {
                        backToTopButton.classList.remove('opacity-100', 'visible');
                        backToTopButton.classList.add('opacity-0', 'invisible');
                    }
                });

                backToTopButton.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Close modal when clicking outside (only if modal exists)
            const cartModal = document.getElementById('cartModal');
            if (cartModal) {
                cartModal.addEventListener('click', (e) => {
                    if (e.target === cartModal) {
                        toggleModal('cartModal');
                    }
                });
            }
        });
    </script>
</body>

</html>