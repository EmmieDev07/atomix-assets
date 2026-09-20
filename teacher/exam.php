<?php
header('Location: exam_manager.php');
exit;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();
$db = Database::getInstance()->getConnection();
requireTeacherPermission($db, 'can_manage_quizzes');

function getQuizTypeLabels() {
    return [
        'lesson' => 'By Lesson',
        'chapter_graded' => 'By Chapter',
        'summative' => 'Summative',
        'mcq' => 'MCQ',
        'true_false' => 'True/False',
        'short_answer' => 'Short Answer'
    ];
}

function ensureQuizTypeColumnSupportsCategories(PDO $db) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $checked = true;
    $columnStmt = $db->prepare(
        "SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'quizzes'
           AND COLUMN_NAME = 'quiz_type'
         LIMIT 1"
    );
    $columnStmt->execute();
    $columnType = (string) $columnStmt->fetchColumn();
    if ($columnType === '' || stripos($columnType, 'enum(') !== 0) {
        return;
    }

    preg_match_all("/'([^']+)'/", $columnType, $matches);
    $existingValues = $matches[1] ?? [];
    $requiredValues = ['lesson', 'chapter_graded', 'summative'];
    $missingValues = array_diff($requiredValues, $existingValues);
    if (empty($missingValues)) {
        return;
    }

    $mergedValues = array_values(array_unique(array_merge($existingValues, $requiredValues)));
    $enumList = implode(', ', array_map(function ($value) {
        return "'" . str_replace("'", "''", $value) . "'";
    }, $mergedValues));

    $db->exec("ALTER TABLE quizzes MODIFY quiz_type ENUM($enumList) NOT NULL DEFAULT 'lesson'");
}

ensureQuizTypeColumnSupportsCategories($db);

// Define the $teacher_id variable
$teacher_id = $_SESSION['teacher_id'];

$dbError = null;
try {
// Load chapters for chapter-based quizzes
$chapters = $db->query(
    "SELECT c.chapter_id, c.chapter_title, c.chapter_order, l.lesson_id, l.lesson_title, COUNT(q.question_id) as question_count
     FROM chapters c
     LEFT JOIN lessons l ON c.chapter_id = l.chapter_id
     LEFT JOIN questions_master q ON l.lesson_id = q.lesson_id
     WHERE q.visibility = 'public' OR q.created_by_teacher_id = $teacher_id
     GROUP BY c.chapter_id, c.chapter_title, c.chapter_order, l.lesson_id, l.lesson_title
     ORDER BY c.chapter_title ASC, l.lesson_id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Organize chapters and lessons into a nested structure
$organizedChapters = [];
foreach ($chapters as $row) {
    $chapterId = $row['chapter_id'];
    $lessonId = $row['lesson_id'];

    if (!isset($organizedChapters[$chapterId])) {
        $organizedChapters[$chapterId] = [
            'chapter_title' => $row['chapter_title'],
            'chapter_order' => $row['chapter_order'],
            'lessons' => []
        ];
    }

    if ($lessonId) {
        $organizedChapters[$chapterId]['lessons'][$lessonId] = [
            'lesson_title' => $row['lesson_title'],
            'question_count' => $row['question_count']
        ];
    }
}

// Sort chapters by chapter_title
uasort($organizedChapters, function($a, $b) {
    return strcmp($a['chapter_title'], $b['chapter_title']);
});

$availableLessonIds = [];
$chapterLessonMap = [];
foreach ($organizedChapters as $chapterId => $chapter) {
    $chapterLessonMap[$chapterId] = [];
    foreach (array_keys($chapter['lessons']) as $lessonId) {
        $lessonId = (int) $lessonId;
        $availableLessonIds[] = $lessonId;
        $chapterLessonMap[$chapterId][] = $lessonId;
    }
}
$availableLessonIds = array_values(array_unique($availableLessonIds));

// Rename the array to avoid overwriting the PDO statement
$questionsStmt = $db->prepare(
    "SELECT q.*, l.lesson_id, l.lesson_title, c.chapter_title, c.chapter_id
     FROM questions_master q
     JOIN lessons l ON q.lesson_id = l.lesson_id
     JOIN chapters c ON l.chapter_id = c.chapter_id
     WHERE q.visibility = 'public' OR q.created_by_teacher_id = ?"
);
$questionsStmt->execute([$teacher_id]);
$questions = [];
while ($row = $questionsStmt->fetch(PDO::FETCH_ASSOC)) {
    $lessonId = $row['lesson_id'];
    if (!isset($questions[$lessonId])) {
        $questions[$lessonId] = [];
    }
    $questions[$lessonId][] = $row;
}

$questionIds = [];
foreach ($questions as $lessonQuestions) {
    foreach ($lessonQuestions as $question) {
        $questionIds[] = (int) $question['question_id'];
    }
}

$choicesByQuestion = [];
if (!empty($questionIds)) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $choicesStmt = $db->prepare(
        "SELECT question_id, choice_text, is_correct FROM quiz_choices WHERE question_id IN ($placeholders) ORDER BY choice_id"
    );
    $choicesStmt->execute($questionIds);
    while ($choice = $choicesStmt->fetch(PDO::FETCH_ASSOC)) {
        $qid = (int) $choice['question_id'];
        if (!isset($choicesByQuestion[$qid])) {
            $choicesByQuestion[$qid] = [];
        }
        $choicesByQuestion[$qid][] = $choice;
    }
}
} catch (PDOException $e) {
    $dbError = 'A database error occurred. Please try again later.';
    error_log('quizzes.php DB error: ' . $e->getMessage());
    $chapters = []; $organizedChapters = []; $questions = [];
    $questionIds = []; $choicesByQuestion = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deploy_quiz' && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    // Direct form submission, redirect to avoid showing JSON
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
} else {
    if (($_POST['action'] ?? '') === 'create_default_stage') {
        try {
            $columnStmt = $db->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME IN ('stages', 'chapters')"
            );
            $columnStmt->execute();
            $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);

            $stageColumns = [];
            $chapterColumns = [];
            foreach ($columns as $col) {
                if ($col['TABLE_NAME'] === 'stages') {
                    $stageColumns[$col['COLUMN_NAME']] = $col['IS_NULLABLE'];
                } elseif ($col['TABLE_NAME'] === 'chapters') {
                    $chapterColumns[$col['COLUMN_NAME']] = $col['IS_NULLABLE'];
                }
            }

            $stagesHasChapterId = array_key_exists('chapter_id', $stageColumns);
            $chaptersHasStageId = array_key_exists('stage_id', $chapterColumns);
            $chaptersStageIdNullable = $chaptersHasStageId && $chapterColumns['stage_id'] === 'YES';

            if ($stagesHasChapterId && $chaptersHasStageId && !$chaptersStageIdNullable) {
                echo json_encode(['success' => false, 'error' => 'Cannot auto-create stage with current schema. Create a stage and chapter manually.']);
                exit;
            }

            $gameIdStmt = $db->query("SELECT MIN(game_id) FROM games");
            $gameId = (int) $gameIdStmt->fetchColumn();
            if ($gameId <= 0) {
                $gameStmt = $db->prepare("INSERT INTO games (game_title, description) VALUES (?, ?)");
                $gameStmt->execute(['Default Game', 'Auto-created game']);
                $gameId = (int) $db->lastInsertId();
            }

            $chapterId = null;
            if ($stagesHasChapterId) {
                if ($chaptersHasStageId && $chaptersStageIdNullable) {
                    $chapterStmt = $db->prepare(
                        "INSERT INTO chapters (stage_id, chapter_title, chapter_order) VALUES (?, ?, ?)"
                    );
                    $chapterStmt->execute([null, 'Default Chapter', 1]);
                } else {
                    $chapterStmt = $db->prepare(
                        "INSERT INTO chapters (chapter_title, chapter_order) VALUES (?, ?)"
                    );
                    $chapterStmt->execute(['Default Chapter', 1]);
                }
                $chapterId = (int) $db->lastInsertId();
            }

            $stageStmt = $db->prepare(
                $stagesHasChapterId
                    ? "INSERT INTO stages (game_id, stage_name, science_concept, stage_order, total_levers, chapter_id)
                       VALUES (?, ?, ?, ?, ?, ?)"
                    : "INSERT INTO stages (game_id, stage_name, science_concept, stage_order, total_levers)
                       VALUES (?, ?, ?, ?, ?)"
            );
            $stageParams = [$gameId, 'Default Stage', 'Auto-created stage', 1, 0];
            if ($stagesHasChapterId) {
                $stageParams[] = $chapterId;
            }
            $stageStmt->execute($stageParams);
            $newStageId = (int) $db->lastInsertId();

            if ($chaptersHasStageId && $chaptersStageIdNullable && $chapterId) {
                $updateChapterStmt = $db->prepare("UPDATE chapters SET stage_id = ? WHERE chapter_id = ?");
                $updateChapterStmt->execute([$newStageId, $chapterId]);
            }

            echo json_encode(['success' => true, 'stage_id' => $newStageId]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
            exit;
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $jsonBody = json_decode($rawBody, true);
            if (is_array($jsonBody)) {
                $_POST = $jsonBody;
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'deploy_quiz') {
        try {
            // Handle quiz deployment
        $quiz_name = trim($_POST['quiz_name'] ?? '');
        $quiz_type = trim($_POST['quiz_type'] ?? '');
        $quiz_code = strtoupper(trim($_POST['quiz_code'] ?? ''));
        $class_ids = $_POST['class_ids'] ?? [];
        if (!is_array($class_ids)) {
            $class_ids = [];
        }
        $class_ids = array_values(array_unique(array_map('intval', $class_ids)));

        // Backward compatibility for old single-class submitters
        if (empty($class_ids) && !empty($_POST['class_id'])) {
            $class_ids[] = (int) $_POST['class_id'];
        }
        $selected_lesson_ids = $_POST['lesson_ids'] ?? [];
        if (!is_array($selected_lesson_ids)) {
            $selected_lesson_ids = [];
        }
        $selected_lesson_ids = array_map('intval', $selected_lesson_ids);
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $time_limit = trim($_POST['time_limit'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $selected_questions = $_POST['questions'] ?? [];
        $allowedQuizTypes = ['lesson', 'chapter_graded', 'summative'];

        $missing = [];
        if ($quiz_name === '') $missing[] = 'quiz_name';
        if ($quiz_type === '') $missing[] = 'quiz_type';
        if ($quiz_code === '') $missing[] = 'quiz_code';
        if (empty($class_ids)) $missing[] = 'class_ids';
        if (empty($selected_lesson_ids)) $missing[] = 'lesson_ids';
        if ($start_time === '') $missing[] = 'start_time';
        if ($end_time === '') $missing[] = 'end_time';

        if (!empty($missing)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $missing)]);
            exit;
        }

        if (!in_array($quiz_type, $allowedQuizTypes, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid quiz type selected.']);
            exit;
        }

        $selected_lesson_ids = array_values(array_intersect($selected_lesson_ids, $availableLessonIds));

        if ($quiz_type === 'summative') {
            $selected_lesson_ids = $availableLessonIds;
        } elseif ($quiz_type === 'chapter_graded') {
            $selectedChapterIds = [];
            foreach ($chapterLessonMap as $chapterId => $chapterLessonIds) {
                if (empty($chapterLessonIds)) {
                    continue;
                }
                $matchedLessonIds = array_intersect($chapterLessonIds, $selected_lesson_ids);
                if (!empty($matchedLessonIds)) {
                    if (count($matchedLessonIds) !== count($chapterLessonIds)) {
                        echo json_encode(['success' => false, 'error' => 'Chapter-graded quizzes must include complete chapters.']);
                        exit;
                    }
                    $selectedChapterIds[] = (int) $chapterId;
                }
            }

            if (empty($selectedChapterIds)) {
                echo json_encode(['success' => false, 'error' => 'Select at least one complete chapter for a chapter-graded quiz.']);
                exit;
            }
        }

        // Validate each class belongs to this teacher
        $classCheckStmt = $db->prepare("SELECT class_id FROM classes WHERE class_id = ? AND teacher_id = ?");
        $validClassIds = [];
        foreach ($class_ids as $cid) {
            $classCheckStmt->execute([$cid, $teacher_id]);
            if ($classCheckStmt->fetch()) {
                $validClassIds[] = $cid;
            }
        }
        if (empty($validClassIds) || count($validClassIds) !== count($class_ids)) {
            echo json_encode(['success' => false, 'error' => 'One or more selected classes are invalid.']);
            exit;
        }

        if (!is_array($selected_questions) || count($selected_questions) === 0) {
            echo json_encode(['success' => false, 'error' => 'Select at least one question.']);
            exit;
        }

        if (strtotime($start_time) >= strtotime($end_time)) {
            echo json_encode(['success' => false, 'error' => 'End time must be after start time.']);
            exit;
        }

        if ($time_limit !== '' && (!is_numeric($time_limit) || (int) $time_limit <= 0)) {
            echo json_encode(['success' => false, 'error' => 'Time limit must be greater than 0.']);
            exit;
        }

        $selected_questions = array_values(array_map('intval', $selected_questions));
        $placeholders = implode(',', array_fill(0, count($selected_questions), '?'));

        // Ensure selected questions belong to the same lesson
        $metaStmt = $db->prepare(
            "SELECT DISTINCT l.lesson_id, q.question_type
             FROM questions_master q
             JOIN lessons l ON q.lesson_id = l.lesson_id
             WHERE q.question_id IN ($placeholders)"
        );

        $metaStmt->execute($selected_questions);
        $metaRows = $metaStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($metaRows)) {
            echo json_encode(['success' => false, 'error' => 'Selected questions are invalid.']);
            exit;
        }

        $lessonIds = [];
        $questionTypes = [];
        foreach ($metaRows as $row) {
            if (isset($row['lesson_id'])) {
                $lessonIds[] = (int) $row['lesson_id'];
            }
            if (!empty($row['question_type'])) {
                $questionTypes[] = $row['question_type'];
            }
        }

        $lessonIds = array_values(array_unique($lessonIds));
        $questionTypes = array_values(array_unique($questionTypes));

        // Ensure all questions belong to selected lessons
        foreach ($lessonIds as $lid) {
            if (!in_array($lid, $selected_lesson_ids)) {
                echo json_encode(['success' => false, 'error' => 'Selected questions must belong to the selected lessons.']);
                exit;
            }
        }

        // The game handles stages in Unity; do not require or resolve a stage here.
        // Use NULL for `stage_id` so foreign key constraints are not violated.
        $resolvedStageId = null;

        $db->beginTransaction();
        try {
            $totalScore = count($selected_questions);
            $quizInsertStmt = $db->prepare(
                "INSERT INTO quizzes (quiz_title, class_id, stage_id, teacher_id, quiz_type, total_score, instruction, start_time, end_time, time_limit)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $quizQuestionStmt = $db->prepare("INSERT INTO quiz_questions (quiz_id, question_id) VALUES (?, ?)");
            $codeInsertStmt = $db->prepare(
                "INSERT INTO game_access_codes (teacher_id, quiz_id, access_code, is_active) VALUES (?, ?, ?, ?)"
            );
            $codeExistsStmt = $db->prepare("SELECT 1 FROM game_access_codes WHERE access_code = ? LIMIT 1");

            // Determine initial is_active value from start/end times (server time)
            $is_active = (strtotime($start_time) <= time() && strtotime($end_time) >= time()) ? 1 : 0;

            $created = [];
            $usedCodes = [];
            foreach ($validClassIds as $index => $cid) {
                $quizInsertStmt->execute([
                    $quiz_name,
                    $cid,
                    $resolvedStageId,
                    $teacher_id,
                    $quiz_type,
                    $totalScore,
                    $instructions,
                    $start_time,
                    $end_time,
                    $time_limit === '' ? null : (int) $time_limit
                ]);
                $quiz_id = (int) $db->lastInsertId();

                foreach ($selected_questions as $question_id) {
                    $quizQuestionStmt->execute([$quiz_id, $question_id]);
                }

                // Build globally-unique access code for each section to avoid lookup ambiguity.
                $candidate = strtoupper($quiz_code . ($index === 0 ? '' : '-' . ($index + 1)));
                while (true) {
                    if (in_array($candidate, $usedCodes, true)) {
                        $candidate = strtoupper(substr(md5(uniqid((string) $cid, true)), 0, 8));
                        continue;
                    }
                    $codeExistsStmt->execute([$candidate]);
                    if (!$codeExistsStmt->fetchColumn()) {
                        break;
                    }
                    $candidate = strtoupper(substr(md5(uniqid((string) $cid, true)), 0, 8));
                }

                $usedCodes[] = $candidate;
                $codeInsertStmt->execute([$teacher_id, $quiz_id, $candidate, $is_active]);
                $created[] = ['class_id' => $cid, 'quiz_id' => $quiz_id, 'quiz_code' => $candidate];
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Quiz deployed to ' . count($created) . ' section(s).',
                'quiz_code' => $created[0]['quiz_code'] ?? $quiz_code,
                'deployments' => $created
            ]);
        } catch (Exception $innerEx) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $innerEx;
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
    }
}
$classStmt = $db->prepare("SELECT c.class_id, c.class_name FROM classes c JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1 WHERE c.teacher_id = ? ORDER BY c.class_name");
$classStmt->execute([$teacher_id]);
$classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Quiz - Atomix</title>
    <link rel="stylesheet" href="../assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink-900: #101828;
            --ink-800: #182230;
            --ink-700: #344054;
            --ink-600: #475467;
            --ink-500: #667085;
            --border-200: #e4e7ec;
            --border-100: #eef2f6;
            --border-300: #d0d5dd;
            --bg-50: #f8fafc;
            --bg-100: #f1f5f9;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --primary-300: #93c5fd;
            --primary-400: #60a5fa;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e3a8a;
            --success-50: #ecfdf3;
            --success-200: #a6f4c5;
            --success-600: #039855;
            --success-700: #027a48;
            --accent-600: #0f766e;
            --brand-700: #0b3d91;
            --card: #ffffff;
            --shadow: 0 18px 40px rgba(16, 24, 40, 0.12);
            --shadow-sm: 0 4px 14px rgba(16, 24, 40, 0.08);
            --shadow-md: 0 10px 22px rgba(16, 24, 40, 0.14);
            --radius-16: 16px;
            --radius-12: 12px;
            --radius-lg: 16px;
            --radius-md: 10px;
            --radius-sm: 8px;
            --text-primary: #101828;
            --text-secondary: #667085;
            --primary-color: #2563eb;
            --transition: all 0.2s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(circle at 0% 0%, rgba(37, 99, 235, 0.1), transparent 28%),
                radial-gradient(circle at 100% 100%, rgba(15, 118, 110, 0.08), transparent 32%),
                #f6f9ff;
        }

        .content-area {
            background: var(--card);
            border: 1px solid var(--border-200);
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--shadow-sm);
            animation: fadeInUp 0.45s ease;
        }

        .db-error-alert {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }


        #deployQuizForm {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 24px;
        }

        #deployQuizForm .form-group {
            margin-bottom: 0;
        }

        /* Make lessons and questions sections span full width */
        #deployQuizForm .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-700);
            letter-spacing: 0.2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            background: var(--bg-50);
            color: var(--ink-900);
            font-size: 14px;
            transition: border 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: rgba(37, 99, 235, 0.6);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            outline: none;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .quiz-code-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .quiz-code-row input {
            flex: 1;
            min-width: 0;
        }

        .quiz-code-row .btn {
            white-space: nowrap;
            flex-shrink: 0;
        }

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .form-group > h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink-900);
            margin-bottom: 6px;
        }

        .field-help {
            display: block;
            margin-top: 6px;
            color: var(--ink-600);
            font-size: 13px;
        }

        .select-shell {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            background: var(--bg-50);
            padding: 12px;
        }

        .select-all-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--ink-600);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .class-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0;
            font-size: 14px;
            color: var(--ink-700);
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: #ffffff;
            cursor: pointer;
            transition: var(--transition);
        }

        .class-row:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }

        .class-row input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-600);
        }

        .lessons-shell {
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            padding: 16px;
            background: linear-gradient(180deg, #f9fbff 0%, #f8fafc 100%);
        }

        .chapter-group {
            margin-bottom: 20px;
            border-bottom: 1px dashed #d9e3ef;
            padding-bottom: 16px;
        }

        .chapter-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .chapter-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: var(--ink-900);
        }

        .chapter-select-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-600);
            font-weight: 600;
        }

        .lesson-count {
            color: var(--ink-500);
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            color: var(--ink-500);
            font-style: italic;
            margin: 8px 0;
        }

        .questions-header {
            background: linear-gradient(135deg, var(--bg-50) 0%, white 100%);
            border: 1px solid var(--border-200);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .selected-lessons-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 200px;
        }

        .selected-lessons-info i {
            color: var(--primary-600);
            font-size: 1.125rem;
        }

        .selected-lessons-info .label {
            font-weight: 600;
            color: var(--ink-700);
            font-size: 0.875rem;
        }

        .lessons-list {
            color: var(--ink-600);
            font-size: 0.875rem;
            flex: 1;
        }

        .questions-stats {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--ink-600);
            background: var(--bg-100);
        }

        .stat-item i {
            font-size: 1rem;
        }

        .stat-item.selected {
            background: var(--success-50);
            color: var(--success-700);
            border: 1px solid var(--success-200);
        }

        .stat-item.selected i {
            color: var(--success-600);
        }

        .stat-number {
            font-weight: 700;
            font-size: 1rem;
        }


        .chapter {
            display: none; /* Hidden by default - only show when contains selected lessons */
            background: var(--bg-50);
            border: 1px solid var(--border-200);
            border-radius: var(--radius-16);
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .chapter.visible {
            display: block;
        }

        .chapter h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink-900);
            margin-bottom: 8px;
        }

        .lesson {
            background: #ffffff;
            border-radius: var(--radius-12);
            border: 1px solid #eef2f6;
            padding: 12px 14px;
            margin: 12px 0 0 0;
        }

        .lesson h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink-600);
        }

        .questions {
            margin-top: 1rem;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .question-meta-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-left: auto;
        }

        .quiz-choice-list {
            display: grid;
            gap: 10px;
        }

        .quiz-choice-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eef2f6;
            background: #ffffff;
        }

        .quiz-choice-item.correct {
            border-color: #22c55e;
            background: #f0fdf4;
        }

        .quiz-choice-item i {
            color: #64748b;
        }

        .quiz-choice-item.correct i {
            color: #16a34a;
        }

        .question {
            display: none; /* Hidden by default - only show when lessons are selected */
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-200);
            background: linear-gradient(135deg, white 0%, var(--bg-50) 100%);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .question.visible {
            display: flex;
        }

        .question:hover {
            border-color: var(--primary-300);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .question.selected {
            border-color: var(--primary-400);
            background: linear-gradient(135deg, var(--primary-50) 0%, white 100%);
        }

        .question input[type="checkbox"] {
            margin-top: 0.125rem;
            width: 1.125rem;
            height: 1.125rem;
            accent-color: var(--primary-600);
            cursor: pointer;
            flex-shrink: 0;
        }

        .question-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .question-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .question-type {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .field-error {
            margin-top: 6px;
            font-size: 12px;
            color: #dc2626;
            display: none;
        }

        .field-error.show {
            display: block;
        }

        .input-error {
            border-color: #fca5a5 !important;
            background: #fff5f5 !important;
        }

        .lesson-checkbox-container {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .lesson-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            margin: 0;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            color: var(--ink-700);
        }

        .lesson-checkbox {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-600);
            cursor: pointer;
            margin: 0;
        }

        .lesson-text {
            flex: 1;
            line-height: 1.4;
        }

        .lesson-checkbox:checked + .lesson-text {
            color: var(--primary-700);
            font-weight: 500;
        }

        .chapter-select-all {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-600);
            cursor: pointer;
            margin: 0;
        }

        .lesson-select-all {
            width: 14px;
            height: 14px;
            accent-color: var(--primary-600);
            cursor: pointer;
            margin: 0;
        }

        .question-content label {
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            margin: 0;
            line-height: 1.4;
        }

        .selected-questions-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--bg-50);
            border: 1px solid var(--border-200);
            border-radius: var(--radius-lg);
            display: none;
        }

        .selected-questions-section h3 {
            margin: 0 0 1rem 0;
            color: var(--ink-700);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .selected-questions-container {
            display: grid;
            gap: 1rem;
        }

        .chapter-questions-group {
            background: var(--bg-50);
            border: 1px solid var(--border-300);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .chapter-questions-header {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-200);
        }

        .chapter-questions-header h3 {
            margin: 0;
            color: var(--primary-800);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .lesson-questions-group {
            background: white;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .lesson-questions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-200);
        }

        .lesson-questions-header h4 {
            margin: 0;
            color: var(--ink-700);
            font-size: 1rem;
            font-weight: 600;
        }

        .lesson-questions-header .lesson-badge {
            background: var(--primary-100);
            color: var(--primary-700);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .source-lesson-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .source-lesson-title {
            margin: 0;
        }

        .source-lesson-select {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-600);
        }

        .form-actions {
            grid-column: 1 / -1;
            position: sticky;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.6), #ffffff 45%);
            padding-top: 12px;
            margin-top: 6px;
            border-top: 1px solid var(--border-100);
            display: flex;
            justify-content: flex-end;
        }

        .form-actions .btn.btn-primary {
            min-width: 170px;
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
        }

        .deploy-results {
            display: none;
            margin-top: 18px;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            background: linear-gradient(180deg, #fcfdff 0%, #f8fafc 100%);
            padding: 14px 16px;
            animation: fadeInUp 0.35s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .deploy-results-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .deploy-results-title {
            margin: 0;
            font-size: 16px;
            color: var(--ink-800);
        }

        .deploy-results-count {
            color: var(--ink-600);
        }

        .deploy-results-list {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }

        .deploy-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid var(--border-100);
            border-radius: 10px;
            background: #ffffff;
        }

        .deploy-result-class {
            font-weight: 600;
            color: var(--ink-800);
        }

        .deploy-result-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            color: var(--brand-700);
            font-weight: 700;
            letter-spacing: 0.4px;
            background: #eef4ff;
            padding: 4px 8px;
            border-radius: 999px;
        }


        @media (max-width: 980px) {
            #deployQuizForm {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .quiz-code-row {
                flex-direction: column;
                align-items: stretch;
            }

            .quiz-code-row .btn {
                width: 100%;
                justify-content: center;
            }

            .questions {
                grid-template-columns: 1fr;
            }

            .chapter,
            .lesson {
                padding: 12px;
            }

            .lessons-grid {
                grid-template-columns: 1fr;
            }

            .selected-lessons-info {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
         <aside class="sidebar">
            <?php include 'sidebar.php'; ?>
                
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-header">
                <h1>Deploy Exam</h1>
                <a href="profile.php" class="user-info" title="My Profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <a href="questions.php">Questions Bank</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <a href="active_quizzes.php">Quizzes</a>
                <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
                <span class="breadcrumb-current">Active Quizzes</span>
            </nav>
            <?php if (!empty($dbError)): ?>
            <div class="db-error-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>
            <div class="content-area">
                <form id="deployQuizForm" onsubmit="deployQuiz(event)">
                    <div class="form-group">
                        <label for="quizName">Quiz Title *</label>
                        <input type="text" id="quizName" name="quiz_name" required placeholder="e.g., Chapter 1 Quiz">
                        <div class="field-error" data-error-for="quizName"></div>
                    </div>

                  
                    <div class="form-group">
                        <label>Sections / Classes *</label>
                        <div class="select-shell">
                            <label class="select-all-row">
                                <input type="checkbox" id="selectAllClasses" onchange="toggleAllClasses(this.checked)">
                                Select all sections
                            </label>
                            <?php foreach ($classes as $class): ?>
                                <label class="class-row">
                                    <input type="checkbox" name="class_ids[]" class="class-assignment-checkbox" value="<?php echo (int) $class['class_id']; ?>">
                                    <span><?php echo htmlspecialchars($class['class_name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="field-help">You can deploy the same quiz to multiple sections at once.</small>
                        <div class="field-error" data-error-for="classIds"></div>
                    </div>
                    <div class="form-group full-width">
                        <label id="lessonScopeLabel">Lessons *</label>
                        <div id="lessonsContainer" class="lessons-shell">
                            <?php if (!empty($organizedChapters)): ?>
                                <?php $chapterNumber = 1; ?>
                                <?php foreach ($organizedChapters as $cid => $chapter): ?>
                                    <div class="chapter-group" data-chapter-id="<?php echo (int) $cid; ?>">
                                        <div class="chapter-head">
                                            <h4 class="chapter-title"><?php echo htmlspecialchars($chapter['chapter_title']); ?></h4>
                                            <label class="chapter-select-label">
                                                <input type="checkbox" class="chapter-select-all" data-chapter-id="<?php echo (int) $cid; ?>" onchange="toggleChapterLessons(<?php echo (int) $cid; ?>, this.checked)">
                                                Select all
                                            </label>
                                        </div>
                                        <div class="lessons-grid">
                                            <?php if (!empty($chapter['lessons'])): ?>
                                                <?php foreach ($chapter['lessons'] as $lid => $lesson): ?>
                                                    <div class="lesson-checkbox-container">
                                                        <label class="lesson-label">
                                                            <input type="checkbox" name="lesson_ids[]" value="<?php echo (int) $lid; ?>" class="lesson-checkbox" onchange="updateLessonFilter()">
                                                            <span class="lesson-text"><?php echo htmlspecialchars($lesson['lesson_title']); ?> <small class="lesson-count"> (<?php echo $lesson['question_count']; ?> questions)</small></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="no-lessons empty-state" style="grid-column: 1 / -1;">No lessons available in this chapter.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-chapters empty-state">No chapters available.</p>
                            <?php endif; ?>
                        </div>
                        <div class="field-error" data-error-for="lessonIds"></div>
                    </div>
                    <div class="form-group">
                        <label for="startTime">Start Time *</label>
                        <input type="datetime-local" id="startTime" name="start_time">
                        <div class="field-error" data-error-for="startTime"></div>
                    </div>
                    <div class="form-group">
                        <label for="endTime">End Time *</label>
                        <input type="datetime-local" id="endTime" name="end_time">
                        <div class="field-error" data-error-for="endTime"></div>
                    </div>
                    <div class="form-group">
                        <label for="timeLimit">Time Limit (minutes)</label>
                        <input type="number" id="timeLimit" name="time_limit" placeholder="Leave blank for no time limit">
                        <div class="field-error" data-error-for="timeLimit"></div>
                    </div>
                    <div class="form-group">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" placeholder="Enter any instructions for students..."></textarea>
                    </div>
                    <div class="form-group full-width">
                        <h2>Select Questions</h2>
                        <div id="questionsHeader" class="questions-header">
                            <div class="selected-lessons-info">
                                <i class="fas fa-book-open"></i>
                                <span class="label">Selected lessons:</span>
                                <span id="selectedLessonsLabel" class="lessons-list">None selected</span>
                            </div>
                            <div class="questions-stats">
                                <span id="totalQuestionsCount" class="stat-item">
                                    <i class="fas fa-question-circle"></i>
                                    <span class="stat-number">0</span> questions available
                                </span>
                                <span id="selectedQuestionsCount" class="stat-item selected">
                                    <i class="fas fa-check-circle"></i>
                                    <span class="stat-number">0</span> selected
                                </span>
                            </div>
                        </div>
                        <div class="field-error" data-error-for="questions"></div>
                        
                        <!-- Active Questions Section -->
                        <div id="selectedQuestionsSection" class="selected-questions-section">
                            <h3>Questions</h3>
                            <div id="selectedQuestionsContainer" class="selected-questions-container">
                                <!-- Questions will be dynamically added here -->
                            </div>
                        </div>
                        <div id="questionSourceLibrary" style="display: none;" aria-hidden="true">
                        <?php foreach ($organizedChapters as $chapterId => $chapter): ?>
                            <div class="chapter" data-chapter-id="<?php echo (int) $chapterId; ?>" data-lesson-ids="<?php echo htmlspecialchars(implode(',', array_map('intval', array_keys($chapter['lessons'])))); ?>" id="chapter-<?php echo (int) $chapterId; ?>">
                                <h3><?php echo htmlspecialchars($chapter['chapter_title']); ?></h3>
                                <?php if (!empty($chapter['lessons'])): ?>
                                    <?php foreach ($chapter['lessons'] as $lessonId => $lesson): ?>
                                        <div class="lesson" data-lesson-id="<?php echo (int) $lessonId; ?>">
                                            <div class="source-lesson-head">
                                                <h4 class="source-lesson-title"><?php echo htmlspecialchars($lesson['lesson_title']); ?> (<?php echo $lesson['question_count']; ?> questions)</h4>
                                                <label class="source-lesson-select">
                                                    <input type="checkbox" class="lesson-select" data-lesson-id="<?php echo $lessonId; ?>" onclick="toggleLessonQuestions(<?php echo $lessonId; ?>, this.checked)">
                                                    Select all
                                                </label>
                                            </div>
                                            <div class="questions">
                                                <?php if (!empty($questions[$lessonId])): ?>
                                                    <?php foreach ($questions[$lessonId] as $question): ?>
                                                        <div class="question" data-question-id="<?php echo $question['question_id']; ?>" data-question-text="<?php echo htmlspecialchars($question['question_text']); ?>">
                                                            <input type="checkbox" name="questions[]" value="<?php echo $question['question_id']; ?>" data-lesson-id="<?php echo $lessonId; ?>">
                                                            <div class="question-content">
                                                                <label><?php echo htmlspecialchars($question['question_text']); ?></label>
                                                                <div class="question-meta">
                                                                    <span class="question-type"><?php echo htmlspecialchars($question['question_type'] ?? 'mcq'); ?></span>
                                                                    <span>•</span>
                                                                    <span>Lesson: <?php echo htmlspecialchars($lesson['lesson_title']); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="question-meta-actions">
                                                                <button type="button" class="btn btn-secondary btn-sm" onclick="openChoicesModal(<?php echo $question['question_id']; ?>)">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p>No questions available in this lesson.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No lessons available in this chapter.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Deploy Quiz</button>
                    </div>
                </form>
                <div id="deployResults" class="deploy-results">
                    <div class="deploy-results-head">
                        <h3 class="deploy-results-title">Deployment Results</h3>
                        <small id="deployResultsCount" class="deploy-results-count"></small>
                    </div>
                    <div id="deployResultsList" class="deploy-results-list"></div>
                </div>
                <div class="toast" id="toast" style="display:none;"><span class="toast-message"></span></div>
            </div>
            <div class="modal" id="choicesModal">
                <div class="modal-content" style="margin:0 auto; max-width: 700px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-list"></i> Question Details</h3>
                        <button class="close-btn" type="button" onclick="closeModal('choicesModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="choicesQuestionText" style="margin-bottom: 12px;"></p>
                        <div id="choicesList" class="quiz-choice-list"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
    const quizChoices = <?php echo json_encode($choicesByQuestion); ?>;
    const quizTypeLabels = {
        lesson: 'Lessons *',
        chapter_graded: 'Chapters *',
        summative: 'Coverage *'
    };

    function showToast(msg) {
        var toast = document.getElementById('toast');
        toast.style.display = 'block';
        toast.querySelector('.toast-message').textContent = msg;
        setTimeout(() => { toast.style.display = 'none'; }, 3500);
    }
    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.classList.add('show');
    }
    function closeModal(id) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.classList.remove('show');
    }
    function generateCode() {
        document.getElementById('quizCode').value = Math.random().toString(36).substring(2, 10).toUpperCase();
    }

    function renderDeploymentResults(deployments) {
        var wrapper = document.getElementById('deployResults');
        var count = document.getElementById('deployResultsCount');
        var list = document.getElementById('deployResultsList');
        if (!wrapper || !count || !list) {
            return;
        }

        if (!Array.isArray(deployments) || deployments.length === 0) {
            wrapper.style.display = 'none';
            count.textContent = '';
            list.innerHTML = '';
            return;
        }

        count.textContent = deployments.length + ' section(s) deployed';
        list.innerHTML = deployments.map(function(item) {
            var className = item.class_name || ('Class #' + (item.class_id || ''));
            var code = item.quiz_code || '-';
            return '<div class="deploy-result-item">'
                + '<div class="deploy-result-class">' + escapeHtml(className) + '</div>'
                + '<div class="deploy-result-code">' + escapeHtml(code) + '</div>'
                + '</div>';
        }).join('');
        wrapper.style.display = 'block';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toggleAllClasses(checked) {
        document.querySelectorAll('.class-assignment-checkbox').forEach(function(cb) {
            cb.checked = checked;
        });
    }

    function updateSelectAllClassesState() {
        var all = document.querySelectorAll('.class-assignment-checkbox');
        var checked = document.querySelectorAll('.class-assignment-checkbox:checked');
        var selectAll = document.getElementById('selectAllClasses');
        if (!selectAll || all.length === 0) return;

        if (checked.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checked.length === all.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    function getQuizTypeMode() {
        var select = document.getElementById('quizType');
        return select ? select.value : 'lesson';
    }

    function applyQuizTypeMode() {
        var quizType = getQuizTypeMode();
        var lessonLabel = document.getElementById('lessonScopeLabel');
        var quizTypeHelp = document.getElementById('quizTypeHelp');
        var lessonCheckboxes = document.querySelectorAll('.lesson-checkbox');
        var chapterCheckboxes = document.querySelectorAll('.chapter-select-all');

        if (lessonLabel && quizTypeLabels[quizType]) {
            lessonLabel.textContent = quizTypeLabels[quizType];
        }

        if (quizTypeHelp) {
            if (quizType === 'chapter_graded') {
                quizTypeHelp.textContent = 'Select complete chapters. All lessons inside each selected chapter will be included.';
            } else if (quizType === 'summative') {
                quizTypeHelp.textContent = 'Summative quizzes automatically include all lessons across all chapters.';
            } else {
                quizTypeHelp.textContent = 'Select individual lessons and choose questions from those lessons.';
            }
        }

        lessonCheckboxes.forEach(function(cb) {
            cb.disabled = quizType !== 'lesson';
        });

        chapterCheckboxes.forEach(function(cb) {
            cb.disabled = quizType === 'summative';
        });

        if (quizType === 'summative') {
            lessonCheckboxes.forEach(function(cb) {
                cb.checked = true;
            });
            chapterCheckboxes.forEach(function(cb) {
                cb.checked = true;
                cb.indeterminate = false;
            });
        } else if (quizType === 'chapter_graded') {
            chapterCheckboxes.forEach(function(cb) {
                toggleChapterLessons(cb.getAttribute('data-chapter-id'), cb.checked, true);
            });
        }

        updateLessonFilter();
    }

    // When lessons are selected, show only chapters and questions from selected lessons
    function updateLessonFilter() {
        var checkboxes = document.querySelectorAll('.lesson-checkbox:checked');
        var selectedLessonIds = Array.from(checkboxes).map(cb => cb.value);
        var questions = document.querySelectorAll('.question');
        var chapters = document.querySelectorAll('.chapter');
        var chapterGroups = document.querySelectorAll('.chapter-group');
        var label = document.getElementById('selectedLessonsLabel');
        
        if (selectedLessonIds.length === 0) {
            // Hide all chapters and questions if no lessons selected
            chapters.forEach(function(chapter){
                chapter.classList.remove('visible');
            });
            chapterGroups.forEach(function(cg){
                cg.style.display = 'block';
            });
            questions.forEach(function(q){
                q.classList.remove('visible');
            });
            if (label) {
                label.textContent = 'None selected';
            }
        } else {
            // Show only chapters that contain selected lessons
            chapters.forEach(function(chapter){
                var chapterLessonIds = (chapter.getAttribute('data-lesson-ids') || '').split(',').filter(Boolean);
                var hasSelectedLesson = chapterLessonIds.some(id => selectedLessonIds.includes(id));
                if (hasSelectedLesson) {
                    chapter.classList.add('visible');
                } else {
                    chapter.classList.remove('visible');
                }
            });
            
            chapterGroups.forEach(function(cg){
                cg.style.display = 'block';
            });
            
            questions.forEach(function(q){
                var questionLessonId = q.querySelector('input[name="questions[]"]').getAttribute('data-lesson-id');
                if (selectedLessonIds.includes(questionLessonId)) {
                    q.classList.add('visible');
                } else {
                    q.classList.remove('visible');
                }
            });
            if (label) {
                var selectedNames = Array.from(checkboxes).map(cb => cb.parentElement.querySelector('.lesson-text').textContent.split(' (')[0].trim());
                label.textContent = selectedNames.join(', ');
            }
        }

        // Update question counts after filtering
        updateQuestionCounts();
        
        // Update selected questions display
        updateSelectedQuestionsDisplay(selectedLessonIds);
        
        // Update chapter select all states
        updateChapterSelectAllStates();
    }

    function updateSelectedQuestionsDisplay(selectedLessonIds) {
        var container = document.getElementById('selectedQuestionsContainer');
        var section = document.getElementById('selectedQuestionsSection');
        
        if (selectedLessonIds.length === 0) {
            section.style.display = 'none';
            return;
        }
        
        section.style.display = 'block';
        container.innerHTML = '';
        
        // Group questions by lesson
        var questionsByLesson = {};
        
        selectedLessonIds.forEach(function(lessonId) {
            var lessonElement = document.querySelector('.lesson[data-lesson-id="' + lessonId + '"]');
            if (lessonElement) {
                var lessonTitle = lessonElement.querySelector('h4').textContent.split(' (')[0].trim();
                // Find the chapter title by traversing up the DOM
                var chapterElement = lessonElement.closest('.chapter');
                var chapterTitle = chapterElement ? chapterElement.querySelector('h3').textContent.trim() : 'Unknown Chapter';
                
                questionsByLesson[lessonId] = {
                    title: lessonTitle,
                    chapterTitle: chapterTitle,
                    questions: []
                };
            }
        });
        
        // Collect questions for each selected lesson - look in all questions regardless of visibility
        document.querySelectorAll('.lesson .questions .question').forEach(function(question) {
            var input = question.querySelector('input[name="questions[]"]');
            var lessonId = input ? input.getAttribute('data-lesson-id') : null;
            
            if (lessonId && selectedLessonIds.includes(lessonId) && questionsByLesson[lessonId]) {
                var questionData = {
                    id: question.getAttribute('data-question-id'),
                    text: question.getAttribute('data-question-text'),
                    type: question.querySelector('.question-type') ? question.querySelector('.question-type').textContent.trim() : 'mcq',
                    lessonTitle: questionsByLesson[lessonId].title,
                    chapterTitle: questionsByLesson[lessonId].chapterTitle
                };
                questionsByLesson[lessonId].questions.push(questionData);
            }
        });
        
        // Group by chapter first, then by lesson
        var questionsByChapter = {};
        Object.keys(questionsByLesson).forEach(function(lessonId) {
            var lessonData = questionsByLesson[lessonId];
            var chapterTitle = lessonData.chapterTitle;
            
            if (!questionsByChapter[chapterTitle]) {
                questionsByChapter[chapterTitle] = {};
            }
            questionsByChapter[chapterTitle][lessonId] = lessonData;
        });
        
        // Build the display grouped by chapter
        Object.keys(questionsByChapter).forEach(function(chapterTitle) {
            var chapterLessons = questionsByChapter[chapterTitle];
            
            var chapterGroup = document.createElement('div');
            chapterGroup.className = 'chapter-questions-group';
            
            var chapterHeader = document.createElement('div');
            chapterHeader.className = 'chapter-questions-header';
            chapterHeader.innerHTML = `
                <h3>${chapterTitle}</h3>
            `;
            chapterGroup.appendChild(chapterHeader);
            
            Object.keys(chapterLessons).forEach(function(lessonId) {
                var lessonData = chapterLessons[lessonId];
                if (lessonData.questions.length > 0) {
                    var lessonGroup = document.createElement('div');
                    lessonGroup.className = 'lesson-questions-group';
                    
                    var header = document.createElement('div');
                    header.className = 'lesson-questions-header';
                    header.innerHTML = `
                        <h4>${lessonData.title}</h4>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--ink-600);">
                                <input type="checkbox" class="lesson-select-all" data-lesson-id="${lessonId}" onchange="toggleLessonQuestionsSelected(${lessonId}, this.checked)">
                                Select all
                            </label>
                            <span class="lesson-badge">${lessonData.questions.length} question${lessonData.questions.length !== 1 ? 's' : ''}</span>
                        </div>
                    `;
                    lessonGroup.appendChild(header);
                    
                    var questionsGrid = document.createElement('div');
                    questionsGrid.className = 'questions';
                    
                    lessonData.questions.forEach(function(qData) {
                        // Create new question element
                        var questionDiv = document.createElement('div');
                        questionDiv.className = 'question visible';
                        questionDiv.setAttribute('data-question-id', qData.id);
                        questionDiv.setAttribute('data-question-text', qData.text);
                        
                        questionDiv.innerHTML = `
                            <input type="checkbox" name="questions[]" value="${qData.id}" data-lesson-id="${lessonId}">
                            <div class="question-content">
                                <label>${qData.text}</label>
                                <div class="question-meta">
                                    <span class="question-type">${qData.type}</span>
                                    <span>•</span>
                                    <span>Lesson: ${qData.lessonTitle}</span>
                                </div>
                            </div>
                            <div class="question-meta-actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openChoicesModal(${qData.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        `;
                        
                        questionsGrid.appendChild(questionDiv);
                    });
                    
                    lessonGroup.appendChild(questionsGrid);
                    chapterGroup.appendChild(lessonGroup);
                }
            });
            
            container.appendChild(chapterGroup);
        });
        
        // Update lesson select all states after building the display
        updateLessonSelectAllStates();
        
        // Update question counts after building the display
        updateQuestionCounts();
    }

    function updateQuestionCounts() {
        // Update total available questions count - only count questions in selected questions section
        var selectedQuestions = document.querySelectorAll('#selectedQuestionsContainer .question');
        var totalCount = selectedQuestions.length;

        document.getElementById('totalQuestionsCount').querySelector('.stat-number').textContent = totalCount;

        // Update selected count
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var selectedCheckboxes = document.querySelectorAll('input[name="questions[]"]:checked');
        var selectedCount = selectedCheckboxes.length;
        document.getElementById('selectedQuestionsCount').querySelector('.stat-number').textContent = selectedCount;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize lesson filter
        applyQuizTypeMode();
        updateLessonFilter();
        updateQuestionCounts();
        updateChapterSelectAllStates();
        updateSelectAllClassesState();

        // Add event listeners for lesson checkboxes
        document.addEventListener('change', function(e) {
            if (e.target.id === 'quizType') {
                applyQuizTypeMode();
            }
            if (e.target.classList.contains('class-assignment-checkbox')) {
                updateSelectAllClassesState();
            }
            if (e.target.classList.contains('lesson-checkbox')) {
                updateLessonFilter();
            }
            if (e.target.name === 'questions[]') {
                updateSelectedCount();
                updateLessonSelectAllStates();
            }
        });
    });

    function clearFieldError(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('input-error');
        }
        var error = document.querySelector('.field-error[data-error-for="' + fieldId + '"]');
        if (error) {
            error.textContent = '';
            error.classList.remove('show');
        }
    }

    function setFieldError(fieldId, message) {
        var field = document.getElementById(fieldId);
        if (field) {
            field.classList.add('input-error');
        }
        var error = document.querySelector('.field-error[data-error-for="' + fieldId + '"]');
        if (error) {
            error.textContent = message;
            error.classList.add('show');
        }
    }

    function validateQuizForm(form) {
        var isValid = true;
        var quizName = form.quizName.value.trim();
        var quizType = form.quizType.value.trim();
        var quizCode = form.quizCode.value.trim();
        var selectedLessons = form.querySelectorAll('input[name="lesson_ids[]"]:checked');
        var startTime = form.startTime.value;
        var endTime = form.endTime.value;
        var timeLimit = form.timeLimit.value;
        var selectedQuestions = form.querySelectorAll('input[name="questions[]"]:checked');

        ['quizName', 'quizType', 'quizCode', 'classIds', 'lessonIds', 'startTime', 'endTime', 'timeLimit', 'questions'].forEach(clearFieldError);

        if (!quizName) {
            setFieldError('quizName', 'Quiz title is required.');
            isValid = false;
        }

       

     

        var selectedClasses = form.querySelectorAll('input[name="class_ids[]"]:checked');
        if (selectedClasses.length === 0) {
            setFieldError('classIds', 'At least one section/class must be selected.');
            isValid = false;
        }

        if (selectedLessons.length === 0) {
            setFieldError('lessonIds', 'At least one lesson must be selected.');
            isValid = false;
        }

        if (!startTime) {
            setFieldError('startTime', 'Start time is required.');
            isValid = false;
        }

        if (!endTime) {
            setFieldError('endTime', 'End time is required.');
            isValid = false;
        }

        if (startTime && endTime && new Date(startTime) >= new Date(endTime)) {
            setFieldError('endTime', 'End time must be after start time.');
            isValid = false;
        }

        if (timeLimit && (isNaN(Number(timeLimit)) || Number(timeLimit) <= 0)) {
            setFieldError('timeLimit', 'Time limit must be greater than 0.');
            isValid = false;
        }

        if (selectedQuestions.length === 0) {
            var questionError = document.querySelector('.field-error[data-error-for="questions"]');
            if (questionError) {
                questionError.textContent = 'Select at least one question.';
                questionError.classList.add('show');
            }
            isValid = false;
        }

        if (!isValid) {
            showToast('Please fix the highlighted fields.');
        }

        return isValid;
    }

    function deployQuiz(event) {
        event.preventDefault();
        const form = event.target;
        if (!validateQuizForm(form)) {
            return;
        }
        const selectedClasses = Array.from(form.querySelectorAll('input[name="class_ids[]"]:checked')).map(cb => cb.value);
        const selectedLessons = Array.from(form.querySelectorAll('input[name="lesson_ids[]"]:checked')).map(cb => cb.value);
        const data = {
            action: 'deploy_quiz',
            quiz_name: form.elements['quiz_name'].value,
            quiz_type: form.elements['quiz_type'].value,
            quiz_code: form.elements['quiz_code'].value,
            class_ids: selectedClasses,
            lesson_ids: selectedLessons, // Send array of lesson IDs
            start_time: form.elements['start_time'].value,
            end_time: form.elements['end_time'].value,
            time_limit: form.elements['time_limit'].value,
            instructions: form.elements['instructions'].value
        };
        const selectedQuestions = Array.from(form.querySelectorAll('input[name=\'questions[]\']:checked')).map(q => q.value);
        const payload = new FormData();
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach(value => payload.append(key + '[]', value));
            } else {
                payload.append(key, data[key]);
            }
        });
        selectedQuestions.forEach(id => payload.append('questions[]', id));
        fetch('quizzes.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload
        })
        .then(res => res.text())
        .then(text => {
            let payload;
            try {
                payload = JSON.parse(text);
            } catch (err) {
                payload = null;
            }
            if (!payload) {
                showToast('Error: Unexpected server response.');
                return;
            }
            if (payload.success) {
                if (Array.isArray(payload.deployments) && payload.deployments.length > 0) {
                    const codes = payload.deployments.map(item => item.quiz_code).join(', ');
                    showToast('Quiz deployed to ' + payload.deployments.length + ' section(s). Codes: ' + codes);
                    renderDeploymentResults(payload.deployments);
                } else {
                    showToast('Quiz deployed! Code: ' + payload.quiz_code);
                    renderDeploymentResults([]);
                }
                form.reset();
                // Reset checkboxes and filter
                document.querySelectorAll('.class-assignment-checkbox').forEach(cb => cb.checked = false);
                updateSelectAllClassesState();
                document.querySelectorAll('.lesson-checkbox').forEach(cb => cb.checked = false);
                updateLessonFilter();
            } else {
                showToast('Error: ' + (payload.error || 'Unknown error'));
            }
        })
        .catch(() => showToast('Error deploying quiz.'));
    }

    function toggleLessonQuestions(lessonId, checked) {
        document.querySelectorAll('input[name="questions[]"][data-lesson-id="' + lessonId + '"]').forEach(input => {
            input.checked = checked;
        });

        // Update selected count after toggling
        updateSelectedCount();
    }

    function toggleChapterLessons(chapterId, checked, skipRefresh) {
        // Find all lesson checkboxes in this chapter
        var chapterCb = document.querySelector('input[data-chapter-id="' + chapterId + '"]');
        if (chapterCb) {
            var chapterGroup = chapterCb.closest('.chapter-group');
            if (chapterGroup) {
                chapterGroup.querySelectorAll('.lesson-checkbox').forEach(cb => {
                    cb.checked = checked;
                });
            }
        }
        
        // Update the lesson filter and selected questions display
        if (!skipRefresh) {
            updateLessonFilter();
        }
    }

    function toggleLessonQuestionsSelected(lessonId, checked) {
        // Find all question checkboxes in the selected questions display for this lesson
        document.querySelectorAll('#selectedQuestionsContainer input[name="questions[]"][data-lesson-id="' + lessonId + '"]').forEach(input => {
            input.checked = checked;
        });

        // Update selected count after toggling
        updateSelectedCount();
        
        // Update the lesson select all checkbox state
        updateLessonSelectAllStates();
    }

    function updateLessonSelectAllStates() {
        // Update the state of all lesson "Select All" checkboxes in selected questions
        document.querySelectorAll('.lesson-select-all').forEach(lessonCb => {
            var lessonId = lessonCb.getAttribute('data-lesson-id');
            var lessonQuestions = document.querySelectorAll('#selectedQuestionsContainer input[name="questions[]"][data-lesson-id="' + lessonId + '"]');
            var checkedQuestions = document.querySelectorAll('#selectedQuestionsContainer input[name="questions[]"][data-lesson-id="' + lessonId + '"]:checked');
            
            if (checkedQuestions.length === 0) {
                lessonCb.checked = false;
                lessonCb.indeterminate = false;
            } else if (checkedQuestions.length === lessonQuestions.length) {
                lessonCb.checked = true;
                lessonCb.indeterminate = false;
            } else {
                lessonCb.checked = false;
                lessonCb.indeterminate = true;
            }
        });
    }

    function updateChapterSelectAllStates() {
        // Update the state of all chapter "Select All" checkboxes
        document.querySelectorAll('.chapter-select-all').forEach(chapterCb => {
            var chapterId = chapterCb.getAttribute('data-chapter-id');
            var chapterGroup = chapterCb.closest('.chapter-group');
            if (chapterGroup) {
                var lessonCheckboxes = chapterGroup.querySelectorAll('.lesson-checkbox');
                var checkedLessons = chapterGroup.querySelectorAll('.lesson-checkbox:checked');
                
                if (checkedLessons.length === 0) {
                    chapterCb.checked = false;
                    chapterCb.indeterminate = false;
                } else if (checkedLessons.length === lessonCheckboxes.length) {
                    chapterCb.checked = true;
                    chapterCb.indeterminate = false;
                } else {
                    chapterCb.checked = false;
                    chapterCb.indeterminate = true;
                }
            }
        });
    }

    function openChoicesModal(questionId) {
        const modal = document.getElementById('choicesModal');
        const questionEl = document.querySelector('.question[data-question-text][data-question-id="' + questionId + '"]');
        const fallbackQuestion = document.querySelector('.question input[value="' + questionId + '"]');
        const questionTextEl = document.getElementById('choicesQuestionText');
        const choicesList = document.getElementById('choicesList');

        let questionText = '';
        if (questionEl) {
            questionText = questionEl.getAttribute('data-question-text') || '';
        } else if (fallbackQuestion && fallbackQuestion.closest('.question')) {
            questionText = fallbackQuestion.closest('.question').getAttribute('data-question-text') || '';
        }

        questionTextEl.textContent = questionText ? questionText : 'Question details';
        choicesList.innerHTML = '';

        const choices = quizChoices[questionId] || [];
        if (choices.length === 0) {
            choicesList.innerHTML = '<div class="quiz-choice-item">No choices found.</div>';
        } else {
            choices.forEach(choice => {
                const item = document.createElement('div');
                item.className = 'quiz-choice-item' + (parseInt(choice.is_correct, 10) === 1 ? ' correct' : '');
                item.innerHTML = (parseInt(choice.is_correct, 10) === 1 ? '<i class="fas fa-check-circle"></i>' : '<i class="far fa-circle"></i>') +
                    '<span>' + choice.choice_text + '</span>';
                choicesList.appendChild(item);
            });
        }

        openModal('choicesModal');
    }
    </script>
</body>
</html>
