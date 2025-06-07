<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection file
require '../config/db_connection.php';

// Redirect if the user is not logged in or not an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

$error_message = "";
$success_message = "";

// Fetch settings
try {
    $stmt = $conn->prepare("SELECT * FROM admin_settings WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        "site_name" => "",
        "contact_email" => "",
        "phone_number" => "",
        "address" => "",
        "theme_mode" => "light",
        "notifications_enabled" => 0
    ];
} catch (PDOException $e) {
    $error_message = "Error fetching settings: " . $e->getMessage();
}

// Update settings
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $site_name = trim($_POST["site_name"] ?? '');
    $contact_email = trim($_POST["contact_email"] ?? '');
    $phone_number = trim($_POST["phone_number"] ?? '');
    $address = trim($_POST["address"] ?? '');
    $theme_mode = in_array($_POST["theme_mode"], ["light", "dark"]) ? $_POST["theme_mode"] : "light";
    $notifications_enabled = isset($_POST["notifications_enabled"]) ? 1 : 0;

    if (empty($site_name) || empty($contact_email) || empty($phone_number) || empty($address)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO admin_settings (id, site_name, contact_email, phone_number, address, theme_mode, notifications_enabled) 
    VALUES (1, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    site_name=VALUES(site_name), contact_email=VALUES(contact_email), phone_number=VALUES(phone_number), 
    address=VALUES(address), theme_mode=VALUES(theme_mode), notifications_enabled=VALUES(notifications_enabled)");

$stmt->execute([$site_name, $contact_email, $phone_number, $address, $theme_mode, $notifications_enabled]);

            $success_message = "Settings updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-amazon-navy">System Settings</h1>
                </div>

                <!-- Alerts -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <strong>Error: </strong> <?= htmlspecialchars($error_message) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <strong>Success: </strong> <?= htmlspecialchars($success_message) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Settings Form Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3">
                        <h2 class="text-lg font-semibold text-white">Website Configuration</h2>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
                                <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                                <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="text" name="phone_number" value="<?= htmlspecialchars($settings['phone_number'] ?? '') ?>" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Theme Mode</label>
                                <select name="theme_mode" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                                    <option value="light" <?= ($settings['theme_mode'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                    <option value="dark" <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="notifications" name="notifications_enabled" value="1" 
                                <?= ($settings['notifications_enabled'] ?? 0) ? 'checked' : '' ?> 
                                class="h-4 w-4 text-amazon-blue focus:ring-amazon-blue border-gray-300 rounded">
                            <label for="notifications" class="ml-2 block text-sm text-gray-700">
                                Enable Email Notifications
                            </label>
                        </div>
                        <div class="pt-4">
                            <button type="submit" class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-md transition duration-200">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>