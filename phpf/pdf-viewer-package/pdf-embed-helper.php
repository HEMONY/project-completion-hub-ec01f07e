<?php
/**
 * PDF Embed Helper Functions
 * Usage: Include this file for utility functions
 */

/**
 * Embed PDF viewer in any page
 */
function embedPDFViewer($pdfFile, $width = '100%', $height = '500px', $options = []) {
    require_once 'pdf-viewer.php';
    
    $defaultOptions = [
        'width' => $width,
        'height' => $height,
        'className' => 'embedded-pdf-viewer'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    return showPDFViewer($pdfFile, $options);
}

/**
 * Create PDF viewer with custom path
 */
function embedPDFFromPath($pdfFile, $path = '', $options = []) {
    require_once 'pdf-viewer.php';
    
    $defaultOptions = [
        'path' => $path,
        'className' => 'pdf-from-path'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    return showPDFViewer($pdfFile, $options);
}

/**
 * Create responsive PDF viewer
 */
function embedResponsivePDF($pdfFile, $options = []) {
    require_once 'pdf-viewer.php';
    
    $defaultOptions = [
        'className' => 'responsive-pdf-viewer',
        'width' => '100%',
        'height' => '600px'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    return showPDFViewer($pdfFile, $options);
}

/**
 * Create minimal PDF viewer (no controls)
 */
function embedMinimalPDF($pdfFile, $options = []) {
    require_once 'pdf-viewer.php';
    
    $defaultOptions = [
        'showControls' => false,
        'className' => 'minimal-pdf-viewer',
        'width' => '100%',
        'height' => '500px'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    return showPDFViewer($pdfFile, $options);
}

/**
 * Create PDF viewer with custom ID for JavaScript control
 */
function embedPDFWithID($pdfFile, $elementId, $options = []) {
    require_once 'pdf-viewer.php';
    
    $defaultOptions = [
        'id' => $elementId,
        'className' => 'custom-id-pdf-viewer'
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    return showPDFViewer($pdfFile, $options);
}

/**
 * Get PDF viewer HTML as string for AJAX loading
 */
function getPDFViewerHTML($pdfFile, $options = []) {
    require_once 'pdf-viewer.php';
    return showPDFViewer($pdfFile, $options);
}