<?php
// Enhanced configuration with performance optimizations

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'collector');

// Database Connection Options for Performance
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => true, // Use persistent connections
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// API Keys
define('OMDB_API_KEY', 'YOUR_OMDB_KEY');
define('GOOGLE_BOOKS_KEY', 'YOUR_GOOGLE_BOOKS_KEY');
define('DISCOGS_TOKEN', 'YOUR_DISCOGS_TOKEN');
define('COMICVINE_KEY', 'YOUR_COMICVINE_KEY');

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour
define('CACHE_DIR', __DIR__ . '/cache/');

// Security Configuration
define('SESSION_TIMEOUT', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Performance Settings
define('MAX_ITEMS_PER_PAGE', 100);
define('DEFAULT_ITEMS_PER_PAGE', 20);
define('API_TIMEOUT', 10); // seconds
define('API_CONNECT_TIMEOUT', 5); // seconds

// Geolocation Settings
define('GEO_CACHE_DURATION', 86400); // 24 hours
define('GEO_API_TIMEOUT', 5);
define('ALLOWED_COUNTRIES', ['US', 'CA']);

// Error Handling
define('ENABLE_ERROR_LOGGING', true);
define('LOG_FILE', __DIR__ . '/logs/app.log');

// Application Settings
define('APP_NAME', 'Media Collection Manager');
define('APP_VERSION', '2.0.0');
define('MAINTENANCE_MODE', false);

// Helper function to create database connection with retry logic
function getDbConnection($retries = 3) {
    $attempt = 0;
    
    while ($attempt < $retries) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                DB_OPTIONS
            );
            return $pdo;
        } catch (PDOException $e) {
            $attempt++;
            if ($attempt >= $retries) {
                error_log("Database connection failed after {$retries} attempts: " . $e->getMessage());
                throw $e;
            }
            usleep(100000); // Wait 100ms before retry
        }
    }
}

// Simple cache helper
class SimpleCache {
    private static $cacheDir;
    
    public static function init() {
        self::$cacheDir = CACHE_DIR;
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key) {
        if (!CACHE_ENABLED) return null;
        
        $file = self::$cacheDir . md5($key) . '.cache';
        if (!file_exists($file)) return null;
        
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public static function set($key, $value, $duration = null) {
        if (!CACHE_ENABLED) return false;
        
        $duration = $duration ?? CACHE_DURATION;
        $file = self::$cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + $duration
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    public static function delete($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    public static function clear() {
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
}

// Enhanced error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (ENABLE_ERROR_LOGGING) {
        $logDir = dirname(LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Error {$errno}: {$errstr} in {$errfile} on line {$errline}\n";
        error_log($logMessage, 3, LOG_FILE);
    }
    
    // Don't display errors in production
    return true;
}

// Enhanced session management
function initSecureSession() {
    // Configure session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        session_start();
    }
    
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Rate limiting helper
class RateLimiter {
    private static $attempts = [];
    
    public static function checkLimit($identifier, $maxAttempts = null, $window = null) {
        $maxAttempts = $maxAttempts ?? MAX_LOGIN_ATTEMPTS;
        $window = $window ?? LOCKOUT_DURATION;
        
        $now = time();
        
        if (!isset(self::$attempts[$identifier])) {
            self::$attempts[$identifier] = [];
        }
        
        // Remove old attempts outside the window
        self::$attempts[$identifier] = array_filter(
            self::$attempts[$identifier],
            function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            }
        );
        
        return count(self::$attempts[$identifier]) < $maxAttempts;
    }
    
    public static function recordAttempt($identifier) {
        if (!isset(self::$attempts[$identifier])) {
            self::$attempts[$identifier] = [];
        }
        self::$attempts[$identifier][] = time();
    }
}

// Initialize components
if (CACHE_ENABLED) {
    SimpleCache::init();
}

if (ENABLE_ERROR_LOGGING) {
    set_error_handler('customErrorHandler');
}

// Check maintenance mode
if (MAINTENANCE_MODE && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(503);
    die('Site is temporarily down for maintenance. Please try again later.');
}
?>