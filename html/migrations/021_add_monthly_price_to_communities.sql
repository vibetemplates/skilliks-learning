-- Migration: Add monthly_price to communities table
-- Date: 2025-08-05
-- Description: Adds monthly_price column to support paid communities

-- Add monthly_price column to communities table
ALTER TABLE `communities` 
ADD COLUMN `monthly_price` DECIMAL(10, 2) DEFAULT NULL AFTER `requires_approval`,
ADD INDEX idx_monthly_price (`monthly_price`);

-- Add comment for clarity
ALTER TABLE `communities` 
MODIFY COLUMN `monthly_price` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Monthly subscription price for paid communities, NULL for free communities';