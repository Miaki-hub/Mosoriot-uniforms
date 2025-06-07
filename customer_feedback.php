<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require '../config/db_connection.php';

// Redirect if the user is not logged in or not an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../authentication/login.php");
    exit();
}

// Fetch the admin's name from the session or database
$user_name = $_SESSION["user_name"] ?? "Admin"; // Fallback to "Admin" if not set

// Initialize messages
$error_message = "";
$success_message = "";

// Handle Feedback Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_feedback"])) {
    // Use htmlspecialchars or FILTER_SANITIZE_FULL_SPECIAL_CHARS instead of FILTER_SANITIZE_STRING
    $customer_name = trim(htmlspecialchars($_POST['customer_name']));
    $feedback = trim(htmlspecialchars($_POST['feedback']));
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);

    if (!$customer_name || !$feedback || $rating < 1 || $rating > 5) {
        $error_message = "All fields are required, and rating must be between 1 and 5.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO customer_feedback (customer_name, feedback, rating) VALUES (:customer_name, :feedback, :rating)");
            $stmt->bindParam(':customer_name', $customer_name, PDO::PARAM_STR);
            $stmt->bindParam(':feedback', $feedback, PDO::PARAM_STR);
            $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
            $stmt->execute();
            $success_message = "Feedback submitted successfully!";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle Admin Reply
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_reply"])) {
    $feedback_id = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);
    $admin_reply = trim(htmlspecialchars($_POST['admin_reply']));

    if (!$admin_reply || !$feedback_id) {
        $error_message = "Reply cannot be empty.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE customer_feedback SET admin_reply = :admin_reply WHERE id = :feedback_id");
            $stmt->bindParam(':admin_reply', $admin_reply, PDO::PARAM_STR);
            $stmt->bindParam(':feedback_id', $feedback_id, PDO::PARAM_INT);
            $stmt->execute();
            $success_message = "Reply submitted successfully!";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle Feedback Deletion
if (isset($_GET["delete"])) {
    $feedback_id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($feedback_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM customer_feedback WHERE id = :feedback_id");
            $stmt->bindParam(':feedback_id', $feedback_id, PDO::PARAM_INT);
            $stmt->execute();
            $success_message = "Feedback deleted successfully!";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "Invalid feedback ID.";
    }
}

// Fetch Feedback Entries
try {
    $stmt = $conn->prepare("SELECT * FROM customer_feedback ORDER BY created_at DESC");
    $stmt->execute();
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching feedback: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': '#001f3f',
                        'amazon-blue': '#146eb4',
                        'amazon-light': '#232f3e',
                    }
                }
            }
        }
    </script>
    <script>
        function closeAlert(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar Navigation - Amazon Style -->
    <aside class="w-64 bg-navy shadow-lg p-4 flex flex-col justify-between">
        <div>
            <div class="flex items-center space-x-3 p-3 border-b border-amazon-light">
                <div class="bg-white rounded-full p-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amazon-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-white font-bold"><?= htmlspecialchars($user_name) ?></h2>
                    <p class="text-gray-300 text-xs">Administrator</p>
                </div>
            </div>

            <nav class="mt-4 space-y-1">
                <a href="../ai/Predictions_AI.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    <span>Predictions AI</span>
                </a>
                <a href="admin_dashboard.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="manage_users.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Manage Users</span>
                </a>
                <a href="manage_orders.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span>Manage Orders</span>
                </a>
                <a href="manage_stock.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Stock Management</span>
                </a>
                <a href="sales_reports.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Sales Reports</span>
                </a>
                <a href="customer_feedback.php" class="flex items-center space-x-3 px-3 py-2 bg-amazon-blue text-white rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <span>Customer Feedback</span>
                </a>
                <a href="admin_settings.php" class="flex items-center space-x-3 px-3 py-2 text-white hover:bg-amazon-light rounded transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Settings</span>
                </a>
                
            </nav>
        </div>
        <a href="../authentication/logout.php" class="flex items-center space-x-3 px-3 py-2 text-white bg-amazon-light hover:bg-red-600 rounded transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>Logout</span>
        </a>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header with Amazon-style search bar -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-navy">Customer Feedback</h1>
                <div class="relative w-1/3">
                    <input type="text" placeholder="Search feedback..." class="w-full py-2 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amazon-blue focus:border-amazon-blue">
                    <button class="absolute right-0 top-0 h-full px-4 bg-amazon-blue text-white rounded-r-lg hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Display Messages -->
            <?php if ($error_message): ?>
                <div id="errorAlert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 relative">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <button class="absolute top-3 right-3 text-red-500 hover:text-red-700" onclick="closeAlert('errorAlert')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div id="successAlert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 relative">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <button class="absolute top-3 right-3 text-green-500 hover:text-green-700" onclick="closeAlert('successAlert')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Feedback Form - Amazon Style Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 border border-gray-200">
                <h2 class="text-xl font-semibold text-navy mb-4">Submit New Feedback</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                        <input type="text" name="customer_name" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
                        <textarea name="feedback" required 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                        <select name="rating" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                            <option value="4">⭐⭐⭐⭐ (4 - Good)</option>
                            <option value="3">⭐⭐⭐ (3 - Average)</option>
                            <option value="2">⭐⭐ (2 - Poor)</option>
                            <option value="1">⭐ (1 - Terrible)</option>
                        </select>
                    </div>
                    <button type="submit" name="submit_feedback" 
                            class="w-full bg-amazon-blue hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-150">
                        Submit Feedback
                    </button>
                </form>
            </div>

            <!-- Feedback Table - Amazon Style -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-navy">Customer Feedback</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($feedbacks as $feedback): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($feedback["customer_name"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($feedback["feedback"]) ?></div>
                                        <?php if (!empty($feedback["admin_reply"])): ?>
                                            <div class="mt-2 p-2 bg-blue-50 border-l-4 border-amazon-blue rounded">
                                                <div class="text-xs text-gray-500 font-semibold">Admin Reply:</div>
                                                <div class="text-sm text-gray-700"><?= htmlspecialchars($feedback["admin_reply"]) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-yellow-500"><?= str_repeat("★", $feedback["rating"]) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($feedback["created_at"])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?delete=<?= $feedback['id'] ?>" class="text-red-600 hover:text-red-900 mr-4">Delete</a>
                                        <button onclick="document.getElementById('replyModal<?= $feedback['id'] ?>').showModal()" 
                                                class="text-amazon-blue hover:text-blue-900">Reply</button>
                                        
                                        <!-- Reply Modal -->
                                        <dialog id="replyModal<?= $feedback['id'] ?>" class="bg-white rounded-lg shadow-xl p-6 w-1/3">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-semibold text-navy">Reply to Feedback</h3>
                                                <button onclick="document.getElementById('replyModal<?= $feedback['id'] ?>').close()" 
                                                        class="text-gray-400 hover:text-gray-500">
                                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Your Reply</label>
                                                    <textarea name="admin_reply" required 
                                                              class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue"><?= htmlspecialchars($feedback["admin_reply"] ?? '') ?></textarea>
                                                </div>
                                                <div class="flex justify-end space-x-3">
                                                    <button type="button" onclick="document.getElementById('replyModal<?= $feedback['id'] ?>').close()" 
                                                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" name="submit_reply" 
                                                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-white bg-amazon-blue hover:bg-blue-700">
                                                        Submit Reply
                                                    </button>
                                                </div>
                                            </form>
                                        </dialog>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>