<?php
/**
 * Initialize Default Blog Categories
 * 
 * Creates default categories for communities that don't have any
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'classes/BlogCategory.php';

// Require login
requireLogin();

$currentCommunityId = getCurrentCommunityId();
$blogCategory = new BlogCategory();

// Check if community has categories
$categories = $blogCategory->getByCommunity($currentCommunityId);

if (empty($categories)) {
    // Create default categories
    $blogCategory->createDefaults($currentCommunityId);
    echo "Default categories created successfully!";
} else {
    echo "Categories already exist for this community.";
}

// Redirect back to blog
header('Location: blog.php');
exit;