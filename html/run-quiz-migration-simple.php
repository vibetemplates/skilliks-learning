<?php
/**
 * Run Simplified Quiz Migration Script
 */

// Parse database config
require_once 'config/database.php';

try {
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
    
    echo "Running simplified SQL migration to create quiz tables...\n";
    
    // Read the simplified SQL file
    $sqlFile = file_get_contents('migrations/008_create_quiz_tables_simple.sql');
    
    // Execute the entire SQL file as one query
    $pdo->exec($sqlFile);
    
    echo "SQL migration completed.\n\n";
    
    // Check if tables were created
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Quiz tables created:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // Also check question_bank tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'question_bank%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nQuiz database structure has been successfully created!\n";
    
    // Clean up
    unlink(__FILE__);
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}