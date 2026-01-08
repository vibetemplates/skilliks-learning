<?php
/**
 * Test script to diagnose send-single-prompt.php hanging issue
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/DevSystemAPI.php';

// Get prompt ID from command line or request
$promptId = $argv[1] ?? $_GET['prompt_id'] ?? null;

if (!$promptId) {
    die("Usage: php test-prompt-api.php <prompt_id>\n");
}

echo "Testing API call for prompt ID: $promptId\n";
echo "========================================\n\n";

$db = getDB();

// Get prompt details
$stmt = $db->prepare("
    SELECT pdp.*, s.project_id, p.dev_system_url, p.skilliks_api_key
    FROM project_dev_prompts pdp
    LEFT JOIN sprints s ON pdp.sprint_id = s.id
    LEFT JOIN projects p ON pdp.project_id = p.id
    WHERE pdp.id = ?
");
$stmt->execute([$promptId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    die("Prompt not found!\n");
}

echo "Prompt Details:\n";
echo "- Project ID: " . $prompt['project_id'] . "\n";
echo "- API URL: " . $prompt['dev_system_url'] . "\n";
echo "- API Key: " . (empty($prompt['skilliks_api_key']) ? 'NOT SET' : 'SET (hidden)') . "\n";
echo "- Status: " . $prompt['status'] . "\n";
echo "- Prompt text: " . substr($prompt['prompt_text'], 0, 100) . "...\n\n";

if (empty($prompt['dev_system_url'])) {
    die("ERROR: Development System URL not configured!\n");
}

if (empty($prompt['skilliks_api_key'])) {
    echo "WARNING: API key not configured in database, using default-api-key\n\n";
    $prompt['skilliks_api_key'] = 'default-api-key';
} else {
    // Override API key for testing
    $prompt['skilliks_api_key'] = 'default-api-key';
}

// Test 1: Simple curl test
echo "Test 1: Simple curl connectivity test\n";
echo "--------------------------------------\n";

$testUrl = $prompt['dev_system_url'] . '/api/run-coder';
$curlCmd = sprintf(
    'curl -v -X POST %s -H "Content-Type: application/json" -H "X-API-Key: %s" -d \'{"prompt":"test"}\' 2>&1',
    escapeshellarg($testUrl),
    escapeshellarg($prompt['skilliks_api_key'])
);

echo "Running: curl test (verbose output)...\n";
$output = shell_exec($curlCmd);
echo $output . "\n\n";

// Test 2: Test with timeout
echo "Test 2: API call with 10 second timeout\n";
echo "---------------------------------------\n";

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => 'test with timeout']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-API-Key: ' . $prompt['skilliks_api_key']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, fopen('php://output', 'w'));

$startTime = microtime(true);
echo "Starting request at: " . date('Y-m-d H:i:s') . "\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$curlInfo = curl_getinfo($ch);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\nRequest completed in: $duration seconds\n";
echo "HTTP Code: $httpCode\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . ($response ?: 'Empty') . "\n";

curl_close($ch);

// Test 3: Test with very short timeout
echo "\n\nTest 3: API call with 2 second timeout (to force timeout)\n";
echo "--------------------------------------------------------\n";

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['prompt' => 'test with short timeout']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-API-Key: ' . $prompt['skilliks_api_key']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2 second connection timeout

$startTime = microtime(true);
echo "Starting request at: " . date('Y-m-d H:i:s') . "\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "Request completed in: $duration seconds\n";
echo "HTTP Code: $httpCode\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . ($response ?: 'Empty') . "\n";

curl_close($ch);

// Test 4: Test actual DevSystemAPI class
echo "\n\nTest 4: Using DevSystemAPI class with the actual prompt\n";
echo "------------------------------------------------------\n";

$api = new DevSystemAPI($prompt['dev_system_url'], $prompt['skilliks_api_key']);

// Override timeout for testing
$reflection = new ReflectionClass($api);
$timeoutProp = $reflection->getProperty('timeout');
$timeoutProp->setAccessible(true);
$timeoutProp->setValue($api, 15); // 15 second timeout

echo "Sending prompt through DevSystemAPI...\n";
$startTime = microtime(true);

try {
    $result = $api->sendPrompt($promptId, $prompt);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "API call completed in: $duration seconds\n";
    echo "Result:\n";
    print_r($result);
} catch (Exception $e) {
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    echo "API call failed after: $duration seconds\n";
    echo "Exception: " . $e->getMessage() . "\n";
}

// Check server logs
echo "\n\nServer Error Logs (last 10 lines):\n";
echo "-----------------------------------\n";
$errorLog = shell_exec('tail -n 10 /var/log/apache2/error.log 2>&1');
echo $errorLog;

echo "\n\nDiagnostics complete.\n";