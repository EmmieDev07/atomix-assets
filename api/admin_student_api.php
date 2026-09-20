<?php
/**
 * Admin Student API - Admin Side Management
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkAdminAuth();

$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    switch ($action) {
        case 'get_students':
            $stmt = $db->query("
                SELECT s.*, u.email, u.username, u.status, u.created_at, u.last_login,
                       cs.class_id, c.class_name, sy.label as school_year
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN class_students cs ON s.student_id = cs.student_id
                LEFT JOIN classes c ON cs.class_id = c.class_id
                LEFT JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
                ORDER BY s.last_name, s.first_name
            ");
            jsonResponse(true, 'Students retrieved', $stmt->fetchAll());
            break;

        case 'get_student':
            $studentId = intval($_GET['student_id'] ?? 0);
            if (!$studentId) {
                jsonResponse(false, 'Student ID is required');
            }

                $stmt = $db->prepare("
                    SELECT s.*, u.email, u.username, u.status,
                           cs.class_id, c.class_name, sy.label as school_year
                    FROM students s
                    JOIN users u ON s.user_id = u.user_id
                    LEFT JOIN class_students cs ON s.student_id = cs.student_id
                    LEFT JOIN classes c ON cs.class_id = c.class_id
                    LEFT JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1
                    WHERE s.student_id = ?
                    LIMIT 1
                ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();

            if ($student) {
                jsonResponse(true, 'Student found', $student);
            } else {
                jsonResponse(false, 'Student not found');
            }
            break;

        case 'update_student':
            $studentId = intval($_POST['student_id'] ?? 0);
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $classId = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
            $status = $_POST['status'] ?? 'active';

            // Validation
            if (!$studentId) {
                jsonResponse(false, 'Student ID is required');
            }

            if (empty($firstName) || empty($lastName) || empty($username)) {
                jsonResponse(false, 'All required fields must be filled');
            }

            if (strlen($firstName) > 50 || strlen($lastName) > 50) {
                jsonResponse(false, 'Names must be 50 characters or less');
            }

            if (strlen($username) > 50) {
                jsonResponse(false, 'Username must be 50 characters or less');
            }

            // Check if student exists
            $stmt = $db->prepare("SELECT user_id FROM students WHERE student_id = ?");
            $stmt->execute([$studentId]);
            $userId = $stmt->fetchColumn();

            if (!$userId) {
                jsonResponse(false, 'Student not found');
            }

            // Check for duplicate username (excluding current user)
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, 'Username is already in use');
            }

            // Start transaction
            $db->beginTransaction();

            // Update user table
            $stmt = $db->prepare("UPDATE users SET username = ?, status = ? WHERE user_id = ?");
            $stmt->execute([$username, $status, $userId]);

            // Update student table (students table does not store class_id)
            $stmt = $db->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE student_id = ?");
            $stmt->execute([$firstName, $lastName, $studentId]);

            // Update class assignment in class_students table
            // Remove existing assignments for this student
            $del = $db->prepare("DELETE FROM class_students WHERE student_id = ?");
            $del->execute([$studentId]);
            // If a class was provided, insert new assignment
            if (!empty($classId)) {
                $ins = $db->prepare("INSERT INTO class_students (class_id, student_id, joined_at) VALUES (?, ?, NOW())");
                $ins->execute([$classId, $studentId]);
            }

            $db->commit();
            jsonResponse(true, 'Student updated successfully');
            break;

        case 'toggle_status':
            $input  = json_decode(file_get_contents('php://input'), true);
            $userId = intval($input['user_id'] ?? 0);
            $action = $input['action'] ?? '';

            if (!$userId || !in_array($action, ['activate', 'deactivate'])) {
                jsonResponse(false, 'Invalid request: user_id and action (activate|deactivate) required');
            }

            $newStatus = $action === 'activate' ? 'active' : 'inactive';

            $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'student'");
            $stmt->execute([$newStatus, $userId]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(false, 'Student not found or no change made');
            }

            jsonResponse(true, "Student {$action}d successfully");
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
?>