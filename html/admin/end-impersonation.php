<?php
/**
 * End Impersonation
 * 
 * Allows admins to return to their original account after impersonating
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is impersonating
if (empty($_SESSION['is_impersonating']) || $_SESSION['is_impersonating'] !== true) {
    setFlashMessage('warning', 'You are not currently impersonating another user.');
    redirect('/dashboard');
}

// Get original admin ID
$originalAdminId = $_SESSION['original_admin_id'] ?? 0;

if (!$originalAdminId) {
    setFlashMessage('danger', 'Could not restore original session. Please login again.');
    session_destroy();
    redirect('/login.php');
}

try {
    $db = getDB();
    
    // Get original admin info
    $stmt = $db->prepare("SELECT id, email, first_name, last_name, default_community_id FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $originalAdminId]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        setFlashMessage('danger', 'Original admin account not found. Please login again.');
        session_destroy();
        redirect('/login.php');
    }
    
    // Log the end of impersonation
    $currentUserId = $_SESSION['user_id'];
    $stmt = $db->prepare("INSERT INTO admin_actions (admin_id, action_type, target_user_id, details, ip_address, user_agent, created_at) 
                          VALUES (:admin_id, 'end_impersonate', :target_user_id, :details, :ip_address, :user_agent, NOW())");
    $stmt->execute([
        ':admin_id' => $originalAdminId,
        ':target_user_id' => $currentUserId,
        ':details' => json_encode([
            'impersonated_user_id' => $currentUserId,
            'impersonated_user_email' => $_SESSION['user_email']
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Clear current session
    session_destroy();
    session_start();
    
    // Restore admin session
    $_SESSION['user_id'] = $adminUser['id'];
    $_SESSION['user_email'] = $adminUser['email'];
    $_SESSION['user_name'] = $adminUser['first_name'] . ' ' . $adminUser['last_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['is_impersonating'] = false;
    
    // Set community if admin has one
    if ($adminUser['default_community_id']) {
        $_SESSION['current_community_id'] = $adminUser['default_community_id'];
    }
    
    // Update last login for admin
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :user_id");
    $stmt->execute([':user_id' => $adminUser['id']]);
    
    setFlashMessage('success', 'You have returned to your admin account.');
    redirect('/admin/users.php');
    
} catch (PDOException $e) {
    error_log("Error ending impersonation: " . $e->getMessage());
    setFlashMessage('danger', 'An error occurred while ending impersonation. Please login again.');
    session_destroy();
    redirect('/login.php');
}