<?php
/**
 * Cron job to check status of executing prompts
 * This script should be run every minute via cron
 * 
 * Crontab entry example:
 * * * * * * /usr/bin/php /var/www/html/cron/check-executing-prompts.php >> /var/log/check-executing-prompts.log 2>&1
 */

// Load required files
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/classes/DevSystemAPI.php';

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line');
}

echo "[" . date('Y-m-d H:i:s') . "] Starting check-executing-prompts cron job\n";

try {
    $db = getDB();
    
    // Get all prompts in 'executing' status with pid, dev_system_url and API key
    $stmt = $db->prepare("
        SELECT pdp.*, p.dev_system_url, p.skilliks_api_key
        FROM project_dev_prompts pdp
        JOIN projects p ON pdp.project_id = p.id
        WHERE pdp.status = 'executing' 
        AND pdp.pid IS NOT NULL 
        AND pdp.log_file_name IS NOT NULL
        AND p.dev_system_url IS NOT NULL
        AND p.skilliks_api_key IS NOT NULL
    ");
    $stmt->execute();
    $executingPrompts = $stmt->fetchAll();
    
    echo "Found " . count($executingPrompts) . " executing prompts to check\n";
    
    // Check status of each executing prompt
    foreach ($executingPrompts as $prompt) {
        echo "Checking prompt ID: {$prompt['id']}, PID: {$prompt['pid']}\n";
        
        try {
            // Initialize API client with project-specific key
            $apiKey = $prompt['skilliks_api_key'];
            $devSystemAPI = new DevSystemAPI($prompt['dev_system_url'], $apiKey);
            
            // Check coder status
            $result = $devSystemAPI->checkCoderStatus($prompt['pid'], $prompt['log_file_name']);
            
            if ($result['success']) {
                $status = $result['data']['status'] ?? 'unknown';
                $response = $result['data']['response'] ?? '';
                $responseSoFar = $result['data']['response_so_far'] ?? '';
                $bytes = $result['data']['bytes'] ?? 0;
                $error = $result['data']['error'] ?? '';
                
                echo "  Status: $status\n";
                
                switch($status) {
                    case 'running':
                        // Update with partial response if available
                        if (!empty($responseSoFar)) {
                            $updateStmt = $db->prepare("
                                UPDATE project_dev_prompts 
                                SET response_text = ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$responseSoFar, $prompt['id']]);
                            echo "  Updated partial response (" . strlen($responseSoFar) . " characters)\n";
                        }
                        break;
                        
                    case 'done':
                        // Save to responses table
                        $execNumStmt = $db->prepare("
                            SELECT COALESCE(MAX(execution_number), 0) + 1 as next_num 
                            FROM project_prompt_responses 
                            WHERE prompt_id = ?
                        ");
                        $execNumStmt->execute([$prompt['id']]);
                        $nextExecNum = $execNumStmt->fetch()['next_num'];
                        
                        // Insert response record
                        $respStmt = $db->prepare("
                            INSERT INTO project_prompt_responses 
                            (prompt_id, execution_number, pid, log_file_name, response_text, status, completed_at)
                            VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                        ");
                        $respStmt->execute([
                            $prompt['id'],
                            $nextExecNum,
                            $prompt['pid'],
                            $prompt['log_file_name'],
                            $response
                        ]);
                        
                        // Update prompt to test-ready
                        $updateStmt = $db->prepare("
                            UPDATE project_dev_prompts 
                            SET status = 'test-ready', 
                                response_text = ?,
                                completed_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$response, $prompt['id']]);
                        echo "  Prompt completed successfully - marked as test-ready\n";
                        
                        // Mark for notification
                        markPromptForNotification($db, $prompt['id'], 'completed');
                        break;
                        
                    case 'unknown':
                        // Save failure to responses table
                        $execNumStmt = $db->prepare("
                            SELECT COALESCE(MAX(execution_number), 0) + 1 as next_num 
                            FROM project_prompt_responses 
                            WHERE prompt_id = ?
                        ");
                        $execNumStmt->execute([$prompt['id']]);
                        $nextExecNum = $execNumStmt->fetch()['next_num'];
                        
                        // Insert failed response record
                        $respStmt = $db->prepare("
                            INSERT INTO project_prompt_responses 
                            (prompt_id, execution_number, pid, log_file_name, response_text, error_message, status, completed_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'failed', NOW())
                        ");
                        $respStmt->execute([
                            $prompt['id'],
                            $nextExecNum,
                            $prompt['pid'],
                            $prompt['log_file_name'],
                            $response ?: $responseSoFar ?: null,
                            $error ?: 'Process not found or terminated'
                        ]);
                        
                        // Update main prompt record
                        $updateStmt = $db->prepare("
                            UPDATE project_dev_prompts 
                            SET status = 'failed', 
                                error_message = ?,
                                response_text = ?,
                                completed_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $error ?: 'Process not found or terminated',
                            $response ?: $responseSoFar ?: null,
                            $prompt['id']
                        ]);
                        echo "  Prompt failed - marked as failed\n";
                        
                        // Mark for notification
                        markPromptForNotification($db, $prompt['id'], 'failed');
                        break;
                }
            } else {
                echo "  Failed to check status: " . $result['error'] . "\n";
            }
            
        } catch (Exception $e) {
            echo "  Error checking prompt: " . $e->getMessage() . "\n";
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Check-executing-prompts cron job completed\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Mark prompt for browser notification
 */
function markPromptForNotification($db, $promptId, $status) {
    try {
        // Check if notification tracking table exists (we'll create it in next step)
        $checkStmt = $db->prepare("SHOW TABLES LIKE 'prompt_notifications'");
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
            // Insert notification record
            $stmt = $db->prepare("
                INSERT INTO prompt_notifications (prompt_id, status, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), created_at = NOW()
            ");
            $stmt->execute([$promptId, $status]);
        }
    } catch (Exception $e) {
        // Silently fail if table doesn't exist yet
    }
}