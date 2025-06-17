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
require __DIR__ . '/db_connection.php'; // Ensure this path is correct

// Initialize message variable
$message = "";

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid request. Please try again.";
    } else {
        // Sanitize and validate inputs
        $email = trim($_POST["email"]);
        $username = trim($_POST["username"]);
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];
        $role = trim($_POST["role"]);
        $admin_code = trim($_POST["admin_code"] ?? "");

        // Check if passwords match
        if ($password !== $confirm_password) {
            $message = "Passwords do not match!";
        } else {
            // Validate password length (changed from 8 to 4)
            if (strlen($password) < 4) {
                $message = "Password must be at least 4 characters long!";
            } else {
                // Validate admin code if role is admin
                if ($role === "admin" && $admin_code !== "ADMIN123") { // Replace "ADMIN123" with your unique admin code
                    $message = "Invalid admin code!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    try {
                        // Check if email already exists
                        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $message = "Email already exists!";
                        } else {
                            // Insert user with role and admin code
                            $stmt = $conn->prepare("INSERT INTO users (email, password, role, username, admin_code) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$email, $hashed_password, $role, $username, $role === "admin" ? $admin_code : null]);

                            // Redirect to login page after successful registration
                            header("Location: login.php?signup=success");
                            exit();
                        }
                    } catch (PDOException $e) {
                        // Log the error and display a generic message
                        error_log("Database error: " . $e->getMessage());
                        $message = "An error occurred. Please try again later. Error: " . $e->getMessage(); // Display the actual error for debugging
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Uniform Stock Management</title>
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
        .strength-meter {
            height: 5px;
            width: 100%;
            margin-top: 5px;
            background-color: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .validation-message {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .valid {
            color: #10b981; /* green-500 */
        }
        
        .invalid {
            color: #ef4444; /* red-500 */
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
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="flex flex-col md:flex-row min-h-screen">
    <!-- Left side - Infographics -->
    <div class="w-full md:w-1/2 navy-bg text-white p-8 flex flex-col justify-center">
        <div class="max-w-md mx-auto" data-aos="fade-right">
            <div class="flex items-center mb-8">
                <div class="bg-blue p-3 rounded-lg mr-4">
                    <i class="fas fa-tshirt text-white text-2xl floating"></i>
                </div>
                <h1 class="text-3xl font-bold">Uniform Stock Management</h1>
            </div>
            
            <h2 class="text-2xl font-bold mb-6">Create Your Account</h2>
            
            <div class="space-y-8 mb-12">
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="100">
                    <h3 class="text-xl font-semibold mb-2">Fill in Your Details</h3>
                    <p class="text-gray-300">Provide your username, email address, and a secure password.</p>
                </div>
                
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="200">
                    <h3 class="text-xl font-semibold mb-2">Choose Your Role</h3>
                    <p class="text-gray-300">Select whether you're registering as a customer or administrator.</p>
                </div>
                
                <div class="infographic-step" data-aos="fade-up" data-aos-delay="300">
                    <h3 class="text-xl font-semibold mb-2">Complete Registration</h3>
                    <p class="text-gray-300">Click register and start managing your uniform stock.</p>
                </div>
            </div>
            
            <div class="bg-blue bg-opacity-20 p-6 rounded-lg border-l-4 border-orange" data-aos="fade-up">
                <div class="flex items-center">
                    <div class="bg-orange text-white p-3 rounded-full mr-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">Secure Registration</h3>
                        <p class="text-gray-300 text-sm">Your information is protected with industry-standard security measures.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right side - Registration Form -->
    <div class="w-full md:w-1/2 flex items-center justify-center p-8">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg overflow-hidden" data-aos="fade-left">
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Create Account</h2>
                    <p class="text-gray-600">Join our uniform stock management system</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" data-aos="fade-up">
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6" id="registrationForm">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Username Field -->
                    <div data-aos="fade-up" data-aos-delay="100">
                        <label class="block text-gray-700 font-medium mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="username" id="username" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="Enter your username" required>
                        </div>
                        <div id="usernameValidation" class="validation-message"></div>
                    </div>

                    <!-- Email Field -->
                    <div data-aos="fade-up" data-aos-delay="150">
                        <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" name="email" id="email" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="your@email.com" required>
                        </div>
                        <div id="emailValidation" class="validation-message"></div>
                    </div>

                    <!-- Password Field -->
                    <div data-aos="fade-up" data-aos-delay="200">
                        <label class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="password" id="password" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="••••••••" required>
                        </div>
                        <div class="strength-meter">
                            <div id="strengthMeterFill" class="strength-meter-fill"></div>
                        </div>
                        <div id="passwordValidation" class="validation-message"></div>
                        <div id="passwordTips" class="text-xs text-gray-500 mt-1">
                            Password should contain at least 4 characters. For better security, include numbers, special characters, and mixed case letters.
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div data-aos="fade-up" data-aos-delay="250">
                        <label class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="confirm_password" id="confirm_password" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="••••••••" required>
                        </div>
                        <div id="confirmPasswordValidation" class="validation-message"></div>
                    </div>

                    <!-- Role Dropdown -->
                    <div data-aos="fade-up" data-aos-delay="300">
                        <label class="block text-gray-700 font-medium mb-2">Role</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user-tag text-gray-400"></i>
                            </div>
                            <select name="role" id="role" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent appearance-none" onchange="toggleAdminCode()" required>
                                <option value="customer">Customer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Admin Code Field (Hidden by Default) -->
                    <div id="adminCodeField" class="hidden" data-aos="fade-up" data-aos-delay="350">
                        <label class="block text-gray-700 font-medium mb-2">Admin Code</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-400"></i>
                            </div>
                            <input type="text" name="admin_code" id="admin_code" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue focus:border-transparent" placeholder="Enter admin code">
                        </div>
                        <div id="adminCodeValidation" class="validation-message"></div>
                    </div>

                    <!-- Submit Button -->
                    <div data-aos="fade-up" data-aos-delay="400">
                        <button type="submit" id="submitButton" class="w-full btn-primary text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition confetti-btn">
                            <i class="fas fa-user-plus mr-2"></i> Register
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center" data-aos="fade-up" data-aos-delay="450">  
    <p class="text-gray-600 mb-3">Already a member?</p>  
    <a href="login.php" class="inline-block px-6 py-2 rounded-full border-2 border-blue-500 text-blue-600 font-medium hover:bg-blue-50 transition-all duration-300 hover:shadow-sm">  
        Sign In to Your Account  
    </a>  
    <p class="text-gray-400 text-xs mt-2">Welcome back!</p>  
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

        // Show/hide admin code field based on role selection
        function toggleAdminCode() {
            const role = document.getElementById("role").value;
            const adminCodeField = document.getElementById("adminCodeField");
            if (role === "admin") {
                adminCodeField.classList.remove("hidden");
                adminCodeField.classList.add("block");
            } else {
                adminCodeField.classList.remove("block");
                adminCodeField.classList.add("hidden");
            }
        }

        // DOM elements
        const usernameInput = document.getElementById("username");
        const emailInput = document.getElementById("email");
        const passwordInput = document.getElementById("password");
        const confirmPasswordInput = document.getElementById("confirm_password");
        const adminCodeInput = document.getElementById("admin_code");
        const submitButton = document.getElementById("submitButton");
        const form = document.getElementById("registrationForm");

        // Validation elements
        const usernameValidation = document.getElementById("usernameValidation");
        const emailValidation = document.getElementById("emailValidation");
        const passwordValidation = document.getElementById("passwordValidation");
        const confirmPasswordValidation = document.getElementById("confirmPasswordValidation");
        const adminCodeValidation = document.getElementById("adminCodeValidation");
        const strengthMeterFill = document.getElementById("strengthMeterFill");

        // Validation flags
        let isUsernameValid = false;
        let isEmailValid = false;
        let isPasswordValid = false;
        let isConfirmPasswordValid = false;
        let isAdminCodeValid = true; // Default to true since it's optional

        // Username validation
        usernameInput.addEventListener("input", function() {
            const username = this.value;
            const minLength = 4;
            const regex = /^[a-zA-Z0-9]+$/;
            
            if (username.length < minLength) {
                usernameValidation.textContent = `Username must be at least ${minLength} characters long`;
                usernameValidation.className = "validation-message invalid";
                isUsernameValid = false;
            } else if (!/[a-zA-Z]/.test(username)) {
                usernameValidation.textContent = "Username must contain at least one letter";
                usernameValidation.className = "validation-message invalid";
                isUsernameValid = false;
            } else if (!regex.test(username)) {
                usernameValidation.textContent = "Username can only contain letters and numbers";
                usernameValidation.className = "validation-message invalid";
                isUsernameValid = false;
            } else {
                usernameValidation.textContent = "Username is valid";
                usernameValidation.className = "validation-message valid";
                isUsernameValid = true;
            }
            updateSubmitButton();
        });

        // Email validation
        emailInput.addEventListener("input", function() {
            const email = this.value;
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (regex.test(email)) {
                emailValidation.textContent = "Email is valid";
                emailValidation.className = "validation-message valid";
                isEmailValid = true;
            } else {
                emailValidation.textContent = "Please enter a valid email address";
                emailValidation.className = "validation-message invalid";
                isEmailValid = false;
            }
            updateSubmitButton();
        });

        // Password strength calculation
        passwordInput.addEventListener("input", function() {
            const password = this.value;
            const minLength = 4;
            
            // Reset strength meter
            let strength = 0;
            
            // Length check
            if (password.length >= minLength) {
                strength += 1;
            }
            
            // Contains number
            if (/\d/.test(password)) {
                strength += 1;
            }
            
            // Contains special character
            if (/[!@#$%^&*()?":{}|<>]/.test(password)) {
                strength += 1;
            }
            
            // Mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
                strength += 1;
            }
            
            // Update strength meter
            let width = 0;
            let color = "#ef4444"; // red
            
            if (strength === 1) {
                width = 25;
                color = "#ef4444"; // red
            } else if (strength === 2) {
                width = 50;
                color = "#f59e0b"; // amber
            } else if (strength === 3) {
                width = 75;
                color = "#3b82f6"; // blue
            } else if (strength >= 4) {
                width = 100;
                color = "#10b981"; // emerald
            }
            
            strengthMeterFill.style.width = `${width}%`;
            strengthMeterFill.style.backgroundColor = color;
            
            // Basic validation (minimum length)
            if (password.length < minLength) {
                passwordValidation.textContent = `Password must be at least ${minLength} characters long`;
                passwordValidation.className = "validation-message invalid";
                isPasswordValid = false;
            } else {
                passwordValidation.textContent = strength >= 3 ? "Password is strong" : "Password is weak";
                passwordValidation.className = strength >= 3 ? "validation-message valid" : "validation-message invalid";
                isPasswordValid = true;
            }
            
            // Update password confirmation if needed
            if (confirmPasswordInput.value) {
                validatePasswordConfirmation();
            }
            
            updateSubmitButton();
        });

        // Password confirmation validation
        confirmPasswordInput.addEventListener("input", validatePasswordConfirmation);
        
        function validatePasswordConfirmation() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword === password) {
                confirmPasswordValidation.textContent = "Passwords match";
                confirmPasswordValidation.className = "validation-message valid";
                isConfirmPasswordValid = true;
            } else {
                confirmPasswordValidation.textContent = "Passwords do not match";
                confirmPasswordValidation.className = "validation-message invalid";
                isConfirmPasswordValid = false;
            }
            updateSubmitButton();
        }

        // Admin code validation
        if (adminCodeInput) {
            adminCodeInput.addEventListener("input", function() {
                const adminCode = this.value;
                
                if (document.getElementById("role").value === "admin") {
                    if (adminCode === "ADMIN123") {
                        adminCodeValidation.textContent = "Admin code is valid";
                        adminCodeValidation.className = "validation-message valid";
                        isAdminCodeValid = true;
                    } else {
                        adminCodeValidation.textContent = "Invalid admin code";
                        adminCodeValidation.className = "validation-message invalid";
                        isAdminCodeValid = false;
                    }
                    updateSubmitButton();
                }
            });
        }

        // Update submit button state
        function updateSubmitButton() {
            const role = document.getElementById("role").value;
            const allValid = isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid && 
                           (role !== "admin" || isAdminCodeValid);
            
            submitButton.disabled = !allValid;
            if (allValid) {
                submitButton.className = "w-full btn-primary text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition confetti-btn";
            } else {
                submitButton.className = "w-full bg-gray-400 text-white font-bold py-3 px-4 rounded-lg cursor-not-allowed";
            }
        }

        // Form submission validation
        form.addEventListener("submit", function(event) {
            // Final check before submission
            if (!isUsernameValid || !isEmailValid || !isPasswordValid || !isConfirmPasswordValid) {
                event.preventDefault();
                return false;
            }
            
            if (document.getElementById("role").value === "admin" && !isAdminCodeValid) {
                event.preventDefault();
                return false;
            }
            
            // Confetti celebration on successful submission
            confetti({
                particleCount: 80,
                spread: 60,
                origin: { y: 0.6 },
                colors: ['#FF851B', '#0074D9', '#001F3F', '#FFFFFF']
            });
            
            return true;
        });

        // Initialize form validation
        toggleAdminCode();
        updateSubmitButton();
    </script>
</body>
</html>