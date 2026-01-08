<?php
/**
 * Project Artifacts Page
 * 
 * Display and manage project artifacts/files
 */

$page_title = 'Project Artifacts';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/FileManager.php';

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
    setFlashMessage('error', 'You must be a project member to view artifacts.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Get project files
$fileManager = new FileManager();
$projectFiles = $fileManager->getFiles('project', $projectId);

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Artifacts</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Project Artifacts</h1>
        <button type="button" class="btn btn-primary" onclick="showUploadModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
            <i class="bi bi-cloud-upload"></i> Upload File
        </button>
    </div>

    <!-- Artifacts List -->
    <?php if (empty($projectFiles)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No artifacts uploaded yet.</p>
                <button type="button" class="btn btn-primary" onclick="showUploadModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                    <i class="bi bi-cloud-upload"></i> Upload First Document
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($projectFiles as $file): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <i class="<?php echo $fileManager->getFileIcon($file['file_type']); ?> text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <h6 class="card-title text-truncate" title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                        <?php echo htmlspecialchars($file['original_filename']); ?>
                                    </h6>
                                    <p class="card-text small text-muted mb-1">
                                        <?php echo $fileManager->formatFileSize($file['file_size']); ?>
                                    </p>
                                    <p class="card-text small text-muted mb-2">
                                        Uploaded <?php echo date('M j, Y', strtotime($file['upload_date'])); ?>
                                        by <?php echo htmlspecialchars($file['uploader_name'] ?? 'Unknown'); ?>
                                    </p>
                                    <?php if ($file['description']): ?>
                                        <p class="card-text small mb-2"><?php echo htmlspecialchars($file['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <a href="/api/file-download.php?id=<?php echo $file['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <?php if ($file['uploaded_by'] == $currentUserId || $isProjectManagerOrAdmin || $isCreator): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFile(<?php echo $file['id']; ?>)">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- File Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" id="uploadEntityType" name="entity_type" value="project">
                    <input type="hidden" id="uploadEntityId" name="entity_id" value="">
                    
                    <div class="mb-3">
                        <label for="uploadFile" class="form-label">Select File</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" class="form-control" id="uploadFile" name="file" required style="display: none;">
                            <div class="upload-content">
                                <i class="bi bi-cloud-upload-fill" style="font-size: 3rem; color: var(--primary);"></i>
                                <p class="mt-2 mb-1"><strong>Click to upload</strong> or drag and drop</p>
                                <p class="text-muted small">All file types allowed except .exe and .msi</p>
                            </div>
                        </div>
                        <div id="selectedFile" class="mt-2" style="display: none;">
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-file-earmark me-2"></i>
                                <span id="selectedFileName"></span>
                                <button type="button" class="btn-close ms-auto" onclick="clearSelectedFile()"></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="uploadDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="uploadDescription" name="description" rows="3" placeholder="Brief description of this document..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadButton" onclick="uploadFile()">
                    <i class="bi bi-cloud-upload"></i> Upload
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.file-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    border-color: #6c757d;
    background-color: #f8f9fa;
}

.file-upload-area.dragover {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}
</style>

<script>
// Project-specific file management functions
let currentProjectId = <?php echo $projectId; ?>;
let currentProjectName = '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>';

function showUploadModal(projectId, projectName) {
    document.getElementById('uploadEntityId').value = projectId;
    document.getElementById('uploadModalLabel').textContent = 'Upload Document for ' + projectName;
    clearUploadForm();
    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

function clearUploadForm() {
    document.getElementById('uploadFile').value = '';
    document.getElementById('uploadDescription').value = '';
    document.getElementById('selectedFile').style.display = 'none';
    document.getElementById('selectedFileName').textContent = '';
}

function clearSelectedFile() {
    clearUploadForm();
}

function uploadFile() {
    const fileInput = document.getElementById('uploadFile');
    const entityType = document.getElementById('uploadEntityType').value;
    const entityId = document.getElementById('uploadEntityId').value;
    const description = document.getElementById('uploadDescription').value;
    
    if (!fileInput.files.length) {
        alert('Please select a file to upload.');
        return;
    }
    
    const uploadButton = document.getElementById('uploadButton');
    const originalText = uploadButton.innerHTML;
    uploadButton.disabled = true;
    uploadButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('entity_type', entityType);
    formData.append('entity_id', entityId);
    formData.append('description', description);
    
    fetch('/api/file-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
            window.location.reload();
        } else {
            alert('Error uploading file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error uploading file. Please try again.');
    })
    .finally(() => {
        uploadButton.disabled = false;
        uploadButton.innerHTML = originalText;
    });
}

function deleteFile(fileId) {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }
    
    fetch('/api/file-upload.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error deleting file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting file. Please try again.');
    });
}

function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
}

// Initialize drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('uploadFile');
    
    if (fileUploadArea && fileInput) {
        // Click to select file
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                document.getElementById('selectedFileName').textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                document.getElementById('selectedFile').style.display = 'block';
            }
        });
        
        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                const file = files[0];
                document.getElementById('selectedFileName').textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                document.getElementById('selectedFile').style.display = 'block';
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>