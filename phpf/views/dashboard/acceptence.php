<?php
session_start();
require_once '../../config/db.php'; // Assuming you have database connection file
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);

// Database connection - assuming your database.php sets up $pdo connection
// Check if user is logged in (adjust based on your authentication system)
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$userName = $isLoggedIn ? ($_SESSION['full_name'] ?? 'Saleh Amin') : 'Saleh Amin';

// Get entity ID and engagement number from URL or session
$entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : (isset($_SESSION['current_entity_id']) ? $_SESSION['current_entity_id'] : 0);
$engagementNumber = isset($_GET['engagement_number']) ? $_GET['engagement_number'] : (isset($_SESSION['current_engagement_number']) ? $_SESSION['current_engagement_number'] : '700-345610-2021 Approval');

// Default values
$clientName = 'Union Mall';
$financialYear = 'From 01/01/2021 To 31/12/2021';
$commencementDate = '14/12/2025';
$riskLevel = 'LOW RISK';
$auditorName = $userName;

// Get data from database if entity ID is provided
if ($entityId > 0) {
    try {
        // Get entity information
        $stmt = $pdo->prepare("SELECT entity_name, engagement_number FROM entities WHERE id = ?");
        $stmt->execute([$entityId]);
        if ($row = $stmt->fetch()) {
            $clientName = $row['entity_name'];
            if ($row['engagement_number']) {
                $engagementNumber = $row['engagement_number'];
            }
        }
        
        // Get financial year from entity_step3
        $stmt = $pdo->prepare("SELECT current_fy_start_date, current_fy_end_date FROM entity_step3 WHERE entity_id = ?");
        $stmt->execute([$entityId]);
        if ($row = $stmt->fetch()) {
            $startDate = date('d/m/Y', strtotime($row['current_fy_start_date']));
            $endDate = date('d/m/Y', strtotime($row['current_fy_end_date']));
            $financialYear = "From $startDate To $endDate";
        }
        
        // Get CDD verification to determine risk level
        $stmt = $pdo->prepare("SELECT eligibility_status, economic_sector FROM cdd_verifications WHERE entity_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$entityId]);
        if ($row = $stmt->fetch()) {
            if ($row['eligibility_status'] === 'eligible') {
                $riskLevel = 'LOW RISK';
            } elseif ($row['eligibility_status'] === 'not_eligible') {
                $riskLevel = 'HIGH RISK';
            } else {
                $riskLevel = 'MEDIUM RISK';
            }
        }
        
        // Check if there's an independence confirmation
        $stmt = $pdo->prepare("SELECT confirmation_type, confirmation_status FROM independence_confirmations WHERE entity_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$entityId]);
        if ($row = $stmt->fetch()) {
            if ($row['confirmation_type'] === 'conflict' || $row['confirmation_status'] === 'conflict_declared') {
                $riskLevel = 'HIGH RISK';
            }
        }
        
    } catch (PDOException $e) {
        // Log error but continue with default values
        error_log("Database error: " . $e->getMessage());
    }
}

// Handle form submission to save memorandum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_memorandum') {
    try {
        // Check if memorandum already exists
        $stmt = $pdo->prepare("SELECT id FROM audit_acceptance_memorandum WHERE entity_id = ?");
        $stmt->execute([$entityId]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            // Update existing memorandum
            $stmt = $pdo->prepare("
                UPDATE audit_acceptance_memorandum 
                SET client_name = ?, engagement_number = ?, financial_year = ?, 
                    commencement_date = ?, risk_assessment = ?, auditor_name = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $clientName, $engagementNumber, $financialYear,
                $commencementDate, $riskLevel, $auditorName,
                $existingId
            ]);
        } else {
            // Insert new memorandum
            $stmt = $pdo->prepare("
                INSERT INTO audit_acceptance_memorandum 
                (entity_id, client_name, engagement_number, financial_year, 
                 commencement_date, risk_assessment, auditor_name, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $entityId ?: null,
                $clientName,
                $engagementNumber,
                $financialYear,
                $commencementDate,
                $riskLevel,
                $auditorName,
                $userId
            ]);
            
            $memorandumId = $pdo->lastInsertId();
        }
        
        // Success message (could be displayed as alert or notification)
        $saveSuccess = true;
        
    } catch (PDOException $e) {
        error_log("Database error saving memorandum: " . $e->getMessage());
        $saveError = true;
    }
}

// Get current date for signature
$currentDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Client Acceptance Memorandum</title>
    <!-- Link to Poppins font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px 25px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2c3e50;
        }
        
        h1 {
            font-size: 26px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .client-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            padding: 0;
        }
        
        .info-group {
            margin-bottom: 20px;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 15px;
            display: block;
        }
        
        .info-value {
            color: #555;
            font-size: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .divider {
            height: 2px;
            background: #2c3e50;
            margin: 40px 0;
        }
        
        .content {
            margin-bottom: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            padding: 0;
        }
        
        .section-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .section-text {
            font-size: 15px;
            color: #555;
            margin-bottom: 20px;
            text-align: justify;
            padding-left: 5px;
        }
        
        .list {
            margin-left: 25px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .list-item {
            margin-bottom: 12px;
            font-size: 15px;
            color: #555;
            position: relative;
            padding-left: 10px;
        }
        
        .list-item:before {
            content: "•";
            color: #3498db;
            font-weight: bold;
            font-size: 18px;
            position: absolute;
            left: -15px;
            top: 0;
        }
        
        .risk-assessment {
            margin-top: 40px;
            padding: 0;
            position: relative;
        }
        
        .risk-title {
            font-weight: 600;
            color: #27ae60;
            margin-bottom: 20px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #27ae60;
            display: flex;
            align-items: center;
        }
        
        .risk-title i {
            margin-right: 10px;
            color: #27ae60;
        }
        
        .risk-tag {
            position: absolute;
            top: 0;
            right: 0;
            color: #27ae60;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
        }
        
        .risk-text {
            font-size: 15px;
            color: #555;
            margin-top: 15px;
            font-weight: 500;
        }
        
        .signature-section {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #2c3e50;
        }
        
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            width: 300px;
            padding-top: 10px;
            margin-bottom: 10px;
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
        
        .print-button {
            display: block;
            margin: 20px auto 10px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 14px 35px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .print-button i {
            margin-right: 10px;
        }
        
        .print-button:hover {
            background-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .save-button {
            display: block;
            margin: 10px auto 30px;
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 14px 35px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .save-button i {
            margin-right: 10px;
        }
        
        .save-button:hover {
            background-color: #219653;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        @media print {
            .print-button,
            .save-button {
                display: none;
            }
            
            body {
                padding: 0;
                max-width: 100%;
            }
            
            .header {
                border-bottom: 2px solid #333;
            }
            
            .client-info {
                border: none;
            }
            
            .section {
                border: none;
                page-break-inside: avoid;
            }
            
            .risk-assessment {
                border: none;
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .client-info {
                grid-template-columns: 1fr;
            }
            
            .risk-tag {
                position: relative;
                top: 0;
                right: 0;
                display: block;
                margin-bottom: 15px;
            }
            
            .signature-line {
                width: 200px;
            }
        }
        
        .footer-note {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #7f8c8d;
            font-size: 13px;
            font-style: italic;
        }
        
        .verification-list {
            margin-left: 25px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .verification-item {
            margin-bottom: 15px;
            font-size: 15px;
            color: #555;
            position: relative;
            padding-left: 10px;
            line-height: 1.6;
        }
        
        .verification-item:before {
            content: counter(verification-counter) ".";
            counter-increment: verification-counter;
            color: #3498db;
            font-weight: bold;
            font-size: 15px;
            position: absolute;
            left: -25px;
            top: 0;
        }
        
        .verification-container {
            counter-reset: verification-counter;
        }
        
        .note-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 25px;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #3498db;
            font-style: italic;
            background-color: #f9f9f9;
        }
        
        .alert-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
            display: none;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid #2ecc71;
            color: #27ae60;
        }
        
        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            color: #c0392b;
        }
        
        .button-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <?php if (isset($saveSuccess)): ?>
    <div class="alert-message alert-success" id="successMessage">
        <i class="fas fa-check-circle"></i> Memorandum saved successfully!
    </div>
    <?php elseif (isset($saveError)): ?>
    <div class="alert-message alert-error" id="errorMessage">
        <i class="fas fa-exclamation-triangle"></i> Error saving memorandum. Please try again.
    </div>
    <?php endif; ?>
    
    <div class="header-container">
        <div class="header">
            <h1>Audit Client Acceptance Memorandum</h1>
        </div>
        
        <div class="client-info">
            <div class="info-group">
                <span class="info-label">Audit Client:</span>
                <div class="info-value"><?php echo htmlspecialchars($clientName); ?></div>
            </div>
            
            <div class="info-group">
                <span class="info-label">Financial Year:</span>
                <div class="info-value"><?php echo htmlspecialchars($financialYear); ?></div>
            </div>
            
            <div class="info-group">
                <span class="info-label">Engagement Number:</span>
                <div class="info-value"><?php echo htmlspecialchars($engagementNumber); ?></div>
            </div>
            
            <div class="info-group">
                <span class="info-label">Commencement Date of Business Relationship:</span>
                <div class="info-value"><?php echo htmlspecialchars($commencementDate); ?></div>
            </div>
        </div>
        
        <div class="divider"></div>
    </div>
    
    <div class="content">
        <div class="section">
            <h2 class="section-title"><i class="fas fa-file-contract"></i> Policies and Procedures</h2>
            
            <div class="section-text">
                In accordance with International Standard on Auditing (ISA) 210, the auditor shall not accept an engagement to audit financial statements unless the auditor has determined that the financial reporting framework to be applied in the preparation of the financial statements is acceptable, or is prescribed by law or regulation.
            </div>
            
            <div class="section-text">
                For recurring engagements, the auditor is required to assess whether circumstances necessitate a revision of the terms of engagement or a reminder to the client of the existing terms.
            </div>
            
            <div class="section-text">
                Pursuant to paragraph (26) of the International Standard on Quality Management (ISQM 1), the audit firm shall establish policies and procedures for the acceptance and continuance of client relationships and audit engagements, designed to provide reasonable assurance that the firm will only accept or continue engagements where it:
            </div>
            
            <div class="list">
                <div class="list-item">Is competent to perform the engagement,</div>
                <div class="list-item">Has the necessary time and technical resources, and</div>
                <div class="list-item">Has concluded that the integrity of the client is acceptable.</div>
            </div>
            
            <div class="section-text">
                The engagement lead is also required to document how any issues identified during the acceptance assessment were addressed, and to ensure that the final conclusion is appropriate and that the procedures performed are consistent with the requirements of International Standard on Auditing (ISA) 220.
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="section">
            <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Audit Client Acceptance – Verification Summary</h2>
            
            <div class="section-text">
                As part of the client acceptance procedures, the following verifications have been performed and completed, based on the information and documentation provided, and to the best of our knowledge:
            </div>
            
            <div class="verification-container">
                <div class="verification-list">
                    <div class="verification-item">Sanctions Screening: Verification that no partial or full match with applicable local or international sanctions lists has been identified.</div>
                    <div class="verification-item">Politically Exposed Person (PEP): Verification that the client is not a Politically Exposed Person (PEP) and is not closely associated with a PEP.</div>
                    <div class="verification-item">Turnover Threshold: Verification that the total turnover does not exceed AED 50 million for the financial year under review.</div>
                    <div class="verification-item">Identity, Documentation & Data Consistency: Verification that all submitted identification and licensing documents are valid, complete, and up to date, and that the information provided is consistent with the supporting documentation.</div>
                    <div class="verification-item">Client Type & Eligibility: Verification that the client does not fall within an excluded or higher-complexity entity category, including but not limited to Special Purpose Vehicles (SPVs), offshore entities or entities licensed outside the UAE (including representative offices), public or private joint stock companies, financial institutions, designated non-financial businesses or professions (DNFBPs), non-profit or public interest entities, or entities with complex group or holding structures.</div>
                    <div class="verification-item">Independence & Conflict of Interest: Verification that no independence or conflict-of-interest issues have been identified that could impair objectivity.</div>
                    <div class="verification-item">Previous Auditor Considerations: Verification that no significant concerns have been identified, to the best of available information, by the previous auditor regarding the entity's going concern or other matters indicating elevated professional risk.</div>
                </div>
            </div>
            
            <div class="note-text">
                These verifications are based on information available at the time of acceptance and do not replace ongoing risk assessment procedures throughout the engagement.
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="risk-assessment">
            <div class="risk-tag" style="color: <?php 
                echo $riskLevel === 'LOW RISK' ? '#27ae60' : 
                     ($riskLevel === 'MEDIUM RISK' ? '#f39c12' : '#e74c3c'); 
            ?>;">
                <?php echo $riskLevel; ?>
            </div>
            
            <h2 class="risk-title" style="color: <?php 
                echo $riskLevel === 'LOW RISK' ? '#27ae60' : 
                     ($riskLevel === 'MEDIUM RISK' ? '#f39c12' : '#e74c3c'); 
            ?>; border-color: <?php 
                echo $riskLevel === 'LOW RISK' ? '#27ae60' : 
                     ($riskLevel === 'MEDIUM RISK' ? '#f39c12' : '#e74c3c'); 
            ?>;">
                <i class="fas fa-check-circle"></i> Initial Client Risk Assessment
            </h2>
            
            <div class="risk-text">
                Based on the above, it has been concluded that there are no factors preventing acceptance of the client, that the level of risk is within professionally acceptable limits, and that no other circumstances exist that would warrant rejection or deferral of the engagement.
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-name">Engagement Lead: <?php echo htmlspecialchars($auditorName); ?></div>
            <div class="signature-date" id="signature-date">Date: <?php echo $currentDate; ?></div>
        </div>
        
        <div class="footer-note">
            This memorandum constitutes formal documentation of client acceptance in accordance with ISA and ISQM 1 requirements.
        </div>
    </div>
    
    <form method="POST" id="saveForm">
        <input type="hidden" name="action" value="save_memorandum">
        <input type="hidden" name="entity_id" value="<?php echo $entityId; ?>">
        
        <div class="button-container">
            <button type="submit" class="save-button">
                <i class="fas fa-save"></i> Save Memorandum
            </button>
            <button type="button" class="print-button" onclick="window.print()">
                <i class="fas fa-print"></i> Print Memorandum
            </button>
        </div>
    </form>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Audit Client Acceptance Memorandum generated on <?php echo $currentDate; ?>");
            
            // Show/hide alert messages
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            if (successMessage) {
                successMessage.style.display = 'block';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000);
            }
            
            if (errorMessage) {
                errorMessage.style.display = 'block';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            }
            
            // Form submission confirmation
            const saveForm = document.getElementById('saveForm');
            if (saveForm) {
                saveForm.addEventListener('submit', function(e) {
                    const confirmSave = confirm("Are you sure you want to save this memorandum? This action will record the acceptance in the database.");
                    if (!confirmSave) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>