<?php
ob_start();
session_start();
// if (empty($_SESSION['form']['step2'])){
//     header("Location: Audit-Fee.php");
// }

// echo '<pre>';
// print_r($_SESSION['form']['step0'] ?? 'Session step0 is empty or not set');
// echo '</pre>';


require_once "../../widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);

$step1 = $_SESSION['form']['step1'] ?? [];
$step0 = $_SESSION['form']['step0'] ?? [];
$step3 = $_SESSION['form']['step3'] ?? [];
// echo $step0['new'];
// Determine user status
if (isset($step0['new'])) {
    $userstatus = 'new';
} elseif (isset($step0['return'])) {
    $userstatus = 'return';
} else {
    $userstatus = 'new'; // Default
}

if($step0['application_type']=='new'){
    $userstatus='new';
    $new='new';
    $return='';
    }else{
        $userstatus='return';
        $return='return';
         $new='';
    }
// $new = isset($step0['new']) ? $step0['new'] : '';
// $return = isset($step0['return']) ? $step0['return'] : '';
$regitration = $step1['registration-status'] ?? '';


            // echo $userstatus;
            // echo $new;
            // echo $return;
            // echo $regitration;
// Populate form data from session
$formData = $step3 ?? [];

// Get specific values from session
$firstFinancialStatements = $formData['year'] ?? '';
$firstStartDate = $formData['first-start-date'] ?? '';
$firstEndDate = $formData['first-end-date'] ?? '';
$currentStartDate = $formData['current-start-date'] ?? '';
$currentEndDate = $formData['current-end-date'] ?? '';
$previousStartDate = $formData['previous-start-date'] ?? '';
$previousEndDate = $formData['previous-end-date'] ?? '';
$previousAudited = $formData['previous-audited'] ?? '';
$uploadedAuditStatement = isset($_SESSION['uploaded_audit_statement']) ? $_SESSION['uploaded_audit_statement'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['form']['step3'] = $_POST;
    
    // Handle file upload if exists
    if (isset($_FILES['upload-audit-statement']) && $_FILES['upload-audit-statement']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = [
            'name' => $_FILES['upload-audit-statement']['name'],
            'size' => $_FILES['upload-audit-statement']['size'],
            'type' => $_FILES['upload-audit-statement']['type'],
            'tmp_name' => $_FILES['upload-audit-statement']['tmp_name']
        ];
        $_SESSION['uploaded_audit_statement'] = [$fileInfo];
    }
    
      header("Location: ../../../controller/steps/save_fin_year.php");
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Entity Application - Financial Year Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Add Flatpickr for calendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .hide-form {
        display: none;
    }
    
    body { 
        font-family: 'Poppins', sans-serif; 
        background: #eef1f6; 
        padding: 40px; /* Reduced from 60px 40px */
        font-size: 16px; 
        line-height: 1.6; 
        color: #333; 
        display: flex;
        margin: 0;
        
        height: 100vh; /* Full viewport height */
        overflow: hidden; /* Hide overall scroll */
        box-sizing: border-box;
    }
    
    /* Sidebar Steps - Fixed Sidebar */
    .steps-sidebar {
        width: 280px;
        background: #ffffff;
        padding: 30px; /* Reduced from 40px 30px */
        border-radius: 8px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        margin-right: 30px;
        flex-shrink: 0;
        position: sticky;
        top: 40px; /* Stick to top with padding */
        align-self: flex-start; /* Align to top */
        max-height: calc(100vh - 80px); /* Limit height to viewport */
        overflow-y: auto; /* Make sidebar scrollable if needed */
        margin-top:30px;
    }
    
    /* Hide sidebar scrollbar */
    .steps-sidebar::-webkit-scrollbar {
        width: 0;
        background: transparent;
    }
    
    .steps-sidebar {
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }
    
    .sidebar-title {
        font-size: 24px;
        color: #1a1a1a;
        margin-bottom: 25px; /* Reduced from 30px */
        padding-bottom: 12px; /* Reduced from 15px */
        border-bottom: 1px solid #e0e0e0;
        font-weight: 400;
    }
    
    .sidebar-subtitle {
        font-size: 14px;
        color: #555;
        margin-bottom: 30px; /* Reduced from 40px */
        line-height: 1.5;
    }
    
    .steps-vertical {
        list-style: none;
        padding: 0;
        margin: 0;
        position: relative;
    }
    
    .steps-vertical::before {
        content: '';
        position: absolute;
        left: 14px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e0e0e0;
    }
    
    .step-vertical-item {
        display: flex;
        align-items: center;
        margin-bottom: 28px; /* Reduced from 35px */
        position: relative;
        min-height: 36px; /* Reduced from 40px */
    }
    
    .step-vertical-circle {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #e0e0e0;
        border: 2px solid #e0e0e0;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: 600;
        font-size: 14px;
        color: #fff;
        margin-right: 20px;
        z-index: 2;
        position: relative;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .step-vertical-circle.completed {
        background-color: #dd5656 !important;
        border-color: #dd5656 !important;
    }
    
    .step-vertical-circle.active {
        background-color: #333;
        border-color: #333;
    }
    
    .step-vertical-content {
        flex-grow: 1;
    }
    
    .step-vertical-title {
        font-size: 16px;
        color: #1a1a1a;
        margin: 0 0 4px 0; /* Reduced from 5px */
        line-height: 1.3; /* Tighter line height */
    }
    
    .step-vertical-status {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
        line-height: 1.2; /* Tighter line height */
    }
    
    .step-vertical-status.pending {
        color: #d17a0b;
    }
    
    .step-vertical-status.completed {
        color: #dd5656;
    }
    
    /* Main content - Scrollable */
    .main-content {
        flex-grow: 1;
        background: #ffffff;
        padding: 40px 50px; /* Reduced from 50px 60px */
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        min-width: 900px;
        max-height: calc(100vh - 80px); /* Limit height to viewport */
        overflow-y: auto; /* Make main content scrollable */
        position: relative;
        margin-top:30px;
        max-height: calc(90vh - 80px);
        
    }
    
    /* Hide main content scrollbar */
    .main-content::-webkit-scrollbar {
        width: 8px;
    }
    
    .main-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .main-content::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .main-content::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* For Firefox */
    .main-content {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }
    
    .content-header h1 {
        font-size: 32px;
        color: #1a1a1a;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px; /* Reduced from 12px */
        margin-top: 0;
        margin-bottom: 6px; /* Reduced from 8px */
        font-weight: 400;
    }
    
    .content-header .subheading {
        font-size: 14px;
        color: #555;
        margin-bottom: 30px; /* Reduced from 40px */
        line-height: 1.5;
    }
    
    .step-content-container {
        display: none;
    }
    
    .step-content-container.active {
        display: block;
        padding-bottom: 20px; /* Add padding at bottom */
    }
    
    .form-group { 
        margin-bottom: 20px; /* Reduced from 25px */
        position: relative; 
    }
    
    .form-group label { 
        display: block; 
        margin-bottom: 8px; 
        font-size: 15px; 
    }
    
    .form-group label.bold-label {
        font-weight: 600 !important;
    }
    
    .form-group select,
    .form-group input[type="text"] {
        width: 100%; 
        max-width: 500px;
        padding: 10px 15px; /* Reduced from 12px */
        border: 1px solid #ccc; 
        border-radius: 4px; 
        font-size: 15px; 
        box-sizing: border-box;
        background-color: white;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
    }
    
    /* Date field styling with calendar */
    .date-input-wrapper {
        position: relative;
        width: 100%;
        max-width: 500px;
    }
    
    .date-input-wrapper input {
        padding-right: 40px !important;
        width: 100%;
        padding: 10px 15px; /* Reduced from 12px */
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 15px;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }
    
    .calendar-icon {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #666;
        font-size: 18px;
        z-index: 10;
    }
    
    .calendar-icon:hover {
        color: #333;
    }
    
    /* Hide default calendar arrow */
    input[type="date"] {
        display: none;
    }
    
    .date-input-container {
        display: flex;
        gap: 25px; /* Reduced from 30px */
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    .date-input-group {
        flex: 1;
        min-width: 250px;
    }
    
    .attachments-container {
        margin-top: 20px; /* Reduced spacing */
        padding: 18px; /* Reduced from 20px */
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        width: 100%;
        max-width: 460px;
    }
    
    .uploaded-files {
        margin-top: 12px; /* Reduced from 15px */
        padding: 12px; /* Reduced from 15px */
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        min-height: 55px; /* Reduced from 60px */
    }
    
    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px; /* Reduced from 10px 12px */
        background: #f5f5f5;
        border-radius: 4px;
        margin-bottom: 8px; /* Reduced from 10px */
        border: 1px solid #e0e0e0;
    }
    
    .file-item:last-child {
        margin-bottom: 0;
    }
    
    .file-name {
        flex-grow: 1;
        font-size: 14px;
        color: #333;
    }
    
    .file-actions {
        display: flex;
        gap: 8px; /* Reduced from 10px */
    }
    
    .download-btn, .delete-btn {
        padding: 4px 8px !important; /* Reduced from 5px 10px */
        font-size: 12px !important;
        border-radius: 3px !important;
        color: #333 !important;
        background-color: #f2f2f2 !important;
        border: 1px solid #ddd !important;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
    }
    
    .download-btn:hover, .delete-btn:hover {
        background-color: #e6e6e6 !important;
    }
    
    .error-message {
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 12px; /* Reduced from 15px */
        margin: 15px 0; /* Reduced from 20px */
        font-size: 14px;
        width: 100%;
        max-width: 500px;
    }
    
    .ineligible-message {
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 12px; /* Reduced from 15px */
        margin: 12px 0; /* Reduced from 15px */
        font-size: 14px;
        line-height: 1.6;
        width: 100%;
        max-width: 500px;
        display: none;
    }
    
    .validation-errors-container {
        margin-top: 25px; /* Reduced from 30px */
        padding: 18px; /* Reduced from 20px */
        background: #fdebea;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        color: #721c24;
        display: none;
        width: 100%;
        max-width: 460px;
    }
    
    .validation-errors-container.returning-user {
        width: 400px !important;
        max-width: 400px !important;
    }
    
    .validation-errors-container h4 {
        margin-top: 0;
        margin-bottom: 12px; /* Reduced from 15px */
        color: #721c24;
        font-size: 16px;
        font-weight: 300 !important;
    }
    
    .error-item {
        margin-bottom: 6px; /* Reduced from 8px */
        padding-left: 20px;
        position: relative;
        font-size: 14px;
        line-height: 1.4; /* Tighter line height */
    }
    
    .error-item::before {
        content: "⚠";
        position: absolute;
        left: 0;
    }
    
    .error-item:last-child {
        margin-bottom: 0;
    }
    
    .navigation-buttons {
        display: flex;
        justify-content: space-between;
        margin-top: 30px; /* Reduced from 40px */
        padding-top: 25px; /* Reduced from 30px */
        border-top: 1px solid #e0e0e0;
        width: 100%;
    }
    
    button {
        background-color: #f2f2f2;
        color: #333;
        padding: 10px 20px; /* Reduced from 12px 25px */
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        font-size: 14px; /* Reduced from 15px */
        font-weight: 600;
        border: 1px solid #ddd;
        transition: background-color 0.3s ease;
    }
    
    button:hover {
        background-color: #e6e6e6;
    }
    
    button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    button.proceed-btn {
        background-color: #f2f2f2 !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
    }
    
    button.proceed-btn:hover:not(:disabled) {
        background-color: #e6e6e6 !important;
    }
    
    button.proceed-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .question-container {
        margin-bottom: 25px; /* Reduced from 30px */
        max-width: 500px;
    }
    
    .file-upload-label {
        display: inline-block;
        padding: 10px 15px; /* Reduced from 12px 20px */
        background-color: #f2f2f2;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px; /* Reduced from 14px */
        color: #333;
        margin-top: 8px; /* Reduced from 10px */
        transition: background-color 0.3s ease;
    }
    
    .file-upload-label:hover {
        background-color: #e6e6e6;
    }
    
    input[type="file"] {
        display: none;
    }
    
    .attachments-title {
        font-weight: 600 !important;
        margin-bottom: 12px !important; /* Reduced from 15px */
        color: #333;
        font-size: 15px;
    }
    
    /* Flatpickr calendar styling */
    .flatpickr-calendar {
        font-family: 'Poppins', sans-serif !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        border-radius: 8px !important;
        border: 1px solid #ddd !important;
    }
    
    .flatpickr-day.selected {
        background-color: #dd5656 !important;
        border-color: #dd5656 !important;
    }
    
    .flatpickr-day.today {
        border-color: #dd5656 !important;
    }
    
    .flatpickr-day:hover {
        background-color: #eef1f6 !important;
    }
    
    .date-placeholder {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        pointer-events: none;
        z-index: 0;
        background: white;
        padding: 0 5px;
    }
    
    /* Additional spacing improvements */
    .date-input-container[style*="margin-top: 30px"] {
        margin-top: 20px !important; /* Reduced from 30px */
    }
    
    .question-container[style*="margin-top: 30px"] {
        margin-top: 20px !important; /* Reduced from 30px */
    }
    
    /* Form positioning adjustments */
    .step-content-container.active > .question-container:first-child {
        margin-top: 5px;
    }
    
    /* Select field spacing improvements */
    .form-group select {
        margin-bottom: 2px;
    }
    
    /* Attachments styling improvements */
    .attachments-container .form-group {
        margin-bottom: 15px;
    }

     .app-header {
    position: fixed;
    top: 5px;
    left: 40px;
    font-family: 'Poppins', sans-serif;
    font-size: 22px;
    font-weight: 600;
    color: #1a1a1a;
    z-index: 1000;
    margin-top:20px;
}

</style>
</head>
<body>
        <div class="app-header">Muhasba</div>

    <div class="steps-sidebar">
        <div class="sidebar-title"><?php if ($userstatus=="new"){echo("New Engagement Application");}elseif($userstatus=="return"){echo("Continue Engagement Application");} ?></div>
        <div class="sidebar-subtitle">
            <?php if($userstatus=="new"){echo("Ensures that each new entity meets onboarding, compliance, and eligibility standards before activation on the platform.");} elseif($userstatus=="return"){echo("Ensuring the entity meets all compliance standards before rolling forward to the next fiscal year.");}?>
        </div>
        
        <ul class="steps-vertical">
            <li class="step-vertical-item">
                <div class="step-vertical-circle completed">1</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Know Your Customer (KYC)</div>
                    <div class="step-vertical-status completed">COMPLETED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle completed">2</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Audit Fee Acknowledgement</div>
                    <div class="step-vertical-status completed">COMPLETED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle active">3</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Financial Year Details</div>
                    <div class="step-vertical-status pending">PENDING</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle">4</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Tax Status Disclosure</div>
                    <div class="step-vertical-status">NOT STARTED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle">5</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Engagement Letter Acceptance</div>
                    <div class="step-vertical-status">NOT STARTED</div>
                </div>
            </li>
        </ul>
    </div>

    <form method="POST" action="../../../controller/steps/save_fin_year.php" enctype="multipart/form-data" id="financial-year-form">
        <div class="main-content">
            <div class="content-header">
                <h1>Financial Year Details</h1>
                <div class="subheading">
                    Please provide details about your financial reporting periods.
                </div>
            </div>
            
            <div class="step-content-container active">
                
                <div class="question-container">
                    <div class="form-group">
                        <select id="business-registration-status" onchange="validateStep(); toggleFinancialYearFields()" disabled class="hide-form">
                            <option value="">Select Business Status</option>
                            <option value="Unlicensed Natural Person(s)" <?php echo ($regitration == 'Unlicensed Natural Person(s)') ? 'selected' : ''; ?>>UNLICENSED NATURAL PERSON(S)</option>
                            <option value="Mainland Licensed-Sole Owner" <?php echo ($regitration == 'Mainland Licensed-Sole Owner') ? 'selected' : ''; ?>>MAINLAND LICENSED-SOLE OWNER</option>
                            <option value="Mainland Licensed-Multiple Owners" <?php echo ($regitration == 'Mainland Licensed-Multiple Owners') ? 'selected' : ''; ?>>MAINLAND LICENSED-MULTIPLE OWNERS</option>
                            <option value="Free Zone Licensed" <?php echo ($regitration == 'Free Zone Licensed') ? 'selected' : ''; ?>>FREE ZONE LICENSED</option>
                        </select>
                    </div>
                </div>

                <?php if ($return == 'return'): ?>
                    <!-- Returning user - Show only not-first year fields -->
                    <div id="not-first-year-fields">
                        <div class="date-input-container">
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="current-start-date">Start date of the current financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="current-start-date" name="current-start-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($currentStartDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('current-start-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="current-end-date">End date of the current financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="current-end-date" name="current-end-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($currentEndDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('current-end-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="date-input-container" style="margin-top: 30px;">
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="previous-start-date">Start date of the previous financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="previous-start-date" name="previous-start-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($previousStartDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('previous-start-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="previous-end-date">End date of the previous financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="previous-end-date" name="previous-end-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($previousEndDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('previous-end-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Returning users automatically have previous year audited -->
                        <input type="hidden" id="previous-audited" name="previous-audited" value="Yes">
                    </div>
                    <?php echo $regitration; ?>
                <?php elseif ($new == 'new'): ?>
                    <!-- New user - Show the question and relevant fields -->
                    <div class="question-container">
                        <div class="form-group">
                            <label for="first-financial-statements">
                                Are these the first financial statements being prepared?
                            </label>
                            <select id="first-financial-statements" onchange="toggleFinancialYearFields();" name="year">
                                <option value="">Select</option>
                                <option value="Yes" <?php echo ($firstFinancialStatements == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo ($firstFinancialStatements == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="first-year-fields" style="display: none;">
                        <div class="date-input-container">
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="first-start-date">Start date of the financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="first-start-date" name="first-start-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($firstStartDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('first-start-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="first-end-date">End date of the financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="first-end-date" name="first-end-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($firstEndDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('first-end-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="new-not-first-year-fields" style="display: none;">
                        <div class="date-input-container">
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="current-start-date">Start date of the current financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="current-start-date" name="current-start-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($currentStartDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('current-start-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="current-end-date">End date of the current financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="current-end-date" name="current-end-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($currentEndDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('current-end-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="date-input-container" style="margin-top: 30px;">
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="previous-start-date">Start date of the previous financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="previous-start-date" name="previous-start-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($previousStartDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('previous-start-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-input-group">
                                <div class="form-group">
                                    <label for="previous-end-date">End date of the previous financial year</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" id="previous-end-date" name="previous-end-date" 
                                               placeholder="DD/MM/YYYY" 
                                               value="<?php echo htmlspecialchars($previousEndDate); ?>"
                                               oninput="formatDateInput(this); validateStep()">
                                        <span class="calendar-icon" onclick="openCalendar('previous-end-date')">📅</span>
                                        <span class="date-placeholder">DD/MM/YYYY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="question-container" style="margin-top: 30px;">
                            <div class="form-group">
                                <label for="previous-audited">
                                    Were the prior year financial statements audited and issued with a report by a licensed auditor?
                                </label>
                                <select id="previous-audited" name="previous-audited" onchange="toggleAuditUpload(); validateStep()">
                                    <option value="">Select</option>
                                    <option value="Yes" <?php echo ($previousAudited == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo ($previousAudited == 'No') ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="audit-upload-fields" style="display: none;">
                            <div class="attachments-container">
                                <div class="form-group">
                                    <label for="upload-audit-statement" class="attachments-title">
                                        Please upload a PDF copy of the audited financial statements for the previous financial year:
                                    </label>
                                    <label for="upload-audit-statement" class="file-upload-label">
                                        <?php echo !empty($uploadedAuditStatement) ? 'Change PDF File' : 'Choose PDF File'; ?>
                                    </label>
                                    <input type="file" id="upload-audit-statement" name="upload-audit-statement" accept=".pdf" onchange="handleAuditFileUpload(event)" />
                                    <div class="uploaded-files" id="audit-statement-files">
                                        <?php if (!empty($uploadedAuditStatement)): ?>
                                            <?php foreach ($uploadedAuditStatement as $index => $file): ?>
                                                <div class="file-item">
                                                    <div class="file-name"><?php echo htmlspecialchars($file['name']); ?> (<?php echo round($file['size'] / (1024*1024), 2); ?> MB)</div>
                                                    <div class="file-actions">
                                                        <button type="button" class="download-btn" onclick="downloadFile(<?php echo $index; ?>)">Download</button>
                                                        <button type="button" class="delete-btn" onclick="deleteFile(<?php echo $index; ?>)">Delete</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="color: #888; font-style: italic;">No files uploaded yet</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="validation-errors-container <?php echo ($return == 'return') ? 'returning-user' : ''; ?>" id="validation-errors">
                    <h4>Missing Information Required:</h4>
                    <div id="validation-errors-list"></div>
                    <div id="ineligible-message-container" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f5c6cb;"></div>
                </div>
                
                <div class="navigation-buttons">
                    <button type="button" onclick="goToPreviousStep()">
                        ← Previous Step
                    </button>
                    <button type="button" id="next-step-btn" class="proceed-btn" onclick="proceedToNextStep()">
                        Proceed to Next Step →
                    </button>
                </div>
            </div>
        </div>
    </form>

                                      
    <!-- Add Flatpickr library -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/default.js"></script>
    
    <script>
        const registrationStatusSelect = document.getElementById('business-registration-status');
        const firstFinancialStatements = document.getElementById('first-financial-statements');
        const firstYearFields = document.getElementById('first-year-fields');
        const notFirstYearFields = document.getElementById('not-first-year-fields');
        const newNotFirstYearFields = document.getElementById('new-not-first-year-fields');
        const previousAudited = document.getElementById('previous-audited');
        const auditUploadFields = document.getElementById('audit-upload-fields');
        const uploadAuditStatement = document.getElementById('upload-audit-statement');
        const auditStatementFiles = document.getElementById('audit-statement-files');
        const validationErrors = document.getElementById('validation-errors');
        const validationErrorsList = document.getElementById('validation-errors-list');
        const ineligibleMessageContainer = document.getElementById('ineligible-message-container');
        const nextStepBtn = document.getElementById('next-step-btn');
        const fileUploadLabel = document.querySelector('.file-upload-label');

        // Initialize uploaded files from PHP session
        let uploadedAuditStatement = <?php echo json_encode($uploadedAuditStatement); ?>;
        let entityType = '<?php echo $regitration; ?>';
        let validateTimeout;
        let isReturningUser = <?php echo ($return == 'return') ? 'true' : 'false'; ?>;
        let isNewUser = <?php echo ($new == 'new') ? 'true' : 'false'; ?>;

        // Flatpickr instances
        let datePickers = {};

        // Function to extract year from DD/MM/YYYY format
        function getYearFromDate(dateStr) {
            if (!dateStr || dateStr.length !== 10) return null;
            const parts = dateStr.split('/');
            if (parts.length !== 3) return null;
            return parseInt(parts[2], 10);
        }

        // Function to calculate next day in DD/MM/YYYY format
        function getNextDay(dateStr) {
            if (!dateStr || dateStr.length !== 10) return null;
            
            const parts = dateStr.split('/');
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1; // JavaScript months are 0-indexed
            const year = parseInt(parts[2], 10);
            
            const date = new Date(year, month, day);
            date.setDate(date.getDate() + 1); // Add one day
            
            // Format back to DD/MM/YYYY
            const nextDay = date.getDate().toString().padStart(2, '0');
            const nextMonth = (date.getMonth() + 1).toString().padStart(2, '0');
            const nextYear = date.getFullYear();
            
            return `${nextDay}/${nextMonth}/${nextYear}`;
        }

        // Function to format date as DD/MM/YYYY for comparison
        function formatDateForDisplay(date) {
            if (!date) return '';
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Set entity type from PHP
            if (registrationStatusSelect) {
                registrationStatusSelect.value = entityType;
            }
            
            // Bind registration status field to update and validation functions
            registrationStatusSelect.addEventListener('change', function() {
                updateEntityType();
                validateStep();
            });
            
            if (firstFinancialStatements) {
                firstFinancialStatements.addEventListener('change', function() {
                    toggleFinancialYearFields();
                    validateStep();
                });
            }
            
            if (previousAudited) {
                previousAudited.addEventListener('change', function() {
                    toggleAuditUpload();
                    validateStep();
                });
            }
            
            // Initialize all fields based on user type
            if (isReturningUser) {
                // Returning user - no need to toggle fields
                validateStep();
            } else if (isNewUser && firstFinancialStatements) {
                // New user - initialize based on the question
                toggleFinancialYearFields();
                toggleAuditUpload();
            }
            
            // Setup Flatpickr for all date inputs
            setupDatePickers();
            
            // Add event listeners to all date inputs
            setupDateFieldListeners();
            
            // Perform initial validation to set button state
            validateStep();
            
            // Initialize button state
            updateNextButtonState();
        });

        // Setup Flatpickr instances for date inputs
        function setupDatePickers() {
            const dateInputs = document.querySelectorAll('.date-input-wrapper input[type="text"]');
            
            dateInputs.forEach(input => {
                if (!input.id) return;
                
                datePickers[input.id] = flatpickr(input, {
                    dateFormat: "d/m/Y",
                    allowInput: true,
                    locale: "default",
                    disableMobile: true,
                    onChange: function(selectedDates, dateStr, instance) {
                        instance.input.value = dateStr;
                        validateStep();
                        
                        // Hide placeholder when date is selected
                        const placeholder = instance.input.parentNode.querySelector('.date-placeholder');
                        if (placeholder && dateStr) {
                            placeholder.style.display = 'none';
                        }
                    },
                    onOpen: function(selectedDates, dateStr, instance) {
                        // Show placeholder when calendar opens if empty
                        const placeholder = instance.input.parentNode.querySelector('.date-placeholder');
                        if (placeholder && !dateStr) {
                            placeholder.style.display = 'block';
                        }
                    }
                });
            });
        }

        // Open calendar for specific input
        function openCalendar(inputId) {
            if (datePickers[inputId]) {
                datePickers[inputId].open();
            }
        }

        // Format date input field to DD/MM/YYYY
        function formatDateInput(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.length > 2 && value.length <= 4) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            } else if (value.length > 4) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4) + '/' + value.substring(4, 8);
            }
            
            input.value = value;
            
            // Hide placeholder when typing
            const placeholder = input.parentNode.querySelector('.date-placeholder');
            if (placeholder && value) {
                placeholder.style.display = 'none';
            }
            
            // Validate date when complete
            if (value.length === 10) {
                validateDateInput(input);
            }
            
            validateStep();
        }

        // Validate DD/MM/YYYY date format
        function validateDateInput(input) {
            const value = input.value;
            const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
            
            if (!dateRegex.test(value)) {
                return false;
            }
            
            const parts = value.split('/');
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            
            // Validate date
            if (month < 1 || month > 12) return false;
            if (day < 1 || day > 31) return false;
            
            // Validate months with 30 days
            if ([4, 6, 9, 11].includes(month) && day > 30) return false;
            
            // Validate February and leap year
            if (month === 2) {
                const isLeapYear = (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
                if (day > 29 || (day === 29 && !isLeapYear)) return false;
            }
            
            return true;
        }

        // Convert DD/MM/YYYY to Date object
        function parseDDMMYYYY(dateStr) {
            if (!dateStr || dateStr.length !== 10) return null;
            
            const parts = dateStr.split('/');
            if (parts.length !== 3) return null;
            
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const year = parseInt(parts[2], 10);
            
            return new Date(year, month, day);
        }

        // Convert DD/MM/YYYY to YYYY-MM-DD format for calculations
        function convertToYYYYMMDD(dateStr) {
            if (!dateStr || !validateDateInput({value: dateStr})) return '';
            
            const parts = dateStr.split('/');
            return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
        }

        // Update entity type from new dropdown
        function updateEntityType() {
            entityType = registrationStatusSelect.value;
        }

        function toggleFinancialYearFields() {
            if (!firstFinancialStatements) return;
            
            const answer = firstFinancialStatements.value;
            
            if (firstYearFields) firstYearFields.style.display = 'none';
            if (newNotFirstYearFields) newNotFirstYearFields.style.display = 'none';
            if (auditUploadFields) auditUploadFields.style.display = 'none';
            
            if (answer === 'Yes') {
                firstYearFields.style.display = 'block';
            } else if (answer === 'No') {
                newNotFirstYearFields.style.display = 'block';
                
                // Update audit upload field display based on previous audit status
                toggleAuditUpload();
            }
            
            // Re-validate after changing displayed fields
            validateStep();
        }

        function toggleAuditUpload() {
            if (!previousAudited) return;
            
            const answer = previousAudited.value;
            
            if (auditUploadFields) {
                auditUploadFields.style.display = 'none';
                
                if (answer === 'Yes') {
                    auditUploadFields.style.display = 'block';
                }
            }
            
            validateStep();
        }

        function setupDateFieldListeners() {
            // Add event listeners to all date inputs
            const dateInputs = document.querySelectorAll('.date-input-wrapper input[type="text"]');
            dateInputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateStep();
                });
                
                input.addEventListener('focus', function() {
                    const placeholder = this.parentNode.querySelector('.date-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        const placeholder = this.parentNode.querySelector('.date-placeholder');
                        if (placeholder) {
                            placeholder.style.display = 'block';
                        }
                    }
                });
            });
        }

        function handleAuditFileUpload(event) {
            const files = event.target.files;
            const MAX_SIZE = 10 * 1024 * 1024; // 10MB
            
            // Clear previous files
            uploadedAuditStatement = [];
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (file.type !== 'application/pdf') {
                    alert('Only PDF files are allowed.');
                    continue;
                }
                
                if (file.size > MAX_SIZE) {
                    alert('File size must be less than 10MB');
                    continue;
                }
                
                // Validate file name
                if (!/^[a-zA-Z0-9._\-() ]+$/.test(file.name)) {
                    alert('Invalid file name. Only letters, numbers, spaces, dots, hyphens, underscores and parentheses are allowed.');
                    continue;
                }
                
                uploadedAuditStatement.push({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    file: file
                });
            }
            
            updateFileDisplay();
            validateStep();
            
            if (uploadedAuditStatement.length > 0 && fileUploadLabel) {
                fileUploadLabel.textContent = 'Change PDF File';
            } else if (fileUploadLabel) {
                fileUploadLabel.textContent = 'Choose PDF File';
            }
        }

        function updateFileDisplay() {
            if (!auditStatementFiles) return;
            
            auditStatementFiles.innerHTML = '';
            
            if (uploadedAuditStatement.length === 0) {
                auditStatementFiles.innerHTML = '<div style="color: #888; font-style: italic;">No files uploaded yet</div>';
                return;
            }
            
            uploadedAuditStatement.forEach((fileData, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const sizeInMB = (fileData.size / (1024 * 1024)).toFixed(2);
                
                fileItem.innerHTML = `
                    <div class="file-name">${fileData.name} (${sizeInMB} MB)</div>
                    <div class="file-actions">
                        <button type="button" class="download-btn" onclick="downloadFile(${index})">Download</button>
                        <button type="button" class="delete-btn" onclick="deleteFile(${index})">Delete</button>
                    </div>
                `;
                
                auditStatementFiles.appendChild(fileItem);
            });
        }

        function downloadFile(index) {
            if (index >= 0 && index < uploadedAuditStatement.length) {
                const fileData = uploadedAuditStatement[index];
                if (fileData.file) {
                    const url = URL.createObjectURL(fileData.file);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = fileData.name;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
            }
        }

        function deleteFile(index) {
            if (index >= 0 && index < uploadedAuditStatement.length) {
                uploadedAuditStatement.splice(index, 1);
                updateFileDisplay();
                validateStep();
                
                // Clear the file input
                if (uploadAuditStatement) {
                    uploadAuditStatement.value = '';
                }
                
                if (uploadedAuditStatement.length === 0 && fileUploadLabel) {
                    fileUploadLabel.textContent = 'Choose PDF File';
                }
            }
        }

        // Calculate month difference between two dates (DD/MM/YYYY format)
        function getMonthDifference(startDateStr, endDateStr) {
            const start = parseDDMMYYYY(startDateStr);
            const end = parseDDMMYYYY(endDateStr);
            
            if (!start || !end) {
                return 0;
            }
            
            let months = (end.getFullYear() - start.getFullYear()) * 12;
            months -= start.getMonth();
            months += end.getMonth();

            // Add 1 because period is calculated from first day of first month to last day of last month
            return months + 1;
        }

        // Main validation logic
        function validateStep() {
            clearTimeout(validateTimeout);
            validateTimeout = setTimeout(() => {
                performValidation();
            }, 300);
        }

        function performValidation() {
            let errors = [];
            let ineligibleMessage = '';
            
            // Update entity type before validation
            updateEntityType(); 
            
            // 1. Validate registration status
            if (!entityType) {
                errors.push("Please select the Business Registration Status to continue");
            }

            if (isReturningUser) {
                // Returning user validation
                const currentStartDateInput = document.getElementById('current-start-date');
                const currentEndDateInput = document.getElementById('current-end-date');
                const previousStartDateInput = document.getElementById('previous-start-date');
                const previousEndDateInput = document.getElementById('previous-end-date');
                
                const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                
                if (!currentStartDate) errors.push("Start date of the current financial year is required");
                if (!currentEndDate) errors.push("End date of the current financial year is required");
                if (!previousStartDate) errors.push("Start date of the previous financial year is required");
                if (!previousEndDate) errors.push("End date of the previous financial year is required");
                
                if (currentStartDate && currentEndDate) {
                    if (!validateDateInput({value: currentStartDate})) {
                        errors.push("Please enter a valid date for current financial year start date (DD/MM/YYYY)");
                    }
                    if (!validateDateInput({value: currentEndDate})) {
                        errors.push("Please enter a valid date for current financial year end date (DD/MM/YYYY)");
                    }
                    
                    if (validateDateInput({value: currentStartDate}) && validateDateInput({value: currentEndDate})) {
                        const startDate = parseDDMMYYYY(currentStartDate);
                        const endDate = parseDDMMYYYY(currentEndDate);
                        
                        if (endDate <= startDate) {
                            errors.push("Current financial year: End date must be after start date");
                        }
                        
                        // UPDATED RULE: For ALL returning users (not first statements), current year must be exactly 12 months
                        const currentMonths = getMonthDifference(currentStartDate, currentEndDate);
                        if (currentMonths !== 12) {
                            errors.push("For all entity types (not first statements), the current financial year must be exactly 12 months.");
                        }
                    }
                }
                
                if (previousStartDate && previousEndDate) {
                    if (!validateDateInput({value: previousStartDate})) {
                        errors.push("Please enter a valid date for previous financial year start date (DD/MM/YYYY)");
                    }
                    if (!validateDateInput({value: previousEndDate})) {
                        errors.push("Please enter a valid date for previous financial year end date (DD/MM/YYYY)");
                    }
                    
                    if (validateDateInput({value: previousStartDate}) && validateDateInput({value: previousEndDate})) {
                        const startDate = parseDDMMYYYY(previousStartDate);
                        const endDate = parseDDMMYYYY(previousEndDate);
                        
                        if (endDate <= startDate) {
                            errors.push("Previous financial year: End date must be after start date");
                        }

                        const isIndividualOrSoleOwner = (entityType === 'Unlicensed Natural Person(s)' || entityType === 'Mainland Licensed-Sole Owner');
                        const isMultipleOrFreeZone = (entityType === 'Mainland Licensed-Multiple Owners' || entityType === 'Free Zone Licensed');
                        
                        if (isMultipleOrFreeZone) {
                            // Rule 2.2: Previous financial year must be between 6 and 18 months
                            const prevMonths = getMonthDifference(previousStartDate, previousEndDate);
                            if (prevMonths < 6 || prevMonths > 18) {
                                errors.push("For licensed entities with multiple owners or free zone entities, the previous financial year must be between 6 and 18 months.");
                            }
                        } else if (isIndividualOrSoleOwner) {
                            // Rule 1: Previous financial year must end on December 31st
                            const previousEndDateObj = parseDDMMYYYY(previousEndDate);
                            if (previousEndDateObj.getMonth() !== 11 || previousEndDateObj.getDate() !== 31) {
                                errors.push("For natural persons or sole owner licensed entities, the previous financial year must end on December 31st.");
                            }
                            
                            // NEW RULE: For natural persons and sole owners (not first year), previous year start and end date must be in same calendar year
                            const prevStartYear = getYearFromDate(previousStartDate);
                            const prevEndYear = getYearFromDate(previousEndDate);
                            
                            if (prevStartYear && prevEndYear && prevStartYear !== prevEndYear) {
                                errors.push("For natural persons or sole owner licensed entities (not first financial statements), the previous financial year start date and end date must fall within the same calendar year. Please ensure that both dates are within one calendar year.");
                            }
                        }
                    }
                }
                
                // NEW RULE: For returning users, current start date must be exactly next day after previous end date
                if (previousEndDate && currentStartDate) {
                    const expectedNextDay = getNextDay(previousEndDate);
                    if (expectedNextDay && currentStartDate !== expectedNextDay) {
                        errors.push("For not first statements, the start date of the current financial year must be exactly the next day after the end date of the previous financial year. Expected start date: " + expectedNextDay);
                    }
                }
                
            } else if (isNewUser && firstFinancialStatements) {
                // New user validation
                // 2. Validate "Are these the first financial statements" answer
                if (!firstFinancialStatements.value || firstFinancialStatements.value === '') {
                    errors.push("Please answer if these are the first financial statements");
                }
                
                const isIndividualOrSoleOwner = (entityType === 'Unlicensed Natural Person(s)' || entityType === 'Mainland Licensed-Sole Owner');
                const isMultipleOrFreeZone = (entityType === 'Mainland Licensed-Multiple Owners' || entityType === 'Free Zone Licensed');

                if (firstFinancialStatements.value === 'Yes') {
                    const firstStartDateInput = document.getElementById('first-start-date');
                    const firstEndDateInput = document.getElementById('first-end-date');
                    
                    const firstStartDate = firstStartDateInput ? firstStartDateInput.value : '';
                    const firstEndDate = firstEndDateInput ? firstEndDateInput.value : '';
                    
                    if (!firstStartDate) errors.push("Start date of the financial year is required");
                    if (!firstEndDate) errors.push("End date of the financial year is required");
                    
                    if (firstStartDate && firstEndDate) {
                        if (!validateDateInput({value: firstStartDate})) {
                            errors.push("Please enter a valid date for financial year start date (DD/MM/YYYY)");
                        }
                        if (!validateDateInput({value: firstEndDate})) {
                            errors.push("Please enter a valid date for financial year end date (DD/MM/YYYY)");
                        }
                        
                        if (validateDateInput({value: firstStartDate}) && validateDateInput({value: firstEndDate})) {
                            const startDate = parseDDMMYYYY(firstStartDate);
                            const endDate = parseDDMMYYYY(firstEndDate);
                            
                            if (endDate <= startDate) {
                                errors.push("End date must be after start date");
                            }
                            
                            const months = getMonthDifference(firstStartDate, firstEndDate);
                            
                            // UPDATED RULE: For Natural Persons AND Sole Owners (first statements), start year and end year must be the same
                            if (entityType === 'Unlicensed Natural Person(s)' || entityType === 'Mainland Licensed-Sole Owner') {
                                const startYear = getYearFromDate(firstStartDate);
                                const endYear = getYearFromDate(firstEndDate);
                                
                                if (startYear && endYear && startYear !== endYear) {
                                    errors.push("For unlicensed natural persons or sole owner licensed entities (first financial statements), the start year and end year must fall within the same calendar year. Please ensure that both dates are within one calendar year.");
                                }
                            }
                            
                            if (isMultipleOrFreeZone) {
                                // Rule 2.1: Period must be between 6 and 18 months (for first financial statements)
                                if (months < 6 || months > 18) {
                                    errors.push("Please note that for licensed entities with multiple owners or free zone entities (first statements), the financial period must be between 6 and 18 months. Kindly adjust the selected period to continue.");
                                }
                            } else if (isIndividualOrSoleOwner) {
                                // Rule 1: Must end on December 31st
                                const endDateObj = parseDDMMYYYY(firstEndDate);
                                if (endDateObj.getMonth() !== 11 || endDateObj.getDate() !== 31) {
                                    errors.push("Please note that for natural persons or sole owner licensed entities, the financial year must end on December 31st. The start date may fall in any month, provided that the period concludes on December 31. Kindly adjust the selected period to proceed.");
                                }
                            }
                        }
                    }
                    
                } else if (firstFinancialStatements.value === 'No') {
                    const currentStartDateInput = document.getElementById('current-start-date');
                    const currentEndDateInput = document.getElementById('current-end-date');
                    const previousStartDateInput = document.getElementById('previous-start-date');
                    const previousEndDateInput = document.getElementById('previous-end-date');
                    
                    const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                    const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                    const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                    const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                    
                    if (!currentStartDate) errors.push("Start date of the current financial year is required");
                    if (!currentEndDate) errors.push("End date of the current financial year is required");
                    if (!previousStartDate) errors.push("Start date of the previous financial year is required");
                    if (!previousEndDate) errors.push("End date of the previous financial year is required");
                    
                    if (!previousAudited.value || previousAudited.value === '') {
                        errors.push("Please answer if the prior year financial statements were audited");
                    }
                    
                    if (currentStartDate && currentEndDate) {
                        if (!validateDateInput({value: currentStartDate})) {
                            errors.push("Please enter a valid date for current financial year start date (DD/MM/YYYY)");
                        }
                        if (!validateDateInput({value: currentEndDate})) {
                            errors.push("Please enter a valid date for current financial year end date (DD/MM/YYYY)");
                        }
                        
                        if (validateDateInput({value: currentStartDate}) && validateDateInput({value: currentEndDate})) {
                            const startDate = parseDDMMYYYY(currentStartDate);
                            const endDate = parseDDMMYYYY(currentEndDate);
                            
                            if (endDate <= startDate) {
                                errors.push("Current financial year: End date must be after start date");
                            }
                            
                            // UPDATED RULE: For ALL entity types when these are NOT the first statements, current year must be exactly 12 months
                            const currentMonths = getMonthDifference(currentStartDate, currentEndDate);
                            if (currentMonths !== 12) {
                                errors.push("For all entity types when these are not the first financial statements, the current financial year must be exactly 12 months.");
                            }
                        }
                    }
                    
                    if (previousStartDate && previousEndDate) {
                        if (!validateDateInput({value: previousStartDate})) {
                            errors.push("Please enter a valid date for previous financial year start date (DD/MM/YYYY)");
                        }
                        if (!validateDateInput({value: previousEndDate})) {
                            errors.push("Please enter a valid date for previous financial year end date (DD/MM/YYYY)");
                        }
                        
                        if (validateDateInput({value: previousStartDate}) && validateDateInput({value: previousEndDate})) {
                            const startDate = parseDDMMYYYY(previousStartDate);
                            const endDate = parseDDMMYYYY(previousEndDate);
                            
                            if (endDate <= startDate) {
                                errors.push("Previous financial year: End date must be after start date");
                            }

                            if (isMultipleOrFreeZone) {
                                // Rule 2.2: Previous financial year must be between 6 and 18 months
                                const prevMonths = getMonthDifference(previousStartDate, previousEndDate);
                                if (prevMonths < 6 || prevMonths > 18) {
                                    errors.push("For licensed entities with multiple owners or free zone entities, the previous financial year must be between 6 and 18 months.");
                                }
                            } else if (isIndividualOrSoleOwner) {
                                // Rule 1: Previous financial year must end on December 31st
                                const previousEndDateObj = parseDDMMYYYY(previousEndDate);
                                if (previousEndDateObj.getMonth() !== 11 || previousEndDateObj.getDate() !== 31) {
                                    errors.push("For natural persons or sole owner licensed entities, the previous financial year must end on December 31st.");
                                }
                                
                                // NEW RULE: For natural persons and sole owners (not first year), previous year start and end date must be in same calendar year
                                const prevStartYear = getYearFromDate(previousStartDate);
                                const prevEndYear = getYearFromDate(previousEndDate);
                                
                                if (prevStartYear && prevEndYear && prevStartYear !== prevEndYear) {
                                    errors.push("For natural persons or sole owner licensed entities (not first financial statements), the previous financial year start date and end date must fall within the same calendar year. Please ensure that both dates are within one calendar year.");
                                }
                            }
                        }
                    }
                    
                    // NEW RULE: For new users with not first statements, current start date must be exactly next day after previous end date
                    if (previousEndDate && currentStartDate) {
                        const expectedNextDay = getNextDay(previousEndDate);
                        if (expectedNextDay && currentStartDate !== expectedNextDay) {
                            errors.push("For not first statements, the start date of the current financial year must be exactly the next day after the end date of the previous financial year. Expected start date: " + expectedNextDay);
                        }
                    }
                    
                    if (previousAudited.value === 'Yes' && uploadedAuditStatement.length === 0) {
                        errors.push("Please upload the audited financial statements for the previous financial year");
                    }
                    
                    if (previousAudited.value === 'No') {
                        // Ineligibility message will appear in the main validation box
                        ineligibleMessage = "We're sorry — you are not eligible to proceed at this stage. The financial statements for the previous financial year must be audited by a licensed auditor. Alternatively, you may proceed by changing the financial reporting period. After doing so, you can create a new engagement for the updated financial period.";
                    }
                }
            }
            
            // Show/hide error messages
            if (errors.length > 0 || ineligibleMessage) {
                validationErrors.style.display = 'block';
                
                if (errors.length > 0) {
                    validationErrorsList.innerHTML = errors.map(error => 
                        `<div class="error-item">${error}</div>`
                    ).join('');
                } else {
                    validationErrorsList.innerHTML = '';
                }
                
                if (ineligibleMessage) {
                    ineligibleMessageContainer.style.display = 'block';
                    ineligibleMessageContainer.innerHTML = `<div class="error-item">${ineligibleMessage}</div>`;
                } else {
                    ineligibleMessageContainer.style.display = 'none';
                }
                
                disableNextButton();
            } else {
                validationErrors.style.display = 'none';
                validationErrorsList.innerHTML = '';
                ineligibleMessageContainer.style.display = 'none';
                
                updateNextButtonState();
            }
        }

        function disableNextButton() {
            nextStepBtn.disabled = true;
            nextStepBtn.style.opacity = '0.5';
            nextStepBtn.style.cursor = 'not-allowed';
        }

        function updateNextButtonState() {
            // Enable only if registration status is selected
            if (registrationStatusSelect.value) {
                let isValid = true;
                
                if (isReturningUser) {
                    // Returning user validation
                    const currentStartDateInput = document.getElementById('current-start-date');
                    const currentEndDateInput = document.getElementById('current-end-date');
                    const previousStartDateInput = document.getElementById('previous-start-date');
                    const previousEndDateInput = document.getElementById('previous-end-date');
                    
                    const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                    const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                    const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                    const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                    
                    if (!currentStartDate || !currentEndDate || !previousStartDate || !previousEndDate) {
                        isValid = false;
                    }
                } else if (isNewUser) {
                    // New user validation
                    if (!firstFinancialStatements.value || firstFinancialStatements.value === '') {
                        isValid = false;
                    } else if (firstFinancialStatements.value === 'Yes') {
                        const firstStartDateInput = document.getElementById('first-start-date');
                        const firstEndDateInput = document.getElementById('first-end-date');
                        
                        const firstStartDate = firstStartDateInput ? firstStartDateInput.value : '';
                        const firstEndDate = firstEndDateInput ? firstEndDateInput.value : '';
                        
                        if (!firstStartDate || !firstEndDate) {
                            isValid = false;
                        }
                    } else if (firstFinancialStatements.value === 'No') {
                        const currentStartDateInput = document.getElementById('current-start-date');
                        const currentEndDateInput = document.getElementById('current-end-date');
                        const previousStartDateInput = document.getElementById('previous-start-date');
                        const previousEndDateInput = document.getElementById('previous-end-date');
                        
                        const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                        const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                        const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                        const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                        const hasPreviousAudited = previousAudited ? previousAudited.value : '';
                        
                        if (!currentStartDate || !currentEndDate || !previousStartDate || !previousEndDate || !hasPreviousAudited) {
                            isValid = false;
                        }
                        
                        // Check if audit file is required and uploaded
                        if (hasPreviousAudited === 'Yes' && uploadedAuditStatement.length === 0) {
                            isValid = false;
                        }
                    }
                }
                
                if (isValid) {
                    nextStepBtn.disabled = false;
                    nextStepBtn.style.opacity = '1';
                    nextStepBtn.style.cursor = 'pointer';
                    return;
                }
            }
            
            disableNextButton();
        }

        function goToPreviousStep() {
            window.location.href = 'Audit-Fee.php'; 
        }

        function proceedToNextStep() {
            if (validateStepImmediate()) {
                showLoadingState();
                
                // Submit the form
                document.getElementById('financial-year-form').submit();
            } else {
                // Scroll to errors
                document.querySelector('.validation-errors-container').scrollIntoView({ 
                    behavior: 'smooth' 
                });
            }
        }

        function validateStepImmediate() {
            // Perform validation without debounce
            let errors = [];
            let ineligibleMessage = '';
            
            updateEntityType();
            
            if (!entityType) {
                errors.push("Please select the Business Registration Status to continue");
            }

            if (isReturningUser) {
                const currentStartDateInput = document.getElementById('current-start-date');
                const currentEndDateInput = document.getElementById('current-end-date');
                const previousStartDateInput = document.getElementById('previous-start-date');
                const previousEndDateInput = document.getElementById('previous-end-date');
                
                const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                
                if (!currentStartDate) errors.push("Start date of the current financial year is required");
                if (!currentEndDate) errors.push("End date of the current financial year is required");
                if (!previousStartDate) errors.push("Start date of the previous financial year is required");
                if (!previousEndDate) errors.push("End date of the previous financial year is required");
                
            } else if (isNewUser) {
                if (!firstFinancialStatements.value || firstFinancialStatements.value === '') {
                    errors.push("Please answer if these are the first financial statements");
                }
                
                if (firstFinancialStatements.value === 'Yes') {
                    const firstStartDateInput = document.getElementById('first-start-date');
                    const firstEndDateInput = document.getElementById('first-end-date');
                    
                    const firstStartDate = firstStartDateInput ? firstStartDateInput.value : '';
                    const firstEndDate = firstEndDateInput ? firstEndDateInput.value : '';
                    
                    if (!firstStartDate) errors.push("Start date of the financial year is required");
                    if (!firstEndDate) errors.push("End date of the financial year is required");
                } else if (firstFinancialStatements.value === 'No') {
                    const currentStartDateInput = document.getElementById('current-start-date');
                    const currentEndDateInput = document.getElementById('current-end-date');
                    const previousStartDateInput = document.getElementById('previous-start-date');
                    const previousEndDateInput = document.getElementById('previous-end-date');
                    
                    const currentStartDate = currentStartDateInput ? currentStartDateInput.value : '';
                    const currentEndDate = currentEndDateInput ? currentEndDateInput.value : '';
                    const previousStartDate = previousStartDateInput ? previousStartDateInput.value : '';
                    const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
                    
                    if (!currentStartDate) errors.push("Start date of the current financial year is required");
                    if (!currentEndDate) errors.push("End date of the current financial year is required");
                    if (!previousStartDate) errors.push("Start date of the previous financial year is required");
                    if (!previousEndDate) errors.push("End date of the previous financial year is required");
                    
                    if (!previousAudited.value || previousAudited.value === '') {
                        errors.push("Please answer if the prior year financial statements were audited");
                    }
                    
                    if (previousAudited.value === 'Yes' && uploadedAuditStatement.length === 0) {
                        errors.push("Please upload the audited financial statements for the previous financial year");
                    }
                }
            }
            
            return errors.length === 0 && !ineligibleMessage;
        }

        function showLoadingState() {
            const btn = document.getElementById('next-step-btn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid #333;border-top:2px solid transparent;border-radius:50%;animation:spin 1s linear infinite;margin-right:8px;"></span> Processing...';
            
            // Add spinner animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
            
            // Store original text to restore if needed
            btn.dataset.originalText = originalText;
        }

        // Prevent form submission if validation fails
        document.getElementById('financial-year-form').addEventListener('submit', function(e) {
            if (!validateStepImmediate()) {
                e.preventDefault();
                document.querySelector('.validation-errors-container').scrollIntoView({ 
                    behavior: 'smooth' 
                });
            }
        });
    </script>
</body>
</html>