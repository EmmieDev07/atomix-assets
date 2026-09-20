<?php
/**
 * JWT Token Refresh API
 * Atomix Learning Platform
 * 
 * Use this endpoint to refresh an expired access token using a valid refresh token
 */

require_once '../config/database.php';
require_once '../includes/jwt_helper.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/**
 * Callback to get fresh user data from database
 */
function getUserCallback($userId, $role) {
    $db = Database::getInstance()->getConnection();
    
    switch ($role) {
        case 'teacher':
            $stmt = $db->prepare("
                SELECT u.*, t.teacher_id, t.first_name, t.last_name 
                FROM users u 
                JOIN teachers t ON u.user_id = t.user_id 
                WHERE u.user_id = ? AND u.role = 'teacher' AND u.status = 'active' AND u.email_verified_at IS NOT NULL
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                return [
                    'user_id' => $user['user_id'],
                    'teacher_id' => $user['teacher_id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => 'teacher'
                ];
            }
            break;
            
        case 'student':
            $stmt = $db->prepare("
                SELECT u.*, s.student_id, s.first_name, s.last_name 
                FROM users u 
                JOIN students s ON u.user_id = s.user_id 
                WHERE u.user_id = ? AND u.role = 'student' AND u.status = 'active'
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                return [
                    'user_id' => $user['user_id'],
                    'student_id' => $user['student_id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => 'student'
                ];
            }
            break;
            
        case 'admin':
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE user_id = ? AND role = 'admin' AND status = 'active'
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                return [
                    'user_id' => $user['user_id'],
                    'email' => $user['email'] ?? $user['username'],
                    'name' => $user['username'],
                    'role' => 'admin'
                ];
            }
            break;
    }
    
    return null;
}

// Try to refresh the token
$result = JWTHelper::refreshAccessToken('getUserCallback');

if ($result) {
    echo json_encode([
        'success' => true,
        'access_token' => $result['access_token'],
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TOKEN_EXPIRY,
        'user' => $result['user']
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to refresh token. Please login again.'
    ]);
}
