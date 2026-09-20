<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
$teacher_id = getTeacherId();

$dbError = null;
try {
    $stmt = $db->prepare("
        SELECT t.teacher_id, t.first_name, t.last_name,
               u.email, u.username, u.created_at, u.last_login, u.status
        FROM teachers t
        JOIN users u ON t.user_id = u.user_id
        WHERE t.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        $dbError = 'Profile not found.';
        $profile = [];
    }
} catch (PDOException $e) {
    error_log('profile.php DB error: ' . $e->getMessage());
    $dbError = 'A database error occurred. Please try again.';
    $profile = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }
        .profile-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 28px 24px;
            box-shadow: 0 2px 12px rgba(37,99,235,.08);
        }
        .profile-card h3 {
            margin: 0 0 20px;
            font-size: 1.1rem;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-active   { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: .95rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-weight: 500; }
        .info-value { color: #1e293b; font-weight: 600; }
        .form-feedback { font-size: .85rem; margin-top: 6px; }
        .form-feedback.success { color: #166534; }
        .form-feedback.error   { color: #991b1b; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <h1>My Profile</h1>
            <a href="profile.php" class="user-info" title="My Profile">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <i class="fas fa-user-circle"></i>
            </a>
        </header>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">My Profile</span>
        </nav>

        <?php if ($dbError): ?>
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <div class="content-area">
            <div class="profile-grid">

                <!-- ── Account Info ───────────────────────────────────────── -->
                <div class="profile-card">
                    <h3><i class="fas fa-id-card"></i> Account Information</h3>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="profile-badge <?php echo ($profile['status'] ?? '') === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <i class="fas <?php echo ($profile['status'] ?? '') === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo ucfirst(htmlspecialchars($profile['status'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($profile['username'] ?? ''); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Member since</span>
                        <span class="info-value"><?php echo htmlspecialchars($profile['created_at'] ? date('M j, Y', strtotime($profile['created_at'])) : '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last login</span>
                        <span class="info-value"><?php echo htmlspecialchars($profile['last_login'] ? date('M j, Y g:i A', strtotime($profile['last_login'])) : 'Never'); ?></span>
                    </div>
                </div>

                <!-- ── Edit Profile ───────────────────────────────────────── -->
                <div class="profile-card">
                    <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                    <form id="profileForm" onsubmit="saveProfile(event)" novalidate>
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="first_name" class="form-control"
                                   value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" maxlength="50" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="last_name" class="form-control"
                                   value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" maxlength="50" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" maxlength="100" required>
                        </div>
                        <div class="modal-actions" style="padding:0;margin-top:16px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                        <div id="profileFeedback" class="form-feedback" style="display:none;"></div>
                    </form>
                </div>

                <!-- ── Change Password ────────────────────────────────────── -->
                <div class="profile-card" style="grid-column: 1 / -1;">
                    <h3><i class="fas fa-lock"></i> Change Password</h3>
                    <form id="passwordForm" onsubmit="changePassword(event)" novalidate style="max-width:480px;">
                        <div class="form-group">
                            <label for="currentPassword">Current Password *</label>
                            <input type="password" id="currentPassword" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password *</label>
                            <input type="password" id="newPassword" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password *</label>
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="modal-actions" style="padding:0;margin-top:16px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </div>
                        <div id="passwordFeedback" class="form-feedback" style="display:none;"></div>
                    </form>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- Toast -->
<div class="toast" id="toast"><span class="toast-message"></span></div>

<script>
function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.querySelector('.toast-message').textContent = msg;
    t.style.background = isError ? '#ef4444' : '#22c55e';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function showFeedback(id, msg, isError) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.className = 'form-feedback ' + (isError ? 'error' : 'success');
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

async function saveProfile(e) {
    e.preventDefault();
    const form = document.getElementById('profileForm');
    const data = new FormData(form);
    data.append('action', 'update');
    try {
        const res = await fetch('../api/profile_api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            showFeedback('profileFeedback', 'Profile updated successfully!', false);
            showToast('Profile saved.');
        } else {
            showFeedback('profileFeedback', json.message || 'Update failed.', true);
        }
    } catch {
        showFeedback('profileFeedback', 'Network error. Please try again.', true);
    }
}

async function changePassword(e) {
    e.preventDefault();
    const np = document.getElementById('newPassword').value;
    const cp = document.getElementById('confirmPassword').value;
    if (np !== cp) {
        showFeedback('passwordFeedback', 'New passwords do not match.', true);
        return;
    }
    const data = new FormData(document.getElementById('passwordForm'));
    data.append('action', 'change_password');
    try {
        const res = await fetch('../api/profile_api.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            showFeedback('passwordFeedback', 'Password updated successfully!', false);
            document.getElementById('passwordForm').reset();
            showToast('Password changed.');
        } else {
            showFeedback('passwordFeedback', json.message || 'Change failed.', true);
        }
    } catch {
        showFeedback('passwordFeedback', 'Network error. Please try again.', true);
    }
}
</script>
</body>
</html>
