<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$error = '';

// Skip current-password check only when the system requires a first-time change
$mustChange = !empty($_SESSION['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password  = $_POST['current_password']  ?? '';
    $new_password      = $_POST['new_password']      ?? '';
    $confirm_password  = $_POST['confirm_password']  ?? '';

    // Verify current password (skip only on forced first-time change)
    if (!$mustChange) {
        $userStmt = $db->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $userStmt->execute([$_SESSION['user_id']]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$userRow || !password_verify($current_password, $userRow['password'])) {
            $error = 'Current password is incorrect.';
        }
    }

    if (!$error) {
        // --- Strong Password Validation Requirements ---
        $uppercase   = preg_match('@[A-Z]@', $new_password);
        $lowercase   = preg_match('@[a-z]@', $new_password);
        $number      = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);

        if (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif (!$uppercase) {
            $error = 'Password must include at least one uppercase letter (A-Z).';
        } elseif (!$lowercase) {
            $error = 'Password must include at least one lowercase letter (a-z).';
        } elseif (!$number) {
            $error = 'Password must include at least one number (0-9).';
        } elseif (!$specialChars) {
            $error = 'Password must include at least one special character (!@#$%^&* etc.).';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            unset($_SESSION['must_change_password']);
            header('Location: dashboard.php?pw_changed=1');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(120deg, #e0f2fe 0%, #2563eb 100%);
        }
        .form-card {
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(37,99,235,0.13);
            padding: 2.5rem 2rem 2rem 2rem;
            position: relative;
            overflow: hidden;
        }
        .form-card .icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb 60%, #0a7fc2 100%);
            color: #fff;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            font-size: 2.2rem;
            margin: 0 auto 1.2rem auto;
            box-shadow: 0 4px 16px #2563eb33;
        }
        .form-card h2 {
            color: #2563eb;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 1.3rem;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            color: #1e293b;
            font-weight: 500;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            border: 1.5px solid #93c5fd;
            background: #f1f5f9;
            font-size: 1rem;
            transition: border 0.2s;
        }
        input[type="password"]:focus {
            border: 1.5px solid #2563eb;
            outline: none;
            background: #e0f2fe;
        }
        button[type="submit"] {
            background: linear-gradient(90deg, #2563eb 60%, #0a7fc2 100%);
            color: #fff;
            border: none;
            padding: 0.9rem 0;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            box-shadow: 0 2px 8px #2563eb33;
            transition: background 0.2s, box-shadow 0.2s;
            margin-top: 0.5rem;
        }
        button[type="submit"]:hover {
            background: linear-gradient(90deg, #0a7fc2 0%, #2563eb 100%);
            box-shadow: 0 6px 24px #2563eb33;
        }
        .error {
            color: #b91c1c;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.7rem 1rem;
            margin-bottom: 1.2rem;
            text-align: center;
            font-size: 0.95rem;
        }
        /* Password Requirements List */
        .pw-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.8rem 1rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .pw-requirements p {
            margin: 0 0 0.4rem 0;
            font-weight: 600;
            color: #475569;
        }
        .pw-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.3rem 0.5rem;
        }
        .pw-requirements li {
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: color 0.2s ease, font-weight 0.2s ease;
        }
        /* Active status when requirement is satisfied (Light Green) */
        .pw-requirements li.valid {
            color: #22c55e; /* Light Green */
            font-weight: 600;
        }
        .pw-requirements li i {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="form-card">
        <div class="icon">
            <i class="fa-solid fa-key"></i>
        </div>
        <h2>Change Password</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" id="passwordForm">
            <?php if (!$mustChange): ?>
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password" required placeholder="Enter current password">
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
                
                <!-- Live Password Strength Checker UI -->
                <div class="pw-requirements">
                    <p>Password must contain:</p>
                    <ul>
                        <li id="req-length"><i class="fa-solid fa-circle"></i> Min 8 chars</li>
                        <li id="req-upper"><i class="fa-solid fa-circle"></i> 1 Uppercase</li>
                        <li id="req-lower"><i class="fa-solid fa-circle"></i> 1 Lowercase</li>
                        <li id="req-number"><i class="fa-solid fa-circle"></i> 1 Number</li>
                        <li id="req-special" style="grid-column: span 2;"><i class="fa-solid fa-circle"></i> 1 Special char (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Re-enter new password">
            </div>

            <button type="submit"><i class="fa-solid fa-arrow-rotate-right" style="margin-right:8px;"></i>Change Password</button>
        </form>
    </div>

    <script>
        const newPwInput = document.getElementById('new_password');
        const reqs = {
            length: { el: document.getElementById('req-length'), regex: /.{8,}/ },
            upper:  { el: document.getElementById('req-upper'),  regex: /[A-Z]/ },
            lower:  { el: document.getElementById('req-lower'),  regex: /[a-z]/ },
            number: { el: document.getElementById('req-number'), regex: /[0-9]/ },
            special:{ el: document.getElementById('req-special'),regex: /[^\w]/ }
        };

        newPwInput.addEventListener('input', function() {
            const val = newPwInput.value;
            for (let key in reqs) {
                const isValid = reqs[key].regex.test(val);
                const icon = reqs[key].el.querySelector('i');
                if (isValid) {
                    reqs[key].el.classList.add('valid');
                    icon.className = 'fa-solid fa-circle-check';
                } else {
                    reqs[key].el.classList.remove('valid');
                    icon.className = 'fa-solid fa-circle';
                }
            }
        });
    </script>
</body>
</html>