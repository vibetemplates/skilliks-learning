-- Add API key functionality to users table
ALTER TABLE users 
ADD COLUMN api_key VARCHAR(64) NULL AFTER password,
ADD UNIQUE INDEX idx_api_key (api_key);

-- Add is_active column if it doesn't exist (referenced in BaseAPI.php)
ALTER TABLE users 
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER api_key;