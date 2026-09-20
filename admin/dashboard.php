<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();
$dbError = null;
$stats = [];
$recentStudents = [];
$recentTeachers = [];

try {
    // Get statistics
    $stats = [
        'total_students'     => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'total_classes'      => $db->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
        'total_assessments'  => $db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(),
        'total_questions'    => $db->query("SELECT COUNT(*) FROM questions_master")->fetchColumn(),
        'total_teachers'     => $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn()
    ];

    // Get recent students (last 5)
    $recentStudents = $db->query("
        SELECT s.first_name, s.last_name, u.created_at, c.class_name
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN class_students cs ON s.student_id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.class_id
        ORDER BY u.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Get recent teachers (last 5)
    $recentTeachers = $db->query("
        SELECT t.first_name, t.last_name, u.created_at, u.email
        FROM teachers t
        JOIN users u ON t.user_id = u.user_id
        ORDER BY u.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $dbError = 'Failed to load dashboard data. Please try again.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            color: #1e293b;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
        }

        .dashboard-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .dashboard-card h2 {
            color: #1e40af;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dashboard-card h2 i {
            color: #1e40af;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem 1rem;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-decoration: none;
            color: #1e40af;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
            border-color: #1e40af;
        }

        .action-btn i {
            font-size: 1.5rem;
        }

        /* Recent Students */
        .recent-list {
            margin-bottom: 1.5rem;
        }

        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .recent-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .student-class {
            color: #64748b;
            font-size: 0.85rem;
        }

        .student-date {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .view-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .view-all-btn:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.6;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
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
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="system_analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Analytics</span></a>
                <a href="school_years.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>School Years</span></a>
                <a href="classes.php" class="nav-item"><i class="fas fa-chalkboard"></i><span>Classes</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
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
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <i class="fas fa-home" style="color:#3b82f6;"></i>
                    &nbsp;<span class="breadcrumb-current">Dashboard</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($stats['total_students']); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($stats['total_classes']); ?></div>
                            <div class="stat-label">Total Classes</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($stats['total_assessments']); ?></div>
                            <div class="stat-label">Assessments</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                            <div class="stat-label">Questions</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($stats['total_teachers']); ?></div>
                            <div class="stat-label">Teachers</div>
                        </div>
                    </div>


                </div>

                <!-- Quick Actions & Recent Students -->
                <div class="dashboard-grid">
                    <!-- Quick Actions -->
                    <div class="dashboard-card">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        <div class="quick-actions">
                            <a href="create_teacher.php" class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span>Add Teacher</span>
                            </a>
                            <a href="school_years.php" class="action-btn">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Add School Year</span>
                            </a>
                            <a href="classes.php" class="action-btn">
                                <i class="fas fa-chalkboard"></i>
                                <span>Manage Classes</span>
                            </a>
                            <a href="backup.php" class="action-btn">
                                <i class="fas fa-database"></i>
                                <span>Create Backup</span>
                            </a>
                            <a href="system.php" class="action-btn">
                                <i class="fas fa-cogs"></i>
                                <span>System Info</span>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Students -->
                    <div class="dashboard-card">
                        <h2><i class="fas fa-user-clock"></i> Recent Students</h2>
                        <?php if (empty($recentStudents)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No students added yet</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentStudents as $student): ?>
                                    <div class="recent-item">
                                        <div class="student-info">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </div>
                                            <div class="student-class">
                                                <?php echo htmlspecialchars($student['class_name'] ?? 'No Class Assigned'); ?>
                                            </div>
                                        </div>
                                        <div class="student-date">
                                            <?php
                                            $studentCreated = strtotime($student['created_at'] ?? '');
                                            echo $studentCreated ? date('M j, Y g:i A', $studentCreated) : 'N/A';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <a href="students.php" class="view-all-btn">
                            <i class="fas fa-arrow-right"></i> View All Students
                        </a>
                    </div>

                    <!-- Recent Teachers -->
                    <div class="dashboard-card">
                        <h2><i class="fas fa-chalkboard-teacher"></i> Recent Teachers</h2>
                        <?php if (empty($recentTeachers)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No teachers added yet</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentTeachers as $teacher): ?>
                                    <div class="recent-item">
                                        <div class="student-info">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                            </div>
                                            <div class="student-class">
                                                <?php echo htmlspecialchars($teacher['email']); ?>
                                            </div>
                                        </div>
                                        <div class="student-date">
                                            <?php
                                            $teacherCreated = strtotime($teacher['created_at'] ?? '');
                                            echo $teacherCreated ? date('M j, Y g:i A', $teacherCreated) : 'N/A';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <a href="teachers_list.php" class="view-all-btn">
                            <i class="fas fa-arrow-right"></i> View All Teachers
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
