<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX request for adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $response = ['success' => false, 'message' => ''];
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = 'Invalid CSRF token';
        echo json_encode($response);
        exit();
    }
    
    // Validate required fields
    $required = ['item_id', 'table_source', 'uniform_type', 'price', 'school_id', 'size', 'quantity'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $response['message'] = "Missing required field: $field";
            echo json_encode($response);
            exit();
        }
    }
    
    // Prepare cart item data
    $item = [
        'id' => $_POST['item_id'],
        'table' => $_POST['table_source'],
        'type' => $_POST['uniform_type'],
        'price' => (float)$_POST['price'],
        'school_id' => $_POST['school_id'],
        'size' => $_POST['size'],
        'quantity' => (int)$_POST['quantity'],
        'timestamp' => time()
    ];
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if item already exists in cart
    $found = false;
    foreach ($_SESSION['cart'] as &$cartItem) {
        if ($cartItem['id'] === $item['id'] && $cartItem['table'] === $item['table'] && $cartItem['size'] === $item['size']) {
            $newQuantity = $cartItem['quantity'] + $item['quantity'];
            
            // Check stock availability
            try {
                $stmt = $conn->prepare("SELECT quantity FROM {$item['table']} WHERE id = ? AND size = ?");
                $stmt->execute([$item['id'], $item['size']]);
                $stock = $stmt->fetchColumn();
                
                if ($newQuantity <= $stock) {
                    $cartItem['quantity'] = $newQuantity;
                    $found = true;
                    break;
                } else {
                    $response['message'] = "Cannot add more than available stock";
                    echo json_encode($response);
                    exit();
                }
            } catch (PDOException $e) {
                $response['message'] = "Error checking stock: " . $e->getMessage();
                echo json_encode($response);
                exit();
            }
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = $item;
    }
    
    $response['success'] = true;
    $response['cart_count'] = count($_SESSION['cart']);
    echo json_encode($response);
    exit();
}

// Fetch schools for filter dropdown
$schools = [];
try {
    $schoolStmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $schoolStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching schools: " . $e->getMessage());
}

// Uniform types mapping to their tables
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

// Fetch all uniforms directly from their respective tables
$uniforms = [];
foreach ($uniformTypes as $type => $table) {
    // Check if the table has a barcode column
    $hasBarcode = false;
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM $table LIKE 'barcode'");
        $hasBarcode = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasBarcode = false;
    }

    // Build query based on available columns
    $query = "SELECT 
                d.id, 
                '$type' AS type,
                d.size, 
                d.price, 
                d.quality, 
                d.color,
                d.quantity AS stock_quantity,
                d.school_id, " .
                ($hasBarcode ? "d.barcode, " : "NULL AS barcode, ") .
               "s.name AS school_name
              FROM $table d
              LEFT JOIN schools s ON d.school_id = s.id
              WHERE d.quantity > 0";
    
    try {
        $result = $conn->query($query);
        while ($row = $result->fetch()) {
            $row['table'] = $table;
            $uniforms[] = $row;
        }
    } catch (PDOException $e) {
        // Log error but continue execution
        error_log("Error fetching uniforms from $table: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniform Collection - Mosoriot Uniforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            scroll-behavior: smooth;
        }
        .uniform-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .size-option {
            transition: all 0.2s ease;
        }
        .size-option:hover {
            background-color: #f0f2f5;
        }
        .size-option.selected {
            background-color: #146EB4;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-blue-800 shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="bg-blue-600 p-2 rounded-lg">
                        <i class="fas fa-tshirt text-white text-2xl"></i>
                    </div>
                    <a href="index.php" class="ml-3 text-xl font-bold text-white font-poppins">Mosoriot Uniforms</a>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-white hover:text-orange-300">Home</a>
                    <a href="uniforms.php" class="text-white hover:text-orange-300">Uniforms</a>
                    <a href="orders.php" class="text-white hover:text-orange-300">My Orders</a>
                    <a href="contact.php" class="text-white hover:text-orange-300">Contact</a>
                    <a href="cart.php" class="relative bg-orange-500 text-white px-6 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-300">
                        <i class="fas fa-shopping-cart mr-2"></i> Cart
                        <?php if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center cart-count">
                                <?php echo count($_SESSION['cart']); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="md:w-1/4 bg-white p-6 rounded-lg shadow-md h-fit sticky top-24">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filters</h2>
                
                <div class="mb-6">
                    <h3 class="font-medium mb-2">School</h3>
                    <select id="school-filter" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">All Schools</option>
                        <?php foreach($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <h3 class="font-medium mb-2">Uniform Type</h3>
                    <select id="type-filter" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">All Types</option>
                        <?php foreach($uniformTypes as $type => $table): ?>
                            <option value="<?php echo strtolower($type); ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <h3 class="font-medium mb-2">Price Range</h3>
                    <div class="flex items-center justify-between mb-2">
                        <span id="price-min" class="text-sm">KSh 0</span>
                        <span id="price-max" class="text-sm">KSh 5000+</span>
                    </div>
                    <input type="range" id="price-range" min="0" max="5000" step="100" value="5000" class="w-full">
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" id="in-stock-only" class="mr-2">
                        <span>In stock only</span>
                    </label>
                </div>
            </div>
            
            <!-- Uniforms Grid -->
            <div class="md:w-3/4">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Uniform Collection</h1>
                    <div class="relative">
                        <input type="text" id="search-uniforms" placeholder="Search uniforms..." class="p-2 border border-gray-300 rounded-md pl-8">
                        <i class="fas fa-search absolute left-2 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <?php if(empty($uniforms)): ?>
                    <div class="bg-white p-8 rounded-lg shadow-md text-center">
                        <i class="fas fa-tshirt text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">No Uniforms Available</h3>
                        <p class="text-gray-600 mb-4">We currently don't have any uniforms in stock. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="uniforms-container">
                        <?php foreach($uniforms as $uniform): ?>
                            <div class="uniform-card bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 transition duration-300"
                                 data-id="<?php echo $uniform['id']; ?>"
                                 data-type="<?php echo strtolower($uniform['type']); ?>"
                                 data-school="<?php echo $uniform['school_id']; ?>"
                                 data-price="<?php echo $uniform['price']; ?>">
                                <div class="relative h-48 overflow-hidden">
                                    <?php 
                                    $imageMap = [
                                        'sweater' => 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80',
                                        'shirt' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80',
                                        'dress' => 'https://images.unsplash.com/photo-1551232864-3f0890e580d9?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80',
                                        'trouser' => 'https://images.unsplash.com/photo-1596704017255-ee2d5a1c7c2b?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80',
                                        'default' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'
                                    ];
                                    $image = $imageMap[strtolower($uniform['type'])] ?? $imageMap['default'];
                                    ?>
                                    <img src="<?php echo $image; ?>" alt="<?php echo $uniform['type']; ?>" class="w-full h-full object-cover">
                                    <?php if($uniform['stock_quantity'] < 5): ?>
                                        <div class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                            Low Stock
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-lg font-bold text-gray-800"><?php echo $uniform['type']; ?></h3>
                                        <span class="text-blue-600 font-bold">KSh <?php echo number_format($uniform['price'], 2); ?></span>
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 mb-3">
                                        <p class="mb-1"><span class="font-medium">School:</span> <?php echo $uniform['school_name'] ?? 'Generic'; ?></p>
                                        <p class="mb-1"><span class="font-medium">Size:</span> <?php echo $uniform['size'] ?? 'Various'; ?></p>
                                        <p class="mb-1"><span class="font-medium">Color:</span> <?php echo $uniform['color'] ?? 'Standard'; ?></p>
                                        <p class="mb-1"><span class="font-medium">Quality:</span> <?php echo $uniform['quality'] ?? 'High'; ?></p>
                                        <p class="mb-1"><span class="font-medium">In Stock:</span> <?php echo $uniform['stock_quantity']; ?></p>
                                        <?php if(!empty($uniform['barcode'])): ?>
                                            <p class="mb-1"><span class="font-medium">Barcode:</span> <?php echo $uniform['barcode']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form class="add-to-cart-form" data-uniform-id="<?php echo $uniform['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="item_id" value="<?php echo $uniform['id']; ?>">
                                        <input type="hidden" name="table_source" value="<?php echo $uniform['table']; ?>">
                                        <input type="hidden" name="uniform_type" value="<?php echo $uniform['type']; ?>">
                                        <input type="hidden" name="price" value="<?php echo $uniform['price']; ?>">
                                        <input type="hidden" name="school_id" value="<?php echo $uniform['school_id']; ?>">
                                        <input type="hidden" name="add_to_cart" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium mb-1">Size:</label>
                                            <div class="flex flex-wrap gap-2">
                                                <?php
                                                $sizesQuery = "SELECT DISTINCT size FROM {$uniform['table']} WHERE id = ? AND quantity > 0";
                                                $stmt = $conn->prepare($sizesQuery);
                                                $stmt->execute([$uniform['id']]);
                                                $sizes = $stmt->fetchAll();
                                                
                                                foreach($sizes as $size): ?>
                                                    <label class="size-option border border-gray-300 rounded px-3 py-1 text-sm cursor-pointer">
                                                        <input type="radio" name="size" value="<?php echo $size['size']; ?>" required class="hidden">
                                                        <?php echo $size['size']; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <button type="button" class="quantity-btn minus bg-gray-200 px-3 py-1 rounded-l">-</button>
                                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $uniform['stock_quantity']; ?>" class="w-12 text-center border-t border-b border-gray-300 py-1">
                                                <button type="button" class="quantity-btn plus bg-gray-200 px-3 py-1 rounded-r">+</button>
                                            </div>
                                            <button type="button" 
        onclick="window.location.href='add_to_cart.php'" 
        class="flex items-center gap-2 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
  <i class="fas fa-cart-plus"></i>
  <span>Add to Cart</span>
</button>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add to Cart Success Modal -->
    <div id="cart-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Added to Cart</h3>
                <button id="close-cart-modal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="cart-item-details" class="mb-6">
                <!-- Content will be added by JavaScript -->
            </div>
            
            <div class="flex space-x-4">
                <button id="continue-shopping" class="flex-1 bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300 transition">
                    Continue Shopping
                </button>
                <a href="cart.php" class="flex-1 bg-orange-500 text-white py-2 px-4 rounded-md text-center hover:bg-orange-600 transition">
                    View Cart
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-blue-800 text-white py-8 mt-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-bold mb-4">Mosoriot Uniforms</h3>
                    <p class="text-gray-300 text-sm">Providing quality school uniforms to Mosoriot families since 2010.</p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2 text-sm text-gray-300">
                        <li><a href="index.php" class="hover:text-white transition">Home</a></li>
                        <li><a href="uniforms.php" class="hover:text-white transition">Uniforms</a></li>
                        <li><a href="orders.php" class="hover:text-white transition">My Orders</a></li>
                        <li><a href="contact.php" class="hover:text-white transition">Contact Us</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Contact</h3>
                    <ul class="space-y-2 text-sm text-gray-300">
                        <li><i class="fas fa-map-marker-alt mr-2"></i> Mosoriot Town Center</li>
                        <li><i class="fas fa-phone-alt mr-2"></i> +254 792 264 952</li>
                        <li><i class="fas fa-envelope mr-2"></i> info@mosoriotuniforms.co.ke</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-blue-700 mt-8 pt-6 text-center text-gray-300 text-sm">
                <p>&copy; <?php echo date('Y'); ?> Mosoriot Uniforms Depot. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Quantity buttons
            $(document).on('click', '.quantity-btn', function() {
                const input = $(this).siblings('input[name="quantity"]');
                let value = parseInt(input.val());
                const max = parseInt(input.attr('max'));
                
                if($(this).hasClass('minus') && value > 1) {
                    input.val(value - 1);
                } else if($(this).hasClass('plus') && value < max) {
                    input.val(value + 1);
                }
            });

            // Size selection
            $(document).on('click', '.size-option', function() {
                $(this).siblings().removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            // Add to cart functionality with redirect option
            $(document).on('submit', '.add-to-cart-form', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = form.serialize();
                const submitBtn = form.find('button[type="submit"]');
                const originalText = submitBtn.html();
                
                // Show loading state
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Adding...');
                
                $.ajax({
                    url: 'uniforms.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            // Update cart count in navigation
                            $('.cart-count').text(response.cart_count || '0');
                            
                            // Show success modal with item details
                            const itemName = form.find('input[name="uniform_type"]').val();
                            const itemSize = form.find('input[name="size"]:checked').val();
                            const itemQuantity = form.find('input[name="quantity"]').val();
                            const itemPrice = form.find('input[name="price"]').val();
                            const itemImage = form.closest('.uniform-card').find('img').attr('src');
                            
                            $('#cart-item-details').html(`
                                <div class="flex items-start mb-4">
                                    <img src="${itemImage}" class="w-16 h-16 object-cover rounded-md mr-4">
                                    <div>
                                        <h4 class="font-bold">${itemName}</h4>
                                        <p class="text-sm text-gray-600">Size: ${itemSize}</p>
                                        <p class="text-sm text-gray-600">Qty: ${itemQuantity}</p>
                                        <p class="text-blue-600 font-bold">KSh ${parseFloat(itemPrice).toFixed(2)} each</p>
                                    </div>
                                </div>
                                <div class="bg-gray-100 p-3 rounded-md">
                                    <p class="text-sm">${response.cart_count} item(s) in cart</p>
                                    <p class="font-bold">Total: KSh ${(parseFloat(itemPrice) * parseInt(itemQuantity)).toFixed(2)}</p>
                                </div>
                            `);
                            
                            $('#cart-modal').removeClass('hidden').addClass('flex');
                        } else {
                            alert('Error: ' + (response.message || 'Failed to add item to cart'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('An error occurred: ' + error);
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Close cart modal
            $('#close-cart-modal, #continue-shopping').on('click', function() {
                $('#cart-modal').removeClass('flex').addClass('hidden');
            });

            // Filter uniforms
            $('#school-filter, #type-filter, #price-range, #in-stock-only').on('change input', function() {
                const schoolFilter = $('#school-filter').val();
                const typeFilter = $('#type-filter').val();
                const priceFilter = $('#price-range').val();
                const inStockOnly = $('#in-stock-only').is(':checked');
                
                $('.uniform-card').each(function() {
                    const school = $(this).data('school') || '';
                    const type = $(this).data('type');
                    const price = $(this).data('price');
                    const stock = $(this).find('input[name="quantity"]').attr('max');
                    
                    const schoolMatch = !schoolFilter || school == schoolFilter;
                    const typeMatch = !typeFilter || type === typeFilter;
                    const priceMatch = price <= priceFilter;
                    const stockMatch = !inStockOnly || stock > 0;
                    
                    if(schoolMatch && typeMatch && priceMatch && stockMatch) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Search uniforms
            $('#search-uniforms').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                
                $('.uniform-card').each(function() {
                    const text = $(this).text().toLowerCase();
                    if(text.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Update price range display
            $('#price-range').on('input', function() {
                const value = $(this).val();
                $('#price-max').text(value == 5000 ? 'KSh 5000+' : `KSh ${value}`);
            });
        });
    </script>
</body>
</html>