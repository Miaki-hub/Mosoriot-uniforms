<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start a secure session
session_start();
session_regenerate_id(true);

// Redirect if not logged in or not an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

// Include the database configuration
require __DIR__ . '/../authentication/db_connection.php';

// Securely get session variables
$user_name = $_SESSION['user_name'] ?? 'Admin';

// Function to fetch all schools
function fetchAllSchools($conn) {
    try {
        $query = "SELECT * FROM schools ORDER BY name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching schools: " . $e->getMessage());
        $_SESSION['error'] = "Error loading school data";
        return [];
    }
}

// Handle Add School Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_school'])) {
    // Sanitize user input
    $name = htmlspecialchars(trim($_POST['name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $contact_email = htmlspecialchars(trim($_POST['contact_email']));
    $contact_phone = htmlspecialchars(trim($_POST['contact_phone']));

    // Validate inputs
    if (empty($name)) {
        $_SESSION['error'] = "School name is required";
        header("Location: manage_schools.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO schools (name, address, contact_email, contact_phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $address, $contact_email, $contact_phone]);
        
        $_SESSION['success'] = 'School added successfully!';
    } catch (PDOException $e) {
        error_log("Error adding school: " . $e->getMessage());
        $_SESSION['error'] = 'Error adding school: ' . $e->getMessage();
    }

    header("Location: manage_schools.php");
    exit();
}

// Handle Edit School Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_school'])) {
    $id = intval($_POST['id']);
    $name = htmlspecialchars(trim($_POST['name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $contact_email = htmlspecialchars(trim($_POST['contact_email']));
    $contact_phone = htmlspecialchars(trim($_POST['contact_phone']));

    // Validate inputs
    if (empty($name)) {
        $_SESSION['error'] = "School name is required";
        header("Location: manage_schools.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE schools SET name = ?, address = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
        $stmt->execute([$name, $address, $contact_email, $contact_phone, $id]);
        
        $_SESSION['success'] = 'School updated successfully!';
    } catch (PDOException $e) {
        error_log("Error updating school: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating school: ' . $e->getMessage();
    }

    header("Location: manage_schools.php");
    exit();
}

// Handle Delete School Request
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // First check if any uniforms are associated with this school
        $stmt = $conn->prepare("SELECT COUNT(*) FROM uniforms WHERE school_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete school because it has associated uniforms. Please reassign or delete those items first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'School deleted successfully!';
        }
    } catch (PDOException $e) {
        error_log("Error deleting school: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting school: ' . $e->getMessage();
    }

    header("Location: manage_schools.php");
    exit();
}

// Get all schools from database
$schools = fetchAllSchools($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - Admin Dashboard</title>
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

        function openEditModal(school) {
            document.getElementById('edit_id').value = school.id;
            document.getElementById('edit_name').value = school.name;
            document.getElementById('edit_address').value = school.address || '';
            document.getElementById('edit_contact_email').value = school.contact_email || '';
            document.getElementById('edit_contact_phone').value = school.contact_phone || '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function confirmDelete(schoolId, schoolName) {
            if (confirm(`Are you sure you want to delete "${schoolName}"? This action cannot be undone.`)) {
                window.location.href = `manage_schools.php?delete=${schoolId}`;
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
                    <a href="../customer/customer_feedback.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
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
                    <h1 class="text-2xl font-bold text-amazon-navy">School Management</h1>
                    <div class="relative w-64">
                        <input type="text" placeholder="Search schools..." class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-amazon-yellow">
                        <button class="absolute right-0 top-0 h-full px-3 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
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

                <!-- Add School Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 mb-8">
                    <div class="bg-amazon-blue px-6 py-3">
                        <h2 class="text-lg font-semibold text-white">Add New School</h2>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">School Name*</label>
                                <input type="text" name="name" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                                <input type="tel" name="contact_phone" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <input type="email" name="contact_email" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                        </div>
                        <div class="pt-2">
                            <button type="submit" name="add_school" class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-2 px-6 rounded-md transition duration-200">
                                Add School
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Schools List Card -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                    <div class="bg-amazon-blue px-6 py-3 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-white">School Directory</h2>
                        <span class="text-sm text-white bg-amazon-light px-3 py-1 rounded-full">
                            <?= count($schools) ?> schools
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Phone</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($schools)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No schools found. Add your first school above.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schools as $school): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($school['name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($school['address']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($school['contact_email']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($school['contact_phone']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($school), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="text-amazon-blue hover:text-amazon-navy mr-3">
                                                    Edit
                                                </button>
                                                <button onclick="confirmDelete(<?= $school['id'] ?>, '<?= addslashes($school['name']) ?>')"
                                                    class="text-red-600 hover:text-red-900">
                                                    Delete
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
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit School</h3>
                <form method="POST" class="mt-2">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Name*</label>
                            <input type="text" id="edit_name" name="name" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="edit_address" name="address" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <input type="email" id="edit_contact_email" name="contact_email" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                            <input type="tel" id="edit_contact_phone" name="contact_phone" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="submit" name="edit_school" class="bg-amazon-blue hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                            Save Changes
                        </button>
                        <button type="button" onclick="closeEditModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>