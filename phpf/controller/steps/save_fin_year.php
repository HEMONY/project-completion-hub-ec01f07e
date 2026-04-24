<?php
session_start();

// Include your existing database connection
require_once __DIR__ . '/../../config/db.php';

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function convertToDBDate($date) {
    if (empty($date)) return null;
    
    $parts = explode('/', $date);
    if (count($parts) !== 3) return null;
    
    $day = (int)$parts[0];
    $month = (int)$parts[1];
    $year = (int)$parts[2];
    
    if ($month < 1 || $month > 12) return null;
    if ($day < 1 || $day > 31) return null;
    
    if (!checkdate($month, $day, $year)) return null;
    
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function handleFileUpload($file, $entity_id, $user_id) {
    if (!isset($file) || !is_array($file) || !isset($file['error'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'File upload verification failed'];
        }
        
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileType = $file['type'];
        $fileTmpName = $file['tmp_name'];
        
        $allowedMimeTypes = ['application/pdf'];
        $allowedExtensions = ['pdf'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileType, $allowedMimeTypes) || !in_array($fileExtension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Only PDF files are allowed'];
        }
        
        $maxSize = 10 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            return ['success' => false, 'message' => 'File size must be less than 10MB'];
        }
        
        $sanitizedFileName = preg_replace('/[^a-zA-Z0-9._\-() ]/', '_', $fileName);
        $fileContent = file_get_contents($fileTmpName);
        
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Failed to read file content'];
        }
        
        $base64Data = base64_encode($fileContent);
        
        $fileInfo = [
            'file_name' => $sanitizedFileName,
            'original_name' => $fileName,
            'mime_type' => $fileType,
            'size' => $fileSize,
            'base64_data' => $base64Data,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'entity_id' => $entity_id,
            'user_id' => $user_id
        ];
        
        return ['success' => true, 'files' => [$fileInfo]];
        
    } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'files' => []];
    } else {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return ['success' => false, 'message' => $errorMessages[$file['error']] ?? 'Unknown upload error'];
    }
}

// ============================================================================
// MAIN PROCESSING LOGIC
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = 'Please login to continue';
        header('Location: ../../views/direct/kyc-steps/Financial-Year.php');
        exit;
    }

    if (!isset($_SESSION['entity_id']) || empty($_SESSION['entity_id'])) {
        $_SESSION['error_message'] = 'No entity found. Please start a new application.';
        header('Location: ../../views/direct/kyc-steps/Financial-Year.php');
        exit;
    }

    $entity_id = $_SESSION['entity_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Database connection failed: ' . $e->getMessage();
        header('Location: ../../views/direct/kyc-steps/Financial-Year.php');
        exit;
    }

    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get form data
        $previous_audited = $_POST['previous_audited'] ?? $_POST['previous-audited'] ?? '';
        $first_financial_statements = $_POST['first_financial_statements'] ?? $_POST['first-financial-statements'] ?? '';
        
        // Get date fields
        $current_start_date = $_POST['current-start-date'] ?? $_POST['current_start_date'] ?? '';
        $current_end_date = $_POST['current-end-date'] ?? $_POST['current_end_date'] ?? '';
        $previous_start_date = $_POST['previous-start-date'] ?? $_POST['previous_start_date'] ?? '';
        $previous_end_date = $_POST['previous-end-date'] ?? $_POST['previous_end_date'] ?? '';
        $first_start_date = $_POST['first-start-date'] ?? $_POST['first_start_date'] ?? '';
        $first_end_date = $_POST['first-end-date'] ?? $_POST['first_end_date'] ?? '';
        
        // Determine if user is returning or new
        $isReturningUser = isset($_SESSION['form']['step0']['application_type']) && 
                          $_SESSION['form']['step0']['application_type'] === 'return';
        
        // Initialize variables
        $current_fy_start_date = null;
        $current_fy_end_date = null;
        $previous_fy_start_date = null;
        $previous_fy_end_date = null;
        $previous_auditor_files_json = null;
        $fileResult = null;
        
        // LOGIC: If current dates are not provided, use first financial year dates
        if (!empty($current_start_date) && !empty($current_end_date)) {
            // Use provided current dates
            $current_fy_start_date = convertToDBDate($current_start_date);
            $current_fy_end_date = convertToDBDate($current_end_date);
        } elseif (!empty($first_start_date) && !empty($first_end_date)) {
            // Use first financial year dates as current dates
            $current_fy_start_date = convertToDBDate($first_start_date);
            $current_fy_end_date = convertToDBDate($first_end_date);
        }
        
        // Get previous dates if provided
        if (!empty($previous_start_date) && !empty($previous_end_date)) {
            $previous_fy_start_date = convertToDBDate($previous_start_date);
            $previous_fy_end_date = convertToDBDate($previous_end_date);
        }
        
        // Validate we have current dates (either from current or first)
        if (!$current_fy_start_date || !$current_fy_end_date) {
            throw new Exception('Financial year dates are required. Please provide either current financial year or first financial year dates in DD/MM/YYYY format.');
        }
        
        // Validate previous_audited (only if previous dates are provided)
        if ($previous_fy_start_date && $previous_fy_end_date && empty($previous_audited)) {
            throw new Exception('Please specify if previous year was audited');
        }
        
        // If it's first financial statements, previous_audited should be 'No'
        if ($first_financial_statements === 'Yes') {
            $previous_audited = 'No';
            $previous_fy_start_date = null;
            $previous_fy_end_date = null;
        }
        
        // Handle file upload if previous year was audited AND previous dates exist
        if ($previous_audited === 'Yes' && $previous_fy_start_date && $previous_fy_end_date) {
            if ($isReturningUser) {
                // FOR RETURNING USERS: File is optional
                if (isset($_FILES['upload-audit-statement']) && $_FILES['upload-audit-statement']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $fileResult = handleFileUpload($_FILES['upload-audit-statement'], $entity_id, $user_id);
                    if (!$fileResult['success']) {
                        throw new Exception($fileResult['message']);
                    }
                    
                    if (!empty($fileResult['files'])) {
                        $previous_auditor_files_json = json_encode($fileResult['files'], JSON_UNESCAPED_UNICODE);
                    }
                }
                // If no file uploaded, that's OK for returning users
            } else {
                // FOR NEW USERS: File is REQUIRED
                if (!isset($_FILES['upload-audit-statement']) || $_FILES['upload-audit-statement']['error'] === UPLOAD_ERR_NO_FILE) {
                    throw new Exception('Audited financial statements file is required when previous year was audited');
                }
                
                $fileResult = handleFileUpload($_FILES['upload-audit-statement'], $entity_id, $user_id);
                if (!$fileResult['success']) {
                    throw new Exception($fileResult['message']);
                }
                
                if (empty($fileResult['files'])) {
                    throw new Exception('Failed to upload audit statement file');
                }
                
                $previous_auditor_files_json = json_encode($fileResult['files'], JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Validate date order
        if ($current_fy_end_date <= $current_fy_start_date) {
            throw new Exception('Financial year end date must be after start date');
        }
        
        if ($previous_fy_start_date && $previous_fy_end_date && $previous_fy_end_date <= $previous_fy_start_date) {
            throw new Exception('Previous financial year end date must be after start date');
        }
        
        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM entity_step3 WHERE entity_id = ?");
        $checkStmt->execute([$entity_id]);
        
        // Prepare SQL parameters
        $params = [
            ':entity_id' => $entity_id,
            ':current_fy_start_date' => $current_fy_start_date,
            ':current_fy_end_date' => $current_fy_end_date,
            ':previous_fy_start_date' => $previous_fy_start_date,
            ':previous_fy_end_date' => $previous_fy_end_date,
            ':previous_auditor_files' => $previous_auditor_files_json
        ];
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing record
            $sql = "UPDATE entity_step3 SET 
                    current_fy_start_date = :current_fy_start_date,
                    current_fy_end_date = :current_fy_end_date,
                    previous_fy_start_date = :previous_fy_start_date,
                    previous_fy_end_date = :previous_fy_end_date,
                    previous_auditor_files = :previous_auditor_files,
                    updated_at = NOW()
                    WHERE entity_id = :entity_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $action = 'updated';
        } else {
            // Insert new record
            $sql = "INSERT INTO entity_step3 
                    (entity_id, current_fy_start_date, current_fy_end_date, 
                     previous_fy_start_date, previous_fy_end_date, 
                     previous_auditor_files) 
                    VALUES (:entity_id, :current_fy_start_date, :current_fy_end_date, 
                            :previous_fy_start_date, :previous_fy_end_date, 
                            :previous_auditor_files)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $action = 'inserted';
        }
        
        // Update entity current step
        $updateEntityStmt = $conn->prepare("UPDATE entities SET current_step = 4 WHERE id = :entity_id");
        $updateEntityStmt->execute([':entity_id' => $entity_id]);
        
        // Commit transaction
        $conn->commit();
        
        // Update session
        $_SESSION['form']['step3'] = [
            'previous_audited' => $previous_audited,
            'first_financial_statements' => $first_financial_statements,
            'current_start_date' => $current_start_date,
            'current_end_date' => $current_end_date,
            'previous_start_date' => $previous_start_date,
            'previous_end_date' => $previous_end_date,
            'first_start_date' => $first_start_date,
            'first_end_date' => $first_end_date,
            'is_returning_user' => $isReturningUser
        ];
        
        // Store file info in session if uploaded
        if (isset($fileResult) && !empty($fileResult['files'])) {
            $sessionFiles = [];
            foreach ($fileResult['files'] as $file) {
                $sessionFiles[] = [
                    'name' => $file['original_name'] ?? $file['file_name'],
                    'size' => $file['size'],
                    'type' => $file['mime_type'],
                    'uploaded_at' => $file['uploaded_at']
                ];
            }
            $_SESSION['uploaded_audit_statement'] = $sessionFiles;
        } else {
            unset($_SESSION['uploaded_audit_statement']);
        }
        
        // Clear any error messages
        if (isset($_SESSION['error_message'])) {
            unset($_SESSION['error_message']);
        }
        
        // SUCCESS - Redirect to TAX STATUS page
        $_SESSION['form']['step3'] = $_POST;
        header('Location: ../../views/direct/kyc-steps/TAX STATUS.php?success=1&entity_id=' . $entity_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Store form data in session and show error
        $_SESSION['form_data'] = $_POST;
        $_SESSION['error_message'] = $e->getMessage();
        
        // Redirect back to Financial Year page with error
        header('Location: ../../views/direct/kyc-steps/Financial-Year.php?error=1&entity_id=' . $entity_id);
        exit;
    }
    
} else {
    // Invalid request method
    $_SESSION['error_message'] = 'Invalid request method. Only POST is accepted.';
    header('Location: ../../views/direct/kyc-steps/Financial-Year.php');
    exit;
}