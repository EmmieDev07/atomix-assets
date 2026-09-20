<?php
// API endpoint to fetch latest questions and choices for the logged-in teacher

require_once '../config/database.php';
require_once '../includes/auth_check.php';

$db = Database::getInstance()->getConnection();

// If admin, return all questions; otherwise require teacher auth and return teacher's questions
$role = $_SESSION['role'] ?? null;
if ($role === 'admin') {
    $questions = $db->query(
        "SELECT q.*, l.lesson_title, c.chapter_title, t.first_name, t.last_name, (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count
         FROM questions_master q
         LEFT JOIN lessons l ON q.lesson_id = l.lesson_id
         LEFT JOIN chapters c ON l.chapter_id = c.chapter_id
         LEFT JOIN teachers t ON q.created_by_teacher_id = t.teacher_id
         ORDER BY q.question_id DESC"
    )->fetchAll();
} else {
    checkTeacherAuth();
    $teacher_id = getTeacherId();
    $questions = $db->query(
        "SELECT q.*, l.lesson_title, c.chapter_title, (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count
         FROM questions_master q
         JOIN lessons l ON q.lesson_id = l.lesson_id
         JOIN chapters c ON l.chapter_id = c.chapter_id
         WHERE q.created_by_teacher_id = " . intval($teacher_id) . "
         ORDER BY q.question_id DESC"
    )->fetchAll();
}

$allChoices = [];
$choicesStmt = $db->query("SELECT * FROM quiz_choices ORDER BY choice_id");
while ($choice = $choicesStmt->fetch()) {
    $allChoices[$choice['question_id']][] = $choice;
}

header('Content-Type: application/json');
echo json_encode([
    'questions' => $questions,
    'choices' => $allChoices
]);
