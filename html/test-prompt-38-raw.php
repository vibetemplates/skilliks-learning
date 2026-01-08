<?php
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT response_text FROM project_dev_prompts WHERE id = 38");
$stmt->execute();
$responseText = $stmt->fetchColumn();

$data = json_decode($responseText, true);
$text = $data[1]['message']['content'][0]['text'];

// Let's look for the pattern changes
echo "=== LOOKING FOR PATTERNS ===\n\n";

// Check first 2000 characters
echo "First 2000 characters:\n";
echo substr($text, 0, 2000) . "\n\n";

// Look for the end of first object
$pos = strpos($text, '}}');
if ($pos !== false) {
    echo "Found }} at position $pos\n";
    echo "Characters around that position:\n";
    echo "..." . substr($text, $pos - 20, 60) . "...\n\n";
}

// Look for common separators
$separators = [
    "}\n{",
    "}\n\n{",
    "}}\n\n{",
    "}\n\nFixed",
    '"text":"',
    '{"type":"tool_result"',
];

foreach ($separators as $sep) {
    $count = substr_count($text, $sep);
    if ($count > 0) {
        echo "Found '$sep' $count times\n";
    }
}

// Save to file for manual inspection
file_put_contents('/tmp/prompt38_text.txt', $text);
echo "\nFull text saved to /tmp/prompt38_text.txt\n";