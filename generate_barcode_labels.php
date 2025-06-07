<?php
// Start session and check authentication
session_start();

// Database connection
require_once '../config/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    die("Error: Authentication required. Please log in.");
}

// Get current user info
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'admin') {
        die("Error: Admin privileges required.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Define uniform tables
$uniformTables = [
    'Sweater' => 'sweaters',
    'Shirt' => 'shirts',
    // ... other tables ...
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

// Get items with barcodes
$itemsWithBarcodes = [];
foreach ($uniformTables as $type => $table) {
    if (tableHasColumn($conn, $table, 'barcode')) {
        try {
            $query = "SELECT id, size, color, barcode FROM $table WHERE barcode IS NOT NULL AND barcode != ''";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $item) {
                $item['type'] = $type;
                $itemsWithBarcodes[] = $item;
            }
        } catch (PDOException $e) {
            error_log("Error fetching barcodes from $table: " . $e->getMessage());
        }
    }
}

// Check if we have items to print
if (empty($itemsWithBarcodes)) {
    die("Error: No items with barcodes found in inventory.");
}

try {
    // Include TCPDF library
    $tcpdfPath = '../lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        throw new Exception("TCPDF library not found at $tcpdfPath");
    }
    require_once($tcpdfPath);

    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('UniformStock PRO');
    $pdf->SetAuthor('UniformStock PRO');
    $pdf->SetTitle('Barcode Labels');
    $pdf->SetSubject('Inventory Barcode Labels');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);

    // Add a page
    $pdf->AddPage();

    // Set font for labels
    $pdf->SetFont('helvetica', '', 10);

    // Define label dimensions (in mm)
    $labelWidth = 68;
    $labelHeight = 36;
    $margin = 2;
    $cols = 3;
    $rows = 7;
    $currentCol = 0;
    $currentRow = 0;

    // Try to include barcode font
    $barcodeFontPath = '../lib/tcpdf/fonts/librebarcode39.php';
    if (file_exists($barcodeFontPath)) {
        $pdf->AddFont('librebarcode39', '', 'librebarcode39.php');
        $barcodeFontAvailable = true;
    } else {
        $barcodeFontAvailable = false;
        error_log("Barcode font not found at $barcodeFontPath");
    }

    // Loop through items and generate labels
    foreach ($itemsWithBarcodes as $item) {
        if ($currentCol >= $cols) {
            $currentCol = 0;
            $currentRow++;
        }
        
        if ($currentRow >= $rows) {
            $pdf->AddPage();
            $currentRow = 0;
            $currentCol = 0;
        }
        
        // Calculate position
        $x = 10 + ($currentCol * $labelWidth);
        $y = 10 + ($currentRow * $labelHeight);
        
        // Draw label border
        $pdf->Rect($x, $y, $labelWidth, $labelHeight, 'D', array('width' => 0.2));
        
        // Add item information
        $pdf->SetXY($x + $margin, $y + $margin);
        $pdf->Cell($labelWidth - ($margin * 2), 6, $item['type'], 0, 1, 'C');
        
        $pdf->SetX($x + $margin);
        $pdf->Cell($labelWidth - ($margin * 2), 6, "Size: {$item['size']} | Color: {$item['color']}", 0, 1, 'C');
        
        // Add barcode
        if ($barcodeFontAvailable) {
            $pdf->SetFont('librebarcode39', '', 18);
            $pdf->SetXY($x + $margin, $y + 14);
            $pdf->Cell($labelWidth - ($margin * 2), 10, '*' . $item['barcode'] . '*', 0, 1, 'C');
        }
        
        // Add human-readable barcode
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($x + $margin, $y + ($barcodeFontAvailable ? 24 : 14));
        $pdf->Cell($labelWidth - ($margin * 2), 6, $item['barcode'], 0, 1, 'C');
        
        // Add ID for reference
        $pdf->SetXY($x + $margin, $y + ($barcodeFontAvailable ? 30 : 20));
        $pdf->Cell($labelWidth - ($margin * 2), 4, "ID: {$item['id']}", 0, 1, 'R');
        
        $currentCol++;
    }

    // Output PDF document
    $pdf->Output('barcode_labels.pdf', 'I');

} catch (Exception $e) {
    // Clean any output that might have been sent
    if (ob_get_length()) ob_clean();
    
    // Send error message
    header('Content-Type: text/plain');
    die("Error generating PDF: " . $e->getMessage() . 
        "\n\nTechnical details have been logged. Please contact support.");
}

// Close database connection
$conn = null;
?>