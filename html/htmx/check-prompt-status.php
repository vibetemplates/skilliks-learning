<?php
/**
 * HTMX Check Prompt Status Endpoint
 */

// Debug output at the very start
error_log("check-prompt-status.php - Script started");
echo "<!-- Debug: check-prompt-status.php started -->\n";

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/DevSystemAPI.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<!-- Debug: Not a POST request -->";
    exit;
}

echo "<!-- Debug: POST request received -->\n";

$promptId = $_POST['prompt_id'] ?? null;
echo "<!-- Debug: Prompt ID = " . htmlspecialchars($promptId ?? 'null') . " -->\n";

if (!$promptId) {
    echo '<div class="alert alert-danger">Prompt ID is required.</div>';
    exit;
}

$db = getDB();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details with project info including API key
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, p.dev_system_url, p.skilliks_api_key, 
               p.skilliks_system_url, p.skilliks_agent_api
        FROM project_dev_prompts pdp
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        LEFT JOIN projects p ON pdp.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check permissions
    $project = $projectObj->findById($prompt['project_id']);
    $isProjectManager = $project && $project['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to check this prompt status.</div>';
        exit;
    }
    
    // ALWAYS use Claude for status checking
    $isSkilliks = false;
    $devTool = 'claude'; // always use Claude
    
    // Check if we have the necessary process information
    if ($isSkilliks) {
        if (empty($prompt['session_id'])) {
            echo '<div class="alert alert-warning">No session ID available for this Skilliks prompt.</div>';
            exit;
        }
        if (empty($prompt['skilliks_system_url'])) {
            echo '<div class="alert alert-warning">Skilliks System URL not configured for this project.</div>';
            exit;
        }
        $apiUrl = $prompt['skilliks_system_url'];
        $apiKey = $prompt['skilliks_agent_api']; // Skilliks doesn't use API key for status checks
    } else {
        // Claude
        if (empty($prompt['pid']) || empty($prompt['log_file_name'])) {
            echo '<div class="alert alert-warning">No process information available for this prompt.</div>';
            exit;
        }
        if (empty($prompt['dev_system_url'])) {
            echo '<div class="alert alert-warning">Development System URL not configured for this project.</div>';
            exit;
        }
        if (empty($prompt['skilliks_api_key'])) {
            echo '<div class="alert alert-warning">No API key configured for this project. Please configure it in the project settings.</div>';
            exit;
        }
        $apiUrl = $prompt['dev_system_url'];
        $apiKey = $prompt['skilliks_api_key'];
    }
    
    // Initialize API client with project-specific key
    $devSystemAPI = new DevSystemAPI($apiUrl, $apiKey, $devTool);
    
    // Debug information
    /*
    echo '<div class="alert alert-info mb-2">';
    echo '<h6>Debug - Check Status Request:</h6>';
    echo '<strong>Development Tool:</strong> ' . htmlspecialchars($devTool) . '<br>';
    
    if ($isSkilliks) {
        echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl) . '/api/status<br>';
        echo '<strong>Session ID:</strong> ' . htmlspecialchars($prompt['session_id']) . '<br>';
        
        echo '<br><strong>Curl command:</strong><br>';
        $queryParams = http_build_query(['sessionId' => $prompt['session_id']]);
        echo '<pre class="mb-0">curl "' . htmlspecialchars($apiUrl) . '/api/status?' . htmlspecialchars($queryParams) . '"</pre>';
    } else {
        echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl) . '/api/check-coder<br>';
        echo '<strong>PID:</strong> ' . htmlspecialchars($prompt['pid']) . ' (type: ' . gettype($prompt['pid']) . ')<br>';
        echo '<strong>Temp File:</strong> ' . htmlspecialchars($prompt['log_file_name']) . '<br>';
        
        // Prepare payload for curl example
        $pidValue = is_numeric($prompt['pid']) ? intval($prompt['pid']) : $prompt['pid'];
        $payload = json_encode(['pid' => $pidValue, 'outfile' => $prompt['log_file_name']]);  // Changed to 'outfile'
        
        echo '<br><strong>Trying as GET request:</strong><br>';
        $queryParams = http_build_query(['pid' => $pidValue, 'outfile' => $prompt['log_file_name']]);  // Changed to 'outfile'
        echo '<pre class="mb-0">curl -X GET "' . htmlspecialchars($apiUrl) . '/api/check-coder?' . htmlspecialchars($queryParams) . '" \\
    -H "Accept: application/json" \\
    -H "X-API-Key: ' . htmlspecialchars($apiKey) . '"</pre>';
        
        echo '<br><strong>Or as POST request:</strong><br>';
        echo '<pre class="mb-0">curl -X POST ' . htmlspecialchars($apiUrl) . '/api/check-coder \\
    -H "Content-Type: application/json" \\
    -H "X-API-Key: ' . htmlspecialchars($apiKey) . '" \\
    -d \'' . htmlspecialchars($payload) . '\'</pre>';
    }
    echo '</div>';
    */
    
    // Call the appropriate status check endpoint
    if ($isSkilliks) {
        // For Skilliks, use the session ID to check status
        $result = $devSystemAPI->checkCoderStatus($prompt['session_id'], null);
    } else {
        // For Claude, use pid and log file
        $result = $devSystemAPI->checkCoderStatus($prompt['pid'], $prompt['log_file_name']);
    }
    
    // Create debug file for check status response
    // Use uploads directory instead of /tmp
    $debugDir = '/var/www/html/uploads/debug';
    if (!is_dir($debugDir)) {
        mkdir($debugDir, 0777, true);
    }
    $checkDebugFile = $debugDir . '/sprint_check_status_debug_' . date('Y-m-d_H-i-s') . '_prompt_' . $promptId . '.json';
    $checkDebugData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'prompt_id' => $promptId,
        'dev_tool' => $devTool,
        'pid' => $isSkilliks ? null : $prompt['pid'],
        'session_id' => $isSkilliks ? $prompt['session_id'] : null,
        'log_file_name' => $isSkilliks ? null : $prompt['log_file_name'],
        'api_url' => $apiUrl . ($isSkilliks ? '/api/status' : '/api/check-coder'),
        'result' => $result
    ];
    
    // Add debug output to response
    echo '<!-- Debug: Attempting to write to ' . $checkDebugFile . ' -->';
    
    // Also log to a persistent debug log
    $debugLog = $debugDir . '/debug_file_creation.log';
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Attempting to create: $checkDebugFile\n", FILE_APPEND);
    
    $writeResult = file_put_contents($checkDebugFile, json_encode($checkDebugData, JSON_PRETTY_PRINT));
    if ($writeResult === false) {
        error_log("check-prompt-status - FAILED to write check debug file: " . $checkDebugFile);
        error_log("check-prompt-status - Error: " . error_get_last()['message']);
        echo '<!-- Debug: FAILED to write debug file -->';
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - FAILED to create file\n", FILE_APPEND);
    } else {
        error_log("check-prompt-status - Check debug data written to: " . $checkDebugFile . " (bytes: " . $writeResult . ")");
        echo '<!-- Debug: Successfully wrote ' . $writeResult . ' bytes to debug file -->';
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Created file with $writeResult bytes\n", FILE_APPEND);
        
        // Verify file exists
        if (file_exists($checkDebugFile)) {
            echo '<!-- Debug: File exists and is ' . filesize($checkDebugFile) . ' bytes -->';
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Verified: File exists\n", FILE_APPEND);
            
            // List debug directory to see what's there
            $files = glob($debugDir . '/sprint_*debug*.json');
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - Sprint debug files in /tmp: " . count($files) . "\n", FILE_APPEND);
            foreach ($files as $file) {
                file_put_contents($debugLog, "  - $file\n", FILE_APPEND);
            }
        } else {
            echo '<!-- Debug: File does not exist after writing! -->';
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " - ERROR: File disappeared!\n", FILE_APPEND);
        }
    }
    
    // Debug response
    /*
    if (!$result['success']) {
        echo '<div class="alert alert-warning">';
        echo '<h6>Debug - API Response:</h6>';
        if (isset($result['http_code'])) {
            echo '<strong>HTTP Code:</strong> ' . htmlspecialchars($result['http_code']) . '<br>';
        }
        if (isset($result['raw_response'])) {
            echo '<strong>Raw Response:</strong><br>';
            echo '<pre class="mb-0">' . htmlspecialchars($result['raw_response']) . '</pre>';
        }
        echo '</div>';
    }
    */
    
    if ($result['success']) {
        $status = $result['data']['status'] ?? 'unknown';
        $response = $result['data']['response'] ?? '';
        $responseSoFar = $result['data']['response_so_far'] ?? '';
        $bytes = $result['data']['bytes'] ?? 0;
        $error = $result['data']['error'] ?? '';
        
        // For debugging: Store full JSON response for Skilliks (like Claude)
        $responseToStore = $isSkilliks ? json_encode($result['data']) : $response;
        
        echo '<div class="alert alert-info">';
        echo '<h6>Process Status Check</h6>';
        if ($isSkilliks) {
            echo '<strong>Session ID:</strong> ' . htmlspecialchars($prompt['session_id']) . '<br>';
            if (isset($result['data']['model'])) {
                echo '<strong>Model:</strong> ' . htmlspecialchars($result['data']['model']) . '<br>';
            }
            if (isset($result['data']['conversationLength'])) {
                echo '<strong>Conversation Length:</strong> ' . htmlspecialchars($result['data']['conversationLength']) . '<br>';
            }
        } else {
            echo '<strong>Process ID:</strong> ' . htmlspecialchars($prompt['pid']) . '<br>';
            echo '<strong>Temp File:</strong> ' . htmlspecialchars($prompt['log_file_name']) . '<br>';
        }
        echo '<strong>Status:</strong> <span class="badge bg-';
        
        // Map status to badge color
        switch($status) {
            case 'running':
                echo 'warning">Running';
                // Update with partial response
                if (!empty($responseSoFar)) {
                    $updateStmt = $db->prepare("
                        UPDATE project_dev_prompts 
                        SET response_text = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    // For partial responses, store the JSON for Skilliks too
                    $partialResponseToStore = $isSkilliks ? json_encode($result['data']) : $responseSoFar;
                    $updateStmt->execute([$partialResponseToStore, $promptId]);
                }
                break;
            case 'done':
                echo 'info">Test Ready';
                // Update prompt status to test-ready and save response
                // Allow re-processing for debugging purposes
                if (true || ($prompt['status'] !== 'test-ready' && $prompt['status'] !== 'completed')) {
                    // First, save to responses table
                    $execNumStmt = $db->prepare("
                        SELECT COALESCE(MAX(execution_number), 0) + 1 as next_num 
                        FROM project_prompt_responses 
                        WHERE prompt_id = ?
                    ");
                    $execNumStmt->execute([$promptId]);
                    $nextExecNum = $execNumStmt->fetch()['next_num'];
                    
                    // Insert response record
                    $respStmt = $db->prepare("
                        INSERT INTO project_prompt_responses 
                        (prompt_id, execution_number, pid, log_file_name, response_text, status, completed_at)
                        VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                    ");
                    $respStmt->execute([
                        $promptId,
                        $nextExecNum,
                        $isSkilliks ? $prompt['session_id'] : $prompt['pid'],  // Use session_id for Skilliks
                        $isSkilliks ? null : $prompt['log_file_name'],  // No log file for Skilliks
                        $response
                    ]);
                    
                    // Store the AI response in the new tables FIRST to get session_id
                    $sessionId = null;
                    try {
                        if (!class_exists('AIResponseManager')) {
                            require_once '../classes/AIResponseManager.php';
                        }
                        
                        $aiManager = new AIResponseManager();
                        
                        // Try to parse the response as JSON first
                        $responseData = json_decode($response, true);
                        if (!$responseData) {
                            // If not JSON, create a simple response structure
                            $responseData = [
                                'status' => 'success',
                                'data' => [
                                    'response' => $response,
                                    'sessionId' => uniqid('check_'),
                                    'model' => 'unknown',
                                    'status' => 'completed',
                                    'active' => false,
                                    'toolsExecuted' => []
                                ]
                            ];
                        }
                        
                        // Determine platform based on response structure
                        // Check if it's Claude format by looking for Claude-specific fields
                        $platform = 'skilliks';
                        if (is_array($responseData) && !empty($responseData)) {
                            // Check if it's a single result object (like prompt 107)
                            if (isset($responseData['type']) && $responseData['type'] === 'result' &&
                                isset($responseData['usage']) && isset($responseData['total_cost_usd'])) {
                                // This is a Claude result object with usage data
                                $platform = 'claude_result';
                            }
                            // Check for Claude array format
                            elseif (isset($responseData[0])) {
                                $firstMsg = $responseData[0];
                                if (isset($firstMsg['type']) && isset($firstMsg['session_id'])) {
                                    // This is Claude format
                                    $platform = 'claude';
                                } elseif (isset($firstMsg['message']['usage']) ||
                                         (isset($firstMsg['type']) && in_array($firstMsg['type'], ['system', 'assistant', 'user', 'result']))) {
                                    // Also Claude format
                                    $platform = 'claude';
                                }
                            }
                        }
                        
                        // Create debug file for AI response storage
                        $aiDebugFile = $debugDir . '/sprint_ai_response_debug_' . date('Y-m-d_H-i-s') . '_prompt_' . $promptId . '.json';
                        $aiDebugData = [
                            'timestamp' => date('Y-m-d H:i:s'),
                            'prompt_id' => $promptId,
                            'sprint_id' => $prompt['sprint_id'],
                            'platform' => $platform,
                            'status' => $status,
                            'response_data' => $responseData,
                            'response_length' => strlen($response)
                        ];
                        
                        // Store based on platform
                        $sessionId = null;
                        $directUsageData = null;

                        if ($platform === 'claude_result') {
                            // Handle single Claude result object with usage data
                            // Extract session_id and usage data directly
                            $sessionId = $responseData['session_id'] ?? uniqid('claude_result_');

                            // Extract usage data directly from the result object
                            if (isset($responseData['usage']) && isset($responseData['total_cost_usd'])) {
                                $directUsageData = [
                                    'input_tokens' => $responseData['usage']['input_tokens'] ?? 0,
                                    'output_tokens' => $responseData['usage']['output_tokens'] ?? 0,
                                    'cache_read_tokens' => $responseData['usage']['cache_read_input_tokens'] ?? 0,
                                    'cache_creation_tokens' => $responseData['usage']['cache_creation_input_tokens'] ?? 0,
                                    'total_cost_usd' => $responseData['total_cost_usd']
                                ];
                            }

                            // Convert to array format for AIResponseManager (optional)
                            $wrappedResponse = [$responseData];
                            $sessionId = $aiManager->storeClaudeResponse($wrappedResponse, $prompt['sprint_id'], $promptId);
                            $aiDebugData['session_id'] = $sessionId;
                        } elseif ($platform === 'claude' && is_array($responseData)) {
                            $sessionId = $aiManager->storeClaudeResponse($responseData, $prompt['sprint_id'], $promptId);
                            $aiDebugData['session_id'] = $sessionId;
                        } else {
                            $sessionId = $aiManager->storeSkiliksResponse($responseData, $prompt['sprint_id'], $promptId);
                            $aiDebugData['session_id'] = $sessionId;
                        }
                        
                        // Add platform detection info to debug
                        $aiDebugData['platform_detection'] = [
                            'detected_platform' => $platform,
                            'first_msg_type' => $responseData[0]['type'] ?? 'unknown',
                            'has_session_id' => isset($responseData[0]['session_id']),
                            'has_usage' => isset($responseData[0]['message']['usage'])
                        ];
                        
                        // Write debug file
                        $writeResult = file_put_contents($aiDebugFile, json_encode($aiDebugData, JSON_PRETTY_PRINT));
                        if ($writeResult === false) {
                            error_log("check-prompt-status - FAILED to write AI debug file: " . $aiDebugFile);
                            error_log("check-prompt-status - Error: " . error_get_last()['message']);
                        } else {
                            error_log("check-prompt-status - AI debug data written to: " . $aiDebugFile . " (bytes: " . $writeResult . ")");
                        }
                        
                        // Get usage data - prefer direct extraction over AIResponseManager
                        $usageData = $directUsageData ?: $aiManager->getResultUsageData();

                        // Now update prompt with session_id and token usage
                        if ($usageData) {
                            $updateStmt = $db->prepare("
                                UPDATE project_dev_prompts 
                                SET status = 'test-ready', 
                                    response_text = ?,
                                    session_id = ?,
                                    input_tokens = ?,
                                    output_tokens = ?,
                                    cache_read_tokens = ?,
                                    cache_creation_tokens = ?,
                                    total_cost_usd = ?,
                                    completed_at = NOW(),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $updateStmt->execute([
                                $responseToStore, 
                                $sessionId,
                                $usageData['input_tokens'],
                                $usageData['output_tokens'],
                                $usageData['cache_read_tokens'],
                                $usageData['cache_creation_tokens'],
                                $usageData['total_cost_usd'],
                                $promptId
                            ]);
                        } else {
                            // Fallback without usage data
                            $updateStmt = $db->prepare("
                                UPDATE project_dev_prompts 
                                SET status = 'test-ready', 
                                    response_text = ?,
                                    session_id = ?,
                                    completed_at = NOW(),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$responseToStore, $sessionId, $promptId]);
                        }
                        
                    } catch (Exception $e) {
                        error_log("Failed to store AI response: " . $e->getMessage());
                        // Still update prompt status even if AI storage fails
                        $updateStmt = $db->prepare("
                            UPDATE project_dev_prompts 
                            SET status = 'test-ready', 
                                response_text = ?,
                                completed_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$responseToStore, $promptId]);
                    }
                }
                break;
            case 'unknown':
                echo 'danger">Unknown/Failed';
                // Update prompt status if needed
                if ($prompt['status'] !== 'failed') {
                    // Save to responses table first
                    $execNumStmt = $db->prepare("
                        SELECT COALESCE(MAX(execution_number), 0) + 1 as next_num 
                        FROM project_prompt_responses 
                        WHERE prompt_id = ?
                    ");
                    $execNumStmt->execute([$promptId]);
                    $nextExecNum = $execNumStmt->fetch()['next_num'];
                    
                    // Insert failed response record
                    $respStmt = $db->prepare("
                        INSERT INTO project_prompt_responses 
                        (prompt_id, execution_number, pid, log_file_name, response_text, error_message, status, completed_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'failed', NOW())
                    ");
                    $respStmt->execute([
                        $promptId,
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
                        $responseToStore ?: ($isSkilliks ? json_encode($result['data']) : null),
                        $promptId
                    ]);
                }
                break;
            default:
                echo 'secondary">Unknown';
        }
        echo '</span><br>';
        
        if ($bytes > 0) {
            echo '<strong>Bytes processed:</strong> ' . number_format($bytes) . '<br>';
        }
        
        // Show output based on status
        $outputToShow = '';
        if ($status === 'done' && !empty($response)) {
            $outputToShow = $response;
            echo '<strong>Output:</strong><br>';
        } elseif ($status === 'running' && !empty($responseSoFar)) {
            $outputToShow = $responseSoFar;
            echo '<strong>Output so far:</strong><br>';
        } elseif (!empty($error)) {
            $outputToShow = $error;
            echo '<strong>Error:</strong><br>';
        }
        
        if (!empty($outputToShow)) {
            echo '<pre class="mt-2 p-2 bg-light rounded" style="max-height: 300px; overflow-y: auto;">';
            echo htmlspecialchars($outputToShow);
            echo '</pre>';
        }
        
        echo '</div>';

        // Show Complete button if status is done (test-ready)
        if ($status === 'done') {
            echo '<div class="mt-2">';
            echo '<button class="btn btn-sm btn-success" ';
            echo 'onclick="if(confirm(\'Mark this prompt as completed?\')) { ';
            echo 'fetch(\'/htmx/mark-prompt-complete.php\', { ';
            echo 'method: \'POST\', ';
            echo 'headers: {\'Content-Type\': \'application/x-www-form-urlencoded\'}, ';
            echo 'body: \'prompt_id=' . $promptId . '\' ';
            echo '}).then(() => location.reload()); ';
            echo '}">';
            echo '<i class="bi bi-check-circle"></i> Complete';
            echo '</button>';
            echo '</div>';
        }

        // Show update notification if response was saved
        if ($status === 'running' && !empty($responseSoFar)) {
            echo '<div class="alert alert-info alert-dismissible fade show mt-2" role="alert">';
            echo '<i class="bi bi-check-circle"></i> Response updated with latest output.';
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
        }
        
        // Always refresh the accordion content to show updated response
        echo '<script>
            setTimeout(function() { 
                // Refresh the specific accordion item
                var accordionItem = document.querySelector("#collapse' . $promptId . '").closest(".accordion-item");
                if (accordionItem) {
                    htmx.trigger(accordionItem, "refreshPrompt");
                }
                // If done or unknown (failed), refresh the whole page
                if ("' . $status . '" === "done" || "' . $status . '" === "unknown") {
                    window.location.reload();
                }
            }, 1000);
        </script>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<i class="bi bi-exclamation-triangle"></i> Failed to check status: ';
        echo htmlspecialchars($result['error']);
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}