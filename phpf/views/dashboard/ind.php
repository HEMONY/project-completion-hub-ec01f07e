<?php
session_start();
require_once '../../config/db.php';
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get user role for permissions
$user_role = $_SESSION['role'] ?? 'client';

// Get entity ID from URL
$entity_id = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : 0;

if ($entity_id === 0) {
    die("Invalid entity ID");
}

// Fetch entity information and check screening status
$entity_query = "SELECT e.*, u.full_name, u.email 
                 FROM entities e 
                 JOIN users u ON e.user_id = u.id 
                 WHERE e.id = ?";
$stmt = $pdo->prepare($entity_query);
$stmt->execute([$entity_id]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entity) {
    die("Entity not found");
}

// Check if user has permission to access this entity
if ($user_role === 'client' && $entity['user_id'] != $_SESSION['user_id']) {
    die("You don't have permission to access this entity");
}

// Check if screening is completed
if (empty($entity['screening_completed']) || $entity['screening_completed'] != 1) {
    die("Screening must be completed before proceeding to ICID. Please complete screening first.");
}

// Check if IND is already completed
$ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;

// Get existing IND confirmation if it exists
$existingConfirmation = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM independence_confirmations WHERE entity_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$entity_id]);
    $existingConfirmation = $stmt->fetch();
} catch (PDOException $e) {
    // Continue without existing confirmation
}

// Handle form submission
$confirmationType = '';
$confirmationMessage = '';
$signatureHtml = '';
$statusMessageHtml = '';
$disableButtons = $ind_completed || !empty($existingConfirmation);
$confirmationId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    if ($action === 'confirm_independence') {
        $confirmationType = 'confirmed';
        $confirmationMessage = "Independence has been confirmed. No conflicts of interest identified.";
        $messageClass = "confirmed-message";
        
        // Save to database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO independence_confirmations 
                (entity_id, engagement_number, confirmation_type, confirmation_status, 
                 confirmed_by, confirmation_text, signature_name, signature_date,
                 client_audit, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $entity_id,
                $entity['engagement_number'] ?? 'N/A',
                'confirmed',
                'confirmed',
                $_SESSION['user_id'],
                "Independence confirmed. No conflicts of interest identified.",
                $_SESSION['full_name'] ?? 'Unknown',
                $entity['entity_name'] ?? 'Unknown',
                $ipAddress,
                $userAgent
            ]);
            
            $confirmationId = $pdo->lastInsertId();
            
            // Update entities table to mark IND as completed
            $update_entity_query = "UPDATE entities SET ind_completed = 1, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_entity_query);
            $stmt->execute([$entity_id]);
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }
        
        $signatureHtml = "
            <div class=\"signature-name\">Confirmed Independence by Engagement Lead: " . ($_SESSION['full_name'] ?? 'Unknown') . "</div>
            <div class=\"signature-date\">on " . date('F j, Y') . "</div>
        ";
        $disableButtons = true;
        
    } elseif ($action === 'declare_conflict') {
        $confirmationType = 'conflict';
        $confirmationMessage = "A conflict of interest has been declared. This matter requires immediate attention and escalation.";
        $messageClass = "conflict-message";
        
        // Save to database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO independence_confirmations 
                (entity_id, engagement_number, confirmation_type, confirmation_status, 
                 confirmed_by, confirmation_text, signature_name, signature_date,
                 conflict_details, status_message, client_audit, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
            ");
            
            $conflictDetails = "Conflict of interest declared by user.";
            $statusMessage = "Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform's acceptable threshold.";
            
            $stmt->execute([
                $entity_id,
                $entity['engagement_number'] ?? 'N/A',
                'conflict',
                'conflict_declared',
                $_SESSION['user_id'],
                "Conflict of interest declared.",
                $_SESSION['full_name'] ?? 'Unknown',
                $conflictDetails,
                $statusMessage,
                $entity['entity_name'] ?? 'Unknown',
                $ipAddress,
                $userAgent
            ]);
            
            $confirmationId = $pdo->lastInsertId();
            
            // Update entities table - mark IND as completed but with conflict
            $update_entity_query = "UPDATE entities SET ind_completed = 1, application_status = 'rejected', updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_entity_query);
            $stmt->execute([$entity_id]);
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }
        
        $signatureHtml = "
            <div class=\"signature-name\">Independence Issue Declared by Engagement Lead: " . ($_SESSION['full_name'] ?? 'Unknown') . "</div>
            <div class=\"signature-date\">on " . date('F j, Y') . "</div>
        ";
        
        $statusMessageHtml = '
            <div class="status-message" id="statusMessage">
                <div class="status-header">
                    <i class="fas fa-ban"></i> Status: Not Eligible to Proceed
                </div>
                <div class="status-content">
                    Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform\'s acceptable threshold.
                </div>
                <button class="send-button" id="sendBtn">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        ';
        $disableButtons = true;
        
    } elseif ($action === 'send_status' && isset($_POST['confirmation_id'])) {
        $confirmationId = (int)$_POST['confirmation_id'];
        
        // Update database
        try {
            $stmt = $pdo->prepare("
                UPDATE independence_confirmations 
                SET confirmation_status = 'terminated', 
                    is_sent = 1, 
                    sent_at = NOW(), 
                    sent_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$_SESSION['user_id'], $confirmationId]);
            
            echo json_encode(['success' => true, 'message' => 'Status notification sent successfully.']);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// If there's existing confirmation, show it
if ($existingConfirmation && !$disableButtons) {
    $disableButtons = true;
    $confirmationType = $existingConfirmation['confirmation_type'];
    
    if ($existingConfirmation['confirmation_type'] === 'confirmed') {
        $confirmationMessage = $existingConfirmation['confirmation_text'] ?? "Independence has been confirmed. No conflicts of interest identified.";
        $messageClass = "confirmed-message";
        $signatureHtml = "
            <div class=\"signature-name\">Confirmed Independence by Engagement Lead: {$existingConfirmation['signature_name']}</div>
            <div class=\"signature-date\">on " . date('F j, Y', strtotime($existingConfirmation['signature_date'])) . "</div>
        ";
    } else {
        $confirmationMessage = $existingConfirmation['confirmation_text'] ?? "A conflict of interest has been declared. This matter requires immediate attention and escalation.";
        $messageClass = "conflict-message";
        $signatureHtml = "
            <div class=\"signature-name\">Independence Issue Declared by Engagement Lead: {$existingConfirmation['signature_name']}</div>
            <div class=\"signature-date\">on " . date('F j, Y', strtotime($existingConfirmation['signature_date'])) . "</div>
        ";
        
        if ($existingConfirmation['confirmation_status'] === 'conflict_declared') {
            $statusMessageHtml = '
                <div class="status-message" id="statusMessage">
                    <div class="status-header">
                        <i class="fas fa-ban"></i> Status: Not Eligible to Proceed
                    </div>
                    <div class="status-content">
                        Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform\'s acceptable threshold.
                    </div>
                    <button class="send-button" id="sendBtn">
                        <i class="fas fa-paper-plane"></i> ' . ($existingConfirmation['is_sent'] ? 'Sent' : 'Send') . '
                    </button>
                </div>
            ';
        }
    }
    
    if ($confirmationMessage) {
        $confirmationMessage = '<div class="confirmation-message ' . $messageClass . '" id="confirmationMessage">' . htmlspecialchars($confirmationMessage) . '</div>';
    }
}

// Check workflow progress
$screening_completed = !empty($entity['screening_completed']) && $entity['screening_completed'] == 1;
$ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;
$cdd_completed = !empty($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Independence and Conflict of Interest Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #404040;
            --secondary-color: #666666;
            --accent-color: #2a5bd7;
            --light-gray: #f8f9fa;
            --border-color: #eaeaea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --screening-color: #6f42c1;
            --ind-color: #17a2b8;
            --cdd-color: #20c997;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 25px;
        }
        
        /* WORKFLOW PROGRESS BAR - UPDATED TO MATCH CDD DESIGN */
        .steps-container-horizontal {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            margin-bottom: 35px;
            position: relative;
        }
        
        .steps-container-horizontal::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 30px;
            right: 30px;
            height: 1px;
            background-color: var(--border-color);
            z-index: 1;
        }
        
        .step-item-horizontal {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            max-width: 220px;
        }
        
        .step-number-horizontal {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 16px;
            border: 2px solid white;
        }
        
        .step-item-horizontal.active .step-number-horizontal {
            background-color: var(--ind-color);
            color: white;
            border-color: var(--ind-color);
        }
        
        .step-item-horizontal.completed .step-number-horizontal {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .step-content-horizontal {
            text-align: center;
            padding: 0 10px;
        }
        
        .step-title-horizontal {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 6px;
            color: var(--primary-color);
        }
        
        .step-status-horizontal {
            font-size: 12px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .step-item-horizontal.completed .step-status-horizontal {
            color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .steps-container-horizontal {
                flex-direction: column;
                align-items: flex-start;
                gap: 30px;
            }
            
            .steps-container-horizontal::before {
                display: none;
            }
            
            .step-item-horizontal {
                flex-direction: row;
                max-width: 100%;
                width: 100%;
            }
            
            .step-number-horizontal {
                margin-right: 20px;
                margin-bottom: 0;
            }
            
            .step-content-horizontal {
                text-align: left;
                padding: 0;
            }
        }
        
        /* EVERYTHING BELOW THIS LINE REMAINS EXACTLY THE SAME AS BEFORE */
        .entity-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
        }
        
        .field-group {
            margin-bottom: 0;
        }
        
        .field-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }
        
        .field-value {
            padding: 12px 16px;
            background-color: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            min-height: 48px;
            display: flex;
            align-items: center;
            font-weight: 400;
        }
        
        .content {
            margin-bottom: 35px;
        }
        
        .intro {
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.7;
            text-align: justify;
        }
        
        .declaration-section {
            margin-bottom: 35px;
        }
        
        .declaration-item {
            margin-bottom: 25px;
            padding-left: 5px;
        }
        
        .declaration-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .declaration-text {
            font-size: 14.5px;
            color: #555;
            line-height: 1.7;
        }
        
        .confirmation-section {
            margin-top: 40px;
            margin-bottom: 40px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .confirmation-statement {
            font-size: 15px;
            margin-bottom: 25px;
            font-style: italic;
            color: #2c3e50;
        }
        
        .signature-section {
            margin-top: 40px;
        }
        
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            width: 300px;
            padding-top: 10px;
        }
        
        .signature-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .signature-date {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .confirmation-message {
            padding: 20px;
            border-radius: 6px;
            margin-top: 25px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            border-left: 4px solid;
        }
        
        .confirmed-message {
            background-color: rgba(46, 204, 113, 0.1);
            border-color: #2ecc71;
            color: #27ae60;
        }
        
        .conflict-message {
            background-color: rgba(231, 76, 60, 0.1);
            border-color: #e74c3c;
            color: #c0392b;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 35px;
            margin-bottom: 35px;
            flex-wrap: wrap;
        }
        
        .action-button {
            padding: 14px 35px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 250px;
        }
        
        .action-button i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .confirm-button {
            background-color: var(--ind-color);
            color: white;
        }
        
        .confirm-button:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.25);
        }
        
        .conflict-button {
            background-color: #e74c3c;
            color: white;
        }
        
        .conflict-button:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25);
        }
        
        .action-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .buttons-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 35px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 13px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            min-width: 130px;
            text-decoration: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-back {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        
        .btn-back:hover {
            background-color: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.1);
        }
        
        .btn-print {
            background-color: #64748b;
            color: white;
            border: 1px solid #64748b;
        }
        
        .btn-print:hover {
            background-color: #475569;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25);
        }
        
        .btn-next {
            background-color: var(--cdd-color);
            color: white;
            border: 1px solid var(--cdd-color);
        }
        
        .btn-next:hover {
            background-color: #1ba784;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 201, 151, 0.25);
        }
        
        .status-message {
            margin-top: 25px;
            padding: 20px;
            background-color: #fff5f5;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            color: #c0392b;
        }
        
        .status-header {
            font-weight: 600;
            font-size: 17px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .status-header i {
            margin-right: 10px;
        }
        
        .status-content {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .send-button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-button i {
            margin-right: 8px;
        }
        
        .send-button:hover {
            background-color: #c0392b;
        }
        
        .send-button:disabled {
            background-color: #7f8c8d;
            cursor: not-allowed;
        }
        
        .next-step-info {
            background-color: #e8f4ff;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .next-step-info h4 {
            margin-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .next-step-info h4 i {
            margin-right: 8px;
        }
        
        @media print {
            .action-buttons,
            .buttons-container,
            .status-message,
            .send-button,
            .next-step-info {
                display: none;
            }
            
            .container {
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            
            body {
                background-color: white;
                padding: 0;
            }
            
            .confirmation-message {
                display: block !important;
                border: 1px solid #333;
                background-color: transparent !important;
                color: #333 !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .entity-info {
                grid-template-columns: 1fr;
                text-align: left;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-button {
                width: 100%;
                max-width: 100%;
            }
            
            .buttons-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Independence and Conflict of Interest Confirmation</h1>
            <div class="page-subtitle">Step 2: Confirm independence before proceeding to CDD</div>
        </div>
        
        <!-- WORKFLOW PROGRESS BAR - UPDATED TO MATCH CDD DESIGN -->
        <div class="steps-container-horizontal">
            <div class="step-item-horizontal <?php echo $screening_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">1</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Sanctions Screening Report</div>
                    <div class="step-status-horizontal"><?php echo $screening_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $ind_completed ? 'completed' : 'active'; ?>">
                <div class="step-number-horizontal">2</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Independence and Conflict of Interest Confirmation</div>
                    <div class="step-status-horizontal"><?php echo $ind_completed ? 'COMPLETED' : 'IN PROGRESS'; ?></div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $cdd_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">3</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Audit Client Verification</div>
                    <div class="step-status-horizontal"><?php echo $cdd_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
        </div>
        
        <div class="entity-info">
           
            <div class="field-group">
                <div class="field-label">Audit Client:</div>
                <div class="field-value"><?php echo htmlspecialchars($entity['entity_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="field-group">
                <div class="field-label">Engagement Number:</div>
                <div class="field-value"><?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="field-group">
                <div class="field-label">Workflow Status:</div>
                <div class="field-value">
                    <?php if ($ind_completed): ?>
                        <span style="color: #27ae60; font-weight: 600;">ICID Completed</span>
                    <?php elseif ($screening_completed): ?>
                        <span style="color: #f59e0b; font-weight: 600;">ICID Pending</span>
                    <?php else: ?>
                        <span style="color: #ef4444; font-weight: 600;">Screening Required</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="intro">
                We confirm, in our capacity as Licensed Auditors, that we have complied
                with the fundamental principles of integrity, objectivity,
                professional competence and due care, confidentiality, and professional
                behavior, as set out in the IESBA International Code of Ethics for
                Professional Accountants, and with the professional conduct
                requirements issued by the Ministry of Economy of the United Arab
                Emirates.
            </div>
            
            <div class="declaration-section">
                <p style="margin-bottom: 20px; font-weight: 500; color: #2c3e50;">Accordingly, we declare the following:</p>
                
                <div class="declaration-item">
                    <div class="declaration-title">1. Financial Interests</div>
                    <div class="declaration-text">
                        Neither I nor any member of my immediate family holds, directly or
                        indirectly, any financial interest, equity instrument, or other
                        securities in the client or any of its listed or unlisted
                        subsidiaries. No purchases or sales of such securities have been
                        made.
                    </div>
                </div>
                
                <div class="declaration-item">
                    <div class="declaration-title">2. Business, Personal, and Family Relationships</div>
                    <div class="declaration-text">
                        I have no close personal, business, or family relationships with any
                        member of the client's executive management or those charged with
                        governance that could give rise to a self-interest, familiarity,
                        or intimidation threat.
                    </div>
                </div>
                
                <div class="declaration-item">
                    <div class="declaration-title">3. Loans and Credit Arrangements</div>
                    <div class="declaration-text">
                        I have not obtained any loans, guarantees, credit facilities, or
                        other financial benefits from the client, either prior to or during
                        my involvement in the audit engagement, and no such financial
                        relationships exist.
                    </div>
                </div>
                
                <div class="declaration-item">
                    <div class="declaration-title">4. Other Threats to Independence</div>
                    <div class="declaration-text">
                        I am not aware of any circumstances, relationships, or interests
                        that would create a self-interest, self-review, advocacy,
                        familiarity, or intimidation threat to my independence or
                        objectivity, as defined under the IESBA Code.
                    </div>
                </div>
                
                <div class="declaration-item">
                    <div class="declaration-title">5. Non-Assurance Services</div>
                    <div class="declaration-text">
                        Any accounting or related services provided to the client, including
                        assistance in the preparation or adjustment of financial statements,
                        have been performed strictly in accordance with applicable ethical
                        requirements, without assuming any management responsibility,
                        decision-making authority, or governance role, and remain
                        acceptable within the independence framework.
                    </div>
                </div>
                
                <div class="declaration-item">
                    <div class="declaration-title">6. Fees and Independence</div>
                    <div class="declaration-text">
                        The agreed professional fees have been determined in a manner that does not create any self-interest, intimidation, or other threats to independence. The fees are not contingent upon the outcome of the engagement and do not compromise the auditor's objectivity or professional judgment.
                        By approving the fees, the client acknowledges that such fees do not impair the auditor's independence in accordance with the IESBA Code of Ethics.
                    </div>
                </div>
            </div>
            
            <form method="POST" id="confirmationForm">
                <input type="hidden" name="entity_id" value="<?php echo $entity_id; ?>">
                <input type="hidden" name="engagement_number" value="<?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?>">
                
                <div class="action-buttons">
                    <button class="action-button confirm-button" id="confirmBtn" type="button"
                        <?php echo $disableButtons ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle"></i> 
                        <?php echo ($confirmationType === 'confirmed' && $disableButtons) ? 'Independence Confirmed' : 'Confirm Independence'; ?>
                    </button>
                    <button class="action-button conflict-button" id="conflictBtn" type="button"
                        <?php echo $disableButtons ? 'disabled' : ''; ?>>
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php echo ($confirmationType === 'conflict' && $disableButtons) ? 'Conflict Declared' : 'Declare Conflict of Interest'; ?>
                    </button>
                </div>
                
                <div class="confirmation-section">
                    <?php 
                    if ($confirmationMessage) {
                        echo $confirmationMessage;
                    }
                    echo $statusMessageHtml; 
                    ?>
                    
                    <div class="signature-section" id="signatureSection">
                        <?php echo $signatureHtml; ?>
                    </div>
                </div>
            </form>
            
            <?php if ($ind_completed && $confirmationType === 'confirmed'): ?>
            <div class="next-step-info">
                <h4><i class="fas fa-arrow-right"></i> Next Step Available</h4>
                <p>ICID completed successfully! You can now proceed to the CDD (Customer Due Diligence) step.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="buttons-container">
            <a href="screening.php?entity_id=<?php echo $entity_id; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Screening
            </a>
            
            <?php if ($ind_completed): ?>
            <a href="CDD.php?entity_id=<?php echo $entity_id; ?>" class="btn btn-next" id="next-btn">
                <i class="fas fa-arrow-right"></i> Proceed to CDD
            </a>
            <?php else: ?>
            <button class="btn btn-next" disabled>
                <i class="fas fa-lock"></i> Complete ICID First
            </button>
            <?php endif; ?>
            
            
            
            <a href="entities_dashboard.php" class="btn btn-back">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // DOM elements
        const confirmBtn = document.getElementById('confirmBtn');
        const conflictBtn = document.getElementById('conflictBtn');
        const confirmationForm = document.getElementById('confirmationForm');
        const signatureSection = document.getElementById('signatureSection');
        const statusMessage = document.getElementById('statusMessage');
        const sendBtn = document.getElementById('sendBtn');
        const progressFill = document.getElementById('progressFill');
        
        // State tracking
        let confirmationMade = <?php echo $disableButtons ? 'true' : 'false'; ?>;
        let confirmationType = '<?php echo $confirmationType; ?>';
        
        // Function to disable buttons
        function disableButtons() {
            confirmBtn.disabled = true;
            conflictBtn.disabled = true;
            confirmationMade = true;
        }
        
        // Confirm Independence button event
        confirmBtn.addEventListener('click', function() {
            if (confirmationMade) return;
            
            const userConfirmation = confirm("Are you sure you want to confirm independence? This action will be recorded and will complete the ICID step.");
            if (!userConfirmation) return;
            
            // Create hidden input for action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'confirm_independence';
            confirmationForm.appendChild(actionInput);
            
            // Show loading state
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            conflictBtn.disabled = true;
            
            // Submit form
            confirmationForm.submit();
        });
        
        // Declare Conflict button event
        conflictBtn.addEventListener('click', function() {
            if (confirmationMade) return;
            
            const userConfirmation = confirm("WARNING: You are about to declare a conflict of interest. This action will be recorded and will terminate the workflow. Are you sure you want to proceed?");
            if (!userConfirmation) return;
            
            // Create hidden input for action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'declare_conflict';
            confirmationForm.appendChild(actionInput);
            
            // Show loading state
            conflictBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            conflictBtn.disabled = true;
            
            // Submit form
            confirmationForm.submit();
        });
        
        // Send button event (for AJAX request)
        if (sendBtn) {
            sendBtn.addEventListener('click', function() {
                const userConfirmation = confirm("Are you sure you want to send this status notification? This action cannot be undone.");
                if (!userConfirmation) return;
                
                // Disable button
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                // Create form data
                const formData = new FormData();
                formData.append('action', 'send_status');
                formData.append('confirmation_id', '<?php echo $confirmationId ?? ($existingConfirmation['id'] ?? 0); ?>');
                formData.append('entity_id', '<?php echo $entity_id; ?>');
                
                // Send AJAX request
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        sendBtn.innerHTML = '<i class="fas fa-check"></i> Sent';
                        sendBtn.style.backgroundColor = '#7f8c8d';
                        
                        // Show success message
                        const sentMessage = document.createElement('div');
                        sentMessage.className = "confirmation-message";
                        sentMessage.style.backgroundColor = '#f8f9fa';
                        sentMessage.style.borderColor = '#7f8c8d';
                        sentMessage.style.color = '#2c3e50';
                        sentMessage.style.marginTop = '15px';
                        sentMessage.textContent = "Status notification sent. Client onboarding terminated.";
                        sentMessage.style.display = 'block';
                        
                        statusMessage.parentNode.insertBefore(sentMessage, statusMessage.nextSibling);
                        
                        alert("Status notification sent successfully. The client onboarding process has been terminated due to conflict of interest.");
                    } else {
                        alert("Error: " + data.message);
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("An error occurred while sending the notification.");
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                });
            });
        }
        
        // Initialize form
        console.log("Independence and Conflict of Interest Confirmation form loaded.");
        
        // If buttons are already disabled, update their appearance
        if (confirmationMade) {
            if (confirmationType === 'confirmed') {
                confirmBtn.style.backgroundColor = '#27ae60';
            } else if (confirmationType === 'conflict') {
                conflictBtn.style.backgroundColor = '#c0392b';
            }
        }
    </script>
</body>
</html>