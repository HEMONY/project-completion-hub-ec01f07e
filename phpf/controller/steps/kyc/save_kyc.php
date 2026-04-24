<?php
// save_kyc.php
session_start();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log POST data for debugging
error_log("=== KYC SAVE START ===");
error_log("POST data received: " . print_r($_POST, true));
error_log("FILES data count: " . count($_FILES));
error_log("SESSION form data: " . print_r($_SESSION['form'] ?? [], true));

// Debug specific UBO fields
error_log("=== UBO DATA DEBUG ===");
error_log("UBO Question: " . ($_POST['ubo-question'] ?? 'NOT SET'));
error_log("UBO Names: " . (isset($_POST['ubo_name']) ? print_r($_POST['ubo_name'], true) : 'NOT SET'));
error_log("UBO Capitals: " . (isset($_POST['ubo_capital']) ? print_r($_POST['ubo_capital'], true) : 'NOT SET'));
error_log("UBO Nationalities: " . (isset($_POST['ubo_nationality']) ? print_r($_POST['ubo_nationality'], true) : 'NOT SET'));

require_once '../../../config/db.php';

// Initialize variables
$errors = [];
$success = false;
$step1_id = null;
$entity_id = null;

// Function to handle file uploads
function handleFileUpload($fileInputName, $maxSize = 5 * 1024 * 1024) {
    $uploadedFiles = [];
    
    if (isset($_FILES[$fileInputName]) && !empty($_FILES[$fileInputName]['name'][0])) {
        $files = $_FILES[$fileInputName];
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // Check file size
                if ($files['size'][$i] > $maxSize) {
                    error_log("File too large: " . $files['name'][$i] . " (" . $files['size'][$i] . " bytes)");
                    continue;
                }
                
                // Read file and convert to base64
                $fileContent = file_get_contents($files['tmp_name'][$i]);
                if ($fileContent === false) {
                    error_log("Failed to read file: " . $files['name'][$i]);
                    continue;
                }
                
                $base64Data = base64_encode($fileContent);
                
                $uploadedFiles[] = [
                    'file_name' => $files['name'][$i],
                    'mime_type' => $files['type'][$i],
                    'size' => $files['size'][$i],
                    'base64_data' => $base64Data,
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'compressed' => false
                ];
                
                error_log("Uploaded file via $_FILES: " . $files['name'][$i] . " (" . $files['size'][$i] . " bytes)");
            } else {
                error_log("File upload error for " . $files['name'][$i] . ": " . $files['error'][$i]);
            }
        }
    } else {
        error_log("No files found in $_FILES[$fileInputName] or empty upload");
    }
    
    return $uploadedFiles;
}

// Function to compress base64 data
function compressBase64($base64_data) {
    if (strlen($base64_data) < 10000) {
        return $base64_data;
    }
    
    $decoded = base64_decode($base64_data);
    if ($decoded === false) {
        return $base64_data;
    }
    
    if (function_exists('gzcompress')) {
        $compressed = gzcompress($decoded, 9);
        if ($compressed !== false) {
            return base64_encode($compressed);
        }
    }
    
    return $base64_data;
}

// Function to generate engagement number
function generateEngagementNumber($pdo) {
    $prefix = 'ENG';
    $year = date('Y');
    $month = date('m');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM entities WHERE engagement_number LIKE ?");
    $like_pattern = $prefix . $year . $month . '%';
    $stmt->execute([$like_pattern]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] + 1;
    
    return $prefix . $year . $month . str_pad($count, 3, '0', STR_PAD_LEFT);
}

try {
    // Get database connection
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Prepare data from session AND POST (we need both)
    $step1_data = $_SESSION['form']['step1'] ?? [];
    
    // First, check if we have data_in_session flag
    $data_in_session = $_POST['data_in_session'] ?? false;
    if ($data_in_session) {
        error_log("Using data from session");
        // Use session data primarily
        $post_data = $_POST;
    } else {
        // Use POST data primarily, fall back to session
        $post_data = array_merge($step1_data, $_POST);
    }
    
    // Validate required fields
    $required_fields = [
        'registration-status' => 'Business Registration Status',
        'owner-name' => 'Company/Owner Name',
        'address' => 'Address',
        'turnover' => 'Total Turnover'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $form_field => $field_name) {
        $value = $post_data[$form_field] ?? '';
        if (empty(trim($value))) {
            $missing_fields[] = $field_name;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
    }
    
    // Get company/owner name
    $company_owner_name = $post_data['owner-name'] ?? '';
    error_log("Company/Owner Name: " . $company_owner_name);
    
    // Check if user is logged in
    $user_id = $_SESSION['user_id'] ?? 1;
    
    // STEP 1: CREATE OR GET ENTITY RECORD
    if (isset($_SESSION['form']['step0']['entity_id'])) {
        $entity_id = $_SESSION['form']['step0']['entity_id'];
        
        $stmt = $pdo->prepare("UPDATE entities SET entity_name = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$company_owner_name, $entity_id]);
        
        error_log("Updated existing entity ID: " . $entity_id . " with name: " . $company_owner_name);
    } else {
        $engagement_number = generateEngagementNumber($pdo);
        
        $step0_data = $_SESSION['form']['step0'] ?? [];
        $application_type = isset($step0_data['new']) && $step0_data['new'] ? 'new' : 'return';
        
        $stmt = $pdo->prepare("INSERT INTO entities 
                              (user_id, entity_name, engagement_number, application_type, application_status, current_step) 
                              VALUES (?, ?, ?, ?, 'draft', 1)");
        $stmt->execute([$user_id, $company_owner_name, $engagement_number, $application_type]);
        
        $entity_id = $pdo->lastInsertId();
        
        $_SESSION['form']['step0'] = [
            'entity_id' => $entity_id,
            'entity_name' => $company_owner_name,
            'engagement_number' => $engagement_number,
            'application_type' => $application_type
        ];
        
        error_log("Created new entity ID: " . $entity_id . " with engagement number: " . $engagement_number);
    }
    
    // Verify entity
    $stmt = $pdo->prepare("SELECT id, entity_name, engagement_number FROM entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity) {
        throw new Exception("Failed to create or retrieve entity record");
    }
    
    // STEP 2: PROCESS ALL FORM DATA
    
    // Business registration status
    $business_registration_status = $post_data['registration-status'] ?? '';
    error_log("Business Registration Status: " . $business_registration_status);
    
    // Mainland company type
    $mainland_company_type = null;
    if ($business_registration_status === 'Mainland Licensed-Multiple Owners') {
        $mainland_company_type = $post_data['mainland-type'] ?? null;
        error_log("Mainland Company Type: " . $mainland_company_type);
    }
    
    // License data
    $license_number = $post_data['license-number'] ?? null;
    $license_issue_date = null;
    $license_expiry_date = null;
    
    if (!empty($license_number)) {
        $issue_date_input = $post_data['issue-date'] ?? '';
        $expiry_date_input = $post_data['expiry-date'] ?? '';
        
        if (!empty($issue_date_input)) {
            $date_parts = explode('/', $issue_date_input);
            if (count($date_parts) === 3) {
                $license_issue_date = sprintf('%04d-%02d-%02d', 
                    $date_parts[2], $date_parts[1], $date_parts[0]);
            }
        }
        
        if (!empty($expiry_date_input)) {
            $date_parts = explode('/', $expiry_date_input);
            if (count($date_parts) === 3) {
                $license_expiry_date = sprintf('%04d-%02d-%02d', 
                    $date_parts[2], $date_parts[1], $date_parts[0]);
            }
        }
        
        error_log("License Number: " . $license_number . ", Issue: " . $license_issue_date . ", Expiry: " . $license_expiry_date);
    }
    
    // Other fields
    $main_activity = $post_data['main-activity-input'] ?? '';
    $emirate = $post_data['emirate-select'] ?? '';
    $address = $post_data['address'] ?? '';
    
    error_log("Main Activity: " . $main_activity);
    error_log("Emirate: " . $emirate);
    error_log("Address: " . $address);
    
    // STEP 3: PROCESS SHAREHOLDERS - FIXED
    $shareholders = [];
    if (isset($post_data['shareholder_name']) && is_array($post_data['shareholder_name'])) {
        error_log("Processing shareholders - count: " . count($post_data['shareholder_name']));
        
        for ($i = 0; $i < count($post_data['shareholder_name']); $i++) {
            $name = trim($post_data['shareholder_name'][$i] ?? '');
            if (!empty($name)) {
                $shareholder = [
                    'name' => $name,
                    'capital_percentage' => floatval($post_data['shareholder_capital'][$i] ?? 0),
                    'nationality' => trim($post_data['shareholder_nationality'][$i] ?? ''),
                    'emirates_id' => trim($post_data['shareholder_emiratesId'][$i] ?? ''),
                    'emirates_id_expiry' => trim($post_data['shareholder_expiryDate'][$i] ?? ''),
                    'pep_status' => $post_data['shareholder_pep'][$i] ?? 'No'
                ];
                
                // Validate EID expiry date format
                if (!empty($shareholder['emirates_id_expiry'])) {
                    $date_parts = explode('/', $shareholder['emirates_id_expiry']);
                    if (count($date_parts) === 3) {
                        $shareholder['emirates_id_expiry'] = sprintf('%04d-%02d-%02d', 
                            $date_parts[2], $date_parts[1], $date_parts[0]);
                    }
                }
                
                $shareholders[] = $shareholder;
                error_log("  Shareholder $i: " . $name . " (" . $shareholder['capital_percentage'] . "%)");
            }
        }
    }
    error_log("Total shareholders processed: " . count($shareholders));
    
    // STEP 4: PROCESS UBOS - FIXED
    $ubos = [];
    $ubo_question = $post_data['ubo-question'] ?? 'No';
    error_log("UBO Question from form: " . $ubo_question);
    
    if ($ubo_question === 'Yes') {
        error_log("UBO Question is YES, processing UBOs...");
        
        // Check if UBO data exists in POST
        if (isset($post_data['ubo_name']) && is_array($post_data['ubo_name'])) {
            error_log("UBO names found in POST: " . count($post_data['ubo_name']));
            
            for ($i = 0; $i < count($post_data['ubo_name']); $i++) {
                $name = trim($post_data['ubo_name'][$i] ?? '');
                if (!empty($name)) {
                    $ubo = [
                        'name' => $name,
                        'capital_percentage' => floatval($post_data['ubo_capital'][$i] ?? 0),
                        'nationality' => trim($post_data['ubo_nationality'][$i] ?? ''),
                        'emirates_id' => trim($post_data['ubo_emiratesId'][$i] ?? ''),
                        'emirates_id_expiry' => trim($post_data['ubo_expiryDate'][$i] ?? ''),
                        'pep_status' => $post_data['ubo_pep'][$i] ?? 'No'
                    ];
                    
                    // Validate EID expiry date format
                    if (!empty($ubo['emirates_id_expiry'])) {
                        $date_parts = explode('/', $ubo['emirates_id_expiry']);
                        if (count($date_parts) === 3) {
                            $ubo['emirates_id_expiry'] = sprintf('%04d-%02d-%02d', 
                                $date_parts[2], $date_parts[1], $date_parts[0]);
                        }
                    }
                    
                    $ubos[] = $ubo;
                    error_log("  UBO $i: " . $name . " (" . $ubo['capital_percentage'] . "%)");
                }
            }
        } else {
            error_log("WARNING: UBO question is Yes but no UBO data found in POST!");
            // Try to get from session
            if (isset($step1_data['ubo_name']) && is_array($step1_data['ubo_name'])) {
                error_log("Found UBO data in session instead");
                for ($i = 0; $i < count($step1_data['ubo_name']); $i++) {
                    $name = trim($step1_data['ubo_name'][$i] ?? '');
                    if (!empty($name)) {
                        $ubos[] = [
                            'name' => $name,
                            'capital_percentage' => floatval($step1_data['ubo_capital'][$i] ?? 0),
                            'nationality' => trim($step1_data['ubo_nationality'][$i] ?? ''),
                            'emirates_id' => trim($step1_data['ubo_emiratesId'][$i] ?? ''),
                            'emirates_id_expiry' => trim($step1_data['ubo_expiryDate'][$i] ?? ''),
                            'pep_status' => $step1_data['ubo_pep'][$i] ?? 'No'
                        ];
                    }
                }
            }
        }
    } else {
        error_log("UBO Question is NO, skipping UBO processing");
    }
    
    error_log("Total UBOs processed: " . count($ubos));
    
    // STEP 5: PROCESS MANAGEMENT CONTROL
    $management_control = $post_data['management-control-select'] ?? null;
    error_log("Management Control: " . $management_control);
    
    // Process management details if "Other" is selected
    $management_details = null;
    if ($management_control === 'Other') {
        if (isset($post_data['management_name']) && !empty($post_data['management_name'])) {
            $management_details = [
                'name' => $post_data['management_name'] ?? '',
                'position' => $post_data['management_position'] ?? '',
                'nationality' => $post_data['management_nationality'] ?? '',
                'emirates_id' => $post_data['management_emiratesId'] ?? '',
                'emirates_id_expiry' => $post_data['management_expiryDate'] ?? '',
                'pep_status' => $post_data['management_pep'] ?? 'No'
            ];
            
            // Convert date format if needed
            if (!empty($management_details['emirates_id_expiry'])) {
                $date_parts = explode('/', $management_details['emirates_id_expiry']);
                if (count($date_parts) === 3) {
                    $management_details['emirates_id_expiry'] = sprintf('%04d-%02d-%02d', 
                        $date_parts[2], $date_parts[1], $date_parts[0]);
                }
            }
            
            // Store as JSON
            $management_control = json_encode($management_details, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // STEP 6: PROCESS TOTAL TURNOVER
    $total_turnover_input = $post_data['turnover'] ?? '0';
    $total_turnover = str_replace(',', '', $total_turnover_input);
    $total_turnover = floatval($total_turnover);
    error_log("Total Turnover: " . $total_turnover);
    
    // STEP 7: PROCESS FILE UPLOADS
    error_log("=== PROCESSING FILE UPLOADS ===");
    
    // Process EID/Passport files
    $eid_passports = [];
    if (isset($_FILES['upload_id_passport']) && !empty($_FILES['upload_id_passport']['name'][0])) {
        $eid_passports = handleFileUpload('upload_id_passport');
    } elseif (isset($step1_data['id_passport_files']) && is_array($step1_data['id_passport_files'])) {
        error_log("Using EID files from session");
        $eid_passports = $step1_data['id_passport_files'];
    }
    error_log("EID/Passport files: " . count($eid_passports));
    
    // Process Trade License files
    $trade_license = [];
    if (isset($_FILES['upload_trade_license']) && !empty($_FILES['upload_trade_license']['name'][0])) {
        $trade_license = handleFileUpload('upload_trade_license');
    } elseif (isset($step1_data['trade_license_files']) && is_array($step1_data['trade_license_files'])) {
        error_log("Using Trade License files from session");
        $trade_license = $step1_data['trade_license_files'];
    }
    error_log("Trade License files: " . count($trade_license));
    
    // Apply compression
    foreach ($eid_passports as &$file) {
        if (isset($file['base64_data'])) {
            $original_size = strlen($file['base64_data']);
            $file['base64_data'] = compressBase64($file['base64_data']);
            $new_size = strlen($file['base64_data']);
            $file['compressed'] = ($original_size !== $new_size);
        }
    }
    
    foreach ($trade_license as &$file) {
        if (isset($file['base64_data'])) {
            $original_size = strlen($file['base64_data']);
            $file['base64_data'] = compressBase64($file['base64_data']);
            $new_size = strlen($file['base64_data']);
            $file['compressed'] = ($original_size !== $new_size);
        }
    }
    
    // STEP 8: PREPARE JSON DATA FOR DATABASE
    $shareholders_json = !empty($shareholders) ? json_encode($shareholders, JSON_UNESCAPED_UNICODE) : null;
    $ubos_json = !empty($ubos) ? json_encode($ubos, JSON_UNESCAPED_UNICODE) : null;
    $eid_passports_json = !empty($eid_passports) ? json_encode($eid_passports, JSON_UNESCAPED_UNICODE) : null;
    $trade_license_json = !empty($trade_license) ? json_encode($trade_license, JSON_UNESCAPED_UNICODE) : null;
    
    // Log JSON data for debugging
    error_log("Shareholders JSON length: " . strlen($shareholders_json ?? ''));
    error_log("UBOs JSON length: " . strlen($ubos_json ?? ''));
    error_log("EID Passports JSON length: " . strlen($eid_passports_json ?? ''));
    error_log("Trade License JSON length: " . strlen($trade_license_json ?? ''));
    
    // STEP 9: SAVE TO DATABASE
    $stmt = $pdo->prepare("SELECT id FROM entity_step1 WHERE entity_id = ?");
    $stmt->execute([$entity_id]);
    $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_record) {
        // Update existing record
        $sql = "UPDATE entity_step1 SET 
                business_registration_status = ?,
                company_owner_name = ?,
                mainland_company_type = ?,
                license_number = ?,
                license_issue_date = ?,
                license_expiry_date = ?,
                main_activity = ?,
                emirate = ?,
                address = ?,
                shareholders = ?,
                ubos = ?,
                management_control = ?,
                total_turnover = ?,
                eid_passports = ?,
                trade_license = ?,
                updated_at = NOW()
                WHERE entity_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $business_registration_status,
            $company_owner_name,
            $mainland_company_type,
            $license_number,
            $license_issue_date,
            $license_expiry_date,
            $main_activity,
            $emirate,
            $address,
            $shareholders_json,
            $ubos_json,
            $management_control,
            $total_turnover,
            $eid_passports_json,
            $trade_license_json,
            $entity_id
        ]);
        
        $step1_id = $existing_record['id'];
        error_log("Updated existing step1 record ID: " . $step1_id);
    } else {
        // Insert new record
        $sql = "INSERT INTO entity_step1 (
                entity_id,
                business_registration_status,
                company_owner_name,
                mainland_company_type,
                license_number,
                license_issue_date,
                license_expiry_date,
                main_activity,
                emirate,
                address,
                shareholders,
                ubos,
                management_control,
                total_turnover,
                eid_passports,
                trade_license,
                created_at,
                updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $entity_id,
            $business_registration_status,
            $company_owner_name,
            $mainland_company_type,
            $license_number,
            $license_issue_date,
            $license_expiry_date,
            $main_activity,
            $emirate,
            $address,
            $shareholders_json,
            $ubos_json,
            $management_control,
            $total_turnover,
            $eid_passports_json,
            $trade_license_json
        ]);
        
        if ($result) {
            $step1_id = $pdo->lastInsertId();
            error_log("Inserted new step1 record ID: " . $step1_id);
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to insert step1 record: " . ($errorInfo[2] ?? 'Unknown error'));
        }
    }
    
    // Update the entities table
    $stmt = $pdo->prepare("UPDATE entities SET current_step = 2, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$entity_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Store data in session
    $_SESSION['form']['step1']['step1_id'] = $step1_id;
    $_SESSION['form']['step1']['completed'] = true;
    $_SESSION['form']['step1']['saved_at'] = date('Y-m-d H:i:s');
    $_SESSION['form']['current_step'] = 2;
    
    // Store entity info
    $_SESSION['form']['current_entity'] = [
        'id' => $entity_id,
        'name' => $company_owner_name,
        'engagement_number' => $entity['engagement_number'],
        'application_type' => $application_type ?? 'new'
    ];
    
    // Clear large file data from session
    if (isset($_SESSION['form']['step1']['id_passport_files'])) {
        unset($_SESSION['form']['step1']['id_passport_files']);
    }
    if (isset($_SESSION['form']['step1']['trade_license_files'])) {
        unset($_SESSION['form']['step1']['trade_license_files']);
    }
    
    // Also save the raw POST data to session for debugging
    $_SESSION['form']['step1']['post_data_debug'] = $post_data;
    
    $success = true;
    error_log("=== KYC SAVE SUCCESS ===");
    
} catch (Exception $e) {
    error_log("=== KYC SAVE ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
            error_log("Transaction rolled back successfully");
        } catch (Exception $rollbackError) {
            error_log("Rollback failed: " . $rollbackError->getMessage());
        }
    }
    
    $errors[] = "Save failed: " . $e->getMessage();
}

// Check if request is AJAX (with XMLHttpRequest header)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($success) {
    // Store success message in session
    $_SESSION['success_message'] = 'KYC data saved successfully!';
    $_SESSION['last_saved_entity'] = [
        'id' => $entity_id,
        'name' => $company_owner_name ?? '',
        'engagement_number' => $entity['engagement_number'] ?? '',
        'step1_id' => $step1_id
    ];

    $_SESSION['form']['step1'] =$_POST ;
     header("Location: ../../../views/direct/kyc-steps/Audit-Fee.php");
        exit();
    
    
} else {
    // Handle error case
    $_SESSION['error_message'] = 'Failed to save KYC data. Please try again.';
    $_SESSION['form_errors'] = $errors;
    
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'message' => 'Failed to save KYC data. Please try again.',
            'debug' => [
                'ubos_received' => isset($post_data['ubo_name']) ? count($post_data['ubo_name']) : 0,
                'ubo_question' => $ubo_question ?? 'Not set'
            ]
        ]);
        exit();
    } else {
        // Redirect back to the form page with errors
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'kyc-form.php'));
        exit();
    }
}

// Don't save raw POST to session as it overwrites structured data
// Only save specific fields if needed
if (isset($post_data['ubo-question'])) {
    $_SESSION['form']['step1']['ubo-question'] = $post_data['ubo-question'];
}

// This line won't be reached due to exit() calls above