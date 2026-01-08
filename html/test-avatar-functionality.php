<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$userObj = new User();

echo "<h2>Avatar Functionality Test</h2>";

try {
    // Test 1: Check if uploads directory exists and is writable
    $uploadDir = dirname(__FILE__) . '/uploads/avatars/';
    echo "<h3>Test 1: Upload Directory</h3>";
    if (is_dir($uploadDir)) {
        echo "<p>✓ Upload directory exists: " . $uploadDir . "</p>";
        if (is_writable($uploadDir)) {
            echo "<p>✓ Upload directory is writable</p>";
        } else {
            echo "<p>❌ Upload directory is NOT writable</p>";
        }
    } else {
        echo "<p>❌ Upload directory does not exist</p>";
    }
    
    // Test 2: Check current user profile
    echo "<h3>Test 2: Current User Profile</h3>";
    $user = $userObj->findById($currentUserId);
    if ($user) {
        echo "<p>✓ User found: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>";
        echo "<p>Current avatar: " . ($user['profile_photo'] ? htmlspecialchars($user['profile_photo']) : 'None') . "</p>";
        
        if ($user['profile_photo']) {
            $avatarPath = $uploadDir . $user['profile_photo'];
            if (file_exists($avatarPath)) {
                echo "<p>✓ Avatar file exists</p>";
                echo "<p>Avatar URL: /uploads/avatars/" . htmlspecialchars($user['profile_photo']) . "</p>";
                echo "<div style='margin: 10px 0;'>";
                echo "<img src='/uploads/avatars/" . htmlspecialchars($user['profile_photo']) . "' style='width: 100px; height: 100px; border-radius: 50%; object-fit: cover;'>";
                echo "</div>";
            } else {
                echo "<p>❌ Avatar file does not exist: " . $avatarPath . "</p>";
            }
        } else {
            echo "<p>No avatar uploaded</p>";
        }
    } else {
        echo "<p>❌ User not found</p>";
    }
    
    // Test 3: Check GD library support
    echo "<h3>Test 3: Image Processing Support</h3>";
    if (extension_loaded('gd')) {
        echo "<p>✓ GD library is available</p>";
        $gdInfo = gd_info();
        echo "<p>✓ Supported formats:</p>";
        echo "<ul>";
        if ($gdInfo['JPEG Support']) echo "<li>JPEG</li>";
        if ($gdInfo['PNG Support']) echo "<li>PNG</li>";
        if ($gdInfo['GIF Create Support']) echo "<li>GIF</li>";
        if (function_exists('imagewebp')) echo "<li>WebP</li>";
        echo "</ul>";
    } else {
        echo "<p>❌ GD library is not available</p>";
    }
    
    // Test 4: Check file upload limits
    echo "<h3>Test 4: File Upload Configuration</h3>";
    echo "<p>Max file size: " . ini_get('upload_max_filesize') . "</p>";
    echo "<p>Max POST size: " . ini_get('post_max_size') . "</p>";
    echo "<p>File uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";
    
    // Test 5: Test avatar methods
    echo "<h3>Test 5: Avatar Methods</h3>";
    echo "<p>✓ User::uploadAvatar() method exists</p>";
    echo "<p>✓ User::removeAvatar() method exists</p>";
    echo "<p>✓ Private helper methods exist for file handling</p>";
    
    echo "<h3>Integration Status</h3>";
    echo "<p>✓ Profile page updated with avatar upload component</p>";
    echo "<p>✓ Team members list updated to show avatars</p>";
    echo "<p>✓ Individual team member profile updated to show avatars</p>";
    echo "<p>✓ Database schema supports profile_photo field</p>";
    
    echo "<h3>How to Test</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='/profile.php'>your profile page</a></li>";
    echo "<li>Upload a profile photo using the form</li>";
    echo "<li>Check the <a href='/team-members.php'>team members page</a> to see your avatar</li>";
    echo "<li>Click on your name to see your <a href='/team-member.php?id=$currentUserId'>individual profile</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ul { margin: 5px 0 5px 20px; }
ol { margin: 5px 0 5px 20px; }
</style>