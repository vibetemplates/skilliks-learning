<?php
/**
 * HTMX endpoint for toggling post likes
 * Returns only the updated like button HTML fragment
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../classes/BlogPost.php';

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo '<span class="text-danger">Please login to like posts</span>';
    exit;
}

$postId = $_POST['post_id'] ?? null;

if (!$postId) {
    http_response_code(400);
    echo '<span class="text-danger">Invalid request</span>';
    exit;
}

$userId = getCurrentUserId();
$blogPost = new BlogPost();

// Toggle the like
$isLiked = $blogPost->toggleLike($postId, $userId);

// Get updated like count
$likeCount = $blogPost->getLikeCount($postId);

// Return the updated button HTML
?>
<a href="#" class="text-decoration-none like-button <?php echo $isLiked ? 'liked' : 'text-muted'; ?>" 
   hx-post="/htmx/toggle-like.php" 
   hx-vals='{"post_id": "<?php echo $postId; ?>"}'
   hx-swap="outerHTML"
   onclick="event.preventDefault(); event.stopPropagation();">
    <i class="bi <?php echo $isLiked ? 'bi-heart-fill' : 'bi-heart'; ?> me-1"></i>
    <span class="like-count"><?php echo number_format($likeCount); ?></span>
</a>