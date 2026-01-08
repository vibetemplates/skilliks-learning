<?php
/**
 * Quiz Results Page
 * 
 * Shows quiz results using the normalized database structure
 */

$page_title = 'Quiz Results';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Course.php';
require_once 'classes/Lesson.php';
require_once 'classes/Quiz.php';
require_once 'classes/QuizAttempt.php';

// Require login
requireLogin();

$db = getDB();
$communityId = getCurrentCommunityId();
$userId = $_SESSION['user_id'];
$courseClass = new Course($db, $communityId);
$lessonClass = new Lesson($db);
$quiz = new Quiz($db);
$quizAttempt = new QuizAttempt($db);

// Get attempt ID from query parameter
$attemptId = $_GET['attempt_id'] ?? null;

if (!$attemptId) {
    setFlashMessage('error', 'No quiz attempt specified');
    redirect('courses');
}

// Get attempt details
$attemptDetails = $quiz->getAttemptDetails($attemptId);
if (!$attemptDetails) {
    setFlashMessage('error', 'Quiz attempt not found');
    redirect('courses');
}

// Verify the attempt belongs to this user (unless admin)
if ($attemptDetails['user_id'] != $userId && !isAdmin()) {
    setFlashMessage('error', 'Unauthorized access to quiz results');
    redirect('courses');
}

// Get full attempt info with quiz and lesson details
$fullAttempt = $quizAttempt->getById($attemptId);
if (!$fullAttempt) {
    setFlashMessage('error', 'Quiz attempt details not found');
    redirect('courses');
}

// Get course details
$course = $courseClass->getById($fullAttempt['course_id']);
if (!$course) {
    setFlashMessage('error', 'Course not found');
    redirect('courses');
}

// Check if user can review answers
$canReview = $attemptDetails['allow_review'] && $attemptDetails['status'] == 'completed';
$showCorrectAnswers = $fullAttempt['show_correct_answers'] && $canReview;

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mt-3">
            <li class="breadcrumb-item"><a href="courses">Courses</a></li>
            <li class="breadcrumb-item"><a href="course-detail?id=<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></a></li>
            <li class="breadcrumb-item"><?php echo htmlspecialchars($fullAttempt['lesson_title']); ?></li>
            <li class="breadcrumb-item active">Results</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <!-- Results Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-clipboard-check me-2"></i>
                        Quiz Results - Attempt #<?php echo $attemptDetails['attempt_number']; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-md-4">
                            <h2 class="<?php echo $attemptDetails['passed'] ? 'text-success' : 'text-danger'; ?>">
                                <?php echo number_format($attemptDetails['score_achieved'], 1); ?>%
                            </h2>
                            <p class="text-muted mb-0">Your Score</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-secondary">
                                <?php echo $attemptDetails['points_earned']; ?>/<?php echo $attemptDetails['total_points']; ?>
                            </h2>
                            <p class="text-muted mb-0">Points Earned</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-secondary">
                                <?php 
                                $minutes = floor($attemptDetails['time_spent_seconds'] / 60);
                                $seconds = $attemptDetails['time_spent_seconds'] % 60;
                                echo sprintf("%d:%02d", $minutes, $seconds);
                                ?>
                            </h2>
                            <p class="text-muted mb-0">Time Taken</p>
                        </div>
                    </div>

                    <div class="alert <?php echo $attemptDetails['passed'] ? 'alert-success' : 'alert-warning'; ?>">
                        <i class="bi <?php echo $attemptDetails['passed'] ? 'bi-check-circle' : 'bi-x-circle'; ?> me-2"></i>
                        <?php if ($attemptDetails['passed']): ?>
                            <strong>Congratulations!</strong> You passed the quiz with a score of <?php echo number_format($attemptDetails['score_achieved'], 1); ?>%.
                        <?php else: ?>
                            <strong>Not Passed.</strong> You scored <?php echo number_format($attemptDetails['score_achieved'], 1); ?>%. 
                            The passing score is <?php echo number_format($fullAttempt['passing_score'], 0); ?>%.
                            <?php if ($quiz->canTakeQuiz($attemptDetails['quiz_id'], $userId)): ?>
                                You can retake the quiz.
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="course-detail?id=<?php echo $course['id']; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Course
                        </a>
                        <?php if (!$attemptDetails['passed'] && $quiz->canTakeQuiz($attemptDetails['quiz_id'], $userId)): ?>
                            <a href="quiz-take.php?lesson_id=<?php echo $fullAttempt['lesson_id']; ?>" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-2"></i>Retake Quiz
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Question Review -->
            <?php if ($canReview): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Question Review</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($attemptDetails['responses'] as $index => $response): ?>
                            <div class="mb-4 pb-4 <?php echo $index < count($attemptDetails['responses']) - 1 ? 'border-bottom' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">
                                        Question <?php echo $index + 1; ?>
                                        <?php if ($response['is_correct']): ?>
                                            <i class="bi bi-check-circle text-success ms-2"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle text-danger ms-2"></i>
                                        <?php endif; ?>
                                    </h6>
                                    <span class="badge bg-secondary">
                                        <?php echo $response['points_earned']; ?>/<?php echo $response['max_points']; ?> points
                                    </span>
                                </div>

                                <p class="mb-3"><?php echo htmlspecialchars($response['question_text']); ?></p>

                                <?php if ($response['question_type'] == 'multiple_choice' || $response['question_type'] == 'true_false'): ?>
                                    <div class="ms-3">
                                        <p class="mb-1">
                                            <strong>Your Answer:</strong> 
                                            <span class="<?php echo $response['is_correct'] ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo htmlspecialchars($response['selected_answer'] ?: 'Not answered'); ?>
                                            </span>
                                        </p>
                                        <?php if ($showCorrectAnswers && !$response['is_correct'] && $response['correct_answer']): ?>
                                            <p class="mb-1">
                                                <strong>Correct Answer:</strong> 
                                                <span class="text-success"><?php echo htmlspecialchars($response['correct_answer']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($response['question_type'] == 'short_answer'): ?>
                                    <div class="ms-3">
                                        <p class="mb-1">
                                            <strong>Your Answer:</strong> 
                                            <span class="<?php echo $response['is_correct'] ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo htmlspecialchars($response['answer_text'] ?: 'Not answered'); ?>
                                            </span>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($response['explanation'] && $showCorrectAnswers): ?>
                                    <div class="alert alert-info mt-2 mb-0">
                                        <strong>Explanation:</strong> <?php echo htmlspecialchars($response['explanation']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($response['feedback']): ?>
                                    <div class="alert alert-secondary mt-2 mb-0">
                                        <strong>Feedback:</strong> <?php echo htmlspecialchars($response['feedback']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Detailed question review is not available for this quiz.
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Attempt Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Attempt Summary</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Started:</dt>
                        <dd class="col-sm-6"><?php echo date('M d, Y g:i A', strtotime($attemptDetails['start_time'])); ?></dd>
                        
                        <dt class="col-sm-6">Completed:</dt>
                        <dd class="col-sm-6"><?php echo date('M d, Y g:i A', strtotime($attemptDetails['end_time'])); ?></dd>
                        
                        <dt class="col-sm-6">Duration:</dt>
                        <dd class="col-sm-6">
                            <?php 
                            $minutes = floor($attemptDetails['time_spent_seconds'] / 60);
                            $seconds = $attemptDetails['time_spent_seconds'] % 60;
                            echo sprintf("%d min %d sec", $minutes, $seconds);
                            ?>
                        </dd>
                        
                        <dt class="col-sm-6">Questions:</dt>
                        <dd class="col-sm-6"><?php echo count($attemptDetails['responses']); ?></dd>
                        
                        <dt class="col-sm-6">Correct:</dt>
                        <dd class="col-sm-6">
                            <?php 
                            $correct = array_filter($attemptDetails['responses'], function($r) { return $r['is_correct']; });
                            echo count($correct) . ' (' . round(count($correct) / count($attemptDetails['responses']) * 100) . '%)';
                            ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- All Attempts -->
            <?php
            $allAttempts = $quiz->getUserAttempts($attemptDetails['quiz_id'], $userId);
            if (count($allAttempts) > 1):
            ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Attempts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Attempt</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allAttempts as $attempt): ?>
                                    <?php if ($attempt['status'] == 'completed'): ?>
                                    <tr <?php echo $attempt['id'] == $attemptId ? 'class="table-active"' : ''; ?>>
                                        <td>#<?php echo $attempt['attempt_number']; ?></td>
                                        <td>
                                            <?php echo number_format($attempt['score_achieved'], 1); ?>%
                                            <?php if ($attempt['passed']): ?>
                                                <i class="bi bi-check-circle text-success"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d', strtotime($attempt['end_time'])); ?></td>
                                        <td>
                                            <?php if ($attempt['id'] != $attemptId): ?>
                                                <a href="quiz-results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-link p-0">
                                                    View
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>