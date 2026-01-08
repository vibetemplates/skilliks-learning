<?php
require_once dirname(__DIR__) . '/config/database.php';

echo "=== Adding Sample Project Categories ===\n\n";

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if we already have more than just the default category
    $checkStmt = $db->query("SELECT COUNT(*) as count FROM project_categories");
    $result = $checkStmt->fetch();
    
    if ($result['count'] > 1) {
        echo "✓ Sample categories already exist\n";
    } else {
        echo "Adding sample categories...\n";
        
        // Read the migration file
        $sql = file_get_contents(__DIR__ . '/019_add_sample_project_categories.sql');
        
        // Remove comments and execute
        $sql = preg_replace('/--.*$/m', '', $sql);
        $db->exec($sql);
        
        echo "\n✓ Sample project categories added successfully!\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}