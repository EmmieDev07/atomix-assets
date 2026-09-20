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

// Helper function to render export HTML structure
function generateExportHtml($db, $selectedQuestionIds, $docType, $customTitle, $includeAnswerKey) {
    // Sanitize and force UNIQUE question IDs to prevent duplicate rendering
    $selectedQuestionIds = array_values(array_unique(array_map('intval', $selectedQuestionIds)));

    if (empty($selectedQuestionIds)) {
        return '';
    }

    $inClause = implode(',', $selectedQuestionIds);
    
    // Fetch questions - removed invalid column 'q.correct_answer'
    $stmt = $db->query("
        SELECT DISTINCT q.question_id, q.question_text, q.question_type, l.lesson_title, c.chapter_title, c.chapter_id, c.chapter_order
        FROM questions_master q
        JOIN lessons l ON q.lesson_id = l.lesson_id
        JOIN chapters c ON l.chapter_id = c.chapter_id
        WHERE q.question_id IN ($inClause)
        ORDER BY c.chapter_order ASC, q.question_id ASC
    ");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch choices (used for choices and answer key derivation)
    $choicesStmt = $db->query("
        SELECT * FROM quiz_choices 
        WHERE question_id IN ($inClause) 
        ORDER BY question_id, choice_id
    ");
    $allChoices = [];
    while ($choice = $choicesStmt->fetch(PDO::FETCH_ASSOC)) {
        $allChoices[$choice['question_id']][] = $choice;
    }

    ob_start();
    ?>
    <style>
        .export-render-body { font-family: 'Times New Roman', 'Calibri', serif; font-size: 12pt; line-height: 1.3; color: #000; background: #fff; padding: 20px; }
        .export-render-body .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .export-render-body .header-table td { padding: 4px; font-weight: bold; }
        .export-render-body .header-border { border-bottom: 2pt solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .export-render-body .doc-title { text-align: center; font-size: 15pt; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; }
        .export-render-body .chapter-heading { font-size: 13pt; font-weight: bold; text-decoration: underline; margin-top: 22px; margin-bottom: 12px; }
        .export-render-body .q-block { margin-bottom: 14px; page-break-inside: avoid; }
        .export-render-body .q-table { border-collapse: collapse; width: 100%; margin-bottom: 4px; }
        .export-render-body .q-line-cell { width: 55px; vertical-align: top; font-weight: normal; }
        .export-render-body .q-text-cell { vertical-align: top; }
        .export-render-body .choice-list { margin-left: 55px; border-collapse: collapse; }
        .export-render-body .choice-list-blank { margin-left: 25px; border-collapse: collapse; }
        .export-render-body .choice-row td { padding: 2px 0; vertical-align: top; }
        .export-render-body .choice-label { width: 25px; vertical-align: top; }
        .export-render-body .answer-key-section { page-break-before: always; margin-top: 30px; }
        .export-render-body .answer-key-header { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 15px; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
    <div class="export-render-body">
        <?php if ($docType === 'exam'): ?>
            <table class="header-table header-border">
                <tr>
                    <td>Name: _____________________________________</td>
                    <td>Date: ________________________</td>
                </tr>
                <tr>
                    <td>Section: __________________________________</td>
                    <td>Score: _______________________</td>
                </tr>
            </table>
            <div class="doc-title"><?php echo htmlspecialchars($customTitle); ?></div>

        <?php elseif ($docType === 'quiz'): ?>
            <table class="header-table header-border">
                <tr>
                    <td style="font-size: 14pt;"><?php echo htmlspecialchars($customTitle); ?></td>
                    <td style="text-align: right;">Score: ________ / ________</td>
                </tr>
                <tr>
                    <td colspan="2">Name: _____________________________________ | Date: __________________ | Section: ________</td>
                </tr>
            </table>

        <?php elseif ($docType === 'blank'): ?>
            <div class="doc-title" style="margin-top: 10px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                <?php echo htmlspecialchars($customTitle); ?>
            </div>
        <?php endif; ?>

        <?php 
        $currentChapterId = null;
        $globalQuestionCounter = 1;
        $answerKeys = [];

        foreach ($questions as $q): 
            if ($currentChapterId !== $q['chapter_id']) {
                $currentChapterId = $q['chapter_id'];
                echo '<div class="chapter-heading">' . htmlspecialchars($q['chapter_title']) . '</div>';
            }

            $type = $q['question_type'];
            $qChoices = $allChoices[$q['question_id']] ?? [];
            
            // Strip [AI] prefix from output
            $cleanQuestionText = preg_replace('/^\s*\[ai\]\s*/i', '', $q['question_text']);
        ?>
            <div class="q-block">
                <?php if ($docType === 'blank'): ?>
                    <!-- Plain question layout for Blank type -->
                    <div class="q-text-cell" style="margin-bottom: 6px;">
                        <strong><?php echo $globalQuestionCounter . '.'; ?></strong> <?php echo htmlspecialchars($cleanQuestionText); ?>
                    </div>

                    <?php if ($type === 'mcq' && !empty($qChoices)): ?>
                        <table class="choice-list-blank">
                            <?php 
                            $labels = ['a', 'b', 'c', 'd', 'e', 'f'];
                            foreach ($qChoices as $idx => $choice): 
                                $lbl = $labels[$idx] ?? ($idx + 1);
                                if (!empty($choice['is_correct'])) {
                                    $answerKeys[$globalQuestionCounter] = $lbl . '. ' . $choice['choice_text'];
                                }
                            ?>
                                <tr class="choice-row">
                                    <td class="choice-label"><?php echo $lbl . '.'; ?></td>
                                    <td><?php echo htmlspecialchars($choice['choice_text']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                    <?php elseif ($type === 'true_false'): ?>
                        <table class="choice-list-blank">
                            <tr class="choice-row">
                                <td class="choice-label">a.</td>
                                <td>True</td>
                            </tr>
                            <tr class="choice-row">
                                <td class="choice-label">b.</td>
                                <td>False</td>
                            </tr>
                        </table>
                        <?php 
                        $tfAnswer = 'N/A';
                        foreach ($qChoices as $c) {
                            if (!empty($c['is_correct'])) {
                                $tfAnswer = $c['choice_text'];
                                break;
                            }
                        }
                        $answerKeys[$globalQuestionCounter] = $tfAnswer;
                        ?>

                    <?php elseif ($type === 'short_answer'): ?>
                        <?php 
                        $saAnswer = 'N/A';
                        foreach ($qChoices as $c) {
                            if (!empty($c['is_correct'])) {
                                $saAnswer = $c['choice_text'];
                                break;
                            }
                        }
                        $answerKeys[$globalQuestionCounter] = $saAnswer;
                        ?>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Standard exam/quiz layout -->
                    <table class="q-table">
                        <tr>
                            <td class="q-line-cell">______</td>
                            <td class="q-text-cell">
                                <strong><?php echo $globalQuestionCounter . '.'; ?></strong> <?php echo htmlspecialchars($cleanQuestionText); ?>
                            </td>
                        </tr>
                    </table>

                    <?php if ($type === 'mcq' && !empty($qChoices)): ?>
                        <table class="choice-list">
                            <?php 
                            $labels = ['a', 'b', 'c', 'd', 'e', 'f'];
                            foreach ($qChoices as $idx => $choice): 
                                $lbl = $labels[$idx] ?? ($idx + 1);
                                if (!empty($choice['is_correct'])) {
                                    $answerKeys[$globalQuestionCounter] = $lbl . '. ' . $choice['choice_text'];
                                }
                            ?>
                                <tr class="choice-row">
                                    <td class="choice-label"><?php echo $lbl . '.'; ?></td>
                                    <td><?php echo htmlspecialchars($choice['choice_text']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                    <?php elseif ($type === 'true_false'): ?>
                        <table class="choice-list">
                            <tr class="choice-row">
                                <td class="choice-label">a.</td>
                                <td>True</td>
                            </tr>
                            <tr class="choice-row">
                                <td class="choice-label">b.</td>
                                <td>False</td>
                            </tr>
                        </table>
                        <?php 
                        $tfAnswer = 'N/A';
                        foreach ($qChoices as $c) {
                            if (!empty($c['is_correct'])) {
                                $tfAnswer = $c['choice_text'];
                                break;
                            }
                        }
                        $answerKeys[$globalQuestionCounter] = $tfAnswer;
                        ?>

                    <?php elseif ($type === 'short_answer'): ?>
                        <div style="margin-left: 55px; margin-top: 4px;">
                            Answer: __________________________________________________________________
                        </div>
                        <?php 
                        $saAnswer = 'N/A';
                        foreach ($qChoices as $c) {
                            if (!empty($c['is_correct'])) {
                                $saAnswer = $c['choice_text'];
                                break;
                            }
                        }
                        $answerKeys[$globalQuestionCounter] = $saAnswer;
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php 
            $globalQuestionCounter++;
        endforeach; 
        ?>

        <?php if ($includeAnswerKey): ?>
            <div class="answer-key-section">
                <div class="answer-key-header">Answer Key - <?php echo htmlspecialchars($customTitle); ?></div>
                <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                    <thead>
                        <tr style="background-color: #f2f2f2;">
                            <th style="width: 15%; text-align: center;">#</th>
                            <th>Correct Answer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($answerKeys as $qNum => $ans): ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold;"><?php echo $qNum; ?></td>
                                <td><?php echo htmlspecialchars($ans); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Handle AJAX Preview Endpoint
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    header('Content-Type: text/html; charset=utf-8');
    $selectedQuestionIds = $_POST['question_ids'] ?? [];
    $selectedQuestionIds = array_values(array_unique($selectedQuestionIds));

    if (empty($selectedQuestionIds)) {
        echo "<div style='padding:30px; text-align:center; color:#ef4444; font-weight:bold;'>Please select at least one question to preview.</div>";
        exit;
    }

    $docType = $_POST['doc_type'] ?? 'exam';
    $customTitle = trim($_POST['custom_title'] ?? 'Assessment Export');
    $includeAnswerKey = isset($_POST['include_answer_key']) && $_POST['include_answer_key'] == '1';

    echo generateExportHtml($db, $selectedQuestionIds, $docType, $customTitle, $includeAnswerKey);
    exit;
}

// Handle Word Document Export Stream
if (isset($_POST['export_doc'])) {
    $docType = $_POST['doc_type'] ?? 'exam';
    $selectedQuestionIds = $_POST['question_ids'] ?? [];
    $selectedQuestionIds = array_values(array_unique($selectedQuestionIds));

    $includeAnswerKey = isset($_POST['include_answer_key']);
    $customTitle = trim($_POST['custom_title'] ?? 'Assessment Export');

    if (empty($selectedQuestionIds)) {
        $_SESSION['flash_error'] = "Please select at least one question to export.";
        header("Location: export_questions.php");
        exit;
    }

    $filename = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $customTitle)) . '_' . date('Y_m_d') . '.doc';
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: false");
    header("Pragma: no-cache");

    $contentHtml = generateExportHtml($db, $selectedQuestionIds, $docType, $customTitle, $includeAnswerKey);
    ?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset="utf-8">
        <title><?php echo htmlspecialchars($customTitle); ?></title>
    </head>
    <body>
        <?php echo $contentHtml; ?>
    </body>
    </html>
    <?php
    exit;
}

// Fetch Chapters and Questions grouped by Chapter
try {
    $chaptersStmt = $db->query("
        SELECT c.chapter_id, c.chapter_title, c.chapter_order,
               (SELECT COUNT(*) FROM questions_master q2 
                JOIN lessons l2 ON q2.lesson_id = l2.lesson_id 
                WHERE l2.chapter_id = c.chapter_id AND (q2.created_by_teacher_id = $teacher_id OR q2.visibility = 'public')) as total_questions
        FROM chapters c
        ORDER BY c.chapter_order ASC
    ");
    $chapters = $chaptersStmt->fetchAll(PDO::FETCH_ASSOC);

    $questionsStmt = $db->query("
        SELECT q.question_id, q.question_text, q.question_type, c.chapter_id
        FROM questions_master q
        JOIN lessons l ON q.lesson_id = l.lesson_id
        JOIN chapters c ON l.chapter_id = c.chapter_id
        WHERE q.created_by_teacher_id = $teacher_id OR q.visibility = 'public'
        ORDER BY c.chapter_order ASC, q.question_id DESC
    ");
    $allQuestions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

    $questionsByChapter = [];
    foreach ($allQuestions as $q) {
        $questionsByChapter[$q['chapter_id']][] = $q;
    }
} catch (PDOException $e) {
    error_log("Export page DB error: " . $e->getMessage());
    $chapters = [];
    $questionsByChapter = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Questions for Hardcopy - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pri: #4f46e5;
            --pri-hover: #4338ca;
            --pri-50: #eef2ff;
            --ok: #10b981;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        .export-grid { display: grid; grid-template-columns: 340px 1fr; gap: 24px; }
        @media (max-width: 992px) { .export-grid { grid-template-columns: 1fr; } } 
        
        .config-card { background: #fff; border: 1.5px solid var(--gray-200); border-radius: 12px; padding: 20px; height: fit-content; }
        .config-title { font-size: 1.1rem; font-weight: 800; color: var(--gray-900); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        .form-group-ui { margin-bottom: 16px; }
        .form-group-ui label { font-size: 0.85rem; font-weight: 700; color: var(--gray-700); display: block; margin-bottom: 6px; }
        .form-control-ui { width: 100%; padding: 10px 12px; border: 1.5px solid var(--gray-200); border-radius: 8px; font-size: 0.88rem; box-sizing: border-box; }
        .form-control-ui:focus { border-color: var(--pri); outline: none; }

        .doc-type-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
        .doc-type-btn { border: 1.5px solid var(--gray-200); border-radius: 8px; padding: 10px 6px; text-align: center; cursor: pointer; transition: all .15s; background: #fff; }
        .doc-type-btn:hover { border-color: var(--pri); }
        .doc-type-btn input { display: none; }
        .doc-type-btn.active { border-color: var(--pri); background: var(--pri-50); color: var(--pri); font-weight: bold; }
        .doc-type-btn i { font-size: 1.2rem; display: block; margin-bottom: 4px; }

        .chapter-accordion { background: #fff; border: 1.5px solid var(--gray-200); border-radius: 12px; margin-bottom: 14px; overflow: hidden; }
        .chapter-header { background: var(--gray-100); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; }
        .chapter-header strong { font-size: 0.95rem; color: var(--gray-900); }
        .chapter-body { padding: 16px; display: none; border-top: 1px solid var(--gray-200); }
        .chapter-accordion.open .chapter-body { display: block; }

        .q-select-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--gray-200); }
        .q-select-item:last-child { border-bottom: none; }
        .q-select-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--pri); margin-top: 3px; cursor: pointer; }
        
        .badge-type-sm { font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: #fef3c7; color: #b45309; text-transform: uppercase; white-space: nowrap; }

        .selection-bar { background: var(--pri-50); border: 1px solid #c7d2fe; padding: 12px 18px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }

        .btn-actions-group { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
        .btn-red { background: #dc2626; color: #fff; border: none; }
        .btn-red:hover { background: #b91c1c; color: #fff; }

        /* Preview Modal Styles */
        .preview-modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; }
        .preview-modal-card { background: #fff; width: 90%; max-width: 850px; height: 90vh; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); }
        .preview-modal-header { padding: 16px 24px; background: #1e293b; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        .preview-modal-body { flex: 1; overflow-y: auto; background: #525659; padding: 20px; }
        .preview-paper { background: #fff; width: 100%; max-width: 750px; margin: 0 auto; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); min-height: 100%; box-sizing: border-box; }
        .preview-modal-footer { padding: 14px 24px; background: #f1f5f9; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <h1>Document Generator &amp; Hardcopy Export</h1>
                <a href="profile.php" class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <nav class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Export Hardcopy</span>
            </nav>

            <form id="exportForm" method="POST" action="export_questions.php" onsubmit="return validateFormSelection()">
                <div class="export-grid">
                    
                    <!-- Left Column: Export Controls -->
                    <div class="config-card">
                        <div class="config-title">
                            <i class="fas fa-sliders-h" style="color:var(--pri);"></i> Document Settings
                        </div>

                        <div class="form-group-ui">
                            <label>Format Style</label>
                            <div class="doc-type-selector">
                                <label class="doc-type-btn active" id="btn-exam">
                                    <input type="radio" name="doc_type" value="exam" checked onclick="setDocType('exam')">
                                    <i class="fas fa-file-signature"></i> Exam
                                </label>
                                <label class="doc-type-btn" id="btn-quiz">
                                    <input type="radio" name="doc_type" value="quiz" onclick="setDocType('quiz')">
                                    <i class="fas fa-pen-clip"></i> Quiz
                                </label>
                                <label class="doc-type-btn" id="btn-blank">
                                    <input type="radio" name="doc_type" value="blank" onclick="setDocType('blank')">
                                    <i class="fas fa-file"></i> Blank
                                </label>
                            </div>
                        </div>

                        <div class="form-group-ui">
                            <label for="custom_title">Document Title / Header</label>
                            <input type="text" id="custom_title" name="custom_title" class="form-control-ui" value="Midterm Examination - Physics" required>
                        </div>

                        <div class="form-group-ui" style="margin-top: 10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="include_answer_key" name="include_answer_key" value="1" style="width:16px; height:16px; accent-color:var(--pri);">
                                <span>Include Answer Key at end</span>
                            </label>
                        </div>

                        <div class="btn-actions-group">
                            <button type="button" class="btn btn-secondary" onclick="openPreview()" style="width:100%; padding: 11px; font-weight: 700;">
                                <i class="fas fa-eye"></i> Preview Document
                            </button>

                            <button type="submit" name="export_doc" class="btn btn-primary" style="width: 100%; padding: 11px; font-weight: 700;">
                                <i class="fas fa-file-word"></i> Download .DOC File
                            </button>

                            <button type="button" class="btn btn-red" onclick="printPdf()" style="width: 100%; padding: 11px; font-weight: 700;">
                                <i class="fas fa-file-pdf"></i> Export / Print PDF
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Select Questions by Chapter -->
                    <div>
                        <div class="selection-bar">
                            <span style="font-weight: 700; color: var(--gray-900);">
                                Selected Unique Questions: <span id="selectedCountText" style="color:var(--pri);">0</span>
                            </span>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllQuestions(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllQuestions(false)">Deselect All</button>
                            </div>
                        </div>

                        <?php foreach ($chapters as $ch): 
                            $chQuestions = $questionsByChapter[$ch['chapter_id']] ?? [];
                        ?>
                            <div class="chapter-accordion open" id="ch-acc-<?php echo $ch['chapter_id']; ?>">
                                <div class="chapter-header" onclick="toggleAccordion('ch-acc-<?php echo $ch['chapter_id']; ?>')">
                                    <div>
                                        <input type="checkbox" onclick="toggleChapterAll(event, <?php echo $ch['chapter_id']; ?>)" class="ch-master-checkbox" data-chapter="<?php echo $ch['chapter_id']; ?>" style="margin-right:8px; accent-color:var(--pri);">
                                        <strong><?php echo htmlspecialchars($ch['chapter_title']); ?></strong>
                                    </div>
                                    <span style="font-size: 0.8rem; font-weight:600; color:#64748b;">
                                        <?php echo count($chQuestions); ?> Questions <i class="fas fa-chevron-down" style="margin-left: 6px;"></i>
                                    </span>
                                </div>
                                <div class="chapter-body">
                                    <?php if (empty($chQuestions)): ?>
                                        <div style="color: #94a3b8; font-size: 0.85rem;">No questions available in this chapter.</div>
                                    <?php else: ?>
                                        <?php foreach ($chQuestions as $q): 
                                            // Strip [AI] prefix for UI display
                                            $cleanText = preg_replace('/^\s*\[ai\]\s*/i', '', $q['question_text']);
                                        ?>
                                            <div class="q-select-item">
                                                <input type="checkbox" name="question_ids[]" value="<?php echo $q['question_id']; ?>" data-text="<?php echo htmlspecialchars(trim(strtolower($cleanText))); ?>" class="q-checkbox ch-q-<?php echo $ch['chapter_id']; ?>" onchange="updateSelectedCount()">
                                                <div style="flex:1;">
                                                    <span class="badge-type-sm"><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?></span>
                                                    <span style="font-size:0.88rem; color: var(--gray-900); font-weight: 500; margin-left: 6px;">
                                                        <?php echo htmlspecialchars($cleanText); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- LIVE PREVIEW MODAL -->
    <div id="previewModal" class="preview-modal-overlay">
        <div class="preview-modal-card">
            <div class="preview-modal-header">
                <h3 style="margin:0; font-size:1.1rem; color:#fff;"><i class="fas fa-search"></i> Hardcopy Live Preview</h3>
                <button type="button" onclick="closePreview()" style="background:transparent; border:none; color:#fff; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <div class="preview-modal-body">
                <div id="previewPaperContent" class="preview-paper">
                    <div style="text-align:center; color:#94a3b8; padding:40px;">Generating preview...</div>
                </div>
            </div>
            <div class="preview-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePreview()">Close</button>
                <button type="button" class="btn btn-red" onclick="printPdfFromModal()"><i class="fas fa-print"></i> Print / Save PDF</button>
            </div>
        </div>
    </div>

    <script>
        function setDocType(type) {
            document.querySelectorAll('.doc-type-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + type).classList.add('active');
        }

        function toggleAccordion(id) {
            document.getElementById(id).classList.toggle('open');
        }

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.q-checkbox:checked');
            const uniqueIds = new Set();
            checkedBoxes.forEach(cb => uniqueIds.add(cb.value));
            document.getElementById('selectedCountText').innerText = uniqueIds.size;
        }

        function toggleChapterAll(event, chapterId) {
            event.stopPropagation();
            const checked = event.target.checked;
            document.querySelectorAll('.ch-q-' + chapterId).forEach(cb => cb.checked = checked);
            updateSelectedCount();
        }

        function selectAllQuestions(select) {
            document.querySelectorAll('.q-checkbox').forEach(cb => cb.checked = select);
            document.querySelectorAll('.ch-master-checkbox').forEach(cb => cb.checked = select);
            updateSelectedCount();
        }

        function validateFormSelection() {
            const selected = document.querySelectorAll('.q-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one question to export.');
                return false;
            }

            const seenTexts = new Set();
            let duplicateFound = false;

            selected.forEach(cb => {
                const text = cb.getAttribute('data-text');
                if (text && seenTexts.has(text)) {
                    duplicateFound = true;
                } else if (text) {
                    seenTexts.add(text);
                }
            });

            if (duplicateFound) {
                return confirm("Notice: Duplicate questions were detected in your selection. They will automatically be deduplicated in the final export. Continue?");
            }

            return true;
        }

        function openPreview() {
            if (!validateFormSelection()) return;

            const selected = document.querySelectorAll('.q-checkbox:checked');
            const modal = document.getElementById('previewModal');
            const paper = document.getElementById('previewPaperContent');
            modal.style.display = 'flex';
            paper.innerHTML = '<div style="text-align:center; color:#64748b; padding:40px;"><i class="fas fa-circle-notch fa-spin fa-2x"></i><br><br>Building document preview...</div>';

            const formData = new FormData();
            formData.append('action', 'preview');
            formData.append('doc_type', document.querySelector('input[name="doc_type"]:checked').value);
            formData.append('custom_title', document.getElementById('custom_title').value);
            if (document.getElementById('include_answer_key').checked) {
                formData.append('include_answer_key', '1');
            }

            const uniqueIds = new Set();
            selected.forEach(cb => uniqueIds.add(cb.value));
            uniqueIds.forEach(id => formData.append('question_ids[]', id));

            fetch('export_questions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(html => {
                paper.innerHTML = html;
            })
            .catch(err => {
                paper.innerHTML = '<div style="color:red; text-align:center;">Failed to generate document preview.</div>';
            });
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function printPdf() {
            if (!validateFormSelection()) return;
            openPreview();
            setTimeout(() => {
                printPdfFromModal();
            }, 600);
        }

        function printPdfFromModal() {
            const content = document.getElementById('previewPaperContent').innerHTML;
            const printWindow = window.open('', '_blank', 'height=800,width=900');
            
            printWindow.document.write('<html><head><title>Print Hardcopy Assessment</title>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);
        }
    </script>
</body>
</html>