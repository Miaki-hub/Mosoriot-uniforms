<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if user is not logged in
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: authentication/login.php");
    exit();
}

// Database Connection
require 'config/db_connection.php';

// Check if the 'phone' column exists in the 'users' table
$checkPhoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
$hasPhoneColumn = $checkPhoneColumn->rowCount() > 0;

// Fetch Current User Data
$user_id = $_SESSION['user_id'];
$query = "SELECT name, email" . ($hasPhoneColumn ? ", phone" : "") . " FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = [ // Set default values to avoid errors
        'name' => '',
        'email' => '',
        'phone' => ''
    ];
}

// Handle Profile Update
$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = $hasPhoneColumn ? trim($_POST["phone"]) : null;

    // Basic Validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif ($hasPhoneColumn && (!preg_match('/^\d{10}$/', $phone))) {
        $error_message = "Phone number must be 10 digits.";
    } else {
        // Update Database
        $updateQuery = "UPDATE users SET name = ?, email = ?" . ($hasPhoneColumn ? ", phone = ?" : "") . " WHERE id = ?";
        $updateStmt = $pdo->prepare($updateQuery);
        $params = [$name, $email];
        if ($hasPhoneColumn) {
            $params[] = $phone;
        }
        $params[] = $user_id;

        if ($updateStmt->execute($params)) {
            $success_message = "Profile updated successfully!";
            // Update session data
            $_SESSION['user_name'] = $name;
        } else {
            $error_message = "Failed to update profile. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        background: '#F4F7FA',
                        card: '#FFFFFF',
                        primary: '#2C3E50',
                        secondary: '#2980B9',
                        accent: '#5DADE2',
                        text: '#2D3436',
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-background min-h-screen flex">

   <!-- Sidebar Navigation -->
   <aside class="w-72 bg-white shadow-lg p-6 flex flex-col justify-between rounded-r-lg">
        <div class="text-center">
            <img src="../assets/customer_avatar.png" alt="Customer Avatar" class="mx-auto w-24 h-24 rounded-full shadow-md">
            <h2 class="text-lg font-bold text-gray-900 mt-4"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer') ?></h2>
            <p class="text-gray-500 text-sm">Customer</p>
        </div>
        <nav class="mt-6 space-y-3">
            <a href="profile.php" class="block py-3 px-4 bg-blue-100 hover:bg-blue-200 rounded-lg">👤 Profile</a>
            <a href="order_uniforms.php" class="block py-3 px-4 bg-blue-100 hover:bg-blue-200 rounded-lg">📦 Order Uniforms</a>
            <a href="order_history.php" class="block py-3 px-4 bg-blue-100 hover:bg-blue-200 rounded-lg">📋 Order History</a>
            <a href="order_status.php" class="block py-3 px-4 bg-blue-100 hover:bg-blue-200 rounded-lg">📊 Order Status</a>
            <a href="customer_feedback.php" class="block py-3 px-4 bg-blue-100 hover:bg-blue-200 rounded-lg">💬 Feedback</a>
        </nav>
        <a href="../authentication/logout.php" class="block py-3 px-4 bg-red-500 text-white text-center rounded-lg hover:bg-red-600">🚪 Logout</a>
    </aside>
    <!-- Main Content -->
    <main class="flex-1 p-10">
        <div class="max-w-3xl mx-auto bg-card p-8 rounded-lg shadow-lg">
            <h1 class="text-3xl font-bold text-primary mb-6">✏️ Edit Profile</h1>

            <?php if ($error_message): ?>
                <p class="bg-red-200 text-red-800 p-4 rounded-lg mb-4"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <p class="bg-green-200 text-green-800 p-4 rounded-lg mb-4"><?php echo $success_message; ?></p>
            <?php endif; ?>

            <!-- Edit Profile Form -->
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-text font-semibold">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>

                <div class="mb-4">
                    <label class="block text-text font-semibold">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full p-3 border border-gray-300 rounded-lg" required>
                </div>

                <?php if ($hasPhoneColumn): ?>
                    <div class="mb-4">
                        <label class="block text-text font-semibold">Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="w-full p-3 border border-gray-300 rounded-lg" required>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-secondary transition">💾 Save Changes</button>
            </form>
        </div>
    </main>

</body>
</html>
