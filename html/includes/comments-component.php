<?php
/**
 * Universal Comments Component
 * 
 * Usage: include this file and call renderComments($type, $id)
 * Example: renderComments('task', $taskId)
 */

require_once dirname(__DIR__) . '/classes/Comment.php';

function renderComments($commentableType, $commentableId, $projectId = null) {
    $commentObj = new Comment();
    $comments = $commentObj->getByEntity($commentableType, $commentableId);
    $commentCount = $commentObj->getCommentCount($commentableType, $commentableId);
    $currentUserId = $_SESSION['user_id'] ?? 0;
    ?>
    
    <div class="comments-section" data-type="<?php echo htmlspecialchars($commentableType); ?>" data-id="<?php echo $commentableId; ?>">
        <div class="comments-header d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-chat-dots"></i> Comments 
                <span class="badge bg-secondary"><?php echo $commentCount; ?></span>
            </h5>
        </div>
        
        <!-- Add Comment Form -->
        <?php if (isLoggedIn()): ?>
        <div class="add-comment-form mb-4">
            <form id="commentForm" onsubmit="return postComment(event)">
                <input type="hidden" name="commentable_type" value="<?php echo htmlspecialchars($commentableType); ?>">
                <input type="hidden" name="commentable_id" value="<?php echo $commentableId; ?>">
                <div class="input-group">
                    <textarea class="form-control" name="content" rows="2" 
                              placeholder="Add a comment... Use @username to mention someone" 
                              required></textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Post
                    </button>
                </div>
                <small class="text-muted mt-1 d-block">
                    <i class="bi bi-markdown"></i> Markdown supported. 
                    Press Ctrl+Enter to submit.
                </small>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Comments List -->
        <div class="comments-list" id="commentsList">
            <?php if (empty($comments)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-chat" style="font-size: 2rem;"></i>
                    <p class="mt-2">No comments yet. Be the first to comment!</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <?php renderComment($comment); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .comments-section {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 20px;
    }
    
    .comment-item {
        border-bottom: 1px solid var(--border-bg);
        padding: 15px 0;
        transition: all 0.3s ease;
    }
    
    .comment-item:last-child {
        border-bottom: none;
    }
    
    .comment-item:hover {
        background: var(--bg-shade1);
        margin: 0 -20px;
        padding: 15px 20px;
        border-radius: var(--border-radius-sm);
    }
    
    .comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }
    
    .comment-content {
        flex: 1;
    }
    
    .comment-header {
        margin-bottom: 8px;
    }
    
    .comment-author {
        font-weight: 600;
        color: var(--font-color);
        margin-right: 10px;
    }
    
    .comment-time {
        font-size: 12px;
        color: var(--font-color-light);
    }
    
    .comment-body {
        color: var(--font-color);
        line-height: 1.6;
        word-wrap: break-word;
    }
    
    .comment-body p {
        margin-bottom: 8px;
    }
    
    .comment-body p:last-child {
        margin-bottom: 0;
    }
    
    .comment-actions {
        margin-top: 10px;
        display: flex;
        gap: 15px;
    }
    
    .comment-action {
        font-size: 13px;
        color: var(--font-color-light);
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        background: none;
        padding: 0;
    }
    
    .comment-action:hover {
        color: var(--primary-color);
    }
    
    .comment-action.liked {
        color: var(--danger-color);
    }
    
    .comment-replies {
        margin-left: 55px;
        margin-top: 15px;
    }
    
    .reply-form {
        margin-left: 55px;
        margin-top: 15px;
        padding: 15px;
        background: var(--bg-shade1);
        border-radius: var(--border-radius-sm);
    }
    
    .mention {
        color: var(--primary-color);
        font-weight: 500;
        cursor: pointer;
    }
    
    .mention:hover {
        text-decoration: underline;
    }
    
    .comment-edited {
        font-size: 11px;
        color: var(--font-color-light);
        font-style: italic;
    }
    
    /* Reactions */
    .comment-reactions {
        display: flex;
        gap: 5px;
        margin-top: 8px;
    }
    
    .reaction-button {
        padding: 2px 8px;
        border: 1px solid var(--border-bg);
        border-radius: 15px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--white);
    }
    
    .reaction-button:hover {
        background: var(--bg-shade1);
        transform: scale(1.1);
    }
    
    .reaction-button.active {
        background: var(--primary-light);
        border-color: var(--primary-color);
    }
    
    /* Loading state */
    .comment-loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    /* Mention dropdown */
    .mention-dropdown {
        position: absolute;
        background: var(--white);
        border: 1px solid var(--border-bg);
        border-radius: var(--border-radius-sm);
        box-shadow: var(--box-shadow);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }
    
    .mention-dropdown.show {
        display: block;
    }
    
    .mention-item {
        padding: 8px 15px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .mention-item:hover {
        background: var(--primary-light);
    }
    
    .mention-item.selected {
        background: var(--primary-light);
    }
    </style>
    
    <script>
    // Comment functionality
    function postComment(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';
        submitBtn.disabled = true;
        
        fetch('/api/comment-create.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear form
                form.reset();
                
                // Reload comments
                loadComments();
                
                // Show success message
                showNotification('Comment posted successfully', 'success');
            } else {
                showNotification(data.error || 'Failed to post comment', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to post comment', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
        
        return false;
    }
    
    function loadComments() {
        const section = document.querySelector('.comments-section');
        const type = section.dataset.type;
        const id = section.dataset.id;
        
        fetch(`/api/comment-list.php?type=${type}&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const listContainer = document.getElementById('commentsList');
                    
                    if (data.comments.length === 0) {
                        listContainer.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-chat" style="font-size: 2rem;"></i>
                                <p class="mt-2">No comments yet. Be the first to comment!</p>
                            </div>
                        `;
                    } else {
                        listContainer.innerHTML = data.comments.map(comment => renderCommentHTML(comment)).join('');
                    }
                    
                    // Update count
                    document.querySelector('.comments-header .badge').textContent = data.count;
                }
            })
            .catch(error => {
                console.error('Error loading comments:', error);
            });
    }
    
    function renderCommentHTML(comment) {
        const isOwner = comment.user_id == <?php echo $currentUserId; ?>;
        const timeAgo = getTimeAgo(comment.created_at);
        
        let html = `
            <div class="comment-item" data-comment-id="${comment.id}">
                <div class="d-flex">
                    <div class="comment-avatar me-3">
                        ${comment.profile_photo ? 
                            `<img src="/uploads/avatars/${comment.profile_photo}" alt="${comment.author_name}" class="w-100 h-100 rounded-circle">` :
                            comment.author_initials
                        }
                    </div>
                    <div class="comment-content flex-grow-1">
                        <div class="comment-header">
                            <span class="comment-author">${comment.author_name}</span>
                            <span class="comment-time">${timeAgo}</span>
                            ${comment.edited ? '<span class="comment-edited">(edited)</span>' : ''}
                        </div>
                        <div class="comment-body">
                            ${processCommentContent(comment.content)}
                        </div>
                        <div class="comment-actions">
                            <button class="comment-action ${comment.is_liked ? 'liked' : ''}" onclick="toggleLike(${comment.id})">
                                <i class="bi bi-heart${comment.is_liked ? '-fill' : ''}"></i> 
                                <span class="like-count">${comment.like_count || 0}</span>
                            </button>
                            <button class="comment-action" onclick="showReplyForm(${comment.id})">
                                <i class="bi bi-reply"></i> Reply
                            </button>
                            ${isOwner ? `
                                <button class="comment-action" onclick="editComment(${comment.id})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="comment-action" onclick="deleteComment(${comment.id})">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
                ${comment.replies && comment.replies.length > 0 ? `
                    <div class="comment-replies">
                        ${comment.replies.map(reply => renderCommentHTML(reply)).join('')}
                    </div>
                ` : ''}
                <div id="replyForm${comment.id}" class="reply-form d-none"></div>
            </div>
        `;
        
        return html;
    }
    
    function processCommentContent(content) {
        // Convert mentions to links
        content = content.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
        
        // Basic markdown support
        // Bold
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic
        content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Links
        content = content.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Line breaks
        content = content.replace(/\n/g, '<br>');
        
        return content;
    }
    
    function toggleLike(commentId) {
        fetch('/api/comment-like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ comment_id: commentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const commentEl = document.querySelector(`[data-comment-id="${commentId}"]`);
                const likeBtn = commentEl.querySelector('.comment-action');
                const likeIcon = likeBtn.querySelector('i');
                const likeCount = likeBtn.querySelector('.like-count');
                
                if (data.action === 'liked') {
                    likeBtn.classList.add('liked');
                    likeIcon.className = 'bi bi-heart-fill';
                } else {
                    likeBtn.classList.remove('liked');
                    likeIcon.className = 'bi bi-heart';
                }
                
                likeCount.textContent = data.count;
            }
        })
        .catch(error => {
            console.error('Error toggling like:', error);
        });
    }
    
    function showReplyForm(commentId) {
        const replyFormDiv = document.getElementById(`replyForm${commentId}`);
        
        if (replyFormDiv.classList.contains('d-none')) {
            replyFormDiv.innerHTML = `
                <form onsubmit="return postReply(event, ${commentId})">
                    <div class="input-group">
                        <textarea class="form-control" name="content" rows="2" 
                                  placeholder="Write a reply..." required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send"></i> Reply
                        </button>
                    </div>
                </form>
            `;
            replyFormDiv.classList.remove('d-none');
            replyFormDiv.querySelector('textarea').focus();
        } else {
            replyFormDiv.classList.add('d-none');
            replyFormDiv.innerHTML = '';
        }
    }
    
    function postReply(event, parentId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const section = document.querySelector('.comments-section');
        
        formData.append('commentable_type', section.dataset.type);
        formData.append('commentable_id', section.dataset.id);
        formData.append('parent_comment_id', parentId);
        
        fetch('/api/comment-create.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
                showNotification('Reply posted successfully', 'success');
            } else {
                showNotification(data.error || 'Failed to post reply', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to post reply', 'error');
        });
        
        return false;
    }
    
    function editComment(commentId) {
        // Implementation for edit functionality
        const commentEl = document.querySelector(`[data-comment-id="${commentId}"]`);
        const bodyEl = commentEl.querySelector('.comment-body');
        const currentContent = bodyEl.textContent.trim();
        
        bodyEl.innerHTML = `
            <form onsubmit="return updateComment(event, ${commentId})">
                <div class="input-group">
                    <textarea class="form-control" name="content" rows="3" required>${currentContent}</textarea>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="loadComments()">
                        <i class="bi bi-x"></i> Cancel
                    </button>
                </div>
            </form>
        `;
        
        bodyEl.querySelector('textarea').focus();
    }
    
    function updateComment(event, commentId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('comment_id', commentId);
        
        fetch('/api/comment-update.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
                showNotification('Comment updated successfully', 'success');
            } else {
                showNotification(data.error || 'Failed to update comment', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to update comment', 'error');
        });
        
        return false;
    }
    
    function deleteComment(commentId) {
        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }
        
        fetch('/api/comment-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ comment_id: commentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadComments();
                showNotification('Comment deleted successfully', 'success');
            } else {
                showNotification(data.error || 'Failed to delete comment', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to delete comment', 'error');
        });
    }
    
    function getTimeAgo(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    }
    
    // Keyboard shortcuts
    document.addEventListener('DOMContentLoaded', function() {
        const commentTextarea = document.querySelector('textarea[name="content"]');
        if (commentTextarea) {
            commentTextarea.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    this.form.dispatchEvent(new Event('submit'));
                }
            });
        }
    });
    
    // Auto-refresh comments every 30 seconds
    setInterval(loadComments, 30000);
    </script>
    <?php
}

function renderComment($comment) {
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $isOwner = $comment['user_id'] == $currentUserId;
    ?>
    <div class="comment-item" data-comment-id="<?php echo $comment['id']; ?>">
        <div class="d-flex">
            <div class="comment-avatar me-3">
                <?php if (!empty($comment['profile_photo'])): ?>
                    <img src="/uploads/avatars/<?php echo htmlspecialchars($comment['profile_photo']); ?>" 
                         alt="<?php echo htmlspecialchars($comment['author_name']); ?>" 
                         class="w-100 h-100 rounded-circle">
                <?php else: ?>
                    <?php echo htmlspecialchars($comment['author_initials']); ?>
                <?php endif; ?>
            </div>
            <div class="comment-content flex-grow-1">
                <div class="comment-header">
                    <span class="comment-author"><?php echo htmlspecialchars($comment['author_name']); ?></span>
                    <span class="comment-time"><?php echo timeAgo($comment['created_at']); ?></span>
                    <?php if ($comment['edited']): ?>
                        <span class="comment-edited">(edited)</span>
                    <?php endif; ?>
                </div>
                <div class="comment-body">
                    <?php echo processCommentContent($comment['content']); ?>
                </div>
                <div class="comment-actions">
                    <button class="comment-action <?php echo $comment['is_liked'] ? 'liked' : ''; ?>" 
                            onclick="toggleLike(<?php echo $comment['id']; ?>)">
                        <i class="bi bi-heart<?php echo $comment['is_liked'] ? '-fill' : ''; ?>"></i> 
                        <span class="like-count"><?php echo $comment['like_count'] ?: 0; ?></span>
                    </button>
                    <button class="comment-action" onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                        <i class="bi bi-reply"></i> Reply
                    </button>
                    <?php if ($isOwner): ?>
                        <button class="comment-action" onclick="editComment(<?php echo $comment['id']; ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="comment-action" onclick="deleteComment(<?php echo $comment['id']; ?>)">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($comment['replies'])): ?>
            <div class="comment-replies">
                <?php foreach ($comment['replies'] as $reply): ?>
                    <?php renderComment($reply); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div id="replyForm<?php echo $comment['id']; ?>" class="reply-form d-none"></div>
    </div>
    <?php
}

function processCommentContent($content) {
    // Escape HTML first
    $content = htmlspecialchars($content);
    
    // Convert mentions to spans
    $content = preg_replace('/@(\w+)/', '<span class="mention">@$1</span>', $content);
    
    // Basic markdown support
    // Bold
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
    
    // Italic
    $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
    
    // Links
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $content);
    
    // Line breaks
    $content = nl2br($content);
    
    return $content;
}
?>