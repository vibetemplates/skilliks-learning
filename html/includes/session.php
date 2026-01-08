<?php
/**
 * Session Management
 * 
 * Handles session initialization and security
 */

// Security settings (must be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login - redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        exit;
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

// Get and clear flash message
function getFlashMessage($type) {
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

// Get current user's global role
function getCurrentUserGlobalRole() {
    if (!isLoggedIn()) {
        return 'guest';
    }
    
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT global_role FROM users WHERE id = ?");
        $stmt->execute([getCurrentUserId()]);
        $user = $stmt->fetch();
        return $user ? $user['global_role'] : 'user';
    } catch (Exception $e) {
        return 'user';
    }
}

// Get current user role (deprecated - for backwards compatibility)
function getCurrentUserRole() {
    // Check if global admin
    if (isCurrentUserAdmin()) {
        return 'admin';
    }
    
    // Check if has elevated role in any community
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT cm.role 
            FROM community_members cm 
            WHERE cm.user_id = ? 
            AND cm.role IN ('admin', 'moderator') 
            AND cm.is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([getCurrentUserId()]);
        $result = $stmt->fetch();
        
        if ($result && $result['role'] === 'admin') {
            return 'project_manager'; // Map community admin to project_manager for backwards compat
        }
        
        return 'member';
    } catch (Exception $e) {
        return 'member';
    }
}

// Check if current user is global admin
function isCurrentUserGlobalAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM global_admins WHERE user_id = ?");
        $stmt->execute([getCurrentUserId()]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check if current user is admin (alias for global admin)
function isCurrentUserAdmin() {
    return isCurrentUserGlobalAdmin();
}

// Check if current user is project manager or admin (checks community roles)
function isCurrentUserProjectManagerOrAdmin() {
    // Global admins always have elevated privileges
    if (isCurrentUserGlobalAdmin()) {
        return true;
    }
    
    // Check if has admin/moderator role in any community
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM community_members 
            WHERE user_id = ? 
            AND role IN ('admin', 'moderator', 'owner') 
            AND is_active = 1
        ");
        $stmt->execute([getCurrentUserId()]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Get current user's role in a specific community
function getCurrentUserCommunityRole($communityId) {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT role 
            FROM community_members 
            WHERE user_id = ? 
            AND community_id = ? 
            AND is_active = 1
        ");
        $stmt->execute([getCurrentUserId(), $communityId]);
        $result = $stmt->fetch();
        return $result ? $result['role'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// Check if current user can manage a specific community
function canManageCommunity($communityId) {
    // Global admins can manage any community
    if (isCurrentUserGlobalAdmin()) {
        return true;
    }
    
    // Check community-specific role
    $role = getCurrentUserCommunityRole($communityId);
    return in_array($role, ['admin', 'owner']);
}

// Get current user's default community
function getCurrentUserDefaultCommunity() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        require_once dirname(__FILE__) . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT default_community_id FROM users WHERE id = ?");
        $stmt->execute([getCurrentUserId()]);
        $user = $stmt->fetch();
        return $user ? $user['default_community_id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// Get current active community from session
function getCurrentCommunityId() {
    // Return from session if set
    if (isset($_SESSION['current_community_id'])) {
        return $_SESSION['current_community_id'];
    }
    
    // Otherwise return user's default community
    $defaultCommunity = getCurrentUserDefaultCommunity();
    if ($defaultCommunity) {
        $_SESSION['current_community_id'] = $defaultCommunity;
        return $defaultCommunity;
    }
    
    // If no default community, try to get user's first active community membership
    if (isLoggedIn()) {
        try {
            require_once dirname(__FILE__) . '/../config/database.php';
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT cm.community_id 
                FROM community_members cm
                JOIN communities c ON cm.community_id = c.id
                WHERE cm.user_id = ? AND cm.is_active = 1 AND c.is_active = 1
                ORDER BY cm.joined_at ASC
                LIMIT 1
            ");
            $stmt->execute([getCurrentUserId()]);
            $result = $stmt->fetch();
            
            if ($result) {
                $_SESSION['current_community_id'] = $result['community_id'];
                return $result['community_id'];
            }
        } catch (Exception $e) {
            error_log("Error getting user's first community: " . $e->getMessage());
        }
    }
    
    return null;
}

// Set current active community in session
function setCurrentCommunity($communityId) {
    $_SESSION['current_community_id'] = $communityId;
}