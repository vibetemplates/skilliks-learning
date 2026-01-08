<?php
/**
 * Blog Post Create/Edit Page
 * 
 * Allows creating and editing blog posts
 */

$page_title = 'Create Community Post';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/BlogPost.php';
require_once 'classes/BlogCategory.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$blogPost = new BlogPost();
$blogCategory = new BlogCategory();
$community = new Community();

// Check if user is admin
$currentCommunity = $community->getById($currentCommunityId);
$userRole = $community->isMember($currentCommunityId, $currentUserId);
if ($userRole !== 'admin') {
    header('Location: dashboard');
    exit;
}

// Check if editing existing post
$postId = $_GET['id'] ?? null;
$post = null;
$postCategories = [];

if ($postId) {
    $post = $blogPost->getById($postId);
    if (!$post || !$blogPost->canEdit($postId, $currentUserId)) {
        header('Location: dashboard');
        exit;
    }
    $page_title = 'Edit Community Post';
    $postCategories = array_column($blogPost->getCategories($postId), 'id');
}

// Get all categories
$categories = $blogCategory->getByCommunity($currentCommunityId);

// Create default categories if none exist
if (empty($categories)) {
    $blogCategory->createDefaults($currentCommunityId);
    $categories = $blogCategory->getByCommunity($currentCommunityId);
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="row mt-4">
        <div class="col-lg-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><?php echo $post ? 'Edit' : 'Create'; ?> Blog Post</h1>
                <a href="dashboard" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Blog
                </a>
            </div>

            <!-- Post Form -->
            <form id="postForm" method="post" enctype="multipart/form-data" onsubmit="savePost(event)">
                <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                <!-- Hidden fields for defaults -->
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($post['status'] ?? 'published'); ?>">
                <input type="hidden" name="visibility" value="<?php echo htmlspecialchars($post['visibility'] ?? 'community'); ?>">
                <input type="hidden" name="allow_comments" value="1">
                <input type="hidden" name="slug" id="slug" value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>">
                <input type="hidden" name="excerpt" value="">
                <input type="hidden" name="tags" value="">
                
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Post Details</h5>
                                
                                <!-- Title -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" required>
                                </div>

                                <!-- Category Dropdown -->
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="categories[]">
                                        <option value="">Select a category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo in_array($category['id'], $postCategories) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>


                                <!-- Content -->
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
                                </div>


                                <!-- Featured and Pinned Options -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_featured" 
                                                       name="is_featured" value="1" 
                                                       <?php echo ($post['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_featured">
                                                    <i class="bi bi-star me-1"></i>Feature this post
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_pinned" 
                                                       name="is_pinned" value="1" 
                                                       <?php echo ($post['is_pinned'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_pinned">
                                                    <i class="bi bi-pin me-1"></i>Pin to top of blog
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" name="action" value="save_draft" class="btn btn-secondary">
                                        <i class="bi bi-save me-2"></i>Save Draft
                                    </button>
                                    <button type="submit" name="action" value="publish" class="btn btn-primary">
                                        <i class="bi bi-send me-2"></i><?php echo $post ? 'Update' : 'Publish'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Media -->
                    <div class="col-md-6">
                        <!-- Preview Section -->
                        <div class="card shadow-sm mb-4" id="mediaPreview" style="display: none;">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Preview</h5>
                                <div id="previewContent">
                                    <!-- Dynamic preview content will be inserted here -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Media</h5>

                                <!-- Featured Image -->
                                <div class="mb-3">
                                    <label for="featured_image" class="form-label">Featured Image</label>
                                    <input type="file" class="form-control" id="featured_image" name="featured_image" 
                                           accept="image/*" onchange="previewImage(this)">
                                    <?php if ($post && $post['featured_image']): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                             class="img-thumbnail" style="max-width: 200px;" id="imagePreview">
                                        <input type="hidden" name="existing_featured_image" 
                                               value="<?php echo htmlspecialchars($post['featured_image']); ?>">
                                    </div>
                                    <?php else: ?>
                                    <div class="mt-2">
                                        <img src="" class="img-thumbnail d-none" style="max-width: 200px;" id="imagePreview">
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Video -->
                                <div class="mb-3">
                                    <label for="video_url" class="form-label">Video URL</label>
                                    <input type="url" class="form-control" id="video_url" name="video_url" 
                                           value="<?php echo htmlspecialchars($post['video_url'] ?? ''); ?>"
                                           placeholder="https://youtube.com/watch?v=... or https://example.com/video.mp4">
                                    <small class="form-text text-muted">YouTube, Vimeo URLs, or direct video file links</small>
                                </div>

                                <!-- Video Embed Code -->
                                <div class="mb-3">
                                    <label for="video_embed_code" class="form-label">Video Embed Code</label>
                                    <textarea class="form-control" id="video_embed_code" name="video_embed_code" rows="3" 
                                              placeholder="YouTube, Vimeo, or other embed code"><?php echo htmlspecialchars($post['video_embed_code'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
// Show media preview
function showMediaPreview(content) {
    const previewSection = document.getElementById('mediaPreview');
    const previewContent = document.getElementById('previewContent');
    
    if (content) {
        previewContent.innerHTML = content;
        previewSection.style.display = 'block';
    } else {
        previewSection.style.display = 'none';
    }
}

// Preview image on selection
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            
            // Update media preview
            showMediaPreview(`<img src="${e.target.result}" class="img-fluid rounded" alt="Featured image preview">`);
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        // Check if there's a video to show instead
        updateVideoPreview();
    }
}

// Update video preview
function updateVideoPreview() {
    const videoUrl = document.getElementById('video_url').value;
    const embedCode = document.getElementById('video_embed_code').value;
    
    if (embedCode) {
        // Show embed code preview
        showMediaPreview(`<div class="ratio ratio-16x9">${embedCode}</div>`);
    } else if (videoUrl) {
        // Check if it's a YouTube URL
        const youtubeMatch = videoUrl.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
        if (youtubeMatch) {
            const videoId = youtubeMatch[1];
            showMediaPreview(`
                <div class="ratio ratio-16x9">
                    <iframe src="https://www.youtube.com/embed/${videoId}" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen></iframe>
                </div>
            `);
        } else if (videoUrl.match(/vimeo\.com\/(\d+)/)) {
            // Vimeo URL
            const vimeoMatch = videoUrl.match(/vimeo\.com\/(\d+)/);
            const videoId = vimeoMatch[1];
            showMediaPreview(`
                <div class="ratio ratio-16x9">
                    <iframe src="https://player.vimeo.com/video/${videoId}" 
                            frameborder="0" 
                            allow="autoplay; fullscreen; picture-in-picture" 
                            allowfullscreen></iframe>
                </div>
            `);
        } else {
            // Generic video URL
            showMediaPreview(`
                <video controls class="w-100 rounded">
                    <source src="${videoUrl}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `);
        }
    } else {
        // No video, check if there's an existing featured image
        const existingImage = document.getElementById('imagePreview');
        if (existingImage && !existingImage.classList.contains('d-none') && existingImage.src) {
            showMediaPreview(`<img src="${existingImage.src}" class="img-fluid rounded" alt="Featured image preview">`);
        } else {
            showMediaPreview('');
        }
    }
}

// Auto-generate slug from title
document.getElementById('title').addEventListener('blur', function() {
    const slug = document.getElementById('slug');
    if (!slug.value) {
        slug.value = this.value.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .trim();
    }
});

// Add video URL blur event
document.getElementById('video_url').addEventListener('blur', updateVideoPreview);

// Add video embed code input event
document.getElementById('video_embed_code').addEventListener('input', updateVideoPreview);

// Initialize preview on page load if editing
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($post && $post['featured_image']): ?>
    showMediaPreview(`<img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="img-fluid rounded" alt="Featured image preview">`);
    <?php else: ?>
    updateVideoPreview();
    <?php endif; ?>
});

// Save post
function savePost(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Get the action from the clicked button
    const action = event.submitter.value;
    formData.append('action', action);
    
    // If publishing, set status to published
    if (action === 'publish') {
        formData.set('status', 'published');
    }
    
    // Show loading state
    event.submitter.disabled = true;
    event.submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    fetch('api/blog-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'blog-detail.php?id=' + data.post_id;
        } else {
            alert(data.message || 'Failed to save post');
            event.submitter.disabled = false;
            event.submitter.innerHTML = event.submitter.value === 'save_draft' ? 
                '<i class="bi bi-save me-2"></i>Save Draft' : 
                '<i class="bi bi-send me-2"></i><?php echo $post ? 'Update' : 'Publish'; ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the post');
        event.submitter.disabled = false;
        event.submitter.innerHTML = event.submitter.value === 'save_draft' ? 
            '<i class="bi bi-save me-2"></i>Save Draft' : 
            '<i class="bi bi-send me-2"></i><?php echo $post ? 'Update' : 'Publish'; ?>';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>