<?php
// logout.php - Complete logout handler

// Start session
session_start();

// Include database connection
require_once '../../config/db.php';

// CSRF protection (if logout is via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Log potential CSRF attempt
        error_log("CSRF token mismatch during logout. IP: " . $_SERVER['REMOTE_ADDR']);
        die('Security error. Please try again.');
    }
}

// Log logout activity
function logLogoutActivity($userId, $email, $ip, $userAgent, $logoutType = 'manual') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logout_logs (user_id, email, ip_address, user_agent, logout_type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $email, $ip, $userAgent, $logoutType]);
    } catch (PDOException $e) {
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// Get user info before destroying session
$userId = $_SESSION['user_id'] ?? null;
$email = $_SESSION['email'] ?? 'unknown';
$fullName = $_SESSION['full_name'] ?? 'unknown';
$role = $_SESSION['role'] ?? 'unknown';

// Log activity if user was logged in
if ($userId) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Determine logout type
    $logoutType = 'manual';
    if (isset($_GET['timeout'])) {
        $logoutType = 'timeout';
    } elseif (isset($_GET['multiple'])) {
        $logoutType = 'multiple_sessions';
    }
    
    logLogoutActivity($userId, $email, $ip, $userAgent, $logoutType);
    
    // Clear remember me tokens from database if exists
    if (isset($_COOKIE['remember_token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to delete remember tokens: " . $e->getMessage());
        }
    }
}

// Clear all session variables
$_SESSION = array();

// If using session cookies, delete them
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookies
setcookie('remember_token', '', time() - 3600, '/', '', true, true);
setcookie('user_id', '', time() - 3600, '/', '', true, true);

// Clear any other application-specific cookies
$appCookies = ['session_token', 'auth_token', 'login_token'];
foreach ($appCookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/', '', true, true);
    }
}

// Redirect to login page with logout message
$redirectUrl = '../login.php?logout=success&user=' . urlencode($fullName);
header("Location: " . $redirectUrl);
exit();
?>