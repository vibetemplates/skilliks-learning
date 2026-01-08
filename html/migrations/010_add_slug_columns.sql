-- Add slug columns to tables for URL routing
-- This migration adds slug columns to tables that need custom URLs

-- Add slug column to courses table
ALTER TABLE courses 
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER title,
ADD INDEX idx_slug (slug);

-- Add slug column to programs table  
ALTER TABLE programs
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER name,
ADD INDEX idx_slug (slug);

-- Add slug column to projects table
ALTER TABLE projects
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER name,
ADD INDEX idx_slug (slug);

-- Add slug column to posts table
ALTER TABLE posts
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER title,
ADD INDEX idx_slug (slug);

-- Add slug column to team_members table
ALTER TABLE team_members
ADD COLUMN slug VARCHAR(255) DEFAULT NULL AFTER name,
ADD INDEX idx_slug (slug);

-- Example slugs (you can update these with actual values)
-- UPDATE programs SET slug = 'beginners-series' WHERE id = 5;
-- UPDATE team_members SET slug = 'edward-honour' WHERE id = 1;