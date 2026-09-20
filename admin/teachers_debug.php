<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();

header('Content-Type: text/plain');
echo "SESSION DUMP\n";
print_r($_SESSION);

// Try to run the teachers.php logic inline
try {
    require_once '../config/database.php';
    $db = Database::getInstance()->getConnection();
    $teachers = $db->query("SELECT t.*, u.email, u.username, u.status, u.created_at, u.last_login FROM teachers t JOIN users u ON t.user_id = u.user_id ORDER BY t.last_name, t.first_name")->fetchAll();
    echo "\nTeachers count: ".count($teachers)."\n";
    if (count($teachers) > 0) {
        foreach ($teachers as $teacher) {
            echo $teacher['first_name'] . ' ' . $teacher['last_name'] . ' | ' . $teacher['email'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "DB ERROR: ".$e->getMessage()."\n";
}

// Try to include teachers.php as text
$path = __DIR__ . '/teachers.php';
echo "\n\n--- teachers.php source ---\n";
echo file_get_contents($path);
?>