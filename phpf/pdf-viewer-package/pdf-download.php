<?php
/**
 * PDF Download Handler
 * Force download of base64 PDF data
 */

session_start();

// Get parameters
$cacheKey = isset($_GET['cache']) ? $_GET['cache'] : '';
$encodedData = isset($_GET['data']) ? $_GET['data'] : '';
$fileName = isset($_GET['name']) ? $_GET['name'] : 'document.pdf';

// Try to get from cache first
if ($cacheKey && isset($_SESSION[$cacheKey])) {
    $cachedData = $_SESSION[$cacheKey];
    
    // Check if cache is still valid
    if (isset($cachedData['timestamp']) && (time() - $cachedData['timestamp']) < 3600) {
        $base64Data = $cachedData['data'];
        $fileName = $cachedData['name'] ?? $fileName;
    } else {
        // Cache expired
        unset($_SESSION[$cacheKey]);
        http_response_code(410);
        die('Cached PDF data has expired.');
    }
} elseif ($encodedData) {
    // Get from direct data parameter
    $base64Data = base64_decode($encodedData);
    if ($base64Data === false) {
        http_response_code(400);
        die('Invalid base64 data.');
    }
} else {
    http_response_code(400);
    die('No PDF data provided.');
}

// Decode base64 data
$pdfData = base64_decode($base64Data);
if ($pdfData === false) {
    http_response_code(400);
    die('Failed to decode base64 data.');
}

// Force download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
header('Content-Length: ' . strlen($pdfData));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfData;
exit;