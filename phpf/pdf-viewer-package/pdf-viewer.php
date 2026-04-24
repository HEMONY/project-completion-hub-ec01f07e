<?php
/**
 * PDF Viewer Component
 * Usage: Include this file and call showPDFViewer($pdfData, $options)
 * Supports: Direct files, base64 data, JSON arrays - all handled in PHP
 */

class PDFViewer {
    
    /**
     * Display a PDF viewer component
     * 
     * @param mixed $pdfData Can be: filename, base64 string, or JSON array
     * @param array $options Configuration options
     * @return string HTML output
     */
    public static function show($pdfData, $options = []) {
        // Default options
        $defaults = [
            'width' => '100%',
            'height' => '500px',
            'title' => 'PDF Document',
            'showControls' => true,
            'downloadable' => true,
            'printable' => true,
            'fullscreen' => true,
            'className' => '',
            'id' => 'pdf-viewer-' . uniqid(),
            'type' => 'auto', // 'file', 'base64', 'json', 'auto'
            'dataIndex' => 0, // For JSON arrays, which item to show
            'showNavigation' => false, // Show next/prev for multiple files
            'proxy' => true, // Use PHP proxy for base64 data
            'path' => '', // Custom path for files
            'cache' => true, // Cache base64 data in session
        ];
        
        $options = array_merge($defaults, $options);
        
        // Determine data type
        if ($options['type'] === 'auto') {
            $options['type'] = self::detectDataType($pdfData);
        }
        
        // Generate viewer HTML
        return self::renderViewer($pdfData, $options);
    }
    
    /**
     * Detect data type automatically
     */
    private static function detectDataType($data) {
        // Check if it's a JSON string
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return 'json';
            }
            
            // Check if it's a file path ending with .pdf
            if (preg_match('/\.pdf$/i', $data)) {
                return 'file';
            }
            
            // Check if it's base64 (contains typical base64 chars and ends with =)
            if (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $data) && strlen($data) > 100) {
                return 'base64';
            }
        }
        
        // Check if it's already a PHP array
        if (is_array($data)) {
            return 'json';
        }
        
        // Default to file
        return 'file';
    }
    
    /**
     * Process PDF data based on type
     */
    private static function processPDFData($pdfData, $options) {
        $type = $options['type'];
        $index = $options['dataIndex'];
        
        switch ($type) {
            case 'json':
                // Handle JSON array
                if (is_string($pdfData)) {
                    $dataArray = json_decode($pdfData, true);
                } else {
                    $dataArray = $pdfData;
                }
                
                if (!is_array($dataArray) || empty($dataArray)) {
                    return [
                        'error' => 'Invalid JSON data or empty array',
                        'title' => 'Error'
                    ];
                }
                
                // Ensure index is valid
                if ($index >= count($dataArray)) {
                    $index = 0;
                }
                
                $item = $dataArray[$index];
                
                // Extract data from JSON item
                $base64Data = $item['base64_data'] ?? $item['data'] ?? '';
                $fileName = $item['file_name'] ?? $item['name'] ?? 'document.pdf';
                $mimeType = $item['mime_type'] ?? 'application/pdf';
                $size = $item['size'] ?? 0;
                $compressed = $item['compressed'] ?? false;
                $uploadedAt = $item['uploaded_at'] ?? date('Y-m-d H:i:s');
                
                // Handle compressed data
                if ($compressed && function_exists('gzdecode')) {
                    $binaryData = base64_decode($base64Data);
                    $decompressed = @gzdecode($binaryData);
                    if ($decompressed !== false) {
                        $base64Data = base64_encode($decompressed);
                    }
                }
                
                // Cache in session if enabled
                if ($options['cache'] && session_status() === PHP_SESSION_ACTIVE) {
                    $cacheKey = 'pdf_cache_' . md5($base64Data);
                    $_SESSION[$cacheKey] = [
                        'data' => $base64Data,
                        'name' => $fileName,
                        'type' => $mimeType,
                        'timestamp' => time()
                    ];
                }
                
                return [
                    'type' => 'base64',
                    'data' => $base64Data,
                    'title' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'uploaded_at' => $uploadedAt,
                    'is_json' => true,
                    'json_index' => $index,
                    'json_total' => count($dataArray),
                    'json_data' => $dataArray,
                    'cache_key' => isset($cacheKey) ? $cacheKey : null
                ];
                
            case 'base64':
                // Handle direct base64 string
                return [
                    'type' => 'base64',
                    'data' => $pdfData,
                    'title' => $options['title'],
                    'mime_type' => 'application/pdf',
                    'size' => strlen(base64_decode($pdfData)),
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                
            case 'file':
            default:
                // Handle file path
                $fullPath = $options['path'] ? rtrim($options['path'], '/') . '/' . $pdfData : $pdfData;
                
                if (!file_exists($fullPath)) {
                    return [
                        'error' => 'File not found: ' . htmlspecialchars($fullPath),
                        'title' => 'Error'
                    ];
                }
                
                return [
                    'type' => 'file',
                    'path' => $fullPath,
                    'title' => basename($fullPath),
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                ];
        }
    }
    
    /**
     * Render the viewer HTML
     */
    private static function renderViewer($pdfData, $options) {
        // Process PDF data
        $pdfInfo = self::processPDFData($pdfData, $options);
        
        // Handle errors
        if (isset($pdfInfo['error'])) {
            return self::renderError($pdfInfo['error'], $options);
        }
        
        // Update title if not set
        if ($options['title'] === 'PDF Document' && isset($pdfInfo['title'])) {
            $options['title'] = $pdfInfo['title'];
        }
        
        // Get viewer URL
        $viewerUrl = self::getViewerUrl($pdfInfo, $options);
        
        // Build HTML
        $html = '<div class="pdf-viewer-container ' . htmlspecialchars($options['className']) . '" id="' . htmlspecialchars($options['id']) . '-container">';
        
        if ($options['showControls']) {
            $html .= self::renderControls($pdfInfo, $options);
        }
        
        $html .= '<iframe src="' . htmlspecialchars($viewerUrl) . '" 
                         class="pdf-viewer-frame"
                         id="' . htmlspecialchars($options['id']) . '"
                         style="width: ' . htmlspecialchars($options['width']) . '; 
                                height: ' . htmlspecialchars($options['height']) . ';"
                         title="PDF Viewer - ' . htmlspecialchars($options['title']) . '"
                         allowfullscreen></iframe>';
        
        // Add navigation for JSON arrays with multiple files
        if (isset($pdfInfo['is_json']) && $pdfInfo['json_total'] > 1 && $options['showNavigation']) {
            $html .= self::renderJSONNavigation($pdfInfo, $options);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get viewer URL based on data type
     */
    private static function getViewerUrl($pdfInfo, $options) {
        $type = $pdfInfo['type'];
        
        if ($type === 'file') {
            // Direct file access
            return htmlspecialchars($pdfInfo['path']) . '#toolbar=0&navpanes=0&scrollbar=1';
        } else {
            // Use proxy for base64 data
            $proxyUrl = 'pdf-proxy.php?';
            
            // Add cache key if available
            if (isset($pdfInfo['cache_key'])) {
                $proxyUrl .= 'cache=' . urlencode($pdfInfo['cache_key']);
            } else {
                // Add direct data (encoded for safety)
                $proxyUrl .= 'data=' . urlencode(base64_encode($pdfInfo['data']));
            }
            
            // Add additional info
            $proxyUrl .= '&name=' . urlencode($pdfInfo['title']);
            $proxyUrl .= '&type=' . urlencode($pdfInfo['mime_type']);
            
            return $proxyUrl;
        }
    }
    
    /**
     * Render control buttons
     */
    private static function renderControls($pdfInfo, $options) {
        $downloadUrl = self::getDownloadUrl($pdfInfo, $options);
        
        $controls = '<div class="pdf-viewer-controls">';
        $controls .= '<div class="pdf-viewer-title">📄 ' . htmlspecialchars($options['title']) . '</div>';
        $controls .= '<div class="pdf-viewer-actions">';
        
        if ($options['downloadable']) {
            $controls .= '<a href="' . htmlspecialchars($downloadUrl) . '" 
                              class="pdf-action-btn" 
                              download="' . htmlspecialchars($pdfInfo['title']) . '"
                              title="Download PDF">
                              <span>📥</span> Download
                          </a>';
        }
        
        if ($options['printable']) {
            $controls .= '<button class="pdf-action-btn" onclick="printPDF(\'' . htmlspecialchars($options['id']) . '\')" title="Print">
                            <span>🖨️</span> Print
                          </button>';
        }
        
        if ($options['fullscreen']) {
            $controls .= '<button class="pdf-action-btn" onclick="toggleFullscreen(\'' . htmlspecialchars($options['id']) . '\')" title="Fullscreen">
                            <span>🔍</span> Fullscreen
                          </button>';
        }
        
        $controls .= '</div></div>';
        return $controls;
    }
    
    /**
     * Get download URL
     */
    private static function getDownloadUrl($pdfInfo, $options) {
        if ($pdfInfo['type'] === 'file') {
            return $pdfInfo['path'];
        } else {
            // For base64 data, use download proxy
            $downloadUrl = 'pdf-download.php?';
            
            if (isset($pdfInfo['cache_key'])) {
                $downloadUrl .= 'cache=' . urlencode($pdfInfo['cache_key']);
            } else {
                $downloadUrl .= 'data=' . urlencode(base64_encode($pdfInfo['data']));
            }
            
            $downloadUrl .= '&name=' . urlencode($pdfInfo['title']);
            return $downloadUrl;
        }
    }
    
    /**
     * Render navigation for JSON arrays
     */
    private static function renderJSONNavigation($pdfInfo, $options) {
        $current = $pdfInfo['json_index'] + 1;
        $total = $pdfInfo['json_total'];
        $containerId = $options['id'] . '-container';
        $jsonData = json_encode($pdfInfo['json_data']);
        
        $html = '<div class="pdf-json-navigation">';
        $html .= '<button class="pdf-nav-btn" onclick="pdfNavigateJSON(' . htmlspecialchars($current - 2) . ', \'' . htmlspecialchars($containerId) . '\', ' . htmlspecialchars(json_encode($jsonData)) . ')" ' . ($current <= 1 ? 'disabled' : '') . '>
                    <span>◀</span> Previous
                  </button>';
        $html .= '<div class="pdf-nav-counter">File ' . $current . ' of ' . $total . '</div>';
        $html .= '<button class="pdf-nav-btn" onclick="pdfNavigateJSON(' . $current . ', \'' . htmlspecialchars($containerId) . '\', ' . htmlspecialchars(json_encode($jsonData)) . ')" ' . ($current >= $total ? 'disabled' : '') . '>
                    Next <span>▶</span>
                  </button>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render error message
     */
    private static function renderError($message, $options) {
        return '<div class="pdf-viewer-error">
                    <div class="error-icon">❌</div>
                    <div class="error-title">PDF Viewer Error</div>
                    <div class="error-message">' . htmlspecialchars($message) . '</div>
                </div>';
    }
    
    /**
     * List available PDFs in directory
     */
    public static function listPDFs($directory = '.', $showSize = true) {
        $pdfs = [];
        
        if (is_dir($directory)) {
            $files = scandir($directory);
            foreach ($files as $file) {
                $path = $directory . '/' . $file;
                if (is_file($path) && preg_match('/\.pdf$/i', $file)) {
                    $pdf = [
                        'name' => $file,
                        'path' => $path,
                        'size' => filesize($path),
                        'modified' => filemtime($path)
                    ];
                    $pdfs[] = $pdf;
                }
            }
        }
        
        return $pdfs;
    }
}

/**
 * Helper function for quick usage
 */
function showPDFViewer($pdfData, $options = []) {
    return PDFViewer::show($pdfData, $options);
}