<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/email_service.php';

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? 'password123'; // Default password for AJAX requests
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    // Validation
    if (!$email || !$username || !$first_name || !$last_name) {
        $message = 'All fields are required.';
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
    } elseif (!isValidEmailAddress($email)) {
        $message = 'Invalid email address format.';
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
    } else {
        $emailValidationReason = '';
        if (!isEmailDomainDeliverable($email, $emailValidationReason)) {
            $message = $emailValidationReason ?: 'Email domain is not active or cannot receive mail.';
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
        } else {
            if (!$isAjax && strlen($password) < 6) {
                $message = 'Password must be at least 6 characters.';
            } else {
                // Check each field separately so the conflicting value is clear.
                $emailCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $emailCheck->execute([$email]);
                $usernameCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $usernameCheck->execute([$username]);

                $duplicateFields = [];
                if ((int)$emailCheck->fetchColumn() > 0) $duplicateFields[] = 'email';
                if ((int)$usernameCheck->fetchColumn() > 0) $duplicateFields[] = 'username';
                if ($duplicateFields) {
                    $message = ucfirst(implode(' and ', $duplicateFields)) . ' already exists for another account.';
                    if ($isAjax) {
                        echo json_encode(['success' => false, 'message' => $message]);
                        exit;
                    }
                } else {
                    try {
                        $db->beginTransaction();
                        $hashed = password_hash($password, PASSWORD_DEFAULT);

                        // Generate short numeric code for verification
                        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $codeHash = hash('sha256', $code);
                        $codeExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                        // Create user - set status to 'pending' until email verification
                        $stmt = $db->prepare("INSERT INTO users (email, password, username, role, must_change_password, status, email_verified_at, email_verification_code_hash, email_verification_code_expires_at) VALUES (?, ?, ?, 'teacher', 1, 'pending', NULL, ?, ?)");
                        $stmt->execute([$email, $hashed, $username, $codeHash, $codeExpiresAt]);
                        $user_id = $db->lastInsertId();
                        // Create teacher profile
                        $stmt2 = $db->prepare("INSERT INTO teachers (user_id, first_name, last_name) VALUES (?, ?, ?)");
                        $stmt2->execute([$user_id, $first_name, $last_name]);

                        $teacherFullName = trim($first_name . ' ' . $last_name);
                        if (!sendAccountVerificationCodeEmail($email, $teacherFullName, $code, 'Atomix Admin')) {
                            throw new Exception('Verification code email could not be sent.');
                        }

                        $db->commit();
                        $message = 'Teacher account created successfully! Please verify the email before logging in.';

                        if ($isAjax) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Teacher account created. Verification email sent.',
                                'email_sent' => true
                            ]);
                            exit;
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('Create teacher error: ' . $e->getMessage());
                        $message = 'Failed to create teacher account. Please try again.';
                        if ($isAjax) {
                            echo json_encode(['success' => false, 'message' => $message]);
                            exit;
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Teacher Account</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            min-height: 100vh;
            color: #1e293b;
        }
        .form-card {
            max-width: 500px;
            margin: 40px auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .form-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }
        .form-card h2 {
            color: #1e40af;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 0.8rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
        }
        input:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        button {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #fff;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        .message {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            font-weight: 500;
        }
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Create Teacher Account</h2>
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" id="last_name" required>
            </div>
            <!-- Subject field removed -->
            <button type="submit">Create Teacher</button>
        </form>
        <a href="teachers_list.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Teachers List</a>
    </div>
</body>
</html>
