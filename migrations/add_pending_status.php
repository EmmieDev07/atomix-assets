<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$messages = [];

try {
    // Check current definition
    $check = $db->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'");
    $check->execute();
    $current = $check->fetchColumn();

    if (strpos($current, "'pending'") === false) {
        $db->exec("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active'");
        $messages[] = "Updated users.status to include 'pending'.";
    } else {
        $messages[] = "users.status already includes 'pending'.";
    }
} catch (PDOException $e) {
    $messages[] = 'Migration failed: ' . $e->getMessage();
}

foreach ($messages as $m) {
    echo $m . PHP_EOL;
}

?>
