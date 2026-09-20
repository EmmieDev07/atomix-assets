<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();

$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_quizzes');
$teacher_id = $_SESSION['teacher_id'];

function formatQuizTypeLabel($quizType) {
    $labels = [
        'lesson' => 'By Lesson',
        'chapter_graded' => 'By Chapter',
        'summative' => 'Summative',
        'mcq' => 'MCQ',
        'true_false' => 'True/False',
        'short_answer' => 'Short Answer'
    ];

    $quizType = (string) $quizType;
    return $labels[$quizType] ?? ucwords(str_replace('_', ' ', $quizType));
}

function renderFormattedDateTime($dateTimeStr) {
    if (!$dateTimeStr || $dateTimeStr === 'N/A') {
        return '<span class="text-muted">N/A</span>';
    }
    try {
        $dt = new DateTime($dateTimeStr);
        return '
        <div class="datetime-card">
            <div class="date-line"><i class="far fa-calendar-alt"></i> ' . $dt->format('M j, Y') . '</div>
            <div class="time-line"><i class="far fa-clock"></i> ' . $dt->format('g:i A') . '</div>
        </div>';
    } catch (Exception $e) {
        return htmlspecialchars($dateTimeStr);
    }
}

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

$dbError = null;
try {
    $totalStmt = $db->prepare("SELECT COUNT(*) as total FROM quizzes q LEFT JOIN game_access_codes gac ON q.quiz_id = gac.quiz_id AND gac.teacher_id = ? JOIN classes c ON q.class_id = c.class_id JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1 WHERE q.teacher_id = ? AND ( (gac.is_active = 1) AND (q.start_time <= NOW() AND q.end_time >= NOW()) )");
    $totalStmt->execute([$teacher_id, $teacher_id]);
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $now = new DateTime();
    $quizzesStmt = $db->prepare(
        "SELECT q.quiz_id, q.quiz_title, q.quiz_type, q.start_time, q.end_time, q.time_limit,
                gac.access_code, gac.is_active, gac.created_at,
                c.class_name,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS question_count,
                ( (gac.is_active = 1) AND (q.start_time <= NOW() AND q.end_time >= NOW()) ) AS db_is_active
         FROM quizzes q
         LEFT JOIN game_access_codes gac ON q.quiz_id = gac.quiz_id AND gac.teacher_id = ?
         JOIN classes c ON q.class_id = c.class_id
         JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
         WHERE q.teacher_id = ?
         ORDER BY q.quiz_id DESC"
    );
    $quizzesStmt->execute([$teacher_id, $teacher_id]);
    $quizzes = $quizzesStmt->fetchAll(PDO::FETCH_ASSOC);

    $activeQuizzes = array_filter($quizzes, function($quiz) {
        return (int)$quiz['db_is_active'] === 1;
    });
    $upcomingQuizzes = array_filter($quizzes, function($quiz) use ($now) {
        $startTime = $quiz['start_time'] ? new DateTime($quiz['start_time']) : null;
        return $startTime && $now < $startTime;
    });
    $historyQuizzes = array_filter($quizzes, function($quiz) use ($now) {
        $endTime = $quiz['end_time'] ? new DateTime($quiz['end_time']) : null;
        return $endTime && $now > $endTime;
    });
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('active_quizzes.php DB error: ' . $e->getMessage());
    $quizzes = []; $activeQuizzes = []; $upcomingQuizzes = []; $historyQuizzes = [];
}

// Active Pagination
$totalActive = count($activeQuizzes);
$totalPages = max(1, ceil($totalActive / $limit));
$paginatedActive = array_slice($activeQuizzes, $offset, $limit);

// Upcoming Pagination
$upcomingPage = isset($_GET['upcoming_page']) && is_numeric($_GET['upcoming_page']) ? (int)$_GET['upcoming_page'] : 1;
$upcomingPage = max(1, $upcomingPage);
$totalUpcoming = count($upcomingQuizzes);
$totalUpcomingPages = max(1, ceil($totalUpcoming / $limit));
$paginatedUpcoming = array_slice($upcomingQuizzes, ($upcomingPage - 1) * $limit, $limit);

// History Pagination
$historyPage = isset($_GET['history_page']) && is_numeric($_GET['history_page']) ? (int)$_GET['history_page'] : 1;
$historyPage = max(1, $historyPage);
$totalHistory = count($historyQuizzes);
$totalHistoryPages = max(1, ceil($totalHistory / $limit));
$paginatedHistory = array_slice($historyQuizzes, ($historyPage - 1) * $limit, $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - Atomix</title>
    <!-- Your existing external layout styles -->
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Isolated styles exclusively for the Quiz Section -->
    <style>
        .quiz-section-wrapper {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .tab-nav {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .tab-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            border-radius: 6px 6px 0 0;
            transition: all 0.2s ease;
        }

        .tab-button:hover {
            color: #4f46e5;
            background: #f8fafc;
        }

        .tab-button.active {
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
            margin-bottom: -2px;
            background: #ffffff;
        }

        .tab-content {
            display: none;
            width: 100%;
        }

        .tab-content.active {
            display: block;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
        }

        .quiz-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .quiz-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .quiz-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #1e293b;
        }

        .quiz-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .badge-type {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active { background: #dcfce7; color: #15803d; }
        .status-inactive { background: #f1f5f9; color: #64748b; }

        .datetime-card {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            font-size: 0.8rem;
        }

        .date-line { font-weight: 600; color: #1e293b; }
        .time-line { color: #64748b; }

        .access-code-box {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 0.25rem 0.4rem;
            border-radius: 6px;
            font-family: monospace;
            font-weight: 600;
            color: #1d4ed8;
        }

        .copy-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 0.15rem 0.35rem;
            cursor: pointer;
            font-size: 0.7rem;
            color: #475569;
        }

        .copy-btn:hover { background: #4f46e5; color: white; border-color: #4f46e5; }

        .pagination {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.3rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 0.4rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #64748b;
            background: #ffffff;
            font-size: 0.8rem;
        }

        .page-link.active {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1>Quizzes</h1>
                <a href="profile.php" class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <?php if (!empty($dbError)): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:15px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
                </div>
            <?php endif; ?>

            <!-- QUIZ SECTION (Responsive Container) -->
            <div class="quiz-section-wrapper">
                <div class="tab-nav">
                    <button class="tab-button active" onclick="showTab('active')">
                        <i class="fas fa-play-circle"></i> Active
                    </button>
                    <button class="tab-button" onclick="showTab('upcoming')">
                        <i class="fas fa-clock"></i> Upcoming
                    </button>
                    <button class="tab-button" onclick="showTab('history')">
                        <i class="fas fa-history"></i> History
                    </button>
                </div>

                <!-- Active Tab -->
                <div id="active-tab" class="tab-content active">
                    <div class="table-responsive">
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>Quiz Title</th>
                                    <th>Type</th>
                                    <th>Class</th>
                                    <th>Questions</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Limit</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginatedActive)): ?>
                                    <tr><td colspan="9" style="text-align:center; color:#64748b;">No active quizzes found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($paginatedActive as $quiz): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($quiz['quiz_title'] ?? 'N/A'); ?></strong></td>
                                            <td><span class="badge-type"><?php echo htmlspecialchars(formatQuizTypeLabel($quiz['quiz_type'] ?? '')); ?></span></td>
                                            <td><?php echo htmlspecialchars($quiz['class_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (int)($quiz['question_count'] ?? 0); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['start_time'] ?? null); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['end_time'] ?? null); ?></td>
                                            <td><?php echo isset($quiz['time_limit']) ? (int)$quiz['time_limit'] . 'm' : 'N/A'; ?></td>
                                            <td>
                                                <div class="access-code-box">
                                                    <span><?php echo htmlspecialchars($quiz['access_code'] ?? 'N/A'); ?></span>
                                                    <?php if (!empty($quiz['access_code'])): ?>
                                                        <button class="copy-btn" onclick="copyAccessCode('<?php echo htmlspecialchars($quiz['access_code']); ?>', this)">Copy</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-status status-active"><i class="fas fa-check-circle"></i> Active</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Tab -->
                <div id="upcoming-tab" class="tab-content">
                    <div class="table-responsive">
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>Quiz Title</th>
                                    <th>Type</th>
                                    <th>Class</th>
                                    <th>Questions</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Limit</th>
                                    <th>Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginatedUpcoming)): ?>
                                    <tr><td colspan="8" style="text-align:center; color:#64748b;">No upcoming quizzes found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($paginatedUpcoming as $quiz): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($quiz['quiz_title'] ?? 'N/A'); ?></strong></td>
                                            <td><span class="badge-type"><?php echo htmlspecialchars(formatQuizTypeLabel($quiz['quiz_type'] ?? '')); ?></span></td>
                                            <td><?php echo htmlspecialchars($quiz['class_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (int)($quiz['question_count'] ?? 0); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['start_time'] ?? null); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['end_time'] ?? null); ?></td>
                                            <td><?php echo isset($quiz['time_limit']) ? (int)$quiz['time_limit'] . 'm' : 'N/A'; ?></td>
                                            <td>
                                                <div class="access-code-box">
                                                    <span><?php echo htmlspecialchars($quiz['access_code'] ?? 'N/A'); ?></span>
                                                    <?php if (!empty($quiz['access_code'])): ?>
                                                        <button class="copy-btn" onclick="copyAccessCode('<?php echo htmlspecialchars($quiz['access_code']); ?>', this)">Copy</button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- History Tab -->
                <div id="history-tab" class="tab-content">
                    <div class="table-responsive">
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>Quiz Title</th>
                                    <th>Type</th>
                                    <th>Class</th>
                                    <th>Questions</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginatedHistory)): ?>
                                    <tr><td colspan="8" style="text-align:center; color:#64748b;">No past quizzes found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($paginatedHistory as $quiz): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($quiz['quiz_title'] ?? 'N/A'); ?></strong></td>
                                            <td><span class="badge-type"><?php echo htmlspecialchars(formatQuizTypeLabel($quiz['quiz_type'] ?? '')); ?></span></td>
                                            <td><?php echo htmlspecialchars($quiz['class_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (int)($quiz['question_count'] ?? 0); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['start_time'] ?? null); ?></td>
                                            <td><?php echo renderFormattedDateTime($quiz['end_time'] ?? null); ?></td>
                                            <td>
                                                <div class="access-code-box">
                                                    <span><?php echo htmlspecialchars($quiz['access_code'] ?? 'N/A'); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge-status status-inactive">Expired</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function copyAccessCode(code, button) {
            navigator.clipboard.writeText(code).then(() => {
                button.textContent = 'Copied!';
                setTimeout(() => button.textContent = 'Copy', 2000);
            });
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>