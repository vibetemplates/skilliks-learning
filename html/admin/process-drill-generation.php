<?php
$pageTitle = 'Processing Skills Drill Generation';
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

// Check if we have lessons to generate
if (!isset($_SESSION['lessons_to_generate']) || empty($_SESSION['lessons_to_generate'])) {
    setFlashMessage('error', 'No lessons selected for generation');
    redirect('/admin/generate-all-skills-drills.php');
}

$db = Database::getInstance()->getConnection();

// Get lesson details
$lessonIds = $_SESSION['lessons_to_generate'];
$placeholders = str_repeat('?,', count($lessonIds) - 1) . '?';
$sql = "SELECT l.*, c.title as course_title 
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id IN ($placeholders)
        ORDER BY c.id, l.order_index";

$stmt = $db->prepare($sql);
$stmt->execute($lessonIds);
$lessons = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1>Generating Skills Drills</h1>
            
            <div class="card">
                <div class="card-body">
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             id="progressBar"
                             style="width: 0%">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Status:</strong> <span id="statusText">Initializing...</span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Current Lesson:</strong> <span id="currentLesson">-</span>
                    </div>
                    
                    <div class="row text-center mb-3">
                        <div class="col-md-4">
                            <h3 class="text-primary"><span id="totalCount">0</span></h3>
                            <p class="text-muted">Total Lessons</p>
                        </div>
                        <div class="col-md-4">
                            <h3 class="text-success"><span id="successCount">0</span></h3>
                            <p class="text-muted">Successful</p>
                        </div>
                        <div class="col-md-4">
                            <h3 class="text-danger"><span id="failureCount">0</span></h3>
                            <p class="text-muted">Failed</p>
                        </div>
                    </div>
                    
                    <div id="resultsContainer" style="display: none;">
                        <h4>Results</h4>
                        <div id="resultsList" class="alert alert-secondary" style="max-height: 300px; overflow-y: auto;"></div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-primary" id="startBtn" onclick="startGeneration()">
                            <i class="bi bi-play-fill"></i> Start Generation
                        </button>
                        <a href="/admin/generate-all-skills-drills.php" class="btn btn-secondary" id="backBtn" style="display: none;">
                            <i class="bi bi-arrow-left"></i> Back to Selection
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const lessons = <?= json_encode($lessons) ?>;
let currentIndex = 0;
let isProcessing = false;
let successCount = 0;
let failureCount = 0;
let results = [];

// Update counts
document.getElementById('totalCount').textContent = lessons.length;

function updateProgress() {
    const progress = Math.round((currentIndex / lessons.length) * 100);
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = progress + '%';
    document.getElementById('progressText').textContent = progress + '%';
}

function updateStatus(message) {
    document.getElementById('statusText').textContent = message;
}

function updateCurrentLesson(lesson) {
    if (lesson) {
        document.getElementById('currentLesson').textContent = 
            `${lesson.course_title} - ${lesson.title}`;
    } else {
        document.getElementById('currentLesson').textContent = '-';
    }
}

function addResult(lesson, success, message) {
    const result = {
        lesson: lesson,
        success: success,
        message: message
    };
    results.push(result);
    
    // Update counts
    if (success) {
        successCount++;
        document.getElementById('successCount').textContent = successCount;
    } else {
        failureCount++;
        document.getElementById('failureCount').textContent = failureCount;
    }
    
    // Show results container
    document.getElementById('resultsContainer').style.display = 'block';
    
    // Add to results list
    const resultsList = document.getElementById('resultsList');
    const resultClass = success ? 'text-success' : 'text-danger';
    const icon = success ? '✓' : '✗';
    
    resultsList.innerHTML += `
        <div class="${resultClass}">
            ${icon} <strong>${lesson.course_title} - ${lesson.title}</strong>: ${message}
        </div>
    `;
    
    // Scroll to bottom
    resultsList.scrollTop = resultsList.scrollHeight;
}

async function generateDrillForLesson(lesson) {
    updateCurrentLesson(lesson);
    updateStatus(`Generating drill for lesson ${currentIndex + 1} of ${lessons.length}...`);
    
    try {
        const response = await fetch('/admin/api/generate-drill.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: lesson.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            addResult(lesson, true, `Generated ${data.question_count} questions`);
        } else {
            addResult(lesson, false, data.error || 'Unknown error');
        }
    } catch (error) {
        addResult(lesson, false, `Network error: ${error.message}`);
    }
}

async function processNextLesson() {
    if (currentIndex >= lessons.length) {
        // All done
        isProcessing = false;
        updateStatus('Generation complete!');
        updateCurrentLesson(null);
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('backBtn').style.display = 'inline-block';
        
        // Clear session data
        await fetch('/admin/api/clear-generation-session.php', { method: 'POST' });
        
        return;
    }
    
    const lesson = lessons[currentIndex];
    await generateDrillForLesson(lesson);
    
    currentIndex++;
    updateProgress();
    
    // Add delay to avoid rate limiting (3 seconds between API calls)
    if (currentIndex < lessons.length) {
        updateStatus('Waiting before next lesson...');
        setTimeout(() => {
            processNextLesson();
        }, 3000);
    } else {
        processNextLesson();
    }
}

function startGeneration() {
    if (isProcessing) return;
    
    isProcessing = true;
    document.getElementById('startBtn').disabled = true;
    document.getElementById('startBtn').innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    
    processNextLesson();
}

// Check if we should auto-start (e.g., if returning from a refresh)
window.addEventListener('load', function() {
    <?php if (isset($_SESSION['generation_progress']) && $_SESSION['generation_progress'] > 0): ?>
        // Resume from where we left off
        currentIndex = <?= $_SESSION['generation_progress'] ?>;
        updateProgress();
        startGeneration();
    <?php endif; ?>
});

// Warn before leaving if processing
window.addEventListener('beforeunload', function(e) {
    if (isProcessing) {
        e.preventDefault();
        e.returnValue = 'Generation is still in progress. Are you sure you want to leave?';
    }
});
</script>

<?php include '../includes/footer.php'; ?>