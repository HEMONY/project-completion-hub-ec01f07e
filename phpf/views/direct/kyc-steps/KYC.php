<?php
// kyc.php
ob_start(); // Start output buffering to prevent header conflicts
session_start();
if (empty($_SESSION['form']['step0'])){
    header("Location: step0.php");
}

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

if(!empty($_SESSION['form']['step0'])){
    $step0=$_SESSION['form']['step0'];
}

if (isset($step0['new'])) {
    $userstatus = $step0['new'];
} else {
    $userstatus = 'return';
}

if (isset($step0['return'])) {
    $userstatus = $step0['return'];
} else {
    $userstatus = 'new';
}




// In your step1.php POST handling section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure session structure
    $_SESSION['form'] ??= [];
    $_SESSION['form']['step1'] ??= [];
    $_SESSION['form']['step1']['id_passport_files'] ??= [];
    
    // Save POST fields safely
    foreach ($_POST as $key => $value) {
        $_SESSION['form']['step1'][$key] = $value;
    }
    
    // Save ALL uploaded files with size validation
    if (!empty($_FILES['upload_id_passport']['name'][0])) {
        foreach ($_FILES['upload_id_passport']['tmp_name'] as $i => $tmp) {
            if ($_FILES['upload_id_passport']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file_size = $_FILES['upload_id_passport']['size'][$i];
            
            // Check file size (5MB limit)
            if ($file_size > 5 * 1024 * 1024) {
                // You might want to handle this error
                continue;
            }
            
            // Read file and encode as base64
            $file_content = file_get_contents($tmp);
            
            $_SESSION['form']['step1']['id_passport_files'][] = [
                'filename' => $_FILES['upload_id_passport']['name'][$i],
                'mime'     => $_FILES['upload_id_passport']['type'][$i],
                'size'     => $file_size,
                'data'     => base64_encode($file_content)
            ];
        }
    }
    
    // Save trade license files
    if (!empty($_FILES['upload-trade-license']['name'][0])) {
        $_SESSION['form']['step1']['trade_license_files'] = [];
        
        foreach ($_FILES['upload-trade-license']['tmp_name'] as $i => $tmp) {
            if ($_FILES['upload-trade-license']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file_size = $_FILES['upload-trade-license']['size'][$i];
            
            // Check file size (5MB limit)
            if ($file_size > 5 * 1024 * 1024) {
                continue;
            }
            
            $file_content = file_get_contents($tmp);
            
            $_SESSION['form']['step1']['trade_license_files'][] = [
                'filename' => $_FILES['upload-trade-license']['name'][$i],
                'mime'     => $_FILES['upload-trade-license']['type'][$i],
                'size'     => $file_size,
                'data'     => base64_encode($file_content)
            ];
        }
    }
    
    // For AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Data saved to session']);
        exit;
    }
    
    // Redirect to save script
    header("Location: ../../../controller/steps/kyc/save_kyc.php");
    exit;
}



ob_end_flush(); // End output buffering

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Entity Application</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- إضافة Flatpickr للتقويم -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    /* CSS الأساسي */
    body { 
        font-family: 'Poppins', sans-serif; 
        background: #eef1f6; 
        padding: 60px 40px; 
        font-size: 16px; 
        line-height: 1.6; 
        color: #333; 
        display: flex;
        margin: 0;
    }
    
    /* الخطوات الجانبية */
    .steps-sidebar {
        width: 280px;
        background: #ffffff;
        padding: 40px 30px;
        border-radius: 8px 0 0 8px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        margin-right: 30px;
        flex-shrink: 0;
        margin-top:15px;
    }
    
    .sidebar-title {
        font-size: 24px;
        color: #1a1a1a;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 400;
    }
    
    .sidebar-subtitle {
        font-size: 14px;
        color: #555;
        margin-bottom: 40px;
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
        margin-bottom: 35px;
        position: relative;
        min-height: 40px;
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
        background-color: #0b2e59;
        border-color: #0b2e59;
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
        font-weight: 400;
        color: #1a1a1a;
        margin: 0 0 5px 0;
    }
    
    .step-vertical-status {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
    }
    
    .step-vertical-status.pending {
        color: #d17a0b;
    }
    
    .step-vertical-status.completed {
        color: #0b2e59;
    }
    
    /* محتوى الصفحة الرئيسي */
    .main-content {
        flex-grow: 1;
        background: #ffffff;
        padding: 50px 60px;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        max-width: 1200px;
        min-width: 900px;
        margin-top:15px;
    }
    
    .content-header h1 {
        font-size: 32px;
        color: #1a1a1a;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 12px;
        font-weight: 400;
        margin-top: 0;
        margin-bottom: 8px;
    }
    
    .content-header .subheading {
        font-size: 14px;
        color: #555;
        margin-bottom: 40px;
    }
    
    .step-content-container {
        display: none;
    }
    
    .step-content-container.active {
        display: block;
    }
    
    .form-group { 
        margin-bottom: 20px; 
        position: relative; 
    }
    
    .form-group label { 
        display: block; 
        margin-bottom: 5px; 
        font-weight: 600; 
        font-size: 15px; 
    }
    
    .form-group select, 
    .form-group input[type="text"], 
    .form-group input[type="date"], 
    .form-group input[type="number"], 
    #shareholders-table input, 
    #shareholders-table select, 
    #ubos-table input, 
    #ubos-table select, 
    #management-table input, 
    #management-table select {
        width: 100%; 
        padding: 10px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        font-size: 15px; 
        box-sizing: border-box;
    }
    
    /* تحسين حقول التاريخ مع التقويم */
    .date-input-wrapper {
        position: relative;
    }
    
    .date-input-wrapper input {
        padding-right: 40px !important;
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
    
    .initial-acknowledgement { 
        margin-top: 10px; 
        margin-bottom: 25px; 
        padding: 0; 
        background: inherit; 
        border: none; 
        border-radius: 0; 
    }
    
    .modal { 
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.4); 
    }
    
    .modal-content { 
        background-color: #fefefe; 
        margin: 10% auto; 
        padding: 30px; 
        border: 1px solid #888; 
        width: 80%; 
        max-width: 500px; 
        border-radius: 8px; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.3); 
    }
    
    .modal-content h3 { 
        color: #404040;
        border-bottom: 1px solid #eee; 
        padding-bottom: 10px; 
        margin-top: 0; 
    }
    
    /* Tooltip أنماط جديدة */
    .tooltip-icon { 
        display: inline-block; 
        margin-left: 5px; 
        cursor: pointer; 
        position: relative; 
        vertical-align: middle;
        color: #404040;
        font-weight: 600;
        font-size: 14px;
        transition: color 0.2s ease;
    }
    
    .tooltip-icon:hover {
        color: #404040;
    }
    
    .tooltip-content { 
        visibility: hidden; 
        width: 350px; 
        background-color: #fff;
        color: #333;
        text-align: left; 
        border-radius: 6px; 
        padding: 15px; 
        position: absolute; 
        z-index: 1000; 
        bottom: 125%; 
        left: 50%; 
        margin-left: -175px; 
        opacity: 0; 
        transition: opacity 0.3s; 
        font-size: 13px; 
        line-height: 1.5; 
        font-weight: 400;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid #ddd;
    }
    
    .tooltip-content::after { 
        content: ""; 
        position: absolute; 
        top: 100%; 
        left: 50%; 
        margin-left: -5px; 
        border-width: 5px; 
        border-style: solid; 
        border-color: #fff transparent transparent transparent; 
    }
    
    .tooltip-icon:hover .tooltip-content { 
        visibility: visible; 
        opacity: 1; 
    }
    
    /* Tables */
    #shareholders-table, #ubos-table, #management-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 15px;
        table-layout: fixed;
    }
    
    #shareholders-table thead th, 
    #ubos-table thead th, 
    #management-table thead th { 
        background-color: #f5f5f5; 
        border: 1px solid #ddd; 
        padding: 12px; 
        text-align: left; 
        font-size: 14px; 
        font-weight: 600; 
    }
    
    #shareholders-table tbody td, 
    #ubos-table tbody td, 
    #management-table tbody td { 
        border: 1px solid #ddd; 
        padding: 10px; 
        vertical-align: middle; 
    }
    
    #shareholders-table input, 
    #shareholders-table select, 
    #ubos-table input, 
    #ubos-table select, 
    #management-table input, 
    #management-table select { 
        padding: 8px; 
        border: 1px solid #ddd; 
        width: 100%;
        box-sizing: border-box;
        font-size: 14px;
    }
    
    /* عرض أوسع للجداول */
    #shareholders-table th:nth-child(1), #shareholders-table td:nth-child(1),
    #ubos-table th:nth-child(1), #ubos-table td:nth-child(1),
    #management-table th:nth-child(1), #management-table td:nth-child(1) {
        width: 30%;
    }
    
    #shareholders-table th:nth-child(2), #shareholders-table td:nth-child(2),
    #ubos-table th:nth-child(2), #ubos-table td:nth-child(2) {
        width: 12%;
    }
    
    #management-table th:nth-child(2), #management-table td:nth-child(2) {
        width: 20%;
    }
    
    #shareholders-table th:nth-child(3), #shareholders-table td:nth-child(3),
    #ubos-table th:nth-child(3), #ubos-table td:nth-child(3),
    #management-table th:nth-child(3), #management-table td:nth-child(3) {
        width: 15%;
    }
    
    #shareholders-table th:nth-child(4), #shareholders-table td:nth-child(4),
    #shareholders-table th:nth-child(5), #shareholders-table td:nth-child(5),
    #ubos-table th:nth-child(4), #ubos-table td:nth-child(4),
    #ubos-table th:nth-child(5), #ubos-table td:nth-child(5),
    #management-table th:nth-child(4), #management-table td:nth-child(4),
    #management-table th:nth-child(5), #management-table td:nth-child(5) {
        width: 18%;
    }
    
    #shareholders-table th:nth-child(6), #shareholders-table td:nth-child(6),
    #ubos-table th:nth-child(6), #ubos-table td:nth-child(6),
    #management-table th:nth-child(6), #management-table td:nth-child(6) {
        width: 12%;
    }
    
    #shareholders-table th:nth-child(7), #shareholders-table td:nth-child(7),
    #ubos-table th:nth-child(7), #ubos-table td:nth-child(7),
    #management-table th:nth-child(7), #management-table td:nth-child(7) {
        width: 10%;
    }
    
    /* إخفاء زر الإضافة والإزالة من جدول الإدارة */
    #management-table-fields button {
        display: none !important;
    }
    
    .subtype-container { 
        margin-top: 15px; 
        padding: 15px; 
        background: #f9f9f9; 
        border: 1px solid #e0e0e0; 
        border-radius: 4px;
    }
    
    /* Buttons - جميعها f2f2f2 */
    button {
        background-color: #f2f2f2 !important;
        color: #333 !important;
        padding: 10px 15px !important;
        border: none !important;
        border-radius: 4px !important;
        cursor: pointer !important;
        font-family: 'Poppins', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        border: 1px solid #ddd !important;
    }
    
    button:hover {
        background-color: #e6e6e6 !important;
    }
    
    .remove-btn, .delete-btn {
        background-color: #f2f2f2 !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
    }
    
    .remove-btn:hover, .delete-btn:hover {
        background-color: #e6e6e6 !important;
    }
    
    /* Attachments Styles */
    .attachments-container {
        margin-top: 40px;
        padding: 20px;
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }
    
    .attachments-container h3 {
        font-size: 20px;
        color: #1a1a1a;
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .uploaded-files {
        margin-top: 15px;
        padding: 15px;
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        min-height: 60px;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        background: #f5f5f5;
        border-radius: 4px;
        margin-bottom: 10px;
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
        gap: 10px;
    }
    
    .download-btn, .delete-btn {
        padding: 5px 10px !important;
        font-size: 12px !important;
        border-radius: 3px !important;
        color: #333 !important;
    }
    
    .download-btn {
        background-color: #f2f2f2 !important;
    }
    
    .delete-btn {
        background-color: #f2f2f2 !important;
    }
    
    /* Validation Errors */
    .validation-errors-container {
        margin-top: 30px;
        padding: 20px;
        background: #fdebea;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        color: #721c24;
    }
    
    .validation-errors-container h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #721c24;
        font-size: 16px;
        font-weight: 300 !important;
    }
    
    .error-item {
        margin-bottom: 8px;
        padding-left: 20px;
        position: relative;
        font-size: 14px;
    }
    
    .error-item::before {
        content: "⚠";
        position: absolute;
        left: 0;
    }
    
    .error-item:last-child {
        margin-bottom: 0;
    }
    
    /* تحسين مظهر Flatpickr */
    .flatpickr-calendar {
        font-family: 'Poppins', sans-serif !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        border-radius: 8px !important;
        border: 1px solid #ddd !important;
    }
    
    .flatpickr-day.selected {
        background-color: #0b2e59 !important;
        border-color: #0b2e59 !important;
    }
    
    .flatpickr-day.today {
        border-color: #0b2e59 !important;
    }
    
    .flatpickr-day:hover {
        background-color: #eef1f6 !important;
    }
    
    .key-facts{
        color: #d17a0b;
        font-weight: 600;
    }
    
    /* License edit button */
    .edit-license-btn {
        background-color: #f2f2f2 !important;
        color: #333 !important;
        padding: 10px !important;
        height: 40px !important;
        border: 1px solid #ddd !important;
    }
    
    /* Modal errors */
    .modal-error {
        color: #d9534f;
        font-size: 13px;
        margin-top: 5px;
        display: none;
    }
    
    .modal-error.show {
        display: block;
    }
    
    /* App header */
    .app-header {
        position: absolute;
        top: 20px;
        left: 40px;
        font-family: 'Poppins', sans-serif;
        font-size: 22px;
        font-weight: 600;
        color: #1a1a1a;
        z-index: 1000;
    }
    
    /* NEW STYLES FOR NAME VALIDATION */
    .name-input-container {
        position: relative;
    }
    
    .name-validation-error {
        position: absolute;
        right: -10px;
        top: 50%;
        transform: translate(100%, -50%);
        background-color: #ffebee;
        color: #d32f2f;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 100;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #ffcdd2;
        display: none;
    }
    
    .name-validation-error.show {
        display: block;
    }
    
    .name-validation-error::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 50%;
        transform: translateY(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: transparent #ffebee transparent transparent;
    }
    
    .invalid-name {
        border-color: #f44336 !important;
        background-color: #fff8f8 !important;
    }
    
    .valid-name {
        border-color: #4caf50 !important;
    }
  </style>
</head>
<body>
    <div class="app-header">Muhasba</div>
    
    <!-- الخطوات الجانبية -->
    <div class="steps-sidebar">
        <div class="sidebar-title"><?php if ($userstatus=="new"){echo("New Engagement Application");}elseif($userstatus=="return"){echo("Continue Engagement Application");} ?></div>
        <div class="sidebar-subtitle">
            <?php if($userstatus=="new"){echo("Ensures that each new entity meets onboarding, compliance, and eligibility standards before activation on the platform.");} elseif($userstatus=="return"){echo("Ensuring the entity meets all compliance standards before rolling forward to the next fiscal year.");}?>
        </div>
        
        <ul class="steps-vertical">
            <li class="step-vertical-item">
                <div class="step-vertical-circle active" id="step-circle-1">1</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Know Your Customer (KYC)</div>
                    <div class="step-vertical-status pending" id="step-status-1">Pending</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle" id="step-circle-2">2</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Audit Fee Acknowledgement</div>
                    <div class="step-vertical-status" id="step-status-2">Not Started</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle" id="step-circle-3">3</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Financial Year Details</div>
                    <div class="step-vertical-status" id="step-status-3">Not Started</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle" id="step-circle-4">4</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Tax Status Disclosure</div>
                    <div class="step-vertical-status" id="step-status-4">Not Started</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle" id="step-circle-5">5</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Engagement Letter Acceptance</div>
                    <div class="step-vertical-status" id="step-status-5">Not Started</div>
                </div>
            </li>
        </ul>
    </div>

    <form method="POST" action="../../../controller/steps/kyc/save_kyc.php" enctype="multipart/form-data" id="kyc-form">
    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        <div class="content-header">
            <h1>Know Your Customer (KYC) Details</h1>
            <div class="subheading">
                Complete all required fields to proceed to the next step.
            </div>
        </div>
        
        <!-- الخطوة 1: KYC Details -->
        <div class="step-content-container active" id="step-1-content">
            <div class="initial-acknowledgement">
                <label>
                    <input type="checkbox" id="kyc-ack-checkbox" required name="kyc-checkbox" <?php echo (isset($step1["kyc-checkbox"]) && $step1["kyc-checkbox"] == 'on') ? 'checked' : ''; ?>/>
                    I confirm that I have reviewed the <a href="key-facts.php" class="key-facts">Engagement Key Facts Statement</a>, including the audit terms and conditions and the eligibility requirements for using the Muhasba.com platform, and I agree to them.
                </label>
            </div>

            <div id="kyc-fields" style="display:none;">
                <!-- Business Registration Status -->
                <div class="form-group">
                    <label for="reg-status">
                        Business Registration Status
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                Select the appropriate registration status for your business entity.
                            </div>
                        </span>
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <select id="reg-status" onchange="toggleConditionalFields()" required name="registration-status" style="flex: 1;"> 
                            <option value="">Select</option>
                            <option value="Unlicensed Natural Person(s)">Unlicensed Natural Person(s)</option>
                            <option value="Free Zone Licensed">Free Zone Licensed</option>
                            <option value="Mainland Licensed-Multiple Owners">Mainland Licensed-Multiple Owners</option>
                            <option value="Mainland Licensed-Sole Owner">Mainland Licensed-Sole Owner</option>
                        </select>
                        <button type="button" id="edit-license-btn" class="edit-license-btn" onclick="showLicenseModalForEdit()" style="display: none;">
                            Edit
                        </button>
                    </div>
                </div>

                <!-- Mainland Company Type Subtype -->
                <div id="mainland-subtype-fields" style="display: none;" class="subtype-container">
                    <div class="form-group">
                        <label for="mainland-type">
                            Mainland Company Type
                            <span class="tooltip-icon">
                                +
                                <div class="tooltip-content">
                                    Select the type of mainland company structure.
                                </div>
                            </span>
                        </label>
                        <select id="mainland-type" onchange="handleMainlandTypeChange()" required name="mainland-type">
                            <option value="">Select</option>
                            <option value="Civil Company">Civil Company</option>
                            <option value="Limited Liability Company">Limited Liability Company</option>
                            <option value="General Partnership Company">General Partnership Company</option>
                            <option value="Limited Partnership Company">Limited Partnership Company</option>
                            <option value="Branch of Local Company">Branch of Local Company</option>
                            <option value="Branch of Foreign Company">Branch of Foreign Company</option>
                        </select>
                    </div>
                </div>

                <!-- Entity Name -->
                <div class="form-group">
                    <label for="entity-name">
                        Company / Owner Name
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                <div style="margin-bottom: 8px;">1. For <strong>Mainland Licensed – Sole Owner</strong>: Please enter the full legal name of the owner as per the trade license or official ID.</div>
                                <div style="margin-bottom: 8px;">2. For <strong>Unlicensed Natural Person(s)</strong>: If the activity is owned by one individual, enter the full name of that individual. If owned by multiple individuals from the same family, enter a collective name including the family name, e.g.: <em>Heirs of the late Amin Makhamreh</em>. If owned by multiple unrelated individuals, enter the name of the individual holding the largest ownership or influence, followed by "and partners", e.g.: <em>Saleh Amin Makhamreh and partners</em>.</div>
                                <div>3. For <strong>Licensed Entities (Mainland or Free Zone)</strong>: Please enter the exact legal name as stated on the trade license, without abbreviation or modification.</div>
                            </div>
                        </span>
                    </label>
                    <input type="text" id="entity-name" placeholder="Company Name" name="owner-name" required />
                </div>
                
                <!-- Main Activity -->
                <div class="form-group">
                    <label for="main-activity-input">
                        Main Activity
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content" style="width: 250px; margin-left: -125px;">
                                Enter the main activity that generates most of the income and is primarily managed from the UAE.
                            </div>
                        </span>
                    </label>
                    <input type="text" id="main-activity-input" placeholder="e.g., General Trading" required name="main-activity-input"/>
                </div>

                <!-- Emirate -->
                <div class="form-group">
                    <label for="emirate-select">
                        Emirate
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                Select the emirate where your business is primarily located.
                            </div>
                        </span>
                    </label>
                    <select id="emirate-select" required name="emirate-select">
                        <option value="">Select</option>
                        <option value="Abu Dhabi">Abu Dhabi</option>
                        <option value="Dubai">Dubai</option>
                        <option value="Sharjah">Sharjah</option>
                        <option value="Ajman">Ajman</option>
                        <option value="Umm Al Quwain">Umm Al Quwain</option>
                        <option value="Ras Al Khaimah">Ras Al Khaimah</option>
                        <option value="Fujairah">Fujairah</option>
                    </select>
                </div>
                
                <!-- Address -->
                <div class="form-group">
                    <label for="address-input">
                        Address
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                Enter the complete business address including city, street, building name, and office number.
                            </div>
                        </span>
                    </label>
                    <input type="text" id="address-input" placeholder="City, Street, Building Name, Office Number" name="address" required />
                </div>
                
                <!-- Shareholders Section -->
                <div style="margin-top: 35px; margin-bottom: 10px;">
                    <label style="font-size: 18px; font-weight: 600; color: #1a1a1a;">
                        Shareholders / Proprietors
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content" style="width: 400px; margin-left: -200px;">
                                If a corporate entity holds an ownership interest in the capital, such interest must be allocated to the natural persons who own that entity, in proportion to their ownership percentages in that entity.
                            </div>
                        </span>
                    </label>
                </div>

                <!-- Shareholders Table -->
                <table id="shareholders-table" name="shareholders-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Capital %</th>
                            <th>Nationality</th>
                            <th>EID Number</th>
                            <th>EID Expiry Date</th>
                            <th>
                                PEP 
                                <span class="tooltip-icon" style="top: -2px;">
                                    +
                                    <div class="tooltip-content" style="width: 300px; margin-left: -150px;">
                                        PEP: A person who holds or has held a prominent public function, including close family members and close business associates.
                                    </div>
                                </span>
                            </th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="shareholders-tbody">
                        <!-- سيتم إضافة الصفوف ديناميكياً -->
                    </tbody>
                </table>
                
                <button type="button" id="add-shareholder-btn">
                    + Add Shareholder
                </button>
                
                <!-- UBO Question -->
                <div class="form-group" style="margin-top: 40px;">
                    <label style="font-size: 15px;">
                        Is there any other individual who directly or indirectly owns 25% or more of the capital?
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                Individuals who ultimately own or control the entity through indirect ownership of 25% or more.
                            </div>
                        </span>
                    </label>
                    <select id="ubo-question" name="ubo-question" onchange="toggleUBOFields()" required>
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                
                <!-- UBO Fields -->
                <div id="ubo-fields" style="display: none; margin-top: 20px;">
                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 18px; font-weight: 600; color: #1a1a1a;">
                            Ultimate Beneficial Owners (UBOs)
                            <span class="tooltip-icon">
                                +
                                <div class="tooltip-content">
                                    Individuals who ultimately own or control the entity through indirect ownership of 25% or more.
                                </div>
                            </span>
                        </label>
                    </div>
                    
                    <table id="ubos-table" name="ubos-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Capital %</th>
                                <th>Nationality</th>
                                <th>EID Number</th>
                                <th>EID Expiry Date</th>
                                <th>
                                    PEP 
                                    <span class="tooltip-icon" style="top: -2px;">
                                        +
                                        <div class="tooltip-content" style="width: 300px; margin-left: -150px;">
                                            PEP: A person who holds or has held a prominent public function, including close family members and close business associates.
                                        </div>
                                    </span>
                                </th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ubos-tbody">
                            <!-- سيتم إضافة الصفوف ديناميكياً -->
                        </tbody>
                    </table>
                    
                    <button type="button" id="add-ubo-btn">
                        + Add UBO
                    </button>
                </div>
                
                <!-- Management Control -->
                <div class="form-group" style="margin-top: 40px;">
                    <label for="management-control-select">
                        Who is responsible for management and effective control?
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content">
                                Select the individual who have management authority and effective control over the entity's operations.
                            </div>
                        </span>
                    </label>
                    <select id="management-control-select" onchange="toggleManagementTable()" required name="management-control-select">
                        <!-- سيتم ملؤها ديناميكياً -->
                    </select>
                </div>

                <!-- Management Table -->
                <div id="management-table-fields" style="display: none; margin-top: 20px;">
                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 18px; font-weight: 600; color: #1a1a1a;">
                            Management Details (Not Listed Above)
                            <span class="tooltip-icon">
                                +
                                <div class="tooltip-content">
                                    Add individual who is responsible for management but is not listed as shareholder or UBO.
                                </div>
                            </span>
                        </label>
                    </div>
                    
                    <table id="management-table" name="management-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Nationality</th>
                                <th>EID Number</th>
                                <th>EID Expiry Date</th>
                                <th>
                                    PEP 
                                    <span class="tooltip-icon" style="top: -2px;">
                                        +
                                        <div class="tooltip-content" style="width: 300px; margin-left: -150px;">
                                            PEP: A person who holds or has held a prominent public function, including close family members and close business associates.
                                        </div>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="management-tbody">
                            <!-- سيتم إضافة صف واحد فقط ديناميكياً -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Total Turnover -->
                <div class="form-group" style="margin-top: 40px;">
                    <label for="total-turnover-input">
                        Total Turnover (AED)
                        <span class="tooltip-icon">
                            +
                            <div class="tooltip-content" style="width: 300px; margin-left: -150px;">
                                Turnover refers to the total gross revenue from all activities, including operating and non-operating sources, for the current financial year under review.
                            </div>
                        </span>
                    </label>
                    <input type="text" 
                           id="total-turnover-input" 
                           name="turnover" 
                           placeholder="Enter annual gross turnover in AED" 
                           required 
                           oninput="handleTurnoverChange(event)" 
                           onfocus="handleTurnoverFocus(event)" 
                           onblur="handleTurnoverBlur(event)" />
                </div>

                <!-- Attachments Section -->
                <div class="attachments-container">
                    <h3>Attachments & Declaration</h3>
                    
                    <!-- Emirates ID and Passport Upload -->
                    <div class="form-group">
                        <label for="upload-id-passport">
                            Upload Emirates ID and Passport for all individuals:
                            <span class="tooltip-icon">
                                +
                                <div class="tooltip-content">
                                    Upload clear copies of Emirates ID (front and back) and passport for all individuals listed above.
                                </div>
                            </span>
                        </label>
                        <input type="file" id="upload-id-passport" multiple accept=".pdf,.jpg,.jpeg,.png" name="upload_id_passport[]"/>
                        <div class="uploaded-files" id="id-passport-files">
                            <!-- الملفات المرفوعة ستظهر هنا -->
                        </div>
                    </div>

                    <!-- Trade License Upload -->
                    <div class="form-group" id="trade-license-upload-group" style="display: none;">
                        <label for="upload-trade-license">
                            Upload Trade License:
                            <span class="tooltip-icon">
                                +
                                <div class="tooltip-content">
                                    Upload a clear copy of the current trade license.
                                </div>
                            </span>
                        </label>
                        <input type="file" id="upload-trade-license" multiple accept=".pdf,.jpg,.jpeg,.png" name="upload_trade_license[]" />
                        <div class="uploaded-files" id="trade-license-files">
                            <!-- الملفات المرفوعة ستظهر هنا -->
                        </div>
                    </div>
                    
                    <!-- Documents Certification -->
                    <div style="margin-top: 25px; padding: 15px; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px;">
                        <label>
                            <input type="checkbox" id="documents-certification" required />
                            I hereby certify that all documents provided are true copies of the originals, and that the information within this form is correct and complete to the best of my knowledge.
                        </label>
                    </div>
                </div>
                
                <!-- Validation Errors Container -->
                <div class="validation-errors-container" id="validation-errors" style="display: none;">
                    <h4>Please fix the following issues:</h4>
                    <div id="validation-errors-list">
                        <!-- رسائل الخطأ ستظهر هنا -->
                    </div>
                </div>
            </div>
            
            <!-- زر الانتقال للخطوة التالية -->
            <div style="margin-top: 40px; text-align: right;">
                <button type="submit" id="next-step-btn" onclick="proceedToNextStep()" style="display: none;">
                    Proceed to Next Step
                </button>
            </div>
        </div>
        
        <!-- محتوى الخطوات الأخرى (مخفية حالياً) -->
        <div class="step-content-container" id="step-2-content">
            <h2>Audit Fee Acknowledgement</h2>
            <p>Confirm acceptance of the proposed audit fees and payment terms.</p>
        </div>
        
        <div class="step-content-container" id="step-3-content">
            <h2>Financial Year Details</h2>
            <p>Specify the financial year start and end dates for the audit engagement.</p>
        </div>
        
        <div class="step-content-container" id="step-4-content">
            <h2>Tax Status Disclosure</h2>
            <p>Declare the entity's current tax registration status (VAT/Corporate Tax).</p>
        </div>
        
        <div class="step-content-container" id="step-5-content">
            <h2>Engagement Letter Acceptance</h2>
            <p>Final review and acceptance of the official engagement letter.</p>
        </div>
    </div>
    
    <!-- نموذج بيانات الرخصة -->
    <div id="license-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">License Details Required</h3>
            <p id="modal-description">Please provide your business license information to continue the application.</p>
            
            <!-- إخطارات الأخطاء داخل النافذة -->
            <div id="modal-errors" style="display: none; background: #fdebea; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #f5c6cb;">
                <strong>Please complete the following fields:</strong>
                <ul id="modal-errors-list" style="margin: 5px 0 0 0; padding-left: 20px;">
                </ul>
            </div>
            
            <div class="form-group">
                <label for="license-number">License Number</label>
                <input type="text" id="license-number" name="license-number" required>
                <div class="modal-error" id="license-number-error">License Number is required</div>
            </div>
            <div class="form-group">
                <label for="issue-date">License Issue Date (DD/MM/YYYY)</label>
                <div class="date-input-wrapper">
                    <input type="text" id="issue-date" placeholder="DD/MM/YYYY" required name="issue-date">
                    <span class="calendar-icon" onclick="openCalendar('issue-date')">📅</span>
                </div>
                <div class="modal-error" id="issue-date-error">Issue Date is required and must be in DD/MM/YYYY format</div>
            </div>
            <div class="form-group">
                <label for="expiry-date">License Expiry Date (DD/MM/YYYY)</label>
                <div class="date-input-wrapper">
                    <input type="text" id="expiry-date" placeholder="DD/MM/YYYY" required name="expiry-date">
                    <span class="calendar-icon" onclick="openCalendar('expiry-date')">📅</span>
                </div>
                <div class="modal-error" id="expiry-date-error">Expiry Date is required and must be in DD/MM/YYYY format</div>
            </div>
            <div style="margin-top: 20px;">
                <button onclick="confirmLicenseDetails()" style="margin-right: 10px;">
                    Confirm Details
                </button>
                <button onclick="closeLicenseModal()" style="background-color: #f2f2f2;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </form>
    
    <!-- إضافة مكتبة Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/default.js"></script>
    
    <script>
// ************************************
// ********* وظائف JavaScript *********
// ************************************

// العناصر الرئيسية
const tbodyShareholders = document.getElementById('shareholders-tbody');
const addBtnShareholder = document.getElementById('add-shareholder-btn');
const uboQuestion = document.getElementById('ubo-question');
const uboFields = document.getElementById('ubo-fields');
const tbodyUBOs = document.getElementById('ubos-tbody');
const addBtnUBO = document.getElementById('add-ubo-btn');
const managementSelect = document.getElementById('management-control-select');
const managementFieldsDiv = document.getElementById('management-table-fields');
const tbodyManagement = document.getElementById('management-tbody');
const validationErrors = document.getElementById('validation-errors');
const validationErrorsList = document.getElementById('validation-errors-list');
const totalTurnoverInput = document.getElementById('total-turnover-input');
const tradeLicenseGroup = document.getElementById('trade-license-upload-group');
const mainlandSubtypeFields = document.getElementById('mainland-subtype-fields');
const mainlandTypeSelect = document.getElementById('mainland-type');
const kycCheckbox = document.getElementById('kyc-ack-checkbox');
const kycFields = document.getElementById('kyc-fields');
const nextStepBtn = document.getElementById('next-step-btn');
const idPassportUpload = document.getElementById('upload-id-passport');
const tradeLicenseUpload = document.getElementById('upload-trade-license');
const idPassportFilesContainer = document.getElementById('id-passport-files');
const tradeLicenseFilesContainer = document.getElementById('trade-license-files');
const editLicenseBtn = document.getElementById('edit-license-btn');
const kycForm = document.getElementById('kyc-form');

// عناصر النافذة المنبثقة
const licenseModal = document.getElementById('license-modal');
const licenseNumberInput = document.getElementById('license-number');
const issueDateInput = document.getElementById('issue-date');
const expiryDateInput = document.getElementById('expiry-date');
const modalErrors = document.getElementById('modal-errors');
const modalErrorsList = document.getElementById('modal-errors-list');

// مصفوفات لتخزين الملفات المرفوعة
let uploadedIdPassportFiles = [];
let uploadedTradeLicenseFiles = [];

// متغير لتخزين بيانات النافذة المنبثقة
let licenseData = {
    licenseNumber: '',
    issueDate: '',
    expiryDate: '',
    hasData: false,
    expiryDateValid: true
};

// متغيرات حالة الخطوات
let currentStep = 1;
const totalSteps = 5;

// مثيلات Flatpickr
let issueDatePicker = null;
let expiryDatePicker = null;

// متغيرات لتخزين قيم الـ turnover
let totalTurnoverFormatted = '';
let totalTurnoverRaw = '';
let lastTurnoverCursorPos = 0;
let turnoverRoundedValue = '';

// تهيئة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    createShareholderRow(); // صف أول في جدول الشركاء
    toggleConditionalFields();
    setupFileUploadListeners();
    setupStepNavigation();
    
    // تحديث العناوين الفرعية
    updatePlaceholders();
    
    // إضافة مستمع للتغيرات في جميع الحقول للتحقق التلقائي
    document.querySelectorAll('input, select').forEach(element => {
        element.addEventListener('change', validateAll);
        element.addEventListener('input', validateAll);
    });
    
    // تحديث قائمة الإدارة بعد وقت قصير
    setTimeout(populateManagementSelect, 100);
    
    // إعداد التقويمات
    setupDatePickers();
    
    // إعداد مستمعين لحقول PEP
    setupAllPEPListeners();
    
    // إعداد مستمع لإرسال النموذج
    setupFormSubmission();
    
    // إعداد مستمعين لتحقق الاسم
    setupNameValidationObservers();
    
    // إضافة صف UBO واحد افتراضي إذا كانت الإجابة "نعم"
    setTimeout(() => {
        if (uboQuestion.value === 'Yes') {
            createUBORow();
        }
    }, 200);
    
    // تحميل البيانات المحفوظة من الجلسة
    loadSavedData();
});

// *************** إعداد مستمعين لتحقق الاسم ***************
function setupNameValidationObservers() {
    // مراقبة الجداول للإضافات الجديدة وإضافة مستمعات للتحقق من الاسم
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                // إضافة مستمعات لحقول الاسم الجديدة
                setupNameValidationListeners();
            }
        });
    });
    
    // مراقبة الجداول للإضافات الجديدة
    observer.observe(tbodyShareholders, { childList: true });
    observer.observe(tbodyUBOs, { childList: true });
    observer.observe(tbodyManagement, { childList: true });
    
    // إعداد المستمعات الأولية
    setupNameValidationListeners();
}

function setupNameValidationListeners() {
    // إعداد مستمعات لجميع حقول الاسم
    document.querySelectorAll('input[name^="shareholder_name"], input[name^="ubo_name"], input[name="management_name"]').forEach(input => {
        // إزالة أي مستمعات سابقة لتجنب التكرار
        input.removeEventListener('input', validateNameField);
        input.removeEventListener('blur', validateNameField);
        // إضافة مستمعات جديدة
        input.addEventListener('input', validateNameField);
        input.addEventListener('blur', validateNameField);
        
        // التحقق الأولي
        validateNameField({ target: input });
    });
}

function validateNameField(event) {
    const input = event.target;
    const value = input.value.trim();
    
    // التحقق من أن الحقل محاط بـ container مخصص
    let container = input.parentNode;
    if (!container.classList.contains('name-input-container')) {
        // إنشاء container جديد إذا لم يكن موجوداً
        const newContainer = document.createElement('div');
        newContainer.className = 'name-input-container';
        input.parentNode.insertBefore(newContainer, input);
        newContainer.appendChild(input);
        container = newContainer;
    }
    
    // إزالة رسالة الخطأ القديمة إن وجدت
    const oldError = container.querySelector('.name-validation-error');
    if (oldError) {
        oldError.remove();
    }
    
    // إزالة فئات التنسيق السابقة
    input.classList.remove('invalid-name', 'valid-name');
    
    // إذا كان الحقل فارغاً، لا تظهر رسالة خطأ
    if (value === '') {
        return;
    }
    
    // تقسيم الاسم إلى كلمات (فصل بالمسافات)
    const words = value.split(/\s+/).filter(word => word.length > 0);
    
    // التحقق من وجود 3 كلمات على الأقل
    if (words.length < 3) {
        // إضافة فئة الخطأ
        input.classList.add('invalid-name');
        input.classList.remove('valid-name');
        
        // إنشاء رسالة الخطأ
        const errorDiv = document.createElement('div');
        errorDiv.className = 'name-validation-error show';
        errorDiv.textContent = 'Name must contain at least 3 words';
        
        // إضافة رسالة الخطأ إلى container
        container.appendChild(errorDiv);
        
        return false;
    } else {
        // الاسم صالح
        input.classList.remove('invalid-name');
        input.classList.add('valid-name');
        
        // إزالة أي رسالة خطأ متبقية
        const existingError = container.querySelector('.name-validation-error');
        if (existingError) {
            existingError.remove();
        }
        
        return true;
    }
}

// *************** تحميل البيانات المحفوظة ***************
function loadSavedData() {
    // يمكنك إضافة منطق لتحميل البيانات المحفوظة مسبقًا هنا
    console.log('جاري تحميل البيانات المحفوظة...');
}

// *************** وظيفة لجمع كل بيانات النموذج ***************
function collectAllFormData() {
    console.log('=== جمع بيانات النموذج ===');
    
    // إنشاء كائن FormData من النموذج الأساسي
    const formData = new FormData(kycForm);
    
    // 1. جمع بيانات المساهمين
    console.log('جمع بيانات المساهمين:');
    const shareholderRows = tbodyShareholders.querySelectorAll('tr');
    shareholderRows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && input.name.includes('shareholder_')) {
                formData.append(input.name, input.value);
                console.log(`  مساهم ${index + 1}: ${input.name} = ${input.value}`);
            }
        });
    });
    
    // 2. جمع بيانات UBOs إذا كانت الإجابة "نعم"
    console.log('حالة سؤال UBO:', uboQuestion.value);
    formData.append('ubo-question', uboQuestion.value);
    console.log(`سؤال UBO: ${uboQuestion.value}`);
    
    if (uboQuestion.value === 'Yes') {
        const uboRows = tbodyUBOs.querySelectorAll('tr');
        console.log(`عدد صفوف UBOs: ${uboRows.length}`);
        
        uboRows.forEach((row, index) => {
            const inputs = row.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.name && input.name.includes('ubo_')) {
                    formData.append(input.name, input.value);
                    console.log(`  UBO ${index + 1}: ${input.name} = ${input.value}`);
                }
            });
        });
    } else {
        // إذا كانت الإجابة "لا"، تأكد من عدم إرسال بيانات UBOs
        console.log('إجابة UBO هي "لا"، تم مسح بيانات UBOs');
        // إزالة أي بيانات UBOs محتملة من FormData
        formData.delete('ubo_name[]');
        formData.delete('ubo_capital[]');
        formData.delete('ubo_nationality[]');
        formData.delete('ubo_emiratesId[]');
        formData.delete('ubo_expiryDate[]');
        formData.delete('ubo_pep[]');
    }
    
    // 3. جمع بيانات الإدارة إذا تم اختيار "أخرى"
    console.log('اختيار الإدارة:', managementSelect.value);
    formData.append('management-control-select', managementSelect.value);
    
    if (managementSelect.value === 'Other') {
        const managementRows = tbodyManagement.querySelectorAll('tr');
        managementRows.forEach((row, index) => {
            const inputs = row.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.name && (input.name.includes('management_') || input.name === 'management_pep')) {
                    formData.append(input.name, input.value);
                    console.log(`  الإدارة: ${input.name} = ${input.value}`);
                }
            });
        });
    } else {
        // إذا لم تكن "أخرى"، مسح بيانات الإدارة
        formData.delete('management_name');
        formData.delete('management_position');
        formData.delete('management_nationality');
        formData.delete('management_emiratesId');
        formData.delete('management_expiryDate');
        formData.delete('management_pep');
    }
    
    // 4. إضافة بيانات الرخصة التجارية إذا كانت موجودة
    if (licenseData.hasData) {
        formData.append('license-number', licenseData.licenseNumber);
        formData.append('issue-date', licenseData.issueDate);
        formData.append('expiry-date', licenseData.expiryDate);
        console.log('بيانات الرخصة التجارية مضافة');
    } else {
        // مسح بيانات الرخصة إذا لم تكن موجودة
        formData.delete('license-number');
        formData.delete('issue-date');
        formData.delete('expiry-date');
    }
    
    // 5. التحقق من وجود الملفات في FormData
    console.log('الملفات في FormData:');
    for (let pair of formData.entries()) {
        console.log(`  ${pair[0]} = ${pair[1] instanceof File ? `ملف: ${pair[1].name}` : pair[1]}`);
    }
    
    return formData;
}

// *************** إعداد إرسال النموذج ***************
function setupFormSubmission() {
    kycForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('=== بدء إرسال النموذج ===');
        
        // التحقق من صحة النموذج
        if (!validateAll()) {
            validationErrors.style.display = 'block';
            validationErrors.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        
        // جمع كل بيانات النموذج
        const formData = collectAllFormData();
        
        // إضافة الملفات المرفوعة إلى FormData
        console.log('إضافة الملفات إلى FormData:');
        
        // ملفات الهوية والجواز
        if (idPassportUpload.files.length > 0) {
            for (let i = 0; i < idPassportUpload.files.length; i++) {
                formData.append('upload_id_passport[]', idPassportUpload.files[i]);
                console.log(`  ملف هوية/جواز ${i + 1}: ${idPassportUpload.files[i].name}`);
            }
        }
        
        // ملفات الرخصة التجارية
        if (tradeLicenseUpload && tradeLicenseUpload.files.length > 0) {
            for (let i = 0; i < tradeLicenseUpload.files.length; i++) {
                formData.append('upload_trade_license[]', tradeLicenseUpload.files[i]);
                console.log(`  ملف الرخصة التجارية ${i + 1}: ${tradeLicenseUpload.files[i].name}`);
            }
        }
        
        // إضافة إشارة أن البيانات موجودة في الجلسة
        formData.append('data_in_session', 'true');
        
        // إظهار مؤشر التحميل
        const submitBtn = kycForm.querySelector('button[type="submit"]') || nextStepBtn;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = 'جاري الحفظ...';
        submitBtn.disabled = true;
        
        try {
            console.log('إرسال البيانات إلى الخادم...');
            const response = await fetch('../../../controller/steps/kyc/save_kyc.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('استجابة الخادم:', result);
            
            if (result.success) {
                alert('تم حفظ البيانات بنجاح!');
                // توجيه المستخدم إلى الصفحة التالية
                window.location.href = result.redirect_url || 'Audit-Fee.php';
            } else {
                alert('فشل في حفظ البيانات: ' + (result.message || 'خطأ غير معروف'));
                if (result.debug) {
                    console.log('تفاصيل الخطأ:', result.debug);
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('خطأ أثناء الحفظ:', error);
            alert('حدث خطأ أثناء حفظ البيانات. الرجاء المحاولة مرة أخرى.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

// *************** إعداد مستمعين لحقول PEP ***************
function setupAllPEPListeners() {
    // إضافة مستمعات للحقول الحالية
    updatePEPListeners();
    
    // إضافة مستمع للجداول الجديدة
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                updatePEPListeners();
            }
        });
    });
    
    // مراقبة الجداول للإضافات الجديدة
    observer.observe(tbodyShareholders, { childList: true });
    observer.observe(tbodyUBOs, { childList: true });
    observer.observe(tbodyManagement, { childList: true });
}

function updatePEPListeners() {
    // تحديث جميع حقول PEP
    document.querySelectorAll('select[name^="shareholder_pep"], select[name^="ubo_pep"], select[name="management_pep"]').forEach(select => {
        // إزالة أي مستمعات سابقة لتجنب التكرار
        select.removeEventListener('change', handlePEPChange);
        // إضافة مستمع جديد
        select.addEventListener('change', handlePEPChange);
    });
}

function handlePEPChange(e) {
    const select = e.target;
    
    // تحديث التنسيق البصري
    if (select.value === "" || select.value === "Select") {
        select.classList.add('error-state');
        select.classList.remove('valid');
    } else {
        select.classList.remove('error-state');
        select.classList.add('valid');
    }
    
    // تحديث رسائل التحقق
    clearSpecificValidationMessages('PEP selection is required');
    
    // التحقق الشامل
    validateAll();
}

// *************** مسح رسائل تحقق محددة ***************
function clearSpecificValidationMessages(textContains) {
    const errorItems = validationErrorsList.querySelectorAll('.error-item');
    
    errorItems.forEach(item => {
        if (item.textContent.includes(textContains)) {
            // التحقق من أن جميع حقول PEP قد تم ملؤها
            let allPEPFilled = true;
            
            // التحقق من جدول Shareholders
            Array.from(tbodyShareholders.rows).forEach(row => {
                const pepSelect = row.querySelector('select[name^="shareholder_pep"]');
                if (pepSelect && (!pepSelect.value || pepSelect.value === "")) {
                    allPEPFilled = false;
                }
            });
            
            // التحقق من جدول UBOs
            if (uboFields.style.display === 'block' && uboQuestion.value === 'Yes') {
                Array.from(tbodyUBOs.rows).forEach(row => {
                    const pepSelect = row.querySelector('select[name^="ubo_pep"]');
                    if (pepSelect && (!pepSelect.value || pepSelectSelect.value === "")) {
                        allPEPFilled = false;
                    }
                });
            }
            
            // التحقق من جدول الإدارة
            if (managementFieldsDiv.style.display === 'block') {
                const managementRows = tbodyManagement.rows;
                if (managementRows.length > 0) {
                    const row = managementRows[0];
                    const pepSelect = row.querySelector('select[name="management_pep"]');
                    if (pepSelect && (!pepSelect.value || pepSelect.value === "")) {
                        allPEPFilled = false;
                    }
                }
            }
            
            // إذا تم ملء جميع حقول PEP، قم بإزالة رسالة الخطأ
            if (allPEPFilled) {
                item.remove();
            }
        }
    });
    
    // إخفاء حاوية الأخطاء إذا لم يتبق أخطاء
    const remainingErrors = validationErrorsList.querySelectorAll('.error-item');
    if (remainingErrors.length === 0) {
        validationErrors.style.display = 'none';
    }
}

// *************** وظائف التنسيق للـ Turnover ***************
function handleTurnoverChange(e) {
    const input = e.target;
    const inputValue = input.value;
    lastTurnoverCursorPos = input.selectionStart;
    
    // If empty, reset everything
    if (inputValue === '') {
        totalTurnoverFormatted = '';
        totalTurnoverRaw = '';
        turnoverRoundedValue = '';
        input.value = '';
        validateAll();
        return;
    }
    
    // Allow numbers and one decimal point
    let cleanedValue = '';
    let hasDecimal = false;
    
    for (let i = 0; i < inputValue.length; i++) {
        const char = inputValue[i];
        
        if (char >= '0' && char <= '9') {
            cleanedValue += char;
        } else if (char === '.' && !hasDecimal) {
            cleanedValue += char;
            hasDecimal = true;
        }
        // Ignore other characters
    }
    
    // Store the raw value
    totalTurnoverRaw = cleanedValue;
    
    // Format for display (add commas to integer part)
    if (cleanedValue) {
        // Split into integer and decimal parts
        const parts = cleanedValue.split('.');
        let integerPart = parts[0];
        let decimalPart = parts.length > 1 ? '.' + parts[1] : '';
        
        // Add commas to integer part
        integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        
        // Build formatted value
        totalTurnoverFormatted = integerPart + decimalPart;
        input.value = totalTurnoverFormatted;
    } else {
        totalTurnoverFormatted = '';
        input.value = '';
    }
    
    // Calculate new cursor position
    setTimeout(() => {
        if (totalTurnoverInput) {
            let newCursorPos = lastTurnoverCursorPos;
            
            if (totalTurnoverFormatted.length > inputValue.length) {
                newCursorPos = lastTurnoverCursorPos + (totalTurnoverFormatted.length - inputValue.length);
            } else if (totalTurnoverFormatted.length < inputValue.length) {
                newCursorPos = Math.max(0, lastTurnoverCursorPos - (inputValue.length - totalTurnoverFormatted.length));
            }
            
            newCursorPos = Math.min(newCursorPos, totalTurnoverFormatted.length);
            newCursorPos = Math.max(newCursorPos, 0);
            
            totalTurnoverInput.setSelectionRange(newCursorPos, newCursorPos);
        }
    }, 0);
    
    validateAll();
}

function handleTurnoverBlur(e) {
    if (!totalTurnoverRaw || totalTurnoverRaw === '') {
        totalTurnoverInput.value = '';
        turnoverRoundedValue = '';
        validateAll();
        return;
    }
    
    // Parse the raw value
    const numericValue = parseFloat(totalTurnoverRaw) || 0;
    
    // Apply Math.round() - .50 becomes 1, .49 becomes 0
    const roundedValue = Math.round(numericValue);
    turnoverRoundedValue = roundedValue.toString();
    
    if (roundedValue !== 0) {
        // Format with commas for thousands
        totalTurnoverFormatted = roundedValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        totalTurnoverInput.value = totalTurnoverFormatted;
    } else {
        totalTurnoverFormatted = '';
        totalTurnoverInput.value = '';
        turnoverRoundedValue = '';
    }
    
    // Update hidden input
    updateTurnoverHiddenInput();
    validateAll();
}

function handleTurnoverFocus(e) {
    e.target.select();
}

// تحديث الحقل المخفي لتخزين القيمة غير المنسقة
function updateTurnoverHiddenInput() {
    // Remove any existing hidden input
    const existingHiddenInput = document.getElementById('turnover-hidden');
    if (existingHiddenInput) {
        existingHiddenInput.remove();
    }
    
    // Create new hidden input if we have a rounded value
    if (turnoverRoundedValue && turnoverRoundedValue !== '' && turnoverRoundedValue !== '0') {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'turnover-hidden';
        hiddenInput.name = 'turnover';
        hiddenInput.value = turnoverRoundedValue;
        totalTurnoverInput.parentNode.appendChild(hiddenInput);
        
        // Remove name from visible input to prevent conflict
        totalTurnoverInput.removeAttribute('name');
    } else {
        // If no value, restore name to visible input
        totalTurnoverInput.name = 'turnover';
    }
}

// *************** إعداد التقويمات ***************
function setupDatePickers() {
    // إعداد خيارات Flatpickr الأساسية
    const flatpickrOptions = {
        dateFormat: "d/m/Y",
        allowInput: true,
        locale: "default",
        disableMobile: true,
        onChange: function(selectedDates, dateStr, instance) {
            // عند تغيير التاريخ من التقويم، تحديث الحقل يدويًا
            instance.input.value = dateStr;
            // تحقق من صحة التاريخ
            validateDateInput(instance.input);
            // إذا كان حقل في النافذة المنبثقة، قم بالتحقق
            if (instance.input.id === 'issue-date' || instance.input.id === 'expiry-date') {
                validateModalDateField(instance.input);
            }
            // تحقق شامل
            validateAll();
        }
    };
    
    // إنشاء مثيلات Flatpickr للنافذة المنبثقة
    issueDatePicker = flatpickr("#issue-date", flatpickrOptions);
    expiryDatePicker = flatpickr("#expiry-date", flatpickrOptions);
}

function openCalendar(inputId) {
    if (inputId === 'issue-date' && issueDatePicker) {
        issueDatePicker.open();
    } else if (inputId === 'expiry-date' && expiryDatePicker) {
        expiryDatePicker.open();
    }
}

// تنسيق حقل التاريخ لـ DD/MM/YYYY
function formatDateInput(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 2 && value.length <= 4) {
        value = value.substring(0, 2) + '/' + value.substring(2);
    } else if (value.length > 4) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4) + '/' + value.substring(4, 8);
    }
    
    input.value = value;
    
    // التحقق من صحة التاريخ عند اكتماله
    if (value.length === 10) {
        validateDateInput(input);
    }
}

// التحقق من صحة تاريخ DD/MM/YYYY
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
    
    // التحقق من صحة التاريخ
    if (month < 1 || month > 12) return false;
    if (day < 1 || day > 31) return false;
    
    // التحقق من الأشهر التي لها 30 يومًا
    if ([4, 6, 9, 11].includes(month) && day > 30) return false;
    
    // التحقق من فبراير والسنة الكبيسة
    if (month === 2) {
        const isLeapYear = (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
        if (day > 29 || (day === 29 && !isLeapYear)) return false;
    }
    
    return true;
}

// تحويل تاريخ DD/MM/YYYY إلى كائن Date
function parseDDMMYYYY(dateStr) {
    if (!dateStr || dateStr.length !== 10) return null;
    
    const parts = dateStr.split('/');
    if (parts.length !== 3) return null;
    
    const day = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const year = parseInt(parts[2], 10);
    
    return new Date(year, month, day);
}

// التحقق من حقل التاريخ في النافذة المنبثقة
function validateModalDateField(input) {
    const errorElement = document.getElementById(input.id + '-error');
    
    if (!input.value) {
        errorElement.textContent = input.id === 'issue-date' ? 
            'Issue Date is required' : 'Expiry Date is required';
        errorElement.classList.add('show');
        return false;
    } else if (!validateDateInput(input)) {
        errorElement.textContent = 'Date must be in DD/MM/YYYY format';
        errorElement.classList.add('show');
        return false;
    } else {
        errorElement.classList.remove('show');
        return true;
    }
}

// التحقق من تاريخ انتهاء الرخصة (إذا كان اليوم أو قبله)
function isLicenseExpired(expiryDateStr) {
    if (!expiryDateStr || !validateDateInput({value: expiryDateStr})) {
        return false;
    }
    
    const expiryDate = parseDDMMYYYY(expiryDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    return expiryDate <= today;
}

// *************** تحديث عناوين الحقول ***************
function updatePlaceholders() {
    // تحديث عنوان Total Turnover
    totalTurnoverInput.placeholder = "Enter annual gross turnover in AED";
    
    // تهيئة عرض الملفات
    updateFileDisplay(uploadedIdPassportFiles, idPassportFilesContainer);
    updateFileDisplay(uploadedTradeLicenseFiles, tradeLicenseFilesContainer, true);
}

// *************** إدارة الملفات المرفوعة ***************
function setupFileUploadListeners() {
    // مستمع لتحميل الهويات والجوازات
    idPassportUpload.addEventListener('change', function(e) {
        handleFileUpload(e.target.files, uploadedIdPassportFiles, idPassportFilesContainer);
    });
    
    // مستمع لتحميل الرخصة التجارية
    if (tradeLicenseUpload) {
        tradeLicenseUpload.addEventListener('change', function(e) {
            handleFileUpload(e.target.files, uploadedTradeLicenseFiles, tradeLicenseFilesContainer, true);
        });
    }
}

async function handleFileUpload(files, fileArray, container, singleFile = false) {
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // إذا كان ملف واحد فقط مسموح (كالتجارة)
        if (singleFile && fileArray.length > 0) {
            alert('Only one trade license file is allowed. Please remove the existing file first.');
            continue;
        }
        
        // تحويل الملف إلى base64
        const base64 = await fileToBase64(file);
        
        fileArray.push({
            name: file.name,
            size: file.size,
            type: file.type,
            base64: base64
        });
    }
    
    // تحديث عرض الملفات
    updateFileDisplay(fileArray, container, singleFile);
    validateAll();
}

function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = () => resolve(reader.result);
        reader.onerror = error => reject(error);
    });
}

function updateFileDisplay(fileArray, container, singleFile = false) {
    container.innerHTML = '';
    
    if (fileArray.length === 0) {
        container.innerHTML = '<div style="color: #888; font-style: italic;">No files uploaded yet</div>';
        return;
    }
    
    fileArray.forEach((fileData, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        
        const sizeInMB = (fileData.size / (1024 * 1024)).toFixed(2);
        
        fileItem.innerHTML = `
            <div class="file-name">${fileData.name} (${sizeInMB} MB)</div>
            <div class="file-actions">
                <button type="button" class="download-btn" onclick="downloadFileFromBase64(${index}, ${singleFile})">Download</button>
                <button type="button" class="delete-btn" onclick="deleteFile(${index}, ${singleFile})">Delete</button>
            </div>
        `;
        
        container.appendChild(fileItem);
    });
}

function downloadFileFromBase64(index, isTradeLicense = false) {
    const fileArray = isTradeLicense ? uploadedTradeLicenseFiles : uploadedIdPassportFiles;
    
    if (index >= 0 && index < fileArray.length) {
        const fileData = fileArray[index];
        const link = document.createElement('a');
        link.href = fileData.base64;
        link.download = fileData.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function deleteFile(index, isTradeLicense = false) {
    const fileArray = isTradeLicense ? uploadedTradeLicenseFiles : uploadedIdPassportFiles;
    const container = isTradeLicense ? tradeLicenseFilesContainer : idPassportFilesContainer;
    const singleFile = isTradeLicense;
    
    if (index >= 0 && index < fileArray.length) {
        fileArray.splice(index, 1);
        updateFileDisplay(fileArray, container, singleFile);
        validateAll();
    }
}

// *************** إدارة الخطوات ***************
function setupStepNavigation() {
    // مستمع لـ KYC checkbox
    kycCheckbox.addEventListener('change', function() {
        kycFields.style.display = this.checked ? 'block' : 'none';
        if (this.checked) {
            togglePlaceholder(); 
            populateManagementSelect();
            validateAll(); 
        } else {
            hideNextStepButton();
        }
    });
}

function proceedToNextStep() {
    // تحديث الحقل المخفي قبل الإرسال
    updateTurnoverHiddenInput();
    
    document.getElementById('kyc-form').submit();

    // إخفاء محتوى الخطوة الحالية
}

function showNextStepButton() {
    nextStepBtn.style.display = 'inline-block';
}

function hideNextStepButton() {
    nextStepBtn.style.display = 'none';
}

// *************** وظائف إظهار/إخفاء الحقول ***************
function toggleConditionalFields() {
    const regStatus = document.getElementById('reg-status').value;
    
    togglePlaceholder(regStatus);

    // 1. إظهار/إخفاء حقل تحميل الرخصة التجارية
    if (regStatus === 'Mainland Licensed-Sole Owner' || regStatus === 'Unlicensed Natural Person(s)' || regStatus === '') {
        tradeLicenseGroup.style.display = 'none';
    } else {
        tradeLicenseGroup.style.display = 'block';
    }
    
    // 2. ظهور القائمة الفرعية Mainland Company Type
    if (regStatus === 'Mainland Licensed-Multiple Owners') {
        mainlandSubtypeFields.style.display = 'block';
    } else {
        mainlandSubtypeFields.style.display = 'none';
        mainlandTypeSelect.value = '';
    }
    
    // 3. عرض/إخفاء زر التعديل
    if (regStatus === 'Free Zone Licensed' || 
        (regStatus === 'Mainland Licensed-Multiple Owners' && mainlandTypeSelect.value && mainlandTypeSelect.value !== '')) {
        editLicenseBtn.style.display = 'inline-block';
        
        // إذا لم تكن هناك بيانات للرخصة، عرض النافذة المنبثقة
        if (!licenseData.hasData) {
            showLicenseModal(regStatus === 'Free Zone Licensed' ? 'Free Zone License' : 'Mainland License - ' + mainlandTypeSelect.value);
        }
    } else {
        editLicenseBtn.style.display = 'none';
    }
    
    validateAll();
}

function handleMainlandTypeChange() {
    const mainlandType = mainlandTypeSelect.value;
    
    if (mainlandType && mainlandType !== 'Select') {
        // عرض زر التعديل
        editLicenseBtn.style.display = 'inline-block';
        
        // إذا لم تكن هناك بيانات للرخصة، عرض النافذة المنبثقة
        if (!licenseData.hasData) {
            showLicenseModal('Mainland License - ' + mainlandType);
        }
    } else {
        editLicenseBtn.style.display = 'none';
    }
    validateAll();
}

function togglePlaceholder(regStatus) {
    const status = regStatus || document.getElementById('reg-status').value;
    const entityNameInput = document.getElementById('entity-name');
    
    if (status === 'Mainland Licensed-Sole Owner' || status === 'Unlicensed Natural Person(s)') {
        entityNameInput.placeholder = 'Owner Name';
    } else {
        entityNameInput.placeholder = 'Company Name';
    }
}

// *************** وظائف النافذة المنبثقة للرخصة ***************
function showLicenseModal(licenseType) {
    const modalTitle = document.getElementById('modal-title');
    const modalDescription = document.getElementById('modal-description');
    
    if (licenseType.includes('Mainland')) {
        modalTitle.textContent = 'Mainland License Details';
        modalDescription.textContent = 'Please provide your mainland business license information to continue the application.';
    } else if (licenseType.includes('Free Zone')) {
        modalTitle.textContent = 'Free Zone License Details';
        modalDescription.textContent = 'Please provide your free zone business license information to continue the application.';
    } else {
        modalTitle.textContent = 'License Details Required';
        modalDescription.textContent = 'Please provide your business license information to continue the application.';
    }
    
    // إعادة تعيين البيانات إذا كانت موجودة
    if (licenseData.hasData) {
        licenseNumberInput.value = licenseData.licenseNumber;
        issueDateInput.value = licenseData.issueDate;
        expiryDateInput.value = licenseData.expiryDate;
        
        // تحديث التقويمات
        if (issueDatePicker) issueDatePicker.setDate(licenseData.issueDate, true);
        if (expiryDatePicker) expiryDatePicker.setDate(licenseData.expiryDate, true);
    } else {
        licenseNumberInput.value = '';
        issueDateInput.value = '';
        expiryDateInput.value = '';
        
        // تحديث التقويمات
        if (issueDatePicker) issueDatePicker.clear();
        if (expiryDatePicker) expiryDatePicker.clear();
    }
    
    // إخفاء رسائل الأخطاء
    hideModalErrors();
    
    licenseModal.style.display = 'block';
}

function showLicenseModalForEdit() {
    const regStatus = document.getElementById('reg-status').value;
    let licenseType = '';
    
    if (regStatus === 'Free Zone Licensed') {
        licenseType = 'Free Zone License';
    } else if (regStatus === 'Mainland Licensed-Multiple Owners') {
        const mainlandType = mainlandTypeSelect.value;
        licenseType = 'Mainland License - ' + (mainlandType || 'Multiple Owners');
    }
    
    showLicenseModal(licenseType);
}

function closeLicenseModal() {
    licenseModal.style.display = 'none';
    hideModalErrors();
}

function confirmLicenseDetails() {
    // التحقق من صحة البيانات
    const licenseNumber = licenseNumberInput.value.trim();
    const issueDate = issueDateInput.value.trim();
    const expiryDate = expiryDateInput.value.trim();
    
    let modalErrorsArray = [];
    
    // التحقق من الحقول المطلوبة
    if (!licenseNumber) {
        modalErrorsArray.push("License Number is required");
        document.getElementById('license-number-error').classList.add('show');
    } else {
        document.getElementById('license-number-error').classList.remove('show');
    }
    
    // التحقق من تاريخ الإصدار
    const issueDateValid = validateModalDateField(issueDateInput);
    if (!issueDateValid) {
        modalErrorsArray.push("Issue Date is required and must be in DD/MM/YYYY format");
    }
    
    // التحقق من تاريخ الانتهاء
    const expiryDateValid = validateModalDateField(expiryDateInput);
    if (!expiryDateValid) {
        modalErrorsArray.push("Expiry Date is required and must be in DD/MM/YYYY format");
    }
    
    // التحقق من أن تاريخ الانتهاء بعد تاريخ الإصدار
    if (issueDate && expiryDate && validateDateInput({value: issueDate}) && validateDateInput({value: expiryDate})) {
        const issueDateObj = parseDDMMYYYY(issueDate);
        const expiryDateObj = parseDDMMYYYY(expiryDate);
        
        if (expiryDateObj <= issueDateObj) {
            modalErrorsArray.push("Expiry Date must be after Issue Date");
            document.getElementById('expiry-date-error').textContent = 'Expiry Date must be after Issue Date';
            document.getElementById('expiry-date-error').classList.add('show');
        }
    }
    
    // التحقق من تاريخ انتهاء الرخصة (إذا كان اليوم أو قبله)
    if (expiryDate && validateDateInput({value: expiryDate})) {
        if (isLicenseExpired(expiryDate)) {
            licenseData.expiryDateValid = false;
        } else {
            licenseData.expiryDateValid = true;
        }
    }
    
    // إذا كانت هناك أخطاء، عرضها
    if (modalErrorsArray.length > 0) {
        showModalErrors(modalErrorsArray);
        return;
    }
    
    // حفظ البيانات
    licenseData.licenseNumber = licenseNumber;
    licenseData.issueDate = issueDate;
    licenseData.expiryDate = expiryDate;
    licenseData.hasData = true;
    
    licenseModal.style.display = 'none';
    hideModalErrors();
    
    validateAll();
}

function showModalErrors(errors) {
    modalErrorsList.innerHTML = '';
    errors.forEach(error => {
        const li = document.createElement('li');
        li.textContent = error;
        modalErrorsList.appendChild(li);
    });
    modalErrors.style.display = 'block';
}

function hideModalErrors() {
    modalErrors.style.display = 'none';
    modalErrorsList.innerHTML = '';
    
    // إخفاء جميع رسائل الأخطاء الفردية
    document.querySelectorAll('.modal-error').forEach(error => {
        error.classList.remove('show');
    });
}

// إغلاق النافذة عند الضغط خارجها
window.onclick = function(event) {
    if (event.target === licenseModal) {
        closeLicenseModal();
        
        // إذا تم إغلاق النافذة بدون إدخال بيانات، إضافة خطأ إلى التحقق
        const regStatus = document.getElementById('reg-status').value;
        if ((regStatus === 'Free Zone Licensed' || 
             (regStatus === 'Mainland Licensed-Multiple Owners' && document.getElementById('mainland-type').value)) && 
            !licenseData.hasData) {
            validateAll();
        }
    }
};

// *************** وظائف جداول البيانات ***************
function createShareholderRow() {
    const row = tbodyShareholders.insertRow();
    
    row.innerHTML = `
        <td>
            <div class="name-input-container">
                <input type="text" placeholder="Full Name" name="shareholder_name[]" required oninput="validateAll(); updateManagementSelectOnChange(); validateNameField(event)">
            </div>
        </td>
        <td><input type="number" placeholder="e.g., 25" name="shareholder_capital[]" min="0" max="100" required oninput="validateAll()"></td>
        <td><input type="text" placeholder="Nationality" name="shareholder_nationality[]" required></td>
        <td><input type="text" placeholder="15-digit EID" name="shareholder_emiratesId[]" maxlength="15" pattern="[0-9]{15}" required oninput="validateAll()"></td>
        <td>
            <div class="date-input-wrapper">
                <input type="text" placeholder="DD/MM/YYYY" name="shareholder_expiryDate[]" required oninput="validateAll(); formatDateInput(this)" pattern="\\d{2}/\\d{2}/\\d{4}">
                <span class="calendar-icon" onclick="openTableCalendar(this)">📅</span>
            </div>
        </td>
        <td>
            <select name="shareholder_pep[]" class="pep-select" required>
                <option value="">Select</option>
                <option value="No">No</option>
                <option value="Yes">Yes</option>
            </select>
        </td>
        <td><button type="button" onclick="removeShareholderRow(this)" class="remove-btn">Remove</button></td>
    `;
    
    // إعداد التقويم لحقل التاريخ
    setupTableDatePicker(row.querySelector('input[name="shareholder_expiryDate[]"]'));
    
    // إضافة مستمع لحقل PEP
    const pepSelect = row.querySelector('select[name="shareholder_pep[]"]');
    pepSelect.addEventListener('change', handlePEPChange);
    
    // التحقق من الاسم
    const nameInput = row.querySelector('input[name="shareholder_name[]"]');
    setTimeout(() => validateNameField({ target: nameInput }), 100);
    
    validateAll();
    setTimeout(populateManagementSelect, 50);
}

// إعداد تقويم لحقول التاريخ في الجداول
function setupTableDatePicker(inputElement) {
    flatpickr(inputElement, {
        dateFormat: "d/m/Y",
        allowInput: true,
        disableMobile: true,
        onChange: function(selectedDates, dateStr, instance) {
            instance.input.value = dateStr;
            validateAll();
        }
    });
}

// فتح تقويم الجداول
function openTableCalendar(iconElement) {
    const input = iconElement.parentNode.querySelector('input');
    if (input._flatpickr) {
        input._flatpickr.open();
    }
}

function removeShareholderRow(button) {
    const row = button.parentNode.parentNode;
    row.remove();
    updateManagementSelectOnChange(); 
    validateAll();
}

function toggleUBOFields() {
    if (uboQuestion.value === 'Yes') {
        uboFields.style.display = 'block';
        if (tbodyUBOs.rows.length === 0) {
            createUBORow(); 
        }
    } else {
        uboFields.style.display = 'none';
        // إزالة جميع صفوف UBOs عند اختيار No
        while (tbodyUBOs.rows.length > 0) {
            tbodyUBOs.deleteRow(0);
        }
    }
    updateManagementSelectOnChange();
    validateAll();
}

function createUBORow() {
    const row = tbodyUBOs.insertRow();
    
    row.innerHTML = `
        <td>
            <div class="name-input-container">
                <input type="text" placeholder="Full Name" name="ubo_name[]" required oninput="validateAll(); updateManagementSelectOnChange(); validateNameField(event)">
            </div>
        </td>
        <td><input type="number" placeholder="e.g., 25" name="ubo_capital[]" min="25" max="100" required oninput="validateAll()"></td>
        <td><input type="text" placeholder="Nationality" name="ubo_nationality[]" required></td>
        <td><input type="text" placeholder="15-digit EID" name="ubo_emiratesId[]" maxlength="15" pattern="[0-9]{15}" required oninput="validateAll()"></td>
        <td>
            <div class="date-input-wrapper">
                <input type="text" placeholder="DD/MM/YYYY" name="ubo_expiryDate[]" required oninput="validateAll(); formatDateInput(this)" pattern="\\d{2}/\\d{2}/\\d{4}">
                <span class="calendar-icon" onclick="openTableCalendar(this)">📅</span>
            </div>
        </td>
        <td>
            <select name="ubo_pep[]" class="pep-select" required>
                <option value="">Select</option>
                <option value="No">No</option>
                <option value="Yes">Yes</option>
            </select>
        </td>
        <td><button type="button" onclick="removeUBORow(this)" class="remove-btn">Remove</button></td>
    `;
    
    // إعداد التقويم لحقل التاريخ
    setupTableDatePicker(row.querySelector('input[name="ubo_expiryDate[]"]'));
    
    // إضافة مستمع لحقل PEP
    const pepSelect = row.querySelector('select[name="ubo_pep[]"]');
    pepSelect.addEventListener('change', handlePEPChange);
    
    // التحقق من الاسم
    const nameInput = row.querySelector('input[name="ubo_name[]"]');
    setTimeout(() => validateNameField({ target: nameInput }), 100);
    
    setTimeout(populateManagementSelect, 50);
    validateAll();
}

function removeUBORow(button) {
    const row = button.parentNode.parentNode;
    row.remove();
    updateManagementSelectOnChange();
    validateAll();
}

// *************** تحديث قائمة الإدارة تلقائياً ***************
function updateManagementSelectOnChange() {
    setTimeout(populateManagementSelect, 100);
}

function populateManagementSelect() {
    let options = [];
    
    // جمع أسماء جميع الأشخاص من جدول Shareholders
    Array.from(tbodyShareholders.rows).forEach(row => {
        const nameInput = row.querySelector('input[name="shareholder_name[]"]');
        if (nameInput && nameInput.value.trim() !== '') {
            options.push({
                value: nameInput.value,
                text: nameInput.value
            });
        }
    });

    // جمع أسماء جميع الأشخاص من جدول UBOs
    if (uboFields.style.display === 'block' && uboQuestion.value === 'Yes') {
        Array.from(tbodyUBOs.rows).forEach(row => {
            const nameInput = row.querySelector('input[name="ubo_name[]"]');
            if (nameInput && nameInput.value.trim() !== '') {
                // التحقق من عدم تكرار الاسم
                const exists = options.some(opt => opt.value === nameInput.value);
                if (!exists) {
                    options.push({
                        value: nameInput.value,
                        text: nameInput.value
                    });
                }
            }
        });
    }

    let html = '';
    
    // إذا كان هناك أسماء، نعرضها كخيارات
    if (options.length > 0) {
        html = '<option value="">Select One</option>';
        
        // إضافة الخيارات الموجودة
        options.forEach(option => {
            html += `<option value="${option.value}">${option.value}</option>`;
        });
        
        // إضافة خيار "Other"
        html += '<option value="Other">Other (not listed above)</option>';
    } else {
        // إذا لم يكن هناك أسماء، نعرض فقط خيار Select One
        html = '<option value="">Select One</option>';
    }
    
    managementSelect.innerHTML = html;
    
    // تحديث القائمة عند كل تغيير
    if (managementSelect.innerHTML === '') {
        managementSelect.innerHTML = '<option value="">Select One</option>';
    }
    
    toggleManagementTable();
}

function toggleManagementTable() {
    if (managementSelect.value === 'Other') {
        managementFieldsDiv.style.display = 'block';
        if (tbodyManagement.rows.length === 0) {
            createManagementRow(); 
        }
    } else {
        managementFieldsDiv.style.display = 'none';
        // إزالة أي صف في جدول الإدارة عند اختيار شخص مدرج
        while (tbodyManagement.rows.length > 0) {
            tbodyManagement.deleteRow(0);
        }
    }
    validateAll();
}

function createManagementRow() {
    // التحقق من عدم وجود صف مسبقاً
    while (tbodyManagement.rows.length > 0) {
        tbodyManagement.deleteRow(0);
    }
    
    const row = tbodyManagement.insertRow();
    
    row.innerHTML = `
        <td>
            <div class="name-input-container">
                <input type="text" placeholder="Full Name" name="management_name" required oninput="validateNameField(event)">
            </div>
        </td>
        <td><input type="text" placeholder="Position" name="management_position" required></td>
        <td><input type="text" placeholder="Nationality" name="management_nationality" required></td>
        <td><input type="text" placeholder="15-digit EID" name="management_emiratesId" maxlength="15" pattern="[0-9]{15}" required oninput="validateAll()"></td>
        <td>
            <div class="date-input-wrapper">
                <input type="text" placeholder="DD/MM/YYYY" name="management_expiryDate" required oninput="validateAll(); formatDateInput(this)" pattern="\\d{2}/\\d{2}/\\d{4}">
                <span class="calendar-icon" onclick="openTableCalendar(this)">📅</span>
            </div>
        </td>
        <td>
            <select name="management_pep" class="pep-select" required>
                <option value="">Select</option>
                <option value="No">No</option>
                <option value="Yes">Yes</option>
            </select>
        </td>
    `;
    
    // إعداد التقويم لحقل التاريخ
    setupTableDatePicker(row.querySelector('input[name="management_expiryDate"]'));
    
    // إضافة مستمع لحقل PEP
    const pepSelect = row.querySelector('select[name="management_pep"]');
    pepSelect.addEventListener('change', handlePEPChange);
    
    // التحقق من الاسم
    const nameInput = row.querySelector('input[name="management_name"]');
    setTimeout(() => validateNameField({ target: nameInput }), 100);
    
    validateAll();
}

// *************** دالة التحقق الشاملة ***************
function validateAll() {
    let errors = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // التحقق من الموافقة على KYC
    if (!kycCheckbox.checked) {
        validationErrorsList.innerHTML = '<div class="error-item">Please acknowledge the KYC terms to continue</div>';
        validationErrors.style.display = 'block';
        hideNextStepButton();
        return false;
    }

    // التحقق من بيانات النافذة المنبثقة للرخصة (إذا كانت مطلوبة)
    const regStatus = document.getElementById('reg-status').value;
    if ((regStatus === 'Free Zone Licensed' || 
         (regStatus === 'Mainland Licensed-Multiple Owners' && document.getElementById('mainland-type').value)) && 
        !licenseData.hasData) {
        errors.push("License details are required. Please complete the license information in the modal window.");
    }
    
    // التحقق من تاريخ انتهاء الرخصة (إذا كانت البيانات موجودة)
    if (licenseData.hasData && licenseData.expiryDate) {
        if (isLicenseExpired(licenseData.expiryDate)) {
            errors.push("License Expiry date: Apology, you are not eligible to proceed because the Trade License expired (or expires today). Please renew the Trade License.");
        }
    }

    // 1. التحقق من الحقول الأساسية المطلوبة
    const requiredFields = [
        { id: 'reg-status', name: 'Business Registration Status' },
        { id: 'entity-name', name: 'Company / Owner Name' },
        { id: 'main-activity-input', name: 'Main Activity' },
        { id: 'emirate-select', name: 'Emirate' },
        { id: 'address-input', name: 'Address' },
        { id: 'total-turnover-input', name: 'Total Turnover' },
        { id: 'ubo-question', name: 'UBO Question' },
        { id: 'management-control-select', name: 'Management Control' }
    ];

    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (element && (!element.value || element.value === '')) {
            errors.push(`${field.name} is required`);
        }
    });

    // التحقق من Company Structure إذا تم اختيار Mainland Licensed-Multiple Owners
    if (regStatus === 'Mainland Licensed-Multiple Owners') {
        if (!mainlandTypeSelect.value || mainlandTypeSelect.value === '') {
            errors.push("Please select the Company Structure for Mainland Licensed-Multiple Owners");
        }
    }

    // 2. التحقق من Shareholders
    const shareholderRows = tbodyShareholders.rows;
    let totalCapitalShareholders = 0;
    
    if (shareholderRows.length === 0) {
        errors.push("Please add at least one Shareholder or Owner (Shareholders Table)");
    } else {
        for (let i = 0; i < shareholderRows.length; i++) {
            const row = shareholderRows[i];
            
            // التحقق من الحقول المطلوبة في الصف
            const nameInput = row.querySelector('input[name="shareholder_name[]"]');
            const capitalInput = row.querySelector('input[name="shareholder_capital[]"]');
            const nationalityInput = row.querySelector('input[name="shareholder_nationality[]"]');
            const eidInput = row.querySelector('input[name="shareholder_emiratesId[]"]');
            const expiryDateInput = row.querySelector('input[name="shareholder_expiryDate[]"]');
            const pepSelect = row.querySelector('select[name="shareholder_pep[]"]');
            
            // التحقق من الاسم (3 كلمات على الأقل)
            if (!nameInput || !nameInput.value || nameInput.value.trim() === '') {
                errors.push(`Shareholder ${i+1}: Name is required`);
            } else {
                // التحقق من أن الاسم يحتوي على 3 كلمات على الأقل
                const words = nameInput.value.trim().split(/\s+/).filter(word => word.length > 0);
                if (words.length < 3) {
                    errors.push(`Shareholder ${i+1}: Name must contain at least 3 words`);
                }
            }
            
            if (!capitalInput || !capitalInput.value) {
                errors.push(`Shareholder ${i+1}: Capital % is required`);
            }
            
            if (!nationalityInput || !nationalityInput.value || nationalityInput.value.trim() === '') {
                errors.push(`Shareholder ${i+1}: Nationality is required`);
            }
            
            if (!eidInput || !eidInput.value || eidInput.value.trim() === '') {
                errors.push(`Shareholder ${i+1}: EID Number is required`);
            }
            
            if (!expiryDateInput || !expiryDateInput.value || expiryDateInput.value.trim() === '') {
                errors.push(`Shareholder ${i+1}: EID Expiry Date is required`);
            }
            
            // التحقق المحسن لحقل PEP
            if (!pepSelect || !pepSelect.value || pepSelect.value.trim() === "" || pepSelect.value === "Select") {
                errors.push(`Shareholder ${i+1}: PEP selection is required`);
            }
            
            totalCapitalShareholders += parseFloat(capitalInput ? capitalInput.value : 0) || 0;
            
            if (eidInput && eidInput.value && eidInput.value.length !== 15) {
                errors.push(`Shareholder ${i+1}: EID must be exactly 15 digits`);
            }
            
            if (expiryDateInput && expiryDateInput.value) {
                // التحقق من تنسيق التاريخ
                if (!validateDateInput(expiryDateInput)) {
                    errors.push(`Shareholder ${i+1}: EID Expiry Date must be in DD/MM/YYYY format`);
                } else {
                    const expiryDate = parseDDMMYYYY(expiryDateInput.value);
                    expiryDate.setHours(0, 0, 0, 0);
                    if (expiryDate <= today) {
                        errors.push(`Shareholder ${i+1}: Apology, you are not eligible to proceed because the EID is expired (or expires today). Please renew the ID.`);
                    }
                }
            }
        }
        
        if (shareholderRows.length > 0 && Math.round(totalCapitalShareholders) !== 100) {
            errors.push(`Shareholders Total Capital must equal 100%. Current total is ${totalCapitalShareholders.toFixed(2)}%`);
        }
    }

    // 3. التحقق من UBOs
    if (uboQuestion.value === 'Yes') {
        const uboRows = tbodyUBOs.rows;
        
        if (uboRows.length === 0) {
            errors.push("Please add at least one Ultimate Beneficial Owner (UBOs Table)");
        } else {
            for (let i = 0; i < uboRows.length; i++) {
                const row = uboRows[i];
                
                // التحقق من الحقول المطلوبة في الصف
                const nameInput = row.querySelector('input[name="ubo_name[]"]');
                const capitalInput = row.querySelector('input[name="ubo_capital[]"]');
                const nationalityInput = row.querySelector('input[name="ubo_nationality[]"]');
                const eidInput = row.querySelector('input[name="ubo_emiratesId[]"]');
                const expiryDateInput = row.querySelector('input[name="ubo_expiryDate[]"]');
                const pepSelect = row.querySelector('select[name="ubo_pep[]"]');
                
                // التحقق من الاسم (3 كلمات على الأقل)
                if (!nameInput || !nameInput.value || nameInput.value.trim() === '') {
                    errors.push(`UBO ${i+1}: Name is required`);
                } else {
                    // التحقق من أن الاسم يحتوي على 3 كلمات على الأقل
                    const words = nameInput.value.trim().split(/\s+/).filter(word => word.length > 0);
                    if (words.length < 3) {
                        errors.push(`UBO ${i+1}: Name must contain at least 3 words`);
                    }
                }
                
                if (!capitalInput || !capitalInput.value) {
                    errors.push(`UBO ${i+1}: Capital % is required`);
                }
                
                if (!nationalityInput || !nationalityInput.value || nationalityInput.value.trim() === '') {
                    errors.push(`UBO ${i+1}: Nationality is required`);
                }
                
                if (!eidInput || !eidInput.value || eidInput.value.trim() === '') {
                    errors.push(`UBO ${i+1}: EID Number is required`);
                }
                
                if (!expiryDateInput || !expiryDateInput.value || expiryDateInput.value.trim() === '') {
                    errors.push(`UBO ${i+1}: EID Expiry Date is required`);
                }
                
                // التحقق المحسن لحقل PEP
                if (!pepSelect || !pepSelect.value || pepSelect.value.trim() === "" || pepSelect.value === "Select") {
                    errors.push(`UBO ${i+1}: PEP selection is required`);
                }
                
                if (capitalInput && parseFloat(capitalInput.value) < 25) {
                    errors.push(`UBO ${i+1}: Capital percentage must be 25% or more`);
                }
                
                if (eidInput && eidInput.value && eidInput.value.length !== 15) {
                    errors.push(`UBO ${i+1}: EID must be exactly 15 digits`);
                }
                
                if (expiryDateInput && expiryDateInput.value) {
                    // التحقق من تنسيق التاريخ
                    if (!validateDateInput(expiryDateInput)) {
                        errors.push(`UBO ${i+1}: EID Expiry Date must be in DD/MM/YYYY format`);
                    } else {
                        const expiryDate = parseDDMMYYYY(expiryDateInput.value);
                        expiryDate.setHours(0, 0, 0, 0);
                        if (expiryDate <= today) {
                            errors.push(`UBO ${i+1}: Apology, you are not eligible to proceed because the EID is expired (or expires today). Please renew the ID.`);
                        }
                    }
                }
            }
        }
    } else {
        // If UBO question is "No", ensure UBO table is empty
        if (tbodyUBOs.rows.length > 0) {
            // This shouldn't happen, but just in case
            errors.push("UBO table should be empty when UBO question is 'No'");
        }
    }

    // 4. التحقق من Management
    if (!managementSelect.value || managementSelect.value === '') {
        errors.push("Please select who is responsible for management and effective control");
    } else if (managementSelect.value === 'Other') {
        const managementRows = tbodyManagement.rows;
        
        if (managementRows.length === 0) {
            errors.push("Please add management details when selecting 'Other'");
        } else {
            // التحقق من صف واحد فقط
            const row = managementRows[0];
            
            // التحقق من الحقول المطلوبة في الصف
            const nameInput = row.querySelector('input[name="management_name"]');
            const positionInput = row.querySelector('input[name="management_position"]');
            const nationalityInput = row.querySelector('input[name="management_nationality"]');
            const eidInput = row.querySelector('input[name="management_emiratesId"]');
            const expiryDateInput = row.querySelector('input[name="management_expiryDate"]');
            const pepSelect = row.querySelector('select[name="management_pep"]');
            
            // التحقق من الاسم (3 كلمات على الأقل)
            if (!nameInput || !nameInput.value || nameInput.value.trim() === '') {
                errors.push(`Management: Name is required`);
            } else {
                // التحقق من أن الاسم يحتوي على 3 كلمات على الأقل
                const words = nameInput.value.trim().split(/\s+/).filter(word => word.length > 0);
                if (words.length < 3) {
                    errors.push(`Management: Name must contain at least 3 words`);
                }
            }
            
            if (!positionInput || !positionInput.value || positionInput.value.trim() === '') {
                errors.push(`Management: Position is required`);
            }
            
            if (!nationalityInput || !nationalityInput.value || nationalityInput.value.trim() === '') {
                errors.push(`Management: Nationality is required`);
            }
            
            if (!eidInput || !eidInput.value || eidInput.value.trim() === '') {
                errors.push(`Management: EID Number is required`);
            }
            
            if (!expiryDateInput || !expiryDateInput.value || expiryDateInput.value.trim() === '') {
                errors.push(`Management: EID Expiry Date is required`);
            }
            
            // التحقق المحسن لحقل PEP
            if (!pepSelect || !pepSelect.value || pepSelect.value.trim() === "" || pepSelect.value === "Select") {
                errors.push(`Management: PEP selection is required`);
            }
            
            if (eidInput && eidInput.value && eidInput.value.length !== 15) {
                errors.push(`Management: EID must be exactly 15 digits`);
            }
            
            if (expiryDateInput && expiryDateInput.value) {
                // التحقق من تنسيق التاريخ
                if (!validateDateInput(expiryDateInput)) {
                    errors.push(`Management: EID Expiry Date must be in DD/MM/YYYY format`);
                } else {
                    const expiryDate = parseDDMMYYYY(expiryDateInput.value);
                    expiryDate.setHours(0, 0, 0, 0);
                    if (expiryDate <= today) {
                        errors.push(`Management: Apology, you are not eligible to proceed because the EID is expired (or expires today). Please renew the ID.`);
                    }
                }
            }
        }
    }

    // 5. التحقق من Total Turnover - استخدام القيمة بعد التقريب
    const turnoverValue = parseFloat(turnoverRoundedValue) || 0;
    const MAX_TURNOVER = 50000000;
    if (totalTurnoverInput.value && turnoverValue > MAX_TURNOVER) {
         errors.push(`Muhasba.com does not onboard entities with annual gross turnover above AED 50 million.`);
    }

    // 6. التحقق من المرفقات
    if (uploadedIdPassportFiles.length === 0) {
        errors.push("Please upload Emirates ID and Passport for all individuals");
    }
    
    if (tradeLicenseGroup.style.display === 'block' && uploadedTradeLicenseFiles.length === 0) {
        errors.push("Please upload Trade License");
    }

    // 7. التحقق من شهادة المستندات
    const documentsCertification = document.getElementById('documents-certification');
    if (!documentsCertification.checked) {
        errors.push("Please certify that all documents provided are true copies of the originals");
    }

    // عرض أو إخفاء رسائل الخطأ
    if (errors.length > 0) {
        validationErrors.style.display = 'block';
        validationErrorsList.innerHTML = errors.map(error => 
            `<div class="error-item">${error}</div>`
        ).join('');
        hideNextStepButton();
        return false;
    } else {
        validationErrors.style.display = 'none';
        validationErrorsList.innerHTML = '';
        showNextStepButton();
        return true;
    }
}

// ربط أزرار الإضافة
addBtnShareholder.addEventListener('click', () => { 
    createShareholderRow(); 
});

addBtnUBO.addEventListener('click', () => { 
    createUBORow(); 
});
</script>
    <script src="../../../controller/steps/kyc/save-kyc.js"></script>
</body>
</html>