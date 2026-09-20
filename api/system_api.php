<?php
/**
 * System Info API — admin only
 */
require_once '../includes/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

checkAdminAuth();

$db     = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'info';

try {
    switch ($action) {

        case 'info':
            // PHP / server / DB meta
            $dbVersion = $db->query('SELECT VERSION()')->fetchColumn();

            $sizeStmt = $db->query("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ");
            $dbSizeMb = $sizeStmt->fetchColumn() ?: 0;

            echo json_encode([
                'success'           => true,
                'php_version'       => PHP_VERSION,
                'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'db_version'        => $dbVersion,
                'db_size_mb'        => (float) $dbSizeMb,
            ]);
            break;

        case 'table_counts':
            $tables = ['users','teachers','students','classes','class_students',
                       'chapters','questions','quizzes','school_year'];
            $counts = [];
            foreach ($tables as $table) {
                try {
                    $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                } catch (PDOException $e) {
                    $counts[$table] = null;
                }
            }
            echo json_encode(['success' => true, 'counts' => $counts]);
            break;

        case 'recent_logins':
            $limit  = min((int) ($_GET['limit'] ?? 10), 50);
            $rows   = $db->prepare("
                SELECT username, role, last_login
                FROM users
                WHERE last_login IS NOT NULL
                ORDER BY last_login DESC
                LIMIT ?
            ");
            $rows->execute([$limit]);
            echo json_encode(['success' => true, 'logins' => $rows->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
