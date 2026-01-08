-- Create table for allowed users who can register for specific communities
CREATE TABLE IF NOT EXISTS community_allowed_users (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    community_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    notes TEXT,
    
    -- Foreign key constraints
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Ensure unique email per community
    UNIQUE KEY unique_community_email (community_id, email),
    
    -- Indexes for performance
    INDEX idx_community_id (community_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;