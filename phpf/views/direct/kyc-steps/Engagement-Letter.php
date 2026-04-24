<?php
ob_start(); // Start output buffering to prevent header conflicts
session_start();
if (empty($_SESSION['form']['step4'])){
    header("Location: TAX STATUS.php");
}
require_once "../../widgets/chat_widget.php";
require_once "../../../helpers/DateFormatter.php";

displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);




$audit_fee= $_SESSION['form']['audit_fee'];
$step1 = $_SESSION['form']['step1'] ?? [];
$step0 = $_SESSION['form']['step0'] ?? [];
$step3 = $_SESSION['form']['step3'] ?? [];

if (!empty($step3['current-end-date'])){
$current_end_date=$step3['current-end-date'];
}
else {
    $current_end_date=$step3['first-end-date'];

}

// $current_end_date=$step3['first-end-date'];


// $step0=$_SESSION['form']['step0'];

// echo "Raw value: " . $current_end_date . "<br>";
// echo "Type: " . gettype($current_end_date) . "<br>";
// var_dump($current_end_date);
//////////////////////////////////////////


// Usage examples:



/////////////////////////////////


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
///////////////////////////////////

if (isset($step0['new'])) {
    $new = $step0['new'];
} else {
    $new = '';
}

if (isset($step0['return'])) {
    $return = $step0['return'];
} else {
    $return = '';
}

$regitration = $step1['registration-status'] ?? '';

///////////////////////////////////////////////////////
// Calculate engagement number
$userId = $_SESSION['user_id'] ?? 0;
$entityId = $_SESSION['current_entity_id'] ?? 0;

// D is static, 256 is userId+255
$staticPart = 'D';
$userIdPart = $userId + 255; // or get from database entity ID if available

// Get middle part from license or default to 'P'
$middlePart = 'P'; // Default
if(isset($step1['license-number']) && !empty($step1['license-number'])) {
    $license = trim($step1['license-number']);
    if(!empty($license)) {
        $middlePart = substr($license, 0, 1); // First character of license
    }
}

// Get year from current end date
$year = DateFormatter::extractYear($current_end_date);

// Generate engagement number
$engagementNumber = $staticPart . $userIdPart . '-' . $middlePart . '-' . $year.' Provisional';

// Check if this number already exists in database (to avoid duplicates)
require_once '../../../config/db.php';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM entity_step5 WHERE engagement_number LIKE ?");
$stmt->execute([$engagementNumber . '%']);
$count = $stmt->fetchColumn();

// If exists, add suffix
if ($count > 0) {
    $counter = 1;
    $originalNumber = $engagementNumber;
    
    do {
        $engagementNumber = $originalNumber . '-' . $counter;
        $stmt->execute([$engagementNumber . '%']);
        $count = $stmt->fetchColumn();
        $counter++;
    } while ($count > 0);
}

// Store in session for later use
$_SESSION['form']['step5']['engagement_number'] = $engagementNumber;

///////////////////////////////////////////////////////

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['form']['step5'] = $_POST;
    header("Location: ../../../controller/steps/save_tax.php");
    exit;
}

ob_end_flush(); // End output buffering

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Entity Application - Engagement Letter Acceptance</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    /* ===== COMMON STYLES ===== */
body { 
    font-family: 'Poppins', sans-serif; 
    background: #eef1f6; 
    padding: 40px; /* Changed to match KYC form */
    font-size: 16px; 
    line-height: 1.6; 
    color: #333; 
    display: flex;
    margin: 0;
    height: 100vh; /* Full viewport height */
    overflow: hidden; /* Hide overall scroll */
    box-sizing: border-box;
}

/* Steps Sidebar - Fixed with scrolling */
.steps-sidebar {
    width: 280px;
    background: #ffffff;
    padding: 30px; /* Reduced to match KYC form */
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
    margin-bottom: 25px; /* Reduced to match KYC form */
    padding-bottom: 12px; /* Reduced to match KYC form */
    border-bottom: 1px solid #e0e0e0;
}

.sidebar-subtitle {
    font-size: 14px;
    color: #555;
    margin-bottom: 30px; /* Reduced to match KYC form */
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
    margin-bottom: 28px; /* Reduced to match KYC form */
    position: relative;
    min-height: 36px; /* Reduced to match KYC form */
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
    margin: 0 0 4px 0; /* Reduced to match KYC form */
    line-height: 1.3; /* Tighter line height */
}

.step-vertical-status {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    line-height: 1.2; /* Tighter line height */
}

.step-vertical-status.completed {
    color: #dd5656;
}

.step-vertical-status.pending {
    color: #d17a0b;
}

.step-vertical-status.not-started {
    color: #777;
}

/* Main Content - Scrollable */
.main-content {
    flex-grow: 1;
    background: #ffffff;
    padding: 40px 50px; /* Reduced to match KYC form */
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    min-width: 900px;
    max-height: calc(100vh - 80px); /* Limit height to viewport */
    overflow-y: auto; /* Make main content scrollable */
    position: relative;
    margin-top:30px;
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
    padding-bottom: 10px; /* Reduced to match KYC form */
    margin-top: 0;
    margin-bottom: 6px; /* Reduced to match KYC form */
    font-weight: 400;
}

.content-header .subheading {
    font-size: 14px;
    color: #555;
    margin-bottom: 30px; /* Reduced to match KYC form */
    line-height: 1.5;
}

/* Engagement Letter Content Styles */
.engagement-letter-intro {
    padding: 0;
    margin-bottom: 25px; /* Reduced margin */
}

.engagement-letter-intro p {
    margin: 0 0 12px 0; /* Reduced margin */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
    color: #333;
}

.letter-preview-container {
    padding: 0;
    margin-bottom: 25px; /* Reduced margin */
}

.letter-preview-title {
    font-size: 18px;
    color: #333;
    margin-bottom: 15px; /* Reduced margin */
}

.view-letter-btn {
    background-color: #dd5656 !important;
    color: white !important;
    border: 1px solid #dd5656 !important;
    padding: 12px 25px; /* Reduced padding */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 15px; /* Reduced font size */
    transition: background-color 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.view-letter-btn:hover {
    background-color: #c04c4c !important;
}

.view-letter-btn:disabled {
    background-color: #e0e0e0;
    color: #999;
    cursor: not-allowed;
    border-color: #d0d0d0;
}

.confirmation-section {
    margin-top: 25px; /* Reduced margin */
    padding-top: 20px; /* Reduced padding */
    border-top: 1px solid #e0e0e0;
}

.confirmation-title {
    font-size: 20px;
    color: #1a1a1a;
    margin-bottom: 20px; /* Reduced margin */
}

.auto-confirmation-message {
    padding: 0;
    margin-bottom: 25px; /* Reduced margin */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
}

.application-status {
    margin-top: 20px; /* Reduced margin */
    padding: 0;
}

.application-status h4 {
    font-size: 18px;
    color: #1a1a1a;
    margin-bottom: 12px; /* Reduced margin */
}

.status-placeholder {
    min-height: 80px; /* Reduced min-height */
    border: 1px dashed #ddd;
    border-radius: 4px;
    padding: 15px; /* Reduced padding */
    color: #999;
    font-style: italic;
    line-height: 1.5; /* Tighter line height */
}

.completion-message {
    color: #333;
    padding: 0;
    margin-top: 30px; /* Reduced margin */
    display: none;
}

.completion-message h4 {
    margin-top: 0;
    margin-bottom: 12px; /* Reduced margin */
    font-size: 18px;
    line-height: 1.3; /* Tighter line height */
}

.completion-message p {
    margin: 0 0 8px 0; /* Reduced margin */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
}

.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px; /* Reduced margin */
    padding-top: 25px; /* Reduced padding */
    border-top: 1px solid #e0e0e0;
    width: 100%;
}

button {
    background-color: #f2f2f2;
    color: #333;
    padding: 10px 20px; /* Reduced padding */
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
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

button.complete-btn {
    background-color: #dd5656 !important;
    color: white !important;
    border: 1px solid #dd5656 !important;
}

button.complete-btn:hover:not(:disabled) {
    background-color: #c04c4c !important;
}

button.complete-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.download-section {
    text-align: left;
    margin-top: 25px; /* Reduced margin */
    padding: 0;
}

.download-btn {
    background-color: #f2f2f2;
    color: #333;
    border: 1px solid #ddd;
    padding: 10px 30px; /* Reduced padding */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
    transition: background-color 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.download-btn:hover {
    background-color: #e6e6e6;
}

.download-btn:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    opacity: 0.5;
}

/* ===== FULL SCREEN MODAL - NO SCROLLING ===== */
.fullscreen-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: white;
    z-index: 1000;
    overflow: auto;
}

.modal-header {
    background-color: #ffffff;
    color: #333;
    padding: 15px 30px; /* Reduced padding */
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px; /* Reduced font size */
}

.close-modal {
    background: none;
    border: none;
    color: #333;
    font-size: 28px; /* Reduced font size */
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* LETTER CONTAINER - NO SCROLLING */
.letter-container {
    width: 100%;
    display: flex;
    flex-direction: column;
    min-height: auto;
    overflow: visible;
}

@media (min-width: 1024px) {
    .letter-container {
        flex-direction: row;
    }
}

/* COLUMNS - NO SCROLLING, TAKE NATURAL WIDTH */
.english-column, .arabic-column {
    padding: 30px; /* Reduced padding */
    overflow: visible; /* Changed from auto to visible */
    box-sizing: border-box;
    width: 100%;
}

@media (min-width: 1024px) {
    .english-column, .arabic-column {
        width: 50%;
        max-height: none; /* Removed max-height */
        overflow: visible; /* No scrolling */
    }
    
    .english-column {
        border-right: 1px solid #e0e0e0;
    }
}

.english-column {
    text-align: left;
}

.arabic-column {
    text-align: right;
    direction: rtl;
    font-family: 'Cairo', sans-serif;
}

.letter-section {
    margin-bottom: 25px; /* Reduced margin */
    page-break-inside: avoid;
}

.letter-section h4 {
    color: #333;
    margin-bottom: 12px; /* Reduced margin */
    font-size: 18px; /* Reduced font size */
    border-bottom: 1px solid #eee;
    padding-bottom: 6px; /* Reduced padding */
    line-height: 1.3; /* Tighter line height */
}

.letter-section p {
    margin: 0 0 12px 0; /* Reduced margin */
    line-height: 1.5; /* Tighter line height */
    font-size: 14px; /* Reduced font size */
}

.letter-section table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px; /* Reduced margin */
    table-layout: fixed;
}

.letter-section table, .letter-section th, .letter-section td {
    border: 1px solid #ddd;
}

.letter-section th, .letter-section td {
    padding: 8px; /* Reduced padding */
    text-align: left;
    word-wrap: break-word;
    font-size: 13px; /* Reduced font size */
    line-height: 1.4; /* Tighter line height */
}

.letter-section th {
    background-color: #f5f5f5;
}

.arabic-column .letter-section h4 {
    font-size: 16px !important; /* Reduced font size */
    font-family: 'Cairo', sans-serif;
    line-height: 1.3; /* Tighter line height */
}

.arabic-column .letter-section p {
    font-size: 14px !important; /* Reduced font size */
    font-family: 'Cairo', sans-serif;
    line-height: 1.8;
}

.arabic-column .letter-section table {
    direction: rtl;
}

.arabic-column .letter-section th, 
.arabic-column .letter-section td {
    text-align: right;
}

/* Signature Section */
.signature-section {
    padding: 30px; /* Reduced padding */
    border-top: 2px solid #e0e0e0;
    margin-top: 30px; /* Reduced margin */
    width: 100%;
    box-sizing: border-box;
}

.signature-title {
    font-size: 20px; /* Reduced font size */
    margin-bottom: 20px; /* Reduced margin */
    color: #333;
    text-align: center;
}

.signature-box {
    border: 2px dashed #ccc;
    padding: 30px; /* Reduced padding */
    border-radius: 8px;
    margin-bottom: 25px; /* Reduced margin */
    min-height: 120px; /* Reduced min-height */
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px; /* Reduced font size */
    color: #777;
    background-color: #f9f9f9;
    box-sizing: border-box;
    text-align: center;
    line-height: 1.4; /* Tighter line height */
}

.signature-box.signed {
    border: 2px solid #4CAF50;
    background-color: #f8fff8;
}

.signature-box.completed {
    border: 2px solid #dd5656;
    background-color: #fff8f8;
}

.signature-actions {
    display: flex;
    flex-direction: column;
    gap: 12px; /* Reduced gap */
    margin-bottom: 25px; /* Reduced margin */
    width: 100%;
}

@media (min-width: 768px) {
    .signature-actions {
        flex-direction: row;
        justify-content: center;
        gap: 15px; /* Reduced gap */
    }
}

.signature-actions.hidden {
    display: none;
}

.sign-btn {
    background-color: #f2f2f2;
    color: #333;
    border: 1px solid #ddd;
    padding: 10px 25px; /* Reduced padding */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
    width: 100%;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .sign-btn {
        width: auto;
        min-width: 150px;
    }
}

.sign-btn:hover {
    background-color: #e6e6e6;
}

.sign-btn:disabled {
    background-color: #e0e0e0;
    color: #999;
    cursor: not-allowed;
    border-color: #d0d0d0;
}

.clear-sign-btn {
    background-color: #f2f2f2;
    color: #333;
    border: 1px solid #ddd;
}

.clear-sign-btn:hover {
    background-color: #e6e6e6;
}

.modal-footer {
    padding: 15px 25px; /* Reduced padding */
    border-top: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    gap: 15px; /* Reduced gap */
    position: sticky;
    bottom: 0;
    background-color: white;
    width: 100%;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .modal-footer {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.footer-note {
    font-size: 14px; /* Reduced font size */
    color: #666;
    font-style: italic;
    text-align: center;
    line-height: 1.5;
}

@media (min-width: 768px) {
    .footer-note {
        text-align: left;
    }
}

.legal-notice {
    font-size: 12px; /* Reduced font size */
    color: #666;
    font-style: italic;
    margin-top: 8px; /* Reduced margin */
    text-align: center;
    padding-top: 8px; /* Reduced padding */
    border-top: 1px solid #eee;
    line-height: 1.4; /* Tighter line height */
}

.download-notice {
    font-size: 12px; /* Reduced font size */
    color: #666;
    font-style: italic;
    margin-top: 8px; /* Reduced margin */
    text-align: center;
    padding: 8px; /* Reduced padding */
    background-color: #f9f9f9;
    border-radius: 4px;
    border: 1px solid #eee;
    line-height: 1.4; /* Tighter line height */
}

.completed-notice {
    font-size: 14px; /* Reduced font size */
    color: #dd5656;
    text-align: center;
    margin-top: 15px; /* Reduced margin */
    padding: 10px; /* Reduced padding */
    background-color: #fff8f8;
    border-radius: 4px;
    border: 1px solid #ffdddd;
    line-height: 1.4; /* Tighter line height */
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .english-column, .arabic-column {
        padding: 20px;
    }
    
    .letter-section h4 {
        font-size: 16px;
    }
    
    .letter-section p {
        font-size: 13px;
    }
    
    .arabic-column .letter-section h4 {
        font-size: 14px !important;
    }
    
    .arabic-column .letter-section p {
        font-size: 12px !important;
    }
    
    .signature-section {
        padding: 20px;
    }
    
    .signature-box {
        padding: 20px;
        min-height: 100px;
        font-size: 16px;
    }
    
    .modal-header {
        padding: 12px 20px;
    }
    
    .modal-header h2 {
        font-size: 20px;
    }
}

/* Tablet adjustments */
@media (min-width: 769px) and (max-width: 1023px) {
    .letter-container {
        flex-direction: column;
    }
    
    .english-column, .arabic-column {
        width: 100%;
    }
    
    .english-column {
        border-right: none;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .arabic-column {
        border-top: 1px solid #e0e0e0;
    }
}

 .app-header {
    position: fixed;
    top: 5px;
    left: 40px;
    font-weight:600;
    font-family: 'Poppins', sans-serif;
    font-size: 22px;
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
                <div class="step-vertical-circle completed">3</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Financial Year Details</div>
                    <div class="step-vertical-status completed">COMPLETED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle completed">4</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Tax Status Disclosure</div>
                    <div class="step-vertical-status completed">COMPLETED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle active">5</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Engagement Letter Acceptance</div>
                    <div class="step-vertical-status pending">PENDING</div>
                </div>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="content-header">
            <h1>Engagement Letter Acceptance</h1>
            <div class="subheading">
                Final step: Review and sign the engagement letter to complete your application.
            </div>
        </div>
        
        <div class="engagement-letter-intro">
            <p><strong>Please find attached the Engagement Letter outlining the scope, terms, and conditions of the engagement.</strong></p>
            <p>You are requested to review and confirm your acceptance in order to proceed.</p>
        </div>
        
        <div class="letter-preview-container">
            <button class="view-letter-btn" id="view-letter-btn" onclick="openEngagementLetter()">
                 View and Sign Engagement Letter
            </button>
        </div>
        
        <div class="confirmation-section">
            <div class="confirmation-title">Acceptance Confirmation</div>
            
            <div class="auto-confirmation-message">
                <p><strong>By electronically signing the Engagement Letter, you automatically confirm that:</strong></p>
                <p>1. You have read and understood the Engagement Letter in its entirety.</p>
                <p>2. You agree to be bound by all terms and conditions outlined in the Engagement Letter.</p>
                <p>3. You accept the scope of work, timeline, and fee structure as specified.</p>
                <p>4. Your digital signature is legally binding and represents your full acceptance.</p>
            </div>
        </div>
        
      
        
        <div class="completion-message" id="completion-message">
            <h4>Thank you for completing the required onboarding steps on Muhasba.com.</h4>
            <p>Our audit team will commence the internal review of your submitted information within 24–48 hours to ensure accuracy, completeness, and compliance.</p>
            <p>If any clarifications or additional documents are required during the review process, you will be notified through the platform and by email.</p>
            
            <div class="application-status">
                <h4>Your Application Status</h4>
                <div class="status-placeholder">
                    <?php echo("compelete") ?>
                    <!-- This space is reserved for application status updates -->
                </div>
            </div>
        </div>
        
        <div class="navigation-buttons">
            <button type="button" onclick="goToPreviousStep()">
                ← Previous Step
            </button>
            <button type="button" id="complete-btn" class="complete-btn" onclick="completeOnboarding()" disabled>
                Complete Onboarding
            </button>
        </div>
    </div>
    
    <!-- Full Screen Engagement Letter Modal -->
    <div class="fullscreen-modal" id="fullscreen-modal">
        <div class="modal-header">
            <h2>Engagement Letter - Review and Sign</h2>
            <button class="close-modal" id="close-modal-btn" onclick="closeEngagementLetter()">&times;</button>
        </div>
        
        <div class="modal-content">
            <div class="letter-container">
                <!-- English Column -->
                <div class="english-column">
                    <!-- Engagement Letter Content -->
                    <div class="letter-section">
                        <h4>Engagement Letter</h4>
                        <p>This Engagement Letter constitutes the full and binding agreement between Sultan Ali Auditing of Accounts (the "Auditor" or the "Audit Firm" or the "Firm" or the "Muhasba") and the Audit Client (the "Entity")</p>
                        <p>Audit Client: <?php echo ($step1['owner-name']); ?> (the "Entity")</p>
               
<p>
<?php 
if(isset($step1['license-number']) && !empty($step1['license-number'])) {
     echo "License Number: ",$step1['license-number'];
} else {
    // echo "Client Number: 256";
}
?>
</p>

<p>Address: <?php echo($step1['address']) ?></p>

<?php
// Determine the middle part
$middlePart = 'P'; // Default

if(isset($step1['license-number']) && is_string($step1['license-number'])) {
    $license = trim($step1['license-number']);
    if(!empty($license)) {
        $middlePart = $license;
    }
}
?>
 <p>Engagement Number: <?php echo htmlspecialchars($engagementNumber); ?> Provisional</p>
                        
                    </div>
                    
                    <div class="letter-section">
                        <h4>Purpose of the Engagement</h4>
                        <p>Based on the Client's request and intention, the client has engaged us through the Muhasba platform to audit the financial statements for the year ended <?php echo(DateFormatter::formatDateToReadable($current_end_date,'en')); ?></p>
                        <p>We are pleased to confirm our acceptance and understanding of the engagement, and we will conduct our audit with the objective of expressing an opinion on the financial statements in accordance with International Standards on Auditing (ISA) and the applicable laws of the United Arab Emirates.</p>
                        <p>The audit services will be conducted digitally through the Muhasba Platform, in compliance with the International Standards on Auditing. These standards require adherence to professional conduct, proper planning, and obtaining reasonable assurance about whether the financial statements are free from material misstatement.</p>
                        <p>The audit procedures include examining evidence supporting the amounts and disclosures in the financial statements, evaluating accounting policies used, and assessing the overall presentation of the financial statements.</p>
                        <p>We will perform the engagement in accordance with the terms set out in this agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Unforeseen Circumstances</h4>
                        <p>If unforeseen circumstances prevent us from completing the agreed procedures or issuing the related reports as outlined in this agreement, we will promptly notify you and report the matter to those charged with governance of the Entity.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Availability of Services</h4>
                        <p>Our services will be provided digitally through the Muhasba Platform, in accordance with the timelines and procedures mutually agreed upon within the system. The execution of the engagement through the platform shall constitute acceptance of the terms and obligations stated in this agreement. We also confirm our full independence and the absence of any financial or personal interest in the affairs of the Entity or in the services provided under this agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Professional and Ethical Compliance</h4>
                        <p>The audit firm complies with all professional conduct standards issued by the UAE Ministry of Economy, including the obligations under the Anti-Money Laundering and Counter-Terrorism Financing laws, and adheres to the International Ethics Standards Board for Accountants (IESBA) Code of Ethics. Accordingly, any additional services beyond the scope of this engagement will require our prior approval to ensure continued compliance with the principles of independence, integrity, and objectivity.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Timely Provision of Information</h4>
                        <p>The Entity is required to provide all necessary data and information in a timely manner. Any delay on the part of the Entity may result in an adjustment to the engagement timeline.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Legal and Regulatory Changes</h4>
                        <p>In the event of changes to laws or regulations that impact this agreement, both parties shall agree on the necessary amendments to the scope of work, the fees, or the engagement timeline.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Force Majeure</h4>
                        <p>Neither party shall be held liable for any delay or failure resulting from events beyond its reasonable control, including but not limited to natural disasters, pandemics, or regulatory changes. The affected party shall notify the other party as soon as reasonably practicable.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Deliverables</h4>
                        <p>The deliverables of this agreement include the issuance of the audit report on the Entity's financial statements in accordance with the International Standards on Auditing and the applicable laws of the United Arab Emirates. The report will be provided in digital form through the platform, and its issuance shall constitute completion of the agreed audit engagement. This agreement does not cover any advisory services or additional reports unless separately agreed upon in writing by both parties.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Scope and Limitations of Services</h4>
                        <p>The agreed scope of work does not aim to detect fraud or to express an opinion on the effectiveness of the internal control system. Due to the nature of audit procedures and their inherent limitations, there is an unavoidable risk that material misstatements in the financial statements may remain undetected. If all required information is not provided within the agreed scope of work, there is a risk of scope limitation.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Management Responsibilities</h4>
                        <p>The Entity bears full responsibility for the accuracy and completeness of all information provided to the auditor and for any misstatement or omission of material information that may affect the audit results. The Entity is also responsible for ensuring compliance with all legal and regulatory requirements in its financial reporting and for granting the auditor full access to all relevant information and documentation. The Entity shall provide any additional data or clarifications requested during the engagement, and the auditor is authorized to communicate with any individuals within the Entity deemed necessary to obtain sufficient and appropriate audit evidence.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Fee Structure and Payment Terms</h4>
                        <p>The estimated professional fees for this engagement amount to AED <?php echo(DateFormatter::formatMoney($audit_fee));?>  as detailed in the Entity's account on the Muhasba Platform. These fees cover the agreed scope of work and are payable upon its completion, fees may be adjusted if the client—whether unintentionally or intentionally—provides incorrect, inaccurate, or misleading information or disclosures, or if material changes arise that affect the scope or size of the audit engagement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Timeframe</h4>
                        <p>The expected timeframe for completing this engagement is between one and three working days, starting from the date of receiving all required data and information relevant to the agreed scope of work, provided that the Entity cooperates and submits the necessary documents in a timely manner.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Third-Party Audit Evidence</h4>
                        <p>If the required audit evidence from third parties cannot be obtained through official or electronic channels. Muhasba will promptly notify the Entity. The unavailability of such evidence may affect our audit opinion. It is understood that the Entity is responsible for providing the necessary authorizations and approvals to contact external parties.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Confidentiality</h4>
                        <p>All information and data obtained during this engagement will be treated as strictly confidential and will be used solely for the purpose of providing the agreed services under this agreement.</p>
                        <p>The auditor undertakes to implement appropriate measures to protect the Entity's information from unauthorized access or disclosure.</p>
                        <p>Confidentiality obligations shall remain in effect even after the termination of this agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Dispute Resolution</h4>
                        <p>In the event of any dispute arising under this agreement, both parties shall first endeavour to resolve the matter amicably and in good faith. If an amicable resolution cannot be reached, the matter shall be referred to the competent courts of the United Arab Emirates.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Termination</h4>
                        <p>The audit firm reserves the right to terminate this agreement immediately and without prior notice whether the Entity breaches any of the terms of this agreement or if professional or legal risks become elevated.</p>
                        <p>The client will be notified in such cases prior to any request for settlement of fees related to completed work.</p>
                        <p>The Client may cancel the Platform subscription at any time, which shall constitute a full and final termination of the engagement, unless the Engagement Letter has been signed. Upon signing the Engagement Letter, we become legally obligated to retain the Client's data and not delete or dispose of it in accordance with applicable regulations.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Limitation of Liability</h4>
                        <p>The audit firm's financial liability shall be limited to the total fees paid under this agreement and shall not exceed that amount, except in cases of gross negligence or wilful misconduct. The Entity agrees that the audit firm shall not be liable for any indirect, consequential, or incidental losses, or for any liabilities arising from inaccurate or incomplete information provided by the Entity, or from any breach by the Entity of its obligations under this agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Assignment</h4>
                        <p>Neither party may assign or transfer any of its rights or obligations under this agreement to any third party without the prior written consent of the other party.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Third-Party Reliance</h4>
                        <p>The audit report issued under this agreement is intended solely for the internal use of the Entity, as defined in the agreed scope of work, and shall not be relied upon by any third party without the prior written consent of the audit firm.</p>
                        <p>The audit firm disclaims any responsibility or liability toward any third party who relies on the report without authorization or beyond its intended purpose.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Data Protection</h4>
                        <p>Both parties agree to maintain appropriate cybersecurity and data protection measures to ensure the integrity and confidentiality of all electronic records and shared information.</p>
                        <p>In the event of a security breach or incident affecting the confidentiality or integrity of the data, the affected party shall promptly notify the other party without undue delay and fully cooperate to mitigate the impact and ensure business continuity.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Intellectual Property</h4>
                        <p>All working papers, methodologies, templates, and digital tools developed or provided through the Muhasba Platform remain the exclusive intellectual property of Sultan Ali Auditing of Accounts.</p>
                        <p>The final audit report and related documents are provided to the Entity for internal use only within the agreed scope of work.</p>
                        <p>The Entity shall not disclose, reproduce, or distribute such materials to any third party without prior written consent from Muhasba.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Fraud and Illegal Acts</h4>
                        <p>If Muhasba suspects or becomes aware of indications of fraud or illegal activities during the engagement, it reserves the right to suspend or terminate the engagement immediately and notify the Entity accordingly, in accordance with the laws of the United Arab Emirates.</p>
                        <p>Where required, the firm may also report such matters to the Ministry of Economy or other competent authorities, in compliance with Anti-Money Laundering (AML) and Counter-Terrorist Financing (CFT) regulations.</p>
                        <p>The firm retains the right to receive and collect fees for all work performed up to the date of suspension or withdrawal, and to take any necessary legal action or make mandatory disclosures to the relevant authorities.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Subcontractors and Affiliates</h4>
                        <p>Where it becomes necessary to engage subcontractors or affiliated entities or business partners to perform parts of the engagement through the Muhasba Platform, the Audit Firm remains fully responsible for supervising the quality and performance of such parties.</p>
                        <p>This delegation shall not be deemed a waiver or transfer of any obligations or responsibilities under this Agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Communication and Point of Contact</h4>
                        <p>Each party shall appoint a primary point of contact for coordination related to this engagement. All correspondence and instructions shall be communicated through the designated contact points via the Muhasba Platform or official email, unless otherwise agreed in writing.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Independent Contractual Relationship</h4>
                        <p>Both parties agree that our services to the Entity shall be provided as an independent contractor. We shall not be considered or classified as an employee, agent, or intermediary of the Entity.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Entire Agreement</h4>
                        <p>This Agreement constitutes the entire understanding between the Entity and the Audit Firm with respect to the services described herein. It supersedes and replaces any prior proposals, correspondence, or agreements, whether written or verbal.</p>
                        <p>The provisions of this Agreement shall remain in force until the completion of the engagement or its termination in accordance with the terms of this Agreement.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>Sultan Ali Auditing of Accounts</h4>
                        <p>Licensed by the Ministry of Economy</p>
                        <p>Auditor Entry Number: LC4377-01</p>
                        <div class="legal-notice">
                            This engagement letter has been electronically issued by Sultan Ali Auditing of Accounts and is legally effective without a handwritten signature. Client acceptance through the platform constitutes full and binding agreement.
                        </div>
                    </div>
                </div>
                
                <!-- Arabic Column -->
                <div class="arabic-column">
                    <!-- Arabic Engagement Letter Content -->
                    <div class="letter-section">
                        <h4>خطاب الإرتباط</h4>
                        <p>يُمثل خطاب الإرتباط هذا الاتفاق بين سلطان علي لمراجعة الحسابات ("الشركة" أو "المدقق" أو "شركة التدقيق" أو "محاسبة") وعميل التدقيق ("الكيان")</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الغاية من الإرتباط</h4>
                        <p>بناء على طلب ورغبة العميل، قام العميل بتكليفنا عبر منصة محاسبة لمراجعة القوائم المالية للسنة المنتهية في <?php echo(DateFormatter::formatDateToReadable($current_end_date,'ar')); ?>.</p>
                        <p>يسعدنا أن نؤكد قبولنا وتفهّمنا للمهمة المطلوبة، وأننا سنقوم بأداء عملية المراجعة بهدف إبداء الرأي على القوائم المالية وفقًا لمتطلبات معايير المراجعة الدولية والقوانين السارية في دولة الإمارات العربية المتحدة.</p>
                        <p>سيتم تنفيذ خدمات المراجعة إلكترونيًا من خلال منصة محاسبة، وبما يتوافق مع معايير المراجعة الدولية، التي تتطلب الالتزام بالسلوك المهني، والتخطيط الجيد، والحصول على تأكيدات معقولة فيما إذا كانت القوائم المالية خالية من التحريف الجوهري.</p>
                        <p>تشمل أعمال المراجعة فحص الأدلة المؤيدة للمبالغ والإيضاحات المعروضة في القوائم المالية، وتقييم السياسات المحاسبية المستخدمة، بالإضافة إلى تقييم العرض العام للقوائم المالية ككل.</p>
                        <p>سنقوم بتنفيذ المهمة وفقًا للشروط الواردة في هذا الاتفاق.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الظروف الغير متوقعة</h4>
                        <p>في حال وجود ظروف غير متوقعة تمنعنا من استكمال الإجراءات المتفق عليها أو إصدار التقرير/التقارير ذات الصلة كما هو موضح في هذا الإتفاق، فسنقوم بإبلاغكم فورًا، وإبلاغ المسؤولين عن الحوكمة في الكيان.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>توفر الخدمات</h4>
                        <p>ستُقدَّم خدماتنا من خلال منصة محاسبة إلكترونيًا، وفق المواعيد والإجراءات المتفق عليها بين الطرفين عبر النظام. ويُعد تنفيذ الخدمة عبر المنصة بمثابة موافقة على الشروط والالتزامات الواردة في هذا الإتفاق. كما نؤكد التزامنا الكامل بالاستقلالية وعدم وجود أي مصلحة مالية أو شخصية لنا في شؤون الكيان أو في الخدمات المقدمة بموجب هذه الاتفاقية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الالتزام المهني وقواعد السلوك</h4>
                        <p>تلتزم شركة التدقيق بجميع قواعد السلوك المهني الصادرة عن وزارة الاقتصاد في دولة الإمارات العربية المتحدة، بما في ذلك الالتزامات المنصوص عليها في قوانين مكافحة غسل الأموال وتمويل الإرهاب، ومعايير الأخلاقيات الدولية للمحاسبين (IESBA). ولذلك، فإن أي خدمات إضافية تتجاوز نطاق هذه الاتفاقية ستتطلب موافقتنا المسبقة لضمان استمرار امتثالنا لمتطلبات الاستقلالية والنزاهة والموضوعية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>توفير المعلومات في الوقت المناسب</h4>
                        <p>يتعين على الكيان توفير جميع البيانات والمعلومات اللازمة في الوقت المناسب. وقد يؤدي أي تأخير ناتج عن الكيان إلى تعديل الجدول الزمني لتنفيذ المهمة.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>التغييرات القانونية والتنظيمية</h4>
                        <p>في حال حدوث تغييرات على القوانين أو اللوائح التي تؤثر على الاتفاقية، يتعين على الطرفين الاتفاق على التعديلات اللازمة في نطاق المهمة أو الرسوم أو الجدول الزمني لتنفيذها.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>القوة القاهرة</h4>
                        <p>لن يتحمل أي طرف المسؤولية عن التأخير أو الإخفاق الناتج عن أحداث خارجة عن إرادته، بما في ذلك – على سبيل المثال لا الحصر – الكوارث الطبيعية، الأوبئة، أو التغييرات التنظيمية. ويتعين على الطرف المتضرر إشعار الطرف الآخر في أقرب وقت ممكن.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>المخرجات</h4>
                        <p>تشمل مخرجات هذه الاتفاقية إصدار تقرير المراجعة عن القوائم المالية للكيان وفقًا لمتطلبات معايير المراجعة الدولية والقوانين السارية في دولة الإمارات العربية المتحدة. وسيتم توفير التقرير بصيغة رقمية عبر المنصة، ويُعد إصداره بمثابة إتمام لمهمة المراجعة المتفق عليها. ولا تشمل هذه الاتفاقية أي خدمات استشارية أو تقارير إضافية ما لم يتم الاتفاق عليها كتابيًا مسبقًا بين الطرفين.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>حدود نطاق الخدمات</h4>
                        <p>نطاق العمل المتفق عليه لا يهدف إلى كشف عمليات الاحتيال أو إبداء رأي حول فاعلية نظام الرقابة الداخلية. ونظرًا لطبيعة إجراءات المراجعة والقيود الملازمة لها، فإن هناك مخاطر لا يمكن تجنبها، وقد تبقى تحريفات جوهرية في القوائم المالية دون اكتشاف. في حال عدم توفير جميع المعلومات المطلوبة خلال نطاق العمل، فقد يؤدي ذلك إلى قيود في النطاق.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>مسؤوليات الإدارة</h4>
                        <p>يتحمل الكيان المسؤولية الكاملة عن صحة واكتمال جميع المعلومات المقدمة للمدقق، وعن أي تحريف أو إغفال لمعلومات جوهرية قد يؤثر على نتائج المراجعة. كما يلتزم الكيان بضمان الامتثال للمتطلبات القانونية والتنظيمية في تقاريره المالية، وتمكين المدقق من الوصول إلى جميع المعلومات والمستندات ذات الصلة. ويتعين على الكيان تزويد المدقق بأي بيانات أو إيضاحات إضافية تُطلب أثناء تنفيذ المهمة، ويُصرح للمدقق بالتواصل مع أي من الأشخاص داخل الكيان الذين يرى أن من الضروري الحصول منهم على أدلة تدقيقية كافية ومناسبة.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>هيكل الرسوم وشروط السداد</h4>
                        <p>تم تفصيل الأتعاب التقديرية لهذه المهمة في حساب الكيان لدى منصة محاسبة، تبلغ قيمة تلك الأتعاب <?php echo(DateFormatter::formatMoney($audit_fee));?> درهم إماراتي شاملة كافة مراحل تنفيذ نطاق المهمة المتفق عليه. تُدفع الأتعاب عند استكمال نطاق المهمة، قد تُعدّل الرسوم إذا قدّم العميل - سواءً عن قصد أو عن غير قصد - معلومات أو إفصاحات غير صحيحة أو غير دقيقة أو مضللة، أو إذا طرأت تغييرات جوهرية تؤثر على نطاق أو حجم مهمة التدقيق.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الإطار الزمني</h4>
                        <p>الإطار الزمني المتوقع لتنفيذ هذا التكليف هو من يوم واحد إلى ثلاثة أيام عمل، وذلك اعتبارًا من تاريخ استلام جميع البيانات والمعلومات المطلوبة ذات الصلة بنطاق العمل، شريطة تعاون الكيان وتوفير المستندات في الوقت المناسب.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>أدلة التدقيق من الأطراف الثالثة</h4>
                        <p>في حال تعذّر الحصول على أدلة التدقيق المطلوبة من الأطراف الثالثة من خلال القنوات الرسمية أو الإلكترونية المعتمدة، ستقوم محاسبة  بإبلاغ الكيان بذلك. وقد يؤثر عدم توافر الأدلة على رأينا في تقرير التدقيق. يُفهم أن مسؤولية توفير التفويضات والموافقات اللازمة للتواصل مع الأطراف الخارجية تقع على عاتق الكيان.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>السرية</h4>
                        <p>سيتم التعامل مع جميع المعلومات والبيانات التي يتم الحصول عليها أثناء تنفيذ هذا التكليف بسرية تامة، ولن تُستخدم إلا لأغراض تقديم الخدمات المتفق عليها بموجب هذه الاتفاقية.</p>
                        <p>يتعهد المدقق باتخاذ الإجراءات المناسبة لضمان حماية معلومات الكيان ومنع الوصول غير المصرح به أو الإفصاح غير المصرح به عنها.</p>
                        <p>تظل التزامات السرية سارية حتى بعد انتهاء هذه الاتفاقية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>حل النزاعات</h4>
                        <p>في حال نشوء أي نزاع يتعلق بهذه الاتفاقية، سيسعى الطرفان أولًا إلى حله وديًا وبحسن نية. وإذا تعذر التوصل إلى حل ودي، تُحال المسألة إلى الجهات القضائية المختصة في دولة الإمارات العربية المتحدة.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الإنهاء</h4>
                        <p>تحتفظ شركة التدقيق بالحق في إنهاء الاتفاقية فورًا ودون إشعار مسبق في حال إخلال الكيان بأي من الشروط الواردة في هذه الاتفاقية أو عند ارتفاع مستوى المخاطر المهنية أو القانونية.</p>
                        <p>سيتم إشعار العميل في حال حدوث ذلك قبل طلب تسوية الرسوم المستحقة عن الأعمال المنجزة.</p>
                        <p>يحق للعميل إلغاء اشتراكه في المنصة في أي وقت، ويُعد ذلك إنهاءً كاملًا ونهائيًا للتعامل، ما لم يتم توقيع خطاب الارتباط. ويترتب على توقيع خطاب الارتباط التزام قانوني بالاحتفاظ ببيانات العميل وعدم حذفها أو التصرف بها وفقًا للأنظمة المعمول بها.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>حدود المسؤولية</h4>
                        <p>تُحدد المسؤولية المالية لشركة التدقيق بإجمالي الرسوم المدفوعة عن هذه الاتفاقية، ولا تتحمل الشركة أي التزامات تتجاوز ذلك المبلغ، باستثناء الحالات الناتجة عن الإهمال الجسيم أو سوء النية المتعمد. يوافق الكيان على أن شركة التدقيق لن تكون مسؤولة عن أي خسائر أو أضرار غير مباشرة أو تبعية أو عن أي التزامات تنشأ نتيجة معلومات خاطئة أو ناقصة مقدمة من الكيان أو عن أي خرق من جانبه لالتزاماته بموجب هذه الاتفاقية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>التنازل</h4>
                        <p>لا يجوز لأي من الطرفين التنازل عن أي من حقوقه أو التزاماته بموجب هذه الاتفاقية أو تفويضها إلى طرف ثالث دون الحصول على موافقة خطية مسبقة من الطرف الآخر.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>اعتماد الأطراف الثالثة</h4>
                        <p>يُعد تقرير المراجعة الصادر بموجب هذه الاتفاقية مخصصًا للاستخدام الداخلي للكيان فقط، وفقًا لما هو موضح في نطاق العمل، ولا يجوز الاعتماد عليه من قبل أي طرف ثالث دون الحصول على موافقة خطية مسبقة من شركة التدقيق. وتُخلي شركة التدقيق مسؤوليتها الكاملة تجاه أي طرف يعتمد على التقرير دون إذن مسبق أو خارج الغرض الذي أُعد من أجله.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>حماية البيانات</h4>
                        <p>يتفق الطرفان على الحفاظ على تدابير مناسبة للأمن السيبراني وحماية البيانات، لضمان سلامة وسرية السجلات والمعلومات الإلكترونية المتبادلة. وفي حال وقوع أي خرق أمني أو حادث يؤثر على سرية أو سلامة البيانات، يلتزم الطرف المتأثر بإخطار الطرف الآخر فورًا دون تأخير غير مبرر، مع التعاون الكامل لاتخاذ التدابير اللازمة للتخفيف من الأثر وضمان استمرارية العمل بأمان.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الملكية الفكرية</h4>
                        <p>تُعتبر جميع أوراق العمل، والمنهجيات، والنماذج، والقوالب، والأدوات التقنية التي تم تطويرها أو توفيرها من خلال منصة محاسبة  ملكًا فكريًا حصريًا لشركة سلطان علي لمراجعة الحسابات. ويُقدَّم تقرير التدقيق النهائي والوثائق ذات الصلة للكيان لاستخدامه الداخلي فقط ضمن نطاق العمل المتفق عليه. ولا يجوز للكيان الإفصاح عن هذه المواد أو توزيعها على أي طرف ثالث دون موافقة خطية مسبقة من محاسبة.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>الاحتيال والأعمال غير القانونية</h4>
                        <p>إذا ما اشتبهت محاسبة أو اكتشفت خلال تنفيذ المهمة وجود مؤشرات لاحتيال أو ممارسات غير قانونية، فإنها تحتفظ بالحق في تعليق أو إنهاء المهمة فورًا، وإبلاغ الكيان بذلك حسب ما تقتضيه القوانين المعمول بها في دولة الإمارات العربية المتحدة. وفي حال استدعى الأمر، قد تقوم الشركة بإبلاغ وزارة الاقتصاد أو الجهات المختصة وفقًا لمتطلبات قوانين مكافحة غسل الأموال وتمويل الإرهاب. وتحتفظ الشركة بحقها في استلام وتحصيل الأتعاب عن الأعمال التي تم تنفيذها حتى تاريخ التعليق أو الانسحاب، مع احتفاظها بكامل حقوقها القانونية الناتجة عن إنهاء المهمة أو الإفصاح اللازم للسلطات المختصة.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>التعهيد والشركات التابعة</h4>
                        <p>في حال اقتضت الضرورة الاستعانة بخبراء من الخارج أو بشركات تابعة أو شركاء أعمال لتنفيذ بعض أجزاء المهمة عبر منصة محاسبة ، شركة التدقيق تظل مسؤولة بالكامل عن الإشراف على جودة العمل وأداء الأطراف المشاركة. ولا يُعتبر هذا التفويض تنازلًا عن أي من الالتزامات أو المسؤوليات المقررة بموجب هذه الاتفاقية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>التواصل ونقطة الاتصال</h4>
                        <p>يتم توجيه جميع المراسلات والتعليمات من خلال نقاط الاتصال المحددة عبر منصة محاسبة أو البريد الإلكتروني الرسمي، ما لم يتم الاتفاق على خلاف ذلك كتابيًا.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>علاقة تعاقدية مستقلة</h4>
                        <p>يتفق الطرفان على أن تقديمنا للخدمات إلى الكيان سيتم بصفتنا طرفًا مستقلاً، ولا يجوز اعتبارنا أو تصنيفنا كموظف أو وكيل أو وسيط للكيان.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>التعهدات</h4>
                        <p>إن هذه الاتفاقية تمثل كامل التفاهم بين الكيان وشركة التدقيق فيما يتعلق بالخدمات المشار إليها هنا، وتحل محل وتلغي أي عروض أو مراسلات أو اتفاقيات سابقة، سواء كانت خطية أو شفهية. تبقى أحكام هذه الاتفاقية سارية المفعول حتى استكمال نطاق العمل أو إنهاء الخدمات وفقًا لشروط هذه الاتفاقية.</p>
                    </div>
                    
                    <div class="letter-section">
                        <h4>سلطان علي لمراجعة الحسابات</h4>
                        <p>مرخص من وزارة الاقتصاد</p>
                        <p>رقم قيد المدقق : LC4377-01</p>
                        <div class="legal-notice">
                            تم إصدار خطاب الارتباط هذا إلكترونيًا من قبل شركة سلطان علي لمراجعة الحسابات عبر المنصة، ويُعد ساري المفعول وملزمًا قانونيًا دون الحاجة إلى توقيع بخط اليد. ويُشكّل قبول العميل لهذا الخطاب عبر المنصة موافقة صريحة ونهائية على جميع الشروط والأحكام الواردة فيه، ويُعد هذا القبول بمثابة اتفاق كامل وملزم بين الطرفين، يتمتع بذات الحجية القانونية للعقود الموقعة توقيعًا تقليديًا.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="signature-section">
                <div class="signature-title">Digital Signature</div>
                <div class="signature-box" id="signature-box">
                    Click "Sign Document" to add your digital signature
                </div>
                <div id="completed-notice" class="completed-notice" style="display: none;">
                    ✓ Onboarding Completed - Signature is finalized
                </div>
                <div class="signature-actions" id="signature-actions">
                    <button class="sign-btn" id="sign-document-btn" onclick="signDocument()">Sign Document</button>
                    <button class="sign-btn clear-sign-btn" id="clear-signature-btn" onclick="clearSignature()">Clear Signature</button>
                </div>
            </div>
            
            <div class="modal-footer">
                <div class="footer-note">
                    Note: By signing this document, you automatically confirm all acceptance terms.
                </div>
                <div>
                    <button type="button" onclick="saveAndCloseLetter()" class="sign-btn" id="save-close-btn">
                        Save and Close
                    </button>
                </div>
            </div>
        </div>
        <p id="download-section"></p>
    </div>

    <script>

        // Function to submit engagement data
function submitEngagementData() {
    // Collect data from the form/session
    const engagementData = {
        terms_accepted: isDocumentSigned ? 1 : 0,
        digital_signature_name: getClientName(),
        digital_signature_date: new Date().toISOString(),
        engagement_number: "<?php echo $engagementNumber ?? ''; ?>"
    };

    // Show loading state
    const completeBtn = document.getElementById('complete-btn');
    const originalText = completeBtn.textContent;
    completeBtn.disabled = true;
    completeBtn.textContent = 'Processing...';

    // Send data via AJAX
    fetch('../../../controller/steps/save_engagement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(engagementData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Save successful
            if (data.terms_accepted) {
                // Redirect to completion page
                window.location.href = data.redirect_url;
            } else {
                // Just saved, enable complete button
                completeBtn.disabled = false;
                completeBtn.textContent = originalText;
                alert('Engagement data saved successfully!');
            }
        } else {
            // Handle error
            completeBtn.disabled = false;
            completeBtn.textContent = originalText;
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        completeBtn.disabled = false;
        completeBtn.textContent = originalText;
        alert('Network error: ' + error.message);
    });
}

        // DOM Elements
        const fullscreenModal = document.getElementById('fullscreen-modal');
        const signatureBox = document.getElementById('signature-box');
        const completeBtn = document.getElementById('complete-btn');
        const downloadSection = document.getElementById('download-section');
        const downloadBtn = document.getElementById('download-btn');
        const downloadNotice = document.getElementById('download-notice');
        const completionMessage = document.getElementById('completion-message');
        const viewLetterBtn = document.getElementById('view-letter-btn');
        const signatureActions = document.getElementById('signature-actions');
        const completedNotice = document.getElementById('completed-notice');
        
        // State variables
        let isDocumentSigned = false;
        let isOnboardingCompleted = false;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Check if onboarding is already completed
            const onboardingCompleted = localStorage.getItem('onboardingCompleted');
            if (onboardingCompleted === 'true') {
                isOnboardingCompleted = true;
                
                // Lock everything if onboarding is completed
                lockEverythingAfterCompletion();
                
                // Show completion message
                completionMessage.style.display = 'block';
            } else {
                // Check if letter was already signed from localStorage
                const savedSignature = localStorage.getItem('engagementLetterSigned');
                if (savedSignature === 'true') {
                    isDocumentSigned = true;
                    updateUIAfterSignature();
                }
            }
            
            // Prevent direct download without signing
            downloadBtn.addEventListener('click', function(e) {
                if (!isDocumentSigned) {
                    e.preventDefault();
                    downloadNotice.style.display = 'block';
                    setTimeout(() => {
                        downloadNotice.style.display = 'none';
                    }, 3000);
                    return false;
                }
            });
        });
        
        // Function to lock all signature functionality after onboarding completion
        function lockEverythingAfterCompletion() {
            // Update complete button
            if (completeBtn) {
                completeBtn.disabled = true;
                completeBtn.textContent = "Completed";
            }
            
            // Update signature box to show locked state
            if (signatureBox) {
                const clientName = "<?php echo $step1['owner-name']; ?>"
                const signDate = localStorage.getItem('signatureDate') || new Date().toLocaleDateString();
                const signTime = localStorage.getItem('signatureTime') || new Date().toLocaleTimeString();
                
                signatureBox.innerHTML = `
                    <div style="text-align: center; width: 100%;">
                        <div style="font-family: 'Brush Script MT', cursive; font-size: 36px; margin-bottom: 10px; color: #2c3e50;">
                            ${clientName}
                        </div>
                        <div style="margin-bottom: 8px; color: #555; font-size: 16px;">Digitally Signed ✓</div>
                        <div style="font-size: 14px; color: #777;">Date: ${signDate}</div>
                        <div style="font-size: 14px; color: #777;">Time: ${signTime}</div>
                        <div style="font-size: 12px; color: #999; margin-top: 10px; font-style: italic;">
                            This digital signature represents acceptance of all terms
                        </div>
                    </div>
                `;
                signatureBox.classList.add('signed');
            }
            
            // Hide signature actions (Sign and Clear buttons)
            if (signatureActions) {
                signatureActions.classList.add('hidden');
            }
            
            // Show completed notice
            if (completedNotice) {
                completedNotice.style.display = 'block';
            }
            
            // Show download section
            if (downloadSection) {
                downloadSection.style.display = 'block';
            }
            
            // Update step status to completed
            updateStepStatus('completed');
            
            // Document is considered signed
            isDocumentSigned = true;
        }
        
        // Function to open engagement letter modal
        function openEngagementLetter() {
            fullscreenModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Function to close engagement letter modal
        function closeEngagementLetter() {
            fullscreenModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Function to sign document
        function signDocument() {
            if (isOnboardingCompleted) {
                return;
            }
            
            const clientName = getClientName();
            const signDate = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const signTime = new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Save signature date and time
            localStorage.setItem('signatureDate', signDate);
            localStorage.setItem('signatureTime', signTime);
            
            signatureBox.innerHTML = `
                <div style="text-align: center; width: 100%;">
                    <div style="font-family: 'Brush Script MT', cursive; font-size: 36px; margin-bottom: 10px; color: #2c3e50;">
                        ${clientName}
                    </div>
                    <div style="margin-bottom: 8px; color: #555; font-size: 16px;">Digitally Signed</div>
                    <div style="font-size: 14px; color: #777;">Date: ${signDate}</div>
                    <div style="font-size: 14px; color: #777;">Time: ${signTime}</div>
                    <div style="font-size: 12px; color: #999; margin-top: 10px; font-style: italic;">
                        This digital signature represents acceptance of all terms
                    </div>
                </div>
            `;
            signatureBox.classList.add('signed');
            isDocumentSigned = true;
            
            // Show success message
            setTimeout(() => {
                alert("Document signed successfully! All acceptance terms have been confirmed automatically.");
            }, 100);
        }
        
        // Function to clear signature
        function clearSignature() {
            if (isOnboardingCompleted) {
                return;
            }
            
            signatureBox.textContent = "Click 'Sign Document' to add your digital signature";
            signatureBox.classList.remove('signed');
            isDocumentSigned = false;
            
            // Hide download section
            downloadSection.style.display = 'none';
            completeBtn.disabled = true;
            
            // Clear saved signature data
            localStorage.removeItem('signatureDate');
            localStorage.removeItem('signatureTime');
        }
        
        // Function to save and close the letter
        function saveAndCloseLetter() {
            if (isDocumentSigned) {
                // Save signature to localStorage
                localStorage.setItem('engagementLetterSigned', 'true');
                
                // Update UI
                updateUIAfterSignature();
                
                // Close modal
                closeEngagementLetter();
            } else {
                alert("Please sign the document before saving.");
                return;
            }
        }
        
        // Function to update UI after signature
        function updateUIAfterSignature() {
            // Show download section
            downloadSection.style.display = 'block';
            
            // Enable complete button
            completeBtn.disabled = false;
            
            // Update step status in sidebar
            updateStepStatus('pending');
        }
        
        // Function to download engagement letter
        function downloadEngagementLetter() {
            if (!isDocumentSigned) {
                downloadNotice.style.display = 'block';
                setTimeout(() => {
                    downloadNotice.style.display = 'none';
                }, 3000);
                return false;
            }
            
            const clientName = getClientName();
            const downloadDate = new Date().toLocaleDateString();
            
            // Create content for the downloaded file
            const content = `
ENGAGEMENT LETTER
=================

Audit Client: ${clientName}
Date Signed: ${downloadDate}
Reference: 700-346610-2021

This document has been digitally signed by ${clientName}.

Terms Accepted:
1. Read and understood the Engagement Letter in its entirety.
2. Agreed to be bound by all terms and conditions.
3. Accepted the scope of work, timeline, and fee structure.
4. Digital signature is legally binding.

Please note that the engagement letter will be issued and made available in your account only after completion of our review of the submitted data and documents and formal acceptance of the engagement by Sultan Ali Auditing of Accounts.

--- END OF DOCUMENT ---
            `;
            
            // Create and trigger download
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Engagement_Letter_${clientName.replace(/\s+/g, '_')}_${downloadDate.replace(/\//g, '-')}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            alert("Engagement Letter Notice!");
        }
        
        // Function to complete onboarding
        function completeOnboarding() {
            if (!isDocumentSigned) {
                alert("Please sign the engagement letter before completing onboarding.");
                return;
            }
            
            if (isOnboardingCompleted) {
                alert("Onboarding has already been completed.");
                return;
            }
            
            // Save completion data
            const onboardingData = {
                completed: true,
                completionDate: new Date().toISOString(),
                clientName: getClientName(),
                steps: {
                    kyc: true,
                    auditFee: true,
                    financialYear: true,
                    taxStatus: true,
                    engagementLetter: true
                }
            };
            
            // Save to localStorage
            localStorage.setItem('onboardingData', JSON.stringify(onboardingData));
            localStorage.setItem('onboardingCompleted', 'true');
            
            // Set onboarding completed flag
            isOnboardingCompleted = true;
            
            // Lock all signature functionality
            lockEverythingAfterCompletion();
            
            // Show completion message
            completionMessage.style.display = 'block';
            
            // Scroll to completion message
            completionMessage.scrollIntoView({ behavior: 'smooth' });
            
            // Show success message
            
            alert("Onboarding completed successfully! Your application is now under review.");
            submitEngagementData();
        }
        
        // Function to get client name from saved data
        function getClientName() {
            // Try to get from KYC data
            const kycData = localStorage.getItem('kycData');
            if (kycData) {
                try {
                    const parsedData = JSON.parse(kycData);
                    if (parsedData.entityName) {
                        return parsedData.entityName;
                    }
                } catch (e) {
                    console.error("Error parsing KYC data:", e);
                }
            }
            const phpClientName = "<?php echo isset($step1['owner-name']) ? addslashes($step1['owner-name']) : ''; ?>";
            // Default name
            
            return phpClientName;
        }
        
        // Function to update step status in sidebar
        function updateStepStatus(status) {
            const stepCircle = document.querySelector('.step-vertical-item:last-child .step-vertical-circle');
            const stepStatus = document.querySelector('.step-vertical-item:last-child .step-vertical-status');
            
            if (status === 'completed') {
                stepCircle.classList.remove('active');
                stepCircle.classList.add('completed');
                stepStatus.textContent = "COMPLETED";
                stepStatus.className = "step-vertical-status completed";
            } else if (status === 'pending') {
                stepCircle.classList.add('active');
                stepCircle.classList.remove('completed');
                stepStatus.textContent = "PENDING";
                stepStatus.className = "step-vertical-status pending";
            }
        }
        
        // Function to go to previous step
        function goToPreviousStep() {
            window.location.href = 'TAX STATUS.php';
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && fullscreenModal.style.display === 'block') {
                closeEngagementLetter();
            }
        });
    </script>
</body>
</html>