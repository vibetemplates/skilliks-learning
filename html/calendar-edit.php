<?php
/**
 * Calendar Edit Page
 * 
 * Allows editing of calendar events
 */

$page_title = 'Edit Event';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Calendar.php';
require_once 'classes/Project.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventId) {
    setFlashMessage('error', 'Invalid event ID');
    header('Location: /calendar');
    exit;
}

// Initialize calendar
$calendar = new Calendar();
$project = new Project();

// Get event details
$event = $calendar->getEventById($eventId);

if (!$event) {
    setFlashMessage('error', 'Event not found');
    header('Location: /calendar');
    exit;
}

// Check if user can manage events
if (!$calendar->canManageEvents($currentUserId, $event['community_id'])) {
    setFlashMessage('error', 'You do not have permission to edit this event');
    header('Location: /calendar');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $requiredFields = ['title', 'start_datetime', 'end_datetime'];
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    
    // Validate dates
    if (empty($errors)) {
        $startDate = new DateTime($_POST['start_datetime']);
        $endDate = new DateTime($_POST['end_datetime']);
        
        if ($endDate < $startDate) {
            $errors[] = 'End date must be after start date';
        }
    }
    
    // Validate zoom link if provided
    if (!empty($_POST['zoom_link']) && !filter_var($_POST['zoom_link'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid Zoom link format';
    }
    
    if (empty($errors)) {
        // Prepare update data
        $updateData = [
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? null,
            'start_datetime' => $_POST['start_datetime'],
            'end_datetime' => $_POST['end_datetime'],
            'all_day' => isset($_POST['all_day']) ? 1 : 0,
            'location' => $_POST['location'] ?? null,
            'zoom_link' => $_POST['zoom_link'] ?? null,
            'color' => $_POST['color'] ?? '#0d6efd',
            'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
            'course_id' => !empty($_POST['course_id']) ? $_POST['course_id'] : null
        ];
        
        if ($calendar->updateEvent($eventId, $updateData)) {
            setFlashMessage('success', 'Event updated successfully');
            header('Location: /calendar');
            exit;
        } else {
            $errors[] = 'Failed to update event';
        }
    }
}

// Get user's projects
$userProjects = $project->getUserProjects($currentUserId);

// Get courses
$db = getDB();
$stmt = $db->prepare("
    SELECT id, title 
    FROM courses 
    WHERE community_id = :community_id 
    AND status = 'published'
    ORDER BY title ASC
");
$stmt->execute([':community_id' => $event['community_id']]);
$courses = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Event</h1>
                <a href="/calendar" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Calendar
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? $event['title']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_datetime" class="form-label">Start Date/Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($_POST['start_datetime'] ?? $event['start_datetime'])); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_datetime" class="form-label">End Date/Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="end_datetime" name="end_datetime" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($_POST['end_datetime'] ?? $event['end_datetime'])); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_day" name="all_day"
                                       <?php echo (isset($_POST['all_day']) || $event['all_day']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="all_day">
                                    All Day Event
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $event['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? $event['location'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="zoom_link" class="form-label">Zoom Link</label>
                                    <input type="url" class="form-control" id="zoom_link" name="zoom_link" 
                                           placeholder="https://zoom.us/j/..." 
                                           value="<?php echo htmlspecialchars($_POST['zoom_link'] ?? $event['zoom_link'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="project_id" class="form-label">Link to Project (Optional)</label>
                                    <select class="form-select" id="project_id" name="project_id">
                                        <option value="">None</option>
                                        <?php foreach ($userProjects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>"
                                                <?php echo ($proj['id'] == ($_POST['project_id'] ?? $event['project_id'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($proj['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Link to Course (Optional)</label>
                                    <select class="form-select" id="course_id" name="course_id">
                                        <option value="">None</option>
                                        <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"
                                                <?php echo ($course['id'] == ($_POST['course_id'] ?? $event['course_id'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Event Color</label>
                            <input type="color" class="form-control form-control-color" id="color" 
                                   name="color" value="<?php echo htmlspecialchars($_POST['color'] ?? $event['color'] ?? '#0d6efd'); ?>">
                        </div>
                        
                        <?php if ($event['recurrence_type'] !== 'none'): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This is a recurring event. Changes will only affect this instance.
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                <i class="bi bi-trash"></i> Delete Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('all_day').addEventListener('change', function() {
    const startInput = document.getElementById('start_datetime');
    const endInput = document.getElementById('end_datetime');
    
    if (this.checked) {
        // Convert to date only
        const startDate = startInput.value.split('T')[0];
        const endDate = endInput.value.split('T')[0];
        startInput.type = 'date';
        endInput.type = 'date';
        startInput.value = startDate;
        endInput.value = endDate;
    } else {
        // Convert back to datetime
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
        // Add default time if needed
        if (!startInput.value.includes('T')) {
            startInput.value += 'T09:00';
        }
        if (!endInput.value.includes('T')) {
            endInput.value += 'T10:00';
        }
    }
});

function confirmDelete() {
    if (confirm('Are you sure you want to delete this event?')) {
        fetch('/api/calendar-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                event_id: <?php echo $eventId; ?>,
                delete_recurring: false
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/calendar';
            } else {
                alert('Error deleting event: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete event');
        });
    }
}

// Initialize all_day checkbox behavior on load
if (document.getElementById('all_day').checked) {
    document.getElementById('start_datetime').type = 'date';
    document.getElementById('end_datetime').type = 'date';
}
</script>

<?php require_once 'includes/footer.php'; ?>