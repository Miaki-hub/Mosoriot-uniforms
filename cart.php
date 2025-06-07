<?php
session_start();

// Fix the database connection path (adjust according to your file structure)
$dbPath = __DIR__ . '/../config/db_connection.php'; // or '../config/db.php' if that's your file
if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    die("Database configuration file not found!");
}

// Initialize cart if not exists
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

// Handle remove item
if (isset($_POST['remove_item']) && isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];
    if (isset($_SESSION['pos_cart'][$item_id])) {
        unset($_SESSION['pos_cart'][$item_id]);
    }
    header("Location: cart.php");
    exit;
}

// Handle quantity changes
if (isset($_POST['adjust_quantity'])) {
    $item_id = $_POST['item_id'];
    $action = $_POST['action'];
    
    if (isset($_SESSION['pos_cart'][$item_id])) {
        if ($action === 'increase') {
            $_SESSION['pos_cart'][$item_id]['quantity']++;
        } elseif ($action === 'decrease' && $_SESSION['pos_cart'][$item_id]['quantity'] > 1) {
            $_SESSION['pos_cart'][$item_id]['quantity']--;
        }
    }
    header("Location: cart.php");
    exit;
}

// Calculate cart total
$cartTotal = 0;
foreach ($_SESSION['pos_cart'] as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Cart - Mosoriot Uniforms</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-10 px-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Shopping Cart</h1>
            <a href="uniforms.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Continue Shopping
            </a>
        </div>

        <?php if (empty($_SESSION['pos_cart'])): ?>
            <div class="bg-white p-8 rounded-lg shadow-md text-center">
                <i class="fas fa-shopping-cart text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600 text-lg">Your cart is empty</p>
                <a href="uniforms.php" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                    Browse Uniforms
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($_SESSION['pos_cart'] as $item_id => $item): 
                                $subtotal = $item['price'] * $item['quantity'];
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded" src="<?= $item['image'] ?? 'https://via.placeholder.com/50' ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($item['school_name'] ?? 'Generic') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($item['size']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    KSh <?= number_format($item['price'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="flex items-center">
                                        <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                        <button type="submit" name="adjust_quantity" value="decrease" class="px-2 text-gray-600 hover:text-gray-900">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <span class="mx-2"><?= $item['quantity'] ?></span>
                                        <button type="submit" name="adjust_quantity" value="increase" class="px-2 text-gray-600 hover:text-gray-900">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    KSh <?= number_format($subtotal, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                        <button type="submit" name="remove_item" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-right text-sm font-medium text-gray-500 uppercase">Total</td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                    KSh <?= number_format($cartTotal, 2) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-4">
                <a href="uniforms.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-md">
                    Continue Shopping
                </a>
                <a href="checkout.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md">
                    Proceed to Checkout
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>