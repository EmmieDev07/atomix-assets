<?php
session_start();
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

$message = '';
$error = '';
$editUser = null;

// Handle Edit Fetch Request
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$_GET['edit_id']]);
    $editUser = $stmt->fetch();
}

// Handle Form Submission (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $userId = $_POST['user_id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($action === 'create' && strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($action === 'update' && !empty($password) && strlen($password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        try {
            if ($action === 'create') {
                // Check if email already exists
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                $checkStmt->execute([$username]);
                
                if ($checkStmt->fetch()) {
                    $error = 'An account with this email address already exists.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $db->prepare("
                        INSERT INTO users (username, password, role, created_at)
                        VALUES (?, ?, 'admin', NOW())
                    ");
                    $stmt->execute([$username, $hashed_password]);
                    $message = 'New admin account created successfully!';
                }
            } elseif ($action === 'update' && $userId) {
                // Update Email
                $updateStmt = $db->prepare("UPDATE users SET username = ? WHERE user_id = ? AND role = 'admin'");
                $updateStmt->execute([$username, $userId]);

                // Update Password if provided
                if (!empty($password)) {
                    $pwHash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$pwHash, $userId]);
                }

                $message = 'Admin account updated successfully!';
                
                // Refresh edited user details
                $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin' LIMIT 1");
                $stmt->execute([$userId]);
                $editUser = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all existing admins
$adminsList = $db->query("SELECT user_id, username, created_at, last_login FROM users WHERE role = 'admin' ORDER BY user_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account Manager</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f1724; color: #fff; margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 768px) { .container { grid-template-columns: 1fr; } }
        .card { background: #1e293b; padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #94a3b8; }
        input { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid #334155; background: #0f1724; color: #fff; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0284c7; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-top: 10px; font-size: 15px; }
        button:hover { background: #0369a1; }
        .btn-cancel { background: #475569; margin-top: 5px; text-decoration: none; display: block; text-align: center; padding: 10px; border-radius: 6px; color: white; font-weight: 600; font-size: 13px; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .error { background: #7f1d1d; color: #fca5a5; border: 1px solid #f87171; }
        .success { background: #14532d; color: #86efac; border: 1px solid #4ade80; }
        h2 { margin-top: 0; color: #f8fafc; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; }
        .edit-link { color: #38bdf8; text-decoration: none; font-weight: 600; }
        .edit-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div style="max-width:900px; margin: 0 auto;">
        <h2>Admin Manager</h2>
        <p style="color:#94a3b8; font-size:13px; margin-bottom:25px;">Create new administrator accounts or edit existing ones without altering database tables.</p>

        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    </div>

    <div class="container">
        <!-- FORM: CREATE / EDIT -->
        <div class="card">
            <h2><?php echo $editUser ? 'Edit Admin #' . $editUser['user_id'] : 'Create New Admin'; ?></h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Admin Email Address</label>
                    <input type="email" name="username" value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label><?php echo $editUser ? 'New Password (Leave blank to keep existing)' : 'Password (Min 8 chars)'; ?></label>
                    <input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                </div>

                <button type="submit"><?php echo $editUser ? 'Save Account Changes' : 'Create Admin Account'; ?></button>
                <?php if ($editUser): ?>
                    <a href="manage_admin.php" class="btn-cancel">Cancel Editing</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- LIST: EXISTING ADMINS -->
        <div class="card">
            <h2>Existing Admins (<?php echo count($adminsList); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminsList as $adm): ?>
                    <tr>
                        <td>#<?php echo $adm['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($adm['username']); ?></td>
                        <td>
                            <a href="manage_admin.php?edit_id=<?php echo $adm['user_id']; ?>" class="edit-link">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>