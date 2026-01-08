<?php
/**
 * Database Connection Test
 * 
 * Run this file to test the database connection
 */

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';

echo "<h1>Database Connection Test</h1>";
echo "<pre>";

try {
    // Test basic connection
    echo "Testing database connection...\n";
    $db = getDB();
    echo "✓ Connected successfully!\n\n";
    
    // Test Database class
    echo "Testing Database singleton class...\n";
    $dbInstance = Database::getInstance()->getConnection();
    echo "✓ Database singleton working!\n\n";
    
    // Check tables
    echo "Checking database tables:\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠ No tables found. Please run the database initialization script first.\n";
        echo "Run: php " . dirname(__FILE__) . "/../database/init.php\n";
    } else {
        echo "Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        echo "\n";
        
        // Check if roles are populated
        echo "Checking default roles:\n";
        $stmt = $db->query("SELECT * FROM roles");
        $roles = $stmt->fetchAll();
        
        if (empty($roles)) {
            echo "⚠ No roles found. The initialization script may not have completed.\n";
        } else {
            foreach ($roles as $role) {
                echo "  - {$role['name']}: {$role['description']}\n";
            }
        }
    }
    
    echo "\n✓ All tests passed!\n";
    
} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    echo "\nPossible issues:\n";
    echo "1. Database server not running (Is MAMP started?)\n";
    echo "2. Wrong credentials in config/database.php\n";
    echo "3. Database 'project_tracker' doesn't exist\n";
    echo "4. Port mismatch (check MAMP MySQL port)\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Display configuration info
echo "<h2>Configuration Info</h2>";
echo "<pre>";
echo "Host: " . $db_config['host'] . "\n";
echo "Port: " . $db_config['port'] . "\n";
echo "Database: " . $db_config['dbname'] . "\n";
echo "Username: " . $db_config['username'] . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "</pre>";