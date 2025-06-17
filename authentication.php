<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/db_connection.php';
require 'vendor/autoload.php'; // Include OpenAI PHP library

use OpenAI\Client;

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: authentication/login.php");
    exit();
}

$error_message = "";
$chat_response = "";

// Handle Chatbot Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["chat_message"])) {
    $chat_message = trim($_POST["chat_message"]);

    if (empty($chat_message)) {
        $error_message = "Please enter a message.";
    } else {
        try {
            // Initialize OpenAI Client
            $openai = new Client('your-openai-api-key');

            // Send the message to OpenAI GPT
            $response = $openai->completions()->create([
                'model' => 'text-davinci-003', // Use the GPT-3.5 model
                'prompt' => $chat_message,
                'max_tokens' => 150, // Limit response length
            ]);

            // Get the AI response
            $chat_response = $response['choices'][0]['text'];
        } catch (Exception $e) {
            $error_message = "AI Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-background min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-72 bg-card shadow-lg p-6 flex flex-col justify-between rounded-r-lg">
        <div class="text-center">
            <img src="admin/admin_avatar.png" alt="Admin Avatar" class="mx-auto w-24 h-24 rounded-full shadow-md">
            <h2 class="text-lg font-bold text-text mt-4"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
            <p class="text-gray-500 text-sm">Administrator</p>
        </div>

        <!-- Navigation Links -->
        <nav class="mt-6 space-y-3">
            <a href="admin/admin_dashboard.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">🏠 Dashboard</a>
            <a href="admin/manage_users.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">👥 Manage Users</a>
            <a href="admin/manage_orders.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">📦 Manage Orders</a>
            <a href="admin/manage_stock.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">📊 Manage Uniforms</a>
            <a href="customer/customer_feedback.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">💬 Customer Feedback</a>
            <a href="ai/ai_chatbot.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">🤖 AI Chatbot</a>
            <a href="admin/admin_settings.php" class="block py-3 px-4 bg-accent/10 hover:bg-accent/20 rounded-lg transition">⚙️ Settings</a>
        </nav>

        <!-- Logout Button -->
        <a href="authentication/logout.php" class="block py-3 px-4 bg-red-500 text-white text-center rounded-lg hover:bg-red-600 transition">🚪 Logout</a>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-10">
        <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-lg p-6">
            <h1 class="text-3xl font-bold text-primary mb-8">🤖 AI Chatbot</h1>

            <!-- Display Messages -->
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Chatbot Interface -->
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Your Message:</label>
                    <textarea name="chat_message" required 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <button type="submit" 
                    class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg w-full transition-all duration-300">
                    Send Message
                </button>
            </form>

            <!-- AI Response -->
            <?php if (!empty($chat_response)): ?>
                <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                    <h2 class="text-lg font-bold text-primary mb-2">AI Response:</h2>
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($chat_response)) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
