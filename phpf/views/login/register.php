<?php
require_once '../../config/db.php'; // External database connection

// Process registration form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $account_type = $_POST['account_type'] ?? 'individual';
    $terms_accepted = isset($_POST['terms']);
    
    // Validation
    $validation_errors = [];
    
    // Full name validation
    if (empty($full_name)) {
        $validation_errors['full_name'] = 'Full name is required';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $validation_errors['full_name'] = 'Full name must be between 2 and 100 characters';
    } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $full_name)) {
        $validation_errors['full_name'] = 'Full name can only contain letters, spaces, hyphens, and apostrophes';
    }
    
    // Email validation
    if (empty($email)) {
        $validation_errors['email'] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = 'Please enter a valid email address';
    } elseif (strlen($email) > 255) {
        $validation_errors['email'] = 'Email address is too long';
    }
    
    // Mobile number validation for UAE - SIMPLIFIED AND FIXED
    if (empty($mobile)) {
        $validation_errors['mobile'] = 'Mobile number is required';
    } else {
        // Clean the mobile number - remove all non-digit characters
        $mobile_clean = preg_replace('/[^0-9]/', '', $mobile);
        
        // UAE mobile numbers must start with 5 and be 9 digits (without country code)
        if (strlen($mobile_clean) === 9 && $mobile_clean[0] === '5') {
            // Valid UAE number, format to +971XXXXXXXXX
            $mobile = '+971' . $mobile_clean;
        }
        // Also accept 10 digits starting with 05
        elseif (strlen($mobile_clean) === 10 && substr($mobile_clean, 0, 2) === '05') {
            // Remove leading 0 and add +971
            $mobile = '+971' . substr($mobile_clean, 1);
        }
        // Accept numbers already in +971 format
        elseif (preg_match('/^\+9715[0-9]{8}$/', $mobile)) {
            // Already in correct format
            $mobile = $mobile;
        }
        // Accept numbers in +97150/55/56/52/54/58 format (10 digits after +971)
        elseif (preg_match('/^\+971(50|55|56|52|54|58)[0-9]{7}$/', $mobile)) {
            // Already in correct format for special prefixes
            $mobile = $mobile;
        }
        else {
            $validation_errors['mobile'] = 'Please enter a valid UAE mobile number starting with 5 (e.g., 5XXXXXXXX or 05XXXXXXXX)';
        }
    }
    
    // Password validation
    if (empty($password)) {
        $validation_errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $validation_errors['password'] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $validation_errors['password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $validation_errors['password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $validation_errors['password'] = 'Password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $validation_errors['password'] = 'Password must contain at least one special character';
    }
    
    // Confirm password validation
    if ($password !== $confirm_password) {
        $validation_errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Terms acceptance validation
    if (!$terms_accepted) {
        $validation_errors['terms'] = 'You must accept the terms and conditions';
    }
    
    // If no validation errors, proceed with registration
    if (empty($validation_errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $validation_errors['email'] = 'Email address is already registered';
            }
            
            // Check if mobile already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
            $stmt->execute([$mobile]);
            if ($stmt->rowCount() > 0) {
                $validation_errors['mobile'] = 'Mobile number is already registered';
            }
            
            // If no duplicate errors, create user
            if (empty($validation_errors)) {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate verification code
                $verification_code = sprintf('%06d', random_int(0, 999999));
                
                // Insert user into database
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, mobile, password_hash, account_type, verification_code) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([$full_name, $email, $mobile, $password_hash, $account_type, $verification_code]);
                
                // Store user data in session for verification page
                $_SESSION['registration_data'] = [
                    'user_id' => $pdo->lastInsertId(),
                    'email' => $email,
                    'mobile' => $mobile,
                    'verification_code' => $verification_code
                ];
                
                // Redirect to verification page
                header('Location: login.php');
                exit;
            }
            
        } catch (PDOException $e) {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Muhasba</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef1f6 0%, #f5f7fa 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .registration-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 500px;
            padding: 50px;
            position: relative;
            overflow: hidden;
        }

        .registration-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0b2e59 0%, #4285F4 100%);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .logo p {
            font-size: 14px;
            color: #666;
            font-weight: 400;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }

        .form-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e53e3e;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 14px 14px 46px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #4285F4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .form-control.error {
            border-color: #e53e3e;
            background: #fff5f5;
        }

        .form-control.success {
            border-color: #38a169;
        }

        .form-control::placeholder {
            color: #999;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
        }

        .password-toggle:hover {
            color: #333;
        }

        .error-message {
            color: #e53e3e;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message i {
            font-size: 14px;
        }

        .success-message {
            color: #38a169;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .success-message i {
            font-size: 14px;
        }

        .mobile-hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .mobile-hint i {
            color: #0b2e59;
            font-size: 14px;
        }

        .account-type {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .account-type-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .account-type-option:hover {
            border-color: #4285F4;
            background: #f8f9ff;
        }

        .account-type-option.selected {
            border-color: #4285F4;
            background: #f0f7ff;
        }

        .account-type-option i {
            font-size: 24px;
            color: #4285F4;
            margin-bottom: 8px;
        }

        .account-type-option h4 {
            font-size: 16px;
            color: #333;
            margin-bottom: 4px;
        }

        .account-type-option p {
            font-size: 12px;
            color: #666;
        }

        input[name="account_type"] {
            display: none;
        }

        .terms-container {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .terms-container label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #555;
            line-height: 1.5;
        }

        .terms-container input[type="checkbox"] {
            margin-top: 3px;
            accent-color: #4285F4;
        }

        .terms-container a {
            color: #4285F4;
            text-decoration: none;
            font-weight: 500;
        }

        .terms-container a:hover {
            text-decoration: underline;
        }

        .register-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #0b2e59 0%, #4285F4 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.3);
        }

        .register-button:active {
            transform: translateY(0);
        }

        .register-button:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .login-link {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #4285F4;
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 12px;
        }

        .footer a {
            color: #666;
            text-decoration: none;
            margin: 0 8px;
        }

        .footer a:hover {
            color: #4285F4;
            text-decoration: underline;
        }

        /* Country code selector with flag */
        .country-flag {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .country-flag .flag-icon {
            width: 20px;
            height: 15px;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .country-flag .country-code-text {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .mobile-input {
            padding-left: 100px !important;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .registration-container {
                padding: 40px 24px;
                margin: 20px;
            }

            .logo h1 {
                font-size: 28px;
            }

            .account-type {
                flex-direction: column;
            }

            .form-header h2 {
                font-size: 22px;
            }
            
            .country-flag {
                padding: 4px 8px;
            }
            
            .mobile-input {
                padding-left: 90px !important;
            }
        }

        @media (max-width: 400px) {
            .registration-container {
                padding: 30px 20px;
            }
            
            .country-flag {
                left: 12px;
                padding: 3px 6px;
            }
            
            .mobile-input {
                padding-left: 85px !important;
            }
        }

        /* Password strength meter */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            background: #e0e0e0;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #e53e3e;
            width: 33%;
        }

        .strength-medium {
            background: #d69e2e;
            width: 66%;
        }

        .strength-strong {
            background: #38a169;
            width: 100%;
        }

        .password-requirements {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .requirement i {
            font-size: 10px;
        }

        .requirement.valid {
            color: #38a169;
        }

        .requirement.invalid {
            color: #999;
        }
        
        /* Mobile format examples */
        .mobile-examples {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            padding-left: 5px;
        }
        
        .mobile-examples span {
            display: inline-block;
            margin-right: 10px;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        /* UAE Flag styling */
        .uae-flag {
            display: inline-block;
            width: 20px;
            height: 15px;
            position: relative;
            border-radius: 2px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .uae-flag:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 33.33%;
            height: 100%;
            background: #00732f;
        }
        
        .uae-flag:after {
            content: '';
            position: absolute;
            top: 0;
            left: 33.33%;
            width: 66.67%;
            height: 100%;
            background: linear-gradient(to bottom, 
                #ff0000 0%, 
                #ff0000 33.33%, 
                #ffffff 33.33%, 
                #ffffff 66.67%, 
                #000000 66.67%, 
                #000000 100%);
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="logo">
            <h1>Muhasba</h1>
            <p>Create Your Account</p>
        </div>

        <div class="form-header">
            <h2>Join Muhasba.com</h2>
            <p>Create your account to start your business journey in UAE</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 20px; background: #fed7d7; padding: 12px; border-radius: 6px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registrationForm">
            <!-- Account Type Selection -->
            <div class="form-group">
                <label>Account Type <span class="required">*</span></label>
                <div class="account-type">
                    <div class="account-type-option <?php echo (($_POST['account_type'] ?? 'individual') === 'individual') ? 'selected' : ''; ?>" 
                         onclick="selectAccountType('individual')">
                        <i class="fas fa-user"></i>
                        <h4>Individual</h4>
                        <p>For sole proprietors</p>
                    </div>
                    <div class="account-type-option <?php echo (($_POST['account_type'] ?? '') === 'company') ? 'selected' : ''; ?>" 
                         onclick="selectAccountType('company')">
                        <i class="fas fa-building"></i>
                        <h4>Company</h4>
                        <p>For registered businesses</p>
                    </div>
                </div>
                <input type="radio" name="account_type" id="account_individual" value="individual" 
                       <?php echo (($_POST['account_type'] ?? 'individual') === 'individual') ? 'checked' : ''; ?> required>
                <input type="radio" name="account_type" id="account_company" value="company"
                       <?php echo (($_POST['account_type'] ?? '') === 'company') ? 'checked' : ''; ?>>
            </div>

            <!-- Full Name -->
            <div class="form-group">
                <label for="full_name">Full Name <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-control <?php echo isset($validation_errors['full_name']) ? 'error' : ''; ?>"
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="Enter your full name"
                           required
                           autocomplete="name">
                </div>
                <?php if (isset($validation_errors['full_name'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['full_name']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="Enter your email address"
                           required
                           autocomplete="email">
                </div>
                <?php if (isset($validation_errors['email'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['email']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mobile Number (UAE Only) - UPDATED WITH FLAG -->
            <div class="form-group">
                <label for="mobile">Mobile Number <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-phone"></i>
                    <div class="country-flag">
                        <div class="uae-flag"></div>
                        <span class="country-code-text">+971</span>
                    </div>
                    <input type="tel" 
                           id="mobile" 
                           name="mobile" 
                           class="form-control mobile-input <?php echo isset($validation_errors['mobile']) ? 'error' : ''; ?>"
                           value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>"
                           placeholder="5XXXXXXXX"
                           required
                           autocomplete="tel"
                           maxlength="10"
                           pattern="[0-9]*"
                           inputmode="numeric">
                </div>
                <div class="mobile-hint">
                    <i class="fas fa-info-circle"></i>
                    Enter your 9-digit UAE mobile number starting with 5
                </div>
                <div class="mobile-examples">
                    <span>5XXXXXXXX</span>
                    <span>05XXXXXXXX</span>
                    <span>+9715XXXXXXXX</span>
                </div>
                <?php if (isset($validation_errors['mobile'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['mobile']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control <?php echo isset($validation_errors['password']) ? 'error' : ''; ?>"
                           placeholder="Create a strong password"
                           required
                           autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-meter" id="passwordStrength"></div>
                </div>
                <div class="password-requirements" id="passwordRequirements">
                    <div class="requirement invalid" id="reqLength">
                        <i class="fas fa-circle"></i> At least 8 characters
                    </div>
                    <div class="requirement invalid" id="reqUpper">
                        <i class="fas fa-circle"></i> One uppercase letter
                    </div>
                    <div class="requirement invalid" id="reqLower">
                        <i class="fas fa-circle"></i> One lowercase letter
                    </div>
                    <div class="requirement invalid" id="reqNumber">
                        <i class="fas fa-circle"></i> One number
                    </div>
                    <div class="requirement invalid" id="reqSpecial">
                        <i class="fas fa-circle"></i> One special character
                    </div>
                </div>
                <?php if (isset($validation_errors['password'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['password']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>"
                           placeholder="Confirm your password"
                           required
                           autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="error-message" id="passwordMatchError" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    Passwords do not match
                </div>
                <?php if (isset($validation_errors['confirm_password'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['confirm_password']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Terms and Conditions -->
            <div class="terms-container <?php echo isset($validation_errors['terms']) ? 'error' : ''; ?>">
                <label>
                    <input type="checkbox" 
                           name="terms" 
                           id="terms"
                           <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>
                           required>
                    <span>
                        I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>. 
                        I confirm that I am at least 18 years old and have read and understood all terms.
                    </span>
                </label>
                <?php if (isset($validation_errors['terms'])): ?>
                    <div class="error-message" style="margin-top: 10px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($validation_errors['terms']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="register-button" id="registerButton">
                <span id="buttonText">Create Account</span>
                <div class="loading-spinner" id="loadingSpinner"></div>
            </button>
        </form>

        <div class="login-link">
            Already have an account? 
            <a href="login.php">Sign In</a>
        </div>

        <div class="footer">
            <a href="#">Privacy Policy</a> • 
            <a href="#">Terms of Service</a> • 
            <a href="#">Support</a>
            <p style="margin-top: 8px;">© 2024 Muhasba.com. All rights reserved.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registrationForm = document.getElementById('registrationForm');
            const registerButton = document.getElementById('registerButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatchError = document.getElementById('passwordMatchError');
            const mobileInput = document.getElementById('mobile');
            
            // Account type selection
            function selectAccountType(type) {
                document.querySelectorAll('.account-type-option').forEach(option => {
                    option.classList.remove('selected');
                });
                document.querySelector(`.account-type-option[onclick="selectAccountType('${type}')"]`).classList.add('selected');
                document.getElementById(`account_${type}`).checked = true;
            }
            
            // Password visibility toggle
            function togglePassword(fieldId) {
                const field = document.getElementById(fieldId);
                const icon = field.parentNode.querySelector('.password-toggle i');
                
                if (field.type === 'password') {
                    field.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    field.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                const requirements = {
                    length: password.length >= 8,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                
                // Update requirement indicators
                document.getElementById('reqLength').className = requirements.length ? 
                    'requirement valid' : 'requirement invalid';
                document.getElementById('reqUpper').className = requirements.upper ? 
                    'requirement valid' : 'requirement invalid';
                document.getElementById('reqLower').className = requirements.lower ? 
                    'requirement valid' : 'requirement invalid';
                document.getElementById('reqNumber').className = requirements.number ? 
                    'requirement valid' : 'requirement invalid';
                document.getElementById('reqSpecial').className = requirements.special ? 
                    'requirement valid' : 'requirement invalid';
                
                // Calculate strength
                if (requirements.length) strength++;
                if (requirements.upper) strength++;
                if (requirements.lower) strength++;
                if (requirements.number) strength++;
                if (requirements.special) strength++;
                
                // Update strength meter
                const strengthMeter = document.getElementById('passwordStrength');
                strengthMeter.className = 'strength-meter';
                
                if (strength <= 2) {
                    strengthMeter.classList.add('strength-weak');
                } else if (strength <= 4) {
                    strengthMeter.classList.add('strength-medium');
                } else {
                    strengthMeter.classList.add('strength-strong');
                }
            }
            
            // Check password match
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword === '') {
                    passwordMatchError.style.display = 'none';
                    confirmPasswordInput.classList.remove('error', 'success');
                    return;
                }
                
                if (password === confirmPassword && password !== '') {
                    passwordMatchError.style.display = 'none';
                    confirmPasswordInput.classList.remove('error');
                    confirmPasswordInput.classList.add('success');
                } else {
                    passwordMatchError.style.display = 'flex';
                    confirmPasswordInput.classList.remove('success');
                    confirmPasswordInput.classList.add('error');
                }
            }
            
            // SIMPLIFIED UAE mobile validation
            function validateUAEMobile(input) {
                let value = input.value.trim();
                
                // Remove any non-digit characters
                let digitsOnly = value.replace(/\D/g, '');
                
                // If empty, clear validation
                if (digitsOnly === '') {
                    input.classList.remove('error', 'success');
                    return false;
                }
                
                let isValid = false;
                
                // Check if it's a valid UAE mobile number
                // UAE numbers must start with 5 and be 9 digits (without country code)
                // Or 10 digits starting with 05
                if (digitsOnly.length === 9 && digitsOnly[0] === '5') {
                    isValid = true;
                    // Format as 5XXXXXXXX
                    input.value = digitsOnly;
                } 
                else if (digitsOnly.length === 10 && digitsOnly.substring(0, 2) === '05') {
                    isValid = true;
                    // Format as 5XXXXXXXX (remove leading 0)
                    input.value = digitsOnly.substring(1);
                }
                else if (value.startsWith('+971') && value.length >= 13) {
                    // Check +971 format
                    let afterCode = value.substring(4).replace(/\D/g, '');
                    if (afterCode.length === 9 && afterCode[0] === '5') {
                        isValid = true;
                        // Format as 5XXXXXXXX
                        input.value = afterCode;
                    }
                }
                
                // Update validation styling
                if (isValid) {
                    input.classList.remove('error');
                    input.classList.add('success');
                } else {
                    input.classList.remove('success');
                    input.classList.add('error');
                }
                
                return isValid;
            }
            
            // Format mobile number as user types
            function formatMobileNumber(input) {
                let value = input.value;
                
                // Remove all non-digit characters
                let digitsOnly = value.replace(/\D/g, '');
                
                // If empty, return
                if (digitsOnly === '') {
                    return;
                }
                
                // Limit to 10 digits (allows 05XXXXXXXX)
                digitsOnly = digitsOnly.substring(0, 10);
                
                // Format based on length
                let formatted = digitsOnly;
                
                if (digitsOnly.length <= 9) {
                    // Just show the digits
                    formatted = digitsOnly;
                } else if (digitsOnly.length === 10) {
                    // Show as 05XXXXXXXX
                    formatted = digitsOnly;
                }
                
                // Update input value
                input.value = formatted;
                
                // Validate
                validateUAEMobile(input);
            }
            
            // Event listeners
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Mobile input handling - simplified
            mobileInput.addEventListener('input', function() {
                formatMobileNumber(this);
            });
            
            mobileInput.addEventListener('blur', function() {
                validateUAEMobile(this);
            });
            
            // Form submission
            registrationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!validateForm()) {
                    return;
                }
                
                // Show loading state
                buttonText.style.display = 'none';
                loadingSpinner.style.display = 'block';
                registerButton.disabled = true;
                
                // Submit the form
                registrationForm.submit();
            });
            
            // Form validation
            function validateForm() {
                let isValid = true;
                
                // Check required fields
                const requiredFields = registrationForm.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                    }
                });
                
                // Validate mobile number
                if (!validateUAEMobile(mobileInput)) {
                    isValid = false;
                    mobileInput.classList.add('error');
                    // Show error message if not already shown
                    if (!document.querySelector('#mobile-error')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.id = 'mobile-error';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid UAE mobile number starting with 5';
                        mobileInput.parentNode.parentNode.appendChild(errorDiv);
                    }
                } else {
                    // Remove error message if exists
                    const existingError = document.querySelector('#mobile-error');
                    if (existingError) {
                        existingError.remove();
                    }
                }
                
                // Check password match
                if (passwordInput.value !== confirmPasswordInput.value) {
                    isValid = false;
                    passwordMatchError.style.display = 'flex';
                    confirmPasswordInput.classList.add('error');
                }
                
                // Check password strength
                checkPasswordStrength(passwordInput.value);
                
                // Check terms acceptance
                const termsCheckbox = document.getElementById('terms');
                if (!termsCheckbox.checked) {
                    isValid = false;
                    termsCheckbox.parentNode.parentNode.classList.add('error');
                }
                
                return isValid;
            }
            
            // Auto-format mobile number on page load if value exists
            if (mobileInput.value) {
                validateUAEMobile(mobileInput);
            }
            
            // Check password strength on page load if value exists
            if (passwordInput.value) {
                checkPasswordStrength(passwordInput.value);
            }
            
            // Real-time validation for all fields
            registrationForm.querySelectorAll('input').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('error');
                    } else if (this.type === 'email' && this.value) {
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test(this.value)) {
                            this.classList.add('error');
                        } else {
                            this.classList.remove('error');
                        }
                    }
                });
                
                input.addEventListener('input', function() {
                    this.classList.remove('error');
                    if (this.id === 'password') {
                        checkPasswordStrength(this.value);
                    }
                    // Remove mobile error when user starts typing
                    if (this.id === 'mobile' && document.querySelector('#mobile-error')) {
                        document.querySelector('#mobile-error').remove();
                    }
                });
            });
            
            // Test function for validation
            function testMobileValidation() {
                const testCases = [
                    '501234567',    // Should be valid
                    '512345678',    // Should be valid
                    '551234567',    // Should be valid
                    '0501234567',   // Should be valid (converts to 501234567)
                    '0512345678',   // Should be valid (converts to 512345678)
                    '0551234567',   // Should be valid (converts to 551234567)
                    '+971501234567', // Should be valid (converts to 501234567)
                    '+971512345678', // Should be valid (converts to 512345678)
                    '123456789',    // Should be invalid (doesn't start with 5)
                    '0123456789',   // Should be invalid (doesn't start with 05)
                    '5',            // Should be invalid (too short)
                ];
                
                console.log('Testing mobile validation:');
                testCases.forEach(test => {
                    const tempInput = document.createElement('input');
                    tempInput.value = test;
                    const result = validateUAEMobile(tempInput);
                    console.log(`${test} -> ${tempInput.value} (Valid: ${result})`);
                });
            }
            
            // Uncomment to test validation
            // testMobileValidation();
        });
    </script>
</body>
</html>