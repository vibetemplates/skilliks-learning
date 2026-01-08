<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/SkillsDrill.php';

// Require login
requireLogin();

$skillsDrill = new SkillsDrill();
$db = Database::getInstance()->getConnection();

// Get drill ID
$drillId = isset($_GET['drill_id']) ? intval($_GET['drill_id']) : 0;

// Get drill info
$drill = $skillsDrill->getById($drillId);
if (!$drill) {
    $_SESSION['error_message'] = "Skills drill not found.";
    header("Location: my-courses.php");
    exit;
}

// Get course information
$stmt = $db->prepare("
    SELECT c.id, c.title as course_title 
    FROM courses c 
    WHERE c.id = ?
");
$stmt->execute([$drill['course_id']]);
$course = $stmt->fetch();
$drill['course_title'] = $course ? $course['course_title'] : 'Unknown Course';

// Check enrollment
require_once 'classes/User.php';
$userObj = new User();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT * FROM course_enrollments 
    WHERE user_id = ? AND course_id = ? AND status IN ('enrolled', 'in_progress')
");
$stmt->execute([$userId, $drill['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment && !$userObj->isAdmin($userId)) {
    setFlashMessage('error', 'You must be enrolled in this course to take the skills drill');
    header("Location: course-detail?id=" . $drill['course_id']);
    exit;
}

// Get or create session
$sessionId = null;
if (isset($_SESSION['skills_drill_session_id'])) {
    // Check if session is for this drill
    $stmt = $db->prepare("SELECT * FROM skills_drill_sessions WHERE id = ? AND drill_id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->execute([$_SESSION['skills_drill_session_id'], $drillId, $_SESSION['user_id']]);
    $session = $stmt->fetch();
    
    if ($session) {
        $sessionId = $session['id'];
    }
}

// Start new session if needed
if (!$sessionId) {
    $sessionId = $skillsDrill->startSession($drillId, $_SESSION['user_id']);
    $_SESSION['skills_drill_session_id'] = $sessionId;
}

// Get questions
$questions = $skillsDrill->getQuestions($drillId, $drill['max_questions_per_session'], $drill['shuffle_questions']);

// Check if we have questions
if (empty($questions)) {
    $_SESSION['error_message'] = "No questions found for this skills drill.";
    header("Location: course-detail?id=" . $drill['course_id']);
    exit;
}

// Debug log
error_log("Skills drill $drillId loaded " . count($questions) . " questions");

// Get current session progress
$stmt = $db->prepare("SELECT question_id, MAX(is_correct) as solved 
                      FROM skills_drill_responses 
                      WHERE session_id = ? 
                      GROUP BY question_id");
$stmt->execute([$sessionId]);
$answeredQuestions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get user stats
$userStats = $skillsDrill->getUserStats($_SESSION['user_id'], $drillId);

$pageTitle = htmlspecialchars($drill['title']) . ' - Skills Practice';
require_once 'includes/header.php';
?>
<style>
        .question-card {
            min-height: 400px;
        }
        .option-button {
            text-align: left;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .option-button:hover {
            transform: translateX(5px);
        }
        .option-button.correct {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .option-button.incorrect {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .option-button:disabled {
            cursor: not-allowed;
            opacity: 0.8;
        }
        .points-display {
            font-size: 2rem;
            font-weight: bold;
        }
        .attempt-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin: 0 2px;
        }
        .attempt-indicator.used {
            background-color: #dc3545;
        }
        .attempt-indicator.available {
            background-color: #28a745;
        }
        .question-nav-btn {
            margin: 2px;
            min-width: 45px;
        }
        .question-nav-btn.answered {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
</style>

<div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card question-card" id="question-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <span id="question-number">Question 1</span> of <span id="total-questions"><?= count($questions) ?></span>
                            </h5>
                            <div>
                                <span class="me-3">Attempts: <span id="attempts-display"></span></span>
                                <span class="badge bg-primary">
                                    <span id="current-question-points">1</span> point
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="question-content">
                            <h6 class="mb-4" id="question-text"></h6>
                            <div id="answer-options"></div>
                            <div id="feedback-section" class="mt-4 d-none">
                                <div class="alert" id="feedback-alert">
                                    <div id="feedback-text"></div>
                                </div>
                            </div>
                            <div id="explanation-section" class="mt-3 d-none">
                                <div class="alert alert-success">
                                    <i class="bi bi-info-circle"></i> <strong>Explanation:</strong>
                                    <div id="explanation-text"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-center">
                            <button class="btn btn-success d-none" onclick="completeSession()" id="complete-btn">
                                <i class="bi bi-check-circle"></i> Complete Session
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- All Drills Card (moved from right column) -->
                <div class="card mt-3" id="all-drills">
                    <div class="card-header">
                        <h5 class="mb-0">All Drills</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Practice your skills with other available drills</p>
                        <a href="/skills-drills" class="btn btn-sm btn-secondary">
                            <i class="bi bi-list"></i> View All Drills
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Session Stats -->
                <div class="card mb-3" id="session-stats">
                    <div class="card-header">
                        <h5 class="mb-0">Current Session</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="points-display text-primary mb-3">
                            <span id="session-points">0</span> points
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="text-muted">Questions Answered</div>
                                <div class="h4" id="questions-answered">0</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Success Rate</div>
                                <div class="h4" id="success-rate">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Stats -->
                <?php if ($userStats): ?>
                <div class="card" id="overall-stats">
                    <div class="card-header">
                        <h5 class="mb-0">Your Overall Stats</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="bi bi-trophy text-warning"></i>
                                <strong>Total Points:</strong> <?= number_format($userStats['total_points'], 1) ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-star text-info"></i>
                                <strong>Best Session:</strong> <?= number_format($userStats['best_session_points'], 1) ?> points
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-calendar-check text-success"></i>
                                <strong>Sessions:</strong> <?= $userStats['total_sessions'] ?>
                            </li>
                            <li>
                                <i class="bi bi-question-circle text-primary"></i>
                                <strong>Questions Answered:</strong> <?= $userStats['total_questions_answered'] ?>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Current Course Card -->
                <div class="card mt-3" id="current-course">
                    <div class="card-header">
                        <h5 class="mb-0">Current Course</h5>
                    </div>
                    <div class="card-body">
                        <h6><?= htmlspecialchars($drill['course_title']) ?></h6>
                        <p class="mb-2"><?= htmlspecialchars($drill['title']) ?></p>
                        <a href="/course-detail?id=<?= $drill['course_id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-left"></i> Back to Course
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const questions = <?= json_encode($questions) ?>;
        const sessionId = <?= $sessionId ?>;
        const answeredQuestions = <?= json_encode($answeredQuestions) ?>;
        let currentQuestionIndex = 0;
        let sessionPoints = 0;
        let questionsAnswered = 0;
        let currentAttempts = {};
        let questionsToRepeat = []; // Track questions to repeat if answered incorrectly
        
        console.log('Questions loaded:', questions);
        console.log('Session ID:', sessionId);
        console.log('Number of questions:', questions.length);
        if (questions.length > 0) {
            console.log('First question:', questions[0]);
            console.log('First question options:', questions[0].options);
            if (questions[0].options && questions[0].options.length > 0) {
                console.log('First option structure:', questions[0].options[0]);
                console.log('Option keys:', Object.keys(questions[0].options[0]));
            }
        }
        
        function initializeDrill() {
            console.log('Initializing drill with', questions.length, 'questions');
            if (!questions || questions.length === 0) {
                console.error('No questions loaded!');
                alert('Error: No questions found for this drill.');
                return;
            }
            
            // Count already answered questions
            for (let qId in answeredQuestions) {
                if (answeredQuestions[qId] == 1) {
                    questionsAnswered++;
                }
            }
            
            displayQuestion(0);
            updateSessionStats();
        }
        
        function findQuestionIndex(questionId) {
            return questions.findIndex(q => q.id == questionId);
        }
        
        function displayQuestion(index) {
            currentQuestionIndex = index;
            const question = questions[index];
            const questionId = question.id;
            
            // Update question display
            const questionNumberEl = document.getElementById('question-number');
            const questionTextEl = document.getElementById('question-text');
            
            if (questionNumberEl) {
                questionNumberEl.textContent = `Question ${index + 1}`;
            } else {
                console.error('Question number element not found');
            }
            
            if (questionTextEl) {
                questionTextEl.textContent = question.question_text;
            } else {
                console.error('Question text element not found');
            }
            
            // Reset UI
            document.getElementById('feedback-section').classList.add('d-none');
            document.getElementById('explanation-section').classList.add('d-none');
            
            // Hint button removed - no longer needed
            
            // Get current attempts for this question
            if (!currentAttempts[questionId]) {
                currentAttempts[questionId] = 0;
            }
            
            // Update attempts display
            updateAttemptsDisplay(currentAttempts[questionId]);
            
            // Update points display
            updatePointsDisplay(currentAttempts[questionId]);
            
            // Display answer options
            displayAnswerOptions(question);
            
            // Check if should show complete button
            checkCompleteButton();
        }
        
        function displayAnswerOptions(question) {
            const container = document.getElementById('answer-options');
            container.innerHTML = '';
            
            console.log('Displaying options for question:', question);
            
            if (!question.options || question.options.length === 0) {
                console.error('No options found for question:', question);
                container.innerHTML = '<div class="alert alert-danger">No answer options available for this question.</div>';
                return;
            }
            
            const isAnswered = answeredQuestions[question.id] == 1;
            
            question.options.forEach((option, index) => {
                console.log('Option:', option);
                const button = document.createElement('button');
                button.className = 'btn btn-outline-primary option-button w-100';
                // Check if answer_text exists, if not try text property
                const answerText = option.answer_text || option.text || 'Option ' + (index + 1);
                // Use textContent to escape HTML entities
                const letter = String.fromCharCode(65 + index);
                button.textContent = `${letter}. ${answerText}`;
                button.setAttribute('data-option-id', option.id);
                button.setAttribute('data-question-id', question.id);
                button.onclick = () => submitAnswer(question.id, option.id);
                
                if (isAnswered) {
                    button.disabled = true;
                    if (option.is_correct == 1) {
                        button.classList.add('correct');
                    }
                }
                
                container.appendChild(button);
            });
            
            if (isAnswered && question.explanation) {
                const explanationTextEl = document.getElementById('explanation-text');
                const explanationSectionEl = document.getElementById('explanation-section');
                
                if (explanationTextEl) {
                    explanationTextEl.textContent = question.explanation;
                }
                if (explanationSectionEl) {
                    explanationSectionEl.classList.remove('d-none');
                }
            }
        }
        
        function updateAttemptsDisplay(attempts) {
            const container = document.getElementById('attempts-display');
            container.innerHTML = '';
            
            for (let i = 0; i < 4; i++) {
                const indicator = document.createElement('span');
                indicator.className = 'attempt-indicator ' + (i < attempts ? 'used' : 'available');
                container.appendChild(indicator);
            }
        }
        
        function updatePointsDisplay(attempts) {
            let points = 1;
            if (attempts === 1) points = 0;
            else if (attempts === 2) points = -0.5;
            else if (attempts >= 3) points = -1;
            
            const pointsEl = document.getElementById('current-question-points');
            if (pointsEl) {
                pointsEl.textContent = points;
            } else {
                console.error('Current question points element not found');
            }
        }
        
        // Hint functionality removed
        
        async function submitAnswer(questionId, answerOptionId) {
            try {
                console.log('Submitting answer:', { questionId, answerOptionId, sessionId });
                
                const response = await fetch('api/skills-drill-submit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        question_id: questionId,
                        answer_option_id: answerOptionId
                    })
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    throw new Error('Invalid JSON response from server');
                }
                
                console.log('API Result:', result);
                
                if (result.success) {
                    console.log('Answer submission successful');
                    currentAttempts[questionId] = result.attempt_number;
                    
                    // Update UI based on result
                    const buttons = document.querySelectorAll('.option-button');
                    console.log('Found buttons:', buttons.length);
                    buttons.forEach(btn => btn.disabled = true);
                    
                    const clickedButton = Array.from(buttons).find(btn => 
                        btn.getAttribute('data-option-id') == answerOptionId
                    );
                    
                    if (!clickedButton) {
                        console.error('Could not find clicked button for answer option:', answerOptionId);
                        console.error('Available buttons:', Array.from(buttons).map(btn => btn.getAttribute('data-option-id')));
                        return;
                    }
                    
                    if (result.is_correct) {
                        clickedButton.classList.add('correct');
                        answeredQuestions[questionId] = 1;
                        questionsAnswered++;
                        
                        // Show success feedback
                        const feedbackAlert = document.getElementById('feedback-alert');
                        const feedbackText = document.getElementById('feedback-text');
                        if (feedbackAlert && feedbackText) {
                            feedbackAlert.className = 'alert alert-success';
                            feedbackText.innerHTML = 
                                `<i class="bi bi-check-circle"></i> Correct! You earned ${result.points_earned} point(s).`;
                            document.getElementById('feedback-section').classList.remove('d-none');
                        }
                        
                        
                        // Show explanation if available
                        const question = questions[currentQuestionIndex];
                        if (question.explanation) {
                            const explanationTextEl = document.getElementById('explanation-text');
                            const explanationSectionEl = document.getElementById('explanation-section');
                            
                            if (explanationTextEl) {
                                explanationTextEl.textContent = question.explanation;
                            }
                            if (explanationSectionEl) {
                                explanationSectionEl.classList.remove('d-none');
                            }
                        }
                        
                        // Automatically go to next question after a short delay
                        setTimeout(() => {
                            if (currentQuestionIndex < questions.length - 1) {
                                displayQuestion(currentQuestionIndex + 1);
                            } else {
                                // On last question, show complete button
                                checkCompleteButton();
                            }
                        }, 1000); // 1 second delay to allow user to see feedback
                    } else {
                        clickedButton.classList.add('incorrect');
                        
                        // Show feedback
                        const feedbackAlert = document.getElementById('feedback-alert');
                        const feedbackText = document.getElementById('feedback-text');
                        if (feedbackAlert && feedbackText) {
                            feedbackAlert.className = 'alert alert-warning';
                            feedbackText.innerHTML = 
                                `<i class="bi bi-x-circle"></i> Incorrect. ${4 - result.attempt_number} attempt(s) remaining.`;
                            document.getElementById('feedback-section').classList.remove('d-none');
                        }
                        
                        // If first attempt was wrong, add to repeat list
                        if (result.attempt_number === 1 && !questionsToRepeat.includes(questionId)) {
                            questionsToRepeat.push(questionId);
                            // Add the question to the end of the questions array
                            const currentQuestion = questions.find(q => q.id == questionId);
                            if (currentQuestion) {
                                questions.push({...currentQuestion}); // Clone the question
                                // Update total questions display
                                const totalQuestionsEl = document.getElementById('total-questions');
                                if (totalQuestionsEl) {
                                    totalQuestionsEl.textContent = questions.length;
                                }
                            }
                        }
                        
                        // Re-enable buttons for next attempt
                        if (result.attempt_number < 4) {
                            setTimeout(() => {
                                buttons.forEach(btn => {
                                    if (!btn.classList.contains('incorrect')) {
                                        btn.disabled = false;
                                    }
                                });
                            }, 1500);
                        }
                    }
                    
                    document.getElementById('feedback-section').classList.remove('d-none');
                    
                    // Update displays
                    updateAttemptsDisplay(result.attempt_number);
                    updatePointsDisplay(result.attempt_number);
                    
                    // Update session points
                    const pointsEarned = parseFloat(result.points_earned) || 0;
                    sessionPoints += pointsEarned;
                    console.log('Points earned:', pointsEarned, 'Total session points:', sessionPoints);
                    updateSessionStats();
                    
                } else {
                    alert('Error submitting answer: ' + result.message);
                }
            } catch (error) {
                console.error('Error submitting answer:', error);
                alert('Error submitting answer: ' + error.message);
            }
        }
        
        function updateSessionStats() {
            const sessionPointsEl = document.getElementById('session-points');
            const questionsAnsweredEl = document.getElementById('questions-answered');
            const successRateEl = document.getElementById('success-rate');
            
            if (sessionPointsEl) sessionPointsEl.textContent = sessionPoints.toFixed(1);
            if (questionsAnsweredEl) questionsAnsweredEl.textContent = questionsAnswered;
            
            const successRate = questionsAnswered > 0 ? 
                Math.round((questionsAnswered / Object.keys(currentAttempts).length) * 100) : 0;
            if (successRateEl) successRateEl.textContent = successRate + '%';
        }
        
        // Navigation button functions removed - using question navigation only
        
        // Show complete button on last question
        function checkCompleteButton() {
            const isLastQuestion = currentQuestionIndex === questions.length - 1;
            const completeBtn = document.getElementById('complete-btn');
            if (completeBtn) {
                completeBtn.classList.toggle('d-none', !isLastQuestion);
            }
        }
        
        async function completeSession() {
            if (confirm('Are you sure you want to complete this session?')) {
                try {
                    const response = await fetch('api/skills-drill-complete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: sessionId
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        window.location.href = `skills-drill-results.php?session_id=${sessionId}`;
                    } else {
                        alert('Error completing session: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error completing session. Please try again.');
                }
            }
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', initializeDrill);
    </script>

<?php require_once 'includes/footer.php'; ?>