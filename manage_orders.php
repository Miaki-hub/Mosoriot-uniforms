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

$user_name = $_SESSION['user_name'] ?? 'Admin';
$error_message = "";
$success_message = "";

// Handle Order Status Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $order_id = $_POST["order_id"] ?? null;
    $status = $_POST["status"] ?? '';

    if (empty($order_id) || empty($status)) {
        $error_message = "Invalid request parameters.";
    } else {
        try {
            $conn->beginTransaction();
            
            // First update the order status
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            
            // If status is being updated to 'completed', record the sale
            if ($status === 'completed') {
                // Get complete order details including uniform information
                $orderStmt = $conn->prepare("
                    SELECT 
                        o.*, 
                        u.name as customer_name,
                        unif.size as uniform_size,
                        unif.type as uniform_type
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    LEFT JOIN uniforms unif ON o.uniform_id = unif.id
                    WHERE o.id = ?
                ");
                $orderStmt->execute([$order_id]);
                $orderDetails = $orderStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($orderDetails) {
                    // Insert into sales table
                    $salesStmt = $conn->prepare("
                        INSERT INTO sales (
                            item_id, 
                            user_id, 
                            order_id, 
                            customer_name, 
                            uniform_type, 
                            size, 
                            quantity, 
                            total_price,
                            uniform_id,
                            sale_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $salesStmt->execute([
                        $orderDetails['uniform_id'],
                        $orderDetails['user_id'],
                        $order_id,
                        $orderDetails['customer_name'],
                        $orderDetails['uniform_type'] ?? 'Unknown',
                        $orderDetails['uniform_size'] ?? 'Unknown',
                        $orderDetails['quantity'],
                        $orderDetails['total_price'],
                        $orderDetails['uniform_id']
                    ]);
                }
            }
            
            $conn->commit();
            $success_message = "Order status updated successfully!";
            
            // Refresh the page to show updated data
            header("Location: manage_orders.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Delete Order
if (isset($_GET["delete"])) {
    $order_id = $_GET["delete"];

    try {
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $success_message = "Order deleted successfully!";
        
        // Also delete corresponding sales record if exists
        $salesStmt = $conn->prepare("DELETE FROM sales WHERE order_id = ?");
        $salesStmt->execute([$order_id]);
        
        // Refresh the page to show updated data
        header("Location: manage_orders.php");
        exit();
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Fetch Orders with User and Uniform Information
try {
    $stmt = $conn->prepare("
        SELECT 
            o.id, 
            o.uniform_id, 
            o.quantity, 
            o.total_price, 
            o.delivery_option,
            o.school_name,
            o.status, 
            o.created_at,
            u.name as customer_name,
            unif.size as uniform_size,
            unif.type as uniform_type
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN uniforms unif ON o.uniform_id = unif.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        'success-green': '#10B981'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }

        function confirmDelete(orderId) {
            if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
                window.location.href = `?delete=${orderId}`;
            }
        }
        
        function searchOrders() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('ordersTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
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
        .nav-link:hover {
            color: #FFA41C;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .status-cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .order-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
                    <span class="ml-2">UniformShop Admin</span>
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
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h1 class="text-2xl font-bold text-amazon-navy mb-4 md:mb-0">Order Management</h1>
                    
                    <div class="relative w-full md:w-64">
                        <input type="text" id="searchInput" onkeyup="searchOrders()" placeholder="Search orders..." 
                            class="w-full px-4 py-2 pl-10 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amazon-yellow">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 animate-fade-in">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Orders</p>
                                <p class="text-2xl font-semibold"><?= count($orders) ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-shopping-bag text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Completed</p>
                                <p class="text-2xl font-semibold">
                                    <?= count(array_filter($orders, function($order) { return $order['status'] === 'completed'; })) ?>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Pending</p>
                                <p class="text-2xl font-semibold">
                                    <?= count(array_filter($orders, function($order) { return $order['status'] === 'pending'; })) ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-clock text-yellow-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Cancelled</p>
                                <p class="text-2xl font-semibold">
                                    <?= count(array_filter($orders, function($order) { return $order['status'] === 'cancelled'; })) ?>
                                </p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-times-circle text-red-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <strong>Error: </strong> <?= htmlspecialchars($error_message) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>Success: </strong> <?= htmlspecialchars($success_message) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Orders List -->
                <div class="space-y-4">
                    <?php if (empty($orders)): ?>
                        <div class="bg-white rounded-lg shadow-md p-8 text-center">
                            <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700">No orders found</h3>
                            <p class="text-gray-500 mt-2">There are currently no orders in the system.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden order-card animate-fade-in">
                                <div class="p-5">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                        <div class="mb-4 md:mb-0">
                                            <div class="flex items-center space-x-3">
                                                <span class="text-lg font-semibold text-amazon-navy">Order #<?= htmlspecialchars($order['id']) ?></span>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full status-<?= htmlspecialchars($order['status']) ?>">
                                                    <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <i class="far fa-calendar-alt mr-1"></i>
                                                <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" 
                                                    class="px-3 py-1 text-sm font-medium rounded-full status-<?= htmlspecialchars($order['status']) ?> focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            
                                            <button onclick="confirmDelete(<?= htmlspecialchars($order['id']) ?>)" 
                                                class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="border-t border-gray-200 mt-4 pt-4">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">Customer</h4>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($order['customer_name']) ?></p>
                                            </div>
                                            
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">Uniform</h4>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($order['uniform_type']) ?> (Size: <?= htmlspecialchars($order['uniform_size']) ?>)</p>
                                            </div>
                                            
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">School</h4>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($order['school_name']) ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">Quantity</h4>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($order['quantity']) ?></p>
                                            </div>
                                            
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">Delivery</h4>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($order['delivery_option']) ?></p>
                                            </div>
                                            
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-500">Total Price</h4>
                                                <p class="text-sm font-medium text-amazon-blue">KSh <?= number_format($order['total_price'], 2) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>