<?php
/**
 * HTMX Send Single Prompt Endpoint
 */

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
    exit;
}

$promptId = $_POST['prompt_id'] ?? null;
$devTool = $_POST['dev_tool'] ?? 'claude'; // Default to claude for backward compatibility

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
    // Get prompt details with project info including API keys and URLs
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, wi.type, wi.title, wi.priority, 
               p.dev_system_url, p.skilliks_api_key, p.skilliks_system_url, p.skilliks_agent_api
        FROM project_dev_prompts pdp
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
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
        echo '<div class="alert alert-danger">You do not have permission to send this prompt.</div>';
        exit;
    }
    
    // Check configuration based on selected development tool
    if ($devTool === 'claude') {
        // Check Claude Code configuration
        if (empty($prompt['dev_system_url'])) {
            echo '<div class="alert alert-warning">Claude Code Development System URL not configured for this project.</div>';
            exit;
        }
        if (empty($prompt['skilliks_api_key'])) {
            echo '<div class="alert alert-warning">No Skilliks API key configured for Claude Code. Please configure it in the project settings.</div>';
            exit;
        }
        $apiUrl = $prompt['dev_system_url'];
        $apiKey = $prompt['skilliks_api_key'];
    } elseif ($devTool === 'skilliks') {
        // Check Skilliks Coder configuration
        if (empty($prompt['skilliks_system_url'])) {
            echo '<div class="alert alert-warning">Skilliks Coder System URL not configured for this project.</div>';
            exit;
        }
        if (empty($prompt['skilliks_agent_api'])) {
            echo '<div class="alert alert-warning">No Skilliks Agent API key configured. Please configure it in the project settings.</div>';
            exit;
        }
        $apiUrl = $prompt['skilliks_system_url'];
        $apiKey = $prompt['skilliks_agent_api'];
    } else {
        echo '<div class="alert alert-danger">Invalid development tool selected.</div>';
        exit;
    }
    
    // Check if prompt is in a sendable state
    if (!in_array($prompt['status'], ['pending', 'failed'])) {
        echo '<div class="alert alert-warning">This prompt cannot be sent. Status: ' . htmlspecialchars($prompt['status']) . '</div>';
        exit;
    }
    
    // Initialize API client with the appropriate configuration
    $devSystemAPI = new DevSystemAPI($apiUrl, $apiKey, $devTool);
    
    // Debug information
    /*
    echo '<div class="alert alert-info">';
    echo '<h6>Debug Information - API Request:</h6>';
    echo '<strong>API URL:</strong> ' . htmlspecialchars($apiUrl) . ($devTool === 'skilliks' ? '/api/chat' : '/api/run-coder') . '<br>';
    echo '<strong>Headers:</strong><br>';
    echo '<pre>';
    echo "Content-Type: application/json\n";
    echo "Accept: application/json\n";
    if ($devTool !== 'skilliks') {
        echo "X-API-Key: " . htmlspecialchars($apiKey) . "\n";
    }
    echo '</pre>';
    echo '<strong>Prompt ID:</strong> ' . htmlspecialchars($prompt['id']) . '<br>';
    echo '<strong>Project ID:</strong> ' . htmlspecialchars($prompt['project_id']) . '<br>';
    echo '<strong>Work Item ID:</strong> ' . htmlspecialchars($prompt['work_item_id']) . '<br>';
    echo '<strong>Sprint ID:</strong> ' . htmlspecialchars($prompt['sprint_id']) . '<br>';
    echo '<strong>Session ID:</strong> ' . htmlspecialchars($prompt['session_id'] ?? '(none)') . '<br>';
    echo '<strong>Original Prompt Text:</strong><br>';
    echo '<pre class="mb-0">' . htmlspecialchars(substr($prompt['prompt_text'], 0, 500)) . (strlen($prompt['prompt_text']) > 500 ? '...' : '') . '</pre>';
    */
    
    // Show what will actually be sent after cleaning
    $cleanedPrompt = $prompt['prompt_text'];
    if (!isset($_POST['use_test_prompt']) || $_POST['use_test_prompt'] !== '1') {
        $cleanedPrompt = preg_replace('/^#{1,6}\s+/m', '', $cleanedPrompt);
        if (preg_match('/Implementation Request[:\s]+(.+?)$/is', $cleanedPrompt, $matches)) {
            $cleanedPrompt = trim($matches[1]);
        } else {
            $cleanedPrompt = strip_tags($cleanedPrompt);
            $cleanedPrompt = preg_replace('/\s+/', ' ', $cleanedPrompt);
            $cleanedPrompt = trim($cleanedPrompt);
        }
        if (!empty($prompt['work_item_title'])) {
            $cleanedPrompt = "Work on: " . $prompt['work_item_title'] . ". " . $cleanedPrompt;
        }
    }
    
    // Prepend session_id if it exists
    if (!empty($prompt['session_id'])) {
        $cleanedPrompt = $prompt['session_id'] . ' ' . $cleanedPrompt;
    }

    // Append instruction to run the action
    $cleanedPrompt = $cleanedPrompt . ' Please run this action and do not just plan it out.';

    echo '<br><strong>Cleaned Prompt (what will be sent):</strong><br>';
    echo '<pre class="mb-0">' . htmlspecialchars($cleanedPrompt) . '</pre>';
    
    // Show the exact JSON payload (format based on dev tool)
    if ($devTool === 'skilliks') {
        $payload = [
            'message' => isset($_POST['use_test_prompt']) && $_POST['use_test_prompt'] === '1' 
                ? 'On templates.php please change the word Marketplace to Market in the hero section'
                : $cleanedPrompt,
            'async' => true
        ];
    } else {
        $payload = [
            'prompt' => isset($_POST['use_test_prompt']) && $_POST['use_test_prompt'] === '1' 
                ? 'On templates.php please change the word Marketplace to Market in the hero section'
                : $cleanedPrompt
        ];
    }
    
    echo '<br><strong>JSON Payload being sent:</strong><br>';
    echo '<pre class="mb-0">' . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>';
    
    // Test with a simple prompt
    echo '<br><strong>Testing with simple prompt:</strong><br>';
    $testPrompt = 'On templates.php please change the word Marketplace to Market in the hero section';
    if (!empty($prompt['session_id'])) {
        $testPrompt = $prompt['session_id'] . ' ' . $testPrompt;
    }
    if ($devTool === 'skilliks') {
        $testPayload = [
            'message' => $testPrompt,
            'async' => true
        ];
    } else {
        $testPayload = [
            'prompt' => $testPrompt
        ];
    }
    echo '<pre class="mb-0">' . htmlspecialchars(json_encode($testPayload, JSON_PRETTY_PRINT)) . '</pre>';
    
    // Show curl command equivalent
    echo '<br><strong>Equivalent curl command:</strong><br>';
    // Escape single quotes in JSON for shell by replacing ' with '\''
    $escapedJson = str_replace("'", "'\\''", json_encode($payload));
    if ($devTool === 'skilliks') {
        echo '<pre class="mb-0">curl -X POST ' . htmlspecialchars($apiUrl) . '/api/chat \\
    -H "Content-Type: application/json" \\
    -d \'' . htmlspecialchars($escapedJson) . '\'</pre>';
    } else {
        echo '<pre class="mb-0">curl -X POST ' . htmlspecialchars($apiUrl) . '/api/run-coder \\
    -H "Content-Type: application/json" \\
    -H "X-API-Key: ' . htmlspecialchars($apiKey) . '" \\
    -d \'' . htmlspecialchars($escapedJson) . '\'</pre>';
    }
    echo '</div>';
    
    // Send the prompt
    $result = $devSystemAPI->sendPrompt($prompt['id'], $prompt);
    
    // Show debug response
    /*
    echo '<div class="alert alert-warning mt-2">';
    echo '<h6>Debug Information - API Response:</h6>';
    echo '<strong>Success:</strong> ' . ($result['success'] ? 'Yes' : 'No') . '<br>';
    if (!$result['success']) {
        echo '<strong>Error:</strong> ' . htmlspecialchars($result['error'] ?? 'Unknown error') . '<br>';
        if (isset($result['http_code'])) {
            echo '<strong>HTTP Code:</strong> ' . htmlspecialchars($result['http_code']) . '<br>';
        }
        if (isset($result['raw_response'])) {
            echo '<strong>Raw Response:</strong><br>';
            echo '<pre class="mb-0">' . htmlspecialchars($result['raw_response']) . '</pre>';
        }
        
        // Check server error log
        $errorLog = shell_exec('tail -n 20 /var/log/apache2/error.log 2>&1 | grep DevSystemAPI');
        if ($errorLog) {
            echo '<strong>Server Log:</strong><br>';
            echo '<pre class="mb-0">' . htmlspecialchars($errorLog) . '</pre>';
        }
    }
    if (isset($result['response'])) {
        echo '<strong>Response Data:</strong><br>';
        echo '<pre class="mb-0">' . htmlspecialchars(print_r($result['response'], true)) . '</pre>';
    }
    echo '</div>';
    */
    
    if ($result['success']) {
        echo '<div class="alert alert-success">';
        echo '<i class="bi bi-check-circle"></i> Prompt sent successfully!';
        if ($devTool === 'skilliks') {
            if (!empty($result['session_id'])) {
                echo '<br><small>Session ID: ' . htmlspecialchars($result['session_id']) . '</small>';
            }
            if (!empty($result['async_processing'])) {
                echo '<br><small>Processing asynchronously</small>';
            }
        } else {
            if (!empty($result['log_file'])) {
                echo '<br><small>Log file: ' . htmlspecialchars($result['log_file']) . '</small>';
            }
            if (!empty($result['process_id'])) {
                echo '<br><small>Process ID: ' . htmlspecialchars($result['process_id']) . '</small>';
            }
        }
        echo '<br><small class="text-muted">Debug files created in /var/www/html/uploads/debug/</small>';
        echo '</div>';
        
        // Refresh after delay
        echo '<script>setTimeout(function() { window.location.reload(); }, 2000);</script>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<i class="bi bi-exclamation-triangle"></i> Failed to send prompt: ';
        echo htmlspecialchars($result['error']);
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log("send-single-prompt.php Exception: " . $e->getMessage());
    error_log("send-single-prompt.php Exception trace: " . $e->getTraceAsString());
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log("send-single-prompt.php PDOException: " . $e->getMessage());
    error_log("send-single-prompt.php PDOException code: " . $e->getCode());
    error_log("send-single-prompt.php PDOException trace: " . $e->getTraceAsString());
}