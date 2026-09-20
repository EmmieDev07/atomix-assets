<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

// Check lessons table structure
echo "Lessons table structure:" . PHP_EOL;
$columns = $db->query("DESCRIBE lessons")->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})" . PHP_EOL;
}

// Check chapters table
echo PHP_EOL . "Chapters table structure:" . PHP_EOL;
$columns = $db->query("DESCRIBE chapters")->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})" . PHP_EOL;
}
?>