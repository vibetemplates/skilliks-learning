<?php
/**
 * Project Courses API
 * 
 * Handles CRUD operations for project course assignments
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
        // Get project courses and display management interface
        displayCourseManagement($projectId);
        break;
        
    case 'POST':
        // Add course to project
        $data = json_decode(file_get_contents('php://input'), true);
        addCourseToProject($projectId, $data, $currentUserId);
        break;
        
    case 'DELETE':
        // Remove course from project
        $data = json_decode(file_get_contents('php://input'), true);
        removeCourseFromProject($projectId, $data);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function displayCourseManagement($projectId) {
    $db = getDB();
    
    // Get current project courses
    $stmt = $db->prepare("
        SELECT pc.*, c.title, c.course_code, c.short_description,
               u.first_name, u.last_name
        FROM project_course_assignments pc
        JOIN courses c ON pc.course_id = c.id
        LEFT JOIN users u ON pc.assigned_by = u.id
        WHERE pc.project_id = ?
        ORDER BY pc.assignment_type DESC, c.title
    ");
    $stmt->execute([$projectId]);
    $projectCourses = $stmt->fetchAll();
    
    // Get all available courses
    $stmt = $db->prepare("
        SELECT id, title, course_code, short_description
        FROM courses
        WHERE id NOT IN (
            SELECT course_id FROM project_course_assignments WHERE project_id = ?
        )
        ORDER BY title
    ");
    $stmt->execute([$projectId]);
    $availableCourses = $stmt->fetchAll();
    
    ?>
    <div class="row">
        <div class="col-md-7">
            <h6>Current Courses</h6>
            <?php if (empty($projectCourses)): ?>
                <p class="text-muted">No courses assigned to this project yet.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($projectCourses as $course): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <?php if ($course['course_code']): ?>
                                        <p class="mb-1"><small class="text-muted"><?php echo htmlspecialchars($course['course_code']); ?></small></p>
                                    <?php endif; ?>
                                    <?php if ($course['short_description']): ?>
                                        <p class="mb-1"><small><?php echo htmlspecialchars($course['short_description']); ?></small></p>
                                    <?php endif; ?>
                                    <div>
                                        <span class="badge bg-<?php echo $course['assignment_type'] === 'required' ? 'danger' : ($course['assignment_type'] === 'recommended' ? 'primary' : 'secondary'); ?>">
                                            <?php echo ucfirst($course['assignment_type']); ?>
                                        </span>
                                        <?php if ($course['notes']): ?>
                                            <small class="text-muted ms-2"><?php echo htmlspecialchars($course['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        Added by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                        on <?php echo date('M j, Y', strtotime($course['assigned_at'])); ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-danger remove-course-btn" data-course-id="<?php echo $course['course_id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-5">
            <h6>Add Course</h6>
            <div class="mb-3">
                <label for="course-select" class="form-label">Select Course</label>
                <select class="form-control" id="course-select">
                    <option value="">-- Select a course --</option>
                    <?php foreach ($availableCourses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['title']); ?>
                            <?php if ($course['course_code']): ?>
                                (<?php echo htmlspecialchars($course['course_code']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="assignment-type" class="form-label">Assignment Type</label>
                <select class="form-control" id="assignment-type">
                    <option value="recommended">Recommended</option>
                    <option value="required">Required</option>
                    <option value="optional">Optional</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="course-notes" class="form-label">Notes (Optional)</label>
                <textarea class="form-control" id="course-notes" rows="2" placeholder="Any additional notes about this course assignment..."></textarea>
            </div>
            
            <button class="btn btn-primary" id="add-course-btn">
                <i class="bi bi-plus-circle"></i> Add Course
            </button>
        </div>
    </div>
    <?php
}

function addCourseToProject($projectId, $data, $userId) {
    $db = getDB();
    
    $courseId = $data['course_id'] ?? null;
    $assignmentType = $data['assignment_type'] ?? 'recommended';
    $notes = $data['notes'] ?? null;
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO project_course_assignments 
            (project_id, course_id, assignment_type, notes, assigned_by, assigned_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$projectId, $courseId, $assignmentType, $notes, $userId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo json_encode(['success' => false, 'message' => 'This course is already assigned to the project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add course']);
        }
    }
}

function removeCourseFromProject($projectId, $data) {
    $db = getDB();
    
    $courseId = $data['course_id'] ?? null;
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            DELETE FROM project_course_assignments 
            WHERE project_id = ? AND course_id = ?
        ");
        
        $stmt->execute([$projectId, $courseId]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove course']);
    }
}
?>