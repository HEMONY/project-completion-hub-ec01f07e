<?php
/**
 * Embedded PDF Viewer (Simplified)
 * For direct file access only - base64 handled by proxy
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');

$pdfFile = isset($_GET['file']) ? basename($_GET['file']) : '';
$path = isset($_GET['path']) ? rtrim($_GET['path'], '/') . '/' : '';
$fullPath = $path . $pdfFile;

// Security validation - only allow direct files
if (!$pdfFile || !file_exists($fullPath)) {
    http_response_code(404);
    die('PDF file not found.');
}

if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(400);
    die('Invalid file type. Only PDF files are allowed.');
}

$fileSize = filesize($fullPath);
$fileModified = date('Y-m-d H:i:s', filemtime($fullPath));
$fileName = htmlspecialchars($pdfFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Viewer: <?php echo $fileName; ?></title>
    <link rel="stylesheet" href="pdf-styles.css">
</head>
<body class="embedded-viewer">
    <!-- Header -->
    <div class="pdf-header">
        <div class="pdf-header-left">
            <div class="pdf-icon">📄</div>
            <div class="pdf-title"><?php echo $fileName; ?></div>
            <div class="pdf-size">(<?php echo formatFileSize($fileSize); ?>)</div>
        </div>
        
        <div class="pdf-header-right">
            <a href="<?php echo htmlspecialchars($pdfFile); ?>" 
               class="pdf-btn" 
               download
               title="Download">
                <span class="pdf-btn-icon">📥</span>
                <span class="pdf-btn-text">Download</span>
            </a>
            <button class="pdf-btn" onclick="printPDF()" title="Print">
                <span class="pdf-btn-icon">🖨️</span>
                <span class="pdf-btn-text">Print</span>
            </button>
            <button class="pdf-btn" onclick="toggleFullscreen()" title="Fullscreen">
                <span class="pdf-btn-icon">🔍</span>
                <span class="pdf-btn-text">Fullscreen</span>
            </button>
        </div>
    </div>
    
    <!-- PDF Container -->
    <div class="pdf-container">
        <div class="pdf-loading" id="pdfLoading">
            <div class="loading-spinner"></div>
            <div class="loading-text">Loading PDF document...</div>
        </div>
        
        <iframe class="pdf-frame" 
                id="pdfFrame"
                src="<?php echo htmlspecialchars($pdfFile); ?>#toolbar=0&navpanes=0&scrollbar=1&view=FitH"
                onload="hideLoading()"
                onerror="showError()"
                allowfullscreen>
        </iframe>
    </div>
    
    <script src="pdf-scripts.js"></script>
    <script>
        // Auto-hide loading
        setTimeout(() => {
            const loading = document.getElementById('pdfLoading');
            if (loading && loading.style.display !== 'none') {
                hideLoading();
            }
        }, 10000);
        
        function printPDF() {
            const iframe = document.getElementById('pdfFrame');
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch(e) {
                alert('Printing may be blocked by browser.');
            }
        }
        
        function toggleFullscreen() {
            const container = document.querySelector('.pdf-container');
            if (!document.fullscreenElement) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        }
        
        function hideLoading() {
            const loading = document.getElementById('pdfLoading');
            if (loading) loading.style.display = 'none';
        }
        
        function showError() {
            const loading = document.getElementById('pdfLoading');
            if (loading) {
                loading.innerHTML = '<div style="color: white; text-align: center;">Error loading PDF</div>';
            }
        }
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>