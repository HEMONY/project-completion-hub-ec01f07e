<?php
session_start();
require_once '../../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if viewing specific entity documents
$entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$view_documents = $entity_id > 0;

// Check if downloading a specific document
if (isset($_GET['download']) && $view_documents) {
    downloadDocument($pdo, $entity_id, $_GET['download']);
    exit();
}

// Check if downloading all documents
if (isset($_GET['download_all']) && $view_documents) {
    downloadAllDocuments($pdo, $entity_id);
    exit();
}

// Check if previewing a document
if (isset($_GET['preview']) && $view_documents) {
    previewDocument($pdo, $entity_id, $_GET['preview']);
    exit();
}

// ===========================
// HELPER FUNCTIONS
// ===========================

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * Process base64 data on the server side
 * Handles double-encoding and gzip decompression
 */
function processBase64Data($base64Data) {
    try {
        if (empty($base64Data)) {
            return $base64Data;
        }
        
        $processedData = trim($base64Data);
        
        // Remove data URL prefix if present (data:image/png;base64,...)
        if (strpos($processedData, 'data:') === 0) {
            $parts = explode(',', $processedData);
            if (count($parts) > 1) {
                $processedData = $parts[1];
            }
        }
        
        // Clean up any whitespace or newlines
        $processedData = preg_replace('/\s+/', '', $processedData);
        
        // Check if it's valid base64
        if (!preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $processedData)) {
            // Not valid base64, return as-is
            return $base64Data;
        }
        
        // Decode once
        $decoded = base64_decode($processedData, true);
        
        if ($decoded === false) {
            // Failed to decode, return original
            return $base64Data;
        }
        
        // Check if the decoded data is itself base64
        $decodedString = $decoded;
        
        // Check if it looks like base64 again (simplified check)
        if (strlen($decodedString) > 0) {
            // Check if first 100 characters are printable ASCII
            $sample = substr($decodedString, 0, min(100, strlen($decodedString)));
            $isLikelyBase64 = true;
            
            for ($i = 0; $i < strlen($sample); $i++) {
                $char = ord($sample[$i]);
                // Check if character is outside typical ASCII range for PDF/PNG/JPEG
                if ($char < 32 && $char != 9 && $char != 10 && $char != 13) {
                    $isLikelyBase64 = false;
                    break;
                }
            }
            
            // If it looks like base64 again, try decoding
            if ($isLikelyBase64 && preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', substr($decodedString, 0, 100))) {
                $doubleDecoded = base64_decode($decodedString, true);
                if ($doubleDecoded !== false) {
                    $decodedString = $doubleDecoded;
                }
            }
        }
        
        // Check for gzip compression
        if (strlen($decodedString) > 2) {
            // Check for gzip signature (0x1F 0x8B)
            $firstByte = ord($decodedString[0]);
            $secondByte = ord($decodedString[1]);
            
            if ($firstByte === 0x1F && $secondByte === 0x8B) {
                // Try to decompress
                $decompressed = @gzdecode($decodedString);
                if ($decompressed !== false) {
                    $decodedString = $decompressed;
                }
            }
        }
        
        // Return the decoded binary data
        return $decodedString;
        
    } catch (Exception $e) {
        error_log("Error processing base64 data: " . $e->getMessage());
        return $base64Data;
    }
}

/**
 * Process all document data in an array - FIXED VERSION
 */
function processDocumentData($documents) {
    if (empty($documents) || !is_array($documents)) {
        return [];
    }
    
    $processedDocs = [];
    foreach ($documents as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        
        // Create a copy of the document
        $processedDoc = $doc;
        
        // Process base64 data if it exists
        if (isset($doc['base64_data']) && !empty($doc['base64_data'])) {
            $processedDoc['binary_data'] = processBase64Data($doc['base64_data']);
            $processedDoc['has_preview'] = true;
            
            // Get file size
            if (isset($processedDoc['binary_data'])) {
                $processedDoc['size'] = strlen($processedDoc['binary_data']);
            }
            
            // Detect MIME type from filename and content
            if (empty($processedDoc['mime_type']) && isset($doc['file_name'])) {
                $fileName = strtolower($doc['file_name']);
                if (strpos($fileName, '.png') !== false) {
                    $processedDoc['mime_type'] = 'image/png';
                } elseif (strpos($fileName, '.jpg') !== false || strpos($fileName, '.jpeg') !== false) {
                    $processedDoc['mime_type'] = 'image/jpeg';
                } elseif (strpos($fileName, '.gif') !== false) {
                    $processedDoc['mime_type'] = 'image/gif';
                } elseif (strpos($fileName, '.pdf') !== false) {
                    $processedDoc['mime_type'] = 'application/pdf';
                } elseif (strpos($fileName, '.webp') !== false) {
                    $processedDoc['mime_type'] = 'image/webp';
                } else {
                    // Try to detect from binary data
                    if (isset($processedDoc['binary_data']) && !empty($processedDoc['binary_data'])) {
                        $firstBytes = substr($processedDoc['binary_data'], 0, 4);
                        if (strpos($firstBytes, '%PDF') === 0) {
                            $processedDoc['mime_type'] = 'application/pdf';
                        } elseif ($firstBytes === "\x89PNG") {
                            $processedDoc['mime_type'] = 'image/png';
                        } elseif (substr($firstBytes, 0, 2) === "\xFF\xD8") {
                            $processedDoc['mime_type'] = 'image/jpeg';
                        } elseif (substr($firstBytes, 0, 2) === "II" || substr($firstBytes, 0, 2) === "MM") {
                            $processedDoc['mime_type'] = 'image/tiff';
                        } else {
                            $processedDoc['mime_type'] = 'application/octet-stream';
                        }
                    } else {
                        $processedDoc['mime_type'] = 'application/octet-stream';
                    }
                }
            }
        } else {
            $processedDoc['has_preview'] = false;
            $processedDoc['binary_data'] = null;
            $processedDoc['size'] = $doc['size'] ?? 0;
        }
        
        // Ensure uploaded_at exists
        if (empty($processedDoc['uploaded_at'])) {
            $processedDoc['uploaded_at'] = date('Y-m-d H:i:s');
        }
        
        $processedDocs[] = $processedDoc;
    }
    
    return $processedDocs;
}

// ===========================
// MAIN FUNCTIONS - FIXED
// ===========================

// Get ALL entities for the current user (including all statuses)
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.entity_name,
        e.engagement_number,
        e.application_status,
        e.created_at,
        e.updated_at,
        e.submitted_at,
        e.reviewed_at,
        e.cdd_completed,
        e.screening_completed,
        e.ind_completed,
        es1.company_owner_name,
        es1.license_number,
        es1.main_activity,
        es1.emirate,
        es5.digital_signature_name,
        es5.digital_signature_date,
        es5.accepted_at,
        (SELECT COUNT(*) FROM screening_results sr WHERE sr.entity_id = e.id) as screening_count,
        (SELECT COUNT(*) FROM independence_confirmations ic WHERE ic.entity_id = e.id AND ic.confirmation_status = 'confirmed') as independence_count,
        (SELECT id FROM audit_acceptance_memorandum aam WHERE aam.entity_id = e.id LIMIT 1) as memo_id,
        (SELECT id FROM independence_confirmations ic2 WHERE ic2.entity_id = e.id AND ic2.confirmation_status = 'confirmed' LIMIT 1) as independence_id
    FROM entities e
    LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
    LEFT JOIN entity_step5 es5 ON e.id = es5.entity_id
    WHERE e.user_id = ?
    ORDER BY 
        CASE e.application_status 
            WHEN 'approved' THEN 1
            WHEN 'under_review' THEN 2
            WHEN 'submitted' THEN 3
            WHEN 'draft' THEN 4
            WHEN 'rejected' THEN 5
            ELSE 6
        END,
        e.updated_at DESC
");
$stmt->execute([$user_id]);
$entities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get documents for specific entity if requested
$entity_documents = [];
$current_entity = null;

if ($view_documents && $entity_id) {
    // Verify entity belongs to user
    $stmt = $pdo->prepare("
        SELECT e.*, es1.* 
        FROM entities e
        LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
        WHERE e.id = ? AND e.user_id = ?
    ");
    $stmt->execute([$entity_id, $user_id]);
    $current_entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_entity) {
        // Get all documents for this entity
        $entity_documents = getEntityDocuments($pdo, $entity_id, $current_entity);
    } else {
        // Entity doesn't belong to user or doesn't exist
        header('Location: documents.php');
        exit();
    }
}

// Function to get all documents for an entity - FIXED
function getEntityDocuments($pdo, $entity_id, $entity_data) {
    $documents = [];
    
    // 1. Get step1 documents (EID/Passport, Trade License, Authorization Letter)
    $stmt = $pdo->prepare("
        SELECT 
            eid_passports,
            trade_license,
            authorization_letter
        FROM entity_step1 
        WHERE entity_id = ?
    ");
    $stmt->execute([$entity_id]);
    $step1_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step1_data) {
        // Process EID/Passport documents
        if ($step1_data['eid_passports']) {
            $eid_docs = json_decode($step1_data['eid_passports'], true);
            if ($eid_docs && is_array($eid_docs)) {
                $processed_eid_docs = processDocumentData($eid_docs);
                
                $counter = 1;
                foreach ($processed_eid_docs as $doc) {
                    $documents[] = [
                        'id' => 'eid_' . $counter,
                        'type' => 'EID/Passport',
                        'category' => 'identity',
                        'name' => $doc['file_name'] ?? 'EID_Passport_' . $counter . '.pdf',
                        'description' => 'Emirates ID or Passport copy',
                        'date' => $doc['uploaded_at'] ?? $entity_data['created_at'],
                        'size' => $doc['size'] ?? 0,
                        'mime_type' => $doc['mime_type'] ?? 'application/pdf',
                        'has_preview' => $doc['has_preview'] ?? false,
                        'binary_data' => $doc['binary_data'] ?? null,
                        'source' => 'entity_step1',
                        'file_index' => $counter - 1,
                        'document_type' => 'eid_passports'
                    ];
                    $counter++;
                }
            }
        }
        
        // Process Trade License documents
        if ($step1_data['trade_license']) {
            $license_docs = json_decode($step1_data['trade_license'], true);
            if ($license_docs && is_array($license_docs)) {
                $processed_license_docs = processDocumentData($license_docs);
                
                $counter = 1;
                foreach ($processed_license_docs as $doc) {
                    $documents[] = [
                        'id' => 'license_' . $counter,
                        'type' => 'Trade License',
                        'category' => 'business',
                        'name' => $doc['file_name'] ?? 'Trade_License_' . $counter . '.pdf',
                        'description' => 'Business trade license',
                        'date' => $doc['uploaded_at'] ?? $entity_data['created_at'],
                        'size' => $doc['size'] ?? 0,
                        'mime_type' => $doc['mime_type'] ?? 'application/pdf',
                        'has_preview' => $doc['has_preview'] ?? false,
                        'binary_data' => $doc['binary_data'] ?? null,
                        'source' => 'entity_step1',
                        'file_index' => $counter - 1,
                        'document_type' => 'trade_license'
                    ];
                    $counter++;
                }
            }
        }
        
        // Process Authorization Letter documents
        if ($step1_data['authorization_letter']) {
            $auth_docs = json_decode($step1_data['authorization_letter'], true);
            if ($auth_docs && is_array($auth_docs)) {
                $processed_auth_docs = processDocumentData($auth_docs);
                
                $counter = 1;
                foreach ($processed_auth_docs as $doc) {
                    $documents[] = [
                        'id' => 'auth_' . $counter,
                        'type' => 'Authorization Letter',
                        'category' => 'legal',
                        'name' => $doc['file_name'] ?? 'Authorization_Letter_' . $counter . '.pdf',
                        'description' => 'Signed authorization letter',
                        'date' => $doc['uploaded_at'] ?? $entity_data['created_at'],
                        'size' => $doc['size'] ?? 0,
                        'mime_type' => $doc['mime_type'] ?? 'application/pdf',
                        'has_preview' => $doc['has_preview'] ?? false,
                        'binary_data' => $doc['binary_data'] ?? null,
                        'source' => 'entity_step1',
                        'file_index' => $counter - 1,
                        'document_type' => 'authorization_letter'
                    ];
                    $counter++;
                }
            }
        }
    }
    
    // 2. Get step3 documents (Previous Auditor Files)
    $stmt = $pdo->prepare("
        SELECT previous_auditor_files 
        FROM entity_step3 
        WHERE entity_id = ?
    ");
    $stmt->execute([$entity_id]);
    $step3_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($step3_data && $step3_data['previous_auditor_files']) {
        $auditor_docs = json_decode($step3_data['previous_auditor_files'], true);
        if ($auditor_docs && is_array($auditor_docs)) {
            $processed_auditor_docs = processDocumentData($auditor_docs);
            
            $counter = 1;
            foreach ($processed_auditor_docs as $doc) {
                $documents[] = [
                    'id' => 'auditor_' . $counter,
                    'type' => 'Previous Auditor Report',
                    'category' => 'financial',
                    'name' => $doc['file_name'] ?? 'Previous_Auditor_Report_' . $counter . '.pdf',
                    'description' => 'Previous year auditor financial statements',
                    'date' => $doc['uploaded_at'] ?? $entity_data['created_at'],
                    'size' => $doc['size'] ?? 0,
                    'mime_type' => $doc['mime_type'] ?? 'application/pdf',
                    'has_preview' => $doc['has_preview'] ?? false,
                    'binary_data' => $doc['binary_data'] ?? null,
                    'source' => 'entity_step3',
                    'file_index' => $counter - 1,
                    'document_type' => 'previous_auditor_files'
                ];
                $counter++;
            }
        }
    }
    
    // 3. Get Audit Acceptance Memorandum
    $stmt = $pdo->prepare("
        SELECT 
            id,
            client_name,
            engagement_number,
            financial_year,
            commencement_date,
            risk_assessment,
            auditor_name,
            created_at
        FROM audit_acceptance_memorandum 
        WHERE entity_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$entity_id]);
    $memo_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($memo_data) {
        $documents[] = [
            'id' => 'memo_1',
            'type' => 'Audit Acceptance Memorandum',
            'category' => 'official',
            'name' => 'Audit_Acceptance_Memorandum_' . ($memo_data['financial_year'] ?? '') . '.pdf',
            'description' => 'Official audit acceptance document',
            'date' => $memo_data['created_at'],
            'financial_year' => $memo_data['financial_year'],
            'auditor_name' => $memo_data['auditor_name'],
            'risk_assessment' => $memo_data['risk_assessment'],
            'is_memo' => true,
            'source' => 'audit_acceptance_memorandum'
        ];
    }
    
    // 4. Get Independence Confirmation
    $stmt = $pdo->prepare("
        SELECT 
            id,
            confirmation_type,
            confirmation_status,
            confirmation_text,
            signature_name,
            signature_date,
            created_at
        FROM independence_confirmations 
        WHERE entity_id = ? 
        AND confirmation_status = 'confirmed'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$entity_id]);
    $independence_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($independence_data) {
        $documents[] = [
            'id' => 'independence_1',
            'type' => 'Independence Confirmation',
            'category' => 'certificate',
            'name' => 'Independence_Confirmation_Certificate.pdf',
            'description' => 'Auditor independence confirmation',
            'date' => $independence_data['created_at'],
            'signed_by' => $independence_data['signature_name'],
            'is_independence' => true,
            'source' => 'independence_confirmations'
        ];
    }
    
    // Sort documents by date (newest first)
    usort($documents, function($a, $b) {
        $timeA = strtotime($a['date'] ?? '1970-01-01');
        $timeB = strtotime($b['date'] ?? '1970-01-01');
        return $timeB - $timeA;
    });
    
    return $documents;
}

// Function to download a specific document - FIXED
function downloadDocument($pdo, $entity_id, $document_id) {
    // Verify entity belongs to user
    $stmt = $pdo->prepare("SELECT user_id, entity_name FROM entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity || $entity['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Get all documents for this entity
    $documents = getEntityDocuments($pdo, $entity_id, $entity);
    
    // Find the requested document
    $requested_doc = null;
    foreach ($documents as $doc) {
        if ($doc['id'] === $document_id) {
            $requested_doc = $doc;
            break;
        }
    }
    
    if (!$requested_doc) {
        http_response_code(404);
        exit('Document not found');
    }
    
    // Special handling for memo and independence certificates
    if (isset($requested_doc['is_memo']) && $requested_doc['is_memo']) {
        // Generate memo PDF on the fly
        generateMemoPDF($entity, $requested_doc);
        exit();
    }
    
    if (isset($requested_doc['is_independence']) && $requested_doc['is_independence']) {
        // Generate independence certificate PDF on the fly
        generateIndependencePDF($entity, $requested_doc);
        exit();
    }
    
    // Check if binary data is available
    if (!isset($requested_doc['binary_data']) || empty($requested_doc['binary_data'])) {
        http_response_code(404);
        exit('Document content is empty');
    }
    
    $file_content = $requested_doc['binary_data'];
    $file_name = $requested_doc['name'] ?? 'document.pdf';
    $mime_type = $requested_doc['mime_type'] ?? 'application/pdf';
    
    // Set headers for download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . strlen($file_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $file_content;
    exit();
}

// Function to preview a document - FIXED VERSION
function previewDocument($pdo, $entity_id, $document_id) {
    // Verify entity belongs to user
    $stmt = $pdo->prepare("SELECT user_id, entity_name FROM entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity || $entity['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // Get all documents for this entity
    $documents = getEntityDocuments($pdo, $entity_id, $entity);
    
    // Find the requested document
    $requested_doc = null;
    foreach ($documents as $doc) {
        if ($doc['id'] === $document_id) {
            $requested_doc = $doc;
            break;
        }
    }
    
    if (!$requested_doc) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }
    
    // Special handling for memo and independence certificates
    if (isset($requested_doc['is_memo']) && $requested_doc['is_memo']) {
        // Return memo info for display
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'type' => 'memo',
            'mime_type' => 'text/html',
            'data' => $requested_doc
        ]);
        exit();
    }
    
    if (isset($requested_doc['is_independence']) && $requested_doc['is_independence']) {
        // Return independence info for display
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'type' => 'independence',
            'mime_type' => 'text/html',
            'data' => $requested_doc
        ]);
        exit();
    }
    
    // Check if binary data is available
    if (!isset($requested_doc['binary_data']) || empty($requested_doc['binary_data'])) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document content is empty']);
        exit();
    }
    
    $file_content = $requested_doc['binary_data'];
    $mime_type = $requested_doc['mime_type'] ?? 'application/pdf';
    
    // For PDFs
    if ($mime_type === 'application/pdf') {
        // Create temporary file and return its URL
        $temp_file = sys_get_temp_dir() . '/preview_' . uniqid() . '.pdf';
        file_put_contents($temp_file, $file_content);
        
        // Return JSON with file URL
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'type' => 'pdf',
            'mime_type' => $mime_type,
            'file_url' => 'data:application/pdf;base64,' . base64_encode($file_content),
            'file_name' => $requested_doc['name'] ?? 'document.pdf'
        ]);
        
        // Clean up temp file after serving
        register_shutdown_function(function() use ($temp_file) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        });
        exit();
    }
    
    // For images
    if (strpos($mime_type, 'image/') === 0) {
        // Convert to base64 data URL
        $base64_data = base64_encode($file_content);
        $data_url = 'data:' . $mime_type . ';base64,' . $base64_data;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'type' => 'image',
            'mime_type' => $mime_type,
            'data_url' => $data_url,
            'file_name' => $requested_doc['name'] ?? 'image'
        ]);
        exit();
    }
    
    // For other types
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'type' => 'unsupported',
        'message' => 'This file type cannot be previewed inline. Please download the file.'
    ]);
    exit();
}

// Function to generate memo PDF
function generateMemoPDF($entity, $memo_data) {
    // Create simple HTML PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($memo_data['name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 18px; color: #666; margin-bottom: 30px; }
            .section { margin-bottom: 25px; }
            .section-title { font-weight: bold; font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
            .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin-bottom: 10px; }
            .label { font-weight: bold; }
            .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">AUDIT ACCEPTANCE MEMORANDUM</div>
            <div class="subtitle">Muhasba - Audit Engagement Platform</div>
        </div>
        
        <div class="section">
            <div class="section-title">Client Information</div>
            <div class="info-grid">
                <div class="label">Client Name:</div>
                <div>' . htmlspecialchars($memo_data['auditor_name'] ?? 'N/A') . '</div>
                
                <div class="label">Entity Name:</div>
                <div>' . htmlspecialchars($entity['entity_name']) . '</div>
                
                <div class="label">Engagement Number:</div>
                <div>' . htmlspecialchars($memo_data['engagement_number'] ?? $entity['engagement_number'] ?? 'N/A') . '</div>
                
                <div class="label">Financial Year:</div>
                <div>' . htmlspecialchars($memo_data['financial_year'] ?? 'N/A') . '</div>
                
                <div class="label">Commencement Date:</div>
                <div>' . (isset($memo_data['commencement_date']) ? date('F d, Y', strtotime($memo_data['commencement_date'])) : 'N/A') . '</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Risk Assessment</div>
            <p>' . nl2br(htmlspecialchars($memo_data['risk_assessment'] ?? 'No specific risk assessment recorded.')) . '</p>
        </div>
        
        <div class="section">
            <div class="section-title">Terms & Conditions</div>
            <p>This memorandum confirms the acceptance of the audit engagement in accordance with International Standards on Auditing and the professional requirements of the Muhasba platform.</p>
            <p>The engagement is subject to the terms agreed upon during the application process and the platform\'s standard terms of service.</p>
        </div>
        
        <div class="footer">
            <p>Generated on: ' . date('F d, Y') . '</p>
            <p>This is a system-generated document. For official copies, please contact Muhasba support.</p>
        </div>
    </body>
    </html>';
    
    // For now, output as HTML. In production, use a PDF library like TCPDF or Dompdf
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="' . $memo_data['name'] . '"');
    echo $html;
    exit();
}

// Function to generate independence certificate PDF
function generateIndependencePDF($entity, $independence_data) {
    // Create simple HTML PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($independence_data['name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 18px; color: #666; margin-bottom: 30px; }
            .certificate { border: 2px solid #000; padding: 30px; margin: 30px 0; background: #f9f9f9; }
            .certificate-title { font-size: 20px; text-align: center; margin-bottom: 20px; }
            .content { line-height: 1.6; margin-bottom: 20px; text-align: justify; }
            .signature { margin-top: 50px; }
            .signature-line { border-top: 1px solid #000; width: 300px; margin-bottom: 5px; }
            .signature-label { font-size: 14px; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">INDEPENDENCE CONFIRMATION CERTIFICATE</div>
            <div class="subtitle">Muhasba - Professional Independence Declaration</div>
        </div>
        
        <div class="certificate">
            <div class="certificate-title">CERTIFICATE OF INDEPENDENCE</div>
            
            <div class="content">
                <p>This is to certify that the undersigned auditor has confirmed their independence with respect to the audit engagement for:</p>
                
                <p style="text-align: center; font-weight: bold; margin: 20px 0;">
                    ' . htmlspecialchars($entity['entity_name']) . '
                </p>
                
                <p>The auditor has declared that they are free from any relationships, financial interests, or circumstances that could compromise their independence in conducting the audit engagement for the above-mentioned entity.</p>
                
                <p>This confirmation is made in accordance with International Standards on Auditing and the ethical requirements for professional accountants.</p>
                
                <p><strong>Confirmation Type:</strong> ' . htmlspecialchars($independence_data['confirmation_type'] ?? 'General') . '</p>
                
                <p><strong>Confirmation Text:</strong><br>' . nl2br(htmlspecialchars($independence_data['confirmation_text'] ?? 'Standard independence confirmation.')) . '</p>
            </div>
            
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-label">
                    <strong>' . htmlspecialchars($independence_data['signed_by'] ?? 'Auditor') . '</strong><br>
                    Date: ' . (isset($independence_data['signature_date']) ? date('F d, Y', strtotime($independence_data['signature_date'])) : date('F d, Y')) . '
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Generated on: ' . date('F d, Y') . '</p>
            <p>This is a system-generated document. For official copies, please contact Muhasba support.</p>
        </div>
    </body>
    </html>';
    
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="' . $independence_data['name'] . '"');
    echo $html;
    exit();
}

// Function to download all documents as ZIP - FIXED
function downloadAllDocuments($pdo, $entity_id) {
    // Verify entity belongs to user
    $stmt = $pdo->prepare("SELECT user_id, entity_name FROM entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity || $entity['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('ZIP creation not supported on this server');
    }
    
    // Create temporary directory
    $temp_dir = sys_get_temp_dir() . '/documents_' . $entity_id . '_' . time();
    if (!mkdir($temp_dir, 0777, true)) {
        http_response_code(500);
        exit('Failed to create temporary directory');
    }
    
    // Get all documents
    $documents = getEntityDocuments($pdo, $entity_id, $entity);
    
    // Save documents to files
    $files_added = 0;
    foreach ($documents as $doc) {
        if (isset($doc['is_memo']) && $doc['is_memo']) {
            // Generate memo PDF
            $file_name = $temp_dir . '/' . sanitizeFileName($doc['name']);
            ob_start();
            generateMemoPDF($entity, $doc);
            $content = ob_get_clean();
            if (file_put_contents($file_name, $content) !== false) {
                $files_added++;
            }
        } elseif (isset($doc['is_independence']) && $doc['is_independence']) {
            // Generate independence certificate PDF
            $file_name = $temp_dir . '/' . sanitizeFileName($doc['name']);
            ob_start();
            generateIndependencePDF($entity, $doc);
            $content = ob_get_clean();
            if (file_put_contents($file_name, $content) !== false) {
                $files_added++;
            }
        } elseif (isset($doc['binary_data']) && !empty($doc['binary_data'])) {
            $file_content = $doc['binary_data'];
            $file_name = $temp_dir . '/' . sanitizeFileName($doc['name']);
            if (file_put_contents($file_name, $file_content) !== false) {
                $files_added++;
            }
        }
    }
    
    if ($files_added === 0) {
        rmdir($temp_dir);
        http_response_code(404);
        exit('No documents available for download');
    }
    
    // Create ZIP file
    $zip_file = $temp_dir . '/documents.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        exit('Cannot create zip file');
    }
    
    // Add files to zip
    $files = glob($temp_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== 'documents.zip') {
            $zip->addFile($file, basename($file));
        }
    }
    
    $zip->close();
    
    // Send ZIP file to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . sanitizeFileName($entity['entity_name']) . '_documents.zip"');
    header('Content-Length: ' . filesize($zip_file));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($zip_file);
    
    // Clean up
    array_map('unlink', glob($temp_dir . '/*'));
    rmdir($temp_dir);
    
    exit();
}

// Helper function to sanitize file names
function sanitizeFileName($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents Center - Muhasba</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #0b2e59;
            --accent-color: #d17a0b;
            --light-bg: #eef1f6;
            --border-color: #e0e0e0;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: var(--light-bg); 
            color: #333; 
            line-height: 1.6;
            min-height: 100vh;
            padding: 0;
        }
        
        /* Top Header */
        .top-header {
            background: #ffffff;
            padding: 15px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .app-header {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .logout-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .logout-btn:hover {
            background: #f5f5f5;
            color: #333;
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }
        
        /* Left Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            padding: 40px 30px;
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            overflow-y: auto;
        }
        
        .sidebar-title {
            font-size: 22px;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .sidebar-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 40px;
            line-height: 1.5;
        }
        
        /* Navigation Menu */
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .nav-link:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background: var(--secondary-color);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            background: var(--light-bg);
            min-height: calc(100vh - 70px);
        }
        
        /* Content Header */
        .content-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-header h1 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .content-header .subheading {
            font-size: 16px;
            color: #666;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #f5f5f5;
            color: var(--primary-color);
            transform: translateX(-5px);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            color: #666;
        }
        
        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .breadcrumb i {
            font-size: 12px;
        }
        
        /* Status Filter */
        .status-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .status-filter {
            padding: 8px 15px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .status-filter:hover {
            background: #f5f5f5;
        }
        
        .status-filter.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        /* Entities Grid */
        .entities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .entity-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--secondary-color);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .entity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left-color: var(--accent-color);
        }
        
        .entity-card.draft { border-left-color: #6c757d; }
        .entity-card.submitted { border-left-color: var(--info-color); }
        .entity-card.under_review { border-left-color: var(--warning-color); }
        .entity-card.approved { border-left-color: var(--success-color); }
        .entity-card.rejected { border-left-color: var(--danger-color); }
        
        .entity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .entity-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            max-width: 70%;
            word-break: break-word;
        }
        
        .entity-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-submitted { background: var(--info-color); color: white; }
        .status-under_review { background: var(--warning-color); color: white; }
        .status-approved { background: var(--success-color); color: white; }
        .status-rejected { background: var(--danger-color); color: white; }
        
        .entity-meta {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .entity-meta div {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .entity-meta i {
            width: 16px;
            color: var(--secondary-color);
        }
        
        .entity-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .doc-count {
            font-size: 14px;
            color: #666;
        }
        
        .doc-count strong {
            color: var(--primary-color);
        }
        
        .compliance-status {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .compliance-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .compliance-badge.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .compliance-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .view-docs-btn {
            padding: 8px 15px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-docs-btn:hover {
            background: #0a244a;
        }
        
        /* Documents Section */
        .documents-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .section-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .document-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .document-icon {
            width: 50px;
            height: 50px;
            background: var(--secondary-color);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .document-icon.identity { background: #3498db; }
        .document-icon.business { background: #2ecc71; }
        .document-icon.legal { background: #9b59b6; }
        .document-icon.financial { background: #f39c12; }
        .document-icon.official { background: #e74c3c; }
        .document-icon.certificate { background: #1abc9c; }
        
        .document-info {
            flex: 1;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            line-height: 1.3;
        }
        
        .document-type {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .document-details {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .document-details div {
            margin-bottom: 3px;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a244a;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .btn-outline {
            background: white;
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }
        
        .btn-outline:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        /* Document Preview Modal */
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .preview-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .preview-header {
            padding: 20px;
            background: var(--secondary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .close-preview {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }
        
        .close-preview:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .preview-body {
            flex: 1;
            padding: 20px;
            overflow: auto;
        }
        
        #pdf-viewer {
            width: 100%;
            height: 600px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        #image-viewer {
            max-width: 100%;
            max-height: 600px;
            display: block;
            margin: 0 auto;
        }
        
        .preview-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #888;
            margin-bottom: 30px;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            .main-content {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -240px;
                transition: left 0.3s ease;
                z-index: 1001;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1002;
                background: white;
                border: none;
                padding: 10px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                cursor: pointer;
            }
            
            .entities-grid,
            .documents-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .top-header {
                padding: 15px 20px;
            }
            
            .user-info span {
                display: none;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .content-header h1 {
                font-size: 24px;
            }
            
            .document-actions {
                flex-direction: column;
            }
            
            .document-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Document Category Filters */
        .category-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .category-filter {
            padding: 8px 15px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category-filter:hover {
            background: #f5f5f5;
        }
        
        .category-filter.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        /* Preview Button */
        .btn-preview {
            background: #3498db;
            color: white;
        }
        
        .btn-preview:hover {
            background: #2980b9;
        }
        
        .btn-preview:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        
        /* Document Preview Container */
        .document-preview-container {
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-top: 30px;
            display: none;
        }
        
        .preview-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .preview-title-row {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .preview-close-btn {
            background: none;
            border: none;
            color: #666;
            font-size: 16px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .preview-close-btn:hover {
            background: #f5f5f5;
        }
        
        /* Preview content wrappers */
        .preview-content-wrapper {
            display: none;
        }
        
        .preview-content-wrapper.active {
            display: block;
        }
        
        /* PDF controls */
        .pdf-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
            flex-wrap: wrap;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        
        /* Loading spinner */
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* HTML preview content */
        .preview-html-content {
            padding: 20px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
            max-height: 600px;
            overflow-y: auto;
        }
        
        /* Document size indicator */
        .document-size {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }
        
        .text-muted {
            color: #666 !important;
        }
        
        .text-info {
            color: #17a2b8 !important;
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="app-header">Muhasba</div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../../auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Menu Toggle for Mobile -->
    <button class="menu-toggle" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Left Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-title">Documents Center</div>
            <div class="sidebar-subtitle">
                Access and manage all your audit documents
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link active">
                        <i class="fas fa-folder"></i>
                        Documents Center
                    </a>
                </li>
                <li class="nav-item">
                    <a href="entities.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        My Engagements
                    </a>
                </li>
                <li class="nav-item">
                    <a href="new_entity.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        New Audit
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Content Header -->
            <div class="content-header">
                <div>
                    <h1>
                        <?php if ($view_documents && $current_entity): ?>
                            Documents for <?php echo htmlspecialchars($current_entity['entity_name']); ?>
                        <?php else: ?>
                            Documents Center
                        <?php endif; ?>
                    </h1>
                    <div class="subheading">
                        <?php if ($view_documents && $current_entity): ?>
                            View and download all documents for this entity
                        <?php else: ?>
                            Select an entity to view its documents
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($view_documents && $current_entity): ?>
                <a href="documents.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Entities
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Breadcrumb -->
            <?php if ($view_documents && $current_entity): ?>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="documents.php">Documents Center</a>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo htmlspecialchars($current_entity['entity_name']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($view_documents && $current_entity): ?>
                <!-- Entity Documents View -->
                <div class="documents-section">
                    <div class="section-header">
                        <div>
                            <div class="section-title">
                                All Documents
                                <div class="section-subtitle">
                                    <?php echo count($entity_documents); ?> document(s) found
                                </div>
                            </div>
                        </div>
                        <div>
                            <?php if (count($entity_documents) > 0): ?>
                            <a href="documents.php?entity_id=<?php echo $entity_id; ?>&download_all=1" 
                               class="btn btn-outline" onclick="return confirm('Download all documents as ZIP?')">
                                <i class="fas fa-download"></i> Download All as ZIP
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (empty($entity_documents)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3>No Documents Found</h3>
                            <p>This entity doesn't have any documents uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <!-- Category Filters -->
                        <div class="category-filters">
                            <button class="category-filter active" data-category="all">All Documents</button>
                            <button class="category-filter" data-category="identity">Identity</button>
                            <button class="category-filter" data-category="business">Business</button>
                            <button class="category-filter" data-category="legal">Legal</button>
                            <button class="category-filter" data-category="financial">Financial</button>
                            <button class="category-filter" data-category="official">Official</button>
                            <button class="category-filter" data-category="certificate">Certificates</button>
                        </div>
                        
                        <!-- Search Box -->
                        <div class="search-box">
                            <input type="text" id="documentSearch" placeholder="Search documents...">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <div class="documents-grid" id="documentsContainer">
                            <?php foreach ($entity_documents as $doc): 
                                $has_preview = $doc['has_preview'] ?? false;
                                $can_preview = $has_preview && isset($doc['binary_data']) && !empty($doc['binary_data']);
                                $can_preview = $can_preview || isset($doc['is_memo']) || isset($doc['is_independence']);
                                $file_size = $doc['size'] ?? 0;
                            ?>
                            <div class="document-card" 
                                 data-category="<?php echo htmlspecialchars($doc['category']); ?>"
                                 data-type="<?php echo htmlspecialchars($doc['type']); ?>"
                                 data-doc-id="<?php echo htmlspecialchars($doc['id']); ?>">
                                <div class="document-header">
                                    <div class="document-icon <?php echo htmlspecialchars($doc['category']); ?>">
                                        <?php 
                                        $icon = 'fa-file-alt';
                                        switch($doc['category']) {
                                            case 'identity': $icon = 'fa-id-card'; break;
                                            case 'business': $icon = 'fa-building'; break;
                                            case 'legal': $icon = 'fa-gavel'; break;
                                            case 'financial': $icon = 'fa-chart-line'; break;
                                            case 'official': $icon = 'fa-file-contract'; break;
                                            case 'certificate': $icon = 'fa-certificate'; break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-title"><?php echo htmlspecialchars($doc['name']); ?></div>
                                        <div class="document-type"><?php echo htmlspecialchars($doc['type']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="document-details">
                                    <?php if (isset($doc['description'])): ?>
                                    <div><?php echo htmlspecialchars($doc['description']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($doc['date'])); ?>
                                    </div>
                                    
                                    <?php if ($file_size > 0): ?>
                                    <div>
                                        <i class="fas fa-file"></i>
                                        <?php echo formatFileSize($file_size); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($doc['financial_year'])): ?>
                                    <div>
                                        <i class="fas fa-calendar-alt"></i>
                                        Financial Year: <?php echo htmlspecialchars($doc['financial_year']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($doc['signed_by'])): ?>
                                    <div>
                                        <i class="fas fa-signature"></i>
                                        Signed by: <?php echo htmlspecialchars($doc['signed_by']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($doc['auditor_name'])): ?>
                                    <div>
                                        <i class="fas fa-user-tie"></i>
                                        Auditor: <?php echo htmlspecialchars($doc['auditor_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($doc['is_memo'])): ?>
                                    <div class="text-info">
                                        <i class="fas fa-cogs"></i> System Generated
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($doc['is_independence'])): ?>
                                    <div class="text-info">
                                        <i class="fas fa-certificate"></i> Certificate
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="document-actions">
                                    <?php if ($can_preview): ?>
                                    <button class="btn btn-preview preview-document-btn" 
                                            data-doc-id="<?php echo htmlspecialchars($doc['id']); ?>"
                                            data-doc-name="<?php echo htmlspecialchars($doc['name']); ?>"
                                            data-mime-type="<?php echo htmlspecialchars($doc['mime_type'] ?? 'application/pdf'); ?>"
                                            <?php if (isset($doc['is_memo'])): ?>data-is-memo="1"<?php endif; ?>
                                            <?php if (isset($doc['is_independence'])): ?>data-is-independence="1"<?php endif; ?>>
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-preview" disabled>
                                        <i class="fas fa-eye-slash"></i> No Preview
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="documents.php?entity_id=<?php echo $entity_id; ?>&download=<?php echo urlencode($doc['id']); ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Document Preview Container -->
                        <div class="document-preview-container" id="documentPreviewContainer">
                            <div class="preview-header-row">
                                <div class="preview-title-row">
                                    <span id="previewTitle">Document Preview</span>
                                    <small id="previewFileInfo" class="text-muted ms-3"></small>
                                </div>
                                <button class="preview-close-btn" id="closePreviewBtn">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                            
                            <div class="mt-3" id="previewLoading" style="display: none;">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-3">Loading document preview...</p>
                                </div>
                            </div>
                            
                            <!-- PDF Viewer -->
                            <div id="pdfViewerWrapper" class="preview-content-wrapper">
                                <div class="pdf-controls mb-2">
                                    <button class="btn btn-sm btn-outline-secondary" id="prevPageBtn" disabled>
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <span class="mx-2" id="pageInfo">Page: 1 / ?</span>
                                    <button class="btn btn-sm btn-outline-secondary" id="nextPageBtn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <div class="ms-3">
                                        <button class="btn btn-sm btn-outline-secondary" id="zoomOutBtn">
                                            <i class="fas fa-search-minus"></i>
                                        </button>
                                        <span class="mx-2" id="zoomLevel">100%</span>
                                        <button class="btn btn-sm btn-outline-secondary" id="zoomInBtn">
                                            <i class="fas fa-search-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" id="resetZoomBtn">
                                            <i class="fas fa-sync-alt"></i> Reset
                                        </button>
                                    </div>
                                    <a href="#" class="btn btn-sm btn-success ms-auto" id="previewDownloadBtn">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                                <div style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                                    <canvas id="pdfCanvas" style="width: 100%; max-height: 600px;"></canvas>
                                </div>
                            </div>
                            
                            <!-- Image Viewer -->
                            <div id="imageViewerWrapper" class="preview-content-wrapper">
                                <div class="text-center mb-2">
                                    <a href="#" class="btn btn-sm btn-success" id="imageDownloadBtn">
                                        <i class="fas fa-download"></i> Download Image
                                    </a>
                                </div>
                                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                                    <img id="imagePreview" class="img-fluid" style="max-height: 600px;">
                                </div>
                            </div>
                            
                            <!-- HTML Content Viewer (for memos/certificates) -->
                            <div id="htmlViewerWrapper" class="preview-content-wrapper">
                                <div class="text-center mb-2">
                                    <a href="#" class="btn btn-sm btn-success" id="htmlDownloadBtn">
                                        <i class="fas fa-download"></i> Download Document
                                    </a>
                                </div>
                                <div id="htmlPreview" class="preview-html-content">
                                    <!-- HTML content will be loaded here -->
                                </div>
                            </div>
                            
                            <!-- Unsupported Type -->
                            <div id="unsupportedViewerWrapper" class="preview-content-wrapper">
                                <div class="text-center py-5">
                                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                    <h4>Preview Not Available</h4>
                                    <p class="text-muted">This file type cannot be previewed inline.</p>
                                    <a href="#" class="btn btn-success" id="unsupportedDownloadBtn">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Entity Information -->
                <div class="documents-section" style="margin-top: 30px;">
                    <div class="section-header">
                        <div class="section-title">Entity Information</div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <strong>Entity Name:</strong><br>
                            <?php echo htmlspecialchars($current_entity['entity_name']); ?>
                        </div>
                        <div>
                            <strong>Owner Name:</strong><br>
                            <?php echo htmlspecialchars($current_entity['company_owner_name'] ?? 'N/A'); ?>
                        </div>
                        <div>
                            <strong>Status:</strong><br>
                            <span class="entity-status status-<?php echo $current_entity['application_status']; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $current_entity['application_status'])); ?>
                            </span>
                        </div>
                        <div>
                            <strong>Created Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($current_entity['created_at'])); ?>
                        </div>
                        <?php if ($current_entity['license_number']): ?>
                        <div>
                            <strong>License Number:</strong><br>
                            <?php echo htmlspecialchars($current_entity['license_number']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($current_entity['engagement_number']): ?>
                        <div>
                            <strong>Engagement #:</strong><br>
                            <?php echo htmlspecialchars($current_entity['engagement_number']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($current_entity['emirate']): ?>
                        <div>
                            <strong>Emirate:</strong><br>
                            <?php echo htmlspecialchars($current_entity['emirate']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Entities List View -->
                <div class="documents-section">
                    <div class="section-header">
                        <div class="section-title">Select Entity to View Documents</div>
                        <div class="section-subtitle">
                            <?php echo count($entities); ?> entity(ies) found
                        </div>
                    </div>
                    
                    <!-- Status Filters -->
                    <div class="status-filters">
                        <button class="status-filter active" data-status="all">All Entities</button>
                        <button class="status-filter" data-status="draft">Draft</button>
                        <button class="status-filter" data-status="submitted">Submitted</button>
                        <button class="status-filter" data-status="under_review">Under Review</button>
                        <button class="status-filter" data-status="approved">Approved</button>
                        <button class="status-filter" data-status="rejected">Rejected</button>
                    </div>
                    
                    <?php if (empty($entities)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h3>No Entities Found</h3>
                            <p>You don't have any entities yet.</p>
                            <p class="mb-4">Start a new audit engagement to create your first entity.</p>
                            <a href="new_entity.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Start Your First Audit
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="entities-grid" id="entitiesContainer">
                            <?php foreach ($entities as $entity): 
                                $can_view_docs = in_array($entity['application_status'], ['submitted', 'under_review', 'approved']);
                            ?>
                            <div class="entity-card <?php echo $entity['application_status']; ?>" 
                                 data-status="<?php echo $entity['application_status']; ?>">
                                <div class="entity-header">
                                    <div class="entity-title"><?php echo htmlspecialchars($entity['entity_name']); ?></div>
                                    <div class="entity-status status-<?php echo $entity['application_status']; ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $entity['application_status'])); ?>
                                    </div>
                                </div>
                                
                                <!-- Compliance Status -->
                                <?php if ($entity['application_status'] != 'draft'): ?>
                                <div class="compliance-status">
                                    <span class="compliance-badge <?php echo $entity['screening_completed'] ? 'completed' : 'pending'; ?>">
                                        Screening <?php echo $entity['screening_completed'] ? '✓' : '⏳'; ?>
                                    </span>
                                    <span class="compliance-badge <?php echo $entity['ind_completed'] ? 'completed' : 'pending'; ?>">
                                        Independence <?php echo $entity['ind_completed'] ? '✓' : '⏳'; ?>
                                    </span>
                                    <span class="compliance-badge <?php echo $entity['cdd_completed'] ? 'completed' : 'pending'; ?>">
                                        CDD <?php echo $entity['cdd_completed'] ? '✓' : '⏳'; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="entity-meta">
                                    <?php if ($entity['company_owner_name']): ?>
                                    <div>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($entity['company_owner_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entity['license_number']): ?>
                                    <div>
                                        <i class="fas fa-id-card"></i>
                                        License: <?php echo htmlspecialchars($entity['license_number']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entity['main_activity']): ?>
                                    <div>
                                        <i class="fas fa-briefcase"></i>
                                        <?php echo htmlspecialchars($entity['main_activity']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entity['engagement_number']): ?>
                                    <div>
                                        <i class="fas fa-hashtag"></i>
                                        Eng. #: <?php echo htmlspecialchars($entity['engagement_number']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <i class="far fa-calendar"></i>
                                        Created: <?php echo date('M d, Y', strtotime($entity['created_at'])); ?>
                                    </div>
                                    
                                    <?php if ($entity['submitted_at']): ?>
                                    <div>
                                        <i class="fas fa-paper-plane"></i>
                                        Submitted: <?php echo date('M d, Y', strtotime($entity['submitted_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($entity['reviewed_at']): ?>
                                    <div>
                                        <i class="fas fa-check-circle"></i>
                                        Reviewed: <?php echo date('M d, Y', strtotime($entity['reviewed_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="entity-footer">
                                    <div class="doc-count">
                                        <strong>
                                            <?php 
                                            // Count documents
                                            $doc_count = 0;
                                            if ($entity['screening_count']) $doc_count += $entity['screening_count'];
                                            if ($entity['independence_count']) $doc_count += $entity['independence_count'];
                                            if ($entity['memo_id']) $doc_count++;
                                            if ($entity['independence_id']) $doc_count++;
                                            echo $doc_count;
                                            ?>
                                        </strong> document(s)
                                    </div>
                                    <?php if ($can_view_docs): ?>
                                    <a href="documents.php?entity_id=<?php echo $entity['id']; ?>" 
                                       class="view-docs-btn">
                                        View Documents <i class="fas fa-arrow-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="view-docs-btn" style="background: #ccc; cursor: not-allowed;" disabled>
                                        Documents <i class="fas fa-clock"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- PDF.js library for PDF rendering -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>
    
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (window.innerWidth <= 992) {
            menuToggle.style.display = 'block';
        }
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 992) {
                menuToggle.style.display = 'block';
            } else {
                menuToggle.style.display = 'none';
                sidebar.classList.remove('active');
            }
        });
        
        <?php if ($view_documents && $current_entity && !empty($entity_documents)): ?>
        // Document management functions for specific entity view
        
        // Helper function to format file size (JavaScript version)
        function formatFileSizeJS(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Category filters
        document.querySelectorAll('.category-filter').forEach(filter => {
            filter.addEventListener('click', function() {
                // Update active filter
                document.querySelectorAll('.category-filter').forEach(f => f.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                const searchTerm = document.getElementById('documentSearch').value.toLowerCase();
                
                // Filter documents
                filterDocuments(category, searchTerm);
            });
        });
        
        // Search functionality
        document.getElementById('documentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const activeCategory = document.querySelector('.category-filter.active').dataset.category;
            filterDocuments(activeCategory, searchTerm);
        });
        
        function filterDocuments(category, searchTerm) {
            document.querySelectorAll('.document-card').forEach(card => {
                const docCategory = card.dataset.category;
                const docType = card.dataset.type.toLowerCase();
                const docName = card.querySelector('.document-title').textContent.toLowerCase();
                const docDesc = card.querySelector('.document-details').textContent.toLowerCase();
                
                const matchesCategory = category === 'all' || docCategory === category;
                const matchesSearch = !searchTerm || 
                                     docName.includes(searchTerm) || 
                                     docType.includes(searchTerm) ||
                                     docDesc.includes(searchTerm);
                
                card.style.display = matchesCategory && matchesSearch ? 'block' : 'none';
            });
        }
        
        // Document Preview System
        const previewContainer = document.getElementById('documentPreviewContainer');
        const closePreviewBtn = document.getElementById('closePreviewBtn');
        const previewLoading = document.getElementById('previewLoading');
        const previewTitle = document.getElementById('previewTitle');
        const previewFileInfo = document.getElementById('previewFileInfo');
        
        // Viewer wrappers
        const pdfViewerWrapper = document.getElementById('pdfViewerWrapper');
        const imageViewerWrapper = document.getElementById('imageViewerWrapper');
        const htmlViewerWrapper = document.getElementById('htmlViewerWrapper');
        const unsupportedViewerWrapper = document.getElementById('unsupportedViewerWrapper');
        
        // PDF viewer elements
        const pdfCanvas = document.getElementById('pdfCanvas');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageInfo = document.getElementById('pageInfo');
        const zoomInBtn = document.getElementById('zoomInBtn');
        const zoomOutBtn = document.getElementById('zoomOutBtn');
        const resetZoomBtn = document.getElementById('resetZoomBtn');
        const zoomLevel = document.getElementById('zoomLevel');
        const previewDownloadBtn = document.getElementById('previewDownloadBtn');
        
        // Image viewer elements
        const imagePreview = document.getElementById('imagePreview');
        const imageDownloadBtn = document.getElementById('imageDownloadBtn');
        
        // HTML viewer elements
        const htmlPreview = document.getElementById('htmlPreview');
        const htmlDownloadBtn = document.getElementById('htmlDownloadBtn');
        
        // Unsupported viewer elements
        const unsupportedDownloadBtn = document.getElementById('unsupportedDownloadBtn');
        
        // PDF viewer state
        let pdfDoc = null;
        let pdfPageNum = 1;
        let pdfPageRendering = false;
        let pdfPageNumPending = null;
        let pdfScale = 1.0;
        
        let currentPreviewDocId = null;
        let currentPreviewDocName = null;
        let currentPreviewDownloadUrl = null;
        
        // Close preview
        closePreviewBtn.addEventListener('click', hidePreview);
        
        function hidePreview() {
            previewContainer.style.display = 'none';
            hideAllViewers();
            previewLoading.style.display = 'none';
            
            // Reset PDF viewer
            if (pdfDoc) {
                pdfDoc.destroy();
                pdfDoc = null;
            }
            pdfPageNum = 1;
            pdfScale = 1.0;
            
            currentPreviewDocId = null;
            currentPreviewDocName = null;
            currentPreviewDownloadUrl = null;
        }
        
        function hideAllViewers() {
            pdfViewerWrapper.classList.remove('active');
            imageViewerWrapper.classList.remove('active');
            htmlViewerWrapper.classList.remove('active');
            unsupportedViewerWrapper.classList.remove('active');
        }
        
        // Document preview buttons
        document.querySelectorAll('.preview-document-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const docId = this.dataset.docId;
                const docName = this.dataset.docName;
                const mimeType = this.dataset.mimeType;
                const isMemo = this.dataset.isMemo === '1';
                const isIndependence = this.dataset.isIndependence === '1';
                
                // Find the document card
                const documentCard = document.querySelector(`.document-card[data-doc-id="${docId}"]`);
                if (!documentCard) {
                    alert('Document not found.');
                    return;
                }
                
                // Get download URL
                const downloadLink = documentCard.querySelector('.btn-success');
                if (!downloadLink) {
                    alert('No download available for this document.');
                    return;
                }
                
                currentPreviewDocId = docId;
                currentPreviewDocName = docName;
                currentPreviewDownloadUrl = downloadLink.href;
                
                // Show preview container
                previewContainer.style.display = 'block';
                previewTitle.textContent = docName;
                previewFileInfo.textContent = '';
                
                // Show loading state
                hideAllViewers();
                previewLoading.style.display = 'block';
                
                // Set download buttons
                previewDownloadBtn.href = downloadLink.href;
                imageDownloadBtn.href = downloadLink.href;
                htmlDownloadBtn.href = downloadLink.href;
                unsupportedDownloadBtn.href = downloadLink.href;
                
                try {
                    // Fetch preview data
                    const previewUrl = `documents.php?entity_id=<?php echo $entity_id; ?>&preview=${encodeURIComponent(docId)}`;
                    const response = await fetch(previewUrl);
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to load preview');
                    }
                    
                    // Hide loading
                    previewLoading.style.display = 'none';
                    
                    // Handle different preview types
                    if (data.type === 'pdf') {
                        await showPDFPreview(data.file_url, docName);
                    } else if (data.type === 'image') {
                        showImagePreview(data.data_url, docName);
                    } else if (data.type === 'memo' || data.type === 'independence') {
                        showHTMLPreview(data.data, docName);
                    } else {
                        showUnsupportedPreview(docName);
                    }
                    
                } catch (error) {
                    console.error('Error loading preview:', error);
                    previewLoading.style.display = 'none';
                    showUnsupportedPreview(docName);
                }
            });
        });
        
        // PDF Preview Functions
        async function showPDFPreview(pdfUrl, docName) {
            hideAllViewers();
            pdfViewerWrapper.classList.add('active');
            
            // Reset PDF state
            pdfPageNum = 1;
            pdfScale = 1.0;
            zoomLevel.textContent = '100%';
            
            try {
                // Load PDF
                const loadingTask = pdfjsLib.getDocument(pdfUrl);
                pdfDoc = await loadingTask.promise;
                
                // Update page info
                updatePageInfo();
                
                // Enable/disable navigation
                prevPageBtn.disabled = true;
                nextPageBtn.disabled = pdfDoc.numPages <= 1;
                
                // Render first page
                await renderPDFPage(pdfPageNum);
                
            } catch (error) {
                console.error('Error loading PDF:', error);
                showUnsupportedPreview(docName);
            }
        }
        
        async function renderPDFPage(pageNum) {
            pdfPageRendering = true;
            
            try {
                const page = await pdfDoc.getPage(pageNum);
                const viewport = page.getViewport({ scale: pdfScale });
                
                // Set canvas dimensions
                const canvas = pdfCanvas;
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                // Render page
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                
                await page.render(renderContext).promise;
                
                pdfPageRendering = false;
                
                if (pdfPageNumPending !== null) {
                    renderPDFPage(pdfPageNumPending);
                    pdfPageNumPending = null;
                }
                
                updatePageInfo();
                updateNavigationButtons();
                
            } catch (error) {
                console.error('Error rendering PDF page:', error);
                pdfPageRendering = false;
            }
        }
        
        function updatePageInfo() {
            if (pdfDoc) {
                pageInfo.textContent = `Page: ${pdfPageNum} / ${pdfDoc.numPages}`;
            }
        }
        
        function updateNavigationButtons() {
            if (pdfDoc) {
                prevPageBtn.disabled = pdfPageNum <= 1;
                nextPageBtn.disabled = pdfPageNum >= pdfDoc.numPages;
            }
        }
        
        function queueRenderPDFPage(pageNum) {
            if (pdfPageRendering) {
                pdfPageNumPending = pageNum;
            } else {
                renderPDFPage(pageNum);
            }
        }
        
        // PDF Navigation
        prevPageBtn.addEventListener('click', () => {
            if (pdfPageNum > 1) {
                pdfPageNum--;
                queueRenderPDFPage(pdfPageNum);
            }
        });
        
        nextPageBtn.addEventListener('click', () => {
            if (pdfDoc && pdfPageNum < pdfDoc.numPages) {
                pdfPageNum++;
                queueRenderPDFPage(pdfPageNum);
            }
        });
        
        // PDF Zoom
        zoomInBtn.addEventListener('click', () => {
            if (pdfScale < 3.0) {
                pdfScale += 0.25;
                zoomLevel.textContent = Math.round(pdfScale * 100) + '%';
                queueRenderPDFPage(pdfPageNum);
            }
        });
        
        zoomOutBtn.addEventListener('click', () => {
            if (pdfScale > 0.25) {
                pdfScale -= 0.25;
                zoomLevel.textContent = Math.round(pdfScale * 100) + '%';
                queueRenderPDFPage(pdfPageNum);
            }
        });
        
        resetZoomBtn.addEventListener('click', () => {
            pdfScale = 1.0;
            zoomLevel.textContent = '100%';
            queueRenderPDFPage(pdfPageNum);
        });
        
        // Image Preview
        function showImagePreview(dataUrl, docName) {
            hideAllViewers();
            imageViewerWrapper.classList.add('active');
            imagePreview.src = dataUrl;
            imagePreview.alt = docName;
        }
        
        // HTML Preview (for memos/certificates)
        function showHTMLPreview(data, docName) {
            hideAllViewers();
            htmlViewerWrapper.classList.add('active');
            
            // Generate HTML content from data
            let html = '<div class="html-document">';
            
            if (data.is_memo) {
                html += `
                    <h3 class="mb-4">Audit Acceptance Memorandum</h3>
                    <div class="mb-4">
                        <p><strong>Client:</strong> ${data.auditor_name || 'N/A'}</p>
                        <p><strong>Entity:</strong> <?php echo htmlspecialchars($current_entity['entity_name']); ?></p>
                        <p><strong>Engagement #:</strong> ${data.engagement_number || 'N/A'}</p>
                        <p><strong>Financial Year:</strong> ${data.financial_year || 'N/A'}</p>
                        ${data.commencement_date ? `<p><strong>Commencement Date:</strong> ${new Date(data.commencement_date).toLocaleDateString()}</p>` : ''}
                    </div>
                    <div class="mb-4">
                        <h5>Risk Assessment:</h5>
                        <p>${data.risk_assessment || 'No specific risk assessment recorded.'}</p>
                    </div>
                    <div class="mt-5 pt-4 border-top">
                        <p><small>This is a preview of the system-generated memorandum. Download the full document for official use.</small></p>
                    </div>
                `;
            } else if (data.is_independence) {
                html += `
                    <h3 class="mb-4">Independence Confirmation Certificate</h3>
                    <div class="mb-4">
                        <p><strong>Entity:</strong> <?php echo htmlspecialchars($current_entity['entity_name']); ?></p>
                        <p><strong>Confirmation Type:</strong> ${data.confirmation_type || 'General'}</p>
                        <p><strong>Signed By:</strong> ${data.signed_by || 'N/A'}</p>
                        ${data.signature_date ? `<p><strong>Signature Date:</strong> ${new Date(data.signature_date).toLocaleDateString()}</p>` : ''}
                    </div>
                    <div class="mb-4">
                        <h5>Confirmation Text:</h5>
                        <p>${data.confirmation_text || 'Standard independence confirmation.'}</p>
                    </div>
                    <div class="mt-5 pt-4 border-top">
                        <p><small>This is a preview of the system-generated certificate. Download the full document for official use.</small></p>
                    </div>
                `;
            }
            
            html += '</div>';
            htmlPreview.innerHTML = html;
        }
        
        // Unsupported Preview
        function showUnsupportedPreview(docName) {
            hideAllViewers();
            unsupportedViewerWrapper.classList.add('active');
        }
        
        <?php else: ?>
        // Entity filtering functionality for main entities view
        document.querySelectorAll('.status-filter').forEach(filter => {
            filter.addEventListener('click', function() {
                // Update active filter
                document.querySelectorAll('.status-filter').forEach(f => f.classList.remove('active'));
                this.classList.add('active');
                
                const status = this.dataset.status;
                
                // Filter entities
                document.querySelectorAll('.entity-card').forEach(card => {
                    const entityStatus = card.dataset.status;
                    
                    if (status === 'all' || entityStatus === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>