<?php
/**
 * Project Survey Page
 * 
 * Allows admins and project creators to take the project survey for AI-powered recommendations
 */

$page_title = 'Project Survey';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectSurvey.php';
require_once 'classes/Survey.php';

// Require login
requireLogin();

$userObj = new User();
$projectObj = new Project();
$projectSurvey = new ProjectSurvey();
$currentUserId = getCurrentUserId();

// Get project ID and survey ID - support both 'project' and 'project_id' parameters
$projectId = $_GET['project'] ?? $_GET['project_id'] ?? null;
$requestedSurveyId = $_GET['survey_id'] ?? null;
$surveyType = $_GET['survey_type'] ?? null; // Get survey type (general, requirements, tech-stack, design-notes)

if (!$projectId) {
    setFlashMessage('error', 'Invalid project ID.');
    header('Location: /projects');
    exit;
}

// Get project details
$project = $projectObj->findById($projectId);
if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects');
    exit;
}

// Check if user is admin, project creator, or member
$isAdmin = $userObj->isAdmin($currentUserId);
$isCreator = $project['created_by'] == $currentUserId;
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

if (!$isAdmin && !$isCreator && !$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to access the survey.');
    header("Location: /project-detail?id={$projectId}");
    exit;
}

// Handle survey selection
if ($requestedSurveyId) {
    // Check if this survey is associated with the project
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ps.*, s.* 
        FROM project_surveys ps
        JOIN surveys s ON ps.survey_id = s.id
        WHERE ps.project_id = ? AND ps.survey_id = ?
    ");
    $stmt->execute([$projectId, $requestedSurveyId]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey) {
        setFlashMessage('error', 'Survey not found for this project.');
        header("Location: /project-edit?id={$projectId}");
        exit;
    }
    
    $surveyId = $requestedSurveyId;
} elseif ($surveyType) {
    // First check if this project already has a survey of this sub_type
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ps.*, s.* 
        FROM project_surveys ps
        JOIN surveys s ON ps.survey_id = s.id
        WHERE ps.project_id = ? AND s.type = 'project' AND s.sub_type = ?
    ");
    $stmt->execute([$projectId, $surveyType]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey) {
        // No existing survey of this sub_type for this project
        // Check if there's a survey template of this sub_type for the community
        $surveyObj = new Survey();
        $survey = $surveyObj->getSurveyByCommunity($project['community_id'], 'project', $surveyType);
        
        if (!$survey) {
            // Create a new survey with the specified sub_type
            $whenUsedDescriptions = [
                'general' => 'Use for all standard projects requiring basic configuration and setup information',
                'requirements' => 'Use for projects that need detailed requirements gathering and specification',
                'tech-stack' => 'Use for projects requiring technology stack selection and architecture decisions',
                'design-notes' => 'Use for projects with complex design requirements or UI/UX focus'
            ];
            
            $surveyData = [
                'community_id' => $project['community_id'],
                'name' => ucfirst(str_replace('-', ' ', $surveyType)) . ' Project Survey',
                'description' => 'Project survey for ' . $surveyType . ' configuration',
                'type' => 'project',
                'sub_type' => $surveyType,
                'when_used' => $whenUsedDescriptions[$surveyType] ?? 'General purpose survey for project configuration',
                'is_active' => 1,
                'created_by' => $currentUserId
            ];
            
            $newSurveyId = $surveyObj->create($surveyData);
            if ($newSurveyId) {
                $survey = $surveyObj->getSurveyById($newSurveyId);
            } else {
                setFlashMessage('error', 'Unable to create project survey. Please contact support.');
                header("Location: /project-edit?id={$projectId}");
                exit;
            }
        }
        
        // Link the survey to this project
        if ($survey) {
            $stmt = $db->prepare("INSERT INTO project_surveys (project_id, survey_id) VALUES (?, ?)");
            $stmt->execute([$projectId, $survey['id']]);
        }
    }
    
    $surveyId = $survey['id'];
} else {
    // Default to 'general' survey type when not specified
    $surveyType = 'general';
    
    // First check if this project already has a general survey
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ps.*, s.* 
        FROM project_surveys ps
        JOIN surveys s ON ps.survey_id = s.id
        WHERE ps.project_id = ? AND s.type = 'project' AND s.sub_type = 'general'
    ");
    $stmt->execute([$projectId]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey) {
        // No existing general survey for this project
        // Check if there's a general survey template for the community
        $surveyObj = new Survey();
        $survey = $surveyObj->getSurveyByCommunity($project['community_id'], 'project', 'general');
        
        if (!$survey) {
            // Create a new general survey
            $surveyData = [
                'community_id' => $project['community_id'],
                'name' => 'General Project Survey',
                'description' => 'Project survey for general configuration',
                'type' => 'project',
                'sub_type' => 'general',
                'when_used' => 'Use for all standard projects requiring basic configuration and setup information',
                'is_active' => 1,
                'created_by' => $currentUserId
            ];
            
            $newSurveyId = $surveyObj->create($surveyData);
            if ($newSurveyId) {
                $survey = $surveyObj->getSurveyById($newSurveyId);
            } else {
                setFlashMessage('error', 'Unable to create project survey. Please contact support.');
                header("Location: /project-edit?id={$projectId}");
                exit;
            }
        }
        
        // Link the survey to this project
        if ($survey) {
            $stmt = $db->prepare("INSERT INTO project_surveys (project_id, survey_id) VALUES (?, ?)");
            $stmt->execute([$projectId, $survey['id']]);
        }
    }
    
    $surveyId = $survey['id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responses = $_POST['responses'] ?? [];
    $rankings = $_POST['rankings'] ?? [];
    $texts = $_POST['texts'] ?? [];
    
    // Save all responses
    $saveCount = 0;
    
    // Handle multiple choice and checkbox responses
    foreach ($responses as $questionId => $response) {
        if (is_array($response)) {
            // Checkbox questions - save each selected option
            foreach ($response as $optionId) {
                if ($projectSurvey->saveResponse($surveyId, $currentUserId, $questionId, null, $optionId)) {
                    $saveCount++;
                }
            }
        } else {
            // Single choice questions
            if ($projectSurvey->saveResponse($surveyId, $currentUserId, $questionId, null, $response)) {
                $saveCount++;
            }
        }
    }
    
    // Handle ranking questions
    foreach ($rankings as $questionId => $rankedOptions) {
        $rank = 1;
        foreach ($rankedOptions as $optionId) {
            if ($optionId && $projectSurvey->saveResponse($surveyId, $currentUserId, $questionId, null, $optionId, $rank)) {
                $saveCount++;
                $rank++;
            }
        }
    }
    
    // Handle text responses
    foreach ($texts as $questionId => $text) {
        if (trim($text) && $projectSurvey->saveResponse($surveyId, $currentUserId, $questionId, $text)) {
            $saveCount++;
        }
    }
    
    if ($saveCount > 0) {
        setFlashMessage('success', "Survey responses saved successfully! Saved {$saveCount} responses.");
        
        // If the action is to generate recommendations
        if (isset($_POST['action']) && $_POST['action'] === 'generate_recommendations') {
            // For now, redirect to the edit page with a message
            setFlashMessage('info', 'AI recommendations generation will be available soon. Your survey responses have been saved.');
            header("Location: /project-edit?id={$projectId}");
            exit;
        }
    } else {
        setFlashMessage('warning', 'No responses were saved. Please answer at least one question.');
    }
}

// Get survey sections and questions
$sections = $projectSurvey->getSurveySections($surveyId);
$questions = [];
$totalQuestions = 0;

foreach ($sections as $section) {
    $sectionQuestions = $projectSurvey->getSectionQuestions($section['id']);
    $questions[$section['id']] = $sectionQuestions;
    $totalQuestions += count($sectionQuestions);
}

// Get existing responses
$existingResponses = $projectSurvey->getAllUserResponses($surveyId, $currentUserId);
$responseMap = [];
$rankingMap = [];
$textMap = [];

foreach ($existingResponses as $response) {
    if ($response['question_type'] === 'ranking') {
        if (!isset($rankingMap[$response['question_id']])) {
            $rankingMap[$response['question_id']] = [];
        }
        $rankingMap[$response['question_id']][$response['rank_value'] ?? 1] = $response['answer_option_id'];
    } elseif ($response['question_type'] === 'text') {
        $textMap[$response['question_id']] = $response['answer_text'];
    } elseif ($response['question_type'] === 'checkbox') {
        if (!isset($responseMap[$response['question_id']])) {
            $responseMap[$response['question_id']] = [];
        }
        $responseMap[$response['question_id']][] = $response['answer_option_id'];
    } else {
        $responseMap[$response['question_id']] = $response['answer_option_id'];
    }
}

// Calculate completion
$answeredQuestions = count(array_unique(array_column($existingResponses, 'question_id')));
$completionPercentage = $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100) : 0;

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $surveyType ? ucfirst(str_replace('-', ' ', $surveyType)) . ' Survey' : 'Project Survey'; ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $surveyType ? ucfirst(str_replace('-', ' ', $surveyType)) . ' Survey' : 'Project Survey'; ?></h1>
    </div>


    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php 
                        switch($surveyType) {
                            case 'general':
                                echo 'General Project Configuration';
                                break;
                            case 'requirements':
                                echo 'Requirements Gathering Survey';
                                break;
                            case 'tech-stack':
                                echo 'Technology Stack Assessment';
                                break;
                            case 'design-notes':
                                echo 'Design Notes and Architecture';
                                break;
                            default:
                                echo 'Project Planning Survey';
                        }
                        ?>
                    </h5>
                    <p class="text-muted mb-0">
                        <?php 
                        switch($surveyType) {
                            case 'general':
                                echo 'Configure basic project settings and preferences to guide the development process.';
                                break;
                            case 'requirements':
                                echo 'Define functional and non-functional requirements for your project.';
                                break;
                            case 'tech-stack':
                                echo 'Select and configure the technologies, frameworks, and tools for your project.';
                                break;
                            case 'design-notes':
                                echo 'Document architectural decisions, design patterns, and implementation notes.';
                                break;
                            default:
                                echo 'Complete this survey to receive AI-powered architecture, tech stack, and documentation recommendations for your project.';
                        }
                        ?>
                    </p>
                </div>
                <div class="card-body">
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Progress</span>
                            <span><?php echo $completionPercentage; ?>% Complete</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $completionPercentage; ?>%" 
                                 aria-valuenow="<?php echo $completionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Generate Recommendations Button (only for skills surveys with responses) -->
                    <?php if ($answeredQuestions > 0 && $survey['type'] === 'skills'): ?>
                    <div class="mb-4">
                        <button type="button" class="btn btn-success" onclick="generateRecommendations()">
                            <i class="bi bi-magic"></i> Generate AI Recommendations
                        </button>
                        <span class="text-muted ms-2">
                            You've answered <?php echo $answeredQuestions; ?> out of <?php echo $totalQuestions; ?> questions.
                        </span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="surveyForm">
                        <?php foreach ($sections as $section): ?>
                            <div class="survey-section mb-5">
                                <h4 class="mb-3"><?php echo htmlspecialchars($section['name']); ?></h4>
                                <?php if ($section['description']): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($section['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php 
                                $sectionQuestions = $questions[$section['id']] ?? [];
                                foreach ($sectionQuestions as $question): 
                                    $questionId = $question['id'];
                                    $isRequired = $question['is_required'];
                                ?>
                                    <div class="mb-4 question-container" data-question-id="<?php echo $questionId; ?>">
                                        <label class="form-label fw-bold">
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                            <?php if ($isRequired): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($question['help_text']): ?>
                                            <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($question['help_text']); ?></small>
                                        <?php endif; ?>
                                        
                                        <?php
                                        switch ($question['question_type']) {
                                            case 'multiple_choice':
                                                $options = $question['answer_options'] ?? [];
                                                $selectedOption = $responseMap[$questionId] ?? null;
                                                foreach ($options as $option):
                                                ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" 
                                                               name="responses[<?php echo $questionId; ?>]" 
                                                               id="option_<?php echo $option['id']; ?>" 
                                                               value="<?php echo $option['id']; ?>"
                                                               <?php echo $selectedOption == $option['id'] ? 'checked' : ''; ?>
                                                               <?php echo $isRequired ? 'required' : ''; ?>>
                                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                            <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                        </label>
                                                    </div>
                                                <?php
                                                endforeach;
                                                break;
                                                
                                            case 'checkbox':
                                                $options = $question['answer_options'] ?? [];
                                                $selectedOptions = $responseMap[$questionId] ?? [];
                                                foreach ($options as $option):
                                                ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="responses[<?php echo $questionId; ?>][]" 
                                                               id="option_<?php echo $option['id']; ?>" 
                                                               value="<?php echo $option['id']; ?>"
                                                               <?php echo in_array($option['id'], $selectedOptions) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                            <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                        </label>
                                                    </div>
                                                <?php
                                                endforeach;
                                                break;
                                                
                                            case 'ranking':
                                                $options = $question['answer_options'] ?? [];
                                                $rankedOptions = $rankingMap[$questionId] ?? [];
                                                ?>
                                                <div class="ranking-container" data-question-id="<?php echo $questionId; ?>">
                                                    <ol class="list-group ranking-list">
                                                        <?php 
                                                        // Display already ranked options
                                                        ksort($rankedOptions);
                                                        foreach ($rankedOptions as $rank => $optionId):
                                                            $option = array_filter($options, fn($o) => $o['id'] == $optionId);
                                                            $option = reset($option);
                                                            if ($option):
                                                        ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center" 
                                                                data-option-id="<?php echo $option['id']; ?>">
                                                                <span class="drag-handle me-2">☰</span>
                                                                <span class="flex-grow-1"><?php echo htmlspecialchars($option['text'] ?? ''); ?></span>
                                                                <input type="hidden" name="rankings[<?php echo $questionId; ?>][]" 
                                                                       value="<?php echo $option['id']; ?>">
                                                            </li>
                                                        <?php 
                                                            endif;
                                                        endforeach;
                                                        
                                                        // Display unranked options
                                                        foreach ($options as $option):
                                                            if (!in_array($option['id'], $rankedOptions)):
                                                        ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center" 
                                                                data-option-id="<?php echo $option['id']; ?>">
                                                                <span class="drag-handle me-2">☰</span>
                                                                <span class="flex-grow-1"><?php echo htmlspecialchars($option['text'] ?? ''); ?></span>
                                                                <input type="hidden" name="rankings[<?php echo $questionId; ?>][]" 
                                                                       value="<?php echo $option['id']; ?>">
                                                            </li>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        ?>
                                                    </ol>
                                                    <small class="text-muted">Drag and drop to rank in order of preference (top = highest priority)</small>
                                                </div>
                                                <?php
                                                break;
                                                
                                            case 'text':
                                                $textValue = $textMap[$questionId] ?? '';
                                                ?>
                                                <input type="text" class="form-control" 
                                                       name="texts[<?php echo $questionId; ?>]" 
                                                       value="<?php echo htmlspecialchars($textValue); ?>"
                                                       <?php echo $isRequired ? 'required' : ''; ?>>
                                                <?php
                                                break;
                                                
                                            case 'textarea':
                                                $textValue = $textMap[$questionId] ?? '';
                                                ?>
                                                <textarea class="form-control" 
                                                          name="texts[<?php echo $questionId; ?>]" 
                                                          rows="3"
                                                          <?php echo $isRequired ? 'required' : ''; ?>><?php echo htmlspecialchars($textValue); ?></textarea>
                                                <?php
                                                break;
                                                
                                            case 'radio':
                                                $options = $question['answer_options'] ?? [];
                                                $selectedOption = $responseMap[$questionId] ?? null;
                                                foreach ($options as $option):
                                                ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" 
                                                               name="responses[<?php echo $questionId; ?>]" 
                                                               id="option_<?php echo $option['id']; ?>" 
                                                               value="<?php echo $option['id']; ?>"
                                                               <?php echo $selectedOption == $option['id'] ? 'checked' : ''; ?>
                                                               <?php echo $isRequired ? 'required' : ''; ?>>
                                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                            <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                        </label>
                                                    </div>
                                                <?php
                                                endforeach;
                                                break;
                                                
                                            case 'dropdown':
                                                $options = $question['answer_options'] ?? [];
                                                $selectedOption = $responseMap[$questionId] ?? null;
                                                ?>
                                                <select class="form-select" 
                                                        name="responses[<?php echo $questionId; ?>]"
                                                        <?php echo $isRequired ? 'required' : ''; ?>>
                                                    <option value="">-- Select an option --</option>
                                                    <?php foreach ($options as $option): ?>
                                                        <option value="<?php echo $option['id']; ?>"
                                                                <?php echo $selectedOption == $option['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($option['text'] ?? ''); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php
                                                break;
                                        }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex justify-content-between">
                            <?php 
                            $backUrl = match($surveyType) {
                                'general' => "/project-edit?id={$projectId}",
                                'requirements' => "/project-requirements.php?project={$projectId}",
                                'tech-stack' => "/project-tech-stack.php?project={$projectId}",
                                'design-notes' => "/project-design-notes.php?project={$projectId}",
                                default => "/project-edit?id={$projectId}"
                            };
                            ?>
                            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Responses
                                </button>
                                <?php if ($answeredQuestions > 0 && $survey['type'] === 'skills'): ?>
                                <button type="submit" name="action" value="generate_recommendations" class="btn btn-success ms-2">
                                    <i class="bi bi-magic"></i> Save & Generate Recommendations
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Survey Information</h5>
                </div>
                <div class="card-body">
                    <h6>What is this survey for?</h6>
                    <?php 
                    switch($surveyType) {
                        case 'general':
                            echo '<p>This survey helps configure basic project settings:</p>';
                            echo '<ul>';
                            echo '<li><strong>Project Type</strong> - Web app, mobile app, API, etc.</li>';
                            echo '<li><strong>Team Structure</strong> - Team size and experience levels</li>';
                            echo '<li><strong>Timeline</strong> - Project deadlines and milestones</li>';
                            echo '<li><strong>Deployment</strong> - Target environments and infrastructure</li>';
                            echo '</ul>';
                            break;
                        case 'requirements':
                            echo '<p>This survey helps define project requirements:</p>';
                            echo '<ul>';
                            echo '<li><strong>Functional Requirements</strong> - Core features and capabilities</li>';
                            echo '<li><strong>User Stories</strong> - User needs and workflows</li>';
                            echo '<li><strong>Constraints</strong> - Technical and business limitations</li>';
                            echo '<li><strong>Acceptance Criteria</strong> - Success metrics and validation</li>';
                            echo '</ul>';
                            break;
                        case 'tech-stack':
                            echo '<p>This survey helps select appropriate technologies:</p>';
                            echo '<ul>';
                            echo '<li><strong>Languages</strong> - Programming languages for different components</li>';
                            echo '<li><strong>Frameworks</strong> - Web, mobile, and backend frameworks</li>';
                            echo '<li><strong>Databases</strong> - Data storage and caching solutions</li>';
                            echo '<li><strong>DevOps Tools</strong> - CI/CD, monitoring, and deployment</li>';
                            echo '</ul>';
                            break;
                        case 'design-notes':
                            echo '<p>This survey captures architectural decisions:</p>';
                            echo '<ul>';
                            echo '<li><strong>Architecture Patterns</strong> - MVC, microservices, serverless, etc.</li>';
                            echo '<li><strong>Design Patterns</strong> - Common solutions to recurring problems</li>';
                            echo '<li><strong>API Design</strong> - REST, GraphQL, or other API styles</li>';
                            echo '<li><strong>Security Design</strong> - Authentication and authorization approach</li>';
                            echo '</ul>';
                            break;
                        default:
                            echo '<p>This survey helps us understand your project requirements to provide:</p>';
                            echo '<ul>';
                            echo '<li><strong>Architecture Recommendations</strong> - Best practices for structuring your project</li>';
                            echo '<li><strong>Tech Stack Suggestions</strong> - Recommended languages, frameworks, and tools</li>';
                            echo '<li><strong>CLAUDE.md Content</strong> - AI assistant instructions for your project</li>';
                            echo '<li><strong>Requirements.md</strong> - Detailed project requirements documentation</li>';
                            echo '</ul>';
                    }
                    ?>
                    
                    <hr>
                    
                    <h6>Tips for Best Results</h6>
                    <ul class="small">
                        <li>Answer as many questions as possible for more accurate recommendations</li>
                        <li>Be specific about your technical requirements and constraints</li>
                        <li>Consider your team's experience level when answering</li>
                        <li>Think about long-term maintenance and scalability</li>
                    </ul>
                    
                    <?php if ($completionPercentage < 100): ?>
                    <hr>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong><?php echo 100 - $completionPercentage; ?>%</strong> of questions remain unanswered
                    </div>
                    <?php else: ?>
                    <hr>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> 
                        <strong>Survey Complete!</strong> Ready to generate recommendations.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.ranking-list {
    cursor: move;
}

.ranking-list .list-group-item {
    cursor: move;
}

.drag-handle {
    cursor: grab;
}

.ranking-list .list-group-item.sortable-chosen {
    opacity: 0.5;
}

.question-container {
    border-left: 3px solid transparent;
    padding-left: 1rem;
    transition: border-color 0.3s;
}

.question-container:hover {
    border-left-color: #0d6efd;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// Initialize ranking drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const rankingContainers = document.querySelectorAll('.ranking-list');
    
    rankingContainers.forEach(container => {
        new Sortable(container, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            handle: '.drag-handle'
        });
    });
});

// Generate recommendations function
function generateRecommendations() {
    if (confirm('This will save your current responses and generate AI recommendations. Continue?')) {
        const form = document.getElementById('surveyForm');
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'generate_recommendations';
        form.appendChild(actionInput);
        form.submit();
    }
}

// Auto-save draft every 30 seconds
let autoSaveTimer;
function startAutoSave() {
    autoSaveTimer = setInterval(function() {
        const form = document.getElementById('surveyForm');
        const formData = new FormData(form);
        formData.append('action', 'autosave');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            console.log('Auto-save completed');
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 30000); // 30 seconds
}

// Start auto-save when page loads
document.addEventListener('DOMContentLoaded', startAutoSave);

// Stop auto-save when leaving page
window.addEventListener('beforeunload', function() {
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>