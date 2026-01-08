-- Add skool_url column to communities table
-- This column stores the skool.com URL for communities that require skool registration

ALTER TABLE communities 
ADD COLUMN skool_url VARCHAR(500) DEFAULT NULL 
AFTER requires_skool_registration;

-- Add index for better query performance
CREATE INDEX idx_skool_url ON communities(skool_url);