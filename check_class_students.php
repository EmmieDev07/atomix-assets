<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

// Check class_students table structure
echo "Class_students table structure:" . PHP_EOL;
$columns = $db->query("DESCRIBE class_students")->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})" . PHP_EOL;
}
?>