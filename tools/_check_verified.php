<?php
require 'c:/xampp/htdocs/finalweb/config/database.php';
$db = Database::getInstance()->getConnection();
$cols = $db->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->fetchAll(PDO::FETCH_ASSOC);
echo "COLUMN:\n";
print_r($cols);
$rows = $db->query("SELECT user_id,email,role,status,email_verified_at,created_at FROM users WHERE role='student' ORDER BY user_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "ROWS:\n";
print_r($rows);
?>
