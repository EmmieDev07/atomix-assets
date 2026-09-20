<?php
/**
 * Migration: allow users.email to be NULL for students created without an email.
 * Run once from browser or CLI.
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$messages = [];

try {
    $column = $db->query("SHOW COLUMNS FROM users LIKE 'email'")->fetch();

    if (!$column) {
        throw new RuntimeException('Column users.email was not found.');
    }

    if (stripos((string) ($column['Null'] ?? ''), 'YES') !== false) {
        $messages[] = '✔ users.email already allows NULL.';
    } else {
        $db->exec("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
        $messages[] = '✔ Updated users.email to allow NULL values.';
    }

    $messages[] = '✔ Migration complete.';
} catch (Throwable $e) {
    $messages[] = '✘ Error: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration: users.email nullable</title>
    <style>
        body { font-family: monospace; padding: 2rem; background: #f0fdf4; }
        p { padding: 0.5rem 1rem; border-radius: 4px; }
        p.ok { background: #dcfce7; color: #166534; }
        p.err { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<h2>Migration: users.email nullable</h2>
<?php foreach ($messages as $msg): ?>
    <p class="<?php echo str_starts_with($msg, '✘') ? 'err' : 'ok'; ?>"><?php echo $msg; ?></p>
<?php endforeach; ?>
</body>
</html>
