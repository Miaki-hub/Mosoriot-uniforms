<?php
// Start session and check authentication
session_start();

// Database connection
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Get current user info from database to verify admin status
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, name, username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify user exists and is admin
if (!$user || $user['role'] !== 'admin') {
    header("Location: ../authentication/login.php");
    exit();
}

$user_name = $user['name'] ?? $user['username'] ?? 'Admin';

// Define uniform types and their tables
$uniformTables = [
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

// Initialize POS cart if not exists
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

// Function to generate barcode
function generateBarcode($prefix = 'UNI') {
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

// Function to check if table has column
function tableHasColumn($conn, $table, $column) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking for column: " . $e->getMessage());
        return false;
    }
}

// Function to get all schools
function getSchools($conn) {
    $stmt = $conn->prepare("SELECT id, name FROM schools ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get stock data
function getStockData($conn, $type = null) {
    global $uniformTables;
    
    if ($type && isset($uniformTables[$type])) {
        $table = $uniformTables[$type];
        $hasBarcode = tableHasColumn($conn, $table, 'barcode');
        $barcodeField = $hasBarcode ? "barcode" : "NULL as barcode";
        
        $query = "SELECT id, size, color, quantity, price, school_id, 
                 $barcodeField, '$type' as type 
                 FROM $table ORDER BY color, size";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $queries = [];
        foreach ($uniformTables as $type => $table) {
            $hasBarcode = tableHasColumn($conn, $table, 'barcode');
            $barcodeField = $hasBarcode ? "barcode" : "NULL as barcode";
            
            $queries[] = "SELECT id, size, color, quantity, price, school_id, 
                         $barcodeField, '$type' as type 
                         FROM $table";
        }
        
        $fullQuery = implode(" UNION ", $queries) . " ORDER BY type, color";
        $stmt = $conn->prepare($fullQuery);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Function to get analytics data
function getAnalyticsData($conn) {
    $salesQuery = "SELECT DATE(order_date) as date, COUNT(*) as transactions, 
                  SUM(total_price) as revenue FROM orders 
                  WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY DATE(order_date) ORDER BY date";
    $salesStmt = $conn->prepare($salesQuery);
    $salesStmt->execute();
    $salesData = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lowStock = [];
    foreach ($GLOBALS['uniformTables'] as $type => $table) {
        $query = "SELECT id, size, color, quantity FROM $table WHERE quantity < 10 ORDER BY quantity LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $item) {
            $item['type'] = $type;
            if (tableHasColumn($conn, $table, 'barcode')) {
                $barcodeStmt = $conn->prepare("SELECT barcode FROM $table WHERE id = ?");
                $barcodeStmt->execute([$item['id']]);
                $item['barcode'] = $barcodeStmt->fetchColumn();
            } else {
                $item['barcode'] = null;
            }
            $lowStock[] = $item;
        }
    }
    
    $distribution = [];
    foreach ($GLOBALS['uniformTables'] as $type => $table) {
        $query = "SELECT COUNT(*) as count FROM $table WHERE quantity > 0";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $distribution[] = ['type' => $type, 'count' => $count];
        }
    }
    
    return [
        'sales' => $salesData,
        'low_stock' => $lowStock,
        'distribution' => $distribution
    ];
}

// Get data from database
$schools = getSchools($conn);
$currentStock = getStockData($conn, $_GET['type'] ?? null);
$analytics = getAnalyticsData($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $type = $_POST['type'];
        $size = $_POST['size'];
        $color = $_POST['color'];
        $quantity = (int)$_POST['quantity'];
        $price = (float)$_POST['price'];
        $school_id = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
        $barcode = $_POST['barcode'] ?: generateBarcode(substr($type, 0, 3));
        
        if (!empty($type) && isset($uniformTables[$type])) {
            $table = $uniformTables[$type];
            $hasBarcode = tableHasColumn($conn, $table, 'barcode');
            
            // First, insert into uniforms table to get the uniform_id
            $uniformQuery = "INSERT INTO uniforms (type, color, school_id) VALUES (?, ?, ?)";
            $uniformStmt = $conn->prepare($uniformQuery);
            $uniformStmt->execute([$type, $color, $school_id]);
            $uniform_id = $conn->lastInsertId();
            
            if ($hasBarcode) {
                $query = "INSERT INTO $table (uniform_id, size, quality, quantity, price, barcode) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([$uniform_id, $size, 'High', $quantity, $price, $barcode]);
            } else {
                $query = "INSERT INTO $table (uniform_id, size, quality, quantity, price) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([$uniform_id, $size, 'High', $quantity, $price]);
            }
            
            if ($success) {
                $_SESSION['success'] = "Item added successfully!" . ($hasBarcode ? " Barcode: $barcode" : "");
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $_SESSION['error'] = "Error adding item: " . $stmt->errorInfo()[2];
            }
        }
    }
    
    if (isset($_POST['edit_item'])) {
        $id = (int)$_POST['id'];
        $type = $_POST['type'];
        $size = $_POST['size'];
        $color = $_POST['color'];
        $quantity = (int)$_POST['quantity'];
        $price = (float)$_POST['price'];
        $school_id = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
        $barcode = $_POST['barcode'];
        
        if (!empty($type) && isset($uniformTables[$type])) {
            $table = $uniformTables[$type];
            $hasBarcode = tableHasColumn($conn, $table, 'barcode');
            
            // First get the uniform_id for this item
            $getUniformQuery = "SELECT uniform_id FROM $table WHERE id = ?";
            $getUniformStmt = $conn->prepare($getUniformQuery);
            $getUniformStmt->execute([$id]);
            $uniform_id = $getUniformStmt->fetchColumn();
            
            // Update the uniforms table
            $uniformUpdateQuery = "UPDATE uniforms SET type = ?, color = ?, school_id = ? WHERE id = ?";
            $uniformStmt = $conn->prepare($uniformUpdateQuery);
            $uniformStmt->execute([$type, $color, $school_id, $uniform_id]);
            
            if ($hasBarcode) {
                $query = "UPDATE $table SET size = ?, quantity = ?, price = ?, barcode = ? 
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([$size, $quantity, $price, $barcode, $id]);
            } else {
                $query = "UPDATE $table SET size = ?, quantity = ?, price = ? 
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([$size, $quantity, $price, $id]);
            }
            
            if ($success) {
                $_SESSION['success'] = "Item updated successfully!";
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $_SESSION['error'] = "Error updating item: " . $stmt->errorInfo()[2];
            }
        }
    }
    
    // Rest of your existing code for cart and checkout...
        if (isset($_POST['add_to_cart'])) {
        $item_id = $_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Get item details from database
        $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            if (!isset($_SESSION['pos_cart'][$item_id])) {
                $_SESSION['pos_cart'][$item_id] = [
                    'type' => $item['type'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'price' => $item['price'],
                    'quantity' => $quantity,
                    'barcode' => $item['barcode'] ?? null
                ];
            } else {
                $_SESSION['pos_cart'][$item_id]['quantity'] += $quantity;
            }
            
            $_SESSION['success'] = "Item added to cart!";
            header("Location: admin_dashboard.php");
            exit();
        }
    }
    
    if (isset($_POST['remove_from_cart'])) {
        $item_id = $_POST['item_id'];
        if (isset($_SESSION['pos_cart'][$item_id])) {
            unset($_SESSION['pos_cart'][$item_id]);
            $_SESSION['success'] = "Item removed from cart!";
            header("Location: admin_dashboard.php");
            exit();
        }
    }
    
    if (isset($_POST['checkout'])) {
        if (!empty($_SESSION['pos_cart'])) {
            $user_id = $_SESSION['user_id'];
            $total = 0;
            
            // Create order
            $orderStmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, 'completed')");
            
            foreach ($_SESSION['pos_cart'] as $item_id => $item) {
                $total += $item['price'] * $item['quantity'];
                
                // Update inventory
                $updateStmt = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ?");
                $updateStmt->execute([$item['quantity'], $item_id]);
            }
            
            $orderStmt->execute([$user_id, $total]);
            $order_id = $conn->lastInsertId();
            
            // Create order items
            foreach ($_SESSION['pos_cart'] as $item_id => $item) {
    $stmt = $conn->prepare("INSERT INTO orders (user_id, uniform_id, quantity, total_price, delivery_option, school_name, status, sale_date, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE())");
    
    $user_id = $_SESSION['user_id']; // Ensure this is set
    $uniform_id = $item_id; // Assuming $item_id is the actual uniform_id
    $quantity = $item['quantity'];
    $price = $item['price'];
    $total_price = $quantity * $price;
    $delivery_option = $_POST['delivery_option'] ?? 'Pickup';
    function getSchoolNameFromId($school_id, $conn) {
    if (!$school_id) return '';
    $stmt = $conn->prepare("SELECT name FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return '';
}

    $school_name = getSchoolNameFromId($_POST['school_id'] ?? null, $conn);
    $status = 'pending';

    $stmt->execute([
        $user_id,
        $uniform_id,
        $quantity,
        $total_price,
        $delivery_option,
        $school_name,
        $status
    ]);
}

            
            // Generate receipt
            $receipt = "Order #$order_id\n";
            $receipt .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
            $receipt .= "Items:\n";
            
            foreach ($_SESSION['pos_cart'] as $item) {
                $receipt .= sprintf("%-20s %3d x %8.2f = %8.2f\n", 
                    $item['type'], 
                    $item['quantity'], 
                    $item['price'], 
                    $item['quantity'] * $item['price']);
            }
            
            $receipt .= "\nTotal: " . number_format($total, 2) . "\n";
            $receipt .= "Thank you for your business!";
            
            $_SESSION['last_receipt'] = $receipt;
            $_SESSION['pos_cart'] = [];
            $_SESSION['success'] = "Checkout completed successfully!";
            header("Location: admin_dashboard.php");
            exit();
        }
    }
    
    if (isset($_POST['scan_barcode'])) {
        $barcode = $_POST['barcode'];
        if (!empty($barcode)) {
            // Search for item with this barcode
            $found = false;
            foreach ($uniformTables as $type => $table) {
                if (tableHasColumn($conn, $table, 'barcode')) {
                    $stmt = $conn->prepare("SELECT * FROM $table WHERE barcode = ? LIMIT 1");
                    $stmt->execute([$barcode]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item) {
                        $item['type'] = $type;
                        $_SESSION['success'] = "Item found: " . $type;
                        // You could automatically add to cart or show details
                        header("Location: admin_dashboard.php");
                        exit();
                    }
                }
            }
            
            if (!$found) {
                $_SESSION['error'] = "No item found with barcode: $barcode";
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }
}




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Uniform Stock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <style>
        :root {
            --amazon-orange: #FF9900;
            --amazon-dark: #131921;
            --amazon-light: #232F3E;
            --amazon-gray: #EAEDED;
        }
        
        body {
            font-family: 'Amazon Ember', Arial, sans-serif;
            background-color: var(--amazon-gray);
        }
        
        .amazon-header {
            background-color: var(--amazon-dark);
            height: 60px;
        }
        
        .amazon-nav {
            background-color: var(--amazon-light);
            height: 40px;
        }
        
        .amazon-orange-btn {
            background-color: var(--amazon-orange);
            border-color: #a88734 #9c7e31 #846a29;
        }
        
        .amazon-orange-btn:hover {
            background-color: #f0c14b;
        }
        
        .amazon-card {
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #DDD;
        }
        
        .amazon-search {
            background: white;
            border-radius: 4px;
            border: 2px solid var(--amazon-orange);
        }
        
        .amazon-search:focus {
            box-shadow: 0 0 0 3px rgba(255,153,0,0.3);
        }
        
        .sidebar-item:hover {
            background-color: #f3f3f3;
            border-left: 3px solid var(--amazon-orange);
        }
        
        .sidebar-item.active {
            background-color: #f3f3f3;
            border-left: 3px solid var(--amazon-orange);
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link:hover:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--amazon-orange);
        }
        
        .chart-container {
            background: white;
            border-radius: 4px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        #scannerModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        #scannerContainer {
            position: relative;
            width: 80%;
            max-width: 600px;
            margin: 5% auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        #interactive {
            width: 100%;
            height: 300px;
            background: black;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .hover-scale {
            transition: transform 0.2s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
        }
        
        .barcode-display {
            font-family: 'Libre Barcode 39', cursive;
            font-size: 24px;
            letter-spacing: 2px;
        }
        
        .no-barcode {
            color: #999;
            font-style: italic;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Amazon-style Header -->
    <header class="amazon-header text-white shadow-sm">
        <div class="container mx-auto px-4 h-full flex items-center">
            <div class="flex items-center space-x-4">
                <a href="admin_dashboard.php" class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-tshirt mr-2"></i>
                    <span>UniformStock PRO</span>
                </a>
            </div>
            
            <div class="ml-auto flex items-center space-x-6">
                <div class="text-sm">
                    <span class="text-gray-300">Welcome, <?= htmlspecialchars($user_name) ?></span>
                </div>
                <a href="../authentication/logout.php" class="text-sm hover:text-gray-300">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Secondary Navigation -->
    <nav class="amazon-nav text-white">
        <div class="container mx-auto px-4 h-full flex items-center space-x-6 overflow-x-auto">
            <a href="admin_dashboard.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
            <a href="manage_users.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-users mr-1"></i> Users</a>
            <a href="manage_orders.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-shopping-bag mr-1"></i> Orders</a>
            <a href="manage_stock.php" class="text-sm font-medium text-yellow-300 whitespace-nowrap"><i class="fas fa-boxes mr-1"></i> Inventory</a>
            <a href="sales_reports.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-chart-line mr-1"></i> Reports</a>
            <a href="customer_feedback.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-comments mr-1"></i> Feedback</a>
            <a href="admin_settings.php" class="text-sm nav-link whitespace-nowrap"><i class="fas fa-cog mr-1"></i> Settings</a>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>Success: </strong> <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <strong>Error: </strong> <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['last_receipt'])): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-receipt mr-2"></i>
                        <strong>Last Receipt Generated</strong>
                    </div>
                    <div class="flex space-x-2">
                        <a href="download_receipt.php" class="text-blue-700 hover:text-blue-900 text-sm font-medium">
                            <i class="fas fa-download mr-1"></i> Download
                        </a>
                        <button onclick="printReceipt()" class="text-blue-700 hover:text-blue-900 text-sm font-medium">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6 fade-in">
            <h1 class="text-2xl font-bold text-gray-800"> Uniforms Inventory Management</h1>
            <div class="flex items-center space-x-4">
                <div class="relative amazon-search">
                    <input type="text" placeholder="Search items..." class="w-full px-4 py-2 rounded-md focus:outline-none">
                    <button class="absolute right-0 top-0 h-full px-3 text-gray-500">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <button onclick="openCartModal()" class="amazon-orange-btn hover-scale text-white font-semibold py-2 px-4 rounded-md flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    POS Cart <span class="ml-2 bg-white text-orange-600 rounded-full px-2 py-1 text-xs"><?= count($_SESSION['pos_cart']) ?></span>
                </button>
                <button onclick="window.open('barcode.php','_blank','width=800,height=600')" 
        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md flex items-center">
    <i class="fas fa-barcode mr-2"></i>
    Scan Barcode
</button>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 fade-in">
            <!-- Stock Summary -->
            <div class="amazon-card hover-scale p-6">
                <div class="flex items-center justify-between mb-4 p-3 rounded-lg bg-white shadow-sm hover:bg-gray-50 cursor-pointer transition-colors duration-200" onclick="window.location.href='uniforms.php'">
    <h3 class="text-lg font-semibold">Total Items</h3>
    <div class="bg-blue-100 p-3 rounded-full">
        <i class="fas fa-boxes text-blue-600 text-xl"></i>
    </div>
</div>
                </div>
                <p class="text-3xl font-bold"><?= count($currentStock) ?></p>
                <p class="text-sm text-gray-600 mt-2">Items in inventory</p>
            </div>
            
            <!-- Low Stock -->
            <div class="amazon-card hover-scale p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Low Stock</h3>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold"><?= count($analytics['low_stock']) ?></p>
                <p class="text-sm text-red-600 mt-2">Items need restocking</p>
            </div>
            
            <!-- Recent Sales -->
            <div class="amazon-card hover-scale p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Today's Revenue</h3>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold">KSh 
                    <?php 
                    $todayRevenue = 0;
                    foreach ($analytics['sales'] as $sale) {
                        if (date('Y-m-d') === $sale['date']) {
                            $todayRevenue += $sale['revenue'];
                        }
                    }
                    echo number_format($todayRevenue, 2);
                    ?>
                </p>
                <p class="text-sm text-gray-600 mt-2">Today's sales</p>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 fade-in">
            <!-- Left Sidebar -->
            <div class="lg:col-span-1">
                <div class="amazon-card p-4 mb-6">
                    <h3 class="font-semibold mb-4 text-lg border-b pb-2">Quick Actions</h3>
                    <ul class="space-y-2">
                        <li>
                            <button onclick="openAddModal()" class="w-full text-left flex items-center p-2 hover:bg-gray-100 rounded">
                                <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                                Add New Item
                            </button>
                        </li>
                        <li>
                            <a href="bulk_import.php" class="flex items-center p-2 hover:bg-gray-100 rounded">
                                <i class="fas fa-file-import text-blue-600 mr-2"></i>
                                Bulk Import
                            </a>
                        </li>
                        <li>
                            <a href="sales_reports.php" class="flex items-center p-2 hover:bg-gray-100 rounded">
                                <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                                View Reports
                            </a>
                        </li>
                        <li>
                            <button onclick="generateBarcodeLabels()" class="w-full text-left flex items-center p-2 hover:bg-gray-100 rounded">
                                <i class="fas fa-barcode text-red-600 mr-2"></i>
                                Print Barcode Labels
                            </button>
                        </li>
                    </ul>
                </div>
                
                <?php if (!empty($analytics['low_stock'])): ?>
                <div class="amazon-card p-4">
                    <h3 class="font-semibold mb-4 text-lg border-b pb-2">Stock Alerts</h3>
                    <div class="space-y-3">
                        <?php foreach ($analytics['low_stock'] as $item): ?>
                        <div class="flex items-start p-2 bg-red-50 rounded">
                            <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-2"></i>
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($item['type']) ?> (<?= htmlspecialchars($item['color']) ?>, <?= htmlspecialchars($item['size']) ?>)</p>
                                <p class="text-sm text-gray-600">Only <?= $item['quantity'] ?> left in stock</p>
                                <?php if ($item['barcode']): ?>
                                    <p class="text-xs font-mono">Barcode: <?= htmlspecialchars($item['barcode']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="amazon-card p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">Recent Sales</h2>
                        <select class="border rounded px-3 py-1">
                            <option>Last 7 days</option>
                            <option>Last 30 days</option>
                        </select>
                    </div>
                    
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="amazon-card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">Inventory Items</h2>
                        <div class="flex space-x-2">
                            <select id="uniformType" class="border rounded px-3 py-1" onchange="filterByType()">
                                <option value="">All Categories</option>
                                <?php foreach (array_keys($uniformTables) as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= isset($_GET['type']) && $_GET['type'] === $type ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($currentStock)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No items found in inventory.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($currentStock as $item): ?>
                                        <?php 
                                        $school_name = 'N/A';
                                        if (!empty($item['school_id'])) {
                                            foreach ($schools as $school) {
                                                if ($school['id'] == $item['school_id']) {
                                                    $school_name = htmlspecialchars($school['name']);
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-tshirt text-blue-600"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['type'] ?? $item['item_type'] ?? 'Unknown') ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($school_name) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($item['barcode'])): ?>
                                                    <div class="text-sm font-mono"><?= htmlspecialchars($item['barcode']) ?></div>
                                                    <div class="barcode-display">*<?= htmlspecialchars($item['barcode']) ?>*</div>
                                                <?php else: ?>
                                                    <div class="no-barcode text-sm">No barcode</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['size'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['color'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= ($item['quantity'] < 10) ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                                    <?= $item['quantity'] ?> in stock
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KSh <?= number_format($item['price'] ?? 0, 2) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openEditModal(
                                                    <?= htmlspecialchars(json_encode([
                                                        'id' => $item['id'],
                                                        'type' => $item['type'] ?? $item['item_type'] ?? '',
                                                        'size' => $item['size'] ?? '',
                                                        'color' => $item['color'] ?? '',
                                                        'quantity' => $item['quantity'],
                                                        'price' => $item['price'] ?? 0,
                                                        'school_id' => $item['school_id'] ?? null,
                                                        'barcode' => $item['barcode'] ?? ''
                                                    ])) ?>
                                                )" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                                <button onclick="openPosModal(
                                                    <?= htmlspecialchars(json_encode([
                                                        'id' => $item['id'],
                                                        'type' => $item['type'] ?? $item['item_type'] ?? '',
                                                        'size' => $item['size'] ?? '',
                                                        'color' => $item['color'] ?? '',
                                                        'quantity' => $item['quantity'],
                                                        'price' => $item['price'] ?? 0,
                                                        'barcode' => $item['barcode'] ?? ''
                                                    ])) ?>
                                                )" class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-cash-register mr-1"></i> Sell
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Item</h3>
                <form method="POST" class="mt-2">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Item Type:</label>
                            <select name="type" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                                <option value="">-- Select --</option>
                                <?php foreach (array_keys($uniformTables) as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Size:</label>
                            <input type="text" name="size" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color:</label>
                            <input type="text" name="color" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity:</label>
                            <input type="number" name="quantity" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price:</label>
                            <input type="number" step="0.01" name="price" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode:</label>
                            <div class="flex">
                                <input type="text" name="barcode" id="newBarcode" class="w-full px-4 py-2 border border-gray-300 rounded-l-md focus:ring-primary focus:border-primary">
                                <button type="button" onclick="generateNewBarcode()" class="bg-gray-200 hover:bg-gray-300 px-3 rounded-r-md">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School (optional):</label>
                            <select name="school_id" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                                <option value="">-- None --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="submit" name="add_item" class="amazon-orange-btn hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-md flex items-center">
                            <i class="fas fa-plus mr-2"></i> Add Item
                        </button>
                        <button type="button" onclick="closeAddModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Item</h3>
                <form method="POST" class="mt-2">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" id="edit_item_type" name="type">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Item Type:</label>
                            <select id="edit_type" disabled class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100">
                                <!-- Dynamically filled by JavaScript -->
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Size:</label>
                            <input type="text" id="edit_size" name="size" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color:</label>
                            <input type="text" id="edit_color" name="color" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity:</label>
                            <input type="number" id="edit_quantity" name="quantity" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price:</label>
                            <input type="number" step="0.01" id="edit_price" name="price" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode:</label>
                            <input type="text" id="edit_barcode" name="barcode" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School (optional):</label>
                            <select id="edit_school" name="school_id" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                                <option value="">-- None --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="submit" name="edit_item" class="amazon-orange-btn hover:bg-orange-600 text-white font-semibold py-2 px-6 rounded-md flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeEditModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    `<!-- POS Add to Cart Modal -->
    <div id="posModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Add to POS Cart</h3>
                <form method="POST" class="mt-2">
                    <input type="hidden" id="pos_item_id" name="item_id">
                    <input type="hidden" id="pos_item_type" name="item_type">
                    <div class="space-y-4">
                        <div class="text-left">
                            <p class="text-sm font-medium text-gray-700">Item:</p>
                            <p id="pos_item_name" class="text-lg font-semibold"></p>
                        </div>
                        <div class="text-left">
                            <div id="barcode-display-container">
                                <p class="text-sm font-mono">Barcode: <span id="pos_item_barcode"></span></p>
                                <div class="barcode-display">*<span id="pos_item_barcode_display"></span>*</div>
                            </div>
                        </div>
                        <div class="text-left">
                            <p id="pos_item_stock" class="text-sm text-gray-600"></p>
                        </div>
                        <div class="text-left">
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity:</label>
                            <input type="number" name="quantity" min="1" value="1" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="submit" name="add_to_cart" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md flex items-center">
                            <i class="fas fa-cart-plus mr-2"></i> Add to Cart
                        </button>
                        <button type="button" onclick="closePosModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                           ` Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- POS Cart Modal -->
    <div id="cartModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">POS Cart</h3>
                    <button onclick="closeCartModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <?php if (empty($_SESSION['pos_cart'])): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mx-auto text-gray-400 mb-4"></i>
                        <p class="mt-2 text-gray-500">Your cart is empty</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $cartTotal = 0;
                                foreach ($_SESSION['pos_cart'] as $item_id => $item): 
                                    $subtotal = $item['price'] * $item['quantity'];
                                    $cartTotal += $subtotal;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['type']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($item['barcode'])): ?>
                                                <div class="text-sm font-mono"><?= htmlspecialchars($item['barcode']) ?></div>
                                                <div class="barcode-display">*<?= htmlspecialchars($item['barcode']) ?>*</div>
                                            <?php else: ?>
                                                <div class="no-barcode text-sm">No barcode</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['color']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['size']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $item['quantity'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KSh <?= number_format($item['price'], 2) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KSh <?= number_format($subtotal, 2) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                                <button type="submit" name="remove_from_cart" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">Total:</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">KSh <?= number_format($cartTotal, 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-4">
                        <button onclick="closeCartModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-md">
                            Continue Shopping
                        </button>
                        <form method="POST">
                            <button type="submit" name="checkout" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md flex items-center">
                                <i class="fas fa-check-circle mr-2"></i> Complete Sale
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Barcode Scanner Modal - Updated Version -->
<div id="scannerModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Barcode Scanner</h3>
                <button onclick="closeScannerModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="scannerContainer">
                <div id="interactive" class="viewport w-full h-64 bg-black relative">
                    <video id="video" class="w-full h-full object-cover" playsinline></video>
                    <canvas id="canvas" class="hidden"></canvas>
                </div>
                <div class="mt-4 flex justify-center">
                    <button onclick="window.open('barcode.php','_blank','width=800,height=600')" 
        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md flex items-center">
    <i class="fas fa-barcode mr-2"></i>
    Scan Barcode
</button>
                    <button id="stopScannerBtn" onclick="stopScanner()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md hidden">
                        <i class="fas fa-stop mr-2"></i> Stop Scanner
                    </button>
                </div>
                <div class="mt-4">
                    <form method="POST" class="flex">
                        <input type="text" name="barcode" id="scannedBarcode" placeholder="Or enter barcode manually" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-l-md focus:ring-primary focus:border-primary">
                        <button type="submit" name="scan_barcode" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-r-md">
                            <i class="fas fa-search mr-2"></i> Search
                        </button>
                    </form>
                </div>
                <div id="scannerError" class="mt-2 text-red-600 text-sm hidden"></div>
            </div>
        </div>
    </div>
</div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesData = <?= json_encode($analytics['sales']) ?>;
            
            const dates = salesData.map(item => item.date);
            const revenues = salesData.map(item => parseFloat(item.revenue));
            
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Daily Revenue (KSh)',
                        data: revenues,
                        backgroundColor: 'rgba(255, 153, 0, 0.1)',
                        borderColor: '#FF9900',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KSh ' + value;
                                }
                            }
                        }
                    }
                }
            });
            
            
            // Add fade-in effect to elements
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.style.opacity = '1';
                }, 100 * index);
            });
        });

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            generateNewBarcode();
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function openEditModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_item_type').value = item.type;
            
            // Set the type dropdown
            const typeSelect = document.getElementById('edit_type');
            typeSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = item.type;
            option.textContent = item.type;
            option.selected = true;
            typeSelect.appendChild(option);
            
            // Set other fields
            document.getElementById('edit_size').value = item.size;
            document.getElementById('edit_color').value = item.color;
            document.getElementById('edit_quantity').value = item.quantity;
            document.getElementById('edit_price').value = item.price;
            document.getElementById('edit_barcode').value = item.barcode || '';
            document.getElementById('edit_school').value = item.school_id || '';
            
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openPosModal(item) {
            document.getElementById('pos_item_id').value = item.id;
            document.getElementById('pos_item_type').value = item.type;
            document.getElementById('pos_item_name').textContent = `${item.type} (${item.color}, ${item.size})`;
            
            const barcodeSpan = document.getElementById('pos_item_barcode');
            const barcodeDisplay = document.getElementById('pos_item_barcode_display');
            const barcodeContainer = document.getElementById('barcode-display-container');
            
            if (item.barcode) {
                barcodeSpan.textContent = item.barcode;
                barcodeDisplay.textContent = item.barcode;
                barcodeContainer.style.display = 'block';
            } else {
                barcodeContainer.style.display = 'none';
            }
            
            document.getElementById('pos_item_stock').textContent = `Available: ${item.quantity}`;
            document.getElementById('posModal').classList.remove('hidden');
        }

        function closePosModal() {
            document.getElementById('posModal').classList.add('hidden');
        }

        function openCartModal() {
            document.getElementById('cartModal').classList.remove('hidden');
        }

        function closeCartModal() {
            document.getElementById('cartModal').classList.add('hidden');
        }

        function openScannerModal() {
            document.getElementById('scannerModal').classList.remove('hidden');
        }

        function closeScannerModal() {
            document.getElementById('scannerModal').classList.add('hidden');
            stopScanner();
        }

        function filterByType() {
            const type = document.getElementById('uniformType').value;
            if (type) {
                window.location.href = `admin_dashboard.php?type=${encodeURIComponent(type)}`;
            } else {
                window.location.href = 'admin_dashboard.php';
            }
        }

        function printReceipt() {
            const receiptContent = `<?= isset($_SESSION['last_receipt']) ? addslashes($_SESSION['last_receipt']) : '' ?>`;
            if (!receiptContent) {
                alert('No receipt available to print');
                return;
            }
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<pre>' + receiptContent + '</pre>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }

        function generateNewBarcode() {
            const typeSelect = document.querySelector('#addModal select[name="type"]');
            const prefix = typeSelect.value ? typeSelect.value.substring(0, 3).toUpperCase() : 'UNI';
            document.getElementById('newBarcode').value = prefix + Math.random().toString(36).substring(2, 12).toUpperCase();
        }

        function generateBarcodeLabels() {
            window.open('generate_barcode_labels.php', '_blank');
        }

        // Barcode scanner functions
        let scannerActive = false;

        function startScanner() {
            if (scannerActive) return;
            
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#interactive'),
                    constraints: {
                        width: 480,
                        height: 320,
                        facingMode: "environment"
                    },
                },
                decoder: {
                    readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "code_39_vin_reader", "codabar_reader", "upc_reader", "upc_e_reader", "i2of5_reader"],
                    debug: {
                        showCanvas: true,
                        showPatches: true,
                        showFoundPatches: true,
                        showSkeleton: true,
                        showLabels: true,
                        showPatchLabels: true,
                        showRemainingPatchLabels: true,
                        boxFromPatches: {
                            showTransformed: true,
                            showTransformedBox: true,
                            showBB: true
                        }
                    }
                },
                locate: true
            }, function(err) {
                if (err) {
                    console.error(err);
                    alert('Error initializing scanner: ' + err);
                    return;
                }
                scannerActive = true;
                Quagga.start();
            });

            Quagga.onDetected(function(result) {
                const code = result.codeResult.code;
                document.getElementById('scannedBarcode').value = code;
                stopScanner();
                document.querySelector('#scannerModal form').submit();
            });
        }

        function stopScanner() {
            if (scannerActive) {
                Quagga.stop();
                scannerActive = false;
            }
        }
    </script>
</body>
</html>