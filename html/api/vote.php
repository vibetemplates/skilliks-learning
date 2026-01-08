<?php
/**
 * Voting API
 * 
 * Handles voting for projects and features
 */

require_once dirname(dirname(__FILE__)) . '/includes/session.php';
require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/config/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$currentUserId = getCurrentUserId();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? ''; // 'project' or 'feature'
    $id = (int)($input['id'] ?? 0);
    $voteType = $input['vote_type'] ?? 'up'; // 'up' or 'down'
    $action = $input['action'] ?? 'vote'; // 'vote' or 'unvote'
    
    if (!in_array($type, ['project', 'feature'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid vote type.']);
        exit;
    }
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
        exit;
    }
    
    if (!in_array($voteType, ['up', 'down'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid vote type.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        if ($type === 'project') {
            // Verify project exists
            $stmt = $db->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Project not found.']);
                exit;
            }
            
            if ($action === 'unvote') {
                // Remove vote
                $stmt = $db->prepare("DELETE FROM project_votes WHERE project_id = ? AND user_id = ?");
                $stmt->execute([$id, $currentUserId]);
            } else {
                // Add or update vote
                $stmt = $db->prepare("
                    INSERT INTO project_votes (project_id, user_id, vote_type) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type), voted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$id, $currentUserId, $voteType]);
            }
            
            // Update vote count
            $stmt = $db->prepare("
                UPDATE projects 
                SET vote_count = (
                    SELECT COALESCE(SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END), 0)
                    FROM project_votes 
                    WHERE project_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$id, $id]);
            
            // Get updated vote count
            $stmt = $db->prepare("SELECT vote_count FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $voteCount = $result['vote_count'];
            
        } else { // feature
            // Verify feature exists
            $stmt = $db->prepare("SELECT id FROM features WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Feature not found.']);
                exit;
            }
            
            if ($action === 'unvote') {
                // Remove vote
                $stmt = $db->prepare("DELETE FROM feature_votes WHERE feature_id = ? AND user_id = ?");
                $stmt->execute([$id, $currentUserId]);
            } else {
                // Add or update vote
                $stmt = $db->prepare("
                    INSERT INTO feature_votes (feature_id, user_id, vote_type) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type), voted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$id, $currentUserId, $voteType]);
            }
            
            // Update vote count
            $stmt = $db->prepare("
                UPDATE features 
                SET vote_count = (
                    SELECT COALESCE(SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END), 0)
                    FROM feature_votes 
                    WHERE feature_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$id, $id]);
            
            // Get updated vote count
            $stmt = $db->prepare("SELECT vote_count FROM features WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            $voteCount = $result['vote_count'];
        }
        
        // Get user's current vote
        if ($type === 'project') {
            $stmt = $db->prepare("SELECT vote_type FROM project_votes WHERE project_id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("SELECT vote_type FROM feature_votes WHERE feature_id = ? AND user_id = ?");
        }
        $stmt->execute([$id, $currentUserId]);
        $userVote = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'vote_count' => $voteCount,
            'user_vote' => $userVote ? $userVote['vote_type'] : null,
            'message' => $action === 'unvote' ? 'Vote removed successfully.' : 'Vote recorded successfully.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Voting error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while processing vote.']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get vote status for a user
    $type = $_GET['type'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
    
    if (!in_array($type, ['project', 'feature']) || $id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        if ($type === 'project') {
            // Get project vote info
            $stmt = $db->prepare("
                SELECT p.vote_count, pv.vote_type as user_vote
                FROM projects p
                LEFT JOIN project_votes pv ON p.id = pv.project_id AND pv.user_id = ?
                WHERE p.id = ?
            ");
            $stmt->execute([$currentUserId, $id]);
        } else {
            // Get feature vote info
            $stmt = $db->prepare("
                SELECT f.vote_count, fv.vote_type as user_vote
                FROM features f
                LEFT JOIN feature_votes fv ON f.id = fv.feature_id AND fv.user_id = ?
                WHERE f.id = ?
            ");
            $stmt->execute([$currentUserId, $id]);
        }
        
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'vote_count' => $result['vote_count'] ?? 0,
                'user_vote' => $result['user_vote']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Item not found.']);
        }
        
    } catch (PDOException $e) {
        error_log("Vote fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while fetching vote.']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>