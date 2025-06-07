<?php
/**
 * Mosoriot Uniforms Depot - Barcode Scanner
 * 
 * Handles barcode scanning functionality for:
 * - Mobile camera scanning
 * - Physical barcode scanner input
 * - Quick returns processing
 */

// Start session and check authentication
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Handle barcode scan input
$barcode = $_GET['scan'] ?? '';
$result = null;

if (!empty($barcode)) {
    // Search for item in database
    $stmt = $conn->prepare("
        SELECT * FROM (
            SELECT id, 'sweaters' as table_name, barcode, size, color, price, quantity FROM sweaters WHERE barcode = ?
            UNION SELECT id, 'shirts' as table_name, barcode, size, color, price, quantity FROM shirts WHERE barcode = ?
            UNION SELECT id, 'dresses' as table_name, barcode, size, color, price, quantity FROM dresses WHERE barcode = ?
            UNION SELECT id, 'trousers' as table_name, barcode, size, color, price, quantity FROM trousers WHERE barcode = ?
            UNION SELECT id, 'gameshorts' as table_name, barcode, size, color, price, quantity FROM gameshorts WHERE barcode = ?
        ) AS items
        LIMIT 1
    ");
    
    $stmt->execute([$barcode, $barcode, $barcode, $barcode, $barcode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission for returns/processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_item'])) {
    // Process the item (e.g., return, exchange, etc.)
    // Implementation depends on your specific requirements
    $_SESSION['success'] = "Item processed successfully!";
    header("Location: barcode.php?scan=" . urlencode($_POST['barcode']));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner - Mosoriot Uniforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <style>
        #scanner-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }
        #scanner-video {
            width: 100%;
            border: 2px solid #FF9900;
            border-radius: 8px;
        }
        .scan-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .scan-line {
            width: 80%;
            height: 3px;
            background: #FF9900;
            animation: scan 2s infinite linear;
        }
        @keyframes scan {
            0% { transform: translateY(-50px); }
            100% { transform: translateY(50px); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header -->
            <div class="bg-blue-800 text-white p-4">
                <h1 class="text-2xl font-bold">Barcode Scanner</h1>
                <p class="text-blue-200">Scan items using camera or enter barcode manually</p>
            </div>
            
            <!-- Scanner Section -->
            <div class="p-6">
                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Camera Scanner -->
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold mb-4">Camera Scanner</h2>
                        <div id="scanner-container">
                            <video id="scanner-video"></video>
                            <div class="scan-overlay">
                                <div class="scan-line"></div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-center space-x-4">
                            <button id="start-scan" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                                <i class="fas fa-camera mr-2"></i> Start Scanner
                            </button>
                            <button id="stop-scan" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded" disabled>
                                <i class="fas fa-stop-circle mr-2"></i> Stop
                            </button>
                        </div>
                    </div>
                    
                    <!-- Manual Input -->
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold mb-4">Manual Entry</h2>
                        <form method="GET" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Enter Barcode:</label>
                                <input type="text" name="scan" value="<?= htmlspecialchars($barcode) ?>" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="Scan or enter barcode" autofocus>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">
                                <i class="fas fa-search mr-2"></i> Lookup Item
                            </button>
                        </form>
                        
                        <!-- Scan from Receipt -->
                        <div class="mt-6">
                            <h3 class="text-lg font-medium mb-2">Scan from Receipt</h3>
                            <p class="text-sm text-gray-600 mb-2">Use your camera to scan the QR/barcode from a receipt</p>
                            <a href="scan_receipt.php" class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">
                                <i class="fas fa-receipt mr-2"></i> Scan Receipt
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Scan Results -->
                <?php if (!empty($barcode)): ?>
                    <div class="mt-8 border-t pt-6">
                        <h2 class="text-xl font-semibold mb-4">Scan Results</h2>
                        
                        <?php if ($result): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            Item found in inventory!
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Item Information
                                    </h3>
                                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                        Barcode: <?= htmlspecialchars($barcode) ?>
                                    </p>
                                </div>
                                <div class="border-t border-gray-200">
                                    <dl>
                                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                            <dt class="text-sm font-medium text-gray-500">
                                                Type
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                                <?= ucfirst(str_replace('s', '', $result['table_name'])) ?>
                                            </dd>
                                        </div>
                                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                            <dt class="text-sm font-medium text-gray-500">
                                                Size
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                                <?= htmlspecialchars($result['size']) ?>
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                            <dt class="text-sm font-medium text-gray-500">
                                                Color
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                                <?= htmlspecialchars($result['color']) ?>
                                            </dd>
                                        </div>
                                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                            <dt class="text-sm font-medium text-gray-500">
                                                Price
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                                KSh <?= number_format($result['price'], 2) ?>
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                            <dt class="text-sm font-medium text-gray-500">
                                                Stock Available
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                                <?= $result['quantity'] ?> units
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="mt-6 flex flex-wrap gap-3">
                                <a href="admin_dashboard.php?search=<?= urlencode($barcode) ?>" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                    <i class="fas fa-edit mr-2"></i> Edit Item
                                </a>
                                
                                <form method="POST" class="inline-flex">
                                    <input type="hidden" name="barcode" value="<?= htmlspecialchars($barcode) ?>">
                                    <button type="submit" name="process_item" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                                        <i class="fas fa-exchange-alt mr-2"></i> Process Return
                                    </button>
                                </form>
                                
                                <a href="sales_report.php?barcode=<?= urlencode($barcode) ?>" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700">
                                    <i class="fas fa-chart-line mr-2"></i> View Sales History
                                </a>
                            </div>
                            
                        <?php else: ?>
                            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            No item found with barcode: <?= htmlspecialchars($barcode) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-sm text-gray-600">Possible actions:</p>
                                <div class="mt-2 space-x-2">
                                    <a href="add_item.php?barcode=<?= urlencode($barcode) ?>" 
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700">
                                        <i class="fas fa-plus mr-1"></i> Add New Item
                                    </a>
                                    <a href="admin_dashboard.php" 
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200">
                                        <i class="fas fa-search mr-1"></i> Search Inventory
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Barcode scanner functionality
        document.addEventListener('DOMContentLoaded', function() {
            const startBtn = document.getElementById('start-scan');
            const stopBtn = document.getElementById('stop-scan');
            let scannerActive = false;
            
            // Start barcode scanner
            startBtn.addEventListener('click', function() {
                scannerActive = true;
                startBtn.disabled = true;
                stopBtn.disabled = false;
                
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.querySelector('#scanner-video'),
                        constraints: {
                            width: 480,
                            height: 320,
                            facingMode: "environment"
                        },
                    },
                    decoder: {
                        readers: [
                            "code_128_reader",
                            "ean_reader",
                            "ean_8_reader",
                            "code_39_reader",
                            "code_39_vin_reader",
                            "codabar_reader",
                            "upc_reader",
                            "upc_e_reader"
                        ]
                    },
                }, function(err) {
                    if (err) {
                        console.error(err);
                        alert("Error initializing scanner: " + err);
                        return;
                    }
                    console.log("Initialization finished. Ready to start");
                    Quagga.start();
                });
                
                Quagga.onDetected(function(result) {
                    if (scannerActive) {
                        const code = result.codeResult.code;
                        console.log("Barcode detected: ", code);
                        window.location.href = "barcode.php?scan=" + encodeURIComponent(code);
                    }
                });
            });
            
            // Stop barcode scanner
            stopBtn.addEventListener('click', function() {
                scannerActive = false;
                startBtn.disabled = false;
                stopBtn.disabled = true;
                Quagga.stop();
            });
            
            // Focus on barcode input when page loads
            document.querySelector('input[name="scan"]').focus();
        });
    </script>
</body>
</html>