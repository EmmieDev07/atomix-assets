<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $teacher_id = $input['teacher_id'] ?? null;
    $archive_reason = trim((string)($input['archive_reason'] ?? ''));
    $reasonLength = function_exists('mb_strlen') ? mb_strlen($archive_reason) : strlen($archive_reason);

    if (!$teacher_id) {
        echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
        exit;
    }

    if ($archive_reason === '') {
        echo json_encode(['success' => false, 'message' => 'Archive reason is required']);
        exit;
    }

    if ($reasonLength > 500) {
        echo json_encode(['success' => false, 'message' => 'Archive reason must not exceed 500 characters']);
        exit;
    }

    try {
        // Find matching teacher user account
        $stmt = $db->prepare("SELECT user_id FROM teachers WHERE teacher_id = ? LIMIT 1");
        $stmt->execute([$teacher_id]);
        $user_id = $stmt->fetchColumn();

        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found']);
            exit;
        }

        $db->beginTransaction();

        // Archive teacher by inactivating the linked user account.
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'teacher'");
        $stmt->execute([$user_id]);

        // Persist archive reason for auditing.
        $logStmt = $db->prepare("\n            INSERT INTO teacher_archive_logs (teacher_id, teacher_user_id, admin_user_id, archive_reason)\n            VALUES (?, ?, ?, ?)\n        ");
        $logStmt->execute([
            $teacher_id,
            $user_id,
            $_SESSION['user_id'] ?? null,
            $archive_reason
        ]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Teacher archived successfully']);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Failed to archive teacher: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
}
?>