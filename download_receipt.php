<?php
/**
 * Mosoriot Uniforms Depot - Enhanced Receipt System
 * 
 * Features:
 * - Customer retention elements
 * - Simplified tax calculation
 * - Professional receipt formatting
 * - Secure download handling
 */

// Secure session handling
session_start();

// Validate session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_receipt'])) {
    header('Location: admin_dashboard.php');
    exit;
}

// Business information
$businessInfo = [
    'name' => "MOSORIOT UNIFORMS DEPOT",
    'address' => "P.O Box 123, Mosoriot, Kenya",
    'phone' => "+254 700 123456",
    'email' => "info@mosoriotuniforms.co.ke",
    'vat_no' => "P0512345678"
];

// Customer retention messages
$retentionMessages = [
    'feedback' => "We value your feedback! Review us: bit.ly/mosoriot-feedback",
    'offer' => "Show this receipt for 5% discount on next purchase!",
    'support' => "Need help? Call: +254 711 123456 (8AM-5PM)"
];

/**
 * Calculate Kenyan VAT (16%)
 */
function calculateTax($amount) {
    $vatRate = 0.16; // 16% VAT
    return [
        'subtotal' => $amount,
        'vat' => round($amount * $vatRate, 2),
        'total' => round($amount * (1 + $vatRate), 2)
    ];
}

// Extract amount from receipt
preg_match('/TOTAL:\s+([\d,]+\.\d{2})/', $_SESSION['last_receipt'], $matches);
$amount = isset($matches[1]) ? (float)str_replace(',', '', $matches[1]) : 0;
$tax = calculateTax($amount);

// Build enhanced receipt
$receiptContent = "";

// Header
$receiptContent .= str_repeat("=", 50) . "\n";
$receiptContent .= str_pad($businessInfo['name'], 50, " ", STR_PAD_BOTH) . "\n";
$receiptContent .= str_pad($businessInfo['address'], 50, " ", STR_PAD_BOTH) . "\n";
$receiptContent .= "Tel: " . $businessInfo['phone'] . " | VAT: " . $businessInfo['vat_no'] . "\n";
$receiptContent .= str_repeat("-", 50) . "\n";

// Original transaction
$receiptContent .= $_SESSION['last_receipt'];

// Tax breakdown
$receiptContent .= "\n" . str_repeat("-", 50) . "\n";
$receiptContent .= sprintf("%-20s %15s\n", "Subtotal:", number_format($tax['subtotal'], 2));
$receiptContent .= sprintf("%-20s %15s\n", "VAT (16%):", number_format($tax['vat'], 2));
$receiptContent .= sprintf("%-20s %15s\n", "Total:", number_format($tax['total'], 2));
$receiptContent .= str_repeat("-", 50) . "\n";

// Customer retention section
$receiptContent .= "\nCUSTOMER BENEFITS:\n";
$receiptContent .= "• " . $retentionMessages['feedback'] . "\n";
$receiptContent .= "• " . $retentionMessages['offer'] . "\n";
$receiptContent .= "• " . $retentionMessages['support'] . "\n";

// Footer
$receiptContent .= str_repeat("=", 50) . "\n";
$receiptContent .= "Served by: " . ($_SESSION['user_name'] ?? "Staff") . "\n";
$receiptContent .= "Date: " . date('d/m/Y H:i:s') . "\n";
$receiptContent .= str_repeat("=", 50) . "\n";
$receiptContent .= "THANK YOU FOR CHOOSING MOSORIOT UNIFORMS!\n";

// Configure download
$filename = "Mosoriot_Receipt_" . date('Ymd_His') . ".txt";

// Send headers
header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($receiptContent));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output receipt
echo $receiptContent;

// Clear receipt from session if needed
// unset($_SESSION['last_receipt']);

exit;