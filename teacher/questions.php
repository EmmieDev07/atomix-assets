<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();

$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_questions');

$teacher_id = getTeacherId();

$dbError = null;
try {
    // Fetch chapters with lesson content for AI context selection
    $chapters = $db->query("
        SELECT chapter_id, chapter_title, lesson_content, CHAR_LENGTH(lesson_content) AS text_len 
        FROM chapters 
        ORDER BY chapter_order ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $lessons = $db->query("
        SELECT l.*, c.chapter_title
        FROM lessons l
        JOIN chapters c ON l.chapter_id = c.chapter_id
        ORDER BY c.chapter_order, l.lesson_order
    ")->fetchAll();

    // Fetch questions including the is_ai_generated flag explicitly
    $questionsStmt = $db->prepare("
        SELECT q.*, l.lesson_title, c.chapter_title,
               (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count,
               t.first_name, t.last_name
        FROM questions_master q 
        JOIN lessons l ON q.lesson_id = l.lesson_id 
        JOIN chapters c ON l.chapter_id = c.chapter_id 
        LEFT JOIN teachers t ON q.created_by_teacher_id = t.teacher_id
        WHERE q.created_by_teacher_id = ? OR q.visibility = 'public'
        ORDER BY q.question_id DESC
    ");
    $questionsStmt->execute([$teacher_id]);
    $questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get choices for all questions
    $allChoices = [];
    $choicesStmt = $db->query("SELECT * FROM quiz_choices ORDER BY choice_id");
    while ($choice = $choicesStmt->fetch(PDO::FETCH_ASSOC)) {
        $allChoices[$choice['question_id']][] = $choice;
    }
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('questions.php DB error: ' . $e->getMessage());
    $chapters = []; $lessons = []; $questions = []; $allChoices = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Management - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --pri: #4f46e5;
            --pri-50: #eef2ff;
            --pri-100: #e0e7ff;
            --ok: #10b981;
            --ok-50: #f0fdf4;
            --ok-100: #d1fae5;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --radius: 10px;
        }

        /* Card Container & Header Layout Styles */
        .question-card {
            background: #fff;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: border-color .15s ease;
        }
        .question-card:hover {
            border-color: var(--gray-300);
        }

        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
        }

        .tag-badge-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            row-gap: 8px;
            flex: 1;
        }

        .btn-ai-open {
            padding: 9px 18px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border: none; border-radius: var(--radius);
            font-size: .88rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 2px 8px rgba(16,185,129,.3);
            transition: all .15s ease;
        }
        .btn-ai-open:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(16,185,129,.4); }

        /* Modal Custom Box Expansion */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.65);
            backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        
        .modal-box-ui {
            background: #fff; border-radius: 18px;
            width: 900px; max-width: 95vw; max-height: 90vh;
            display: flex; flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,.28);
            animation: modalIn .2s ease-out;
            overflow: hidden;
        }
        @keyframes modalIn {
            from { transform: scale(.96) translateY(-10px); opacity: 0; }
            to   { transform: scale(1) translateY(0); opacity: 1; }
        }

        .modal-header-custom {
            display: flex; align-items: center; gap: 14px;
            padding: 20px 28px; border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, #f0fdf4, #eef2ff);
        }
        .modal-header-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16,185,129,.3);
        }
        .modal-header-custom h3 { margin: 0; font-size: 1.15rem; color: var(--gray-900); flex: 1; font-weight: 800; }
        .modal-close-custom {
            background: #fff; border: 1px solid var(--gray-200); color: var(--gray-600);
            cursor: pointer; font-size: 1.1rem; padding: 6px 12px;
            border-radius: 8px; line-height: 1; transition: all .15s;
        }
        .modal-close-custom:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

        .modal-body-custom { flex: 1; overflow-y: auto; padding: 24px 28px; }
        .modal-footer-custom {
            padding: 18px 28px; border-top: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: space-between;
            background: #fafbfc;
        }

        /* Form Control Layouts inside Modal */
        .ai-field-group { margin-bottom: 18px; position: relative; }
        .ai-field-label { font-size: .86rem; font-weight: 700; color: var(--gray-700); display: block; margin-bottom: 6px; }
        .ai-select {
            width: 100%; box-sizing: border-box;
            padding: 11px 14px; border: 1.5px solid var(--gray-200);
            border-radius: 10px; font-size: .88rem; color: var(--gray-900);
            background: #fff; font-family: inherit; transition: border-color .15s, box-shadow .15s;
        }
        .ai-select:focus { outline: none; border-color: var(--pri); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

        /* Custom Multi-Select Checkbox Dropdown */
        .multi-select-box {
            position: relative;
            user-select: none;
        }
        .multi-select-trigger {
            width: 100%; box-sizing: border-box;
            padding: 11px 14px; border: 1.5px solid var(--gray-200);
            border-radius: 10px; font-size: .88rem; color: var(--gray-900);
            background: #fff; cursor: pointer; display: flex;
            align-items: center; justify-content: space-between;
            transition: all 0.15s ease;
        }
        .multi-select-trigger:hover { border-color: var(--gray-300); }
        .multi-select-options {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-height: 220px; overflow-y: auto; z-index: 50;
            display: none; padding: 6px 0;
        }
        .multi-select-options.open { display: block; }
        .multi-option-item {
            padding: 10px 14px; display: flex; align-items: center; gap: 10px;
            cursor: pointer; transition: background 0.15s; font-size: 0.88rem; color: var(--gray-700);
        }
        .multi-option-item:hover { background: var(--pri-50); }
        .multi-option-item input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: var(--ok); cursor: pointer;
        }

        /* Draft Card Item */
        .ai-draft-card {
            background: #fff; border: 1.5px solid var(--gray-200);
            border-radius: 14px; padding: 18px; margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,.03); transition: border-color .15s;
        }
        .ai-draft-card:hover { border-color: #cbd5e1; }

        /* Extended Badges Styling */
        .badge-type { background: #fef3c7; color: #b45309; padding: 5px 12px; border-radius: 14px; font-weight: 700; text-transform: capitalize; font-size: 0.78rem; white-space: nowrap; }
        .badge-vis { background: #e2e8f0; color: #475569; padding: 5px 12px; border-radius: 14px; font-weight: 600; font-size: 0.78rem; white-space: nowrap; }
        .badge-ai { background: linear-gradient(135deg, #a855f7, #ec4899); color: #ffffff; padding: 5px 12px; border-radius: 14px; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 5px rgba(168, 85, 247, 0.3); white-space: nowrap; }
        .badge-manual { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 5px 12px; border-radius: 14px; font-weight: 600; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
        .meta-tag { color: #64748b; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #f8fafc; padding: 4px 10px; border-radius: 8px; border: 1px solid #f1f5f9; }

        /* Interactive Choice Buttons for Main View */
        .choices-preview-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .choice-pill { border-radius: 20px; padding: 6px 14px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .choice-pill.correct { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .choice-pill.incorrect { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .btn-accuracy-proof {
            font-size: 0.75rem; font-weight: 700; padding: 6px 12px;
            border-radius: 8px; background: #e0f2fe; color: #0369a1;
            border: 1px solid #bae6fd; cursor: pointer; transition: all 0.15s ease;
        }
        .btn-accuracy-proof:hover { background: #0284c7; color: #ffffff; }
        .accuracy-proof-box {
            display: none; margin-top: 12px; padding: 12px 14px;
            background: #f0fdf4; border-left: 4px solid var(--ok);
            border-radius: 8px; font-size: .84rem; color: #166534; line-height: 1.5;
        }
        .accuracy-proof-box.open { display: block; }
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
                <h1>Question Management</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Questions Bank</span>
            </nav>
            <?php if (!empty($dbError)): ?>
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <div class="content-area">
                <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <h2>My Questions</h2>
                        <div style="color:#64748b;font-weight:600;display:flex;align-items:center;gap:0.5rem;font-size:0.95rem;">
                            <i class="fas fa-info-circle"></i>
                            Question creation is managed by the admin unless you have permission.
                        </div>
                    </div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
                        <button type="button" class="btn-ai-open" onclick="openAIModal()">
                            <i class="fas fa-wand-magic-sparkles"></i> AI Assistant
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="openModal('uploadQuestionsModal')">
                            <i class="fas fa-file-excel"></i> Upload Excel
                        </button>
                        <button type="button" class="btn btn-success" onclick="openModal('addQuestionModal')">
                            <i class="fas fa-plus"></i> Add Question
                        </button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="search-bar-ui">
                    <div class="search-input-wrapper" style="margin-left:0;">
                        <input type="text" id="liveSearchInput" class="form-control search-input-ui" placeholder="Search questions..." oninput="filterQuestions()">
                        <span class="search-icon-ui"><i class="fas fa-search"></i></span>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
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
                    <!-- STRICT FILTER: SOURCE TYPE -->
                    <div class="filter-group">
                        <label>Source:</label>
                        <select id="filterSource" onchange="filterQuestions()">
                            <option value="">All Sources</option>
                            <option value="manual">Manual Questions</option>
                            <option value="ai">AI Generated</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status:</label>
                        <select id="filterArchive" onchange="filterQuestions()">
                            <option value="active">Active Questions</option>
                            <option value="archived">Archived Questions</option>
                            <option value="">All Questions</option>
                        </select>
                    </div>
                </div>

                <!-- Questions List Container -->
                <div class="questions-container" id="questionsContainer">
                    <?php if (empty($questions)): ?>
                        <div style="text-align:center; padding: 40px; color: #64748b; font-weight:600;">No questions found.</div>
                    <?php else: ?>
                        <?php foreach ($questions as $q): 
                            $isAi = (isset($q['is_ai_generated']) && (int)$q['is_ai_generated'] === 1) ? '1' : '0';
                            $qChoices = $allChoices[$q['question_id']] ?? [];
                        ?>
                        <div class="question-card" 
                             data-lesson="<?php echo $q['lesson_id']; ?>" 
                             data-type="<?php echo htmlspecialchars($q['question_type']); ?>" 
                             data-visibility="<?php echo htmlspecialchars($q['visibility']); ?>" 
                             data-ai="<?php echo $isAi; ?>"
                             data-archived="<?php echo (int)($q['is_archived'] ?? 0); ?>">
                            
                            <div class="card-header-flex">
                                <div class="tag-badge-group">
                                    <span class="badge-type"><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?></span>
                                    <span class="badge-vis"><?php echo ucfirst(htmlspecialchars($q['visibility'])); ?></span>
                                    
                                    <!-- AI COLORED TAG DISPLAY -->
                                    <?php if ($isAi === '1'): ?>
                                        <span class="badge-ai"><i class="fas fa-wand-magic-sparkles"></i> AI Generated</span>
                                    <?php else: ?>
                                        <span class="badge-manual"><i class="fas fa-user-pen"></i> Manual</span>
                                    <?php endif; ?>

                                    <span class="meta-tag"><i class="fas fa-book"></i> <?php echo htmlspecialchars($q['lesson_title']); ?></span>
                                    <span class="meta-tag"><i class="fas fa-user"></i> By: <?php echo htmlspecialchars(($q['first_name'] ?? 'John') . ' ' . ($q['last_name'] ?? 'Doe')); ?></span>
                                </div>

                                <div style="display:flex; gap:6px; flex-shrink:0;">
                                    <button class="btn btn-sm btn-primary" style="padding:6px 10px;" onclick="editQuestion(<?php echo $q['question_id']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm <?php echo !empty($q['is_archived']) ? 'btn-success' : 'btn-danger'; ?>" style="padding:6px 10px;" onclick="toggleArchiveQuestion(<?php echo $q['question_id']; ?>, <?php echo !empty($q['is_archived']) ? 'true' : 'false'; ?>)" title="<?php echo !empty($q['is_archived']) ? 'Unarchive question' : 'Archive question'; ?>">
                                        <i class="fas <?php echo !empty($q['is_archived']) ? 'fa-box-open' : 'fa-box-archive'; ?>"></i>
                                    </button>
                                </div>
                            </div>

                            <div style="font-weight:600; font-size:1rem; color:#0f172a; margin-bottom:12px; line-height:1.4;">
                                <?php echo htmlspecialchars($q['question_text']); ?>
                            </div>

                            <?php if (!empty($qChoices)): ?>
                                <div class="choices-preview-grid">
                                    <?php foreach ($qChoices as $c): ?>
                                        <div class="choice-pill <?php echo $c['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                            <i class="far fa-circle<?php echo $c['is_correct'] ? '-check' : ''; ?>"></i>
                                            <?php echo htmlspecialchars($c['choice_text']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ADD QUESTION MODAL -->
    <div class="modal" id="addQuestionModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Question</h3>
                <button type="button" class="close-btn" onclick="closeModal('addQuestionModal')">&times;</button>
            </div>
            <form id="addQuestionForm" onsubmit="submitQuestion(event)" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="questionLesson">Lesson *</label>
                        <select id="questionLesson" name="lesson_id" required>
                            <option value="">Select a Lesson</option>
                            <?php foreach ($lessons as $lesson): ?>
                            <option value="<?php echo (int)$lesson['lesson_id']; ?>">
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
                    <textarea id="questionText" name="question_text" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="questionVisibility">Visibility</label>
                    <select id="questionVisibility" name="visibility">
                        <option value="private">Private (Only you)</option>
                        <option value="public">Public (All teachers)</option>
                    </select>
                </div>
                <div class="choices-section" id="choicesSection">
                    <h4><i class="fas fa-list"></i> Answer Choices</h4>
                    <div id="choicesContainer">
                        <?php foreach (['A', 'B', 'C', 'D'] as $index => $label): ?>
                        <div class="choice-row">
                            <input type="radio" name="correct_choice" value="<?php echo $index; ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                            <input type="text" name="choices[]" placeholder="Choice <?php echo $label; ?>" required>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="addChoiceButton" class="btn btn-secondary btn-sm" onclick="addChoice()"><i class="fas fa-plus"></i> Add Choice</button>
                </div>
                <div class="trueFalse-section" id="trueFalseSection" style="display:none;">
                    <h4><i class="fas fa-check-double"></i> Correct Answer</h4>
                    <label class="radio-label"><input type="radio" name="tf_answer" value="true" checked> <span>True</span></label>
                    <label class="radio-label"><input type="radio" name="tf_answer" value="false"> <span>False</span></label>
                </div>
                <div class="shortAnswer-section" id="shortAnswerSection" style="display:none;">
                    <label for="shortAnswerInput"><strong>Correct Answer *</strong></label>
                    <input type="text" id="shortAnswerInput" name="short_answer">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Create Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EXCEL UPLOAD MODAL -->
    <div class="modal" id="uploadQuestionsModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-file-excel"></i> Bulk Upload Questions</h3>
                <button type="button" class="close-btn" onclick="closeModal('uploadQuestionsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Use the template columns: Question Type, Question Text, Choice1, Choice2, Choice3, Choice4, Correct Choice.</p>
                <button type="button" class="btn btn-outline-primary" onclick="downloadTemplate()"><i class="fas fa-download"></i> Download Template</button>
                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-group">
                        <label for="uploadLesson">Lesson *</label>
                        <select id="uploadLesson" required>
                            <option value="">Select a Lesson</option>
                            <?php foreach ($lessons as $lesson): ?>
                            <option value="<?php echo (int)$lesson['lesson_id']; ?>">
                                <?php echo htmlspecialchars($lesson['chapter_title'] . ' - ' . $lesson['lesson_title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="uploadVisibility">Visibility *</label>
                        <select id="uploadVisibility" required>
                            <option value="private">Private (Only you)</option>
                            <option value="public">Public (All teachers)</option>
                        </select>
                    </div>
                </div>
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="file-upload-content">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag and drop your Excel file here or <span class="file-upload-link">browse</span></p>
                        <input type="file" id="excelFile" accept=".xlsx,.xls,.csv" required style="display:none;">
                    </div>
                    <div class="file-info" id="fileInfo" style="display:none;">
                        <i class="fas fa-file-excel"></i>
                        <span id="fileName"></span>
                        <button type="button" class="btn btn-sm btn-link" onclick="clearFile()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadQuestionsModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="uploadQuestions()" id="uploadBtn"><i class="fas fa-upload"></i> Upload &amp; Import</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT QUESTION MODAL -->
    <div class="modal" id="editQuestionModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Question</h3>
                <button type="button" class="close-btn" onclick="closeModal('editQuestionModal')">&times;</button>
            </div>
            <form id="editQuestionForm" onsubmit="updateQuestion(event)" novalidate>
                <input type="hidden" id="editQuestionId" name="question_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editQuestionLesson">Lesson *</label>
                        <select id="editQuestionLesson" name="lesson_id" required>
                            <option value="">Select a Lesson</option>
                            <?php foreach ($lessons as $lesson): ?>
                            <option value="<?php echo (int)$lesson['lesson_id']; ?>">
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
                        <option value="private">Private (Only you)</option>
                        <option value="public">Public (All teachers)</option>
                    </select>
                </div>
                <div class="choices-section" id="editChoicesSection">
                    <h4><i class="fas fa-list"></i> Answer Choices</h4>
                    <div id="editChoicesContainer"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addEditChoice()"><i class="fas fa-plus"></i> Add Choice</button>
                </div>
                <div class="trueFalse-section" id="editTrueFalseSection" style="display:none;">
                    <h4><i class="fas fa-check-double"></i> Correct Answer</h4>
                    <label class="radio-label"><input type="radio" name="edit_tf_answer" value="true" checked> <span>True</span></label>
                    <label class="radio-label"><input type="radio" name="edit_tf_answer" value="false"> <span>False</span></label>
                </div>
                <div class="shortAnswer-section" id="editShortAnswerSection" style="display:none;">
                    <label for="editShortAnswerInput"><strong>Correct Answer *</strong></label>
                    <input type="text" id="editShortAnswerInput" name="edit_short_answer">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editQuestionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODERN AI ASSISTANT MODAL -->
    <div class="modal-overlay" id="aiModal" onclick="closeAIOnOverlay(event)">
        <div class="modal-box-ui">
            <div class="modal-header-custom">
                <div class="modal-header-icon"><i class="fas fa-brain"></i></div>
                <div>
                    <h3>AI Automatic Question Generator</h3>
                    <div style="font-size:0.8rem; color:#64748b; font-weight:500;">Select module chapters to automatically extract text and build quiz questions.</div>
                </div>
                <button class="modal-close-custom" onclick="closeAIModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body-custom" style="position:relative;">
                <div id="aiLoadingOverlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.93);z-index:50;flex-direction:column;align-items:center;justify-content:center;gap:14px;border-radius:8px;">
                    <div style="width:44px;height:44px;border:4px solid #d1fae5;border-top-color:#10b981;border-radius:50%;animation:aiSpin .8s linear infinite;"></div>
                    <div id="aiLoadingText" style="font-size:.92rem;font-weight:600;color:#065f46;text-align:center;white-space:pre-line;max-width:280px;line-height:1.5;"></div>
                    <style>@keyframes aiSpin{to{transform:rotate(360deg);}}</style>
                </div>
                
                <!-- Multi-Select Chapter Selection Dropdown -->
                <div class="ai-field-group">
                    <label class="ai-field-label"><i class="fas fa-book" style="color:var(--ok);"></i> Select Target Chapters (Multiple Allowed):</label>
                    <div class="multi-select-box">
                        <div class="multi-select-trigger" onclick="toggleMultiSelectDropdown(event)">
                            <span id="multiSelectLabel">-- Select Chapters --</span>
                            <i class="fas fa-chevron-down" style="font-size:0.8rem; color:#94a3b8;"></i>
                        </div>
                        <div class="multi-select-options" id="multiSelectDropdown">
                            <?php if (empty($chapters)): ?>
                                <div class="multi-option-item" style="color:#94a3b8;">No chapters found.</div>
                            <?php else: ?>
                                <?php foreach ($chapters as $ch): ?>
                                    <label class="multi-option-item">
                                        <input type="checkbox" class="chapter-checkbox" value="<?php echo $ch['chapter_id']; ?>" data-content="<?php echo htmlspecialchars($ch['lesson_content'] ?? ''); ?>" data-title="<?php echo htmlspecialchars($ch['chapter_title']); ?>" onchange="updateChapterSelection()">
                                        <span><?php echo htmlspecialchars($ch['chapter_title']); ?> <?php echo empty($ch['lesson_content']) ? ' (No text stored)' : ' (' . number_format($ch['text_len']) . ' chars)'; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:4px;">
                    <div>
                        <label class="ai-field-label"><i class="fas fa-sliders"></i> Format Mode:</label>
                        <select id="aiFormatType" class="ai-select">
                            <option value="mixed" selected>Mixed Formats</option>
                            <option value="mcq">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                        </select>
                    </div>
                    <div>
                        <label class="ai-field-label"><i class="fas fa-list-ol"></i> Quantity:</label>
                        <select id="aiQuestionCount" class="ai-select">
                            <option value="3">3 Questions</option>
                            <option value="5">5 Questions</option>
                            <option value="10" selected>10 Questions</option>
                            <option value="15">15 Questions</option>
                            <option value="20">20 Questions</option>
                            <option value="25">25 Questions</option>
                            <option value="30">30 Questions</option>
                        </select>
                    </div>
                    <!-- PUBLIC / PRIVATE OPTION FOR AI QUESTIONS -->
                    <div>
                        <label class="ai-field-label"><i class="fas fa-eye"></i> Visibility:</label>
                        <select id="aiVisibility" class="ai-select">
                            <option value="private" selected>Private (Only you)</option>
                            <option value="public">Public (All teachers)</option>
                        </select>
                    </div>
                </div>
                <p style="font-size:.78rem;color:#64748b;margin:4px 0 16px;"><i class="fas fa-info-circle"></i> Lesson assignment per chapter appears in the preview after generation.</p>

                <div style="display:flex; justify-content:flex-end; margin-bottom: 20px;">
                    <button type="button" class="btn-ai-open" id="btnGenerateAI" onclick="requestAIGeneration()">
                        <i class="fas fa-wand-magic-sparkles"></i> Generate Questions
                    </button>
                </div>

                <!-- Preview Area -->
                <div id="aiPreviewContainer" style="display: none; border-top: 1px dashed var(--gray-300); padding-top: 18px;">
                    <h4 style="margin: 0 0 16px; font-size: .98rem; color: var(--gray-900); display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-circle-check" style="color:var(--ok);"></i> Generated Questions Preview:
                    </h4>
                    <div id="aiQuestionsList" style="max-height: 340px; overflow-y: auto; padding-right: 4px;"></div>
                </div>
            </div>

            <div class="modal-footer-custom">
                <span id="aiCountBadge" style="font-size:.83rem; font-weight:700; background:#d1fae5; color:#065f46; padding:6px 16px; border-radius:20px;">0 Items Ready</span>
                <button type="button" class="btn btn-success" id="btnSaveAIQuestions" onclick="saveAIQuestionsToMaster()" disabled style="padding: 10px 22px;">
                    <i class="fas fa-plus-circle"></i> Save All to Question Bank
                </button>
            </div>
        </div>
    </div>

    <!-- Scripting Changes -->
    <script src="../assets/js/xlsx.full.min.js"></script>
    <script>
        // Safely parse questions and enforce is_ai_generated integer status
        const questionsData = <?php echo json_encode(array_map(function($q) {
            $q['is_ai_generated'] = (isset($q['is_ai_generated']) && (int)$q['is_ai_generated'] === 1) ? 1 : 0;
            return $q;
        }, $questions)); ?>;

        const choicesData = <?php echo json_encode($allChoices); ?>;
        const lessonsData = <?php echo json_encode(array_map(function($l){ return ['lesson_id'=>(int)$l['lesson_id'],'lesson_title'=>$l['lesson_title'],'chapter_id'=>(int)$l['chapter_id']]; }, $lessons)); ?>;
        const currentTeacherId = <?php echo json_encode((int)$teacher_id); ?>;
        window.useServerQuestionMarkup = true;

        // Prevent external scripts from overwriting server-rendered cards on DOM ready
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById('questionsContainer');
            if (container && container.children.length > 0) {
                window.initialRenderDone = true;
            }
        });

        function filterQuestions() {
            const lesson = document.getElementById('filterLesson').value;
            const type = document.getElementById('filterType').value;
            const visibility = document.getElementById('filterVisibility').value;
            const source = document.getElementById('filterSource')?.value || '';
            const search = document.getElementById('liveSearchInput')?.value.toLowerCase().trim() || '';

            const cards = document.querySelectorAll('.question-card');
            cards.forEach(card => {
                const cardLesson = card.getAttribute('data-lesson');
                const cardType = card.getAttribute('data-type');
                const cardVisibility = card.getAttribute('data-visibility');
                const cardIsAi = card.getAttribute('data-ai');
                const text = card.innerText.toLowerCase();

                let match = true;
                if (lesson && cardLesson !== lesson) match = false;
                if (type && cardType !== type) match = false;
                if (visibility && cardVisibility !== visibility) match = false;
                if (source === 'ai' && cardIsAi !== '1') match = false;
                if (source === 'manual' && cardIsAi === '1') match = false;
                if (search && !text.includes(search)) match = false;

                card.style.display = match ? 'block' : 'none';
            });
        }

        /* --- ACCURACY PROOF TOGGLE --- */
        function toggleAccuracyProof(bIdx, qIdx) {
            const proofBox = document.getElementById(`proof-${bIdx}-${qIdx}`);
            if (proofBox) {
                proofBox.classList.toggle('open');
            }
        }

        /* --- MULTI-SELECT CHAPTER LOGIC --- */
        let selectedChaptersData = [];

        function toggleMultiSelectDropdown(e) {
            e.stopPropagation();
            document.getElementById('multiSelectDropdown').classList.toggle('open');
        }

        document.addEventListener('click', (e) => {
            const box = document.querySelector('.multi-select-box');
            if (box && !box.contains(e.target)) {
                document.getElementById('multiSelectDropdown').classList.remove('open');
            }
        });

        function updateChapterSelection() {
            const checkboxes = document.querySelectorAll('.chapter-checkbox:checked');
            const label = document.getElementById('multiSelectLabel');
            selectedChaptersData = [];

            if (checkboxes.length === 0) {
                label.innerText = '-- Select Chapters --';
            } else {
                label.innerText = `${checkboxes.length} Chapter(s) Selected`;
                checkboxes.forEach(cb => {
                    if (cb.dataset.content) {
                        selectedChaptersData.push({ chapterId: cb.value, title: cb.dataset.title, text: cb.dataset.content });
                    }
                });
            }
        }

        /* --- AI GENERATOR MODAL LOGIC --- */
        let generatedAIDrafts = [];

        function openAIModal() {
            document.getElementById('aiModal').classList.add('open');
        }
        function closeAIModal() {
            document.getElementById('aiModal').classList.remove('open');
        }
        function closeAIOnOverlay(e) {
            if (e.target === document.getElementById('aiModal')) closeAIModal();
        }

        function showAILoading(show, msg) {
            const overlay = document.getElementById('aiLoadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
            if (msg) document.getElementById('aiLoadingText').textContent = msg;
        }

        async function requestAIGeneration() {
            const formatType  = document.getElementById('aiFormatType').value;
            const totalCount  = parseInt(document.getElementById('aiQuestionCount').value);
            const btn         = document.getElementById('btnGenerateAI');

            if (selectedChaptersData.length === 0) {
                Swal.fire({ icon: 'warning', title: 'No Chapters Selected', text: 'Please select at least one chapter containing stored PDF module content.', confirmColor: '#10b981' });
                return;
            }

            const numChapters    = selectedChaptersData.length;
            const basePerChapter = Math.floor(totalCount / numChapters);
            const remainder      = totalCount - (basePerChapter * numChapters);
            const CHAR_BUDGET    = 4000;

            generatedAIDrafts = [];
            showAILoading(true, 'Preparing...');
            btn.disabled  = true;

            try {
                for (let i = 0; i < numChapters; i++) {
                    const ch      = selectedChaptersData[i];
                    const chCount = basePerChapter + (i < remainder ? 1 : 0);
                    if (chCount === 0) continue;

                    showAILoading(true, `Generating... (${i + 1}/${numChapters})\n${ch.title}`);

                    const res  = await fetch('generate_ai_questions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contextText: ch.text.trim().substring(0, CHAR_BUDGET), count: chCount, formatType })
                    });
                    const data = await res.json();

                    if (!data.success) {
                        const errLower = (data.error || '').toLowerCase();
                        if (errLower.includes('quota') || errLower.includes('limit') || errLower.includes('429'))
                            throw new Error('Gemini AI daily limit reached. Please try again later.');
                        throw new Error(`"${ch.title}": ${data.error || 'Generation failed.'}`);
                    }

                    generatedAIDrafts.push({ chapterId: ch.chapterId, chapterTitle: ch.title, questions: data.questions });
                }

                renderAIPreview();
                Swal.fire({ icon: 'success', title: 'Drafts Created!', text: `Generated questions across ${numChapters} chapter(s). Assign lessons below then save.`, confirmColor: '#10b981', timer: 2200 });

            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Generation Failed', text: err.message, confirmColor: '#ef4444' });
            } finally {
                showAILoading(false);
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate Questions';
            }
        }

        function renderAIPreview() {
            const container = document.getElementById('aiPreviewContainer');
            const list      = document.getElementById('aiQuestionsList');
            const badge     = document.getElementById('aiCountBadge');
            const saveBtn   = document.getElementById('btnSaveAIQuestions');

            container.style.display = 'block';
            list.innerHTML = '';
            let totalQ = 0;

            generatedAIDrafts.forEach((batch, bIdx) => {
                const chapterLessons = lessonsData.filter(l => String(l.chapter_id) === String(batch.chapterId));
                const lessonOptions  = chapterLessons.length
                    ? chapterLessons.map(l => `<option value="${l.lesson_id}">${escapeHtml(l.lesson_title)}</option>`).join('')
                    : '<option value="">No lessons in this chapter</option>';

                const groupHeader = document.createElement('div');
                groupHeader.style.cssText = `background:#f0fdf4;border:1px solid #a7f3d0;border-radius:8px;padding:10px 14px;margin-bottom:10px;margin-top:${bIdx > 0 ? '20px' : '0'}`;
                groupHeader.innerHTML = `
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <strong style="color:#065f46;font-size:.9rem;"><i class="fas fa-book"></i> ${escapeHtml(batch.chapterTitle)} <span style="font-weight:400;color:#16a34a;">(${batch.questions.length} question${batch.questions.length !== 1 ? 's' : ''})</span></strong>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="font-size:.78rem;font-weight:600;color:#374151;white-space:nowrap;">Save to lesson:</label>
                            <select id="batchLesson_${bIdx}" class="ai-select" style="padding:5px 10px;min-width:160px;">${lessonOptions}</select>
                        </div>
                    </div>`;
                list.appendChild(groupHeader);

                batch.questions.forEach((q, qIdx) => {
                    totalQ++;
                    const item = document.createElement('div');
                    item.className = 'ai-draft-card';

                    const type = (q.question_type || 'mcq').toLowerCase();
                    let badgeClass = 'badge-mcq', typeLabel = 'Multiple Choice';
                    if (type === 'true_false')    { badgeClass = 'badge-tf'; typeLabel = 'True / False'; }
                    else if (type === 'short_answer') { badgeClass = 'badge-sa'; typeLabel = 'Short Answer'; }

                    const sourceQuote = q.source_quote || 'Verified from lesson source text.';
                    let answerHTML = '';

                    if (type === 'mcq') {
                        const choicesList = [
                            { text: q.answer_0 || '', is_correct: q.correct_answer_index == 0 },
                            { text: q.answer_1 || '', is_correct: q.correct_answer_index == 1 },
                            { text: q.answer_2 || '', is_correct: q.correct_answer_index == 2 },
                            { text: q.answer_3 || '', is_correct: q.correct_answer_index == 3 }
                        ];
                        const labels = ['A','B','C','D'];
                        answerHTML = `<div class="ai-choice-grid">${choicesList.map((ch, ci) =>
                            `<div class="ai-choice-item ${ch.is_correct ? 'correct' : ''}"><strong>${labels[ci]}:</strong> ${escapeHtml(ch.text)} ${ch.is_correct ? '<i class="fas fa-check-circle"></i>' : ''}</div>`
                        ).join('')}</div>`;
                    } else if (type === 'true_false') {
                        const tfVal = String(q.correct_answer || 'true').toLowerCase();
                        answerHTML = `<div style="margin-top:10px;font-size:.86rem;font-weight:700;color:#15803d;"><i class="fas fa-toggle-on"></i> Correct Answer: <span style="text-transform:uppercase;">${escapeHtml(tfVal)}</span></div>`;
                    } else {
                        answerHTML = `<div style="margin-top:10px;font-size:.86rem;font-weight:700;color:#15803d;"><i class="fas fa-key"></i> Expected Answer: <span>${escapeHtml(q.correct_answer || '')}</span></div>`;
                    }

                    item.innerHTML = `
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                            <div style="flex:1;">
                                <span class="badge-type">${typeLabel}</span>
                                <span class="badge-ai"><i class="fas fa-wand-magic-sparkles"></i> AI Generated</span>
                                <div style="font-size:.92rem;font-weight:700;color:#0f172a;margin-top:6px;line-height:1.4;">Q${totalQ}: ${escapeHtml(q.question_text)}</div>
                            </div>
                            <button type="button" class="btn-accuracy-proof" onclick="toggleAccuracyProof(${bIdx},${qIdx})">🔍 Source</button>
                        </div>
                        <div class="accuracy-proof-box" id="proof-${bIdx}-${qIdx}">
                            <strong>🎯 Grounded Text Snippet:</strong><br><em>"${escapeHtml(sourceQuote)}"</em>
                        </div>
                        ${answerHTML}`;
                    list.appendChild(item);
                });
            });

            badge.innerText  = `${totalQ} Drafts Ready`;
            saveBtn.disabled = totalQ === 0;
        }

        async function saveAIQuestionsToMaster() {
            if (!generatedAIDrafts.length) return;

            const selectedVisibility = document.getElementById('aiVisibility').value;

            for (let bIdx = 0; bIdx < generatedAIDrafts.length; bIdx++) {
                const sel = document.getElementById(`batchLesson_${bIdx}`);
                if (!sel || !sel.value) {
                    Swal.fire({ icon: 'warning', title: 'Missing Lesson', text: `Please select a lesson for "${generatedAIDrafts[bIdx].chapterTitle}".`, confirmColor: '#10b981' });
                    return;
                }
            }

            const btn = document.getElementById('btnSaveAIQuestions');
            btn.disabled  = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            let totalSaved = 0;
            const errors   = [];

            for (let bIdx = 0; bIdx < generatedAIDrafts.length; bIdx++) {
                const batch    = generatedAIDrafts[bIdx];
                const lessonId = document.getElementById(`batchLesson_${bIdx}`).value;

                const mapped = batch.questions.map(q => {
                    const type = (q.question_type || 'mcq').toLowerCase();
                    const base = { 
                        lesson_id: parseInt(lessonId), 
                        question_type: type, 
                        question_text: q.question_text, 
                        visibility: selectedVisibility,
                        is_ai_generated: 1 
                    };
                    if (type === 'mcq') {
                        return { ...base, choices: [q.answer_0, q.answer_1, q.answer_2, q.answer_3], correct_choice: q.correct_answer_index };
                    } else if (type === 'true_false') {
                        return { ...base, correct_choice: String(q.correct_answer).toLowerCase() === 'true' ? 'true' : 'false' };
                    } else {
                        return { ...base, correct_choice: q.correct_answer || '' };
                    }
                });

                const fd = new FormData();
                fd.append('action', 'bulk_create_questions');
                fd.append('questions', JSON.stringify(mapped));
                try {
                    const r    = await fetch('../api/question_api.php', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (data.success) totalSaved += (data.data?.imported_count ?? batch.questions.length);
                    else errors.push(`"${batch.chapterTitle}": ${data.message || 'Failed'}`);
                } catch (e) {
                    errors.push(`"${batch.chapterTitle}": Network error`);
                }
            }

            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-plus-circle"></i> Save All to Question Bank';

            if (errors.length === 0) {
                Swal.fire({ icon: 'success', title: 'Saved!', text: `${totalSaved} questions saved to the question bank.`, confirmColor: '#10b981' })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Some Batches Failed', html: errors.join('<br>'), confirmColor: '#ef4444' });
            }
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    </script>
    <script src="../assets/js/questions.js"></script>
</body>
</html>