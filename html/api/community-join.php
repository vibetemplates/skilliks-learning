<?php
/**
 * API endpoint to join a community
 */

require_once '../includes/session.php';
require_once '../classes/Community.php';
require_once '../classes/CommunityAutoApproval.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['community_id']) || !is_numeric($input['community_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid community ID']);
    exit;
}

$communityId = (int)$input['community_id'];
$userId = $_SESSION['user_id'];

// Get user details for auto-approval check
require_once '../config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT email, github_username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userDetails = $stmt->fetch();

// Initialize classes
$community = new Community();
$autoApproval = new CommunityAutoApproval();

// Get community details
$communityData = $community->getById($communityId);

if (!$communityData) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Community not found']);
    exit;
}

// Check if community is public
if (!$communityData['is_public']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This is a private community']);
    exit;
}

// Check if user is already a member
$userRole = $community->getUserRole($communityId, $userId);
if ($userRole) {
    echo json_encode(['success' => true, 'message' => 'You are already a member of this community']);
    exit;
}

// Check if user is auto-approved
$isAutoApproved = false;
if ($communityData['requires_approval'] && $userDetails) {
    $isAutoApproved = $autoApproval->isAutoApproved(
        $communityId, 
        $userDetails['email'], 
        $userDetails['github_username']
    );
}

// Check for existing pending join request
$stmt = $db->prepare("
    SELECT id FROM community_join_requests 
    WHERE community_id = ? AND user_id = ? AND status = 'pending'
");
$stmt->execute([$communityId, $userId]);
$existingRequest = $stmt->fetch();

if ($existingRequest) {
    echo json_encode([
        'success' => false,
        'message' => 'You already have a pending request to join this community'
    ]);
    exit;
}

// If community doesn't require approval or user is auto-approved
if (!$communityData['requires_approval'] || $isAutoApproved) {
    // Add user to community directly
    $role = 'member';
    $result = $community->addMember($communityId, $userId, $role);
    
    if ($result) {
        // Update member status to approved if auto-approved
        if ($isAutoApproved) {
            $stmt = $db->prepare("UPDATE community_members SET is_active = 1 WHERE community_id = ? AND user_id = ?");
            $stmt->execute([$communityId, $userId]);
        }
        
        setCurrentCommunity($communityId);
        
        echo json_encode([
            'success' => true,
            'message' => $isAutoApproved ? 'Successfully joined community (auto-approved)' : 'Successfully joined community',
            'requires_approval' => false,
            'auto_approved' => $isAutoApproved
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to join community']);
    }
} else {
    // Create join request
    $requestMessage = isset($input['message']) ? trim($input['message']) : null;
    
    $stmt = $db->prepare("
        INSERT INTO community_join_requests (community_id, user_id, request_message) 
        VALUES (?, ?, ?)
    ");
    
    try {
        $stmt->execute([$communityId, $userId, $requestMessage]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Your request to join has been submitted and is pending approval',
            'requires_approval' => true,
            'auto_approved' => false
        ]);
    } catch (PDOException $e) {
        error_log("Failed to create join request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit join request']);
    }
}