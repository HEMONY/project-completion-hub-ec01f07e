<?php
/**
 * PDF Proxy - Serve base64 PDF data
 * Handles base64 and JSON data from PHP session or direct input
 */

session_start();

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Range');

// Get parameters
$cacheKey = isset($_GET['cache']) ? $_GET['cache'] : '';
$encodedData = isset($_GET['data']) ? $_GET['data'] : '';
$fileName = isset($_GET['name']) ? $_GET['name'] : 'document.pdf';
$mimeType = isset($_GET['type']) ? $_GET['type'] : 'application/pdf';

// Try to get from cache first
if ($cacheKey && isset($_SESSION[$cacheKey])) {
    $cachedData = $_SESSION[$cacheKey];
    
    // Check if cache is still valid (1 hour)
    if (isset($cachedData['timestamp']) && (time() - $cachedData['timestamp']) < 3600) {
        $base64Data = $cachedData['data'];
        $fileName = $cachedData['name'] ?? $fileName;
        $mimeType = $cachedData['type'] ?? $mimeType;
    } else {
        // Cache expired
        unset($_SESSION[$cacheKey]);
        http_response_code(410); // Gone
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

// Get file size
$fileSize = strlen($pdfData);

// Handle Range requests for partial content (for PDF.js)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    list($start, $end) = explode('-', $range);
    
    $start = intval($start);
    $end = $end ? intval($end) : $fileSize - 1;
    $length = $end - $start + 1;
    
    // Validate range
    if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
        http_response_code(416);
        die('Requested range not satisfiable');
    }
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");
    header('Accept-Ranges: bytes');
    
    echo substr($pdfData, $start, $length);
    exit;
}

// Serve full file
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=3600');
header('Pragma: public');
header('Accept-Ranges: bytes');

echo $pdfData;
exit;