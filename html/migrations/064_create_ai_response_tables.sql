-- Migration: Create AI Response Tables for Claude and Skilliks
-- This migration creates tables to store AI conversation responses from both platforms

-- 1. Create sessions table
CREATE TABLE IF NOT EXISTS ai_sessions (
    id VARCHAR(36) PRIMARY KEY,
    platform ENUM('claude', 'skilliks') NOT NULL,
    model VARCHAR(100),
    status VARCHAR(50),
    working_directory VARCHAR(500),
    permission_mode VARCHAR(50),
    api_key_source VARCHAR(50),
    conversation_length INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    sprint_id INT UNSIGNED,
    prompt_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    last_activity TIMESTAMP NULL,
    
    -- Foreign keys
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    INDEX idx_sprint (sprint_id),
    INDEX idx_prompt (prompt_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE CASCADE,
    FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create messages table
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(36),
    session_id VARCHAR(36) NOT NULL,
    type ENUM('system', 'assistant', 'user', 'result') NOT NULL,
    subtype VARCHAR(50),
    role VARCHAR(50),
    sequence_number INT NOT NULL,
    parent_tool_use_id VARCHAR(36),
    stop_reason VARCHAR(100),
    stop_sequence VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    INDEX idx_session (session_id),
    INDEX idx_type (type),
    INDEX idx_sequence (session_id, sequence_number),
    INDEX idx_message_id (message_id),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create message content table
CREATE TABLE IF NOT EXISTS ai_message_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    content_type ENUM('text', 'tool_use', 'tool_result', 'response') NOT NULL,
    content_text LONGTEXT,
    content_data JSON,
    tool_use_id VARCHAR(36),
    tool_name VARCHAR(100),
    is_error BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    INDEX idx_message (message_id),
    INDEX idx_content_type (content_type),
    INDEX idx_tool_use (tool_use_id),
    FOREIGN KEY (message_id) REFERENCES ai_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create tool executions table
CREATE TABLE IF NOT EXISTS ai_tool_executions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    message_id INT UNSIGNED,
    tool_use_id VARCHAR(36),
    tool_name VARCHAR(100) NOT NULL,
    parameters JSON,
    result JSON,
    result_summary TEXT,
    is_error BOOLEAN DEFAULT FALSE,
    duration_ms INT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    INDEX idx_session (session_id),
    INDEX idx_message (message_id),
    INDEX idx_tool (tool_name),
    INDEX idx_executed (executed_at),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES ai_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create token usage table
CREATE TABLE IF NOT EXISTS ai_token_usage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36),
    message_id INT UNSIGNED,
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    cache_creation_tokens INT DEFAULT 0,
    cache_read_tokens INT DEFAULT 0,
    prompt_tokens INT DEFAULT 0,
    candidate_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    cached_content_tokens INT,
    cache_creation_5m_tokens INT DEFAULT 0,
    cache_creation_1h_tokens INT DEFAULT 0,
    utilization_percent DECIMAL(5,2),
    cost_usd DECIMAL(10,6),
    service_tier VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    INDEX idx_session (session_id),
    INDEX idx_message (message_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES ai_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create context statistics table (for skilliks)
CREATE TABLE IF NOT EXISTS ai_context_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    message_count INT DEFAULT 0,
    token_count INT DEFAULT 0,
    token_limit INT DEFAULT 0,
    utilization_percent DECIMAL(5,2) DEFAULT 0,
    tool_execution_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    UNIQUE KEY idx_session (session_id),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create session tools table (for claude init tools)
CREATE TABLE IF NOT EXISTS ai_session_tools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    tool_name VARCHAR(100) NOT NULL,
    tool_order INT DEFAULT 0,
    
    -- Foreign keys and indexes
    INDEX idx_session (session_id),
    INDEX idx_tool (tool_name),
    UNIQUE KEY idx_session_tool (session_id, tool_name),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create aggregated results table for analysis
CREATE TABLE IF NOT EXISTS ai_conversation_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    sprint_id INT UNSIGNED,
    prompt_id INT UNSIGNED,
    platform ENUM('claude', 'skilliks') NOT NULL,
    final_response LONGTEXT,
    total_duration_ms INT,
    api_duration_ms INT,
    num_turns INT DEFAULT 0,
    total_cost_usd DECIMAL(10,6),
    total_input_tokens INT DEFAULT 0,
    total_output_tokens INT DEFAULT 0,
    tools_used_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys and indexes
    UNIQUE KEY idx_session (session_id),
    INDEX idx_sprint (sprint_id),
    INDEX idx_prompt (prompt_id),
    INDEX idx_platform (platform),
    INDEX idx_created (created_at),
    FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE CASCADE,
    FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for common query patterns
CREATE INDEX idx_session_platform_created ON ai_sessions(platform, created_at);
CREATE INDEX idx_message_session_type ON ai_messages(session_id, type);
CREATE INDEX idx_tool_session_name ON ai_tool_executions(session_id, tool_name);