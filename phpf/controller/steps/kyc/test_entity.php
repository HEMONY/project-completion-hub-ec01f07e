<?php
// test_entities.php
session_start();
require_once '../../../config/db.php';

// Check if user is logged in (optional)
if (!isset($_SESSION['user_id'])) {
    // If you want to restrict access, uncomment below
    // header("Location: login.php");
    // exit;
}

// Initialize variables
$entities = [];
$selected_entity = null;
$step_data = [
    'step1' => null,
    'step2' => null,
    'step3' => null,
    'step4' => null,
    'step5' => null
];
$entity_id = null;
$error = null;
$image_previews = [];

// Get all entities from database
try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
    
    // Check if viewing a specific entity
    if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
        $entity_id = intval($_GET['entity_id']);
        
        // Get entity details
        $stmt = $pdo->prepare("SELECT * FROM entities WHERE id = ?");
        $stmt->execute([$entity_id]);
        $selected_entity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selected_entity) {
            // Get all step data for this entity
            $step_data = getAllStepData($pdo, $entity_id);
        } else {
            $error = "Entity not found";
        }
    }
    
    // Get all entities with some step data for the list
    $stmt = $pdo->query("
        SELECT e.*, 
               es1.company_owner_name,
               es5.digital_signature_name,
               es5.accepted_at
        FROM entities e
        LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
        LEFT JOIN entity_step5 es5 ON e.id = es5.entity_id
        ORDER BY e.created_at DESC
    ");
    $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Test Entities Error: " . $e->getMessage());
}

/**
 * Get all step data for an entity
 */
function getAllStepData($pdo, $entity_id) {
    $step_data = [
        'step1' => null,
        'step2' => null,
        'step3' => null,
        'step4' => null,
        'step5' => null
    ];
    
    // Get Step 1 data
    $stmt = $pdo->prepare("SELECT * FROM entity_step1 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $step1_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step1_data) {
        // Decode JSON fields
        $step1_data['shareholders'] = json_decode($step1_data['shareholders'] ?? '[]', true);
        $step1_data['ubos'] = json_decode($step1_data['ubos'] ?? '[]', true);
        $step1_data['eid_passports'] = json_decode($step1_data['eid_passports'] ?? '[]', true);
        $step1_data['trade_license'] = json_decode($step1_data['trade_license'] ?? '[]', true);
        $step1_data['authorization_letter'] = json_decode($step1_data['authorization_letter'] ?? '[]', true);
        
        // Decode management_control if it's JSON
        if ($step1_data['management_control'] && 
            substr($step1_data['management_control'], 0, 1) === '{') {
            $step1_data['management_control'] = json_decode($step1_data['management_control'], true);
        }
        
        $step_data['step1'] = $step1_data;
    }
    
    // Get Step 2 data
    $stmt = $pdo->prepare("SELECT * FROM entity_step2 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $step2_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step2_data) {
        $step_data['step2'] = $step2_data;
    }
    
    // Get Step 3 data
    $stmt = $pdo->prepare("SELECT * FROM entity_step3 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $step3_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step3_data) {
        $step3_data['previous_auditor_files'] = json_decode($step3_data['previous_auditor_files'] ?? '[]', true);
        $step_data['step3'] = $step3_data;
    }
    
    // Get Step 4 data
    $stmt = $pdo->prepare("SELECT * FROM entity_step4 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $step4_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step4_data) {
        $step_data['step4'] = $step4_data;
    }
    
    // Get Step 5 data
    $stmt = $pdo->prepare("SELECT * FROM entity_step5 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $step5_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step5_data) {
        $step_data['step5'] = $step5_data;
    }
    
    return $step_data;
}

/**
 * Get base64 file data and create preview
 */
function getFilePreview($file_data, $index, $type) {
    if (empty($file_data['base64_data'])) {
        return null;
    }
    
    $base64_data = $file_data['base64_data'];
    
    // Check if data is gzipped/compressed
    if (isset($file_data['compressed']) && $file_data['compressed'] === true) {
        $decoded = base64_decode($base64_data);
        if (function_exists('gzuncompress')) {
            $decompressed = @gzuncompress($decoded);
            if ($decompressed !== false) {
                $base64_data = base64_encode($decompressed);
            }
        }
    }
    
    // Determine mime type
    $mime_type = $file_data['mime_type'] ?? getMimeTypeFromBase64($base64_data);
    $file_name = $file_data['file_name'] ?? "file_$index.$type";
    
    return [
        'data_url' => "data:$mime_type;base64," . $base64_data,
        'file_name' => $file_name,
        'mime_type' => $mime_type,
        'size' => $file_data['size'] ?? 0,
        'base64_data' => $base64_data
    ];
}

/**
 * Get mime type from base64 data
 */
function getMimeTypeFromBase64($base64_data) {
    $data = base64_decode(substr($base64_data, 0, 100));
    
    // Check for common file signatures
    if (strpos($data, '%PDF') === 0) {
        return 'application/pdf';
    } elseif (strpos($data, "\xFF\xD8\xFF") === 0) {
        return 'image/jpeg';
    } elseif (strpos($data, "\x89PNG\x0D\x0A\x1A\x0A") === 0) {
        return 'image/png';
    } elseif (strpos($data, 'GIF') === 0) {
        return 'image/gif';
    } elseif (strpos($data, 'BM') === 0) {
        return 'image/bmp';
    } elseif (strpos($data, "\x49\x49\x2A\x00") === 0 || strpos($data, "\x4D\x4D\x00\x2A") === 0) {
        return 'image/tiff';
    } else {
        return 'application/octet-stream';
    }
}

/**
 * Format file size
 */
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

/**
 * Get file extension icon
 */
function getFileIcon($mime_type, $file_name) {
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $image_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $pdf_types = ['pdf'];
    $word_types = ['doc', 'docx', 'odt', 'rtf'];
    $excel_types = ['xls', 'xlsx', 'ods', 'csv'];
    $text_types = ['txt', 'log', 'md'];
    
    if (in_array($extension, $image_types) || strpos($mime_type, 'image/') === 0) {
        return 'fa-file-image';
    } elseif (in_array($extension, $pdf_types) || $mime_type === 'application/pdf') {
        return 'fa-file-pdf';
    } elseif (in_array($extension, $word_types) || strpos($mime_type, 'application/msword') !== false || 
              strpos($mime_type, 'application/vnd.openxmlformats') !== false) {
        return 'fa-file-word';
    } elseif (in_array($extension, $excel_types) || strpos($mime_type, 'application/vnd.ms-excel') !== false) {
        return 'fa-file-excel';
    } elseif (in_array($extension, $text_types) || strpos($mime_type, 'text/') === 0) {
        return 'fa-file-alt';
    } else {
        return 'fa-file';
    }
}

/**
 * Check if file is viewable in browser
 */
function isViewableImage($mime_type, $file_name) {
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $viewable_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml'];
    
    return in_array($extension, $image_extensions) || in_array($mime_type, $viewable_mimes);
}

/**
 * Check if file is PDF
 */
function isPDF($mime_type, $file_name) {
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    return $extension === 'pdf' || $mime_type === 'application/pdf';
}

/**
 * Format money amount
 */
function formatMoney($amount) {
    if ($amount === null || $amount === '') return 'N/A';
    return 'AED ' . number_format($amount, 2);
}

/**
 * Get step completion status
 */
function getStepCompletionStatus($step_data, $step_number) {
    if ($step_data && !empty($step_data)) {
        return '<span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>';
    } else {
        return '<span class="status-badge status-draft"><i class="fas fa-clock"></i> Not Started</span>';
    }
}

/**
 * Format date for display
 */
function formatDate($date_string) {
    if (empty($date_string) || $date_string == '0000-00-00') {
        return 'N/A';
    }
    return date('F j, Y', strtotime($date_string));
}

/**
 * Format date/time for display
 */
function formatDateTime($datetime_string) {
    if (empty($datetime_string) || $datetime_string == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('F j, Y H:i:s', strtotime($datetime_string));
}

/**
 * Get file download URL
 */
function getFileDownloadUrl($file_data, $type, $index) {
    if (empty($file_data['base64_data'])) {
        return '#';
    }
    
    return "download_file.php?type=$type&index=$index&entity_id=" . ($_GET['entity_id'] ?? '');
}

/**
 * Display file preview card
 */
function displayFileCard($file_data, $index, $type, $is_image = false, $is_pdf = false) {
    $preview = getFilePreview($file_data, $index, $type);
    if (!$preview) return '';
    
    $file_name = htmlspecialchars($preview['file_name']);
    $data_url = $preview['data_url'];
    $mime_type = $preview['mime_type'];
    $file_size = formatFileSize($preview['size']);
    $uploaded_at = !empty($file_data['uploaded_at']) ? date('Y-m-d H:i', strtotime($file_data['uploaded_at'])) : 'Unknown';
    $compressed = isset($file_data['compressed']) && $file_data['compressed'] ? 'Yes' : 'No';
    $icon_class = getFileIcon($mime_type, $file_name);
    
    $html = '<div class="file-item">';
    $html .= '<div class="file-header">';
    $html .= '<div class="file-icon"><i class="fas ' . $icon_class . '"></i></div>';
    $html .= '<div class="file-name">' . $file_name . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="file-preview-container">';
    if ($is_image) {
        $html .= '<img src="' . $data_url . '" class="file-preview" alt="' . $file_name . '" onclick="openModal(\'' . $data_url . '\', \'' . $file_name . '\')" style="cursor: pointer;">';
    } elseif ($is_pdf) {
        $html .= '<div class="pdf-preview" onclick="openModal(\'' . $data_url . '\', \'' . $file_name . '\')" style="cursor: pointer;">';
        $html .= '<i class="fas fa-file-pdf pdf-icon"></i>';
        $html .= '<div>PDF Preview</div>';
        $html .= '<small>Click to view</small>';
        $html .= '</div>';
    } else {
        $html .= '<div class="no-preview">';
        $html .= '<i class="fas ' . $icon_class . '"></i>';
        $html .= '<div>Preview not available</div>';
        $html .= '<small>Download to view</small>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    $html .= '<div class="file-meta">';
    $html .= '<div><i class="fas fa-file-alt"></i> Type: ' . htmlspecialchars($mime_type) . '</div>';
    $html .= '<div><i class="fas fa-weight-hanging"></i> Size: ' . $file_size . '</div>';
    $html .= '<div><i class="fas fa-calendar"></i> Uploaded: ' . $uploaded_at . '</div>';
    $html .= '<div><i class="fas fa-compress"></i> Compressed: ' . $compressed . '</div>';
    $html .= '</div>';
    
    $html .= '<div class="file-actions">';
    if ($is_image || $is_pdf) {
        $html .= '<button class="action-btn view-btn" onclick="openModal(\'' . $data_url . '\', \'' . $file_name . '\')">';
        $html .= '<i class="fas fa-eye"></i> View';
        $html .= '</button>';
    }
    
    $html .= '<button class="action-btn download-btn" onclick="downloadBase64File(\'' . $file_name . '\', \'' . $preview['base64_data'] . '\', \'' . $mime_type . '\')">';
    $html .= '<i class="fas fa-download"></i> Download';
    $html .= '</button>';
    
    $html .= '<button class="action-btn copy-btn" onclick="copyToClipboard(\'' . addslashes($preview['base64_data']) . '\')">';
    $html .= '<i class="fas fa-copy"></i> Copy Base64';
    $html .= '</button>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Display files from a specific step
 */
function displayFilesFromStep($step_data, $field_name, $type_label) {
    if (empty($step_data[$field_name]) || !is_array($step_data[$field_name])) {
        return '';
    }
    
    $files = $step_data[$field_name];
    $html = '<div class="file-preview-section">';
    $html .= '<h4 style="margin: 30px 0 15px 0; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">';
    $html .= '<i class="fas fa-file-upload"></i> ' . $type_label . ' (' . count($files) . ')';
    $html .= '</h4>';
    $html .= '<div class="file-list">';
    
    foreach ($files as $index => $file) {
        if (!empty($file['base64_data'])) {
            $is_image = isViewableImage($file['mime_type'] ?? '', $file['file_name'] ?? '');
            $is_pdf = isPDF($file['mime_type'] ?? '', $file['file_name'] ?? '');
            $html .= displayFileCard($file, $index, $field_name, $is_image, $is_pdf);
        }
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entities Dashboard - Complete Step Data</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px 40px;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .main-content {
            display: flex;
            min-height: 600px;
        }
        
        .sidebar {
            width: 400px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 25px;
            overflow-y: auto;
        }
        
        .entity-list {
            margin-top: 20px;
        }
        
        .entity-item {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .entity-item:hover {
            border-color: #4f46e5;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.1);
        }
        
        .entity-item.active {
            border-color: #4f46e5;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(124, 58, 237, 0.05) 100%);
        }
        
        .entity-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .entity-meta {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .entity-id {
            background: #4f46e5;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 8px;
        }
        
        .step-status {
            display: inline-block;
            font-size: 0.8rem;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 5px;
            margin-top: 3px;
        }
        
        .step-completed {
            background: #10b981;
            color: white;
        }
        
        .step-pending {
            background: #f59e0b;
            color: white;
        }
        
        .content-area {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Tabs Navigation */
        .tabs-navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: #e2e8f0;
            color: #4f46e5;
        }
        
        .tab-btn.active {
            background: #4f46e5;
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Step Content Styling */
        .detail-section {
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 25px;
            background: white;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4f46e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #4f46e5;
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .data-item {
            margin-bottom: 15px;
        }
        
        .data-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-value {
            font-size: 1.1rem;
            color: #1e293b;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
            word-break: break-word;
        }
        
        .data-value.empty {
            color: #94a3b8;
            font-style: italic;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background: #4f46e5;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        /* File Preview Styles */
        .file-preview-section {
            margin-top: 20px;
        }
        
        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .file-item {
            flex: 0 0 calc(33.333% - 20px);
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .file-item:hover {
            border-color: #4f46e5;
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.1);
        }
        
        .file-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            min-height: 60px;
        }
        
        .file-icon {
            font-size: 24px;
            color: #4f46e5;
            margin-right: 10px;
            width: 40px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .file-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            word-break: break-all;
            flex: 1;
        }
        
        .file-preview-container {
            width: 100%;
            height: 200px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .file-preview {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .pdf-preview {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #ef4444;
            text-align: center;
        }
        
        .pdf-preview:hover {
            background: #fef2f2;
        }
        
        .pdf-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .no-preview {
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
            padding: 20px;
        }
        
        .no-preview i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #cbd5e1;
        }
        
        .file-meta {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 10px;
            flex: 1;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .view-btn {
            background: #4f46e5;
            color: white;
        }
        
        .view-btn:hover {
            background: #4338ca;
        }
        
        .download-btn {
            background: #10b981;
            color: white;
        }
        
        .download-btn:hover {
            background: #0da271;
        }
        
        .copy-btn {
            background: #f59e0b;
            color: white;
        }
        
        .copy-btn:hover {
            background: #d97706;
        }
        
        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .image-modal.active {
            display: flex;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .modal-image {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            display: block;
        }
        
        .pdf-embed {
            width: 100%;
            height: 90vh;
            border: none;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            z-index: 1001;
        }
        
        .modal-close:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        .modal-info {
            position: absolute;
            bottom: 10px;
            left: 10px;
            color: white;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.7);
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-submitted {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-under_review {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-completed {
            background: #10b981;
            color: white;
        }
        
        .json-view {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .back-button {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: background 0.3s;
        }
        
        .back-button:hover {
            background: #4338ca;
        }
        
        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }
        
        .info-box {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .warning-box {
            background: #fef3c7;
            color: #92400e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        
        .two-column-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1200px) {
            .two-column-grid {
                grid-template-columns: 1fr;
            }
            
            .file-item {
                flex: 0 0 calc(50% - 20px);
            }
        }
        
        @media (max-width: 1024px) {
            .main-content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .tabs-navigation {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
        }
        
        @media (max-width: 768px) {
            .file-item {
                flex: 0 0 100%;
            }
            
            .data-grid {
                grid-template-columns: 1fr;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .tab-btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
        
        /* Notification */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 1001;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification.show {
            display: flex;
        }
        
        /* Step Completion Indicator */
        .step-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 5px solid #4f46e5;
        }
        
        .step-indicator i {
            font-size: 24px;
            color: #4f46e5;
            margin-right: 15px;
        }
        
        .step-indicator-content h4 {
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .step-indicator-content p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .tax-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .tax-item {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        .tax-item.current-year {
            border-left-color: #4f46e5;
        }
        
        .tax-item.previous-year {
            border-left-color: #8b5cf6;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Preview Modal -->
    <div id="imageModal" class="image-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
            <div id="modalContent"></div>
            <div class="modal-info" id="modalInfo"></div>
        </div>
    </div>
    
    <!-- Notification -->
    <div id="notification" class="notification">
        <i class="fas fa-check-circle"></i>
        <span id="notificationText">Copied to clipboard!</span>
    </div>
    
    <div class="container">
        <header>
            <h1><i class="fas fa-layer-group"></i> Entities Dashboard - Complete Step Data</h1>
            <p class="subtitle">View and manage all entities with their complete step-by-step data</p>
        </header>
        
        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="main-content">
            <div class="sidebar">
                <h2><i class="fas fa-list"></i> All Entities (<?php echo count($entities); ?>)</h2>
                
                <div class="entity-list">
                    <?php if (empty($entities)): ?>
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i> No entities found in the database.
                        </div>
                    <?php else: ?>
                        <?php foreach ($entities as $entity): ?>
                            <a href="?entity_id=<?php echo $entity['id']; ?>" style="text-decoration: none;">
                                <div class="entity-item <?php echo ($selected_entity && $selected_entity['id'] == $entity['id']) ? 'active' : ''; ?>">
                                    <div class="entity-name">
                                        <span class="entity-id">#<?php echo $entity['id']; ?></span>
                                        <?php echo htmlspecialchars($entity['entity_name'] ?: $entity['company_owner_name'] ?: 'Unnamed Entity'); ?>
                                    </div>
                                    <div class="entity-meta">
                                        <div>
                                            <i class="fas fa-hashtag"></i> 
                                            <?php echo htmlspecialchars($entity['engagement_number'] ?: 'No Ref'); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo date('M d, Y', strtotime($entity['created_at'])); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-step-forward"></i> 
                                            Step <?php echo $entity['current_step']; ?>
                                            <span class="status-badge status-<?php echo $entity['application_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $entity['application_status'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($entity['digital_signature_name']): ?>
                                            <div>
                                                <i class="fas fa-signature"></i> 
                                                Signed: <?php echo date('M d', strtotime($entity['accepted_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <!-- Step Status Indicators -->
                                        <div style="margin-top: 8px;">
                                            <span class="step-status <?php echo $entity['company_owner_name'] ? 'step-completed' : 'step-pending'; ?>">
                                                Step 1
                                            </span>
                                            <span class="step-status step-<?php echo $entity['current_step'] >= 2 ? 'completed' : 'pending'; ?>">
                                                Step 2
                                            </span>
                                            <span class="step-status step-<?php echo $entity['current_step'] >= 3 ? 'completed' : 'pending'; ?>">
                                                Step 3
                                            </span>
                                            <span class="step-status step-<?php echo $entity['current_step'] >= 4 ? 'completed' : 'pending'; ?>">
                                                Step 4
                                            </span>
                                            <span class="step-status step-<?php echo $entity['current_step'] >= 5 ? 'completed' : 'pending'; ?>">
                                                Step 5
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($selected_entity): ?>
                    <a href="test_entities.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    
                    <!-- Tabs Navigation -->
                    <div class="tabs-navigation">
                        <button class="tab-btn active" onclick="showTab('overview')">
                            <i class="fas fa-info-circle"></i> Overview
                        </button>
                        <button class="tab-btn" onclick="showTab('step1')">
                            <i class="fas fa-id-card"></i> Step 1: KYC
                            <?php echo getStepCompletionStatus($step_data['step1'], 1); ?>
                        </button>
                        <button class="tab-btn" onclick="showTab('step2')">
                            <i class="fas fa-money-bill-wave"></i> Step 2: Audit Fee
                            <?php echo getStepCompletionStatus($step_data['step2'], 2); ?>
                        </button>
                        <button class="tab-btn" onclick="showTab('step3')">
                            <i class="fas fa-calendar-alt"></i> Step 3: Financial Year
                            <?php echo getStepCompletionStatus($step_data['step3'], 3); ?>
                        </button>
                        <button class="tab-btn" onclick="showTab('step4')">
                            <i class="fas fa-receipt"></i> Step 4: Tax Status
                            <?php echo getStepCompletionStatus($step_data['step4'], 4); ?>
                        </button>
                        <button class="tab-btn" onclick="showTab('step5')">
                            <i class="fas fa-file-signature"></i> Step 5: Engagement
                            <?php echo getStepCompletionStatus($step_data['step5'], 5); ?>
                        </button>
                        <button class="tab-btn" onclick="showTab('raw')">
                            <i class="fas fa-code"></i> Raw Data
                        </button>
                    </div>
                    
                    <!-- Tab Contents -->
                    <div id="overview" class="tab-content active">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-building"></i> Entity Overview
                            </h3>
                            
                            <div class="two-column-grid">
                                <div>
                                    <h4 style="margin-bottom: 15px; color: #475569;">Basic Information</h4>
                                    <div class="data-grid">
                                        <div class="data-item">
                                            <div class="data-label">Entity ID</div>
                                            <div class="data-value">#<?php echo $selected_entity['id']; ?></div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Entity Name</div>
                                            <div class="data-value">
                                                <?php echo htmlspecialchars($selected_entity['entity_name'] ?: 'Not specified'); ?>
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Engagement Number</div>
                                            <div class="data-value">
                                                <?php echo htmlspecialchars($selected_entity['engagement_number'] ?: 'Not generated'); ?>
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Application Type</div>
                                            <div class="data-value">
                                                <?php echo ucfirst($selected_entity['application_type']); ?>
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Current Step</div>
                                            <div class="data-value">
                                                Step <?php echo $selected_entity['current_step']; ?> / 5
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Status</div>
                                            <div class="data-value">
                                                <span class="status-badge status-<?php echo $selected_entity['application_status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $selected_entity['application_status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Created</div>
                                            <div class="data-value">
                                                <?php echo formatDateTime($selected_entity['created_at']); ?>
                                            </div>
                                        </div>
                                        <div class="data-item">
                                            <div class="data-label">Updated</div>
                                            <div class="data-value">
                                                <?php echo formatDateTime($selected_entity['updated_at']); ?>
                                            </div>
                                        </div>
                                        <?php if ($selected_entity['submitted_at']): ?>
                                            <div class="data-item">
                                                <div class="data-label">Submitted</div>
                                                <div class="data-value">
                                                    <?php echo formatDateTime($selected_entity['submitted_at']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($selected_entity['reviewed_at']): ?>
                                            <div class="data-item">
                                                <div class="data-label">Reviewed</div>
                                                <div class="data-value">
                                                    <?php echo formatDateTime($selected_entity['reviewed_at']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 style="margin-bottom: 15px; color: #475569;">Step Completion Status</h4>
                                    
                                    <!-- Step 1 Status -->
                                    <div class="step-indicator">
                                        <i class="fas fa-id-card"></i>
                                        <div class="step-indicator-content">
                                            <h4>Step 1: KYC Information</h4>
                                            <p>
                                                <?php if ($step_data['step1']): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                    <?php if ($step_data['step1']['company_owner_name']): ?>
                                                        - <?php echo htmlspecialchars($step_data['step1']['company_owner_name']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge status-draft">Not Started</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 2 Status -->
                                    <div class="step-indicator">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <div class="step-indicator-content">
                                            <h4>Step 2: Audit Fee Acknowledgement</h4>
                                            <p>
                                                <?php if ($step_data['step2']): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                    <?php if ($step_data['step2']['audit_fee_amount']): ?>
                                                        - <?php echo formatMoney($step_data['step2']['audit_fee_amount']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge status-draft">Not Started</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 3 Status -->
                                    <div class="step-indicator">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div class="step-indicator-content">
                                            <h4>Step 3: Financial Year Details</h4>
                                            <p>
                                                <?php if ($step_data['step3']): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                    <?php if ($step_data['step3']['current_fy_end_date']): ?>
                                                        - FY ends <?php echo formatDate($step_data['step3']['current_fy_end_date']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge status-draft">Not Started</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 4 Status -->
                                    <div class="step-indicator">
                                        <i class="fas fa-receipt"></i>
                                        <div class="step-indicator-content">
                                            <h4>Step 4: Tax Status Disclosure</h4>
                                            <p>
                                                <?php if ($step_data['step4']): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                    <?php if ($step_data['step4']['current_year_corporate_tax_status']): ?>
                                                        - <?php echo ucfirst($step_data['step4']['current_year_corporate_tax_status']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge status-draft">Not Started</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 5 Status -->
                                    <div class="step-indicator">
                                        <i class="fas fa-file-signature"></i>
                                        <div class="step-indicator-content">
                                            <h4>Step 5: Engagement Letter</h4>
                                            <p>
                                                <?php if ($step_data['step5']): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                    <?php if ($step_data['step5']['digital_signature_name']): ?>
                                                        - Signed by <?php echo htmlspecialchars($step_data['step5']['digital_signature_name']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge status-draft">Not Started</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1: KYC Tab -->
                    <div id="step1" class="tab-content">
                        <?php if ($step_data['step1']): ?>
                            <div class="detail-section">
                                <h3 class="section-title">
                                    <i class="fas fa-id-card"></i> Step 1: Know Your Customer (KYC)
                                    <span class="status-badge status-completed">Completed</span>
                                </h3>
                                
                                <!-- Basic Information -->
                                <h4 style="margin: 20px 0 15px 0; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                                    <i class="fas fa-info-circle"></i> Basic Information
                                </h4>
                                <div class="data-grid">
                                    <?php if ($step_data['step1']['business_registration_status']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Business Registration Status</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['business_registration_status']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['company_owner_name']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Company/Owner Name</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['company_owner_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['mainland_company_type']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Mainland Company Type</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['mainland_company_type']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['license_number']): ?>
                                        <div class="data-item">
                                            <div class="data-label">License Number</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['license_number']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['license_issue_date']): ?>
                                        <div class="data-item">
                                            <div class="data-label">License Issue Date</div>
                                            <div class="data-value"><?php echo formatDate($step_data['step1']['license_issue_date']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['license_expiry_date']): ?>
                                        <div class="data-item">
                                            <div class="data-label">License Expiry Date</div>
                                            <div class="data-value"><?php echo formatDate($step_data['step1']['license_expiry_date']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['main_activity']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Main Activity</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['main_activity']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['emirate']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Emirate</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['emirate']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['address']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Address</div>
                                            <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['address']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step1']['total_turnover']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Total Turnover</div>
                                            <div class="data-value"><?php echo formatMoney($step_data['step1']['total_turnover']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Shareholders -->
                                <?php if (!empty($step_data['step1']['shareholders'])): ?>
                                    <h4 style="margin: 30px 0 15px 0; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                                        <i class="fas fa-users"></i> Shareholders (<?php echo count($step_data['step1']['shareholders']); ?>)
                                    </h4>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Percentage</th>
                                                <th>Nationality</th>
                                                <th>Passport Number</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($step_data['step1']['shareholders'] as $shareholder): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($shareholder['name'] ?? ''); ?></td>
                                                    <td><?php echo $shareholder['percentage'] ?? 0; ?>%</td>
                                                    <td><?php echo htmlspecialchars($shareholder['nationality'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($shareholder['passport_number'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                                
                                <!-- UBOs -->
                                <?php if (!empty($step_data['step1']['ubos'])): ?>
                                    <h4 style="margin: 30px 0 15px 0; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                                        <i class="fas fa-user-tie"></i> Ultimate Beneficial Owners (<?php echo count($step_data['step1']['ubos']); ?>)
                                    </h4>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Percentage</th>
                                                <th>Nationality</th>
                                                <th>Passport Number</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($step_data['step1']['ubos'] as $ubo): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ubo['name'] ?? ''); ?></td>
                                                    <td><?php echo $ubo['percentage'] ?? 0; ?>%</td>
                                                    <td><?php echo htmlspecialchars($ubo['nationality'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($ubo['passport_number'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                                
                                <!-- Management Control -->
                                <?php if ($step_data['step1']['management_control']): ?>
                                    <h4 style="margin: 30px 0 15px 0; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                                        <i class="fas fa-user-cog"></i> Management Control
                                    </h4>
                                    <?php if (is_array($step_data['step1']['management_control'])): ?>
                                        <div class="data-grid">
                                            <?php foreach ($step_data['step1']['management_control'] as $key => $value): ?>
                                                <div class="data-item">
                                                    <div class="data-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                                                    <div class="data-value"><?php echo htmlspecialchars($value); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step1']['management_control']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- File Previews from Step 1 -->
                                <?php if ($step_data['step1']): ?>
                                    <!-- EID/Passport Files -->
                                    <?php if (!empty($step_data['step1']['eid_passports'])): ?>
                                        <?php echo displayFilesFromStep($step_data['step1'], 'eid_passports', 'EID/Passport Files'); ?>
                                    <?php endif; ?>
                                    
                                    <!-- Trade License Files -->
                                    <?php if (!empty($step_data['step1']['trade_license'])): ?>
                                        <?php echo displayFilesFromStep($step_data['step1'], 'trade_license', 'Trade License Files'); ?>
                                    <?php endif; ?>
                                    
                                    <!-- Authorization Letter Files -->
                                    <?php if (!empty($step_data['step1']['authorization_letter'])): ?>
                                        <?php echo displayFilesFromStep($step_data['step1'], 'authorization_letter', 'Authorization Letter Files'); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                            </div>
                        <?php else: ?>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i> No KYC (Step 1) data found for this entity.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 2: Audit Fee Tab -->
                    <div id="step2" class="tab-content">
                        <?php if ($step_data['step2']): ?>
                            <div class="detail-section">
                                <h3 class="section-title">
                                    <i class="fas fa-money-bill-wave"></i> Step 2: Audit Fee Acknowledgement
                                    <span class="status-badge status-completed">Completed</span>
                                </h3>
                                
                                <div class="data-grid">
                                    <div class="data-item">
                                        <div class="data-label">Fee Acknowledged</div>
                                        <div class="data-value">
                                            <?php echo $step_data['step2']['audit_fee_acknowledged'] ? 'Yes' : 'No'; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Audit Fee Amount</div>
                                        <div class="data-value">
                                            <?php echo formatMoney($step_data['step2']['audit_fee_amount']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Payment Terms</div>
                                        <div class="data-value">
                                            <?php echo htmlspecialchars($step_data['step2']['payment_terms'] ?: 'Not specified'); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($step_data['step2']['acknowledged_at']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Acknowledged At</div>
                                            <div class="data-value">
                                                <?php echo formatDateTime($step_data['step2']['acknowledged_at']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Created</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step2']['created_at']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Updated</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step2']['updated_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i> No Audit Fee (Step 2) data found for this entity.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 3: Financial Year Tab -->
                    <div id="step3" class="tab-content">
                        <?php if ($step_data['step3']): ?>
                            <div class="detail-section">
                                <h3 class="section-title">
                                    <i class="fas fa-calendar-alt"></i> Step 3: Financial Year Details
                                    <span class="status-badge status-completed">Completed</span>
                                </h3>
                                
                                <div class="two-column-grid">
                                    <div>
                                        <h4 style="margin-bottom: 15px; color: #475569;">Current Financial Year</h4>
                                        <div class="data-grid">
                                            <div class="data-item">
                                                <div class="data-label">Start Date</div>
                                                <div class="data-value"><?php echo formatDate($step_data['step3']['current_fy_start_date']); ?></div>
                                            </div>
                                            <div class="data-item">
                                                <div class="data-label">End Date</div>
                                                <div class="data-value"><?php echo formatDate($step_data['step3']['current_fy_end_date']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h4 style="margin-bottom: 15px; color: #475569;">Previous Financial Year</h4>
                                        <div class="data-grid">
                                            <div class="data-item">
                                                <div class="data-label">Start Date</div>
                                                <div class="data-value">
                                                    <?php echo formatDate($step_data['step3']['previous_fy_start_date']); ?>
                                                </div>
                                            </div>
                                            <div class="data-item">
                                                <div class="data-label">End Date</div>
                                                <div class="data-value">
                                                    <?php echo formatDate($step_data['step3']['previous_fy_end_date']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Previous Auditor Files -->
                                <?php if (!empty($step_data['step3']['previous_auditor_files'])): ?>
                                    <?php echo displayFilesFromStep($step_data['step3'], 'previous_auditor_files', 'Previous Auditor Files'); ?>
                                <?php endif; ?>
                                
                                <div class="data-grid" style="margin-top: 20px;">
                                    <div class="data-item">
                                        <div class="data-label">Created</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step3']['created_at']); ?>
                                        </div>
                                    </div>
                                    <div class="data-item">
                                        <div class="data-label">Updated</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step3']['updated_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i> No Financial Year (Step 3) data found for this entity.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 4: Tax Status Tab -->
                    <div id="step4" class="tab-content">
                        <?php if ($step_data['step4']): ?>
                            <div class="detail-section">
                                <h3 class="section-title">
                                    <i class="fas fa-receipt"></i> Step 4: Tax Status Disclosure
                                    <span class="status-badge status-completed">Completed</span>
                                </h3>
                                
                                <div class="two-column-grid">
                                    <div>
                                        <h4 style="margin-bottom: 15px; color: #475569;">Current Year Tax Status</h4>
                                        <div class="tax-grid">
                                            <?php if ($step_data['step4']['current_year_vat_status']): ?>
                                                <div class="tax-item current-year">
                                                    <div class="data-label">VAT Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['current_year_vat_status']); ?></div>
                                                    <?php if ($step_data['step4']['current_year_vat_reg_number']): ?>
                                                        <div class="data-label">VAT Reg Number</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['current_year_vat_reg_number']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($step_data['step4']['current_year_excise_tax_status']): ?>
                                                <div class="tax-item current-year">
                                                    <div class="data-label">Excise Tax Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['current_year_excise_tax_status']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($step_data['step4']['current_year_corporate_tax_status']): ?>
                                                <div class="tax-item current-year">
                                                    <div class="data-label">Corporate Tax Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['current_year_corporate_tax_status']); ?></div>
                                                    <?php if ($step_data['step4']['current_year_corporate_tax_reg_number']): ?>
                                                        <div class="data-label">CT Reg Number</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['current_year_corporate_tax_reg_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['current_year_corporate_tax_treatment']): ?>
                                                        <div class="data-label">CT Treatment</div>
                                                        <div class="data-value"><?php echo ucfirst(str_replace('_', ' ', $step_data['step4']['current_year_corporate_tax_treatment'])); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['current_year_small_business_relief']): ?>
                                                        <div class="data-label">Small Business Relief</div>
                                                        <div class="data-value"><?php echo $step_data['step4']['current_year_small_business_relief']; ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['current_year_reason_not_registered_ct']): ?>
                                                        <div class="data-label">Reason Not Registered CT</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['current_year_reason_not_registered_ct']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h4 style="margin-bottom: 15px; color: #475569;">Previous Year Tax Status</h4>
                                        <div class="tax-grid">
                                            <?php if ($step_data['step4']['previous_year_vat_status']): ?>
                                                <div class="tax-item previous-year">
                                                    <div class="data-label">VAT Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['previous_year_vat_status']); ?></div>
                                                    <?php if ($step_data['step4']['previous_year_vat_reg_number']): ?>
                                                        <div class="data-label">VAT Reg Number</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['previous_year_vat_reg_number']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($step_data['step4']['previous_year_excise_tax_status']): ?>
                                                <div class="tax-item previous-year">
                                                    <div class="data-label">Excise Tax Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['previous_year_excise_tax_status']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($step_data['step4']['previous_year_corporate_tax_status']): ?>
                                                <div class="tax-item previous-year">
                                                    <div class="data-label">Corporate Tax Status</div>
                                                    <div class="data-value"><?php echo ucfirst($step_data['step4']['previous_year_corporate_tax_status']); ?></div>
                                                    <?php if ($step_data['step4']['previous_year_corporate_tax_reg_number']): ?>
                                                        <div class="data-label">CT Reg Number</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['previous_year_corporate_tax_reg_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['previous_year_corporate_tax_treatment']): ?>
                                                        <div class="data-label">CT Treatment</div>
                                                        <div class="data-value"><?php echo ucfirst(str_replace('_', ' ', $step_data['step4']['previous_year_corporate_tax_treatment'])); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['previous_year_small_business_relief']): ?>
                                                        <div class="data-label">Small Business Relief</div>
                                                        <div class="data-value"><?php echo $step_data['step4']['previous_year_small_business_relief']; ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($step_data['step4']['previous_year_reason_not_registered_ct']): ?>
                                                        <div class="data-label">Reason Not Registered CT</div>
                                                        <div class="data-value"><?php echo htmlspecialchars($step_data['step4']['previous_year_reason_not_registered_ct']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="data-grid" style="margin-top: 20px;">
                                    <div class="data-item">
                                        <div class="data-label">Created</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step4']['created_at']); ?>
                                        </div>
                                    </div>
                                    <div class="data-item">
                                        <div class="data-label">Updated</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step4']['updated_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i> No Tax Status (Step 4) data found for this entity.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 5: Engagement Letter Tab -->
                    <div id="step5" class="tab-content">
                        <?php if ($step_data['step5']): ?>
                            <div class="detail-section">
                                <h3 class="section-title">
                                    <i class="fas fa-file-signature"></i> Step 5: Engagement Letter Acceptance
                                    <span class="status-badge status-completed">Completed</span>
                                </h3>
                                
                                <div class="data-grid">
                                    <div class="data-item">
                                        <div class="data-label">Engagement Number</div>
                                        <div class="data-value">
                                            <?php echo htmlspecialchars($step_data['step5']['engagement_number'] ?: 'Not specified'); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Terms Accepted</div>
                                        <div class="data-value">
                                            <?php echo $step_data['step5']['terms_accepted'] ? 
                                                '<span class="status-badge status-completed">Yes</span>' : 
                                                '<span class="status-badge status-draft">No</span>'; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($step_data['step5']['accepted_at']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Accepted At</div>
                                            <div class="data-value">
                                                <?php echo formatDateTime($step_data['step5']['accepted_at']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step5']['digital_signature_name']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Digital Signature Name</div>
                                            <div class="data-value">
                                                <?php echo htmlspecialchars($step_data['step5']['digital_signature_name']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step5']['digital_signature_date']): ?>
                                        <div class="data-item">
                                            <div class="data-label">Digital Signature Date</div>
                                            <div class="data-value">
                                                <?php echo formatDateTime($step_data['step5']['digital_signature_date']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step5']['ip_address']): ?>
                                        <div class="data-item">
                                            <div class="data-label">IP Address</div>
                                            <div class="data-value">
                                                <?php echo htmlspecialchars($step_data['step5']['ip_address']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($step_data['step5']['user_agent']): ?>
                                        <div class="data-item">
                                            <div class="data-label">User Agent</div>
                                            <div class="data-value">
                                                <small><?php echo htmlspecialchars(substr($step_data['step5']['user_agent'], 0, 100)); ?>...</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Created</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step5']['created_at']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-item">
                                        <div class="data-label">Updated</div>
                                        <div class="data-value">
                                            <?php echo formatDateTime($step_data['step5']['updated_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i> No Engagement Letter (Step 5) data found for this entity.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Raw Data Tab -->
                    <div id="raw" class="tab-content">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-code"></i> Raw Database Data
                            </h3>
                            
                            <div class="data-grid">
                                <div class="data-item">
                                    <div class="data-label">Main Entity Data</div>
                                    <div class="json-view" style="max-height: 300px;">
                                        <pre><?php echo json_encode($selected_entity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                    </div>
                                </div>
                                
                                <?php foreach ($step_data as $step_name => $step): ?>
                                    <?php if ($step): ?>
                                        <div class="data-item">
                                            <div class="data-label"><?php echo strtoupper($step_name); ?> Data</div>
                                            <div class="json-view" style="max-height: 300px;">
                                                <pre><?php echo json_encode($step, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-mouse-pointer"></i>
                        <h2>Select an Entity</h2>
                        <p>Click on any entity from the sidebar to view its complete step-by-step data.</p>
                        <p style="margin-top: 20px; font-size: 0.9rem; color: #94a3b8;">
                            <i class="fas fa-info-circle"></i> 
                            Showing <?php echo count($entities); ?> entities in the database
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer style="background: #f8fafc; padding: 20px; text-align: center; color: #64748b; border-top: 1px solid #e2e8f0;">
            <p>Entities Dashboard | <?php echo count($entities); ?> entities found | Database: <?php echo $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS); ?> | <?php echo date('Y-m-d H:i:s'); ?></p>
        </footer>
    </div>
    
    <script>
        // Tab navigation
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.currentTarget.classList.add('active');
        }
        
        // Modal functions for viewing files
        function openModal(dataUrl, fileName) {
            const modal = document.getElementById('imageModal');
            const modalContent = document.getElementById('modalContent');
            const modalInfo = document.getElementById('modalInfo');
            
            modalInfo.textContent = fileName;
            
            // Check if it's a PDF
            if (dataUrl.includes('application/pdf') || fileName.toLowerCase().endsWith('.pdf')) {
                modalContent.innerHTML = `<iframe src="${dataUrl}" class="pdf-embed" title="${fileName}"></iframe>`;
            } else if (dataUrl.includes('image/')) {
                modalContent.innerHTML = `<img src="${dataUrl}" class="modal-image" alt="${fileName}">`;
            } else {
                modalContent.innerHTML = `
                    <div style="padding: 40px; text-align: center;">
                        <i class="fas fa-file fa-4x" style="color: #4f46e5; margin-bottom: 20px;"></i>
                        <h3>${fileName}</h3>
                        <p>This file type cannot be previewed in browser.</p>
                        <button onclick="downloadBase64File('${fileName}', '${dataUrl.split(',')[1]}', '${dataUrl.split(';')[0].split(':')[1]}')" 
                                class="action-btn download-btn" style="margin-top: 20px;">
                            <i class="fas fa-download"></i> Download File
                        </button>
                    </div>
                `;
            }
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal when clicking outside content
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Download base64 file
        function downloadBase64File(fileName, base64Data, mimeType) {
            try {
                // Decode base64 data
                const byteCharacters = atob(base64Data);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: mimeType });
                
                // Create download link
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                
                showNotification('Download started: ' + fileName);
            } catch (error) {
                console.error('Download error:', error);
                showNotification('Error downloading file: ' + error.message, 'error');
            }
        }
        
        // Copy base64 to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Base64 data copied to clipboard!');
            }).catch(err => {
                showNotification('Failed to copy: ' + err, 'error');
            });
        }
        
        // Notification system
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');
            
            notificationText.textContent = message;
            notification.className = 'notification show';
            
            if (type === 'error') {
                notification.style.background = '#ef4444';
                notification.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + notification.innerHTML;
            }
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Auto-refresh entity list every 30 seconds
        setTimeout(() => {
            if (!window.location.search.includes('entity_id')) {
                window.location.reload();
            }
        }, 30000);
        
        // View all images in a step
        function viewAllImages(stepNumber) {
            // Get all images in the current step
            const images = document.querySelectorAll(`#step${stepNumber} .file-preview`);
            if (images.length > 0) {
                // Create image gallery view
                const galleryHtml = images.map((img, index) => `
                    <div style="margin-bottom: 20px; text-align: center;">
                        <h4>${img.alt}</h4>
                        <img src="${img.src}" style="max-width: 100%; max-height: 70vh; margin: 10px 0;">
                        <div style="margin-top: 10px;">
                            <button onclick="downloadBase64File('${img.alt}', '${img.src.split(',')[1]}', '${img.src.split(';')[0].split(':')[1]}')" 
                                    class="action-btn download-btn">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                `).join('');
                
                const modalContent = document.getElementById('modalContent');
                const modalInfo = document.getElementById('modalInfo');
                const modal = document.getElementById('imageModal');
                
                modalContent.innerHTML = `
                    <div style="padding: 20px; max-height: 90vh; overflow-y: auto;">
                        <h3 style="margin-bottom: 20px; color: #4f46e5;">All Images - Step ${stepNumber}</h3>
                        ${galleryHtml}
                    </div>
                `;
                modalInfo.textContent = `Step ${stepNumber} - ${images.length} images`;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
    </script>
</body>
</html>