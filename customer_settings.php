<?php
// Error reporting at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Secure session configuration
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams["lifetime"],
    'path' => $cookieParams["path"],
    'domain' => $cookieParams["domain"],
    'secure' => true,    // Requires HTTPS
    'httponly' => true,  // Prevents JavaScript access
    'samesite' => 'Lax'  // CSRF protection
]);

// Start session
session_start();
session_regenerate_id(true); // Prevent session fixation

// Database connection
require_once __DIR__ . '/../config/db_connection.php';

// Check database connection
if (!isset($conn)) {
    die("Database connection not established. Check db_connection.php.");
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'customer';
$is_admin = ($user_role === 'admin');
$is_customer = ($user_role === 'customer');
$error = $success = '';
$customer = $customers = [];

// Handle AJAX request for updating customer data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    try {
        $id = (int)$_POST['id'];
        
        // Validate full name (must contain at least first and last name)
        $name = trim($_POST['name']);
        if (!preg_match('/^[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}$/', $name)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid full name (first and last name)']);
            exit();
        }
        $name = htmlspecialchars($name);
        
        // Validate email
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit();
        }
        
        // Validate phone number (only numbers and optional + - ( ) )
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        if ($phone && !preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Only numbers and + - ( ) are allowed.']);
            exit();
        }
        $phone = $phone ? htmlspecialchars($phone) : null;

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $id]);
        
        // Fetch updated data
        $stmt = $conn->prepare("SELECT id, name, username, email, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $updatedCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully',
            'customer' => $updatedCustomer
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Update customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating customer']);
        exit();
    }
}

// Customer data (for customer view)
if ($is_customer) {
    try {
        $stmt = $conn->prepare("SELECT id, name, username, email, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            $error = "Customer data not found!";
        }
    } catch (PDOException $e) {
        error_log("Customer data fetch error: " . $e->getMessage());
        $error = "Error fetching your data. Please try again later.";
    }
}

// Admin actions
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token (should be added)
    
    if (isset($_POST['add_customer'])) {
        // Validate inputs
        $required = ['name', 'username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $error = "Please fill all required fields!";
                break;
            }
        }
        
        if (!$error) {
            // Validate full name
            $name = trim($_POST['name']);
            if (!preg_match('/^[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}$/', $name)) {
                $error = "Please enter a valid full name (first and last name)";
            }
            $name = htmlspecialchars($name);
            
            $username = htmlspecialchars(trim($_POST['username']));
            $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Validate phone number
            $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
            if ($phone && !preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
                $error = "Invalid phone number format. Only numbers and + - ( ) are allowed.";
            }
            $phone = $phone ? htmlspecialchars($phone) : null;

            if (!$email) {
                $error = "Invalid email address!";
            } elseif (!$error) {
                try {
                    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role, phone) VALUES (?, ?, ?, ?, 'customer', ?)");
                    $stmt->execute([$name, $username, $email, $password, $phone]);
                    $success = "Customer added successfully!";
                } catch (PDOException $e) {
                    error_log("Add customer error: " . $e->getMessage());
                    $error = strpos($e->getMessage(), 'Duplicate entry') !== false 
                        ? "Username or email already exists!" 
                        : "Error adding customer. Please try again.";
                }
            }
        }
    } 
    elseif (isset($_POST['update_customer'])) {
        $id = (int)$_POST['id'];
        
        // Validate full name
        $name = trim($_POST['name']);
        if (!preg_match('/^[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}$/', $name)) {
            $error = "Please enter a valid full name (first and last name)";
        }
        $name = htmlspecialchars($name);
        
        $username = htmlspecialchars(trim($_POST['username']));
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        
        // Validate phone number
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        if ($phone && !preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
            $error = "Invalid phone number format. Only numbers and + - ( ) are allowed.";
        }
        $phone = $phone ? htmlspecialchars($phone) : null;

        if (!$email) {
            $error = "Invalid email address!";
        } elseif (!$error) {
            try {
                $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $phone, $id]);
                $success = "Customer updated successfully!";
            } catch (PDOException $e) {
                error_log("Update customer error: " . $e->getMessage());
                $error = "Error updating customer. Please try again.";
            }
        }
    } 
    elseif (isset($_POST['delete_customer'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Customer deleted successfully!";
            } else {
                $error = "Customer not found or already deleted!";
            }
        } catch (PDOException $e) {
            error_log("Delete customer error: " . $e->getMessage());
            $error = "Error deleting customer. Please try again.";
        }
    }
}

// Fetch all customers for admin view
if ($is_admin) {
    try {
        $stmt = $conn->prepare("SELECT id, name, username, email, phone, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC");
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch customers error: " . $e->getMessage());
        $error = "Error loading customer data. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_admin ? 'Customer Management' : 'My Settings' ?> - UniformStock</title>
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
                        <p class="text-xs text-gray-400"><?= $is_admin ? 'Administrator' : 'Customer' ?></p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="<?= $is_admin ? '../admin/admin_dashboard.php' : 'customer_dashboard.php' ?>" 
                       class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                    
                    <?php if ($is_customer): ?>
                        <a href="order_uniforms.php" 
                           class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                            <i class="fas fa-boxes mr-2"></i> Order Uniforms
                        </a>
                        <a href="order_status.php" 
                           class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                            <i class="fas fa-clipboard-list mr-2"></i> My Orders
                        </a>
                        <a href="order_history.php" 
                           class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                            <i class="fas fa-history mr-2"></i> Order History
                        </a>
                    <?php endif; ?>
                    
                    <a href="customer_settings.php" 
                       class="block sidebar-item active py-2 px-3 text-sm rounded transition duration-200">
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
                    <h1 class="text-2xl font-bold text-amazon-navy"><?= $is_admin ? 'Customer Management' : 'My Account Settings' ?></h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-600"><?= date('l, F j, Y') ?></span>
                        <?php if ($is_admin): ?>
                            <button onclick="showModal('addCustomerModal')" 
                                    class="bg-amazon-blue hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition">
                                <i class="fas fa-plus mr-2"></i> Add Customer
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if ($success || $error): ?>
                    <div class="mb-6">
                        <?php if ($success): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <strong>Success: </strong> <?= htmlspecialchars($success) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                    <strong>Error: </strong> <?= htmlspecialchars($error) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Content Sections -->
                <?php if ($is_customer): ?>
                    <!-- Customer View -->
                    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                            <!-- Personal Information -->
                            <div id="personalInfoSection">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xl font-semibold text-amazon-navy">Personal Information</h3>
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Customer</span>
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-gray-600 text-sm">Full Name</label>
                                        <p id="displayName" class="font-medium"><?= htmlspecialchars($customer['name'] ?? 'Not provided') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 text-sm">Username</label>
                                        <p id="displayUsername" class="font-medium"><?= htmlspecialchars($customer['username'] ?? 'Not set') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 text-sm">Email</label>
                                        <p id="displayEmail" class="font-medium"><?= htmlspecialchars($customer['email'] ?? 'Not provided') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 text-sm">Phone</label>
                                        <p id="displayPhone" class="font-medium"><?= htmlspecialchars($customer['phone'] ?? 'Not provided') ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 text-sm">Member Since</label>
                                        <p id="displayCreatedAt" class="font-medium">
                                            <?= isset($customer['created_at']) ? date('F j, Y', strtotime($customer['created_at'])) : 'Unknown' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Form -->
                            <div>
                                <h3 class="text-xl font-semibold text-amazon-navy mb-4">Update Information</h3>
                                <form method="POST" action="" id="updateCustomerForm">
                                    <input type="hidden" name="id" value="<?= $user_id ?>">
                                    <input type="hidden" name="ajax_update" value="1">
                                    
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_name">Full Name *</label>
                                        <input type="text" id="edit_name" name="name" 
                                               value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required
                                               pattern="[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}"
                                               title="Please enter first and last name (letters only)"
                                               class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                                        <p class="text-xs text-gray-500 mt-1">First and last name (letters only)</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_email">Email *</label>
                                        <input type="email" id="edit_email" name="email" 
                                               value="<?= htmlspecialchars($customer['email'] ?? '') ?>" required
                                               class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_phone">Phone Number</label>
                                        <input type="tel" id="edit_phone" name="phone" 
                                               value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                                               pattern="[\+\d\s\-\(\)]{10,20}"
                                               title="Only numbers and + - ( ) are allowed"
                                               class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                                        <p class="text-xs text-gray-500 mt-1">Format: 1234567890 or +1 (123) 456-7890</p>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" id="saveChangesBtn"
                                                class="bg-amazon-blue hover:bg-blue-700 text-white font-medium py-2 px-6 rounded transition">
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Admin View -->
                    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                        <!-- Customer Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No customers found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium"><?= htmlspecialchars($customer['name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-gray-600"><?= htmlspecialchars($customer['username']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-gray-600"><?= htmlspecialchars($customer['email']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-gray-600"><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-gray-600">
                                                        <?= date('M j, Y', strtotime($customer['created_at'])) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button onclick="showEditModal(
                                                        '<?= $customer['id'] ?>', 
                                                        '<?= htmlspecialchars(addslashes($customer['name'])) ?>', 
                                                        '<?= htmlspecialchars(addslashes($customer['username'])) ?>', 
                                                        '<?= htmlspecialchars(addslashes($customer['email'])) ?>', 
                                                        '<?= htmlspecialchars(addslashes($customer['phone'] ?? '')) ?>'
                                                    )" class="text-amazon-blue hover:text-blue-700 mr-4">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </button>
                                                    <button onclick="confirmDelete('<?= $customer['id'] ?>')" 
                                                            class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modals (Admin Only) -->
    <?php if ($is_admin): ?>
        <!-- Add Customer Modal -->
        <div id="addCustomerModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="flex justify-between items-center border-b p-4">
                    <h3 class="text-lg font-semibold text-amazon-navy">Add New Customer</h3>
                    <button onclick="hideModal('addCustomerModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" action="" class="p-4">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required
                                   pattern="[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}"
                                   title="Please enter first and last name (letters only)"
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                            <p class="text-xs text-gray-500 mt-1">First and last name (letters only)</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username *</label>
                            <input type="text" id="username" name="username" required
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password *</label>
                            <input type="password" id="password" name="password" required
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                            <p class="text-xs text-gray-500 mt-1">At least 8 characters</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                   pattern="[\+\d\s\-\(\)]{10,20}"
                                   title="Only numbers and + - ( ) are allowed"
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                            <p class="text-xs text-gray-500 mt-1">Format: 1234567890 or +1 (123) 456-7890</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hideModal('addCustomerModal')" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="add_customer" 
                                class="px-4 py-2 bg-amazon-blue text-white rounded-md hover:bg-blue-700">
                            Add Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Customer Modal -->
        <div id="editCustomerModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="flex justify-between items-center border-b p-4">
                    <h3 class="text-lg font-semibold text-amazon-navy">Edit Customer</h3>
                    <button onclick="hideModal('editCustomerModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" action="" class="p-4">
                    <input type="hidden" id="edit_id" name="id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_name">Full Name *</label>
                            <input type="text" id="edit_name" name="name" required
                                   pattern="[a-zA-Z\s]{2,} [a-zA-Z\s]{2,}"
                                   title="Please enter first and last name (letters only)"
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                            <p class="text-xs text-gray-500 mt-1">First and last name (letters only)</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_username">Username *</label>
                            <input type="text" id="edit_username" name="username" required
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_email">Email *</label>
                            <input type="email" id="edit_email" name="email" required
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_phone">Phone Number</label>
                            <input type="tel" id="edit_phone" name="phone"
                                   pattern="[\+\d\s\-\(\)]{10,20}"
                                   title="Only numbers and + - ( ) are allowed"
                                   class="w-full px-3 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-amazon-blue">
                            <p class="text-xs text-gray-500 mt-1">Format: 1234567890 or +1 (123) 456-7890</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hideModal('editCustomerModal')" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="update_customer" 
                                class="px-4 py-2 bg-amazon-blue text-white rounded-md hover:bg-blue-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div class="flex justify-between items-center border-b p-4">
                    <h3 class="text-lg font-semibold text-amazon-navy">Confirm Deletion</h3>
                    <button onclick="hideModal('deleteModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="p-4">
                    <p class="mb-4 text-gray-700">Are you sure you want to delete this customer? This action cannot be undone.</p>
                    
                    <form method="POST" action="" id="deleteForm">
                        <input type="hidden" id="delete_id" name="id">
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideModal('deleteModal')" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" name="delete_customer" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- JavaScript -->
    <script>
        // Modal functions
        function showModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function hideModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Edit modal with customer data
        function showEditModal(id, name, username, email, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            showModal('editCustomerModal');
        }
        
        // Confirm deletion
        function confirmDelete(id) {
            document.getElementById('delete_id').value = id;
            showModal('deleteModal');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                hideModal(event.target.id);
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        hideModal(modal.id);
                    }
                });
            }
        });

        // AJAX form submission for customer updates
        document.getElementById('updateCustomerForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const saveBtn = document.getElementById('saveChangesBtn');
            
            // Show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the display with new data
                    document.getElementById('displayName').textContent = data.customer.name || 'Not provided';
                    document.getElementById('displayEmail').textContent = data.customer.email || 'Not provided';
                    document.getElementById('displayPhone').textContent = data.customer.phone || 'Not provided';
                    
                    // Show success message
                    showAlert('success', 'Your information has been updated successfully!');
                } else {
                    showAlert('error', data.message || 'Error updating your information');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'An error occurred while updating your information');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Changes';
            });
        });

        // Helper function to show alerts
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `bg-${type === 'success' ? 'green' : 'red'}-100 border border-${type === 'success' ? 'green' : 'red'}-400 text-${type === 'success' ? 'green' : 'red'}-700 px-4 py-3 rounded relative mb-6`;
            alertDiv.role = 'alert';
            
            const messageSpan = document.createElement('span');
            messageSpan.className = 'block sm:inline';
            messageSpan.textContent = message;
            
            const closeButton = document.createElement('button');
            closeButton.className = 'absolute top-0 bottom-0 right-0 px-4 py-3';
            closeButton.innerHTML = '<i class="fas fa-times"></i>';
            closeButton.onclick = function() {
                alertDiv.remove();
            };
            
            alertDiv.appendChild(messageSpan);
            alertDiv.appendChild(closeButton);
            
            // Insert at the top of the main content
            const mainContent = document.querySelector('main > div');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>