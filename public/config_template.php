<?php
/**
 * Media Collection System Configuration
 * Copy this file to config.php and update the settings below
 */

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================

// Database connection settings
define('DB_HOST', 'localhost');           // Database server (usually localhost)
define('DB_NAME', 'media_collection');    // Database name
define('DB_USER', 'root');                // Database username
define('DB_PASS', '');                    // Database password
define('DB_PORT', 3306);                  // Database port (default: 3306)

// Database options
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// =============================================================================
// APPLICATION SETTINGS
// =============================================================================

// Application details
define('APP_NAME', 'My Media Collection');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/media-collection'); // Your app URL
define('APP_TIMEZONE', 'America/New_York');            // Your timezone

// Debug and development
define('DEBUG_MODE', true);               // Set to false in production
define('ALLOW_DEMO_LOGIN', true);         // Set to false in production
define('LOG_QUERIES', false);             // Log database queries for debugging

// =============================================================================
// SECURITY SETTINGS
// =============================================================================

// Session configuration
define('SESSION_LIFETIME', 86400);        // Session timeout (24 hours)
define('SESSION_NAME', 'MEDIACOLLECTION');
define('SECURE_SESSIONS', false);         // Set to true if using HTTPS

// Password requirements
define('MIN_PASSWORD_LENGTH', 8);
define('REQUIRE_SPECIAL_CHARS', true);
define('REQUIRE_NUMBERS', true);

// Security keys (change these for production!)
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here!');
define('HASH_SALT', 'your-unique-salt-here');

// =============================================================================
// FILE UPLOAD SETTINGS
// =============================================================================

// Upload directories (relative to root)
define('UPLOAD_DIR', 'uploads/');
define('POSTER_DIR', 'uploads/posters/');
define('BACKUP_DIR', 'backups/');
define('CACHE_DIR', 'cache/');

// File size limits (in bytes)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);      // 10MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);      // 5MB

// Allowed file types
define('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp');
define('ALLOWED_BACKUP_TYPES', 'sql,csv,json,xml');

// =============================================================================
// API INTEGRATIONS
// =============================================================================

// External API keys (optional - for enhanced functionality)
define('TMDB_API_KEY', '');               // The Movie Database API
define('GOOGLE_BOOKS_API_KEY', '');       // Google Books API
define('IGDB_API_KEY', '');               // Internet Game Database
define('MUSICBRAINZ_USER_AGENT', '');     // MusicBrainz user agent

// Barcode scanning
define('ENABLE_BARCODE_LOOKUP', true);
define('BARCODE_API_KEY', '');            // UPC Database API key

// =============================================================================
// GEOLOCATION & ACCESS CONTROL
// =============================================================================

// Geographic restrictions (optional)
define('ENABLE_GEO_RESTRICTIONS', false);
define('ALLOWED_COUNTRIES', 'US,CA,GB');  // Comma-separated country codes
define('GEO_API_KEY', '');                // IP geolocation service key

// IP restrictions
define('ENABLE_IP_WHITELIST', false);
define('ALLOWED_IPS', '127.0.0.1,::1');  // Comma-separated IP addresses

// =============================================================================
// FEATURE FLAGS
// =============================================================================

// Core features
define('ENABLE_CATEGORIES', true);
define('ENABLE_WISHLIST', true);
define('ENABLE_RATINGS', true);
define('ENABLE_REVIEWS', true);
define('ENABLE_SHARING', false);          // Social sharing features

// Advanced features
define('ENABLE_BARCODE_SCANNING', true);
define('ENABLE_AUTO_IMPORT', true);       // Import from other systems
define('ENABLE_BACKUP_RESTORE', true);
define('ENABLE_MULTI_USER', false);       // Multiple user accounts
define('ENABLE_STATISTICS', true);
define('ENABLE_REPORTS', true);

// Experimental features
define('ENABLE_AI_RECOMMENDATIONS', false);
define('ENABLE_PRICE_TRACKING', false);
define('ENABLE_MARKETPLACE_INTEGRATION', false);

// =============================================================================
// EMAIL CONFIGURATION (for notifications)
// =============================================================================

define('ENABLE_EMAIL', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');         // tls or ssl
define('FROM_EMAIL', 'noreply@yoursite.com');
define('FROM_NAME', 'Media Collection System');

// =============================================================================
// CACHING CONFIGURATION
// =============================================================================

define('ENABLE_CACHING', true);
define('CACHE_LIFETIME', 3600);           // 1 hour in seconds
define('CACHE_TYPE', 'file');             // file, redis, memcached

// Redis settings (if using Redis cache)
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_DATABASE', 0);

// =============================================================================
// PERFORMANCE SETTINGS
// =============================================================================

// Pagination
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// Image processing
define('ENABLE_IMAGE_OPTIMIZATION', true);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 200);
define('POSTER_MAX_WIDTH', 500);
define('POSTER_MAX_HEIGHT', 750);

// API rate limiting
define('ENABLE_RATE_LIMITING', true);
define('API_RATE_LIMIT', 100);            // Requests per hour per IP

// =============================================================================
// LOGGING CONFIGURATION
// =============================================================================

define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'INFO');              // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', 'logs/app.log');
define('MAX_LOG_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_ROTATION', true);

// What to log
define('LOG_LOGINS', true);
define('LOG_ERRORS', true);
define('LOG_API_CALLS', false);
define('LOG_DATABASE_QUERIES', false);

// =============================================================================
// BACKUP CONFIGURATION
// =============================================================================

define('AUTO_BACKUP', false);
define('BACKUP_FREQUENCY', 'weekly');     // daily, weekly, monthly
define('BACKUP_RETENTION', 30);           // Days to keep backups
define('BACKUP_COMPRESSION', true);

// =============================================================================
// NOTIFICATION SETTINGS
// =============================================================================

define('ENABLE_NOTIFICATIONS', false);
define('NOTIFY_NEW_ITEMS', true);
define('NOTIFY_WISHLIST_AVAILABLE', true);
define('NOTIFY_PRICE_CHANGES', false);
define('NOTIFY_BACKUP_COMPLETE', true);

// =============================================================================
// CUSTOMIZATION
// =============================================================================

// Theme and appearance
define('DEFAULT_THEME', 'default');
define('ALLOW_THEME_SWITCHING', true);
define('CUSTOM_CSS_FILE', '');            // Path to custom CSS file

// Currency and formatting
define('DEFAULT_CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Default categories (will be created on first run)
define('DEFAULT_CATEGORIES', json_encode([
    'movies' => ['Action', 'Comedy', 'Drama', 'Horror', 'Sci-Fi'],
    'books' => ['Fiction', 'Non-Fiction', 'Biography', 'History', 'Science'],
    'comics' => ['Marvel', 'DC', 'Independent', 'Manga', 'Graphic Novels'],
    'music' => ['Rock', 'Pop', 'Classical', 'Jazz', 'Electronic'],
    'games' => ['Action', 'RPG', 'Strategy', 'Sports', 'Puzzle']
]));

// =============================================================================
// DEVELOPMENT SETTINGS (remove in production)
// =============================================================================

if (DEBUG_MODE) {
    // Show all errors in debug mode
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    // Development database (optional)
    // define('DB_NAME', 'media_collection_dev');
    
    // Disable caching in development
    // define('ENABLE_CACHING', false);
}

// =============================================================================
// CONSTANTS AND CALCULATED VALUES
// =============================================================================

// Don't edit these - they're calculated from the settings above
define('UPLOAD_PATH', dirname(__FILE__) . '/' . UPLOAD_DIR);
define('POSTER_PATH', dirname(__FILE__) . '/' . POSTER_DIR);
define('BACKUP_PATH', dirname(__FILE__) . '/' . BACKUP_DIR);
define('CACHE_PATH', dirname(__FILE__) . '/' . CACHE_DIR);

// Database DSN
define('DB_DSN', "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET);

// Application paths
define('ROOT_PATH', dirname(__FILE__));
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('API_PATH', ROOT_PATH . '/api');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// =============================================================================
// VALIDATION
// =============================================================================

// Validate critical settings
if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER)) {
    die('Error: Database configuration is incomplete. Please check your config.php file.');
}

if (DEBUG_MODE && !defined('DB_PASS')) {
    define('DB_PASS', ''); // Allow empty password in debug mode
}

// Create required directories
$requiredDirs = [UPLOAD_PATH, POSTER_PATH, BACKUP_PATH, CACHE_PATH];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(APP_TIMEZONE);
}

// Set session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    session_name(SESSION_NAME);
}

// =============================================================================
// SUCCESS MESSAGE
// =============================================================================

if (DEBUG_MODE) {
    error_log("Media Collection Config Loaded Successfully");
    error_log("App: " . APP_NAME . " v" . APP_VERSION);
    error_log("Database: " . DB_HOST . "/" . DB_NAME);
    error_log("Debug Mode: " . (DEBUG_MODE ? "ON" : "OFF"));
}

?>

<!-- 
=============================================================================
CONFIGURATION CHECKLIST
=============================================================================

□ 1. Copy this file to 'config.php'
□ 2. Update database settings (DB_HOST, DB_NAME, DB_USER, DB_PASS)
□ 3. Set your application URL (APP_URL)
□ 4. Configure timezone (APP_TIMEZONE)
□ 5. Change security keys (ENCRYPTION_KEY, HASH_SALT)
□ 6. Set DEBUG_MODE to false for production
□ 7. Configure file upload directories
□ 8. Set up any API keys you plan to use
□ 9. Test database connection
□ 10. Import database schema (schema.sql)

=============================================================================
QUICK START FOR LOCALHOST
=============================================================================

For local development, you can usually just update these settings:

- DB_NAME: Create a database called 'media_collection'
- DB_USER: Usually 'root' for XAMPP/WAMP
- DB_PASS: Usually empty for XAMPP/WAMP
- APP_URL: http://localhost/your-folder-name

=============================================================================
-->