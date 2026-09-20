<?php
/**
 * School Year API - Admin Side
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
        case 'create_sy':
            $label = trim($_POST['label'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

            // Validation
            if (empty($label)) {
                jsonResponse(false, 'School year label is required');
            }

            if (strlen($label) > 20) {
                jsonResponse(false, 'School year label must be 20 characters or less');
            }

            // Check for valid format (e.g., 2025-2026)
            if (!preg_match('/^\d{4}-\d{4}$/', $label)) {
                jsonResponse(false, 'School year label should be in format YYYY-YYYY (e.g., 2025-2026)');
            }

            // Check for duplicate labels
            $stmt = $db->prepare("SELECT COUNT(*) FROM school_year WHERE label = ?");
            $stmt->execute([$label]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, 'School year label already exists');
            }

            // If creating as active, deactivate all other school years
            if ($is_active == 1) {
                // First, get the IDs of school years that will be deactivated
                $stmt = $db->prepare("SELECT sy_id FROM school_year WHERE is_active = 1");
                $stmt->execute();
                $deactivatedSyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Deactivate all other school years
                $stmt = $db->prepare("UPDATE school_year SET is_active = 0 WHERE is_active = 1");
                $stmt->execute();

                // Deactivate students for the school years that were just deactivated
                if (!empty($deactivatedSyIds)) {
                    $placeholders = str_repeat('?,', count($deactivatedSyIds) - 1) . '?';
                    $stmt = $db->prepare("UPDATE users SET status = 'inactive'
                        WHERE user_id IN (
                            SELECT DISTINCT s.user_id
                            FROM students s
                            JOIN class_students cs ON s.student_id = cs.student_id
                            JOIN classes c ON cs.class_id = c.class_id
                            WHERE c.sy_id IN ($placeholders)
                        )");
                    $stmt->execute($deactivatedSyIds);
                }
            }

            $stmt = $db->prepare("INSERT INTO school_year (label, is_active) VALUES (?, ?)");
            $stmt->execute([$label, $is_active]);
            jsonResponse(true, 'School year created successfully', ['sy_id' => $db->lastInsertId()]);
            break;
        case 'get_school_years':
            // Admins should receive all records (including inactive) for management; other roles get only active years
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $stmt = $db->query("SELECT * FROM school_year ORDER BY sy_id DESC");
            } else {
                $stmt = $db->query("SELECT * FROM school_year WHERE is_active = 1 ORDER BY sy_id DESC");
            }
            jsonResponse(true, 'School years retrieved', $stmt->fetchAll());
            break;
        case 'delete_sy':
            $id = intval($_POST['sy_id'] ?? 0);

            if (!$id) {
                jsonResponse(false, 'School year ID is required');
            }

            // Check if record exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM school_year WHERE sy_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                jsonResponse(false, 'School year not found');
            }

            // Check if school year is being used in other tables (optional - depends on your business logic)
            // You might want to check quizzes, classes, etc. that reference this school year

            $stmt = $db->prepare("DELETE FROM school_year WHERE sy_id = ?");
            $stmt->execute([$id]);

            // Set students in associated classes to inactive when school year is deleted
            $stmt = $db->prepare("UPDATE users SET status = 'inactive'
                WHERE user_id IN (
                    SELECT DISTINCT s.user_id
                    FROM students s
                    JOIN class_students cs ON s.student_id = cs.student_id
                    JOIN classes c ON cs.class_id = c.class_id
                    WHERE c.sy_id = ?
                )");
            $stmt->execute([$id]);

            jsonResponse(true, 'School year deleted successfully');
            break;
        case 'get_sy':
            $id = intval($_GET['sy_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM school_year WHERE sy_id = ?");
            $stmt->execute([$id]);
            $sy = $stmt->fetch();
            if ($sy) jsonResponse(true, 'School year found', $sy);
            else jsonResponse(false, 'School year not found');
            break;
        case 'update_sy':
            $id = intval($_POST['sy_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

            // Validation
            if (!$id) {
                jsonResponse(false, 'School year ID is required');
            }

            if (empty($label)) {
                jsonResponse(false, 'School year label is required');
            }

            if (strlen($label) > 20) {
                jsonResponse(false, 'School year label must be 20 characters or less');
            }

            // Check for valid format (e.g., 2025-2026)
            if (!preg_match('/^\d{4}-\d{4}$/', $label)) {
                jsonResponse(false, 'School year label should be in format YYYY-YYYY (e.g., 2025-2026)');
            }

            // Check for duplicate labels (excluding current record)
            $stmt = $db->prepare("SELECT COUNT(*) FROM school_year WHERE label = ? AND sy_id != ?");
            $stmt->execute([$label, $id]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, 'School year label already exists');
            }

            // Check if record exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM school_year WHERE sy_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                jsonResponse(false, 'School year not found');
            }

            // If activating, deactivate all other school years
            if ($is_active == 1) {
                // First, get the IDs of school years that will be deactivated
                $stmt = $db->prepare("SELECT sy_id FROM school_year WHERE is_active = 1 AND sy_id != ?");
                $stmt->execute([$id]);
                $deactivatedSyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Deactivate all other school years
                $stmt = $db->prepare("UPDATE school_year SET is_active = 0 WHERE is_active = 1 AND sy_id != ?");
                $stmt->execute([$id]);

                // Deactivate students for the school years that were just deactivated
                if (!empty($deactivatedSyIds)) {
                    $placeholders = str_repeat('?,', count($deactivatedSyIds) - 1) . '?';
                    $stmt = $db->prepare("UPDATE users SET status = 'inactive'
                        WHERE user_id IN (
                            SELECT DISTINCT s.user_id
                            FROM students s
                            JOIN class_students cs ON s.student_id = cs.student_id
                            JOIN classes c ON cs.class_id = c.class_id
                            WHERE c.sy_id IN ($placeholders)
                        )");
                    $stmt->execute($deactivatedSyIds);
                }
            }

            // If deactivating, ensure at least one school year remains active
            if ($is_active == 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM school_year WHERE is_active = 1 AND sy_id != ?");
                $stmt->execute([$id]);
                $otherActiveCount = $stmt->fetchColumn();
                if ($otherActiveCount == 0) {
                    jsonResponse(false, 'Cannot deactivate the only active school year. Please activate another school year first.');
                }
            }

            $stmt = $db->prepare("UPDATE school_year SET label = ?, is_active = ? WHERE sy_id = ?");
            $stmt->execute([$label, $is_active, $id]);

            // Handle student status based on school year activation/deactivation
            if ($is_active == 0) {
                // If deactivating this school year, set students in associated classes to inactive
                $stmt = $db->prepare("UPDATE users SET status = 'inactive'
                    WHERE user_id IN (
                        SELECT DISTINCT s.user_id
                        FROM students s
                        JOIN class_students cs ON s.student_id = cs.student_id
                        JOIN classes c ON cs.class_id = c.class_id
                        WHERE c.sy_id = ?
                    )");
                $stmt->execute([$id]);
            } elseif ($is_active == 1) {
                // If activating this school year, set students in associated classes to active
                $stmt = $db->prepare("UPDATE users SET status = 'active'
                    WHERE user_id IN (
                        SELECT DISTINCT s.user_id
                        FROM students s
                        JOIN class_students cs ON s.student_id = cs.student_id
                        JOIN classes c ON cs.class_id = c.class_id
                        WHERE c.sy_id = ?
                    )");
                $stmt->execute([$id]);
            }

            jsonResponse(true, 'School year updated successfully');
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
