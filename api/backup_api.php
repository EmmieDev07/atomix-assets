<?php
/**
 * Backup API — list, create, download, restore backups
 */
require_once '../config/database.php';
require_once '../includes/auth_check.php';
checkAdminAuth();

$backupDir = '../backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Download (streams file, no JSON) ─────────────────────────────────────────
if ($action === 'download') {
    $filename = basename($_GET['file'] ?? '');
    $filepath = $backupDir . $filename;
    if (!$filename || !file_exists($filepath) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

header('Content-Type: application/json');

function listBackups(string $dir): array {
    $backups = [];
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') continue;
        $fp = $dir . $file;
        $backups[] = [
            'filename' => $file,
            'size'     => filesize($fp),
            'modified' => date('Y-m-d H:i:s', filemtime($fp)),
        ];
    }
    usort($backups, fn($a, $b) => strcmp($b['modified'], $a['modified']));
    return $backups;
}

try {
    switch ($action) {

        case 'list':
            echo json_encode(['success' => true, 'backups' => listBackups($backupDir)]);
            break;

        case 'create':
            $db = Database::getInstance()->getConnection();
            $timestamp = date('Ymd_His');
            $filename  = "atomix_db_backup_{$timestamp}.sql";
            $filepath  = $backupDir . $filename;

            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            $sql  = "-- Atomix Database Backup\n";
            $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n";

            foreach ($tables as $table) {
                $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "-- Table `$table`\n" . $create['Create Table'] . ";\n\n";

                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    $cols    = array_keys($rows[0]);
                    $values  = [];
                    foreach ($rows as $row) {
                        $rv = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
                        $values[] = '(' . implode(', ', $rv) . ')';
                    }
                    $sql .= "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES\n"
                          . implode(",\n", $values) . ";\n\n";
                }
            }
            $sql .= "COMMIT;\n";

            if (!file_put_contents($filepath, $sql)) {
                throw new Exception('Could not write backup file');
            }
            echo json_encode([
                'success'  => true,
                'message'  => 'Backup created: ' . $filename,
                'filename' => $filename,
                'size'     => filesize($filepath),
                'modified' => date('Y-m-d H:i:s'),
            ]);
            break;

        case 'restore':
            $input    = json_decode(file_get_contents('php://input'), true) ?? [];
            $filename = basename($input['filename'] ?? '');
            $filepath = $backupDir . $filename;

            if (!$filename || !file_exists($filepath) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                throw new Exception('Backup file not found');
            }

            $sql = file_get_contents($filepath);
            if ($sql === false) throw new Exception('Could not read backup file');

            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();
            try {
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt) $db->exec($stmt);
                }
                $db->commit();
            } catch (PDOException $e) {
                $db->rollBack();
                throw new Exception('Restore failed: ' . $e->getMessage());
            }
            echo json_encode(['success' => true, 'message' => 'Database restored from ' . $filename]);
            break;

        case 'delete':
            $input    = json_decode(file_get_contents('php://input'), true) ?? [];
            $filename = basename($input['filename'] ?? '');
            $filepath = $backupDir . $filename;

            if (!$filename || !file_exists($filepath) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                throw new Exception('Backup file not found');
            }
            unlink($filepath);
            echo json_encode(['success' => true, 'message' => 'Backup deleted']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
