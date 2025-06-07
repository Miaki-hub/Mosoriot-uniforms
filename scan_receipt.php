<?php
// scan_receipt.php
session_start();
require_once '../config/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Check admin role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header("Location: ../authentication/login.php");
    exit();
}

// Get current user info
$stmt = $conn->prepare("SELECT name, username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_info['name'] ?? $user_info['username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Scanner - UniformStock PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <style>
        /* Maintain existing Amazon-style UI */
        :root {
            --amazon-orange: #FF9900;
            --amazon-dark: #131921;
            --amazon-light: #232F3E;
            --amazon-gray: #EAEDED;
        }
        
        body {
            font-family: 'Amazon Ember', Arial, sans-serif;
            background-color: var(--amazon-gray);
        }
        
        .amazon-header {
            background-color: var(--amazon-dark);
            height: 60px;
        }
        
        .amazon-nav {
            background-color: var(--amazon-light);
            height: 40px;
        }
        
        .amazon-orange-btn {
            background-color: var(--amazon-orange);
            border-color: #a88734 #9c7e31 #846a29;
        }
        
        .amazon-orange-btn:hover {
            background-color: #f0c14b;
        }
        
        .amazon-card {
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #DDD;
        }
        
        .viewport {
            width: 100%;
            height: 300px;
            background: black;
            position: relative;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 2px dashed rgba(255,255,255,0.7);
            box-sizing: border-box;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Amazon-style Header -->
    <header class="amazon-header text-white shadow-sm">
        <div class="container mx-auto px-4 h-full flex items-center">
            <div class="flex items-center space-x-4">
                <a href="admin_dashboard.php" class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-tshirt mr-2"></i>
                    <span>UniformStock PRO</span>
                </a>
            </div>
            
            <div class="ml-auto flex items-center space-x-6">
                <div class="text-sm">
                    <span class="text-gray-300">Welcome, <?= htmlspecialchars($user_name) ?></span>
                </div>
                <a href="../authentication/logout.php" class="text-sm hover:text-gray-300">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Secondary Navigation -->
    <nav class="amazon-nav text-white">
        <div class="container mx-auto px-4 h-full flex items-center space-x-6 overflow-x-auto">
            <a href="admin_dashboard.php" class="text-sm whitespace-nowrap"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
            <a href="scan_receipt.php" class="text-sm font-medium text-yellow-300 whitespace-nowrap"><i class="fas fa-receipt mr-1"></i> Receipt Scanner</a>
            <a href="manage_stock.php" class="text-sm whitespace-nowrap"><i class="fas fa-boxes mr-1"></i> Inventory</a>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <!-- Main Content -->
        <div class="amazon-card p-6 fade-in">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Receipt Scanner</h2>
                <div class="flex space-x-2">
                    <button id="captureBtn" class="amazon-orange-btn text-white font-semibold py-2 px-4 rounded-md flex items-center">
                        <i class="fas fa-camera mr-2"></i> Capture Receipt
                    </button>
                    <button id="uploadBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md flex items-center">
                        <i class="fas fa-upload mr-2"></i> Upload Image
                    </button>
                    <input type="file" id="fileInput" accept="image/*" class="hidden">
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Camera Preview -->
                <div class="amazon-card p-4">
                    <h3 class="font-semibold mb-4 text-lg border-b pb-2">Camera Preview</h3>
                    <div id="cameraView" class="viewport">
                        <video id="video" class="w-full h-full object-cover" playsinline autoplay></video>
                        <div class="scanner-overlay"></div>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button id="startCameraBtn" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-md mr-4">
                            <i class="fas fa-video mr-2"></i> Start Camera
                        </button>
                        <button id="stopCameraBtn" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md hidden">
                            <i class="fas fa-stop-circle mr-2"></i> Stop Camera
                        </button>
                    </div>
                </div>
                
                <!-- Results -->
                <div class="amazon-card p-4">
                    <h3 class="font-semibold mb-4 text-lg border-b pb-2">Extracted Data</h3>
                    <div id="resultsContainer" class="h-64 overflow-y-auto p-2 bg-gray-50 rounded border border-gray-200">
                        <p class="text-gray-500 text-center mt-20">Extracted receipt data will appear here...</p>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div id="progressText" class="text-sm text-gray-600"></div>
                        <button id="saveResultsBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-6 rounded-md hidden">
                            <i class="fas fa-save mr-2"></i> Save to Database
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Processed Image -->
            <div class="amazon-card p-4 mt-6">
                <h3 class="font-semibold mb-4 text-lg border-b pb-2">Processed Image</h3>
                <div id="imagePreview" class="flex justify-center">
                    <canvas id="canvas" class="max-w-full border border-gray-200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Camera and OCR state
        let cameraStream = null;
        let processing = false;
        
        // DOM elements
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        const resultsContainer = document.getElementById('resultsContainer');
        const progressText = document.getElementById('progressText');
        const saveResultsBtn = document.getElementById('saveResultsBtn');
        
        // Initialize camera
        async function startCamera() {
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ 
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
                
                video.srcObject = cameraStream;
                document.getElementById('startCameraBtn').classList.add('hidden');
                document.getElementById('stopCameraBtn').classList.remove('hidden');
            } catch (err) {
                console.error('Error accessing camera:', err);
                alert('Could not access camera: ' + err.message);
            }
        }
        
        // Stop camera
        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            video.srcObject = null;
            document.getElementById('startCameraBtn').classList.remove('hidden');
            document.getElementById('stopCameraBtn').classList.add('hidden');
        }
        
        // Capture image from camera
        function captureImage() {
            if (!cameraStream || processing) return;
            
            // Set canvas dimensions to match video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw video frame to canvas
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Process the captured image
            processImage(canvas);
        }
        
        // Handle file upload
        document.getElementById('uploadBtn').addEventListener('click', () => {
            document.getElementById('fileInput').click();
        });
        
        document.getElementById('fileInput').addEventListener('change', (e) => {
            if (processing) return;
            
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    // Set canvas dimensions to match image
                    canvas.width = img.width;
                    canvas.height = img.height;
                    
                    // Draw image to canvas
                    ctx.drawImage(img, 0, 0, img.width, img.height);
                    
                    // Process the uploaded image
                    processImage(canvas);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
        
        // Process image with Tesseract.js
        function processImage(imageSource) {
            processing = true;
            progressText.textContent = 'Processing receipt...';
            resultsContainer.innerHTML = '<p class="text-gray-500 text-center mt-20">Processing receipt...</p>';
            saveResultsBtn.classList.add('hidden');
            
            Tesseract.recognize(
                imageSource,
                'eng',
                {
                    logger: m => updateProgress(m),
                    tessedit_pageseg_mode: 6 // Assume a single uniform block of text
                }
            ).then(({ data: { text, lines } }) => {
                processing = false;
                displayResults(text, lines);
                saveResultsBtn.classList.remove('hidden');
            }).catch(err => {
                console.error('OCR Error:', err);
                progressText.textContent = 'Error processing receipt';
                resultsContainer.innerHTML = `<p class="text-red-500">Error: ${err.message}</p>`;
                processing = false;
            });
        }
        
        // Update progress during OCR
        function updateProgress(message) {
            if (message.status) {
                progressText.textContent = `Status: ${message.status}...`;
            }
        }
        
        // Display extracted results
        function displayResults(text, lines) {
            progressText.textContent = 'Processing complete!';
            
            // Simple parsing of receipt data (customize based on your receipt format)
            const items = [];
            const priceRegex = /(\d+\.\d{2})/;
            
            lines.forEach(line => {
                const lineText = line.text.trim();
                if (lineText && priceRegex.test(lineText)) {
                    items.push(lineText);
                }
            });
            
            if (items.length > 0) {
                let html = '<div class="space-y-2">';
                items.forEach(item => {
                    html += `<div class="p-2 bg-white rounded border-b border-gray-200">${item}</div>`;
                });
                html += '</div>';
                resultsContainer.innerHTML = html;
            } else {
                resultsContainer.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-receipt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">No items found in receipt</p>
                        <p class="text-sm text-gray-500 mt-2">Raw text extracted:</p>
                        <pre class="text-xs text-left mt-2 p-2 bg-gray-100 rounded">${text}</pre>
                    </div>
                `;
            }
        }
        
        // Save results to database
        saveResultsBtn.addEventListener('click', () => {
            const extractedText = resultsContainer.innerText.trim();
            if (!extractedText) return;
            
            // Here you would send the data to your server
            alert('Receipt data would be saved to database in a real implementation');
            console.log('Data to save:', extractedText);
            
            // In a real implementation, you would:
            // 1. Parse the extracted text into structured data
            // 2. Send to server via AJAX
            // 3. Handle the response
        });
        
        // Event listeners
        document.getElementById('startCameraBtn').addEventListener('click', startCamera);
        document.getElementById('stopCameraBtn').addEventListener('click', stopCamera);
        document.getElementById('captureBtn').addEventListener('click', captureImage);
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Start camera automatically
            startCamera();
        });
    </script>
</body>
</html>