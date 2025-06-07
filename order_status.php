<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once __DIR__ . '/../config/db_connection.php';

// Debugging: Check if $conn is set
if (!isset($conn)) {
    die("Database connection not established. Check db_connection.php.");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'customer'; // Default role

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

// Check if 'delivery_option' exists
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_option'");
    $hasDeliveryOption = $checkColumn->rowCount() > 0;
} catch (PDOException $e) {
    die("Error checking for 'delivery_option' column: " . $e->getMessage());
}

// Build the SELECT query dynamically - using created_at instead of order_date
$sql = "SELECT 
            o.id as order_id,
            u.type as uniform_type,
            u.color,
            o.quantity,
            o.status,
            o.total_price,
            o.created_at as order_date";

if ($hasDeliveryOption) {
    $sql .= ", o.delivery_option";  // Include delivery_option only if it exists
}

$sql .= " FROM orders o
          JOIN uniforms u ON o.uniform_id = u.id";

if ($user_role !== 'admin') {
    $sql .= " WHERE o.user_id = ?";
}

$sql .= " ORDER BY o.created_at DESC";  // Changed to use created_at for sorting

try {
    $stmt = $conn->prepare($sql);
    $user_role !== 'admin' ? $stmt->execute([$user_id]) : $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - UniformStock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
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
                    <span class="text-sm text-gray-300">Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer') ?></span>
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
                        <h3 class="font-medium"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer') ?></h3>
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
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-2xl font-bold text-amazon-navy">Order History</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-600"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>

                <!-- Display success/error messages -->
                <?php if (isset($_SESSION['order_history_message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <strong>Notice: </strong> <?= $_SESSION['order_history_message']; unset($_SESSION['order_history_message']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Orders Table -->
                <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                    <?php if (empty($orders)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 text-lg">You haven't placed any orders yet.</p>
                            <a href="order_uniforms.php" class="mt-4 inline-block text-amazon-blue hover:text-blue-700">
                                Browse our uniform collection
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uniform</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <?php if ($hasDeliveryOption): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery</th>
                                        <?php endif; ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?= htmlspecialchars($order['order_id']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($order['uniform_type']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="flex items-center">
                                                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?= htmlspecialchars($order['color']) ?>"></span>
                                                    <?= htmlspecialchars($order['color']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($order['quantity']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KSh <?= number_format($order['total_price'], 2) ?></td>
                                            <?php if ($hasDeliveryOption): ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($order['delivery_option']) ?></td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $statusClass = 'status-pending';
                                                if ($order['status'] === 'completed') {
                                                    $statusClass = 'status-completed';
                                                } elseif ($order['status'] === 'cancelled') {
                                                    $statusClass = 'status-cancelled';
                                                } elseif ($order['status'] === 'processing') {
                                                    $statusClass = 'status-processing';
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>