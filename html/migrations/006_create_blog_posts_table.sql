-- Blog Posts Feature Database Schema
-- This script adds blog functionality to the project tracking tool

-- =============================================
-- BLOG POSTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `community_id` int unsigned NOT NULL,
  `author_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext NOT NULL,
  `featured_image` varchar(500),
  `video_url` varchar(500),
  `video_embed_code` text,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `visibility` enum('public', 'community', 'private') DEFAULT 'community',
  `allow_comments` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `tags` text,
  `meta_description` text,
  `published_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug_community` (`slug`, `community_id`),
  KEY `idx_community_id` (`community_id`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_status` (`status`),
  KEY `idx_published_at` (`published_at`),
  KEY `idx_is_featured` (`is_featured`),
  KEY `idx_is_pinned` (`is_pinned`),
  FULLTEXT KEY `ft_title_content` (`title`, `content`),
  FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BLOG POST LIKES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_post_like` (`user_id`, `post_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BLOG POST VIEWS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_post_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int unsigned NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_viewed_at` (`viewed_at`),
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BLOG POST ATTACHMENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_post_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100),
  `file_size` int(11),
  `caption` text,
  `display_order` int(11) DEFAULT 0,
  `uploaded_by` int unsigned NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_display_order` (`display_order`),
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- UPDATE EXISTING COMMENTS TABLE
-- =============================================
-- Add new columns to existing comments table if they don't exist
ALTER TABLE `comments` 
  MODIFY COLUMN `commentable_type` varchar(50) NOT NULL,
  ADD COLUMN IF NOT EXISTS `community_id` int unsigned NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `status` enum('active', 'hidden', 'deleted', 'pending') DEFAULT 'active' AFTER `content`,
  ADD COLUMN IF NOT EXISTS `is_pinned` tinyint(1) DEFAULT 0 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `like_count` int(11) DEFAULT 0 AFTER `is_pinned`,
  ADD COLUMN IF NOT EXISTS `reply_count` int(11) DEFAULT 0 AFTER `like_count`;

-- Add foreign key for community_id if it doesn't exist
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_community_id` FOREIGN KEY IF NOT EXISTS (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_comments_community_id` ON `comments` (`community_id`);
CREATE INDEX IF NOT EXISTS `idx_comments_status` ON `comments` (`status`);

-- Note: comment_likes table already exists, so we'll use it as is

-- =============================================
-- BLOG POST CATEGORIES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `community_id` int unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `color` varchar(7) DEFAULT '#6c757d',
  `icon` varchar(50),
  `parent_id` int(11) NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug_community` (`slug`, `community_id`),
  KEY `idx_community_id` (`community_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_is_active` (`is_active`),
  FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `blog_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BLOG POST CATEGORIES RELATIONSHIP TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `blog_post_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_category` (`post_id`, `category_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_category_id` (`category_id`),
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CREATE TRIGGERS
-- =============================================
DELIMITER //

-- Update blog post like count
CREATE TRIGGER update_blog_post_likes_count_insert
AFTER INSERT ON blog_post_likes
FOR EACH ROW
BEGIN
    UPDATE blog_posts 
    SET like_count = like_count + 1 
    WHERE id = NEW.post_id;
END//

CREATE TRIGGER update_blog_post_likes_count_delete
AFTER DELETE ON blog_post_likes
FOR EACH ROW
BEGIN
    UPDATE blog_posts 
    SET like_count = like_count - 1 
    WHERE id = OLD.post_id;
END//

-- Update blog post comment count
CREATE TRIGGER update_blog_post_comment_count_insert
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    IF NEW.commentable_type = 'blog_post' AND NEW.parent_comment_id IS NULL THEN
        UPDATE blog_posts 
        SET comment_count = comment_count + 1 
        WHERE id = NEW.commentable_id;
    END IF;
END//

CREATE TRIGGER update_blog_post_comment_count_delete
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    IF OLD.commentable_type = 'blog_post' AND OLD.parent_comment_id IS NULL THEN
        UPDATE blog_posts 
        SET comment_count = comment_count - 1 
        WHERE id = OLD.commentable_id;
    END IF;
END//

-- Update comment reply count (only if reply_count column exists)
DROP TRIGGER IF EXISTS update_comment_reply_count_insert//
CREATE TRIGGER update_comment_reply_count_insert
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    IF NEW.parent_comment_id IS NOT NULL THEN
        UPDATE comments 
        SET reply_count = reply_count + 1 
        WHERE id = NEW.parent_comment_id;
    END IF;
END//

DROP TRIGGER IF EXISTS update_comment_reply_count_delete//
CREATE TRIGGER update_comment_reply_count_delete
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    IF OLD.parent_comment_id IS NOT NULL THEN
        UPDATE comments 
        SET reply_count = reply_count - 1 
        WHERE id = OLD.parent_comment_id;
    END IF;
END//

-- Note: Skipping comment_likes triggers as the table already exists with potentially different structure

DELIMITER ;

-- =============================================
-- INSERT DEFAULT CATEGORIES
-- =============================================
-- Note: These will need to be inserted per community as needed
-- Example categories that can be created:
-- INSERT INTO `blog_categories` (`community_id`, `name`, `slug`, `description`, `color`, `icon`, `display_order`) VALUES
-- (1, 'Announcements', 'announcements', 'Official community announcements', '#dc3545', 'bi-megaphone', 1),
-- (1, 'Tutorials', 'tutorials', 'How-to guides and tutorials', '#28a745', 'bi-book', 2),
-- (1, 'Discussion', 'discussion', 'General discussion topics', '#17a2b8', 'bi-chat-dots', 3),
-- (1, 'Resources', 'resources', 'Helpful resources and links', '#ffc107', 'bi-link-45deg', 4),
-- (1, 'Projects', 'projects', 'Project updates and showcases', '#6f42c1', 'bi-folder', 5);

-- =============================================
-- CREATE INDEXES FOR PERFORMANCE
-- =============================================
CREATE INDEX idx_blog_posts_community_status ON blog_posts(community_id, status, published_at);
CREATE INDEX idx_blog_posts_author_status ON blog_posts(author_id, status);
CREATE INDEX idx_comments_type_id_status ON comments(commentable_type, commentable_id, status);
CREATE INDEX idx_blog_post_views_post_user ON blog_post_views(post_id, user_id);