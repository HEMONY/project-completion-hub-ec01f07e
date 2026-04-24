<?php
// Start output buffering early
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    ob_end_flush();
    exit();
}

try {
    // ============================================
    // ENTITY ID HANDLING
    // ============================================
    $entity_id = $_SESSION['form']['step0']['entity_id'];
    
    // Check all possible sources for entity_id
    if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
        $entity_id = intval($_GET['entity_id']);
    } else if (isset($_POST['entity_id']) && !empty($_POST['entity_id'])) {
        $entity_id = intval($_POST['entity_id']);
    } else if (isset($_SESSION['entity_id'])) {
        $entity_id = $_SESSION['entity_id'];
    } else if (isset($_SESSION['form']['step1']['entity_id'])) {
        $entity_id = $_SESSION['form']['step1']['entity_id'];
    }
    
    if (empty($entity_id)) {
        header("Location: ../../dashboard.php?error=no_entity");
        ob_end_flush();
        exit();
    }
    
    $_SESSION['entity_id'] = $entity_id;
    echo $_SESSION['entity_id'];
    // ============================================
    // DATABASE CONNECTION
    // ============================================
    $dbFile = '../../config/db.php';
    if (!file_exists($dbFile)) {
        throw new Exception("Database config file not found");
    }
    
    require_once $dbFile;
    
    if (!class_exists('Database')) {
        throw new Exception('Database class not found');
    }
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        throw new Exception('Failed to get database connection');
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ============================================
    // VERIFY ENTITY
    // ============================================
    $userId = $_SESSION['user_id'];
    $verifyStmt = $pdo->prepare("
        SELECT id, entity_name, current_step 
        FROM entities 
        WHERE id = :entity_id 
        AND user_id = :user_id
    ");
    
    $verifyStmt->execute([
        ':entity_id' => $entity_id,
        ':user_id' => $userId
    ]);
    
    $entity = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity) {
        header("Location: ../../dashboard.php?error=entity_not_found");
        ob_end_flush();
        exit();
    }
    
    // ============================================
    // GET AUDIT FEE AMOUNT
    // ============================================
    $calculated_fee = 0.00;
    
    // Try multiple sources for the fee amount
    if (isset($_POST['calculated_fee']) && !empty($_POST['calculated_fee'])) {
        $calculated_fee = floatval($_POST['calculated_fee']);
    } 
    else if (isset($_POST['audit_fee_amount']) && !empty($_POST['audit_fee_amount'])) {
        $calculated_fee = floatval($_POST['audit_fee_amount']);
    }
    else if (isset($_POST['fee_amount']) && !empty($_POST['fee_amount'])) {
        $calculated_fee = floatval($_POST['fee_amount']);
    }
    else if (isset($_SESSION['form']['step2']['calculated_fee'])) {
        $calculated_fee = floatval($_SESSION['form']['step2']['calculated_fee']);
    }
    else if (isset($_SESSION['audit_fee_amount'])) {
        $calculated_fee = floatval($_SESSION['audit_fee_amount']);
    }
    
    // If still 0, check for turnover-based calculation
    if ($calculated_fee == 0) {
        if (isset($_POST['turnover']) && !empty($_POST['turnover'])) {
            $turnover = floatval($_POST['turnover']);
            $calculated_fee = $turnover * 0.002;
        }
        else if (isset($_SESSION['form']['step2']['turnover'])) {
            $turnover = floatval($_SESSION['form']['step2']['turnover']);
            $calculated_fee = $turnover * 0.002;
        }
    }
    
    // Set a minimum fee if still 0
    if ($calculated_fee == 0) {
        $calculated_fee = 1000.00;
    }
    
    // ============================================
    // PREPARE DATA FOR DATABASE
    // ============================================
    $audit_fee_acknowledged = 1;
    $audit_fee_amount = $calculated_fee;
    $payment_terms = "Payable upon completion";
    $acknowledged_at = date('Y-m-d H:i:s');
    
    // ============================================
    // DATABASE TRANSACTION
    // ============================================
    try {
        $pdo->beginTransaction();
        
        // Check if step2 record already exists
        $checkStmt = $pdo->prepare("SELECT id FROM entity_step2 WHERE entity_id = :entity_id");
        $checkStmt->execute([':entity_id' => $entity_id]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // UPDATE EXISTING RECORD
            $updateStmt = $pdo->prepare("
                UPDATE entity_step2 
                SET audit_fee_acknowledged = :acknowledged, 
                    audit_fee_amount = :amount, 
                    payment_terms = :terms, 
                    acknowledged_at = :acknowledged_at, 
                    updated_at = NOW() 
                WHERE entity_id = :entity_id
            ");
            
            $updateStmt->execute([
                ':acknowledged' => $audit_fee_acknowledged,
                ':amount' => $audit_fee_amount,
                ':terms' => $payment_terms,
                ':acknowledged_at' => $acknowledged_at,
                ':entity_id' => $entity_id
            ]);
            
        } else {
            // INSERT NEW RECORD
            $insertStmt = $pdo->prepare("
                INSERT INTO entity_step2 
                (entity_id, audit_fee_acknowledged, audit_fee_amount, payment_terms, acknowledged_at, created_at, updated_at) 
                VALUES (:entity_id, :acknowledged, :amount, :terms, :acknowledged_at, NOW(), NOW())
            ");
            
            $insertStmt->execute([
                ':entity_id' => $entity_id,
                ':acknowledged' => $audit_fee_acknowledged,
                ':amount' => $audit_fee_amount,
                ':terms' => $payment_terms,
                ':acknowledged_at' => $acknowledged_at
            ]);
        }
        
        // UPDATE MAIN ENTITY RECORD TO STEP 3
        $updateEntityStmt = $pdo->prepare("
            UPDATE entities 
            SET current_step = 3, 
                updated_at = NOW() 
            WHERE id = :entity_id
        ");
        
        $updateEntityStmt->execute([':entity_id' => $entity_id]);
        
        // ADD AUDIT LOG
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $auditStmt = $pdo->prepare("
            INSERT INTO user_audit_logs 
            (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (:user_id, 'step2_completed', :description, :ip_address, :user_agent, NOW())
        ");
        
        $description = "Completed audit fee step. Amount: AED " . number_format($audit_fee_amount, 2) . " for entity ID: " . $entity_id;
        
        $auditStmt->execute([
            ':user_id' => $userId,
            ':description' => $description,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
        // COMMIT TRANSACTION
        $pdo->commit();
        
        // ============================================
        // UPDATE SESSION
        // ============================================
        $_SESSION['form']['current_step'] = 3;
        $_SESSION['step2_completed'] = true;
        $_SESSION['audit_fee_amount'] = $audit_fee_amount;
        
        // Store POST data in session for step2
        if (isset($_POST)) {
            $_SESSION['form']['step2'] = $_POST;
        }
        
        // ============================================
        // CLEAN OUTPUT BUFFER AND REDIRECT
        // ============================================
        // Clear all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Redirect to next page
        header("Location: ../../views/direct/kyc-steps/FINANCIAL-YEAR.php?entity_id=" . $entity_id . "&success=1");
        exit();
        
    } catch (PDOException $e) {
        // ROLLBACK ON ERROR
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Clear output buffer before redirect
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        header("Location: audit_fee.php?error=database&entity_id=" . $entity_id);
        exit();
    }
    
} catch (Exception $e) {
    // Clear output buffer before showing error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .error-container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ff6b6b; background-color: #ffe6e6; border-radius: 5px; }
            .error-message { color: #d32f2f; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h2>Error Processing Step 2</h2>
            <p class='error-message'>An error occurred: " . htmlspecialchars($e->getMessage()) . "</p>
            <a href='audit_fee.php?entity_id=" . ($entity_id ?? '') . "' class='back-link'>Go Back</a>
        </div>
    </body>
    </html>";
    exit();
}