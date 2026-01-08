<?php
/**
 * Project CLAUDE.md Page
 * 
 * Manage project CLAUDE.md documentation
 */

$page_title = 'Project CLAUDE.md';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

// Get project ID
$projectId = $_GET['project'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$project = $projectObj->findById($projectId);

if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$currentUserId = getCurrentUserId();
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

$userObj = new User();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Require member access
if (!$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to view CLAUDE.md.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'save' && ($isProjectManagerOrAdmin || $isCreator)) {
        $content = $_POST['content'] ?? '';
        
        try {
            // Check if CLAUDE.md exists for this project
            $stmt = $db->prepare("SELECT id FROM project_claude_md WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing
                $stmt = $db->prepare("
                    UPDATE project_claude_md 
                    SET content = ?, version = version + 1, updated_at = NOW() 
                    WHERE project_id = ?
                ");
                $stmt->execute([$content, $projectId]);
            } else {
                // Insert new
                $stmt = $db->prepare("
                    INSERT INTO project_claude_md (project_id, content, created_by) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$projectId, $content, $currentUserId]);
            }
            setFlashMessage('success', 'CLAUDE.md saved successfully!');
        } catch (PDOException $e) {
            error_log("Error saving CLAUDE.md: " . $e->getMessage());
            setFlashMessage('error', 'Failed to save CLAUDE.md.');
        }
        header('Location: /project-claude-md.php?project=' . $projectId);
        exit;
    }
}

// Get CLAUDE.md content
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, u.first_name, u.last_name 
        FROM project_claude_md c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.project_id = ?
    ");
    $stmt->execute([$projectId]);
    $claudeMd = $stmt->fetch();
} catch (PDOException $e) {
    $claudeMd = null;
}

// Default template if no content exists
$defaultTemplate = "# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

[Provide a brief description of the project]

## Project Structure

```
/project-root
├── src/           # Source code
├── tests/         # Test files
├── docs/          # Documentation
└── ...
```

## Key Technologies

- [List main technologies used]
- [Programming languages]
- [Frameworks]
- [Databases]

## Development Guidelines

### Code Style
- [Describe coding standards]
- [Naming conventions]
- [File organization]

### Testing
- [How to run tests]
- [Test coverage requirements]
- [Testing frameworks used]

### Building and Deployment
- [Build commands]
- [Deployment process]
- [Environment setup]

## Important Notes

- [Any specific instructions for AI assistants]
- [Common pitfalls to avoid]
- [Project-specific conventions]

## Useful Commands

```bash
# Development
npm run dev

# Testing
npm test

# Build
npm run build
```
";

$content = $claudeMd ? $claudeMd['content'] : $defaultTemplate;

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">CLAUDE.md</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">CLAUDE.md</h1>
        <div>
            <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                <button type="button" class="btn btn-primary" id="editBtn" onclick="toggleEdit()">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button type="button" class="btn btn-success" id="saveBtn" style="display: none;" onclick="saveContent()">
                    <i class="bi bi-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <?php if ($claudeMd): ?>
                        <div class="text-muted small mb-3">
                            Last updated by <?php echo htmlspecialchars($claudeMd['first_name'] . ' ' . $claudeMd['last_name']); ?> 
                            on <?php echo date('M j, Y g:i A', strtotime($claudeMd['updated_at'])); ?>
                            • Version <?php echo $claudeMd['version']; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> No CLAUDE.md file exists for this project yet. 
                            <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                Click Edit to create one using the template below.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Preview Mode -->
                    <div id="previewMode">
                        <div class="markdown-body" id="markdownPreview"></div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                        <div id="editMode" style="display: none;">
                            <form method="POST" id="claudeForm">
                                <input type="hidden" name="action" value="save">
                                <div class="mb-3">
                                    <textarea class="form-control font-monospace" id="content" name="content" 
                                              rows="25" style="font-size: 14px;"><?php echo htmlspecialchars($content); ?></textarea>
                                </div>
                            </form>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                This file uses Markdown formatting. Use # for headers, ** for bold, * for italics, 
                                ``` for code blocks, and - for bullet points.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include Marked.js for Markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
/* GitHub-like Markdown styling */
.markdown-body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.5;
    word-wrap: break-word;
}

.markdown-body h1,
.markdown-body h2,
.markdown-body h3,
.markdown-body h4,
.markdown-body h5,
.markdown-body h6 {
    margin-top: 24px;
    margin-bottom: 16px;
    font-weight: 600;
    line-height: 1.25;
}

.markdown-body h1 {
    font-size: 2em;
    border-bottom: 1px solid #eaecef;
    padding-bottom: .3em;
}

.markdown-body h2 {
    font-size: 1.5em;
    border-bottom: 1px solid #eaecef;
    padding-bottom: .3em;
}

.markdown-body h3 {
    font-size: 1.25em;
}

.markdown-body code {
    padding: .2em .4em;
    margin: 0;
    font-size: 85%;
    background-color: rgba(27,31,35,.05);
    border-radius: 3px;
}

.markdown-body pre {
    padding: 16px;
    overflow: auto;
    font-size: 85%;
    line-height: 1.45;
    background-color: #f6f8fa;
    border-radius: 3px;
}

.markdown-body pre code {
    display: inline;
    max-width: auto;
    padding: 0;
    margin: 0;
    overflow: visible;
    line-height: inherit;
    word-wrap: normal;
    background-color: transparent;
    border: 0;
}

.markdown-body ul,
.markdown-body ol {
    padding-left: 2em;
}

.markdown-body blockquote {
    padding: 0 1em;
    color: #6a737d;
    border-left: .25em solid #dfe2e5;
}
</style>

<script>
// Initial render
renderMarkdown();

function renderMarkdown() {
    const content = document.getElementById('content').value;
    const preview = document.getElementById('markdownPreview');
    preview.innerHTML = marked.parse(content);
}

function toggleEdit() {
    document.getElementById('previewMode').style.display = 'none';
    document.getElementById('editMode').style.display = 'block';
    document.getElementById('editBtn').style.display = 'none';
    document.getElementById('saveBtn').style.display = 'inline-block';
    document.getElementById('cancelBtn').style.display = 'inline-block';
}

function cancelEdit() {
    document.getElementById('previewMode').style.display = 'block';
    document.getElementById('editMode').style.display = 'none';
    document.getElementById('editBtn').style.display = 'inline-block';
    document.getElementById('saveBtn').style.display = 'none';
    document.getElementById('cancelBtn').style.display = 'none';
    
    // Reload original content
    location.reload();
}

function saveContent() {
    document.getElementById('claudeForm').submit();
}

// Live preview while editing
<?php if ($isProjectManagerOrAdmin || $isCreator): ?>
document.getElementById('content').addEventListener('input', function() {
    // Optionally implement live preview in a split view
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>