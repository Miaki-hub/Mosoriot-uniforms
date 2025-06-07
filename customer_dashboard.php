<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start a secure session
session_start();
session_regenerate_id(true);

// Include the database configuration
require __DIR__ . '/../authentication/db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Check if user is admin (redirect to admin dashboard)
if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin") {
    header("Location: ../admin/admin_dashboard.php");
    exit();
}

// Securely get session variables
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$user_id = intval($_SESSION['user_id']);

// Define uniform types with their corresponding tables
$uniformTypes = [
    'Sweater' => 'sweaters',
    'Shirt' => 'shirts',
    'Dress' => 'dresses',
    'Short' => 'shorts',
    'Trouser' => 'trousers',
    'Socks' => 'socks',
    'Skirt' => 'skirts',
    'Jamper' => 'jampers',
    'Tracksuit' => 'tracksuits',
    'Gameshort' => 'gameshorts',
    'T-shirt' => 'tshirts',
    'Wrapskirt' => 'wrapskirts'
];

// Navigation configuration
$nav_items = [
    'customer_dashboard.php' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-home'
    ],
    'order_uniforms.php' => [
        'title' => 'Order Uniforms',
        'icon' => 'fas fa-boxes'
    ],
    'order_status.php' => [
        'title' => 'My Orders',
        'icon' => 'fas fa-clipboard-list'
    ],
    'order_history.php' => [
        'title' => 'Order History',
        'icon' => 'fas fa-history'
    ],
    'customer_settings.php' => [
        'title' => 'Settings',
        'icon' => 'fas fa-cog'
    ]
];

// Function to fetch all uniform stock
function fetchAllUniformStock($conn, $uniformTypes) {
    $stock = [];
    
    foreach ($uniformTypes as $typeName => $table) {
        try {
            $query = "SELECT 
                        u.id,
                        '$typeName' as type,
                        s.size,
                        s.quality,
                        s.quantity as stock_quantity,
                        s.price,
                        u.color
                      FROM $table s
                      JOIN uniforms u ON s.uniform_id = u.id
                      WHERE s.quantity > 0";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $typeStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stock = array_merge($stock, $typeStock);
        } catch (PDOException $e) {
            error_log("Error fetching $typeName: " . $e->getMessage());
            $_SESSION['error'] = "Error loading $typeName data";
        }
    }
    
    return $stock;
}

// Function to fetch stock by type
function fetchStockByType($conn, $type, $uniformTypes) {
    if (!array_key_exists($type, $uniformTypes)) {
        return [];
    }
    
    $table = $uniformTypes[$type];
    $query = "SELECT 
                u.id,
                '$type' as type,
                s.size,
                s.quality,
                s.quantity as stock_quantity,
                s.price,
                u.color
              FROM $table s
              JOIN uniforms u ON s.uniform_id = u.id
              WHERE s.quantity > 0";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching $type: " . $e->getMessage());
        $_SESSION['error'] = "Error loading $type data";
        return [];
    }
}

// Get current stock from database
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $currentStock = fetchStockByType($conn, $_GET['type'], $uniformTypes);
} else {
    $currentStock = fetchAllUniformStock($conn, $uniformTypes);
}

// Get customer's orders with uniform type information
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               (SELECT type FROM uniforms WHERE id = o.uniform_id) AS uniform_type
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $customerOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    $customerOrders = [];
    $_SESSION['error'] = "Failed to load order data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Uniform Stock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'amazon-blue': '#146EB4',
                        'amazon-navy': '#131921',
                        'amazon-light': '#232F3E',
                        'amazon-yellow': '#FFD814',
                        'amazon-orange': '#FFA41C',
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-item:hover {
            background-color: #37475A;
            border-left: 4px solid #FFA41C;
        }
        .sidebar-item.active {
            background-color: #37475A;
            border-left: 4px solid #FFA41C;
        }
        .amazon-search:focus {
            box-shadow: 0 0 0 3px rgba(255, 168, 28, 0.5);
        }
        .nav-link:hover {
            color: #FFA41C;
        }
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="bg-amazon-navy text-white">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="customer_dashboard.php" class="text-2xl font-bold text-white flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amazon-yellow" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-2">UniformStock</span>
                </a>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <span class="text-sm text-gray-300">Hello, <?= htmlspecialchars($user_name) ?></span>
                </div>
                <a href="../authentication/logout.php" class="flex items-center text-sm hover:text-amazon-yellow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l3-3a1 1 0 10-1.414-1.414L14 7.586l-2.293-2.293a1 1 0 10-1.414 1.414L12.586 9H7a1 1 0 100 2h5.586l-1.293 1.293z" clip-rule="evenodd" />
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Secondary Navigation -->
    <div class="bg-amazon-light text-white">
        <div class="container mx-auto px-4 py-2 flex items-center space-x-6 overflow-x-auto">
            <a href="customer_dashboard.php" class="text-sm font-medium text-amazon-yellow whitespace-nowrap">🏠 Dashboard</a>
            <a href="order_uniforms.php" class="text-sm nav-link whitespace-nowrap">🛒 Order Uniforms</a>
            <a href="order_status.php" class="text-sm nav-link whitespace-nowrap">📦 My Orders</a>
            <a href="order_history.php" class="text-sm nav-link whitespace-nowrap">📜 Order History</a>
            <a href="customer_settings.php" class="text-sm nav-link whitespace-nowrap">⚙️ Settings</a>
        </div>
    </div>

    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-amazon-navy text-white min-h-screen hidden md:block">
            <div class="p-4">
                <div class="flex items-center space-x-3 p-3 mb-6">
                    <img src="../uploads/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default_avatar.png') ?>" 
                         alt="Customer Avatar" class="w-10 h-10 rounded-full object-cover">
                    <div>
                        <h3 class="font-medium"><?= htmlspecialchars($user_name) ?></h3>
                        <p class="text-xs text-gray-400">Customer</p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="customer_dashboard.php" class="block sidebar-item active py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                    <a href="order_uniforms.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-boxes mr-2"></i> Order Uniforms
                    </a>
                    <a href="order_status.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-clipboard-list mr-2"></i> My Orders
                    </a>
                    <a href="order_history.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-history mr-2"></i> Order History
                    </a>
                    <a href="customer_settings.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-amazon-navy">Welcome, <?= $user_name ?></h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-600"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <strong>Success: </strong> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <strong>Error: </strong> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow product-card">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-boxes text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500">Available Products</p>
                                <h3 class="text-2xl font-bold"><?= count($currentStock) ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow product-card">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-clipboard-check text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500">Completed Orders</p>
                                <h3 class="text-2xl font-bold">
                                    <?= count(array_filter($customerOrders, function($order) { return $order['status'] === 'completed'; })) ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow product-card">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500">Pending Orders</p>
                                <h3 class="text-2xl font-bold">
                                    <?= count(array_filter($customerOrders, function($order) { return $order['status'] === 'pending'; })) ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Catalog Section -->
                <div class="bg-white shadow-md rounded-lg p-6 mb-8 border border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Available Uniform Items</h2>
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search items..." 
                                   class="pl-10 pr-4 py-2 border rounded-lg amazon-search focus:outline-none">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Filter Controls -->
                    <div class="mb-6 flex flex-col md:flex-row md:items-center md:space-x-4 space-y-4 md:space-y-0">
                        <div class="flex-1">
                            <label for="uniformTypeFilter" class="block text-gray-700 mb-2">Filter by Type:</label>
                            <select id="uniformTypeFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue">
                                <option value="">All Types</option>
                                <?php foreach ($uniformTypes as $type => $table): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label for="qualityFilter" class="block text-gray-700 mb-2">Filter by Quality:</label>
                            <select id="qualityFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue">
                                <option value="">All Qualities</option>
                                <?php 
                                $qualities = array_unique(array_column($currentStock, 'quality'));
                                foreach ($qualities as $quality): ?>
                                    <?php if (!empty($quality)): ?>
                                        <option value="<?= htmlspecialchars($quality) ?>"><?= htmlspecialchars($quality) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label for="sizeFilter" class="block text-gray-700 mb-2">Filter by Size:</label>
                            <select id="sizeFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue">
                                <option value="">All Sizes</option>
                                <?php 
                                $sizes = array_unique(array_column($currentStock, 'size'));
                                foreach ($sizes as $size): ?>
                                    <?php if (!empty($size)): ?>
                                        <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Product Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($currentStock)): ?>
                            <div class="col-span-full text-center py-8">
                                <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-500 text-lg">No uniform items available at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($currentStock as $item): ?>
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm product-card">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($item['type']) ?></h3>
                                                <p class="text-sm text-gray-500">ID: <?= htmlspecialchars($item['id']) ?></p>
                                            </div>
                                            <?php if (!empty($item['quality'])): ?>
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    <?= $item['quality'] === 'High' ? 'bg-green-100 text-green-800' : 
                                                       ($item['quality'] === 'Medium' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                                    <?= htmlspecialchars($item['quality']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <p class="text-gray-500">Size</p>
                                                <p><?= htmlspecialchars($item['size'] ?? 'N/A') ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-500">Available</p>
                                                <p><?= htmlspecialchars($item['stock_quantity']) ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-500">Price</p>
                                                <p class="font-semibold">$<?= number_format($item['price'], 2) ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-500">Type</p>
                                                <p><?= htmlspecialchars($item['type']) ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 flex justify-end">
                                            <button onclick="openOrderModal(<?= htmlspecialchars(json_encode($item)) ?>)" 
                                                    class="px-3 py-1 bg-amazon-blue text-white rounded hover:bg-blue-600 transition">
                                                <i class="fas fa-cart-plus mr-1"></i> Order
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Orders Section -->
                <div class="bg-white shadow-md rounded-lg p-6 mb-8 border border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Recent Orders</h2>
                    
                    <?php if (empty($customerOrders)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">You haven't placed any orders yet.</p>
                            <a href="order_uniforms.php" class="mt-2 inline-block text-amazon-blue hover:text-blue-700">
                                Browse our uniform collection
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="py-3 px-4 border-b text-left">Order #</th>
                                        <th class="py-3 px-4 border-b text-left">Date</th>
                                        <th class="py-3 px-4 border-b text-left">Item</th>
                                        <th class="py-3 px-4 border-b text-left">Quantity</th>
                                        <th class="py-3 px-4 border-b text-left">Total</th>
                                        <th class="py-3 px-4 border-b text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($customerOrders, 0, 5) as $order): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b"><?= htmlspecialchars($order['id']) ?></td>
                                            <td class="py-3 px-4 border-b"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                            <td class="py-3 px-4 border-b"><?= htmlspecialchars($order['uniform_type'] ?? 'Unknown') ?></td>
                                            <td class="py-3 px-4 border-b"><?= htmlspecialchars($order['quantity']) ?></td>
                                            <td class="py-3 px-4 border-b">$<?= number_format($order['total_price'], 2) ?></td>
                                            <td class="py-3 px-4 border-b">
                                                <span class="px-2 py-1 rounded-full text-xs 
                                                    <?= $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                       ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="order_history.php" class="text-amazon-blue hover:text-blue-700">View all orders →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Modal -->
    <div id="orderModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Place New Order</h3>
                <form method="POST" action="place_order.php" class="mt-4 space-y-4">
                    <input type="hidden" id="order_item_id" name="item_id">
                    <input type="hidden" id="order_item_type" name="item_type">
                    
                    <div>
                        <label class="block text-gray-700 text-left mb-1">Product:</label>
                        <input type="text" id="order_item_name" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-left mb-1">Size:</label>
                            <input type="text" id="order_item_size" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-left mb-1">Price:</label>
                            <input type="text" id="order_item_price" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-left mb-1">Quality:</label>
                            <input type="text" id="order_item_quality" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-left mb-1">Available:</label>
                            <input type="text" id="order_item_stock" class="w-full px-4 py-2 border rounded-lg bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-left mb-1">Quantity:</label>
                        <input type="number" name="quantity" min="1" value="1" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue"
                               id="order_quantity">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-left mb-1">Delivery Option:</label>
                        <select name="delivery_option" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue">
                            <option value="Pickup">Pickup</option>
                            <option value="Delivery">Delivery</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-left mb-1">School Name:</label>
                        <input type="text" name="school_name" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-amazon-blue">
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('orderModal').classList.add('hidden')" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-amazon-orange text-white rounded-lg hover:bg-yellow-600">
                            <i class="fas fa-check mr-1"></i> Confirm Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Open order modal with item data
        function openOrderModal(item) {
            document.getElementById('order_item_id').value = item.id;
            document.getElementById('order_item_type').value = item.type;
            document.getElementById('order_item_name').value = item.type + (item.size ? ' (' + item.size + ')' : '');
            document.getElementById('order_item_size').value = item.size || 'N/A';
            document.getElementById('order_item_price').value = '$' + parseFloat(item.price).toFixed(2);
            document.getElementById('order_item_quality').value = item.quality || 'Standard';
            document.getElementById('order_item_stock').value = item.stock_quantity;
            document.getElementById('order_quantity').setAttribute('max', item.stock_quantity);
            document.getElementById('orderModal').classList.remove('hidden');
        }

        // Filter functionality
        function applyFilters() {
            const typeFilter = document.getElementById('uniformTypeFilter').value.toLowerCase();
            const qualityFilter = document.getElementById('qualityFilter').value.toLowerCase();
            const sizeFilter = document.getElementById('sizeFilter').value.toLowerCase();
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            const items = document.querySelectorAll('.product-card');
            items.forEach(item => {
                const type = item.querySelector('h3').textContent.toLowerCase();
                const quality = item.querySelector('span') ? item.querySelector('span').textContent.toLowerCase() : '';
                const size = item.querySelectorAll('div > p')[1].textContent.toLowerCase(); // Size is in the second p tag
                const text = item.textContent.toLowerCase();
                
                const typeMatch = !typeFilter || type === typeFilter.toLowerCase();
                const qualityMatch = !qualityFilter || quality === qualityFilter.toLowerCase();
                const sizeMatch = !sizeFilter || size === sizeFilter.toLowerCase();
                const searchMatch = !searchTerm || text.includes(searchTerm);
                
                item.style.display = typeMatch && qualityMatch && sizeMatch && searchMatch ? '' : 'none';
            });
        }

        // Initialize event listeners for filters
        document.getElementById('uniformTypeFilter').addEventListener('change', applyFilters);
        document.getElementById('qualityFilter').addEventListener('change', applyFilters);
        document.getElementById('sizeFilter').addEventListener('change', applyFilters);
        document.getElementById('searchInput').addEventListener('input', applyFilters);

        // Initialize any other needed functionality
        $(document).ready(function() {
            // You can add additional jQuery functionality here
        });
    </script>
</body>
</html>