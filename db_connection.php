<?php
$host = 'localhost'; // Database host
$dbname = 'login_system'; // Database name
$username = 'root'; // Database username
$password = ''; // Database password

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Enable exceptions for errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch data as associative arrays
            PDO::ATTR_EMULATE_PREPARES => false, // Disable emulated prepared statements
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>