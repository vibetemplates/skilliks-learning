<?php
/**
 * AI Conversations Viewer
 * 
 * View stored AI conversation responses
 */

$page_title = 'AI Conversations';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Sprint.php';
require_once 'classes/Project.php';
require_once 'classes/AIResponseManager.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$db = getDB();
$sprintObj = new Sprint();
$projectObj = new Project();
$aiManager = new AIResponseManager();

// Get sprint ID from query params
$sprintId = $_GET['sprint_id'] ?? null;
$projectId = $_GET['project_id'] ?? null;

// Get filter parameters
$platform = $_GET['platform'] ?? null;
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Build query to get AI conversations
$query = "
    SELECT 
        s.*,
        r.final_response,
        r.total_duration_ms,
        r.total_cost_usd,
        r.total_input_tokens,
        r.total_output_tokens,
        r.tools_used_count,
        r.error_count,
        sp.name as sprint_name,
        p.name as project_name,
        pdp.prompt_text,
        pdp.title as prompt_title,
        wi.title as work_item_title
    FROM ai_sessions s
    LEFT JOIN ai_conversation_results r ON s.id = r.session_id
    LEFT JOIN sprints sp ON s.sprint_id = sp.id
    LEFT JOIN projects p ON sp.project_id = p.id
    LEFT JOIN project_dev_prompts pdp ON s.prompt_id = pdp.id
    LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
    WHERE 1=1
";

$params = [];

if ($sprintId) {
    $query .= " AND s.sprint_id = ?";
    $params[] = $sprintId;
}

if ($projectId) {
    $query .= " AND p.id = ?";
    $params[] = $projectId;
}

if ($platform) {
    $query .= " AND s.platform = ?";
    $params[] = $platform;
}

if ($dateFrom) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $dateTo;
}

// Check user permissions - only show conversations from projects they have access to
$query .= " AND EXISTS (
    SELECT 1 FROM team_members tm 
    JOIN projects p2 ON p2.team_id = tm.team_id
    WHERE tm.user_id = ? AND p2.id = p.id
)";
$params[] = $currentUserId;

$query .= " ORDER BY s.created_at DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "
    SELECT 
        s.platform,
        COUNT(*) as total_conversations,
        COUNT(CASE WHEN r.error_count > 0 THEN 1 END) as failed_conversations,
        SUM(r.total_cost_usd) as total_cost,
        SUM(r.total_input_tokens) as total_input_tokens,
        SUM(r.total_output_tokens) as total_output_tokens,
        AVG(r.total_duration_ms) as avg_duration_ms
    FROM ai_sessions s
    LEFT JOIN ai_conversation_results r ON s.id = r.session_id
    WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
";

$statsParams = [$dateFrom, $dateTo];

if ($sprintId) {
    $statsQuery .= " AND s.sprint_id = ?";
    $statsParams[] = $sprintId;
}

if ($projectId) {
    $statsQuery .= " AND EXISTS (SELECT 1 FROM sprints sp WHERE sp.project_id = ? AND sp.id = s.sprint_id)";
    $statsParams[] = $projectId;
}

$statsQuery .= " GROUP BY s.platform";

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$statistics = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
.conversation-card {
    transition: all 0.2s ease;
}
.conversation-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.platform-badge {
    font-size: 0.875rem;
    padding: 0.25rem 0.75rem;
}
.token-info {
    font-size: 0.875rem;
    color: #6c757d;
}
.response-preview {
    max-height: 100px;
    overflow: hidden;
    position: relative;
}
.response-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 30px;
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
}
</style>

<main class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3">
        <h1 class="h2">AI Conversations</h1>
        <div class="btn-toolbar">
            <?php if ($sprintId): 
                $sprint = $sprintObj->findById($sprintId);
            ?>
            <a href="sprint-dashboard.php?id=<?php echo $sprintId; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Sprint
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php 
        $totalStats = [
            'conversations' => 0,
            'failed' => 0,
            'cost' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0
        ];
        
        foreach ($statistics as $stat) {
            $totalStats['conversations'] += $stat['total_conversations'];
            $totalStats['failed'] += $stat['failed_conversations'] ?? 0;
            $totalStats['cost'] += $stat['total_cost'] ?? 0;
            $totalStats['input_tokens'] += $stat['total_input_tokens'] ?? 0;
            $totalStats['output_tokens'] += $stat['total_output_tokens'] ?? 0;
        }
        ?>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Conversations</h6>
                    <h3 class="mb-0"><?php echo number_format($totalStats['conversations']); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Failed</h6>
                    <h3 class="mb-0 text-danger"><?php echo number_format($totalStats['failed']); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Cost</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalStats['cost'], 4); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Tokens</h6>
                    <h3 class="mb-0"><?php echo number_format($totalStats['input_tokens'] + $totalStats['output_tokens']); ?></h3>
                    <small class="text-muted">
                        <?php echo number_format($totalStats['input_tokens']); ?> in / 
                        <?php echo number_format($totalStats['output_tokens']); ?> out
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <?php if ($sprintId): ?>
                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                <?php endif; ?>
                <?php if ($projectId): ?>
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label for="platform" class="form-label">Platform</label>
                    <select name="platform" id="platform" class="form-select">
                        <option value="">All Platforms</option>
                        <option value="claude" <?php echo $platform === 'claude' ? 'selected' : ''; ?>>Claude</option>
                        <option value="skilliks" <?php echo $platform === 'skilliks' ? 'selected' : ''; ?>>Skilliks</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?php echo $dateFrom; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?php echo $dateTo; ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Conversations List -->
    <div class="row">
        <?php if (empty($conversations)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No AI conversations found for the selected filters.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($conversations as $conversation): ?>
        <div class="col-12 mb-3">
            <div class="card conversation-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="card-title mb-1">
                                <?php if ($conversation['prompt_title']): ?>
                                    <?php echo htmlspecialchars($conversation['prompt_title']); ?>
                                <?php elseif ($conversation['work_item_title']): ?>
                                    <?php echo htmlspecialchars($conversation['work_item_title']); ?>
                                <?php else: ?>
                                    Conversation #<?php echo htmlspecialchars($conversation['id']); ?>
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted mb-2">
                                <?php if ($conversation['project_name']): ?>
                                    <strong><?php echo htmlspecialchars($conversation['project_name']); ?></strong>
                                    <?php if ($conversation['sprint_name']): ?>
                                        / <?php echo htmlspecialchars($conversation['sprint_name']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                • <?php echo date('M d, Y H:i', strtotime($conversation['created_at'])); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge platform-badge bg-<?php echo $conversation['platform'] === 'claude' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst($conversation['platform']); ?>
                            </span>
                            <?php if ($conversation['status'] === 'completed'): ?>
                            <span class="badge bg-success">Completed</span>
                            <?php elseif ($conversation['error_count'] > 0): ?>
                            <span class="badge bg-danger">Failed</span>
                            <?php else: ?>
                            <span class="badge bg-warning">In Progress</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($conversation['prompt_text']): ?>
                    <div class="mb-3">
                        <strong>Prompt:</strong>
                        <div class="response-preview">
                            <?php echo nl2br(htmlspecialchars(substr($conversation['prompt_text'], 0, 200))); ?>...
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($conversation['final_response']): ?>
                    <div class="mb-3">
                        <strong>Response:</strong>
                        <div class="response-preview">
                            <?php echo nl2br(htmlspecialchars(substr($conversation['final_response'], 0, 300))); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row token-info">
                        <div class="col-md-3">
                            <i class="bi bi-clock"></i> Duration: 
                            <?php echo $conversation['total_duration_ms'] ? 
                                number_format($conversation['total_duration_ms'] / 1000, 2) . 's' : 'N/A'; ?>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-tools"></i> Tools Used: 
                            <?php echo number_format($conversation['tools_used_count'] ?? 0); ?>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-coin"></i> Cost: 
                            $<?php echo number_format($conversation['total_cost_usd'] ?? 0, 4); ?>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-hash"></i> Tokens: 
                            <?php echo number_format(($conversation['total_input_tokens'] ?? 0) + 
                                                    ($conversation['total_output_tokens'] ?? 0)); ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="ai-conversation-detail.php?id=<?php echo urlencode($conversation['id']); ?>" 
                           class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> View Full Conversation
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>