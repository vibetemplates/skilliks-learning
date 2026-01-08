<?php
// Ensure we're in the correct directory
if (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') {
    // Direct access - need to go up one level
    require_once '../includes/session.php';
    require_once '../config/database.php';
    require_once '../config/functions.php';
    require_once '../classes/User.php';
    require_once '../classes/Survey.php';
} else {
    // Router access - paths are relative to document root
    require_once 'includes/session.php';
    require_once 'config/database.php';
    require_once 'config/functions.php';
    require_once 'classes/User.php';
    require_once 'classes/Survey.php';
}

// Check admin access
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$survey = new Survey();
$pdo = getDB();

// Get survey ID
$surveyId = $_GET['id'] ?? null;
if (!$surveyId) {
    header('Location: /admin/project-surveys');
    exit;
}

// Get survey details
$stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ? AND type = 'project'");
$stmt->execute([$surveyId]);
$surveyData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surveyData) {
    setFlashMessage('error', 'Survey template not found.');
    header('Location: /admin/project-surveys');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_survey':
                $stmt = $pdo->prepare("UPDATE surveys SET name = ?, description = ?, sub_type = ?, when_used = ?, is_active = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['sub_type'] ?? null,
                    $_POST['when_used'] ?? null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $surveyId
                ]);
                setFlashMessage('success', 'Survey template updated successfully.');
                break;
                
            case 'add_section':
                $stmt = $pdo->prepare("INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([
                    $surveyId,
                    $_POST['section_name'],
                    $_POST['section_description'],
                    $_POST['display_order'] ?? 999
                ]);
                setFlashMessage('success', 'Section added successfully.');
                break;
                
            case 'update_section':
                $stmt = $pdo->prepare("UPDATE survey_sections SET name = ?, description = ?, display_order = ? WHERE id = ? AND survey_id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['display_order'],
                    $_POST['section_id'],
                    $surveyId
                ]);
                setFlashMessage('success', 'Section updated successfully.');
                break;
                
            case 'delete_section':
                $stmt = $pdo->prepare("DELETE FROM survey_sections WHERE id = ? AND survey_id = ?");
                $stmt->execute([$_POST['section_id'], $surveyId]);
                setFlashMessage('success', 'Section deleted successfully.');
                break;
                
            case 'add_question':
                $stmt = $pdo->prepare("INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['section_id'],
                    $_POST['question_text'],
                    $_POST['question_type'],
                    isset($_POST['is_required']) ? 1 : 0,
                    $_POST['display_order'] ?? 999,
                    $_POST['help_text']
                ]);
                $questionId = $pdo->lastInsertId();
                
                // Add answer options if applicable
                if (in_array($_POST['question_type'], ['radio', 'checkbox', 'dropdown', 'ranking', 'multiple_choice'])) {
                    $options = explode("\n", trim($_POST['answer_options']));
                    $order = 1;
                    foreach ($options as $option) {
                        $option = trim($option);
                        if ($option) {
                            $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, display_order, is_active) VALUES (?, ?, ?, 1)");
                            $stmt->execute([$questionId, $option, $order++]);
                        }
                    }
                }
                setFlashMessage('success', 'Question added successfully.');
                break;
                
            case 'update_question':
                $stmt = $pdo->prepare("UPDATE survey_questions SET question_text = ?, question_type = ?, is_required = ?, display_order = ?, help_text = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['question_text'],
                    $_POST['question_type'],
                    isset($_POST['is_required']) ? 1 : 0,
                    $_POST['display_order'],
                    $_POST['help_text'],
                    $_POST['question_id']
                ]);
                
                // Update answer options if applicable
                if (in_array($_POST['question_type'], ['radio', 'checkbox', 'dropdown', 'ranking', 'multiple_choice'])) {
                    // Delete existing options
                    $stmt = $pdo->prepare("DELETE FROM survey_answer_options WHERE question_id = ?");
                    $stmt->execute([$_POST['question_id']]);
                    
                    // Add new options
                    $options = explode("\n", trim($_POST['answer_options']));
                    $order = 1;
                    foreach ($options as $option) {
                        $option = trim($option);
                        if ($option) {
                            $stmt = $pdo->prepare("INSERT INTO survey_answer_options (question_id, option_text, display_order, is_active) VALUES (?, ?, ?, 1)");
                            $stmt->execute([$_POST['question_id'], $option, $order++]);
                        }
                    }
                }
                setFlashMessage('success', 'Question updated successfully.');
                break;
                
            case 'delete_question':
                $stmt = $pdo->prepare("DELETE FROM survey_questions WHERE id = ?");
                $stmt->execute([$_POST['question_id']]);
                setFlashMessage('success', 'Question deleted successfully.');
                break;
        }
        
        // Redirect to refresh the page
        header("Location: /admin/survey-template-edit?id=$surveyId");
        exit;
    }
}

// Get sections and questions
$sections = $survey->getSurveySections($surveyId);
$questions = [];
foreach ($sections as $section) {
    $questions[$section['id']] = $survey->getSectionQuestions($section['id']);
}

if (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') {
    include '../includes/header.php';
} else {
    include 'includes/header.php';
}
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-pencil-square"></i> Edit Survey Template</h1>
                <a href="/admin/project-surveys" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Surveys
                </a>
            </div>

            <!-- Survey Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Survey Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_survey">
                        <div class="mb-3">
                            <label for="name" class="form-label">Survey Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($surveyData['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($surveyData['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="sub_type" class="form-label">Sub Type</label>
                            <select class="form-select" id="sub_type" name="sub_type">
                                <option value="">None</option>
                                <option value="general" <?php echo ($surveyData['sub_type'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                                <option value="requirements" <?php echo ($surveyData['sub_type'] ?? '') === 'requirements' ? 'selected' : ''; ?>>Requirements</option>
                                <option value="tech-stack" <?php echo ($surveyData['sub_type'] ?? '') === 'tech-stack' ? 'selected' : ''; ?>>Tech Stack</option>
                                <option value="design-notes" <?php echo ($surveyData['sub_type'] ?? '') === 'design-notes' ? 'selected' : ''; ?>>Design Notes</option>
                            </select>
                            <small class="form-text text-muted">Used for project surveys to categorize different configuration areas</small>
                        </div>
                        <div class="mb-3">
                            <label for="when_used" class="form-label">When Used</label>
                            <textarea class="form-control" id="when_used" name="when_used" rows="3" 
                                      placeholder="Describe what type of project uses this survey (e.g., 'Use for web applications with complex frontend requirements')"><?php echo htmlspecialchars($surveyData['when_used'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">This helps determine which survey to use based on project characteristics</small>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   <?php echo $surveyData['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Survey</button>
                    </form>
                </div>
            </div>

            <!-- Sections and Questions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sections and Questions</h5>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="bi bi-plus-circle"></i> Add Section
                    </button>
                </div>
                <div class="card-body">
                    <?php foreach ($sections as $section): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6><?php echo htmlspecialchars($section['name']); ?></h6>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($section['description']); ?></p>
                                    <small class="text-muted">Order: <?php echo $section['display_order']; ?></small>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="addQuestion(<?php echo $section['id']; ?>)">
                                        <i class="bi bi-plus"></i> Question
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section and all its questions?');">
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Questions -->
                            <div class="ms-4 mt-3">
                                <?php 
                                $sectionQuestions = $questions[$section['id']] ?? [];
                                foreach ($sectionQuestions as $question): 
                                ?>
                                    <div class="border-start border-3 ps-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <p class="mb-1">
                                                    <strong><?php echo htmlspecialchars($question['question_text']); ?></strong>
                                                    <?php if ($question['is_required']): ?>
                                                        <span class="badge bg-danger ms-1">Required</span>
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    Type: <?php echo $question['question_type']; ?> | 
                                                    Order: <?php echo $question['display_order']; ?>
                                                    <?php if ($question['help_text']): ?>
                                                        | Help: <?php echo htmlspecialchars($question['help_text']); ?>
                                                    <?php endif; ?>
                                                </small>
                                                
                                                <?php if (!empty($question['answer_options'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Options:</small>
                                                        <ul class="small mb-0">
                                                            <?php foreach ($question['answer_options'] as $option): ?>
                                                                <li><?php echo htmlspecialchars($option['text'] ?? $option['option_text'] ?? ''); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-primary me-1" 
                                                        onclick='editQuestion(<?php echo htmlspecialchars(json_encode($question)); ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this question?');">
                                                    <input type="hidden" name="action" value="delete_question">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_section">
                <div class="modal-header">
                    <h5 class="modal-title">Add Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="section_name" class="form-label">Section Name</label>
                        <input type="text" class="form-control" id="section_name" name="section_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="section_description" name="section_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="999">
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
            <form method="POST">
                <input type="hidden" name="action" value="update_section">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_section_name" class="form-label">Section Name</label>
                        <input type="text" class="form-control" id="edit_section_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_section_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order">
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
            <form method="POST">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="section_id" id="question_section_id">
                <div class="modal-header">
                    <h5 class="modal-title">Add Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <input type="text" class="form-control" id="question_text" name="question_text" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="question_type" class="form-label">Question Type</label>
                            <select class="form-select" id="question_type" name="question_type" required onchange="toggleAnswerOptions()">
                                <option value="text">Text (single line)</option>
                                <option value="textarea">Textarea (multi-line)</option>
                                <option value="radio">Radio (single choice)</option>
                                <option value="checkbox">Checkbox (multiple choice)</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="ranking">Ranking</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="question_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="question_display_order" name="display_order" value="999">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="help_text" class="form-label">Help Text</label>
                        <input type="text" class="form-control" id="help_text" name="help_text">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_required" name="is_required">
                        <label class="form-check-label" for="is_required">Required</label>
                    </div>
                    <div class="mb-3" id="answer_options_container" style="display: none;">
                        <label for="answer_options" class="form-label">Answer Options (one per line)</label>
                        <textarea class="form-control" id="answer_options" name="answer_options" rows="5"></textarea>
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
            <form method="POST">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_question_text" class="form-label">Question Text</label>
                        <input type="text" class="form-control" id="edit_question_text" name="question_text" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_question_type" class="form-label">Question Type</label>
                            <select class="form-select" id="edit_question_type" name="question_type" required onchange="toggleEditAnswerOptions()">
                                <option value="text">Text (single line)</option>
                                <option value="textarea">Textarea (multi-line)</option>
                                <option value="radio">Radio (single choice)</option>
                                <option value="checkbox">Checkbox (multiple choice)</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="ranking">Ranking</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_question_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_question_display_order" name="display_order">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_help_text" class="form-label">Help Text</label>
                        <input type="text" class="form-control" id="edit_help_text" name="help_text">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_required" name="is_required">
                        <label class="form-check-label" for="edit_is_required">Required</label>
                    </div>
                    <div class="mb-3" id="edit_answer_options_container" style="display: none;">
                        <label for="edit_answer_options" class="form-label">Answer Options (one per line)</label>
                        <textarea class="form-control" id="edit_answer_options" name="answer_options" rows="5"></textarea>
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

<script>
function editSection(section) {
    document.getElementById('edit_section_id').value = section.id;
    document.getElementById('edit_section_name').value = section.name;
    document.getElementById('edit_section_description').value = section.description || '';
    document.getElementById('edit_display_order').value = section.display_order;
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function addQuestion(sectionId) {
    document.getElementById('question_section_id').value = sectionId;
    new bootstrap.Modal(document.getElementById('addQuestionModal')).show();
}

function toggleAnswerOptions() {
    const type = document.getElementById('question_type').value;
    const container = document.getElementById('answer_options_container');
    const needsOptions = ['radio', 'checkbox', 'dropdown', 'ranking', 'multiple_choice'].includes(type);
    container.style.display = needsOptions ? 'block' : 'none';
}

function editQuestion(question) {
    document.getElementById('edit_question_id').value = question.id;
    document.getElementById('edit_question_text').value = question.question_text;
    document.getElementById('edit_question_type').value = question.question_type;
    document.getElementById('edit_question_display_order').value = question.display_order;
    document.getElementById('edit_help_text').value = question.help_text || '';
    document.getElementById('edit_is_required').checked = question.is_required == 1;
    
    // Handle answer options
    const optionsContainer = document.getElementById('edit_answer_options_container');
    const optionsTextarea = document.getElementById('edit_answer_options');
    
    if (question.answer_options && question.answer_options.length > 0) {
        const optionTexts = question.answer_options.map(opt => opt.text || opt.option_text || '');
        optionsTextarea.value = optionTexts.join('\n');
    } else {
        optionsTextarea.value = '';
    }
    
    toggleEditAnswerOptions();
    new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
}

function toggleEditAnswerOptions() {
    const type = document.getElementById('edit_question_type').value;
    const container = document.getElementById('edit_answer_options_container');
    const needsOptions = ['radio', 'checkbox', 'dropdown', 'ranking', 'multiple_choice'].includes(type);
    container.style.display = needsOptions ? 'block' : 'none';
}
</script>

<?php 
if (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') {
    include '../includes/footer.php';
} else {
    include 'includes/footer.php';
}
?>