-- Migration: Add community_id to existing tables
-- Date: 2025-07-17
-- Description: Adds community_id column to all relevant tables and migrates existing data

-- Get the default community ID
SET @default_community_id = (SELECT `id` FROM `communities` WHERE `slug` = 'default');

-- =====================================================
-- ADD COMMUNITY_ID TO PROJECTS TABLE
-- =====================================================
ALTER TABLE `projects` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing projects to belong to default community
UPDATE `projects` SET `community_id` = @default_community_id WHERE `community_id` = 1;

-- Remove the default value after migration
ALTER TABLE `projects` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO COURSES TABLE
-- =====================================================
ALTER TABLE `courses` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing courses to belong to default community
UPDATE `courses` SET `community_id` = @default_community_id WHERE `community_id` = 1;

-- Remove the default value after migration
ALTER TABLE `courses` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO FEATURES TABLE
-- =====================================================
ALTER TABLE `features` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing features based on their project's community
UPDATE `features` f
JOIN `projects` p ON f.`project_id` = p.`id`
SET f.`community_id` = p.`community_id`;

-- Remove the default value after migration
ALTER TABLE `features` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO TASKS TABLE
-- =====================================================
ALTER TABLE `tasks` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing tasks based on their project's community
UPDATE `tasks` t
JOIN `projects` p ON t.`project_id` = p.`id`
SET t.`community_id` = p.`community_id`;

-- Remove the default value after migration
ALTER TABLE `tasks` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO SPRINTS TABLE
-- =====================================================
ALTER TABLE `sprints` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing sprints based on their project's community
UPDATE `sprints` s
JOIN `projects` p ON s.`project_id` = p.`id`
SET s.`community_id` = p.`community_id`;

-- Remove the default value after migration
ALTER TABLE `sprints` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO ACTIVITIES TABLE
-- =====================================================
ALTER TABLE `activities` 
ADD COLUMN `community_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing activities based on their project's community
UPDATE `activities` a
LEFT JOIN `projects` p ON a.`project_id` = p.`id`
SET a.`community_id` = COALESCE(p.`community_id`, @default_community_id);

-- =====================================================
-- ADD COMMUNITY_ID TO NOTIFICATIONS TABLE
-- =====================================================
ALTER TABLE `notifications` 
ADD COLUMN `community_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing notifications to default community
UPDATE `notifications` SET `community_id` = @default_community_id WHERE `community_id` IS NULL;

-- =====================================================
-- ADD COMMUNITY_ID TO STANDUPS TABLE
-- =====================================================
ALTER TABLE `standups` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `user_id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing standups based on their project's community
UPDATE `standups` s
JOIN `projects` p ON s.`project_id` = p.`id`
SET s.`community_id` = p.`community_id`;

-- Remove the default value after migration
ALTER TABLE `standups` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO GIT_REPOSITORIES TABLE
-- =====================================================
ALTER TABLE `git_repositories` 
ADD COLUMN `community_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing repositories based on their project's community
UPDATE `git_repositories` g
JOIN `projects` p ON g.`project_id` = p.`id`
SET g.`community_id` = p.`community_id`;

-- Remove the default value after migration
ALTER TABLE `git_repositories` ALTER COLUMN `community_id` DROP DEFAULT;

-- =====================================================
-- ADD COMMUNITY_ID TO COURSE_CATEGORIES TABLE
-- =====================================================
ALTER TABLE `course_categories` 
ADD COLUMN `community_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
ADD FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
ADD INDEX idx_community_id (`community_id`);

-- Update existing categories to be global (NULL community_id means available to all communities)
-- Categories can be community-specific or global

-- =====================================================
-- CREATE COMPOSITE INDEXES FOR BETTER PERFORMANCE
-- =====================================================
-- Projects
CREATE INDEX idx_projects_community_status ON `projects`(`community_id`, `status`);
CREATE INDEX idx_projects_community_dates ON `projects`(`community_id`, `start_date`, `end_date`);

-- Courses
CREATE INDEX idx_courses_community_status ON `courses`(`community_id`, `status`);
CREATE INDEX idx_courses_community_featured ON `courses`(`community_id`, `featured`);

-- Tasks
CREATE INDEX idx_tasks_community_status ON `tasks`(`community_id`, `status`);
CREATE INDEX idx_tasks_community_assignee ON `tasks`(`community_id`, `assignee_id`);

-- Features
CREATE INDEX idx_features_community_status ON `features`(`community_id`, `status`);
CREATE INDEX idx_features_community_priority ON `features`(`community_id`, `priority`);

-- Sprints
CREATE INDEX idx_sprints_community_status ON `sprints`(`community_id`, `status`);
CREATE INDEX idx_sprints_community_dates ON `sprints`(`community_id`, `start_date`, `end_date`);