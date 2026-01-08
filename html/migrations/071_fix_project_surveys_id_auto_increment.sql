-- Fix project_surveys table: Add PRIMARY KEY and AUTO_INCREMENT to id column
-- This migration addresses the error: Field 'id' doesn't have a default value

ALTER TABLE project_surveys
ADD PRIMARY KEY (id);

ALTER TABLE project_surveys
MODIFY COLUMN id int(10) unsigned NOT NULL AUTO_INCREMENT;
