<?php
// Debug endpoint to check what the questions_list_api.php returns
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();
$db = Database::getInstance()->getConnection();
$teacher_id = getTeacherId();

$questions = $db->query("
    SELECT q.*, l.lesson_title, c.chapter_title,
           (SELECT COUNT(*) FROM quiz_choices WHERE question_id = q.question_id) as choice_count
    FROM questions_master q 
    JOIN lessons l ON q.lesson_id = l.lesson_id 
    JOIN chapters c ON l.chapter_id = c.chapter_id 
    WHERE q.created_by_teacher_id = $teacher_id
    ORDER BY q.question_id DESC
")->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'teacher_id' => $teacher_id,
    'count' => count($questions),
    'questions' => $questions
]);
