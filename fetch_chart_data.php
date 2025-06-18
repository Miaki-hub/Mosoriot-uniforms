<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'login_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Fetch sales data for the line chart (last 7 days)
$salesDataQuery = $pdo->query("
    SELECT DATE(sale_date) as date, SUM(total_price) as daily_sales 
    FROM orders 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(sale_date) 
    ORDER BY date
");
$salesData = $salesDataQuery->fetchAll();

// Fetch uniform items for the bar/pie charts
$itemsQuery = $pdo->query("
    SELECT u.id, u.name, u.quantity, u.price 
    FROM uniforms u
    JOIN orders o ON u.id = o.uniform_id
    GROUP BY u.id, u.name, u.quantity, u.price
    ORDER BY COUNT(o.id) DESC
");
$items = $itemsQuery->fetchAll();

// Prepare data for the response
$response = [
    'salesDates' => array_column($salesData, 'date'),
    'salesValues' => array_column($salesData, 'daily_sales'),
    'itemNames' => array_column($items, 'name'),
    'stockLevels' => array_column($items, 'quantity'),
    'prices' => array_column($items, 'price')
];

// Return as JSON
header('Content-Type: application/json');
echo json_encode($response);