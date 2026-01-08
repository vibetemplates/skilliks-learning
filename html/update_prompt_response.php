#!/usr/bin/env php
<?php
/**
 * Helper program to update project_dev_prompts response_text from JSON file
 *
 * Usage: php update_prompt_response.php <json_filename> <prompt_id>
 *
 * Arguments:
 *   json_filename: Path to JSON file to read
 *   prompt_id: ID of the record in project_dev_prompts table to update
 */

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Check arguments
if ($argc !== 3) {
    echo "Usage: php " . basename($argv[0]) . " <json_filename> <prompt_id>\n";
    echo "  json_filename: Path to JSON file to read\n";
    echo "  prompt_id: ID of the record in project_dev_prompts table to update\n";
    exit(1);
}

$jsonFile = $argv[1];
$promptId = $argv[2];

// Validate JSON file exists
if (!file_exists($jsonFile)) {
    die("Error: JSON file '$jsonFile' does not exist\n");
}

// Validate prompt ID is numeric
if (!is_numeric($promptId)) {
    die("Error: prompt_id must be a number\n");
}

// Read and validate JSON file
$jsonContent = file_get_contents($jsonFile);
if ($jsonContent === false) {
    die("Error: Unable to read file '$jsonFile'\n");
}

// Validate JSON
$jsonData = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON in file '$jsonFile': " . json_last_error_msg() . "\n");
}

// Include database configuration
require_once __DIR__ . '/config/database.php';

try {
    // Get database connection
    $db = getDB();

    // Check if record exists
    $checkStmt = $db->prepare("SELECT id FROM project_dev_prompts WHERE id = ?");
    $checkStmt->execute([$promptId]);

    if (!$checkStmt->fetch()) {
        die("Error: No record found with ID $promptId in project_dev_prompts table\n");
    }

    // Extract token usage and cost data from JSON if available
    $inputTokens = null;
    $outputTokens = null;
    $totalCostUsd = null;
    $cacheReadTokens = null;
    $cacheCreationTokens = null;

    // Check for Claude response format (usage field at root level)
    if (isset($jsonData['usage'])) {
        $inputTokens = $jsonData['usage']['input_tokens'] ?? null;
        $outputTokens = $jsonData['usage']['output_tokens'] ?? null;
        $cacheReadTokens = $jsonData['usage']['cache_read_input_tokens'] ?? null;
        $cacheCreationTokens = $jsonData['usage']['cache_creation_input_tokens'] ?? null;
    }

    // Check for total cost
    if (isset($jsonData['total_cost_usd'])) {
        $totalCostUsd = $jsonData['total_cost_usd'];
    }

    // Build update query based on available data
    if ($inputTokens !== null || $outputTokens !== null || $totalCostUsd !== null) {
        // Update with token usage data
        $sql = "UPDATE project_dev_prompts SET response_text = ?";
        $params = [$jsonContent];

        if ($inputTokens !== null) {
            $sql .= ", input_tokens = ?";
            $params[] = $inputTokens;
        }
        if ($outputTokens !== null) {
            $sql .= ", output_tokens = ?";
            $params[] = $outputTokens;
        }
        if ($cacheReadTokens !== null) {
            $sql .= ", cache_read_tokens = ?";
            $params[] = $cacheReadTokens;
        }
        if ($cacheCreationTokens !== null) {
            $sql .= ", cache_creation_tokens = ?";
            $params[] = $cacheCreationTokens;
        }
        if ($totalCostUsd !== null) {
            $sql .= ", total_cost_usd = ?";
            $params[] = $totalCostUsd;
        }

        $sql .= " WHERE id = ?";
        $params[] = $promptId;

        $updateStmt = $db->prepare($sql);
        $result = $updateStmt->execute($params);

        echo "Success: Updated prompt ID $promptId with response and token usage data\n";
        echo "  Input tokens: " . ($inputTokens ?? 'N/A') . "\n";
        echo "  Output tokens: " . ($outputTokens ?? 'N/A') . "\n";
        echo "  Cache read tokens: " . ($cacheReadTokens ?? 'N/A') . "\n";
        echo "  Cache creation tokens: " . ($cacheCreationTokens ?? 'N/A') . "\n";
        echo "  Total cost USD: " . ($totalCostUsd ?? 'N/A') . "\n";
    } else {
        // No token usage data found, just update response_text
        $updateStmt = $db->prepare("UPDATE project_dev_prompts SET response_text = ? WHERE id = ?");
        $result = $updateStmt->execute([$jsonContent, $promptId]);

        echo "Success: Updated response_text for prompt ID $promptId\n";
        echo "Note: No token usage data found in JSON\n";
    }

    echo "JSON file: $jsonFile\n";
    echo "JSON size: " . strlen($jsonContent) . " bytes\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}