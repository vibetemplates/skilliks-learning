<?php
$pageTitle = 'Generate All Missing Skills Drills';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/SkillsDrill.php';
require_once '../classes/OpenAISkillsDrillGenerator.php';
require_once '../classes/User.php';

// Require admin
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    setFlashMessage('error', 'Admin access required');
    redirect('/dashboard');
}

$db = Database::getInstance()->getConnection();

// Process generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $lessonIds = $_POST['lesson_ids'] ?? [];
    
    if (empty($lessonIds)) {
        setFlashMessage('error', 'Please select at least one lesson');
    } else {
        $_SESSION['generating_drills'] = true;
        $_SESSION['lessons_to_generate'] = $lessonIds;
        $_SESSION['generation_progress'] = 0;
        $_SESSION['generation_total'] = count($lessonIds);
        $_SESSION['generation_results'] = [];
        
        // Redirect to process page
        header('Location: process-drill-generation.php');
        exit;
    }
}

// Get lessons without drills
$sql = "SELECT l.*, c.title as course_title,
        LENGTH(l.video_transcript) as transcript_length
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.video_transcript IS NOT NULL 
        AND l.video_transcript != ''
        AND NOT EXISTS (
            SELECT 1 FROM skills_drills sd 
            WHERE sd.lesson_id = l.id
        )
        ORDER BY c.id, l.order_index";

$stmt = $db->prepare($sql);
$stmt->execute();
$lessons = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as total FROM lessons WHERE video_transcript IS NOT NULL AND video_transcript != ''");
$result = $stmt->fetch();
$totalWithTranscripts = $result['total'];

$stmt = $db->query("SELECT COUNT(DISTINCT lesson_id) as total FROM skills_drills");
$result = $stmt->fetch();
$totalWithDrills = $result['total'];

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Generate Missing Skills Drills</h1>
            
            <div class="alert alert-info">
                <h5>Current Status</h5>
                <ul class="mb-0">
                    <li>Total lessons with transcripts: <strong><?= $totalWithTranscripts ?></strong></li>
                    <li>Lessons with skills drills: <strong><?= $totalWithDrills ?></strong></li>
                    <li>Lessons needing drills: <strong><?= count($lessons) ?></strong></li>
                </ul>
            </div>
            
            <?php if (empty($lessons)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> All lessons with transcripts already have skills drills!
                </div>
            <?php else: ?>
                <form method="POST" id="generateForm">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Select Lessons to Generate Drills</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">Select All</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectNone()">Select None</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAllCheck" onchange="toggleAll(this)">
                                            </th>
                                            <th>Course</th>
                                            <th>Lesson</th>
                                            <th>Transcript Size</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="lesson_ids[]" 
                                                           value="<?= $lesson['id'] ?>" 
                                                           class="lesson-check">
                                                </td>
                                                <td><?= htmlspecialchars($lesson['course_title']) ?></td>
                                                <td><?= htmlspecialchars($lesson['title']) ?></td>
                                                <td>
                                                    <?= number_format($lesson['transcript_length']) ?> chars
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="generate" class="btn btn-primary" id="generateBtn">
                                <i class="bi bi-lightning"></i> Generate Selected Drills
                            </button>
                            <span class="ms-3 text-muted" id="selectedCount">0 lessons selected</span>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    document.querySelectorAll('.lesson-check').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateCount();
}

function selectAll() {
    document.querySelectorAll('.lesson-check').forEach(cb => {
        cb.checked = true;
    });
    document.getElementById('selectAllCheck').checked = true;
    updateCount();
}

function selectNone() {
    document.querySelectorAll('.lesson-check').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheck').checked = false;
    updateCount();
}

function updateCount() {
    const checked = document.querySelectorAll('.lesson-check:checked').length;
    document.getElementById('selectedCount').textContent = checked + ' lessons selected';
    document.getElementById('generateBtn').disabled = checked === 0;
}

// Update count on individual checkbox change
document.querySelectorAll('.lesson-check').forEach(cb => {
    cb.addEventListener('change', updateCount);
});

// Initial count
updateCount();

// Confirm before generating
document.getElementById('generateForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.lesson-check:checked').length;
    if (checked > 0) {
        if (!confirm(`Generate skills drills for ${checked} lessons? This may take several minutes.`)) {
            e.preventDefault();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>