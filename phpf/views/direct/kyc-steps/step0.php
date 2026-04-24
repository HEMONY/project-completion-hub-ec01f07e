<?php
//////////////////////////using chat widget////////////////////
ob_start(); // Start output buffering to prevent header conflicts
session_start();
// exit();
// phpinfo();
require_once "../../widgets/chat_widget.php";
// require_once "../../../config/db.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
/////////////////////end chat widget//////////////////////////////

if($_SERVER['REQUEST_METHOD']==='POST')
{
    $_SESSION['form']['step0']=$_POST;
    header("Location: KYC.php");

}
// Step0UserType.php - Combined React Component and CSS in PHP
header('Content-Type: text/html; charset=utf-8');

// Check if this is an API request or page load
if (isset($_GET['api']) && $_GET['api'] === 'component') {
    // Return just the component for AJAX loading
    renderComponent();
    exit;
}
ob_end_flush(); // End output buffering
// Main page rendering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Onboarding - User Type Selection</title>
    <style>
        <?php echo getCSS(); ?>
    </style>
    <script>
        // Application state management
        let applicationData = {
            userType: <?php echo json_encode(['type' => '', 'selectedAt' => null]); ?>
        };

        function handleUserTypeSelect(type) {
            applicationData.userType = {
                type: type,
                selectedAt: new Date().toISOString()
            };
            
            // Update UI
            updateSelectedCard(type);
            enableContinueButton();
            
            // In a real app, you might send this to the server
            console.log('Selected user type:', type);
        }

        function updateSelectedCard(type) {
            // Remove selected class from all cards
            document.querySelectorAll('.user-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            const selectedCard = document.querySelector(`[data-type="${type}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
        }

        function enableContinueButton() {
            const continueBtn = document.querySelector('.btn-primary');
            continueBtn.disabled = false;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers to user type cards
            document.querySelectorAll('.user-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    handleUserTypeSelect(type);
                });
                
                // Add keyboard navigation
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const type = this.getAttribute('data-type');
                        handleUserTypeSelect(type);
                    }
                });
            });
            
            // Make cards focusable for accessibility
            document.querySelectorAll('.user-type-card').forEach(card => {
                card.setAttribute('tabindex', '0');
            });
        });
    </script>
</head>
<body>
    <?php echo renderComponent(); ?>
</body>
</html>

<?php
// PHP Functions
function renderComponent() {
    ob_start();
    ?>
    <div class="step-container">
        <form method="POST" action="" enctype="multipart/form-data" id="step0-form" class="user-type-selection-form">
            <div class="step-header">
                <h2>Welcome to Client Onboarding</h2>
                <p>Please select your user type to begin the onboarding process</p>
            </div>
            
            <div class="user-type-options">
                <div class="user-type-card" data-type="new">
                    <div class="user-type-icon">👤</div>
                    <h3>New Client</h3>
                    <p>I'm starting a new engagement or relationship</p>
                    <div class="user-type-features">
                        <ul>
                            <li>Complete new profile creation</li>
                            <li>Initial assessment required</li>
                            <li>Document submission needed</li>
                            <li>Full verification process</li>
                        </ul>
                    </div>
                    <button type="submit" name="new" value="new" class="submit-btn new-client-btn">
                        <span class="btn-text">Start as New Client</span>
                        <span class="btn-icon">→</span>
                    </button>
                </div>
                
                <div class="user-type-card" name="return">
                    <div class="user-type-icon">🔄</div>
                    <h3>Returning Client</h3>
                    <p>I have an existing profile and want to continue</p>
                    <div class="user-type-features">
                        <ul>
                            <li>Access existing profile</li>
                            <li>Update information only</li>
                            <li>Quick verification</li>
                            <li>Continue where you left off</li>
                        </ul>
                    </div>
                    <button type="submit" name="return" value="return" class="submit-btn returning-client-btn">
                        <span class="btn-text">Continue as Returning</span>
                        <span class="btn-icon">→</span>
                    </button>
                </div>
            </div>
            
            <div class="selection-note">
                <p>Select an option above to proceed with the onboarding process</p>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function getCSS() {
    return '
    .step-container {
        min-height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .user-type-selection-form {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
        width: 100%;
    }
    
    .step-header {
        text-align: center;
        margin-bottom: 60px;
    }
    
    .step-header h2 {
        font-size: 36px;
        font-weight: 300;
        color: #1a1a1a;
        margin-bottom: 15px;
        letter-spacing: -0.5px;
    }
    
    .step-header p {
        font-size: 18px;
        color: #666;
        line-height: 1.6;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .user-type-options {
        display: flex;
        gap: 30px;
        margin-bottom: 30px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .user-type-card {
        flex: 1;
        min-width: 350px;
        max-width: 400px;
        background: white;
        border-radius: 16px;
        padding: 40px 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid #e0e0e0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .user-type-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        border-color: #d0d0d0;
    }
    
    .user-type-card.selected {
        border-color: #0b2e59;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        box-shadow: 0 15px 35px rgba(11, 46, 89, 0.15);
    }
    
    .user-type-card.selected::before {
        content: \'\';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #0b2e59 0%, #1e4a82 100%);
    }
    
    .user-type-icon {
        font-size: 64px;
        margin-bottom: 25px;
        display: inline-block;
        transition: transform 0.3s ease;
        filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
    }
    
    .user-type-card:hover .user-type-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .user-type-card h3 {
        font-size: 28px;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 15px;
        letter-spacing: -0.3px;
    }
    
    .user-type-card p {
        font-size: 16px;
        color: #666;
        line-height: 1.6;
        margin-bottom: 30px;
        min-height: 50px;
    }
    
    .user-type-features {
        text-align: left;
        margin: 25px 0 40px;
        padding-top: 25px;
        border-top: 1px solid #eee;
        flex-grow: 1;
    }
    
    .user-type-features ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .user-type-features li {
        padding: 10px 0;
        padding-left: 30px;
        position: relative;
        font-size: 15px;
        color: #555;
        line-height: 1.5;
    }
    
    .user-type-features li::before {
        content: \'✓\';
        position: absolute;
        left: 0;
        color: #0b2e59;
        font-weight: bold;
        font-size: 16px;
    }
    
    /* Submit Button Styles */
    .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 18px 30px;
        font-size: 17px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
        position: relative;
        overflow: hidden;
        min-height: 60px;
        letter-spacing: 0.3px;
    }
    
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.35);
    }
    
    .submit-btn:active {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    .submit-btn::before {
        content: \'\';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }
    
    .submit-btn:hover::before {
        left: 100%;
    }
    
    .btn-text {
        flex-grow: 1;
        text-align: left;
        font-weight: 500;
    }
    
    .btn-icon {
        font-size: 22px;
        font-weight: 300;
        transition: transform 0.3s ease;
    }
    
    .submit-btn:hover .btn-icon {
        transform: translateX(5px);
    }
    
    /* Specific button styles */
    .new-client-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
    }
    
    .new-client-btn:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4090 100%);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.35);
    }
    
    .returning-client-btn {
        background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
        box-shadow: 0 6px 20px rgba(0, 176, 155, 0.25);
    }
    
    .returning-client-btn:hover {
        background: linear-gradient(135deg, #009e8a 0%, #86b336 100%);
        box-shadow: 0 10px 25px rgba(0, 176, 155, 0.35);
    }
    
    .user-type-card:hover .submit-btn {
        transform: translateY(-2px);
    }
    
    .user-type-card.selected .submit-btn {
        animation: pulse 2s infinite;
    }
    
    .selection-note {
        text-align: center;
        margin-top: 40px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 10px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .selection-note p {
        font-size: 15px;
        color: #666;
        margin: 0;
        font-weight: 500;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .user-type-selection-form {
            padding: 20px 15px;
        }
        
        .step-header h2 {
            font-size: 28px;
        }
        
        .step-header p {
            font-size: 16px;
        }
        
        .user-type-options {
            flex-direction: column;
            align-items: center;
            gap: 25px;
        }
        
        .user-type-card {
            min-width: 100%;
            padding: 30px 20px;
        }
        
        .user-type-card h3 {
            font-size: 24px;
        }
        
        .user-type-icon {
            font-size: 56px;
            margin-bottom: 20px;
        }
        
        .submit-btn {
            padding: 16px 25px;
            font-size: 16px;
            min-height: 56px;
        }
        
        .selection-note {
            margin-top: 30px;
            padding: 15px;
        }
    }
    
    @media (max-width: 480px) {
        .step-header h2 {
            font-size: 24px;
        }
        
        .step-header p {
            font-size: 15px;
        }
        
        .user-type-card {
            padding: 25px 15px;
        }
        
        .user-type-card h3 {
            font-size: 22px;
        }
        
        .user-type-icon {
            font-size: 48px;
        }
        
        .submit-btn {
            padding: 14px 20px;
            font-size: 15px;
            min-height: 52px;
        }
        
        .btn-text {
            font-size: 15px;
        }
        
        .btn-icon {
            font-size: 20px;
        }
    }
    
    /* Selection Animation */
    @keyframes pulse {
        0% {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
        }
        50% {
            box-shadow: 0 6px 30px rgba(102, 126, 234, 0.4);
        }
        100% {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
        }
    }
    
    .user-type-card.selected {
        animation: cardPulse 3s infinite;
    }
    
    @keyframes cardPulse {
        0% {
            box-shadow: 0 15px 35px rgba(11, 46, 89, 0.15);
        }
        50% {
            box-shadow: 0 15px 40px rgba(11, 46, 89, 0.25);
        }
        100% {
            box-shadow: 0 15px 35px rgba(11, 46, 89, 0.15);
        }
    }
    
    /* Accessibility */
    .user-type-card:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(11, 46, 89, 0.2);
    }
    
    .submit-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(11, 46, 89, 0.3), 0 6px 20px rgba(102, 126, 234, 0.25);
    }
    
    /* Loading state */
    .submit-btn.loading {
        position: relative;
        color: transparent;
    }
    
    .submit-btn.loading::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin: -10px 0 0 -10px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    ';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = isset($_POST['userType']) ? json_decode($_POST['userType'], true) : null;
    
    if ($userType && in_array($userType['type'], ['new', 'returning'])) {
        // Save to session or database
        session_start();
        $_SESSION['userType'] = $userType;
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'User type saved successfully',
            'nextStep' => 'Step1.php?userType=' . $userType['type']
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type selected'
        ]);
        exit;
    }
}
?>