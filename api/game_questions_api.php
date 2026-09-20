<?php
/**
 * Game Questions API
 *
 * Teacher CRUD (POST, session-authenticated):
 *   action=add    — insert a new game question
 *   action=update — update an existing game question
 *   action=delete — delete a game question
 *
 * Unity GET endpoint (no session required, read-only):
 *   GET ?action=get_by_lesson&lesson_id=X
 *   GET ?action=get_all        (all questions, grouped by lesson)
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();

// ── GET requests (Unity fetches) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action    = $_GET['action'] ?? '';
    $lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : null;

    if ($action === 'get_by_lesson' && $lesson_id) {
        $stmt = $db->prepare("
            SELECT question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index
            FROM game_questions
            WHERE lesson_id = ?
            ORDER BY game_question_id
        ");
        $stmt->execute([$lesson_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Shape matches Unity's Question class exactly
        $result = array_map(fn($r) => [
            'questionText'       => $r['question_text'],
            'answers'            => [$r['answer_0'], $r['answer_1'], $r['answer_2'], $r['answer_3']],
            'correctAnswerIndex' => (int)$r['correct_answer_index'],
        ], $rows);

        echo json_encode($result);
        exit;
    }

    if ($action === 'get_all') {
        $stmt = $db->query("
            SELECT gq.game_question_id, gq.lesson_id, gq.question_text,
                   gq.answer_0, gq.answer_1, gq.answer_2, gq.answer_3,
                   gq.correct_answer_index,
                   l.lesson_title, c.chapter_title
            FROM game_questions gq
            JOIN lessons l ON gq.lesson_id = l.lesson_id
            JOIN chapters c ON l.chapter_id = c.chapter_id
            ORDER BY c.chapter_order, l.lesson_order, gq.game_question_id
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['chapter_title']][$r['lesson_title']][] = [
                'questionText'       => $r['question_text'],
                'answers'            => [$r['answer_0'], $r['answer_1'], $r['answer_2'], $r['answer_3']],
                'correctAnswerIndex' => (int)$r['correct_answer_index'],
            ];
        }

        echo json_encode($grouped);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── POST requests (teacher CRUD — require session) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify teacher session
require_once '../includes/auth_check.php';
checkTeacherAuth(); // redirects if not authenticated

$action    = trim($_POST['action'] ?? '');
$teacher_id = getTeacherId();

// ── ADD ──────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $lesson_id    = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $question_text = trim($_POST['question_text'] ?? '');
    $answer_0     = trim($_POST['answer_0'] ?? '');
    $answer_1     = trim($_POST['answer_1'] ?? '');
    $answer_2     = trim($_POST['answer_2'] ?? '');
    $answer_3     = trim($_POST['answer_3'] ?? '');
    $correct      = isset($_POST['correct_answer_index']) ? (int)$_POST['correct_answer_index'] : 0;

    if (!$lesson_id || !$question_text || !$answer_0 || !$answer_1 || !$answer_2 || !$answer_3) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if ($correct < 0 || $correct > 3) {
        echo json_encode(['success' => false, 'message' => 'correct_answer_index must be 0–3.']);
        exit;
    }

    // Verify teacher owns this lesson (via chapter → teacher classes, or simply allow if lesson exists)
    $lessonCheck = $db->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ?");
    $lessonCheck->execute([$lesson_id]);
    if (!$lessonCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found.']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO game_questions
            (lesson_id, question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index, created_by_teacher_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$lesson_id, $question_text, $answer_0, $answer_1, $answer_2, $answer_3, $correct, $teacher_id]);

    echo json_encode(['success' => true, 'game_question_id' => (int)$db->lastInsertId()]);
    exit;
}

// ── UPDATE ───────────────────────────────────────────────────────────────────
if ($action === 'update') {
    $gqid          = isset($_POST['game_question_id']) ? (int)$_POST['game_question_id'] : 0;
    $question_text = trim($_POST['question_text'] ?? '');
    $answer_0     = trim($_POST['answer_0'] ?? '');
    $answer_1     = trim($_POST['answer_1'] ?? '');
    $answer_2     = trim($_POST['answer_2'] ?? '');
    $answer_3     = trim($_POST['answer_3'] ?? '');
    $correct      = isset($_POST['correct_answer_index']) ? (int)$_POST['correct_answer_index'] : 0;

    if (!$gqid || !$question_text || !$answer_0 || !$answer_1 || !$answer_2 || !$answer_3) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if ($correct < 0 || $correct > 3) {
        echo json_encode(['success' => false, 'message' => 'correct_answer_index must be 0–3.']);
        exit;
    }

    // Only allow the teacher who created the question to update it
    $stmt = $db->prepare("
        UPDATE game_questions
        SET question_text = ?, answer_0 = ?, answer_1 = ?, answer_2 = ?,
            answer_3 = ?, correct_answer_index = ?
        WHERE game_question_id = ? AND created_by_teacher_id = ?
    ");
    $stmt->execute([$question_text, $answer_0, $answer_1, $answer_2, $answer_3, $correct, $gqid, $teacher_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Question not found or not authorized.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── IMPORT (bulk insert from quiz question bank) ──────────────────────────────
if ($action === 'import') {
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $raw       = $_POST['questions'] ?? '';

    if (!$lesson_id || !$raw) {
        echo json_encode(['success' => false, 'message' => 'lesson_id and questions are required.']);
        exit;
    }

    // Verify lesson exists
    $lessonCheck = $db->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ?");
    $lessonCheck->execute([$lesson_id]);
    if (!$lessonCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found.']);
        exit;
    }

    $questions = json_decode($raw, true);
    if (!is_array($questions) || empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'No questions provided.']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO game_questions
            (lesson_id, question_text, answer_0, answer_1, answer_2, answer_3, correct_answer_index, created_by_teacher_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $imported = 0;
    foreach ($questions as $q) {
        $qt  = trim($q['question_text']  ?? '');
        $a0  = trim($q['answer_0']       ?? '');
        $a1  = trim($q['answer_1']       ?? '');
        $a2  = trim($q['answer_2']       ?? '');
        $a3  = trim($q['answer_3']       ?? '');
        $idx = isset($q['correct_answer_index']) ? (int)$q['correct_answer_index'] : 0;

        if (!$qt || !$a0 || !$a1 || !$a2 || !$a3) continue;
        if ($idx < 0 || $idx > 3) $idx = 0;

        $stmt->execute([$lesson_id, $qt, $a0, $a1, $a2, $a3, $idx, $teacher_id]);
        $imported++;
    }

    echo json_encode(['success' => true, 'imported' => $imported]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $gqid = isset($_POST['game_question_id']) ? (int)$_POST['game_question_id'] : 0;

    if (!$gqid) {
        echo json_encode(['success' => false, 'message' => 'game_question_id is required.']);
        exit;
    }

    $stmt = $db->prepare("
        DELETE FROM game_questions
        WHERE game_question_id = ? AND created_by_teacher_id = ?
    ");
    $stmt->execute([$gqid, $teacher_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Question not found or not authorized.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
