<?php
/**
 * Admin Logout Script
 * Atomix Learning Platform
 */

require_once '../includes/jwt_helper.php';

session_start();

// Clear all session variables
$_SESSION = array();

// Clear JWT cookies for admin role
JWTHelper::clearTokenCookies('admin');

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?></content>
<parameter name="filePath">c:\xampp\htdocs\finalweb\admin\logout.php