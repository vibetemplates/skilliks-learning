-- Add slug column to users table for custom URLs
-- This allows users to have URLs like /john-doe that redirect to their profile

-- Add slug column to users table
ALTER TABLE users 
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER last_name,
ADD UNIQUE INDEX idx_users_slug (slug);

-- Generate initial slugs from existing user names
-- You can customize these later
UPDATE users 
SET slug = LOWER(CONCAT(
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(first_name, ' ', '-'), '.', ''), ',', ''), '\'', ''), '"', ''),
    '-',
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(last_name, ' ', '-'), '.', ''), ',', ''), '\'', ''), '"', '')
))
WHERE slug IS NULL;