<?php
// Include the database connection and entity data fetching
require_once 'fetch_entity_data.php';
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Due Diligence (CDD) - Audit Client Verification</title>
    <!-- إضافة خط Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            --ai-color: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--primary-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header and steps */
        .header-container {
            background-color: #fff;
            padding: 20px 40px 0 40px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo-header i {
            color: var(--accent-color);
            font-size: 26px;
            margin-right: 12px;
        }
        
        .logo-header h1 {
            font-size: 28px;
            color: var(--primary-color);
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            color: var(--secondary-color);
            font-size: 15px;
            margin-bottom: 25px;
            line-height: 1.7;
            padding: 0;
            background-color: transparent;
            border-left: none;
            border-radius: 0;
            font-weight: 400;
        }
        
        .steps-container-horizontal {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
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
        
        /* Main content */
        .main-content {
            padding: 40px;
            width: 100%;
            flex: 1;
        }
        
        .content-header {
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        
        .page-subtitle {
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 400;
        }
        
        /* Client information */
        .client-info {
            background-color: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
        }
        
        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 35px;
            margin-bottom: 30px;
        }
        
        .client-detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 14px;
            color: var(--secondary-color);
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .detail-value.provisional {
            color: var(--primary-color);
            padding: 8px 0;
        }
        
        .select-input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 16px;
            background-color: white;
            color: var(--primary-color);
            margin-top: 5px;
            transition: border-color 0.3s;
        }
        
        .select-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .buttons-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1f4bc2;
        }
        
        /* Accordion */
        .accordion-container {
            background-color: white;
            border-radius: 8px;
            padding: 0;
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .accordion-header {
            padding: 25px 35px;
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s;
        }
        
        .accordion-header:hover {
            background-color: var(--light-gray);
        }
        
        .accordion-title {
            font-weight: 600;
            font-size: 18px;
            color: var(--primary-color);
        }
        
        .accordion-content {
            display: none;
            padding: 0;
        }
        
        .accordion-container.open .accordion-content {
            display: block;
        }
        
        .side-by-side-layout {
            display: flex;
            min-height: 550px;
        }
        
        .document-viewer {
            width: 50%;
            padding: 35px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        
        .document-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .doc-btn {
            padding: 12px 24px;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-color);
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            position: relative;
        }
        
        .doc-btn:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        
        .doc-btn.active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .doc-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f5f5f5;
        }
        
        .doc-btn.disabled:hover {
            border-color: var(--border-color);
            color: var(--primary-color);
        }
        
        .doc-btn .tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s;
            margin-bottom: 5px;
            z-index: 1000;
        }
        
        .doc-btn .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .doc-btn.disabled:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .doc-preview {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 400px;
        }
        
        .doc-preview .placeholder {
            color: #666;
            font-size: 16px;
            text-align: center;
            padding: 20px;
        }
        
        .doc-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .document-info {
            margin-top: 15px;
            padding: 15px;
            background-color: var(--light-gray);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            display: none;
        }
        
        .document-info.active {
            display: none;
        }
        
        .document-info-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }
        
        .document-info-content {
            font-size: 14px;
            color: var(--secondary-color);
            line-height: 1.5;
        }
        
        /* Trade License Information Styles */
        .entity-info-section {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .entity-info-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3b82f6;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }
        
        .info-item {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-value {
            color: #1f2937;
            font-size: 15px;
            line-height: 1.5;
            padding: 8px 12px;
            background-color: #f9fafb;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        
        .shareholders-list {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
        }
        
        .shareholder-item {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .shareholder-item:last-child {
            border-bottom: none;
        }
        
       
        
        .shareholder-item .percentage {
            color: #6b7280;
            font-size: 14px;
            margin-left: 8px;
        }
        
        /* Style for "Not Registered" status */
        .info-value.not-registered {
            color: #ef4444;
            font-weight: 500;
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        
        /* Ownership & Management Table */
        .ownership-table-container {
            padding: 20px;
            overflow-y: auto;
        }
        
        .ownership-section {
            margin-bottom: 30px;
        }
        
        .ownership-section:last-child {
            margin-bottom: 0;
        }
        
        .ownership-title {
            font-weight: 600;
            font-size: 18px;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ownership-title i {
            color: var(--accent-color);
        }
        
        .ownership-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .ownership-table th {
            background-color: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .ownership-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--secondary-color);
            font-size: 14px;
        }
        
        .ownership-table tr:last-child td {
            border-bottom: none;
        }
        
        .ownership-table tr:hover {
            background-color: rgba(42, 91, 215, 0.02);
        }
        
        .ownership-percentage {
            font-weight: 600;
            color: var(--accent-color);
        }
        
        .ownership-role {
            display: inline-block;
            padding: 4px 12px;
            background-color: #e8f4fd;
            color: #1f4bc2;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .kyc-form {
            width: 50%;
            padding: 35px;
            overflow-y: auto;
        }
        
        .form-title {
            font-weight: 600;
            font-size: 20px;
            margin-bottom: 30px;
            color: var(--primary-color);
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 14px;
        }
        
        .form-value {
            padding: 14px 16px;
            background-color: var(--light-gray);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 16px;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        /* Verification table */
        .verification-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: 8px;
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
        }
        
        .verification-table th {
            background-color: white;
            padding: 25px 25px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
            font-size: 16px;
        }
        
        .verification-table td {
            padding: 30px 25px;
            border-bottom: 1px solid var(--border-color);
            color: var(--secondary-color);
            vertical-align: top;
        }
        
        .verification-table tr:last-child td {
            border-bottom: none;
        }
        
        .ai-check {
            color: var(--ai-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        
        .ai-check i {
            font-size: 18px;
        }
        
        .admin-verification {
            display: flex;
            gap: 15px;
        }
        
        .verification-option {
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid var(--border-color);
            background-color: white;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
        }
        
        .verification-option.verified {
            color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .verification-option.failed {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .verification-option.selected {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .verification-option.selected.failed {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        /* Economic sector */
        .economic-sector {
            background-color: white;
            border-radius: 8px;
            padding: 35px;
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
        }
        
        .sector-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .sector-select {
            width: 100%;
            padding: 14px 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 16px;
            background-color: white;
            color: var(--primary-color);
            transition: border-color 0.3s;
        }
        
        .sector-select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover:not(:disabled) {
            background-color: #218838;
        }
        
        .btn-success:disabled {
            background-color: #adb5bd;
            cursor: not-allowed;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: var(--primary-color);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Eligibility status */
        .eligibility-status {
            padding: 35px;
            border-radius: 8px;
            margin-bottom: 40px;
            display: none;
            border: 1px solid;
        }
        
        .status-title {
            font-weight: 600;
            font-size: 22px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-message {
            font-size: 16px;
            line-height: 1.7;
        }
        
        .status-eligible {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .status-not-eligible {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .status-pending {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        /* Verification History */
        .verification-history {
            margin-bottom: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }
        
        .history-title {
            font-weight: 600;
            font-size: 20px;
            margin-bottom: 25px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .history-title i {
            color: var(--accent-color);
        }
        
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .history-item {
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            position: relative;
        }
        
        .history-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 28px;
            top: 52px;
            bottom: -16px;
            width: 2px;
            background-color: var(--border-color);
            z-index: 1;
        }
        
        .history-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            z-index: 2;
            position: relative;
        }
        
        .history-icon.initial {
            background-color: var(--success-color);
            color: white;
        }
        
        .history-icon.update {
            background-color: var(--accent-color);
            color: white;
        }
        
        .history-content {
            flex: 1;
        }
        
        .history-action {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .history-timestamp {
            font-size: 14px;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .history-timestamp i {
            font-size: 12px;
        }
        
        .empty-history {
            text-align: center;
            padding: 40px;
            color: var(--secondary-color);
            font-style: italic;
        }
        
        /* File navigation controls */
        .file-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 6px;
            display: none;
        }
        
        .file-navigation.active {
            display: flex;
        }
        
        .file-counter {
            font-size: 14px;
            color: var(--secondary-color);
        }
        
        .nav-btn {
            padding: 8px 16px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .nav-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .nav-btn:hover:not(:disabled) {
            background-color: #1f4bc2;
        }
        
        /* Chat Assistant */
        .chat-assistant {
            display: none;
            position: fixed;
            bottom: 0;
            right: 0;
            width: 400px;
            height: 500px;
            background: white;
            border-top-left-radius: 12px;
            box-shadow: -5px -5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
            border-right: none;
            border-bottom: none;
            z-index: 1000;
        }
        
        .chat-header {
            padding: 20px;
            background: var(--light-gray);
            border-top-left-radius: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .chat-subtitle {
            font-size: 14px;
            color: var(--secondary-color);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .message-ai {
            background: var(--light-gray);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .message-user {
            background: var(--accent-color);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        
        .chat-input-container {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            font-size: 14px;
            outline: none;
        }
        
        .chat-input:focus {
            border-color: var(--accent-color);
        }
        
        .send-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .send-button:hover {
            background: #1f4bc2;
        }
        
        .chat-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(42, 91, 215, 0.3);
        }
        
        /* Disclaimer */
        .disclaimer {
            padding: 30px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--secondary-color);
            margin-top: 40px;
            border-top: 1px solid var(--border-color);
        }
        
        /* PDF viewer styles - UPDATED FOR EMBEDDED VIEWER */
        .pdf-viewer-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: auto;
        }
        
        .pdf-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            width: 100%;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .pdf-page-info {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .pdf-canvas {
            max-width: 100%;
            max-height: calc(100% - 80px);
            border: 1px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            margin: auto;
            display: block;
        }
        
        .pdf-nav-btn {
            padding: 8px 16px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pdf-nav-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .pdf-nav-btn:hover:not(:disabled) {
            background-color: #1f4bc2;
        }
        
        .pdf-zoom-controls {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pdf-zoom-btn {
            padding: 6px 12px;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: var(--primary-color);
        }
        
        .pdf-zoom-btn:hover {
            background-color: var(--light-gray);
        }
        
        .pdf-zoom-value {
            font-size: 14px;
            color: var(--secondary-color);
            min-width: 60px;
            text-align: center;
        }
        
        .pdf-download-btn {
            padding: 8px 16px;
            background-color: var(--success-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: auto;
        }
        
        .pdf-download-btn:hover {
            background-color: #218838;
        }
        
        /* Failed Reason Textbox */
        .failed-reason-container {
            background-color: white;
            border-radius: 8px;
            padding: 35px;
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
            display: none;
        }
        
        .failed-reason-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--danger-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .failed-reason-title i {
            font-size: 20px;
        }
        
        .failed-reason-textbox {
            width: 100%;
            padding: 14px 16px;
            border-radius: 6px;
            border: 1px solid var(--danger-color);
            font-size: 16px;
            background-color: #fff8f8;
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.3s;
        }
        
        .failed-reason-textbox:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .failed-reason-textbox::placeholder {
            color: #999;
        }
        
        /* New navigation buttons */
        .navigation-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            justify-content: flex-end;
        }
        
        .btn-info {
            background-color: var(--ai-color);
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        .btn-dark {
            background-color: #343a40;
            color: white;
        }
        
        .btn-dark:hover {
            background-color: #23272b;
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .chat-assistant {
                width: 350px;
                height: 450px;
            }
        }
        
        @media (max-width: 1024px) {
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
            
            .side-by-side-layout {
                flex-direction: column;
                min-height: auto;
            }
            
            .document-viewer, .kyc-form {
                width: 100%;
            }
            
            .document-viewer {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                min-height: 400px;
            }
            
            .chat-assistant {
                width: 100%;
                height: 400px;
                right: 0;
                border-radius: 0;
                border-top-right-radius: 12px;
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header-container, .main-content {
                padding: 20px;
            }
            
            .client-details {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .verification-table {
                display: block;
                overflow-x: auto;
            }
            
            .admin-verification {
                flex-direction: column;
                gap: 10px;
            }
            
            .verification-option {
                width: 100%;
                text-align: center;
            }
            
            .document-buttons {
                flex-direction: column;
            }
            
            .doc-btn {
                width: 100%;
            }
            
            .chat-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .pdf-controls {
                flex-direction: column;
                gap: 8px;
            }
            
            .pdf-download-btn {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
            
            .navigation-buttons {
                flex-direction: column;
            }
        }
        .client-detail-item{
            display: none;
        }
        .documentInfo{
            display: none;
        }
    </style>
    <!-- PDF.js library for PDF rendering -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>
    <!-- pako for gzip decompression -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js"></script>
</head>
<body>
    <!-- Header and steps -->
    <div class="header-container">
        <div class="logo-header">
            <i class="fas fa-user-check"></i>
            <h1>Client Due Diligence (CDD)</h1>
        </div>
        
        <div class="subtitle">
            This assessment is based on information disclosed during client onboarding and available verification results, without prejudice to any future findings.
        </div>
        
        <div class="steps-container-horizontal">
            <div class="step-item-horizontal <?php echo $screening_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">1</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Sanctions Screening Report</div>
                    <div class="step-status-horizontal"><?php echo $screening_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $ind_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">2</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Independence and Conflict of Interest Confirmation</div>
                    <div class="step-status-horizontal"><?php echo $ind_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $cdd_completed ? 'completed' : 'active'; ?>">
                <div class="step-number-horizontal">3</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Audit Client Verification</div>
                    <div class="step-status-horizontal"><?php echo $cdd_completed ? 'COMPLETED' : 'IN PROGRESS'; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main content -->
    <div class="main-content">
        <div class="content-header">
            <h2 class="page-title">Audit Client Verification</h2>
            <div class="page-subtitle">Complete the verification process for client onboarding</div>
        </div>
        
        <!-- Client information -->
        <div class="client-info">
            <div class="client-details">
                <div class="client-detail-item">
                    <div class="detail-label">Audit Client</div>
                    <div class="detail-value"><?php echo htmlspecialchars($entity['entity_name'] ?? 'Union Mall'); ?></div>
                </div>
                
                <div class="client-detail-item">
                    <div class="detail-label">Engagement Number</div>
                    <div class="detail-value provisional"><?php echo htmlspecialchars($engagement_number); ?></div>
                </div>
                
                <div class="client-detail-item">
                    <div class="detail-label">Client Status</div>
                    <select class="select-input" id="clientStatus">
                        <option value="New Client" <?php echo $client_status === 'New Client' ? 'selected' : ''; ?>>New Client</option>
                        <option value="Returning Client" <?php echo $client_status === 'Returning Client' ? 'selected' : ''; ?>>Returning Client</option>
                    </select>
                </div>
                
                <div class="client-detail-item">
                    <div class="detail-label">Business Registration Status</div>
                    <select class="select-input" id="businessRegistrationStatus">
                        <option value="">Select Registration Status</option>
                        <option value="Unlicensed Natural Person(s)" <?php echo ($entity['business_registration_status'] ?? '') === 'Unlicensed Natural Person(s)' ? 'selected' : ''; ?>>Unlicensed Natural Person(s)</option>
                        <option value="Mainland License-Sole Owner" <?php echo ($entity['business_registration_status'] ?? '') === 'Mainland License-Sole Owner' ? 'selected' : ''; ?>>Mainland License-Sole Owner</option>
                        <option value="Mainland Licensed-Multiple Owners" <?php echo ($entity['business_registration_status'] ?? '') === 'Mainland Licensed-Multiple Owners' ? 'selected' : ''; ?>>Mainland Licensed-Multiple Owners</option>
                        <option value="Free Zone Licensed" <?php echo ($entity['business_registration_status'] ?? '') === 'Free Zone Licensed' ? 'selected' : ''; ?>>Free Zone Licensed</option>
                    </select>
                </div>
            </div>
            
            <div class="buttons-container">
                <button class="btn btn-primary" id="viewClientInfoBtn">
                    <i class="fas fa-folder-open"></i> View Client Information and Documents
                </button>
            </div>
        </div>
        
        <!-- Accordion -->
        <div class="accordion-container" id="accordionContainer">
            <div class="accordion-header" id="accordionHeader">
                <div class="accordion-title">Client Information & Documents</div>
                <div>
                    <i class="fas fa-chevron-down" id="accordionIcon"></i>
                </div>
            </div>
            
            <div class="accordion-content" id="accordionContent">
                <div class="side-by-side-layout">
                    <!-- Document viewer section -->
                    <div class="document-viewer">
                        <div class="document-buttons" id="documentButtonsContainer">
                            <!-- Buttons will be dynamically added here by JavaScript -->
                        </div>
                        
                        <div class="doc-preview" id="docPreview">
                            <!-- Document preview will be shown here -->
                            <div class="placeholder">Select a document to view</div>
                        </div>
                        
                        <!-- File navigation for multiple files -->
                        <div class="file-navigation" id="fileNavigation">
                            <button class="nav-btn" id="prevFileBtn" disabled>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <div class="file-counter" id="fileCounter">File 1 of 1</div>
                            <button class="nav-btn" id="nextFileBtn" disabled>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="document-info" id="documentInfo">
                            <!-- Document information will be shown here -->
                        </div>
                    </div>
                    
                    <!-- KYC form or Trade License Information -->
                    <div class="kyc-form" id="rightPanel">
                        <!-- Default content - will be replaced based on selection -->
                        <div id="entityInfoContainer">
                            <!-- Content will be dynamically loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verification table -->
        <table class="verification-table">
            <thead>
                <tr>
                    <th>Verification Area</th>
                    <th>AI Result</th>
                    <th>Verified by Admin</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Identity, Documentation & Data Consistency Verification</strong><br>
                        <small>Verification that all submitted identification and licensing documents are valid, complete, and up to date, and that the information provided is consistent with the supporting documentation.</small>
                    </td>
                    <td>
                        <div class="ai-check">
                            <i class="fas fa-robot"></i> Verified
                        </div>
                    </td>
                    <td>
                        <div class="admin-verification" data-verification="identity">
                            <div class="verification-option verified" data-value="verified">Verified</div>
                            <div class="verification-option failed" data-value="failed">Failed</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Client Type & Eligibility</strong><br>
                        <small>Verification that the client does not fall within an excluded or higher-complexity entity or person category, including a Special Purpose Vehicle (SPV), an entity licensed offshore or outside the UAE (including a representative office), a public or private joint stock company, a financial institution, a Virtual Asset Service Provider (VASP), a designated non-financial business or profession (DNFBP), a non-profit or public-interest entity, an entity with a complex group or holding structure, any Politically Exposed Person (PEP), or any individual holding the nationality of, or otherwise associated with, a high-risk jurisdiction subject to a FATF call for action, including Iran, Myanmar, or the Democratic People's Republic of Korea (North Korea).</small>
                    </td>
                    <td>
                        <div class="ai-check">
                            <i class="fas fa-robot"></i> Verified
                        </div>
                    </td>
                    <td>
                        <div class="admin-verification" data-verification="eligibility">
                            <div class="verification-option verified" data-value="verified">Verified</div>
                            <div class="verification-option failed" data-value="failed">Failed</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Previous Auditor Concerns</strong><br>
                        <small>Verification that no concerns have been identified, to the best of available information, by the previous auditor regarding the entity's going concern or other matters indicating significant professional risk.</small>
                    </td>
                    <td>
                        <div class="ai-check">
                            <i class="fas fa-robot"></i> Verified
                        </div>
                    </td>
                    <td>
                        <div class="admin-verification" data-verification="auditor">
                            <div class="verification-option verified" data-value="verified">Verified</div>
                            <div class="verification-option failed" data-value="failed">Failed</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Economic sector -->
        <div class="economic-sector">
            <div class="sector-title">Economic Sector</div>
            <select class="sector-select" id="economicSector">
                <option value="">Select Economic Sector</option>
                <option value="Agriculture" <?php echo $economic_sector === 'Agriculture' ? 'selected' : ''; ?>>Agriculture, Forestry & Fishing</option>
                <option value="Mining" <?php echo $economic_sector === 'Mining' ? 'selected' : ''; ?>>Mining & Quarrying</option>
                <option value="Manufacturing" <?php echo $economic_sector === 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                <option value="Energy" <?php echo $economic_sector === 'Energy' ? 'selected' : ''; ?>>Energy</option>
                <option value="Construction" <?php echo $economic_sector === 'Construction' ? 'selected' : ''; ?>>Construction, Engineering & Machinery</option>
                <option value="Transportation" <?php echo $economic_sector === 'Transportation' ? 'selected' : ''; ?>>Transportation & Logistics</option>
                <option value="Technology" <?php echo $economic_sector === 'Technology' ? 'selected' : ''; ?>>Technology & Telecom</option>
                <option value="RealEstate" <?php echo $economic_sector === 'RealEstate' ? 'selected' : ''; ?>>Real Estate & Facility Services</option>
                <option value="Education" <?php echo $economic_sector === 'Education' ? 'selected' : ''; ?>>Education</option>
                <option value="HealthCare" <?php echo $economic_sector === 'HealthCare' ? 'selected' : ''; ?>>Health Care</option>
                <option value="Hospitality" <?php echo $economic_sector === 'Hospitality' ? 'selected' : ''; ?>>Hospitality</option>
                <option value="ProfessionalServices" <?php echo $economic_sector === 'ProfessionalServices' ? 'selected' : ''; ?>>Professional Services</option>
                <option value="PersonalServices" <?php echo $economic_sector === 'PersonalServices' ? 'selected' : ''; ?>>Personal & Community Services</option>
                <option value="Media" <?php echo $economic_sector === 'Media' ? 'selected' : ''; ?>>Media</option>
                <option value="SupportServices" <?php echo $economic_sector === 'SupportServices' ? 'selected' : ''; ?>>Support Services</option>
                <option value="GeneralTrading" <?php echo $economic_sector === 'GeneralTrading' ? 'selected' : ''; ?>>General Trading</option>
                <option value="Tourism" <?php echo $economic_sector === 'Tourism' ? 'selected' : ''; ?>>Tourism & Travel Services</option>
                <option value="Other" <?php echo $economic_sector === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        
        <!-- Failed Reason Container -->
        <div class="failed-reason-container" id="failedReasonContainer">
            <div class="failed-reason-title">
                <i class="fas fa-exclamation-circle"></i>
                Failure Reason for Identity, Documentation & Data Consistency Verification
            </div>
            <textarea 
                class="failed-reason-textbox" 
                id="failedReasonTextbox" 
                placeholder="Please provide detailed reason why the identity, documentation, or data consistency verification failed..."></textarea>
        </div>
        
        <!-- Action buttons -->
        <div class="action-buttons">
            <button class="btn btn-success" id="saveBtn" disabled>
                <i class="fas fa-save"></i> Save Verification
            </button>
            <button class="btn btn-warning" id="editBtn">
                <i class="fas fa-edit"></i> Edit Verification
            </button>
            <button class="btn btn-dark" id="backToDashboardBtn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </button>
            <button class="btn btn-info" id="viewAcceptanceBtn">
                <i class="fas fa-file-contract"></i> View Acceptance
            </button>
        </div>
        
        <!-- Eligibility status -->
        <div class="eligibility-status" id="eligibilityStatus">
            <div class="status-title" id="statusTitle"></div>
            <div class="status-message" id="statusMessage"></div>
        </div>
        
        <!-- Verification History -->
        <div class="verification-history" id="verificationHistory" style="<?php echo !empty($verification_history) ? 'display: block;' : ''; ?>">
            <div class="history-title">
                <i class="fas fa-history"></i> Verification History
            </div>
            <div class="history-list" id="historyList">
                <?php if (empty($verification_history)): ?>
                    <div class="empty-history" id="emptyHistory">
                        No verification history available yet. Save your first verification to start the history.
                    </div>
                <?php else: ?>
                    <?php foreach ($verification_history as $history): ?>
                        <div class="history-item">
                            <div class="history-icon <?php echo isset($history['isUpdate']) && $history['isUpdate'] ? 'update' : 'initial'; ?>">
                                <i class="<?php echo isset($history['isUpdate']) && $history['isUpdate'] ? 'fas fa-sync-alt' : 'fas fa-check-circle'; ?>"></i>
                            </div>
                            <div class="history-content">
                                <div class="history-action"><?php echo htmlspecialchars($history['action'] ?? 'Verified'); ?></div>
                                <div class="history-timestamp">
                                    <i class="far fa-clock"></i>
                                    <?php echo htmlspecialchars($history['timestamp'] ?? ''); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Navigation buttons -->
        
        
        <!-- Disclaimer -->
        <div class="disclaimer">
            This due diligence assessment follows Muhasba.com's risk-based client onboarding methodology. Certain AML risk factors are mitigated by platform design and eligibility requirements. Any residual or emerging risks that cannot be reasonably identified at the onboarding stage are assessed subsequently as part of the engagement risk assessment, based on the client's financial data, supporting documentation, explanations obtained, and professional judgment applied during the course of the engagement.
        </div>
    </div>
    
    <!-- Chat Toggle Button -->
    
    
    <!-- Chat Assistant -->
    <div class="chat-assistant" id="chatAssistant" style="display: none;">
        <div class="chat-header">
            <div class="chat-title">Hello, Saleh Amin</div>
            <div class="chat-subtitle">Chat with Sultan AI Assistant</div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message message-ai">
                Hello! I'm Sultan AI, your verification assistant. How can I help you with the client verification process today?
            </div>
        </div>
        
        <div class="chat-input-container">
            <input type="text" class="chat-input" id="chatInput" placeholder="Type your message here...">
            <button class="send-button" id="sendButton">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // State variables
            let isSaved = <?php echo $cdd_completed ? 'true' : 'false'; ?>;
            let verificationStatus = {
                identity: '<?php echo $identity_verification; ?>',
                eligibility: '<?php echo $eligibility_verification; ?>',
                auditor: '<?php echo $auditor_verification; ?>'
            };
            
            // File navigation state
            let currentFileIndex = 0;
            let currentDocumentType = '';
            let currentFilesArray = [];
            
            // PDF Viewer state
            let pdfDoc = null;
            let pdfPageNum = 1;
            let pdfPageRendering = false;
            let pdfPageNumPending = null;
            let pdfScale = 1.0; // Start with 100% zoom
            
            // Get eligibility status from PHP
            const phpEligibilityStatus = '<?php echo $eligibility_status; ?>';
            
            // Verification history array - initialize from PHP data
            let verificationHistory = <?php echo json_encode($verification_history ?: []); ?>;
            
            // Document data from PHP (ALREADY PROCESSED BY PHP)
            const eidPassports = <?php echo json_encode($entity['eid_passports'] ?? []); ?>;
            const tradeLicense = <?php echo json_encode($entity['trade_license'] ?? []); ?>;
            const authorizationLetter = <?php echo json_encode($entity['authorization_letter'] ?? []); ?>;
            const previousAuditorFiles = <?php echo json_encode($entity['previous_auditor_files'] ?? []); ?>;
            
            // Check if documents exist
            const hasEidPassports = eidPassports && eidPassports.length > 0;
            const hasTradeLicense = tradeLicense && tradeLicense.length > 0;
            const hasAuthorizationLetter = authorizationLetter && authorizationLetter.length > 0;
            const hasPreviousAuditorFiles = previousAuditorFiles && previousAuditorFiles.length > 0;
            
            // Entity data from PHP
            const entityName = '<?php echo htmlspecialchars($entity['entity_name'] ?? $entity['company_owner_name'] ?? ''); ?>';
            const licenseNumber = '<?php echo htmlspecialchars($entity['license_number'] ?? ''); ?>';
            const licenseIssueDate = '<?php echo htmlspecialchars($entity['license_issue_date_formatted'] ?? ''); ?>';
            const licenseExpiryDate = '<?php echo htmlspecialchars($entity['license_expiry_date_formatted'] ?? ''); ?>';
            const mainActivity = '<?php echo htmlspecialchars($entity['main_activity'] ?? ''); ?>';
            const currentFYStart = '<?php echo htmlspecialchars($entity['current_fy_start_date_formatted'] ?? ''); ?>';
            const currentFYEnd = '<?php echo htmlspecialchars($entity['current_fy_end_date_formatted'] ?? ''); ?>';
            const previousFYStart = '<?php echo htmlspecialchars($entity['previous_fy_start_date_formatted'] ?? ''); ?>';
            const previousFYEnd = '<?php echo htmlspecialchars($entity['previous_fy_end_date_formatted'] ?? ''); ?>';
            const corporateTaxStatus = '<?php echo htmlspecialchars($entity['current_year_corporate_tax_status'] ?? ''); ?>';
            const reasonNotRegisteredCT = '<?php echo htmlspecialchars($entity['current_year_reason_not_registered_ct'] ?? ''); ?>';
            const businessRegistrationStatus = '<?php echo htmlspecialchars($entity['business_registration_status'] ?? ''); ?>';
            const emirate = '<?php echo htmlspecialchars($entity['emirate'] ?? ''); ?>';
            const address = '<?php echo htmlspecialchars($entity['address'] ?? ''); ?>';
            const totalTurnover = '<?php echo isset($entity['total_turnover']) ? number_format($entity['total_turnover'], 2) : ''; ?>';
            
            // Ownership data from PHP
            // Ownership data from PHP - FIXED
// Ownership data from PHP - FIXED
// Ownership data from PHP - SIMPLEST FIX
const shareholders = <?php echo isset($entity['shareholders']) ? json_encode($entity['shareholders']) : '[]'; ?>;
const ubos = <?php echo isset($entity['ubos']) ? json_encode($entity['ubos']) : '[]'; ?>;
const managementControl = <?php 
    // Simple debugging - see what we have
    if (isset($entity['management_control'])) {
        echo "'" . addslashes($entity['management_control']) . "'";
    } else {
        echo "''";
    }
?>;
            
            // Parse JSON data if needed - FIXED MANAGEMENT CONTROL PARSING
            let shareholdersArray = [];
            let ubosArray = [];
            let managementControlArray = [];
            
            try {
                shareholdersArray = typeof shareholders === 'string' ? JSON.parse(shareholders) : shareholders;
                ubosArray = typeof ubos === 'string' ? JSON.parse(ubos) : ubos;
                
                // FIXED: Properly parse management control data
                if (managementControl && managementControl.trim() !== '') {
                    // Try to parse as JSON first
                    try {
                        const parsed = JSON.parse(managementControl);
                        
                        // Check if it's an array
                        if (Array.isArray(parsed)) {
                            managementControlArray = parsed;
                        } else if (typeof parsed === 'object' && parsed !== null) {
                            // If it's a single object, wrap it in an array
                            managementControlArray = [parsed];
                        } else if (typeof parsed === 'string') {
                            // If it's a string after parsing, check if it's "Same as above"
                            if (parsed.toLowerCase().includes('same as above') || 
                                parsed.toLowerCase().includes('same as') ||
                                parsed.toLowerCase().includes('same')) {
                                managementControlArray = [{name: parsed, role: 'Management Control'}];
                            } else {
                                managementControlArray = [{name: parsed, role: parsed}];
                            }
                        }
                    } catch (e) {
                        // If not valid JSON, check if it's a simple string
                        if (managementControl.toLowerCase().includes('same as above') || 
                            managementControl.toLowerCase().includes('same as') ||
                            managementControl.toLowerCase().includes('same')) {
                            managementControlArray = [{name: managementControl, role: 'Management Control'}];
                        } else {
                            managementControlArray = [{name: managementControl, role: managementControl}];
                        }
                    }
                }
            } catch (e) {
                console.error('Error parsing ownership data:', e);
            }
            
            // Chat state
            let chatVisible = false;
            
            // DOM elements
            const clientStatus = document.getElementById('clientStatus');
            const businessRegistrationStatusSelect = document.getElementById('businessRegistrationStatus');
            const viewClientInfoBtn = document.getElementById('viewClientInfoBtn');
            const accordionContainer = document.getElementById('accordionContainer');
            const accordionHeader = document.getElementById('accordionHeader');
            const accordionIcon = document.getElementById('accordionIcon');
            const documentButtonsContainer = document.getElementById('documentButtonsContainer');
            const docPreview = document.getElementById('docPreview');
            const documentInfo = document.getElementById('documentInfo');
            const fileNavigation = document.getElementById('fileNavigation');
            const prevFileBtn = document.getElementById('prevFileBtn');
            const nextFileBtn = document.getElementById('nextFileBtn');
            const fileCounter = document.getElementById('fileCounter');
            const rightPanel = document.getElementById('rightPanel');
            const entityInfoContainer = document.getElementById('entityInfoContainer');
            const verificationOptions = document.querySelectorAll('.verification-option');
            const saveBtn = document.getElementById('saveBtn');
            const editBtn = document.getElementById('editBtn');
            const printBtn = document.getElementById('printBtn');
            const economicSector = document.getElementById('economicSector');
            const eligibilityStatus = document.getElementById('eligibilityStatus');
            const statusTitle = document.getElementById('statusTitle');
            const statusMessage = document.getElementById('statusMessage');
            const verificationHistoryElement = document.getElementById('verificationHistory');
            const historyList = document.getElementById('historyList');
            const emptyHistory = document.getElementById('emptyHistory');
            
            // Failed reason elements
            const failedReasonContainer = document.getElementById('failedReasonContainer');
            const failedReasonTextbox = document.getElementById('failedReasonTextbox');
            
            // New navigation buttons
            const backToDashboardBtn = document.getElementById('backToDashboardBtn');
            const viewAcceptanceBtn = document.getElementById('viewAcceptanceBtn');
            
            // Chat elements
            const chatToggle = document.getElementById('chatToggle');
            const chatAssistant = document.getElementById('chatAssistant');
            const chatMessages = document.getElementById('chatMessages');
            const chatInput = document.getElementById('chatInput');
            const sendButton = document.getElementById('sendButton');
            
            // Utility Functions
            
            function getMissingDocumentMessage(documentType) {
                let message = '';
                let additionalInfo = '';
                
                switch(documentType) {
                    case 'identification':
                        message = 'Identification documents not available';
                        additionalInfo = 'Passport/ID documents were not uploaded during application.';
                        break;
                    case 'trade_license':
                        message = 'Trade License Information';
                        additionalInfo = `Trade License Number: ${licenseNumber || 'N/A'}<br>
                                         Issue Date: ${licenseIssueDate || 'N/A'}<br>
                                         Expiry Date: ${licenseExpiryDate || 'N/A'}<br>
                                         <small><em>Note: Trade license file was not uploaded</em></small>`;
                        break;
                    case 'authorization_letter':
                        message = 'Authorization Letter not available';
                        additionalInfo = 'Authorization letter was not uploaded during the application process.';
                        break;
                    case 'previous_auditor':
                        const clientStatusValue = clientStatus.value;
                        if (clientStatusValue === 'Returning Client') {
                            message = 'Previous Auditor Files';
                            additionalInfo = 'Not applicable for returning clients.';
                        } else {
                            message = 'Previous Auditor Files not available';
                            additionalInfo = 'No previous auditor files were uploaded.';
                        }
                        break;
                    default:
                        message = 'Select a document to view';
                        additionalInfo = '';
                }
                
                return `<h4>${message}</h4><p>${additionalInfo}</p>`;
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // SIMPLIFIED BASE64 HANDLING - PHP already processed it
            
            function processBase64Data(base64Data, isCompressed = false) {
                try {
                    // If data is already processed by PHP, use as-is
                    let processedData = base64Data;
                    
                    // Remove any data URL prefix if present
                    if (processedData.includes(',')) {
                        processedData = processedData.split(',')[1];
                    }
                    
                    // For compressed data, try to decompress
                    if (isCompressed) {
                        try {
                            // Convert base64 to Uint8Array
                            const binaryString = atob(processedData);
                            const bytes = new Uint8Array(binaryString.length);
                            for (let i = 0; i < binaryString.length; i++) {
                                bytes[i] = binaryString.charCodeAt(i);
                            }
                            
                            // Decompress using pako
                            const decompressed = pako.inflate(bytes);
                            
                            // Convert back to base64 string
                            let result = '';
                            for (let i = 0; i < decompressed.length; i++) {
                                result += String.fromCharCode(decompressed[i]);
                            }
                            return btoa(result);
                        } catch (e) {
                            console.warn('Decompression failed, using original data:', e);
                        }
                    }
                    
                    return processedData;
                } catch (error) {
                    console.error('Error processing base64 data:', error);
                    return base64Data;
                }
            }
            
            // PDF RENDERING FUNCTIONS - FIXED ZOOM ISSUES
            
            function createPDFEmbeddedViewer(base64Data, file) {
                // Clear previous preview
                docPreview.innerHTML = '';
                
                // Create PDF viewer container
                const pdfContainer = document.createElement('div');
                pdfContainer.className = 'pdf-viewer-container';
                pdfContainer.id = 'pdfViewerContainer';
                
                // Create PDF controls
                const pdfControls = document.createElement('div');
                pdfControls.className = 'pdf-controls';
                
                // Previous page button
                const prevPageBtn = document.createElement('button');
                prevPageBtn.className = 'pdf-nav-btn';
                prevPageBtn.id = 'prevPageBtn';
                prevPageBtn.innerHTML = '<i class="fas fa-chevron-left"></i> Prev';
                prevPageBtn.disabled = true;
                
                // Next page button
                const nextPageBtn = document.createElement('button');
                nextPageBtn.className = 'pdf-nav-btn';
                nextPageBtn.id = 'nextPageBtn';
                nextPageBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
                
                // Page info
                const pageInfo = document.createElement('div');
                pageInfo.className = 'pdf-page-info';
                pageInfo.id = 'pageInfo';
                pageInfo.textContent = 'Page: 1 / ?';
                
                // Zoom controls
                const zoomControls = document.createElement('div');
                zoomControls.className = 'pdf-zoom-controls';
                
                const zoomOutBtn = document.createElement('button');
                zoomOutBtn.className = 'pdf-zoom-btn';
                zoomOutBtn.id = 'zoomOutBtn';
                zoomOutBtn.innerHTML = '<i class="fas fa-search-minus"></i>';
                
                const zoomValue = document.createElement('div');
                zoomValue.className = 'pdf-zoom-value';
                zoomValue.id = 'zoomValue';
                zoomValue.textContent = '100%';
                
                const zoomInBtn = document.createElement('button');
                zoomInBtn.className = 'pdf-zoom-btn';
                zoomInBtn.id = 'zoomInBtn';
                zoomInBtn.innerHTML = '<i class="fas fa-search-plus"></i>';
                
                // Reset zoom button
                const resetZoomBtn = document.createElement('button');
                resetZoomBtn.className = 'pdf-zoom-btn';
                resetZoomBtn.id = 'resetZoomBtn';
                resetZoomBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                
                zoomControls.appendChild(zoomOutBtn);
                zoomControls.appendChild(zoomValue);
                zoomControls.appendChild(zoomInBtn);
                zoomControls.appendChild(resetZoomBtn);
                
                // Download button
                const downloadBtn = document.createElement('button');
                downloadBtn.className = 'pdf-download-btn';
                downloadBtn.id = 'pdfDownloadBtn';
                downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download';
                
                // Canvas for PDF rendering
                const canvas = document.createElement('canvas');
                canvas.className = 'pdf-canvas';
                canvas.id = 'pdfCanvas';
                
                // Assemble controls
                pdfControls.appendChild(prevPageBtn);
                pdfControls.appendChild(pageInfo);
                pdfControls.appendChild(nextPageBtn);
                pdfControls.appendChild(zoomControls);
                pdfControls.appendChild(downloadBtn);
                
                // Assemble container
                pdfContainer.appendChild(pdfControls);
                pdfContainer.appendChild(canvas);
                docPreview.appendChild(pdfContainer);
                
                // Initialize PDF.js rendering
                const processedBase64 = processBase64Data(base64Data);
                const pdfDataUri = `data:application/pdf;base64,${processedBase64}`;
                
                // Load PDF document
                const loadingTask = pdfjsLib.getDocument(pdfDataUri);
                loadingTask.promise.then(function(pdf) {
                    pdfDoc = pdf;
                    
                    // Update page info
                    document.getElementById('pageInfo').textContent = `Page: 1 / ${pdfDoc.numPages}`;
                    
                    // Enable/disable navigation buttons
                    document.getElementById('prevPageBtn').disabled = pdfPageNum <= 1;
                    document.getElementById('nextPageBtn').disabled = pdfPageNum >= pdfDoc.numPages;
                    
                    // Render first page
                    renderPDFPage(pdfPageNum);
                    
                    // Attach event listeners for PDF navigation
                    document.getElementById('prevPageBtn').addEventListener('click', () => {
                        if (pdfPageNum <= 1) return;
                        pdfPageNum--;
                        queueRenderPDFPage(pdfPageNum);
                    });
                    
                    document.getElementById('nextPageBtn').addEventListener('click', () => {
                        if (pdfPageNum >= pdfDoc.numPages) return;
                        pdfPageNum++;
                        queueRenderPDFPage(pdfPageNum);
                    });
                    
                    // Zoom controls - FIXED ZOOM FUNCTIONALITY
                    document.getElementById('zoomOutBtn').addEventListener('click', () => {
                        if (pdfScale > 0.5) {
                            pdfScale = Math.max(0.5, pdfScale - 0.25);
                            updateZoomValue();
                            queueRenderPDFPage(pdfPageNum);
                        }
                    });
                    
                    document.getElementById('zoomInBtn').addEventListener('click', () => {
                        if (pdfScale < 3.0) {
                            pdfScale = Math.min(3.0, pdfScale + 0.25);
                            updateZoomValue();
                            queueRenderPDFPage(pdfPageNum);
                        }
                    });
                    
                    // Reset zoom button
                    document.getElementById('resetZoomBtn').addEventListener('click', () => {
                        pdfScale = 1.0;
                        updateZoomValue();
                        queueRenderPDFPage(pdfPageNum);
                    });
                    
                    // Download button
                    document.getElementById('pdfDownloadBtn').addEventListener('click', () => {
                        const downloadLink = document.createElement('a');
                        downloadLink.href = pdfDataUri;
                        downloadLink.download = file.file_name || 'document.pdf';
                        document.body.appendChild(downloadLink);
                        downloadLink.click();
                        document.body.removeChild(downloadLink);
                    });
                    
                }).catch(function(error) {
                    console.error('Error loading PDF:', error);
                    // Fallback to simple download if PDF.js fails
                    createSimplePDFPreview(base64Data, file);
                });
            }
            
            function renderPDFPage(pageNum) {
                pdfPageRendering = true;
                
                // Using promise to fetch the page
                pdfDoc.getPage(pageNum).then(function(page) {
                    const canvas = document.getElementById('pdfCanvas');
                    const context = canvas.getContext('2d');
                    
                    // Calculate viewport with current scale
                    const viewport = page.getViewport({ scale: pdfScale });
                    
                    // Set canvas dimensions to match the viewport
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    // Set display size for CSS
                    canvas.style.height = viewport.height + 'px';
                    canvas.style.width = viewport.width + 'px';
                    
                    // Render PDF page into canvas context
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    
                    const renderTask = page.render(renderContext);
                    
                    renderTask.promise.then(function() {
                        pdfPageRendering = false;
                        
                        if (pdfPageNumPending !== null) {
                            // New page rendering is pending
                            renderPDFPage(pdfPageNumPending);
                            pdfPageNumPending = null;
                        }
                        
                        // Update page info
                        document.getElementById('pageInfo').textContent = `Page: ${pageNum} / ${pdfDoc.numPages}`;
                        
                        // Update navigation buttons
                        document.getElementById('prevPageBtn').disabled = pageNum <= 1;
                        document.getElementById('nextPageBtn').disabled = pageNum >= pdfDoc.numPages;
                    });
                }).catch(function(error) {
                    console.error('Error rendering PDF page:', error);
                    pdfPageRendering = false;
                });
            }
            
            function queueRenderPDFPage(pageNum) {
                if (pdfPageRendering) {
                    pdfPageNumPending = pageNum;
                } else {
                    renderPDFPage(pageNum);
                }
            }
            
            function updateZoomValue() {
                const zoomValue = document.getElementById('zoomValue');
                zoomValue.textContent = `${Math.round(pdfScale * 100)}%`;
            }
            
            // SIMPLIFIED DOCUMENT PREVIEW FUNCTION
            
            function createDocumentPreview(documentType, documentData = null, isDisabled = false, fileIndex = 0) {
                // Clear previous preview
                docPreview.innerHTML = '';
                
                if (!documentData || isDisabled) {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'placeholder';
                    placeholder.innerHTML = getMissingDocumentMessage(documentType);
                    docPreview.appendChild(placeholder);
                    
                    // Hide file navigation
                    fileNavigation.classList.remove('active');
                    return;
                }
                
                // Handle multiple files
                let filesArray = documentData;
                if (!Array.isArray(documentData)) {
                    filesArray = [documentData];
                }
                
                if (filesArray.length === 0) {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'placeholder';
                    placeholder.innerHTML = '<h4>No files available</h4>';
                    docPreview.appendChild(placeholder);
                    fileNavigation.classList.remove('active');
                    return;
                }
                
                // Store current state
                currentDocumentType = documentType;
                currentFilesArray = filesArray;
                currentFileIndex = fileIndex;
                
                // Get the specific file
                const file = filesArray[fileIndex];
                
                // Check if we have base64 data (either processed or original)
                const base64Data = file.processed_base64 || file.base64_data;
                
                if (!base64Data) {
                    // Show file info without preview
                    showFileInfoOnly(file, documentType);
                    return;
                }
                
                // Determine MIME type
                const mimeType = file.mime_type || 'image/png';
                const isCompressed = file.compressed || false;
                
                // Process the base64 data
                const processedBase64 = processBase64Data(base64Data, isCompressed);
                
                // Create image preview for images
                if (mimeType.startsWith('image/')) {
                    createSimpleImagePreview(processedBase64, mimeType, file);
                } else if (mimeType === 'application/pdf') {
                    // Use the new embedded PDF viewer
                    createPDFEmbeddedViewer(processedBase64, file);
                } else {
                    // For other file types, show download link
                    createSimpleDownloadPreview(file, processedBase64, mimeType);
                }
                
                // Update file navigation
                updateFileNavigation(filesArray, fileIndex);
                
                // Update document information
                updateDocumentInfo(documentType, file, filesArray.length > 1);
            }
            
            // SIMPLE IMAGE PREVIEW
            function createSimpleImagePreview(base64Data, mimeType, file) {
                const img = document.createElement('img');
                img.className = 'doc-image';
                img.src = `data:${mimeType};base64,${base64Data}`;
                img.alt = file.file_name || 'Document';
                
                // Create container for image and download button
                const container = document.createElement('div');
                container.style.width = '100%';
                container.style.height = '100%';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
                container.style.padding = '20px';
                
                // Image wrapper
                const imgWrapper = document.createElement('div');
                imgWrapper.style.flex = '1';
                imgWrapper.style.display = 'flex';
                imgWrapper.style.alignItems = 'center';
                imgWrapper.style.justifyContent = 'center';
                imgWrapper.style.width = '100%';
                imgWrapper.appendChild(img);
                
                // Download button
                const downloadLink = document.createElement('a');
                downloadLink.href = `data:${mimeType};base64,${base64Data}`;
                downloadLink.download = file.file_name || 'document';
                downloadLink.className = 'btn btn-primary';
                downloadLink.innerHTML = '<i class="fas fa-download"></i> Download';
                downloadLink.style.marginTop = '20px';
                
                container.appendChild(imgWrapper);
                container.appendChild(downloadLink);
                docPreview.appendChild(container);
            }
            
            // SIMPLE PDF PREVIEW (Fallback)
            function createSimplePDFPreview(base64Data, file) {
                const placeholder = document.createElement('div');
                placeholder.className = 'placeholder';
                placeholder.innerHTML = `
                    <h4>PDF Document</h4>
                    <p>File: ${file.file_name || 'Document'}</p>
                    <p>Type: PDF</p>
                    <p>Size: ${formatFileSize(file.size || 0)}</p>
                    <a href="data:application/pdf;base64,${base64Data}" 
                       download="${file.file_name || 'document.pdf'}"
                       class="btn btn-primary">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                `;
                docPreview.appendChild(placeholder);
            }
            
            // SIMPLE DOWNLOAD PREVIEW FOR OTHER FILES
            function createSimpleDownloadPreview(file, base64Data, mimeType) {
                const placeholder = document.createElement('div');
                placeholder.className = 'placeholder';
                placeholder.innerHTML = `
                    <h4>${file.file_name || 'Document'}</h4>
                    <p>Type: ${mimeType || 'Unknown'}</p>
                    <p>Size: ${formatFileSize(file.size || 0)}</p>
                    <p>Uploaded: ${file.uploaded_at || 'Unknown date'}</p>
                    <a href="data:${mimeType};base64,${base64Data}" 
                       download="${file.file_name || 'document'}"
                       class="btn btn-primary">
                        <i class="fas fa-download"></i> Download File
                    </a>
                `;
                docPreview.appendChild(placeholder);
            }
            
            // SIMPLE FILE INFO WITHOUT DATA
            function showFileInfoOnly(file, documentType) {
                const infoDiv = document.createElement('div');
                infoDiv.className = 'placeholder';
                infoDiv.innerHTML = `
                    <h4>${documentType.toUpperCase().replace('_', ' ')}</h4>
                    <p>File: ${file.file_name || 'Document'}</p>
                    <p>Type: ${file.mime_type || 'Unknown'}</p>
                    <p>Size: ${formatFileSize(file.size || 0)}</p>
                    <p>Uploaded: ${file.uploaded_at || 'Unknown date'}</p>
                    <p><em>Document preview not available (no data)</em></p>
                `;
                docPreview.innerHTML = '';
                docPreview.appendChild(infoDiv);
                
                // Hide file navigation for single files
                fileNavigation.classList.remove('active');
            }
            
            // File Navigation Functions
            
            function updateFileNavigation(filesArray, currentIndex) {
                if (filesArray.length > 1) {
                    fileNavigation.classList.add('active');
                    prevFileBtn.disabled = currentIndex === 0;
                    nextFileBtn.disabled = currentIndex === filesArray.length - 1;
                    fileCounter.textContent = `File ${currentIndex + 1} of ${filesArray.length}`;
                } else {
                    fileNavigation.classList.remove('active');
                }
            }
            
            function navigateToFile(direction) {
                let newIndex = currentFileIndex + direction;
                
                if (newIndex >= 0 && newIndex < currentFilesArray.length) {
                    createDocumentPreview(currentDocumentType, currentFilesArray, false, newIndex);
                }
            }
            
            // Update document information
            
            function updateDocumentInfo(documentType, file, hasMultipleFiles = false) {
                documentInfo.innerHTML = '';
                documentInfo.classList.add('active');
                
                let specificInfo = '';
                let fileInfo = '';
                
                if (file) {
                    fileInfo = `
                        <strong>File Name:</strong> ${file.file_name || 'N/A'}<br>
                        <strong>File Type:</strong> ${file.mime_type || 'Unknown'}<br>
                        <strong>File Size:</strong> ${formatFileSize(file.size || 0)}<br>
                        <strong>Uploaded:</strong> ${file.uploaded_at || 'Unknown date'}<br>
                    `;
                    
                    if (hasMultipleFiles) {
                        fileInfo += `<strong>File:</strong> ${currentFileIndex + 1} of ${currentFilesArray.length}<br>`;
                    }
                }
                
                switch(documentType) {
                    case 'identification':
                        specificInfo = 'Passport or Emirates ID document for identity verification.';
                        break;
                    case 'trade_license':
                        specificInfo = `Trade License Information:<br>
                                      • License Number: ${licenseNumber || 'N/A'}<br>
                                      • Issue Date: ${licenseIssueDate || 'N/A'}<br>
                                      • Expiry Date: ${licenseExpiryDate || 'N/A'}`;
                        break;
                    case 'authorization_letter':
                        specificInfo = 'Authorization letter granting permission for the audit engagement.';
                        break;
                    case 'previous_auditor':
                        specificInfo = 'Previous auditor files including management letters and communications.';
                        break;
                    default:
                        specificInfo = 'Document uploaded during client onboarding process.';
                }
                
                documentInfo.innerHTML = `
                    <div class="document-info-title">${documentType.toUpperCase().replace('_', ' ')} Document</div>
                    <div class="document-info-content">
                        ${specificInfo}<br><br>
                        ${fileInfo}
                    </div>
                `;
            }
            
            // Function to show KYC form
            function showKYCForm() {
                entityInfoContainer.innerHTML = `
                    <div class="form-title">Know Your Customer (KYC) Form</div>
                    
                    <div class="form-group">
                        <div class="form-label">Legal Entity Name</div>
                        <div class="form-value">${entityName || 'Not provided'}</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Trade License Number</div>
                        <div class="form-value">${licenseNumber || 'N/A'}</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Issue Date</div>
                        <div class="form-value">${licenseIssueDate || 'N/A'}</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Expiry Date</div>
                        <div class="form-value">${licenseExpiryDate || 'N/A'}</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Legal Form</div>
                        <div class="form-value"><?php echo htmlspecialchars($entity['mainland_company_type'] ?? 'Limited Liability Company (LLC)'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Registered Address</div>
                        <div class="form-value"><?php echo htmlspecialchars($entity['address'] ?? 'Business Bay, Dubai, United Arab Emirates'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Authorized Signatory</div>
                        <div class="form-value"><?php echo htmlspecialchars($main_owner ? 'Mr. ' . $main_owner : 'Mr. Ahmed Al Mansoori'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Nationality</div>
                        <div class="form-value"><?php echo htmlspecialchars($nationality ?: 'UAE'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Passport Number</div>
                        <div class="form-value"><?php echo htmlspecialchars($passport_number ?: 'A7894562'); ?></div>
                    </div>
                `;
            }
            
            // Function to show trade license information
            function showTradeLicenseInfo() {
                // Check if trade license files exist
                if (hasTradeLicense) {
                    // When trade license files ARE uploaded
                    entityInfoContainer.innerHTML = `
                        <div class="entity-info-section">
                            <h3>Entity Information</h3>
                            
                            <div class="info-item">
                                <label>Company Name:</label>
                                <span class="info-value">${entityName || 'Not provided'}</span>
                            </div>
                            
                            ${licenseNumber ? `
                            <div class="info-item">
                                <label>License Number:</label>
                                <span class="info-value">${licenseNumber}</span>
                            </div>
                            ` : ''}
                            
                            ${licenseIssueDate ? `
                            <div class="info-item">
                                <label>Issued Date:</label>
                                <span class="info-value">${licenseIssueDate}</span>
                            </div>
                            ` : ''}
                            
                            ${licenseExpiryDate ? `
                            <div class="info-item">
                                <label>Expiry Date:</label>
                                <span class="info-value">${licenseExpiryDate}</span>
                            </div>
                            ` : ''}
                            
                            ${mainActivity ? `
                            <div class="info-item">
                                <label>Main Activity:</label>
                                <span class="info-value">${mainActivity}</span>
                            </div>
                            ` : ''}
                            
                            ${shareholdersArray.length > 0 ? `
                            <div class="info-item">
                                <label>Shareholders:</label>
                                <div class="shareholders-list">
                                    ${shareholdersArray.map(shareholder => `
                                        <div class="shareholder-item">
                                            <span>${shareholder.name || 'Unnamed'}</span>
                                            ${shareholder.capital_percentage ? `<span class="percentage">(${shareholder.capital_percentage}%)</span>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${currentFYStart && currentFYEnd ? `
                            <div class="info-item">
                                <label>Current Financial Year:</label>
                                <span class="info-value">${currentFYStart} to ${currentFYEnd}</span>
                            </div>
                            ` : ''}
                            
                            ${previousFYStart && previousFYEnd ? `
                            <div class="info-item">
                                <label>Previous Financial Year:</label>
                                <span class="info-value">${previousFYStart} to ${previousFYEnd}</span>
                            </div>
                            ` : ''}
                            
                            <div class="info-item">
                                <label>Corporate Tax Status:</label>
                                <span class="info-value">${corporateTaxStatus || 'Not specified'}</span>
                            </div>
                            
                            ${(corporateTaxStatus === 'Not Registered' || corporateTaxStatus === 'Exempt' || corporateTaxStatus === 'Pending') && reasonNotRegisteredCT ? `
                            <div class="info-item">
                                <label>Reason:</label>
                                <span class="info-value">${reasonNotRegisteredCT}</span>
                            </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    // When NO trade license files are uploaded
                    entityInfoContainer.innerHTML = `
                        <div class="entity-info-section">
                            <h3>Entity Information</h3>
                            
                            <div class="info-item">
                                <label>Business Registration Status:</label>
                                <span class="info-value ${businessRegistrationStatus === 'Unlicensed Natural Person(s)' ? 'not-registered' : ''}">
                                    ${businessRegistrationStatus || 'Not Registered'}
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <label>Company Name / Owner:</label>
                                <span class="info-value">${entityName || 'Not provided'}</span>
                            </div>
                            
                            ${mainActivity ? `
                            <div class="info-item">
                                <label>Main Activity:</label>
                                <span class="info-value">${mainActivity}</span>
                            </div>
                            ` : ''}
                            
                            ${emirate ? `
                            <div class="info-item">
                                <label>Emirate:</label>
                                <span class="info-value">${emirate}</span>
                            </div>
                            ` : ''}
                            
                            ${address ? `
                            <div class="info-item">
                                <label>Address:</label>
                                <span class="info-value">${address}</span>
                            </div>
                            ` : ''}
                            
                            ${shareholdersArray.length > 0 ? `
                            <div class="info-item">
                                <label>Shareholders:</label>
                                <div class="shareholders-list">
                                    ${shareholdersArray.map(shareholder => `
                                        <div class="shareholder-item">
                                            <strong>${shareholder.name || 'Unnamed'}</strong>
                                            ${shareholder.capital_percentage ? `<span class="percentage">(${shareholder.capital_percentage}%)</span>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${currentFYStart && currentFYEnd ? `
                            <div class="info-item">
                                <label>Current Financial Year:</label>
                                <span class="info-value">${currentFYStart} to ${currentFYEnd}</span>
                            </div>
                            ` : ''}
                            
                            <div class="info-item">
                                <label>Corporate Tax Status:</label>
                                <span class="info-value">${corporateTaxStatus || 'Not specified'}</span>
                            </div>
                            
                            ${(corporateTaxStatus === 'Not Registered' || corporateTaxStatus === 'Exempt' || corporateTaxStatus === 'Pending') && reasonNotRegisteredCT ? `
                            <div class="info-item">
                                <label>Reason:</label>
                                <span class="info-value">${reasonNotRegisteredCT}</span>
                            </div>
                            ` : ''}
                        </div>
                    `;
                }
            }
            
            // Function to show ownership table - UPDATED TO REMOVE CAPITAL COLUMN
            function showOwnershipTable() {
                entityInfoContainer.innerHTML = `
                    <div class="form-title">Ownership & Management Structure</div>
                    
                    <div class="ownership-table-container">
                        ${generateShareholdersTable()}
                        ${generateUBOTable()}
                        ${generateManagementControlTable()}
                    </div>
                `;
            }
            
            // Function to clear right panel content (for Previous Auditor and Authorization Letter)
            function clearRightPanel() {
                entityInfoContainer.innerHTML = `
                    <div class="placeholder" style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 20px; color: #ccc;"></i>
                        <h4>No Additional Information Available</h4>
                        <p>This section does not contain additional information for the selected document type.</p>
                    </div>
                `;
            }
            
            // Function to generate shareholders table HTML - UPDATED TO REMOVE CAPITAL COLUMN
            function generateShareholdersTable() {
                if (!shareholdersArray || shareholdersArray.length === 0) {
                    return `
                        <div class="ownership-section">
                            <div class="ownership-title">
                                <i class="fas fa-users"></i> Shareholders
                            </div>
                            <div style="color: var(--secondary-color); font-style: italic; padding: 20px; text-align: center;">
                                No shareholders information available
                            </div>
                        </div>
                    `;
                }
                
                let tableRows = '';
                shareholdersArray.forEach((shareholder, index) => {
                    tableRows += `
                        <tr>
                            <td style="width: 50px;">${index + 1}</td>
                            <td>${shareholder.name || 'N/A'}</td>
                            <td>${shareholder.nationality || 'N/A'}</td>
                            <td>${shareholder.emirates_id || 'N/A'}</td>
                            <td>${shareholder.emirates_id_expiry || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                return `
                    <div class="ownership-section">
                        <div class="ownership-title">
                            <i class="fas fa-users"></i> Shareholders
                        </div>
                        <table class="ownership-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Shareholder Name</th>
                                    <th>Nationality</th>
                                    <th>EID Number</th>
                                    <th>EID Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // Function to generate UBO table HTML - UPDATED TO REMOVE CAPITAL COLUMN
            function generateUBOTable() {
                if (!ubosArray || ubosArray.length === 0) {
                    return `
                        <div class="ownership-section">
                            <div class="ownership-title">
                                <i class="fas fa-user-tie"></i> Ultimate Beneficial Owners (UBOs)
                            </div>
                            <div style="color: var(--secondary-color); font-style: italic; padding: 20px; text-align: center;">
                                No UBO information available
                            </div>
                        </div>
                    `;
                }
                
                let tableRows = '';
                ubosArray.forEach((ubo, index) => {
                    tableRows += `
                        <tr>
                            <td style="width: 50px;">${index + 1}</td>
                            <td>${ubo.name || 'N/A'}</td>
                            <td>${ubo.nationality || 'N/A'}</td>
                            <td>${ubo.emirates_id || 'N/A'}</td>
                            <td>${ubo.emirates_id_expiry || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                return `
                    <div class="ownership-section">
                        <div class="ownership-title">
                            <i class="fas fa-user-tie"></i> Ultimate Beneficial Owners (UBOs)
                        </div>
                        <table class="ownership-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>UBO Name</th>
                                    <th>Nationality</th>
                                    <th>EID Number</th>
                                    <th>EID Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // Function to generate management control table - FIXED TO HANDLE JSON STRING PROPERLY
            function generateManagementControlTable() {
                if (!managementControlArray || managementControlArray.length === 0) {
                    return `
                        <div class="ownership-section">
                            <div class="ownership-title">
                                <i class="fas fa-user-shield"></i> Management Control
                            </div>
                            <div style="color: var(--secondary-color); font-style: italic; padding: 20px; text-align: center;">
                                No management control information available
                            </div>
                        </div>
                    `;
                }
                
                // Check if it's the "Same as above" scenario (single object with name)
                if (managementControlArray.length === 1 && managementControlArray[0].name && 
                    (!managementControlArray[0].nationality || managementControlArray[0].name.toLowerCase().includes('same as'))) {
                    return `
                        <div class="ownership-section">
                            <div class="ownership-title">
                                <i class="fas fa-user-shield"></i> Management Control
                            </div>
                            <table class="ownership-table">
                                <thead>
                                    <tr>
                                        <th>Role/Position</th>
                                        <th>Person Responsible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Management Control</strong></td>
                                        <td>${managementControlArray[0].name}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                
                // For detailed scenario (array of people with details)
                let tableRows = '';
                managementControlArray.forEach((person, index) => {
                    // Ensure person is an object
                    if (typeof person === 'string') {
                        // If it's a string, treat it as name
                        tableRows += `
                            <tr>
                                <td style="width: 50px;">${index + 1}</td>
                                <td>${person}</td>
                                <td>N/A</td>
                                <td>N/A</td>
                                <td>N/A</td>
                                <td>N/A</td>
                                <td>N/A</td>
                            </tr>
                        `;
                    } else if (typeof person === 'object' && person !== null) {
                        // If it's an object with properties
                        tableRows += `
                            <tr>
                                <td style="width: 50px;">${index + 1}</td>
                                <td>${person.name || person.Name || 'N/A'}</td>
                                <td>${person.nationality || person.Nationality || 'N/A'}</td>
                                <td>${person.emirates_id || person.emirates_id || person.EID || 'N/A'}</td>
                                <td>${person.emirates_id_expiry || person.emirates_id_expiry || person.eid_expiry || 'N/A'}</td>
                                <td>${person.position || person.Position || person.role || 'N/A'}</td>
                                <td>${person.pep_status || person.pep_status || 'N/A'}</td>
                            </tr>
                        `;
                    }
                });
                
                return `
                    <div class="ownership-section">
                        <div class="ownership-title">
                            <i class="fas fa-user-shield"></i> Management Control
                        </div>
                        <table class="ownership-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Name</th>
                                    <th>Nationality</th>
                                    <th>EID Number</th>
                                    <th>EID Expiry Date</th>
                                    <th>Role/Position</th>
                                    <th>PEP Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            // Function to update document buttons
            function updateDocumentButtons() {
                documentButtonsContainer.innerHTML = '';
                
                const clientStatusValue = clientStatus.value;
                
                const buttons = [
                    {
                        id: 'viewIdBtn',
                        text: 'View Identification',
                        type: 'identification',
                        data: hasEidPassports ? eidPassports : null,
                        disabled: !hasEidPassports,
                        tooltip: !hasEidPassports ? 'Identification documents not uploaded' : ''
                    },
                    {
                        id: 'viewLicenseBtn',
                        text: 'View Trade License',
                        type: 'trade_license',
                        data: hasTradeLicense ? tradeLicense : null,
                        disabled: false,
                        tooltip: hasTradeLicense ? '' : 'Trade license information available'
                    },
                    {
                        id: 'viewAuthLetterBtn',
                        text: 'View Authorization Letter',
                        type: 'authorization_letter',
                        data: hasAuthorizationLetter ? authorizationLetter : null,
                        disabled: !hasAuthorizationLetter,
                        tooltip: !hasAuthorizationLetter ? 'Authorization letter not uploaded' : ''
                    },
                    {
                        id: 'viewPrevAuditorBtn',
                        text: 'View Previous Auditor File',
                        type: 'previous_auditor',
                        data: hasPreviousAuditorFiles ? previousAuditorFiles : null,
                        disabled: !hasPreviousAuditorFiles || clientStatusValue === 'Returning Client',
                        tooltip: !hasPreviousAuditorFiles ? 'No previous auditor files uploaded' : 
                                 clientStatusValue === 'Returning Client' ? 'Not applicable for returning clients' : ''
                    }
                ];
                
                buttons.forEach((buttonConfig, index) => {
                    const button = document.createElement('button');
                    button.className = 'doc-btn' + (buttonConfig.disabled ? ' disabled' : '') + (index === 0 ? ' active' : '');
                    button.id = buttonConfig.id;
                    button.textContent = buttonConfig.text;
                    button.dataset.type = buttonConfig.type;
                    button.dataset.disabled = buttonConfig.disabled;
                    
                    if (buttonConfig.data) {
                        button.dataset.data = JSON.stringify(buttonConfig.data);
                    }
                    
                    if (buttonConfig.disabled && buttonConfig.tooltip) {
                        const tooltip = document.createElement('div');
                        tooltip.className = 'tooltip';
                        tooltip.textContent = buttonConfig.tooltip;
                        button.appendChild(tooltip);
                    }
                    
                    documentButtonsContainer.appendChild(button);
                });
                
                // Set identification button as active by default
                const identificationButton = document.getElementById('viewIdBtn');
                if (identificationButton) {
                    identificationButton.classList.add('active');
                    const data = identificationButton.dataset.data ? JSON.parse(identificationButton.dataset.data) : null;
                    const isDisabled = identificationButton.dataset.disabled === 'true';
                    createDocumentPreview('identification', data, isDisabled);
                    // Show ownership table (associated with identification) by default
                    showOwnershipTable();
                }
                
                attachDocumentButtonListeners();
            }
            
            function attachDocumentButtonListeners() {
                const docButtons = document.querySelectorAll('.doc-btn');
                
                docButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (this.classList.contains('disabled')) {
                            return;
                        }
                        
                        docButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                        
                        const documentType = this.dataset.type;
                        const documentData = this.dataset.data ? JSON.parse(this.dataset.data) : null;
                        const isDisabled = this.dataset.disabled === 'true';
                        
                        createDocumentPreview(documentType, documentData, isDisabled);
                        
                        // Update right panel based on selected document type
                        if (documentType === 'identification') {
                            showOwnershipTable();
                        } else if (documentType === 'trade_license') {
                            showTradeLicenseInfo();
                        } else if (documentType === 'authorization_letter' || documentType === 'previous_auditor') {
                            // Clear right panel for Authorization Letter and Previous Auditor
                            clearRightPanel();
                        } else {
                            showKYCForm();
                        }
                    });
                });
            }
            
            // File navigation event listeners
            prevFileBtn.addEventListener('click', () => navigateToFile(-1));
            nextFileBtn.addEventListener('click', () => navigateToFile(1));
            
            // Function to show/hide failed reason textbox
            function toggleFailedReasonContainer(show) {
                if (show) {
                    failedReasonContainer.style.display = 'block';
                    // Scroll to the failed reason container
                    failedReasonContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    failedReasonContainer.style.display = 'none';
                }
            }
            
            // Function to check if identity verification is failed
            function checkIdentityVerificationStatus() {
                if (verificationStatus.identity === 'failed') {
                    toggleFailedReasonContainer(true);
                } else {
                    toggleFailedReasonContainer(false);
                }
            }
            
            // Initialize verification options from PHP data
            function initializeVerificationOptions() {
                Object.keys(verificationStatus).forEach(type => {
                    if (verificationStatus[type]) {
                        const option = document.querySelector(`.admin-verification[data-verification="${type}"] .verification-option[data-value="${verificationStatus[type]}"]`);
                        if (option) {
                            option.classList.add('selected');
                        }
                    }
                });
                
                // Check if identity verification is already failed on load
                if (verificationStatus.identity === 'failed') {
                    checkIdentityVerificationStatus();
                }
            }
            
            // Initialize eligibility status from PHP
            function initializeEligibilityStatus() {
                if (phpEligibilityStatus) {
                    let statusClass, title, message;
                    
                    switch(phpEligibilityStatus) {
                        case 'not_eligible':
                            statusClass = 'status-not-eligible';
                            title = 'Status: Not Eligible to Proceed';
                            message = 'Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform\'s acceptable threshold.';
                            break;
                        case 'pending':
                            statusClass = 'status-pending';
                            title = 'Status: Pending - Action Required';
                            message = 'Some of the submitted identification or licensing documents could not be fully verified, or the information provided does not fully align with the supporting documents, or appears to be incomplete or outdated. Kindly review and update the documents and information accordingly to proceed with the onboarding process.';
                            break;
                        case 'eligible':
                        default:
                            statusClass = 'status-eligible';
                            title = 'Status: Eligible to proceed';
                            message = 'Congratulations! You have successfully completed Stage One. You may now proceed to the Entity Portal to continue with Stage Two, where you will be requested to submit the required financial data.';
                            break;
                    }
                    
                    eligibilityStatus.className = `eligibility-status ${statusClass}`;
                    statusTitle.textContent = title;
                    statusMessage.textContent = message;
                    eligibilityStatus.style.display = 'block';
                    
                    if (isSaved) {
                        saveBtn.disabled = true;
                    }
                }
            }
            
            // Function to get current date and time
            function getCurrentDateTime() {
                const now = new Date();
                const date = now.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                const time = now.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                return `${date} ${time}`;
            }
            
            // Function to format timestamp for display
            function formatTimestampForDisplay(timestamp) {
                return timestamp;
            }
            
            // Function to add verification history
            function addVerificationHistory(isUpdate = false) {
                const timestamp = getCurrentDateTime();
                const formattedTimestamp = formatTimestampForDisplay(timestamp);
                
                const historyItem = {
                    id: Date.now(),
                    action: isUpdate ? 'Update Verified by Saleh Amin' : 'Verified by Saleh Amin',
                    timestamp: formattedTimestamp,
                    isUpdate: isUpdate,
                    date: timestamp
                };
                
                verificationHistory.push(historyItem);
                updateHistoryDisplay();
            }
            
            // Function to update history display
            function updateHistoryDisplay() {
                if (verificationHistory.length > 0) {
                    if (emptyHistory) emptyHistory.style.display = 'none';
                }
                
                const existingItems = historyList.querySelectorAll('.history-item');
                existingItems.forEach(item => item.remove());
                
                const sortedHistory = [...verificationHistory].sort((a, b) => new Date(b.date) - new Date(a.date));
                
                sortedHistory.forEach(item => {
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item';
                    historyItem.dataset.id = item.id;
                    
                    const iconClass = item.isUpdate ? 'fas fa-sync-alt' : 'fas fa-check-circle';
                    const iconType = item.isUpdate ? 'update' : 'initial';
                    
                    historyItem.innerHTML = `
                        <div class="history-icon ${iconType}">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="history-content">
                            <div class="history-action">${item.action}</div>
                            <div class="history-timestamp">
                                <i class="far fa-clock"></i>
                                ${item.timestamp}
                            </div>
                        </div>
                    `;
                    
                    historyList.appendChild(historyItem);
                });
                
                if (verificationHistory.length > 0) {
                    verificationHistoryElement.style.display = 'block';
                }
            }
            
            // Initial setup
            updateDocumentButtons();
            initializeVerificationOptions();
            initializeEligibilityStatus();
            updateHistoryDisplay();
            
            // Event Listeners for navigation buttons
            backToDashboardBtn.addEventListener('click', function() {
                // Navigate back to dashboard
                window.location.href = 'dashboard.php'; // Change to your actual dashboard URL
            });
            
            viewAcceptanceBtn.addEventListener('click', function() {
                // Open acceptance.php in a new tab
                window.open('acceptence.php', '_blank');
            });
            
            clientStatus.addEventListener('change', updateDocumentButtons);
            
            viewClientInfoBtn.addEventListener('click', function() {
                accordionContainer.classList.add('open');
                accordionIcon.className = 'fas fa-chevron-up';
            });
            
            accordionHeader.addEventListener('click', function() {
                accordionContainer.classList.toggle('open');
                accordionIcon.className = accordionContainer.classList.contains('open') 
                    ? 'fas fa-chevron-up' 
                    : 'fas fa-chevron-down';
            });
            
            verificationOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const parent = this.parentElement;
                    const verificationType = parent.getAttribute('data-verification');
                    const value = this.getAttribute('data-value');
                    
                    const siblings = parent.querySelectorAll('.verification-option');
                    siblings.forEach(sibling => {
                        sibling.classList.remove('selected');
                    });
                    
                    this.classList.add('selected');
                    verificationStatus[verificationType] = value;
                    isSaved = false;
                    
                    // Check if identity verification is failed
                    if (verificationType === 'identity') {
                        checkIdentityVerificationStatus();
                    }
                    
                    checkSaveButtonStatus();
                });
            });
            
            economicSector.addEventListener('change', function() {
                isSaved = false;
                checkSaveButtonStatus();
            });
            
            // Event listener for failed reason textbox
            failedReasonTextbox.addEventListener('input', function() {
                isSaved = false;
                checkSaveButtonStatus();
            });
            
            function checkSaveButtonStatus() {
                const allVerificationsSelected = 
                    verificationStatus.identity !== null && 
                    verificationStatus.identity !== '' &&
                    verificationStatus.eligibility !== null && 
                    verificationStatus.eligibility !== '' &&
                    verificationStatus.auditor !== null &&
                    verificationStatus.auditor !== '';
                
                const sectorSelected = economicSector.value !== '';
                
                // Check if identity verification is failed and reason is provided
                const identityFailedWithReason = verificationStatus.identity === 'failed' 
                    ? failedReasonTextbox.value.trim() !== ''
                    : true;
                
                if (allVerificationsSelected && sectorSelected && identityFailedWithReason && !isSaved) {
                    saveBtn.disabled = false;
                    eligibilityStatus.style.display = 'none';
                } else {
                    saveBtn.disabled = true;
                }
            }
            
            checkSaveButtonStatus();
            
            saveBtn.addEventListener('click', function() {
                if (isSaved) {
                    alert('Cannot save twice. Please edit first.');
                    return;
                }
                
                // Validate failed reason if identity verification is failed
                if (verificationStatus.identity === 'failed' && failedReasonTextbox.value.trim() === '') {
                    alert('Please provide a reason for failing the Identity, Documentation & Data Consistency Verification.');
                    failedReasonTextbox.focus();
                    return;
                }
                
                let statusClass, title, message;
                
                if (verificationStatus.eligibility === 'failed' || verificationStatus.auditor === 'failed') {
                    statusClass = 'status-not-eligible';
                    title = 'Status: Not Eligible to Proceed';
                    message = 'Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform\'s acceptable threshold.';
                } else if (verificationStatus.identity === 'failed') {
                    statusClass = 'status-pending';
                    title = 'Status: Pending - Action Required';
                    message = 'Some of the submitted identification or licensing documents could not be fully verified, or the information provided does not fully align with the supporting documents, or appears to be incomplete or outdated. Kindly review and update the documents and information accordingly to proceed with the onboarding process.';
                } else {
                    statusClass = 'status-eligible';
                    title = 'Status: Eligible to proceed';
                    message = 'Congratulations! You have successfully completed Stage One. You may now proceed to the Entity Portal to continue with Stage Two, where you will be requested to submit the required financial data.';
                }
                
                eligibilityStatus.className = `eligibility-status ${statusClass}`;
                statusTitle.textContent = title;
                statusMessage.textContent = message;
                eligibilityStatus.style.display = 'block';
                
                saveToDatabase(statusClass, title, message);
                
                const isFirstSave = verificationHistory.length === 0;
                addVerificationHistory(!isFirstSave);
                
                isSaved = true;
                saveBtn.disabled = true;
                
                setTimeout(() => {
                    const currentStep = document.querySelector('.step-item-horizontal.active');
                    if (currentStep) {
                        currentStep.classList.remove('active');
                        currentStep.classList.add('completed');
                        
                        const stepStatus = currentStep.querySelector('.step-status-horizontal');
                        stepStatus.textContent = 'COMPLETED';
                        stepStatus.style.color = '#28a745';
                    }
                }, 1500);
            });
            
            function saveToDatabase(statusClass, title, message) {
                const entityId = <?php echo $entity_id; ?>;
                const adminId = 1;
                
                let eligibilityStatusDb = '';
                if (statusClass.includes('not-eligible')) {
                    eligibilityStatusDb = 'not_eligible';
                } else if (statusClass.includes('pending')) {
                    eligibilityStatusDb = 'pending';
                } else {
                    eligibilityStatusDb = 'eligible';
                }
                
                // Include failed reason in the data
                const failedReason = verificationStatus.identity === 'failed' ? failedReasonTextbox.value.trim() : '';
                
                const data = {
                    entity_id: entityId,
                    admin_id: adminId,
                    identity_verification: verificationStatus.identity,
                    eligibility_verification: verificationStatus.eligibility,
                    auditor_verification: verificationStatus.auditor,
                    economic_sector: economicSector.value,
                    eligibility_status: eligibilityStatusDb,
                    verification_history: verificationHistory,
                    failed_reason: failedReason // Add failed reason to data
                };
                
                fetch('save_verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        console.log('Verification saved successfully');
                    } else {
                        console.error('Error saving verification:', result.error);
                        alert('Error saving verification. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving verification. Please try again.');
                });
            }
            
            editBtn.addEventListener('click', function() {
                if (!isSaved) {
                    alert('No saved verification to edit. Please save first.');
                    return;
                }
                
                isSaved = false;
                checkSaveButtonStatus();
                eligibilityStatus.style.display = 'none';
                alert('You can now edit the verification and save again.');
            });
            
            printBtn.addEventListener('click', function() {
                window.print();
            });
            
            // Chat functionality
            chatToggle.addEventListener('click', function() {
                chatVisible = !chatVisible;
                if (chatVisible) {
                    chatAssistant.style.display = 'flex';
                    chatToggle.innerHTML = '<i class="fas fa-times"></i>';
                } else {
                    chatAssistant.style.display = 'none';
                    chatToggle.innerHTML = '<i class="fas fa-comment-dots"></i>';
                }
            });
            
            function addMessage(text, isUser = false) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${isUser ? 'message-user' : 'message-ai'}`;
                messageDiv.textContent = text;
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            function getAIResponse(userMessage) {
                const responses = {
                    'help': 'I can help you with:\n1. Understanding verification requirements\n2. Document submission guidelines\n3. Eligibility criteria\n4. Economic sector classification\n5. Troubleshooting verification issues\n\nWhat specific assistance do you need?',
                    'documents': 'Required documents include:\n• Valid Identification Document\n• Trade License (if applicable)\n• Authorization Letter (if uploaded)\n• Previous Auditor Files (for new clients)\n• Complete KYC Form\n\nMake sure all documents are clear, valid, and up-to-date.',
                    'eligibility': 'Eligibility criteria:\n• Must not be an excluded entity type\n• No previous auditor concerns\n• Complete and valid documentation\n• Appropriate economic sector classification\n\nIs there a specific eligibility question you have?',
                    'verification': 'Verification process:\n1. AI verifies document authenticity\n2. Admin reviews AI results\n3. Admin makes final verification decision\n4. Economic sector selection\n5. Save and complete verification\n\nWhere are you in the process?',
                    'sector': 'Economic sectors are categorized based on business activities. Select the most appropriate sector for the client\'s primary business activity. If unsure, choose "Other" and provide details.',
                    'save': 'To save verification:\n1. Complete all admin verifications (Verified/Failed)\n2. Select economic sector\n3. Click "Save Verification"\n4. Review eligibility status\n\nMake sure all fields are completed before saving.',
                    'history': 'The verification history shows all previous saves and updates. Each entry includes:\n• Verification action (Initial Save or Update)\n• Verified by Saleh Amin\n• Date and time of verification\n\nHistory helps track all changes made to the verification.',
                    'ownership': 'Ownership structure includes:\n• Shareholders with their share percentages\n• Ultimate Beneficial Owners (UBOs)\n• Management control personnel\n\nThis information helps verify the entity\'s ownership structure and identify controlling persons.',
                    'default': 'I understand you\'re asking about client verification. Could you please be more specific about what you need help with? You can ask about:\n• Required documents\n• Eligibility criteria\n• Verification process\n• Economic sectors\n• Ownership structure\n• Saving verification\n• Verification history\n\nHow can I assist you further?'
                };
                
                const lowerMessage = userMessage.toLowerCase();
                
                if (lowerMessage.includes('help')) return responses.help;
                if (lowerMessage.includes('document')) return responses.documents;
                if (lowerMessage.includes('eligib')) return responses.eligibility;
                if (lowerMessage.includes('verif')) return responses.verification;
                if (lowerMessage.includes('sector')) return responses.sector;
                if (lowerMessage.includes('save')) return responses.save;
                if (lowerMessage.includes('history')) return responses.history;
                if (lowerMessage.includes('ownership') || lowerMessage.includes('shareholder') || lowerMessage.includes('ubo')) return responses.ownership;
                
                return responses.default;
            }
            
            sendButton.addEventListener('click', function() {
                const message = chatInput.value.trim();
                if (message) {
                    addMessage(message, true);
                    chatInput.value = '';
                    
                    setTimeout(() => {
                        const response = getAIResponse(message);
                        addMessage(response);
                    }, 1000);
                }
            });
            
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendButton.click();
                }
            });
            
            // Add sample conversation
            setTimeout(() => {
                addMessage('Need help with verification? Type your question or type "help" for available commands.');
            }, 2000);
        });
    </script>
</body>
</html>