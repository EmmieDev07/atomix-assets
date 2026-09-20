<?php
date_default_timezone_set('Asia/Manila'); // Centralized PHP default timezone for the app
/**
 * Database Configuration
 * Atomix Learning Platform
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'atomix_db');
define('DB_USER', 'root');
define('DB_PASS', '');

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            // Ensure DB session uses Philippines timezone (UTC+8) to avoid timezone mismatches
            try {
                $this->conn->exec("SET time_zone = '+08:00'");
            } catch (PDOException $e) {
                // If setting timezone fails, continue without halting the app
            }
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
?>
