<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
$teacher_id = getTeacherId();

$dbError = null;
$debugDetails = null;

try {
    $stats = [];

    // 1. Get Total Students (Enrolled across teacher's classes)
    $studentsCountStmt = $db->prepare("
        SELECT COUNT(DISTINCT cs.student_id) 
        FROM class_students cs 
        JOIN classes c ON cs.class_id = c.class_id 
        WHERE c.teacher_id = ?
    ");
    $studentsCountStmt->execute([$teacher_id]);
    $stats['students'] = $studentsCountStmt->fetchColumn();

    // 2. Get Active / Deployed Exams Count
    $activeExamsStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM exams 
        WHERE teacher_id = ? AND is_deployed = 1
    ");
    $activeExamsStmt->execute([$teacher_id]);
    $stats['active_exams'] = $activeExamsStmt->fetchColumn();

    // 3. Get Total Questions
    $questionsStmt = $db->prepare("SELECT COUNT(*) FROM questions_master WHERE created_by_teacher_id = ?");
    $questionsStmt->execute([$teacher_id]);
    $stats['questions'] = $questionsStmt->fetchColumn();

    // 4. Get Total Quizzes
    $quizzesStmt = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ?");
    $quizzesStmt->execute([$teacher_id]);
    $stats['quizzes'] = $quizzesStmt->fetchColumn();

    // 5. Fetch Recent Game Progress
    $recentGameStmt = $db->prepare("
        SELECT gp.status, gp.last_updated,
               st.first_name, st.last_name,
               c.class_name,
               s.stage_name
        FROM game_progress gp
        JOIN class_students cs ON gp.student_id = cs.student_id
        JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
        JOIN students st ON cs.student_id = st.student_id
        JOIN stages s ON gp.stage_id = s.stage_id
        ORDER BY gp.last_updated DESC
        LIMIT 5
    ");
    $recentGameStmt->execute([$teacher_id]);
    $recentGameActivity = $recentGameStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Recent Assessment Results
    $recentAssessments = [];
    try {
        $recentAssessmentsStmt = $db->prepare("
            SELECT ar.score, ar.total_questions, ar.attempted_at,
                   st.first_name, st.last_name,
                   ch.chapter_title,
                   ar.assessment_type
            FROM student_assessment_results ar
            JOIN students st ON ar.student_id = st.student_id
            JOIN class_students cs ON st.student_id = cs.student_id
            JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
            JOIN chapters ch ON ar.chapter_id = ch.chapter_id
            ORDER BY ar.attempted_at DESC
            LIMIT 5
        ");
        $recentAssessmentsStmt->execute([$teacher_id]);
        $recentAssessments = $recentAssessmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $qe) {
        error_log("dashboard.php Assessment Query Error: " . $qe->getMessage());
        $debugDetails .= "Assessment Query Error: " . $qe->getMessage() . "\n";
    }

    // 7. Fetch Monthly Average Score Growth Analytics
    $monthlyGrowth = [];
    try {
        $growthStmt = $db->prepare("
            SELECT 
                DATE_FORMAT(ar.attempted_at, '%b %Y') AS month,
                DATE_FORMAT(ar.attempted_at, '%Y-%m') AS ym,
                ROUND(AVG((ar.score / ar.total_questions) * 100), 1) AS avg_score
            FROM student_assessment_results ar
            JOIN students st ON ar.student_id = st.student_id
            JOIN class_students cs ON st.student_id = cs.student_id
            JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = ?
            WHERE ar.attempted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym, month
            ORDER BY ym ASC
        ");
        $growthStmt->execute([$teacher_id]);
        $monthlyGrowth = $growthStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $qe) {
        error_log("dashboard.php Analytics Query Error: " . $qe->getMessage());
        $debugDetails .= "Analytics Query Error: " . $qe->getMessage() . "\n";
    }

    // Prepare JSON values for Chart.js
    $chartLabels = json_encode(array_column($monthlyGrowth, 'month') ?: ['No Data']);
    $chartData = json_encode(array_column($monthlyGrowth, 'avg_score') ?: [0]);

} catch (PDOException $e) {
    $dbError = 'A database error occurred while loading dashboard statistics.';
    $debugDetails = $e->getMessage();
    error_log('dashboard.php DB error: ' . $e->getMessage());
    
    $stats = ['students' => 0, 'active_exams' => 0, 'questions' => 0, 'quizzes' => 0];
    $recentGameActivity = [];
    $recentAssessments = [];
    $chartLabels = json_encode(['No Data']);
    $chartData = json_encode([0]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--white) 0%, var(--gray-100) 100%);
            border-radius: var(--radius-lg);
            padding: 25px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card.students .icon {
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
            color: white;
        }
        
        .stat-card.active-exams .icon {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
        }
        
        .stat-card.questions .icon {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: white;
        }
        
        .stat-card.quizzes .icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stat-card .label {
            font-size: 14px;
            color: var(--gray-500);
            margin-top: 5px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 25px;
            border: 1px solid var(--gray-200);
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .action-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }
        
        .action-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background-color: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .action-card:hover .icon {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .action-card .text h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .action-card .text p {
            font-size: 13px;
            color: var(--gray-500);
        }
        
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .recent-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 25px;
            border: 1px solid var(--gray-200);
        }
        
        .recent-section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .recent-section h2 .title-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .recent-section h2 i {
            color: var(--primary-color);
        }

        .recent-section h2 .view-all {
            font-size: 13px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .recent-section h2 .view-all:hover {
            text-decoration: underline;
        }
        
        .recent-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-item .item-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .recent-item .item-sub {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 3px;
        }

        .status-pill {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-completed { background: #d1fae5; color: #065f46; }
        .status-in_progress { background: #fef3c7; color: #92400e; }
        .status-not_started { background: #f3f4f6; color: #374151; }

        .score-pill {
            font-size: 12px;
            font-weight: 700;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 8px;
        }

        .debug-box {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 13px;
        }
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
                <h1>Dashboard</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <span class="breadcrumb-current"><i class="fas fa-home"></i> Dashboard</span>
            </nav>

            <?php if (!empty($debugDetails)): ?>
            <div class="debug-box">
                <strong><i class="fas fa-bug"></i> Database Debug Info:</strong>
                <pre style="margin-top: 8px; white-space: pre-wrap;"><?php echo htmlspecialchars($debugDetails); ?></pre>
            </div>
            <?php endif; ?>

            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['pw_changed'])): ?>
            <div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle"></i> Your password has been changed successfully.
            </div>
            <?php endif; ?>

            <div class="content-wrapper">
                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-card students">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="number"><?php echo $stats['students']; ?></div>
                        <div class="label">Total Students</div>
                    </div>
                    <div class="stat-card active-exams">
                        <div class="icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="number"><?php echo $stats['active_exams']; ?></div>
                        <div class="label">Active Deployed Exams</div>
                    </div>
                    <div class="stat-card questions">
                        <div class="icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="number"><?php echo $stats['questions']; ?></div>
                        <div class="label">My Questions</div>
                    </div>
                    <div class="stat-card quizzes">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="number"><?php echo $stats['quizzes']; ?></div>
                        <div class="label">My Quizzes</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <h2 style="margin-bottom: 20px; font-size: 18px;">
                    <i class="fas fa-bolt" style="color: var(--primary-color);"></i> Quick Actions
                </h2>
                <div class="quick-actions">
                    <a href="exams.php" class="action-card">
                        <div class="icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="text">
                            <h3>Exams & Monitoring</h3>
                            <p>Build and monitor student exams</p>
                        </div>
                    </a>
                    <a href="quizzes.php" class="action-card">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="text">
                            <h3>Create Quiz</h3>
                            <p>Build a new quiz for students</p>
                        </div>
                    </a>
                    <a href="classes.php" class="action-card">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="text">
                            <h3>Manage Classes</h3>
                            <p>View and manage your classes</p>
                        </div>
                    </a>
                </div>

                <!-- Live Activity Widgets Grid -->
                <div class="widgets-grid">
                    <!-- Widget 1: Recent Game Progress -->
                    <div class="recent-section">
                        <h2>
                            <span class="title-text"><i class="fas fa-gamepad"></i> Recent Game Progress</span>
                            <a href="game_progress.php" class="view-all">View Report &rarr;</a>
                        </h2>
                        <?php if (empty($recentGameActivity)): ?>
                            <p style="text-align: center; color: var(--gray-500); padding: 20px 0;">
                                No student game progress recorded yet.
                            </p>
                        <?php else: ?>
                            <?php foreach ($recentGameActivity as $game): ?>
                            <div class="recent-item">
                                <div>
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($game['first_name'] . ' ' . $game['last_name']); ?>
                                    </div>
                                    <div class="item-sub">
                                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($game['stage_name']); ?> • 
                                        <span><?php echo htmlspecialchars($game['class_name']); ?></span>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <?php $statusClass = 'status-' . strtolower(str_replace(' ', '_', $game['status'])); ?>
                                    <span class="status-pill <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $game['status']))); ?>
                                    </span>
                                    <div class="item-sub" style="margin-top: 4px;">
                                        <?php echo htmlspecialchars(date('M j, g:i A', strtotime($game['last_updated']))); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Widget 2: Recent Assessment Scores -->
                    <div class="recent-section">
                        <h2>
                            <span class="title-text"><i class="fas fa-poll-h"></i> Recent Assessment Scores</span>
                            <a href="game_progress.php" class="view-all">View All &rarr;</a>
                        </h2>
                        <?php if (empty($recentAssessments)): ?>
                            <p style="text-align: center; color: var(--gray-500); padding: 20px 0;">
                                No recent assessment results recorded.
                            </p>
                        <?php else: ?>
                            <?php foreach ($recentAssessments as $res): ?>
                            <div class="recent-item">
                                <div>
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>
                                    </div>
                                    <div class="item-sub">
                                        <i class="fas fa-file-signature"></i> <?php echo htmlspecialchars($res['chapter_title']); ?> 
                                        <span style="text-transform: capitalize;">(<?php echo htmlspecialchars($res['assessment_type']); ?>)</span>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="score-pill">
                                        <?php echo (int)$res['score']; ?> / <?php echo (int)$res['total_questions']; ?>
                                    </span>
                                    <div class="item-sub" style="margin-top: 4px;">
                                        <?php echo htmlspecialchars(date('M j, g:i A', strtotime($res['attempted_at']))); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Growth & Learning Analytics Panel -->
                <div class="recent-section" style="margin-top: 25px;">
                    <h2>
                        <span class="title-text"><i class="fas fa-chart-line"></i> Student Learning & Growth Analytics</span>
                        <span style="font-size: 12px; color: var(--gray-500); font-weight: normal;">Overall Average Score Trend (%)</span>
                    </h2>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Analytics Chart Initialization Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('growthChart').getContext('2d');
        
        const labels = <?php echo $chartLabels; ?>;
        const scores = <?php echo $chartData; ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Class Average Score (%)',
                    data: scores,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; }
                        },
                        grid: { color: 'rgba(229, 231, 235, 0.6)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return ' Avg Score: ' + context.raw + '%'; }
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>