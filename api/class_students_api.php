<?php
// class_students_api.php: Returns students for a given class_id
require_once '../config/database.php';
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

// Enforce authentication — teachers, admins only
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromJWT();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'students' => []]);
    exit;
}

if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid class_id', 'students' => []]);
    exit;
}
$class_id = (int)$_GET['class_id'];

error_log("API called with class_id: $class_id");

try {
    $db = Database::getInstance()->getConnection();

    // First check if the class exists and belongs to a teacher
    $checkStmt = $db->prepare("SELECT class_id FROM classes WHERE class_id = ?");
    $checkStmt->execute([$class_id]);
    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Class not found', 'students' => []]);
        exit;
    }

    $stmt = $db->prepare("SELECT s.student_id, s.first_name, s.last_name, s.gender, u.email, u.status,
        COUNT(DISTINCT sq.quiz_id) as total_quizzes_taken,
        COALESCE(AVG(sq.score), 0) as average_score
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN class_students cs ON cs.student_id = s.student_id
    LEFT JOIN student_quizzes sq ON s.student_id = sq.student_id AND sq.status IN ('completed', 'passed')
    WHERE cs.class_id = ?
    GROUP BY s.student_id, s.first_name, s.last_name, s.gender, u.email, u.status
    ORDER BY s.last_name, s.first_name");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();

    // Get detailed quiz scores for all students in this class
    $quizScoresStmt = $db->prepare("SELECT sq.student_id, sq.quiz_id, sq.score, sq.submitted_at,
        q.quiz_title, q.total_score, q.start_time, q.end_time
    FROM student_quizzes sq
    JOIN quizzes q ON sq.quiz_id = q.quiz_id
    JOIN class_students cs ON sq.student_id = cs.student_id
    WHERE cs.class_id = ? AND sq.status IN ('completed', 'passed')
    ORDER BY sq.student_id, sq.submitted_at DESC");
    $quizScoresStmt->execute([$class_id]);
    $quizScoresData = $quizScoresStmt->fetchAll();

    // Get final exam results for students assigned to this class.
    $examResultsStmt = $db->prepare("SELECT er.student_id, e.exam_id, e.exam_title,
        er.score, er.total_questions, er.submitted_at
    FROM exam_results er
    JOIN exams e ON e.exam_id = er.exam_id
    JOIN exam_classes ec ON ec.exam_id = e.exam_id
    WHERE ec.class_id = ?
    ORDER BY er.student_id, er.submitted_at DESC");
    $examResultsStmt->execute([$class_id]);
    $examResultsData = $examResultsStmt->fetchAll();

    // Get game progress data for all students in this class
    $gameProgressStmt = $db->prepare("SELECT gp.student_id, gp.stage_id, gp.completed_levels, gp.score, gp.status, gp.last_updated,
        s.stage_name, s.stage_order, s.science_concept,
        c.chapter_title,
        s.game_id,
        spr.score AS pretest_score, spr.total_questions AS pretest_total_questions,
        ar.score AS assessment_score, ar.total_questions AS assessment_total_questions
    FROM game_progress gp
    JOIN stages s ON gp.stage_id = s.stage_id
    LEFT JOIN chapters c ON s.chapter_id = c.chapter_id
    LEFT JOIN chapter_pretests cp ON cp.chapter_id = s.chapter_id
    LEFT JOIN student_pretest_results spr ON spr.pretest_id = cp.pretest_id AND spr.student_id = gp.student_id
    LEFT JOIN student_assessment_results ar ON ar.student_id = gp.student_id
        AND ar.chapter_id = s.chapter_id
        AND ar.stage_id = gp.stage_id
        AND ar.assessment_type = 'stage'
    JOIN class_students cs ON gp.student_id = cs.student_id
    WHERE cs.class_id = ?
    ORDER BY gp.student_id, s.game_id, s.stage_order");
    $gameProgressStmt->execute([$class_id]);
    $gameProgressData = $gameProgressStmt->fetchAll();

    // Organize quiz scores by student_id
    $quizScoresByStudent = [];
    foreach ($quizScoresData as $score) {
        $studentId = $score['student_id'];
        if (!isset($quizScoresByStudent[$studentId])) {
            $quizScoresByStudent[$studentId] = [];
        }
        $quizScoresByStudent[$studentId][] = [
            'quiz_id' => $score['quiz_id'],
            'quiz_title' => $score['quiz_title'],
            'score' => $score['score'],
            'total_score' => $score['total_score'],
            'submitted_at' => $score['submitted_at'],
            'start_time' => $score['start_time'],
            'end_time' => $score['end_time']
        ];
    }

    // Organize exam results by student_id.
    $examResultsByStudent = [];
    foreach ($examResultsData as $result) {
        $studentId = $result['student_id'];
        if (!isset($examResultsByStudent[$studentId])) {
            $examResultsByStudent[$studentId] = [];
        }
        $examResultsByStudent[$studentId][] = [
            'exam_id' => $result['exam_id'],
            'exam_title' => $result['exam_title'],
            'score' => $result['score'],
            'total_questions' => $result['total_questions'],
            'submitted_at' => $result['submitted_at']
        ];
    }

    // Organize game progress by student
    $gameProgressByStudent = [];
    foreach ($gameProgressData as $progress) {
        $studentId = $progress['student_id'];
        if (!isset($gameProgressByStudent[$studentId])) {
            $gameProgressByStudent[$studentId] = [];
        }
        $gameProgressByStudent[$studentId][] = $progress;
    }

    // Add game progress and quiz scores to each student
    foreach ($students as &$student) {
        $studentId = $student['student_id'];
        $student['game_progress'] = $gameProgressByStudent[$studentId] ?? [];
        $student['quiz_scores'] = $quizScoresByStudent[$studentId] ?? [];
        $student['exam_results'] = $examResultsByStudent[$studentId] ?? [];

        // Calculate game statistics
        $totalGames = count(array_unique(array_column($student['game_progress'], 'game_id')));
        $completedStages = count(array_filter($student['game_progress'], function($p) { return $p['status'] === 'completed'; }));
        $totalScore = array_sum(array_column($student['game_progress'], 'score'));
        $avgGameScore = $totalGames > 0 ? $totalScore / $totalGames : 0;

        $student['game_stats'] = [
            'total_games' => $totalGames,
            'completed_stages' => $completedStages,
            'total_score' => $totalScore,
            'average_score' => $avgGameScore
        ];

        // Calculate quiz statistics
        $totalQuizzesTaken = (int)$student['total_quizzes_taken'];
        $avgQuizScore = $totalQuizzesTaken > 0 ? (float)$student['average_score'] : 0;

        $student['quiz_stats'] = [
            'total_quizzes_taken' => $totalQuizzesTaken,
            'average_score' => $avgQuizScore
        ];
    }

    echo json_encode(['success' => true, 'students' => $students, 'count' => count($students)]);
} catch (Exception $e) {
    error_log('Error in class_students_api.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage(), 'students' => []]);
}
