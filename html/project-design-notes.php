<?php
/**
 * Project Design Notes Page
 * 
 * Manage project design notes and documentation
 */

$page_title = 'Project Design Notes';
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
    setFlashMessage('error', 'You must be a project member to view design notes.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'add') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $noteType = $_POST['note_type'] ?? 'other';
        
        if ($title && $content) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO project_design_notes (project_id, title, content, note_type, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$projectId, $title, $content, $noteType, $currentUserId]);
                setFlashMessage('success', 'Design note added successfully!');
            } catch (PDOException $e) {
                error_log("Error adding design note: " . $e->getMessage());
                setFlashMessage('error', 'Failed to add design note.');
            }
        } else {
            setFlashMessage('error', 'Title and content are required.');
        }
        header('Location: /project-design-notes.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'update') {
        $noteId = $_POST['note_id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $noteType = $_POST['note_type'] ?? 'other';
        
        if ($noteId && $title && $content) {
            try {
                // Check if user can edit (creator or admin)
                $stmt = $db->prepare("SELECT created_by FROM project_design_notes WHERE id = ? AND project_id = ?");
                $stmt->execute([$noteId, $projectId]);
                $note = $stmt->fetch();
                
                if ($note && ($note['created_by'] == $currentUserId || $isProjectManagerOrAdmin)) {
                    $stmt = $db->prepare("
                        UPDATE project_design_notes 
                        SET title = ?, content = ?, note_type = ?, updated_at = NOW() 
                        WHERE id = ? AND project_id = ?
                    ");
                    $stmt->execute([$title, $content, $noteType, $noteId, $projectId]);
                    setFlashMessage('success', 'Design note updated successfully!');
                } else {
                    setFlashMessage('error', 'You do not have permission to edit this note.');
                }
            } catch (PDOException $e) {
                error_log("Error updating design note: " . $e->getMessage());
                setFlashMessage('error', 'Failed to update design note.');
            }
        }
        header('Location: /project-design-notes.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'delete') {
        $noteId = $_POST['note_id'] ?? null;
        
        if ($noteId) {
            try {
                // Check if user can delete (creator or admin)
                $stmt = $db->prepare("SELECT created_by FROM project_design_notes WHERE id = ? AND project_id = ?");
                $stmt->execute([$noteId, $projectId]);
                $note = $stmt->fetch();
                
                if ($note && ($note['created_by'] == $currentUserId || $isProjectManagerOrAdmin)) {
                    $stmt = $db->prepare("DELETE FROM project_design_notes WHERE id = ? AND project_id = ?");
                    $stmt->execute([$noteId, $projectId]);
                    setFlashMessage('success', 'Design note deleted successfully!');
                } else {
                    setFlashMessage('error', 'You do not have permission to delete this note.');
                }
            } catch (PDOException $e) {
                error_log("Error deleting design note: " . $e->getMessage());
                setFlashMessage('error', 'Failed to delete design note.');
            }
        }
        header('Location: /project-design-notes.php?project=' . $projectId);
        exit;
    }
}

// Get design notes
$noteTypeFilter = $_GET['type'] ?? '';
try {
    $db = getDB();
    $sql = "
        SELECT n.*, u.first_name, u.last_name 
        FROM project_design_notes n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.project_id = ?
    ";
    $params = [$projectId];
    
    if ($noteTypeFilter) {
        $sql .= " AND n.note_type = ?";
        $params[] = $noteTypeFilter;
    }
    
    $sql .= " ORDER BY n.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    $notes = [];
}

$noteTypeLabels = [
    'architecture' => ['label' => 'Architecture', 'icon' => 'bi-diagram-3', 'color' => 'primary'],
    'ui_ux' => ['label' => 'UI/UX', 'icon' => 'bi-palette', 'color' => 'success'],
    'database' => ['label' => 'Database', 'icon' => 'bi-database', 'color' => 'warning'],
    'api' => ['label' => 'API', 'icon' => 'bi-plug', 'color' => 'info'],
    'workflow' => ['label' => 'Workflow', 'icon' => 'bi-diagram-2', 'color' => 'danger'],
    'other' => ['label' => 'Other', 'icon' => 'bi-file-text', 'color' => 'secondary']
];

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Design Notes</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Design Notes</h1>
        <div class="btn-group" role="group">
            <a href="/project-survey?project_id=<?php echo $projectId; ?>&survey_type=design-notes" class="btn btn-outline-primary">
                <i class="bi bi-clipboard-check"></i> Design Survey
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                <i class="bi bi-plus-circle"></i> Add Note
            </button>
        </div>
    </div>

    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo !$noteTypeFilter ? 'active' : ''; ?>" 
               href="/project-design-notes.php?project=<?php echo $projectId; ?>">All Notes</a>
        </li>
        <?php foreach ($noteTypeLabels as $type => $config): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $noteTypeFilter === $type ? 'active' : ''; ?>" 
                   href="/project-design-notes.php?project=<?php echo $projectId; ?>&type=<?php echo $type; ?>">
                    <i class="<?php echo $config['icon']; ?>"></i> <?php echo $config['label']; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Design Notes List -->
    <div class="row">
        <?php if (empty($notes)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-text text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">
                            <?php if ($noteTypeFilter): ?>
                                No <?php echo $noteTypeLabels[$noteTypeFilter]['label']; ?> notes found.
                            <?php else: ?>
                                No design notes have been created for this project yet.
                            <?php endif; ?>
                        </p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="bi bi-plus-circle"></i> Add First Note
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="<?php echo $noteTypeLabels[$note['note_type']]['icon']; ?> text-<?php echo $noteTypeLabels[$note['note_type']]['color']; ?>"></i>
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </h5>
                                <span class="badge bg-<?php echo $noteTypeLabels[$note['note_type']]['color']; ?>">
                                    <?php echo $noteTypeLabels[$note['note_type']]['label']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="note-content mb-3" style="max-height: 200px; overflow-y: auto;">
                                <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    By <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?> • 
                                    <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                                    <?php if ($note['updated_at'] > $note['created_at']): ?>
                                        <br><em>Updated: <?php echo date('M j, Y g:i A', strtotime($note['updated_at'])); ?></em>
                                    <?php endif; ?>
                                </small>
                                <?php if ($note['created_by'] == $currentUserId || $isProjectManagerOrAdmin): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="editNote(<?php echo htmlspecialchars(json_encode($note)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this note?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">Add Design Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="noteForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="note_id" value="" id="noteId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_type" class="form-label">Type *</label>
                        <select class="form-select" id="note_type" name="note_type" required>
                            <option value="architecture">Architecture</option>
                            <option value="ui_ux">UI/UX</option>
                            <option value="database">Database</option>
                            <option value="api">API</option>
                            <option value="workflow">Workflow</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <span id="submitBtnText">Add Note</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editNote(note) {
    document.getElementById('addNoteModalLabel').textContent = 'Edit Design Note';
    document.getElementById('formAction').value = 'update';
    document.getElementById('noteId').value = note.id;
    document.getElementById('title').value = note.title;
    document.getElementById('note_type').value = note.note_type;
    document.getElementById('content').value = note.content;
    document.getElementById('submitBtnText').textContent = 'Update Note';
    
    new bootstrap.Modal(document.getElementById('addNoteModal')).show();
}

// Reset form when modal is closed
document.getElementById('addNoteModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('noteForm').reset();
    document.getElementById('addNoteModalLabel').textContent = 'Add Design Note';
    document.getElementById('formAction').value = 'add';
    document.getElementById('noteId').value = '';
    document.getElementById('submitBtnText').textContent = 'Add Note';
});
</script>

<?php require_once 'includes/footer.php'; ?>