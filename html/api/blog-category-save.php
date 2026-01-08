<?php
/**
 * Blog Category Save API
 * 
 * Handles creating, updating, and deleting blog categories
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/BlogCategory.php';
require_once '../classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$blogCategory = new BlogCategory();
$community = new Community();

// Check if user is admin
$userRole = $community->isMember($currentCommunityId, $currentUserId);
if ($userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            // Validate required fields
            if (empty($_POST['name'])) {
                throw new Exception('Category name is required');
            }
            
            $data = [
                'community_id' => $currentCommunityId,
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'color' => $_POST['color'] ?? '#6c757d',
                'icon' => trim($_POST['icon'] ?? ''),
                'is_active' => 1
            ];
            
            $categoryId = $blogCategory->create($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully',
                'category_id' => $categoryId
            ]);
            break;
            
        case 'update':
            // Validate required fields
            if (empty($_POST['id']) || empty($_POST['name'])) {
                throw new Exception('Category ID and name are required');
            }
            
            $categoryId = (int)$_POST['id'];
            
            // Verify category belongs to this community
            $category = $blogCategory->getById($categoryId);
            if (!$category || $category['community_id'] != $currentCommunityId) {
                throw new Exception('Category not found');
            }
            
            $data = [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'color' => $_POST['color'] ?? '#6c757d',
                'icon' => trim($_POST['icon'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $blogCategory->update($categoryId, $data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category updated successfully'
            ]);
            break;
            
        case 'delete':
            // Validate required fields
            if (empty($_POST['id'])) {
                throw new Exception('Category ID is required');
            }
            
            $categoryId = (int)$_POST['id'];
            
            // Verify category belongs to this community
            $category = $blogCategory->getById($categoryId);
            if (!$category || $category['community_id'] != $currentCommunityId) {
                throw new Exception('Category not found');
            }
            
            $blogCategory->delete($categoryId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}