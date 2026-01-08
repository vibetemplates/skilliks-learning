<?php
session_start();
$_SESSION['user_id'] = 1;
$_POST['prompt_id'] = 36;

// Change to htmx directory for includes to work
$originalDir = getcwd();
chdir('htmx');

// Clear output buffering to capture the repair output
ob_start();
require 'repair-ai-tables.php';
$output = ob_get_clean();

// Change back
chdir($originalDir);

echo "=== REPAIR OUTPUT ===\n";
echo strip_tags($output) . "\n\n";

// Now check what was stored
require_once 'config/database.php';
$db = getDB();

echo "=== CHECKING STORED DATA ===\n";

// Check message content
$stmt = $db->prepare("
    SELECT
        mc.content_type,
        mc.tool_name,
        CASE
            WHEN mc.content_type = 'tool_use' THEN 'Should be input params as text'
            WHEN mc.content_type = 'tool_result' THEN 'Should be result text'
            ELSE 'Regular text'
        END as expected_format,
        SUBSTRING(mc.content_text, 1, 100) as text_preview,
        LENGTH(mc.content_text) as text_length,
        CASE
            WHEN mc.content_text LIKE '{%' OR mc.content_text LIKE '[%' THEN 'LOOKS LIKE JSON - BAD!'
            ELSE 'OK - Not JSON'
        END as format_check
    FROM ai_message_content mc
    JOIN ai_messages m ON mc.message_id = m.id
    WHERE m.prompt_id = 36
    ORDER BY mc.id
    LIMIT 5
");
$stmt->execute();
$content = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nFIRST 5 MESSAGE CONTENTS:\n";
echo str_repeat("=", 100) . "\n";
foreach ($content as $i => $c) {
    echo "Entry #" . ($i + 1) . ":\n";
    echo "  Type: " . $c['content_type'] . "\n";
    echo "  Tool: " . ($c['tool_name'] ?? 'N/A') . "\n";
    echo "  Expected: " . $c['expected_format'] . "\n";
    echo "  Format Check: " . $c['format_check'] . "\n";
    echo "  Length: " . $c['text_length'] . " bytes\n";
    echo "  Preview: " . $c['text_preview'] . "...\n";
    echo str_repeat("-", 50) . "\n";
}