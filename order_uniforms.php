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

// Uniform types mapping (should match your database)
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

// Process order submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['place_order'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid form submission");
    }

    // Validate required fields
    $required = ['uniform_id', 'uniform_type', 'quantity', 'delivery_option', 'school_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die("Missing required field: $field");
        }
    }

    // Sanitize inputs
    $uniform_id = (int)$_POST['uniform_id'];
    $uniform_type = $_POST['uniform_type'];
    $quantity = (int)$_POST['quantity'];
    $delivery_option = $_POST['delivery_option'];
    $school_id = (int)$_POST['school_id'];

    // Validate uniform type exists
    if (!array_key_exists($uniform_type, $uniformTypes)) {
        die("Invalid uniform type");
    }

    // Process order
    try {
        $conn->beginTransaction();
        
        // 1. Get school information
        $stmt = $conn->prepare("SELECT name FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch();
        
        if (!$school) {
            throw new Exception("Selected school not found");
        }
        $school_name = $school['name'];

        // 2. Get uniform details and verify stock
        $table = $uniformTypes[$uniform_type];
        $stmt = $conn->prepare("SELECT price, quantity FROM $table WHERE id = ?");
        $stmt->execute([$uniform_id]);
        $uniform = $stmt->fetch();
        
        if (!$uniform) {
            throw new Exception("Selected uniform not found");
        }
        if ($uniform['quantity'] < $quantity) {
            throw new Exception("Not enough stock available");
        }

        // 3. Calculate prices
        $base_price = $uniform['price'];
        $delivery_fee = 0;
        if ($delivery_option === 'Home Delivery') $delivery_fee = 200;
        if ($delivery_option === 'Express Delivery') $delivery_fee = 500;
        $total_price = ($base_price * $quantity) + $delivery_fee;

        // 4. Create order record (using proper column names from your schema)
        $stmt = $conn->prepare("INSERT INTO orders (
            user_id, 
            uniform_id, 
            quantity, 
            total_price, 
            delivery_option, 
            school_name,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $uniform_id,
            $quantity,
            $total_price,
            $delivery_option,
            $school_name
        ]);
        
        $order_id = $conn->lastInsertId();

        // 5. Update stock
        $stmt = $conn->prepare("UPDATE $table SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$quantity, $uniform_id]);

        $conn->commit();
        
        // Store order confirmation in session
        $_SESSION['order_confirmation'] = [
            'order_id' => $order_id,
            'uniform_type' => $uniform_type,
            'quantity' => $quantity,
            'total_price' => $total_price,
            'delivery_option' => $delivery_option,
            'school_name' => $school_name
        ];
        
        header("Location: order_confirmation.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Order failed: " . $e->getMessage());
    }
}

// Fetch data for the form
$schools = $conn->query("SELECT id, name FROM schools")->fetchAll();
$uniforms = [];
foreach ($uniformTypes as $type => $table) {
    $result = $conn->query("SELECT id, price, size, color, quantity FROM $table WHERE quantity > 0");
    while ($row = $result->fetch()) {
        $row['type'] = $type;
        $uniforms[] = $row;
    }
}

// Get exchange rates (you might want to cache this in production)
$exchangeRates = [
    'USD' => 0.0078,  // 1 KES = 0.0078 USD (example rate)
    'EUR' => 0.0072,  // 1 KES = 0.0072 EUR (example rate)
    'GBP' => 0.0061,  // 1 KES = 0.0061 GBP (example rate)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Uniforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'amazon-blue': '#146EB4',
                        'amazon-navy': '#131921',
                        'amazon-light': '#232F3E',
                        'amazon-yellow': '#FFD814',
                        'amazon-orange': '#FFA41C',
                    }
                }
            }
        }
    </script>
    <style>
        .uniform-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .selected {
            border-color: #146EB4;
            background-color: #E1F0FA;
        }
        .sidebar-item:hover {
            background-color: #37475A;
            border-left: 4px solid #FFA41C;
        }
        .sidebar-item.active {
            background-color: #37475A;
            border-left: 4px solid #FFA41C;
        }
        .amazon-search:focus {
            box-shadow: 0 0 0 3px rgba(255, 168, 28, 0.5);
        }
        .nav-link:hover {
            color: #FFA41C;
        }
        .currency-selector {
            background-color: #f0f2f5;
            border-radius: 4px;
            padding: 2px;
        }
        .currency-option {
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .currency-option.active {
            background-color: #146EB4;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="bg-amazon-navy text-white">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="#" class="text-2xl font-bold text-white flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amazon-yellow" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-2">UniformShop</span>
                </a>
            </div>
            
            <div class="flex items-center space-x-6">
                <!-- Currency Selector -->
                <div class="currency-selector flex items-center">
                    <div class="currency-option active" data-currency="KES">KES</div>
                    <div class="currency-option" data-currency="USD">USD</div>
                    <div class="currency-option" data-currency="EUR">EUR</div>
                    <div class="currency-option" data-currency="GBP">GBP</div>
                </div>
                
                <div class="relative">
                    <span class="text-sm text-gray-300">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                </div>
                <a href="../authentication/logout.php" class="flex items-center text-sm hover:text-amazon-yellow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l3-3a1 1 0 10-1.414-1.414L14 7.586l-2.293-2.293a1 1 0 10-1.414 1.414L12.586 9H7a1 1 0 100 2h5.586l-1.293 1.293z" clip-rule="evenodd" />
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </header>

     <!-- Secondary Navigation -->
     <div class="bg-amazon-light text-white">
        <div class="container mx-auto px-4 py-2 flex items-center space-x-6 overflow-x-auto">
            <a href="customer_dashboard.php" class="text-sm font-medium text-amazon-yellow whitespace-nowrap">🏠 Dashboard</a>
            <a href="order_uniforms.php" class="text-sm nav-link whitespace-nowrap">🛒 Order Uniforms</a>
            <a href="order_status.php" class="text-sm nav-link whitespace-nowrap">📦 My Orders</a>
            <a href="order_history.php" class="text-sm nav-link whitespace-nowrap">📜 Order History</a>
            <a href="customer_settings.php" class="text-sm nav-link whitespace-nowrap">⚙️ Settings</a>
        </div>
    </div>

    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-amazon-navy text-white min-h-screen hidden md:block">
            <div class="p-4">
                <div class="flex items-center space-x-3 p-3 mb-6">
                    <img src="../uploads/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default_avatar.png') ?>" 
                         alt="Customer Avatar" class="w-10 h-10 rounded-full object-cover">
                    <div>
                        
                        <p class="text-xs text-gray-400">Customer</p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="customer_dashboard.php" class="block sidebar-item active py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                    <a href="order_uniforms.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-boxes mr-2"></i> Order Uniforms
                    </a>
                    <a href="order_status.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-clipboard-list mr-2"></i> My Orders
                    </a>
                    <a href="order_history.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-history mr-2"></i> Order History
                    </a>
                    <a href="customer_settings.php" class="block sidebar-item py-2 px-3 text-sm rounded transition duration-200">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-amazon-navy">Order Uniforms</h1>
                    <div class="relative w-64">
                        <input type="text" id="searchInput" placeholder="Search uniforms..." class="w-full px-4 py-2 rounded-lg border border-gray-300 amazon-search focus:outline-none">
                        <button class="absolute right-0 top-0 h-full px-3 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form method="post" id="orderForm" class="space-y-8">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <!-- Step 1: Select Uniform -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <div class="bg-amazon-blue px-6 py-3">
                            <h2 class="text-lg font-semibold text-white">1. Select Uniform</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="uniforms-container">
                                <?php foreach ($uniforms as $uniform): ?>
                                    <div class="uniform-item border border-gray-200 rounded-lg p-4 cursor-pointer transition duration-200"
                                         data-id="<?= $uniform['id'] ?>" 
                                         data-type="<?= $uniform['type'] ?>" 
                                         data-price="<?= $uniform['price'] ?>"
                                         data-stock="<?= $uniform['quantity'] ?>">
                                        <h3 class="font-medium text-lg text-amazon-navy"><?= htmlspecialchars($uniform['type']) ?></h3>
                                        <div class="mt-2 text-sm text-gray-600">
                                            <p><span class="font-medium">Size:</span> <?= htmlspecialchars($uniform['size'] ?? 'N/A') ?></p>
                                            <p><span class="font-medium">Color:</span> <?= htmlspecialchars($uniform['color'] ?? 'N/A') ?></p>
                                            <p class="price-display"><span class="font-medium">Price:</span> <span class="currency-symbol">KSh</span> <span class="price-value"><?= number_format($uniform['price'], 2) ?></span></p>
                                            <p><span class="font-medium">Available:</span> <?= $uniform['quantity'] ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="uniform_id" id="uniform_id">
                            <input type="hidden" name="uniform_type" id="uniform_type">
                        </div>
                    </div>
                    
                    <!-- Step 2: Select School -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <div class="bg-amazon-blue px-6 py-3">
                            <h2 class="text-lg font-semibold text-white">2. Select School</h2>
                        </div>
                        <div class="p-6">
                            <select name="school_id" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                                <option value="">-- Select School --</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Step 3: Quantity -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <div class="bg-amazon-blue px-6 py-3">
                            <h2 class="text-lg font-semibold text-white">3. Quantity</h2>
                        </div>
                        <div class="p-6">
                            <input type="number" name="quantity" id="quantity" min="1" value="1" required 
                                   class="w-24 px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                            <div id="stockMessage" class="mt-2 text-sm text-red-600"></div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Delivery Option -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <div class="bg-amazon-blue px-6 py-3">
                            <h2 class="text-lg font-semibold text-white">4. Delivery Option</h2>
                        </div>
                        <div class="p-6">
                            <select name="delivery_option" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-amazon-blue focus:border-amazon-blue">
                                <option value="Pickup">Pickup (Free)</option>
                                <option value="Home Delivery">Home Delivery (+<span class="currency-symbol">KSh</span> <span class="delivery-fee">200</span>)</option>
                                <option value="Express Delivery">Express Delivery (+<span class="currency-symbol">KSh</span> <span class="delivery-fee">500</span>)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <div class="bg-amazon-blue px-6 py-3">
                            <h2 class="text-lg font-semibold text-white">Order Summary</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Items:</span>
                                <span id="summary-items" class="font-medium">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Unit Price:</span>
                                <span><span class="currency-symbol">KSh</span> <span id="summary-price" class="font-medium">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Quantity:</span>
                                <span id="summary-quantity" class="font-medium">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal:</span>
                                <span><span class="currency-symbol">KSh</span> <span id="summary-subtotal" class="font-medium">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Delivery:</span>
                                <span><span class="currency-symbol">KSh</span> <span id="summary-delivery" class="font-medium">0.00</span></span>
                            </div>
                            <div class="border-t border-gray-200 pt-4 flex justify-between text-lg font-bold">
                                <span>Total:</span>
                                <span><span class="currency-symbol">KSh</span> <span id="summary-total" class="text-amazon-blue">0.00</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" name="place_order" id="place_order_btn"
                                class="bg-amazon-orange hover:bg-yellow-600 text-white font-semibold py-3 px-8 rounded-md transition duration-200">
                            Place Order
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
    // Define exchange rates (you might want to fetch these from an API in a real application)
    const exchangeRates = <?php echo json_encode($exchangeRates); ?>;
    exchangeRates['KES'] = 1; // Base currency
    
    // Currency symbols
    const currencySymbols = {
        'KES': 'KSh',
        'USD': '$',
        'EUR': '€',
        'GBP': '£'
    };
    
    // Current selected currency
    let currentCurrency = 'KES';
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('orderForm');
        const uniformItems = document.querySelectorAll('.uniform-item');
        const quantityInput = document.getElementById('quantity');
        const stockMessage = document.getElementById('stockMessage');
        const searchInput = document.getElementById('searchInput');
        const placeOrderBtn = document.getElementById("place_order_btn");
        const currencyOptions = document.querySelectorAll('.currency-option');

        let selectedUniform = null;

        // Currency selection
        currencyOptions.forEach(option => {
            option.addEventListener('click', function() {
                currencyOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                currentCurrency = this.dataset.currency;
                updateAllPrices();
            });
        });

        // Uniform selection
        uniformItems.forEach(item => {
            item.addEventListener('click', function() {
                uniformItems.forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');

                selectedUniform = {
                    id: this.dataset.id,
                    type: this.dataset.type,
                    price: parseFloat(this.dataset.price),
                    stock: parseInt(this.dataset.stock)
                };

                document.getElementById('uniform_id').value = selectedUniform.id;
                document.getElementById('uniform_type').value = selectedUniform.type;

                // Update quantity max value
                quantityInput.max = selectedUniform.stock;
                quantityInput.value = 1;
                stockMessage.textContent = '';

                updateSummary();
            });
        });

        // Quantity validation
        quantityInput.addEventListener('input', function() {
            if (selectedUniform) {
                const quantity = parseInt(this.value) || 0;
                if (quantity > selectedUniform.stock) {
                    stockMessage.textContent = `Only ${selectedUniform.stock} available`;
                } else {
                    stockMessage.textContent = '';
                }
                updateSummary();
            }
        });

        // Delivery option change
        document.querySelector('[name="delivery_option"]').addEventListener('change', updateSummary);

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            uniformItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Form submission validation
        form.addEventListener('submit', function(e) {
            if (!selectedUniform) {
                e.preventDefault();
                alert('Please select a uniform');
                return;
            }

            const quantity = parseInt(quantityInput.value);
            if (quantity > selectedUniform.stock) {
                e.preventDefault();
                stockMessage.textContent = `Cannot order more than available stock (${selectedUniform.stock})`;
            }
        });

        function updateAllPrices() {
            // Update currency symbols
            document.querySelectorAll('.currency-symbol').forEach(el => {
                el.textContent = currencySymbols[currentCurrency];
            });
            
            // Update uniform item prices
            document.querySelectorAll('.price-value').forEach(el => {
                const originalPrice = parseFloat(el.dataset.originalPrice || el.textContent.replace(/,/g, ''));
                el.dataset.originalPrice = originalPrice;
                const convertedPrice = convertCurrency(originalPrice);
                el.textContent = convertedPrice.toFixed(2);
            });
            
            // Update delivery fees
            document.querySelectorAll('.delivery-fee').forEach(el => {
                const originalFee = parseFloat(el.dataset.originalFee || el.textContent);
                el.dataset.originalFee = originalFee;
                const convertedFee = convertCurrency(originalFee);
                el.textContent = convertedFee.toFixed(2);
            });
            
            // Update summary if there's a selected uniform
            if (selectedUniform) {
                updateSummary();
            }
        }
        
        function convertCurrency(amount) {
            return amount * exchangeRates[currentCurrency] / exchangeRates['KES'];
        }

        function updateSummary() {
            if (!selectedUniform) return;

            const quantity = parseInt(quantityInput.value) || 0;
            const deliveryOption = document.querySelector('[name="delivery_option"]').value;

            // Get delivery fee in KES (original currency)
            let deliveryFeeKES = 0;
            if (deliveryOption === 'Home Delivery') deliveryFeeKES = 200;
            if (deliveryOption === 'Express Delivery') deliveryFeeKES = 500;
            
            const subtotalKES = selectedUniform.price * quantity;
            const totalKES = subtotalKES + deliveryFeeKES;
            
            // Convert to selected currency
            const subtotal = convertCurrency(subtotalKES);
            const deliveryFee = convertCurrency(deliveryFeeKES);
            const total = convertCurrency(totalKES);

            document.getElementById('summary-items').textContent = '1';
            document.getElementById('summary-price').textContent = convertCurrency(selectedUniform.price).toFixed(2);
            document.getElementById('summary-quantity').textContent = quantity;
            document.getElementById('summary-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('summary-delivery').textContent = deliveryFee.toFixed(2);
            document.getElementById('summary-total').textContent = total.toFixed(2);
        }

        if (placeOrderBtn) {
            placeOrderBtn.addEventListener("click", function(event) {
                event.preventDefault();

                // Always use KES for payment processing
                const amountElement = document.getElementById('summary-total');
                const amountText = amountElement.textContent;
                const amount = parseFloat(amountText) * (currentCurrency === 'KES' ? 1 : (1 / exchangeRates[currentCurrency]));
                
                const email = "nehemiahkibungei@gmail.com";
                const public_key = 'pk_live_504e9f8c8aebfa975ff87ba801235867f91f39f9';

                if (!amount || amount <= 0) {
                    alert("Invalid amount");
                    return;
                }

                const handler = PaystackPop.setup({
                    key: public_key,
                    email: email,
                    currency: "KES", // Always process in KES
                    amount: amount * 100,
                    callback: function(response) {
                        // Create a hidden form with payment reference
                        const hiddenForm = document.createElement('form');
                        hiddenForm.method = 'POST';
                        hiddenForm.action = 'order_confirmation.php';
                        
                        // Add CSRF token
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = document.querySelector('[name="csrf_token"]').value;
                        hiddenForm.appendChild(csrfInput);
            
                        // Add payment reference
                        const refInput = document.createElement('input');
                        refInput.type = 'hidden';
                        refInput.name = 'payment_reference';
                        refInput.value = response.reference;
                        hiddenForm.appendChild(refInput);
                        
                        // Add order details from session
                        const orderInput = document.createElement('input');
                        orderInput.type = 'hidden';
                        orderInput.name = 'order_confirmation';
                        orderInput.value = JSON.stringify(<?php echo json_encode($_SESSION['order_confirmation'] ?? []); ?>);
                        hiddenForm.appendChild(orderInput);
                        
                        document.body.appendChild(hiddenForm);
                        hiddenForm.submit();
                    },
                    onClose: function() {
                        alert('Transaction was not completed');
                    }
                });

                handler.openIframe(); // Open Paystack payment modal
            });
        }
        
        // Initialize all prices
        updateAllPrices();
    });
    </script>
</body>
</html>