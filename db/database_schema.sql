-- =============================================================================
-- MEDIA COLLECTION SYSTEM - DATABASE SCHEMA
-- =============================================================================
-- Version: 1.0.0
-- Compatible with: MySQL 5.7+, MariaDB 10.2+
-- Description: Complete database schema for personal media collection system
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================================================
-- USERS AND AUTHENTICATION
-- =============================================================================

-- Users table (for multi-user support)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `language` varchar(5) DEFAULT 'en',
  `theme` varchar(20) DEFAULT 'default',
  `is_active` tinyint(1) DEFAULT 1,
  `is_admin` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table (simplified for single-user setups)
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `data` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_expires` (`expires_at`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CATEGORIES SYSTEM
-- =============================================================================

-- Enhanced categories table with hierarchical support (from your existing schema)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `description` text,
  `parent_id` int(11) DEFAULT NULL,
  `category_level` int(11) NOT NULL DEFAULT 1,
  `category_path` text,
  `media_type` enum('movie','book','comic','music','game','other') NOT NULL,
  `category_type` enum('genre','format','theme','collection','series','era','location','condition','custom') NOT NULL DEFAULT 'genre',
  `display_order` int(11) DEFAULT 0,
  `color_code` varchar(7) DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  INDEX `idx_parent_id` (`parent_id`),
  INDEX `idx_media_type` (`media_type`),
  INDEX `idx_category_type` (`category_type`),
  INDEX `idx_category_level` (`category_level`),
  INDEX `idx_slug` (`slug`),
  INDEX `idx_display_order` (`display_order`),
  INDEX `idx_path` (`category_path`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category usage statistics
CREATE TABLE IF NOT EXISTS `category_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `usage_count` int(11) DEFAULT 0,
  `last_used` timestamp NULL DEFAULT NULL,
  `media_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_stats` (`category_id`, `media_type`),
  INDEX `idx_usage_count` (`usage_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category templates for quick setup
CREATE TABLE IF NOT EXISTS `category_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `media_type` varchar(50) NOT NULL,
  `category_data` json DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_template_name` (`template_name`),
  INDEX `idx_media_type` (`media_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MAIN COLLECTION TABLES
-- =============================================================================

-- Main collection table
CREATE TABLE IF NOT EXISTS `collection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `creator` varchar(300) DEFAULT NULL,
  `director` varchar(200) DEFAULT NULL,
  `author` varchar(200) DEFAULT NULL,
  `artist` varchar(200) DEFAULT NULL,
  `publisher` varchar(200) DEFAULT NULL,
  `studio` varchar(200) DEFAULT NULL,
  `label` varchar(200) DEFAULT NULL,
  `media_type` enum('movie','book','comic','music','game','other') NOT NULL,
  `format` varchar(100) DEFAULT NULL,
  `genre` varchar(200) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `upc` varchar(20) DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `barcode` varchar(30) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `catalog_number` varchar(50) DEFAULT NULL,
  `description` text,
  `notes` text,
  `condition_rating` enum('mint','near_mint','very_fine','fine','very_good','good','fair','poor') DEFAULT 'very_good',
  `condition_notes` text,
  `location` varchar(200) DEFAULT NULL,
  `storage_location` varchar(200) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `current_value` decimal(10,2) DEFAULT NULL,
  `estimated_value` decimal(10,2) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_location` varchar(200) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `personal_rating` int(1) DEFAULT NULL,
  `review` text,
  `poster_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `external_id` varchar(100) DEFAULT NULL,
  `external_source` varchar(50) DEFAULT NULL,
  `tmdb_id` int(11) DEFAULT NULL,
  `imdb_id` varchar(20) DEFAULT NULL,
  `google_books_id` varchar(50) DEFAULT NULL,
  `musicbrainz_id` varchar(50) DEFAULT NULL,
  `igdb_id` int(11) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'en',
  `country` varchar(5) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `pages` int(11) DEFAULT NULL,
  `tracks` int(11) DEFAULT NULL,
  `episodes` int(11) DEFAULT NULL,
  `season` int(11) DEFAULT NULL,
  `volume` int(11) DEFAULT NULL,
  `issue` int(11) DEFAULT NULL,
  `series` varchar(200) DEFAULT NULL,
  `franchise` varchar(200) DEFAULT NULL,
  `awards` text,
  `tags` text,
  `is_favorite` tinyint(1) DEFAULT 0,
  `is_wishlist` tinyint(1) DEFAULT 0,
  `is_owned` tinyint(1) DEFAULT 1,
  `is_digital` tinyint(1) DEFAULT 0,
  `is_loaned` tinyint(1) DEFAULT 0,
  `loaned_to` varchar(200) DEFAULT NULL,
  `loan_date` date DEFAULT NULL,
  `loan_notes` text,
  `times_viewed` int(11) DEFAULT 0,
  `last_viewed` timestamp NULL DEFAULT NULL,
  `date_added` timestamp DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_media_type` (`media_type`),
  INDEX `idx_title` (`title`),
  INDEX `idx_creator` (`creator`),
  INDEX `idx_year` (`year`),
  INDEX `idx_genre` (`genre`),
  INDEX `idx_rating` (`rating`),
  INDEX `idx_is_favorite` (`is_favorite`),
  INDEX `idx_date_added` (`date_added`),
  INDEX `idx_barcode` (`barcode`),
  INDEX `idx_external_id` (`external_id`),
  FULLTEXT KEY `ft_search` (`title`, `subtitle`, `creator`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Collection-Category relationships (many-to-many)
CREATE TABLE IF NOT EXISTS `collection_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`collection_id`, `category_id`),
  FOREIGN KEY (`collection_id`) REFERENCES `collection` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  INDEX `idx_collection_id` (`collection_id`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- WISHLIST AND SHOPPING
-- =============================================================================

-- Wishlist table
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `creator` varchar(300) DEFAULT NULL,
  `media_type` enum('movie','book','comic','music','game','other') NOT NULL,
  `format` varchar(100) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `estimated_value` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text,
  `external_id` varchar(100) DEFAULT NULL,
  `external_source` varchar(50) DEFAULT NULL,
  `poster_url` varchar(500) DEFAULT NULL,
  `availability_status` enum('available','out_of_stock','pre_order','discontinued') DEFAULT 'available',
  `price_alert` tinyint(1) DEFAULT 0,
  `target_price` decimal(10,2) DEFAULT NULL,
  `found_at_stores` text,
  `last_price_check` timestamp NULL DEFAULT NULL,
  `acquired` tinyint(1) DEFAULT 0,
  `acquired_date` date DEFAULT NULL,
  `acquired_price` decimal(10,2) DEFAULT NULL,
  `collection_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`collection_id`) REFERENCES `collection` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_media_type` (`media_type`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_acquired` (`acquired`),
  INDEX `idx_price_alert` (`price_alert`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist-Category relationships
CREATE TABLE IF NOT EXISTS `wishlist_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wishlist_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist_assignment` (`wishlist_id`, `category_id`),
  FOREIGN KEY (`wishlist_id`) REFERENCES `wishlist` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  INDEX `idx_wishlist_id` (`wishlist_id`),
  INDEX `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SYSTEM TABLES
-- =============================================================================

-- Activity log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_table_record` (`table_name`, `record_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json','text') DEFAULT 'string',
  `description` text,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File uploads tracking
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_type` enum('poster','thumbnail','backup','document','other') DEFAULT 'other',
  `related_table` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_file_type` (`file_type`),
  INDEX `idx_related` (`related_table`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- VIEWS FOR COMMON QUERIES
-- =============================================================================

-- Collection summary view
CREATE OR REPLACE VIEW `collection_summary` AS
SELECT 
    c.*,
    GROUP_CONCAT(cat.name SEPARATOR ', ') as category_names,
    GROUP_CONCAT(DISTINCT cat.category_type SEPARATOR ', ') as category_types,
    u.username as owner_username
FROM `collection` c
LEFT JOIN `collection_categories` cc ON c.id = cc.collection_id
LEFT JOIN `categories` cat ON cc.category_id = cat.id AND cat.is_active = 1
LEFT JOIN `users` u ON c.user_id = u.id
GROUP BY c.id;

-- Statistics view
CREATE OR REPLACE VIEW `collection_stats` AS
SELECT 
    user_id,
    media_type,
    COUNT(*) as item_count,
    AVG(personal_rating) as avg_rating,
    SUM(purchase_price) as total_spent,
    SUM(current_value) as total_value,
    SUM(estimated_value) as estimated_total,
    MIN(date_added) as first_item_date,
    MAX(date_added) as latest_item_date
FROM `collection`
WHERE is_owned = 1
GROUP BY user_id, media_type;

-- =============================================================================
-- TRIGGERS FOR AUTOMATION
-- =============================================================================

DELIMITER //

-- Update category path when categories change
CREATE TRIGGER `update_category_path` 
AFTER INSERT ON `categories`
FOR EACH ROW
BEGIN
    DECLARE path_value TEXT DEFAULT '';
    DECLARE parent_path TEXT DEFAULT '';
    
    IF NEW.parent_id IS NOT NULL THEN
        SELECT category_path INTO parent_path FROM categories WHERE id = NEW.parent_id;
        SET path_value = CONCAT(IFNULL(parent_path, ''), '/', NEW.name);
    ELSE
        SET path_value = NEW.name;
    END IF;
    
    UPDATE categories SET category_path = path_value WHERE id = NEW.id;
END//

-- Update category usage statistics
CREATE TRIGGER `update_category_usage`
AFTER INSERT ON `collection_categories`
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

-- Log collection changes
CREATE TRIGGER `log_collection_changes`
AFTER UPDATE ON `collection`
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (
        NEW.user_id,
        'UPDATE',
        'collection',
        NEW.id,
        JSON_OBJECT(
            'title', OLD.title,
            'current_value', OLD.current_value,
            'condition_rating', OLD.condition_rating
        ),
        JSON_OBJECT(
            'title', NEW.title,
            'current_value', NEW.current_value,
            'condition_rating', NEW.condition_rating
        )
    );
END//

DELIMITER ;

-- =============================================================================
-- DEFAULT DATA INSERTION
-- =============================================================================

-- Create default admin user (password: admin123)
INSERT IGNORE INTO `admins` (`username`, `password`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name', 'My Media Collection', 'string', 'Application name'),
('items_per_page', '20', 'integer', 'Number of items to display per page'),
('enable_categories', 'true', 'boolean', 'Enable category system'),
('enable_wishlist', 'true', 'boolean', 'Enable wishlist functionality'),
('default_currency', 'USD', 'string', 'Default currency for values'),
('auto_backup', 'false', 'boolean', 'Enable automatic backups'),
('backup_frequency', 'weekly', 'string', 'Backup frequency'),
('theme', 'default', 'string', 'Default theme'),
('timezone', 'UTC', 'string', 'Default timezone');

-- Insert category templates from your schema
INSERT IGNORE INTO `category_templates` (`template_name`, `media_type`, `category_data`, `description`) VALUES
('Movie Genres', 'movie', '{"genres":[{"name":"Action","subs":["Adventure","Thriller","Spy"]},{"name":"Comedy","subs":["Romantic Comedy","Dark Comedy","Parody"]},{"name":"Drama","subs":["Historical","Biographical","Family"]},{"name":"Horror","subs":["Psychological","Supernatural","Slasher"]},{"name":"Sci-Fi","subs":["Space Opera","Cyberpunk","Time Travel"]},{"name":"Fantasy","subs":["High Fantasy","Urban Fantasy","Mythology"]}]}', 'Standard movie genre classification'),
('Book Categories', 'book', '{"genres":[{"name":"Fiction","subs":["Literary Fiction","Historical Fiction","Science Fiction","Fantasy","Mystery","Romance"]},{"name":"Non-Fiction","subs":["Biography","History","Science","Self-Help","Business","Travel"]},{"name":"Reference","subs":["Dictionary","Encyclopedia","Manual","Textbook"]}],"formats":["Hardcover","Paperback","Mass Market","E-book","Audiobook"]}', 'Comprehensive book categorization'),
('Music Genres', 'music', '{"genres":[{"name":"Rock","subs":["Classic Rock","Alternative","Progressive","Punk"]},{"name":"Pop","subs":["Dance Pop","Teen Pop","Synth Pop"]},{"name":"Electronic","subs":["House","Techno","Ambient","Dubstep"]},{"name":"Jazz","subs":["Bebop","Smooth Jazz","Fusion"]},{"name":"Classical","subs":["Baroque","Romantic","Modern"]}],"formats":["CD","Vinyl","Digital","Cassette"]}', 'Music genre and format classification'),
('Comic Categories', 'comic', '{"genres":[{"name":"Superhero","subs":["Marvel","DC","Independent"]},{"name":"Manga","subs":["Shonen","Shoujo","Seinen","Josei"]},{"name":"Graphic Novel","subs":["Biography","Historical","Fiction"]},{"name":"Webcomic","subs":["Daily Strip","Long Form","Anthology"]}],"formats":["Single Issue","Trade Paperback","Hardcover","Digital"]}', 'Comic book and graphic novel categories');

COMMIT;

-- =============================================================================
-- POST-INSTALLATION NOTES
-- =============================================================================

/*
INSTALLATION CHECKLIST:
□ 1. Import this schema into your MySQL database
□ 2. Update config.php with your database credentials
□ 3. Set proper file permissions on upload directories
□ 4. Configure your web server (Apache/Nginx)
□ 5. Test database connection via dashboard
□ 6. Change default admin password
□ 7. Configure any external API keys
□ 8. Set up automatic backups (optional)

DEFAULT LOGIN:
Username: admin
Password: admin123
(Change this immediately after first login!)

REQUIRED PHP EXTENSIONS:
- PDO and PDO_MySQL
- JSON
- GD or ImageMagick (for image processing)
- cURL (for API integrations)
- mbstring (for UTF-8 support)

RECOMMENDED SERVER SETTINGS:
- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2+
- memory_limit = 256M
- upload_max_filesize = 10M
- post_max_size = 10M
- max_execution_time = 300

DATABASE SIZE ESTIMATES:
- Small collection (< 1,000 items): ~50MB
- Medium collection (1,000-10,000 items): ~500MB
- Large collection (10,000+ items): ~2GB+

BACKUP RECOMMENDATIONS:
- Schedule regular database backups
- Include uploaded files in backups
- Test restore procedures periodically
- Consider offsite backup storage
*/