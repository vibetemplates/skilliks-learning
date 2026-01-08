<?php
/**
 * Survey Page
 * 
 * Displays and handles the skills assessment survey
 */

$page_title = 'Skills Survey';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Survey.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();


// Initialize Survey class
$survey = new Survey();

// Check if user is admin
require_once 'classes/Community.php';
$community = new Community();
$isAdmin = $community->isAdmin($currentCommunityId, $currentUserId);
$isGlobalAdmin = hasRole('administrator');

// Get the skills survey for current community
$skillsSurvey = $survey->getSurveyByCommunity($currentCommunityId, 'skills');

if (!$skillsSurvey) {
    setFlashMessage('error', "Survey not found.");
    header('Location: dashboard');
    exit;
}

$surveyId = $skillsSurvey['id'];

// Start survey tracking
$survey->startSurvey($surveyId, $currentUserId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $errors = [];
    $success = true;
    $savedCount = 0;
    $totalAnswered = 0;
    
    
    // Get all sections and questions
    $sections = $survey->getSurveySections($surveyId);
    
    foreach ($sections as $section) {
        $questions = $survey->getSectionQuestions($section['id']);
        
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $fieldName = 'question_' . $questionId;
            
            // Skip required validation - we want to save partial responses
            // Just process whatever was provided
            
            // Save response based on question type
            switch ($question['question_type']) {
                case 'text':
                case 'textarea':
                    if (isset($_POST[$fieldName])) {
                        $answerText = trim($_POST[$fieldName]);
                        if (!empty($answerText)) {
                            $saveResult = $survey->saveResponse($surveyId, $currentUserId, $questionId, $answerText, null);
                            if ($saveResult) {
                                $savedCount++;
                            }
                            $totalAnswered++;
                        }
                    }
                    break;
                    
                case 'radio':
                case 'dropdown':
                    if (isset($_POST[$fieldName])) {
                        $answerOptionId = intval($_POST[$fieldName]);
                        if ($answerOptionId > 0) {
                            $result = $survey->saveResponse($surveyId, $currentUserId, $questionId, null, $answerOptionId);
                            if ($result) {
                                $savedCount++;
                            }
                            $totalAnswered++;
                        }
                    }
                    break;
                    
                case 'checkbox':
                    if (isset($_POST[$fieldName])) {
                        $selectedOptions = $_POST[$fieldName];
                        if (!empty($selectedOptions)) {
                            if ($survey->saveMultipleResponses($surveyId, $currentUserId, $questionId, $selectedOptions)) {
                                $savedCount++;
                            }
                            $totalAnswered++;
                        }
                    }
                    break;
                    
                case 'ranking':
                    $rankingField = $fieldName . '_ranking';
                    if (isset($_POST[$rankingField]) && !empty($_POST[$rankingField])) {
                        $rankingData = json_decode($_POST[$rankingField], true);
                        if (!empty($rankingData)) {
                            if ($survey->saveRankingResponses($surveyId, $currentUserId, $questionId, $rankingData)) {
                                $savedCount++;
                            }
                            $totalAnswered++;
                        }
                    }
                    break;
            }
        }
    }
    
    // Update completion percentage
    $completionPercentage = $survey->updateCompletionPercentage($surveyId, $currentUserId);
    
    // Always show success if we saved any responses
    if ($savedCount > 0) {
        $message = "Survey responses saved successfully! ";
        if ($totalAnswered > 0) {
            $message .= "$savedCount response(s) saved. ";
        }
        $message .= "Progress: {$completionPercentage}%";
        
        if ($completionPercentage == 100) {
            $message .= " - Thank you for completing the survey!";
        } else {
            $message .= " - You can continue the survey later.";
        }
        
        setFlashMessage('success', $message);
        header('Location: survey');
        exit;
    } else if ($totalAnswered == 0) {
        setFlashMessage('warning', "No responses were provided. Please answer at least one question to save your progress.");
    } else {
        setFlashMessage('error', "There was an error saving your responses. Please try again.");
    }
}

// Get survey sections and questions
$sections = $survey->getSurveySections($surveyId);
$completionStatus = $survey->getCompletionStatus($surveyId, $currentUserId);

// Check if survey has any questions
$hasQuestions = false;
foreach ($sections as $section) {
    $questions = $survey->getSectionQuestions($section['id']);
    if (!empty($questions)) {
        $hasQuestions = true;
        break;
    }
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo htmlspecialchars($skillsSurvey['name']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="dashboard" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if ($isAdmin || $isGlobalAdmin): ?>
                <a href="survey-manage.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-gear me-1"></i>Manage Survey
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <?php if ($completionStatus): ?>
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span>Survey Progress</span>
            <span class="text-muted"><?php echo $completionStatus['completion_percentage']; ?>% Complete</span>
        </div>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar" role="progressbar" 
                 style="width: <?php echo $completionStatus['completion_percentage']; ?>%"
                 aria-valuenow="<?php echo $completionStatus['completion_percentage']; ?>" 
                 aria-valuemin="0" aria-valuemax="100">
                <?php echo $completionStatus['completion_percentage']; ?>%
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recalculate Lesson Plan Button -->
    <div class="mb-4">
        <button type="button" class="btn btn-success" id="recalculate-btn-top" onclick="recalculateLessonPlan()">
            <i class="bi bi-arrow-repeat me-2"></i>Recalculate Lesson Plan
        </button>
        <small class="text-muted ms-3">
            <i class="bi bi-lightbulb"></i> Generate course recommendations based on your current answers
        </small>
    </div>

    <?php if (!$hasQuestions): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        This survey doesn't have any questions yet.
        <?php if ($isAdmin || $isGlobalAdmin): ?>
        <a href="survey-manage.php" class="alert-link">Add questions</a> to get started.
        <?php endif; ?>
    </div>
    <?php else: ?>
    <form method="POST" action="survey" id="survey-form">
        <input type="hidden" name="submit_survey" value="1">
        <div class="accordion" id="surveyAccordion">
            <?php foreach ($sections as $sectionIndex => $section): ?>
            <?php $questions = $survey->getSectionQuestions($section['id']); ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?php echo $section['id']; ?>">
                    <button class="accordion-button <?php echo $sectionIndex > 0 ? 'collapsed' : ''; ?>" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#collapse<?php echo $section['id']; ?>" 
                            aria-expanded="<?php echo $sectionIndex === 0 ? 'true' : 'false'; ?>" 
                            aria-controls="collapse<?php echo $section['id']; ?>">
                        <strong><?php echo htmlspecialchars($section['name']); ?></strong>
                        <?php if ($section['description']): ?>
                        <span class="ms-2 text-muted">- <?php echo htmlspecialchars($section['description']); ?></span>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="collapse<?php echo $section['id']; ?>" 
                     class="accordion-collapse collapse <?php echo $sectionIndex === 0 ? 'show' : ''; ?>" 
                     aria-labelledby="heading<?php echo $section['id']; ?>" 
                     data-bs-parent="#surveyAccordion">
                    <div class="accordion-body">
                        <?php foreach ($questions as $questionIndex => $question): ?>
                        <?php 
                        $fieldName = 'question_' . $question['id'];
                        $userResponse = $survey->getUserResponse($currentUserId, $question['id']);
                        ?>
                        <div class="mb-4">
                            <label class="form-label">
                                <?php echo htmlspecialchars($question['question_text']); ?>
                                <?php if ($question['is_required']): ?>
                                <span class="text-danger">*</span>
                                <?php endif; ?>
                                <?php if ($question['help_text']): ?>
                                <i class="bi bi-info-circle ms-1" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top" 
                                   title="<?php echo htmlspecialchars($question['help_text']); ?>"></i>
                                <?php endif; ?>
                            </label>
                            
                            <?php switch ($question['question_type']): 
                                case 'text': ?>
                                    <input type="text" 
                                           class="form-control" 
                                           name="<?php echo $fieldName; ?>" 
                                           value="<?php echo htmlspecialchars($userResponse['answer_text'] ?? ''); ?>"
>
                                    <?php break; ?>
                                
                                <?php case 'textarea': ?>
                                    <textarea class="form-control" 
                                              name="<?php echo $fieldName; ?>" 
                                              rows="4"
   ><?php echo htmlspecialchars($userResponse['answer_text'] ?? ''); ?></textarea>
                                    <?php break; ?>
                                
                                <?php case 'radio': ?>
                                    <?php foreach ($question['answer_options'] as $option): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="<?php echo $fieldName; ?>" 
                                               id="option_<?php echo $option['id']; ?>" 
                                               value="<?php echo $option['id']; ?>"
                                               <?php echo ($userResponse && $userResponse['answer_option_id'] == $option['id']) ? 'checked' : ''; ?>
    >
                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if (!empty($option['description'])): ?>
                                            <i class="bi bi-info-circle ms-1" 
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="right" 
                                               title="<?php echo htmlspecialchars($option['description']); ?>"></i>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php break; ?>
                                
                                <?php case 'checkbox': ?>
                                    <?php 
                                    $selectedOptions = $survey->getUserMultipleResponses($currentUserId, $question['id']);
                                    ?>
                                    <?php foreach ($question['answer_options'] as $option): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="<?php echo $fieldName; ?>[]" 
                                               id="option_<?php echo $option['id']; ?>" 
                                               value="<?php echo $option['id']; ?>"
                                               <?php echo in_array($option['id'], $selectedOptions) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if (!empty($option['description'])): ?>
                                            <i class="bi bi-info-circle ms-1" 
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="right" 
                                               title="<?php echo htmlspecialchars($option['description']); ?>"></i>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php break; ?>
                                
                                <?php case 'dropdown': ?>
                                    <select class="form-select" 
                                            name="<?php echo $fieldName; ?>"
 >
                                        <option value="">-- Select an option --</option>
                                        <?php foreach ($question['answer_options'] as $option): ?>
                                        <option value="<?php echo $option['id']; ?>"
                                                <?php echo ($userResponse && $userResponse['answer_option_id'] == $option['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['text']); ?><?php echo !empty($option['description']) ? ' - ' . htmlspecialchars($option['description']) : ''; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php break; ?>
                                
                                <?php case 'ranking': ?>
                                    <?php 
                                    $userRankings = $survey->getUserRankingResponses($currentUserId, $question['id']);
                                    // Ensure rankings array has string keys for consistent comparison
                                    $normalizedRankings = [];
                                    foreach ($userRankings as $optionId => $rank) {
                                        $normalizedRankings[(string)$optionId] = $rank;
                                    }
                                    $userRankings = $normalizedRankings;
                                    ?>
                                    <div class="ranking-container" data-question-id="<?php echo $question['id']; ?>" id="ranking-container-<?php echo $question['id']; ?>">
                                        <div class="row">
                                            <div class="col-md-6" id="available-column-<?php echo $question['id']; ?>">
                                                <h6 class="mb-3">Available Items</h6>
                                                <ul class="list-group ranking-list available-list" id="available_<?php echo $question['id']; ?>">
                                                    <?php 
                                                    // Show unranked items in the left column
                                                    foreach ($question['answer_options'] as $option): 
                                                        // Convert to string to ensure consistent comparison
                                                        $optionId = (string)$option['id'];
                                                        if (!array_key_exists($optionId, $userRankings)):
                                                    ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center ranking-item" 
                                                        data-option-id="<?php echo $option['id']; ?>">
                                                        <span class="drag-handle">
                                                            <i class="bi bi-grip-vertical me-2"></i>
                                                            <?php echo htmlspecialchars($option['text']); ?>
                                                            <?php if (!empty($option['description'])): ?>
                                                            <i class="bi bi-info-circle ms-1" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-placement="right" 
                                                               title="<?php echo htmlspecialchars($option['description']); ?>"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                    </li>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </ul>
                                                <small class="form-text text-muted mt-2">
                                                    <i class="bi bi-arrow-right"></i> Drag items to the right to rank them
                                                </small>
                                            </div>
                                            <div class="col-md-6" id="ranked-column-<?php echo $question['id']; ?>">
                                                <h6 class="mb-3">Your Ranking</h6>
                                                <ul class="list-group ranking-list ranked-list" id="ranked_<?php echo $question['id']; ?>">
                                                    <?php 
                                                    // Sort ranked items by their ranking
                                                    $rankedOptions = [];
                                                    foreach ($question['answer_options'] as $option) {
                                                        // Convert to string to ensure consistent comparison
                                                        $optionId = (string)$option['id'];
                                                        if (array_key_exists($optionId, $userRankings)) {
                                                            $rankedOptions[$userRankings[$optionId]] = $option;
                                                        }
                                                    }
                                                    ksort($rankedOptions);
                                                    
                                                    $rankIndex = 1;
                                                    foreach ($rankedOptions as $option): 
                                                    ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center ranking-item" 
                                                        data-option-id="<?php echo $option['id']; ?>">
                                                        <span class="drag-handle">
                                                            <i class="bi bi-grip-vertical me-2"></i>
                                                            <?php echo htmlspecialchars($option['text']); ?>
                                                            <?php if (!empty($option['description'])): ?>
                                                            <i class="bi bi-info-circle ms-1" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-placement="right" 
                                                               title="<?php echo htmlspecialchars($option['description']); ?>"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="badge bg-primary rank-number"><?php echo $rankIndex++; ?></span>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <small class="form-text text-muted mt-2">
                                                    <i class="bi bi-arrow-up-down"></i> Drag items to reorder your ranking
                                                </small>
                                            </div>
                                        </div>
                                        <input type="hidden" name="<?php echo $fieldName; ?>_ranking" 
                                               id="<?php echo $fieldName; ?>_ranking" value="">
                                    </div>
                                    <?php break; ?>
                            <?php endswitch; ?>
                            
                            <div class="invalid-feedback">
                                This field is required.
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-4 mb-5">
            <button type="submit" name="submit_survey" class="btn btn-primary" id="save-btn">
                <i class="bi bi-check-circle me-2"></i>Save Survey Responses
            </button>
            <a href="dashboard" class="btn btn-secondary ms-2">
                <i class="bi bi-x-circle me-2"></i>Cancel
            </a>
            <small class="text-muted d-block mt-2">
                <i class="bi bi-info-circle"></i> You can save partial responses and continue the survey later.
            </small>
        </div>
    </form>
    
    <!-- Floating Submit Button -->
    <button type="button" class="floating-submit-btn" id="floating-submit-btn" style="display: none;">
        <i class="bi bi-save"></i>
    </button>
    <div class="floating-submit-tooltip">Save Survey Progress</div>
    
    <?php endif; ?>
</main>

<style>
.ranking-container {
    margin-top: 10px;
}

.ranking-list {
    list-style: none;
    padding: 0;
    margin: 0;
    min-height: 100px;
    background-color: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 10px;
}

.ranking-list.available-list {
    background-color: #f8f9fa;
    border-color: #6c757d;
}

.ranking-list.ranked-list {
    background-color: #e7f3ff;
    border-color: #0d6efd;
}

.ranking-list.drag-over {
    background-color: #e9ecef;
    border-color: #0d6efd;
    border-style: solid;
}

.ranking-item {
    cursor: move;
    transition: all 0.2s;
    background-color: white;
    margin-bottom: 5px;
}

.ranking-item:last-child {
    margin-bottom: 0;
}

.ranking-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ranking-item.dragging {
    opacity: 0.5;
    background-color: #e9ecef;
}

.drag-handle {
    display: flex;
    align-items: center;
}

.drag-handle i {
    color: #6c757d;
}

.rank-number {
    min-width: 30px;
    text-align: center;
}

.ranking-container h6 {
    font-weight: 600;
    color: #495057;
}

.ranking-list:empty::after {
    content: "Drop items here";
    display: block;
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 20px;
}

/* Info icon styling */
.bi-info-circle {
    color: #6c757d;
    cursor: help;
    font-size: 0.875rem;
}

.bi-info-circle:hover {
    color: #0d6efd;
}

/* Ensure info icons don't break layout in ranking items */
.ranking-item .drag-handle {
    flex: 1;
}

.ranking-item .bi-info-circle {
    margin-left: 0.5rem;
}

/* Style for dragging */
.dragging {
    opacity: 0.5;
}

/* Floating submit button styles */
.floating-submit-btn {
    position: fixed;
    right: 30px;
    bottom: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #0d6efd;
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1050;
}

.floating-submit-btn:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
}

.floating-submit-btn:active {
    transform: translateY(0);
}

.floating-submit-btn i {
    font-size: 24px;
}

.floating-submit-btn.saving {
    pointer-events: none;
    opacity: 0.8;
}

.floating-submit-btn .spinner-border {
    width: 24px;
    height: 24px;
}

/* Tooltip for floating button */
.floating-submit-tooltip {
    position: fixed;
    right: 100px;
    bottom: 45px;
    background-color: #212529;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 1051;
}

.floating-submit-tooltip::after {
    content: '';
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%);
    border: 4px solid transparent;
    border-left-color: #212529;
}

.floating-submit-btn:hover + .floating-submit-tooltip {
    opacity: 1;
}

/* Hide floating button on small screens to avoid overlapping with form */
@media (max-width: 767px) {
    .floating-submit-btn,
    .floating-submit-tooltip {
        display: none;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const surveyForm = document.getElementById('survey-form');
    const saveBtn = document.getElementById('save-btn');
    
    if (surveyForm && saveBtn) {
        surveyForm.addEventListener('submit', function(event) {
            // Show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
            
            // Ensure all ranking hidden inputs are updated before submission
            const rankingContainers = document.querySelectorAll('.ranking-container');
            rankingContainers.forEach(function(container) {
                const questionId = container.dataset.questionId;
                const hiddenInput = document.getElementById('question_' + questionId + '_ranking');
                const rankedList = container.querySelector('.ranked-list');
                const rankedItems = rankedList.querySelectorAll('.ranking-item');
                const rankings = {};
                
                rankedItems.forEach(function(item, index) {
                    rankings[item.dataset.optionId] = index + 1;
                });
                
                hiddenInput.value = JSON.stringify(rankings);
            });
            
            // Allow form submission regardless of validation
            return true;
        });
    }
});

// Floating submit button functionality
document.addEventListener('DOMContentLoaded', function() {
    const floatingBtn = document.getElementById('floating-submit-btn');
    const surveyForm = document.getElementById('survey-form');
    
    if (floatingBtn && surveyForm) {
        // Show floating button when user scrolls down
        let lastScrollTop = 0;
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Show button when user has scrolled down at least 200px
            if (scrollTop > 200) {
                floatingBtn.style.display = 'flex';
            } else {
                floatingBtn.style.display = 'none';
            }
            
            lastScrollTop = scrollTop;
        });
        
        // Handle floating button click
        floatingBtn.addEventListener('click', function() {
            // Show loading state
            floatingBtn.classList.add('saving');
            floatingBtn.innerHTML = '<span class="spinner-border" role="status"></span>';
            
            // Trigger form submission
            const submitBtn = document.getElementById('save-btn');
            if (submitBtn) {
                submitBtn.click();
            }
        });
    }
});

// Recalculate lesson plan function
function recalculateLessonPlan() {
    // Try to get the button that was clicked (works with both top and bottom buttons)
    const recalculateBtn = document.getElementById('recalculate-btn-top') || document.getElementById('recalculate-btn');
    const surveyForm = document.getElementById('survey-form');
    
    // Show loading state
    recalculateBtn.disabled = true;
    recalculateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Recalculating...';
    
    // Gather current form data
    const formData = new FormData(surveyForm);
    formData.append('action', 'recalculate_recommendations');
    
    // Update ranking data before sending
    const rankingContainers = document.querySelectorAll('.ranking-container');
    rankingContainers.forEach(function(container) {
        const questionId = container.dataset.questionId;
        const hiddenInput = document.getElementById('question_' + questionId + '_ranking');
        const rankedList = container.querySelector('.ranked-list');
        const rankedItems = rankedList.querySelectorAll('.ranking-item');
        const rankings = {};
        
        rankedItems.forEach(function(item, index) {
            rankings[item.dataset.optionId] = index + 1;
        });
        
        hiddenInput.value = JSON.stringify(rankings);
        formData.set(hiddenInput.name, hiddenInput.value);
    });
    
    // Send AJAX request
    fetch('ajax/recalculate-recommendations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>
                ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Insert alert after the button group
            const buttonGroup = recalculateBtn.parentElement;
            buttonGroup.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
            
            // Optionally redirect to recommendations page after a delay
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 2000);
            }
        } else {
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="bi bi-exclamation-circle me-2"></i>
                ${data.message || 'An error occurred while recalculating recommendations.'}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const buttonGroup = recalculateBtn.parentElement;
            buttonGroup.appendChild(alert);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mt-3';
        alert.innerHTML = `
            <i class="bi bi-exclamation-circle me-2"></i>
            An error occurred while recalculating recommendations.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const buttonGroup = recalculateBtn.parentElement;
        buttonGroup.appendChild(alert);
    })
    .finally(() => {
        // Reset button state
        recalculateBtn.disabled = false;
        recalculateBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Recalculate Lesson Plan';
    });
}

// Initialize drag and drop for ranking questions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    const rankingContainers = document.querySelectorAll('.ranking-container');
    
    rankingContainers.forEach(function(container) {
        const questionId = container.dataset.questionId;
        const hiddenInput = document.getElementById('question_' + questionId + '_ranking');
        const availableList = container.querySelector('.available-list');
        const rankedList = container.querySelector('.ranked-list');
        
        // Function to update rankings in the ranked list
        function updateRankings() {
            const rankedItems = rankedList.querySelectorAll('.ranking-item');
            const rankings = {};
            
            rankedItems.forEach(function(item, index) {
                const optionId = item.dataset.optionId;
                const rankNumber = index + 1;
                
                // Update or create rank badge
                let rankBadge = item.querySelector('.rank-number');
                if (!rankBadge) {
                    rankBadge = document.createElement('span');
                    rankBadge.className = 'badge bg-primary rank-number';
                    item.appendChild(rankBadge);
                }
                rankBadge.textContent = rankNumber;
                
                // Store ranking data
                rankings[optionId] = rankNumber;
            });
            
            // Update hidden input with JSON data (only includes ranked items)
            hiddenInput.value = JSON.stringify(rankings);
            
            // Reinitialize tooltips after drag and drop
            var tooltipTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                // Dispose existing tooltip if any
                var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                // Create new tooltip
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Initialize Sortable for available items list
        new Sortable(availableList, {
            group: 'ranking-' + questionId,
            animation: 150,
            ghostClass: 'dragging',
            handle: '.drag-handle',
            sort: false, // Disable sorting within available list
            onAdd: function(evt) {
                // Remove rank badge when item is moved back to available
                const rankBadge = evt.item.querySelector('.rank-number');
                if (rankBadge) {
                    rankBadge.remove();
                }
                updateRankings();
            }
        });
        
        // Initialize Sortable for ranked items list
        new Sortable(rankedList, {
            group: 'ranking-' + questionId,
            animation: 150,
            ghostClass: 'dragging',
            handle: '.drag-handle',
            onAdd: function(evt) {
                updateRankings();
            },
            onUpdate: function(evt) {
                updateRankings();
            }
        });
        
        // Set initial rankings data
        updateRankings();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>