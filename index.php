<?php
// Start the session
if (!isset($_SESSION)) {
    session_start();
}


require_once __DIR__ . '/src/config/includes/functions.php';

// Define current page (simplified example - you may need to adjust based on your routing)
$current_page = basename($_SERVER['PHP_SELF']); // Gets the current filename
$is_home = ($current_page === 'index.php' || $current_page === '/');

// Handle cart actions (only for logged-in users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check if user is logged in
    if (!is_logged_in()) {
        set_flash_message('Please log in to add items to your cart.', 'error');
        header('Location: modules/auth/login.php');
        exit();
    }

    $action = $_POST['action'];
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    if ($action === 'add_to_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
        $result = add_to_cart($item_id, $quantity);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        // Redirect to menu section without opening the cart modal
        // This ensures the cart modal does not open automatically, even when the action originates from favorites.php
        header('Location: index.php#menu');
        exit();
    }

    if ($action === 'update_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $result = update_cart_item($item_id, $quantity);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: index.php#menu');
        exit();
    }

    if ($action === 'remove_from_cart' && validate_csrf_token($_POST['csrf_token'])) {
        $result = remove_from_cart($item_id);
        set_flash_message($result['message'], $result['success'] ? 'success' : 'error');
        header('Location: index.php#menu');
        exit();
    }
}

// Get cart item count (only for logged-in users)
$cart_item_count = is_logged_in() ? get_cart_item_count() : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casa Baraka - Your Cozy Corner</title>
    <!-- Tailwind CSS CDN -->
    <link href="/src/output.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="/public/assets/css/index.css" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <!-- Navigation -->
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <!-- Logo -->
                        <a href="index.php" class="flex items-center py-4 px-2">
                            <i class="fas fa-mug-hot text-amber-600 text-2xl mr-1"></i>
                            <span class="font-semibold text-amber-600 text-lg">Casa Baraka</span>
                        </a>
                    </div>
                    <!-- Primary Navigation -->
                    <div class="hidden md:flex items-center space-x-1">
                        <a href="/" class="py-4 px-2 font-semibold transition duration-300 text-gray-500 hover:text-amber-600">Home</a>
                        <a href="/#menu" class="py-4 px-2 font-semibold transition duration-300 text-gray-500 hover:text-amber-600">Menu</a>
                        <a href="/#about" class="py-4 px-2 font-semibold transition duration-300 text-gray-500 hover:text-amber-600">About</a>
                        <a href="/#contact" class="py-4 px-2 font-semibold transition duration-300 text-gray-500 hover:text-amber-600">Contact</a>
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
                        <a href="modules/customers/dashboard.php" class="py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">Dashboard</a>
                        <a href="modules/auth/logout.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Logout</a>
                    <?php else: ?>
                        <a href="modules/auth/login.php" class="py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">Log In</a>
                        <a href="modules/auth/register.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Sign Up</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile button -->
                <div class="md:hidden flex items-center">
                    <button class="outline-none mobile-menu-button" aria-label="Toggle menu">
                        <i class="fas fa-bars text-amber-600 text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div class="hidden mobile-menu">
            <ul>
                <li><a href="/" class="block py-2 px-4 text-sm hover:bg-amber-50">Home</a></li>
                <li><a href="/#menu" class="block py-2 px-4 text-sm hover:bg-amber-50">Menu</a></li>
                <li><a href="/#about" class="block py-2 px-4 text-sm hover:bg-amber-50">About</a></li>
                <li><a href="/#contact" class="block py-2 px-4 text-sm hover:bg-amber-50">Contact</a></li>
                <?php if (is_logged_in()): ?>
                    <li><button onclick="openCartModal()" class="block py-2 px-4 text-sm hover:bg-amber-50 w-full text-left">Cart (<?php echo $cart_item_count; ?>)</button></li>
                    <li><a href="modules/customers/dashboard.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Dashboard</a></li>
                    <li><a href="modules/auth/logout.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Logout</a></li>
                <?php else: ?>
                    <li><a href="modules/auth/login.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Log In</a></li>
                    <li><a href="modules/auth/register.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container mx-auto px-4 pt-24">
        <?php display_flash_message(); ?>
    </div>

    <!-- Hero Section -->
    <section class="hero-section flex items-center justify-center">
        <div class="text-center px-6">
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-4">Welcome to Casa Baraka</h1>
            <p class="text-xl md:text-2xl text-white mb-8">Your cozy corner for delicious coffee and treats</p>
            <a href="#menu" class="bg-amber-600 hover:bg-amber-500 text-white font-bold py-3 px-6 rounded-full transition duration-300 inline-block">Explore Our Menu</a>
        </div>
    </section>

    <!-- Featured Products with Enhanced UI -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Most Loved Items</h2>
                <div class="w-24 h-1 bg-amber-500 mx-auto"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $featured_items = get_menu_items(null, true); // Fetch available items
                $count = 0;
                foreach ($featured_items as $item) {
                    if ($count >= 3) break;
                ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden menu-card transform transition duration-300 hover:scale-105 hover:shadow-xl">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-full h-64 object-cover">
                            <?php if ($item['is_available']): ?>
                                <div class="absolute top-4 right-4 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full">Available</div>
                            <?php else: ?>
                                <div class="absolute top-4 right-4 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">Sold Out</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <span class="text-amber-600 font-bold text-xl">₱<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                            <p class="text-gray-600 mb-6 line-clamp-3"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="flex items-center justify-between">
                                <span class="inline-block bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-sm font-medium"><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></span>
                                <?php if (is_logged_in()): ?>
                                    <form method="POST" action="index.php" class="w-1/2">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-full transition duration-300 flex items-center justify-center" <?php echo !$item['is_available'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-cart-plus mr-2"></i> Add
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="modules/auth/login.php" class="w-1/2 bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-full transition duration-300 flex items-center justify-center">
                                        <i class="fas fa-sign-in-alt mr-2"></i> Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php
                    $count++;
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Menu Section with Improved UI -->
    <section id="menu" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Our Menu</h2>
                <p class="text-gray-600 text-center max-w-2xl mx-auto mb-6">Explore our delicious selection of coffees, pastries, and light meals prepared with the finest ingredients.</p>
                <div class="w-24 h-1 bg-amber-500 mx-auto"></div>
            </div>

            <!-- Menu Categories Tabs - Improved -->
            <div class="flex flex-wrap justify-center mb-12">
                <button class="category-btn bg-amber-600 text-white py-3 px-8 rounded-full mx-2 mb-3 font-semibold shadow-md transform transition duration-300 hover:scale-105" data-category="all">All Items</button>
                <?php
                $categories = get_categories();
                foreach ($categories as $category) {
                    echo '<button class="category-btn bg-white text-gray-700 hover:bg-amber-600 hover:text-white py-3 px-8 rounded-full mx-2 mb-3 font-semibold shadow-md transform transition duration-300 hover:scale-105" data-category="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</button>';
                }
                ?>
            </div>

            <!-- Search & Filter Bar -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-10 flex flex-col md:flex-row items-center justify-between">
                <div class="relative w-full md:w-1/3 mb-4 md:mb-0">
                    <input type="text" id="menu-search" placeholder="Search our menu..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="available-only" class="mr-2">
                        <label for="available-only" class="text-gray-700">Available Only</label>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-700 mr-2">Sort By:</span>
                        <select id="sort-menu" class="border border-gray-300 rounded-lg px-3 py-1 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="name">Name</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="category">Category</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="hidden flex justify-center items-center mb-6">
                <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-b-4 border-amber-600"></div>
            </div>

            <!-- Menu Items Grid - Enhanced UI -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="menu-items-container">
                <?php
                $menuItems = get_menu_items(null, true); // Fetch available items
                foreach ($menuItems as $item) {
                ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden menu-card transform transition duration-300 hover:translate-y-[-5px] hover:shadow-xl" data-category="<?php echo $item['category_id'] ?: 'uncategorized'; ?>">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-full h-56 object-cover">
                            <div class="absolute top-0 left-0 w-full h-full bg-black bg-opacity-20 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300">
                                <button class="bg-white text-amber-600 hover:bg-amber-600 hover:text-white py-2 px-6 rounded-full font-semibold transform transition duration-300 hover:scale-105 quick-view-btn" data-item-id="<?php echo $item['item_id']; ?>">Quick View</button>
                            </div>
                            <?php if ($item['is_available']): ?>
                                <div class="absolute top-4 right-4 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full">Available</div>
                            <?php else: ?>
                                <div class="absolute top-4 right-4 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">Sold Out</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <span class="text-amber-600 font-bold text-xl">₱<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                            <p class="text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="inline-block bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-sm font-medium"><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></span>
                                <?php if ($item['calories'] !== null): ?>
                                    <span class="inline-block bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-medium"><?php echo $item['calories']; ?> cal</span>
                                <?php endif; ?>
                                <?php if ($item['prep_time'] !== null): ?>
                                    <span class="inline-block bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-medium">Prep: <?php echo $item['prep_time']; ?> min</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['allergens']): ?>
                                <div class="mb-4">
                                    <span class="inline-block bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-medium">Allergens: <?php echo htmlspecialchars($item['allergens']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (is_logged_in()): ?>
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-1/3 border border-gray-300 rounded-lg flex items-center">
                                            <button type="button" class="px-3 py-1 text-gray-600 hover:text-amber-600 qty-btn" data-action="decrease">−</button>
                                            <input type="number" name="quantity" value="1" min="1" max="10" class="w-full text-center py-1 focus:outline-none">
                                            <button type="button" class="px-3 py-1 text-gray-600 hover:text-amber-600 qty-btn" data-action="increase">+</button>
                                        </div>
                                        <button type="submit" class="w-2/3 bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center" <?php echo !$item['is_available'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-cart-plus mr-2"></i> Add to Cart
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <a href="modules/auth/login.php" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                                    <i class="fas fa-sign-in-alt mr-2"></i> Login to Order
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- No Results Message -->
            <div id="no-results" class="hidden text-center py-12">
                <i class="far fa-frown text-4xl text-gray-400 mb-4"></i>
                <p class="text-xl text-gray-600">No items match your search. Try different keywords or filters.</p>
            </div>

            <!-- View Full Menu Button -->
            <div class="text-center mt-16">
                <a href="./modules/pages/menu.php" class="inline-block bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-8 rounded-full transition duration-300 shadow-md transform hover:scale-105">View Full Menu</a>
            </div>
        </div>
    </section>

    <!-- Quick View Modal -->
    <div id="quick-view-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto p-6 m-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800" id="modal-item-name">Item Name</h3>
                <button class="text-gray-500 hover:text-gray-700 text-2xl" id="close-modal">&times;</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <img src="" alt="Item Image" id="modal-item-image" class="w-full h-auto rounded-lg object-cover">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-amber-600 font-bold text-2xl" id="modal-item-price">₱0.00</span>
                        <span id="modal-item-availability" class="inline-block px-3 py-1 rounded-full text-sm font-medium"></span>
                    </div>
                    <p class="text-gray-600 mb-6" id="modal-item-description">Description loading...</p>

                    <div class="space-y-4 mb-6">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-2">Category</h4>
                            <span class="inline-block bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-sm font-medium" id="modal-item-category">Category</span>
                        </div>

                        <div id="modal-item-nutrition-section">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-2">Nutrition</h4>
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-block bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-medium" id="modal-item-calories"></span>
                            </div>
                        </div>

                        <div id="modal-item-allergens-section" class="hidden">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-2">Allergens</h4>
                            <span class="inline-block bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-medium" id="modal-item-allergens"></span>
                        </div>

                        <div id="modal-item-preptime-section">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-2">Preparation Time</h4>
                            <span class="inline-block bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-medium" id="modal-item-preptime"></span>
                        </div>
                    </div>

                    <?php if (is_logged_in()): ?>
                        <form method="POST" action="index.php" id="modal-add-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="item_id" id="modal-item-id" value="">
                            <div class="flex items-center space-x-3 mb-4">
                                <label class="text-gray-700 font-medium">Quantity:</label>
                                <div class="border border-gray-300 rounded-lg flex items-center">
                                    <button type="button" class="px-3 py-1 text-gray-600 hover:text-amber-600 modal-qty-btn" data-action="decrease">−</button>
                                    <input type="number" name="quantity" value="1" min="1" max="10" class="w-16 text-center py-1 focus:outline-none">
                                    <button type="button" class="px-3 py-1 text-gray-600 hover:text-amber-600 modal-qty-btn" data-action="increase">+</button>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center" id="modal-add-btn">
                                <i class="fas fa-cart-plus mr-2"></i> Add to Cart
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="modules/auth/login.php" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i> Login to Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


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
                    <div class="space-y-4 max-h-96 overflow-y-auto">
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
                                    <form method="POST" action="index.php" class="flex items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="update_cart">
                                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="w-16 px-2 py-1 border border-amber-200 rounded-md focus:outline-none focus:ring-amber-500 focus:border-amber-500">
                                        <button type="submit" class="ml-2 text-amber-600 hover:text-amber-700">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="index.php">
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
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold text-amber-600">Total:</span>
                            <span class="text-lg font-bold text-amber-600">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <form method="POST" action="modules/customers/checkout.php">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-6 rounded-full transition duration-300">Proceed to Checkout</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- About Section -->
    <section id="about" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-8 md:mb-0 md:pr-8">
                    <img src="/assets/Uploads/images/logo.png" alt="About Casa Baraka" class="rounded-lg shadow-md w-full">
                </div>
                <div class="md:w-1/2">
                    <h2 class="text-3xl font-bold mb-6">Our Story</h2>
                    <p class="text-gray-600 mb-4">Founded in 2010, Casa Baraka began as a small neighborhood coffee shop with a simple mission: to create a welcoming space where quality coffee meets community connection.</p>
                    <p class="text-gray-600 mb-4">Over the years, we've grown, but our commitment to quality ingredients, skilled baristas, and a warm atmosphere remains unchanged. We source our coffee beans directly from sustainable farms, ensuring both exceptional taste and ethical practices.</p>
                    <p class="text-gray-600 mb-6">Today, Casa Baraka is proud to be your local gathering spot—a place where friendships form, ideas brew, and everyone feels at home.</p>
                    <div class="flex flex-wrap">
                        <div class="w-1/2 md:w-1/3 mb-4">
                            <div class="font-bold text-2xl text-amber-600">12+</div>
                            <div class="text-gray-600">Years of Service</div>
                        </div>
                        <div class="w-1/2 md:w-1/3 mb-4">
                            <div class="font-bold text-2xl text-amber-600">3</div>
                            <div class="text-gray-600">Locations</div>
                        </div>
                        <div class="w-1/2 md:w-1/3 mb-4">
                            <div class="font-bold text-2xl text-amber-600">10k+</div>
                            <div class="text-gray-600">Happy Customers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">What Our Customers Say</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex text-amber-500 mb-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 mb-6">"The atmosphere is so welcoming and the coffee is simply amazing. I come here every morning before work, and it's the perfect start to my day!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-300 mr-4"></div>
                        <div>
                            <h4 class="font-semibold">Sarah Johnson</h4>
                            <p class="text-gray-500 text-sm">Regular Customer</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex text-amber-500 mb-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 mb-6">"I've tried coffee shops all over the city, and Casa Baraka consistently has the best lattes. Their pastries are fresh and delicious too. This place is a gem!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-300 mr-4"></div>
                        <div>
                            <h4 class="font-semibold">Michael Chen</h4>
                            <p class="text-gray-500 text-sm">Coffee Enthusiast</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex text-amber-500 mb-4">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="text-gray-600 mb-6">"As a remote worker, I need a reliable place with good WiFi and even better coffee. Casa Baraka has become my second office. The staff is friendly and the ambiance is perfect for productivity."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-300 mr-4"></div>
                        <div>
                            <h4 class="font-semibold">Jessica Martinez</h4>
                            <p class="text-gray-500 text-sm">Freelance Designer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Get In Touch</h2>
            <div class="flex flex-col md:flex-row">
                <!-- Contact Information -->
                <div class="md:w-1/2 mb-8 md:mb-0 md:pr-8">
                    <div class="bg-gray-50 p-8 rounded-lg shadow-md h-full">
                        <h3 class="text-xl font-semibold mb-6">Contact Information</h3>
                        <div class="flex items-start mb-6">
                            <i class="fas fa-map-marker-alt text-amber-600 mt-1 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-semibold mb-1">Main Location</h4>
                                <p class="text-gray-600">123 Coffee Street<br>Portland, OR 97205</p>
                            </div>
                        </div>
                        <div class="flex items-start mb-6">
                            <i class="fas fa-clock text-amber-600 mt-1 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-semibold mb-1">Opening Hours</h4>
                                <p class="text-gray-600">Monday - Friday: 7:00 AM - 8:00 PM<br>Weekends: 8:00 AM - 9:00 PM</p>
                            </div>
                        </div>
                        <div class="flex items-start mb-6">
                            <i class="fas fa-phone text-amber-600 mt-1 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-semibold mb-1">Phone</h4>
                                <p class="text-gray-600">(503) 555-1234</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-envelope text-amber-600 mt-1 mr-4 text-xl"></i>
                            <div>
                                <h4 class="font-semibold mb-1">Email</h4>
                                <p class="text-gray-600">hello@casabarakat.com</p>
                            </div>
                        </div>
                        <div class="mt-8">
                            <h4 class="font-semibold mb-4">Follow Us</h4>
                            <div class="flex space-x-4">
                                <a href="#" class="text-amber-600 hover:text-amber-500 text-xl"><i class="fab fa-facebook"></i></a>
                                <a href="#" class="text-amber-600 hover:text-amber-500 text-xl"><i class="fab fa-instagram"></i></a>
                                <a href="#" class="text-amber-600 hover:text-amber-500 text-xl"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="text-amber-600 hover:text-amber-500 text-xl"><i class="fab fa-yelp"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="md:w-1/2">
                    <form action="process_contact.php" method="POST" class="bg-gray-50 p-8 rounded-lg shadow-md">
                        <div class="mb-6">
                            <label for="name" class="block text-gray-700 font-medium mb-2">Your Name</label>
                            <input type="text" id="name" name="name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                        </div>
                        <div class="mb-6">
                            <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                        </div>
                        <div class="mb-6">
                            <label for="subject" class="block text-gray-700 font-medium mb-2">Subject</label>
                            <input type="text" id="subject" name="subject" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                        </div>
                        <div class="mb-6">
                            <label for="message" class="block text-gray-700 font-medium mb-2">Message</label>
                            <textarea id="message" name="message" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required></textarea>
                        </div>
                        <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-medium py-2 px-6 rounded-md transition duration-300">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-12 bg-amber-600">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-6 md:mb-0 md:w-1/2 text-center md:text-left">
                    <h3 class="text-2xl font-bold text-white mb-2">Join Our Newsletter</h3>
                    <p class="text-amber-100">Sign up to receive updates on special offers, new menu items, and events.</p>
                </div>
                <div class="md:w-1/2">
                    <form action="subscribe.php" method="POST" class="flex flex-col sm:flex-row">
                        <input type="email" name="email" placeholder="Your email address" class="px-4 py-3 rounded-l-md w-full sm:w-auto mb-2 sm:mb-0 focus:outline-none" required>
                        <button type="submit" class="bg-amber-800 hover:bg-amber-900 text-white px-6 py-3 rounded-r-md font-medium transition duration-300">Subscribe</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include './includes/footer.php' ?>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-6 right-6 bg-amber-600 text-white p-3 rounded-full shadow-lg opacity-0 invisible transition-all duration-300">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="/assets/js/index.js"></script>
    <!-- Scripts -->
    <script>
        // Modal toggle function
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.toggle('hidden');
            } else {
                console.warn(`Modal with ID ${modalId} not found.`);
            }
        }

        // Open Cart Modal (only available for logged-in users)
        window.openCartModal = function() {
            <?php if (is_logged_in()): ?>
                toggleModal('cartModal');
            <?php else: ?>
                window.location.href = 'modules/auth/login.php';
            <?php endif; ?>
        };
        // Mobile menu toggle
        (function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // Category filter functionality
            document.addEventListener('DOMContentLoaded', function() {
                const categoryButtons = document.querySelectorAll('.category-btn');
                const menuItems = document.querySelectorAll('.menu-card');
                const loadingSpinner = document.getElementById('loadingSpinner');

                if (categoryButtons && menuItems && loadingSpinner) {
                    categoryButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const category = button.dataset.category;

                            // Update button styles
                            categoryButtons.forEach(btn => {
                                btn.classList.remove('bg-amber-600', 'text-white');
                                btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');
                            });
                            button.classList.add('bg-amber-600', 'text-white');
                            button.classList.remove('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');

                            // Show loading spinner
                            loadingSpinner.classList.remove('hidden');
                            menuItems.forEach(item => item.classList.add('opacity-50'));

                            // Simulate loading delay
                            setTimeout(() => {
                                menuItems.forEach(item => {
                                    const itemCategory = item.dataset.category;
                                    if (category === 'all' || (category === itemCategory) || (category !== 'uncategorized' && itemCategory === 'uncategorized' && !itemCategory)) {
                                        item.style.display = 'block';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                    item.classList.remove('opacity-50');
                                });
                                loadingSpinner.classList.add('hidden');
                            }, 300);
                        });
                    });
                }

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
                document.addEventListener('click', (e) => {
                    const modal = document.getElementById('cartModal');
                    if (modal && e.target === modal) {
                        modal.classList.add('hidden');
                    }
                });
            });
        })();

        document.querySelector('.mobile-menu-button').addEventListener('click', function() {
            const menu = document.querySelector('.mobile-menu');
            menu.classList.toggle('active');
            menu.classList.toggle('hidden');
        });
    </script>
</body>

</html>