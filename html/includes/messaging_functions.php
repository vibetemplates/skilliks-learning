<?php
/**
 * Messaging Functions
 * 
 * Core functions for the messaging system with security checks
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Check if two users are in the same community
 */
function usersInSameCommunity($user1_id, $user2_id, $community_id = null) {
    $db = getDB();
    
    if ($community_id) {
        // Check specific community
        $sql = "SELECT COUNT(*) FROM community_members 
                WHERE community_id = ? AND user_id IN (?, ?) AND is_active = 1
                GROUP BY community_id
                HAVING COUNT(*) = 2";
        $stmt = $db->prepare($sql);
        $stmt->execute([$community_id, $user1_id, $user2_id]);
        return $stmt->fetchColumn() > 0;
    } else {
        // Check any shared community
        $sql = "SELECT cm1.community_id 
                FROM community_members cm1
                INNER JOIN community_members cm2 ON cm1.community_id = cm2.community_id
                WHERE cm1.user_id = ? AND cm2.user_id = ? 
                AND cm1.is_active = 1 AND cm2.is_active = 1
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user1_id, $user2_id]);
        return $stmt->fetch() !== false;
    }
}

/**
 * Check if a user is blocked by another user
 */
function isUserBlocked($blocker_id, $blocked_id) {
    $db = getDB();
    $sql = "SELECT COUNT(*) FROM user_blocks 
            WHERE blocker_user_id = ? AND blocked_user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$blocker_id, $blocked_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get or create a conversation between two users
 */
function getOrCreateConversation($user1_id, $user2_id, $community_id) {
    $db = getDB();
    
    // First check if users are in the same community
    if (!usersInSameCommunity($user1_id, $user2_id, $community_id)) {
        return ['error' => 'Users must be in the same community to message each other'];
    }
    
    // Check for blocks
    if (isUserBlocked($user1_id, $user2_id) || isUserBlocked($user2_id, $user1_id)) {
        return ['error' => 'Unable to create conversation - user blocked'];
    }
    
    // Look for existing conversation
    $sql = "SELECT c.id FROM conversations c
            INNER JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
            INNER JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
            WHERE c.community_id = ?
            AND cp1.user_id = ? AND cp2.user_id = ?
            AND cp1.is_deleted = 0 AND cp2.is_deleted = 0";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$community_id, $user1_id, $user2_id]);
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        return ['conversation_id' => $conversation['id']];
    }
    
    // Create new conversation
    $db->beginTransaction();
    try {
        // Create conversation
        $sql = "INSERT INTO conversations (community_id) VALUES (?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$community_id]);
        $conversation_id = $db->lastInsertId();
        
        // Add participants
        $sql = "INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$conversation_id, $user1_id, $conversation_id, $user2_id]);
        
        $db->commit();
        return ['conversation_id' => $conversation_id];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => 'Failed to create conversation'];
    }
}

/**
 * Check if a user is a participant in a conversation
 */
function isConversationParticipant($user_id, $conversation_id) {
    $db = getDB();
    $sql = "SELECT COUNT(*) FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND is_deleted = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$conversation_id, $user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Send a message
 */
function sendMessage($conversation_id, $sender_id, $message_text) {
    $db = getDB();
    
    // Verify sender is participant
    if (!isConversationParticipant($sender_id, $conversation_id)) {
        return ['error' => 'You are not a participant in this conversation'];
    }
    
    // Check rate limiting
    if (!checkMessageRateLimit($sender_id)) {
        return ['error' => 'Message rate limit exceeded. Maximum 30 messages per minute.'];
    }
    
    // Sanitize message
    $message_text = trim($message_text);
    if (empty($message_text)) {
        return ['error' => 'Message cannot be empty'];
    }
    
    if (strlen($message_text) > 5000) {
        return ['error' => 'Message too long. Maximum 5000 characters.'];
    }
    
    $db->beginTransaction();
    try {
        // Insert message
        $sql = "INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$conversation_id, $sender_id, $message_text]);
        $message_id = $db->lastInsertId();
        
        // Update conversation timestamp
        $sql = "UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$conversation_id]);
        
        // Update rate limit
        updateMessageRateLimit($sender_id);
        
        $db->commit();
        return ['message_id' => $message_id];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => 'Failed to send message'];
    }
}

/**
 * Check message rate limit
 */
function checkMessageRateLimit($user_id) {
    $db = getDB();
    
    // Check current rate limit
    $sql = "SELECT message_count, window_start FROM message_rate_limits WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $limit = $stmt->fetch();
    
    if (!$limit) {
        // No limit record, user can send
        return true;
    }
    
    // Check if window has expired (1 minute window)
    $window_start = strtotime($limit['window_start']);
    if (time() - $window_start > 60) {
        // Window expired, reset
        $sql = "UPDATE message_rate_limits SET message_count = 0, window_start = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
        return true;
    }
    
    // Check if under limit (30 messages per minute)
    return $limit['message_count'] < 30;
}

/**
 * Update message rate limit
 */
function updateMessageRateLimit($user_id) {
    $db = getDB();
    
    $sql = "INSERT INTO message_rate_limits (user_id, message_count, window_start) 
            VALUES (?, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
            message_count = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) > 60, 1, message_count + 1),
            window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) > 60, CURRENT_TIMESTAMP, window_start)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
}

/**
 * Get user conversations with last message preview
 */
function getUserConversations($user_id, $community_id) {
    $db = getDB();
    
    $sql = "SELECT 
                c.id AS conversation_id,
                c.updated_at,
                cp_other.user_id AS other_user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS other_user_name,
                u.profile_photo AS other_user_photo,
                m.message_text AS last_message,
                m.sender_id AS last_message_sender,
                m.created_at AS last_message_time,
                cp.last_read_at,
                (SELECT COUNT(*) FROM messages m2 
                 WHERE m2.conversation_id = c.id 
                 AND m2.created_at > IFNULL(cp.last_read_at, '1970-01-01')
                 AND m2.sender_id != ?) AS unread_count
            FROM conversations c
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.user_id = ?
            INNER JOIN conversation_participants cp_other ON c.id = cp_other.conversation_id AND cp_other.user_id != ?
            INNER JOIN users u ON cp_other.user_id = u.id
            LEFT JOIN messages m ON m.id = (
                SELECT id FROM messages 
                WHERE conversation_id = c.id AND is_deleted = 0
                ORDER BY created_at DESC LIMIT 1
            )
            WHERE c.community_id = ? AND cp.is_deleted = 0
            ORDER BY c.updated_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $user_id, $user_id, $community_id]);
    return $stmt->fetchAll();
}

/**
 * Get user conversations from all their communities
 */
function getUserConversationsAllCommunities($user_id) {
    $db = getDB();
    
    // First get all communities the user is a member of
    $communities_sql = "SELECT community_id FROM community_members WHERE user_id = ? AND is_active = 1";
    $stmt = $db->prepare($communities_sql);
    $stmt->execute([$user_id]);
    $community_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($community_ids)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($community_ids) - 1) . '?';
    
    $sql = "SELECT 
                c.id AS conversation_id,
                c.updated_at,
                c.community_id,
                com.name AS community_name,
                cp_other.user_id AS other_user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS other_user_name,
                u.profile_photo AS other_user_photo,
                m.message_text AS last_message,
                m.sender_id AS last_message_sender,
                m.created_at AS last_message_time,
                cp.last_read_at,
                (SELECT COUNT(*) FROM messages m2 
                 WHERE m2.conversation_id = c.id 
                 AND m2.created_at > IFNULL(cp.last_read_at, '1970-01-01')
                 AND m2.sender_id != ?) AS unread_count
            FROM conversations c
            INNER JOIN communities com ON c.community_id = com.id
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.user_id = ?
            INNER JOIN conversation_participants cp_other ON c.id = cp_other.conversation_id AND cp_other.user_id != ?
            INNER JOIN users u ON cp_other.user_id = u.id
            LEFT JOIN messages m ON m.id = (
                SELECT id FROM messages 
                WHERE conversation_id = c.id AND is_deleted = 0
                ORDER BY created_at DESC LIMIT 1
            )
            WHERE c.community_id IN ($placeholders) AND cp.is_deleted = 0
            ORDER BY c.updated_at DESC";
    
    $params = array_merge([$user_id, $user_id, $user_id], $community_ids);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get conversation messages with pagination
 */
function getConversationMessages($conversation_id, $user_id, $limit = 50, $offset = 0) {
    $db = getDB();
    
    // Verify user is participant
    if (!isConversationParticipant($user_id, $conversation_id)) {
        return ['error' => 'Access denied'];
    }
    
    $sql = "SELECT 
                m.id,
                m.sender_id,
                m.message_text,
                m.created_at,
                m.is_deleted,
                CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                u.profile_photo AS sender_photo
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$conversation_id, $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // Reverse to show oldest first
    return array_reverse($messages);
}

/**
 * Mark messages as read
 */
function markMessagesAsRead($conversation_id, $user_id) {
    $db = getDB();
    
    $sql = "UPDATE conversation_participants 
            SET last_read_at = CURRENT_TIMESTAMP 
            WHERE conversation_id = ? AND user_id = ?";
    
    $stmt = $db->prepare($sql);
    return $stmt->execute([$conversation_id, $user_id]);
}

/**
 * Delete message (soft delete)
 */
function deleteMessage($message_id, $user_id) {
    $db = getDB();
    
    // Verify user is sender
    $sql = "SELECT sender_id FROM messages WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();
    
    if (!$message || $message['sender_id'] != $user_id) {
        return false;
    }
    
    $sql = "UPDATE messages SET is_deleted = 1 WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute([$message_id]);
}

/**
 * Block/unblock user
 */
function toggleUserBlock($blocker_id, $blocked_id, $block = true) {
    $db = getDB();
    
    if ($block) {
        $sql = "INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$blocker_id, $blocked_id]);
    } else {
        $sql = "DELETE FROM user_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$blocker_id, $blocked_id]);
    }
}

/**
 * Get unread message count for user
 */
function getUnreadMessageCount($user_id, $community_id = null) {
    $db = getDB();
    
    $sql = "SELECT COUNT(DISTINCT m.id) AS unread_count
            FROM messages m
            INNER JOIN conversations c ON m.conversation_id = c.id
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            AND m.sender_id != ?
            AND m.created_at > IFNULL(cp.last_read_at, '1970-01-01')
            AND m.is_deleted = 0
            AND cp.is_deleted = 0";
    
    $params = [$user_id, $user_id];
    
    if ($community_id) {
        $sql .= " AND c.community_id = ?";
        $params[] = $community_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['unread_count'] ?? 0;
}

/**
 * Check for new messages since timestamp
 */
function getNewMessagesSince($user_id, $community_id, $since_timestamp) {
    $db = getDB();
    
    $sql = "SELECT 
                m.id,
                m.conversation_id,
                m.sender_id,
                m.message_text,
                m.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                u.profile_photo AS sender_photo
            FROM messages m
            INNER JOIN conversations c ON m.conversation_id = c.id
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            INNER JOIN users u ON m.sender_id = u.id
            WHERE cp.user_id = ?
            AND c.community_id = ?
            AND m.created_at > ?
            AND m.sender_id != ?
            AND m.is_deleted = 0
            AND cp.is_deleted = 0
            ORDER BY m.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $community_id, $since_timestamp, $user_id]);
    return $stmt->fetchAll();
}

/**
 * Get total unread message count from all communities
 */
function getUnreadMessageCountAllCommunities($user_id) {
    $db = getDB();
    
    // Get all communities the user is a member of
    $communities_sql = "SELECT community_id FROM community_members WHERE user_id = ? AND is_active = 1";
    $stmt = $db->prepare($communities_sql);
    $stmt->execute([$user_id]);
    $community_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($community_ids)) {
        return 0;
    }
    
    $placeholders = str_repeat('?,', count($community_ids) - 1) . '?';
    
    $sql = "SELECT COUNT(DISTINCT m.id) AS unread_count
            FROM messages m
            INNER JOIN conversations c ON m.conversation_id = c.id
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            AND m.sender_id != ?
            AND m.created_at > IFNULL(cp.last_read_at, '1970-01-01')
            AND m.is_deleted = 0
            AND c.community_id IN ($placeholders)";
    
    $params = array_merge([$user_id, $user_id], $community_ids);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch();
    return $result['unread_count'] ?? 0;
}

/**
 * Search for users to message within community
 */
function searchUsersForMessaging($current_user_id, $community_id, $search_term) {
    $db = getDB();
    
    $sql = "SELECT 
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                u.email,
                u.profile_photo,
                CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END AS is_blocked
            FROM users u
            INNER JOIN community_members cm ON u.id = cm.user_id
            LEFT JOIN user_blocks ub ON (ub.blocker_user_id = ? AND ub.blocked_user_id = u.id)
            WHERE cm.community_id = ?
            AND cm.is_active = 1
            AND u.id != ?
            AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)
            ORDER BY CONCAT(u.first_name, ' ', u.last_name) ASC
            LIMIT 10";
    
    $search_pattern = '%' . $search_term . '%';
    $stmt = $db->prepare($sql);
    $stmt->execute([$current_user_id, $community_id, $current_user_id, $search_pattern, $search_pattern]);
    return $stmt->fetchAll();
}

/**
 * Search for users to message from all user's communities
 */
function searchUsersForMessagingAllCommunities($current_user_id, $search_term) {
    $db = getDB();
    
    // Get all communities the user is a member of
    $communities_sql = "SELECT community_id FROM community_members WHERE user_id = ? AND is_active = 1";
    $stmt = $db->prepare($communities_sql);
    $stmt->execute([$current_user_id]);
    $community_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($community_ids)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($community_ids) - 1) . '?';
    
    $sql = "SELECT DISTINCT
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                u.email,
                u.profile_photo,
                CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END AS is_blocked,
                GROUP_CONCAT(DISTINCT com.name ORDER BY com.name SEPARATOR ', ') AS communities
            FROM users u
            INNER JOIN community_members cm ON u.id = cm.user_id
            INNER JOIN communities com ON cm.community_id = com.id
            LEFT JOIN user_blocks ub ON (ub.blocker_user_id = ? AND ub.blocked_user_id = u.id)
            WHERE cm.community_id IN ($placeholders)
            AND cm.is_active = 1
            AND u.id != ?
            AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_photo, ub.id
            ORDER BY CONCAT(u.first_name, ' ', u.last_name) ASC
            LIMIT 10";
    
    $search_pattern = '%' . $search_term . '%';
    $params = array_merge([$current_user_id], $community_ids, [$current_user_id, $search_pattern, $search_pattern]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get user's online status
 */
function getUserOnlineStatus($user_id) {
    $db = getDB();
    
    $sql = "SELECT last_login FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    // Consider online if active within last 5 minutes
    $last_active = strtotime($user['last_login']);
    return (time() - $last_active) < 300;
}
?>