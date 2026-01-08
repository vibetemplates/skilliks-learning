<?php
/**
 * HTMX Get Prompt Messages Endpoint
 * 
 * Returns all AI messages for a specific prompt in sequential order
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/Project.php';

// Require login
requireLogin();

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$promptId = $_GET['prompt_id'] ?? null;

if (!$promptId) {
    echo json_encode(['success' => false, 'error' => 'Prompt ID is required']);
    exit;
}

$db = getDB();
$userObj = new User();
$projectObj = new Project();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details to check permissions
    $promptStmt = $db->prepare("
        SELECT pdp.*, s.project_id
        FROM project_dev_prompts pdp
        JOIN sprints s ON pdp.sprint_id = s.id
        WHERE pdp.id = ?
    ");
    $promptStmt->execute([$promptId]);
    $prompt = $promptStmt->fetch();
    
    if (!$prompt) {
        echo json_encode(['success' => false, 'error' => 'Prompt not found']);
        exit;
    }
    
    // Check permissions - user must be project member or admin
    $project = $projectObj->findById($prompt['project_id']);
    if (!$project || (!$projectObj->isMember($prompt['project_id'], $currentUserId) && !$userObj->isAdmin($currentUserId))) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Query AI messages and content for this prompt with token usage
    $messagesQuery = $db->prepare("
        SELECT 
            m.id,
            m.session_id,
            m.sequence_number,
            m.type as message_type,
            m.subtype,
            m.role,
            c.content_type,
            c.content_text,
            c.tool_name,
            tu.input_tokens,
            tu.output_tokens,
            tu.total_tokens,
            tu.cost_usd
        FROM ai_messages m
        LEFT JOIN ai_message_content c ON m.id = c.message_id
        LEFT JOIN ai_token_usage tu ON m.id = tu.message_id
        WHERE m.prompt_id = ?
        ORDER BY m.sequence_number, c.id
    ");
    $messagesQuery->execute([$promptId]);
    $messages = $messagesQuery->fetchAll();
    
    // Filter to only include content messages (not empty results)
    $filteredMessages = [];
    foreach ($messages as $message) {
        // Include messages that have content_text or are tool_use messages
        if (!empty($message['content_text']) || $message['content_type'] === 'tool_use') {
            // For tool_use messages, show the tool name as the content
            if ($message['content_type'] === 'tool_use' && empty($message['content_text'])) {
                $message['content_text'] = 'Tool: ' . ($message['tool_name'] ?? 'Unknown');
            }
            $filteredMessages[] = $message;
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $filteredMessages,
        'count' => count($filteredMessages)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-prompt-messages.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while fetching messages']);
}