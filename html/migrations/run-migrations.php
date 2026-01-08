<?php
/**
 * Database Migration Runner
 * 
 * This script runs all migration files in the migrations directory
 * Run this script to apply database updates
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = getDB();
    
    // Create migrations table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Migration system initialized.\n\n";
    
    // Get list of executed migrations
    $stmt = $db->query("SELECT filename FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all migration files
    $migrationDir = __DIR__;
    $migrationFiles = glob($migrationDir . '/*.sql');
    sort($migrationFiles); // Ensure migrations run in order
    
    $migrationsRun = 0;
    
    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        
        // Skip if already executed
        if (in_array($filename, $executedMigrations)) {
            echo "✓ Already executed: $filename\n";
            continue;
        }
        
        echo "Running migration: $filename\n";
        
        // Read and execute the migration
        $sql = file_get_contents($file);
        
        // Handle SET statements and other non-transactional commands
        $nonTransactional = ['SET', 'USE', 'CREATE DATABASE', 'DROP DATABASE', 'ALTER DATABASE'];
        $useTransaction = true;
        
        foreach ($nonTransactional as $cmd) {
            if (stripos(trim($sql), $cmd . ' ') !== false) {
                $useTransaction = false;
                break;
            }
        }
        
        try {
            if ($useTransaction) {
                $db->beginTransaction();
            }
            
            // Execute the entire SQL file at once
            // This handles complex statements better
            $db->exec($sql);
            
            // Record successful migration
            $stmt = $db->prepare("INSERT INTO migrations (filename) VALUES (?)");
            $stmt->execute([$filename]);
            
            if ($useTransaction) {
                $db->commit();
            }
            
            $migrationsRun++;
            echo "✓ Successfully executed: $filename\n\n";
            
        } catch (PDOException $e) {
            if ($useTransaction && $db->inTransaction()) {
                $db->rollBack();
                echo "Rolling back transaction...\n";
            }
            echo "✗ Failed to execute $filename: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    if ($migrationsRun > 0) {
        echo "\n✓ Successfully ran $migrationsRun migration(s).\n";
    } else {
        echo "\n✓ All migrations are up to date.\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}