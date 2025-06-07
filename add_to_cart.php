<?php
session_start();

header('Content-Type: application/json');

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Strict PDO connection
$host = 'localhost';
$dbname = 'login_system';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Default response
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0
];

try {
    // CSRF check
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid CSRF token", 403);
    }

    // Required fields
    $required = ['item_id', 'table_source', 'uniform_type', 'price', 'size', 'quantity'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucfirst($field) . " is required", 400);
        }
    }

    // Sanitize inputs
    $itemId = htmlspecialchars($_POST['item_id']);
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_source']);
    $uniformType = htmlspecialchars($_POST['uniform_type']);
    $price = floatval($_POST['price']);
    $size = htmlspecialchars($_POST['size']);
    $quantity = intval($_POST['quantity']);

    if ($price <= 0 || $quantity < 1) {
        throw new Exception("Invalid price or quantity", 400);
    }

    // Check stock
    $stmt = $conn->prepare("SELECT quantity FROM `$table` WHERE id = ? AND size = ?");
    $stmt->execute([$itemId, $size]);
    $stock = $stmt->fetchColumn();

    if ($stock === false) {
        throw new Exception("Item not found", 404);
    }

    // Manage cart
    if (!isset($_SESSION['pos_cart'])) {
        $_SESSION['pos_cart'] = [];
    }

    $cartItemId = md5($itemId . $table . $size);
    $newQty = $quantity;

    if (isset($_SESSION['pos_cart'][$cartItemId])) {
        $newQty += $_SESSION['pos_cart'][$cartItemId]['quantity'];
    }

    if ($newQty > $stock) {
        throw new Exception("Not enough stock (Available: $stock)", 400);
    }

    $_SESSION['pos_cart'][$cartItemId] = [
        'item_id' => $itemId,
        'table_source' => $table,
        'name' => $uniformType,
        'size' => $size,
        'price' => $price,
        'quantity' => $newQty,
        'school_id' => $_POST['school_id'] ?? null,
        'school_name' => $_POST['school_name'] ?? 'Generic',
        'color' => $_POST['color'] ?? 'Standard',
        'quality' => $_POST['quality'] ?? 'High',
        'image' => $_POST['image'] ?? 'https://via.placeholder.com/50',
        'last_updated' => time()
    ];

    $response['success'] = true;
    $response['message'] = "Item added to cart";
    $response['cart_count'] = count($_SESSION['pos_cart']);
    $response['cart_item'] = $_SESSION['pos_cart'][$cartItemId];

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
