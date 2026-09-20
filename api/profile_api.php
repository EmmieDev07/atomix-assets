<?php
/**
 * Profile API - Allows teachers and students to view/update their own profile.
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromJWT();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db  = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

function jsonResponse($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

try {
    switch ($action) {

        // ── GET OWN PROFILE ────────────────────────────────────────────────
        case 'get':
            if ($role === 'teacher') {
                $stmt = $db->prepare("
                    SELECT t.teacher_id, t.first_name, t.last_name,
                           u.email, u.username, u.created_at, u.last_login, u.status
                    FROM teachers t
                    JOIN users u ON t.user_id = u.user_id
                    WHERE u.user_id = ?
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT s.student_id, s.first_name, s.last_name, s.gender,
                           u.email, u.username, u.created_at, u.last_login, u.status
                    FROM students s
                    JOIN users u ON s.user_id = u.user_id
                    WHERE u.user_id = ?
                ");
            }
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$profile) {
                jsonResponse(false, 'Profile not found');
            }
            jsonResponse(true, 'Profile loaded', $profile);
            break;

        // ── UPDATE OWN PROFILE ─────────────────────────────────────────────
        case 'update':
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name']  ?? '');
            $email     = trim($_POST['email']      ?? '');

            if (empty($firstName) || empty($lastName) || empty($email)) {
                jsonResponse(false, 'First name, last name, and email are required');
            }
            if (strlen($firstName) > 50 || strlen($lastName) > 50) {
                jsonResponse(false, 'Names must be 50 characters or less');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Invalid email format');
            }
            if (strlen($email) > 100) {
                jsonResponse(false, 'Email must be 100 characters or less');
            }

            // Check email uniqueness
            $dup = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $dup->execute([$email, $userId]);
            if ($dup->fetchColumn() > 0) {
                jsonResponse(false, 'Email is already used by another account');
            }

            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$email, $userId]);

            if ($role === 'teacher') {
                $stmt = $db->prepare("UPDATE teachers SET first_name = ?, last_name = ? WHERE user_id = ?");
            } else {
                $stmt = $db->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE user_id = ?");
            }
            $stmt->execute([$firstName, $lastName, $userId]);

            $db->commit();

            // Refresh session name
            $_SESSION['name']  = $firstName . ' ' . $lastName;
            $_SESSION['email'] = $email;

            jsonResponse(true, 'Profile updated successfully', [
                'name'  => $_SESSION['name'],
                'email' => $email
            ]);
            break;

        // ── CHANGE OWN PASSWORD ────────────────────────────────────────────
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword     = $_POST['new_password']     ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                jsonResponse(false, 'All password fields are required');
            }
            if (strlen($newPassword) < 6) {
                jsonResponse(false, 'New password must be at least 6 characters');
            }
            if ($newPassword !== $confirmPassword) {
                jsonResponse(false, 'New passwords do not match');
            }

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($currentPassword, $row['password'])) {
                jsonResponse(false, 'Current password is incorrect');
            }

            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?");
            $stmt->execute([$hashed, $userId]);

            jsonResponse(true, 'Password changed successfully');
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('profile_api.php error: ' . $e->getMessage());
    jsonResponse(false, 'A server error occurred. Please try again.');
}
