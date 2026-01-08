<?php
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT response_text FROM project_dev_prompts WHERE id = 38");
$stmt->execute();
$responseText = $stmt->fetchColumn();

if (!$responseText) {
    echo "No response text for prompt 38\n";
    exit;
}

$data = json_decode($responseText, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON decode error: " . json_last_error_msg() . "\n";
    echo "First 500 chars of response_text:\n";
    echo substr($responseText, 0, 500) . "\n";
    exit;
}

echo "=== STRUCTURE ===\n";
echo "Number of items: " . count($data) . "\n";
echo "Item types: ";
foreach ($data as $i => $item) {
    echo $i . ":" . ($item['type'] ?? 'unknown') . " ";
    if (isset($item['subtype'])) {
        echo "(" . $item['subtype'] . ") ";
    }
}
echo "\n\n";

// Check if this is Claude format
$hasSystemInit = false;
$hasAssistantMessage = false;

foreach ($data as $item) {
    if (isset($item['type'])) {
        if ($item['type'] === 'system' && $item['subtype'] === 'init') {
            $hasSystemInit = true;
        }
        if ($item['type'] === 'assistant' && isset($item['message'])) {
            $hasAssistantMessage = true;
        }
    }
}

echo "Has system init: " . ($hasSystemInit ? "Yes" : "No") . "\n";
echo "Has assistant message: " . ($hasAssistantMessage ? "Yes" : "No") . "\n\n";

// Check the assistant message if it exists
$assistantIndex = -1;
foreach ($data as $i => $item) {
    if ($item['type'] === 'assistant' && isset($item['message'])) {
        $assistantIndex = $i;
        break;
    }
}

if ($assistantIndex >= 0) {
    $assistantMsg = $data[$assistantIndex];
    echo "=== ASSISTANT MESSAGE (index $assistantIndex) ===\n";

    if (isset($assistantMsg['message']['content'])) {
        $content = $assistantMsg['message']['content'];
        echo "Content array has " . count($content) . " items\n";

        foreach ($content as $j => $contentItem) {
            echo "  Item $j type: " . ($contentItem['type'] ?? 'unknown') . "\n";

            if (isset($contentItem['text'])) {
                $text = $contentItem['text'];
                echo "    Text length: " . strlen($text) . " bytes\n";
                echo "    First 200 chars: " . substr($text, 0, 200) . "\n\n";

                // Check if it looks like embedded JSON
                if (strpos($text, '{"type"') === 0) {
                    echo "    Looks like embedded JSON tool calls\n";

                    // Try to split and count
                    $parts = explode("}\n\n{", $text);
                    echo "    Split by }\\n\\n{ gives " . count($parts) . " parts\n";
                }
            }
        }
    }
}