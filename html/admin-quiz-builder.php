<?php
/**
 * Admin Quiz Builder
 * 
 * Interface for creating and editing quizzes for lessons
 */

$page_title = 'Quiz Builder';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Course.php';
require_once 'classes/Lesson.php';

// Require login
requireLogin();

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: /courses');
    exit;
}

$db = getDB();
$communityId = getCurrentCommunityId();
$courseClass = new Course($db, $communityId);
$lessonClass = new Lesson($db);

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

// Parse existing quiz data if any
$quizData = [];
if ($lesson['quiz_data']) {
    $quizData = json_decode($lesson['quiz_data'], true) ?: [];
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mt-3">
            <li class="breadcrumb-item"><a href="courses">Courses</a></li>
            <li class="breadcrumb-item"><a href="course-detail?id=<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></a></li>
            <li class="breadcrumb-item"><a href="admin-lesson-edit.php?id=<?php echo $lessonId; ?>"><?php echo htmlspecialchars($lesson['title']); ?></a></li>
            <li class="breadcrumb-item active">Quiz Builder</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quiz Builder: <?php echo htmlspecialchars($lesson['title']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="previewQuiz()">
                <i class="bi bi-eye"></i> Preview
            </button>
            <button type="button" class="btn btn-sm btn-primary" onclick="saveQuiz()">
                <i class="bi bi-save"></i> Save Quiz
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Quiz Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quiz Settings</h5>
                </div>
                <div class="card-body">
                    <form id="quiz-settings-form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quiz-title" class="form-label">Quiz Title</label>
                                <input type="text" class="form-control" id="quiz-title" 
                                       value="<?php echo htmlspecialchars($quizData['title'] ?? 'Quiz for ' . $lesson['title']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="passing-score" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="passing-score" min="0" max="100" 
                                       value="<?php echo $quizData['passing_score'] ?? 70; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="time-limit" class="form-label">Time Limit (minutes)</label>
                                <input type="number" class="form-control" id="time-limit" min="0" 
                                       value="<?php echo $quizData['time_limit'] ?? 0; ?>"
                                       placeholder="0 for no limit">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max-attempts" class="form-label">Max Attempts</label>
                                <input type="number" class="form-control" id="max-attempts" min="0" 
                                       value="<?php echo $quizData['max_attempts'] ?? 0; ?>"
                                       placeholder="0 for unlimited">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="randomize-questions" 
                                           <?php echo ($quizData['randomize_questions'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="randomize-questions">
                                        Randomize Questions
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quiz-instructions" class="form-label">Instructions</label>
                            <textarea class="form-control" id="quiz-instructions" rows="3"><?php echo htmlspecialchars($quizData['instructions'] ?? ''); ?></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Questions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Questions</h5>
                    <button type="button" class="btn btn-sm btn-success" onclick="addQuestion()">
                        <i class="bi bi-plus-circle"></i> Add Question
                    </button>
                </div>
                <div class="card-body">
                    <div id="questions-container">
                        <?php if (empty($quizData['questions'])): ?>
                            <p class="text-muted text-center py-4">No questions yet. Click "Add Question" to get started.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Question Types -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Question Types</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary text-start" onclick="addQuestion('multiple_choice')">
                            <i class="bi bi-ui-radios"></i> Multiple Choice
                        </button>
                        <button type="button" class="btn btn-outline-primary text-start" onclick="addQuestion('true_false')">
                            <i class="bi bi-check2-square"></i> True/False
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quiz Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quiz Summary</h5>
                </div>
                <div class="card-body">
                    <div id="quiz-summary">
                        <p class="mb-2"><strong>Total Questions:</strong> <span id="total-questions">0</span></p>
                        <p class="mb-2"><strong>Total Points:</strong> <span id="total-points">0</span></p>
                        <p class="mb-0"><strong>Est. Time:</strong> <span id="est-time">0 min</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Question Template -->
<script id="question-template" type="text/template">
    <div class="question-item card mb-3" data-question-id="">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <span class="question-number fw-bold"></span>
                <span class="badge bg-secondary question-type-badge ms-2"></span>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveQuestion(this, 'up')">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveQuestion(this, 'down')">
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteQuestion(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Question</label>
                <textarea class="form-control question-text" rows="2" placeholder="Enter your question..."></textarea>
            </div>
            
            <div class="question-options"></div>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Points</label>
                    <input type="number" class="form-control question-points" min="1" value="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Explanation (optional)</label>
                    <textarea class="form-control question-explanation" rows="2" placeholder="Explain the correct answer..."></textarea>
                </div>
            </div>
        </div>
    </div>
</script>

<script>
// Initialize quiz data
let quizData = <?php echo json_encode($quizData); ?> || { questions: [] };
let questionIdCounter = quizData.questions.length;

// Initialize questions on page load
document.addEventListener('DOMContentLoaded', function() {
    if (quizData.questions && quizData.questions.length > 0) {
        quizData.questions.forEach(question => {
            addQuestionToDOM(question);
        });
    }
    updateQuizSummary();
});

function addQuestion(type = 'multiple_choice') {
    const question = {
        id: `q${++questionIdCounter}`,
        type: type,
        question: '',
        points: 10,
        explanation: ''
    };
    
    switch(type) {
        case 'multiple_choice':
            question.options = ['', '', '', ''];
            question.correct_answer = 0;
            break;
        case 'true_false':
            question.options = ['True', 'False'];
            question.correct_answer = 0;
            break;
    }
    
    quizData.questions.push(question);
    addQuestionToDOM(question);
    updateQuizSummary();
}

function addQuestionToDOM(question) {
    const template = document.getElementById('question-template').innerHTML;
    const container = document.getElementById('questions-container');
    
    // Remove empty message if exists
    const emptyMsg = container.querySelector('.text-muted');
    if (emptyMsg) emptyMsg.remove();
    
    // Create new question element
    const div = document.createElement('div');
    div.innerHTML = template;
    const questionEl = div.firstElementChild;
    
    // Set question data
    questionEl.dataset.questionId = question.id;
    questionEl.querySelector('.question-number').textContent = `Question ${quizData.questions.length}`;
    questionEl.querySelector('.question-type-badge').textContent = getQuestionTypeLabel(question.type);
    questionEl.querySelector('.question-text').value = question.question || '';
    questionEl.querySelector('.question-points').value = question.points || 10;
    questionEl.querySelector('.question-explanation').value = question.explanation || '';
    
    // Add type-specific options
    const optionsContainer = questionEl.querySelector('.question-options');
    switch(question.type) {
        case 'multiple_choice':
            addMultipleChoiceOptions(optionsContainer, question);
            break;
        case 'true_false':
            addTrueFalseOptions(optionsContainer, question);
            break;
    }
    
    container.appendChild(questionEl);
}

function addMultipleChoiceOptions(container, question) {
    const html = `
        <div class="mb-3">
            <label class="form-label">Options (select the correct answer)</label>
            ${(question.options || ['', '', '', '']).map((option, index) => `
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="radio" name="correct_${question.id}" 
                               value="${index}" ${question.correct_answer == index ? 'checked' : ''}>
                    </div>
                    <input type="text" class="form-control option-input" placeholder="Option ${String.fromCharCode(65 + index)}" 
                           value="${option || ''}">
                </div>
            `).join('')}
        </div>
    `;
    container.innerHTML = html;
}

function addTrueFalseOptions(container, question) {
    const html = `
        <div class="mb-3">
            <label class="form-label">Correct Answer</label>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="correct_${question.id}" 
                           id="true_${question.id}" value="0" ${question.correct_answer == 0 ? 'checked' : ''}>
                    <label class="form-check-label" for="true_${question.id}">True</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="correct_${question.id}" 
                           id="false_${question.id}" value="1" ${question.correct_answer == 1 ? 'checked' : ''}>
                    <label class="form-check-label" for="false_${question.id}">False</label>
                </div>
            </div>
        </div>
    `;
    container.innerHTML = html;
}


function getQuestionTypeLabel(type) {
    const labels = {
        'multiple_choice': 'Multiple Choice',
        'true_false': 'True/False'
    };
    return labels[type] || type;
}

function deleteQuestion(btn) {
    if (!confirm('Are you sure you want to delete this question?')) return;
    
    const questionEl = btn.closest('.question-item');
    const questionId = questionEl.dataset.questionId;
    
    // Remove from data
    quizData.questions = quizData.questions.filter(q => q.id !== questionId);
    
    // Remove from DOM
    questionEl.remove();
    
    // Update numbering
    updateQuestionNumbers();
    updateQuizSummary();
}

function moveQuestion(btn, direction) {
    const questionEl = btn.closest('.question-item');
    const questionId = questionEl.dataset.questionId;
    const currentIndex = quizData.questions.findIndex(q => q.id === questionId);
    
    if (direction === 'up' && currentIndex > 0) {
        // Swap in array
        [quizData.questions[currentIndex], quizData.questions[currentIndex - 1]] = 
        [quizData.questions[currentIndex - 1], quizData.questions[currentIndex]];
        
        // Move in DOM
        questionEl.previousElementSibling.before(questionEl);
    } else if (direction === 'down' && currentIndex < quizData.questions.length - 1) {
        // Swap in array
        [quizData.questions[currentIndex], quizData.questions[currentIndex + 1]] = 
        [quizData.questions[currentIndex + 1], quizData.questions[currentIndex]];
        
        // Move in DOM
        questionEl.nextElementSibling.after(questionEl);
    }
    
    updateQuestionNumbers();
}

function updateQuestionNumbers() {
    document.querySelectorAll('.question-item').forEach((el, index) => {
        el.querySelector('.question-number').textContent = `Question ${index + 1}`;
    });
}

function updateQuizSummary() {
    const totalQuestions = quizData.questions.length;
    const totalPoints = quizData.questions.reduce((sum, q) => sum + (parseInt(q.points) || 0), 0);
    const estTime = Math.ceil(totalQuestions * 1.5); // Estimate 1.5 min per question
    
    document.getElementById('total-questions').textContent = totalQuestions;
    document.getElementById('total-points').textContent = totalPoints;
    document.getElementById('est-time').textContent = estTime + ' min';
}

function collectQuizData() {
    // Collect settings
    const settings = {
        title: document.getElementById('quiz-title').value,
        instructions: document.getElementById('quiz-instructions').value,
        passing_score: parseInt(document.getElementById('passing-score').value) || 70,
        time_limit: parseInt(document.getElementById('time-limit').value) || 0,
        max_attempts: parseInt(document.getElementById('max-attempts').value) || 0,
        randomize_questions: document.getElementById('randomize-questions').checked
    };
    
    // Collect questions
    const questions = [];
    document.querySelectorAll('.question-item').forEach(questionEl => {
        const questionId = questionEl.dataset.questionId;
        const questionData = quizData.questions.find(q => q.id === questionId);
        
        if (!questionData) return;
        
        const question = {
            id: questionId,
            type: questionData.type,
            question: questionEl.querySelector('.question-text').value,
            points: parseInt(questionEl.querySelector('.question-points').value) || 10,
            explanation: questionEl.querySelector('.question-explanation').value
        };
        
        // Collect type-specific data
        switch(questionData.type) {
            case 'multiple_choice':
                question.options = Array.from(questionEl.querySelectorAll('.option-input')).map(input => input.value);
                question.correct_answer = parseInt(questionEl.querySelector('input[name="correct_' + questionId + '"]:checked')?.value || 0);
                break;
            case 'true_false':
                question.options = ['True', 'False'];
                question.correct_answer = parseInt(questionEl.querySelector('input[name="correct_' + questionId + '"]:checked')?.value || 0);
                break;
        }
        
        questions.push(question);
    });
    
    return { ...settings, questions };
}

function saveQuiz() {
    const quizData = collectQuizData();
    
    // Validate
    if (quizData.questions.length === 0) {
        alert('Please add at least one question to the quiz.');
        return;
    }
    
    // Validate each question
    for (let i = 0; i < quizData.questions.length; i++) {
        const q = quizData.questions[i];
        if (!q.question.trim()) {
            alert(`Question ${i + 1} is empty. Please enter a question.`);
            return;
        }
        
        if (q.type === 'multiple_choice' && q.options.some(opt => !opt.trim())) {
            alert(`Question ${i + 1}: Please fill in all options.`);
            return;
        }
        
    }
    
    // Save via AJAX
    fetch('api/quiz-save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            lesson_id: <?php echo $lessonId; ?>,
            quiz_data: quizData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Quiz saved successfully!');
            // Update local data
            this.quizData = quizData;
        } else {
            alert('Error saving quiz: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving quiz. Please try again.');
    });
}

function previewQuiz() {
    const quizData = collectQuizData();
    
    if (quizData.questions.length === 0) {
        alert('Please add questions before previewing.');
        return;
    }
    
    // Store quiz data temporarily
    sessionStorage.setItem('previewQuizData', JSON.stringify(quizData));
    
    // Open preview in new window
    window.open('quiz-preview.php?lesson_id=<?php echo $lessonId; ?>', '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>