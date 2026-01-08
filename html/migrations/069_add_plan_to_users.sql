-- Add plan column to users table
ALTER TABLE users ADD COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'all' AFTER github_username;

-- Add index for faster lookups
CREATE INDEX idx_users_plan ON users(plan);