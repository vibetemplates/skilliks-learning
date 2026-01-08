<?php
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/OpenAISkillsDrillGenerator.php';
require_once '../classes/Quiz.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();

// Check if user is admin
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    header('Location: /dashboard');
    exit;
}

$db = getDB();

// Handle drill generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lesson_id'])) {
    $lessonId = intval($_POST['lesson_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        // Get lesson transcript
        $stmt = $db->prepare("SELECT l.*, c.title as course_title 
                              FROM lessons l 
                              JOIN courses c ON l.course_id = c.id 
                              WHERE l.id = ?");
        $stmt->execute([$lessonId]);
        $lesson = $stmt->fetch();
        
        if (!$lesson || empty($lesson['video_transcript'])) {
            throw new Exception("Lesson not found or transcript not available");
        }
        
        // Get existing quiz questions to avoid duplicates
        $quiz = new Quiz();
        $existingQuiz = $quiz->getByLessonId($lessonId);
        $existingQuestions = [];
        
        if ($existingQuiz) {
            $existingQuestions = $quiz->getQuestions($existingQuiz['id']);
        }
        
        // Generate skills drill
        $generator = new OpenAISkillsDrillGenerator();
        $questions = $generator->generateDrillFromTranscript(
            $lesson['video_transcript'],
            $lesson['title'],
            $existingQuestions
        );
        
        // Save to database
        $drillId = $generator->saveDrillQuestions($lessonId, $questions, $currentUserId);
        
        $_SESSION['success_message'] = "Skills drill generated successfully with " . count($questions) . " questions!";
        header("Location: generate-skills-drill.php?preview=$drillId");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error generating skills drill: " . $e->getMessage();
    }
}

// Get lessons with transcripts
$sql = "SELECT l.*, c.title as course_title,
               sd.id as drill_id,
               (SELECT COUNT(*) FROM skills_drill_questions WHERE drill_id = sd.id) as question_count
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        LEFT JOIN skills_drills sd ON l.id = sd.lesson_id
        WHERE l.video_transcript IS NOT NULL AND l.video_transcript != ''
        ORDER BY c.title, l.order_index";

$stmt = $db->prepare($sql);
$stmt->execute();
$lessons = $stmt->fetchAll();

// Preview mode
$previewDrill = null;
$previewQuestions = [];
if (isset($_GET['preview'])) {
    $drillId = intval($_GET['preview']);
    $skillsDrill = new SkillsDrill();
    $previewDrill = $skillsDrill->getById($drillId);
    
    if ($previewDrill) {
        $previewQuestions = $skillsDrill->getQuestions($drillId, null, false);
    }
}

$pageTitle = 'Generate Skills Drills';
include '../includes/header.php';
?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Generate Skills Drills</h1>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if ($previewDrill && $previewQuestions): ?>
                    <div class="card mb-4" id="preview-section">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-eye"></i> Preview: <?= htmlspecialchars($previewDrill['title']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Total Questions:</strong> <?= count($previewQuestions) ?></p>
                            <div class="accordion" id="questionsAccordion">
                                <?php foreach ($previewQuestions as $index => $question): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#question<?= $index ?>" 
                                                    aria-expanded="false">
                                                Q<?= $index + 1 ?>: <?= htmlspecialchars(substr($question['question_text'], 0, 80)) ?>...
                                                <span class="badge bg-<?= $question['difficulty_level'] === 'easy' ? 'success' : ($question['difficulty_level'] === 'hard' ? 'danger' : 'warning') ?> ms-2">
                                                    <?= ucfirst($question['difficulty_level']) ?>
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="question<?= $index ?>" class="accordion-collapse collapse" 
                                             data-bs-parent="#questionsAccordion">
                                            <div class="accordion-body">
                                                <p><strong>Question:</strong> <?= htmlspecialchars($question['question_text']) ?></p>
                                                
                                                <?php if ($question['hint']): ?>
                                                    <p><strong>Hint:</strong> <?= htmlspecialchars($question['hint']) ?></p>
                                                <?php endif; ?>
                                                
                                                <p><strong>Options:</strong></p>
                                                <ul>
                                                    <?php foreach ($question['options'] as $option): ?>
                                                        <li class="<?= $option['is_correct'] ? 'text-success fw-bold' : '' ?>">
                                                            <?= htmlspecialchars($option['answer_text']) ?>
                                                            <?php if ($option['is_correct']): ?>
                                                                <i class="bi bi-check-circle-fill"></i>
                                                            <?php endif; ?>
                                                            <?php if ($option['feedback']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($option['feedback']) ?></small>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                
                                                <?php if ($question['explanation']): ?>
                                                    <p><strong>Explanation:</strong> <?= htmlspecialchars($question['explanation']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card" id="lessons-table">
                    <div class="card-header">
                        <h5 class="mb-0">Lessons with Transcripts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Lesson</th>
                                        <th>Drill Status</th>
                                        <th>Questions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($lesson['course_title']) ?></td>
                                            <td><?= htmlspecialchars($lesson['title']) ?></td>
                                            <td>
                                                <?php if ($lesson['drill_id']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Created
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-x-circle"></i> Not Created
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($lesson['question_count']): ?>
                                                    <?= $lesson['question_count'] ?> questions
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($lesson['drill_id']): ?>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('This will replace existing drill questions. Continue?');">
                                                        <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="bi bi-arrow-clockwise"></i> Regenerate
                                                        </button>
                                                    </form>
                                                    <a href="?preview=<?= $lesson['drill_id'] ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Preview
                                                    </a>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-plus-circle"></i> Generate Drill
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
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
    
<?php include '../includes/footer.php'; ?>