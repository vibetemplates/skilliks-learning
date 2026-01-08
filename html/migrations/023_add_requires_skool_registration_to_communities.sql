-- Add requires_skool_registration column to communities table
-- This column indicates whether users must be registered on skool.com first
-- If true, user's email must be in community_allowed_users table

ALTER TABLE communities 
ADD COLUMN requires_skool_registration BOOLEAN DEFAULT FALSE 
AFTER requires_approval;

-- Add index for better performance when filtering communities by this field
CREATE INDEX idx_requires_skool_registration ON communities(requires_skool_registration);