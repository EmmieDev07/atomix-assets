<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Dump session and stop
header('Content-Type: text/plain');
echo "SESSION DUMP\n";
print_r($_SESSION);
echo "\n\n";

// Try to include config and connect to DB
try {
    require_once '../config/database.php';
    $db = Database::getInstance()->getConnection();
    echo "DB CONNECTED\n";
    $teachers = $db->query("SELECT * FROM teachers")->fetchAll();
    echo "Teachers count: ".count($teachers)."\n";
} catch (Exception $e) {
    echo "DB ERROR: ".$e->getMessage()."\n";
}
?>