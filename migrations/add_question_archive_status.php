<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions_master' AND COLUMN_NAME = 'is_archived'");
    $check->execute();

    if (!(int)$check->fetchColumn()) {
        $db->exec("ALTER TABLE `questions_master` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `visibility`");
        echo "Added questions_master.is_archived." . PHP_EOL;
    } else {
        echo "questions_master.is_archived already exists." . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'Migration failed: ' . $e->getMessage() . PHP_EOL;
}
