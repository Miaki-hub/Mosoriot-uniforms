<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session
session_start();

// Include database connection
require __DIR__ . '/../authentication/db_connection.php';

// Function to fetch stock from specific uniform tables
function fetchUniformStock($conn, $type = null) {
    $tables = [
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
    
    $stock = [];
    
    try {
        if ($type && array_key_exists($type, $tables)) {
            // Fetch specific type
            $table = $tables[$type];
            $query = "SELECT 
                        u.id,
                        '$type' as type,
                        s.size,
                        s.quality,
                        s.quantity,
                        s.price,
                        u.color,
                        u.stock_quantity
                      FROM $table s
                      JOIN uniforms u ON s.uniform_id = u.id
                      WHERE s.quantity > 0";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (empty($type)) {
            // Fetch all types
            foreach ($tables as $typeName => $table) {
                $query = "SELECT 
                            u.id,
                            '$typeName' as type,
                            s.size,
                            s.quality,
                            s.quantity,
                            s.price,
                            u.color,
                            u.stock_quantity
                          FROM $table s
                          JOIN uniforms u ON s.uniform_id = u.id
                          WHERE s.quantity > 0";
                
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $typeStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stock = array_merge($stock, $typeStock);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
    
    return $stock;
}

// Get the requested uniform type from POST data
$uniformType = $_POST['uniformType'] ?? null;

// Fetch the stock data
$stockData = fetchUniformStock($conn, $uniformType);

// Return the HTML response
if (empty($stockData)) {
    echo '<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No items found matching your criteria</td></tr>';
    exit;
}

foreach ($stockData as $item) {
    echo '
    <tr class="hover:bg-gray-50">
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="color-swatch" style="background-color: ' . getColorHex($item['color']) . '"></div>
                <div>
                    <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($item['type']) . '</div>
                    <div class="text-sm text-gray-500">' . htmlspecialchars($item['color']) . '</div>
                </div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            ' . htmlspecialchars($item['size'] ?? 'N/A') . '
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
                ($item['quality'] === 'High' ? 'bg-green-100 text-green-800' : 
                 ($item['quality'] === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) . '">
                ' . htmlspecialchars($item['quality']) . '
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">' . htmlspecialchars($item['quantity']) . '</div>';
    
    if ($item['quantity'] < 10) {
        echo '<div class="text-xs text-red-500">Low stock</div>';
    }
    
    echo '</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
            $' . number_format($item['price'], 2) . '
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <button onclick="openOrderModal(' . $item['id'] . ', \'' . addslashes($item['type']) . '\', ' . $item['price'] . ')" 
                    class="text-blue-600 hover:text-blue-900 mr-3">
                <i class="fas fa-shopping-cart mr-1"></i> Order
            </button>
        </td>
    </tr>';
}

// Helper function for color mapping
function getColorHex($colorName) {
    $colors = [
        'Blue' => '#3b82f6',
        'Red' => '#ef4444',
        'White' => '#ffffff',
        'Black' => '#000000',
        'Gray' => '#9ca3af',
        'Navy' => '#1e3a8a',
        'Pink' => '#ec4899',
        'Green' => '#10b981',
        'Yellow' => '#f59e0b',
        'Purple' => '#8b5cf6'
    ];
    return $colors[$colorName] ?? '#cccccc';
}
?>