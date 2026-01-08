<?php
/**
 * File List API Endpoint
 * 
 * Returns list of files for an entity
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/FileManager.php';

// Require login
requireLogin();

header('Content-Type: application/json');

try {
    $currentUserId = getCurrentUserId();
    $fileManager = new FileManager();
    
    $entityType = $_GET['entity_type'] ?? '';
    $entityId = (int)($_GET['entity_id'] ?? 0);
    
    if (!in_array($entityType, ['project', 'feature', 'task'])) {
        throw new Exception('Invalid entity type.');
    }
    
    if ($entityId <= 0) {
        throw new Exception('Invalid entity ID.');
    }
    
    $files = $fileManager->getFiles($entityType, $entityId);
    
    // Format file data for display
    $formattedFiles = [];
    foreach ($files as $file) {
        $formattedFiles[] = [
            'id' => $file['id'],
            'filename' => $file['original_filename'],
            'description' => $file['description'],
            'file_size' => $fileManager->formatFileSize($file['file_size']),
            'file_size_bytes' => $file['file_size'],
            'file_type' => $file['file_type'],
            'mime_type' => $file['mime_type'],
            'upload_date' => date('M j, Y g:i A', strtotime($file['upload_date'])),
            'uploaded_by' => $file['first_name'] . ' ' . $file['last_name'],
            'uploaded_by_id' => $file['uploaded_by'],
            'download_count' => $file['download_count'],
            'icon' => $fileManager->getFileIcon($file['file_type']),
            'can_delete' => ($file['uploaded_by'] == $currentUserId) // TODO: Add admin check
        ];
    }
    
    echo json_encode([
        'success' => true,
        'files' => $formattedFiles,
        'stats' => $fileManager->getFileStats($entityType, $entityId)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>