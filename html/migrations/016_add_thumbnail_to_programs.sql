-- Add thumbnail_url to programs table
ALTER TABLE programs 
ADD COLUMN thumbnail_url VARCHAR(500) AFTER short_description;