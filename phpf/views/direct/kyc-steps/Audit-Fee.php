<?php
ob_start();
session_start();
if (empty($_SESSION['form']['step1'])){
    header("Location: KYC.php");
}
require_once "../../widgets/chat_widget.php";

displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);

    $entity_id = $_SESSION['form']['step0']['entity_id'];
    echo $entity_id;
$step1 = $_SESSION['form']['step1'] ?? [];
$turnover = $step1['turnover'] ?? 0;

$step0 = $_SESSION['form']['step0'] ?? [];

if (isset($step0['new'])) {
    $userstatus = 'new';
} elseif (isset($step0['return'])) {
    $userstatus = 'return';
} else {
    $userstatus = 'new';
}
// Calculate audit fee based on turnover
function calculateAuditFee($turnover) {
    $turnover = floatval($turnover);
    $fee = 0;
    
    if ($turnover <= 0) {
        return 0;
    }

    if ($turnover <= 1000000) {
        $fee = 1000;
    } elseif ($turnover <= 10000000) {
        $fee = 2000;
    } elseif ($turnover <= 20000000) {
        $fee = 3000;
    } else {
        $fee = 3000;
        $excessTurnover = $turnover - 20000000;
        $additionalBlocks = ceil($excessTurnover / 5000000);
        $fee += $additionalBlocks * 500;
    }

    return $fee;
}

// Store calculated fee in session
if ($turnover > 0 && !isset($_SESSION['form']['audit_fee'])) {
    $_SESSION['form']['audit_fee'] = calculateAuditFee($turnover);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the calculated fee from session
    $calculatedFee = $_SESSION['form']['audit_fee'] ?? 0;
    
    // Store step2 data including calculated fee
    $_SESSION['form']['step2'] = array_merge($_POST, [
        'calculated_fee' => $calculatedFee,
        'turnover' => $turnover
    ]);
    // $_SESSION['form']['step2'] = $POST;
    header("Location: ../../../controller/steps/save_audit_fee.php");
    exit();
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Entity Application</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
 <style>
    body { 
        font-family: 'Poppins', sans-serif; 
        background: #eef1f6; 
        padding: 40px;
        margin: 0;
        display: flex;
        height: 100vh; /* Full viewport height */
        overflow: hidden; /* Prevent overall page scroll */
        box-sizing: border-box;
        align-items: flex-start;
    }
    
    /* Steps Sidebar - Fixed with scrolling */
    .steps-sidebar {
        width: 280px;
        background: #ffffff;
        padding: 30px; /* Reduced padding */
        border-radius: 8px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        margin-right: 30px;
        flex-shrink: 0;
        position: sticky;
        top: 40px; /* Stick to top with padding */
        align-self: flex-start;
        max-height: calc(100vh - 80px); /* Limit height to viewport */
        overflow-y: auto; /* Make sidebar scrollable if needed */
        margin-top:50px;
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
        margin-bottom: 25px; /* Reduced margin */
        padding-bottom: 12px; /* Reduced padding */
        border-bottom: 1px solid #e0e0e0;
    }
    
    .sidebar-subtitle {
        font-size: 14px;
        color: #555;
        margin-bottom: 30px; /* Reduced margin */
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
        margin-bottom: 28px; /* Reduced margin */
        position: relative;
        min-height: 36px; /* Reduced min-height */
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
        font-weight: 300;
        color: #1a1a1a;
        margin: 0 0 4px 0; /* Reduced margin */
        line-height: 1.3; /* Tighter line height */
    }
    
    .step-vertical-status {
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 300;
        letter-spacing: 0.05em;
        line-height: 1.2; /* Tighter line height */
    }
    
    .step-vertical-status.completed {
        color: #dd5656;
    }
    
    .step-vertical-status.pending {
        color: #d17a0b;
    }
    
    /* Main Content - Scrollable */
    .main-content {
        flex-grow: 1;
        background: #ffffff;
        padding: 40px 50px; /* Reduced padding */
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        min-width: 900px;
        max-height: calc(100vh - 80px); /* Limit height to viewport */
        overflow-y: auto; /* Make main content scrollable */
        position: relative;
        margin-top:30px;
        max-height: calc(90vh - 80px);
    }
    
    /* Custom scrollbar for main content */
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
        padding-bottom: 10px; /* Reduced padding */
        margin-top: 0;
        margin-bottom: 6px; /* Reduced margin */
        font-weight: 300;
    }
    
    .content-header .subheading {
        font-size: 14px;
        color: #555;
        margin-bottom: 30px; /* Reduced margin */
        line-height: 1.5;
    }
    
    .step-content-container {
        display: none;
    }
    
    .step-content-container.active {
        display: block;
        padding-bottom: 10px; /* Reduced bottom padding */
    }
    
    .audit-fee-content {
        margin-bottom: 20px; /* Reduced margin */
    }
    
    .audit-fee-text {
        font-size: 16px;
        line-height: 1.5;
        margin-bottom: 12px; /* Reduced margin */
    }
    
    .audit-fee-text:last-child {
        margin-bottom: 0;
    }
    
    .agreement-section {
        margin: 20px 0; /* Reduced margin */
    }
    
    .agreement-checkbox {
        display: flex;
        align-items: flex-start;
        font-size: 16px;
        line-height: 1.4;
    }
    
    .agreement-checkbox input {
        margin-right: 10px;
        margin-top: 2px;
    }
    
    .navigation-buttons {
        display: flex;
        justify-content: space-between;
        margin-top: 25px; /* Reduced margin */
        padding-top: 20px; /* Reduced padding */
        border-top: 1px solid #e0e0e0;
    }
    
    button {
        background-color: #f2f2f2;
        color: #333;
        padding: 10px 15px; /* Reduced padding */
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        font-size: 14px; /* Reduced font size */
        font-weight: 600;
        border: 1px solid #ddd;
    }
    
    button:hover {
        background-color: #e6e6e6;
    }
    
    button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .form-group {
        margin-bottom: 18px; /* Reduced margin */
    }
    
    .form-group label {
        display: block;
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px; /* Reduced margin */
    }
    
    .form-group input[type="number"] {
        width: 100%;
        padding: 10px 15px; /* Reduced padding */
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    #calculated-fee {
        font-weight: 700;
        color: #dd5656;
    }
    
    .hide-form {
        display: none;
    }
    
    .turnover-display {
        background-color: #f8f9fa;
        padding: 10px; /* Reduced padding */
        border-radius: 4px;
        margin-bottom: 12px; /* Reduced margin */
        border-left: 4px solid #dd5656;
    }
    
    .turnover-display p {
        margin: 0;
        font-size: 16px;
        line-height: 1.4;
    }
    
    .turnover-value {
        font-weight: 700;
        color: #333;
    }
    
    /* Adjust for better vertical fit */
    @media (max-height: 800px) {
        body {
            padding: 20px;
        }
        
        .main-content, .steps-sidebar {
            padding: 20px;
        }
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
        <div class="sidebar-title">
            <?php echo ($userstatus == "new") ? "New Engagement Application" : "Continue Engagement Application"; ?>
        </div>
        <div class="sidebar-subtitle">
            <?php 
            if($userstatus == "new") {
                echo "Ensures that each new entity meets onboarding, compliance, and eligibility standards before activation on the platform.";
            } elseif($userstatus == "return") {
                echo "Ensuring the entity meets all compliance standards before rolling forward to the next fiscal year.";
            }
            ?>
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
                <div class="step-vertical-circle active">2</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Audit Fee Acknowledgement</div>
                    <div class="step-vertical-status pending">PENDING</div>
                </div>
            </li>
            <li class="step-vertical-item">
                <div class="step-vertical-circle">3</div>
                <div class="step-vertical-content">
                    <div class="step-vertical-title">Financial Year Details</div>
                    <div class="step-vertical-status">NOT STARTED</div>
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

    <form method="POST" action="" enctype="multipart/form-data" id="audit-fee-form">
        <div class="main-content" style="top : 20px">
            <div class="content-header">
                <h1>Audit Fee Acknowledgement</h1>
                <div class="subheading">
                    Please review and acknowledge the audit fee for your engagement.
                </div>
            </div>
            
            <div class="step-content-container active">
                <!-- Display turnover from previous step -->
              
                <!-- Hidden input to store turnover for form submission -->
                <input type="hidden" name="turnover" value="<?php echo $turnover; ?>">
                <input type="hidden" name="calculated_fee" id="calculated-fee-hidden" value="">
                
                <div class="audit-fee-content">
                    <p class="audit-fee-text">
                        Sultan Ali Auditing of Accounts has determined the final audit fee for this engagement at 
                        <span id="calculated-fee"><?php 
                            $calculatedFee = calculateAuditFee($turnover);
                            $_SESSION['form']['audit_fee']=$calculatedFee;
                            echo number_format($calculatedFee, 2); 
                        ?></span> AED, 
                        based on the Total Turnover provided.
                    </p>
                    
                    <p class="audit-fee-text">
                        The audit fee will be payable at the final stage of the engagement, following the review of your financial information and upon receipt of all required supporting documents and clarifications.
                    </p>
                    
                    <div class="agreement-section">
                        <div class="agreement-checkbox">
                            <input type="checkbox" id="audit-fee-agreement" name="agreement" value="1">
                            <label for="audit-fee-agreement">I agree to the calculated audit fee</label>
                        </div>
                    </div>
                </div>
                
                <div class="navigation-buttons">
                    <button type="button" onclick="goToPreviousStep()">
                        ← Previous Step
                    </button>
                    <button type="submit" id="next-step-btn" disabled>
                        Proceed to Next Step →
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const agreementCheckbox = document.getElementById('audit-fee-agreement');
            const nextStepBtn = document.getElementById('next-step-btn');
            const calculatedFeeSpan = document.getElementById('calculated-fee');
            const calculatedFeeHidden = document.getElementById('calculated-fee-hidden');
            
            // Set the calculated fee in hidden input for form submission
            const calculatedFee = calculatedFeeSpan.textContent.replace(/,/g, '');
            calculatedFeeHidden.value = calculatedFee;
            
            // Function to validate and enable/disable next button
            function validateForm() {
                const isAgreementChecked = agreementCheckbox.checked;
                const hasTurnover = <?php echo ($turnover > 0) ? 'true' : 'false'; ?>;
                
                if (isAgreementChecked && hasTurnover) {
                    nextStepBtn.disabled = false;
                    nextStepBtn.style.opacity = '1';
                } else {
                    nextStepBtn.disabled = true;
                    nextStepBtn.style.opacity = '0.5';
                }
            }
            
            // Initial validation
            validateForm();
            
            // Validate when checkbox changes
            agreementCheckbox.addEventListener('change', validateForm);
        });
        
        function goToPreviousStep() {
            window.location.href = 'KYC.php';
        }
    </script>
</body>
</html>