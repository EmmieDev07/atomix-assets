<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();

header('Content-Type: text/plain');
echo "SESSION DUMP BEFORE SET\n";
print_r($_SESSION);

// Set session values manually
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'admin';

echo "\nSESSION DUMP AFTER SET\n";
print_r($_SESSION);

// Try to access teachers.php logic
try {
    require_once '../config/database.php';
    $db = Database::getInstance()->getConnection();
    $teachers = $db->query("SELECT * FROM teachers")->fetchAll();
    echo "\nTeachers count: ".count($teachers)."\n";
} catch (Exception $e) {
    echo "DB ERROR: ".$e->getMessage()."\n";
}
?>