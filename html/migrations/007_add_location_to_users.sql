-- Migration: Add location fields to users table
-- Created: 2025-07-18
-- Description: Adds geographic location fields for displaying members on a map

-- Add location columns to users table
ALTER TABLE users 
ADD COLUMN location_address VARCHAR(255) DEFAULT NULL COMMENT 'User-entered address or location',
ADD COLUMN location_city VARCHAR(100) DEFAULT NULL,
ADD COLUMN location_state VARCHAR(100) DEFAULT NULL,
ADD COLUMN location_country VARCHAR(100) DEFAULT NULL,
ADD COLUMN location_latitude DECIMAL(10, 8) DEFAULT NULL COMMENT 'Latitude coordinate for map display',
ADD COLUMN location_longitude DECIMAL(11, 8) DEFAULT NULL COMMENT 'Longitude coordinate for map display',
ADD COLUMN location_updated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When location was last updated',
ADD INDEX idx_users_location (location_latitude, location_longitude),
ADD INDEX idx_users_country (location_country);

-- Add location privacy setting
ALTER TABLE users
ADD COLUMN location_privacy ENUM('public', 'community', 'private') DEFAULT 'community' 
    COMMENT 'Who can see this users location: public=anyone, community=community members only, private=hidden';