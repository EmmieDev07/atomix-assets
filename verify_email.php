<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/email_service.php';

$db = Database::getInstance()->getConnection();
$message = 'Verification links are no longer used. Please enter the 6-digit verification code sent to your email.';
$success = false;
$loginUrl = 'teacher/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Atomix</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            color: #0f172a;
        }
        .card {
            width: min(560px, calc(100vw - 32px));
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.15);
            padding: 2.5rem;
            text-align: center;
            border: 1px solid #dbeafe;
        }
        .status {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .status.success { background: #dcfce7; color: #166534; }
        .status.error { background: #fee2e2; color: #991b1b; }
        h1 { margin: 0 0 0.75rem; color: #1e40af; }
        p { margin: 0.5rem 0 0; line-height: 1.6; color: #334155; }
        a.button {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.9rem 1.4rem;
            border-radius: 12px;
            text-decoration: none;
            background: #1e40af;
            color: #fff;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="status <?php echo $success ? 'success' : 'error'; ?>"><?php echo $success ? 'Verified' : 'Not Verified'; ?></div>
        <h1>Email Verification</h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="button" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Go to Login</a>
    </main>
</body>
</html>