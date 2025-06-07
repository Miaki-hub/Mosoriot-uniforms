<?php
// Start a secure session
session_start();
session_regenerate_id(true);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require '../config/db_connection.php';

// Initialize all variables at the top
$error_message = "";
$success_message = "";
$items = [];
$uniform_types = [
    'sweaters', 'shirts', 'trousers', 'skirts', 'shorts', 
    'socks', 'jampers', 'tracksuits', 'tshirts', 'gameshorts', 'wrapskirts'
];

// Redirect if the user is not logged in or not an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

// Get current page name for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Navigation configuration
$nav_items = [
    'admin_dashboard.php' => [
        'title' => 'Dashboard',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>'
    ],
    'manage_users.php' => [
        'title' => 'Users',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>'
    ],
    'manage_orders.php' => [
        'title' => 'Orders',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>'
    ],
    'manage_stock.php' => [
        'title' => 'Inventory',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>'
    ],
    'customer_feedback.php' => [
        'title' => 'Feedback',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>'
    ],
    'admin_settings.php' => [
        'title' => 'Settings',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>'
    ]
];

// Handle form submission for adding/updating items
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["item_id"] ?? null;
    $type = trim($_POST["type"] ?? "");
    $size = trim($_POST["size"] ?? "");
    $quality = trim($_POST["quality"] ?? "");
    $quantity = (int) ($_POST["quantity"] ?? 0);
    $price = (float) ($_POST["price"] ?? 0.0);
    $color = trim($_POST["color"] ?? "");
    $school_id = (int) ($_POST["school_id"] ?? 0);

    // Validate input fields
    if (empty($type) || empty($size) || empty($quality) || $quantity <= 0 || $price <= 0) {
        $error_message = "All fields are required, and quantity/price must be greater than zero.";
    } else {
        try {
            $table_name = strtolower($type) . 's'; // Convert type to table name (e.g., "Sweater" -> "sweaters")
            
            if ($id) {
                // Update existing item
                $stmt = $conn->prepare("UPDATE $table_name SET size=?, quality=?, quantity=?, price=?, color=?, school_id=? WHERE id=?");
                $stmt->execute([$size, $quality, $quantity, $price, $color, $school_id, $id]);
                $success_message = "Item updated successfully!";
            } else {
                // Add new item - first create a uniform record
                $stmt = $conn->prepare("INSERT INTO uniforms (type, color) VALUES (?, ?)");
                $stmt->execute([$type, $color]);
                $uniform_id = $conn->lastInsertId();
                
                // Then add to the specific uniform table
                $stmt = $conn->prepare("INSERT INTO $table_name (uniform_id, size, quality, quantity, price, color, school_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$uniform_id, $size, $quality, $quantity, $price, $color, $school_id]);
                $success_message = "Item added successfully!";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle item deletion
if (isset($_GET["delete"])) {
    $parts = explode(':', $_GET["delete"]);
    if (count($parts) === 2) {
        $id = (int) $parts[0];
        $type = $parts[1];
        
        if ($id > 0 && in_array($type, $uniform_types)) {
            try {
                // First delete from the specific uniform table
                $stmt = $conn->prepare("DELETE FROM $type WHERE id=?");
                $stmt->execute([$id]);
                
                $success_message = "Item deleted successfully!";
            } catch (PDOException $e) {
                $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error_message = "Invalid item ID or type.";
        }
    } else {
        $error_message = "Invalid delete parameter.";
    }
}

// Fetch all items from all uniform tables
try {
    $items = [];
    foreach ($uniform_types as $table) {
        $stmt = $conn->prepare("
            SELECT u.id, u.type, t.size, t.quality, t.quantity, t.price, t.color, t.school_id, s.name as school_name 
            FROM $table t
            JOIN uniforms u ON t.uniform_id = u.id
            LEFT JOIN schools s ON t.school_id = s.id
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $item) {
            $item['table'] = $table; // Store which table this item came from
            $items[] = $item;
        }
    }
} catch (PDOException $e) {
    $error_message = "Error fetching items: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        function editItem(id, type, size, quality, quantity, price, color, school_id) {
            document.getElementById("item_id").value = id;
            document.getElementById("type").value = type;
            document.getElementById("size").value = size;
            document.getElementById("quality").value = quality;
            document.getElementById("quantity").value = quantity;
            document.getElementById("price").value = price;
            document.getElementById("color").value = color;
            document.getElementById("school_id").value = school_id;
            document.getElementById("itemForm").scrollIntoView({ behavior: "smooth" });
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
        .nav-link {
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: #FFA41C;
        }
        .nav-link.active {
            color: #FFD814;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="bg-amazon-navy text-white">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="../admin/admin_dashboard.php" class="text-2xl font-bold text-white flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amazon-yellow" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-2">AdminPanel</span>
                </a>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <span class="text-sm text-gray-300">Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
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
            <a href="../ai/Predictions_AI.php" class="text-sm nav-link whitespace-nowrap">🤖 Predictions AI</a>
            <a href="admin_dashboard.php" class="text-sm nav-link whitespace-nowrap">🏠 Dashboard</a>
            <a href="manage_users.php" class="text-sm nav-link whitespace-nowrap">👥 Manage Users</a>
            <a href="manage_orders.php" class="text-sm nav-link whitespace-nowrap">📦 Manage Orders</a>
            <a href="manage_stock.php" class="text-sm font-medium text-amazon-yellow whitespace-nowrap">📊 Stock Management</a>
            <a href="sales_reports.php" class="text-sm nav-link whitespace-nowrap">📈 Sales Reports</a>
            <a href="customer_feedback.php" class="text-sm nav-link whitespace-nowrap">💬 Customer Feedback</a>
            <a href="admin_settings.php" class="text-sm nav-link whitespace-nowrap">⚙️ Settings</a>
            <a href="manage_schools.php" class="text-sm font-medium text-white bg-amazon-blue px-2 py-1 rounded whitespace-nowrap">🏫 Manage Schools</a>
        </div>
    </div>
    <div class="flex">
         <!-- Sidebar Navigation -->
         <aside class="w-64 bg-amazon-navy text-white min-h-screen hidden md:block">
            <div class="p-4">
                <div class="flex items-center space-x-3 p-3 mb-6">
                    <div class="w-10 h-10 rounded-full bg-amazon-blue flex items-center justify-center text-white font-bold">
                      
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Administrator</p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="../ai/Predictions_AI.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-robot mr-2"></i> Predictions AI
                    </a>
                    <a href="admin_dashboard.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="manage_users.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-users mr-2"></i> User Management
                    </a>
                    <a href="manage_orders.php" class="block sidebar-item active py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-shopping-bag mr-2"></i> Order Management
                    </a>
                    <a href="manage_stock.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-boxes mr-2"></i> Inventory
                    </a>
                    <a href="sales_reports.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-chart-line mr-2"></i> Sales Reports
                    </a>
                    <a href="../admin/customer_feedback.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-comments mr-2"></i> Feedback
                    </a>
                    <a href="admin_settings.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                </nav>
            </div>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-amazon-navy">Inventory Management</h1>
                    <div class="relative w-64">
                        <input type="text" placeholder="Search items..." class="w-full px-4 py-2 rounded-lg border border-gray-300 amazon-search focus:outline-none">
                        <button class="absolute right-0 top-0 h-full px-3 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <strong>Error: </strong> <?= htmlspecialchars($error_message) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <strong>Success: </strong> <?= htmlspecialchars($success_message) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Item Form Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8 border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3">
                        <h2 class="text-lg font-semibold text-white"><?= isset($_POST['item_id']) ? 'Update Item' : 'Add New Item' ?></h2>
                    </div>
                    <form method="POST" id="itemForm" class="p-6 space-y-4">
                        <input type="hidden" id="item_id" name="item_id">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Item Type</label>
                                <select id="type" name="type" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                                    <option value="">Select Type</option>
                                    <option value="Sweater">Sweater</option>
                                    <option value="Shirt">Shirt</option>
                                    <option value="Trouser">Trouser</option>
                                    <option value="Skirt">Skirt</option>
                                    <option value="Short">Short</option>
                                    <option value="Sock">Sock</option>
                                    <option value="Jamper">Jamper</option>
                                    <option value="Tracksuit">Tracksuit</option>
                                    <option value="Tshirt">T-shirt</option>
                                    <option value="Gameshort">Gameshort</option>
                                    <option value="Wrapskirt">Wrapskirt</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
                                <input type="text" id="size" name="size" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quality</label>
                                <input type="text" id="quality" name="quality" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" id="quantity" name="quantity" min="1" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Price (KES)</label>
                                <input type="number" id="price" name="price" step="0.01" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                                <input type="text" id="color" name="color" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">School</label>
                                <select id="school_id" name="school_id" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                                    <option value="0">None</option>
                                    <?php
                                    $schools = $conn->query("SELECT * FROM schools")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($schools as $school): ?>
                                        <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-md transition duration-200">
                                <?= isset($_POST['item_id']) ? 'Update Item' : 'Add Item' ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Items Table Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-white">Inventory Items</h2>
                        <span class="text-sm text-white bg-amazon-light px-3 py-1 rounded-full">
                            <?= count($items) ?> items
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quality</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item["id"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item["type"] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item["size"] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item["quality"] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item["quantity"] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KES <?= number_format((float)($item["price"] ?? 0), 2) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item["school_name"] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="editItem(<?= htmlspecialchars($item["id"]) ?>, '<?= htmlspecialchars($item["type"] ?? '') ?>', '<?= htmlspecialchars($item["size"] ?? '') ?>', '<?= htmlspecialchars($item["quality"] ?? '') ?>', <?= htmlspecialchars($item["quantity"] ?? 0) ?>, <?= htmlspecialchars($item["price"] ?? 0) ?>, '<?= htmlspecialchars($item["color"] ?? '') ?>', <?= htmlspecialchars($item["school_id"] ?? 0) ?>)" 
                                                    class="text-amazon-blue hover:text-amazon-navy mr-3">
                                                    Edit
                                                </button>
                                                <a href="?delete=<?= htmlspecialchars($item["id"]) ?>:<?= htmlspecialchars($item["table"] ?? '') ?>" 
                                                    class="text-red-600 hover:text-red-900" 
                                                    onclick="return confirm('Are you sure you want to delete this item?')">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No items found in inventory.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>