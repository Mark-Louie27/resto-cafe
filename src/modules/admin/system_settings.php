<?php
require_once __DIR__ . '/../../includes/functions.php';
require_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real application, you would save these settings to a database table
    $_SESSION['settings_saved'] = true;
    header("Location: system_settings.php?success=Settings saved successfully");
    exit;
}

// Get current settings (in a real app, these would come from a database)
$settings = [
    'restaurant_name' => 'Cafe Restaurant',
    'contact_email' => 'info@caferestaurant.com',
    'contact_phone' => '+1 234 567 8900',
    'opening_hours' => 'Mon-Fri: 8am-10pm, Sat-Sun: 9am-11pm',
    'address' => '123 Main Street, City, Country',
    'currency' => 'USD',
    'tax_rate' => '7.5',
    'reservation_deposit' => '10'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <?php include 'admin_navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">System Settings</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow">
            <form method="POST">
                <div class="space-y-6">
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-lg font-medium text-gray-900">Restaurant Information</h2>

                        <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label for="restaurant_name" class="block text-sm font-medium text-gray-700">Restaurant Name</label>
                                <input type="text" name="restaurant_name" id="restaurant_name" value="<?= htmlspecialchars($settings['restaurant_name']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact Email</label>
                                <input type="email" name="contact_email" id="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                                <input type="text" name="contact_phone" id="contact_phone" value="<?= htmlspecialchars($settings['contact_phone']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-3">
                                <label for="opening_hours" class="block text-sm font-medium text-gray-700">Opening Hours</label>
                                <input type="text" name="opening_hours" id="opening_hours" value="<?= htmlspecialchars($settings['opening_hours']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-6">
                                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="address" rows="3"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($settings['address']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-lg font-medium text-gray-900">Business Settings</h2>

                        <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                                <select name="currency" id="currency"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="USD" <?= $settings['currency'] == 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                                    <option value="EUR" <?= $settings['currency'] == 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                                    <option value="GBP" <?= $settings['currency'] == 'GBP' ? 'selected' : '' ?>>British Pound (GBP)</option>
                                    <option value="JPY" <?= $settings['currency'] == 'JPY' ? 'selected' : '' ?>>Japanese Yen (JPY)</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="tax_rate" class="block text-sm font-medium text-gray-700">Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate" id="tax_rate" value="<?= htmlspecialchars($settings['tax_rate']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="reservation_deposit" class="block text-sm font-medium text-gray-700">Reservation Deposit (%)</label>
                                <input type="number" step="0.01" name="reservation_deposit" id="reservation_deposit" value="<?= htmlspecialchars($settings['reservation_deposit']) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>

</html>