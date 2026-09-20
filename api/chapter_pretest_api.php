<?php
/**
 * Chapter Pretest API
 *
 * Teacher POST actions (session-auth required):
 *   action=save_pretest         — create or update pretest settings for a chapter
 *   action=add_question         — add a question to a pretest
 *   action=import_questions_excel — import pretest questions from Excel/CSV file
 *   action=update_question      — update a pretest question
 *   action=delete_question      — delete a pretest question
 *   action=delete_pretest       — remove entire pretest for a chapter
 *   action=toggle_active        — toggle pretest is_active flag
 *
 * Student POST actions (student session-auth required):
 *   action=submit               — submit pretest answers, records result
 *
 * GET actions (student session-auth required):
 *   action=get_pretest&chapter_id=X  — get pretest questions (shuffled)
 *   action=get_status&chapter_id=X   — get pass/fail status for current student
 *
 * GET actions (teacher, session-auth required):
 *   action=get_results&pretest_id=X  — get all student results for a pretest
 *
 * GET actions (NO AUTH - public, for WebGL Unity game):
 *   action=get_by_chapter&chapter_id=X  — get pretest questions in Unity format
 *       Returns: [{questionText, answers[4], correctAnswerIndex}, ...]
 *       Empty array if no active pretest for chapter
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ─────────────────────────────────────────────────────────────────────────────
// GET handlers
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action     = $_GET['action'] ?? '';
    $chapter_id = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : 0;

    // Student: fetch pretest questions for a chapter
    if ($action === 'get_pretest') {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            exit;
        }
        if (!$chapter_id) {
            echo json_encode(['success' => false, 'message' => 'chapter_id required.']);
            exit;
        }
        $stmt = $db->prepare("
            SELECT cp.pretest_id, cp.title, cp.passing_score
            FROM chapter_pretests cp
            WHERE cp.chapter_id = ? AND cp.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$chapter_id]);
        $pretest = $stmt->fetch();
        if (!$pretest) {
            echo json_encode(['success' => false, 'message' => 'No active pretest for this chapter.']);
            exit;
        }
        $qStmt = $db->prepare("
            SELECT pq_id, question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index
            FROM chapter_pretest_questions
            WHERE pretest_id = ?
            ORDER BY question_order, pq_id
        ");
        $qStmt->execute([$pretest['pretest_id']]);
        $questions = $qStmt->fetchAll();
        echo json_encode([
            'success'       => true,
            'pretest_id'    => (int)$pretest['pretest_id'],
            'title'         => $pretest['title'],
            'passing_score' => (int)$pretest['passing_score'],
            'questions'     => $questions,
        ]);
        exit;
    }

    // Unity: fetch pretest questions for a chapter (NO AUTH - public, for WebGL game)
    // Returns Unity-compatible format: [{questionText, answers[4], correctAnswerIndex}, ...]
    if ($action === 'get_by_chapter') {
        if (!$chapter_id) {
            echo json_encode([]);
            exit;
        }
        $stmt = $db->prepare("
            SELECT cp.pretest_id
            FROM chapter_pretests cp
            WHERE cp.chapter_id = ? AND cp.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$chapter_id]);
        $pretest = $stmt->fetch();
        if (!$pretest) {
            echo json_encode([]);
            exit;
        }
        $qStmt = $db->prepare("
            SELECT question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index
            FROM chapter_pretest_questions
            WHERE pretest_id = ?
            ORDER BY question_order, pq_id
        ");
        $qStmt->execute([$pretest['pretest_id']]);
        $dbQuestions = $qStmt->fetchAll();
        
        // Transform to Unity format: {questionText, answers[4], correctAnswerIndex}
        $unityQuestions = [];
        foreach ($dbQuestions as $q) {
            $unityQuestions[] = [
                'questionText'      => $q['question_text'],
                'answers'           => [
                    (string)$q['answer_0'],
                    (string)$q['answer_1'],
                    (string)$q['answer_2'],
                    (string)$q['answer_3'],
                ],
                'correctAnswerIndex' => (int)$q['correct_answer_index'],
            ];
        }
        
        echo json_encode($unityQuestions);
        exit;
    }

    // Student or teacher: get pretest pass status for a student
    if ($action === 'get_status') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            exit;
        }
        if (!$chapter_id) {
            echo json_encode(['success' => false, 'message' => 'chapter_id required.']);
            exit;
        }

        // Resolve student_id
        $student_id = null;
        if (($_SESSION['role'] ?? '') === 'student') {
            $s = $db->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
            $s->execute([$_SESSION['user_id']]);
            $row = $s->fetch();
            $student_id = $row ? (int)$row['student_id'] : null;
        }
        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT spr.passed, spr.score, spr.total_questions, spr.attempted_at,
                   cp.passing_score, cp.is_active, cp.pretest_id, cp.title
            FROM chapter_pretests cp
            LEFT JOIN student_pretest_results spr
                ON spr.pretest_id = cp.pretest_id AND spr.student_id = ?
            WHERE cp.chapter_id = ?
            LIMIT 1
        ");
        $stmt->execute([$student_id, $chapter_id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode([
                'success'       => true,
                'has_pretest'   => false,
                'passed'        => true, // no pretest = open access
            ]);
            exit;
        }
        echo json_encode([
            'success'       => true,
            'has_pretest'   => true,
            'is_active'     => (bool)$row['is_active'],
            'pretest_id'    => (int)$row['pretest_id'],
            'title'         => $row['title'],
            'passing_score' => (int)$row['passing_score'],
            'attempted'     => !is_null($row['score']),
            'passed'        => (bool)($row['passed'] ?? false),
            'score'         => $row['score'],
            'total'         => $row['total_questions'],
            'attempted_at'  => $row['attempted_at'],
        ]);
        exit;
    }

    // Teacher: get all student results for a pretest
    if ($action === 'get_results') {
        checkTeacherAuth();
        $pretest_id = isset($_GET['pretest_id']) ? (int)$_GET['pretest_id'] : 0;
        if (!$pretest_id) {
            echo json_encode(['success' => false, 'message' => 'pretest_id required.']);
            exit;
        }
        $stmt = $db->prepare("
            SELECT s.student_id, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                   spr.score, spr.total_questions, spr.passed, spr.attempted_at
            FROM student_pretest_results spr
            JOIN students s ON spr.student_id = s.student_id
            WHERE spr.pretest_id = ?
            ORDER BY spr.attempted_at DESC
        ");
        $stmt->execute([$pretest_id]);
        echo json_encode(['success' => true, 'results' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST handlers
// ─────────────────────────────────────────────────────────────────────────────
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ── Student: submit pretest answers ─────────────────────────────────────────
if ($action === 'submit') {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $pretest_id = isset($_POST['pretest_id']) ? (int)$_POST['pretest_id'] : 0;
    $answersRaw = $_POST['answers'] ?? '';

    if (!$pretest_id || !$answersRaw) {
        echo json_encode(['success' => false, 'message' => 'pretest_id and answers are required.']);
        exit;
    }

    $answers = json_decode($answersRaw, true);
    if (!is_array($answers)) {
        echo json_encode(['success' => false, 'message' => 'Invalid answers format.']);
        exit;
    }

    // Resolve student_id
    $sStmt = $db->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
    $sStmt->execute([$_SESSION['user_id']]);
    $sRow = $sStmt->fetch();
    if (!$sRow) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }
    $student_id = (int)$sRow['student_id'];

    // Fetch pretest + questions
    $ptStmt = $db->prepare("SELECT pretest_id, chapter_id, passing_score FROM chapter_pretests WHERE pretest_id = ? AND is_active = 1");
    $ptStmt->execute([$pretest_id]);
    $pretest = $ptStmt->fetch();
    if (!$pretest) {
        echo json_encode(['success' => false, 'message' => 'Pretest not found or inactive.']);
        exit;
    }

    $qStmt = $db->prepare("SELECT pq_id, correct_answer_index FROM chapter_pretest_questions WHERE pretest_id = ?");
    $qStmt->execute([$pretest_id]);
    $questions = $qStmt->fetchAll();
    if (empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'No questions in this pretest.']);
        exit;
    }

    // Grade
    $score = 0;
    foreach ($questions as $q) {
        $submitted = $answers[(string)$q['pq_id']] ?? null;
        if (!is_null($submitted) && (int)$submitted === (int)$q['correct_answer_index']) {
            $score++;
        }
    }
    $total   = count($questions);
    $pct     = $total > 0 ? round($score / $total * 100) : 0;
    $passed  = $pct >= (int)$pretest['passing_score'] ? 1 : 0;

    // Upsert result (allow retakes — always updates)
    $upsert = $db->prepare("
        INSERT INTO student_pretest_results (student_id, pretest_id, chapter_id, score, total_questions, passed, attempted_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), total_questions = VALUES(total_questions),
            passed = VALUES(passed), attempted_at = NOW()
    ");
    $upsert->execute([$student_id, $pretest_id, (int)$pretest['chapter_id'], $score, $total, $passed]);

    echo json_encode([
        'success'       => true,
        'score'         => $score,
        'total'         => $total,
        'percentage'    => $pct,
        'passing_score' => (int)$pretest['passing_score'],
        'passed'        => (bool)$passed,
    ]);
    exit;
}

// ── All remaining actions require teacher auth ───────────────────────────────
checkTeacherAuth();
requireTeacherPermission($db, 'can_manage_questions');
$teacher_id = getTeacherId();

// ── Save (create/update) pretest settings for a chapter ─────────────────────
if ($action === 'save_pretest') {
    $chapter_id    = isset($_POST['chapter_id'])    ? (int)$_POST['chapter_id']    : 0;
    $title         = trim($_POST['title']          ?? 'Chapter Pretest');
    $passing_score = isset($_POST['passing_score']) ? (int)$_POST['passing_score'] : 70;

    if (!$chapter_id) {
        echo json_encode(['success' => false, 'message' => 'chapter_id required.']);
        exit;
    }
    if ($title === '') $title = 'Chapter Pretest';
    if ($passing_score < 1 || $passing_score > 100) $passing_score = 70;

    // Verify chapter exists
    $chk = $db->prepare("SELECT chapter_id FROM chapters WHERE chapter_id = ?");
    $chk->execute([$chapter_id]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chapter not found.']);
        exit;
    }

    $existing = $db->prepare("SELECT pretest_id FROM chapter_pretests WHERE chapter_id = ?");
    $existing->execute([$chapter_id]);
    $row = $existing->fetch();

    if ($row) {
        $db->prepare("UPDATE chapter_pretests SET title=?, passing_score=?, created_by_teacher_id=? WHERE pretest_id=?")
           ->execute([$title, $passing_score, $teacher_id, $row['pretest_id']]);
        echo json_encode(['success' => true, 'pretest_id' => (int)$row['pretest_id']]);
    } else {
        $ins = $db->prepare("INSERT INTO chapter_pretests (chapter_id, created_by_teacher_id, title, passing_score) VALUES (?,?,?,?)");
        $ins->execute([$chapter_id, $teacher_id, $title, $passing_score]);
        echo json_encode(['success' => true, 'pretest_id' => (int)$db->lastInsertId()]);
    }
    exit;
}

// ── Toggle active ────────────────────────────────────────────────────────────
if ($action === 'toggle_active') {
    $pretest_id = isset($_POST['pretest_id']) ? (int)$_POST['pretest_id'] : 0;
    if (!$pretest_id) {
        echo json_encode(['success' => false, 'message' => 'pretest_id required.']);
        exit;
    }
    $db->prepare("UPDATE chapter_pretests SET is_active = 1 - is_active WHERE pretest_id = ? AND created_by_teacher_id = ?")
       ->execute([$pretest_id, $teacher_id]);
    $row = $db->prepare("SELECT is_active FROM chapter_pretests WHERE pretest_id = ?")->execute([$pretest_id]);
    $res = $db->prepare("SELECT is_active FROM chapter_pretests WHERE pretest_id = ?");
    $res->execute([$pretest_id]);
    $state = $res->fetchColumn();
    echo json_encode(['success' => true, 'is_active' => (int)$state]);
    exit;
}

// ── Delete entire pretest ────────────────────────────────────────────────────
if ($action === 'delete_pretest') {
    $pretest_id = isset($_POST['pretest_id']) ? (int)$_POST['pretest_id'] : 0;
    if (!$pretest_id) {
        echo json_encode(['success' => false, 'message' => 'pretest_id required.']);
        exit;
    }
    $db->prepare("DELETE FROM chapter_pretests WHERE pretest_id = ? AND created_by_teacher_id = ?")
       ->execute([$pretest_id, $teacher_id]);
    echo json_encode(['success' => true]);
    exit;
}

// ── Add question ─────────────────────────────────────────────────────────────
if ($action === 'add_question') {
    $pretest_id    = isset($_POST['pretest_id'])           ? (int)$_POST['pretest_id']           : 0;
    $question_text = trim($_POST['question_text']         ?? '');
    $answer_0      = trim($_POST['answer_0']              ?? '');
    $answer_1      = trim($_POST['answer_1']              ?? '');
    $answer_2      = trim($_POST['answer_2']              ?? '');
    $answer_3      = trim($_POST['answer_3']              ?? '');
    $correct       = isset($_POST['correct_answer_index']) ? (int)$_POST['correct_answer_index'] : 0;

    if (!$pretest_id || !$question_text || !$answer_0 || !$answer_1 || !$answer_2 || !$answer_3) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if ($correct < 0 || $correct > 3) {
        echo json_encode(['success' => false, 'message' => 'correct_answer_index must be 0–3.']);
        exit;
    }

    $normalizedAnswers = array_map(function ($value) {
        return strtolower(trim((string)$value));
    }, [$answer_0, $answer_1, $answer_2, $answer_3]);
    if (count(array_unique($normalizedAnswers)) < 4) {
        echo json_encode(['success' => false, 'message' => 'Answers must be unique (no repeated choices).']);
        exit;
    }

    // Verify teacher owns this pretest
    $chk = $db->prepare("SELECT pretest_id FROM chapter_pretests WHERE pretest_id = ? AND created_by_teacher_id = ?");
    $chk->execute([$pretest_id, $teacher_id]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Pretest not found.']);
        exit;
    }
    // Determine next order
    $ord = $db->prepare("SELECT COALESCE(MAX(question_order),0)+1 FROM chapter_pretest_questions WHERE pretest_id = ?");
    $ord->execute([$pretest_id]);
    $order = (int)$ord->fetchColumn();

    $ins = $db->prepare("
        INSERT INTO chapter_pretest_questions
            (pretest_id, question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index, question_order)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $ins->execute([$pretest_id, $question_text, $answer_0, $answer_1, $answer_2, $answer_3, $correct, $order]);
    echo json_encode(['success' => true, 'pq_id' => (int)$db->lastInsertId()]);
    exit;
}

// ── Import questions from Excel/CSV ─────────────────────────────────────────
if ($action === 'import_questions_excel') {
    $pretest_id = isset($_POST['pretest_id']) ? (int)$_POST['pretest_id'] : 0;

    if (!$pretest_id) {
        echo json_encode(['success' => false, 'message' => 'pretest_id required.']);
        exit;
    }

    if (!isset($_FILES['question_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
        exit;
    }

    if ($_FILES['question_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
        ];
        $errorCode = $_FILES['question_file']['error'];
        $errorText = $uploadErrors[$errorCode] ?? 'Unknown upload error.';
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $errorText]);
        exit;
    }

    $checkPretest = $db->prepare("SELECT pretest_id FROM chapter_pretests WHERE pretest_id = ? AND created_by_teacher_id = ?");
    $checkPretest->execute([$pretest_id, $teacher_id]);
    if (!$checkPretest->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Pretest not found.']);
        exit;
    }

    $tmpFile = $_FILES['question_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['question_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['xlsx', 'xls', 'csv'];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Upload .xlsx, .xls, or .csv.']);
        exit;
    }

    if (($ext === 'xlsx' || $ext === 'xls') && !class_exists('ZipArchive')) {
        echo json_encode([
            'success' => false,
            'message' => 'PHP ZipArchive extension is required for Excel files. Enable it or upload CSV instead.'
        ]);
        exit;
    }

    $rows = [];
    if ($ext === 'xlsx' || $ext === 'xls') {
        require_once '../vendor/autoload.php';
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to read Excel file.']);
            exit;
        }
    } else {
        if (($handle = fopen($tmpFile, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to read CSV file.']);
            exit;
        }
    }

    if (count($rows) < 2) {
        echo json_encode(['success' => false, 'message' => 'The file has no question rows.']);
        exit;
    }

    $header = array_map(function ($v) {
        return strtolower(trim((string)$v));
    }, $rows[0]);

    $headerAliases = [
        'question_text' => ['question text', 'question', 'question_text'],
        'answer_0' => ['answer a', 'choice a', 'option a', 'answer_0'],
        'answer_1' => ['answer b', 'choice b', 'option b', 'answer_1'],
        'answer_2' => ['answer c', 'choice c', 'option c', 'answer_2'],
        'answer_3' => ['answer d', 'choice d', 'option d', 'answer_3'],
        'correct' => ['correct answer', 'correct option', 'correct', 'correct_answer_index'],
    ];

    $colMap = [];
    foreach ($headerAliases as $key => $aliases) {
        $colMap[$key] = null;
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $header, true);
            if ($idx !== false) {
                $colMap[$key] = $idx;
                break;
            }
        }
    }

    $requiredCols = ['question_text', 'answer_0', 'answer_1', 'answer_2', 'answer_3', 'correct'];
    foreach ($requiredCols as $col) {
        if (!is_int($colMap[$col])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid template headers. Required columns: Question Text, Answer A, Answer B, Answer C, Answer D, Correct Answer.'
            ]);
            exit;
        }
    }

    $toInsert = [];
    $errors = [];

    for ($i = 1; $i < count($rows); $i++) {
        $raw = $rows[$i];
        $lineNo = $i + 1;

        $qText = trim((string)($raw[$colMap['question_text']] ?? ''));
        $a0 = trim((string)($raw[$colMap['answer_0']] ?? ''));
        $a1 = trim((string)($raw[$colMap['answer_1']] ?? ''));
        $a2 = trim((string)($raw[$colMap['answer_2']] ?? ''));
        $a3 = trim((string)($raw[$colMap['answer_3']] ?? ''));
        $correctRaw = trim((string)($raw[$colMap['correct']] ?? ''));

        // Skip fully empty rows.
        if ($qText === '' && $a0 === '' && $a1 === '' && $a2 === '' && $a3 === '' && $correctRaw === '') {
            continue;
        }

        if ($qText === '' || $a0 === '' || $a1 === '' || $a2 === '' || $a3 === '') {
            $errors[] = 'Row ' . $lineNo . ': Question and all 4 answers are required.';
            continue;
        }

        $normalizedAnswers = array_map(function ($value) {
            return strtolower(trim((string)$value));
        }, [$a0, $a1, $a2, $a3]);
        if (count(array_unique($normalizedAnswers)) < 4) {
            $errors[] = 'Row ' . $lineNo . ': Answers must be unique (no repeated choices).';
            continue;
        }

        $correctIndex = null;
        $normalized = strtolower($correctRaw);

        if (in_array($normalized, ['a', 'b', 'c', 'd'], true)) {
            $correctIndex = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3][$normalized];
        } elseif (is_numeric($correctRaw)) {
            $num = (int)$correctRaw;
            if ($num >= 0 && $num <= 3) {
                $correctIndex = $num;
            } elseif ($num >= 1 && $num <= 4) {
                $correctIndex = $num - 1;
            }
        } else {
            $answers = [$a0, $a1, $a2, $a3];
            foreach ($answers as $idx => $answerText) {
                if (strcasecmp($answerText, $correctRaw) === 0) {
                    $correctIndex = $idx;
                    break;
                }
            }
        }

        if ($correctIndex === null) {
            $errors[] = 'Row ' . $lineNo . ': Correct Answer must be A-D, 0-3, 1-4, or match one answer text.';
            continue;
        }

        $toInsert[] = [
            'question_text' => $qText,
            'answer_0' => $a0,
            'answer_1' => $a1,
            'answer_2' => $a2,
            'answer_3' => $a3,
            'correct' => $correctIndex,
        ];
    }

    if (empty($toInsert)) {
        echo json_encode([
            'success' => false,
            'message' => 'No valid questions found in file.',
            'errors' => $errors,
        ]);
        exit;
    }

    try {
        $db->beginTransaction();

        $ord = $db->prepare("SELECT COALESCE(MAX(question_order),0) FROM chapter_pretest_questions WHERE pretest_id = ?");
        $ord->execute([$pretest_id]);
        $nextOrder = (int)$ord->fetchColumn();

        $ins = $db->prepare("INSERT INTO chapter_pretest_questions (pretest_id, question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index, question_order) VALUES (?,?,?,?,?,?,?,?)");

        foreach ($toInsert as $row) {
            $nextOrder++;
            $ins->execute([
                $pretest_id,
                $row['question_text'],
                $row['answer_0'],
                $row['answer_1'],
                $row['answer_2'],
                $row['answer_3'],
                $row['correct'],
                $nextOrder,
            ]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Imported ' . count($toInsert) . ' question(s).',
            'imported_count' => count($toInsert),
            'skipped_count' => count($errors),
            'errors' => $errors,
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Import failed. Please try again.']);
    }
    exit;
}

// ── Update question ──────────────────────────────────────────────────────────
if ($action === 'update_question') {
    $pq_id         = isset($_POST['pq_id'])                ? (int)$_POST['pq_id']                : 0;
    $question_text = trim($_POST['question_text']         ?? '');
    $answer_0      = trim($_POST['answer_0']              ?? '');
    $answer_1      = trim($_POST['answer_1']              ?? '');
    $answer_2      = trim($_POST['answer_2']              ?? '');
    $answer_3      = trim($_POST['answer_3']              ?? '');
    $correct       = isset($_POST['correct_answer_index']) ? (int)$_POST['correct_answer_index'] : 0;

    if (!$pq_id || !$question_text || !$answer_0 || !$answer_1 || !$answer_2 || !$answer_3) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if ($correct < 0 || $correct > 3) {
        echo json_encode(['success' => false, 'message' => 'correct_answer_index must be 0–3.']);
        exit;
    }

    $normalizedAnswers = array_map(function ($value) {
        return strtolower(trim((string)$value));
    }, [$answer_0, $answer_1, $answer_2, $answer_3]);
    if (count(array_unique($normalizedAnswers)) < 4) {
        echo json_encode(['success' => false, 'message' => 'Answers must be unique (no repeated choices).']);
        exit;
    }

    // Verify ownership via join
    $chk = $db->prepare("
        SELECT cpq.pq_id FROM chapter_pretest_questions cpq
        JOIN chapter_pretests cp ON cpq.pretest_id = cp.pretest_id
        WHERE cpq.pq_id = ? AND cp.created_by_teacher_id = ?
    ");
    $chk->execute([$pq_id, $teacher_id]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Question not found.']);
        exit;
    }
    $db->prepare("
        UPDATE chapter_pretest_questions
        SET question_text=?, answer_0=?, answer_1=?, answer_2=?, answer_3=?, correct_answer_index=?
        WHERE pq_id=?
    ")->execute([$question_text, $answer_0, $answer_1, $answer_2, $answer_3, $correct, $pq_id]);
    echo json_encode(['success' => true]);
    exit;
}

// ── Delete question ──────────────────────────────────────────────────────────
if ($action === 'delete_question') {
    $pq_id = isset($_POST['pq_id']) ? (int)$_POST['pq_id'] : 0;
    if (!$pq_id) {
        echo json_encode(['success' => false, 'message' => 'pq_id required.']);
        exit;
    }
    $chk = $db->prepare("
        SELECT cpq.pq_id FROM chapter_pretest_questions cpq
        JOIN chapter_pretests cp ON cpq.pretest_id = cp.pretest_id
        WHERE cpq.pq_id = ? AND cp.created_by_teacher_id = ?
    ");
    $chk->execute([$pq_id, $teacher_id]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Question not found.']);
        exit;
    }
    $db->prepare("DELETE FROM chapter_pretest_questions WHERE pq_id = ?")->execute([$pq_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
