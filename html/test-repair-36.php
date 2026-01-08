<?php
session_start();
$_SESSION['user_id'] = 1; // Set a user ID for testing

require_once 'config/database.php';

// First, clear any existing AI data for prompt 36
$db = getDB();
$db->beginTransaction();

try {
    // Clear existing AI data
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
                    SELECT id FROM ai_sessions WHERE prompt_id = 36
                )
            ");
        } else {
            if ($table === 'ai_messages') {
                $stmt = $db->prepare("DELETE FROM $table WHERE prompt_id = 36");
            } else {
                $stmt = $db->prepare("
                    DELETE FROM $table
                    WHERE message_id IN (SELECT id FROM ai_messages WHERE prompt_id = 36)
                ");
            }
        }
        $stmt->execute();
    }

    $stmt = $db->prepare("DELETE FROM ai_sessions WHERE prompt_id = 36");
    $stmt->execute();

    echo "Cleared existing AI data for prompt 36\n\n";

    // Now simulate the repair by running the repair logic directly
    $_POST['prompt_id'] = 36;

    // Get prompt details
    $query = $db->prepare("
        SELECT
            pdp.*,
            s.project_id,
            p.created_by,
            pm.user_id as member_id
        FROM project_dev_prompts pdp
        JOIN project_sprints s ON pdp.sprint_id = s.id
        JOIN projects p ON s.project_id = p.id
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = 1
        WHERE pdp.id = 36
    ");
    $query->execute();
    $prompt = $query->fetch(PDO::FETCH_ASSOC);

    $responseText = $prompt['response_text'];
    $responseData = json_decode($responseText, true);

    // Run the repair logic from the file
    ob_start();
    chdir('htmx');
    include 'repair-ai-tables.php';
    chdir('..');
    $output = ob_get_clean();
    echo "Repair output:\n$output\n\n";

    // Check what was stored
    echo "=== CHECKING STORED DATA ===\n";

    // Check sessions
    $stmt = $db->prepare("SELECT * FROM ai_sessions WHERE prompt_id = 36");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Session created: " . ($session ? "Yes, ID: " . $session['id'] : "No") . "\n";

    // Check messages
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_messages WHERE prompt_id = 36");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Messages stored: $count\n";

    // Check message content
    $stmt = $db->prepare("
        SELECT content_type, tool_name,
               SUBSTRING(content_text, 1, 100) as text_preview,
               LENGTH(content_text) as text_length
        FROM ai_message_content mc
        JOIN ai_messages m ON mc.message_id = m.id
        WHERE m.prompt_id = 36
        LIMIT 10
    ");
    $stmt->execute();
    $content = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n=== FIRST 10 MESSAGE CONTENTS ===\n";
    foreach ($content as $c) {
        echo sprintf("Type: %-15s Tool: %-20s Length: %-6d Preview: %s\n",
            $c['content_type'],
            $c['tool_name'] ?? 'N/A',
            $c['text_length'],
            substr($c['text_preview'], 0, 50) . '...'
        );
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}