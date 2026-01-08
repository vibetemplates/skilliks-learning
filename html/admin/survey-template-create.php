<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Survey.php';

// Check admin access
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;
$survey = new Survey();

// Get all communities for the dropdown
$db = getDB();
$stmt = $db->query("SELECT id, name FROM communities ORDER BY name");
$communities = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'project'; // Default to project type
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $community_id = $_POST['community_id'] ?? null;
    
    if (empty($name)) {
        setFlashMessage('error', 'Survey name is required.');
    } elseif (empty($community_id)) {
        setFlashMessage('error', 'Please select a community for this survey.');
    } else {
        // Create the survey
        $surveyData = [
            'community_id' => $community_id,
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'is_active' => $is_active,
            'created_by' => $currentUserId
        ];
        
        $surveyId = $survey->create($surveyData);
        
        if ($surveyId) {
            setFlashMessage('success', 'Survey template created successfully. You can now add sections and questions.');
            header('Location: /admin/survey-template-edit?id=' . $surveyId);
            exit;
        } else {
            setFlashMessage('error', 'Failed to create survey template.');
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mt-4 mb-4">
                <i class="bi bi-plus-circle"></i> Create New Survey Template
            </h1>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
                    <li class="breadcrumb-item"><a href="/admin/project-surveys">Project Surveys</a></li>
                    <li class="breadcrumb-item active">Create Survey Template</li>
                </ol>
            </nav>

            <!-- Flash Messages -->
            <?php if ($success = getFlashMessage('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error = getFlashMessage('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Create Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Survey Template Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="community_id" class="form-label">Community <span class="text-danger">*</span></label>
                                    <select class="form-select" id="community_id" name="community_id" required>
                                        <option value="">Select a community...</option>
                                        <?php foreach ($communities as $community): ?>
                                            <option value="<?php echo $community['id']; ?>" 
                                                    <?php echo (($_POST['community_id'] ?? '') == $community['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($community['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select which community this survey template belongs to</div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Survey Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    <div class="form-text">Give your survey a descriptive name</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Briefly describe what this survey is for</div>
                                </div>

                                <div class="mb-3">
                                    <label for="type" class="form-label">Survey Type</label>
                                    <select class="form-select" id="type" name="type">
                                        <option value="project" <?php echo (($_POST['type'] ?? 'project') === 'project') ? 'selected' : ''; ?>>Project Survey</option>
                                        <option value="skills" <?php echo (($_POST['type'] ?? '') === 'skills') ? 'selected' : ''; ?>>Skills Survey</option>
                                        <option value="feedback" <?php echo (($_POST['type'] ?? '') === 'feedback') ? 'selected' : ''; ?>>Feedback Survey</option>
                                        <option value="assessment" <?php echo (($_POST['type'] ?? '') === 'assessment') ? 'selected' : ''; ?>>Assessment Survey</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                               <?php echo (($_POST['is_active'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active (Survey is available for use)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Tips for Creating Surveys</h6>
                                        <ul class="small mb-0">
                                            <li>Use clear, descriptive names</li>
                                            <li>Project surveys should gather information about technical requirements</li>
                                            <li>Skills surveys assess user competencies</li>
                                            <li>Keep surveys focused on a specific goal</li>
                                            <li>You can add sections and questions after creating the template</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Survey Template
                            </button>
                            <a href="/admin/project-surveys" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>