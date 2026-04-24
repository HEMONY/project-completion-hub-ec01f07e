<?php
// calln8n.php - FIXED TO MATCH YOUR WORKING FRONTEND CODE
require_once '../../config/db.php';

// ===========================
// CONFIGURATION
// ===========================
$UPLOAD_DIR = '../../uploads/entities/';
$N8N_WEBHOOK_URL = 'https://n8n.muhasba.com/webhook-test/ec80628e-52cc-41e8-8db5-2ed4a7a9cae8';

// Set headers FIRST to prevent any output before JSON
header('Content-Type: application/json');

// Create upload directory if it doesn't exist
if (!file_exists($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// ===========================
// EXACT SAME FUNCTIONS AS YOUR fetch_entity_data.php
// ===========================

/**
 * Process base64 data on the server side
 * EXACT COPY FROM YOUR WORKING fetch_entity_data.php
 */
function processBase64Data($base64Data, $isCompressed = false) {
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
        
        // Check if it looks like base64 again
        if (preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $decodedString)) {
            // Try decoding again
            $doubleDecoded = base64_decode($decodedString, true);
            if ($doubleDecoded !== false) {
                $decodedString = $doubleDecoded;
            }
        }
        
        // Handle gzip decompression
        if ($isCompressed && function_exists('gzdecode')) {
            // Check for gzip signature (0x1F 0x8B)
            if (strlen($decodedString) > 2 && 
                ord($decodedString[0]) === 0x1F && 
                ord($decodedString[1]) === 0x8B) {
                
                $decompressed = @gzdecode($decodedString);
                if ($decompressed !== false) {
                    $decodedString = $decompressed;
                }
            }
        }
        
        // Convert back to base64 for client-side use
        return base64_encode($decodedString);
        
    } catch (Exception $e) {
        error_log("Error processing base64 data: " . $e->getMessage());
        return $base64Data;
    }
}

/**
 * Process all document data in an array
 * EXACT COPY FROM YOUR WORKING fetch_entity_data.php
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
            $isCompressed = isset($doc['compressed']) && $doc['compressed'] === true;
            $processedDoc['processed_base64'] = processBase64Data($doc['base64_data'], $isCompressed);
            $processedDoc['has_preview'] = true;
            
            // Detect MIME type
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
                }
            }
        } else {
            $processedDoc['has_preview'] = false;
        }
        
        $processedDocs[] = $processedDoc;
    }
    
    return $processedDocs;
}

// ===========================
// EXACT SAME DATABASE QUERY AS YOUR fetch_entity_data.php
// ===========================

function getEntityData($entity_id) {
    global $pdo;
    
    try {
        // EXACT SAME QUERY as your fetch_entity_data.php
        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                es1.*,
                es2.*,
                es3.*,
                es4.*,
                es5.*,
                u.full_name as client_name,
                u.email as client_email,
                u.mobile as client_mobile
            FROM entities e
            LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
            LEFT JOIN entity_step2 es2 ON e.id = es2.entity_id
            LEFT JOIN entity_step3 es3 ON e.id = es3.entity_id
            LEFT JOIN entity_step4 es4 ON e.id = es4.entity_id
            LEFT JOIN entity_step5 es5 ON e.id = es5.entity_id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$entity_id]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entity) {
            error_log("Entity not found with ID: {$entity_id}");
            return null;
        }
        
        // Format dates
        if ($entity['license_issue_date']) {
            $entity['license_issue_date_formatted'] = date('d/m/Y', strtotime($entity['license_issue_date']));
        }
        
        if ($entity['license_expiry_date']) {
            $entity['license_expiry_date_formatted'] = date('d/m/Y', strtotime($entity['license_expiry_date']));
        }
        
        // Format financial year dates from entity_step3
        if ($entity['current_fy_start_date']) {
            $entity['current_fy_start_date_formatted'] = date('d/m/Y', strtotime($entity['current_fy_start_date']));
        }
        
        if ($entity['current_fy_end_date']) {
            $entity['current_fy_end_date_formatted'] = date('d/m/Y', strtotime($entity['current_fy_end_date']));
        }
        
        if ($entity['previous_fy_start_date']) {
            $entity['previous_fy_start_date_formatted'] = date('d/m/Y', strtotime($entity['previous_fy_start_date']));
        }
        
        if ($entity['previous_fy_end_date']) {
            $entity['previous_fy_end_date_formatted'] = date('d/m/Y', strtotime($entity['previous_fy_end_date']));
        }
        
        // Parse JSON data for shareholders and UBOs
        $entity['shareholders'] = json_decode($entity['shareholders'] ?? '[]', true);
        $entity['ubos'] = json_decode($entity['ubos'] ?? '[]', true);
        
        // Process management_control data
        $managementControl = $entity['management_control'] ?? '';
        $entity['management_control_parsed'] = '';
        
        if (!empty($managementControl)) {
            if (strpos($managementControl, '{') === 0 || strpos($managementControl, '[') === 0) {
                $decoded = json_decode($managementControl, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entity['management_control_parsed'] = $decoded;
                } else {
                    $entity['management_control_parsed'] = $managementControl;
                }
            } else {
                $entity['management_control_parsed'] = $managementControl;
            }
        }
        
        // Process document data - EXACT SAME AS YOUR WORKING CODE
        $raw_eid_passports = json_decode($entity['eid_passports'] ?? '[]', true);
        $raw_trade_license = json_decode($entity['trade_license'] ?? '[]', true);
        $raw_authorization_letter = json_decode($entity['authorization_letter'] ?? '[]', true);
        $raw_previous_auditor_files = json_decode($entity['previous_auditor_files'] ?? '[]', true);
        
        $entity['eid_passports'] = processDocumentData($raw_eid_passports);
        $entity['trade_license'] = processDocumentData($raw_trade_license);
        $entity['authorization_letter'] = processDocumentData($raw_authorization_letter);
        $entity['previous_auditor_files'] = processDocumentData($raw_previous_auditor_files);
        
        error_log("Found entity: " . ($entity['entity_name'] ?? 'Unknown'));
        error_log("EID passports count: " . count($entity['eid_passports']));
        error_log("Trade license count: " . count($entity['trade_license']));
        error_log("Authorization letter count: " . count($entity['authorization_letter']));
        error_log("Previous auditor files count: " . count($entity['previous_auditor_files']));
        
        return $entity;
        
    } catch (PDOException $e) {
        error_log("Database error in getEntityData: " . $e->getMessage());
        return null;
    }
}

// ===========================
// FILE SAVING FUNCTIONS
// ===========================

function saveBinaryFile($binaryData, $entityId, $documentType, $originalFileName) {
    global $UPLOAD_DIR;
    
    try {
        if (empty($binaryData) || strlen($binaryData) === 0) {
            return ['success' => false, 'error' => 'Empty binary data'];
        }
        
        // Create directories
        $entityDir = $UPLOAD_DIR . 'entity_' . $entityId . '/';
        if (!file_exists($entityDir)) {
            mkdir($entityDir, 0755, true);
        }
        
        $typeDir = $entityDir . $documentType . '/';
        if (!file_exists($typeDir)) {
            mkdir($typeDir, 0755, true);
        }
        
        // Create safe filename
        $safeFileName = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $originalFileName);
        
        // Determine file extension from original filename
        $fileExtension = '';
        if (!empty($originalFileName)) {
            $pathInfo = pathinfo($originalFileName);
            if (isset($pathInfo['extension'])) {
                $fileExtension = '.' . strtolower($pathInfo['extension']);
            }
        }
        
        // If no extension, try to detect from MIME type
        if (empty($fileExtension) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $binaryData);
            finfo_close($finfo);
            
            switch ($mimeType) {
                case 'image/png':
                    $fileExtension = '.png';
                    break;
                case 'image/jpeg':
                    $fileExtension = '.jpg';
                    break;
                case 'image/gif':
                    $fileExtension = '.gif';
                    break;
                case 'application/pdf':
                    $fileExtension = '.pdf';
                    break;
                default:
                    $fileExtension = '.bin';
            }
        } else {
            $fileExtension = '.bin';
        }
        
        // Generate unique filename
        $uniqueId = uniqid();
        $finalFileName = $uniqueId . '_' . $safeFileName;
        $finalFileName = preg_replace('/\.[^.]+$/', '', $finalFileName) . $fileExtension;
        
        $filePath = $typeDir . $finalFileName;
        
        // Save binary data
        $bytesWritten = file_put_contents($filePath, $binaryData, LOCK_EX);
        
        if ($bytesWritten !== false && $bytesWritten > 0) {
            $fileSize = filesize($filePath);
            
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
            } else {
                $mimeType = 'application/octet-stream';
            }
            
            error_log("✓ Saved: {$filePath} ({$fileSize} bytes, {$mimeType})");
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'file_url' => str_replace('../../', '/', $filePath),
                'file_name' => $finalFileName,
                'original_name' => $originalFileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ];
        } else {
            error_log("✗ Failed to save: {$filePath}");
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
    } catch (Exception $e) {
        error_log("Exception saving file: " . $e->getMessage());
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// ===========================
// N8N WEBHOOK
// ===========================

function sendToN8n($entityId, $entityName, $entityData, $documentsData) {
    global $N8N_WEBHOOK_URL;
    
    try {
        $payload = [
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'timestamp' => date('Y-m-d H:i:s'),
            'entity_info' => [
                'license_number' => $entityData['license_number'] ?? '',
                'license_issue_date' => $entityData['license_issue_date_formatted'] ?? '',
                'license_expiry_date' => $entityData['license_expiry_date_formatted'] ?? '',
                'main_activity' => $entityData['main_activity'] ?? '',
                'emirate' => $entityData['emirate'] ?? '',
                'address' => $entityData['address'] ?? '',
                'client_name' => $entityData['client_name'] ?? '',
                'client_email' => $entityData['client_email'] ?? '',
                'client_mobile' => $entityData['client_mobile'] ?? '',
                'shareholders' => $entityData['shareholders'] ?? [],
                'ubos' => $entityData['ubos'] ?? []
            ],
            'total_documents' => count($documentsData),
            'documents' => $documentsData
        ];
        
        error_log("Sending to n8n: Entity {$entityId}, {$entityName}, " . count($documentsData) . " docs");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $N8N_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $response, 'http_code' => $httpCode];
        } else {
            return ['success' => false, 'error' => 'HTTP Error: ' . $httpCode, 'curl_error' => $curlError, 'response' => $response];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// ===========================
// MAIN PROCESSING FUNCTION
// ===========================

function processEntityDocuments($entityId) {
    error_log("=== START Processing entity ID: {$entityId} ===");
    
    // Get entity data using EXACT SAME function as your frontend
    $entityData = getEntityData($entityId);
    
    if (!$entityData) {
        error_log("❌ Entity not found in database or error fetching data");
        return ['success' => false, 'error' => 'Entity not found in database or error fetching data'];
    }
    
    $entityName = $entityData['entity_name'] ?? 'Unknown Entity';
    error_log("✅ Found entity: {$entityName} (ID: {$entityId})");
    
    $allDocuments = [];
    $processedCount = 0;
    
    // Document types to process - using the SAME structure as your frontend
    $documentTypes = [
        'identification' => $entityData['eid_passports'] ?? [],
        'trade_license' => $entityData['trade_license'] ?? [],
        'authorization_letter' => $entityData['authorization_letter'] ?? [],
        'previous_auditor' => $entityData['previous_auditor_files'] ?? []
    ];
    
    foreach ($documentTypes as $docType => $docs) {
        $docCount = count($docs);
        error_log("Processing {$docType}: {$docCount} documents");
        
        if ($docCount === 0) {
            continue;
        }
        
        foreach ($docs as $index => $doc) {
            $originalName = $doc['file_name'] ?? "{$docType}_{$index}";
            
            // Check for processed_base64 (from your PHP processing)
            if (isset($doc['processed_base64']) && !empty($doc['processed_base64'])) {
                $binaryData = base64_decode($doc['processed_base64'], true);
                
                if ($binaryData !== false && strlen($binaryData) > 0) {
                    $saveResult = saveBinaryFile($binaryData, $entityId, $docType, $originalName);
                    
                    if ($saveResult['success']) {
                        $documentInfo = [
                            'entity_id' => $entityId,
                            'entity_name' => $entityName,
                            'document_type' => $docType,
                            'original_name' => $originalName,
                            'saved_name' => $saveResult['file_name'],
                            'file_url' => $saveResult['file_url'],
                            'file_path' => $saveResult['file_path'],
                            'file_size' => $saveResult['file_size'],
                            'mime_type' => $saveResult['mime_type'],
                            'upload_date' => date('Y-m-d H:i:s')
                        ];
                        
                        $allDocuments[] = $documentInfo;
                        $processedCount++;
                        
                        error_log("✓ Saved {$docType}: {$originalName} ({$saveResult['file_size']} bytes)");
                    } else {
                        error_log("✗ Failed to save {$docType}: " . $saveResult['error']);
                    }
                } else {
                    error_log("✗ Failed to decode processed_base64 for {$docType}: {$originalName}");
                }
            } else {
                error_log("✗ No processed_base64 data for {$docType}: {$originalName}");
            }
        }
    }
    
    if ($processedCount === 0) {
        error_log("❌ No documents were processed successfully");
        return [
            'success' => false,
            'error' => 'No valid documents found to process',
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'debug_info' => [
                'identification_count' => count($documentTypes['identification']),
                'trade_license_count' => count($documentTypes['trade_license']),
                'authorization_letter_count' => count($documentTypes['authorization_letter']),
                'previous_auditor_count' => count($documentTypes['previous_auditor'])
            ]
        ];
    }
    
    error_log("✅ Processed {$processedCount} documents, sending to n8n...");
    
    // Send to n8n
    $n8nResult = sendToN8n($entityId, $entityName, $entityData, $allDocuments);
    
    // Prepare response
    $response = [
        'success' => true,
        'entity_id' => $entityId,
        'entity_name' => $entityName,
        'documents_processed' => $processedCount,
        'documents' => $allDocuments,
        'n8n_result' => $n8nResult,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // If n8n failed, we still consider it a success since files were saved
    if (!$n8nResult['success']) {
        $response['n8n_warning'] = 'Files saved but n8n webhook failed: ' . ($n8nResult['error'] ?? 'Unknown error');
        error_log("⚠️ n8n webhook failed: " . ($n8nResult['error'] ?? 'Unknown error'));
    } else {
        error_log("✅ n8n webhook successful");
    }
    
    error_log("=== COMPLETED Processing entity ID: {$entityId} ===");
    return $response;
}

// ===========================
// EXECUTION HANDLER
// ===========================

try {
    // Get entity_id from various sources
    $entityId = 0;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['entity_id'])) {
        $entityId = intval($_GET['entity_id']);
        error_log("Got entity_id from GET: {$entityId}");
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['entity_id'])) {
            $entityId = intval($_POST['entity_id']);
            error_log("Got entity_id from POST form: {$entityId}");
        } else {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $jsonData = json_decode($input, true);
                if ($jsonData && isset($jsonData['entity_id'])) {
                    $entityId = intval($jsonData['entity_id']);
                    error_log("Got entity_id from JSON: {$entityId}");
                }
            }
        }
    } elseif (php_sapi_name() === 'cli' && isset($argv[1])) {
        $entityId = intval($argv[1]);
        error_log("Got entity_id from CLI: {$entityId}");
    }
    
    if ($entityId <= 0) {
        throw new Exception('Valid Entity ID is required. Received: ' . $entityId);
    }
    
    // Process documents
    $result = processEntityDocuments($entityId);
    
    // Output result
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
    error_log("Exception: " . $e->getMessage());
}

exit;