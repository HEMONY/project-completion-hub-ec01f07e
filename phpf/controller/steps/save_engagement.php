<?php
// save_engagement_clean.php
ob_start(); // Start output buffering to catch any stray output

error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn OFF display errors - they'll break JSON

session_start();

// Log for debugging
$logFile = 'engagement_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting save_engagement.php\n", FILE_APPEND);

try {
    // Check session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated. Please login again.');
    }
    
    $userId = $_SESSION['user_id'];
    file_put_contents($logFile, "User ID: $userId\n", FILE_APPEND);
    
    // Get ALL input
    $rawInput = file_get_contents('php://input');
    file_put_contents($logFile, "Raw input received: " . $rawInput . "\n", FILE_APPEND);
    
    // Check if it's JSON
    $inputData = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try as form data
        parse_str($rawInput, $inputData);
        
        // If it has 'data' parameter that's JSON
        if (isset($inputData['data']) && !empty($inputData['data'])) {
            $inputData = json_decode($inputData['data'], true);
        }
    }
    
    file_put_contents($logFile, "Parsed data: " . print_r($inputData, true) . "\n", FILE_APPEND);
    
    if (empty($inputData)) {
        throw new Exception('No valid data received. Raw input: ' . substr($rawInput, 0, 100));
    }
    
    // Database connection - with error handling
    $dbConfigPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbConfigPath)) {
        throw new Exception('Database config not found at: ' . $dbConfigPath);
    }
    
    require_once $dbConfigPath;
    
    // Test database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection failed - $pdo not set');
    }
    
    // Get entity ID
    $entityId = $_SESSION['current_entity_id'] ?? null;
    
    if (!$entityId) {
        // Get latest entity
        $stmt = $pdo->prepare("SELECT id FROM entities WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $entity = $stmt->fetch();
        
        if ($entity) {
            $entityId = $entity['id'];
            $_SESSION['current_entity_id'] = $entityId;
        } else {
            throw new Exception('No entity found. Complete previous steps first.');
        }
    }
    
    // Get engagement number from session or input
    $engagementNumber = null;
    
    // Priority 1: Check if engagement number is in session
    if (isset($_SESSION['form']['step5']['engagement_number'])) {
        $engagementNumber = $_SESSION['form']['step5']['engagement_number'];
        file_put_contents($logFile, "Engagement number from session: $engagementNumber\n", FILE_APPEND);
    }
    
    // Priority 2: Check if engagement number is in input data
    if (empty($engagementNumber) && isset($inputData['engagement_number'])) {
        $engagementNumber = $inputData['engagement_number'];
        // Also save to session for future use
        $_SESSION['form']['step5']['engagement_number'] = $engagementNumber;
        file_put_contents($logFile, "Engagement number from input: $engagementNumber\n", FILE_APPEND);
    }
    
    // Priority 3: Check if entity already has an engagement number
    if (empty($engagementNumber)) {
        $stmt = $pdo->prepare("SELECT engagement_number FROM entities WHERE id = ?");
        $stmt->execute([$entityId]);
        $entityRow = $stmt->fetch();
        
        if ($entityRow && !empty($entityRow['engagement_number'])) {
            $engagementNumber = $entityRow['engagement_number'];
            file_put_contents($logFile, "Engagement number from entity: $engagementNumber\n", FILE_APPEND);
        }
    }
    
    // If still no engagement number, check if one was generated earlier in step5
    if (empty($engagementNumber)) {
        $stmt = $pdo->prepare("SELECT engagement_number FROM entity_step5 WHERE entity_id = ?");
        $stmt->execute([$entityId]);
        $step5Row = $stmt->fetch();
        
        if ($step5Row && !empty($step5Row['engagement_number'])) {
            $engagementNumber = $step5Row['engagement_number'];
            file_put_contents($logFile, "Engagement number from step5: $engagementNumber\n", FILE_APPEND);
        }
    }
    
    // Validate engagement number format (if provided)
    if (!empty($engagementNumber)) {
        // Remove any whitespace from beginning and end
        $engagementNumber = trim($engagementNumber);
        
        // Sanitize the engagement number (allow more characters for engagement numbers)
        // Engagement numbers can include: letters, numbers, spaces, dots, hyphens, underscores, forward slashes, parentheses, commas
        $engagementNumber = preg_replace('/[^\w\s\.\-\/\(\)\,\#\&\+]/u', '', $engagementNumber);
        
        // Check if engagement number is not empty after sanitization
        if (empty($engagementNumber)) {
            throw new Exception('Engagement number is invalid after sanitization. Please use a valid engagement number format.');
        }
        
        // Check for uniqueness in entities table (except current entity)
        $stmt = $pdo->prepare("SELECT id, entity_name FROM entities WHERE engagement_number = ? AND id != ?");
        $stmt->execute([$engagementNumber, $entityId]);
        $existingEntity = $stmt->fetch();
        
        if ($existingEntity) {
            throw new Exception('Engagement number "' . $engagementNumber . '" already exists for entity: ' . $existingEntity['entity_name'] . '. Please use a unique engagement number.');
        }
        
        // Check for uniqueness in entity_step5 table (except current entity)
        $stmt = $pdo->prepare("SELECT e.id, e.entity_name 
                               FROM entity_step5 es5 
                               JOIN entities e ON es5.entity_id = e.id 
                               WHERE es5.engagement_number = ? 
                               AND e.id != ?");
        $stmt->execute([$engagementNumber, $entityId]);
        $existingStep5 = $stmt->fetch();
        
        if ($existingStep5) {
            throw new Exception('Engagement number "' . $engagementNumber . '" already exists in another application: ' . $existingStep5['entity_name'] . '. Please use a unique engagement number.');
        }
    }
    
    // Prepare other data
    $termsAccepted = isset($inputData['terms_accepted']) ? (int)$inputData['terms_accepted'] : 0;
    $digitalSignatureName = $inputData['digital_signature_name'] ?? 'Unknown';
    $isComplete = isset($inputData['is_complete']) ? (int)$inputData['is_complete'] : 0;
    
    // Log the data we're about to save
    file_put_contents($logFile, "Data to save - Engagement: $engagementNumber, Terms: $termsAccepted, Signature: $digitalSignatureName\n", FILE_APPEND);
    
    // Check if step5 exists
    $stmt = $pdo->prepare("SELECT id FROM entity_step5 WHERE entity_id = ?");
    $stmt->execute([$entityId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update entity_step5
        $updateParams = [
            ':terms_accepted' => $termsAccepted,
            ':accepted_at' => $termsAccepted ? date('Y-m-d H:i:s') : null,
            ':digital_signature_name' => $digitalSignatureName,
            ':digital_signature_date' => $termsAccepted ? date('Y-m-d H:i:s') : null,
            ':entity_id' => $entityId
        ];
        
        // Build the SQL query dynamically
        $sql = "UPDATE entity_step5 SET 
                terms_accepted = :terms_accepted,
                accepted_at = :accepted_at,
                digital_signature_name = :digital_signature_name,
                digital_signature_date = :digital_signature_date,";
        
        // Add engagement number if provided
        if (!empty($engagementNumber)) {
            $sql .= " engagement_number = :engagement_number,";
            $updateParams[':engagement_number'] = $engagementNumber;
        }
        
        $sql .= " updated_at = NOW()
                WHERE entity_id = :entity_id";
        
        file_put_contents($logFile, "Update SQL: $sql\n", FILE_APPEND);
        file_put_contents($logFile, "Update params: " . print_r($updateParams, true) . "\n", FILE_APPEND);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);
        
        $affectedRows = $stmt->rowCount();
        file_put_contents($logFile, "Update affected rows: $affectedRows\n", FILE_APPEND);
    } else {
        // Insert into entity_step5
        $insertParams = [
            ':entity_id' => $entityId,
            ':terms_accepted' => $termsAccepted,
            ':accepted_at' => $termsAccepted ? date('Y-m-d H:i:s') : null,
            ':digital_signature_name' => $digitalSignatureName,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Build the SQL query dynamically
        $sql = "INSERT INTO entity_step5 (
                entity_id,";
        
        $sqlValues = "VALUES (:entity_id,";
        
        // Add engagement number if provided
        if (!empty($engagementNumber)) {
            $sql .= " engagement_number,";
            $sqlValues .= " :engagement_number,";
            $insertParams[':engagement_number'] = $engagementNumber;
        }
        
        $sql .= " terms_accepted,
                accepted_at,
                digital_signature_name,
                digital_signature_date,
                ip_address,
                user_agent,
                created_at) ";
                
        $sqlValues .= " :terms_accepted,
                :accepted_at,
                :digital_signature_name,
                NOW(),
                :ip_address,
                :user_agent,
                NOW())";
        
        $fullSql = $sql . " " . $sqlValues;
        
        file_put_contents($logFile, "Insert SQL: $fullSql\n", FILE_APPEND);
        file_put_contents($logFile, "Insert params: " . print_r($insertParams, true) . "\n", FILE_APPEND);
        
        $stmt = $pdo->prepare($fullSql);
        $stmt->execute($insertParams);
        
        $insertId = $pdo->lastInsertId();
        file_put_contents($logFile, "Inserted ID: $insertId\n", FILE_APPEND);
    }
    
    // Update main entities table with engagement number and current step
    if (!empty($engagementNumber)) {
        $stmt = $pdo->prepare("UPDATE entities SET engagement_number = ?, current_step = 5 WHERE id = ?");
        $stmt->execute([$engagementNumber, $entityId]);
        
        // Also update session with the confirmed engagement number
        $_SESSION['current_engagement_number'] = $engagementNumber;
        file_put_contents($logFile, "Updated entities table with engagement number: $engagementNumber\n", FILE_APPEND);
    } else {
        // Just update current step if no engagement number
        $stmt = $pdo->prepare("UPDATE entities SET current_step = 5 WHERE id = ?");
        $stmt->execute([$entityId]);
        file_put_contents($logFile, "Updated entities table current_step to 5\n", FILE_APPEND);
    }
    
    // Update application status if terms are accepted
    if ($termsAccepted) {
        $stmt = $pdo->prepare("UPDATE entities SET application_status = 'submitted', submitted_at = NOW() WHERE id = ?");
        $stmt->execute([$entityId]);
        file_put_contents($logFile, "Updated application_status to submitted\n", FILE_APPEND);
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Engagement saved successfully',
        'terms_accepted' => $termsAccepted,
        'entity_id' => $entityId
    ];
    
    // Add engagement number to response if available
    if (!empty($engagementNumber)) {
        $response['engagement_number'] = $engagementNumber;
    }
    
    if ($isComplete) {
        $response['redirect'] = '../../application_complete.php';
    }
    
    file_put_contents($logFile, "Success response prepared: " . json_encode($response) . "\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "ERROR Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ];
}

// Clear any output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Output ONLY JSON
echo json_encode($response, JSON_PRETTY_PRINT);

// Log final output
file_put_contents($logFile, "Final output sent: " . json_encode($response) . "\n", FILE_APPEND);
exit;
?>