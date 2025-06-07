<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if the user is not logged in or not a customer
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=login_system", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch user details
$user_id = $_SESSION["user_id"];
$query = "SELECT name, email, phone, profile_pic FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile picture upload
$uploadError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["profile_pic"])) {
    $targetDir = "uploads/";
    
    // Ensure the uploads directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true); // Create the directory if it doesn't exist
    }

    $fileName = basename($_FILES["profile_pic"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    // Check if the file is an image
    $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($imageFileType, $allowedTypes)) {
        $uploadError = "Only JPG, JPEG, PNG, and GIF files are allowed.";
    } elseif ($_FILES["profile_pic"]["size"] > 5 * 1024 * 1024) { // 5MB limit
        $uploadError = "File size must be less than 5MB.";
    } elseif (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFilePath)) {
        // Update the profile picture in the database
        $updateQuery = "UPDATE users SET profile_pic = ? WHERE id = ?";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([$fileName, $user_id]);

        // Refresh the page to show the new profile picture
        header("Location: profile.php");
        exit();
    } else {
        $uploadError = "Error uploading file. Please try again.";
    }
}

// Ensure user data exists to prevent errors
if (!$user) {
    $user = [
        "name" => "Unknown User",
        "email" => "Not available",
        "phone" => "Not available",
        "profile_pic" => "default_avatar.png"
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
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

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById("profilePreview").src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</head>
<body class="bg-background min-h-screen flex">

  <!-- Sidebar Navigation -->
  <aside class="sidebar w-72 bg-white shadow-lg p-6 flex flex-col justify-between">
        <div>
            <div class="text-center">
                <img src="../uploads/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default_avatar.png') ?>" 
                     alt="Customer Avatar" 
                     class="mx-auto w-24 h-24 rounded-full shadow-md object-cover">
               
                <p class="text-gray-500 text-sm">Customer</p>
            </div>

            <nav class="mt-6 space-y-3">
                <a href="customer_dashboard.php" class="flex items-center py-3 px-4 text-blue-500 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                    <i class="fas fa-home mr-3"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="order_uniforms.php" class="flex items-center py-3 px-4 text-blue-500 hover:bg-blue-100 rounded-lg transition">
                    <i class="fas fa-boxes mr-3"></i>
                    <span class="nav-text">Order Uniforms</span>
                </a>
                <a href="order_status.php" class="flex items-center py-3 px-4 text-blue-500 hover:bg-blue-100 rounded-lg transition">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    <span class="nav-text">My Orders</span>
                </a>
                <a href="order_history.php" class="flex items-center py-3 px-4 text-blue-500 hover:bg-blue-100 rounded-lg transition">
                    <i class="fas fa-history mr-3"></i>
                    <span class="nav-text">Order History</span>
                </a>
                <a href="customer_settings.php" class="flex items-center py-3 px-4 text-blue-500 hover:bg-blue-100 rounded-lg transition">
                    <i class="fas fa-cog mr-3"></i>
                    <span class="nav-text">Settings</span>
                </a>
                <a href="profile.php" class="block py-3 px-4 text-white bg-primary rounded-lg transition">👤 My Profile</a>
            </nav>
        </div>
        <div>
            <button id="toggleSidebar" class="w-full mb-4 py-2 px-4 bg-gray-200 hover:bg-gray-300 rounded-lg text-gray-700">
                <i class="fas fa-chevron-left"></i> <span class="nav-text">Collapse</span>
            </button>
            <a href="../authentication/logout.php" class="flex items-center justify-center py-3 px-4 text-white bg-red-500 rounded-lg hover:bg-red-600 transition">
                <i class="fas fa-sign-out-alt mr-3"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>
    <!-- Main Content -->
    <main class="flex-1 p-10">
        <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-lg p-8">
            <h1 class="text-3xl font-bold text-primary mb-6">👤 My Profile</h1>

            <!-- Profile Info -->
            <div class="flex items-center space-x-6 mb-6">
                <img id="profilePreview" src="uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                     alt="Profile Picture" class="w-32 h-32 rounded-full shadow-lg border">
                
                <div>
                    <h2 class="text-2xl font-semibold text-text"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-gray-600">📧 <?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-gray-600">📞 <?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>

            <!-- Upload Profile Picture Form -->
            <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <label class="block">
                    <span class="text-gray-700">Upload Profile Picture:</span>
                    <input type="file" name="profile_pic" accept="image/*" onchange="previewImage(event)"
                           class="block w-full mt-2 p-2 border rounded-lg focus:outline-none">
                </label>

                <button type="submit" class="px-6 py-2 bg-secondary text-white font-semibold rounded-lg hover:bg-primary transition">
                    📤 Upload Picture
                </button>
            </form>

            <?php if (!empty($uploadError)): ?>
                <p class="text-red-500 mt-3"><?php echo $uploadError; ?></p>
            <?php endif; ?>

            <!-- Edit Profile Button -->
            <div class="mt-6">
                <a href="customer_settings.php" class="px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-secondary transition inline-block">
                    ✏️ Edit Profile
                </a>
            </div>
        </div>
    </main>

</body>
</html>