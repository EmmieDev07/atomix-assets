<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$messages = [];

try {
    $columns = [
        'email_verified_at' => "DATETIME NULL DEFAULT NULL AFTER `status`",
        'email_verification_token_hash' => "CHAR(64) NULL DEFAULT NULL AFTER `email_verified_at`",
        'email_verification_expires_at' => "DATETIME NULL DEFAULT NULL AFTER `email_verification_token_hash`",
    ];

    foreach ($columns as $columnName => $definition) {
        $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $check->execute([$columnName]);
        if ((int) $check->fetchColumn() === 0) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `{$columnName}` {$definition}");
            $messages[] = "Added column {$columnName}.";
        } else {
            $messages[] = "Column {$columnName} already exists.";
        }
    }

    $db->exec("UPDATE users SET email_verified_at = COALESCE(email_verified_at, created_at) WHERE email_verified_at IS NULL");
    $messages[] = 'Backfilled existing users as verified.';
    $messages[] = 'Email verification migration complete.';
} catch (PDOException $e) {
    $messages[] = 'Migration failed: ' . $e->getMessage();
}

foreach ($messages as $message) {
    echo $message . PHP_EOL;
}
