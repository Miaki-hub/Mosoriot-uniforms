<?php
// Start session securely
session_start([
    'cookie_lifetime' => 86400, // 1 day
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Include the configuration file
$configPath = $_SERVER['DOCUMENT_ROOT'] . '/uniform_stock_management/config.php';
if (!file_exists($configPath)) {
    die("<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>
        <h2 style='color: #721c24;'>Configuration Error</h2>
        <p>The configuration file is missing or inaccessible. Please ensure <code>config.php</code> exists in the root directory.</p>
        <p>If you're the administrator, please restore the file from backup or contact your developer.</p>
        <p><small>Technical details: File not found at <code>{$configPath}</code></small></p>
    </div>");
}
require_once($configPath);

// Initialize variables
$errors = [];
$success = false;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        // Validate and sanitize email
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Check if email exists
            try {
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $success = true; // Always say success for security
                } else {
                    $user = $result->fetch_assoc();
                    
                    // Generate secure reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset details
                    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', $user['id'], $reset_token, $expires_at);
                    $stmt->execute();
                    
                    // Create reset link
                    $reset_link = "http://localhost/uniform_stock_management/authentication/reset_password.php?token={$reset_token}";
                    
                    // Save info in session (demo only)
                    $_SESSION['reset_info'] = [
                        'email' => $email,
                        'demo_link' => $reset_link,
                        'expires' => $expires_at
                    ];
                    
                    $success = true;
                }
                
                $_SESSION['forgot_success'] = 'If your email exists in our system, you will receive a password reset link shortly.';
                header('Location: forgot_password.php');
                exit();
                
            } catch (Exception $e) {
                error_log("Password Reset Error: " . $e->getMessage());
                $errors[] = 'A system error occurred. Please try again later.';
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
    <title>Forgot Password - UniformStock Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styling same as you sent */
        :root { --primary: #4361ee; --secondary: #3f37c9; --light: #f8f9fa; --dark: #212529; --success: #4cc9f0; --danger: #f72585; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 20px; }
        .glass-card { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border-radius: 15px; overflow: hidden; max-width: 450px; width: 100%; margin: 0 auto; animation: fadeIn 0.6s ease-out forwards; }
        .card-header { background: rgba(0, 0, 0, 0.2); padding: 20px; text-align: center; }
        .card-body { padding: 30px; }
        .logo-text { font-size: 2rem; font-weight: 600; background: linear-gradient(to right, #fff, #c9d6ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .input-group { margin-bottom: 20px; }
        .form-control { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; height: 45px; }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.7); }
        .form-control:focus { background: rgba(255, 255, 255, 0.2); color: white; border-color: rgba(255, 255, 255, 0.4); }
        .btn-primary { background-color: var(--primary); border: none; padding: 10px; font-weight: 500; }
        .btn-primary:hover { background-color: var(--secondary); transform: translateY(-2px); }
        .alert { border-radius: 8px; margin-bottom: 20px; }
        .login-link { color: rgba(255, 255, 255, 0.8); }
        .login-link:hover { color: white; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="glass-card">
        <div class="card-header">
            <h1 class="logo-text">UniformStock Pro</h1>
            <p class="mb-0">Inventory Management System</p>
        </div>
        <div class="card-body">
            <h2 class="text-center mb-4">Reset Your Password</h2>
            <p class="text-center mb-4">Enter your email to receive a password reset link</p>

            <?php if (!empty($_SESSION['forgot_success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php 
                        echo $_SESSION['forgot_success'];
                        unset($_SESSION['forgot_success']);
                        if (!empty($_SESSION['reset_info'])) {
                            echo "<div class='mt-3 p-2 bg-light text-dark rounded small'>";
                            echo "<p class='mb-1'><strong>Demo Only:</strong></p>";
                            echo "<p class='mb-1'>Reset link: <code>{$_SESSION['reset_info']['demo_link']}</code></p>";
                            echo "<p class='mb-0'>Expires: {$_SESSION['reset_info']['expires']}</p>";
                            echo "</div>";
                            unset($_SESSION['reset_info']);
                        }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <ul class="mb-0 pl-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    </div>
                    <input type="email" name="email" class="form-control" placeholder="Your registered email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Send Reset Link
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="mb-0">Remembered your password? 
                    <a href="login.php" class="login-link"><i class="fas fa-sign-in-alt mr-1"></i> Sign In</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>
