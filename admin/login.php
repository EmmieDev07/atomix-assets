<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jwt_helper.php';
require_once '../config/math_captcha.php';

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

// Check if already logged in via session
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Also check JWT token
$jwtUser = JWTHelper::authenticate();
if ($jwtUser && $jwtUser['role'] === 'admin') {
    // Restore session from JWT
    $_SESSION['user_id'] = $jwtUser['user_id'];
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = $jwtUser['name'];
    header('Location: dashboard.php');
    exit;
}

$error = '';
// Show a friendly message when the session timed out
if (!empty($_GET['timeout'])) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

// Cancel 2FA process
if (isset($_GET['cancel_2fa'])) {
    unset($_SESSION['temp_admin_user']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- STEP 2: VERIFY SECURITY QUESTION ---
    if (isset($_POST['action']) && $_POST['action'] === 'verify_security') {
        $answer = trim(strtolower($_POST['security_answer'] ?? ''));
        $tempUser = $_SESSION['temp_admin_user'] ?? null;

        if ($tempUser && !empty($answer)) {
            $correctHash = $tempUser['selected_answer_hash'];

            if (password_verify($answer, $correctHash)) {
                // SUCCESS: Log Admin in
                $_SESSION['user_id'] = $tempUser['user_id'];
                $_SESSION['role'] = $tempUser['role'];
                $_SESSION['name'] = $tempUser['username'];
                $_SESSION['last_activity'] = time();

                unset($_SESSION['temp_admin_user']);

                $db = Database::getInstance()->getConnection();
                $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$tempUser['user_id']]);

                $userData = [
                    'user_id' => $tempUser['user_id'],
                    'email' => $tempUser['email'] ?? $tempUser['username'],
                    'name' => $tempUser['username'],
                    'role' => 'admin'
                ];
                $tokens = JWTHelper::generateTokenPair($userData);
                JWTHelper::setTokenCookies($tokens['access_token'], $tokens['refresh_token'], 'admin');

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Incorrect security answer.';
            }
        } else {
            $error = 'Please enter your security answer.';
        }
    } 
    // --- STEP 1: VERIFY CREDENTIALS & CAPTCHA WITH EMAIL VALIDATION ---
    else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $captchaAnswer = trim($_POST['captcha_answer'] ?? '');
        
        if (empty($username)) {
            $error = 'Please enter your email address.';
        } else if (empty($password)) {
            $error = 'Please enter your password.';
        } else {
            // Email Syntax Validation
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else if (empty($captchaAnswer) || !MathCaptcha::verify($captchaAnswer, $_SESSION['math_captcha']['answer'])) {
                $error = 'Please solve the math problem correctly.';
            } else {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Pick a random security question from configured columns (q1, q2, q3)
                    $qNum = rand(1, 3);
                    
                    if (!empty($user['security_q' . $qNum])) {
                        $_SESSION['temp_admin_user'] = [
                            'user_id' => $user['user_id'],
                            'username' => $user['username'],
                            'role' => $user['role'],
                            'email' => $user['email'] ?? $user['username'],
                            'selected_question' => $user['security_q' . $qNum],
                            'selected_answer_hash' => $user['security_a' . $qNum]
                        ];
                    } else {
                        // Direct login fallback if no security question set
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['username'];
                        $_SESSION['last_activity'] = time();

                        $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

                        $userData = [
                            'user_id' => $user['user_id'],
                            'email' => $user['email'] ?? $user['username'],
                            'name' => $user['username'],
                            'role' => 'admin'
                        ];
                        $tokens = JWTHelper::generateTokenPair($userData);
                        JWTHelper::setTokenCookies($tokens['access_token'], $tokens['refresh_token'], 'admin');

                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            min-height: 100vh;
            color: #1e293b;
            background: #ffffff;
        }

        .split-layout {
            display: flex;
            min-height: 100vh;
        }

        .left-panel {
            flex: 0 0 48%;
            background: linear-gradient(180deg,#0f1724 0%, #123047 60%);
            color: #cfe8ff;
            padding: 6rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.5rem;
        }

        .left-panel .logo-wrap {
            display:flex; align-items:center; gap:1rem;
        }

        .hero-icon {
            height:72px; width:72px; display:flex; align-items:center; justify-content:center; border-radius:14px; background: rgba(255,255,255,0.04);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }

        .left-panel h1 {
            margin:0; font-size:2rem; color:#ffffff; font-weight:700;
        }

        .left-panel p.lead { color: rgba(255,255,255,0.75); max-width: 380px; }

        .features { margin-top:1.5rem; display:flex; flex-direction:column; gap:0.8rem; }

        .feature { display:flex; gap:0.75rem; align-items:center; color: rgba(255,255,255,0.9); }

        .feature .fa-stack { background: rgba(255,255,255,0.03); padding:0.6rem; border-radius:8px; }

        .right-panel { flex:1; display:flex; align-items:center; justify-content:center; padding:4rem; }

        .auth-card {
            width:100%; max-width:420px; background: #ffffff; border-radius:12px; padding:2.5rem; box-shadow:0 12px 40px rgba(2,6,23,0.12);
            border:1px solid rgba(2,6,23,0.04);
        }

        .badge { display:inline-block; padding:0.35rem 0.75rem; background:#0b4a6f; color:#cfe8ff; border-radius:999px; font-size:0.75rem; font-weight:700; }

        .auth-card h2 { margin:0.5rem 0 0.5rem 0; font-size:1.25rem; color:#0b2540; }
        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; margin-bottom:0.35rem; color:#334155; font-weight:600; }
        .input-with-icon { position:relative; }
        .input-with-icon input { width:100%; padding:0.85rem 1rem; padding-right:3.5rem; border-radius:10px; border:1px solid #e6eef6; box-sizing: border-box; }
        .input-icon { position:absolute; right:0.6rem; top:50%; transform:translateY(-50%); color:#94a3b8; cursor:pointer; }
        .btn-primary { background:#0b2540; color:#fff; border:none; padding:0.85rem 1rem; border-radius:10px; width:100%; font-weight:700; cursor:pointer; }
        .btn-primary:hover { opacity:0.95; }
        .error-message { background:#fff6f6; color:#991b1b; padding:0.9rem; border-radius:8px; margin-bottom:1rem; border:1px solid #fecaca; }

        .back-link { display:block; margin-top:1rem; text-align:center; color:#64748b; font-size:0.9rem; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
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
            border: 1px solid #e6eef6;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0b2540;
            letter-spacing: 1px;
        }
        .math-operator {
            font-size: 1.2rem;
            font-weight: 600;
            color: #334155;
        }
        .math-answer-box {
            width: 58px;
            height: 44px;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0b2540;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .math-answer-box:focus {
            border-color: #0b4a6f;
            box-shadow: 0 0 0 3px rgba(11,74,111,0.1);
        }
        .math-refresh-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            padding: 4px 6px;
            border-radius: 6px;
            transition: color 0.2s, background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .math-refresh-btn:hover {
            color: #0b4a6f;
            background: #eff6ff;
        }
    </style>
</head>
<body>
    <div class="split-layout">
        <aside class="left-panel">
            <div class="logo-wrap">
                <div class="hero-icon"><i class="fas fa-shield-halved" style="font-size:22px;color:#cfe8ff"></i></div>
                <div>
                    <h1>Admin Control</h1>
                    <p class="lead">Secure access to the Atomix administration panel for complete platform management.</p>
                </div>
            </div>

            <div class="features">
                <div class="feature"><span class="fa-stack"><i class="fas fa-users"></i></span>Manage teachers &amp; students</div>
                <div class="feature"><span class="fa-stack"><i class="fas fa-school"></i></span>Organize classes &amp; sections</div>
                <div class="feature"><span class="fa-stack"><i class="fas fa-chart-pie"></i></span>View comprehensive reports</div>
                <div class="feature"><span class="fa-stack"><i class="fas fa-cog"></i></span>System configuration</div>
            </div>
        </aside>

        <section class="right-panel">
            <div class="auth-card">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="badge"><i class="fas fa-lock" style="margin-right:6px;font-size:0.85rem"></i> RESTRICTED ACCESS</span>
                    <img src="../logoatomix.png" alt="Atomix" style="height:28px;opacity:0.85">
                </div>

                <?php if ($error): ?>
                <div class="error-message" style="margin-top:1rem;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- STEP 2: SECURITY VERIFICATION -->
                <?php if (isset($_SESSION['temp_admin_user'])): ?>
                    <h2>Security Verification</h2>
                    <p style="color:#6b7280;margin-top:0.25rem;margin-bottom:1rem;">Answer your security question to complete login.</p>

                    <form method="POST">
                        <input type="hidden" name="action" value="verify_security">
                        <div class="form-group">
                            <label style="color:#0b2540; font-size: 0.95rem; margin-bottom: 8px;">
                                <i class="fas fa-question-circle" style="color:#0b4a6f;"></i> 
                                <?php echo htmlspecialchars($_SESSION['temp_admin_user']['selected_question']); ?>
                            </label>
                            <div class="input-with-icon">
                                <input type="text" name="security_answer" placeholder="Enter your answer" required autofocus autocomplete="off">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-shield-check" style="margin-right:8px"></i> Verify & Sign In</button>
                        <a href="login.php?cancel_2fa=1" class="back-link">&larr; Cancel Login</a>
                    </form>

                <!-- STEP 1: INITIAL CREDENTIALS ENTRY -->
                <?php else: ?>
                    <h2>Admin Login</h2>
                    <p style="color:#6b7280;margin-top:0.25rem;margin-bottom:1rem;">Enter your credentials to access the dashboard</p>

                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Email Address</label>
                            <div class="input-with-icon">
                                <input type="email" id="username" name="username" placeholder="admin@atomix.com" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-with-icon">
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                <span class="input-icon" id="togglePassword"><i class="fas fa-eye"></i></span>
                            </div>
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
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-sign-in-alt" style="margin-right:8px"></i> Sign In to Dashboard</button>
                    </form>

                    <a href="../index.php" class="back-link">&larr; Back to Portal Selection</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        // Toggle password visibility
        const toggle = document.getElementById('togglePassword');
        if (toggle) {
            toggle.addEventListener('click', function(){
                const pw = document.getElementById('password');
                if (!pw) return;
                if (pw.type === 'password') { pw.type = 'text'; toggle.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
                else { pw.type = 'password'; toggle.innerHTML = '<i class="fas fa-eye"></i>'; }
            });
        }

        // AJAX captcha refresh
        const refreshBtn = document.getElementById('refreshCaptcha');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                fetch('login.php?refresh_captcha=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('captchaNum1').textContent = data.num1;
                    document.getElementById('captchaOperator').textContent = data.operator;
                    document.getElementById('captchaNum2').textContent = data.num2;
                    document.getElementById('captcha_answer').value = '';
                    document.getElementById('captcha_answer').focus();
                });
            });
        }
    });
</script>
</html>