<?php
// Secure session cookie settings - MUST BE BEFORE session_start()
$cookieParams = session_get_cookie_params();
session_set_cookie_params(
    $cookieParams["lifetime"],
    $cookieParams["path"],
    $cookieParams["domain"],
    true, // Secure flag (HTTPS only)
    true  // HttpOnly flag (prevents JavaScript access)
);

// Start session securely
session_start();
session_regenerate_id(true); // Prevent session fixation attacks

// Include the database configuration
$dbConnectionPath = $_SERVER['DOCUMENT_ROOT'] . '/uniform_stock_management/authentication/db_connection.php';
if (!file_exists($dbConnectionPath)) {
    die("Error: db_connection.php file not found at $dbConnectionPath");
}
require $dbConnectionPath;

if (!$conn) {
    die("Database connection failed!");
}

// Initialize message variable
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $email = htmlspecialchars(trim($_POST["email"])); // Prevent XSS
    $password = $_POST["password"];

    try {
        // Fetch user from the database
        $stmt = $conn->prepare("SELECT id, email, password, role, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/admin_dashboard.php");
                    exit();
                } elseif ($user['role'] === 'customer') {
                    header("Location: ../admin/customer_dashboard.php");
                    exit();
                } else {
                    $message = "Unauthorized role.";
                }
            } else {
                $message = "Invalid email or password!";
            }
        } else {
            $message = "Invalid email or password!";
        }
    } catch (PDOException $e) {
        // Log the error and display a generic message
        error_log("Database error: " . $e->getMessage());
        $message = "An error occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Uniform Stock Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include AOS animation library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #F9FAFB;
        }
        .btn-primary {
            background-color: #FF851B;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #E76F00;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(255, 133, 27, 0.4);
        }
        .btn-secondary {
            background-color: #0074D9;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #005EA6;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 116, 217, 0.4);
        }
        .navy-bg {
            background-color: #001F3F;
        }
        .infographic-step {
            position: relative;
            padding-left: 3rem;
        }
        .infographic-step:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            background-color: #0074D9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .infographic-step:nth-child(1):before {
            content: '1';
        }
        .infographic-step:nth-child(2):before {
            content: '2';
        }
        .infographic-step:nth-child(3):before {
            content: '3';
        }
        .confetti-btn:hover {
            animation: pulse 1.5s infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .floating {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body class="flex flex-col md:flex-row min-h-screen">
    <!-- Left side - Infographics -->
    <div class="w-full md:w-1/2 navy-bg text-white p-8 flex flex-col justify-center">
        <div class="max-w-md mx-auto" data-aos="fade-right">
            <div class="flex items-center mb-8">
                <div class="bg-blue p-3 rounded-lg mr-4">
                    <i class="fas fa-tshirt text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold">Uniform Stock Management</h1>
            </div>
            
            <h2 class="text-2xl font-bold mb-6">How to Login</h2>
            
            <div class="space-y-8 mb-12">
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="100">
                    <h3 class="text-xl font-semibold mb-2">Enter Your Credentials</h3>
                    <p class="text-gray-300">Provide your registered email address and password in the login form.</p>
                </div>
                
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="200">
                    <h3 class="text-xl font-semibold mb-2">Click Login</h3>
                    <p class="text-gray-300">Press the login button to securely access your account.</p>
                </div>
                
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="300">
                    <h3 class="text-xl font-semibold mb-2">Access Dashboard</h3>
                    <p class="text-gray-300">You'll be redirected to your personalized dashboard based on your role.</p>
                </div>
            </div>
            
            <div class="bg-blue bg-opacity-20 p-6 rounded-lg border-l-4 border-orange" data-aos="fade-up">
                <div class="flex items-center">
                    <div class="bg-orange text-white p-3 rounded-full mr-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">Secure Login</h3>
                        <p class="text-gray-300 text-sm">Your credentials are encrypted and protected.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right side - Login Form -->
    <div class="w-full md:w-1/2 flex items-center justify-center p-8">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg overflow-hidden" data-aos="fade-left">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Welcome Back</h2>
                    <p class="text-gray-600">Login to access your account</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" data-aos="fade-up">
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6">
                    <!-- Email Field -->
                    <div data-aos="fade-up" data-aos-delay="100">
                        <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" name="email" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div data-aos="fade-up" data-aos-delay="150">
                        <label class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="password" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="••••••••" required>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between" data-aos="fade-up" data-aos-delay="200">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue focus:ring-blue border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                        </div>
                        
                    </div>

                    <!-- Submit Button -->
                    <div data-aos="fade-up" data-aos-delay="250">
                        <button type="submit" class="w-full btn-primary text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition confetti-btn">
                            <i class="fas fa-sign-in-alt mr-2"></i> Login
                        </button>
                    </div>
                </form>

                <div class="mt-8 text-center" data-aos="fade-up" data-aos-delay="300">
    <p class="text-gray-600 mb-2">Ready to join our community?</p>
    <a href="register.php" class="inline-block px-6 py-2 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium shadow-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105">
        Create Your Free Account
    </a>
    <p class="text-gray-500 text-sm mt-3">It only takes 30 seconds!</p>
</div>
            
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
                <div class="flex justify-center space-x-6">
                    <a href="#" class="text-gray-400 hover:text-blue">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Include AOS animation library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Include confetti library for celebration effect -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Confetti effect for login button
        document.querySelectorAll('.confetti-btn').forEach(button => {
            button.addEventListener('click', function() {
                confetti({
                    particleCount: 80,
                    spread: 60,
                    origin: { y: 0.6 },
                    colors: ['#FF851B', '#0074D9', '#001F3F', '#FFFFFF']
                });
            });
        });

        // Add floating animation to logo
        const logo = document.querySelector('.fa-tshirt');
        if (logo) {
            logo.classList.add('floating');
        }
    </script>
</body>
</html>