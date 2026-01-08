<?php
/**
 * Project Features Page
 * 
 * Display and manage recommended features for a project
 */

$page_title = 'Project Features';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

// Get project ID
$projectId = $_GET['project'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$project = $projectObj->findById($projectId);

if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$currentUserId = getCurrentUserId();
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

$userObj = new User();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Require member access
if (!$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to view features.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'update_status' && ($isProjectManagerOrAdmin || $isCreator)) {
        $featureId = $_POST['feature_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if ($featureId && $status) {
            try {
                $stmt = $db->prepare("UPDATE features SET status = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$status, $featureId, $projectId]);
                setFlashMessage('success', 'Feature status updated successfully!');
            } catch (PDOException $e) {
                error_log("Error updating feature status: " . $e->getMessage());
                setFlashMessage('error', 'Failed to update feature status.');
            }
        }
        header('Location: /project-features.php?project=' . $projectId);
        exit;
    }
}

// Get project features
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT f.*, u.first_name, u.last_name 
        FROM features f
        LEFT JOIN users u ON f.submitted_by = u.id
        WHERE f.project_id = ? 
        ORDER BY 
            CASE f.priority 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            f.created_at DESC
    ");
    $stmt->execute([$projectId]);
    $features = $stmt->fetchAll();
} catch (PDOException $e) {
    $features = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Features</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Recommended Features</h1>
        <button type="button" class="btn btn-primary" onclick="showFeatureModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
            <i class="bi bi-plus-circle"></i> Add Feature
        </button>
    </div>

    <!-- Features List -->
    <div class="row">
        <?php if (empty($features)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-lightbulb text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No feature recommendations yet.</p>
                        <button type="button" class="btn btn-primary" onclick="showFeatureModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                            <i class="bi bi-lightbulb"></i> Recommend First Feature
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Priority</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Submitted By</th>
                                        <th>Date</th>
                                        <th>Votes</th>
                                        <th>Status</th>
                                        <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($features as $feature): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $feature['priority'] === 'critical' ? 'dark' : ($feature['priority'] === 'high' ? 'danger' : ($feature['priority'] === 'medium' ? 'warning' : 'info')); ?>">
                                                    <?php echo ucfirst($feature['priority']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($feature['title']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(substr($feature['description'], 0, 100) . (strlen($feature['description']) > 100 ? '...' : '')); ?></td>
                                            <td><?php echo htmlspecialchars($feature['first_name'] . ' ' . $feature['last_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($feature['created_at'])); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-outline-success vote-btn" 
                                                            data-type="feature" data-id="<?php echo $feature['id']; ?>" data-vote="up"
                                                            title="Upvote this feature">
                                                        <i class="bi bi-arrow-up"></i>
                                                    </button>
                                                    <span class="mx-2 vote-count" id="vote-count-feature-<?php echo $feature['id']; ?>">
                                                        <?php echo $feature['vote_count'] ?? 0; ?>
                                                    </span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger vote-btn" 
                                                            data-type="feature" data-id="<?php echo $feature['id']; ?>" data-vote="down"
                                                            title="Downvote this feature">
                                                        <i class="bi bi-arrow-down"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $feature['status'] === 'approved' ? 'success' : ($feature['status'] === 'in_progress' ? 'primary' : ($feature['status'] === 'rejected' ? 'danger' : 'secondary')); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $feature['status'])); ?>
                                                </span>
                                            </td>
                                            <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="bi bi-three-dots"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><h6 class="dropdown-header">Update Status</h6></li>
                                                            <?php foreach (['pending', 'approved', 'in_progress', 'rejected'] as $status): ?>
                                                                <?php if ($status !== $feature['status']): ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="update_status">
                                                                            <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Feature Recommendation Modal -->
<div class="modal fade" id="featureModal" tabindex="-1" aria-labelledby="featureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="featureModalLabel">
                    <i class="bi bi-lightbulb"></i> Recommend Feature
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    Recommending feature for: <strong><span id="feature-project-name">Project</span></strong>
                </div>
                
                <form id="featureForm">
                    <input type="hidden" id="feature-project-id" name="project_id">
                    
                    <div class="mb-3">
                        <label for="feature-title" class="form-label">Feature Title *</label>
                        <input type="text" class="form-control" id="feature-title" name="title" required
                               placeholder="Brief descriptive title for the feature">
                    </div>
                    
                    <div class="mb-3">
                        <label for="feature-description" class="form-label">Description *</label>
                        <textarea class="form-control" id="feature-description" name="description" rows="4" required
                                  placeholder="Detailed description of the feature, its benefits, and how it should work..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feature-priority" class="form-label">Priority</label>
                        <select class="form-select" id="feature-priority" name="priority">
                            <option value="low">Low - Nice to have</option>
                            <option value="medium" selected>Medium - Would improve workflow</option>
                            <option value="high">High - Important for project success</option>
                            <option value="critical">Critical - Project depends on this</option>
                        </select>
                    </div>
                </form>
                
                <div id="feature-message"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-feature-btn" onclick="submitFeature()">
                    <i class="bi bi-lightbulb"></i> Submit Recommendation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Feature modal functions
function showFeatureModal(projectId, projectName) {
    document.getElementById('feature-project-name').textContent = projectName;
    document.getElementById('feature-project-id').value = projectId;
    
    // Reset form
    document.getElementById('featureForm').reset();
    document.getElementById('feature-message').innerHTML = '';
    
    new bootstrap.Modal(document.getElementById('featureModal')).show();
}

async function submitFeature() {
    const title = document.getElementById('feature-title').value.trim();
    const description = document.getElementById('feature-description').value.trim();
    const priority = document.getElementById('feature-priority').value;
    const projectId = document.getElementById('feature-project-id').value;
    
    if (!title || !description) {
        document.getElementById('feature-message').innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Please fill in all required fields.
            </div>
        `;
        return;
    }
    
    const submitBtn = document.getElementById('submit-feature-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
    
    try {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);
        formData.append('priority', priority);
        formData.append('project_id', projectId);
        formData.append('action', 'create');
        
        const response = await fetch('/api/feature-create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('feature-message').innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Feature recommendation submitted successfully!
                </div>
            `;
            
            // Close modal and refresh page after success
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('featureModal')).hide();
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(result.error || 'Failed to submit feature recommendation');
        }
        
    } catch (error) {
        document.getElementById('feature-message').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> ${error.message}
            </div>
        `;
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Voting functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.vote-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const type = this.dataset.type;
            const id = this.dataset.id;
            const voteType = this.dataset.vote;
            
            const isCurrentlyActive = 
                (voteType === 'up' && this.classList.contains('btn-success')) ||
                (voteType === 'down' && this.classList.contains('btn-danger'));
            
            const action = isCurrentlyActive ? 'unvote' : 'vote';
            
            try {
                const response = await fetch('/api/vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        id: parseInt(id),
                        vote_type: voteType,
                        action: action
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const countElement = document.getElementById(`vote-count-${type}-${id}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    const upButton = this.parentElement.querySelector('[data-vote="up"]');
                    const downButton = this.parentElement.querySelector('[data-vote="down"]');
                    
                    upButton.classList.remove('btn-success');
                    upButton.classList.add('btn-outline-success');
                    downButton.classList.remove('btn-danger');
                    downButton.classList.add('btn-outline-danger');
                    
                    if (result.user_vote === 'up') {
                        upButton.classList.remove('btn-outline-success');
                        upButton.classList.add('btn-success');
                    } else if (result.user_vote === 'down') {
                        downButton.classList.remove('btn-outline-danger');
                        downButton.classList.add('btn-danger');
                    }
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Voting error:', error);
                alert('Error processing vote. Please try again.');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>