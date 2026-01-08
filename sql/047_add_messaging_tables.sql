-- Migration: Add messaging tables for direct messaging functionality
-- This migration creates tables for conversations, participants, messages, and user blocks

-- Create conversations table
CREATE TABLE IF NOT EXISTS conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_community_id (community_id),
    KEY idx_updated_at (updated_at),
    
    -- Foreign keys
    CONSTRAINT fk_conversations_community FOREIGN KEY (community_id) 
        REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create conversation_participants table
CREATE TABLE IF NOT EXISTS conversation_participants (
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    last_read_at TIMESTAMP NULL DEFAULT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Primary key
    PRIMARY KEY (conversation_id, user_id),
    
    -- Indexes
    KEY idx_user_id (user_id),
    KEY idx_last_read (conversation_id, last_read_at),
    KEY idx_user_deleted (user_id, is_deleted),
    
    -- Foreign keys
    CONSTRAINT fk_participants_conversation FOREIGN KEY (conversation_id) 
        REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_participants_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message_text TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_conversation_created (conversation_id, created_at),
    KEY idx_sender (sender_id),
    KEY idx_created_at (created_at),
    
    -- Foreign keys
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) 
        REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_blocks table
CREATE TABLE IF NOT EXISTS user_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blocker_user_id INT UNSIGNED NOT NULL,
    blocked_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint to prevent duplicate blocks
    UNIQUE KEY unique_block (blocker_user_id, blocked_user_id),
    
    -- Indexes
    KEY idx_blocked_user (blocked_user_id),
    
    -- Foreign keys
    CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create message_rate_limits table for rate limiting
CREATE TABLE IF NOT EXISTS message_rate_limits (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    message_count INT UNSIGNED DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Index
    KEY idx_window_start (window_start),
    
    -- Foreign key
    CONSTRAINT fk_rate_limits_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;