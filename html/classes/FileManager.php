<?php
/**
 * FileManager Class
 * 
 * Handles file uploads, downloads, and management for projects, features, and tasks
 */

require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/config/constants.php';
require_once dirname(dirname(__FILE__)) . '/classes/User.php';

class FileManager {
    private $db;
    private $uploadBasePath;
    private $maxFileSize;
    private $allowedExtensions;
    private $blockedExtensions;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfiguration();
        $this->ensureUploadDirectory();
    }
    
    /**
     * Load configuration from database
     */
    private function loadConfiguration() {
        try {
            $stmt = $this->db->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ('upload_max_file_size', 'upload_allowed_extensions', 'upload_blocked_extensions', 'upload_base_path')");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $this->maxFileSize = (int)($configs['upload_max_file_size'] ?? 50) * 1024 * 1024; // Convert MB to bytes
            $this->allowedExtensions = array_map('trim', explode(',', $configs['upload_allowed_extensions'] ?? ''));
            $this->blockedExtensions = array_map('trim', explode(',', $configs['upload_blocked_extensions'] ?? 'exe,msi'));
            $this->uploadBasePath = rtrim($configs['upload_base_path'] ?? 'uploads/', '/') . '/';
            
        } catch (PDOException $e) {
            // Use defaults if config table doesn't exist yet
            $this->maxFileSize = 50 * 1024 * 1024; // 50MB
            $this->allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png'];
            $this->blockedExtensions = ['exe', 'msi', 'bat', 'cmd'];
            $this->uploadBasePath = 'uploads/';
        }
    }
    
    /**
     * Ensure upload directory exists and is secure
     */
    private function ensureUploadDirectory() {
        $fullPath = dirname(dirname(__FILE__)) . '/' . $this->uploadBasePath;
        
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        // Create .htaccess for security
        $htaccessPath = $fullPath . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Prevent direct execution of uploaded files\n";
            $htaccessContent .= "php_flag engine off\n";
            $htaccessContent .= "AddType text/plain .php .php3 .phtml .pht\n";
            $htaccessContent .= "AddType text/plain .exe .msi .bat .cmd\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }
        
        // Create subdirectories
        foreach (['projects', 'features', 'tasks'] as $type) {
            $typePath = $fullPath . $type;
            if (!is_dir($typePath)) {
                mkdir($typePath, 0755, true);
            }
        }
    }
    
    /**
     * Upload a file
     */
    public function uploadFile($file, $entityType, $entityId, $uploadedBy, $description = null) {
        try {
            // Validate input
            if (!in_array($entityType, ['project', 'feature', 'task'])) {
                return ['success' => false, 'error' => 'Invalid entity type.'];
            }
            
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['success' => false, 'error' => 'No file uploaded or invalid file.'];
            }
            
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Generate secure filename
            $originalFilename = $file['name'];
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $secureFilename = $this->generateSecureFilename($originalFilename, $extension);
            
            // Determine file path
            $relativePath = $this->uploadBasePath . $entityType . 's/' . date('Y/m/');
            $fullPath = dirname(dirname(__FILE__)) . '/' . $relativePath;
            
            // Create directory if it doesn't exist
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            $filePath = $relativePath . $secureFilename;
            $fullFilePath = dirname(dirname(__FILE__)) . '/' . $filePath;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullFilePath)) {
                return ['success' => false, 'error' => 'Failed to save uploaded file.'];
            }
            
            // Calculate file hash
            $fileHash = hash_file('sha256', $fullFilePath);
            
            // Save file record to database
            $stmt = $this->db->prepare("
                INSERT INTO file_attachments 
                (filename, original_filename, file_path, file_size, file_type, mime_type, file_hash, entity_type, entity_id, uploaded_by, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $secureFilename,
                $originalFilename,
                $filePath,
                $file['size'],
                $extension,
                $file['type'],
                $fileHash,
                $entityType,
                $entityId,
                $uploadedBy,
                $description
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'file_id' => $this->db->lastInsertId(),
                    'filename' => $secureFilename,
                    'original_filename' => $originalFilename
                ];
            } else {
                // Remove uploaded file if database insert failed
                unlink($fullFilePath);
                return ['success' => false, 'error' => 'Failed to save file record.'];
            }
            
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred during file upload.'];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File is too large (server limit).',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
            ];
            
            return [
                'valid' => false,
                'error' => $errorMessages[$file['error']] ?? 'Unknown upload error.'
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'error' => 'File is too large. Maximum size is ' . ($this->maxFileSize / 1024 / 1024) . ' MB.'
            ];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, $this->blockedExtensions)) {
            return [
                'valid' => false,
                'error' => 'File type "' . $extension . '" is not allowed for security reasons.'
            ];
        }
        
        if (!empty($this->allowedExtensions) && !in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'error' => 'File type "' . $extension . '" is not allowed. Allowed types: ' . implode(', ', $this->allowedExtensions) . '.'
            ];
        }
        
        // Basic MIME type validation
        $allowedMimeTypes = [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'application/json', 'text/xml', 'application/xml',
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/svg+xml',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Allow text files and common document types even if MIME detection is imperfect
        if (!in_array($detectedMimeType, $allowedMimeTypes) && 
            !str_starts_with($detectedMimeType, 'text/') && 
            !str_starts_with($detectedMimeType, 'application/')) {
            return [
                'valid' => false,
                'error' => 'File type not recognized or potentially unsafe.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($originalFilename, $extension) {
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $baseName);
        $baseName = substr($baseName, 0, 50); // Limit length
        
        $timestamp = time();
        $randomString = bin2hex(random_bytes(4));
        
        return $baseName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
    }
    
    /**
     * Get files for an entity
     */
    public function getFiles($entityType, $entityId) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, u.first_name, u.last_name 
                FROM file_attachments f
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE f.entity_type = ? AND f.entity_id = ? AND f.is_active = 1
                ORDER BY f.upload_date DESC
            ");
            $stmt->execute([$entityType, $entityId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get files error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Download file
     */
    public function downloadFile($fileId, $userId = null) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM file_attachments WHERE id = ? AND is_active = 1");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) {
                return ['success' => false, 'error' => 'File not found.'];
            }
            
            $fullPath = dirname(dirname(__FILE__)) . '/' . $file['file_path'];
            
            if (!file_exists($fullPath)) {
                return ['success' => false, 'error' => 'File not found on disk.'];
            }
            
            // Update download count
            if ($userId) {
                $updateStmt = $this->db->prepare("
                    UPDATE file_attachments 
                    SET download_count = download_count + 1, last_downloaded = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$fileId]);
            }
            
            return [
                'success' => true,
                'file_path' => $fullPath,
                'filename' => $file['original_filename'],
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size']
            ];
            
        } catch (PDOException $e) {
            error_log("Download file error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId, $userId) {
        try {
            // Get file info first
            $stmt = $this->db->prepare("SELECT * FROM file_attachments WHERE id = ? AND is_active = 1");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) {
                return ['success' => false, 'error' => 'File not found.'];
            }
            
            // Check if user can delete (file uploader or admin)
            $userObj = new User();
            if ($file['uploaded_by'] != $userId && !$userObj->isAdmin($userId)) {
                return ['success' => false, 'error' => 'You do not have permission to delete this file.'];
            }
            
            // Mark as inactive (soft delete)
            $deleteStmt = $this->db->prepare("UPDATE file_attachments SET is_active = 0 WHERE id = ?");
            $result = $deleteStmt->execute([$fileId]);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to delete file.'];
            }
            
        } catch (PDOException $e) {
            error_log("Delete file error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get file statistics
     */
    public function getFileStats($entityType = null, $entityId = null) {
        try {
            $sql = "SELECT COUNT(*) as file_count, SUM(file_size) as total_size FROM file_attachments WHERE is_active = 1";
            $params = [];
            
            if ($entityType && $entityId) {
                $sql .= " AND entity_type = ? AND entity_id = ?";
                $params = [$entityType, $entityId];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get file stats error: " . $e->getMessage());
            return ['file_count' => 0, 'total_size' => 0];
        }
    }
    
    /**
     * Format file size for display
     */
    public function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get file icon based on extension
     */
    public function getFileIcon($extension) {
        $iconMap = [
            // Documents
            'pdf' => 'bi-file-earmark-pdf',
            'doc' => 'bi-file-earmark-word',
            'docx' => 'bi-file-earmark-word',
            'xls' => 'bi-file-earmark-excel',
            'xlsx' => 'bi-file-earmark-excel',
            'ppt' => 'bi-file-earmark-ppt',
            'pptx' => 'bi-file-earmark-ppt',
            'txt' => 'bi-file-earmark-text',
            'rtf' => 'bi-file-earmark-text',
            'csv' => 'bi-file-earmark-spreadsheet',
            
            // Code files
            'html' => 'bi-file-earmark-code',
            'css' => 'bi-file-earmark-code',
            'js' => 'bi-file-earmark-code',
            'php' => 'bi-file-earmark-code',
            'py' => 'bi-file-earmark-code',
            'java' => 'bi-file-earmark-code',
            'cpp' => 'bi-file-earmark-code',
            'c' => 'bi-file-earmark-code',
            'h' => 'bi-file-earmark-code',
            'json' => 'bi-file-earmark-code',
            'xml' => 'bi-file-earmark-code',
            
            // Images
            'jpg' => 'bi-file-earmark-image',
            'jpeg' => 'bi-file-earmark-image',
            'png' => 'bi-file-earmark-image',
            'gif' => 'bi-file-earmark-image',
            'bmp' => 'bi-file-earmark-image',
            'svg' => 'bi-file-earmark-image',
            
            // Archives
            'zip' => 'bi-file-earmark-zip',
            'rar' => 'bi-file-earmark-zip',
            '7z' => 'bi-file-earmark-zip',
            'tar' => 'bi-file-earmark-zip',
            'gz' => 'bi-file-earmark-zip',
            
            // Media
            'mp3' => 'bi-file-earmark-music',
            'wav' => 'bi-file-earmark-music',
            'ogg' => 'bi-file-earmark-music',
            'mp4' => 'bi-file-earmark-play',
            'avi' => 'bi-file-earmark-play',
            'mov' => 'bi-file-earmark-play',
            'wmv' => 'bi-file-earmark-play',
            'flv' => 'bi-file-earmark-play',
            'webm' => 'bi-file-earmark-play',
            
            // Other
            'md' => 'bi-file-earmark-text'
        ];
        
        return $iconMap[strtolower($extension)] ?? 'bi-file-earmark';
    }
}
?>