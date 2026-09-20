<?php
session_start();

require_once '../config/database.php';
require_once '../includes/jwt_helper.php';
require_once '../config/math_captcha.php';
require_once '../includes/auth_check.php';

$db = Database::getInstance()->getConnection();

// Generate math captcha for this session
if (!isset($_SESSION['math_captcha'])) {
    $_SESSION['math_captcha'] = MathCaptcha::generate();
}

// AJAX captcha refresh
if (isset($_GET['refresh_captcha']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $_SESSION['math_captcha'] = MathCaptcha::generate();
    header('Content-Type: application/json');
    echo json_encode($_SESSION['math_captcha']);
    exit;
}

// AJAX check for pending verification by email
if (isset($_GET['check_pending']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }
    $stmt = $db->prepare("SELECT user_id, status, email_verified_at FROM users WHERE email = ? AND role = 'teacher' LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => true, 'pending' => false]);
        exit;
    }
    $isPending = empty($row['email_verified_at']) || ($row['status'] ?? '') !== 'active';
    echo json_encode(['success' => true, 'pending' => (bool)$isPending]);
    exit;
}

// Redirect if already logged in (check session or JWT)
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'teacher') {
    header('Location: dashboard.php');
    exit;
}

// Also check JWT token
$jwtUser = JWTHelper::authenticate();
if ($jwtUser && $jwtUser['role'] === 'teacher') {
    $accountStmt = $db->prepare("SELECT status, email_verified_at FROM users WHERE user_id = ? AND role = 'teacher' LIMIT 1");
    $accountStmt->execute([(int) $jwtUser['user_id']]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if ($account && $account['status'] === 'active' && !empty($account['email_verified_at'])) {
        $_SESSION['user_id'] = $jwtUser['user_id'];
        $_SESSION['teacher_id'] = $jwtUser['teacher_id'];
        $_SESSION['email'] = $jwtUser['email'];
        $_SESSION['name'] = $jwtUser['name'];
        $_SESSION['role'] = 'teacher';
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
if (!empty($_GET['timeout'])) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captchaAnswer = trim($_POST['captcha_answer'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } elseif (empty($captchaAnswer) || !MathCaptcha::verify($captchaAnswer, $_SESSION['math_captcha']['answer'])) {
        $error = 'Please solve the math problem correctly.';
    } else {
        $stmt = $db->prepare("\n            SELECT u.*, t.teacher_id, t.first_name, t.last_name\n            FROM users u\n            JOIN teachers t ON u.user_id = t.user_id\n            WHERE u.email = ? AND u.role = 'teacher' LIMIT 1\n        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Already verified -> normal password login
            if (!empty($user['email_verified_at']) && $user['status'] === 'active') {
                if (password_verify($password, $user['password'])) {
                    // proceed with normal login
                } else {
                    $error = 'Invalid email or password.';
                    // stop further processing
                    $user = null;
                }
            }
        }

        if ($user && !empty($user['email_verified_at']) && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['teacher_id'] = $user['teacher_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = 'teacher';
            $_SESSION['last_activity'] = time();

            $userData = [
                'user_id' => $user['user_id'],
                'teacher_id' => $user['teacher_id'],
                'email' => $user['email'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => 'teacher'
            ];
            $tokens = JWTHelper::generateTokenPair($userData);
            JWTHelper::setTokenCookies($tokens['access_token'], $tokens['refresh_token'], 'teacher');

            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);

            if (!empty($user['must_change_password']) && $user['must_change_password'] == 1) {
                header('Location: change_password.php');
                exit;
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode([
                    'success' => true,
                    'user_id' => $user['user_id'],
                    'access_token' => $tokens['access_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => JWT_ACCESS_TOKEN_EXPIRY
                ]);
                exit;
            }

            header('Location: dashboard.php');
            exit;
        } else {
            // handle unverified account: accept verification code in password field
            $pendingStmt = $db->prepare("SELECT user_id, email_verification_code_hash, email_verification_code_expires_at FROM users WHERE email = ? AND role = 'teacher' AND email_verified_at IS NULL LIMIT 1");
            $pendingStmt->execute([$email]);
            $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
            if ($pending) {
                // If submitted password matches the numeric code, verify and log in
                if (preg_match('/^[0-9]{6}$/', $password) && !empty($pending['email_verification_code_hash'])) {
                    if (new DateTime() > new DateTime($pending['email_verification_code_expires_at'])) {
                        $error = 'Verification code has expired. Please ask admin to resend.';
                    } elseif (hash('sha256', $password) === $pending['email_verification_code_hash']) {
                        // mark verified, activate, and require first-time password setup
                        $update = $db->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_code_hash = NULL, email_verification_code_expires_at = NULL, status = 'active', must_change_password = 1 WHERE user_id = ?");
                        $update->execute([$pending['user_id']]);

                        // fetch teacher profile for session
                        $stmt2 = $db->prepare("SELECT u.*, t.teacher_id, t.first_name, t.last_name FROM users u JOIN teachers t ON u.user_id = t.user_id WHERE u.user_id = ? LIMIT 1");
                        $stmt2->execute([$pending['user_id']]);
                        $userRecord = $stmt2->fetch();
                        if ($userRecord) {
                            // send confirmation email (best-effort)
                            $teacherFullName = trim($userRecord['first_name'] . ' ' . $userRecord['last_name']);
                            sendAccountVerifiedEmail($userRecord['email'], $teacherFullName, MAIL_FROM_NAME);

                            // proceed to create session as if logged in
                            $_SESSION['user_id'] = $userRecord['user_id'];
                            $_SESSION['teacher_id'] = $userRecord['teacher_id'];
                            $_SESSION['email'] = $userRecord['email'];
                            $_SESSION['name'] = $userRecord['first_name'] . ' ' . $userRecord['last_name'];
                            $_SESSION['role'] = 'teacher';
                            $_SESSION['last_activity'] = time();
                            $_SESSION['must_change_password'] = 1;

                            $userData = [
                                'user_id' => $userRecord['user_id'],
                                'teacher_id' => $userRecord['teacher_id'],
                                'email' => $userRecord['email'],
                                'name' => $userRecord['first_name'] . ' ' . $userRecord['last_name'],
                                'role' => 'teacher'
                            ];
                            $tokens = JWTHelper::generateTokenPair($userData);
                            JWTHelper::setTokenCookies($tokens['access_token'], $tokens['refresh_token'], 'teacher');

                            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                            $updateStmt->execute([$userRecord['user_id']]);

                            header('Location: change_password.php');
                            exit;
                        }
                    } else {
                        $error = 'Invalid verification code.';
                    }
                } elseif (empty($password)) {
                    $error = 'Account pending verification. Please open the verification modal and enter the code sent to your email.';
                } else {
                    $error = 'Please enter your 6-digit verification code as the password for first login.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - Atomix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f6f8fa;
        }
        .split-container {
            display: flex;
            min-height: 100vh;
        }
        .left-panel {
            flex: 1 1 0;
            background: linear-gradient(135deg, #0a4d8c 0%, #07689F 50%, #0a7fc2 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
        }
        .left-panel .logo {
            margin-bottom: 32px;
        }
        .left-panel .logo img {
            width: 220px;
            max-width: 90%;
            height: auto;
        }
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .welcome-desc {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 32px;
            text-align: center;
            max-width: 420px;
        }
        .features-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-top: 12px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            padding: 10px 18px;
            width: 340px;
            max-width: 100%;
        }
        .feature-item i {
            font-size: 1.3rem;
            color: #fff;
            background: rgba(37,99,235,0.18);
            border-radius: 50%;
            padding: 8px;
        }
        .right-panel {
            flex: 1 1 0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(37,99,235,0.08);
            padding: 40px 32px 32px 32px;
        }

        /* Animations */
        .left-panel, .login-box { opacity: 0; transform: translateY(18px) scale(0.995); transition: opacity 480ms ease, transform 420ms cubic-bezier(.2,.9,.2,1); }
        .left-panel.enter, .login-box.enter { opacity: 1; transform: translateY(0) scale(1); }

        .left-panel .logo img { will-change: transform, filter; transition: transform 420ms ease, filter 360ms ease; }
        .left-panel .logo.animate { animation: teacherLogoFloat 4s ease-in-out infinite; }

        @keyframes teacherLogoFloat {
            0% { transform: translateY(0) rotate(-1deg) }
            45% { transform: translateY(-10px) rotate(1deg) }
            70% { transform: translateY(-4px) rotate(-0.5deg) }
            100% { transform: translateY(0) rotate(0deg) }
        }
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: #232946;
            margin-bottom: 8px;
            text-align: center;
        }
        .login-desc {
            font-size: 1rem;
            color: #6b7280;
            margin-bottom: 28px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            font-weight: 600;
            color: #232946;
            margin-bottom: 6px;
            display: block;
        }
        .input-group {
            display: flex;
            align-items: center;
            background: #f3f4f6;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            padding: 0 16px;
            margin-bottom: 0.2rem;
            position: relative;
        }
        .input-group i {
            font-size: 1.2rem;
            color: #2563eb;
            margin-right: 12px;
            position: static;
            left: unset;
            top: unset;
            transform: none;
        }
        .input-group input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 16px;
            color: #232946;
            padding: 14px 0;
            outline: none;
        }
        .input-group input:focus {
            background: transparent;
            box-shadow: none;
        }
        .toggle-password {
            background: none;
            border: none;
            color: #2563eb;
            margin-left: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
        }
        .toggle-password:hover {
            color: #1e3a8a;
        }
        .math-captcha-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }
        .math-num-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 52px;
            height: 44px;
            padding: 0 10px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            font-size: 1.15rem;
            font-weight: 700;
            color: #374151;
            letter-spacing: 1px;
        }
        .math-operator {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
        }
        .math-answer-box {
            width: 58px;
            height: 44px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #374151;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .math-answer-box:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .math-refresh-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.05rem;
            padding: 4px 6px;
            border-radius: 6px;
            transition: color 0.2s, background 0.2s;
            display: flex;
            align-items: center;
        }
        .math-refresh-btn:hover {
            color: #2563eb;
            background: #eff6ff;
        }
        .forgot-link {
            display: block;
            text-align: right;
            color: #2563eb;
            font-size: 0.98rem;
            margin-bottom: 18px;
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, #2563eb 0%, #1e3a8a 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(37,99,235,0.12);
            transition: background 0.2s, transform 0.2s;
        }
        .btn-login:hover {
            background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%);
            transform: translateY(-2px);
        }
        .info-box {
            background: #f3f8ff;
            color: #2563eb;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 22px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e5e7eb;
        }
        .info-box i {
            font-size: 1.2rem;
        }
        @media (max-width: 900px) {
            .split-container {
                flex-direction: column;
            }
            .left-panel, .right-panel {
                min-height: 420px;
                padding: 32px 10px;
            }
            .left-panel .logo img { width: 140px; }
        }

        @media (max-width: 640px) {
            body {
                align-items: flex-start;
                justify-content: flex-start;
            }

            .split-container {
                min-height: 100vh;
            }

            .left-panel {
                min-height: auto;
                padding: 28px 18px;
                text-align: center;
            }

            .left-panel .logo img {
                width: 112px;
            }

            .welcome-title {
                font-size: 1.9rem;
                line-height: 1.15;
            }

            .welcome-desc {
                font-size: 0.98rem;
                margin-bottom: 22px;
            }

            .features-list {
                width: 100%;
            }

            .feature-item {
                width: 100%;
                padding: 10px 14px;
                font-size: 0.95rem;
            }

            .right-panel {
                padding: 18px 12px 28px;
                align-items: flex-start;
            }

            .login-box {
                max-width: none;
                padding: 26px 18px 20px;
                border-radius: 16px;
            }

            .login-title {
                font-size: 1.6rem;
            }

            .login-desc {
                font-size: 0.95rem;
                margin-bottom: 20px;
            }

            .input-group {
                padding: 0 12px;
            }

            .input-group input {
                font-size: 15px;
                padding: 13px 0;
            }

            .math-captcha-row {
                flex-wrap: wrap;
                gap: 8px;
            }

            .math-num-box,
            .math-answer-box {
                height: 42px;
            }

            .math-answer-box {
                width: 64px;
            }

            .forgot-link {
                text-align: left;
            }

            .btn-login {
                font-size: 16px;
                padding: 14px;
            }

            .info-box {
                font-size: 0.95rem;
                padding: 12px 14px;
            }
        }

        @media (max-width: 420px) {
            .left-panel {
                padding: 24px 14px;
            }

            .welcome-title {
                font-size: 1.65rem;
            }

            .login-box {
                padding: 22px 14px 18px;
            }

            .form-group {
                margin-bottom: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="left-panel">
            <div class="logo">
                <img src="../logoatomix.png" alt="Atomix Logo">
            </div>
            <div class="welcome-title">Welcome Back!</div>
            <div class="welcome-desc">
                Empower your teaching journey with Atomix -<br>
                the modern platform for creating engaging assessments.
            </div>
            <div class="features-list">
                <div class="feature-item"><i class="fas fa-clipboard-list"></i> Create interactive quizzes easily</div>
                <div class="feature-item"><i class="fas fa-chart-line"></i> Track student progress in real-time</div>
                <div class="feature-item"><i class="fas fa-users"></i> Manage multiple classes effortlessly</div>
                <div class="feature-item"><i class="fas fa-gamepad"></i> Gamified learning experience</div>
            </div>
        </div>
        <div class="right-panel">
            <div class="login-box">
                <div class="login-title">Teacher Login</div>
                <div style="text-align: center; margin-bottom: 10px;">
                    <a href="../index.php" style="color: #2563eb; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Back to Login Portal
                    </a>
                </div>
                <div class="login-desc">Enter your credentials to access your dashboard</div>
                <form class="login-form" method="POST" action="">
                    <?php if (!empty($error)): ?>
                        <div class="server-error" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px;border-radius:8px;margin-bottom:12px;font-weight:600;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error" data-error-for="email" style="color:#dc2626;font-size:0.9rem;margin-top:6px;display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password">
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="field-error" data-error-for="password" style="color:#dc2626;font-size:0.9rem;margin-top:6px;display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Verify you're a human</label>
                        <div class="math-captcha-row">
                            <span class="math-num-box" id="captchaNum1"><?php echo $_SESSION['math_captcha']['num1']; ?></span>
                            <span class="math-operator" id="captchaOperator"><?php echo htmlspecialchars($_SESSION['math_captcha']['operator']); ?></span>
                            <span class="math-num-box" id="captchaNum2"><?php echo $_SESSION['math_captcha']['num2']; ?></span>
                            <span class="math-operator">=</span>
                            <input type="text" class="math-answer-box" id="captcha_answer" name="captcha_answer" maxlength="4" autocomplete="off">
                            <button type="button" class="math-refresh-btn" id="refreshCaptcha" title="Get a new problem">
                                <i class="fas fa-rotate-right"></i>
                            </button>
                        </div>
                        <div class="field-error" data-error-for="captcha" style="color:#dc2626;font-size:0.9rem;margin-top:6px;display:none;"></div>
                    </div>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> Contact your administrator if you need an account
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Client-side validation helpers
        function clearFieldError(fieldId) {
            var err = document.querySelector('.field-error[data-error-for="' + fieldId + '"]');
            if (err) {
                err.style.display = 'none';
                err.textContent = '';
            }
            var field = document.getElementById(fieldId);
            if (field) field.classList.remove('input-error');
        }

        function clearServerError() {
            var sev = document.querySelector('.server-error');
            if (sev) {
                sev.style.display = 'none';
            }
        }

        function setFieldError(fieldId, message) {
            var err = document.querySelector('.field-error[data-error-for="' + fieldId + '"]');
            if (err) {
                err.style.display = 'block';
                err.textContent = message;
            }
            var field = document.getElementById(fieldId);
            if (field) field.classList.add('input-error');
        }

        function validateEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(String(email).toLowerCase());
        }

        function validateLoginForm(evt) {
            var form = document.querySelector('.login-form');
            if (!form) return true;
            var email = form.email.value.trim();
            var password = form.password.value || '';
            var valid = true;

            clearFieldError('email');
            clearFieldError('password');

            if (!email) {
                setFieldError('email', 'Email is required.');
                valid = false;
            } else if (!validateEmail(email)) {
                setFieldError('email', 'Please enter a valid email address.');
                valid = false;
            } else if (!password) {
                setFieldError('password', 'Password is required.');
                valid = false;
            }

            if (!valid) {
                evt.preventDefault();
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('.login-form');
            if (form) {
                form.addEventListener('submit', function(evt){
                    if (!validateLoginForm(evt)) return;
                    evt.preventDefault();
                    var email = form.email.value.trim();
                    var password = form.password.value || '';
                    fetch('?check_pending=1&email=' + encodeURIComponent(email), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (data && data.success && data.pending) {
                                window.__pendingLogin = { email: email, password: password };
                                openVerificationModal(email);
                            } else {
                                if (!password) {
                                    setFieldError('password', 'Password is required.');
                                    return;
                                }
                                if (password.length < 6) {
                                    setFieldError('password', 'Password must be at least 6 characters.');
                                    return;
                                }
                                form.submit();
                            }
                        }).catch(function(){
                            if (!password) {
                                setFieldError('password', 'Password is required.');
                                return;
                            }
                            if (password.length < 6) {
                                setFieldError('password', 'Password must be at least 6 characters.');
                                return;
                            }
                            form.submit();
                        });
                });
                // Clear errors on input and also clear the server error when user starts editing
                var inputs = form.querySelectorAll('input');
                inputs.forEach(function(i){
                    i.addEventListener('input', function(){
                        clearFieldError(i.id);
                        clearServerError();
                    });
                });
            }
            // animate entry
            const left = document.querySelector('.left-panel');
            const box = document.querySelector('.login-box');
            const logoImg = document.querySelector('.left-panel .logo img');
            setTimeout(() => {
                if (left) left.classList.add('enter');
                if (box) box.classList.add('enter');
            }, 120);
            // start logo float after entry
            setTimeout(() => { if (logoImg) logoImg.classList.add('animate'); }, 420);

            // Captcha refresh button
            var refreshBtn = document.getElementById('refreshCaptcha');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    fetch('?refresh_captcha=1', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        document.getElementById('captchaNum1').textContent = data.num1;
                        document.getElementById('captchaNum2').textContent = data.num2;
                        document.getElementById('captchaOperator').textContent = data.operator;
                        document.getElementById('captcha_answer').value = '';
                        clearFieldError('captcha');
                    });
                });
            }

            // Add mouse parallax to logo on non-touch, wide screens
            const isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
            if (!isTouch && window.innerWidth > 900 && logoImg && left) {
                let mouseX = 0, mouseY = 0, rx = 0, ry = 0;
                const maxTranslate = 16; // px
                const ease = 0.08;
                let wrapperRect = left.getBoundingClientRect();

                function onMove(e) {
                    // update rect occasionally in case of layout changes
                    wrapperRect = left.getBoundingClientRect();
                    const x = e.clientX - (wrapperRect.left + wrapperRect.width / 2);
                    const y = e.clientY - (wrapperRect.top + wrapperRect.height / 2);
                    mouseX = (x / (wrapperRect.width / 2));
                    mouseY = (y / (wrapperRect.height / 2));
                }

                function update() {
                    rx += (mouseX - rx) * ease;
                    ry += (mouseY - ry) * ease;
                    const tx = -rx * maxTranslate;
                    const ty = -ry * (maxTranslate * 0.6);
                    const r = rx * 5; // rotation deg
                    logoImg.style.transform = `translate3d(${tx}px, ${ty}px, 0) rotate(${r}deg)`;
                    requestAnimationFrame(update);
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseleave', () => { mouseX = 0; mouseY = 0; });
                // start RAF loop
                update();
            }
        });
</script>

    <!-- Verification Modal -->
    <div id="verificationModal" style="display:none;position:fixed;z-index:1100;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
        <div style="background:#fff;padding:22px;border-radius:12px;max-width:420px;width:92%;box-shadow:0 12px 36px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0">Account Verification</h3>
            <p id="verificationModalMessage">Please enter the 6-digit code sent to your email.</p>
            <form id="verificationModalForm">
                <input type="hidden" id="vm_email" name="email">
                <div class="form-group">
                    <label for="vm_code">Verification Code</label>
                    <div class="input-group">
                        <i class="fa fa-key"></i>
                        <input type="text" id="vm_code" name="code" maxlength="6" placeholder="6-digit code" required>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;align-items:center;">
                    <button type="button" id="vm_resend_btn" onclick="resendVerificationCode()" style="background:#3b82f6;color:#fff;border:none;padding:8px 12px;border-radius:8px;">Resend code</button>
                    <span id="vm_resend_countdown" style="font-size:0.9rem;color:#374151;display:none;margin-right:auto;"> </span>
                    <button type="button" onclick="closeVerificationModal()" style="background:#6b7280;color:#fff;border:none;padding:8px 14px;border-radius:8px;">Cancel</button>
                    <button type="submit" style="background:#10b981;color:#fff;border:none;padding:8px 14px;border-radius:8px;">Verify</button>
                </div>
                <div id="vm_error" style="color:#991b1b;font-weight:600;margin-top:10px;display:none;"></div>
                <div id="vm_success" style="color:#065f46;background:#ecfdf5;border:1px solid #bbf7d0;padding:8px;border-radius:6px;margin-top:8px;display:none;font-weight:600;"></div>
            </form>
        </div>
    </div>
    <script>
        function openVerificationModal(email) {
            document.getElementById('vm_email').value = email || '';
            document.getElementById('vm_code').value = '';
            document.getElementById('vm_error').style.display = 'none';
            document.getElementById('vm_success').style.display = 'none';
            var m = document.getElementById('verificationModal');
            if (m) m.style.display = 'flex';
            // Reset resend button state on open
            resetResendState();
        }
        function closeVerificationModal() {
            var m = document.getElementById('verificationModal');
            if (m) m.style.display = 'none';
        }
        document.getElementById('verificationModalForm').addEventListener('submit', function(e){
            e.preventDefault();
            var email = document.getElementById('vm_email').value;
            var code = document.getElementById('vm_code').value.trim();
            if (!/^[0-9]{6}$/.test(code)) {
                var err = document.getElementById('vm_error'); err.style.display = 'block'; err.textContent = 'Please enter a valid 6-digit code.'; return;
            }
            fetch('../api/verify_code.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
                body: 'email=' + encodeURIComponent(email) + '&code=' + encodeURIComponent(code)
            }).then(function(r){ return r.json(); }).then(function(data){
                if (data && data.success) {
                        // On success, redirect to change password so teacher can create a new password
                        window.location.href = data.redirect || '/finalweb/teacher/change_password.php';
                    } else {
                    var err = document.getElementById('vm_error'); err.style.display = 'block'; err.textContent = data && data.message ? data.message : 'Verification failed';
                }
            }).catch(function(){ var err = document.getElementById('vm_error'); err.style.display = 'block'; err.textContent = 'Network error'; });
        });

        // Resend logic with cooldown
        var vmCooldownSeconds = 60;
        var vmCooldownTimer = null;
        function resetResendState() {
            var btn = document.getElementById('vm_resend_btn');
            var cd = document.getElementById('vm_resend_countdown');
            if (btn) { btn.disabled = false; btn.textContent = 'Resend code'; btn.style.opacity = '1'; }
            if (cd) { cd.style.display = 'none'; cd.textContent = ''; }
            if (vmCooldownTimer) { clearInterval(vmCooldownTimer); vmCooldownTimer = null; }
        }

        function startResendCountdown(seconds) {
            var remaining = seconds;
            var btn = document.getElementById('vm_resend_btn');
            var cd = document.getElementById('vm_resend_countdown');
            if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
            if (cd) { cd.style.display = 'inline-block'; }
            if (vmCooldownTimer) clearInterval(vmCooldownTimer);
            vmCooldownTimer = setInterval(function(){
                if (!cd) return;
                cd.textContent = 'You can resend in ' + remaining + 's';
                remaining -= 1;
                if (remaining < 0) {
                    clearInterval(vmCooldownTimer); vmCooldownTimer = null; if (btn) { btn.disabled = false; btn.style.opacity = '1'; } if (cd) { cd.style.display = 'none'; }
                }
            }, 1000);
        }

        function resendVerificationCode() {
            var email = document.getElementById('vm_email').value;
            if (!email) return;
            var btn = document.getElementById('vm_resend_btn');
            btn.disabled = true; btn.textContent = 'Sending...';
            fetch('../api/resend_verification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
                body: 'email=' + encodeURIComponent(email)
            }).then(function(r){ return r.json(); }).then(function(data){
                if (data && data.success) {
                    var success = document.getElementById('vm_success'); success.style.display = 'block'; success.textContent = 'Verification code resent to your email.';
                    document.getElementById('vm_error').style.display = 'none';
                    startResendCountdown(vmCooldownSeconds);
                } else {
                    var err = document.getElementById('vm_error'); err.style.display = 'block'; err.textContent = data && data.message ? data.message : 'Failed to resend code';
                }
                btn.disabled = false; btn.textContent = 'Resend code';
            }).catch(function(){ var err = document.getElementById('vm_error'); err.style.display = 'block'; err.textContent = 'Network error'; btn.disabled = false; btn.textContent = 'Resend code'; });
        }
    </script>
</body>
</html>
</html>
