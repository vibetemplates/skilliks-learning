<?php
/**
 * Admin Course Management Page
 * 
 * Allows admins to manage courses
 */

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';
require_once 'includes/session.php';

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: /dashboard');
    exit;
}

$page_title = 'Course Management';
$current_page = 'courses';

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['course_id']) && isset($_POST['status'])) {
        $courseId = (int)$_POST['course_id'];
        $status = $_POST['status'];
        
        // Validate status
        if (in_array($status, ['draft', 'published', 'archived'])) {
            $stmt = $db->prepare("UPDATE courses SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $courseId])) {
                setFlashMessage('success', 'Course status updated successfully.');
            } else {
                setFlashMessage('error', 'Failed to update course status.');
            }
        }
        
        header('Location: /admin/courses');
        exit;
    }
}

// Get all courses with creator info and enrollment count
$query = "
    SELECT 
        c.*,
        u.first_name,
        u.last_name,
        COUNT(DISTINCT ce.user_id) as enrollment_count,
        COUNT(DISTINCT l.id) as lesson_count
    FROM courses c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN lessons l ON c.id = l.course_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$courses = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Course Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/admin-course-create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Course
            </a>
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Courses</h5>
        </div>
        <div class="card-body">
            <?php if (empty($courses)): ?>
                <p class="text-muted text-center py-4">No courses found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Enrollments</th>
                                <th>Lessons</th>
                                <th>Created By</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                    <?php if ($course['featured']): ?>
                                        <span class="badge bg-warning text-dark ms-1">Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($course['course_code'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($course['category'] ?: '-'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" 
                                                onchange="this.form.submit()" style="width: auto;">
                                            <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                            <option value="archived" <?php echo $course['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $course['enrollment_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $course['lesson_count']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($course['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="/course-detail?id=<?php echo $course['id']; ?>" 
                                           class="btn btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/admin-course-edit.php?id=<?php echo $course['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="/admin-lesson-create.php?course_id=<?php echo $course['id']; ?>" 
                                           class="btn btn-outline-success" title="Add Lesson">
                                            <i class="bi bi-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>