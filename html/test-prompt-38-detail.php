<?php
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT response_text FROM project_dev_prompts WHERE id = 38");
$stmt->execute();
$responseText = $stmt->fetchColumn();

$data = json_decode($responseText, true);

// Get the assistant message text
$text = $data[1]['message']['content'][0]['text'];

// Since it's not split by \n\n, let's see if it's a single JSON object
$parsed = json_decode($text, true);

if (json_last_error() === JSON_ERROR_NONE) {
    echo "The text is a single valid JSON object\n\n";
    echo "Structure:\n";
    echo "  type: " . ($parsed['type'] ?? 'unknown') . "\n";

    if (isset($parsed['tools'])) {
        echo "  It has a 'tools' array with " . count($parsed['tools']) . " items\n";
        foreach ($parsed['tools'] as $i => $tool) {
            echo "    Tool $i:\n";
            echo "      type: " . ($tool['type'] ?? 'unknown') . "\n";
            if (isset($tool['function'])) {
                echo "      function.name: " . ($tool['function']['name'] ?? 'unknown') . "\n";
                echo "      function.arguments: " . substr($tool['function']['arguments'] ?? '', 0, 100) . "...\n";
            }
        }
    }

    if (isset($parsed['content'])) {
        echo "  It has 'content': " . substr($parsed['content'], 0, 200) . "...\n";
    }
} else {
    echo "Not valid JSON as a whole. Error: " . json_last_error_msg() . "\n";
    echo "First 500 chars:\n" . substr($text, 0, 500) . "\n";
}