<?php
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT response_text FROM project_dev_prompts WHERE id = 36");
$stmt->execute();
$responseText = $stmt->fetchColumn();

$data = json_decode($responseText, true);

echo "=== STRUCTURE ===\n";
echo "Number of items: " . count($data) . "\n\n";

// Check the assistant message
if (isset($data[1]['message']['content'][0]['text'])) {
    $text = $data[1]['message']['content'][0]['text'];
    echo "=== ASSISTANT MESSAGE TEXT (first 500 chars) ===\n";
    echo substr($text, 0, 500) . "\n\n";

    // Try to parse the embedded content
    $lines = explode("\n\n", $text);
    echo "Number of lines separated by \\n\\n: " . count($lines) . "\n\n";

    echo "=== FIRST TOOL CALL ===\n";
    $firstCall = json_decode($lines[0], true);
    if ($firstCall) {
        echo "Type: " . $firstCall['type'] . "\n";
        echo "Name: " . $firstCall['name'] . "\n";
        echo "ID: " . $firstCall['id'] . "\n";
        echo "Input structure: " . print_r($firstCall['input'], true) . "\n";
    }

    echo "\n=== FIRST TOOL RESULT ===\n";
    $firstResult = json_decode($lines[1], true);
    if ($firstResult) {
        echo "Type: " . $firstResult['type'] . "\n";
        echo "Tool Call ID: " . $firstResult['tool_call_id'] . "\n";
        echo "Content: " . $firstResult['content'] . "\n";
    }
}