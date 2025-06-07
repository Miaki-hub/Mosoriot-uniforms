<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/db_connection.php';

// Debugging: Check if $conn is set
if (!isset($conn)) {
    die("Database connection not established. Check db_connection.php.");
}

// Authentication Check
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

// Initialize variables
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$error_message = "";
$sales = [];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Administrator');

// Validate and sanitize date inputs
if (!empty($start_date) && !empty($end_date)) {
    $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
    $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);

    if (!$startDateObj || !$endDateObj) {
        $error_message = "Invalid date format.";
        $start_date = $end_date = '';
    }
}

// Fetch sales data from completed orders
try {
    // Base query to get completed orders (which are our sales)
    $query = "SELECT 
                o.id, 
                u.name AS customer_name,
                o.uniform_id,
                o.quantity, 
                o.total_price, 
                o.created_at AS sale_date,
                o.school_name
              FROM orders o
              JOIN users u ON o.user_id = u.id
              WHERE o.status = 'completed'";

    $params = [];

    // Add date filter if valid dates are provided
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
    }

    $query .= " ORDER BY o.created_at DESC";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - UniformStock</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <span class="ml-2">UniformStock</span>
                </a>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="relative">
                    <span class="text-sm text-gray-300">Hello, <?= $user_name ?></span>
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
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
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
                    <h1 class="text-2xl font-bold text-amazon-navy">Sales Reports</h1>
                    <div class="relative w-64">
                        <input type="text" placeholder="Search sales..." class="w-full px-4 py-2 rounded-lg border border-gray-300 amazon-search focus:outline-none">
                        <button class="absolute right-0 top-0 h-full px-3 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Display Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <strong>Error: </strong> <?= $error_message ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter Form Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8 border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3">
                        <h2 class="text-lg font-semibold text-white">Filter Sales</h2>
                    </div>
                    <form method="GET" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-md transition duration-200">
                                Apply Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sales Report Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-white">Sales Records</h2>
                        <div class="text-sm text-white">
                            <?php if (!empty($start_date) && !empty($end_date)): ?>
                                Showing results from <?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?>
                            <?php else: ?>
                                Showing all completed orders
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uniform ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Price</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No completed orders found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($sale["id"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($sale["customer_name"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($sale["uniform_id"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($sale["quantity"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KSh <?= number_format($sale["total_price"], 2) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($sale["school_name"]) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($sale["sale_date"]) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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