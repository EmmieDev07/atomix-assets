<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr) {
    error_log("Export error: $errstr");
});

session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

try {
    checkTeacherAuth();
    
    $db = Database::getInstance()->getConnection();
    $teacher_id = $_SESSION['teacher_id'];

    // Get the class_id parameter
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

    if (!$class_id) {
        throw new Exception('Class ID is required');
    }

    // Get students with their scores and game progress - simplified approach
    $stmt = $db->prepare("
        SELECT s.student_id, s.first_name, s.last_name, s.gender, u.email, u.status,
            c.class_name, sy.label as school_year
        FROM (
            SELECT DISTINCT cs.student_id
            FROM class_students cs
            WHERE cs.class_id = ?
        ) unique_students
        JOIN students s ON unique_students.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN classes c ON c.class_id = ?
        LEFT JOIN school_year sy ON c.sy_id = sy.sy_id
        WHERE c.teacher_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$class_id, $class_id, $teacher_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        throw new Exception('No students found for this class');
    }

    // For each student, get their quiz and game stats
    $studentStats = [];
    foreach ($students as &$student) {
        $sid = $student['student_id'];
        
        // Get quiz stats
        $quizStmt = $db->prepare("
            SELECT COUNT(*) as total_quizzes, AVG(score) as avg_score 
            FROM student_quizzes 
            WHERE student_id = ? AND status = 'completed'
        ");
        $quizStmt->execute([$sid]);
        $quizData = $quizStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get game stats
        $gameStmt = $db->prepare("
            SELECT COUNT(*) as total_games, AVG(score) as avg_score 
            FROM game_progress 
            WHERE student_id = ?
        ");
        $gameStmt->execute([$sid]);
        $gameData = $gameStmt->fetch(PDO::FETCH_ASSOC);
        
        $student['total_quizzes_taken'] = $quizData['total_quizzes'] ?? 0;
        $student['average_quiz_score'] = $quizData['avg_score'] ?? 0;
        $student['total_games_played'] = $gameData['total_games'] ?? 0;
        $student['average_game_score'] = $gameData['avg_score'] ?? 0;
    }

    // Prepare CSV output
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add column headers
    fputcsv($output, [
        'First Name',
        'Last Name',
        'Email',
        'Gender',
        'Class Name',
        'School Year',
        'Status',
        'Total Quizzes Taken',
        'Average Quiz Score',
        'Total Games Played',
        'Average Game Score'
    ]);
    
    // Add data rows
    foreach ($students as $student) {
        fputcsv($output, [
            $student['first_name'] ?? '',
            $student['last_name'] ?? '',
            $student['email'] ?? '',
            $student['gender'] ?? 'N/A',
            $student['class_name'] ?? '',
            $student['school_year'] ?? 'N/A',
            $student['status'] ?? '',
            (int)($student['total_quizzes_taken'] ?? 0),
            round((float)($student['average_quiz_score'] ?? 0), 2),
            (int)($student['total_games_played'] ?? 0),
            round((float)($student['average_game_score'] ?? 0), 2)
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>
