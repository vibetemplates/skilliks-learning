<?php
/**
 * Avatar Display Helper
 * Provides functions to safely display avatar images
 */

function getAvatarUrl($filename) {
    if (empty($filename)) {
        return null;
    }
    
    // Try direct URL first
    $direct_url = '/uploads/avatars/' . htmlspecialchars($filename);
    
    // If we're having issues with direct access, use the serve-avatar.php script
    // You can uncomment this line if direct access fails:
    // return '/serve-avatar.php?file=' . urlencode($filename);
    
    return $direct_url;
}

function displayAvatar($user, $size = 50, $classes = 'rounded-circle') {
    $avatarPath = dirname(dirname(__FILE__)) . '/uploads/avatars/' . ($user['profile_photo'] ?? '');
    
    if (!empty($user['profile_photo']) && file_exists($avatarPath)) {
        $avatarUrl = getAvatarUrl($user['profile_photo']);
        $alt = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        echo '<img src="' . $avatarUrl . '" alt="' . $alt . '" class="' . $classes . '" style="width: ' . $size . 'px; height: ' . $size . 'px; object-fit: cover;">';
    } else {
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        $fontSize = $size > 50 ? '2em' : '1.2em';
        echo '<div class="bg-primary text-white ' . $classes . ' d-flex align-items-center justify-content-center" style="width: ' . $size . 'px; height: ' . $size . 'px; font-size: ' . $fontSize . ';">' . $initials . '</div>';
    }
}
?>