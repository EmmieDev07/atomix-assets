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

// Get classes for filter dropdown
$dbError = null;
try {
    $classesStmt = $db->prepare(
        "SELECT c.class_id, c.class_name, sy.label as school_year 
         FROM classes c 
         JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1 
         WHERE c.teacher_id = ? 
         ORDER BY c.class_name"
    );
    $classesStmt->execute([$teacher_id]);
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    $quizzesStmt = $db->prepare(
        "SELECT q.quiz_id, q.quiz_title, q.quiz_type,
                (SELECT c.chapter_title FROM quiz_questions qq JOIN questions_master qm ON qq.question_id = qm.question_id JOIN lessons l ON qm.lesson_id = l.lesson_id JOIN chapters c ON l.chapter_id = c.chapter_id WHERE qq.quiz_id = q.quiz_id LIMIT 1) AS chapter_title,
                gac.access_code
         FROM quizzes q
         LEFT JOIN game_access_codes gac ON q.quiz_id = gac.quiz_id AND gac.teacher_id = q.teacher_id
         JOIN classes c ON q.class_id = c.class_id
         JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
         WHERE q.teacher_id = ?
         ORDER BY q.quiz_id DESC"
    );
    $quizzesStmt->execute([$teacher_id]);
    $quizzes = $quizzesStmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;
    $selected_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
    $view_tab = isset($_GET['tab']) ? $_GET['tab'] : 'live';

    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $students = [];

    if ($isAjax && $selected_quiz_id > 0) {
        // Ensure the current teacher owns this quiz
        $ownerCheck = $db->prepare("SELECT teacher_id FROM quizzes WHERE quiz_id = ? LIMIT 1");
        $ownerCheck->execute([$selected_quiz_id]);
        $owner = $ownerCheck->fetch();
        
        if (!$owner || (int)$owner['teacher_id'] !== (int)$teacher_id) {
            $students = [];
        } else {
            // Check if quiz expiration or end_time columns exist on table
            $expColumn = "NULL";
            try {
                $colCheck = $db->query("SHOW COLUMNS FROM quizzes LIKE 'quiz_expiration'");
                if ($colCheck && $colCheck->rowCount() > 0) {
                    $expColumn = "q.quiz_expiration";
                } else {
                    $colCheckEnd = $db->query("SHOW COLUMNS FROM quizzes LIKE 'end_time'");
                    if ($colCheckEnd && $colCheckEnd->rowCount() > 0) {
                        $expColumn = "q.end_time";
                    }
                }
            } catch (Exception $e) {
                $expColumn = "NULL";
            }

            // Build query with fallback for total question count
            $query = "
                SELECT sq.student_quiz_id, sq.status, sq.score, sq.started_at, sq.submitted_at,
                       st.student_id, st.first_name, st.last_name,
                       c.class_name, sy.label as school_year,
                       {$expColumn} AS quiz_expire_time,
                       (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as total_questions
                FROM student_quizzes sq
                JOIN students st ON sq.student_id = st.student_id
                JOIN quizzes q ON sq.quiz_id = q.quiz_id
                LEFT JOIN class_students cs ON st.student_id = cs.student_id
                LEFT JOIN classes c ON cs.class_id = c.class_id AND c.teacher_id = q.teacher_id
                LEFT JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
                WHERE q.teacher_id = ? AND sq.quiz_id = ?";
            
            $params = [$teacher_id, $selected_quiz_id];
            
            if ($view_tab === 'history') {
                // Quiz History: Shows all completed or submitted attempts
                $query .= " AND (sq.status IN ('completed', 'submitted', 'finished', 'done') OR sq.submitted_at IS NOT NULL OR sq.score IS NOT NULL)";
            } else {
                // Live Tracker: Shows ALL attempts for this quiz during live window
                // If expire column exists and has passed, completed attempts filter to history
                if ($expColumn !== "NULL") {
                    $query .= " AND ({$expColumn} IS NULL OR {$expColumn} >= NOW() OR sq.status IN ('in_progress', 'ongoing', 'pending'))";
                }
            }

            if ($selected_class_id > 0) {
                $query .= " AND cs.class_id = ?";
                $params[] = $selected_class_id;
            }
            
            $query .= " ORDER BY sq.started_at DESC, st.last_name, st.first_name";
            
            $studentsStmt = $db->prepare($query);
            $studentsStmt->execute($params);
            $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('quiz_takers.php DB error: ' . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'A database error occurred: ' . $e->getMessage()]);
        exit;
    }
    $dbError = 'A database error occurred. Please try again later.';
    $classes = []; $quizzes = []; $students = [];
    $selected_quiz_id = 0; $selected_class_id = 0;
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Takers & Tracker - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tracker-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .tab-btn {
            background: none;
            border: none;
            font-size: 15px;
            font-weight: 600;
            color: #6b7280;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .tab-btn:hover { background: #f3f4f6; color: #111827; }
        .tab-btn.active { background: #4f46e5; color: #fff; }
        
        .live-dot {
            height: 10px;
            width: 10px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }
        .badge-in_progress, .badge-in-progress, .badge-ongoing { background: #e0f2fe; color: #0369a1; }
        .badge-completed, .badge-submitted, .badge-finished, .badge-done { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        
        /* Score Badge Styling */
        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f3f4f6;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            color: #1f2937;
            border: 1px solid #e5e7eb;
        }
        .score-pill .score-num {
            color: #4f46e5;
        }
        .score-pill .score-pct {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            background: #fff;
            padding: 2px 6px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .tracker-status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .last-update-text {
            font-size: 13px;
            color: #6b7280;
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
                <h1>Quiz Monitor & History</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <a href="quizzes.php">Quizzes</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Quiz Takers</span>
            </nav>

            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?>
            </div>
            <?php endif; ?>

            <div class="content-area">
                <!-- Tab Navigation -->
                <div class="tracker-tabs">
                    <button class="tab-btn active" id="tabLive" onclick="switchTab('live')">
                        <span class="live-dot"></span> Live Tracker
                    </button>
                    <button class="tab-btn" id="tabHistory" onclick="switchTab('history')">
                        <i class="fas fa-history"></i> Quiz History
                    </button>
                </div>

                <div class="tracker-status-bar">
                    <h2 id="sectionTitle">Live Student Attempts</h2>
                    <span id="lastRefreshed" class="last-update-text"><i class="fas fa-sync fa-spin"></i> Initializing tracker...</span>
                </div>

                <div class="filter-bar" style="margin-bottom: 20px;">
                    <div class="filter-group">
                        <label for="quizSelect">Select Quiz:</label>
                        <select id="quizSelect">
                            <?php if (!empty($quizzes)): ?>
                                <?php foreach ($quizzes as $index => $quiz): ?>
                                    <?php
                                        $label = !empty($quiz['quiz_title']) ? htmlspecialchars($quiz['quiz_title']) : 'Quiz #' . $quiz['quiz_id'];
                                        if (!empty($quiz['chapter_title'])) $label .= ' - ' . $quiz['chapter_title'];
                                        if (!empty($quiz['access_code'])) $label .= ' (' . $quiz['access_code'] . ')';
                                        $isSelected = ($index === 0) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo (int) $quiz['quiz_id']; ?>" <?php echo $isSelected; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No quizzes available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="classSelect">Filter by Class:</label>
                        <select id="classSelect">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo (int) $class['class_id']; ?>" <?php echo $selected_class_id === (int) $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['school_year'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Started</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody id="quizTakersBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <?php echo !empty($quizzes) ? '<i class="fas fa-spinner fa-spin"></i> Loading live data...' : 'No quizzes available.'; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const quizSelect = document.getElementById('quizSelect');
        const classSelect = document.getElementById('classSelect');
        const tableBody = document.getElementById('quizTakersBody');
        const lastRefreshed = document.getElementById('lastRefreshed');
        const sectionTitle = document.getElementById('sectionTitle');
        
        let activeTab = 'live';
        let pollTimer = null;
        let isFirstLoad = true;

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        window.switchTab = function(tabName) {
            if (activeTab === tabName) return;
            activeTab = tabName;

            document.getElementById('tabLive').classList.toggle('active', activeTab === 'live');
            document.getElementById('tabHistory').classList.toggle('active', activeTab === 'history');

            sectionTitle.textContent = activeTab === 'live' ? 'Live Student Attempts' : 'Completed Quiz Submissions Log';
            
            startLivePolling();
        };

        function loadQuizTakers() {
            const quizId = quizSelect.value;
            const classId = classSelect.value;

            if (!quizId) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Select a quiz to view data.</td></tr>';
                lastRefreshed.textContent = 'Waiting for quiz selection...';
                return;
            }

            if (isFirstLoad) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching student records...</td></tr>';
            }

            const params = new URLSearchParams();
            params.append('quiz_id', quizId);
            params.append('tab', activeTab);
            if (classId) params.append('class_id', classId);

            fetch(window.location.pathname + '?' + params.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                isFirstLoad = false;
                if (data.success && data.students) {
                    renderTable(data.students);
                } else {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No records found for this quiz.</td></tr>';
                }

                const now = new Date();
                if (activeTab === 'live') {
                    lastRefreshed.innerHTML = `<span class="live-dot"></span> Live • Auto-refreshed at ${now.toLocaleTimeString()}`;
                } else {
                    lastRefreshed.innerHTML = `<i class="fas fa-history"></i> History View • Last updated at ${now.toLocaleTimeString()}`;
                }
            })
            .catch(error => {
                console.error('Error fetching quiz records:', error);
                lastRefreshed.textContent = 'Connection error. Retrying...';
            });
        }

        function renderTable(students) {
            if (!students || students.length === 0) {
                const emptyMsg = activeTab === 'live' 
                    ? 'No student attempts found for this quiz.' 
                    : 'No completed quiz submissions logged yet.';
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center">${emptyMsg}</td></tr>`;
                return;
            }

            let html = '';
            students.forEach(student => {
                const rawStatus = (student.status || 'pending').toLowerCase();
                const sanitizedStatus = rawStatus.replace('_', ' ');
                
                // Score Badge Logic
                let scoreDisplay = '<span style="color: #9ca3af;">—</span>';
                if (student.score !== null && student.score !== undefined) {
                    const earned = parseInt(student.score, 10);
                    const total = parseInt(student.total_questions || 0, 10);

                    if (total > 0) {
                        const percentage = Math.round((earned / total) * 100);
                        scoreDisplay = `
                            <span class="score-pill">
                                <i class="fas fa-trophy" style="color:#f59e0b; font-size:11px;"></i>
                                <span class="score-num">${earned} / ${total}</span>
                                <span class="score-pct">${percentage}%</span>
                            </span>`;
                    } else {
                        scoreDisplay = `<span class="score-pill"><span class="score-num">${earned}</span></span>`;
                    }
                }
                
                html += `
                    <tr>
                        <td><strong>${escapeHtml(student.last_name)}, ${escapeHtml(student.first_name)}</strong></td>
                        <td>${escapeHtml(student.class_name || 'No Class')}</td>
                        <td><span class="badge badge-${rawStatus}">${escapeHtml(sanitizedStatus)}</span></td>
                        <td>${scoreDisplay}</td>
                        <td>${escapeHtml(student.started_at || 'N/A')}</td>
                        <td>${escapeHtml(student.submitted_at || 'N/A')}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        function startLivePolling() {
            if (pollTimer) clearInterval(pollTimer);
            isFirstLoad = true;
            loadQuizTakers();
            
            const refreshRate = activeTab === 'live' ? 4000 : 15000;
            pollTimer = setInterval(loadQuizTakers, refreshRate);
        }

        quizSelect.addEventListener('change', startLivePolling);
        classSelect.addEventListener('change', startLivePolling);

        if (quizSelect.value) {
            startLivePolling();
        }
    });
    </script>
</body>
</html>