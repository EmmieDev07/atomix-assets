<?php
/**
 * Students Management - Admin Side
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$dbError = null;
$studentStats = ['total_students' => 0, 'active_students' => 0, 'inactive_students' => 0, 'total_classes' => 0];

// Function to generate student action buttons
$studentActions = function($student) {
    return '<button class="btn btn-sm btn-warning" onclick="resetStudentPassword(' . $student['user_id'] . ')"><i class="fas fa-key"></i></button>';
};

$schoolYears = [];
$classes = [];
$students = [];
$selectedSyId = null;
$selectedSchoolYear = null;

try {
    // Get all school years (active and archived)
    $schoolYears = $db->query("SELECT sy_id, label, is_active FROM school_year ORDER BY is_active DESC, label DESC")->fetchAll();

    // Get selected school year from GET or default to latest active
    $selectedSyId = isset($_GET['sy_id']) ? intval($_GET['sy_id']) : null;
    if (!$selectedSyId) {
        foreach ($schoolYears as $sy) {
            if ($sy['is_active']) { $selectedSyId = $sy['sy_id']; break; }
        }
        if (!$selectedSyId && count($schoolYears)) {
            $selectedSyId = $schoolYears[0]['sy_id'];
        }
    }

    foreach ($schoolYears as $schoolYear) {
        if ((int) $schoolYear['sy_id'] === (int) $selectedSyId) {
            $selectedSchoolYear = $schoolYear;
            break;
        }
    }

    if ($selectedSchoolYear) {
        $statQueries = [
            'total_students' => "SELECT COUNT(DISTINCT s.student_id)
                                 FROM students s
                                 JOIN class_students cs ON s.student_id = cs.student_id
                                 JOIN classes c ON cs.class_id = c.class_id
                                 JOIN school_year sy ON c.sy_id = sy.sy_id
                                 WHERE sy.sy_id = ?",
            'active_students' => "SELECT COUNT(DISTINCT s.student_id)
                                  FROM students s
                                  JOIN users u ON s.user_id = u.user_id
                                  JOIN class_students cs ON s.student_id = cs.student_id
                                  JOIN classes c ON cs.class_id = c.class_id
                                  JOIN school_year sy ON c.sy_id = sy.sy_id
                                  WHERE sy.sy_id = ? AND u.status = 'active'",
            'inactive_students' => "SELECT COUNT(DISTINCT s.student_id)
                                    FROM students s
                                    JOIN users u ON s.user_id = u.user_id
                                    JOIN class_students cs ON s.student_id = cs.student_id
                                    JOIN classes c ON cs.class_id = c.class_id
                                    JOIN school_year sy ON c.sy_id = sy.sy_id
                                    WHERE sy.sy_id = ? AND u.status = 'inactive'",
            'total_classes' => "SELECT COUNT(DISTINCT c.class_id)
                                FROM classes c
                                JOIN school_year sy ON c.sy_id = sy.sy_id
                                WHERE sy.sy_id = ?"
        ];

        foreach ($statQueries as $key => $sql) {
            $statement = $db->prepare($sql);
            $statement->execute([$selectedSyId]);
            $studentStats[$key] = (int) $statement->fetchColumn();
        }
    }

    // Get all classes for filter dropdown (for selected school year)
    $classesStmt = $db->prepare("SELECT c.class_id, c.class_name, sy.label as school_year FROM classes c JOIN school_year sy ON c.sy_id = sy.sy_id WHERE sy.sy_id = ? ORDER BY c.class_name");
    $classesStmt->execute([$selectedSyId]);
    $classes = $classesStmt->fetchAll();

    // Get students for selected school year
    $studentsStmt = $db->prepare("
        SELECT s.*, u.email, u.username, u.status, u.created_at, u.last_login,
               cs.class_id, c.class_name, sy.label as school_year
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN class_students cs ON s.student_id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.class_id
        LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
        WHERE sy.sy_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $studentsStmt->execute([$selectedSyId]);
    $students = $studentsStmt->fetchAll();
} catch (Exception $e) {
    error_log('Students page error: ' . $e->getMessage());
    if (empty($dbError)) $dbError = 'Failed to load student list. Please try again.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - Atomix Admin</title>
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

        .students-stats {
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

        .students-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .students-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .students-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .students-card h2 i {
            color: #1e40af;
        }

        .filters-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-bar {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            min-width: 200px;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .students-grid {
            display: grid;
            gap: 1rem;
        }

        .student-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .student-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }

        .student-item.hidden {
            display: none;
        }

        .students-pagination {
            display: none;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 0.9rem;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .students-pagination-info {
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .students-pagination-controls {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .students-page-btn {
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

        .students-page-btn:hover {
            border-color: #3b82f6;
            color: #1d4ed8;
            transform: translateY(-1px);
        }

        .students-page-btn.active {
            border-color: #1e40af;
            background: #1e40af;
            color: #ffffff;
        }

        .students-page-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        .student-info {
            flex: 1;
        }

        .student-info h4 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .student-meta {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .student-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .student-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .student-status.active {
            background: #dcfce7;
            color: #166534;
        }

        .student-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .student-actions {
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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

        .toast.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
        }

        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.3);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Centered Modal Styles */
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(2,6,23,0.45);
            z-index: 1100;
            padding: 1.5rem;
        }

        /* Ensure SweetAlert2 appears above modals */
        .swal2-container {
            z-index: 20000 !important;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            width: 560px;
            max-width: 100%;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(2,6,23,0.25);
            border: 1px solid rgba(226,232,240,0.8);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eef2f7;
            background: transparent;
        }

        .modal-body { padding: 1.25rem 1.5rem; }

        .close-btn {
            background: transparent;
            border: none;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
            color: #475569;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem 1.5rem;
            background: transparent;
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem 0.5rem; }
            .sidebar { position: static; width: 100%; flex-direction: row; padding: 1rem; }
            .sidebar h2 { display: none; }
            .students-stats { grid-template-columns: 1fr; }
            .students-grid { grid-template-columns: 1fr; }
            .filters-section { flex-direction: column; align-items: stretch; }
            .search-bar { min-width: auto; }
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
                <a href="students.php" class="nav-item active"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="teachers_list.php" class="nav-item"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>Students Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">Students</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
                <h1 class="page-title"><i class="fas fa-users"></i> Students Management</h1>
                <form method="get" style="margin-bottom: 2rem;">
                    <label for="sy_id"><b>School Year:</b></label>
                    <select name="sy_id" id="sy_id" onchange="this.form.submit()" class="filter-select">
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['sy_id']; ?>" <?php if ($sy['sy_id'] == $selectedSyId) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sy['label']); ?><?php if (!$sy['is_active']) echo ' (Archived)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

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

                <!-- Statistics Cards -->
                <div class="students-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($studentStats['total_students']); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($studentStats['active_students']); ?></div>
                        <div class="stat-label">Active Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($studentStats['inactive_students']); ?></div>
                        <div class="stat-label">Inactive Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($studentStats['total_classes']); ?></div>
                        <div class="stat-label">Total Classes</div>
                    </div>
                </div>

                <!-- Students List Card -->
                <div class="students-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2><i class="fas fa-users"></i> All Students (<?php echo number_format($studentStats['total_students']); ?>)</h2>
                        <div style="display:flex; gap:0.75rem;">
                            <a class="btn btn-primary" href="../api/download_students_template.php">
                                <i class="fas fa-file-download"></i> Download Template
                            </a>
                            <button type="button" class="btn btn-warning" onclick="openModal('uploadStudentsModal')">
                                <i class="fas fa-file-upload"></i> Upload Excel
                            </button>
                            <button type="button" class="btn btn-success" onclick="openModal('addStudentModal')">
                                <i class="fas fa-plus"></i> Add New Student
                            </button>
                        </div>
                    </div>

                    <!-- Filters Section -->
                    <div class="filters-section">
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" id="studentSearch" placeholder="Search students by name or username..." onkeyup="filterStudents()">
                        </div>
                        <select class="filter-select" id="classFilter" onchange="filterStudents()">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['school_year'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (empty($students)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Students Found</h3>
                        <p>Get started by adding your first student to the system.</p>
                    </div>
                    <?php else: ?>
                    <div class="students-grid" id="studentsGrid">
                        <?php foreach ($students as $student): ?>
                        <div class="student-item" data-class="<?php echo htmlspecialchars((string)($student['class_id'] ?? '')); ?>" data-name="<?php echo htmlspecialchars(strtolower($student['first_name'] . ' ' . $student['last_name'])); ?>" data-username="<?php echo htmlspecialchars(strtolower($student['username'])); ?>">
                            <div class="student-info">
                                <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                <div class="student-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($student['username']); ?></span>
                                    <span><i class="fas fa-school"></i> <?php echo htmlspecialchars($student['class_name'] ?? ''); ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($student['school_year'] ?? 'N/A'); ?></span>
                                    <?php if (!empty($student['last_login'])): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($student['last_login']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="student-actions">
                                <span class="student-status <?php echo $student['status']; ?>">
                                    <i class="fas <?php echo $student['status'] === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                                <button class="btn btn-sm btn-info" onclick="editStudent(<?php echo $student['student_id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($student['status'] === 'active'): ?>
                                <button class="btn btn-sm btn-danger" onclick="archiveStudent(<?php echo $student['user_id']; ?>)">
                                    Active
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-success" onclick="unarchiveStudent(<?php echo $student['user_id']; ?>)">
                                    Inactive
                                </button>
                                <?php endif; ?>
                                <?php echo $studentActions($student); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="studentsPagination" class="students-pagination"></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Student Modal -->
    <!-- Add Student Modal -->
    <div class="modal" id="addStudentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add Student</h3>
                <button class="close-btn" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm" onsubmit="submitAddStudent(event)">
                    <div class="form-group">
                        <label for="addFirstName">First Name *</label>
                        <input type="text" id="addFirstName" name="first_name" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="addLastName">Last Name *</label>
                        <input type="text" id="addLastName" name="last_name" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="addClass">Class *</label>
                        <select id="addClass" name="class_id" required>
                            <option value="">Select class</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' (' . $class['school_year'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="addGender">Gender *</label>
                        <select id="addGender" name="gender" required>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal" id="editStudentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Student</h3>
                <button class="close-btn" onclick="closeModal('editStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editStudentForm" onsubmit="updateStudent(event)">
                    <input type="hidden" id="editStudentId" name="student_id">
                    <div class="form-group">
                        <label for="editFirstName">First Name *</label>
                        <input type="text" id="editFirstName" name="first_name" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="editLastName">Last Name *</label>
                        <input type="text" id="editLastName" name="last_name" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="editUsername">Username *</label>
                        <input type="text" id="editUsername" name="username" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="editClass">Class</label>
                        <select id="editClass" name="class_id">
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['school_year'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select id="editStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="uploadStudentsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-upload"></i> Upload Students</h3>
                <button class="close-btn" onclick="closeModal('uploadStudentsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="uploadClassId">Class *</label>
                    <select id="uploadClassId" required>
                        <option value="">Select class</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' (' . $class['school_year'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="studentFileUploadArea" style="border:2px dashed #cbd5e1;border-radius:12px;padding:1.25rem;text-align:center;background:#f8fafc;">
                    <div class="file-upload-content">
                        <p style="margin:0 0 8px;color:#475569;"><i class="fas fa-file-excel"></i> Drag and drop an Excel/CSV file here</p>
                        <p style="margin:0 0 12px;color:#64748b;font-size:0.9rem;">Supported: .xlsx, .xls, .csv (max 2MB)</p>
                        <button type="button" class="btn btn-primary file-upload-link">Choose File</button>
                    </div>
                    <input type="file" id="studentExcelFile" name="student_file" accept=".xlsx,.xls,.csv" style="display:none;">
                    <div id="studentFileInfo" style="display:none;align-items:center;justify-content:space-between;gap:0.75rem;margin-top:0.75rem;">
                        <span id="studentFileName" style="color:#1e293b;font-weight:600;"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearStudentFile()"><i class="fas fa-times"></i> Remove</button>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadStudentsModal')">Cancel</button>
                <button type="button" class="btn btn-success" onclick="uploadStudents()">Preview & Import</button>
            </div>
        </div>
    </div>

    <div class="modal" id="previewStudentsModal">
        <div class="modal-content" style="width:980px;max-width:100%;">
            <div class="modal-header">
                <h3><i class="fas fa-table"></i> Preview Students</h3>
                <button class="close-btn" onclick="closeModal('previewStudentsModal')">&times;</button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow:auto;">
                <div id="previewTableContainerModal"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('previewStudentsModal')">Close</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"><span class="toast-message"></span></div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/students.js"></script>
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
</body>
</html>