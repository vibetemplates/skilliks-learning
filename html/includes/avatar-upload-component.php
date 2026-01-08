<?php
/**
 * Avatar Upload Component
 * 
 * Provides a reusable avatar upload interface
 */

// Get current user's avatar if available
$currentUser = $userObj->findById($currentUserId);
$currentAvatar = $currentUser['profile_photo'] ?? null;
?>

<div class="avatar-upload-section">
    <div class="row align-items-center">
        <div class="col-md-3">
            <div class="current-avatar-display">
                <?php 
                $avatarPath = dirname(dirname(__FILE__)) . '/uploads/avatars/' . $currentAvatar;
                if (!empty($currentAvatar) && file_exists($avatarPath)): 
                ?>
                    <img src="/serve-avatar.php?file=<?php echo urlencode($currentAvatar); ?>" 
                         alt="Current avatar" 
                         class="avatar-preview rounded-circle" 
                         style="width: 100px; height: 100px; object-fit: cover;"
                         onerror="this.onerror=null; this.src='/uploads/avatars/<?php echo htmlspecialchars($currentAvatar); ?>';">
                <?php else: ?>
                    <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 100px; height: 100px; font-size: 2em;">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-9">
            <div class="avatar-upload-form">
                <h6>Profile Photo</h6>
                <p class="text-muted small">Upload a profile photo to help teammates recognize you. Recommended size: 200x200 pixels.</p>
                
                <form method="POST" action="/profile.php" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="input-group">
                        <input type="file" 
                               class="form-control" 
                               id="avatar_upload" 
                               name="avatar_upload" 
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               required>
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    </div>
                    <div class="form-text">
                        Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB.
                    </div>
                </form>
                
                <?php if (!empty($currentAvatar)): ?>
                    <form method="POST" action="/profile.php" style="display: inline;">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                onclick="return confirm('Are you sure you want to remove your profile photo?');">
                            <i class="bi bi-trash"></i> Remove Photo
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-upload-section {
    padding: 20px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background-color: #f8f9fa;
    margin-bottom: 20px;
}

.avatar-placeholder {
    border: 2px dashed #dee2e6;
}

.avatar-preview {
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.current-avatar-display {
    text-align: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('avatar_upload');
    const avatarPreview = document.querySelector('.avatar-preview, .avatar-placeholder');
    
    if (fileInput && avatarPreview) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    this.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (avatarPreview.tagName === 'IMG') {
                        avatarPreview.src = e.target.result;
                    } else {
                        // Replace placeholder with image
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Avatar preview';
                        img.className = 'avatar-preview rounded-circle';
                        img.style.cssText = 'width: 100px; height: 100px; object-fit: cover;';
                        avatarPreview.parentNode.replaceChild(img, avatarPreview);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>