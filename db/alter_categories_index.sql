
-- Fix unique constraint to allow same category name for different media types
ALTER TABLE categories DROP INDEX name;
ALTER TABLE categories ADD UNIQUE(name, media_type);
