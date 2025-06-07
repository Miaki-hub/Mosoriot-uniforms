<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Verify payment reference and CSRF token
if (!isset($_POST['payment_reference']) || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: order_uniforms.php");
    exit();
}

// Check if order confirmation exists in session
if (!isset($_SESSION['order_confirmation'])) {
    header("Location: order_uniforms.php");
    exit();
}

// Retrieve order details and payment reference
$order = $_SESSION['order_confirmation'];
$payment_reference = $_POST['payment_reference'];

// Here you would typically:
// 1. Verify the payment with Paystack API using $payment_reference
// 2. Update your database to mark the order as paid
// 3. Store the payment reference in the database

// Get user name for the header
$user_name = $_SESSION['user_name'] ?? 'Customer';

// Clear the session order data after displaying
unset($_SESSION['order_confirmation']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - UniformShop</title>
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
                        'bounce': 'bounce 1s infinite'
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
        .confirmation-card {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .checkmark-circle {
            width: 80px;
            height: 80px;
        }
        .checkmark {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: block;
            stroke-width: 4;
            stroke: #10B981;
            stroke-miterlimit: 10;
            box-shadow: inset 0px 0px 0px #10B981;
            animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
        }
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 40px #10B981; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="bg-amazon-navy text-white">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="#" class="text-2xl font-bold text-white flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amazon-yellow" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-2">UniformShop</span>
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
            <a href="user_dashboard.php" class="text-sm nav-link whitespace-nowrap">🏠 Dashboard</a>
            <a href="order_history.php" class="text-sm nav-link whitespace-nowrap">📦 My Orders</a>
            <a href="order_uniforms.php" class="text-sm font-medium text-amazon-yellow whitespace-nowrap">🛒 Order Uniforms</a>
            <a href="account_settings.php" class="text-sm nav-link whitespace-nowrap">⚙️ Account Settings</a>
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
                        <h3 class="font-medium"><?= htmlspecialchars($user_name) ?></h3>
                        <p class="text-xs text-gray-400">Customer</p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="../ai/Predictions_AI.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        Predictions AI
                    </a>
                    <a href="user_dashboard.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        Dashboard Overview
                    </a>
                    <a href="order_history.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        My Order History
                    </a>
                    <a href="order_uniforms.php" class="block sidebar-item active py-2 px-3 text-sm rounded transition duration-200">
                        Order Uniforms
                    </a>
                    <a href="account_settings.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        Account Settings
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Success Animation -->
                <div class="flex flex-col items-center justify-center mb-8 animate-fade-in">
                    <div class="checkmark-circle mb-4">
                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark-circle-bg" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-success-green mb-2">Payment Successful!</h1>
                    <p class="text-gray-600">Thank you for your purchase. Your order has been placed successfully.</p>
                </div>

                <!-- Order Summary Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 confirmation-card animate-fade-in">
                    <div class="bg-amazon-blue px-6 py-4">
                        <h2 class="text-lg font-semibold text-white">Order & Payment Details</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Order Information -->
                            <div>
                                <h3 class="text-lg font-medium text-amazon-navy mb-4">Order Information</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Order Number:</span>
                                        <span class="font-medium">#<?= htmlspecialchars($order['order_id']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-medium"><?= date('F j, Y') ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Paid</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Information -->
                            <div>
                                <h3 class="text-lg font-medium text-amazon-navy mb-4">Payment Information</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Method:</span>
                                        <span class="font-medium">Paystack</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Reference:</span>
                                        <span class="font-medium"><?= htmlspecialchars($payment_reference) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Amount Paid:</span>
                                        <span class="font-medium">KSh <?= number_format($order['total_price'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mt-8">
                            <h3 class="text-lg font-medium text-amazon-navy mb-4">Order Items</h3>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($order['uniform_type']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($order['quantity']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500">KSh <?= number_format($order['total_price'] / $order['quantity'], 2) ?></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Order Total -->
                        <div class="mt-8">
                            <div class="flex justify-end">
                                <div class="w-full md:w-1/2">
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Subtotal:</span>
                                            <span class="font-medium">KSh <?= number_format($order['total_price'] - ($order['delivery_option'] === 'Home Delivery' ? 200 : ($order['delivery_option'] === 'Express Delivery' ? 500 : 0)), 2) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Delivery:</span>
                                            <span class="font-medium">KSh <?= number_format($order['delivery_option'] === 'Home Delivery' ? 200 : ($order['delivery_option'] === 'Express Delivery' ? 500 : 0), 2) ?></span>
                                        </div>
                                        <div class="flex justify-between border-t border-gray-200 pt-3">
                                            <span class="text-lg font-bold">Total:</span>
                                            <span class="text-lg font-bold text-amazon-blue">KSh <?= number_format($order['total_price'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 p-6 animate-fade-in">
                    <h2 class="text-xl font-bold text-amazon-navy mb-4">What's Next?</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-start space-x-3 p-3 border border-gray-100 rounded-lg">
                            <div class="bg-blue-100 p-2 rounded-full">
                                <i class="fas fa-envelope text-amazon-blue"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">Email Confirmation</h3>
                                <p class="text-sm text-gray-600">You'll receive an order confirmation email shortly.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 border border-gray-100 rounded-lg">
                            <div class="bg-yellow-100 p-2 rounded-full">
                                <i class="fas fa-truck text-yellow-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">Order Processing</h3>
                                <p class="text-sm text-gray-600">We're preparing your order for shipment.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 border border-gray-100 rounded-lg">
                            <div class="bg-green-100 p-2 rounded-full">
                                <i class="fas fa-history text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium">Track Your Order</h3>
                                <p class="text-sm text-gray-600">Check your order status in your dashboard.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4 animate-fade-in">
                    <a href="order_uniforms.php" class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-3 px-6 rounded-md transition duration-200 text-center">
                        <i class="fas fa-shopping-cart mr-2"></i> Place Another Order
                    </a>
                    <a href="customer_dashboard.php" class="bg-white hover:bg-gray-100 text-amazon-navy font-semibold py-3 px-6 border border-gray-300 rounded-md transition duration-200 text-center">
                        <i class="fas fa-tachometer-alt mr-2"></i> Return to Dashboard
                    </a>
                    <a href="order_history.php" class="bg-amazon-blue hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-md transition duration-200 text-center">
                        <i class="fas fa-clipboard-list mr-2"></i> View Order History
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Simple animation trigger
        document.addEventListener('DOMContentLoaded', function() {
            // Confetti effect (optional)
            setTimeout(() => {
                if (typeof confetti === 'function') {
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }
            }, 500);
            
            // Print button functionality
            document.getElementById('printBtn').addEventListener('click', function() {
                window.print();
            });
        });
    </script>
    
    <!-- Optional: Include confetti library for celebration effect -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
</body>
</html>