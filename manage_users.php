<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/db_connection.php';

// Ensure user is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
$error_message = "";
$success_message = "";

// Handle User Management (Add / Edit)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_action"])) {
    $name = trim($_POST["name"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];
    $user_id = $_POST["user_id"] ?? null;
    $password = $_POST["password"] ?? null;

    if (empty($name) || empty($username) || empty($email) || empty($role)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            if ($user_id) {
                // Update existing user (only for suspend/delete actions)
                $error_message = "Edit functionality has been removed.";
            } else {
                // Add new user
                if (empty($password)) {
                    $error_message = "Password is required for new users.";
                } elseif (strlen($password) < 8) {
                    $error_message = "Password must be at least 8 characters long.";
                } else {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
                    $check_stmt->execute([$email, $username]);
                    if ($check_stmt->rowCount() > 0) {
                        $error_message = "Error: Email or username already exists.";
                    } else {
                        $password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $username, $email, $password, $role]);
                        $success_message = "User added successfully!";
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Suspend/Activate User
if (isset($_GET['suspend'])) {
    $user_id = $_GET['suspend'];
    try {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = "User status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating user status: " . $e->getMessage();
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = "User deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}

// Fetch Users from Database
try {
    $stmt = $conn->prepare("SELECT id, name, username, email, role, is_active FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
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
        .nav-link:hover {
            color: #FFA41C;
        }
        .action-btn {
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            transform: translateY(-1px);
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
                    <h1 class="text-2xl font-bold text-amazon-navy">User Management</h1>
                    <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" 
                            class="bg-amazon-orange hover:bg-yellow-600 text-white px-4 py-2 rounded-md flex items-center action-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add User
                    </button>
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

                <!-- Users Table Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3">
                        <h2 class="text-lg font-semibold text-white">User List</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No users found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($user['id']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($user['username']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($user['role']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 py-1 text-xs rounded-full <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $user['is_active'] ? 'Active' : 'Suspended' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="?suspend=<?= htmlspecialchars($user['id']) ?>" 
                                                   class="text-yellow-600 hover:text-yellow-900 mr-3 action-btn">
                                                    <?= $user['is_active'] ? 'Suspend' : 'Activate' ?>
                                                </a>
                                                <a href="?delete=<?= htmlspecialchars($user['id']) ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                                                   class="text-red-600 hover:text-red-900 action-btn">
                                                    Delete
                                                </a>
                                            </td>
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

    <!-- Add User Modal -->
    <div id="addUserModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Add New User</h3>
                <form method="POST" class="mt-2">
                    <input type="hidden" name="user_action" value="add">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                                <option value="admin">Admin</option>
                                <option value="user" selected>User</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="submit" class="bg-amazon-orange hover:bg-yellow-600 text-white px-4 py-2 rounded-md">
                            Add User
                        </button>
                        <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>