<?php
session_start();
session_regenerate_id(true); // Security measure

// Redirect if the user is not logged in or not an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: authentication/login.php");
    exit();
}

// Database connection
require 'config/db_connection.php';

// Fetch all stock items from the database
$sql = "SELECT * FROM uniforms ORDER BY type";
$stmt = $pdo->query($sql);
$uniforms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['add_stock'])) {
        // Add new stock item
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
        $size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_STRING);
        $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

        if ($type && $size && $color && $stock !== false && $price !== false) {
            $stmt = $pdo->prepare("INSERT INTO uniforms (type, size, color, stock_quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$type, $size, $color, $stock, $price]);
            header("Location: admin/manage_stock.php?success=1");
            exit();
        } else {
            die("Error: Invalid input data.");
        }
    } elseif (isset($_POST['update_stock'])) {
        // Update stock item
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

        if ($id && $stock !== false) {
            $stmt = $pdo->prepare("UPDATE uniforms SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$stock, $id]);
            header("Location: admin/manage_stock.php?success=1");
            exit();
        } else {
            die("Error: Invalid input data.");
        }
    } elseif (isset($_POST['delete_stock'])) {
        // Delete stock item
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM uniforms WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: admin/manage_stock.php?success=1");
            exit();
        } else {
            die("Error: Invalid input data.");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Admin Dashboard</title>
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
                        glass: 'rgba(255, 255, 255, 0.3)',
                    },
                    backdropBlur: {
                        sm: '6px',
                    }
                }
            }
        };

        // JavaScript for predicting stock
        function predictStock(item, size, currentStock) {
            fetch('ai/predict_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `current_stock=${currentStock}`,
            })
            .then(response => response.json())
            .then(data => {
                alert(`Predicted stock for ${item} (Size: ${size}) next week: ${data.predicted_stock}`);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to fetch prediction.');
            });
        }
    </script>
</head>
<body class="bg-background min-h-screen flex">

    <!-- Sidebar Navigation -->
    <?php include 'config/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-10">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-3xl font-bold text-primary mb-8">📊 Stock Management</h1>

            <!-- Add New Stock Form -->
            <div class="bg-card shadow-md p-6 rounded-lg mb-8">
                <h2 class="text-2xl font-semibold text-primary mb-4">➕ Add New Stock</h2>
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600">Type</label>
                            <input type="text" name="type" required class="w-full border px-3 py-2 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600">Size</label>
                            <input type="text" name="size" required class="w-full border px-3 py-2 rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600">Color</label>
                            <input type="text" name="color" required class="w-full border px-3 py-2 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600">Stock Quantity</label>
                            <input type="number" name="stock" required class="w-full border px-3 py-2 rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600">Price</label>
                        <input type="number" step="0.01" name="price" required class="w-full border px-3 py-2 rounded-lg">
                    </div>
                    <button type="submit" name="add_stock" class="bg-secondary text-white px-4 py-2 rounded-lg hover:bg-secondary/80 transition">
                        Add Stock
                    </button>
                </form>
            </div>

            <!-- Stock Overview Table -->
            <div class="bg-card shadow-md p-6 rounded-lg">
                <h2 class="text-2xl font-semibold text-primary mb-4">📦 Stock Overview</h2>
                <table class="w-full border-collapse border border-gray-300 text-text">
                    <thead>
                        <tr class="bg-accent/20 text-left">
                            <th class="py-3 px-4 border">ID</th>
                            <th class="py-3 px-4 border">Type</th>
                            <th class="py-3 px-4 border">Size</th>
                            <th class="py-3 px-4 border">Color</th>
                            <th class="py-3 px-4 border">Stock</th>
                            <th class="py-3 px-4 border">Price</th>
                            <th class="py-3 px-4 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php foreach ($uniforms as $uniform): ?>
                            <tr class="hover:bg-gray-100">
                                <td class="py-3 px-4 border"><?php echo htmlspecialchars($uniform['id']); ?></td>
                                <td class="py-3 px-4 border"><?php echo htmlspecialchars($uniform['type']); ?></td>
                                <td class="py-3 px-4 border"><?php echo htmlspecialchars($uniform['size']); ?></td>
                                <td class="py-3 px-4 border"><?php echo htmlspecialchars($uniform['color']); ?></td>
                                <td class="py-3 px-4 border"><?php echo htmlspecialchars($uniform['stock_quantity']); ?></td>
                                <td class="py-3 px-4 border">Ksh <?php echo htmlspecialchars($uniform['price']); ?></td>
                                <td class="py-3 px-4 border">
                                    <button onclick="predictStock('<?php echo htmlspecialchars($uniform['type']); ?>', '<?php echo htmlspecialchars($uniform['size']); ?>', <?php echo htmlspecialchars($uniform['stock_quantity']); ?>)" class="bg-secondary text-white px-4 py-2 rounded-lg hover:bg-secondary/80 transition">
                                        Predict Stock
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
