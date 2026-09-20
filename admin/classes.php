<?php
require_once '../includes/auth_check.php';
checkAdminPage();

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$dbError = null;
$classes = [];
$teachers = [];
$schoolYears = [];
$activeSchoolYear = null;

try {
    $classes = $db->query(
        "SELECT c.class_id, c.class_name, c.sy_id, c.teacher_id,
                sy.label AS school_year, sy.is_active,
                t.first_name, t.last_name,
                u.status AS teacher_status
         FROM classes c
         JOIN school_year sy ON c.sy_id = sy.sy_id
         LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
         LEFT JOIN users u ON t.user_id = u.user_id
         ORDER BY c.class_id DESC"
    )->fetchAll();

    $teachers = $db->query(
        "SELECT t.teacher_id, t.first_name, t.last_name
         FROM teachers t
         JOIN users u ON t.user_id = u.user_id
         WHERE u.status = 'active'
         ORDER BY t.last_name, t.first_name"
    )->fetchAll();

    $schoolYears = $db->query(
        "SELECT sy_id, label, is_active
         FROM school_year
         ORDER BY is_active DESC, sy_id DESC"
    )->fetchAll();

    foreach ($schoolYears as $schoolYear) {
        if (!empty($schoolYear['is_active'])) {
            $activeSchoolYear = $schoolYear;
            break;
        }
    }
} catch (PDOException $e) {
    error_log('Admin classes page error: ' . $e->getMessage());
    $dbError = 'Failed to load classes data.';
}

$activeClasses = array_values(array_filter($classes, function ($class) {
    return !empty($class['is_active']);
}));

$archivedClasses = array_values(array_filter($classes, function ($class) {
    return empty($class['is_active']);
}));

$activeCount = count(array_filter($classes, function ($class) {
    return !empty($class['is_active']);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/js/admin_classes.js" defer></script>
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
        }

        .class-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card,
        .class-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }

        .stat-card {
            padding: 1.75rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2.4rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            color: #64748b;
        }

        .class-card {
            padding: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab-navigation {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
        }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            background: #f3f4f6;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #475569;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.archived {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.unassigned {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.assigned {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table td.actions {
            white-space: nowrap;
        }

        .teacher-name {
            font-weight: 600;
        }

        .teacher-muted {
            color: #64748b;
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
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
                <a href="classes.php" class="nav-item active"><i class="fas fa-chalkboard"></i><span>Classes</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="teachers_list.php" class="nav-item"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1>Class Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>

                <div class="page-title">Classes</div>

                <div class="class-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $activeCount; ?></div>
                        <div class="stat-label">Active School Year Classes</div>
                    </div>
                </div>

                <div class="class-card">
                    <div class="section-header">
                        <h2 style="margin:0;color:#1e40af;">Class List</h2>
                        <button type="button" class="btn btn-primary" onclick="openClassModal()">
                            <i class="fas fa-plus"></i> Add Class
                        </button>
                    </div>

                    <div class="section-info">
                        <i class="fas fa-user-shield"></i>
                        Classes are created by admins. Teachers only receive access after you assign them to a class.
                    </div>

                    <div class="tab-navigation">
                        <button type="button" class="tab-btn active" data-tab="active-classes">
                            <i class="fas fa-check-circle"></i> Active Classes (<?php echo count($activeClasses); ?>)
                        </button>
                        <button type="button" class="tab-btn" data-tab="archived-classes">
                            <i class="fas fa-box-archive"></i> Archived Classes (<?php echo count($archivedClasses); ?>)
                        </button>
                    </div>

                    <div class="tab-content active" id="active-classes-tab">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Class Name</th>
                                        <th>School Year</th>
                                        <th>Teacher</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activeClasses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No active classes found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activeClasses as $class): ?>
                                            <?php
                                            $teacherName = trim(($class['first_name'] ?? '') . ' ' . ($class['last_name'] ?? ''));
                                            $teacherLabel = $teacherName !== '' ? $teacherName : 'Unassigned';
                                            ?>
                                            <tr
                                                data-id="<?php echo (int) $class['class_id']; ?>"
                                                data-class-name="<?php echo htmlspecialchars($class['class_name'], ENT_QUOTES); ?>"
                                                data-sy-id="<?php echo (int) $class['sy_id']; ?>"
                                                data-sy-label="<?php echo htmlspecialchars($class['school_year'], ENT_QUOTES); ?>"
                                                data-teacher-id="<?php echo $class['teacher_id'] !== null ? (int) $class['teacher_id'] : ''; ?>"
                                            >
                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['school_year']); ?></td>
                                                <td>
                                                    <?php if ($teacherName !== ''): ?>
                                                        <span class="teacher-name"><?php echo htmlspecialchars($teacherLabel); ?></span>
                                                    <?php else: ?>
                                                        <span class="teacher-muted">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo !empty($class['teacher_id']) ? 'assigned' : 'unassigned'; ?>">
                                                        <i class="fas <?php echo !empty($class['teacher_id']) ? 'fa-user-check' : 'fa-user-clock'; ?>"></i>
                                                        <?php echo !empty($class['teacher_id']) ? 'Assigned' : 'Unassigned'; ?>
                                                    </span>
                                                    <span class="status-badge active" style="margin-left:6px;">
                                                        <i class="fas fa-check-circle"></i>
                                                        Active SY
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <button type="button" class="btn btn-sm btn-info" onclick="editClass(this)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteClass(<?php echo (int) $class['class_id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-content" id="archived-classes-tab">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Class Name</th>
                                        <th>School Year</th>
                                        <th>Teacher</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($archivedClasses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No archived classes found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($archivedClasses as $class): ?>
                                            <?php
                                            $teacherName = trim(($class['first_name'] ?? '') . ' ' . ($class['last_name'] ?? ''));
                                            $teacherLabel = $teacherName !== '' ? $teacherName : 'Unassigned';
                                            ?>
                                            <tr
                                                data-id="<?php echo (int) $class['class_id']; ?>"
                                                data-class-name="<?php echo htmlspecialchars($class['class_name'], ENT_QUOTES); ?>"
                                                data-sy-id="<?php echo (int) $class['sy_id']; ?>"
                                                data-sy-label="<?php echo htmlspecialchars($class['school_year'], ENT_QUOTES); ?>"
                                                data-teacher-id="<?php echo $class['teacher_id'] !== null ? (int) $class['teacher_id'] : ''; ?>"
                                            >
                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['school_year']); ?></td>
                                                <td>
                                                    <?php if ($teacherName !== ''): ?>
                                                        <span class="teacher-name"><?php echo htmlspecialchars($teacherLabel); ?></span>
                                                    <?php else: ?>
                                                        <span class="teacher-muted">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo !empty($class['teacher_id']) ? 'assigned' : 'unassigned'; ?>">
                                                        <i class="fas <?php echo !empty($class['teacher_id']) ? 'fa-user-check' : 'fa-user-clock'; ?>"></i>
                                                        <?php echo !empty($class['teacher_id']) ? 'Assigned' : 'Unassigned'; ?>
                                                    </span>
                                                    <span class="status-badge archived" style="margin-left:6px;">
                                                        <i class="fas fa-box-archive"></i>
                                                        Archived SY
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <button type="button" class="btn btn-sm btn-info" onclick="editClass(this)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteClass(<?php echo (int) $class['class_id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="classModal">
        <div class="modal-content" style="margin:0 auto;max-width:640px;">
            <div class="modal-header">
                <h3 id="classModalTitle"><i class="fas fa-chalkboard"></i> Add Class</h3>
                <button class="close-btn" type="button" onclick="closeClassModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="classForm">
                    <input type="hidden" name="class_id" id="classId">
                    <div class="form-group">
                        <label for="className">Class Name *</label>
                        <input type="text" id="className" name="class_name" required placeholder="e.g. Grade 10 - Section A">
                    </div>
                    <div class="form-group">
                        <label for="syId">School Year *</label>
                        <input type="hidden" id="syId" name="sy_id" value="<?php echo $activeSchoolYear ? (int) $activeSchoolYear['sy_id'] : ''; ?>">
                        <input type="text" id="syLabelDisplay" value="<?php echo $activeSchoolYear ? htmlspecialchars($activeSchoolYear['label']) : 'No active school year available'; ?>" readonly style="background:#f8fafc;color:#475569;cursor:not-allowed;">
                        <small style="display:block;margin-top:6px;color:#64748b;">New classes are created under the current active school year.</small>
                    </div>
                    <div class="form-group">
                        <label for="teacherId">Assigned Teacher</label>
                        <select id="teacherId" name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo (int) $teacher['teacher_id']; ?>"><?php echo htmlspecialchars(trim($teacher['last_name'] . ', ' . $teacher['first_name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeClassModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="classSubmitButton">Save Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="toast" id="toast">
        <span class="toast-message"></span>
    </div>
</body>
</html>