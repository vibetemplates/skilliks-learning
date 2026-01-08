<?php
require_once dirname(__DIR__) . '/config/database.php';

echo "=== Applying Project Categories Migration ===\n\n";

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if project_categories table already exists
    $checkTable = "SHOW TABLES LIKE 'project_categories'";
    $stmt = $db->query($checkTable);
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Project categories table already exists\n";
    } else {
        echo "Creating project_categories table...\n";
        
        // Read the migration file
        $sql = file_get_contents(__DIR__ . '/018_create_project_categories_table.sql');
        
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                echo "Executing: " . substr($statement, 0, 60) . "...\n";
                $db->exec($statement);
            }
        }
        
        echo "\n✓ Migration 018_create_project_categories_table completed successfully!\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}