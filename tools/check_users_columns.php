<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'");
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $cols) . "\n";
