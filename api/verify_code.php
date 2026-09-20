<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_service.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();

try {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        throw new Exception('Email and verification code are required.');
    }
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        throw new Exception('Please enter the 6-digit verification code sent to your email.');
    }

    $stmt = $db->prepare("SELECT user_id, role, email_verification_code_hash, email_verification_code_expires_at, email_verified_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Account not found');
    }
    if (!empty($user['email_verified_at'])) {
        echo json_encode(['success' => true, 'message' => 'Account already verified']);
        exit;
    }
    if (empty($user['email_verification_code_hash']) || empty($user['email_verification_code_expires_at'])) {
        throw new Exception('No verification code is set for this account');
    }

    if (new DateTime() > new DateTime($user['email_verification_code_expires_at'])) {
        throw new Exception('Verification code has expired');
    }

    if (hash('sha256', $code) !== $user['email_verification_code_hash']) {
        throw new Exception('Invalid verification code');
    }

    // Mark verified and activate; force initial password creation if not already required
    $update = $db->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_code_hash = NULL, email_verification_code_expires_at = NULL, status = 'active', must_change_password = 1 WHERE user_id = ?");
    $update->execute([$user['user_id']]);

    // Send confirmation email (best-effort)
    $stmt = $db->prepare("SELECT u.email, COALESCE(CONCAT(t.first_name, ' ', t.last_name), CONCAT(s.first_name, ' ', s.last_name), u.username) AS full_name FROM users u LEFT JOIN teachers t ON t.user_id = u.user_id LEFT JOIN students s ON s.user_id = u.user_id WHERE u.user_id = ? LIMIT 1");
    $stmt->execute([$user['user_id']]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($info) {
        sendAccountVerifiedEmail($info['email'], trim($info['full_name']) ?: $info['email'], MAIL_FROM_NAME);
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $userInfoStmt = $db->prepare("SELECT u.user_id, u.email, u.must_change_password, u.role, t.teacher_id, s.student_id, COALESCE(t.first_name, s.first_name) AS first_name, COALESCE(t.last_name, s.last_name) AS last_name FROM users u LEFT JOIN teachers t ON t.user_id = u.user_id LEFT JOIN students s ON s.user_id = u.user_id WHERE u.user_id = ? LIMIT 1");
    $userInfoStmt->execute([$user['user_id']]);
    $userInfo = $userInfoStmt->fetch(PDO::FETCH_ASSOC);
    if ($userInfo) {
        $_SESSION['user_id'] = $userInfo['user_id'];
        $_SESSION['email'] = $userInfo['email'];
        $_SESSION['role'] = $userInfo['role'];
        if ($userInfo['role'] === 'teacher') {
            $_SESSION['teacher_id'] = $userInfo['teacher_id'];
        } else {
            $_SESSION['student_id'] = $userInfo['student_id'];
        }
        $_SESSION['name'] = trim($userInfo['first_name'] . ' ' . $userInfo['last_name']);
        if (!empty($userInfo['must_change_password'])) {
            $_SESSION['must_change_password'] = 1;
        }
    }

    // The file is at c:\xampp\htdocs\finalweb\teacher\change_password.php
    // So the URL must be http://localhost/finalweb/teacher/change_password.php
    // Always use /finalweb/ as the base since that's where the project folder is
    $redirectPath = '/finalweb/student/change_password.php';
    if (!empty($userInfo['role']) && $userInfo['role'] === 'teacher') {
        $redirectPath = '/finalweb/teacher/change_password.php';
    }

    echo json_encode(['success' => true, 'message' => 'Email verified successfully', 'redirect' => $redirectPath]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
