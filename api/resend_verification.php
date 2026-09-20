<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_service.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
try {
    $email = trim($_POST['email'] ?? ($_GET['email'] ?? ''));
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }

    $stmt = $db->prepare("SELECT user_id, role, email_verified_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception('Account not found');
    }
    if (!empty($user['email_verified_at'])) {
        throw new Exception('Account already verified');
    }

    // Generate new 6-digit code
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $update = $db->prepare("UPDATE users SET email_verification_code_hash = ?, email_verification_code_expires_at = ? WHERE user_id = ?");
    $update->execute([$codeHash, $expiresAt, $user['user_id']]);

    $nameStmt = $db->prepare("SELECT COALESCE(CONCAT(t.first_name, ' ', t.last_name), CONCAT(s.first_name, ' ', s.last_name), u.username) AS name FROM users u LEFT JOIN teachers t ON t.user_id = u.user_id LEFT JOIN students s ON s.user_id = u.user_id WHERE u.user_id = ? LIMIT 1");
    $nameStmt->execute([$user['user_id']]);
    $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
    $recipientName = $nameRow ? trim($nameRow['name']) : $email;

    if (!sendAccountVerificationCodeEmail($email, $recipientName, $code, MAIL_FROM_NAME)) {
        throw new Exception('Failed to send verification code email');
    }

    echo json_encode(['success' => true, 'message' => 'Verification code resent']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
