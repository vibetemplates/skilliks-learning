<?php
/**
 * Survey Management Page
 * 
 * Allows admins to manage survey sections, questions, and answer options
 */

$page_title = 'Manage Survey';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Survey.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Check if user is admin
$community = new Community();
$isAdmin = $community->isAdmin($currentCommunityId, $currentUserId);
$isGlobalAdmin = hasRole('administrator');

if (!$isAdmin && !$isGlobalAdmin) {
    $_SESSION['error'] = "You don't have permission to manage surveys.";
    header('Location: dashboard');
    exit;
}

// Initialize Survey class
$survey = new Survey();

// Get the skills survey for current community
$skillsSurvey = $survey->getSurveyByCommunity($currentCommunityId, 'skills');

if (!$skillsSurvey) {
    $_SESSION['error'] = "Survey not found.";
    header('Location: dashboard');
    exit;
}

$surveyId = $skillsSurvey['id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_section':
            $name = trim($_POST['section_name']);
            $description = trim($_POST['section_description']);
            $displayOrder = intval($_POST['section_order']);
            
            if (!empty($name)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO survey_sections (survey_id, name, description, display_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$surveyId, $name, $description, $displayOrder]);
                    $_SESSION['success'] = "Section added successfully.";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error adding section: " . $e->getMessage();
                }
            }
            break;
            
        case 'edit_section':
            $sectionId = intval($_POST['section_id']);
            $name = trim($_POST['section_name']);
            $description = trim($_POST['section_description']);
            $displayOrder = intval($_POST['section_order']);
            $isActive = isset($_POST['section_active']) ? 1 : 0;
            
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    UPDATE survey_sections 
                    SET name = ?, description = ?, display_order = ?, is_active = ?
                    WHERE id = ? AND survey_id = ?
                ");
                $stmt->execute([$name, $description, $displayOrder, $isActive, $sectionId, $surveyId]);
                $_SESSION['success'] = "Section updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating section: " . $e->getMessage();
            }
            break;
            
        case 'delete_section':
            $sectionId = intval($_POST['section_id']);
            
            try {
                $db = getDB();
                // Check if section has questions
                $stmt = $db->prepare("SELECT COUNT(*) FROM survey_questions WHERE section_id = ?");
                $stmt->execute([$sectionId]);
                $questionCount = $stmt->fetchColumn();
                
                if ($questionCount > 0) {
                    $_SESSION['error'] = "Cannot delete section with existing questions. Delete questions first.";
                } else {
                    $stmt = $db->prepare("DELETE FROM survey_sections WHERE id = ? AND survey_id = ?");
                    $stmt->execute([$sectionId, $surveyId]);
                    $_SESSION['success'] = "Section deleted successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting section: " . $e->getMessage();
            }
            break;
            
        case 'add_question':
            $sectionId = intval($_POST['section_id']);
            $questionText = trim($_POST['question_text']);
            $questionType = $_POST['question_type'];
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $displayOrder = intval($_POST['question_order']);
            $helpText = trim($_POST['help_text']);
            
            if (!empty($questionText)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO survey_questions 
                        (section_id, question_text, question_type, is_required, display_order, help_text)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$sectionId, $questionText, $questionType, $isRequired, $displayOrder, $helpText]);
                    
                    $questionId = $db->lastInsertId();
                    
                    // Add answer options if provided
                    if (in_array($questionType, ['radio', 'checkbox', 'dropdown', 'ranking']) && !empty($_POST['answer_options'])) {
                        $options = explode("\n", trim($_POST['answer_options']));
                        $order = 1;
                        
                        $stmt = $db->prepare("
                            INSERT INTO survey_answer_options (question_id, option_text, display_order)
                            VALUES (?, ?, ?)
                        ");
                        
                        foreach ($options as $option) {
                            $option = trim($option);
                            if (!empty($option)) {
                                $stmt->execute([$questionId, $option, $order++]);
                            }
                        }
                    }
                    
                    $_SESSION['success'] = "Question added successfully.";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error adding question: " . $e->getMessage();
                }
            }
            break;
            
        case 'edit_question':
            $questionId = intval($_POST['question_id']);
            $questionText = trim($_POST['question_text']);
            $questionType = $_POST['question_type'];
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $displayOrder = intval($_POST['question_order']);
            $helpText = trim($_POST['help_text']);
            $isActive = isset($_POST['question_active']) ? 1 : 0;
            
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    UPDATE survey_questions 
                    SET question_text = ?, question_type = ?, is_required = ?, 
                        display_order = ?, help_text = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$questionText, $questionType, $isRequired, $displayOrder, $helpText, $isActive, $questionId]);
                
                // Update answer options if changed
                if (in_array($questionType, ['radio', 'checkbox', 'dropdown', 'ranking']) && isset($_POST['answer_options'])) {
                    // Delete existing options
                    $stmt = $db->prepare("DELETE FROM survey_answer_options WHERE question_id = ?");
                    $stmt->execute([$questionId]);
                    
                    // Add new options
                    $options = explode("\n", trim($_POST['answer_options']));
                    $order = 1;
                    
                    $stmt = $db->prepare("
                        INSERT INTO survey_answer_options (question_id, option_text, display_order)
                        VALUES (?, ?, ?)
                    ");
                    
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (!empty($option)) {
                            $stmt->execute([$questionId, $option, $order++]);
                        }
                    }
                }
                
                $_SESSION['success'] = "Question updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating question: " . $e->getMessage();
            }
            break;
            
        case 'delete_question':
            $questionId = intval($_POST['question_id']);
            
            try {
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM survey_questions WHERE id = ?");
                $stmt->execute([$questionId]);
                $_SESSION['success'] = "Question deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting question: " . $e->getMessage();
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: survey-manage.php');
    exit;
}

// Get sections with questions
$sections = $survey->getSurveySections($surveyId);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Manage Survey: <?php echo htmlspecialchars($skillsSurvey['name']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="survey" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Survey
                </a>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                <i class="bi bi-plus-circle me-1"></i>Add Section
            </button>
        </div>
    </div>

    <?php if ($skillsSurvey['description']): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <?php echo htmlspecialchars($skillsSurvey['description']); ?>
    </div>
    <?php endif; ?>

    <!-- Sections and Questions -->
    <div class="row">
        <div class="col-12">
            <?php foreach ($sections as $section): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($section['name']); ?>
                                <?php if (!$section['is_active']): ?>
                                <span class="badge bg-secondary ms-2">Inactive</span>
                                <?php endif; ?>
                            </h5>
                            <?php if ($section['description']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($section['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($section['description'] ?? '', ENT_QUOTES); ?>', <?php echo $section['display_order']; ?>, <?php echo $section['is_active']; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['name'], ENT_QUOTES); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-success" 
                                    onclick="addQuestion(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['name'], ENT_QUOTES); ?>')">
                                <i class="bi bi-plus"></i> Add Question
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php 
                    $questions = $survey->getSectionQuestions($section['id']);
                    if (empty($questions)): 
                    ?>
                    <p class="text-muted mb-0">No questions in this section yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th width="50">Order</th>
                                    <th>Question</th>
                                    <th width="120">Type</th>
                                    <th width="80">Required</th>
                                    <th width="80">Active</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $question): ?>
                                <tr>
                                    <td><?php echo $question['display_order']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                        <?php if ($question['help_text']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($question['help_text']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($question['answer_options'])): ?>
                                        <br><small class="text-info">
                                            Options: <?php 
                                            $optionTexts = array_column($question['answer_options'], 'text');
                                            echo htmlspecialchars(implode(', ', array_slice($optionTexts, 0, 3)));
                                            if (count($optionTexts) > 3) echo '...';
                                            ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $question['question_type']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($question['is_required']): ?>
                                        <i class="bi bi-check-circle text-success"></i>
                                        <?php else: ?>
                                        <i class="bi bi-x-circle text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($question['is_active']): ?>
                                        <i class="bi bi-check-circle text-success"></i>
                                        <?php else: ?>
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick='editQuestion(<?php echo json_encode($question); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteQuestion(<?php echo $question['id']; ?>, '<?php echo htmlspecialchars(substr($question['question_text'], 0, 50), ENT_QUOTES); ?>...')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_section">
                <div class="modal-header">
                    <h5 class="modal-title">Add Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="section_name" class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="section_name" name="section_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="section_description" name="section_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="section_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="section_order" name="section_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_section">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_section_name" class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_section_description" name="section_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_section_order" name="section_order">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_section_active" name="section_active" value="1">
                            <label class="form-check-label" for="edit_section_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="section_id" id="add_question_section_id">
                <div class="modal-header">
                    <h5 class="modal-title">Add Question to <span id="add_question_section_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="question_type" class="form-label">Question Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="question_type" name="question_type" required onchange="toggleAnswerOptions()">
                                    <option value="text">Text Input</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="radio">Radio Buttons</option>
                                    <option value="checkbox">Checkboxes</option>
                                    <option value="dropdown">Dropdown</option>
                                    <option value="ranking">Ranking</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="question_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="question_order" name="question_order" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="help_text" class="form-label">Help Text</label>
                        <input type="text" class="form-control" id="help_text" name="help_text" placeholder="Optional help text for the user">
                    </div>
                    <div class="mb-3" id="answer_options_div" style="display: none;">
                        <label for="answer_options" class="form-label">Answer Options <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="answer_options" name="answer_options" rows="5" 
                                  placeholder="Enter one option per line"></textarea>
                        <small class="form-text text-muted">Enter each answer option on a new line</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1">
                            <label class="form-check-label" for="is_required">
                                Required Question
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_question_text" class="form-label">Question Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_question_text" name="question_text" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_question_type" class="form-label">Question Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_question_type" name="question_type" required onchange="toggleEditAnswerOptions()">
                                    <option value="text">Text Input</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="radio">Radio Buttons</option>
                                    <option value="checkbox">Checkboxes</option>
                                    <option value="dropdown">Dropdown</option>
                                    <option value="ranking">Ranking</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_question_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="edit_question_order" name="question_order">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_help_text" class="form-label">Help Text</label>
                        <input type="text" class="form-control" id="edit_help_text" name="help_text">
                    </div>
                    <div class="mb-3" id="edit_answer_options_div" style="display: none;">
                        <label for="edit_answer_options" class="form-label">Answer Options <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_answer_options" name="answer_options" rows="5"></textarea>
                        <small class="form-text text-muted">Enter each answer option on a new line</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_required" name="is_required" value="1">
                            <label class="form-check-label" for="edit_is_required">
                                Required Question
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_question_active" name="question_active" value="1">
                            <label class="form-check-label" for="edit_question_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Forms -->
<form id="deleteSectionForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete_section">
    <input type="hidden" name="section_id" id="delete_section_id">
</form>

<form id="deleteQuestionForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete_question">
    <input type="hidden" name="question_id" id="delete_question_id">
</form>

<script>
// Section management functions
function editSection(id, name, description, order, isActive) {
    document.getElementById('edit_section_id').value = id;
    document.getElementById('edit_section_name').value = name;
    document.getElementById('edit_section_description').value = description;
    document.getElementById('edit_section_order').value = order;
    document.getElementById('edit_section_active').checked = isActive == 1;
    
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function deleteSection(id, name) {
    if (confirm(`Are you sure you want to delete the section "${name}"? This cannot be undone.`)) {
        document.getElementById('delete_section_id').value = id;
        document.getElementById('deleteSectionForm').submit();
    }
}

// Question management functions
function addQuestion(sectionId, sectionName) {
    document.getElementById('add_question_section_id').value = sectionId;
    document.getElementById('add_question_section_name').textContent = sectionName;
    document.getElementById('question_type').value = 'text';
    toggleAnswerOptions();
    
    new bootstrap.Modal(document.getElementById('addQuestionModal')).show();
}

function editQuestion(question) {
    document.getElementById('edit_question_id').value = question.id;
    document.getElementById('edit_question_text').value = question.question_text;
    document.getElementById('edit_question_type').value = question.question_type;
    document.getElementById('edit_question_order').value = question.display_order;
    document.getElementById('edit_help_text').value = question.help_text || '';
    document.getElementById('edit_is_required').checked = question.is_required == 1;
    document.getElementById('edit_question_active').checked = question.is_active == 1;
    
    // Handle answer options
    if (question.answer_options && question.answer_options.length > 0) {
        const optionTexts = question.answer_options.map(opt => opt.text).join('\n');
        document.getElementById('edit_answer_options').value = optionTexts;
    }
    
    toggleEditAnswerOptions();
    
    new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
}

function deleteQuestion(id, text) {
    if (confirm(`Are you sure you want to delete the question "${text}"? This will also delete all user responses to this question.`)) {
        document.getElementById('delete_question_id').value = id;
        document.getElementById('deleteQuestionForm').submit();
    }
}

// Toggle answer options visibility
function toggleAnswerOptions() {
    const type = document.getElementById('question_type').value;
    const optionsDiv = document.getElementById('answer_options_div');
    
    if (['radio', 'checkbox', 'dropdown', 'ranking'].includes(type)) {
        optionsDiv.style.display = 'block';
        document.getElementById('answer_options').required = true;
    } else {
        optionsDiv.style.display = 'none';
        document.getElementById('answer_options').required = false;
    }
}

function toggleEditAnswerOptions() {
    const type = document.getElementById('edit_question_type').value;
    const optionsDiv = document.getElementById('edit_answer_options_div');
    
    if (['radio', 'checkbox', 'dropdown', 'ranking'].includes(type)) {
        optionsDiv.style.display = 'block';
    } else {
        optionsDiv.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>