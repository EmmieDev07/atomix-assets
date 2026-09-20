<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

// Check questions_master table structure
echo "Questions_master table structure:" . PHP_EOL;
$columns = $db->query("DESCRIBE questions_master")->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})" . PHP_EOL;
}

// Check if there are topics/chapters tables
echo PHP_EOL . "Topics table structure:" . PHP_EOL;
$columns = $db->query("DESCRIBE topics")->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})" . PHP_EOL;
}
?>