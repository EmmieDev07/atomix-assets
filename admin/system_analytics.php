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

    try {
    // Get active school year
    $activeSchoolYear = $db->query("SELECT sy_id, label FROM school_year WHERE is_active = 1 LIMIT 1")->fetch();
    $active_sy_id = $activeSchoolYear['sy_id'] ?? null;

    // System-wide analytics for active school year only
    $totalQuizzes = $db->prepare("SELECT COUNT(DISTINCT q.quiz_id) FROM quizzes q JOIN classes c ON q.class_id = c.class_id WHERE c.sy_id = ?");
    $totalQuizzes->execute([$active_sy_id]);
    $totalQuizzes = $totalQuizzes->fetchColumn();

    // Add student gender totals for active school year
    $studentGenderTotals = $db->prepare('
        SELECT 
            SUM(CASE WHEN LOWER(s.gender) = "male" THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN LOWER(s.gender) = "female" THEN 1 ELSE 0 END) AS female,
            SUM(CASE WHEN LOWER(s.gender) = "others" THEN 1 ELSE 0 END) AS others,
            COUNT(*) AS total
        FROM students s
        JOIN class_students cs ON s.student_id = cs.student_id
        JOIN classes c ON cs.class_id = c.class_id
        WHERE c.sy_id = ?
    ');
    $studentGenderTotals->execute([$active_sy_id]);
    $studentGenderTotals = $studentGenderTotals->fetch();

    // Total teachers count
    $totalTeachers = $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

    $totalAttempts = $db->prepare("SELECT COUNT(*) FROM student_quizzes sq JOIN quizzes q ON sq.quiz_id = q.quiz_id JOIN classes c ON q.class_id = c.class_id WHERE c.sy_id = ?");
    $totalAttempts->execute([$active_sy_id]);
    $totalAttempts = $totalAttempts->fetchColumn();



    // Use SUM(score)/SUM(total_score)*100 for true percent-based average
    $avgScore = $db->prepare("SELECT (SUM(sq.score) / SUM(q.total_score)) * 100 FROM student_quizzes sq JOIN quizzes q ON sq.quiz_id = q.quiz_id JOIN classes c ON q.class_id = c.class_id WHERE c.sy_id = ? AND q.total_score > 0");
    $avgScore->execute([$active_sy_id]);
    $avgScore = $avgScore ? round($avgScore->fetchColumn(), 2) : 0;

    $completedQuizzes = $db->prepare("SELECT COUNT(*) FROM student_quizzes sq JOIN quizzes q ON sq.quiz_id = q.quiz_id JOIN classes c ON q.class_id = c.class_id WHERE sq.status = 'completed' AND c.sy_id = ?");
    $completedQuizzes->execute([$active_sy_id]);
    $completedQuizzes = $completedQuizzes->fetchColumn();
    $completionRate = $totalAttempts > 0 ? round(($completedQuizzes / $totalAttempts) * 100, 2) : 0;


    // Score distribution based on percent
    $scoreDist = $db->prepare("
        SELECT
            SUM(CASE WHEN (sq.score / q.total_score) * 100 >= 90 THEN 1 ELSE 0 END) as excellent,
            SUM(CASE WHEN (sq.score / q.total_score) * 100 >= 75 AND (sq.score / q.total_score) * 100 < 90 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN (sq.score / q.total_score) * 100 >= 60 AND (sq.score / q.total_score) * 100 < 75 THEN 1 ELSE 0 END) as fair,
            SUM(CASE WHEN (sq.score / q.total_score) * 100 < 60 THEN 1 ELSE 0 END) as needs_improvement
        FROM student_quizzes sq
        JOIN quizzes q ON sq.quiz_id = q.quiz_id
        JOIN classes c ON q.class_id = c.class_id
        WHERE c.sy_id = ? AND q.total_score > 0
    ");
    $scoreDist->execute([$active_sy_id]);
    $scoreDist = $scoreDist->fetch();



    // Class performance: percent-based average using SUM(score)/SUM(total_score)*100
    $classPerf = $db->prepare("
        SELECT c.class_name,
            COUNT(DISTINCT s.student_id) as students,
            COUNT(sq.student_quiz_id) as total_attempts,
            (SUM(sq.score) / SUM(q.total_score)) * 100 as avg_score,
            SUM(CASE WHEN (q.total_score > 0 AND (sq.score / q.total_score) * 100 >= 75) THEN 1 ELSE 0 END) as passed
        FROM classes c
        JOIN class_students cs ON c.class_id = cs.class_id
        JOIN students s ON cs.student_id = s.student_id
        JOIN student_quizzes sq ON s.student_id = sq.student_id
        JOIN quizzes q ON sq.quiz_id = q.quiz_id
        WHERE c.sy_id = ? AND q.total_score > 0
        GROUP BY c.class_id, c.class_name
        ORDER BY avg_score DESC
    ");
    $classPerf->execute([$active_sy_id]);
    $classPerf = $classPerf->fetchAll();



    // Quiz performance: percent-based using SUM(score)/SUM(total_score)*100
    $quizPerf = $db->prepare("
        SELECT q.quiz_id, q.quiz_title,
            COUNT(sq.student_quiz_id) as attempts,
            (CASE WHEN SUM(q.total_score) > 0 THEN (SUM(sq.score) / SUM(q.total_score)) * 100 ELSE NULL END) as avg_score
        FROM quizzes q
        JOIN classes c ON q.class_id = c.class_id
        LEFT JOIN student_quizzes sq ON q.quiz_id = sq.quiz_id
        WHERE c.sy_id = ?
        GROUP BY q.quiz_id, q.quiz_title
        ORDER BY q.quiz_id ASC
        LIMIT 10
    ");
    $quizPerf->execute([$active_sy_id]);
    $quizPerf = $quizPerf->fetchAll();
    } catch (Exception $e) {
        error_log('System analytics error: ' . $e->getMessage());
        $dbError = 'Failed to load analytics data. Please try again.';
        $totalQuizzes = $totalAttempts = $completedQuizzes = $completionRate = $avgScore = $totalTeachers = 0;
        $studentGenderTotals = $scoreDist = $activeSchoolYear = null;
        $classPerf = $quizPerf = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Analytics - Atomix</title>
        <link rel="stylesheet" href="../assets/css/teacher_style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <style>
            body {
                background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 50%, #81d4fa 100%);
                min-height: 100vh;
                margin: 0;
                font-family: 'Inter', 'Segoe UI', 'Roboto', Arial, sans-serif;
                color: #1e293b;
            }

            .dashboard-container {
                min-height: 100vh;
                display: flex;
                flex-direction: row;
            }

            /* Main Content */
            .main-content {
                flex: 1;
                margin-left: 250px;
                padding: 2rem 2.5rem;
                background: transparent;
            }

            .top-header {
                margin-bottom: 2rem;
            }

            .top-header h1 {
                font-size: 2.2rem;
                font-weight: 700;
                color: #1e40af;
                letter-spacing: -0.5px;
                margin-bottom: 0.5rem;
            }

            .charts-section-title {
                font-size: 1rem;
                color: #64748b;
                font-weight: 500;
                margin-bottom: 1.5rem;
            }

            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
                gap: 1.25rem;
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
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.5rem;
                flex-shrink: 0;
            }

            .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
            .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
            .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
            .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #ec4899, #db2777); }
            .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #6b7280, #4b5563); }

            .stat-content {
                flex: 1;
            }

            .stat-number {
                font-size: 2rem;
                font-weight: 700;
                color: #1e40af;
                line-height: 1.2;
                margin-bottom: 0.25rem;
            }

            .stat-label {
                color: #64748b;
                font-size: 0.9rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Charts Row */
            .charts-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            /* Dashboard Cards */
            .dashboard-card {
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border-radius: 18px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.08);
                padding: 2rem;
                border: 2px solid #e2e8f0;
                margin-bottom: 1.5rem;
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

            .chart-card {
                min-height: auto;
            }

            .chart-canvas-wrap {
                padding: 1.5rem;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                max-width: 320px;
                margin: 0 auto;
            }

            /* Data Table */
            .data-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                margin-top: 0.5rem;
                background: #fff;
                border-radius: 12px;
                overflow: hidden;
            }

            .data-table th,
            .data-table td {
                padding: 1rem 1.25rem;
                text-align: left;
            }

            .data-table th {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                color: #1e40af;
                font-weight: 600;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 2px solid #e2e8f0;
            }

            .data-table td {
                color: #1e293b;
                font-size: 0.95rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .data-table tbody tr {
                transition: all 0.2s ease;
            }

            .data-table tbody tr:hover {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            }

            .data-table tbody tr:last-child td {
                border-bottom: none;
            }

            /* Responsive */
            @media (max-width: 1024px) {
                .charts-row {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 768px) {
                .main-content {
                    margin-left: 0;
                    padding: 1.5rem;
                }

                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .charts-row {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .main-content {
                    padding: 1rem;
                }

                .top-header h1 {
                    font-size: 1.75rem;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                }

                .stat-card {
                    padding: 1.25rem;
                }

                .stat-number {
                    font-size: 1.5rem;
                }

                .dashboard-card {
                    padding: 1.25rem;
                    border-radius: 14px;
                }
            }

            /* Animations */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .stat-card {
                animation: fadeInUp 0.5s ease-out forwards;
            }

            .stat-card:nth-child(1) { animation-delay: 0.05s; }
            .stat-card:nth-child(2) { animation-delay: 0.1s; }
            .stat-card:nth-child(3) { animation-delay: 0.15s; }
            .stat-card:nth-child(4) { animation-delay: 0.2s; }
            .stat-card:nth-child(5) { animation-delay: 0.25s; }

            .dashboard-card {
                animation: fadeInUp 0.6s ease-out forwards;
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
                    <a href="system_analytics.php" class="nav-item active"><i class="fas fa-chart-bar"></i><span>Analytics</span></a>
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
                    <h1>System Analytics</h1>
                </header>

                <nav aria-label="breadcrumb">
                    <div class="breadcrumb">
                        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <span class="breadcrumb-sep">&#9656;</span>
                        <span class="breadcrumb-current">Analytics</span>
                    </div>
                </nav>

                <div class="content-area">
                    <?php if (!empty($dbError)): ?>
                        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                        </div>
                    <?php endif; ?>
                                    <div class="charts-section-title">Student Analytics Charts</div>
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-content"><div class="stat-number"><?php echo number_format($totalTeachers); ?></div><div class="stat-label">Total Teachers</div></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-number"><?php echo (int)($studentGenderTotals['total'] ?? 0); ?></div><div class="stat-label">Total Students</div></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-mars"></i></div><div class="stat-content"><div class="stat-number"><?php echo (int)($studentGenderTotals['male'] ?? 0); ?></div><div class="stat-label">Male Students</div></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-venus"></i></div><div class="stat-content"><div class="stat-number"><?php echo (int)($studentGenderTotals['female'] ?? 0); ?></div><div class="stat-label">Female Students</div></div></div>
                        <div class="stat-card"><div class="stat-icon"><i class="fas fa-genderless"></i></div><div class="stat-content"><div class="stat-number"><?php echo (int)($studentGenderTotals['others'] ?? 0); ?></div><div class="stat-label">Other Gender Students</div></div></div>
                    </div>

                    <!-- Two-column chart layout -->
                    <div class="charts-row">
                        <div class="dashboard-card chart-card">
                            <h2><i class="fas fa-chart-pie"></i> Score Distribution</h2>
                            <div class="chart-canvas-wrap">
                                <canvas id="scoreDistChart" height="220"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card chart-card">
                            <h2><i class="fas fa-venus-mars"></i> Student Gender Distribution</h2>
                            <div class="chart-canvas-wrap">
                                <canvas id="studentGenderChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <h2><i class="fas fa-chart-bar"></i> Class Performance</h2>
                        <table class="data-table">
                            <thead><tr><th>Class</th><th>Students</th><th>Total Attempts</th><th>Average Score</th><th>Passes (&ge;75%)</th></tr></thead>
                            <tbody>
                            <?php foreach ($classPerf as $class): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                    <td><?php echo $class['students']; ?></td>
                                    <td><?php echo $class['total_attempts']; ?></td>
                                    <td><?php echo round($class['avg_score'], 1); ?>%</td>
                                    <td><?php echo $class['passed']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Chart.js global defaults for light theme
                Chart.defaults.color = '#64748b';
                Chart.defaults.borderColor = '#e2e8f0';
                
                // Score Distribution Chart
                var scoreDistCanvas = document.getElementById('scoreDistChart');
                if (scoreDistCanvas) {
                    new Chart(scoreDistCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Excellent (≥90%)', 'Good (75-89%)', 'Fair (60-74%)', 'Needs Improvement (<60%)'],
                            datasets: [{
                                data: [
                                    <?php echo $scoreDist['excellent'] ?? 0; ?>,
                                    <?php echo $scoreDist['good'] ?? 0; ?>,
                                    <?php echo $scoreDist['fair'] ?? 0; ?>,
                                    <?php echo $scoreDist['needs_improvement'] ?? 0; ?>
                                ],
                                backgroundColor: [
                                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444'
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 3,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 12,
                                            weight: 500
                                        },
                                        color: '#1e293b'
                                    }
                                }
                            }
                        }
                    });
                }

                // Student Gender Distribution Chart
                var genderCanvas = document.getElementById('studentGenderChart');
                if (genderCanvas) {
                    new Chart(genderCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Male', 'Female', 'Others'],
                            datasets: [{
                                data: [
                                    <?php echo (int)($studentGenderTotals['male'] ?? 0); ?>,
                                    <?php echo (int)($studentGenderTotals['female'] ?? 0); ?>,
                                    <?php echo (int)($studentGenderTotals['others'] ?? 0); ?>
                                ],
                                backgroundColor: [
                                    '#3b82f6', '#ec4899', '#8b5cf6'
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 3,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 12,
                                            weight: 500
                                        },
                                        color: '#1e293b'
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>

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
