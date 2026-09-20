<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$has = (bool) $db->query("SHOW COLUMNS FROM users LIKE 'archive_reason'")->fetch();
echo 'has_archive_reason=' . ($has ? 'yes' : 'no') . PHP_EOL;

if ($has) {
    $sql = "SELECT u.user_id, u.username, u.status, u.archive_reason, u.archived_at
            FROM users u
            WHERE u.role = 'teacher' AND u.status = 'inactive'
            ORDER BY u.user_id DESC
            LIMIT 10";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $reason = trim((string) ($row['archive_reason'] ?? ''));
        if ($reason === '') {
            $reason = 'EMPTY';
        }
        $archivedAt = $row['archived_at'] ?? 'NULL';
        echo $row['user_id'] . '|' . $row['username'] . '|' . $reason . '|' . $archivedAt . PHP_EOL;
    }
}
