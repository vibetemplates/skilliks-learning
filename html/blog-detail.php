<?php
/**
 * Blog Post Detail Page
 * 
 * Displays a single blog post with comments
 */

$page_title = 'Blog Post';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/BlogPost.php';
require_once 'classes/BlogCategory.php';
require_once 'classes/Comment.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Get post ID from query string
$postId = $_GET['id'] ?? 0;
$postSlug = $_GET['slug'] ?? '';

// Initialize classes
$blogPost = new BlogPost();
$blogCategory = new BlogCategory();
$comment = new Comment();
$community = new Community();

// Get the post
if ($postId) {
    $post = $blogPost->getById($postId, true); // true to increment view count
} elseif ($postSlug) {
    $post = $blogPost->getBySlug($postSlug, $currentCommunityId, true);
} else {
    header('Location: dashboard');
    exit;
}

// Check if post exists and belongs to current community
if (!$post || $post['community_id'] != $currentCommunityId) {
    header('Location: dashboard');
    exit;
}

// Update page title
$page_title = $post['title'];

// Check if user can edit this post
$canEdit = $blogPost->canEdit($post['id'], $currentUserId);

// Check if user has liked this post
$hasLiked = $blogPost->hasLiked($post['id'], $currentUserId);

// Get post categories
$postCategories = $blogPost->getCategories($post['id']);

// Get comments
$comments = $comment->getByEntity('blog_post', $post['id']);

// Get related posts
$relatedPosts = $blogPost->getList([
    'community_id' => $currentCommunityId,
    'status' => 'published',
    'limit' => 3,
    'exclude_id' => $post['id']
]);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="row mt-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard">Community Posts</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($post['title']); ?></li>
                </ol>
            </nav>

            <!-- Post Content -->
            <article class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Post Header -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="d-flex align-items-center">
                            <?php if ($post['author_avatar']): ?>
                            <img src="serve-avatar.php?user_id=<?php echo $post['author_id']; ?>" 
                                 class="rounded-circle me-3" width="60" height="60"
                                 alt="<?php echo htmlspecialchars($post['author_name']); ?>">
                            <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                 style="width: 60px; height: 60px; font-size: 24px;">
                                <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($post['author_name']); ?></h5>
                                <small class="text-muted">
                                    Published <?php echo timeAgo($post['published_at']); ?>
                                    <?php if ($post['updated_at'] > $post['published_at']): ?>
                                    · Updated <?php echo timeAgo($post['updated_at']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="blog-edit.php?id=<?php echo $post['id']; ?>">
                                    <i class="bi bi-pencil me-2"></i>Edit Post
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="deletePost(<?php echo $post['id']; ?>)">
                                    <i class="bi bi-trash me-2"></i>Delete Post
                                </a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Post Title -->
                    <h1 class="h2 mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>

                    <!-- Categories -->
                    <?php if (!empty($postCategories)): ?>
                    <div class="mb-3">
                        <?php foreach ($postCategories as $category): ?>
                        <a href="dashboard?category=<?php echo $category['id']; ?>" 
                           class="badge text-decoration-none me-1" 
                           style="background-color: <?php echo htmlspecialchars($category['color']); ?>; color: white;">
                            <?php if ($category['icon']): ?>
                            <i class="<?php echo htmlspecialchars($category['icon']); ?> me-1"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Featured Image -->
                    <?php if ($post['featured_image']): ?>
                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                         class="img-fluid rounded mb-4" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    <?php endif; ?>

                    <!-- Video Embed -->
                    <?php if ($post['video_url'] || $post['video_embed_code']): ?>
                    <div class="ratio ratio-16x9 mb-4">
                        <?php if ($post['video_embed_code']): ?>
                            <?php echo $post['video_embed_code']; ?>
                        <?php elseif ($post['video_url']): ?>
                            <?php
                            // Check if it's a YouTube URL and convert to embed
                            $video_url = $post['video_url'];
                            $youtube_id = null;
                            
                            // Check for various YouTube URL formats
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches)) {
                                $youtube_id = $matches[1];
                            }
                            
                            if ($youtube_id): ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                </iframe>
                            <?php else: ?>
                                <video controls class="w-100">
                                    <source src="<?php echo htmlspecialchars($post['video_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Post Content -->
                    <div class="post-content mb-4">
                        <?php echo nl2br($post['content']); ?>
                    </div>

                    <!-- Tags -->
                    <?php if ($post['tags']): ?>
                    <div class="mb-4">
                        <?php 
                        $tags = explode(',', $post['tags']);
                        foreach ($tags as $tag): 
                        ?>
                        <span class="badge bg-secondary me-1">#<?php echo htmlspecialchars(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Post Actions -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <div class="d-flex gap-3">
                            <button class="btn btn-sm <?php echo $hasLiked ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                                    onclick="toggleLike(<?php echo $post['id']; ?>)" id="likeBtn">
                                <i class="bi <?php echo $hasLiked ? 'bi-heart-fill' : 'bi-heart'; ?> me-1"></i>
                                <span id="likeCount"><?php echo number_format($post['like_count']); ?></span>
                            </button>
                            <span class="text-muted">
                                <i class="bi bi-eye me-1"></i><?php echo number_format($post['view_count']); ?> views
                            </span>
                            <span class="text-muted">
                                <i class="bi bi-chat me-1"></i><?php echo number_format(count($comments)); ?> comments
                            </span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="sharePost()">
                                <i class="bi bi-share me-1"></i>Share
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Comments Section -->
            <?php if ($post['allow_comments']): ?>
                <?php 
                include 'includes/comments-component.php';
                renderComments('blog_post', $post['id']); 
                ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Author Info -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h5 class="card-title">About the Author</h5>
                    <?php if ($post['author_avatar']): ?>
                    <img src="serve-avatar.php?user_id=<?php echo $post['author_id']; ?>" 
                         class="rounded-circle mb-3" width="80" height="80"
                         alt="<?php echo htmlspecialchars($post['author_name']); ?>">
                    <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3 mx-auto" 
                         style="width: 80px; height: 80px; font-size: 32px;">
                        <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                    <h6><?php echo htmlspecialchars($post['author_name']); ?></h6>
                    <a href="team-member?id=<?php echo $post['author_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                        View Profile
                    </a>
                </div>
            </div>

            <!-- Related Posts -->
            <?php if (!empty($relatedPosts)): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Related Posts</h5>
                    <div class="list-group list-group-flush">
                        <?php foreach ($relatedPosts as $related): ?>
                        <a href="blog-detail.php?id=<?php echo $related['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <h6 class="mb-1"><?php echo htmlspecialchars($related['title']); ?></h6>
                            <small class="text-muted">
                                By <?php echo htmlspecialchars($related['author_name']); ?> · 
                                <?php echo timeAgo($related['published_at']); ?>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Show notification function
function showNotification(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Toggle like functionality
function toggleLike(postId) {
    fetch('api/blog-like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const likeBtn = document.getElementById('likeBtn');
            const likeCount = document.getElementById('likeCount');
            const icon = likeBtn.querySelector('i');
            
            if (data.liked) {
                likeBtn.classList.remove('btn-outline-danger');
                likeBtn.classList.add('btn-danger');
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
            } else {
                likeBtn.classList.remove('btn-danger');
                likeBtn.classList.add('btn-outline-danger');
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
            }
            
            likeCount.textContent = data.like_count.toLocaleString();
        }
    })
    .catch(error => console.error('Error:', error));
}


// Delete post functionality
function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        fetch('api/blog-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                post_id: postId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'dashboard';
            } else {
                alert(data.message || 'Failed to delete post');
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

// Share post functionality
function sharePost() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($post['title']); ?>',
            url: url
        });
    } else {
        // Fallback - copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>