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
$questionStats = ['public_questions' => 0, 'private_questions' => 0, 'total_chapters' => 0, 'total_lessons' => 0];

try {
    $questionStats = [
        'public_questions'  => $db->query("SELECT COUNT(*) FROM questions_master WHERE visibility = 'public'")->fetchColumn(),
        'private_questions' => $db->query("SELECT COUNT(*) FROM questions_master WHERE visibility = 'private'")->fetchColumn(),
        'total_chapters'    => $db->query("SELECT COUNT(*) FROM chapters")->fetchColumn(),
        'total_lessons'     => $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn()
    ];
} catch (Exception $e) {
    error_log('Questions page error: ' . $e->getMessage());
    $dbError = 'Failed to load question statistics.';
}

// Get all questions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalQuestions = 0;
$totalPages = 1;
$allQuestions = [];
$lessons = [];
$questions = [];
$allChoices = [];
$teachers = [];

try {
    // Get total questions count
    $totalQuestions = $db->query("SELECT COUNT(*) FROM questions_master")->fetchColumn();
    $totalPages = ceil($totalQuestions / $perPage);

    // Get questions for current page
    $allQuestions = $db->query("
        SELECT qm.question_id, qm.question_text, qm.question_type, qm.visibility,
               l.lesson_title, c.chapter_title, t.first_name, t.last_name
        FROM questions_master qm
        LEFT JOIN lessons l ON qm.lesson_id = l.lesson_id
        LEFT JOIN chapters c ON l.chapter_id = c.chapter_id
        LEFT JOIN teachers t ON qm.created_by_teacher_id = t.teacher_id
        ORDER BY qm.question_id DESC
        LIMIT $perPage OFFSET $offset
    ")->fetchAll();

    // Also load lessons, all questions and choices so admin can manage questions like teachers
    $lessons = $db->query("
        SELECT l.*, c.chapter_title
        FROM lessons l
        JOIN chapters c ON l.chapter_id = c.chapter_id
        ORDER BY c.chapter_order, l.lesson_order
    ")->fetchAll();

    // For admin show all questions
    $questions = $db->query(
        "SELECT q.*, l.lesson_title, c.chapter_title, t.first_name, t.last_name, (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count
         FROM questions_master q
         LEFT JOIN lessons l ON q.lesson_id = l.lesson_id
         LEFT JOIN chapters c ON l.chapter_id = c.chapter_id
         LEFT JOIN teachers t ON q.created_by_teacher_id = t.teacher_id
         ORDER BY q.question_id DESC"
    )->fetchAll();

    // Get choices for all questions
    $choicesStmt = $db->query("SELECT * FROM quiz_choices ORDER BY choice_id");
    while ($choice = $choicesStmt->fetch()) {
        $allChoices[$choice['question_id']][] = $choice;
    }

    // Load teachers for admin to assign authors (check users.status)
    $teachers = $db->query("SELECT t.teacher_id, t.first_name, t.last_name FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.status = 'active' ORDER BY t.first_name, t.last_name")->fetchAll();
} catch (Exception $e) {
    error_log('Questions page list error: ' . $e->getMessage());
    if (empty($dbError)) $dbError = 'Failed to load questions. Please try again.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank - Atomix Admin</title>
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

        .questions-stats {
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

        .questions-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .questions-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: #1e40af;
        }

        .questions-card h2 {
            color: #1e40af;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .questions-card h2 i {
            color: #1e40af;
        }

        .questions-grid {
            display: grid;
            gap: 1.5rem;
        }

        .question-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .question-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #1e40af;
            transform: translateX(4px);
        }

        .question-content {
            margin-bottom: 1rem;
        }

        .question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .question-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .question-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        .question-visibility {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .question-visibility.public {
            background: #dcfce7;
            color: #166534;
        }

        .question-visibility.private {
            background: #fee2e2;
            color: #991b1b;
        }

        .question-type {
            background: #e0f2fe;
            color: #1e40af;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .question-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .question-details {
            font-size: 0.9rem;
            color: #64748b;
        }

        .question-chapter {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }

        .question-teacher {
            color: #64748b;
            font-style: italic;
        }

        .question-actions {
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            background: white;
            color: #1e40af;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .page-link:hover,
        .page-link.active {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-color: #1e40af;
            transform: translateY(-2px);
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 1.5rem 0.5rem; }
            .sidebar { position: static; width: 100%; flex-direction: row; padding: 1rem; }
            .sidebar h2 { display: none; }
            .questions-stats { grid-template-columns: 1fr; }
            .questions-grid { grid-template-columns: 1fr; }
            .question-info { flex-direction: column; gap: 1rem; align-items: flex-start; }
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
                <a href="teachers_list.php" class="nav-item"><i class="fas fa-chalkboard-teacher"></i><span>Teachers</span></a>
                <a href="questions.php" class="nav-item active"><i class="fas fa-question-circle"></i><span>Questions</span></a>
                <a href="chapters.php" class="nav-item"><i class="fas fa-book-open"></i><span>Chapters</span></a>
                <a href="backup.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
                <a href="system.php" class="nav-item"><i class="fas fa-cogs"></i><span>System</span></a>
                <a href="logout.php" class="nav-item" style="margin-top: auto;" onclick="confirmAdminLogout(event)"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>Question Bank Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <nav aria-label="breadcrumb">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <span class="breadcrumb-sep">&#9656;</span>
                    <span class="breadcrumb-current">Question Bank</span>
                </div>
            </nav>

            <div class="content-area">
                <?php if (!empty($dbError)): ?>
                    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:12px 18px;border-radius:8px;margin-bottom:1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
                <h1 class="page-title"><i class="fas fa-question-circle"></i> Question Bank Management</h1>

                <!-- Statistics Cards -->
                <div class="questions-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($questionStats['public_questions']); ?></div>
                        <div class="stat-label">Public Questions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($questionStats['private_questions']); ?></div>
                        <div class="stat-label">Private Questions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($questionStats['total_chapters']); ?></div>
                        <div class="stat-label">Chapters</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($questionStats['total_lessons']); ?></div>
                        <div class="stat-label">Lessons</div>
                    </div>
                </div>

                <!-- Questions Management (teacher-like) -->
                <div class="questions-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2><i class="fas fa-list"></i> Question Bank (Admin)</h2>
                        <div>
                            <button class="btn btn-secondary" onclick="openModal('uploadQuestionsModal')">
                                <i class="fas fa-upload"></i> Upload Excel
                            </button>
                            <button class="btn btn-success" onclick="openModal('addQuestionModal')">
                                <i class="fas fa-plus"></i> Add Question
                            </button>
                        </div>
                    </div>

                    <!-- Live Search Bar -->
                    <div class="search-bar-ui" style="margin-bottom:1rem;">
                        <div class="search-input-wrapper" style="margin-left:0;">
                            <input type="text" id="liveSearchInput" class="form-control search-input-ui" placeholder="Search questions..." oninput="liveSearchQuestions()">
                            <span class="search-icon-ui"><i class="fas fa-search"></i></span>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="filter-bar" style="margin-bottom:1rem;">
                        <div class="filter-group">
                            <label>Filter by Lesson:</label>
                            <select id="filterLesson" onchange="filterQuestions()">
                                <option value="">All Lessons</option>
                                <?php foreach ($lessons as $lesson): ?>
                                <option value="<?php echo $lesson['lesson_id']; ?>">
                                    <?php echo htmlspecialchars($lesson['chapter_title'] . ' - ' . $lesson['lesson_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Filter by Type:</label>
                            <select id="filterType" onchange="filterQuestions()">
                                <option value="">All Types</option>
                                <option value="mcq">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Visibility:</label>
                            <select id="filterVisibility" onchange="filterQuestions()">
                                <option value="">All</option>
                                <option value="private">Private</option>
                                <option value="public">Public</option>
                            </select>
                        </div>
                    </div>

                    <!-- Questions List Container -->
                    <div class="questions-container" id="questionsContainer"></div>
                </div>

                <!-- Modals and supporting UI (Add/Edit/Upload) -->
                <!-- Add Question Modal -->
                <div class="modal" id="addQuestionModal">
                    <div class="modal-content modal-lg">
                        <div class="modal-header">
                            <h3><i class="fas fa-plus-circle"></i> Add New Question</h3>
                            <button class="close-btn" onclick="closeModal('addQuestionModal')">&times;</button>
                        </div>
                        <form id="addQuestionForm" onsubmit="submitQuestion(event)" novalidate>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="questionLesson">Lesson *</label>
                                    <select id="questionLesson" name="lesson_id" required>
                                        <option value="">Select a Lesson</option>
                                        <?php foreach ($lessons as $lesson): ?>
                                        <option value="<?php echo $lesson['lesson_id']; ?>">
                                            <?php echo htmlspecialchars($lesson['chapter_title'] . ' - ' . $lesson['lesson_title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="questionType">Question Type *</label>
                                    <select id="questionType" name="question_type" required onchange="toggleChoicesSection()">
                                        <option value="mcq">Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                        <option value="short_answer">Short Answer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="questionText">Question Text *</label>
                                <textarea id="questionText" name="question_text" rows="3" required placeholder="Enter your question here..."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="questionVisibility">Visibility</label>
                                <select id="questionVisibility" name="visibility">
                                    <option value="private">Private (Only admin)</option>
                                    <option value="public">Public (All teachers)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="authorTeacher">Author (Teacher)</label>
                                <select id="authorTeacher" name="author_teacher_id" required>
                                    <option value="">Select Author</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Choices Section for MCQ -->
                            <div class="choices-section" id="choicesSection">
                                <h4><i class="fas fa-list"></i> Answer Choices</h4>
                                <p class="help-text">Add at least 4 choices. Mark the correct answer(s).</p>
                                <div id="choicesContainer">
                                    <div class="choice-row">
                                        <input type="radio" name="correct_choice" value="0" checked>
                                        <input type="text" name="choices[]" value="Option A" placeholder="Choice A" required>
                                    </div>
                                    <div class="choice-row">
                                        <input type="radio" name="correct_choice" value="1">
                                        <input type="text" name="choices[]" value="Option B" placeholder="Choice B" required>
                                    </div>
                                    <div class="choice-row">
                                        <input type="radio" name="correct_choice" value="2">
                                        <input type="text" name="choices[]" value="Option C" placeholder="Choice C" required>
                                    </div>
                                    <div class="choice-row">
                                        <input type="radio" name="correct_choice" value="3">
                                        <input type="text" name="choices[]" value="Option D" placeholder="Choice D" required>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addChoice()">
                                    <i class="fas fa-plus"></i> Add Choice
                                </button>
                            </div>

                            <!-- True/False Section -->
                            <div class="trueFalse-section" id="trueFalseSection" style="display: none;">
                                <h4><i class="fas fa-check-double"></i> Correct Answer</h4>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="tf_answer" value="true" checked>
                                        <span>True</span>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="tf_answer" value="false">
                                        <span>False</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Short Answer Section -->
                            <div class="shortAnswer-section" id="shortAnswerSection" style="display: none;">
                                <label for="shortAnswerInput"><strong>Correct Answer *</strong></label>
                                <input type="text" id="shortAnswerInput" name="short_answer" placeholder="Enter correct answer here" required>
                            </div>

                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create Question</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit Question Modal -->
                <div class="modal" id="editQuestionModal">
                    <div class="modal-content modal-lg">
                        <div class="modal-header">
                            <h3><i class="fas fa-edit"></i> Edit Question</h3>
                            <button class="close-btn" onclick="closeModal('editQuestionModal')">&times;</button>
                        </div>
                        <form id="editQuestionForm" onsubmit="updateQuestion(event)" novalidate>
                            <input type="hidden" id="editQuestionId" name="question_id">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editQuestionLesson">Lesson *</label>
                                    <select id="editQuestionLesson" name="lesson_id" required>
                                        <option value="">Select a Lesson</option>
                                        <?php foreach ($lessons as $lesson): ?>
                                        <option value="<?php echo $lesson['lesson_id']; ?>">
                                            <?php echo htmlspecialchars($lesson['chapter_title'] . ' - ' . $lesson['lesson_title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="editQuestionType">Question Type *</label>
                                    <select id="editQuestionType" name="question_type" required onchange="toggleEditChoicesSection()">
                                        <option value="mcq">Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                        <option value="short_answer">Short Answer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="editQuestionText">Question Text *</label>
                                <textarea id="editQuestionText" name="question_text" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="editQuestionVisibility">Visibility</label>
                                <select id="editQuestionVisibility" name="visibility">
                                    <option value="private">Private (Only admin)</option>
                                    <option value="public">Public (All teachers)</option>
                                </select>
                            </div>

                            <!-- Edit Choices Section -->
                            <div class="choices-section" id="editChoicesSection">
                                <h4><i class="fas fa-list"></i> Answer Choices</h4>
                                <div id="editChoicesContainer"></div>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addEditChoice()">
                                    <i class="fas fa-plus"></i> Add Choice
                                </button>
                            </div>

                            <!-- Edit True/False Section -->
                            <div class="trueFalse-section" id="editTrueFalseSection" style="display: none;">
                                <h4><i class="fas fa-check-double"></i> Correct Answer</h4>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="edit_tf_answer" value="true" checked>
                                        <span>True</span>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="edit_tf_answer" value="false">
                                        <span>False</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Edit Short Answer Section -->
                            <div class="shortAnswer-section" id="editShortAnswerSection" style="display: none;">
                                <label for="editShortAnswerInput"><strong>Correct Answer *</strong></label>
                                <input type="text" id="editShortAnswerInput" name="edit_short_answer" placeholder="Enter correct answer here" required>
                            </div>

                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('editQuestionModal')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Question</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Upload Questions Modal -->
                <div class="modal" id="uploadQuestionsModal">
                    <div class="modal-content modal-lg">
                        <div class="modal-header">
                            <h3><i class="fas fa-file-excel"></i> Bulk Upload Questions</h3>
                            <button class="close-btn" onclick="closeModal('uploadQuestionsModal')">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="upload-step">
                                <div class="step-header">
                                    <div class="step-number">1</div>
                                    <h4><i class="fas fa-download"></i> Download Template</h4>
                                </div>
                                <div class="step-content">
                                    <p>Get the Excel template with the correct format for bulk uploading questions.</p>
                                    <a class="btn btn-outline-primary" href="../templates/questions_template.xlsx" download>
                                        <i class="fas fa-file-download"></i> Download Template (Excel)
                                    </a>
                                </div>
                            </div>
                            <div class="upload-step">
                                <div class="step-header">
                                    <div class="step-number">2</div>
                                    <h4><i class="fas fa-cogs"></i> Configure Settings</h4>
                                </div>
                                <div class="step-content">
                                    <p>Choose the lesson and visibility settings that will apply to all questions in your Excel file.</p>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="uploadLesson"><i class="fas fa-book"></i> Lesson *</label>
                                            <select id="uploadLesson" required>
                                                <option value="">Select a Lesson</option>
                                                <?php foreach ($lessons as $lesson): ?>
                                                <option value="<?php echo $lesson['lesson_id']; ?>">
                                                    <?php echo htmlspecialchars($lesson['chapter_title'] . ' - ' . $lesson['lesson_title']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="uploadVisibility"><i class="fas fa-eye"></i> Visibility *</label>
                                            <select id="uploadVisibility" required>
                                                <option value="private">🔒 Private (Only admin)</option>
                                                <option value="public">🌐 Public (All teachers)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="uploadAuthor"><i class="fas fa-user"></i> Author *</label>
                                            <select id="uploadAuthor" name="upload_author_teacher_id" required>
                                                <option value="">Select Author</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['teacher_id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="upload-step">
                                <div class="step-header">
                                    <div class="step-number">3</div>
                                    <h4><i class="fas fa-upload"></i> Upload Excel File</h4>
                                </div>
                                <div class="step-content">
                                    <p>Select your completed Excel file (.xlsx or .xls) with the questions to import.</p>
                                    <div class="file-upload-area" id="fileUploadArea">
                                        <div class="file-upload-content">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p>Drag & drop your Excel file here or <span class="file-upload-link">browse</span></p>
                                            <input type="file" id="excelFile" name="excelFile" accept=".xlsx,.xls" required style="display: none;">
                                        </div>
                                        <div class="file-info" id="fileInfo" style="display: none;">
                                            <i class="fas fa-file-excel"></i>
                                            <span id="fileName"></span>
                                            <button type="button" class="btn btn-sm btn-link" onclick="clearFile()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="upload-requirements">
                                        <h5><i class="fas fa-info-circle"></i> Excel Format Requirements:</h5>
                                        <ul>
                                            <li><strong>Columns:</strong> Question Type, Question Text, Choice1, Choice2, Choice3, Choice4, Correct Choice</li>
                                            <li><strong>MCQ:</strong> Requires all 4 choices and Correct Choice (0-3 index)</li>
                                            <li><strong>True/False:</strong> Only Question Text and Correct Choice (true/false) needed</li>
                                            <li><strong>Short Answer:</strong> Only Question Text and Correct Choice (the answer) needed</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadQuestionsModal')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-primary" onclick="uploadQuestions()" id="uploadBtn">
                                    <i class="fas fa-upload"></i> Upload & Import
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toast Notification -->
                <div class="toast" id="toast">
                    <span class="toast-message"></span>
                </div>

                <script src="../assets/js/xlsx.full.min.js"></script>
                <script>
                    const questionsData = <?php echo json_encode($questions); ?>;
                    const choicesData = <?php echo json_encode($allChoices); ?>;
                    const currentTeacherId = null;
                    const isAdmin = true;
                </script>
                <script src="../assets/js/questions.js"></script>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

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