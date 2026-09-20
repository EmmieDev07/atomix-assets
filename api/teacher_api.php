<?php
/**
 * Teacher API - Admin Side
 * Handles teacher management operations
 */

require_once '../config/database.php';
require_once '../includes/email_service.php';

require_once '../includes/auth_check.php';
checkAdminAuth();

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $db->query("
                SELECT t.teacher_id, t.first_name, t.last_name, t.subject,
                       u.user_id, u.email, u.username, u.status,
                       u.created_at, u.last_login
                FROM teachers t
                JOIN users u ON t.user_id = u.user_id
                WHERE u.role = 'teacher'
                ORDER BY t.last_name, t.first_name
            ");
            echo json_encode(['success' => true, 'teachers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get':
            // Get teacher details
            $teacherId = $_GET['id'] ?? 0;
            if (!$teacherId) {
                throw new Exception('Teacher ID required');
            }

            $stmt = $db->prepare("
                SELECT t.teacher_id, t.first_name, t.last_name, u.email, u.username
                FROM teachers t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.teacher_id = ? AND u.role = 'teacher'
            ");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                throw new Exception('Teacher not found');
            }

            echo json_encode(['success' => true, 'teacher' => $teacher]);
            break;

        case 'update':
            // Update teacher details
            $teacherId = $_POST['teacher_id'] ?? 0;
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');

            if (!$teacherId || !$firstName || !$lastName || !$email || !$username) {
                throw new Exception('All fields are required');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Get user_id from teacher_id
            $stmt = $db->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                throw new Exception('Teacher not found');
            }

            $userId = $teacher['user_id'];

            // Check each field separately so the admin knows what conflicts.
            $emailCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $emailCheck->execute([$email, $userId]);
            $usernameCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $usernameCheck->execute([$username, $userId]);

            $duplicateFields = [];
            if ((int)$emailCheck->fetchColumn() > 0) $duplicateFields[] = 'email';
            if ((int)$usernameCheck->fetchColumn() > 0) $duplicateFields[] = 'username';
            if ($duplicateFields) {
                throw new Exception(ucfirst(implode(' and ', $duplicateFields)) . ' already exists for another account');
            }

            // Update user table
            $stmt = $db->prepare("UPDATE users SET email = ?, username = ? WHERE user_id = ?");
            $stmt->execute([$email, $username, $userId]);

            // Update teacher table
            $stmt = $db->prepare("UPDATE teachers SET first_name = ?, last_name = ? WHERE teacher_id = ?");
            $stmt->execute([$firstName, $lastName, $teacherId]);

            echo json_encode(['success' => true, 'message' => 'Teacher updated successfully']);
            break;

        case 'resend_verification':
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int) ($input['user_id'] ?? 0);

            if (!$userId) {
                throw new Exception('User ID required');
            }

            $stmt = $db->prepare(
                "SELECT u.email, u.status, u.email_verified_at, t.first_name, t.last_name
                 FROM users u
                 JOIN teachers t ON t.user_id = u.user_id
                 WHERE u.user_id = ? AND u.role = 'teacher' LIMIT 1"
            );
            $stmt->execute([$userId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$teacher) {
                throw new Exception('Teacher not found');
            }
            if (!empty($teacher['email_verified_at'])) {
                throw new Exception('Teacher email is already verified');
            }
            if (!isValidEmailAddress($teacher['email'])) {
                throw new Exception('Teacher email is invalid');
            }
            $reason = null;
            if (!isEmailDomainDeliverable($teacher['email'], $reason)) {
                throw new Exception($reason ?: 'Teacher email domain is not active or cannot receive mail');
            }

            // Generate short numeric code and store its hash
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = hash('sha256', $code);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $update = $db->prepare("UPDATE users SET email_verification_code_hash = ?, email_verification_code_expires_at = ? WHERE user_id = ?");
            $update->execute([$codeHash, $expiresAt, $userId]);

            $teacherFullName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
            if (!sendAccountVerificationCodeEmail($teacher['email'], $teacherFullName, $code, 'Atomix Admin')) {
                throw new Exception('Verification code email could not be sent');
            }

            echo json_encode(['success' => true, 'message' => 'Verification code resent successfully']);
            break;

        case 'reset_password':
            // Reset teacher password (set must_change_password flag)
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? 0;

            if (!$userId) {
                throw new Exception('User ID required');
            }

            // Generate a temporary password
            $tempPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE user_id = ? AND role = 'teacher'");
            $stmt->execute([$hashedPassword, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Teacher not found or update failed');
            }

            // Send password reset email to the teacher
            $teacherRow = $db->prepare(
                "SELECT u.email, t.first_name, t.last_name
                 FROM users u JOIN teachers t ON t.user_id = u.user_id
                 WHERE u.user_id = ? LIMIT 1"
            );
            $teacherRow->execute([$userId]);
            $teacherData = $teacherRow->fetch();
            $emailSent = false;
            $emailReason = '';
            $emailTo = $teacherData['email'] ?? '';
            if (!$teacherData || empty($teacherData['email'])) {
                $emailReason = 'No teacher email found for this account.';
            } else if (!filter_var($teacherData['email'], FILTER_VALIDATE_EMAIL)) {
                $emailReason = 'Teacher email is not a valid email format.';
            } else if (strpos(strtolower($teacherData['email']), '@school.local') !== false) {
                $emailReason = 'Teacher email uses a local placeholder domain (@school.local). Update it to a real email to receive reset mail.';
            } else {
                $teacherFullName = trim($teacherData['first_name'] . ' ' . $teacherData['last_name']);
                $emailSent = sendPasswordResetEmail($teacherData['email'], $teacherFullName, $tempPassword, 'Atomix Admin');
                if (!$emailSent) {
                    $emailReason = 'SMTP send failed. Check mail configuration and server logs.';
                } else {
                    error_log('Teacher reset email accepted by SMTP for user_id=' . $userId . ' email=' . $teacherData['email']);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => $emailSent
                    ? 'Password reset successfully. Reset details were sent to email.'
                    : 'Password reset successfully, but reset email could not be sent.',
                'email_sent' => $emailSent,
                'email_to' => $emailTo,
                'email_reason' => $emailReason,
                'temp_password' => $tempPassword
            ]);
            break;

        case 'toggle_status':
            // Activate/deactivate teacher account
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? 0;
            $action = $input['action'] ?? '';

            if (!$userId || !in_array($action, ['activate', 'deactivate'])) {
                throw new Exception('Invalid request');
            }

            $newStatus = $action === 'activate' ? 'active' : 'inactive';

            $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'teacher'");
            $stmt->execute([$newStatus, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Teacher not found or update failed');
            }

            echo json_encode(['success' => true, 'message' => "Teacher {$action}d successfully"]);
            break;

        case 'delete':
            // Delete teacher (soft delete by deactivating)
            $input = json_decode(file_get_contents('php://input'), true);
            $teacherId = $input['teacher_id'] ?? 0;

            if (!$teacherId) {
                throw new Exception('Teacher ID required');
            }

            // Get user_id first
            $stmt = $db->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                throw new Exception('Teacher not found');
            }

            // Deactivate the user account instead of deleting
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
            $stmt->execute([$teacher['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Teacher deactivated successfully']);
            break;

        case 'get_permissions':
            // Get permissions for a teacher
            $teacherId = (int) ($_GET['id'] ?? 0);
            if (!$teacherId) {
                throw new Exception('Teacher ID required');
            }

            // Verify teacher exists
            $stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            if (!$stmt->fetch()) {
                throw new Exception('Teacher not found');
            }

            $stmt = $db->prepare("SELECT permission FROM teacher_permissions WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            $granted = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(['success' => true, 'permissions' => $granted]);
            break;

        case 'update_permissions':
            // Set permissions for a teacher (replace all)
            $input = json_decode(file_get_contents('php://input'), true);
            $teacherId = (int) ($input['teacher_id'] ?? 0);
            $permissions = $input['permissions'] ?? [];

            if (!$teacherId) {
                throw new Exception('Teacher ID required');
            }

            // Define allowed permission keys to prevent arbitrary inserts
            $allowed = [
                'can_manage_questions',
                'can_manage_quizzes',
                'can_view_reports',
                'can_manage_classes',
            ];

            // Sanitize: keep only valid permission keys
            $permissions = array_values(array_intersect($permissions, $allowed));

            // Verify teacher exists
            $stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            if (!$stmt->fetch()) {
                throw new Exception('Teacher not found');
            }

            // Replace all permissions in a transaction
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM teacher_permissions WHERE teacher_id = ?")->execute([$teacherId]);
                $insert = $db->prepare("INSERT INTO teacher_permissions (teacher_id, permission) VALUES (?, ?)");
                foreach ($permissions as $perm) {
                    $insert->execute([$teacherId, $perm]);
                }
                $db->commit();
            } catch (PDOException $e) {
                $db->rollBack();
                throw new Exception('Failed to update permissions: ' . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
            break;

        case 'create':
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name']  ?? '');
            $email     = trim($_POST['email']      ?? '');
            $username  = trim($_POST['username']   ?? '');
            $password  = $_POST['password']        ?? '';

            if (!$firstName || !$lastName || !$email || !$username) {
                throw new Exception('All fields are required');
            }
            if (!isValidEmailAddress($email)) {
                throw new Exception('Invalid email address format');
            }
            $emailValidationReason = '';
            if (!isEmailDomainDeliverable($email, $emailValidationReason)) {
                throw new Exception($emailValidationReason ?: 'Email domain is not active or cannot receive mail');
            }
            if (strlen($password) < 6) {
                $password = 'changeme';
            }

            $check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $check->execute([$email, $username]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Email or username already exists');
            }

            $db->beginTransaction();
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                     $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                     $codeHash = hash('sha256', $verificationCode);
                     $codeExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                     $db->prepare("INSERT INTO users (email, password, username, role, must_change_password, status, email_verified_at, email_verification_code_hash, email_verification_code_expires_at) VALUES (?, ?, ?, 'teacher', 1, 'pending', NULL, ?, ?)")
                         ->execute([$email, $hashed, $username, $codeHash, $codeExpiresAt]);
                $userId = (int) $db->lastInsertId();

                $db->prepare("INSERT INTO teachers (user_id, first_name, last_name) VALUES (?, ?, ?)")
                   ->execute([$userId, $firstName, $lastName]);
                $newTeacherId = (int) $db->lastInsertId();

                     $teacherFullName = trim($firstName . ' ' . $lastName);
                     if (!sendAccountVerificationCodeEmail($email, $teacherFullName, $verificationCode, 'Atomix Admin')) {
                          throw new Exception('Verification email could not be sent');
                     }

                // Seed all permissions for the new teacher
                $ins = $db->prepare("INSERT IGNORE INTO teacher_permissions (teacher_id, permission) VALUES (?, ?)");
                foreach (['can_manage_questions','can_manage_quizzes','can_view_reports','can_manage_classes'] as $perm) {
                    $ins->execute([$newTeacherId, $perm]);
                }
                $db->commit();
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw new Exception('Failed to create teacher: ' . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Teacher created successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
