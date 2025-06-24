-- =============================================================================
-- MARKETPLACE SYNC DATABASE TABLES
-- Add these to your existing database schema
-- =============================================================================

-- Extended wishlist table with marketplace sync fields
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL COMMENT 'External marketplace item ID';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `external_url` TEXT NULL COMMENT 'Link to original listing';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `source` VARCHAR(50) DEFAULT 'manual' COMMENT 'Source: manual, ebay, amazon, mercari';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `current_market_price` DECIMAL(10,2) NULL COMMENT 'Current market price';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `last_price_check` TIMESTAMP NULL COMMENT 'When price was last checked';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `last_price_update` TIMESTAMP NULL COMMENT 'When price was last updated';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `price_alert_enabled` BOOLEAN DEFAULT TRUE COMMENT 'Enable price drop alerts';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `auction_end_time` TIMESTAMP NULL COMMENT 'When auction ends (for eBay)';
ALTER TABLE `wishlist` ADD COLUMN IF NOT EXISTS `seller_info` TEXT NULL COMMENT 'Seller information JSON';

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS `idx_wishlist_external_id` ON `wishlist` (`external_id`);
CREATE INDEX IF NOT EXISTS `idx_wishlist_source` ON `wishlist` (`source`);
CREATE INDEX IF NOT EXISTS `idx_wishlist_price_check` ON `wishlist` (`last_price_check`);

-- =============================================================================
-- PRICE HISTORY TRACKING
-- =============================================================================

CREATE TABLE IF NOT EXISTS `price_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wishlist_id` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `source` VARCHAR(50) NOT NULL COMMENT 'ebay, amazon, manual, etc.',
    `source_url` TEXT NULL,
    `condition_offered` VARCHAR(100) NULL,
    `shipping_cost` DECIMAL(10,2) NULL,
    `availability_status` VARCHAR(50) NULL COMMENT 'in_stock, out_of_stock, auction_active',
    `seller_rating` DECIMAL(3,2) NULL,
    `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`wishlist_id`) REFERENCES `wishlist`(`id`) ON DELETE CASCADE,
    INDEX `idx_price_history_wishlist` (`wishlist_id`),
    INDEX `idx_price_history_date` (`checked_at`),
    INDEX `idx_price_history_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRICE ALERTS SYSTEM
-- =============================================================================

CREATE TABLE IF NOT EXISTS `price_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wishlist_id` INT NOT NULL,
    `alert_type` ENUM('price_drop', 'target_reached', 'auction_ending', 'back_in_stock') NOT NULL,
    `old_price` DECIMAL(10,2) NULL,
    `new_price` DECIMAL(10,2) NULL,
    `threshold_price` DECIMAL(10,2) NULL,
    `message` TEXT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `is_dismissed` BOOLEAN DEFAULT FALSE,
    `email_sent` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`wishlist_id`) REFERENCES `wishlist`(`id`) ON DELETE CASCADE,
    INDEX `idx_alerts_wishlist` (`wishlist_id`),
    INDEX `idx_alerts_type` (`alert_type`),
    INDEX `idx_alerts_unread` (`is_read`),
    INDEX `idx_alerts_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MARKETPLACE API CONFIGURATIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `marketplace_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ebay, amazon, mercari, etc.',
    `is_enabled` BOOLEAN DEFAULT FALSE,
    `api_credentials` JSON NULL COMMENT 'Encrypted API keys and settings',
    `sync_settings` JSON NULL COMMENT 'Sync preferences and options',
    `rate_limit_settings` JSON NULL COMMENT 'Rate limiting configuration',
    `last_sync` TIMESTAMP NULL,
    `next_sync` TIMESTAMP NULL,
    `sync_frequency` INT DEFAULT 3600 COMMENT 'Sync frequency in seconds',
    `total_requests_today` INT DEFAULT 0,
    `daily_limit` INT DEFAULT 1000,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_marketplace_platform` (`platform`),
    INDEX `idx_marketplace_enabled` (`is_enabled`),
    INDEX `idx_marketplace_next_sync` (`next_sync`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SYNC JOB QUEUE
-- =============================================================================

CREATE TABLE IF NOT EXISTS `sync_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_type` ENUM('full_sync', 'price_check', 'single_item', 'bulk_import') NOT NULL,
    `platform` VARCHAR(50) NOT NULL,
    `status` ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `priority` TINYINT DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `payload` JSON NULL COMMENT 'Job-specific data',
    `progress` TINYINT DEFAULT 0 COMMENT 'Completion percentage',
    `error_message` TEXT NULL,
    `items_processed` INT DEFAULT 0,
    `items_total` INT DEFAULT 0,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_sync_jobs_status` (`status`),
    INDEX `idx_sync_jobs_platform` (`platform`),
    INDEX `idx_sync_jobs_priority` (`priority`),
    INDEX `idx_sync_jobs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MARKETPLACE SEARCH QUERIES (For monitoring new listings)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `marketplace_searches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT 'User who created the search',
    `platform` VARCHAR(50) NOT NULL,
    `search_query` VARCHAR(500) NOT NULL,
    `search_filters` JSON NULL COMMENT 'Additional filters (price range, condition, etc.)',
    `max_price` DECIMAL(10,2) NULL,
    `min_price` DECIMAL(10,2) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `check_frequency` INT DEFAULT 3600 COMMENT 'How often to check in seconds',
    `last_checked` TIMESTAMP NULL,
    `last_result_count` INT DEFAULT 0,
    `notification_enabled` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_searches_platform` (`platform`),
    INDEX `idx_searches_active` (`is_active`),
    INDEX `idx_searches_user` (`user_id`),
    INDEX `idx_searches_last_checked` (`last_checked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MARKETPLACE LISTINGS (Cache found items)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `marketplace_listings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `search_id` INT NOT NULL,
    `external_id` VARCHAR(100) NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `condition_info` VARCHAR(100) NULL,
    `seller_info` VARCHAR(200) NULL,
    `listing_url` TEXT NOT NULL,
    `image_url` TEXT NULL,
    `description` TEXT NULL,
    `location` VARCHAR(200) NULL,
    `shipping_cost` DECIMAL(10,2) NULL,
    `listing_type` VARCHAR(50) NULL COMMENT 'auction, buy_it_now, etc.',
    `end_time` TIMESTAMP NULL,
    `is_new_listing` BOOLEAN DEFAULT TRUE,
    `is_dismissed` BOOLEAN DEFAULT FALSE,
    `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`search_id`) REFERENCES `marketplace_searches`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_listing` (`search_id`, `external_id`),
    INDEX `idx_listings_search` (`search_id`),
    INDEX `idx_listings_external_id` (`external_id`),
    INDEX `idx_listings_price` (`price`),
    INDEX `idx_listings_new` (`is_new_listing`),
    INDEX `idx_listings_first_seen` (`first_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- API RATE LIMITING TRACKING
-- =============================================================================

CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(50) NOT NULL,
    `endpoint` VARCHAR(100) NULL,
    `requests_made` INT DEFAULT 0,
    `daily_limit` INT NOT NULL,
    `hourly_limit` INT NULL,
    `reset_time` TIMESTAMP NOT NULL,
    `last_request` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_limited` BOOLEAN DEFAULT FALSE,
    
    UNIQUE KEY `unique_platform_endpoint` (`platform`, `endpoint`),
    INDEX `idx_rate_limits_platform` (`platform`),
    INDEX `idx_rate_limits_reset` (`reset_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SYNC STATISTICS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `sync_statistics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(50) NOT NULL,
    `sync_date` DATE NOT NULL,
    `items_synced` INT DEFAULT 0,
    `items_updated` INT DEFAULT 0,
    `price_changes_detected` INT DEFAULT 0,
    `alerts_generated` INT DEFAULT 0,
    `api_requests_made` INT DEFAULT 0,
    `sync_duration_seconds` INT DEFAULT 0,
    `errors_encountered` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_platform_date` (`platform`, `sync_date`),
    INDEX `idx_stats_platform` (`platform`),
    INDEX `idx_stats_date` (`sync_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INSERT DEFAULT MARKETPLACE CONFIGURATIONS
-- =============================================================================

INSERT IGNORE INTO `marketplace_configs` (`platform`, `is_enabled`, `sync_settings`, `rate_limit_settings`) VALUES
('ebay', FALSE, JSON_OBJECT(
    'sync_watchlist', true,
    'monitor_prices', true,
    'auction_alerts', true,
    'auto_import', false
), JSON_OBJECT(
    'daily_limit', 5000,
    'hourly_limit', 500,
    'requests_per_second', 2
)),

('amazon', FALSE, JSON_OBJECT(
    'sync_wishlist', true,
    'monitor_prices', true,
    'track_lightning_deals', false,
    'auto_import', false
), JSON_OBJECT(
    'daily_limit', 8640,
    'hourly_limit', 360,
    'requests_per_second', 1
)),

('mercari', FALSE, JSON_OBJECT(
    'sync_liked_items', true,
    'monitor_searches', true,
    'auto_import', false
), JSON_OBJECT(
    'daily_limit', 1000,
    'hourly_limit', 100,
    'requests_per_second', 1
));

-- =============================================================================
-- USEFUL VIEWS FOR MARKETPLACE DATA
-- =============================================================================

-- View for items with recent price drops
CREATE OR REPLACE VIEW `recent_price_drops` AS
SELECT 
    w.id,
    w.title,
    w.media_type,
    w.source,
    w.current_market_price,
    w.max_price,
    ph.price as previous_price,
    (ph.price - w.current_market_price) as price_drop,
    ROUND(((ph.price - w.current_market_price) / ph.price) * 100, 2) as drop_percentage,
    w.external_url,
    w.last_price_update
FROM wishlist w
JOIN price_history ph ON w.id = ph.wishlist_id
WHERE w.date_acquired IS NULL
    AND w.current_market_price < ph.price
    AND ph.checked_at = (
        SELECT MAX(checked_at) 
        FROM price_history ph2 
        WHERE ph2.wishlist_id = w.id 
        AND ph2.checked_at < w.last_price_update
    )
    AND w.last_price_update >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY drop_percentage DESC;

-- View for items approaching target price
CREATE OR REPLACE VIEW `approaching_target_price` AS
SELECT 
    w.id,
    w.title,
    w.media_type,
    w.source,
    w.current_market_price,
    w.max_price,
    (w.current_market_price - w.max_price) as price_difference,
    ROUND(((w.current_market_price - w.max_price) / w.max_price) * 100, 2) as over_target_percentage,
    w.external_url,
    w.last_price_check
FROM wishlist w
WHERE w.date_acquired IS NULL
    AND w.max_price IS NOT NULL
    AND w.current_market_price IS NOT NULL
    AND w.current_market_price <= (w.max_price * 1.20)  -- Within 20% of target
ORDER BY over_target_percentage ASC;

-- View for sync performance metrics
CREATE OR REPLACE VIEW `sync_performance` AS
SELECT 
    platform,
    DATE(sync_date) as date,
    SUM(items_synced) as total_synced,
    SUM(items_updated) as total_updated,
    SUM(price_changes_detected) as price_changes,
    SUM(alerts_generated) as alerts,
    SUM(api_requests_made) as api_calls,
    AVG(sync_duration_seconds) as avg_duration,
    SUM(errors_encountered) as errors
FROM sync_statistics
WHERE sync_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY platform, DATE(sync_date)
ORDER BY date DESC, platform;

-- =============================================================================
-- STORED PROCEDURES FOR MARKETPLACE OPERATIONS
-- =============================================================================

DELIMITER //

-- Procedure to check if an item needs price update
CREATE OR REPLACE PROCEDURE `CheckPriceUpdateNeeded`(
    IN item_id INT,
    OUT needs_update BOOLEAN
)
BEGIN
    DECLARE last_check TIMESTAMP;
    DECLARE sync_frequency INT DEFAULT 3600;
    
    SELECT 
        w.last_price_check,
        COALESCE(mc.sync_frequency, 3600)
    INTO last_check, sync_frequency
    FROM wishlist w
    LEFT JOIN marketplace_configs mc ON mc.platform = w.source
    WHERE w.id = item_id;
    
    SET needs_update = (
        last_check IS NULL OR 
        last_check < DATE_SUB(NOW(), INTERVAL sync_frequency SECOND)
    );
END//

-- Procedure to log price change and create alert if needed
CREATE OR REPLACE PROCEDURE `LogPriceChange`(
    IN wishlist_item_id INT,
    IN new_price DECIMAL(10,2),
    IN price_source VARCHAR(50),
    IN source_url TEXT
)
BEGIN
    DECLARE old_price DECIMAL(10,2);
    DECLARE target_price DECIMAL(10,2);
    DECLARE price_drop DECIMAL(10,2);
    
    -- Get current price and target
    SELECT current_market_price, max_price 
    INTO old_price, target_price
    FROM wishlist 
    WHERE id = wishlist_item_id;
    
    -- Only proceed if price actually changed
    IF old_price IS NULL OR old_price != new_price THEN
        
        -- Update wishlist item
        UPDATE wishlist 
        SET current_market_price = new_price,
            last_price_update = NOW(),
            last_price_check = NOW()
        WHERE id = wishlist_item_id;
        
        -- Log price history
        INSERT INTO price_history (
            wishlist_id, price, source, source_url, checked_at
        ) VALUES (
            wishlist_item_id, new_price, price_source, source_url, NOW()
        );
        
        -- Check for price alerts
        IF old_price IS NOT NULL AND new_price < old_price THEN
            SET price_drop = old_price - new_price;
            
            -- Create price drop alert
            INSERT INTO price_alerts (
                wishlist_id, alert_type, old_price, new_price, 
                message, created_at
            ) VALUES (
                wishlist_item_id, 'price_drop', old_price, new_price,
                CONCAT('Price dropped by , ROUND(price_drop, 2)), NOW()
            );
        END IF;
        
        -- Check if target price reached
        IF target_price IS NOT NULL AND new_price <= target_price THEN
            INSERT INTO price_alerts (
                wishlist_id, alert_type, new_price, threshold_price,
                message, created_at
            ) VALUES (
                wishlist_item_id, 'target_reached', new_price, target_price,
                CONCAT('Target price of , target_price, ' reached!'), NOW()
            );
        END IF;
        
    ELSE
        -- Just update last check time
        UPDATE wishlist 
        SET last_price_check = NOW()
        WHERE id = wishlist_item_id;
    END IF;
END//

-- Procedure to clean old price history data
CREATE OR REPLACE PROCEDURE `CleanOldPriceHistory`(
    IN days_to_keep INT DEFAULT 90
)
BEGIN
    DELETE FROM price_history 
    WHERE checked_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    SELECT ROW_COUNT() as records_deleted;
END//

-- Procedure to get items that need price checking
CREATE OR REPLACE PROCEDURE `GetItemsForPriceCheck`(
    IN platform_name VARCHAR(50),
    IN batch_size INT DEFAULT 50
)
BEGIN
    SELECT 
        w.id,
        w.external_id,
        w.title,
        w.current_market_price,
        w.last_price_check,
        w.external_url,
        mc.sync_frequency
    FROM wishlist w
    JOIN marketplace_configs mc ON mc.platform = w.source
    WHERE w.source = platform_name
        AND w.date_acquired IS NULL
        AND w.external_id IS NOT NULL
        AND mc.is_enabled = TRUE
        AND (
            w.last_price_check IS NULL OR 
            w.last_price_check < DATE_SUB(NOW(), INTERVAL mc.sync_frequency SECOND)
        )
    ORDER BY 
        CASE WHEN w.last_price_check IS NULL THEN 0 ELSE 1 END,
        w.last_price_check ASC
    LIMIT batch_size;
END//

-- Procedure to update sync statistics
CREATE OR REPLACE PROCEDURE `UpdateSyncStats`(
    IN platform_name VARCHAR(50),
    IN items_synced INT,
    IN items_updated INT,
    IN price_changes INT,
    IN alerts_created INT,
    IN api_requests INT,
    IN duration_seconds INT,
    IN error_count INT
)
BEGIN
    INSERT INTO sync_statistics (
        platform, sync_date, items_synced, items_updated,
        price_changes_detected, alerts_generated, api_requests_made,
        sync_duration_seconds, errors_encountered
    ) VALUES (
        platform_name, CURDATE(), items_synced, items_updated,
        price_changes, alerts_created, api_requests,
        duration_seconds, error_count
    )
    ON DUPLICATE KEY UPDATE
        items_synced = items_synced + VALUES(items_synced),
        items_updated = items_updated + VALUES(items_updated),
        price_changes_detected = price_changes_detected + VALUES(price_changes_detected),
        alerts_generated = alerts_generated + VALUES(alerts_generated),
        api_requests_made = api_requests_made + VALUES(api_requests_made),
        sync_duration_seconds = sync_duration_seconds + VALUES(sync_duration_seconds),
        errors_encountered = errors_encountered + VALUES(errors_encountered);
END//

DELIMITER ;

-- =============================================================================
-- TRIGGERS FOR AUTOMATIC OPERATIONS
-- =============================================================================

DELIMITER //

-- Trigger to automatically create sync job when new search is added
CREATE TRIGGER `create_sync_job_for_new_search`
AFTER INSERT ON `marketplace_searches`
FOR EACH ROW
BEGIN
    IF NEW.is_active = TRUE THEN
        INSERT INTO sync_jobs (
            job_type, platform, status, priority, payload, created_at
        ) VALUES (
            'single_item', NEW.platform, 'pending', 3,
            JSON_OBJECT('search_id', NEW.id, 'type', 'new_search'),
            NOW()
        );
    END IF;
END//

-- Trigger to log when marketplace config is updated
CREATE TRIGGER `log_marketplace_config_changes`
AFTER UPDATE ON `marketplace_configs`
FOR EACH ROW
BEGIN
    IF OLD.is_enabled != NEW.is_enabled OR OLD.sync_frequency != NEW.sync_frequency THEN
        INSERT INTO sync_jobs (
            job_type, platform, status, priority, payload, created_at
        ) VALUES (
            'full_sync', NEW.platform, 'pending', 2,
            JSON_OBJECT('reason', 'config_updated', 'old_enabled', OLD.is_enabled, 'new_enabled', NEW.is_enabled),
            NOW()
        );
    END IF;
END//

-- Trigger to clean up related data when wishlist item is deleted
CREATE TRIGGER `cleanup_marketplace_data_on_wishlist_delete`
BEFORE DELETE ON `wishlist`
FOR EACH ROW
BEGIN
    DELETE FROM price_history WHERE wishlist_id = OLD.id;
    DELETE FROM price_alerts WHERE wishlist_id = OLD.id;
END//

DELIMITER ;

-- =============================================================================
-- SAMPLE DATA FOR TESTING
-- =============================================================================

-- Insert sample marketplace searches (for testing)
INSERT IGNORE INTO `marketplace_searches` (
    `platform`, `search_query`, `search_filters`, `max_price`, `is_active`
) VALUES 
('ebay', 'Batman Year One comic', JSON_OBJECT('condition', 'used', 'category', 'comics'), 25.00, TRUE),
('ebay', 'Star Wars Blu-ray collection', JSON_OBJECT('condition', 'new', 'category', 'movies'), 50.00, TRUE),
('amazon', 'Dune book series', JSON_OBJECT('format', 'paperback'), 15.00, TRUE);

-- Insert sample rate limiting data
INSERT IGNORE INTO `api_rate_limits` (
    `platform`, `requests_made`, `daily_limit`, `hourly_limit`, `reset_time`
) VALUES 
('ebay', 0, 5000, 500, DATE_ADD(NOW(), INTERVAL 1 DAY)),
('amazon', 0, 8640, 360, DATE_ADD(NOW(), INTERVAL 1 DAY)),
('mercari', 0, 1000, 100, DATE_ADD(NOW(), INTERVAL 1 DAY));

-- =============================================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =============================================================================

-- Additional indexes for better query performance
CREATE INDEX IF NOT EXISTS `idx_wishlist_source_status` ON `wishlist` (`source`, `date_acquired`);
CREATE INDEX IF NOT EXISTS `idx_price_history_compound` ON `price_history` (`wishlist_id`, `checked_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_alerts_unread_date` ON `price_alerts` (`is_read`, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_sync_jobs_status_priority` ON `sync_jobs` (`status`, `priority`);

-- =============================================================================
-- COMMENTS AND DOCUMENTATION
-- =============================================================================

-- Table comments for documentation
ALTER TABLE `price_history` COMMENT = 'Tracks price changes over time for wishlist items from various marketplaces';
ALTER TABLE `price_alerts` COMMENT = 'Stores price drop alerts and notifications for users';
ALTER TABLE `marketplace_configs` COMMENT = 'Configuration settings for each marketplace integration';
ALTER TABLE `sync_jobs` COMMENT = 'Queue system for managing marketplace sync operations';
ALTER TABLE `marketplace_searches` COMMENT = 'Saved searches for monitoring new listings';
ALTER TABLE `marketplace_listings` COMMENT = 'Cache of found marketplace listings from searches';
ALTER TABLE `sync_statistics` COMMENT = 'Daily statistics for marketplace sync operations';

-- =============================================================================
-- MAINTENANCE QUERIES (Run these periodically)
-- =============================================================================

/*
-- Clean old price history (run weekly)
CALL CleanOldPriceHistory(90);

-- Clean old sync jobs (run daily)
DELETE FROM sync_jobs 
WHERE status IN ('completed', 'failed') 
AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Clean dismissed alerts (run monthly)
DELETE FROM price_alerts 
WHERE is_dismissed = TRUE 
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Reset daily API rate limits (run daily at midnight)
UPDATE api_rate_limits 
SET requests_made = 0, is_limited = FALSE, reset_time = DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE reset_time <= NOW();

-- Archive old statistics (run monthly)
-- You might want to move old statistics to an archive table

-- Update marketplace config last sync times
UPDATE marketplace_configs 
SET last_sync = NOW(), next_sync = DATE_ADD(NOW(), INTERVAL sync_frequency SECOND)
WHERE is_enabled = TRUE;
*/