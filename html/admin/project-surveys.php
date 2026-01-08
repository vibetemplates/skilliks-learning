<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Survey.php';
require_once 'classes/ProjectSurvey.php';

// Check admin access
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;
$survey = new Survey();
$projectSurvey = new ProjectSurvey();

// Get database connection
$pdo = getDB();

// Get current community ID
$currentCommunityId = getCurrentCommunityId();

// Get project surveys for current community only
$stmt = $pdo->prepare("
    SELECT 
        ps.*,
        s.name as survey_name,
        p.name as project_name,
        p.id as project_id,
        CONCAT(u.first_name, ' ', u.last_name) as approved_by_username,
        (SELECT COUNT(DISTINCT user_id) FROM survey_responses WHERE survey_id = ps.survey_id) as response_count
    FROM project_surveys ps
    JOIN surveys s ON ps.survey_id = s.id
    JOIN projects p ON ps.project_id = p.id
    LEFT JOIN users u ON ps.approved_by = u.id
    WHERE p.community_id = ?
    ORDER BY ps.created_at DESC
");
$stmt->execute([$currentCommunityId]);
$projectSurveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get survey statistics
$stats = [
    'total_project_surveys' => count($projectSurveys),
    'approved_surveys' => 0,
    'pending_surveys' => 0,
    'with_recommendations' => 0
];

foreach ($projectSurveys as $ps) {
    if ($ps['approved_at']) {
        $stats['approved_surveys']++;
    } else {
        $stats['pending_surveys']++;
    }
    if ($ps['architecture_recommendations'] || $ps['tech_stack_recommendations']) {
        $stats['with_recommendations']++;
    }
}

include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                <h1 class="mb-0">
                    <i class="bi bi-clipboard-data"></i> Project Survey Management
                </h1>
                <a href="/admin/survey-template-create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Create New Survey Template
                </a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Surveys</h5>
                            <h2><?php echo $stats['total_project_surveys']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Approved</h5>
                            <h2><?php echo $stats['approved_surveys']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Pending Review</h5>
                            <h2><?php echo $stats['pending_surveys']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">With AI Recommendations</h5>
                            <h2><?php echo $stats['with_recommendations']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Surveys Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Project Surveys</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Survey</th>
                                    <th>Responses</th>
                                    <th>AI Recommendations</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($projectSurveys)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No project surveys found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($projectSurveys as $ps): ?>
                                        <tr>
                                            <td>
                                                <a href="/project-detail?id=<?php echo $ps['project_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($ps['project_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($ps['survey_name']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $ps['response_count']; ?> responses</span>
                                            </td>
                                            <td>
                                                <?php if ($ps['architecture_recommendations'] || $ps['tech_stack_recommendations']): ?>
                                                    <span class="badge bg-success">Generated</span>
                                                    <?php if ($ps['generated_at']): ?>
                                                        <br><small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($ps['generated_at'])); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not generated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ps['approved_at']): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                    <br><small class="text-muted">by <?php echo htmlspecialchars($ps['approved_by_username']); ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($ps['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="/project-survey?project_id=<?php echo $ps['project_id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       target="_blank"
                                                       title="View Survey">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($ps['response_count'] > 0): ?>
                                                        <a href="/admin/project-survey-responses.php?survey_id=<?php echo $ps['survey_id']; ?>&project_id=<?php echo $ps['project_id']; ?>" 
                                                           class="btn btn-outline-info"
                                                           title="View Responses">
                                                            <i class="bi bi-chat-dots"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($ps['architecture_recommendations'] || $ps['tech_stack_recommendations']): ?>
                                                        <a href="/admin/project-survey-recommendations.php?id=<?php echo $ps['id']; ?>" 
                                                           class="btn btn-outline-success"
                                                           title="View AI Recommendations">
                                                            <i class="bi bi-robot"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Survey Templates -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Survey Templates</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get current community ID
                    $currentCommunityId = getCurrentCommunityId();
                    
                    // Get project survey templates for current community only
                    $stmt = $pdo->prepare("
                        SELECT s.*, 
                               (SELECT COUNT(*) FROM survey_sections WHERE survey_id = s.id) as section_count,
                               (SELECT COUNT(*) FROM survey_questions sq 
                                JOIN survey_sections ss ON sq.section_id = ss.id 
                                WHERE ss.survey_id = s.id) as question_count
                        FROM surveys s 
                        WHERE s.type = 'project' 
                        AND s.community_id = ?
                        ORDER BY s.created_at DESC
                    ");
                    $stmt->execute([$currentCommunityId]);
                    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Template Name</th>
                                    <th>Description</th>
                                    <th>Sections</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($template['name']); ?></td>
                                        <td><?php echo htmlspecialchars($template['description']); ?></td>
                                        <td><?php echo $template['section_count']; ?></td>
                                        <td><?php echo $template['question_count']; ?></td>
                                        <td>
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/admin/survey-template-edit?id=<?php echo $template['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>