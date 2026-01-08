/**
 * File Manager JavaScript
 * 
 * Handles file uploads, downloads, and management interface
 */

class FileManager {
    constructor(entityType, entityId) {
        this.entityType = entityType;
        this.entityId = entityId;
        this.uploadArea = null;
        this.fileInput = null;
        this.fileList = null;
        this.fileStats = null;
        
        this.init();
    }
    
    init() {
        this.createInterface();
        this.attachEventListeners();
        this.loadFiles();
    }
    
    createInterface() {
        const container = document.getElementById('file-manager-container');
        if (!container) return;
        
        container.innerHTML = `
            <!-- File Upload Area -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-cloud-upload"></i> Upload Documents & Files
                    </h6>
                </div>
                <div class="card-body">
                    <div class="file-upload-area" id="file-upload-area">
                        <div class="file-upload-icon">
                            <i class="bi bi-cloud-upload"></i>
                        </div>
                        <h6>Drag & drop files here or click to browse</h6>
                        <p class="text-muted small mb-2">
                            Supported: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, images, code files, archives<br>
                            Maximum size: 50MB per file
                        </p>
                        <input type="file" id="file-input" class="d-none" multiple 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.csv,.json,.xml,.html,.css,.js,.php,.py,.java,.cpp,.c,.h,.md,.zip,.rar,.7z,.tar,.gz,.jpg,.jpeg,.png,.gif,.bmp,.svg,.mp3,.mp4,.avi,.mov,.wmv,.flv,.webm,.ogg">
                        <button type="button" class="btn btn-outline-primary mt-2" onclick="document.getElementById('file-input').click()">
                            <i class="bi bi-plus-circle"></i> Select Files
                        </button>
                    </div>
                    
                    <!-- Upload Form -->
                    <div id="upload-form" class="mt-3" style="display: none;">
                        <div class="mb-3">
                            <label for="file-description" class="form-label">Description (optional)</label>
                            <textarea class="form-control" id="file-description" rows="2" 
                                    placeholder="Add a description for this file..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="upload-btn">
                                <i class="bi bi-upload"></i> Upload
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancel-upload-btn">
                                Cancel
                            </button>
                        </div>
                        <div class="file-upload-progress mt-3" id="upload-progress" style="display: none;">
                            <div class="file-upload-progress-bar" id="upload-progress-bar"></div>
                        </div>
                        <div id="upload-message"></div>
                    </div>
                </div>
            </div>
            
            <!-- File List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-files"></i> Documents & Files
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fileManager.loadFiles()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="file-stats" id="file-stats" style="display: none;">
                        <div class="file-stats-item">
                            <i class="bi bi-files"></i>
                            <span id="file-count">0 files</span>
                        </div>
                        <div class="file-stats-item">
                            <i class="bi bi-hdd"></i>
                            <span id="total-size">0 B</span>
                        </div>
                    </div>
                    <div id="file-list">
                        <div class="text-center py-4">
                            <div class="file-loading"></div>
                            <p class="mt-2 text-muted">Loading files...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Get references to DOM elements
        this.uploadArea = document.getElementById('file-upload-area');
        this.fileInput = document.getElementById('file-input');
        this.fileList = document.getElementById('file-list');
        this.fileStats = document.getElementById('file-stats');
    }
    
    attachEventListeners() {
        // File input change
        this.fileInput.addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files);
        });
        
        // Drag and drop
        this.uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.uploadArea.classList.add('dragover');
        });
        
        this.uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.uploadArea.classList.remove('dragover');
        });
        
        this.uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.uploadArea.classList.remove('dragover');
            this.handleFileSelect(e.dataTransfer.files);
        });
        
        // Click to upload area
        this.uploadArea.addEventListener('click', (e) => {
            if (e.target === this.uploadArea || e.target.closest('.file-upload-icon, h6')) {
                this.fileInput.click();
            }
        });
        
        // Upload and cancel buttons
        document.getElementById('upload-btn').addEventListener('click', () => {
            this.uploadFiles();
        });
        
        document.getElementById('cancel-upload-btn').addEventListener('click', () => {
            this.cancelUpload();
        });
    }
    
    handleFileSelect(files) {
        if (files.length === 0) return;
        
        this.selectedFiles = Array.from(files);
        this.showUploadForm();
    }
    
    showUploadForm() {
        const uploadForm = document.getElementById('upload-form');
        uploadForm.style.display = 'block';
        
        // Show selected files info
        const fileInfo = this.selectedFiles.map(file => 
            `${file.name} (${this.formatFileSize(file.size)})`
        ).join(', ');
        
        const message = document.getElementById('upload-message');
        message.innerHTML = `
            <div class="file-upload-message">
                <strong>Selected files:</strong> ${fileInfo}
            </div>
        `;
    }
    
    cancelUpload() {
        document.getElementById('upload-form').style.display = 'none';
        document.getElementById('file-description').value = '';
        document.getElementById('upload-message').innerHTML = '';
        this.fileInput.value = '';
        this.selectedFiles = [];
    }
    
    async uploadFiles() {
        if (!this.selectedFiles || this.selectedFiles.length === 0) return;
        
        const uploadBtn = document.getElementById('upload-btn');
        const progressContainer = document.getElementById('upload-progress');
        const progressBar = document.getElementById('upload-progress-bar');
        const message = document.getElementById('upload-message');
        const description = document.getElementById('file-description').value.trim();
        
        uploadBtn.disabled = true;
        progressContainer.style.display = 'block';
        message.innerHTML = '';
        
        try {
            for (let i = 0; i < this.selectedFiles.length; i++) {
                const file = this.selectedFiles[i];
                const progress = ((i + 1) / this.selectedFiles.length) * 100;
                
                progressBar.style.width = progress + '%';
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('entity_type', this.entityType);
                formData.append('entity_id', this.entityId);
                formData.append('description', description);
                
                const response = await fetch('/api/file-upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(`Failed to upload ${file.name}: ${result.error}`);
                }
            }
            
            message.innerHTML = `
                <div class="file-upload-message success">
                    <i class="bi bi-check-circle"></i> 
                    Successfully uploaded ${this.selectedFiles.length} file(s)!
                </div>
            `;
            
            // Refresh file list and hide upload form
            setTimeout(() => {
                this.cancelUpload();
                this.loadFiles();
            }, 2000);
            
        } catch (error) {
            message.innerHTML = `
                <div class="file-upload-message error">
                    <i class="bi bi-exclamation-triangle"></i> 
                    ${error.message}
                </div>
            `;
        } finally {
            uploadBtn.disabled = false;
            progressContainer.style.display = 'none';
            progressBar.style.width = '0%';
        }
    }
    
    async loadFiles() {
        try {
            const response = await fetch(`/api/file-list.php?entity_type=${this.entityType}&entity_id=${this.entityId}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayFiles(result.files);
                this.updateStats(result.stats);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            this.fileList.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Failed to load files: ${error.message}
                </div>
            `;
        }
    }
    
    displayFiles(files) {
        if (files.length === 0) {
            this.fileList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-2">No files uploaded yet</p>
                    <small>Upload documents, images, or other files to get started</small>
                </div>
            `;
            return;
        }
        
        const fileListHTML = files.map(file => `
            <div class="file-list-item" data-file-id="${file.id}">
                <div class="file-icon">
                    <i class="bi ${file.icon}"></i>
                </div>
                <div class="file-info">
                    <div class="file-name">${this.escapeHtml(file.filename)}</div>
                    <div class="file-meta">
                        <span><i class="bi bi-hdd"></i> ${file.file_size}</span>
                        <span><i class="bi bi-calendar"></i> ${file.upload_date}</span>
                        <span><i class="bi bi-person"></i> ${this.escapeHtml(file.uploaded_by)}</span>
                        ${file.download_count > 0 ? `<span><i class="bi bi-download"></i> ${file.download_count} downloads</span>` : ''}
                    </div>
                    ${file.description ? `<div class="file-description">"${this.escapeHtml(file.description)}"</div>` : ''}
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            onclick="fileManager.downloadFile(${file.id})" 
                            title="Download file">
                        <i class="bi bi-download"></i>
                    </button>
                    ${file.can_delete ? `
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="fileManager.deleteFile(${file.id})" 
                                title="Delete file">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
        
        this.fileList.innerHTML = fileListHTML;
    }
    
    updateStats(stats) {
        if (stats.file_count > 0) {
            document.getElementById('file-count').textContent = 
                `${stats.file_count} file${stats.file_count !== 1 ? 's' : ''}`;
            document.getElementById('total-size').textContent = 
                this.formatFileSize(stats.total_size || 0);
            this.fileStats.style.display = 'flex';
        } else {
            this.fileStats.style.display = 'none';
        }
    }
    
    downloadFile(fileId) {
        window.open(`/api/file-download.php?id=${fileId}`, '_blank');
    }
    
    async deleteFile(fileId) {
        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }
        
        try {
            const response = await fetch('/api/file-upload.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `file_id=${fileId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Remove file from display
                const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
                if (fileElement) {
                    fileElement.remove();
                }
                
                // Reload files to update stats
                this.loadFiles();
                
                // Show success message
                this.showMessage('File deleted successfully!', 'success');
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            this.showMessage(`Failed to delete file: ${error.message}`, 'error');
        }
    }
    
    showMessage(message, type = 'info') {
        const messageEl = document.createElement('div');
        messageEl.className = `alert alert-${type} alert-dismissible fade show`;
        messageEl.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at top of file list card
        const cardBody = this.fileList.closest('.card-body');
        cardBody.insertBefore(messageEl, cardBody.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (messageEl.parentNode) {
                messageEl.remove();
            }
        }, 5000);
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + units[i];
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global variable to hold file manager instance
let fileManager = null;