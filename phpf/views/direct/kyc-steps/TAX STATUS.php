<?php
ob_start();
session_start();
if (empty($_SESSION['form']['step3'])){
    header("Location: FINANCIAL-YEAR.php");
}
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
$step4 = $_SESSION['form']['step4'] ?? [];

// echo $step3['current-end-date'];

if(isset($step3['year'])){
    $year = $step3['year'];
} else {
    $year = "No";
}

// Determine user status
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

// Populate form data from session
$formData = $step4 ?? [];

// Get specific values from session
$vat_status = $formData['vat_status'] ?? '';
$vat_registration_number = $formData['vat_registration_number'] ?? '';
$excise_tax_status = $formData['excise_tax_status'] ?? '';
$corporate_tax_status = $formData['corporate_tax_status'] ?? '';
$corporate_tax_registration_number = $formData['corporate_tax_registration_number'] ?? '';
$corporate_tax_treatment = $formData['corporate_tax_treatment'] ?? '';
$small_business_relief = $formData['small_business_relief'] ?? '';
$not_registered_reason = $formData['not_registered_reason'] ?? '';
$other_reason_details = $formData['other_reason_details'] ?? '';

// Previous period values
$previous_vat_status = $formData['previous_vat_status'] ?? '';
$previous_excise_tax_status = $formData['previous_excise_tax_status'] ?? '';
$previous_corporate_tax_status = $formData['previous_corporate_tax_status'] ?? '';
$previous_corporate_tax_treatment = $formData['previous_corporate_tax_treatment'] ?? '';
$previous_small_business_relief = $formData['previous_small_business_relief'] ?? '';
$previous_not_registered_reason = $formData['previous_not_registered_reason'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['form']['step4'] = $_POST;
    header("Location: ../../../controller/steps/save_tax.php");
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Entity Application - Tax Status Disclosure</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    /* ===== COMMON STYLES ===== */
body { 
    font-family: 'Poppins', sans-serif; 
    background: #eef1f6; 
    padding: 40px; /* Changed from 20px 40px to match KYC form */
    font-size: 16px; 
    line-height: 1.6; 
    color: #333; 
    display: flex;
    margin: 0;
    height: 100vh; /* Full viewport height */
    overflow: hidden; /* Hide overall scroll */
    box-sizing: border-box;
}

/* الخطوات الجانبية - Fixed Sidebar */
.steps-sidebar {
    width: 280px;
    background: #ffffff;
    padding: 30px; /* Reduced padding to match KYC form */
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
    margin-bottom: 25px; /* Reduced from 20px to match KYC form */
    padding-bottom: 12px; /* Reduced from 12px to match KYC form */
    border-bottom: 1px solid #e0e0e0;
}

.sidebar-subtitle {
    font-size: 14px;
    color: #555;
    margin-bottom: 30px; /* Reduced from 30px to match KYC form */
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
    margin-bottom: 28px; /* Reduced from 25px to match KYC form */
    position: relative;
    min-height: 36px; /* Reduced from 35px to match KYC form */
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
    margin: 0 0 4px 0; /* Reduced margin to match KYC form */
    line-height: 1.3; /* Tighter line height */
}

.step-vertical-status {
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 600;
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

/* محتوى الصفحة الرئيسي - Scrollable */
.main-content {
    flex-grow: 1;
    background: #ffffff;
    padding: 40px 50px; /* Reduced vertical padding to match KYC form */
    border-radius: 8px; /* Changed from 0 8px 8px 0 */
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    min-width: 900px; /* Changed from max-width */
    max-height: calc(91vh - 80px); /* Limit height to viewport */
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
    padding-bottom: 10px; /* Reduced from 10px to match KYC form */
    margin-top: 0;
    margin-bottom: 6px; /* Reduced from 6px to match KYC form */
    font-weight: 400;
}

.content-header .subheading {
    font-size: 14px;
    color: #555;
    margin-bottom: 30px; /* Reduced from 30px to match KYC form */
    line-height: 1.4; /* Tighter line height */
}

/* ===== TAX STATUS SPECIFIC STYLES ===== */
.hide-form {
    display: none;
}

.form-group { 
    margin-bottom: 20px; /* Reduced margin */
    position: relative; 
}

.form-group label { 
    display: block; 
    margin-bottom: 6px; /* Reduced margin */
    font-size: 15px; 
}

.form-group select, .form-group input[type="text"], .form-group textarea {
    width: 100%; 
    max-width: 500px;
    padding: 10px 12px; /* Reduced padding */
    border: 1px solid #ccc; 
    border-radius: 4px; 
    font-size: 15px; 
    box-sizing: border-box;
    background-color: white;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.form-group textarea {
    min-height: 80px; /* Reduced min-height */
    resize: vertical;
    cursor: text;
}

.form-group input[type="text"]:read-only {
    background-color: #f9f9f9;
    cursor: not-allowed;
}

.tax-section {
    margin-top: 30px; /* Reduced margin */
    padding-top: 25px; /* Reduced padding */
    border-top: 1px solid #e0e0e0;
}

.tax-section h3 {
    font-size: 20px;
    color: #1a1a1a;
    margin-top: 0;
    margin-bottom: 15px; /* Reduced margin */
    font-weight: 400;
}

.columns-container {
    display: flex;
    gap: 40px; /* Reduced from 60px */
    flex-wrap: wrap;
}

.column {
    flex: 1;
    min-width: 400px;
}

.column-title {
    font-size: 18px;
    color: #333;
    margin-bottom: 15px; /* Reduced from 20px */
    padding-bottom: 8px; /* Reduced padding */
    border-bottom: 1px solid #e0e0e0;
    font-weight: 400;
}

.missing-fields-container {
    margin-top: 25px; /* Reduced margin */
    padding: 18px; /* Reduced from 15px to match KYC form */
    background: #fdebea;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    color: #721c24;
    display: none;
    width: 44%;
}

.missing-fields-container-first-year {
    margin-top: 25px; /* Reduced margin */
    padding: 18px; /* Reduced from 15px to match KYC form */
    background: #fdebea;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    color: #721c24;
    display: none;
    width: 460px;
}

.missing-fields-container h4,
.missing-fields-container-first-year h4 {
    margin-top: 0;
    margin-bottom: 12px; /* Reduced margin to match KYC form */
    color: #721c24;
    font-size: 16px;
    font-weight: 200;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.3; /* Tighter line height */
}

.missing-fields-container h4::before,
.missing-fields-container-first-year h4::before {
    content: "⚠";
    font-size: 18px;
}

.missing-fields-container .missing-item,
.missing-fields-container-first-year .missing-item {
    margin-bottom: 6px; /* Reduced from 8px to match KYC form */
    padding-left: 20px;
    position: relative;
    font-size: 14px;
    line-height: 1.4; /* Tighter line height */
}

.missing-fields-container .missing-item::before,
.missing-fields-container-first-year .missing-item::before {
    content: "•";
    position: absolute;
    left: 8px;
    color: #721c24;
    font-size: 16px;
}

.missing-fields-container .missing-item:last-child,
.missing-fields-container-first-year .missing-item:last-child {
    margin-bottom: 0;
}

.missing-fields-container .error-item,
.missing-fields-container-first-year .error-item {
    margin-bottom: 6px; /* Reduced margin to match KYC form */
    padding-left: 20px;
    position: relative;
    font-size: 14px;
    line-height: 1.4; /* Tighter line height */
}

.missing-fields-container .error-item::before,
.missing-fields-container-first-year .error-item::before {
    content: "⚠";
    position: absolute;
    left: 0;
    color: #721c24;
}

.missing-fields-container .error-item:last-child,
.missing-fields-container-first-year .error-item:last-child {
    margin-bottom: 0;
}

.reason-options {
    margin-left: 20px;
    margin-top: 8px; /* Reduced margin */
    margin-bottom: 12px; /* Reduced from 15px */
    padding:7px;
}

.reason-option {
    margin-bottom: 8px; /* Reduced margin */
    display: flex;
    align-items: flex-start;
    padding:7px;
}

.reason-option input[type="radio"] {
    margin-right: 10px;
    margin-top: 3px; /* Reduced margin */
}

.reason-option label {
    font-size: 14px;
    line-height: 1.4; /* Tighter line height */
    margin-bottom: 0;
    cursor: pointer;
}

.other-reason-field {
    margin-top: 12px; /* Reduced margin */
    margin-left: 25px;
    display: none;
}

/* ===== ENGAGEMENT LETTER SPECIFIC STYLES ===== */
.engagement-letter-intro {
    padding: 0;
    margin-bottom: 25px; /* Reduced from 30px */
}

.engagement-letter-intro p {
    margin: 0 0 10px 0; /* Reduced from 12px */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
    color: #333;
}

.engagement-letter-intro p:last-child {
    margin-bottom: 0;
}

.letter-preview-container {
    padding: 0;
    margin-bottom: 25px; /* Reduced from 30px */
    text-align: left;
}

.letter-preview-title {
    font-size: 18px;
    color: #333;
    margin-bottom: 12px; /* Reduced from 15px */
    font-weight: 400;
}

.view-letter-btn {
    background-color: #dd5656 !important;
    color: white;
    border: 1px solid #ddd;
    padding: 10px 25px; /* Reduced from 12px 30px */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
    font-weight: 600;
    transition: background-color 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.view-letter-btn:hover {
    background-color: #e6e6e6;
}

.view-letter-btn:disabled {
    background-color: #e0e0e0;
    color: #999;
    cursor: not-allowed;
    border-color: #d0d0d0;
}

.view-letter-btn:disabled:hover {
    background-color: #e0e0e0;
}

.confirmation-section {
    margin-top: 25px; /* Reduced from 30px */
    padding-top: 20px; /* Reduced from 25px */
    border-top: 1px solid #e0e0e0;
}

.confirmation-title {
    font-size: 20px;
    color: #1a1a1a;
    margin-bottom: 15px; /* Reduced from 20px */
    font-weight: 400;
}

.auto-confirmation-message {
    padding: 0;
    margin-bottom: 20px; /* Reduced from 25px */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
}

.download-section {
    text-align: left;
    margin-top: 25px; /* Reduced from 30px */
    padding: 0;
}

.download-btn {
    background-color: #f2f2f2;
    color: #333;
    border: 1px solid #ddd;
    padding: 10px 30px; /* Reduced from 12px 40px */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
    font-weight: 600;
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

.completion-message {
    color: #333;
    padding: 0;
    margin-top: 25px; /* Reduced from 30px */
    display: none;
}

.completion-message h4 {
    margin-top: 0;
    margin-bottom: 10px; /* Reduced from 12px */
    font-size: 18px;
    font-weight: 600;
    line-height: 1.3; /* Tighter line height */
}

.completion-message p {
    margin: 0 0 6px 0; /* Reduced from 8px */
    font-size: 15px;
    line-height: 1.5; /* Tighter line height */
}

.application-status {
    margin-top: 20px; /* Reduced from 25px */
    padding: 0;
}

.application-status h4 {
    font-size: 18px;
    color: #1a1a1a;
    margin-bottom: 10px; /* Reduced from 12px */
    font-weight: 600;
}

.status-placeholder {
    min-height: 70px; /* Reduced from 80px */
    border: 1px dashed #ddd;
    border-radius: 4px;
    padding: 12px; /* Reduced from 15px */
    color: #999;
    font-style: italic;
    line-height: 1.5; /* Tighter line height */
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

.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px; /* Reduced from 30px to match */
    padding-top: 25px; /* Reduced from 25px to match */
    border-top: 1px solid #e0e0e0;
    width: 100%;
}

button {
    background-color: #f2f2f2;
    color: #333;
    padding: 10px 20px; /* Reduced padding to match KYC form */
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size to match KYC form */
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

/* Engagement Letter Modal - Full Screen */
.fullscreen-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: white;
    z-index: 1000;
    overflow-y: auto;
}

.modal-header {
    background-color: #ffffff;
    color: #333;
    padding: 15px 30px; /* Reduced vertical padding */
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
    font-weight: 400;
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

.close-modal:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modal-content {
    padding: 0;
}

.letter-container {
    width: 100%;
    display: flex;
    flex-direction: row;
    min-height: calc(100vh - 80px); /* Reduced height calculation */
}

.english-column {
    flex: 1;
    padding: 25px; /* Reduced from 30px */
    overflow-y: auto;
    text-align: left;
    border-right: 1px solid #e0e0e0;
    line-height: 1.5; /* Tighter line height */
}

.arabic-column {
    flex: 1;
    padding: 25px; /* Reduced from 30px */
    overflow-y: auto;
    text-align: right;
    direction: rtl;
    font-family: 'Cairo', sans-serif;
    line-height: 1.6; /* Tighter line height */
}

.letter-section {
    margin-bottom: 15px; /* Reduced from 20px */
    page-break-inside: avoid;
}

.letter-section h4 {
    color: #333;
    margin-bottom: 10px; /* Reduced from 12px */
    font-size: 16px; /* Reduced font size */
    font-weight: 800;
    border-bottom: 1px solid #eee;
    padding-bottom: 6px; /* Reduced padding */
    line-height: 1.3; /* Tighter line height */
}

.letter-section p {
    margin: 0 0 10px 0; /* Reduced from 12px */
    line-height: 1.5; /* Tighter line height */
    font-size: 13px; /* Reduced font size */
}

/* تعديلات الخط العربي */
.arabic-column .letter-section h4 {
    font-size: 14px !important; /* Reduced font size */
    font-family: 'Cairo', sans-serif;
    font-weight: 700;
    line-height: 1.3; /* Tighter line height */
}

.arabic-column .letter-section p {
    font-size: 12px !important; /* Reduced font size */
    font-family: 'Cairo', sans-serif;
    line-height: 1.6; /* Tighter line height */
}

.letter-section table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px; /* Reduced from 15px */
}

.letter-section table, .letter-section th, .letter-section td {
    border: 1px solid #ddd;
}

.letter-section th, .letter-section td {
    padding: 8px; /* Reduced padding */
    text-align: left;
    font-size: 13px; /* Reduced font size */
    line-height: 1.4; /* Tighter line height */
}

.letter-section th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.signature-section {
    padding: 25px; /* Reduced from 30px */
    border-top: 2px solid #e0e0e0;
    margin-top: 25px; /* Reduced from 30px */
}

.signature-title {
    font-size: 18px; /* Reduced font size */
    margin-bottom: 15px; /* Reduced from 20px */
    color: #333;
    text-align: center;
    font-weight: 600;
}

.signature-box {
    border: 2px dashed #ccc;
    padding: 25px; /* Reduced from 30px */
    border-radius: 8px;
    margin-bottom: 20px; /* Reduced from 25px */
    min-height: 100px; /* Reduced from 120px */
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px; /* Reduced font size */
    color: #777;
    background-color: #f9f9f9;
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
    justify-content: center;
    gap: 12px; /* Reduced from 15px */
    margin-bottom: 20px; /* Reduced from 25px */
}

.signature-actions.hidden {
    display: none;
}

.sign-btn {
    background-color: #f2f2f2;
    color: #333;
    border: 1px solid #ddd;
    padding: 10px 25px; /* Reduced from 12px 30px */
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; /* Reduced font size */
    font-weight: 600;
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

.sign-btn:disabled:hover {
    background-color: #e0e0e0;
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
    padding: 15px 25px; /* Reduced from 15px 30px */
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    position: sticky;
    bottom: 0;
    background-color: white;
}

.footer-note {
    font-size: 13px; /* Reduced font size */
    color: #666;
    font-style: italic;
    line-height: 1.4; /* Tighter line height */
}

.legal-notice {
    font-size: 11px; /* Reduced font size */
    color: #666;
    font-style: italic;
    margin-top: 8px; /* Reduced margin */
    text-align: center;
    border-top: 1px solid #eee;
    padding-top: 8px; /* Reduced padding */
    line-height: 1.4; /* Tighter line height */
}

.download-notice {
    font-size: 11px; /* Reduced font size */
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
    font-size: 13px; /* Reduced font size */
    color: #dd5656;
    font-weight: 600;
    text-align: center;
    margin-top: 12px; /* Reduced from 15px */
    padding: 8px; /* Reduced from 10px */
    background-color: #fff8f8;
    border-radius: 4px;
    border: 1px solid #ffdddd;
    line-height: 1.4; /* Tighter line height */
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .letter-container {
        flex-direction: column;
    }
    
    .english-column, .arabic-column {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e0e0e0;
    }
}

/* Add subtle scrollbar for modal if needed */
.english-column::-webkit-scrollbar,
.arabic-column::-webkit-scrollbar {
    width: 6px;
}

.english-column::-webkit-scrollbar-track,
.arabic-column::-webkit-scrollbar-track {
    background: #f5f5f5;
}

.english-column::-webkit-scrollbar-thumb,
.arabic-column::-webkit-scrollbar-thumb {
    background: #ddd;
    border-radius: 3px;
}

/* تحسينات إضافية للتصميم المدمج */
.column {
    max-width: 500px;
}

/* إزالة التداخل في الروابط */
a {
    text-decoration: none;
    color: inherit;
}

/* تحسينات الأزرار */
button {
    min-height: 40px;
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
                <div class="step-vertical-circle completed">3</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Financial Year Details</div>
                    <div class="step-vertical-status completed">COMPLETED</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle active">4</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Tax Status Disclosure</div>
                    <div class="step-vertical-status pending">PENDING</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle">5</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Engagement Letter Acceptance</div>
                    <div class="step-vertical-status not-started">NOT STARTED</div>
                </div>
            </li>
        </ul>
    </div>
    

<form method="POST" action="../../../controller/steps/save_tax.php" enctype="multipart/form-data" id="tax-status-form">
    <div class="main-content">
        <div class="content-header">
            <h1>Tax Status Disclosure</h1>
            <div class="subheading">
                Please provide details about your entity's tax registration status and treatments.
            </div>
        </div>
        <div class="step-content-container active">
            <div class="form-group">
                <select id="business-registration-status" name="business_registration_status" onchange="handleBusinessRegistrationChange(); validateStep();" disabled class="hide-form">
                    <option value="">Select Business Status</option>
                    <option value="Unlicensed Natural Person(s)" <?php echo ($regitration == 'Unlicensed Natural Person(s)') ? 'selected' : ''; ?>>UNLICENSED NATURAL PERSON(S)</option>
                    <option value="Mainland Licensed-Sole Owner" <?php echo ($regitration == 'Mainland Licensed-Sole Owner') ? 'selected' : ''; ?>>MAINLAND LICENSED-SOLE OWNER</option>
                    <option value="Mainland Licensed-Multiple Owners" <?php echo ($regitration == 'Mainland Licensed-Multiple Owners') ? 'selected' : ''; ?>>MAINLAND LICENSED-MULTIPLE OWNERS</option>
                    <option value="Free Zone Licensed" <?php echo ($regitration == 'Free Zone Licensed') ? 'selected' : ''; ?>>FREE ZONE LICENSED</option>
                </select>
            </div>
            
            <div class="form-group">
                <select id="first-financial-statements" name="first_financial_statements" onchange="toggleFinancialYearFields(); validateStep();" class="hide-form">
                    <option value="">Select</option>
                    <option value="Yes" <?php echo ($year == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                    <option value="No" <?php echo ($year == 'No') ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            
            <div class="tax-section" id="current-tax-section">
                
                <div class="columns-container" id="current-tax-columns">
                    <div class="column" id="current-tax-column">
                        <div class="column-title">Current Financial Period</div>
                        
                        <div class="form-group">
                            <label for="vat-status">VAT Status</label>
                            <select id="vat-status" name="vat_status" onchange="handleVatStatusChange(); validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($vat_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($vat_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="vat-number-group" style="display: none;">
                            <label for="vat-registration-number">VAT Registration Number</label>
                            <input type="text" id="vat-registration-number" name="vat_registration_number" placeholder="Enter VAT registration number" value="<?php echo htmlspecialchars($vat_registration_number); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="excise-tax-status">Excise Tax Status</label>
                            <select id="excise-tax-status" name="excise_tax_status" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($excise_tax_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($excise_tax_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="corporate-tax-status">Corporate Tax Status</label>
                            <select id="corporate-tax-status" name="corporate_tax_status" onchange="handleCorporateTaxStatusChange(); validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($corporate_tax_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($corporate_tax_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="corporate-tax-number-group" style="display: none;">
                            <label for="corporate-tax-registration-number">Corporate Tax Registration Number</label>
                            <input type="text" id="corporate-tax-registration-number" name="corporate_tax_registration_number" placeholder="Enter Corporate Tax registration number" value="<?php echo htmlspecialchars($corporate_tax_registration_number); ?>">
                        </div>
                        
                        <div class="form-group" id="corporate-tax-treatment-group" style="display: none;">
                            <label for="corporate-tax-treatment">Corporate Tax Treatment</label>
                            <select id="corporate-tax-treatment" name="corporate_tax_treatment" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="Standard Corporate Tax rates" <?php echo ($corporate_tax_treatment == 'Standard Corporate Tax rates') ? 'selected' : ''; ?>>Standard Corporate Tax rates</option>
                                <option value="Qualifying Free Zone Person" id="qualifying-freezone-option" <?php echo ($corporate_tax_treatment == 'Qualifying Free Zone Person') ? 'selected' : ''; ?>>Qualifying Free Zone Person</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="small-business-relief-group">
                            <label for="small-business-relief">Small Business Relief applied for this financial year?</label>
                            <select id="small-business-relief" name="small_business_relief" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="Yes" <?php echo ($small_business_relief == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo ($small_business_relief == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        
                        <div id="not-registered-reason-section" style="display: none;">
                            <div class="form-group">
                                <label>State the reason for not registering for Corporate Tax:</label>
                                <div class="reason-options">
                                    <?php
                                    $reasons = [
                                        'reason1' => "The Entity's annual income from its business or licensed activities does not exceed AED 1 million per year",
                                        'reason2' => "The Entity is registered in the Family Business Register",
                                        'reason3' => "The Entity is a branch of a company registered for Corporate Tax",
                                        'reason4' => "The Entity is a tax-transparent unincorporated partnership",
                                        'reason5' => "The Entity derives its income from real estate investment activities that are not subject to licensing requirements.",
                                        'reason6' => "Other"
                                    ];
                                    
                                    foreach ($reasons as $id => $reasonText) {
                                        $checked = ($not_registered_reason == $reasonText) ? 'checked' : '';
                                        echo '<div class="reason-option">
                                            <input type="radio" id="' . $id . '" name="not_registered_reason" value="' . htmlspecialchars($reasonText) . '" onchange="handleReasonChange(); validateStep();" ' . $checked . '>
                                            <label for="' . $id . '">' . htmlspecialchars($reasonText) . '</label>
                                        </div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="form-group other-reason-field" id="other-reason-field" style="<?php echo ($not_registered_reason == 'Other') ? 'display: block;' : 'display: none;'; ?>">
                                <label for="other-reason-details">Please specify the reason:</label>
                                <textarea id="other-reason-details" name="other_reason_details" placeholder="Enter details about why the entity is not registered for Corporate Tax"><?php echo htmlspecialchars($other_reason_details); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="column" id="previous-tax-column" style="display: none;">
                        <div class="column-title">Previous Financial Period</div>
                        
                        <div class="form-group">
                            <label for="previous-vat-status">VAT Status (Previous Period)</label>
                            <select id="previous-vat-status" name="previous_vat_status" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($previous_vat_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($previous_vat_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="previous-excise-tax-status">Excise Tax Status (Previous Period)</label>
                            <select id="previous-excise-tax-status" name="previous_excise_tax_status" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($previous_excise_tax_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($previous_excise_tax_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="previous-corporate-tax-status">Corporate Tax Status (Previous Period)</label>
                            <select id="previous-corporate-tax-status" name="previous_corporate_tax_status" onchange="handlePreviousCorporateTaxStatusChange(); validateStep();">
                                <option value="">Select</option>
                                <option value="REGISTERED" <?php echo ($previous_corporate_tax_status == 'REGISTERED') ? 'selected' : ''; ?>>REGISTERED</option>
                                <option value="NOT REGISTERED" <?php echo ($previous_corporate_tax_status == 'NOT REGISTERED') ? 'selected' : ''; ?>>NOT REGISTERED</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="previous-corporate-tax-treatment-group" style="display: none;">
                            <label for="previous-corporate-tax-treatment">Corporate Tax Treatment (Previous Period)</label>
                            <select id="previous-corporate-tax-treatment" name="previous_corporate_tax_treatment" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="Standard Corporate Tax rates" <?php echo ($previous_corporate_tax_treatment == 'Standard Corporate Tax rates') ? 'selected' : ''; ?>>Standard Corporate Tax rates</option>
                                <option value="Qualifying Free Zone Person" id="previous-qualifying-freezone-option" <?php echo ($previous_corporate_tax_treatment == 'Qualifying Free Zone Person') ? 'selected' : ''; ?>>Qualifying Free Zone Person</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="previous-small-business-relief-group">
                            <label for="previous-small-business-relief">Small Business Relief applied for previous financial year?</label>
                            <select id="previous-small-business-relief" name="previous_small_business_relief" onchange="validateStep();">
                                <option value="">Select</option>
                                <option value="Yes" <?php echo ($previous_small_business_relief == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo ($previous_small_business_relief == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="missing-fields-container" id="missing-fields-box">
                <h4 class="missing">Missing Information Required</h4>
                <div id="missing-fields-list">
                    <!-- Missing fields will appear here -->
                </div>
            </div>
            
            <div class="missing-fields-container-first-year" id="missing-fields-box-first-year">
                <h4 class="missing">Missing Information Required</h4>
                <div id="missing-fields-list-first-year">
                    <!-- Missing fields will appear here -->
                </div>
            </div>
            
            <div class="navigation-buttons">
                <button type="button" onclick="window.location.href='FINANCIAL-YEAR.php'">
                    ← Previous Step
                </button>
                <button type="submit" id="next-step-btn" class="proceed-btn">
                    Proceed to Next Step →
                </button>
            </div>
        </div>
    </div>
</form>
    <script>
        const businessRegistrationStatus = document.getElementById('business-registration-status');
        const firstFinancialStatements = document.getElementById('first-financial-statements');
        const currentTaxSection = document.getElementById('current-tax-section');
        const currentTaxColumns = document.getElementById('current-tax-columns');
        const previousTaxColumn = document.getElementById('previous-tax-column');
        const vatStatus = document.getElementById('vat-status');
        const vatNumberGroup = document.getElementById('vat-number-group');
        const vatRegistrationNumber = document.getElementById('vat-registration-number');
        const exciseTaxStatus = document.getElementById('excise-tax-status');
        const corporateTaxStatus = document.getElementById('corporate-tax-status');
        const corporateTaxNumberGroup = document.getElementById('corporate-tax-number-group');
        const corporateTaxRegistrationNumber = document.getElementById('corporate-tax-registration-number');
        const corporateTaxTreatmentGroup = document.getElementById('corporate-tax-treatment-group');
        const corporateTaxTreatment = document.getElementById('corporate-tax-treatment');
        const qualifyingFreezoneOption = document.getElementById('qualifying-freezone-option');
        const smallBusinessReliefGroup = document.getElementById('small-business-relief-group');
        const smallBusinessRelief = document.getElementById('small-business-relief');
        const notRegisteredReasonSection = document.getElementById('not-registered-reason-section');
        const otherReasonField = document.getElementById('other-reason-field');
        const otherReasonDetails = document.getElementById('other-reason-details');
        
        const previousVatStatus = document.getElementById('previous-vat-status');
        const previousExciseTaxStatus = document.getElementById('previous-excise-tax-status');
        const previousCorporateTaxStatus = document.getElementById('previous-corporate-tax-status');
        const previousCorporateTaxTreatmentGroup = document.getElementById('previous-corporate-tax-treatment-group');
        const previousCorporateTaxTreatment = document.getElementById('previous-corporate-tax-treatment');
        const previousQualifyingFreezoneOption = document.getElementById('previous-qualifying-freezone-option');
        const previousSmallBusinessReliefGroup = document.getElementById('previous-small-business-relief-group');
        const previousSmallBusinessRelief = document.getElementById('previous-small-business-relief');
        
        const missingFieldsBox = document.getElementById('missing-fields-box');
        const missingFieldsBoxFirstYear = document.getElementById('missing-fields-box-first-year');
        const missingFieldsList = document.getElementById('missing-fields-list');
        const missingFieldsListFirstYear = document.getElementById('missing-fields-list-first-year');
        const nextStepBtn = document.getElementById('next-step-btn');

        const fieldMap = {
            'business-registration-status': 'Business Registration Status',
            'first-financial-statements': 'Are these the first financial statements being prepared?',
            'vat-status': 'VAT Status',
            'vat-registration-number': 'VAT Registration Number',
            'excise-tax-status': 'Excise Tax Status',
            'corporate-tax-status': 'Corporate Tax Status',
            'corporate-tax-registration-number': 'Corporate Tax Registration Number',
            'corporate-tax-treatment': 'Corporate Tax Treatment',
            'small-business-relief': 'Small Business Relief applied for this financial year?',
            'not-registered-reason': 'Reason for not registering for Corporate Tax',
            'other-reason-details': 'Reason details for not registering for Corporate Tax',
            'previous-vat-status': 'Previous Period VAT Status',
            'previous-excise-tax-status': 'Previous Period Excise Tax Status',
            'previous-corporate-tax-status': 'Previous Period Corporate Tax Status',
            'previous-corporate-tax-treatment': 'Previous Period Corporate Tax Treatment',
            'previous-small-business-relief': 'Previous Period Small Business Relief'
        };

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize based on saved values
            initializeFromSession();
            toggleFinancialYearFields();
            
            // Set event listeners
            businessRegistrationStatus.addEventListener('change', handleBusinessRegistrationChange);
            firstFinancialStatements.addEventListener('change', toggleFinancialYearFields);
            vatStatus.addEventListener('change', handleVatStatusChange);
            corporateTaxStatus.addEventListener('change', handleCorporateTaxStatusChange);
            
            document.querySelectorAll('input[name="not_registered_reason"]').forEach(radio => {
                radio.addEventListener('change', handleReasonChange);
            });
            
            document.querySelectorAll('select, input[type="text"], textarea').forEach(element => {
                element.addEventListener('change', validateStep);
                element.addEventListener('input', validateStep);
            });
            
            validateStep();
        });

        function initializeFromSession() {
            // Initialize VAT fields
            if (vatStatus.value === 'REGISTERED') {
                vatNumberGroup.style.display = 'block';
            }
            
            // Initialize Corporate Tax fields
            if (corporateTaxStatus.value === 'REGISTERED') {
                corporateTaxNumberGroup.style.display = 'block';
                corporateTaxTreatmentGroup.style.display = 'block';
                notRegisteredReasonSection.style.display = 'none';
                
                // Show Small Business Relief for REGISTERED status
                if (smallBusinessReliefGroup && businessRegistrationStatus.value !== 'Free Zone Licensed') {
                    smallBusinessReliefGroup.style.display = 'block';
                }
            } else if (corporateTaxStatus.value === 'NOT REGISTERED') {
                notRegisteredReasonSection.style.display = 'block';
                corporateTaxNumberGroup.style.display = 'none';
                corporateTaxTreatmentGroup.style.display = 'none';
                
                // Hide Small Business Relief for NOT REGISTERED status
                if (smallBusinessReliefGroup) {
                    smallBusinessReliefGroup.style.display = 'none';
                    smallBusinessRelief.value = '';
                }
            }
            
            // Initialize business registration
            handleBusinessRegistrationChange();
            
            // Initialize previous period fields if showing
            if (firstFinancialStatements.value === 'No') {
                previousTaxColumn.style.display = 'block';
                if (previousCorporateTaxStatus.value === 'REGISTERED') {
                    previousCorporateTaxTreatmentGroup.style.display = 'block';
                    
                    // Show Small Business Relief for REGISTERED status
                    if (previousSmallBusinessReliefGroup && businessRegistrationStatus.value !== 'Free Zone Licensed') {
                        previousSmallBusinessReliefGroup.style.display = 'block';
                    }
                } else {
                    previousCorporateTaxTreatmentGroup.style.display = 'none';
                    
                    // Hide Small Business Relief for NOT REGISTERED status
                    if (previousSmallBusinessReliefGroup) {
                        previousSmallBusinessReliefGroup.style.display = 'none';
                        previousSmallBusinessRelief.value = '';
                    }
                }
            }
        }

        function toggleFinancialYearFields() {
            const isFirst = firstFinancialStatements.value === 'Yes';
            
            if (isFirst) {
                previousTaxColumn.style.display = 'none';
            } else {
                previousTaxColumn.style.display = 'block';
            }
            
            validateStep();
        }

        function handleBusinessRegistrationChange() {
            const businessType = businessRegistrationStatus.value;
            
            if (businessType === 'Free Zone Licensed') {
                if (qualifyingFreezoneOption) {
                    qualifyingFreezoneOption.style.display = 'block';
                }
                if (previousQualifyingFreezoneOption) {
                    previousQualifyingFreezoneOption.style.display = 'block';
                }
                
                // Hide small business relief for free zone
                if (smallBusinessReliefGroup) {
                    smallBusinessReliefGroup.style.display = 'none';
                    smallBusinessRelief.value = '';
                }
                if (previousSmallBusinessReliefGroup) {
                    previousSmallBusinessReliefGroup.style.display = 'none';
                    previousSmallBusinessRelief.value = '';
                }
            } else {
                if (qualifyingFreezoneOption) {
                    qualifyingFreezoneOption.style.display = 'none';
                }
                if (previousQualifyingFreezoneOption) {
                    previousQualifyingFreezoneOption.style.display = 'none';
                }
                
                // Show small business relief only if Corporate Tax is REGISTERED or empty
                const shouldShowSmallBusinessRelief = corporateTaxStatus.value === 'REGISTERED' || corporateTaxStatus.value === '';
                const shouldShowPreviousSmallBusinessRelief = previousCorporateTaxStatus.value === 'REGISTERED' || previousCorporateTaxStatus.value === '';
                
                if (smallBusinessReliefGroup && shouldShowSmallBusinessRelief) {
                    smallBusinessReliefGroup.style.display = 'block';
                } else if (smallBusinessReliefGroup) {
                    smallBusinessReliefGroup.style.display = 'none';
                    smallBusinessRelief.value = '';
                }
                
                if (previousSmallBusinessReliefGroup && shouldShowPreviousSmallBusinessRelief) {
                    previousSmallBusinessReliefGroup.style.display = 'block';
                } else if (previousSmallBusinessReliefGroup) {
                    previousSmallBusinessReliefGroup.style.display = 'none';
                    previousSmallBusinessRelief.value = '';
                }
                
                // Clear qualifying free zone selection if not applicable
                if (corporateTaxTreatment && corporateTaxTreatment.value === 'Qualifying Free Zone Person') {
                    corporateTaxTreatment.value = '';
                }
                if (previousCorporateTaxTreatment && previousCorporateTaxTreatment.value === 'Qualifying Free Zone Person') {
                    previousCorporateTaxTreatment.value = '';
                }
            }
            
            validateStep();
        }

        function handleVatStatusChange() {
            if (vatStatus.value === 'REGISTERED') {
                vatNumberGroup.style.display = 'block';
            } else {
                vatNumberGroup.style.display = 'none';
                vatRegistrationNumber.value = '';
            }
            validateStep();
        }

        function handleCorporateTaxStatusChange() {
            if (corporateTaxStatus.value === 'REGISTERED') {
                corporateTaxNumberGroup.style.display = 'block';
                corporateTaxTreatmentGroup.style.display = 'block';
                notRegisteredReasonSection.style.display = 'none';
                
                // Show Small Business Relief for REGISTERED status (if not Free Zone)
                if (smallBusinessReliefGroup && businessRegistrationStatus.value !== 'Free Zone Licensed') {
                    smallBusinessReliefGroup.style.display = 'block';
                }
                
                document.querySelectorAll('input[name="not_registered_reason"]').forEach(radio => {
                    radio.checked = false;
                });
                otherReasonField.style.display = 'none';
                otherReasonDetails.value = '';
            } else if (corporateTaxStatus.value === 'NOT REGISTERED') {
                corporateTaxNumberGroup.style.display = 'none';
                corporateTaxTreatmentGroup.style.display = 'none';
                corporateTaxRegistrationNumber.value = '';
                corporateTaxTreatment.value = '';
                notRegisteredReasonSection.style.display = 'block';
                
                // Hide Small Business Relief for NOT REGISTERED status
                if (smallBusinessReliefGroup) {
                    smallBusinessReliefGroup.style.display = 'none';
                    smallBusinessRelief.value = ''; // Clear the value
                }
            } else {
                corporateTaxNumberGroup.style.display = 'none';
                corporateTaxTreatmentGroup.style.display = 'none';
                notRegisteredReasonSection.style.display = 'none';
                
                // Show Small Business Relief when no selection (if not Free Zone)
                if (smallBusinessReliefGroup && businessRegistrationStatus.value !== 'Free Zone Licensed') {
                    smallBusinessReliefGroup.style.display = 'block';
                }
            }
            validateStep();
        }

        function handlePreviousCorporateTaxStatusChange() {
            if (previousCorporateTaxStatus.value === 'REGISTERED') {
                previousCorporateTaxTreatmentGroup.style.display = 'block';
                
                // Show Small Business Relief for REGISTERED status (if not Free Zone)
                if (previousSmallBusinessReliefGroup && businessRegistrationStatus.value !== 'Free Zone Licensed') {
                    previousSmallBusinessReliefGroup.style.display = 'block';
                }
            } else {
                previousCorporateTaxTreatmentGroup.style.display = 'none';
                previousCorporateTaxTreatment.value = '';
                
                // Hide Small Business Relief for NOT REGISTERED status
                if (previousSmallBusinessReliefGroup) {
                    previousSmallBusinessReliefGroup.style.display = 'none';
                    previousSmallBusinessRelief.value = ''; // Clear the value
                }
            }
            validateStep();
        }

        function handleReasonChange() {
            const selectedReason = document.querySelector('input[name="not_registered_reason"]:checked');
            
            if (selectedReason && selectedReason.value === 'Other') {
                otherReasonField.style.display = 'block';
            } else {
                otherReasonField.style.display = 'none';
                otherReasonDetails.value = '';
            }
            validateStep();
        }

        function validateStep() {
            let errors = [];
            let missingFields = [];
            
            if (!businessRegistrationStatus.value) {
                missingFields.push(fieldMap['business-registration-status']);
            }
            
            if (!firstFinancialStatements.value) {
                missingFields.push(fieldMap['first-financial-statements']);
            }
            
            if (!vatStatus.value) {
                missingFields.push(fieldMap['vat-status']);
            } else {
                if (vatStatus.value === 'REGISTERED' && !vatRegistrationNumber.value.trim()) {
                    missingFields.push(fieldMap['vat-registration-number']);
                }
            }
            
            if (!exciseTaxStatus.value) {
                missingFields.push(fieldMap['excise-tax-status']);
            }
            
            if (!corporateTaxStatus.value) {
                missingFields.push(fieldMap['corporate-tax-status']);
            } else {
                if (corporateTaxStatus.value === 'REGISTERED') {
                    if (!corporateTaxRegistrationNumber.value.trim()) {
                        missingFields.push(fieldMap['corporate-tax-registration-number']);
                    }
                    
                    if (!corporateTaxTreatment.value) {
                        missingFields.push(fieldMap['corporate-tax-treatment']);
                    }
                    
                    // Only require Small Business Relief if it's visible and entity is not Free Zone
                    if (smallBusinessReliefGroup && 
                        smallBusinessReliefGroup.style.display !== 'none' && 
                        businessRegistrationStatus.value !== 'Free Zone Licensed' && 
                        !smallBusinessRelief.value) {
                        missingFields.push(fieldMap['small-business-relief']);
                    }
                } else if (corporateTaxStatus.value === 'NOT REGISTERED') {
                    const selectedReason = document.querySelector('input[name="not_registered_reason"]:checked');
                    if (!selectedReason) {
                        missingFields.push(fieldMap['not-registered-reason']);
                    } else {
                        if (selectedReason.value === 'Other' && !otherReasonDetails.value.trim()) {
                            missingFields.push(fieldMap['other-reason-details']);
                        }
                    }
                    // Small Business Relief is hidden for NOT REGISTERED, so not required
                }
            }
            
            // Only check Small Business Relief if it's visible and entity is not Free Zone
            if (smallBusinessReliefGroup && 
                smallBusinessReliefGroup.style.display !== 'none' && 
                businessRegistrationStatus.value !== 'Free Zone Licensed' && 
                corporateTaxStatus.value === 'REGISTERED' && 
                !smallBusinessRelief.value) {
                missingFields.push(fieldMap['small-business-relief']);
            }
            
            const isFirstYear = firstFinancialStatements.value === 'Yes';
            const missingContainer = isFirstYear ? missingFieldsBoxFirstYear : missingFieldsBox;
            const missingList = isFirstYear ? missingFieldsListFirstYear : missingFieldsList;
            
            if (!isFirstYear) {
                if (previousTaxColumn && previousTaxColumn.style.display === 'block') {
                    if (!previousVatStatus.value) {
                        missingFields.push(fieldMap['previous-vat-status']);
                    }
                    
                    if (!previousExciseTaxStatus.value) {
                        missingFields.push(fieldMap['previous-excise-tax-status']);
                    }
                    
                    if (!previousCorporateTaxStatus.value) {
                        missingFields.push(fieldMap['previous-corporate-tax-status']);
                    } else {
                        if (previousCorporateTaxStatus.value === 'REGISTERED' && !previousCorporateTaxTreatment.value) {
                            missingFields.push(fieldMap['previous-corporate-tax-treatment']);
                        }
                        // No reason required for NOT REGISTERED in previous period
                    }
                    
                    // Only check Previous Small Business Relief if it's visible and entity is not Free Zone
                    if (previousSmallBusinessReliefGroup && 
                        previousSmallBusinessReliefGroup.style.display !== 'none' && 
                        businessRegistrationStatus.value !== 'Free Zone Licensed' && 
                        previousCorporateTaxStatus.value === 'REGISTERED' && 
                        !previousSmallBusinessRelief.value) {
                        missingFields.push(fieldMap['previous-small-business-relief']);
                    }
                }
            }
            
            const businessType = businessRegistrationStatus.value;
            if (businessType && businessType !== 'Free Zone Licensed') {
                if (corporateTaxTreatment && corporateTaxTreatment.value === 'Qualifying Free Zone Person') {
                    errors.push("Qualifying Free Zone Person option is only available for FREE ZONE LICENSED entities (Current Period)");
                }
                if (!isFirstYear && previousTaxColumn && previousTaxColumn.style.display === 'block' && previousCorporateTaxTreatment && previousCorporateTaxTreatment.value === 'Qualifying Free Zone Person') {
                    errors.push("Qualifying Free Zone Person option is only available for FREE ZONE LICENSED entities (Previous Period)");
                }
            }
            
            if (errors.length > 0 || missingFields.length > 0) {
                missingContainer.style.display = 'block';
                // Hide the other container
                if (isFirstYear) {
                    missingFieldsBox.style.display = 'none';
                } else {
                    missingFieldsBoxFirstYear.style.display = 'none';
                }
                
                let displayList = '';
                
                if (missingFields.length > 0) {
                    displayList += missingFields.map(name => 
                        `<div class="missing-item">${name} is required</div>`
                    ).join('');
                }
                
                if (errors.length > 0) {
                    displayList += errors.map(error => 
                        `<div class="error-item">${error}</div>`
                    ).join('');
                }
                
                missingList.innerHTML = displayList;
                
                disableNextButton();
                return false;
            } else {
                missingFieldsBox.style.display = 'none';
                missingFieldsBoxFirstYear.style.display = 'none';
                missingFieldsList.innerHTML = '';
                missingFieldsListFirstYear.innerHTML = '';
                
                enableNextButton();
                return true;
            }
        }
        
        function disableNextButton() {
            nextStepBtn.disabled = true;
            nextStepBtn.style.opacity = '0.5';
            nextStepBtn.style.cursor = 'not-allowed';
        }

        function enableNextButton() {
            nextStepBtn.disabled = false;
            nextStepBtn.style.opacity = '1';
            nextStepBtn.style.cursor = 'pointer';
        }

        document.getElementById('tax-status-form').addEventListener('submit', function(e) {
            if (!validateStep()) {
                e.preventDefault();
                const isFirstYear = firstFinancialStatements.value === 'Yes';
                const missingContainer = isFirstYear ? missingFieldsBoxFirstYear : missingFieldsBox;
                if (missingContainer.style.display === 'block') {
                    missingContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>