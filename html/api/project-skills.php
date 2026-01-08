<?php
/**
 * Project Skills API
 * 
 * Handles CRUD operations for project skill requirements
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/Project.php';

// Require login
requireLogin();

$userObj = new User();
$projectObj = new Project();
$currentUserId = getCurrentUserId();

// Check if user is admin
if (!$userObj->isAdmin($currentUserId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];
$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

// Verify project exists
$project = $projectObj->findById($projectId);
if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

switch ($method) {
    case 'GET':
        // Get project skills and display management interface
        displaySkillManagement($projectId);
        break;
        
    case 'POST':
        // Add skill to project
        $data = json_decode(file_get_contents('php://input'), true);
        addSkillToProject($projectId, $data, $currentUserId);
        break;
        
    case 'DELETE':
        // Remove skill from project
        $data = json_decode(file_get_contents('php://input'), true);
        removeSkillFromProject($projectId, $data);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function displaySkillManagement($projectId) {
    $db = getDB();
    
    // Get current project skills
    $stmt = $db->prepare("
        SELECT ps.*, s.name, s.category, s.description,
               u.first_name, u.last_name
        FROM project_skills ps
        JOIN skills s ON ps.skill_id = s.id
        LEFT JOIN users u ON ps.added_by = u.id
        WHERE ps.project_id = ?
        ORDER BY ps.importance_level DESC, s.category, s.name
    ");
    $stmt->execute([$projectId]);
    $projectSkills = $stmt->fetchAll();
    
    // Get all available skills grouped by category
    $stmt = $db->prepare("
        SELECT id, name, category, description
        FROM skills
        WHERE id NOT IN (
            SELECT skill_id FROM project_skills WHERE project_id = ?
        )
        ORDER BY category, name
    ");
    $stmt->execute([$projectId]);
    $availableSkills = $stmt->fetchAll();
    
    // Group skills by category
    $skillsByCategory = [];
    foreach ($availableSkills as $skill) {
        $skillsByCategory[$skill['category']][] = $skill;
    }
    
    ?>
    <div class="row">
        <div class="col-md-7">
            <h6>Current Skills</h6>
            <?php if (empty($projectSkills)): ?>
                <p class="text-muted">No skills assigned to this project yet.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($projectSkills as $skill): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($skill['name']); ?></h6>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            <i class="bi bi-folder"></i> <?php echo htmlspecialchars($skill['category']); ?>
                                        </small>
                                    </p>
                                    <?php if ($skill['description']): ?>
                                        <p class="mb-1"><small><?php echo htmlspecialchars($skill['description']); ?></small></p>
                                    <?php endif; ?>
                                    <div>
                                        <span class="badge bg-<?php echo $skill['importance_level'] === 'required' ? 'danger' : ($skill['importance_level'] === 'preferred' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($skill['importance_level']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Added <?php if ($skill['first_name']): ?>by <?php echo htmlspecialchars($skill['first_name'] . ' ' . $skill['last_name']); ?><?php endif; ?>
                                        on <?php echo date('M j, Y', strtotime($skill['added_at'])); ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-danger remove-skill-btn" data-skill-id="<?php echo $skill['skill_id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-5">
            <h6>Add Skill</h6>
            <div class="mb-3">
                <label for="skill-select" class="form-label">Select Skill</label>
                <select class="form-control" id="skill-select">
                    <option value="">-- Select a skill --</option>
                    <?php foreach ($skillsByCategory as $category => $skills): ?>
                        <optgroup label="<?php echo htmlspecialchars($category); ?>">
                            <?php foreach ($skills as $skill): ?>
                                <option value="<?php echo $skill['id']; ?>">
                                    <?php echo htmlspecialchars($skill['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="skill-importance" class="form-label">Importance</label>
                <select class="form-control" id="skill-importance">
                    <option value="required">Required</option>
                    <option value="preferred">Preferred</option>
                    <option value="optional">Optional</option>
                </select>
            </div>
            
            
            <button class="btn btn-primary" id="add-skill-btn">
                <i class="bi bi-plus-circle"></i> Add Skill
            </button>
        </div>
    </div>
    <?php
}

function addSkillToProject($projectId, $data, $userId) {
    $db = getDB();
    
    $skillId = $data['skill_id'] ?? null;
    $importance_level = $data['importance'] ?? 'required';
    
    if (!$skillId) {
        echo json_encode(['success' => false, 'message' => 'Skill ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO project_skills 
            (project_id, skill_id, importance_level, added_by, added_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$projectId, $skillId, $importance_level, $userId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo json_encode(['success' => false, 'message' => 'This skill is already assigned to the project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add skill']);
        }
    }
}

function removeSkillFromProject($projectId, $data) {
    $db = getDB();
    
    $skillId = $data['skill_id'] ?? null;
    
    if (!$skillId) {
        echo json_encode(['success' => false, 'message' => 'Skill ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            DELETE FROM project_skills 
            WHERE project_id = ? AND skill_id = ?
        ");
        
        $stmt->execute([$projectId, $skillId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove skill']);
    }
}
?>