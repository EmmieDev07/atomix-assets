<?php
/**
 * Migration: Create teacher_archive_logs table
 * Run this script once from browser or CLI to set up teacher archive auditing.
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$messages = [];

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `teacher_archive_logs` (
            `log_id` INT(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` INT(11) NULL,
            `teacher_user_id` INT(11) NULL,
            `admin_user_id` INT(11) NULL,
            `archive_reason` VARCHAR(500) NOT NULL,
            `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`log_id`),
            KEY `idx_tal_teacher_id` (`teacher_id`),
            KEY `idx_tal_teacher_user_id` (`teacher_user_id`),
            KEY `idx_tal_admin_user_id` (`admin_user_id`),
            KEY `idx_tal_archived_at` (`archived_at`),
            CONSTRAINT `fk_tal_teacher` FOREIGN KEY (`teacher_id`)
                REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_tal_teacher_user` FOREIGN KEY (`teacher_user_id`)
                REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_tal_admin_user` FOREIGN KEY (`admin_user_id`)
                REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $messages[] = 'Table teacher_archive_logs created (or already exists).';
    $messages[] = 'Migration complete.';
} catch (PDOException $e) {
    $messages[] = 'Error: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration: teacher_archive_logs</title>
    <style>
        body { font-family: monospace; padding: 2rem; background: #f8fafc; }
        p { padding: 0.5rem 1rem; border-radius: 4px; }
        p.ok  { background: #dcfce7; color: #166534; }
        p.err { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<h2>Migration: teacher_archive_logs</h2>
<?php foreach ($messages as $msg): ?>
    <p class="<?php echo str_starts_with($msg, 'Error:') ? 'err' : 'ok'; ?>"><?php echo $msg; ?></p>
<?php endforeach; ?>
</body>
</html>
