-- Run Communities Migration Scripts
-- Execute these scripts in order to add multi-community support

-- First, run the communities table creation
SOURCE 001_create_communities_table.sql;

-- Then, add community_id to existing tables
SOURCE 002_add_community_id_to_existing_tables.sql;

-- Verify the migrations
SHOW TABLES LIKE 'communities%';
SELECT * FROM communities;
SELECT COUNT(*) as member_count FROM community_members;