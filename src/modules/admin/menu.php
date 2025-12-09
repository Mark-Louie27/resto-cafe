<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controller/MenuController.php';
require_admin();

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Get admin data
$admin = get_user_by_id($user_id);
if (!$admin) {
    set_flash_message('User not found', 'error');
    header('Location: /auth/login.php');
    exit();
}

include __DIR__ . '/include/header.php';

$page_title = "Menu Management";
$current_page = "menu";
define('SITE_NAME', 'Resto Cafe'); // Define the SITE_NAME constant

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'])) {
        set_flash_message('CSRF token missing', 'error');
        header('Location: menu.php');
        exit();
    }

    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid CSRF token', 'error');
        header('Location: menu.php');
        exit();
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                handle_add_category();
                break;
            case 'update_category':
                handle_update_category();
                break;
            case 'delete_category':
                handle_delete_category();
                break;
            case 'add_item':
                handle_add_item();
                break;
            case 'update_item':
                handle_update_item();
                break;
            case 'delete_item':
                handle_delete_item();
                break;
            case 'toggle_availability':
                handle_toggle_availability();
                break;
        }
    }
}

// Get all categories and items
$categories = get_categories(true);
$items_by_category = [];

foreach ($categories as $category) {
    $items = get_menu_items($category['category_id']);
    $items_by_category[$category['category_id']] = [
        'category' => $category,
        'items' => $items
    ];
}

// Get uncategorized items
$uncategorized_items = get_menu_items(null);
if (!empty($uncategorized_items)) {
    $items_by_category[0] = [
        'category' => ['category_id' => 0, 'name' => 'Uncategorized', 'item_count' => count($uncategorized_items)],
        'items' => $uncategorized_items
    ];
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
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
        .modal-enter {
            opacity: 0;
            transform: scale(0.95);
        }

        .modal-enter-active {
            opacity: 1;
            transform: scale(1);
            transition: opacity 300ms ease-out, transform 300ms ease-out;
        }

        .modal-exit {
            opacity: 1;
            transform: scale(1);
        }

        .modal-exit-active {
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 200ms ease-in, transform 200ms ease-in;
        }

        .backdrop-enter {
            opacity: 0;
        }

        .backdrop-enter-active {
            opacity: 1;
            transition: opacity 300ms ease-out;
        }

        .backdrop-exit {
            opacity: 1;
        }

        .backdrop-exit-active {
            opacity: 0;
            transition: opacity 200ms ease-in;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans h-full">
    <div class="flex h-full">
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="flex items-center justify-between p-4 lg:mx-auto lg:max-w-7xl">
                    <h1 class="text-2xl font-bold text-amber-900"> Management</h1>
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

            <main class="flex-1 pb-8 overflow-y-auto">
                <!-- Page header -->
                <div class="">
                    <div class="px-4 sm:px-6 lg:mx-auto lg:max-w-7xl lg:px-8">
                        <div class="py-6 md:flex md:items-center md:justify-between lg:border-t lg:border-gray-200">
                            <div class="min-w-0 flex-1">
                            </div>
                            <div class="mt-4 flex md:ml-4 md:mt-0 space-x-3">
                                <button onclick="toggleModal('addCategoryModal')"
                                    class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                                    <i class="fas fa-plus mr-2"></i> Add Category
                                </button>
                                <button onclick="toggleModal('addItemModal')"
                                    class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                                    <i class="fas fa-plus mr-2"></i> Add Item
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <?php display_flash_message(); ?>

                        <div class="space-y-6">
                            <?php foreach ($items_by_category as $category_id => $data): ?>
                                <div class="bg-white shadow rounded-lg overflow-hidden">
                                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                                        <div class="flex items-center">
                                            <h3 class="text-lg font-medium leading-6 text-gray-900">
                                                <?= htmlspecialchars($data['category']['name']) ?>
                                            </h3>
                                            <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                                <?= $data['category']['item_count'] ?> items
                                            </span>
                                        </div>
                                        <?php if ($category_id != 0): ?>
                                            <div class="flex space-x-3">
                                                <button onclick='openEditCategoryModal(<?= json_encode($data['category'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                    class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                                    <i class="fas fa-pencil-alt mr-1.5 text-xs"></i> Edit
                                                </button>
                                                <button onclick="confirmDeleteCategory(<?= $data['category']['category_id'] ?>)"
                                                    class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-red-50">
                                                    <i class="fas fa-trash-alt mr-1.5 text-xs"></i> Delete
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    <th scope="col" class="relative px-6 py-3">
                                                        <span class="sr-only">Actions</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($data['items'] as $item): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                <?php if ($item['image_url']): ?>
                                                                    <div class="flex-shrink-0 h-10 w-10">
                                                                        <img class="h-10 w-10 rounded-full object-cover" src="/../assets/Uploads/menu/<?= htmlspecialchars(basename($item['image_url'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.src='https://picsum.photos/300/200'">
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class=" flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                                        <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="ml-4">
                                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                                                    <div class="text-sm text-gray-500">
                                                                        <?= $item['calories'] ? htmlspecialchars($item['calories']) . ' cal' : 'N/A' ?>
                                                                        <?= $item['prep_time'] ? ' · ' . htmlspecialchars($item['prep_time']) . ' min' : '' ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <div class="text-sm text-gray-900 max-w-xs"><?= htmlspecialchars($item['description']) ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            $<?= number_format($item['price'], 2) ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            $<?= number_format($item['cost'], 2) ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $item['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                                <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <button onclick='openEditItemModal(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                                class="text-amber-600 hover:text-amber-900 mr-3">
                                                                <i class="fas fa-edit mr-1"></i> Edit
                                                            </button>
                                                            <button onclick="confirmDeleteItem(<?= $item['item_id'] ?>)"
                                                                class="text-red-600 hover:text-red-900">
                                                                <i class="fas fa-trash-alt mr-1"></i> Delete
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md modal-enter modal-enter-active">
                    <form method="POST" action="menu.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="add_category">
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                                <i class="fas fa-tag text-amber-600 text-xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900" id="modal-title">Add New Category</h3>
                        </div>
                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
                                <input type="text" name="name" id="category_name" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div>
                                <label for="category_description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="category_description" name="description" rows="3"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category Image</label>
                                <div id="categoryImageDropArea" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-200 border-dashed rounded-lg hover:border-amber-500 transition-colors"
                                    ondragover="handleDragOver(event)" ondragenter="handleDragEnter(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'category_image')">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="category_image" class="relative cursor-pointer rounded-md font-medium text-amber-600 hover:text-amber-500 focus-within:outline-none">
                                                <span>Upload an image</span>
                                                <input id="category_image" name="menu_image" type="file" accept="image/*" class="sr-only" onchange="handleFileSelect(event, 'categoryImagePreview')">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div id="categoryImagePreview" class="mt-2 hidden">
                                    <img src="" alt="Image Preview" class="max-h-40 w-full object-contain rounded-md">
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('addCategoryModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors">
                                Add Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md modal-enter modal-enter-active">
                    <form method="POST" action="menu.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_category">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                                <i class="fas fa-tag text-amber-600 text-xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900" id="modal-title">Edit Category</h3>
                        </div>
                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="edit_category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
                                <input type="text" name="name" id="edit_category_name" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div>
                                <label for="edit_category_description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="edit_category_description" name="description" rows="3"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category Image</label>
                                <div id="editCategoryImageDropArea" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-200 border-dashed rounded-lg hover:border-amber-500 transition-colors"
                                    ondragover="handleDragOver(event)" ondragenter="handleDragEnter(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'edit_category_image')">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="edit_category_image" class="relative cursor-pointer rounded-md font-medium text-amber-600 hover:text-amber-500 focus-within:outline-none">
                                                <span>Upload an image</span>
                                                <input id="edit_category_image" name="menu_image" type="file" accept="image/*" class="sr-only" onchange="handleFileSelect(event, 'editCategoryImagePreview')">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div id="editCategoryImagePreview" class="mt-2 hidden">
                                    <img src="" alt="Image Preview" class="max-h-40 w-full object-contain rounded-md">
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('editCategoryModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors">
                                Update Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md modal-enter modal-enter-active">
                    <form method="POST" action="menu.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" id="delete_category_id">
                        <div class="flex items-start">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <div class="ml-4 text-left">
                                <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Delete Category</h3>
                                <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this category? Items in this category will become uncategorized. This action cannot be undone.</p>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('deleteCategoryModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 transition-colors">
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl modal-enter modal-enter-active">
                    <form method="POST" action="menu.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="add_item">
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                                <i class="fas fa-utensils text-amber-600 text-xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900" id="modal-title">Add New Menu Item</h3>
                        </div>
                        <div class="mt-6 grid grid-cols-1 gap-y-5 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="item_name" class="block text-sm font-medium text-gray-700">Item Name</label>
                                <input type="text" name="name" id="item_name" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="item_description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="item_description" name="description" rows="3"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"></textarea>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="item_category" class="block text-sm font-medium text-gray-700">Category</label>
                                <select id="item_category" name="category_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                                    <option value="">Uncategorized</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="item_prep_time" class="block text-sm font-medium text-gray-700">Prep Time (minutes)</label>
                                <input type="number" name="prep_time" id="item_prep_time" min="1"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="item_price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                                <input type="number" name="price" id="item_price" min="0" step="0.01" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="item_cost" class="block text-sm font-medium text-gray-700">Cost ($)</label>
                                <input type="number" name="cost" id="item_cost" min="0" step="0.01" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="item_calories" class="block text-sm font-medium text-gray-700">Calories</label>
                                <input type="number" name="calories" id="item_calories" min="0"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="item_allergens" class="block text-sm font-medium text-gray-700">Allergens</label>
                                <input type="text" name="allergens" id="item_allergens"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors" placeholder="e.g., nuts, dairy, gluten">
                            </div>
                            <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700">Item Image</label>
                                <div id="itemImageDropArea" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-200 border-dashed rounded-lg hover:border-amber-500 transition-colors"
                                    ondragover="handleDragOver(event)" ondragenter="handleDragEnter(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'item_image')">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="item_image" class="relative cursor-pointer rounded-md font-medium text-amber-600 hover:text-amber-500 focus-within:outline-none">
                                                <span>Upload an image</span>
                                                <input id="item_image" name="menu_image" type="file" accept="image/*" class="sr-only" onchange="handleFileSelect(event, 'itemImagePreview')">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div id="itemImagePreview" class="mt-2 hidden">
                                    <img src="" alt="Image Preview" class="max-h-40 w-full object-contain rounded-md">
                                </div>
                            </div>
                            <div class="sm:col-span-6">
                                <div class="flex items-center">
                                    <input id="item_available" name="is_available" type="checkbox" checked
                                        class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <label for="item_available" class="ml-2 block text-sm text-gray-700">Available on menu</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('addItemModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors">
                                Add Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl modal-enter modal-enter-active">
                    <form method="POST" action="menu.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_item">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                                <i class="fas fa-utensils text-amber-600 text-xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900" id="modal-title">Edit Menu Item</h3>
                        </div>
                        <div class="mt-6 grid grid-cols-1 gap-y-5 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="edit_item_name" class="block text-sm font-medium text-gray-700">Item Name</label>
                                <input type="text" name="name" id="edit_item_name" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="edit_item_description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="edit_item_description" name="description" rows="3"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"></textarea>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="edit_item_category" class="block text-sm font-medium text-gray-700">Category</label>
                                <select id="edit_item_category" name="category_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                                    <option value="">Uncategorized</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label for="edit_item_prep_time" class="block text-sm font-medium text-gray-700">Prep Time (minutes)</label>
                                <input type="number" name="prep_time" id="edit_item_prep_time" min="1"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="edit_item_price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                                <input type="number" name="price" id="edit_item_price" min="0" step="0.01" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="edit_item_cost" class="block text-sm font-medium text-gray-700">Cost ($)</label>
                                <input type="number" name="cost" id="edit_item_cost" min="0" step="0.01" required
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="edit_item_calories" class="block text-sm font-medium text-gray-700">Calories</label>
                                <input type="number" name="calories" id="edit_item_calories" min="0"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors">
                            </div>
                            <div class="sm:col-span-6">
                                <label for="edit_item_allergens" class="block text-sm font-medium text-gray-700">Allergens</label>
                                <input type="text" name="allergens" id="edit_item_allergens"
                                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 transition-colors" placeholder="e.g., nuts, dairy, gluten">
                            </div>
                            <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700">Item Image</label>
                                <div id="editItemImageDropArea" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-200 border-dashed rounded-lg hover:border-amber-500 transition-colors"
                                    ondragover="handleDragOver(event)" ondragenter="handleDragEnter(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'edit_item_image')">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="edit_item_image" class="relative cursor-pointer rounded-md font-medium text-amber-600 hover:text-amber-500 focus-within:outline-none">
                                                <span>Upload an image</span>
                                                <input id="edit_item_image" name="menu_image" type="file" accept="image/*" class="sr-only" onchange="handleFileSelect(event, 'editItemImagePreview')">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div id="editItemImagePreview" class="mt-2 hidden">
                                    <img src="" alt="Image Preview" class="max-h-40 w-full object-contain rounded-md">
                                </div>
                            </div>
                            <div class="sm:col-span-6">
                                <div class="flex items-center">
                                    <input id="edit_item_available" name="is_available" type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <label for="edit_item_available" class="ml-2 block text-sm text-gray-700">Available on menu</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('editItemModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors">
                                Update Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Item Modal -->
    <div id="deleteItemModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-enter backdrop-enter-active"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-xl bg-white px-6 py-8 shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md modal-enter modal-enter-active">
                    <form method="POST" action="menu.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_id" id="delete_item_id">
                        <div class="flex items-start">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <div class="ml-4 text-left">
                                <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Delete Menu Item</h3>
                                <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this menu item? This action cannot be undone.</p>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" onclick="toggleModal('deleteItemModal')"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 transition-colors">
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = modal.querySelector('.fixed.inset-0');
            const modalContent = modal.querySelector('.relative.transform');

            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    backdrop.classList.add('backdrop-enter-active');
                    modalContent.classList.add('modal-enter-active');
                }, 10);
            } else {
                backdrop.classList.add('backdrop-exit-active');
                modalContent.classList.add('modal-exit-active');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    backdrop.classList.remove('backdrop-enter-active', 'backdrop-exit-active');
                    modalContent.classList.remove('modal-enter-active', 'modal-exit-active');
                }, 200);
            }
        }

        function openEditCategoryModal(category) {
            console.log('Opening edit category modal with data:', category);
            try {
                document.getElementById('edit_category_id').value = category.category_id || '';
                document.getElementById('edit_category_name').value = category.name || '';
                document.getElementById('edit_category_description').value = category.description || '';
                const preview = document.getElementById('editCategoryImagePreview');
                const img = preview.querySelector('img');
                if (category.image_url) {
                    img.src = '/../assets/Uploads/menu/' + encodeURIComponent(category.image_url.split('/').pop());
                    preview.classList.remove('hidden');
                } else {
                    img.src = '';
                    preview.classList.add('hidden');
                }
                toggleModal('editCategoryModal');
            } catch (error) {
                console.error('Error in openEditCategoryModal:', error);
            }
        }

        function confirmDeleteCategory(categoryId) {
            console.log('Confirm delete category:', categoryId);
            document.getElementById('delete_category_id').value = categoryId;
            toggleModal('deleteCategoryModal');
        }

        function openEditItemModal(item) {
            console.log('Opening edit item modal with data:', item);
            try {
                document.getElementById('edit_item_id').value = item.item_id || '';
                document.getElementById('edit_item_name').value = item.name || '';
                document.getElementById('edit_item_description').value = item.description || '';
                document.getElementById('edit_item_category').value = item.category_id || '';
                document.getElementById('edit_item_prep_time').value = item.prep_time || '';
                document.getElementById('edit_item_price').value = item.price || '';
                document.getElementById('edit_item_cost').value = item.cost || '';
                document.getElementById('edit_item_calories').value = item.calories || '';
                document.getElementById('edit_item_allergens').value = item.allergens || '';
                document.getElementById('edit_item_available').checked = !!item.is_available;
                const preview = document.getElementById('editItemImagePreview');
                const img = preview.querySelector('img');
                if (item.image_url) {
                    img.src = '/../assets/Uploads/menu/' + encodeURIComponent(item.image_url.split('/').pop());
                    preview.classList.remove('hidden');
                } else {
                    img.src = '';
                    preview.classList.add('hidden');
                }
                toggleModal('editItemModal');
            } catch (error) {
                console.error('Error in openEditItemModal:', error);
            }
        }

        function confirmDeleteItem(itemId) {
            console.log('Confirm delete item:', itemId);
            document.getElementById('delete_item_id').value = itemId;
            toggleModal('deleteItemModal');
        }

        // Drag and Drop Handlers
        function handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('border-amber-500', 'bg-amber-50');
        }

        function handleDragEnter(event) {
            event.preventDefault();
            event.currentTarget.classList.add('border-amber-500', 'bg-amber-50');
        }

        function handleDragLeave(event) {
            event.currentTarget.classList.remove('border-amber-500', 'bg-amber-50');
        }

        function handleDrop(event, inputId) {
            event.preventDefault();
            event.currentTarget.classList.remove('border-amber-500', 'bg-amber-50');
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const input = document.getElementById(inputId);
                input.files = files;
                const previewId = inputId.replace('image', 'ImagePreview');
                handleFileSelect({
                    target: input
                }, previewId);
            }
        }

        function handleFileSelect(event, previewId) {
            console.log('Handling file select for preview:', previewId);
            const file = event.target.files[0];
            const preview = document.getElementById(previewId);
            const img = preview.querySelector('img');

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('File loaded, setting preview src:', e.target.result);
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.onerror = function(e) {
                    console.error('Error reading file:', e);
                    preview.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                console.log('No valid image file selected');
                img.src = '';
                preview.classList.add('hidden');
            }
        }
    </script>
</body>

</html>