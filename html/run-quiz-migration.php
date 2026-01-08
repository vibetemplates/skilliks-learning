<?php
/**
 * Run Quiz Migration Script
 */

// Change to the migrations directory
chdir(__DIR__ . '/migrations');

// First, run the SQL migration to create tables
echo "Running SQL migration to create quiz tables...\n";
$sqlFile = file_get_contents('008_create_quiz_tables.sql');

// Parse database config
require_once '../config/database.php';

try {
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
    
    // Split SQL file by delimiter if needed
    $queries = array_filter(array_map('trim', explode(';', $sqlFile)));
    
    foreach ($queries as $query) {
        if (!empty($query) && stripos($query, 'DELIMITER') === false) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                // Check if it's a "table already exists" error
                if ($e->getCode() != '42S01') {
                    echo "Error executing query: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "SQL migration completed.\n\n";
    
    // Check if tables were created
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Quiz tables created:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nQuiz database structure has been successfully created!\n";
    echo "You can now run the data migration script if you have existing quiz data to migrate.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}