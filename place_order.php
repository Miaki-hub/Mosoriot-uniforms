<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'customer';

// Define uniform types and their tables
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['place_order'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header("Location: place_order.php?success=1"); // Stay on same page
        exit();
    }

    // Validate all required fields
    $required_fields = ['uniform_id', 'uniform_type', 'quantity', 'delivery_option', 'school_id'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['error'] = "The following fields are required: " . implode(', ', $missing_fields);
        header("Location: order_uniforms.php");
        exit();
    }

    // Sanitize and validate inputs
    $uniform_id = filter_input(INPUT_POST, 'uniform_id', FILTER_VALIDATE_INT);
    $uniform_type = htmlspecialchars(trim($_POST['uniform_type']));
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $delivery_option = htmlspecialchars(trim($_POST['delivery_option']));
    $school_id = filter_input(INPUT_POST, 'school_id', FILTER_VALIDATE_INT);

    // Additional validation
    if (!$uniform_id || !$quantity || !$school_id || !in_array($uniform_type, array_keys($uniformTypes))) {
        $_SESSION['error'] = "Invalid input data. Please try again.";
        header("Location: order_uniforms.php");
        exit();
    }

    // Get the specific table for this uniform type
    $table = $uniformTypes[$uniform_type];

    try {
        // Begin transaction
        $conn->beginTransaction();

        // 1. Get school information
        $school_query = "SELECT name FROM schools WHERE id = ?";
        $stmt = $conn->prepare($school_query);
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$school) {
            throw new Exception("Selected school not found.");
        }
        $school_name = $school['name'];

        // 2. Verify uniform exists and get details
        $query = "SELECT s.price, s.quantity as stock, u.color, u.size 
                  FROM $table s
                  JOIN uniforms u ON s.uniform_id = u.id
                  WHERE s.uniform_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$uniform_id]);
        $uniform = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$uniform) {
            throw new Exception("Selected uniform not found in inventory.");
        }

        // 3. Check stock availability
        if ($quantity > $uniform['stock']) {
            throw new Exception("Not enough stock available. Only {$uniform['stock']} items left.");
        }

        // 4. Calculate total price with delivery fee
        $base_price = $uniform['price'];
        $delivery_fee = 0;
        
        switch ($delivery_option) {
            case 'Home Delivery':
                $delivery_fee = 200.00; // KSh 200
                break;
            case 'Express Delivery':
                $delivery_fee = 500.00; // KSh 500
                break;
        }
        
        $subtotal = $base_price * $quantity;
        $total_price = $subtotal + $delivery_fee;

        // 5. Create order record
        $order_query = "INSERT INTO orders (
                            user_id, 
                            uniform_id, 
                            uniform_type,
                            quantity, 
                            base_price,
                            delivery_fee,
                            total_price, 
                            delivery_option, 
                            school_name,
                            status,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($order_query);
        $stmt->execute([
            $user_id,
            $uniform_id,
            $uniform_type,
            $quantity,
            $base_price,
            $delivery_fee,
            $total_price,
            $delivery_option,
            $school_name
        ]);
        
        $order_id = $conn->lastInsertId();

        // 6. Update stock in specific uniform table
        $update_stock_query = "UPDATE $table SET quantity = quantity - ? WHERE uniform_id = ?";
        $stmt = $conn->prepare($update_stock_query);
        $stmt->execute([$quantity, $uniform_id]);

        // Commit transaction
        $conn->commit();

        // Prepare order confirmation data
        $order_details = [
            'order_id' => $order_id,
            'uniform_type' => $uniform_type,
            'color' => $uniform['color'],
            'size' => $uniform['size'],
            'quantity' => $quantity,
            'base_price' => $base_price,
            'delivery_fee' => $delivery_fee,
            'total_price' => $total_price,
            'delivery_option' => $delivery_option,
            'school_name' => $school_name,
            'order_date' => date('Y-m-d H:i:s')
        ];

        // Store in session for confirmation page
        $_SESSION['order_confirmation'] = $order_details;
        
        // Redirect to confirmation page
        header("Location: order_confirmation.php");
        exit();

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Order processing error: " . $e->getMessage());
        $_SESSION['error'] = "Database error occurred. Please try again.";
        header("Location: order_uniforms.php");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: order_uniforms.php");
        exit();
    }
}

// If not a POST request, redirect to order form
header("Location: order_uniforms.php");
exit();