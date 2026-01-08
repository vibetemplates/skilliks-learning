<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(dirname(__FILE__)));
}

// Database configuration
$db_config = [
    'host' => '192.168.100.105',
    'port' => 3306, // MAMP MySQL port
    'dbname' => 'project_tracker',
    'username' => 'project_tracker',
    'password' => '#gemini-cli-replacement123#',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// Create DSN
$dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset={$db_config['charset']}";

// Database connection function
function getDB() {
    global $dsn, $db_config;
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
        } catch (PDOException $e) {
            // In production, log this error and show a generic message
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Alternative: Database class
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        global $dsn, $db_config;
        try {
            $this->pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize Database instance");
    }
}
