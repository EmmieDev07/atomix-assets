<?php
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

$teacher_id = $_SESSION['teacher_id'];

$dbError = null;
try {
$chapters = $db->query(
    "SELECT c.chapter_id, c.chapter_title, c.chapter_order, l.lesson_id, l.lesson_title, COUNT(q.question_id) as question_count
     FROM chapters c
     LEFT JOIN lessons l ON c.chapter_id = l.chapter_id
     LEFT JOIN questions_master q ON l.lesson_id = q.lesson_id
     WHERE q.visibility = 'public' OR q.created_by_teacher_id = $teacher_id
     GROUP BY c.chapter_id, c.chapter_title, c.chapter_order, l.lesson_id, l.lesson_title
     ORDER BY c.chapter_title ASC, l.lesson_id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

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
                    : "INSERT INTO stages (game_id, stage_name, science_concept, stage_order, total_levers)"
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
            $quiz_name = trim($_POST['quiz_name'] ?? '');
            $quiz_type = trim($_POST['quiz_type'] ?? '');
            $quiz_code = strtoupper(trim($_POST['quiz_code'] ?? ''));
            $class_ids = $_POST['class_ids'] ?? [];
            if (!is_array($class_ids)) {
                $class_ids = [];
            }
            $class_ids = array_values(array_unique(array_map('intval', $class_ids)));

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

            $classCheckStmt = $db->prepare("SELECT class_id, class_name FROM classes WHERE class_id = ? AND teacher_id = ?");
            $validClasses = [];
            foreach ($class_ids as $cid) {
                $classCheckStmt->execute([$cid, $teacher_id]);
                if ($cRow = $classCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                    $validClasses[] = $cRow;
                }
            }
            if (empty($validClasses) || count($validClasses) !== count($class_ids)) {
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

            $selected_questions = array_values(array_unique(array_map('intval', $selected_questions)));
            $placeholders = implode(',', array_fill(0, count($selected_questions), '?'));

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
            foreach ($metaRows as $row) {
                if (isset($row['lesson_id'])) {
                    $lessonIds[] = (int) $row['lesson_id'];
                }
            }
            $lessonIds = array_values(array_unique($lessonIds));

            foreach ($lessonIds as $lid) {
                if (!in_array($lid, $selected_lesson_ids)) {
                    echo json_encode(['success' => false, 'error' => 'Selected questions must belong to the selected lessons.']);
                    exit;
                }
            }

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

                $is_active = (strtotime($start_time) <= time() && strtotime($end_time) >= time()) ? 1 : 0;

                $created = [];
                $usedCodes = [];
                foreach ($validClasses as $index => $cData) {
                    $cid = (int)$cData['class_id'];
                    $cName = $cData['class_name'];

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
                    $created[] = ['class_id' => $cid, 'class_name' => $cName, 'quiz_id' => $quiz_id, 'quiz_code' => $candidate];
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
    <!-- SweetAlert2 CSS & JS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            --brand-700: #0b3d91;
            --card: #ffffff;
            --shadow-sm: 0 4px 14px rgba(16, 24, 40, 0.08);
            --radius-16: 16px;
            --radius-12: 12px;
            --radius-lg: 16px;
            --radius-md: 10px;
            --radius-sm: 8px;
            --transition: all 0.2s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #f6f9ff;
            margin: 0;
            padding: 0;
        }

        /* Scope wrapper to retain full layout responsiveness */
        .quiz-container-wrapper {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .content-area {
            background: var(--card);
            border: 1px solid var(--border-200);
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--shadow-sm);
            width: 100%;
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

        #deployQuizForm .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-700);
            display: block;
            margin-bottom: 6px;
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
            min-height: 100px;
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

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        .select-shell {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            background: var(--bg-50);
            padding: 10px;
        }

        .select-all-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
            color: var(--ink-600);
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
        }

        /* Full padding selectability for Class Cards */
        .class-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 4px 0;
            font-size: 14px;
            color: var(--ink-700);
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: #ffffff;
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .class-row:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }

        .class-row input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-600);
            margin: 0;
            pointer-events: none; /* Let parent row handle clicks cleanly */
        }

        .lessons-shell {
            max-height: 380px;
            overflow-y: auto;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-12);
            padding: 16px;
            background: linear-gradient(180deg, #f9fbff 0%, #f8fafc 100%);
        }

        .chapter-group {
            margin-bottom: 18px;
            border-bottom: 1px dashed #d9e3ef;
            padding-bottom: 14px;
        }

        .chapter-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            gap: 12px;
        }

        .chapter-title {
            margin: 0;
            font-size: 16px;
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
            cursor: pointer;
        }

        /* Full Padding Lesson Container Clickability */
        .lesson-checkbox-container {
            background: #ffffff;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-200);
            transition: var(--transition);
            cursor: pointer;
        }

        .lesson-checkbox-container:hover {
            border-color: var(--primary-300);
            background: var(--bg-50);
        }

        .lesson-checkbox-container.selected {
            border-color: var(--primary-600);
            background: var(--primary-50);
        }

        .lesson-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin: 0;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            color: var(--ink-700);
            user-select: none;
        }

        .lesson-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-600);
            cursor: pointer;
            margin: 0;
            pointer-events: none; /* Allows entire box click */
        }

        .questions-header {
            background: #ffffff;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
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

        .stat-item.selected {
            background: var(--success-50);
            color: var(--success-700);
            border: 1px solid var(--success-200);
        }

        .selected-questions-section {
            padding: 20px;
            background: #ffffff;
            border: 1px solid var(--border-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .questions-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid var(--border-200);
            padding-bottom: 12px;
        }

        .questions-section-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--ink-900);
        }

        .search-box {
            position: relative;
            max-width: 280px;
            width: 100%;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px !important;
            font-size: 13px !important;
        }

        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-500);
            font-size: 13px;
        }

        /* Question Box Full Padding Selectable */
        .question {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            border-radius: var(--radius-12);
            border: 1px solid var(--border-200);
            border-left: 4px solid var(--border-300);
            background: #ffffff;
            transition: all 0.2s ease;
            cursor: pointer;
            user-select: none;
            margin-bottom: 8px;
        }

        .question:hover {
            border-color: var(--primary-300);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .question.selected {
            border-color: var(--primary-200);
            border-left-color: var(--primary-600);
            background: #f4f8ff;
        }

        .question input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-600);
            pointer-events: none; /* Let parent container handle clicks reliably */
        }

        .question-content {
            flex: 1;
        }

        .question-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--ink-500);
            margin-top: 4px;
        }

        .question-type {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .question-type.mcq { background: #e0f2fe; color: #0369a1; }
        .question-type.true_false { background: #fef3c7; color: #b45309; }
        .question-type.short_answer { background: #f3e8ff; color: #6b21a8; }

        .field-error {
            margin-top: 4px;
            font-size: 12px;
            color: #dc2626;
            display: none;
        }

        .field-error.show { display: block; }
        .input-error { border-color: #fca5a5 !important; background: #fff5f5 !important; }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            padding-top: 10px;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(16, 24, 40, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #ffffff;
            border-radius: var(--radius-16);
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            border: 1px solid var(--border-200);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-50);
        }
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .quiz-choice-list { display: grid; gap: 8px; }
        .quiz-choice-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-200);
            background: #ffffff;
            font-size: 14px;
        }
        .quiz-choice-item.correct {
            border-color: var(--success-200);
            background: var(--success-50);
            color: var(--success-700);
            font-weight: 600;
        }

        @media (max-width: 980px) {
            #deployQuizForm { grid-template-columns: 1fr; }
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
                <h1>Deploy New Quiz</h1>
                <a href="profile.php" class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </a>
            </header>

            <?php if (!empty($dbError)): ?>
            <div class="db-error-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <div class="quiz-container-wrapper">
                <div class="content-area">
                    <form id="deployQuizForm" onsubmit="deployQuiz(event)">
                        <div class="form-group">
                            <label for="quizName">Quiz Title *</label>
                            <input type="text" id="quizName" name="quiz_name" required placeholder="e.g., Chapter 1 Quiz">
                            <div class="field-error" data-error-for="quizName"></div>
                        </div>

                        <div class="form-group">
                            <label for="quizType">Type *</label>
                            <select id="quizType" name="quiz_type" required>
                                <option value="lesson">By Lesson</option>
                                <option value="chapter_graded">By Chapter</option>
                                <option value="summative">Summative</option>
                            </select>
                            <small id="quizTypeHelp" style="color: var(--ink-500); font-size:12px;">Select individual lessons and choose questions from those lessons.</small>
                            <div class="field-error" data-error-for="quizType"></div>
                        </div>

                        <div class="form-group">
                            <label for="quizCode">Access Code *</label>
                            <div class="quiz-code-row">
                                <input type="text" id="quizCode" name="quiz_code" readonly value="<?php echo strtoupper(substr(md5(uniqid()), 0, 8)); ?>">
                                <button type="button" class="btn btn-secondary" onclick="generateCode()">Generate</button>
                            </div>
                            <div class="field-error" data-error-for="quizCode"></div>
                        </div>

                        <div class="form-group">
                            <label>Sections / Classes *</label>
                            <div class="select-shell">
                                <label class="select-all-row">
                                    <input type="checkbox" id="selectAllClasses" onchange="toggleAllClasses(this.checked)">
                                    Select all sections
                                </label>
                                <?php foreach ($classes as $class): ?>
                                    <div class="class-row" onclick="toggleCheckboxRow(this, event)">
                                        <input type="checkbox" name="class_ids[]" class="class-assignment-checkbox" value="<?php echo (int) $class['class_id']; ?>">
                                        <span><?php echo htmlspecialchars($class['class_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="field-error" data-error-for="classIds"></div>
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
                            <input type="number" id="timeLimit" name="time_limit" placeholder="Leave blank for no limit">
                            <div class="field-error" data-error-for="timeLimit"></div>
                        </div>

                        <div class="form-group">
                            <label for="instructions">Instructions</label>
                            <textarea id="instructions" name="instructions" placeholder="Enter quiz instructions..."></textarea>
                        </div>

                        <!-- Lessons Selection -->
                        <div class="form-group full-width">
                            <label id="lessonScopeLabel">Lessons *</label>
                            <div id="lessonsContainer" class="lessons-shell">
                                <?php if (!empty($organizedChapters)): ?>
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
                                                        <div class="lesson-checkbox-container" onclick="toggleCheckboxRow(this, event)">
                                                            <label class="lesson-label">
                                                                <input type="checkbox" name="lesson_ids[]" value="<?php echo (int) $lid; ?>" class="lesson-checkbox" onchange="updateLessonFilter()">
                                                                <span class="lesson-text"><?php echo htmlspecialchars($lesson['lesson_title']); ?> <small style="color:var(--ink-500);"> (<?php echo $lesson['question_count']; ?> q's)</small></span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="field-error" data-error-for="lessonIds"></div>
                        </div>

                        <!-- Questions Selection & Preview Header -->
                        <div class="form-group full-width">
                            <div id="questionsHeader" class="questions-header">
                                <div class="selected-lessons-info">
                                    <i class="fas fa-book-open" style="color:var(--primary-600)"></i>
                                    <span style="font-weight:600;">Selected lessons:</span>
                                    <span id="selectedLessonsLabel" style="color:var(--ink-600);">None selected</span>
                                </div>
                                <div class="questions-stats">
                                    <span id="totalQuestionsCount" class="stat-item">
                                        <i class="fas fa-question-circle"></i>
                                        <span class="stat-number">0</span> available
                                    </span>
                                    <span id="selectedQuestionsCount" class="stat-item selected">
                                        <i class="fas fa-check-circle"></i>
                                        <span class="stat-number">0</span> selected
                                    </span>
                                </div>
                            </div>
                            <div class="field-error" data-error-for="questions"></div>

                            <!-- Selected Questions Dynamic Container -->
                            <div id="selectedQuestionsSection" class="selected-questions-section">
                                <div class="questions-section-header">
                                    <h3><i class="fas fa-tasks"></i> Question Selection & Preview</h3>
                                    <div class="search-box">
                                        <i class="fas fa-search search-icon"></i>
                                        <input type="text" id="questionSearch" placeholder="Search questions..." oninput="filterQuestionsBySearch()">
                                    </div>
                                </div>
                                <div id="selectedQuestionsContainer"></div>
                            </div>

                            <!-- Hidden Question Data Repository -->
                            <div id="questionSourceLibrary" style="display: none;" aria-hidden="true">
                            <?php foreach ($organizedChapters as $chapterId => $chapter): ?>
                                <div class="chapter" data-chapter-id="<?php echo (int) $chapterId; ?>">
                                    <h3><?php echo htmlspecialchars($chapter['chapter_title']); ?></h3>
                                    <?php if (!empty($chapter['lessons'])): ?>
                                        <?php foreach ($chapter['lessons'] as $lessonId => $lesson): ?>
                                            <div class="lesson" data-lesson-id="<?php echo (int) $lessonId; ?>">
                                                <h4><?php echo htmlspecialchars($lesson['lesson_title']); ?></h4>
                                                <div class="questions">
                                                    <?php if (!empty($questions[$lessonId])): ?>
                                                        <?php foreach ($questions[$lessonId] as $question): ?>
                                                            <div class="question" data-question-id="<?php echo $question['question_id']; ?>" data-question-text="<?php echo htmlspecialchars($question['question_text']); ?>">
                                                                <input type="checkbox" class="source-question-cb" value="<?php echo $question['question_id']; ?>" data-lesson-id="<?php echo $lessonId; ?>">
                                                                <div class="question-content">
                                                                    <label><?php echo htmlspecialchars($question['question_text']); ?></label>
                                                                    <div class="question-meta">
                                                                        <span class="question-type <?php echo htmlspecialchars(strtolower($question['question_type'] ?? 'mcq')); ?>"><?php echo htmlspecialchars($question['question_type'] ?? 'mcq'); ?></span>
                                                                        <span>• Lesson: <?php echo htmlspecialchars($lesson['lesson_title']); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 28px;">Deploy Quiz</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Question Choices Preview Modal -->
            <div class="modal" id="choicesModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 style="margin:0;"><i class="fas fa-list"></i> Question Preview</h3>
                        <button class="close-btn" type="button" onclick="closeModal('choicesModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="choicesQuestionText" style="margin-bottom: 12px; font-weight:600;"></p>
                        <div id="choicesList" class="quiz-choice-list"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
   const quizChoices = <?php echo json_encode($choicesByQuestion); ?>;

    // Helper for full padding card selections
    function toggleCheckboxRow(container, event) {
        if (event.target.tagName === 'BUTTON' || event.target.closest('button')) return;
        var cb = container.querySelector('input[type="checkbox"]');
        if (cb) {
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('show');
    }

    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.remove('show');
    }

    function generateCode() {
        document.getElementById('quizCode').value = Math.random().toString(36).substring(2, 10).toUpperCase();
    }

    function toggleAllClasses(checked) {
        document.querySelectorAll('.class-assignment-checkbox').forEach(cb => cb.checked = checked);
    }

    function applyQuizTypeMode() {
        var quizType = document.getElementById('quizType').value;
        var lessonCheckboxes = document.querySelectorAll('.lesson-checkbox');
        var chapterCheckboxes = document.querySelectorAll('.chapter-select-all');

        lessonCheckboxes.forEach(cb => cb.disabled = (quizType !== 'lesson'));
        chapterCheckboxes.forEach(cb => cb.disabled = (quizType === 'summative'));

        if (quizType === 'summative') {
            lessonCheckboxes.forEach(cb => cb.checked = true);
            chapterCheckboxes.forEach(cb => cb.checked = true);
        } else if (quizType === 'chapter_graded') {
            chapterCheckboxes.forEach(cb => {
                toggleChapterLessons(cb.getAttribute('data-chapter-id'), cb.checked, true);
            });
        }
        updateLessonFilter();
    }

    function updateLessonFilter() {
        document.querySelectorAll('.lesson-checkbox').forEach(cb => {
            var container = cb.closest('.lesson-checkbox-container');
            if (container) {
                if (cb.checked) container.classList.add('selected');
                else container.classList.remove('selected');
            }
        });

        var checkboxes = document.querySelectorAll('.lesson-checkbox:checked');
        var selectedLessonIds = Array.from(checkboxes).map(cb => cb.value);
        var label = document.getElementById('selectedLessonsLabel');

        if (selectedLessonIds.length === 0) {
            if (label) label.textContent = 'None selected';
        } else {
            if (label) {
                var selectedNames = Array.from(checkboxes).map(cb => cb.parentElement.querySelector('.lesson-text').textContent.split(' (')[0].trim());
                label.textContent = selectedNames.join(', ');
            }
        }

        updateSelectedQuestionsDisplay(selectedLessonIds);
    }

    function updateSelectedQuestionsDisplay(selectedLessonIds) {
        var container = document.getElementById('selectedQuestionsContainer');
        var section = document.getElementById('selectedQuestionsSection');

        if (selectedLessonIds.length === 0) {
            section.style.display = 'none';
            container.innerHTML = '';
            updateQuestionCounts();
            return;
        }

        section.style.display = 'block';
        var checkedQuestionIds = Array.from(document.querySelectorAll('#selectedQuestionsContainer input[name="questions[]"]:checked')).map(cb => cb.value);
        container.innerHTML = '';

        var questionsByLesson = {};
        selectedLessonIds.forEach(lessonId => {
            var lessonElement = document.querySelector('.lesson[data-lesson-id="' + lessonId + '"]');
            if (lessonElement) {
                var lessonTitle = lessonElement.querySelector('h4').textContent.trim();
                var chapterElement = lessonElement.closest('.chapter');
                var chapterTitle = chapterElement ? chapterElement.querySelector('h3').textContent.trim() : 'Chapter';

                questionsByLesson[lessonId] = {
                    title: lessonTitle,
                    chapterTitle: chapterTitle,
                    questions: []
                };
            }
        });

        document.querySelectorAll('.lesson .questions .question').forEach(question => {
            var input = question.querySelector('.source-question-cb');
            var lessonId = input ? input.getAttribute('data-lesson-id') : null;

            if (lessonId && selectedLessonIds.includes(lessonId) && questionsByLesson[lessonId]) {
                questionsByLesson[lessonId].questions.push({
                    id: question.getAttribute('data-question-id'),
                    text: question.getAttribute('data-question-text'),
                    type: question.querySelector('.question-type') ? question.querySelector('.question-type').textContent.trim() : 'mcq',
                    lessonTitle: questionsByLesson[lessonId].title
                });
            }
        });

        Object.keys(questionsByLesson).forEach(lessonId => {
            var lData = questionsByLesson[lessonId];
            if (lData.questions.length > 0) {
                var group = document.createElement('div');
                group.style.marginBottom = '15px';

                var head = document.createElement('div');
                head.style.cssText = 'display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;';
                head.innerHTML = `<strong style="color:var(--ink-800);">${escapeHtml(lData.chapterTitle)} - ${escapeHtml(lData.title)}</strong>`;
                group.appendChild(head);

                lData.questions.forEach(qData => {
                    var isChecked = checkedQuestionIds.includes(String(qData.id));
                    var qDiv = document.createElement('div');
                    qDiv.className = 'question' + (isChecked ? ' selected' : '');
                    qDiv.setAttribute('data-question-id', qData.id);
                    qDiv.setAttribute('data-question-text', qData.text);

                    qDiv.innerHTML = `
                        <input type="checkbox" name="questions[]" value="${qData.id}" data-lesson-id="${lessonId}" ${isChecked ? 'checked' : ''}>
                        <div class="question-content">
                            <div>${escapeHtml(qData.text)}</div>
                            <div class="question-meta">
                                <span class="question-type ${qData.type.toLowerCase()}">${qData.type}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openChoicesModal(${qData.id}); event.stopPropagation();">
                            <i class="fas fa-eye"></i> View
                        </button>
                    `;

                    // Entire card click selection
                    qDiv.addEventListener('click', function(e) {
                        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
                        var cb = qDiv.querySelector('input[type="checkbox"]');
                        cb.checked = !cb.checked;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    group.appendChild(qDiv);
                });
                container.appendChild(group);
            }
        });

        updateQuestionCounts();
    }

    function updateQuestionCounts() {
        var total = document.querySelectorAll('#selectedQuestionsContainer .question').length;
        document.getElementById('totalQuestionsCount').querySelector('.stat-number').textContent = total;
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var count = document.querySelectorAll('input[name="questions[]"]:checked').length;
        document.getElementById('selectedQuestionsCount').querySelector('.stat-number').textContent = count;
    }

    function filterQuestionsBySearch() {
        var query = document.getElementById('questionSearch').value.toLowerCase().trim();
        document.querySelectorAll('#selectedQuestionsContainer .question').forEach(q => {
            var text = q.getAttribute('data-question-text').toLowerCase();
            q.style.display = text.includes(query) ? 'flex' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        applyQuizTypeMode();

        document.addEventListener('change', function(e) {
            if (e.target.id === 'quizType') applyQuizTypeMode();
            if (e.target.name === 'questions[]') {
                var card = e.target.closest('.question');
                if (card) {
                    if (e.target.checked) card.classList.add('selected');
                    else card.classList.remove('selected');
                }
                updateSelectedCount();
            }
        });
    });

    function toggleChapterLessons(chapterId, checked, skipRefresh) {
        var chapterGroup = document.querySelector(`.chapter-group[data-chapter-id="${chapterId}"]`);
        if (chapterGroup) {
            chapterGroup.querySelectorAll('.lesson-checkbox').forEach(cb => cb.checked = checked);
        }
        if (!skipRefresh) updateLessonFilter();
    }

    function openChoicesModal(questionId) {
        var card = document.querySelector(`.question[data-question-id="${questionId}"]`);
        var text = card ? card.getAttribute('data-question-text') : 'Question Preview';
        document.getElementById('choicesQuestionText').textContent = text;

        var list = document.getElementById('choicesList');
        list.innerHTML = '';
        var choices = quizChoices[questionId] || [];

        if (choices.length === 0) {
            list.innerHTML = '<div class="quiz-choice-item">No options found.</div>';
        } else {
            choices.forEach(c => {
                var item = document.createElement('div');
                var isCorrect = parseInt(c.is_correct, 10) === 1;
                item.className = 'quiz-choice-item' + (isCorrect ? ' correct' : '');
                item.innerHTML = (isCorrect ? '<i class="fas fa-check-circle"></i>' : '<i class="far fa-circle"></i>') + ' <span>' + escapeHtml(c.choice_text) + '</span>';
                list.appendChild(item);
            });
        }
        openModal('choicesModal');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Deploy Quiz with SweetAlert2 Modal Feedback
    function deployQuiz(event) {
        event.preventDefault();
        const form = event.target;

        const selectedClasses = Array.from(form.querySelectorAll('input[name="class_ids[]"]:checked')).map(cb => cb.value);
        const selectedLessons = Array.from(form.querySelectorAll('input[name="lesson_ids[]"]:checked')).map(cb => cb.value);
        const selectedQuestions = Array.from(form.querySelectorAll('input[name="questions[]"]:checked')).map(q => q.value);

        if (selectedClasses.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Missing Section', text: 'Please select at least one section/class.' });
            return;
        }
        if (selectedLessons.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Missing Lesson', text: 'Please select at least one lesson.' });
            return;
        }
        if (selectedQuestions.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Missing Questions', text: 'Please select at least one question.' });
            return;
        }

        // new FormData(form) automatically extracts checked form inputs without duplicates
        const payload = new FormData(form);
        payload.append('action', 'deploy_quiz');

        fetch('quizzes.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: payload
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let codeListHtml = '<div style="margin-top:15px; text-align:left;">';
                data.deployments.forEach(d => {
                    codeListHtml += `
                        <div style="display:flex; justify-content:space-between; align-items:center; background:#f1f5f9; padding:8px 12px; margin-bottom:6px; border-radius:6px;">
                            <span><strong>${escapeHtml(d.class_name)}</strong></span>
                            <span style="font-family:monospace; font-weight:bold; color:#1d4ed8; background:#dbeafe; padding:2px 8px; border-radius:4px;">${escapeHtml(d.quiz_code)}</span>
                        </div>`;
                });
                codeListHtml += '</div>';

                Swal.fire({
                    icon: 'success',
                    title: 'Quiz Deployed Successfully!',
                    html: `<p>Your quiz has been published to student sections. Here are your access codes:</p>${codeListHtml}`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check"></i> Confirm & Finish',
                    cancelButtonText: '<i class="fas fa-copy"></i> Copy Primary Code',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#64748b'
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        navigator.clipboard.writeText(data.quiz_code);
                        Swal.fire('Copied!', 'Primary access code copied to clipboard.', 'info');
                    }
                    window.location.href = 'active_quizzes.php';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Deployment Failed', text: data.error || 'An error occurred.' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Server or network error encountered.' });
        });
    }
    </script>
</body>
</html>