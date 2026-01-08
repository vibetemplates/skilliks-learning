#!/usr/bin/env php
<?php
/**
 * Batch update script to extract token data from response_text JSON
 * and populate the token columns in project_dev_prompts
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

// Find all prompts with response_text that might have token data but missing in columns
$stmt = $db->prepare("
    SELECT id, response_text
    FROM project_dev_prompts
    WHERE response_text IS NOT NULL
    AND response_text != ''
    AND (
        input_tokens IS NULL
        OR output_tokens IS NULL
        OR (total_cost_usd IS NULL AND response_text LIKE '%total_cost_usd%')
    )
    ORDER BY id DESC
");
$stmt->execute();
$prompts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($prompts) . " prompts to check\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($prompts as $prompt) {
    echo "Processing prompt ID {$prompt['id']}... ";

    // Try to parse the JSON
    $json = json_decode($prompt['response_text'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "SKIPPED (invalid JSON)\n";
        $skipped++;
        continue;
    }

    // Extract token data if available
    $inputTokens = null;
    $outputTokens = null;
    $totalCostUsd = null;
    $cacheReadTokens = null;
    $cacheCreationTokens = null;
    $hasData = false;

    // Check for Claude response format (usage field at root level)
    if (isset($json['usage'])) {
        $inputTokens = $json['usage']['input_tokens'] ?? null;
        $outputTokens = $json['usage']['output_tokens'] ?? null;
        $cacheReadTokens = $json['usage']['cache_read_input_tokens'] ?? null;
        $cacheCreationTokens = $json['usage']['cache_creation_input_tokens'] ?? null;
        $hasData = true;
    }

    // Check for total cost
    if (isset($json['total_cost_usd'])) {
        $totalCostUsd = $json['total_cost_usd'];
        $hasData = true;
    }

    if (!$hasData) {
        echo "SKIPPED (no token data found)\n";
        $skipped++;
        continue;
    }

    // Build update query
    $updateParts = [];
    $params = [];

    if ($inputTokens !== null) {
        $updateParts[] = "input_tokens = ?";
        $params[] = $inputTokens;
    }
    if ($outputTokens !== null) {
        $updateParts[] = "output_tokens = ?";
        $params[] = $outputTokens;
    }
    if ($cacheReadTokens !== null) {
        $updateParts[] = "cache_read_tokens = ?";
        $params[] = $cacheReadTokens;
    }
    if ($cacheCreationTokens !== null) {
        $updateParts[] = "cache_creation_tokens = ?";
        $params[] = $cacheCreationTokens;
    }
    if ($totalCostUsd !== null) {
        $updateParts[] = "total_cost_usd = ?";
        $params[] = $totalCostUsd;
    }

    if (empty($updateParts)) {
        echo "SKIPPED (no updates needed)\n";
        $skipped++;
        continue;
    }

    // Add WHERE clause parameter
    $params[] = $prompt['id'];

    try {
        $sql = "UPDATE project_dev_prompts SET " . implode(", ", $updateParts) . " WHERE id = ?";
        $updateStmt = $db->prepare($sql);
        $updateStmt->execute($params);

        echo "UPDATED (";
        $updates = [];
        if ($inputTokens !== null) $updates[] = "in:$inputTokens";
        if ($outputTokens !== null) $updates[] = "out:$outputTokens";
        if ($totalCostUsd !== null) $updates[] = "$:" . number_format($totalCostUsd, 4);
        echo implode(", ", $updates) . ")\n";

        $updated++;
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "=============================\n";
echo "Summary:\n";
echo "  Updated: $updated prompts\n";
echo "  Skipped: $skipped prompts\n";
echo "  Errors:  $errors prompts\n";
echo "=============================\n";