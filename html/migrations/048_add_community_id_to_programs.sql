-- Add community_id to programs table to allow community-specific programs
ALTER TABLE `programs` 
ADD COLUMN `community_id` INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
ADD INDEX `idx_community_id` (`community_id`),
ADD CONSTRAINT `fk_programs_community` FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE;

-- Update existing programs to belong to community 1 (default)
UPDATE `programs` SET `community_id` = 1 WHERE `community_id` = 0;