-- Create lesson_skills table to link skills with individual lessons
-- This allows tracking which skills are taught in each lesson and at what level

CREATE TABLE IF NOT EXISTS `lesson_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` INT(11) NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    `skill_level` ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    `is_required` BOOLEAN DEFAULT FALSE COMMENT 'Whether this skill is required for the lesson',
    `added_by` INT UNSIGNED COMMENT 'User who added this skill association',
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    CONSTRAINT `fk_lesson_skills_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lesson_skills_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lesson_skills_user` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    -- Indexes for performance
    UNIQUE KEY `unique_lesson_skill` (`lesson_id`, `skill_id`),
    INDEX `idx_lesson_id` (`lesson_id`),
    INDEX `idx_skill_id` (`skill_id`),
    INDEX `idx_skill_level` (`skill_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE `lesson_skills` COMMENT = 'Links skills to individual lessons with skill level and requirement status';