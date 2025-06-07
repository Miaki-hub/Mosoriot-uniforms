<?php
session_start();
require_once '../config/db_connection.php';

// Check authentication and admin privileges
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header("Location: ../authentication/login.php");
    exit();
}

// Define uniform types and their tables
$uniformTables = [
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

// Function to check if table has a column
function tableHasColumn($conn, $table, $column) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking for column: " . $e->getMessage());
        return false;
    }
}

// Function to generate barcode
function generateBarcode($prefix = 'UNI') {
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

// Process form submission
$importResult = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $importResult['error'] = "File upload error: " . $file['error'];
    } elseif (!in_array($file['type'], ['text/csv', 'application/vnd.ms-excel', 'text/plain'])) {
        $importResult['error'] = "Only CSV files are allowed.";
    } else {
        try {
            // Parse CSV file
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new Exception("Could not open uploaded file.");
            }
            
            // Get header row
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new Exception("Empty or invalid CSV file.");
            }
            
            // Validate headers
            $requiredHeaders = ['type', 'size', 'color', 'quantity', 'price'];
            foreach ($requiredHeaders as $header) {
                if (!in_array(strtolower($header), array_map('strtolower', $headers))) {
                    throw new Exception("Missing required column: $header");
                }
            }
            
            // Prepare for import
            $importResult['success'] = 0;
            $importResult['errors'] = [];
            $importResult['skipped'] = 0;
            
            // Begin transaction
            $conn->beginTransaction();
            
            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    $importResult['skipped']++;
                    $importResult['errors'][] = "Skipped row: Column count mismatch";
                    continue;
                }
                
                $data = array_combine($headers, $row);
                
                // Validate data
                $errors = [];
                
                // Validate type
                if (!isset($uniformTables[$data['type']])) {
                    $errors[] = "Invalid type: " . $data['type'];
                }
                
                // Validate numeric fields
                if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
                    $errors[] = "Invalid quantity: " . $data['quantity'];
                }
                
                if (!is_numeric($data['price']) || $data['price'] < 0) {
                    $errors[] = "Invalid price: " . $data['price'];
                }
                
                // Skip if errors
                if (!empty($errors)) {
                    $importResult['skipped']++;
                    $importResult['errors'][] = "Skipped " . $data['type'] . ": " . implode(", ", $errors);
                    continue;
                }
                
                // Prepare data
                $type = $data['type'];
                $table = $uniformTables[$type];
                $size = $data['size'] ?? '';
                $color = $data['color'] ?? '';
                $quantity = (int)$data['quantity'];
                $price = (float)$data['price'];
                $school_id = isset($data['school_id']) && is_numeric($data['school_id']) ? (int)$data['school_id'] : null;
                $barcode = $data['barcode'] ?? generateBarcode(substr($type, 0, 3));
                
                // Check if table has barcode column
                $hasBarcode = tableHasColumn($conn, $table, 'barcode');
                
                try {
                    if ($hasBarcode) {
                        $query = "INSERT INTO $table (uniform_id, size, color, quantity, price, school_id, barcode) 
                                 VALUES (NULL, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$size, $color, $quantity, $price, $school_id, $barcode]);
                    } else {
                        $query = "INSERT INTO $table (uniform_id, size, color, quantity, price, school_id) 
                                 VALUES (NULL, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$size, $color, $quantity, $price, $school_id]);
                    }
                    
                    $importResult['success']++;
                } catch (PDOException $e) {
                    $importResult['skipped']++;
                    $importResult['errors'][] = "Failed to import " . $data['type'] . ": " . $e->getMessage();
                }
            }
            
            // Commit transaction if no critical errors
            if ($importResult['success'] > 0) {
                $conn->commit();
                $_SESSION['import_success'] = "Successfully imported {$importResult['success']} items.";
                if ($importResult['skipped'] > 0) {
                    $_SESSION['import_warning'] = "Skipped {$importResult['skipped']} items due to errors.";
                }
            } else {
                $conn->rollBack();
                $_SESSION['import_error'] = "No items were imported. All rows had errors.";
            }
            
            fclose($handle);
            
        } catch (Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $importResult['error'] = $e->getMessage();
            $_SESSION['import_error'] = $e->getMessage();
        }
    }
}

// Get all schools for dropdown
$schools = [];
try {
    $stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching schools: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - Uniform Stock Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .file-upload {
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload:hover {
            border-color: #0d6efd;
            background-color: #e9f0ff;
        }
        .file-upload i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        .template-download {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 1.5rem;
        }
        .log-container {
            max-height: 300px;
            overflow-y: auto;
        }
        .log-entry {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .log-entry.error {
            color: #dc3545;
        }
        .log-entry.warning {
            color: #ffc107;
        }
        .log-entry.success {
            color: #198754;
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-file-import me-2"></i> Bulk Import Inventory</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['import_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= $_SESSION['import_success'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['import_success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['import_warning'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show">
                                <?= $_SESSION['import_warning'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['import_warning']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['import_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= $_SESSION['import_error'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['import_error']); ?>
                        <?php endif; ?>

                        <div class="mb-4">
                            <form id="importForm" method="post" enctype="multipart/form-data">
                                <div class="file-upload mb-3" id="fileUploadArea">
                                    <i class="fas fa-file-csv"></i>
                                    <h5>Drag & Drop your CSV file here</h5>
                                    <p class="text-muted">or click to browse files</p>
                                    <input type="file" name="import_file" id="import_file" class="d-none" accept=".csv">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-primary" id="browseBtn">
                                            <i class="fas fa-folder-open me-2"></i>Browse Files
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="selectedFile" class="d-none mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-csv text-primary me-2"></i>
                                        <span id="fileName"></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="removeFile">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="school_id" class="form-label">Default School (optional)</label>
                                    <select name="school_id" id="school_id" class="form-select">
                                        <option value="">-- No School --</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                        <i class="fas fa-upload me-2"></i>Start Import
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="template-download mb-4">
                            <h5><i class="fas fa-file-download me-2"></i>Download Template</h5>
                            <p>Use our template to ensure proper formatting of your CSV file.</p>
                            <a href="download_template.php" class="btn btn-outline-success">
                                <i class="fas fa-download me-2"></i>Download CSV Template
                            </a>
                        </div>
                        
                        <div class="mb-3">
                            <h5><i class="fas fa-info-circle me-2"></i>Import Instructions</h5>
                            <ul>
                                <li>CSV file must include headers: <code>type, size, color, quantity, price</code></li>
                                <li>Optional fields: <code>school_id, barcode</code></li>
                                <li>Valid types: <?= implode(", ", array_keys($uniformTables)) ?></li>
                                <li>Quantity and price must be positive numbers</li>
                                <li>Max file size: 2MB</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results Modal -->
    <div class="modal fade" id="importResultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tasks me-2"></i>Import Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="successCount">0</span> items imported successfully
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="skippedCount">0</span> items skipped
                    </div>
                    
                    <div class="mb-3">
                        <h6>Import Log:</h6>
                        <div class="log-container bg-light p-2 rounded">
                            <div id="importLog"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="manage_stock.php" class="btn btn-primary">
                        <i class="fas fa-boxes me-2"></i>View Inventory
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('import_file');
            const browseBtn = document.getElementById('browseBtn');
            const fileUploadArea = document.getElementById('fileUploadArea');
            const selectedFile = document.getElementById('selectedFile');
            const fileName = document.getElementById('fileName');
            const removeFile = document.getElementById('removeFile');
            const submitBtn = document.getElementById('submitBtn');
            const importForm = document.getElementById('importForm');
            
            // Handle file selection
            browseBtn.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    if (file.type.match(/(text\/csv|application\/vnd.ms-excel|text\/plain)/)) {
                        fileName.textContent = file.name;
                        selectedFile.classList.remove('d-none');
                        submitBtn.disabled = false;
                    } else {
                        alert('Please select a CSV file.');
                        this.value = '';
                    }
                }
            });
            
            // Drag and drop functionality
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('border-primary');
                fileUploadArea.style.backgroundColor = '#e9f0ff';
            });
            
            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('border-primary');
                fileUploadArea.style.backgroundColor = '';
            });
            
            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('border-primary');
                fileUploadArea.style.backgroundColor = '';
                
                if (e.dataTransfer.files.length) {
                    const file = e.dataTransfer.files[0];
                    if (file.type.match(/(text\/csv|application\/vnd.ms-excel|text\/plain)/)) {
                        fileInput.files = e.dataTransfer.files;
                        fileName.textContent = file.name;
                        selectedFile.classList.remove('d-none');
                        submitBtn.disabled = false;
                    } else {
                        alert('Please drop a CSV file.');
                    }
                }
            });
            
            // Remove file
            removeFile.addEventListener('click', () => {
                fileInput.value = '';
                selectedFile.classList.add('d-none');
                submitBtn.disabled = true;
            });
            
            // Form submission
            importForm.addEventListener('submit', function(e) {
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Please select a file to import.');
                }
            });
            
            // Show results modal if there are results
            <?php if (!empty($importResult)): ?>
                const resultsModal = new bootstrap.Modal(document.getElementById('importResultsModal'));
                
                // Update modal content
                document.getElementById('successCount').textContent = <?= $importResult['success'] ?? 0 ?>;
                document.getElementById('skippedCount').textContent = <?= $importResult['skipped'] ?? 0 ?>;
                
                const logContainer = document.getElementById('importLog');
                
                <?php if (isset($importResult['error'])): ?>
                    logContainer.innerHTML += `
                        <div class="log-entry error">
                            <i class="fas fa-times-circle me-2"></i>
                            <?= addslashes($importResult['error']) ?>
                        </div>
                    `;
                <?php endif; ?>
                
                <?php if (!empty($importResult['errors'])): ?>
                    <?php foreach ($importResult['errors'] as $error): ?>
                        logContainer.innerHTML += `
                            <div class="log-entry error">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= addslashes($error) ?>
                            </div>
                        `;
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (($importResult['success'] ?? 0) > 0): ?>
                    logContainer.innerHTML += `
                        <div class="log-entry success">
                            <i class="fas fa-check-circle me-2"></i>
                            Import completed successfully!
                        </div>
                    `;
                <?php endif; ?>
                
                resultsModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>