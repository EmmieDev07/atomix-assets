<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();

// Show which database we're connected to
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();
echo "Connected to database: " . $dbName . "\n";

// Check if game_questions exists
$stmt = $db->query("SHOW TABLES LIKE 'game_questions'");
$row = $stmt->fetch();
if ($row) {
    echo "TABLE EXISTS: game_questions\n";
    // Show columns
    $cols = $db->query("DESCRIBE game_questions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  - " . $c['Field'] . " (" . $c['Type'] . ")\n";
    }
} else {
    echo "TABLE NOT FOUND - creating now...\n";
    $db->exec("
    CREATE TABLE `game_questions` (
      `game_question_id` int(11) NOT NULL AUTO_INCREMENT,
      `lesson_id` int(11) NOT NULL,
      `question_text` text NOT NULL,
      `answer_0` varchar(255) NOT NULL DEFAULT '',
      `answer_1` varchar(255) NOT NULL DEFAULT '',
      `answer_2` varchar(255) NOT NULL DEFAULT '',
      `answer_3` varchar(255) NOT NULL DEFAULT '',
      `correct_answer_index` tinyint(1) NOT NULL DEFAULT 0,
      `created_by_teacher_id` int(11) NOT NULL,
      PRIMARY KEY (`game_question_id`),
      KEY `lesson_id` (`lesson_id`),
      KEY `teacher_id` (`created_by_teacher_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created successfully.\n";
}
