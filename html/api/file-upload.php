<?php
/**
 * File Upload API Endpoint
 * 
 * Handles file uploads for projects, features, and tasks
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/FileManager.php';
require_once '../classes/User.php';

// Require login
requireLogin();

header('Content-Type: application/json');

try {
    $currentUserId = getCurrentUserId();
    $fileManager = new FileManager();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required parameters
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (!in_array($entityType, ['project', 'feature', 'task'])) {
            throw new Exception('Invalid entity type.');
        }
        
        if ($entityId <= 0) {
            throw new Exception('Invalid entity ID.');
        }
        
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded.');
        }
        
        // TODO: Add permission checks based on entity type
        // For now, allow any logged-in user to upload files
        
        $result = $fileManager->uploadFile($_FILES['file'], $entityType, $entityId, $currentUserId, $description);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully!',
                'file' => $result
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $result['error']
            ]);
        }
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Handle file deletion
        parse_str(file_get_contents("php://input"), $data);
        $fileId = (int)($data['file_id'] ?? 0);
        
        if ($fileId <= 0) {
            throw new Exception('Invalid file ID.');
        }
        
        $result = $fileManager->deleteFile($fileId, $currentUserId);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'File deleted successfully!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $result['error']
            ]);
        }
        
    } else {
        throw new Exception('Invalid request method.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>