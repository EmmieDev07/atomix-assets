    <!-- Edit Teacher Modal -->
    <div class="modal" id="editTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Teacher</h3>
                <button class="close-btn" onclick="closeModal('editTeacherModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editTeacherForm" onsubmit="submitEditTeacher(event)">
                    <input type="hidden" id="edit_teacher_id" name="teacher_id">
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_first_name">First Name *</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name *</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editTeacherModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
/**
 * Teachers Management - Admin Side
 * RBAC: admin role required
 */
require_once '../includes/auth_check.php';
checkAdminPage();

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

// Get all teachers with their user info
$teachers = [];
$activeTeachers = [];
$archivedTeachers = [];
$loggedInTodayCount = 0;
$fetchError = null;
try {
    $stmt = $db->query("
        SELECT t.*, u.email, u.username, u.status, u.created_at, u.last_login, u.email_verified_at,
               tal.archive_reason, tal.archived_at AS archived_at_log
        FROM teachers t
        JOIN users u ON t.user_id = u.user_id
        LEFT JOIN (
            SELECT l.teacher_id, l.archive_reason, l.archived_at
            FROM teacher_archive_logs l
            INNER JOIN (
                SELECT teacher_id, MAX(log_id) AS max_log_id
                FROM teacher_archive_logs
                GROUP BY teacher_id
            ) latest ON latest.max_log_id = l.log_id
        ) tal ON tal.teacher_id = t.teacher_id
        WHERE u.role = 'teacher'
        ORDER BY t.last_name, t.first_name
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeTeachers = array_values(array_filter($teachers, function ($t) {
        return ($t['status'] ?? '') === 'active' && !empty($t['email_verified_at']);
    }));
    $pendingTeachers = array_values(array_filter($teachers, function ($t) {
        $status = ($t['status'] ?? '');
        return $status === 'pending' || ($status === 'active' && empty($t['email_verified_at']));
    }));
    $archivedTeachers = array_values(array_filter($teachers, function ($t) {
        return ($t['status'] ?? '') === 'inactive';
    }));
    $todayDate = date('Y-m-d');
    $loggedInTodayCount = count(array_filter($teachers, function ($teacher) use ($todayDate) {
        if (empty($teacher['last_login'])) {
            return false;
        }

        $lastLoginTimestamp = strtotime($teacher['last_login']);
        if ($lastLoginTimestamp === false) {
            return false;
        }

        return date('Y-m-d', $lastLoginTimestamp) === $todayDate;
    }));
} catch (PDOException $e) {
    error_log('Teachers list fetch error: ' . $e->getMessage());
    $fetchError = 'Failed to load teachers. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Management - Admin</title>
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

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #1e293b;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .teachers-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            color: #1e40af;
            padding: 2rem;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            border: 2px solid #e0f2fe;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e40af;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
            color: #64748b;
        }

        .teachers-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .teachers-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .teachers-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .teachers-card h2 i {
            color: #1e40af;
        }

        .teacher-tabs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.35rem;
        }

        .teacher-tab-btn {
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #4b5563;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: all 0.2s ease;
        }

        .teacher-tab-btn:hover {
            background: #e5e7eb;
            color: #1f2937;
        }

        .teacher-tab-btn.active {
            background: #ffffff;
            color: #1e40af;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .teacher-tab-pane {
            display: none;
        }

        .teacher-tab-pane.active {
            display: block;
        }

        .teacher-search-wrap {
            margin-bottom: 1.25rem;
        }

        .teacher-search-box {
            position: relative;
            width: 100%;
            max-width: 520px;
        }

        .teacher-search-box i {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.95rem;
        }

        .teacher-search-input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.6rem;
            border: 2px solid #dbe3ef;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #1e293b;
            background: #ffffff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .teacher-search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
        }

        .teacher-search-empty {
            display: none;
            text-align: center;
            margin-top: 1rem;
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 0.9rem 1rem;
        }

        .teacher-pagination {
            display: none;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .teacher-pagination-info {
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .teacher-pagination-controls {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .teacher-page-btn {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #334155;
            border-radius: 8px;
            min-width: 34px;
            height: 34px;
            padding: 0 0.6rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .teacher-page-btn:hover {
            border-color: #3b82f6;
            color: #1d4ed8;
            transform: translateY(-1px);
        }

        .teacher-page-btn.active {
            border-color: #1e40af;
            background: #1e40af;
            color: #ffffff;
        }

        .teacher-page-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        .teachers-grid {
            display: grid;
            gap: 1.5rem;
        }

        .teacher-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .teacher-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }

        .teacher-item.active {
            border-color: #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .teacher-info {
            flex: 1;
        }

        .teacher-info h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .teacher-meta {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .teacher-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .teacher-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .teacher-status.active {
            background: #dcfce7;
            color: #166534;
        }

        .teacher-status.inactive,
        .teacher-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .teacher-verified {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #d97706;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .teacher-actions {
            display: flex;
            gap: 1rem;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .message {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 5px solid;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            border-left-color: #22c55e;
        }

        .message.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        .empty-state h3 {
            color: #1e40af;
            margin-bottom: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 18px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 18px 18px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-actions {
            padding: 1.5rem 2rem;
            background: #f9fafb;
            border-radius: 0 0 18px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Toast Styles */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
            z-index: 1001;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem 0.5rem; }
            .sidebar { position: static; width: 100%; flex-direction: row; padding: 1rem; }
            .sidebar h2 { display: none; }
            .teachers-stats { grid-template-columns: 1fr; }
            .teachers-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="logo">
                <img src="../logoatomix.png" alt="Atomix Logo" style="height: 32px; width: auto;">
                <span>Atomix Admin</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="system_analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Analytics</span></a>
                <a href="school_years.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>School Years</span></a>
                <a href="classes.php" class="nav-item"><i class="fas fa-chalkboard"></i><span>Classes</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="teachers_list.php" class="nav-item active"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>Teachers Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">Teachers</span>
                </div>
            </nav>

            <div class="content-area">
                <h1 class="page-title"><i class="fas fa-users"></i> Teachers Management</h1>

                <?php if (isset($_GET['success'])): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>

                <?php if ($fetchError): ?>
                <div class="message error">
                    <i class="fas fa-database"></i>
                    <?php echo htmlspecialchars($fetchError); ?>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="teachers-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($teachers); ?></div>
                        <div class="stat-label">Total Teachers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($pendingTeachers); ?></div>
                        <div class="stat-label">Pending Verification Teachers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($archivedTeachers); ?></div>
                        <div class="stat-label">Inactive Teachers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $loggedInTodayCount; ?></div>
                        <div class="stat-label">Logged In Today</div>
                    </div>
                </div>

                <div class="teacher-tabs" role="tablist" aria-label="Teacher list tabs">
                    <button id="activeTeachersTabBtn" class="teacher-tab-btn active" type="button" onclick="showTeacherTab('active')">
                        <i class="fas fa-user-check"></i>
                        Active Teachers
                    </button>
                    <button id="pendingTeachersTabBtn" class="teacher-tab-btn" type="button" onclick="showTeacherTab('pending')">
                        <i class="fas fa-user-clock"></i>
                        Pending Teachers
                    </button>
                    <button id="archivedTeachersTabBtn" class="teacher-tab-btn" type="button" onclick="showTeacherTab('archived')">
                        <i class="fas fa-box-archive"></i>
                        Archived Teachers
                    </button>
                </div>

                <!-- Active Teachers Card -->
                <div id="activeTeachersPane" class="teacher-tab-pane active">
                <div class="teachers-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2><i class="fas fa-user-check"></i> Active Teachers</h2>
                        <button class="btn btn-success" onclick="openModal('addTeacherModal')">
                            <i class="fas fa-plus"></i> Add Teacher
                        </button>
                    </div>

                    <div class="teacher-search-wrap">
                        <div class="teacher-search-box">
                            <i class="fas fa-search"></i>
                            <input
                                type="text"
                                id="teacherSearchInput"
                                class="teacher-search-input teacher-search-sync"
                                placeholder="Search teacher by name, email, or username"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <?php if (empty($activeTeachers)): ?>
                    <div class="empty-state" style="padding: 1.5rem 1rem;">
                        <i class="fas fa-user-slash" style="font-size: 2rem;"></i>
                        <p>No active teachers found.</p>
                    </div>
                    <?php else: ?>
                    <div id="activeTeachersGrid" class="teachers-grid">
                        <?php foreach ($activeTeachers as $teacher): ?>
                        <div
                            class="teacher-item active"
                            data-id="<?php echo $teacher['teacher_id']; ?>"
                            data-search="<?php echo htmlspecialchars(strtolower($teacher['first_name'] . ' ' . $teacher['last_name'] . ' ' . $teacher['email'] . ' ' . $teacher['username'] . ' ' . ($teacher['last_login'] ?? ''))); ?>"
                        >
                            <div class="teacher-info">
                                <h4><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                                <div class="teacher-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></span>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($teacher['username']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($teacher['created_at']); ?></span>
                                    <?php if (empty($teacher['email_verified_at'])): ?>
                                    <span class="teacher-verified"><i class="fas fa-hourglass-half"></i> Pending verification</span>
                                    <?php endif; ?>
                                    <?php if (!empty($teacher['last_login'])): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($teacher['last_login']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="teacher-actions">
                                <span class="teacher-status <?php echo $teacher['status']; ?>">
                                    <?php echo ucfirst($teacher['status']); ?>
                                </span>
                                <button class="btn btn-sm btn-info" onclick="editTeacher(<?php echo $teacher['teacher_id']; ?>)" title="Edit Teacher">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;box-shadow:0 4px 15px rgba(139,92,246,.3);" onclick="openPermissions(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['first_name'] . ' ' . $teacher['last_name'])); ?>')" title="Manage Permissions">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <?php if (empty($teacher['email_verified_at'])): ?>
                                <button class="btn btn-sm btn-secondary" onclick="resendTeacherVerification(<?php echo $teacher['user_id']; ?>)" title="Resend verification email">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-warning" onclick="resetTeacherPassword(<?php echo $teacher['user_id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="archiveTeacher(<?php echo $teacher['teacher_id']; ?>)" title="Archive Teacher">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="activeTeachersSearchEmpty" class="teacher-search-empty">No matching active teachers.</div>
                    <div id="activeTeachersPagination" class="teacher-pagination"></div>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Pending Teachers Card -->
                <div id="pendingTeachersPane" class="teacher-tab-pane">
                <div class="teachers-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2><i class="fas fa-user-clock"></i> Pending Teachers</h2>
                    </div>

                    <div class="teacher-search-wrap">
                        <div class="teacher-search-box">
                            <i class="fas fa-search"></i>
                            <input
                                type="text"
                                id="teacherSearchInputPending"
                                class="teacher-search-input teacher-search-sync"
                                placeholder="Search pending teachers by name, email, or username"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <?php if (empty($pendingTeachers)): ?>
                    <div class="empty-state" style="padding: 1.5rem 1rem;">
                        <i class="fas fa-user-clock" style="font-size: 2rem;"></i>
                        <p>No pending teachers found.</p>
                    </div>
                    <?php else: ?>
                    <div id="pendingTeachersGrid" class="teachers-grid">
                        <?php foreach ($pendingTeachers as $teacher): ?>
                        <div
                            class="teacher-item"
                            data-id="<?php echo $teacher['teacher_id']; ?>"
                            data-search="<?php echo htmlspecialchars(strtolower($teacher['first_name'] . ' ' . $teacher['last_name'] . ' ' . $teacher['email'] . ' ' . $teacher['username'] . ' ' . ($teacher['last_login'] ?? ''))); ?>"
                        >
                            <div class="teacher-info">
                                <h4><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                                <div class="teacher-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></span>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($teacher['username']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($teacher['created_at']); ?></span>
                                    <?php if (!empty($teacher['last_login'])): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($teacher['last_login']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="teacher-actions">
                                <span class="teacher-status pending">
                                    Pending Verification
                                </span>
                                <button class="btn btn-sm btn-info" onclick="editTeacher(<?php echo $teacher['teacher_id']; ?>)" title="Edit Teacher">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;box-shadow:0 4px 15px rgba(139,92,246,.3);" onclick="openPermissions(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['first_name'] . ' ' . $teacher['last_name'])); ?>')" title="Manage Permissions">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="resendTeacherVerification(<?php echo $teacher['user_id']; ?>)" title="Resend verification email">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="resetTeacherPassword(<?php echo $teacher['user_id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="archiveTeacher(<?php echo $teacher['teacher_id']; ?>)" title="Archive Teacher">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="pendingTeachersSearchEmpty" class="teacher-search-empty">No matching pending teachers.</div>
                    <div id="pendingTeachersPagination" class="teacher-pagination"></div>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Archived / Inactive Teachers Card -->
                <div id="archivedTeachersPane" class="teacher-tab-pane">
                <div class="teachers-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2><i class="fas fa-box-archive"></i> Archived / Inactive Teachers</h2>
                    </div>

                    <div class="teacher-search-wrap">
                        <div class="teacher-search-box">
                            <i class="fas fa-search"></i>
                            <input
                                type="text"
                                id="teacherSearchInputArchived"
                                class="teacher-search-input teacher-search-sync"
                                placeholder="Search teacher by name, email, or username"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <?php if (empty($archivedTeachers)): ?>
                    <div class="empty-state" style="padding: 1.5rem 1rem;">
                        <i class="fas fa-box-open" style="font-size: 2rem;"></i>
                        <p>No archived teachers found.</p>
                    </div>
                    <?php else: ?>
                    <div id="archivedTeachersGrid" class="teachers-grid">
                        <?php foreach ($archivedTeachers as $teacher): ?>
                        <div
                            class="teacher-item"
                            data-id="<?php echo $teacher['teacher_id']; ?>"
                            data-search="<?php echo htmlspecialchars(strtolower($teacher['first_name'] . ' ' . $teacher['last_name'] . ' ' . $teacher['email'] . ' ' . $teacher['username'] . ' ' . ($teacher['last_login'] ?? '') . ' ' . ($teacher['archive_reason'] ?? ''))); ?>"
                        >
                            <div class="teacher-info">
                                <h4><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                                <div class="teacher-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></span>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($teacher['username']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($teacher['created_at']); ?></span>
                                    <?php if (!empty($teacher['last_login'])): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($teacher['last_login']); ?></span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-comment-dots"></i>
                                        Reason: <?php echo htmlspecialchars($teacher['archive_reason'] ?? 'No reason recorded'); ?>
                                        <?php if (!empty($teacher['archived_at_log'])): ?>
                                            (Archived: <?php echo htmlspecialchars($teacher['archived_at_log']); ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="teacher-actions">
                                <?php
                                    $statusClass = $teacher['status'];
                                    $statusLabel = ucfirst($teacher['status']);
                                    if ($teacher['status'] === 'active' && empty($teacher['email_verified_at'])) {
                                        $statusClass = 'pending';
                                        $statusLabel = 'Pending Verification';
                                    }
                                ?>
                                <span class="teacher-status <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                                <button class="btn btn-sm btn-success" onclick="activateTeacher(<?php echo $teacher['user_id']; ?>)" title="Activate Teacher">
                                    <i class="fas fa-user-check"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="editTeacher(<?php echo $teacher['teacher_id']; ?>)" title="Edit Teacher">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;box-shadow:0 4px 15px rgba(139,92,246,.3);" onclick="openPermissions(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['first_name'] . ' ' . $teacher['last_name'])); ?>')" title="Manage Permissions">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <?php if (empty($teacher['email_verified_at'])): ?>
                                <button class="btn btn-sm btn-secondary" onclick="resendTeacherVerification(<?php echo $teacher['user_id']; ?>)" title="Resend verification email">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-warning" onclick="resetTeacherPassword(<?php echo $teacher['user_id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="archivedTeachersSearchEmpty" class="teacher-search-empty">No matching archived teachers.</div>
                    <div id="archivedTeachersPagination" class="teacher-pagination"></div>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Add Teacher Modal -->
    <div class="modal" id="addTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add Teacher</h3>
                <button class="close-btn" onclick="closeModal('addTeacherModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addTeacherForm" onsubmit="submitTeacher(event)">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTeacherModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Permissions Modal -->
    <div class="modal" id="permissionsModal">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);">
                <h3><i class="fas fa-shield-alt"></i> Teacher Permissions</h3>
                <button class="close-btn" onclick="closeModal('permissionsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="permissionsTeacherName" style="font-weight:600;color:#374151;margin-bottom:1rem;"></p>
                <form id="permissionsForm">
                    <input type="hidden" id="perm_teacher_id">
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.6rem 0;border-bottom:1px solid #e5e7eb;">
                            <input type="checkbox" name="perms" value="can_manage_questions" style="width:18px;height:18px;accent-color:#7c3aed;">
                            <span><i class="fas fa-question-circle" style="color:#7c3aed;"></i> &nbsp;Manage Questions &amp; Question Bank</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.6rem 0;border-bottom:1px solid #e5e7eb;">
                            <input type="checkbox" name="perms" value="can_manage_quizzes" style="width:18px;height:18px;accent-color:#7c3aed;">
                            <span><i class="fas fa-clipboard-list" style="color:#7c3aed;"></i> &nbsp;Manage Quizzes &amp; Active Quizzes</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.6rem 0;border-bottom:1px solid #e5e7eb;">
                            <input type="checkbox" name="perms" value="can_view_reports" style="width:18px;height:18px;accent-color:#7c3aed;">
                            <span><i class="fas fa-chart-bar" style="color:#7c3aed;"></i> &nbsp;View Reports &amp; Game Progress</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.6rem 0;border-bottom:1px solid #e5e7eb;">
                            <input type="checkbox" name="perms" value="can_manage_classes" style="width:18px;height:18px;accent-color:#7c3aed;">
                            <span><i class="fas fa-users" style="color:#7c3aed;"></i> &nbsp;Manage Classes &amp; Class Students</span>
                        </label>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('permissionsModal')">Cancel</button>
                        <button type="submit" class="btn" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);color:white;">Save Permissions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"><span class="toast-message"></span></div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/teachers.js"></script>
    <script>
    async function openPermissions(teacherId, teacherName) {
        document.getElementById('perm_teacher_id').value = teacherId;
        document.getElementById('permissionsTeacherName').textContent = 'Teacher: ' + teacherName;
        document.querySelectorAll('#permissionsForm input[name="perms"]').forEach(cb => cb.checked = false);
        try {
            const res = await fetch('../api/teacher_api.php?action=get_permissions&id=' + teacherId);
            const data = await res.json();
            if (data.success) {
                data.permissions.forEach(perm => {
                    const cb = document.querySelector('#permissionsForm input[value="' + perm + '"]');
                    if (cb) cb.checked = true;
                });
            }
        } catch (e) { /* proceed with all unchecked if fetch fails */ }
        openModal('permissionsModal');
    }

    document.getElementById('permissionsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const teacherId = document.getElementById('perm_teacher_id').value;
        const checked = [...document.querySelectorAll('#permissionsForm input[name="perms"]:checked')]
            .map(cb => cb.value);
        try {
            const res = await fetch('../api/teacher_api.php?action=update_permissions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ teacher_id: parseInt(teacherId), permissions: checked })
            });
            const data = await res.json();
            closeModal('permissionsModal');
            if (data.success) {
                showToast('Permissions updated successfully');
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        } catch (err) {
            showToast('Network error. Please try again.', 'error');
        }
    });

    function showTeacherTab(tab) {
        const activeBtn = document.getElementById('activeTeachersTabBtn');
        const pendingBtn = document.getElementById('pendingTeachersTabBtn');
        const archivedBtn = document.getElementById('archivedTeachersTabBtn');
        const activePane = document.getElementById('activeTeachersPane');
        const pendingPane = document.getElementById('pendingTeachersPane');
        const archivedPane = document.getElementById('archivedTeachersPane');

        if (!activeBtn || !pendingBtn || !archivedBtn || !activePane || !pendingPane || !archivedPane) return;

        const isActiveTab = tab === 'active';
        const isPendingTab = tab === 'pending';

        activeBtn.classList.toggle('active', isActiveTab);
        pendingBtn.classList.toggle('active', isPendingTab);
        archivedBtn.classList.toggle('active', !isActiveTab && !isPendingTab);
        activePane.classList.toggle('active', isActiveTab);
        pendingPane.classList.toggle('active', isPendingTab);
        archivedPane.classList.toggle('active', !isActiveTab && !isPendingTab);

        try {
            localStorage.setItem('adminTeachersActiveTab', isActiveTab ? 'active' : isPendingTab ? 'pending' : 'archived');
        } catch (e) {
            // Ignore storage errors (private mode, storage disabled, etc.)
        }

        filterTeachers();
    }

    function filterTeacherPane(gridId, emptyId, query) {
        // legacy helper kept for backward compatibility
        applyTeacherPagination(gridId, emptyId, null, query, 1, true);
    }

    const TEACHERS_PER_PAGE = 10;
    const teacherPageState = { active: 1, pending: 1, archived: 1 };

    function getPaneIds(key) {
        if (key === 'archived') {
            return {
                gridId: 'archivedTeachersGrid',
                emptyId: 'archivedTeachersSearchEmpty',
                pagerId: 'archivedTeachersPagination'
            };
        }
        if (key === 'pending') {
            return {
                gridId: 'pendingTeachersGrid',
                emptyId: 'pendingTeachersSearchEmpty',
                pagerId: 'pendingTeachersPagination'
            };
        }
        return {
            gridId: 'activeTeachersGrid',
            emptyId: 'activeTeachersSearchEmpty',
            pagerId: 'activeTeachersPagination'
        };
    }

    function applyTeacherPagination(gridId, emptyId, pagerId, query, page, updatePager) {
        const grid = document.getElementById(gridId);
        const empty = document.getElementById(emptyId);
        const pager = pagerId ? document.getElementById(pagerId) : null;

        if (!grid) {
            if (empty) empty.style.display = 'none';
            if (pager) pager.style.display = 'none';
            return { page: 1, totalPages: 1, totalMatches: 0 };
        }

        const items = Array.from(grid.querySelectorAll('.teacher-item'));
        const matches = items.filter((item) => {
            const haystack = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
            return !query || haystack.includes(query);
        });

        const totalMatches = matches.length;
        const totalPages = Math.max(1, Math.ceil(totalMatches / TEACHERS_PER_PAGE));
        const safePage = Math.min(Math.max(1, page || 1), totalPages);
        const start = (safePage - 1) * TEACHERS_PER_PAGE;
        const end = start + TEACHERS_PER_PAGE;

        items.forEach((item) => {
            item.style.display = 'none';
        });

        matches.slice(start, end).forEach((item) => {
            item.style.display = 'flex';
        });

        if (empty) {
            empty.style.display = totalMatches === 0 ? 'block' : 'none';
        }

        if (pager) {
            if (!updatePager || totalMatches <= TEACHERS_PER_PAGE) {
                pager.style.display = totalMatches > 0 ? 'none' : 'none';
                pager.innerHTML = '';
            } else {
                pager.style.display = 'flex';
                renderTeacherPagination(pager, safePage, totalPages, totalMatches);
            }
        }

        return { page: safePage, totalPages, totalMatches };
    }

    function renderTeacherPagination(container, currentPage, totalPages, totalMatches) {
        const startItem = totalMatches === 0 ? 0 : ((currentPage - 1) * TEACHERS_PER_PAGE) + 1;
        const endItem = Math.min(currentPage * TEACHERS_PER_PAGE, totalMatches);

        const pageButtons = [];
        const windowStart = Math.max(1, currentPage - 2);
        const windowEnd = Math.min(totalPages, windowStart + 4);

        pageButtons.push(
            '<button type="button" class="teacher-page-btn" data-page="prev" ' + (currentPage === 1 ? 'disabled' : '') + '>Prev</button>'
        );

        for (let i = windowStart; i <= windowEnd; i++) {
            pageButtons.push(
                '<button type="button" class="teacher-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>'
            );
        }

        pageButtons.push(
            '<button type="button" class="teacher-page-btn" data-page="next" ' + (currentPage === totalPages ? 'disabled' : '') + '>Next</button>'
        );

        container.innerHTML =
            '<div class="teacher-pagination-info">Showing ' + startItem + '-' + endItem + ' of ' + totalMatches + '</div>' +
            '<div class="teacher-pagination-controls">' + pageButtons.join('') + '</div>';
    }

    function getTeacherSearchQuery() {
        const inputs = Array.from(document.querySelectorAll('.teacher-search-sync'));
        const firstWithValue = inputs.find((input) => (input.value || '').trim() !== '');
        const source = firstWithValue || inputs[0] || null;
        return source ? source.value.trim().toLowerCase() : '';
    }

    function syncTeacherSearchInputs(value, sourceInput) {
        document.querySelectorAll('.teacher-search-sync').forEach((input) => {
            if (input !== sourceInput) {
                input.value = value;
            }
        });
    }

    function filterTeachers(resetPage) {
        const shouldReset = !!resetPage;
        const query = getTeacherSearchQuery();

        ['active', 'pending', 'archived'].forEach((key) => {
            if (shouldReset) {
                teacherPageState[key] = 1;
            }

            const ids = getPaneIds(key);
            const result = applyTeacherPagination(
                ids.gridId,
                ids.emptyId,
                ids.pagerId,
                query,
                teacherPageState[key],
                true
            );
            teacherPageState[key] = result.page;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        let savedTab = 'active';
        try {
            const value = localStorage.getItem('adminTeachersActiveTab');
            if (value === 'active' || value === 'pending' || value === 'archived') {
                savedTab = value;
            }
        } catch (e) {
            // Keep default tab when storage is unavailable.
        }

        document.querySelectorAll('.teacher-search-sync').forEach((input) => {
            input.addEventListener('input', function (event) {
                syncTeacherSearchInputs(event.target.value, event.target);
                filterTeachers(true);
            });
        });

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.teacher-page-btn');
            if (!button) {
                return;
            }

            const activePane = document.getElementById('activeTeachersPane');
            const pendingPane = document.getElementById('pendingTeachersPane');
            const isActiveTab = activePane && activePane.classList.contains('active');
            const isPendingTab = pendingPane && pendingPane.classList.contains('active');
            const paneKey = isActiveTab ? 'active' : isPendingTab ? 'pending' : 'archived';
            const pageValue = button.getAttribute('data-page');
            const ids = getPaneIds(paneKey);
            const grid = document.getElementById(ids.gridId);
            if (!grid) {
                return;
            }

            const query = getTeacherSearchQuery();
            const totalMatches = Array.from(grid.querySelectorAll('.teacher-item')).filter((item) => {
                const haystack = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
                return !query || haystack.includes(query);
            }).length;
            const totalPages = Math.max(1, Math.ceil(totalMatches / TEACHERS_PER_PAGE));

            let nextPage = teacherPageState[paneKey] || 1;
            if (pageValue === 'prev') {
                nextPage = Math.max(1, nextPage - 1);
            } else if (pageValue === 'next') {
                nextPage = Math.min(totalPages, nextPage + 1);
            } else {
                nextPage = parseInt(pageValue, 10) || 1;
            }

            teacherPageState[paneKey] = nextPage;
            filterTeachers(false);
        });

        showTeacherTab(savedTab);
        filterTeachers(true);
    });
    </script>
    <script>
    function confirmAdminLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Log Out?',
            text: 'Are you sure you want to log out?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then(r => { if (r.isConfirmed) window.location.href = 'logout.php'; });
    }
    </script>
