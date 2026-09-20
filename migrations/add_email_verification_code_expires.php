<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$messages = [];

try {
    $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verification_code_expires_at'");
    $check->execute();
    if ((int) $check->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `email_verification_code_expires_at` DATETIME NULL DEFAULT NULL AFTER `email_verification_code_hash`");
        $messages[] = "Added column email_verification_code_expires_at.";
    } else {
        $messages[] = "Column email_verification_code_expires_at already exists.";
    }
} catch (PDOException $e) {
    $messages[] = 'Migration failed: ' . $e->getMessage();
}

foreach ($messages as $m) {
    echo $m . PHP_EOL;
}

?>
