<?php
/**
 * Quiz Taking Interface
 * 
 * Students take quizzes using the normalized database structure
 */

$page_title = 'Take Quiz';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Course.php';
require_once 'classes/Lesson.php';
require_once 'classes/Quiz.php';
require_once 'classes/QuizAttempt.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$db = getDB();
$communityId = getCurrentCommunityId();
$userId = $_SESSION['user_id'];
$courseClass = new Course($db, $communityId);
$lessonClass = new Lesson($db);
$quiz = new Quiz($db);
$quizAttempt = new QuizAttempt($db);
$userObj = new User();

// Get lesson ID from query parameter
$lessonId = $_GET['lesson_id'] ?? null;

if (!$lessonId) {
    setFlashMessage('error', 'No lesson specified');
    redirect('courses');
}

// Get lesson details
$lesson = $lessonClass->getById($lessonId);
if (!$lesson) {
    setFlashMessage('error', 'Lesson not found');
    redirect('courses');
}

// Get course details
$course = $courseClass->getById($lesson['course_id']);
if (!$course) {
    setFlashMessage('error', 'Course not found');
    redirect('courses');
}

// Check enrollment
$stmt = $db->prepare("
    SELECT * FROM course_enrollments 
    WHERE user_id = ? AND course_id = ? AND status IN ('enrolled', 'in_progress')
");
$stmt->execute([$userId, $course['id']]);
$enrollment = $stmt->fetch();

if (!$enrollment && !$userObj->isAdmin($userId)) {
    setFlashMessage('error', 'You must be enrolled in this course to take the quiz');
    redirect('course-detail?id=' . $course['id']);
}

// Get quiz details
$quizData = $quiz->getByLessonId($lessonId);
if (!$quizData) {
    setFlashMessage('error', 'Quiz data not found');
    redirect('course-detail?id=' . $course['id']);
}

// Get question count
$stmt = $db->prepare("SELECT COUNT(*) as question_count FROM quiz_questions WHERE quiz_id = ?");
$stmt->execute([$quizData['id']]);
$questionCount = $stmt->fetch()['question_count'];

// Check if user can take the quiz
if (!$quiz->canTakeQuiz($quizData['id'], $userId)) {
    // Get user's attempts to show scores
    $attempts = $quiz->getUserAttempts($quizData['id'], $userId);
    $bestScore = 0;
    foreach ($attempts as $attempt) {
        if ($attempt['score_achieved'] > $bestScore) {
            $bestScore = $attempt['score_achieved'];
        }
    }
    
    setFlashMessage('error', 'You have reached the maximum number of attempts for this quiz. Your best score: ' . number_format($bestScore, 1) . '%');
    redirect('course-detail?id=' . $course['id']);
}

// Check for in-progress attempts
$inProgressAttempts = $quizAttempt->getUserInProgressAttempts($userId);
$currentAttempt = null;
foreach ($inProgressAttempts as $attempt) {
    if ($attempt['quiz_id'] == $quizData['id']) {
        $currentAttempt = $attempt;
        break;
    }
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mt-3">
            <li class="breadcrumb-item"><a href="courses">Courses</a></li>
            <li class="breadcrumb-item"><a href="course-detail?id=<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($lesson['title']); ?></li>
        </ol>
    </nav>

    <?php if ($currentAttempt || $quiz->canTakeQuiz($quizData['id'], $userId)): ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-question-circle me-2"></i>
                            <?php echo htmlspecialchars($quizData['title']); ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Quiz Info -->
                        <div class="alert alert-info mb-4">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Questions:</strong> <span id="total-questions"><?php echo $questionCount; ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Time Limit:</strong> 
                                    <?php if ($quizData['time_limit_minutes']): ?>
                                        <span id="time-limit"><?php echo $quizData['time_limit_minutes']; ?></span> minutes
                                    <?php else: ?>
                                        Unlimited
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Passing Score:</strong> <?php echo $quizData['passing_score']; ?>%
                                </div>
                            </div>
                        </div>

                        <?php if ($quizData['instructions']): ?>
                            <div class="alert alert-secondary mb-4">
                                <h6 class="alert-heading">Instructions:</h6>
                                <?php echo nl2br(htmlspecialchars($quizData['instructions'])); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Quiz Start/Resume -->
                        <div id="quiz-start" class="text-center py-5">
                            <h5 class="mb-4">Ready to begin?</h5>
                            <?php if ($currentAttempt): ?>
                                <p class="text-muted mb-4">You have an in-progress attempt. Would you like to continue?</p>
                                <button type="button" class="btn btn-primary btn-lg" onclick="resumeQuiz(<?php echo $currentAttempt['id']; ?>)">
                                    <i class="bi bi-play-circle me-2"></i>Resume Quiz
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary btn-lg" onclick="startQuiz()">
                                    <i class="bi bi-play-circle me-2"></i>Start Quiz
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Quiz Content (Hidden initially) -->
                        <div id="quiz-content" style="display: none;">
                            <!-- Timer -->
                            <?php if ($quizData['time_limit_minutes']): ?>
                                <div class="alert alert-warning text-center mb-4">
                                    <strong>Time Remaining:</strong> 
                                    <span id="timer" class="fs-4">00:00</span>
                                </div>
                            <?php endif; ?>

                            <!-- Progress Bar -->
                            <div class="progress mb-4" style="height: 25px;">
                                <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%">
                                    <span id="progress-text">0 of 0</span>
                                </div>
                            </div>

                            <!-- Questions Container -->
                            <div id="questions-container">
                                <!-- Questions will be loaded here -->
                            </div>

                            <!-- Navigation -->
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-secondary" id="prev-btn" onclick="previousQuestion()" disabled>
                                    <i class="bi bi-arrow-left me-2"></i>Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="next-btn" onclick="nextQuestion()">
                                    Next<i class="bi bi-arrow-right ms-2"></i>
                                </button>
                                <button type="button" class="btn btn-success" id="submit-btn" onclick="submitQuiz()" style="display: none;">
                                    <i class="bi bi-check-circle me-2"></i>Submit Quiz
                                </button>
                            </div>
                        </div>

                        <!-- Quiz Complete (Hidden initially) -->
                        <div id="quiz-complete" style="display: none;">
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">Quiz Submitted!</h4>
                                <p class="text-muted">Your responses have been recorded.</p>
                                <div id="score-display" class="mt-4">
                                    <!-- Score will be displayed here -->
                                </div>
                                <div class="mt-4">
                                    <a href="quiz-results.php?attempt_id=" id="view-results-btn" class="btn btn-primary">
                                        View Detailed Results
                                    </a>
                                    <a href="course-detail?id=<?php echo $course['id']; ?>" class="btn btn-secondary">
                                        Back to Course
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Question Navigator -->
                <div class="card shadow-sm mb-4" id="question-nav" style="display: none;">
                    <div class="card-header">
                        <h5 class="mb-0">Questions</h5>
                    </div>
                    <div class="card-body">
                        <div id="question-nav-items" class="d-flex flex-wrap gap-2">
                            <!-- Question buttons will be generated here -->
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <span class="badge bg-secondary">Not Answered</span>
                                <span class="badge bg-primary">Current</span>
                                <span class="badge bg-success">Answered</span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Attempt History -->
                <?php
                $previousAttempts = $quiz->getUserAttempts($quizData['id'], $userId);
                if (!empty($previousAttempts)):
                ?>
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Previous Attempts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Attempt</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($previousAttempts as $attempt): ?>
                                        <?php if ($attempt['status'] == 'completed'): ?>
                                        <tr>
                                            <td>#<?php echo $attempt['attempt_number']; ?></td>
                                            <td>
                                                <?php echo number_format($attempt['score_achieved'], 1); ?>%
                                                <?php if ($attempt['passed']): ?>
                                                    <i class="bi bi-check-circle text-success"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle text-danger"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($attempt['end_time'])); ?></td>
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
    <?php endif; ?>
</main>

<!-- JavaScript -->
<script>
// Quiz state
let quizState = {
    lessonId: <?php echo $lessonId; ?>,
    attemptId: null,
    questions: [],
    answers: {},
    currentQuestion: 0,
    startTime: null,
    timeLimit: <?php echo $quizData['time_limit_minutes'] ? $quizData['time_limit_minutes'] * 60 : 'null'; ?>,
    timeElapsed: 0,
    timerInterval: null
};

// Start quiz
async function startQuiz() {
    try {
        const response = await fetch('api/quiz-start.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: quizState.lessonId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            quizState.attemptId = data.attempt_id;
            quizState.startTime = Date.now() - (data.time_elapsed * 1000);
            
            // Load questions
            await loadQuestions();
            
            // Show quiz content
            document.getElementById('quiz-start').style.display = 'none';
            document.getElementById('quiz-content').style.display = 'block';
            document.getElementById('question-nav').style.display = 'block';
            
            // Start timer if applicable
            if (quizState.timeLimit) {
                startTimer();
            }
            
            // Display first question
            displayQuestion(0);
        } else {
            alert(data.message || 'Failed to start quiz');
        }
    } catch (error) {
        console.error('Error starting quiz:', error);
        alert('An error occurred while starting the quiz');
    }
}

// Resume quiz
async function resumeQuiz(attemptId) {
    quizState.attemptId = attemptId;
    await startQuiz();
}

// Load questions
async function loadQuestions() {
    try {
        const response = await fetch(`api/quiz-questions.php?lesson_id=<?php echo $lessonId; ?>`);
        const data = await response.json();
        
        if (data.success) {
            quizState.questions = data.questions;
            document.getElementById('total-questions').textContent = quizState.questions.length;
            
            // Initialize question navigator
            initQuestionNav();
            
            // Load saved answers if any
            if (data.saved_answers) {
                quizState.answers = data.saved_answers;
                updateQuestionNav();
            }
        } else {
            throw new Error(data.message || 'Failed to load questions');
        }
    } catch (error) {
        console.error('Error loading questions:', error);
        alert('Failed to load quiz questions');
    }
}

// Initialize question navigator
function initQuestionNav() {
    const navContainer = document.getElementById('question-nav-items');
    navContainer.innerHTML = '';
    
    quizState.questions.forEach((question, index) => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-sm btn-secondary';
        btn.textContent = index + 1;
        btn.onclick = () => displayQuestion(index);
        navContainer.appendChild(btn);
    });
}

// Display question
function displayQuestion(index) {
    if (index < 0 || index >= quizState.questions.length) return;
    
    // Save current answer before switching
    if (quizState.currentQuestion !== index) {
        saveCurrentAnswer();
    }
    
    quizState.currentQuestion = index;
    const question = quizState.questions[index];
    const container = document.getElementById('questions-container');
    
    let html = `
        <div class="question-item">
            <h5 class="mb-3">
                Question ${index + 1} of ${quizState.questions.length}
                <span class="badge bg-secondary float-end">${question.points} points</span>
            </h5>
            <p class="lead">${escapeHtml(question.question_text)}</p>
    `;
    
    // Display based on question type
    switch (question.question_type) {
        case 'multiple_choice':
            html += '<div class="mt-3">';
            question.answers.forEach((answer, i) => {
                const checked = quizState.answers[question.id] == answer.id ? 'checked' : '';
                html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" 
                               name="question_${question.id}" 
                               id="answer_${answer.id}" 
                               value="${answer.id}" ${checked}>
                        <label class="form-check-label" for="answer_${answer.id}">
                            ${escapeHtml(answer.text)}
                        </label>
                    </div>
                `;
            });
            html += '</div>';
            break;
            
        case 'true_false':
            html += '<div class="mt-3">';
            question.answers.forEach((answer, i) => {
                const checked = quizState.answers[question.id] == answer.id ? 'checked' : '';
                html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" 
                               name="question_${question.id}" 
                               id="answer_${answer.id}" 
                               value="${answer.id}" ${checked}>
                        <label class="form-check-label" for="answer_${answer.id}">
                            ${escapeHtml(answer.text)}
                        </label>
                    </div>
                `;
            });
            html += '</div>';
            break;
    }
    
    html += '</div>';
    container.innerHTML = html;
    
    // Update navigation buttons
    document.getElementById('prev-btn').disabled = index === 0;
    document.getElementById('next-btn').style.display = index === quizState.questions.length - 1 ? 'none' : 'inline-block';
    document.getElementById('submit-btn').style.display = index === quizState.questions.length - 1 ? 'inline-block' : 'none';
    
    // Update progress
    updateProgress();
    updateQuestionNav();
}

// Save current answer
function saveCurrentAnswer() {
    const question = quizState.questions[quizState.currentQuestion];
    
    switch (question.question_type) {
        case 'multiple_choice':
        case 'true_false':
            const selected = document.querySelector(`input[name="question_${question.id}"]:checked`);
            if (selected) {
                quizState.answers[question.id] = selected.value;
            }
            break;
    }
    
    // Auto-save answer
    saveAnswer(question.id);
}

// Save answer to server
async function saveAnswer(questionId) {
    // This could be implemented to save answers in real-time
    // For now, we'll save all answers on submit
}

// Navigate to previous question
function previousQuestion() {
    if (quizState.currentQuestion > 0) {
        displayQuestion(quizState.currentQuestion - 1);
    }
}

// Navigate to next question
function nextQuestion() {
    if (quizState.currentQuestion < quizState.questions.length - 1) {
        displayQuestion(quizState.currentQuestion + 1);
    }
}

// Update progress bar
function updateProgress() {
    const answered = Object.keys(quizState.answers).length;
    const total = quizState.questions.length;
    const percentage = (answered / total) * 100;
    
    document.getElementById('progress-bar').style.width = percentage + '%';
    document.getElementById('progress-text').textContent = `${answered} of ${total}`;
}

// Update question navigator
function updateQuestionNav() {
    const buttons = document.querySelectorAll('#question-nav-items button');
    
    buttons.forEach((btn, index) => {
        const question = quizState.questions[index];
        btn.className = 'btn btn-sm ';
        
        if (index === quizState.currentQuestion) {
            btn.className += 'btn-primary';
        } else if (quizState.answers[question.id] !== undefined) {
            btn.className += 'btn-success';
        } else {
            btn.className += 'btn-secondary';
        }
    });
}

// Timer functions
function startTimer() {
    if (!quizState.timeLimit) return;
    
    quizState.timerInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - quizState.startTime) / 1000);
        const remaining = quizState.timeLimit - elapsed;
        
        if (remaining <= 0) {
            clearInterval(quizState.timerInterval);
            alert('Time is up! The quiz will be submitted automatically.');
            submitQuiz();
        } else {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Warning when less than 5 minutes
            if (remaining < 300) {
                document.getElementById('timer').parentElement.classList.remove('alert-warning');
                document.getElementById('timer').parentElement.classList.add('alert-danger');
            }
        }
    }, 1000);
}

// Submit quiz
async function submitQuiz() {
    // Save current answer
    saveCurrentAnswer();
    
    // Check if all questions are answered
    const unanswered = quizState.questions.filter(q => !quizState.answers[q.id]);
    if (unanswered.length > 0) {
        if (!confirm(`You have ${unanswered.length} unanswered question(s). Are you sure you want to submit?`)) {
            return;
        }
    }
    
    // Stop timer
    if (quizState.timerInterval) {
        clearInterval(quizState.timerInterval);
    }
    
    // Calculate time taken
    const timeTaken = Math.floor((Date.now() - quizState.startTime) / 1000);
    
    try {
        const response = await fetch('api/quiz-submit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: quizState.lessonId,
                attempt_id: quizState.attemptId,
                answers: quizState.answers,
                time_taken: timeTaken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Hide quiz content
            document.getElementById('quiz-content').style.display = 'none';
            document.getElementById('question-nav').style.display = 'none';
            
            // Show completion screen
            document.getElementById('quiz-complete').style.display = 'block';
            
            // Display score if allowed
            if (<?php echo $quizData['show_score_immediately'] ? 'true' : 'false'; ?>) {
                const scoreHtml = `
                    <h2 class="mb-3">
                        Your Score: <span class="${data.passed ? 'text-success' : 'text-danger'}">${data.score.toFixed(1)}%</span>
                    </h2>
                    <p class="text-muted">
                        ${data.passed ? 'Congratulations! You passed the quiz.' : 'You did not meet the passing score of <?php echo $quizData['passing_score']; ?>%.'}
                    </p>
                `;
                document.getElementById('score-display').innerHTML = scoreHtml;
            }
            
            // Update view results button
            document.getElementById('view-results-btn').href = `quiz-results.php?attempt_id=${quizState.attemptId}`;
        } else {
            alert(data.message || 'Failed to submit quiz');
        }
    } catch (error) {
        console.error('Error submitting quiz:', error);
        alert('An error occurred while submitting the quiz');
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Prevent navigation away during quiz
window.addEventListener('beforeunload', (e) => {
    if (document.getElementById('quiz-content').style.display !== 'none') {
        e.preventDefault();
        e.returnValue = 'You have an active quiz. Are you sure you want to leave?';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>