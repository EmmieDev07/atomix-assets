<?php
/**
 * Migration: Create teacher_permissions table
 * Run this script once from browser or CLI to set up teacher RBAC.
 *
 * Permissions:
 *   can_manage_questions  - questions.php, question_bank.php
 *   can_manage_quizzes    - quizzes.php, active_quizzes.php, quiz_takers.php
 *   can_view_reports      - reports.php, game_progress.php
 *   can_manage_classes    - classes.php, class_students.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$messages = [];

try {
    // Create teacher_permissions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `teacher_permissions` (
            `id`          INT(11) NOT NULL AUTO_INCREMENT,
            `teacher_id`  INT(11) NOT NULL,
            `permission`  VARCHAR(100) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_teacher_perm` (`teacher_id`, `permission`),
            CONSTRAINT `fk_tp_teacher` FOREIGN KEY (`teacher_id`)
                REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $messages[] = '✔ Table teacher_permissions created (or already exists).';

    // Define all available permissions
    $allPermissions = [
        'can_manage_questions',
        'can_manage_quizzes',
        'can_view_reports',
        'can_manage_classes',
    ];

    // Seed all existing teachers with all permissions (default = full access)
    $teachers = $db->query("SELECT teacher_id FROM teachers")->fetchAll(PDO::FETCH_COLUMN);

    $insert = $db->prepare("INSERT IGNORE INTO teacher_permissions (teacher_id, permission) VALUES (?, ?)");

    foreach ($teachers as $teacherId) {
        foreach ($allPermissions as $perm) {
            $insert->execute([$teacherId, $perm]);
        }
    }

    // Count what is actually in the table now
    $actualCount = $db->query("SELECT COUNT(*) FROM teacher_permissions")->fetchColumn();
    $messages[] = "✔ teacher_permissions table now has {$actualCount} row(s) for " . count($teachers) . " teacher(s).";
    $messages[] = '✔ Migration complete.';

} catch (PDOException $e) {
    $messages[] = '✘ Error: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration: teacher_permissions</title>
    <style>
        body { font-family: monospace; padding: 2rem; background: #f0fdf4; }
        p { padding: 0.5rem 1rem; border-radius: 4px; }
        p.ok  { background: #dcfce7; color: #166534; }
        p.err { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<h2>Migration: teacher_permissions</h2>
<?php foreach ($messages as $msg): ?>
    <p class="<?php echo str_starts_with($msg, '✘') ? 'err' : 'ok'; ?>"><?php echo $msg; ?></p>
<?php endforeach; ?>
</body>
</html>
