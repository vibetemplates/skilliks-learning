<?php
session_start();
$_SESSION['user_id'] = 1;
$_POST['prompt_id'] = 38;

require_once 'config/database.php';
$db = getDB();

// Clear existing AI data for prompt 38
$tables = [
    'ai_token_usage',
    'ai_tool_executions',
    'ai_message_content',
    'ai_messages',
    'ai_context_stats',
    'ai_conversation_results',
    'ai_session_tools'
];

foreach ($tables as $table) {
    if ($table === 'ai_session_tools' || $table === 'ai_context_stats' || $table === 'ai_conversation_results') {
        $stmt = $db->prepare("
            DELETE FROM $table
            WHERE session_id IN (
                SELECT id FROM ai_sessions WHERE prompt_id = 38
            )
        ");
    } else {
        if ($table === 'ai_messages') {
            $stmt = $db->prepare("DELETE FROM $table WHERE prompt_id = 38");
        } else {
            $stmt = $db->prepare("
                DELETE FROM $table
                WHERE message_id IN (SELECT id FROM ai_messages WHERE prompt_id = 38)
            ");
        }
    }
    $stmt->execute();
}

$stmt = $db->prepare("DELETE FROM ai_sessions WHERE prompt_id = 38");
$stmt->execute();

echo "Cleared existing AI data for prompt 38\n\n";

// Run the repair
$originalDir = getcwd();
chdir('htmx');

ob_start();
require 'repair-ai-tables.php';
$output = ob_get_clean();

chdir($originalDir);

echo "=== REPAIR OUTPUT ===\n";
echo strip_tags($output) . "\n\n";

// Check what was stored
echo "=== CHECKING STORED DATA ===\n";

$stmt = $db->prepare("
    SELECT
        mc.content_type,
        mc.tool_name,
        SUBSTRING(mc.content_text, 1, 100) as text_preview,
        LENGTH(mc.content_text) as text_length
    FROM ai_message_content mc
    JOIN ai_messages m ON mc.message_id = m.id
    WHERE m.prompt_id = 38
    ORDER BY mc.id
    LIMIT 10
");
$stmt->execute();
$content = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nFIRST 10 MESSAGE CONTENTS:\n";
foreach ($content as $i => $c) {
    echo sprintf("%2d. %-15s %-20s %5d bytes: %s\n",
        $i + 1,
        $c['content_type'],
        $c['tool_name'] ?? 'N/A',
        $c['text_length'],
        substr($c['text_preview'], 0, 50) . '...'
    );
}