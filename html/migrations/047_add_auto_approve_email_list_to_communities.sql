-- Add column to communities table to enable auto-approval from free_community_emails list
ALTER TABLE `communities` 
ADD COLUMN `auto_approve_from_email_list` TINYINT(1) DEFAULT 0 
COMMENT 'Auto-approve members if their email is in free_community_emails table' 
AFTER `requires_approval`;

-- Add index for better performance
ALTER TABLE `communities` 
ADD INDEX `idx_auto_approve_email` (`auto_approve_from_email_list`);