<?php
/**
 * File Download API Endpoint
 * 
 * Handles secure file downloads
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/FileManager.php';

// Require login
requireLogin();

try {
    $currentUserId = getCurrentUserId();
    $fileManager = new FileManager();
    
    $fileId = (int)($_GET['id'] ?? 0);
    
    if ($fileId <= 0) {
        throw new Exception('Invalid file ID.');
    }
    
    $result = $fileManager->downloadFile($fileId, $currentUserId);
    
    if (!$result['success']) {
        http_response_code(404);
        die('File not found.');
    }
    
    $filePath = $result['file_path'];
    $filename = $result['filename'];
    $mimeType = $result['mime_type'];
    $fileSize = $result['file_size'];
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file content
    readfile($filePath);
    
} catch (Exception $e) {
    http_response_code(404);
    die('File not found.');
}
?>