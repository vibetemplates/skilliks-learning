<?php
/**
 * AI Conversation Detail View
 * 
 * View detailed AI conversation with messages and tool executions
 */

$page_title = 'AI Conversation Detail';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/AIResponseManager.php';
require_once 'classes/Team.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$db = getDB();
$aiManager = new AIResponseManager();
$teamObj = new Team();

// Get conversation ID
$conversationId = $_GET['id'] ?? null;
if (!$conversationId) {
    header('Location: ai-conversations.php');
    exit;
}

// Get conversation summary
$conversationSummary = $aiManager->getConversationSummary($conversationId);
if (!$conversationSummary) {
    header('Location: ai-conversations.php');
    exit;
}

// Check permissions
$hasAccess = false;
if ($conversationSummary['sprint_id']) {
    $stmt = $db->prepare("
        SELECT 1 FROM sprints s
        JOIN projects p ON s.project_id = p.id
        JOIN team_members tm ON p.team_id = tm.team_id
        WHERE s.id = ? AND tm.user_id = ?
    ");
    $stmt->execute([$conversationSummary['sprint_id'], $currentUserId]);
    $hasAccess = $stmt->rowCount() > 0;
}

if (!$hasAccess) {
    header('Location: ai-conversations.php');
    exit;
}

// Get messages
$messages = $aiManager->getConversationMessages($conversationId);

// Get tool executions
$toolExecutions = $aiManager->getToolExecutions($conversationId);

// Get related info
$stmt = $db->prepare("
    SELECT 
        sp.name as sprint_name,
        p.name as project_name,
        pdp.prompt_text,
        pdp.title as prompt_title,
        wi.title as work_item_title
    FROM ai_sessions s
    LEFT JOIN sprints sp ON s.sprint_id = sp.id
    LEFT JOIN projects p ON sp.project_id = p.id
    LEFT JOIN project_dev_prompts pdp ON s.prompt_id = pdp.id
    LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
    WHERE s.id = ?
");
$stmt->execute([$conversationId]);
$relatedInfo = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
.message-card {
    border-left: 3px solid #dee2e6;
    margin-bottom: 1rem;
}
.message-card.system {
    border-left-color: #6c757d;
    background-color: #f8f9fa;
}
.message-card.assistant {
    border-left-color: #0d6efd;
}
.message-card.user {
    border-left-color: #28a745;
}
.message-card.result {
    border-left-color: #ffc107;
}
.message-content {
    white-space: pre-wrap;
    word-wrap: break-word;
}
.tool-use {
    background-color: #e9ecef;
    border-radius: 4px;
    padding: 0.5rem;
    margin-top: 0.5rem;
}
.token-badge {
    font-size: 0.75rem;
}
</style>

<main class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3">
        <div>
            <h1 class="h2">AI Conversation Detail</h1>
            <p class="text-muted mb-0">
                <?php if ($relatedInfo['project_name']): ?>
                    <strong><?php echo htmlspecialchars($relatedInfo['project_name']); ?></strong>
                    <?php if ($relatedInfo['sprint_name']): ?>
                        / <?php echo htmlspecialchars($relatedInfo['sprint_name']); ?>
                    <?php endif; ?>
                <?php endif; ?>
                • <?php echo date('M d, Y H:i', strtotime($conversationSummary['created_at'])); ?>
            </p>
        </div>
        <div class="btn-toolbar">
            <a href="ai-conversations.php<?php echo $conversationSummary['sprint_id'] ? '?sprint_id=' . $conversationSummary['sprint_id'] : ''; ?>" 
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <h6 class="text-muted">Platform</h6>
                    <p class="mb-0">
                        <span class="badge bg-<?php echo $conversationSummary['platform'] === 'claude' ? 'primary' : 'success'; ?>">
                            <?php echo ucfirst($conversationSummary['platform']); ?>
                        </span>
                        <?php if ($conversationSummary['model']): ?>
                            <br><small><?php echo htmlspecialchars($conversationSummary['model']); ?></small>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted">Status</h6>
                    <p class="mb-0">
                        <?php if ($conversationSummary['status'] === 'completed'): ?>
                            <span class="badge bg-success">Completed</span>
                        <?php elseif ($conversationSummary['error_count'] > 0): ?>
                            <span class="badge bg-danger">Failed</span>
                        <?php else: ?>
                            <span class="badge bg-warning">In Progress</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted">Duration</h6>
                    <p class="mb-0">
                        <?php echo $conversationSummary['total_duration_ms'] ? 
                            number_format($conversationSummary['total_duration_ms'] / 1000, 2) . ' seconds' : 'N/A'; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted">Cost</h6>
                    <p class="mb-0">
                        $<?php echo number_format($conversationSummary['total_cost_usd'] ?? 0, 4); ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-muted">Token Usage</h6>
                    <p class="mb-0">
                        Input: <?php echo number_format($conversationSummary['total_input_tokens'] ?? 0); ?><br>
                        Output: <?php echo number_format($conversationSummary['total_output_tokens'] ?? 0); ?><br>
                        Total: <?php echo number_format(($conversationSummary['total_input_tokens'] ?? 0) + 
                                                      ($conversationSummary['total_output_tokens'] ?? 0)); ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">Tools Used</h6>
                    <p class="mb-0"><?php echo number_format($conversationSummary['tools_used_count'] ?? 0); ?> tools executed</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-muted">Working Directory</h6>
                    <p class="mb-0">
                        <code><?php echo htmlspecialchars($conversationSummary['working_directory'] ?? 'N/A'); ?></code>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Original Prompt -->
    <?php if ($relatedInfo['prompt_text']): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Original Prompt</h5>
        </div>
        <div class="card-body">
            <pre class="message-content mb-0"><?php echo htmlspecialchars($relatedInfo['prompt_text']); ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <!-- Final Response -->
    <?php if ($conversationSummary['final_response']): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Final Response</h5>
        </div>
        <div class="card-body">
            <pre class="message-content mb-0"><?php echo htmlspecialchars($conversationSummary['final_response']); ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages Timeline -->
    <h3 class="h4 mb-3">Conversation Timeline</h3>
    
    <?php foreach ($messages as $message): ?>
    <div class="card message-card <?php echo htmlspecialchars($message['type']); ?>">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo ucfirst($message['type']); ?></strong>
                    <?php if ($message['role']): ?>
                        <span class="text-muted">(<?php echo htmlspecialchars($message['role']); ?>)</span>
                    <?php endif; ?>
                    <?php if ($message['subtype']): ?>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($message['subtype']); ?></span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">
                    #<?php echo $message['sequence_number']; ?>
                    • <?php echo date('H:i:s', strtotime($message['created_at'])); ?>
                </small>
            </div>
        </div>
        <div class="card-body">
            <?php 
            // Parse contents JSON
            $contents = [];
            if ($message['contents']) {
                $contents = json_decode('[' . $message['contents'] . ']', true);
            }
            
            foreach ($contents as $content):
                if ($content['type'] === 'text' && !empty($content['text'])):
            ?>
                <div class="message-content"><?php echo nl2br(htmlspecialchars($content['text'])); ?></div>
            <?php 
                elseif ($content['type'] === 'tool_use'):
                    // Try to parse content data for tool info
                    $toolData = json_decode($content['text'], true);
            ?>
                <div class="tool-use">
                    <strong>Tool Use:</strong> <?php echo htmlspecialchars($toolData['name'] ?? 'Unknown'); ?><br>
                    <?php if (isset($toolData['input'])): ?>
                        <small>Parameters:</small>
                        <pre class="mb-0" style="font-size: 0.875rem;"><?php echo htmlspecialchars(json_encode($toolData['input'], JSON_PRETTY_PRINT)); ?></pre>
                    <?php endif; ?>
                </div>
            <?php 
                elseif ($content['type'] === 'tool_result'):
            ?>
                <div class="tool-use">
                    <strong>Tool Result:</strong><br>
                    <pre class="mb-0" style="font-size: 0.875rem; max-height: 200px; overflow-y: auto;"><?php echo htmlspecialchars($content['text']); ?></pre>
                </div>
            <?php 
                endif;
            endforeach;
            ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Tool Executions -->
    <?php if (!empty($toolExecutions)): ?>
    <h3 class="h4 mb-3 mt-4">Tool Executions</h3>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Parameters</th>
                    <th>Result Summary</th>
                    <th>Error</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($toolExecutions as $exec): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exec['tool_name']); ?></td>
                    <td>
                        <?php if ($exec['parameters']): ?>
                        <small><code><?php echo htmlspecialchars(substr($exec['parameters'], 0, 100)); ?>...</code></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($exec['result_summary']): ?>
                        <small><?php echo htmlspecialchars(substr($exec['result_summary'], 0, 100)); ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($exec['is_error']): ?>
                        <span class="badge bg-danger">Error</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?php echo date('H:i:s', strtotime($exec['executed_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>