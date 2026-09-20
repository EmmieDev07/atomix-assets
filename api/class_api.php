<?php
/**
 * Class API Handler
 * Handles CRUD operations for classes
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Restore session from JWT if needed, then enforce teacher/admin role
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromJWT();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$role = $_SESSION['role'] ?? '';
// Enforce RBAC for teachers: class management requires can_manage_classes
if ($role === 'teacher') {
    $db = Database::getInstance()->getConnection();
    if (!hasTeacherPermission($db, 'can_manage_classes')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: missing can_manage_classes permission']);
        exit;
    }
} else {
    $db = Database::getInstance()->getConnection();
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function classNameExistsInSchoolYear($db, $className, $schoolYearId, $excludeClassId = null) {
    $sql = "SELECT class_id FROM classes WHERE sy_id = ? AND LOWER(TRIM(class_name)) = LOWER(TRIM(?))";
    $params = [$schoolYearId, $className];

    if ($excludeClassId !== null) {
        $sql .= " AND class_id <> ?";
        $params[] = $excludeClassId;
    }

    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

try {
    switch ($action) {
        case 'create_class':
            if ($role !== 'admin') {
                http_response_code(403);
                jsonResponse(false, 'Only admins can create classes');
            }

            $name = trim($_POST['class_name'] ?? '');
            $teacher_id = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? intval($_POST['teacher_id']) : null;

            $activeSchoolYearStmt = $db->query("SELECT sy_id, label FROM school_year WHERE is_active = 1 ORDER BY sy_id DESC LIMIT 1");
            $activeSchoolYear = $activeSchoolYearStmt->fetch();
            $sy_id = $activeSchoolYear ? (int) $activeSchoolYear['sy_id'] : 0;

            if (empty($name) || !$sy_id) {
                jsonResponse(false, 'An active school year is required before creating a class');
            }

            if (!$teacher_id) {
                jsonResponse(false, 'Assigned teacher is required');
            }

            if (classNameExistsInSchoolYear($db, $name, $sy_id)) {
                jsonResponse(false, 'Class name already exists for this school year');
            }

            $teacherStmt = $db->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ? LIMIT 1");
            $teacherStmt->execute([$teacher_id]);
            if (!$teacherStmt->fetch()) {
                jsonResponse(false, 'Selected teacher was not found');
            }

            $stmt = $db->prepare("INSERT INTO classes (class_name, sy_id, teacher_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $sy_id, $teacher_id]);
            jsonResponse(true, 'Class created successfully', ['class_id' => $db->lastInsertId()]);
            break;
        case 'get_classes':
            // If the requester is a teacher, only return their classes. Admins get all classes.
            if ($role === 'teacher') {
                $teacher_id = $_SESSION['teacher_id'] ?? 0;
                $stmt = $db->prepare("SELECT c.*, sy.label AS school_year, t.first_name, t.last_name FROM classes c JOIN school_year sy ON c.sy_id = sy.sy_id AND sy.is_active = 1 JOIN teachers t ON c.teacher_id = t.teacher_id WHERE c.teacher_id = ? ORDER BY c.class_id DESC");
                $stmt->execute([$teacher_id]);
            } else {
                $stmt = $db->query("SELECT c.*, sy.label AS school_year, t.first_name, t.last_name FROM classes c JOIN school_year sy ON c.sy_id = sy.sy_id LEFT JOIN teachers t ON c.teacher_id = t.teacher_id ORDER BY c.class_id DESC");
            }
            jsonResponse(true, 'Classes retrieved', $stmt->fetchAll());
            break;
        case 'delete_class':
            if ($role !== 'admin') {
                http_response_code(403);
                jsonResponse(false, 'Only admins can delete classes');
            }

            $id = intval($_POST['class_id'] ?? 0);
            if (!$id) jsonResponse(false, 'Class ID required');
            $stmt = $db->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->execute([$id]);
            jsonResponse(true, 'Class deleted successfully');
            break;
        case 'get_class':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM classes WHERE class_id = ?");
            $stmt->execute([$id]);
            $class = $stmt->fetch();
            if ($class) jsonResponse(true, 'Class found', $class);
            else jsonResponse(false, 'Class not found');
            break;
        case 'update_class':
        case 'edit_class': // alias for update from JS
            if ($role !== 'admin') {
                http_response_code(403);
                jsonResponse(false, 'Only admins can update classes');
            }

            $id = intval($_POST['class_id'] ?? 0);
            $name = trim($_POST['class_name'] ?? '');
            $sy_id = intval($_POST['sy_id'] ?? 0);
            if (!$id || empty($name) || !$sy_id) {
                jsonResponse(false, 'All fields are required');
            }

            $teacher_id = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? intval($_POST['teacher_id']) : null;
            if (!$teacher_id) {
                jsonResponse(false, 'Assigned teacher is required');
            }

            if (classNameExistsInSchoolYear($db, $name, $sy_id, $id)) {
                jsonResponse(false, 'Class name already exists for this school year');
            }

            $teacherStmt = $db->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ? LIMIT 1");
            $teacherStmt->execute([$teacher_id]);
            if (!$teacherStmt->fetch()) {
                jsonResponse(false, 'Selected teacher was not found');
            }

            $stmt = $db->prepare("UPDATE classes SET class_name = ?, sy_id = ?, teacher_id = ? WHERE class_id = ?");
            $stmt->execute([$name, $sy_id, $teacher_id, $id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(false, 'Class not found or no permission to update it');
            }
            jsonResponse(true, 'Class updated successfully');
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
