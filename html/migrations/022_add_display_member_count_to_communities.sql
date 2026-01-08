-- Migration: Add display_member_count to communities table
-- Date: 2025-08-05
-- Description: Adds display_member_count column to allow custom member count display

-- Add display_member_count column to communities table
ALTER TABLE `communities`
ADD COLUMN `display_member_count` VARCHAR(100) DEFAULT NULL AFTER `monthly_price`,
ADD INDEX idx_display_member_count (`display_member_count`);

-- Add comment for clarity
ALTER TABLE `communities`
MODIFY COLUMN `display_member_count` VARCHAR(100) DEFAULT NULL COMMENT 'Custom display value for member count (can be text or number)';