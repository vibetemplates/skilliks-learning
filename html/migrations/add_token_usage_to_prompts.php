<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Add columns for token usage
$alterStatements = [
    "ALTER TABLE project_dev_prompts ADD COLUMN input_tokens INT DEFAULT NULL AFTER session_id",
    "ALTER TABLE project_dev_prompts ADD COLUMN output_tokens INT DEFAULT NULL AFTER input_tokens", 
    "ALTER TABLE project_dev_prompts ADD COLUMN cache_read_tokens INT DEFAULT NULL AFTER output_tokens",
    "ALTER TABLE project_dev_prompts ADD COLUMN cache_creation_tokens INT DEFAULT NULL AFTER cache_read_tokens",
    "ALTER TABLE project_dev_prompts ADD COLUMN total_cost_usd DECIMAL(10,6) DEFAULT NULL AFTER cache_creation_tokens"
];

foreach ($alterStatements as $sql) {
    try {
        $db->exec($sql);
        echo "✓ Executed: " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "- Column already exists, skipping...\n";
        } else {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration completed!\n";