<?php
/**
 * Dashboard API — stats + recent students/teachers for admin dashboard
 */
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkAdminAuth();

header('Content-Type: application/json');

$db   = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {
        case 'stats':
            $stats = [
                'total_students'      => (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
                'total_classes'       => (int)$db->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
                'total_assessments'   => (int)$db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(),
                'total_questions'     => (int)$db->query("SELECT COUNT(*) FROM questions_master")->fetchColumn(),
                'total_teachers'      => (int)$db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
                'active_school_years' => (int)$db->query("SELECT COUNT(*) FROM school_year WHERE is_active = 1")->fetchColumn(),
            ];
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'recent_students':
            $rows = $db->query("
                SELECT s.first_name, s.last_name, u.created_at, c.class_name
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN class_students cs ON s.student_id = cs.student_id
                LEFT JOIN classes c ON cs.class_id = c.class_id
                ORDER BY u.created_at DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'students' => $rows]);
            break;

        case 'recent_teachers':
            $rows = $db->query("
                SELECT t.first_name, t.last_name, u.created_at, u.email
                FROM teachers t
                JOIN users u ON t.user_id = u.user_id
                ORDER BY u.created_at DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'teachers' => $rows]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
