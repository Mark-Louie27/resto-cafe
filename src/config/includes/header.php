<?php
require_once __DIR__ . '/../includes/functions.php'; // Assuming this connects to your database
require_once __DIR__ . '/../controller/OrderController.php';
require_once __DIR__ . '/../controller/MenuController.php'; // Assuming this connects to your database

// Get cart item count (only for logged-in users)
$cart_item_count = is_logged_in() ? get_cart_item_count() : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Casa Baraka' : 'Casa Baraka - Your Cozy Corner'; ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'amber': {
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>

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

        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../assets/images/cafe-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 80vh;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .alert {
            position: relative;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }

        .alert-success {
            color: #065f46;
            background-color: #d1fae5;
            border-color: #a7f3d0;
        }

        .alert-error {
            color: #991b1b;
            background-color: #fee2e2;
            border-color: #fecaca;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <?php if (is_system_down()): ?>
        <div class="downtime-banner">
            System is currently down for maintenance. You can browse the site, but transactions (orders, reservations, payments) are disabled.
        </div>
    <?php endif; ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-10">
        <div class="max-w-6xl mx-auto px-4">
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
                        <a href="/index.php" class="py-4 px-2 font-semibold transition duration-300 <?php echo $is_home ? 'text-amber-600 border-b-4 border-amber-600' : 'text-gray-500 hover:text-amber-600' ?>">Home</a>
                        <a href="/index.php#menu" class="py-4 px-2 font-semibold transition duration-300 <?php echo ($current_page === 'menu.php' || strpos($_SERVER['REQUEST_URI'], '#menu') !== false) ? 'text-amber-600 border-b-4 border-amber-600' : 'text-gray-500 hover:text-amber-600' ?>">Menu</a>
                        <a href="/index.php#about" class="py-4 px-2 font-semibold transition duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], '#about') !== false ? 'text-amber-600 border-b-4 border-amber-600' : 'text-gray-500 hover:text-amber-600' ?>">About</a>
                        <a href="/index.php#contact" class="py-4 px-2 font-semibold transition duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], '#contact') !== false ? 'text-amber-600 border-b-4 border-amber-600' : 'text-gray-500 hover:text-amber-600' ?>">Contact</a>
                    </div>
                </div>
                <!-- Auth Navigation -->
                <div class="hidden md:flex items-center space-x-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button onclick="openCartModal()" class="relative py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_item_count > 0): ?>
                                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-amber-600 rounded-full"><?php echo $cart_item_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <a href="/modules/customers/dashboard.php" class="py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">Dashboard</a>
                        <a href="/modules/auth/logout.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Logout</a>
                    <?php else: ?>
                        <a href="/modules/auth/login.php" class="py-2 px-4 font-semibold text-gray-500 hover:text-amber-600 transition duration-300">Log In</a>
                        <a href="/modules/auth/register.php" class="py-2 px-4 font-medium text-white bg-amber-600 rounded hover:bg-amber-500 transition duration-300">Sign Up</a>
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
                <li><a href="/index.php" class="block py-2 px-4 text-sm <?php echo $is_home ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Home</a></li>
                <li><a href="#menu" class="block py-2 px-4 text-sm <?php echo ($current_page === 'menu.php' || strpos($_SERVER['REQUEST_URI'], '#menu') !== false) ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Menu</a></li>
                <li><a href="#about" class="block py-2 px-4 text-sm <?php echo strpos($_SERVER['REQUEST_URI'], '#about') !== false ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">About</a></li>
                <li><a href="#contact" class="block py-2 px-4 text-sm <?php echo strpos($_SERVER['REQUEST_URI'], '#contact') !== false ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Contact</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="/modules/customers/dashboard.php" class="block py-2 px-4 text-sm <?php echo $current_page === 'dashboard.php' ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Dashboard</a></li>
                    <li><a href="modules/auth/logout.php" class="block py-2 px-4 text-sm hover:bg-amber-50">Logout</a></li>
                <?php else: ?>
                    <li><a href="modules/auth/login.php" class="block py-2 px-4 text-sm <?php echo $current_page === 'login.php' ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Log In</a></li>
                    <li><a href="modules/auth/register.php" class="block py-2 px-4 text-sm <?php echo $current_page === 'register.php' ? 'bg-amber-50 text-amber-600' : 'hover:bg-amber-50' ?>">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container mx-auto px-4 pt-24">
        <?php
        if (function_exists('display_flash_message')) {
            display_flash_message();
        }
        ?>
    </div>

    <!-- Mobile Menu Script -->
    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        const mobileMenu = document.querySelector('.mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

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

        // Highlight current page based on URL hash
        function highlightCurrentPage() {
            const hash = window.location.hash;
            const navLinks = document.querySelectorAll('nav a');

            navLinks.forEach(link => {
                const linkHref = link.getAttribute('href');

                // Check if link href matches current hash or page
                if (linkHref === window.location.pathname ||
                    (hash && linkHref.includes(hash)) ||
                    (window.location.pathname.includes(linkHref.replace(/^\/|#.*$/g, '')))) {
                    link.classList.add('text-amber-600', 'border-b-4', 'border-amber-600');
                    link.classList.remove('text-gray-500');
                } else {
                    link.classList.remove('text-amber-600', 'border-b-4', 'border-amber-600');
                    link.classList.add('text-gray-500');
                }
            });
        }

        // Run on page load and hash change
        document.addEventListener('DOMContentLoaded', highlightCurrentPage);
        window.addEventListener('hashchange', highlightCurrentPage);

        // Mobile menu toggle
        (function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }

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
    </script>