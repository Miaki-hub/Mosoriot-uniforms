<?php
// Start session
session_start();

// Load configuration
if (!@require_once(__DIR__.'/../config.php')) {
    die("Configuration file missing or corrupted. Please reinstall the system.");
}

// Initialize variables
$errors = [];
$success = false;

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            // Check if email exists
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $errors[] = 'No account found with that email.';
                } else {
                    // You can implement email sending here
                    $success = true;
                    $_SESSION['reset_success'] = 'Password reset instructions have been sent to your email.';
                    header('Location: login.php');
                    exit();
                }
            } catch (Exception $e) {
                error_log("Forgot password error: " . $e->getMessage());
                $errors[] = 'System error. Please try again later.';
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
    <title>Forgot Password - <?php echo $_settings->info('name'); ?></title>
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <script src="plugins/jquery/jquery.min.js"></script>
</head>
<body class="hold-transition login-page dark-mode">
    <script>start_loader()</script>

    <style>
        body {
            background-image: url("<?php echo validate_image($_settings->info('cover')) ?>");
            background-size: cover;
            background-repeat: no-repeat;
        }
        .forgot-title {
            text-shadow: 2px 2px black;
            color: #fff;
        }
    </style>

    <h1 class="text-center py-5 forgot-title"><b><?php echo $_settings->info('name'); ?></b></h1>
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="./" class="h1"><b>Forgot Password</b></a>
            </div>
            <div class="card-body">
                <p class="login-box-msg">Enter your email to reset your password</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane mr-2"></i> Send Reset Link
                            </button>
                        </div>
                    </div>
                </form>

                <div class="mt-3 text-center">
                    <p class="mb-0">
                        <a href="login.php" class="text-light">
                            <i class="fas fa-sign-in-alt mr-1"></i> Back to Login
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function(){
            end_loader();
        });
    </script>
</body>
</html>
