<?php
/**
 * Admin - Impersonate User
 * 
 * Allows global administrators to switch to another user's account for debugging
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Require login first
requireLogin();

$currentUserId = getCurrentUserId();

// Check if user is global admin
if (!isCurrentUserGlobalAdmin()) {
    setFlashMessage('danger', 'You do not have permission to impersonate users.');
    redirect('/dashboard');
}

// Get user_id from request
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$targetUserId) {
    setFlashMessage('danger', 'Invalid user ID.');
    redirect('/admin/users.php');
}

// Prevent self-impersonation
if ($targetUserId === $currentUserId) {
    setFlashMessage('danger', 'You cannot impersonate yourself.');
    redirect('/admin/users.php');
}

try {
    $db = getDB();
    
    // Get target user info including default community
    $stmt = $db->prepare("SELECT id, email, first_name, last_name, default_community_id FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        setFlashMessage('danger', 'User not found.');
        redirect('/admin/users.php');
    }
    
    // Remove is_active check since column doesn't exist
    // Could add this back if we add an is_active column to users table
    
    // Log the impersonation for audit trail
    $stmt = $db->prepare("INSERT INTO admin_actions (admin_id, action_type, target_user_id, details, ip_address, user_agent, created_at) 
                          VALUES (:admin_id, 'impersonate', :target_user_id, :details, :ip_address, :user_agent, NOW())");
    $stmt->execute([
        ':admin_id' => $currentUserId,
        ':target_user_id' => $targetUserId,
        ':details' => json_encode([
            'admin_email' => $_SESSION['user_email'],
            'target_email' => $targetUser['email'],
            'timestamp' => date('Y-m-d H:i:s')
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Store original admin ID before destroying session
    $originalAdminId = $currentUserId;
    
    // Clear current session
    session_destroy();
    session_start();
    
    // Set new session variables for target user
    $_SESSION['user_id'] = $targetUser['id'];
    $_SESSION['user_email'] = $targetUser['email'];
    $_SESSION['user_name'] = $targetUser['first_name'] . ' ' . $targetUser['last_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['is_impersonating'] = true;
    $_SESSION['original_admin_id'] = $originalAdminId;
    
    // Set community if user has one
    if ($targetUser['default_community_id']) {
        $_SESSION['current_community_id'] = $targetUser['default_community_id'];
    }
    
    // Update last login for target user
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :user_id");
    $stmt->execute([':user_id' => $targetUser['id']]);
    
    // Set flash message for the new session
    setFlashMessage('warning', 'You are now impersonating ' . htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']) . '. To return to your account, please logout.');
    
    // Debug log
    error_log("Impersonation successful - redirecting to dashboard.php for user ID: " . $targetUser['id']);
    
    // Make sure no output has been sent
    if (headers_sent($filename, $linenum)) {
        error_log("Headers already sent in $filename on line $linenum");
        echo "Headers already sent. Click <a href='/dashboard.php'>here</a> to continue.";
        exit();
    }
    
    // Redirect to dashboard
    header('Location: /dashboard.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Impersonation error: " . $e->getMessage());
    setFlashMessage('danger', 'An error occurred while impersonating the user.');
    redirect('/admin/users.php');
}