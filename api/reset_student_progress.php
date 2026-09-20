<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkTeacherAuth();

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$student_id  = isset($_POST['student_id'])  ? (int) $_POST['student_id']  : 0;
$chapter_id  = isset($_POST['chapter_id'])  ? (int) $_POST['chapter_id']  : 0;
$teacher_id  = (int) $_SESSION['teacher_id'];

if ($student_id <= 0 || $chapter_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing student_id or chapter_id']);
    exit;
}

// Verify the student belongs to one of this teacher's classes (security gate).
$authCheck = $db->prepare(
    "SELECT 1 FROM class_students cs
     JOIN classes c ON cs.class_id = c.class_id
     WHERE cs.student_id = ? AND c.teacher_id = ? LIMIT 1"
);
$authCheck->execute([$student_id, $teacher_id]);
if (!$authCheck->fetch()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not authorized to reset this student']);
    exit;
}

try {
    $db->beginTransaction();

    // Remove all game progress rows for stages in this chapter.
    $db->prepare(
        "DELETE gp FROM game_progress gp
         JOIN stages s ON s.stage_id = gp.stage_id
         WHERE gp.student_id = ? AND s.chapter_id = ?"
    )->execute([$student_id, $chapter_id]);

    // Remove stage assessment results for this chapter.
    $db->prepare(
        "DELETE FROM student_assessment_results
         WHERE student_id = ? AND chapter_id = ?"
    )->execute([$student_id, $chapter_id]);

    // Remove pretest record so the student must retake it.
    $db->prepare(
        "DELETE FROM student_pretest_results
         WHERE student_id = ? AND chapter_id = ?"
    )->execute([$student_id, $chapter_id]);

    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Progress reset successfully']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log('reset_student_progress error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
}
