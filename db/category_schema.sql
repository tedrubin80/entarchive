-- Enhanced Category Management Database Schema
-- Supports unlimited nesting of categories, subcategories, and sections

-- Drop existing tables if they exist (be careful in production!)
-- DROP TABLE IF EXISTS collection_categories;
-- DROP TABLE IF EXISTS wishlist_categories;
-- DROP TABLE IF EXISTS categories;

-- Enhanced categories table with hierarchical support
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    
    -- Hierarchical structure
    parent_id INT NULL,
    category_level INT NOT NULL DEFAULT 1,
    category_path TEXT, -- Stores full path like "Action/Sci-Fi/Space Opera"
    
    -- Media type association
    media_type ENUM('movie', 'book', 'comic', 'music', 'game', 'other') NOT NULL,
    
    -- Category types for organization
    category_type ENUM('genre', 'format', 'theme', 'collection', 'series', 'era', 'location', 'condition', 'custom') NOT NULL DEFAULT 'genre',
    
    -- Display and organization
    display_order INT DEFAULT 0,
    color_code VARCHAR(7), -- Hex color for visual organization
    icon_class VARCHAR(100), -- CSS icon class (e.g., FontAwesome)
    
    -- Metadata
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    -- Foreign key constraints
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_parent_id (parent_id),
    INDEX idx_media_type (media_type),
    INDEX idx_category_type (category_type),
    INDEX idx_category_level (category_level),
    INDEX idx_slug (slug),
    INDEX idx_display_order (display_order),
    INDEX idx_path (category_path(255))
);

-- Junction table for collection-category relationships (many-to-many)
CREATE TABLE IF NOT EXISTS collection_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    collection_id INT NOT NULL,
    category_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE, -- One primary category per item
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    
    UNIQUE KEY unique_assignment (collection_id, category_id),
    FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    
    INDEX idx_collection_id (collection_id),
    INDEX idx_category_id (category_id),
    INDEX idx_primary (is_primary)
);

-- Junction table for wishlist-category relationships
CREATE TABLE IF NOT EXISTS wishlist_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wishlist_id INT NOT NULL,
    category_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_wishlist_assignment (wishlist_id, category_id),
    FOREIGN KEY (wishlist_id) REFERENCES wishlist(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    
    INDEX idx_wishlist_id (wishlist_id),
    INDEX idx_category_id (category_id)
);

-- Category usage statistics (for optimization and insights)
CREATE TABLE IF NOT EXISTS category_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    media_type VARCHAR(50),
    
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_stats (category_id, media_type),
    INDEX idx_usage_count (usage_count)
);

-- Predefined category templates for quick setup
CREATE TABLE IF NOT EXISTS category_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    media_type VARCHAR(50) NOT NULL,
    category_data JSON, -- Stores the category hierarchy as JSON
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_template_name (template_name),
    INDEX idx_media_type (media_type)
);

-- Insert some default category templates
INSERT INTO category_templates (template_name, media_type, category_data, description) VALUES
('Movie Genres', 'movie', JSON_OBJECT(
    'genres', JSON_ARRAY(
        JSON_OBJECT('name', 'Action', 'subs', JSON_ARRAY('Adventure', 'Thriller', 'Spy')),
        JSON_OBJECT('name', 'Comedy', 'subs', JSON_ARRAY('Romantic Comedy', 'Dark Comedy', 'Parody')),
        JSON_OBJECT('name', 'Drama', 'subs', JSON_ARRAY('Historical', 'Biographical', 'Family')),
        JSON_OBJECT('name', 'Horror', 'subs', JSON_ARRAY('Psychological', 'Supernatural', 'Slasher')),
        JSON_OBJECT('name', 'Sci-Fi', 'subs', JSON_ARRAY('Space Opera', 'Cyberpunk', 'Time Travel')),
        JSON_OBJECT('name', 'Fantasy', 'subs', JSON_ARRAY('High Fantasy', 'Urban Fantasy', 'Mythology'))
    )
), 'Standard movie genre classification'),

('Book Categories', 'book', JSON_OBJECT(
    'genres', JSON_ARRAY(
        JSON_OBJECT('name', 'Fiction', 'subs', JSON_ARRAY('Literary Fiction', 'Historical Fiction', 'Science Fiction', 'Fantasy', 'Mystery', 'Romance')),
        JSON_OBJECT('name', 'Non-Fiction', 'subs', JSON_ARRAY('Biography', 'History', 'Science', 'Self-Help', 'Business', 'Travel')),
        JSON_OBJECT('name', 'Reference', 'subs', JSON_ARRAY('Dictionary', 'Encyclopedia', 'Manual', 'Textbook'))
    ),
    'formats', JSON_ARRAY('Hardcover', 'Paperback', 'Mass Market', 'E-book', 'Audiobook')
), 'Comprehensive book categorization'),

('Music Genres', 'music', JSON_OBJECT(
    'genres', JSON_ARRAY(
        JSON_OBJECT('name', 'Rock', 'subs', JSON_ARRAY('Classic Rock', 'Alternative', 'Progressive', 'Punk')),
        JSON_OBJECT('name', 'Pop', 'subs', JSON_ARRAY('Dance Pop', 'Teen Pop', 'Synth Pop')),
        JSON_OBJECT('name', 'Electronic', 'subs', JSON_ARRAY('House', 'Techno', 'Ambient', 'Dubstep')),
        JSON_OBJECT('name', 'Jazz', 'subs', JSON_ARRAY('Bebop', 'Smooth Jazz', 'Fusion')),
        JSON_OBJECT('name', 'Classical', 'subs', JSON_ARRAY('Baroque', 'Romantic', 'Modern'))
    ),
    'formats', JSON_ARRAY('CD', 'Vinyl', 'Digital', 'Cassette')
), 'Music genre and format classification'),

('Comic Categories', 'comic', JSON_OBJECT(
    'genres', JSON_ARRAY(
        JSON_OBJECT('name', 'Superhero', 'subs', JSON_ARRAY('Marvel', 'DC', 'Independent')),
        JSON_OBJECT('name', 'Manga', 'subs', JSON_ARRAY('Shonen', 'Shoujo', 'Seinen', 'Josei')),
        JSON_OBJECT('name', 'Graphic Novel', 'subs', JSON_ARRAY('Biography', 'Historical', 'Fiction')),
        JSON_OBJECT('name', 'Webcomic', 'subs', JSON_ARRAY('Daily Strip', 'Long Form', 'Anthology'))
    ),
    'formats', JSON_ARRAY('Single Issue', 'Trade Paperback', 'Hardcover', 'Digital')
), 'Comic book and graphic novel categories');

-- Trigger to update category_path when categories are inserted or updated
DELIMITER //
CREATE TRIGGER update_category_path 
AFTER INSERT ON categories
FOR EACH ROW
BEGIN
    DECLARE path_value TEXT DEFAULT '';
    DECLARE current_id INT DEFAULT NEW.id;
    DECLARE current_name VARCHAR(255);
    DECLARE parent_path TEXT DEFAULT '';
    
    -- Build the path from root to current category
    IF NEW.parent_id IS NOT NULL THEN
        SELECT category_path INTO parent_path FROM categories WHERE id = NEW.parent_id;
        SET path_value = CONCAT(IFNULL(parent_path, ''), '/', NEW.name);
    ELSE
        SET path_value = NEW.name;
    END IF;
    
    -- Update the current record
    UPDATE categories SET category_path = path_value WHERE id = NEW.id;
END//

CREATE TRIGGER update_category_path_on_update
AFTER UPDATE ON categories
FOR EACH ROW
BEGIN
    DECLARE path_value TEXT DEFAULT '';
    DECLARE parent_path TEXT DEFAULT '';
    
    -- Build the path from root to current category
    IF NEW.parent_id IS NOT NULL THEN
        SELECT category_path INTO parent_path FROM categories WHERE id = NEW.parent_id;
        SET path_value = CONCAT(IFNULL(parent_path, ''), '/', NEW.name);
    ELSE
        SET path_value = NEW.name;
    END IF;
    
    -- Update the current record
    UPDATE categories SET category_path = path_value WHERE id = NEW.id;
    
    -- Update all child categories if the name changed
    IF OLD.name != NEW.name THEN
        UPDATE categories 
        SET category_path = REPLACE(category_path, OLD.name, NEW.name)
        WHERE category_path LIKE CONCAT('%', OLD.name, '%');
    END IF;
END//
DELIMITER ;

-- Trigger to update usage statistics
DELIMITER //
CREATE TRIGGER update_category_usage
AFTER INSERT ON collection_categories
FOR EACH ROW
BEGIN
    INSERT INTO category_stats (category_id, usage_count, last_used, media_type)
    SELECT NEW.category_id, 1, NOW(), c.media_type
    FROM collection col
    JOIN categories c ON c.id = NEW.category_id
    WHERE col.id = NEW.collection_id
    ON DUPLICATE KEY UPDATE 
        usage_count = usage_count + 1,
        last_used = NOW();
END//
DELIMITER ;

-- Views for easy querying

-- Category hierarchy view
CREATE OR REPLACE VIEW category_hierarchy AS
SELECT 
    c.id,
    c.name,
    c.slug,
    c.description,
    c.parent_id,
    c.category_level,
    c.category_path,
    c.media_type,
    c.category_type,
    c.display_order,
    c.color_code,
    c.icon_class,
    c.is_active,
    c.is_featured,
    p.name as parent_name,
    (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) as child_count,
    COALESCE(cs.usage_count, 0) as usage_count,
    cs.last_used
FROM categories c
LEFT JOIN categories p ON c.parent_id = p.id
LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
WHERE c.is_active = 1
ORDER BY c.media_type, c.category_level, c.display_order, c.name;

-- Popular categories view
CREATE OR REPLACE VIEW popular_categories AS
SELECT 
    c.*,
    cs.usage_count,
    cs.last_used,
    CASE 
        WHEN cs.last_used > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Recent'
        WHEN cs.last_used > DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'Active'
        ELSE 'Inactive'
    END as activity_status
FROM categories c
JOIN category_stats cs ON c.id = cs.category_id
WHERE c.is_active = 1 AND cs.usage_count > 0
ORDER BY cs.usage_count DESC, cs.last_used DESC;

-- Unused categories view
CREATE OR REPLACE VIEW unused_categories AS
SELECT c.*
FROM categories c
LEFT JOIN category_stats cs ON c.id = cs.category_id
WHERE c.is_active = 1 AND (cs.usage_count IS NULL OR cs.usage_count = 0)
ORDER BY c.created_at DESC;