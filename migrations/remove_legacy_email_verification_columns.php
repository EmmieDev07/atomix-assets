<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$columns = ['email_verification_token_hash', 'email_verification_expires_at'];

try {
    foreach ($columns as $column) {
        $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() > 0) {
            $db->exec("ALTER TABLE `users` DROP COLUMN `{$column}`");
            echo "Removed {$column}." . PHP_EOL;
        } else {
            echo "{$column} already removed." . PHP_EOL;
        }
    }
} catch (PDOException $e) {
    echo 'Migration failed: ' . $e->getMessage() . PHP_EOL;
}
