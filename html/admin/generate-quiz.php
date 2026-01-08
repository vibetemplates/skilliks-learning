<?php
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/OpenAIQuizGenerator.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit();
}

$pageTitle = 'Generate Quiz Questions';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1>Generate Quiz Questions with AI</h1>
            <p>Generate multiple choice quiz questions from lesson transcripts using OpenAI.</p>
            
            <?php if (isset($_POST['generate_quiz'])): ?>
                <div class="alert alert-info">
                    <h4>Generating Quiz...</h4>
                    <?php
                    try {
                        $lessonId = intval($_POST['lesson_id']);
                        $pdo = getDB();
                        
                        // Get lesson details and transcript
                        $stmt = $pdo->prepare("
                            SELECT l.*, c.title as course_title 
                            FROM lessons l 
                            JOIN courses c ON l.course_id = c.id 
                            WHERE l.id = ?
                        ");
                        $stmt->execute([$lessonId]);
                        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$lesson) {
                            throw new Exception("Lesson not found");
                        }
                        
                        if (empty($lesson['video_transcript'])) {
                            throw new Exception("This lesson has no transcript. Please fetch the transcript first.");
                        }
                        
                        echo "<p><strong>Lesson:</strong> {$lesson['course_title']} - {$lesson['title']}</p>";
                        echo "<p>Generating quiz questions from transcript...</p>";
                        flush();
                        
                        // Generate quiz
                        $generator = new OpenAIQuizGenerator();
                        $questions = $generator->generateQuizFromTranscript(
                            $lesson['video_transcript'], 
                            $lesson['title']
                        );
                        
                        if (isset($questions['error'])) {
                            throw new Exception($questions['error']);
                        }
                        
                        if (empty($questions)) {
                            throw new Exception("No questions were generated");
                        }
                        
                        echo "<p>Generated " . count($questions) . " questions. Saving to database...</p>";
                        flush();
                        
                        // Save questions
                        $quizTitle = "Quiz: " . $lesson['title'];
                        $result = $generator->saveQuizQuestions($lessonId, $questions, $quizTitle);
                        
                        if ($result && isset($result['quiz_id'])) {
                            echo "<div class='alert alert-success'>";
                            echo "<strong>Success!</strong><br>";
                            echo "Quiz ID: {$result['quiz_id']}<br>";
                            echo "Questions saved: {$result['questions_saved']}";
                            echo "</div>";
                            
                            // Show preview of questions
                            echo "<h3>Generated Questions Preview:</h3>";
                            echo "<ol>";
                            foreach ($questions as $q) {
                                echo "<li class='mb-3'>";
                                echo "<strong>{$q['question']}</strong><br>";
                                echo "A) {$q['choices']['A']}<br>";
                                echo "B) {$q['choices']['B']}<br>";
                                echo "C) {$q['choices']['C']}<br>";
                                echo "D) {$q['choices']['D']}<br>";
                                echo "<span class='text-success'>Correct: {$q['correct_answer']}</span><br>";
                                echo "<em>Explanation: {$q['explanation']}</em>";
                                echo "</li>";
                            }
                            echo "</ol>";
                        } else {
                            throw new Exception("Failed to save questions to database");
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                    ?>
                </div>
            <?php else: ?>
                <?php
                // Get lessons with transcripts
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("
                        SELECT l.id, l.title, c.title as course_title, 
                               LENGTH(l.video_transcript) as transcript_length,
                               (SELECT COUNT(*) FROM quiz_questions qq 
                                JOIN quizzes q ON qq.quiz_id = q.id 
                                WHERE q.lesson_id = l.id) as existing_questions
                        FROM lessons l
                        JOIN courses c ON l.course_id = c.id
                        WHERE l.video_transcript IS NOT NULL 
                        AND l.video_transcript != ''
                        AND c.community_id = ?
                        ORDER BY c.title, l.order_index
                    ");
                    $stmt->execute([$currentCommunityId]);
                    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                    
                    <?php if (empty($lessons)): ?>
                        <div class="alert alert-warning">
                            No lessons with transcripts found. Please fetch transcripts first using the 
                            <a href="/admin/fetch-youtube-transcripts.php">YouTube Transcript Fetcher</a>.
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Select a Lesson</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="lesson_id" class="form-label">Choose a lesson with transcript:</label>
                                        <select name="lesson_id" id="lesson_id" class="form-select" required>
                                            <option value="">-- Select a lesson --</option>
                                            <?php foreach ($lessons as $lesson): ?>
                                                <option value="<?= $lesson['id'] ?>">
                                                    <?= htmlspecialchars($lesson['course_title']) ?> - 
                                                    <?= htmlspecialchars($lesson['title']) ?>
                                                    (<?= number_format($lesson['transcript_length']) ?> chars)
                                                    <?php if ($lesson['existing_questions'] > 0): ?>
                                                        <span class="text-warning">[Has <?= $lesson['existing_questions'] ?> questions]</span>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <strong>Note:</strong> This will generate 15 multiple choice questions based on the lesson transcript.
                                        If a quiz already exists for this lesson, it will be replaced.
                                    </div>
                                    
                                    <button type="submit" name="generate_quiz" class="btn btn-primary">
                                        <i class="bi bi-magic"></i> Generate Quiz Questions
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error loading lessons: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="/admin-courses" class="btn btn-secondary">Back to Course Management</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>