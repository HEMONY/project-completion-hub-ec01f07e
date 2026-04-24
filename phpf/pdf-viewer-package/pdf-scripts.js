/**
 * PDF Viewer JavaScript Utilities
 */

// Global PDF viewer functions
function printPDF(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    try {
        const iframeWindow = viewer.contentWindow;
        iframeWindow.focus();
        iframeWindow.print();
    } catch (error) {
        console.error('Print error:', error);
        alert('Printing may be blocked by browser security. Try downloading the PDF instead.');
    }
}

function toggleFullscreen(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    if (!document.fullscreenElement) {
        if (viewer.requestFullscreen) {
            viewer.requestFullscreen();
        } else if (viewer.webkitRequestFullscreen) {
            viewer.webkitRequestFullscreen();
        } else if (viewer.msRequestFullscreen) {
            viewer.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

function reloadPDF(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    const currentSrc = viewer.src;
    viewer.src = currentSrc.split('#')[0] + '#reload=' + Date.now();
}

function downloadPDF(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    // Try to extract PDF URL from iframe
    const pdfUrl = viewer.src.split('#')[0].replace('pdf-viewer-embed.php?', '') + '?file=' + extractFileName(viewer.src);
    window.open(pdfUrl, '_blank');
}

function extractFileName(url) {
    const match = url.match(/[?&]file=([^&]+)/);
    return match ? decodeURIComponent(match[1]) : 'document.pdf';
}

// Embedded viewer functions
function hideLoading() {
    const loading = document.getElementById('pdfLoading');
    if (loading) {
        loading.style.display = 'none';
    }
}

function showError() {
    const loading = document.getElementById('pdfLoading');
    const error = document.getElementById('pdfError');
    
    if (loading) {
        loading.style.display = 'none';
    }
    
    if (error) {
        error.style.display = 'block';
    }
}

function reloadEmbeddedPDF() {
    const iframe = document.getElementById('pdfFrame');
    if (iframe) {
        const loading = document.getElementById('pdfLoading');
        const error = document.getElementById('pdfError');
        
        if (loading) loading.style.display = 'flex';
        if (error) error.style.display = 'none';
        
        iframe.src = iframe.src;
    }
}

function printEmbeddedPDF() {
    const iframe = document.getElementById('pdfFrame');
    if (iframe) {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (error) {
            console.error('Print error:', error);
            alert('Printing may be blocked. Try downloading instead.');
        }
    }
}

function downloadEmbeddedPDF() {
    const pdfUrl = window.location.href.replace('pdf-viewer-embed.php', '') + '?file=' + extractFileName(window.location.href);
    window.open(pdfUrl, '_blank');
}

function toggleEmbeddedFullscreen() {
    const container = document.querySelector('.pdf-container');
    if (!container) return;
    
    if (!document.fullscreenElement) {
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) {
            container.webkitRequestFullscreen();
        } else if (container.msRequestFullscreen) {
            container.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

// Keyboard shortcuts
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcuts for embedded viewer
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + P to print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            printEmbeddedPDF();
        }
        
        // F for fullscreen
        if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            toggleEmbeddedFullscreen();
        }
        
        // Ctrl/Cmd + R to reload
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            reloadEmbeddedPDF();
        }
        
        // Ctrl/Cmd + S to download
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            downloadEmbeddedPDF();
        }
    });
    
    // Auto-hide loading if PDF takes too long
    setTimeout(() => {
        const loading = document.getElementById('pdfLoading');
        if (loading && loading.style.display !== 'none') {
            hideLoading();
        }
    }, 15000);
    
    // Handle fullscreen change events
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);
    
    function handleFullscreenChange() {
        const isFullscreen = !!(document.fullscreenElement || 
                              document.webkitFullscreenElement || 
                              document.msFullscreenElement);
        
        // Update UI based on fullscreen state
        const fullscreenBtn = document.querySelector('.pdf-btn[onclick*="toggleFullscreen"]');
        if (fullscreenBtn) {
            const icon = fullscreenBtn.querySelector('.pdf-btn-icon');
            if (icon) {
                icon.textContent = isFullscreen ? '🗕' : '🔍';
            }
        }
    }
});

// Export functions for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        printPDF,
        toggleFullscreen,
        reloadPDF,
        downloadPDF,
        hideLoading,
        showError,
        reloadEmbeddedPDF,
        printEmbeddedPDF,
        downloadEmbeddedPDF,
        toggleEmbeddedFullscreen
    };
}

/**
 * PDF Viewer JavaScript Utilities
 */

// Global PDF viewer functions
function printPDF(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    try {
        const iframeWindow = viewer.contentWindow;
        iframeWindow.focus();
        iframeWindow.print();
    } catch (error) {
        console.error('Print error:', error);
        alert('Printing may be blocked by browser security. Try downloading the PDF instead.');
    }
}

function toggleFullscreen(viewerId) {
    const viewer = document.getElementById(viewerId);
    if (!viewer) {
        console.error('PDF viewer not found:', viewerId);
        return;
    }
    
    if (!document.fullscreenElement) {
        if (viewer.requestFullscreen) {
            viewer.requestFullscreen();
        } else if (viewer.webkitRequestFullscreen) {
            viewer.webkitRequestFullscreen();
        } else if (viewer.msRequestFullscreen) {
            viewer.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

// NEW: JSON array navigation
function pdfNavigateJSON(index, containerId, jsonData) {
    // Decode JSON if it's a string
    if (typeof jsonData === 'string') {
        try {
            jsonData = JSON.parse(jsonData);
        } catch (e) {
            console.error('Invalid JSON data:', e);
            return;
        }
    }
    
    // Validate index
    if (index < 0 || index >= jsonData.length) {
        console.error('Invalid index:', index);
        return;
    }
    
    // Get container
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }
    
    // Create loading indicator
    container.innerHTML = '<div style="padding: 20px; text-align: center;">Loading PDF...</div>';
    
    // Create form to submit new index
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'pdf_data';
    dataInput.value = JSON.stringify(jsonData);
    
    const indexInput = document.createElement('input');
    indexInput.type = 'hidden';
    dataInput.name = 'data_index';
    indexInput.value = index;
    
    form.appendChild(dataInput);
    form.appendChild(indexInput);
    document.body.appendChild(form);
    
    // Submit form (this will reload the page with new index)
    form.submit();
}

// Embedded viewer functions remain the same...
// ... (keep existing functions from previous pdf-scripts.js)