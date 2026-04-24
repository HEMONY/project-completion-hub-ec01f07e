<?php
session_start();
require_once '../../config/db.php'; // External database connection

// Process login form
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($username)) {
        $error = "Username or email is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } else {
        try {
            // Check if user exists by email or mobile
            $stmt = $pdo->prepare("
                SELECT id, email, mobile, password_hash, full_name, role, is_verified, is_active 
                FROM users 
                WHERE (email = ? OR mobile = ?) AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if user is verified
                    if (!$user['is_verified']) {
                        $error = "Please verify your email before logging in";
                    } else {
                        // Update last login time
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['mobile'] = $user['mobile'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['authenticated'] = true;
                        
                        // Set remember me cookie if requested
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            // Store token in database
                            $tokenStmt = $pdo->prepare("
                                INSERT INTO remember_tokens (user_id, token, expires_at) 
                                VALUES (?, ?, FROM_UNIXTIME(?))
                            ");
                            $tokenStmt->execute([$user['id'], $token, $expires]);
                            
                            // Set cookie
                            setcookie('remember_token', $token, $expires, '/', '', true, true);
                            setcookie('user_id', $user['id'], $expires, '/', '', true, true);
                        }
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin' || $user['role'] === 'staff' || $user['role'] === 'auditor') {
                            header("Location: ../dashboard/entities_dashboard.php");
                        } else {
                            header("Location: ../dashboard/entities_dashboard.php");
                        }
                        exit;
                    }
                } else {
                    $error = "Invalid username or password";
                    
                    // Log failed attempt
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $logStmt = $pdo->prepare("
                        INSERT INTO login_attempts (email, ip_address, user_agent, success) 
                        VALUES (?, ?, ?, 0)
                    ");
                    $logStmt->execute([$username, $ip, $userAgent]);
                }
            } else {
                $error = "Invalid username or password";
                
                // Log failed attempt
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $logStmt = $pdo->prepare("
                    INSERT INTO login_attempts (email, ip_address, user_agent, success) 
                    VALUES (?, ?, ?, 0)
                ");
                $logStmt->execute([$username, $ip, $userAgent]);
            }
            
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for remember me token
if (empty($_SESSION['authenticated']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, rt.token 
            FROM users u 
            JOIN remember_tokens rt ON u.id = rt.user_id 
            WHERE u.id = ? AND rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->execute([$_COOKIE['user_id'], $_COOKIE['remember_token']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['mobile'] = $user['mobile'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['authenticated'] = true;
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Redirect based on role
            if ($user['role'] === 'admin' || $user['role'] === 'staff' || $user['role'] === 'auditor') {
                header("Location: ../dashboard/entities_dashboard.php");
            } else {
                header("Location: ../dashboard/entities_dashboard.php");
            }
            exit;
        } else {
            // Clear invalid cookies
            setcookie('remember_token', '', time() - 3600, '/');
            setcookie('user_id', '', time() - 3600, '/');
        }
    } catch (PDOException $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}

// Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff' || $_SESSION['role'] === 'auditor')) {
        header("Location: ../dashboard/entities_dashboard.php");
    } else {
        header("Location:../dashboard/entities_dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Muhasba</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .login-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 480px;
            padding: 50px;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
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
            margin-bottom: 40px;
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

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
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
            font-size: 18px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4285F4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .form-group input::placeholder {
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
            font-size: 18px;
            padding: 0;
        }

        .password-toggle:hover {
            color: #333;
        }

        .error-message {
            background: #fdebea;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }

        .error-message::before {
            content: "⚠";
            font-size: 16px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-button {
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
            margin-top: 8px;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4285F4;
        }

        .forgot-password {
            color: #4285F4;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: #0b2e59;
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            color: #999;
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            padding: 0 16px;
        }

        .alternative-login {
            text-align: center;
        }

        .alternative-login p {
            color: #666;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .social-login-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .social-button {
            flex: 1;
            padding: 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .social-button:hover {
            border-color: #4285F4;
            background: #f8f9ff;
        }

        .social-button i {
            font-size: 16px;
        }

        .google-button {
            color: #DB4437;
        }

        .microsoft-button {
            color: #00A4EF;
        }

        .signup-link {
            text-align: center;
            margin-top: 32px;
            color: #666;
            font-size: 14px;
        }

        .signup-link a {
            color: #4285F4;
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

        /* Success message for registration */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #c3e6cb;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }

        .success-message::before {
            content: "✓";
            font-size: 16px;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .login-container {
                padding: 40px 24px;
                margin: 20px;
            }

            .logo h1 {
                font-size: 28px;
            }

            .social-login-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 400px) {
            .remember-forgot {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Muhasba</h1>
            <p>New Entity Application Portal</p>
        </div>

        <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
            <div class="success-message">
                Registration successful! Please check your email for verification.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['verified']) && $_GET['verified'] == 'success'): ?>
            <div class="success-message">
                Email verified successfully! You can now log in.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
            <div class="success-message">
                Password reset successful! You can now log in with your new password.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
            <div class="success-message">
                You have been successfully logged out.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Email or Mobile Number</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           placeholder="Enter your email or mobile number" 
                           required
                           autocomplete="username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password" 
                           required
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    Remember me
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="login-button" id="loginButton">
                <span id="buttonText">Sign In</span>
                <div class="loading-spinner" id="loadingSpinner"></div>
            </button>
        </form>

        <div class="divider">
            <span>Or continue with</span>
        </div>

        <div class="alternative-login">
            <p>Single Sign-On (SSO)</p>
            <div class="social-login-buttons">
                <button type="button" class="social-button google-button">
                    <i class="fab fa-google"></i>
                    Google
                </button>
                <button type="button" class="social-button microsoft-button">
                    <i class="fab fa-microsoft"></i>
                    Microsoft
                </button>
            </div>
        </div>

        <div class="signup-link">
            Don't have an account? 
            <a href="registration.php">Create Account</a>
        </div>

        <div class="footer">
            <a href="privacy.php">Privacy Policy</a> • 
            <a href="terms.php">Terms of Service</a> • 
            <a href="support.php">Support</a>
            <p style="margin-top: 8px;">© 2024 Muhasba.com. All rights reserved.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const rememberCheckbox = document.getElementById('remember');

            // Check if there's a saved username from registration
            const savedRegistrationEmail = localStorage.getItem('registration_email');
            if (savedRegistrationEmail) {
                document.getElementById('username').value = savedRegistrationEmail;
                localStorage.removeItem('registration_email'); // Clear after use
            }

            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle eye icon
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            // Form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!validateForm()) {
                    return;
                }
                
                // Show loading state
                buttonText.style.display = 'none';
                loadingSpinner.style.display = 'block';
                loginButton.disabled = true;
                
                // Submit the form
                loginForm.submit();
            });

            // Form validation
            function validateForm() {
                let isValid = true;
                const username = document.getElementById('username').value.trim();
                const password = passwordInput.value.trim();
                
                // Clear previous errors
                document.querySelectorAll('.form-group input').forEach(input => {
                    input.classList.remove('error');
                });
                
                // Validate username (email or mobile)
                if (!username) {
                    document.getElementById('username').classList.add('error');
                    isValid = false;
                } else {
                    // Check if it looks like email or UAE mobile
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    const mobileRegex = /^[0-9+]{10,15}$/;
                    
                    if (!emailRegex.test(username) && !mobileRegex.test(username)) {
                        document.getElementById('username').classList.add('error');
                        isValid = false;
                    }
                }
                
                // Validate password
                if (!password) {
                    passwordInput.classList.add('error');
                    isValid = false;
                } else if (password.length < 6) {
                    passwordInput.classList.add('error');
                    isValid = false;
                }
                
                return isValid;
            }

            // Enter key to submit
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !loginButton.disabled) {
                    loginForm.dispatchEvent(new Event('submit'));
                }
            });

            // Social login buttons (placeholder functionality)
            document.querySelectorAll('.social-button').forEach(button => {
                button.addEventListener('click', function() {
                    alert('Social login functionality would be implemented here.');
                });
            });

            // Auto-focus on username field
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>