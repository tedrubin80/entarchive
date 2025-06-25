<?php
/**
 * Enhanced Personal Media Management Dashboard with Secure Private Config
 * File: public/enhanced_media_dashboard.php
 * Updated to load config from /private directory
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Helper function for safe file inclusion
function safeInclude($filename) {
    $paths_to_try = [
        __DIR__ . '/' . $filename,                    // Same directory
        __DIR__ . '/../' . $filename,                 // Parent directory
        dirname(__DIR__) . '/' . $filename,           // Parent directory (alternative)
        $_SERVER['DOCUMENT_ROOT'] . '/' . $filename   // Document root
    ];
    
    foreach ($paths_to_try as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
}

// Helper function to load secure configuration from /private directory
function loadSecureConfig() {
    $config_paths = [
        __DIR__ . '/private/config.php',              // Same level private folder
        __DIR__ . '/../private/config.php',           // Parent level private folder
        dirname(__DIR__) . '/private/config.php',     // Alternative parent
        $_SERVER['DOCUMENT_ROOT'] . '/private/config.php', // Document root private
        dirname($_SERVER['DOCUMENT_ROOT']) . '/private/config.php' // Above document root
    ];
    
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return $path;
        }
    }
    
    // Fallback to any config.php file
    $fallback_paths = [
        __DIR__ . '/config.php',
        __DIR__ . '/../config.php',
        dirname(__DIR__) . '/config.php'
    ];
    
    foreach ($fallback_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return $path;
        }
    }
    
    return false;
}

// Debug logging function
function debugLog($message, $data = null) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $logMessage = "[DASHBOARD] " . date('Y-m-d H:i:s') . " - " . $message;
        if ($data !== null) {
            $logMessage .= ' - ' . print_r($data, true);
        }
        error_log($logMessage);
    }
}

// Initialize system status
$systemStatus = [
    'database' => false, 
    'auth' => true, 
    'config' => false,
    'collecting' => true
];

$errors = [];
$stats = [
    'movies' => 0, 'books' => 0, 'comics' => 0, 
    'music' => 0, 'games' => 0, 'total_items' => 0,
    'total_value' => 0, 'wishlist_items' => 0
];

debugLog("Dashboard initialization started");

// Load secure configuration
$config_path = loadSecureConfig();
if ($config_path) {
    $systemStatus['config'] = true;
    debugLog("Configuration loaded successfully from: " . $config_path);
    
    // Verify essential constants are defined
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($required_constants as $constant) {
        if (!defined($constant)) {
            $errors[] = "Missing required configuration: $constant";
            $systemStatus['config'] = false;
        }
    }
} else {
    $errors[] = "Configuration file not found. Please ensure config.php exists in /private directory or project root.";
    $systemStatus['config'] = false;
    debugLog("Configuration file not found in any expected location");
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: user_login.php");
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: user_login.php?logout=1");
    exit;
}

// Get current tab from URL parameter
$currentTab = $_GET['tab'] ?? 'media';

debugLog("Dashboard loaded for user: " . ($_SESSION['admin_user'] ?? 'unknown'));

// Database connection using secure config
$pdo = null;
if ($systemStatus['config']) {
    try {
        // Try to use the helper function if available
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
            $systemStatus['database'] = true;
            debugLog("Database connected via getDBConnection()");
        } else {
            // Direct PDO connection
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $systemStatus['database'] = true;
            debugLog("Database connected via direct PDO");
        }
        
        // Test database connection
        $pdo->query("SELECT 1");
        
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
        $systemStatus['database'] = false;
        debugLog("Database error: " . $e->getMessage());
    }
} else {
    $errors[] = "Cannot connect to database: Configuration not loaded";
    debugLog("Database connection skipped due to missing configuration");
}

// Get statistics
if ($systemStatus['database'] && $pdo) {
    try {
        // Check if collection table exists
        $tables = $pdo->query("SHOW TABLES LIKE 'collection'")->fetchAll();
        
        if (count($tables) > 0) {
            $sql = "SELECT 
                        COUNT(CASE WHEN media_type = 'movie' THEN 1 END) as movies,
                        COUNT(CASE WHEN media_type = 'book' THEN 1 END) as books,
                        COUNT(CASE WHEN media_type = 'comic' THEN 1 END) as comics,
                        COUNT(CASE WHEN media_type = 'music' THEN 1 END) as music,
                        COUNT(CASE WHEN media_type = 'game' THEN 1 END) as games,
                        COUNT(*) as total_items,
                        COALESCE(SUM(current_value), 0) as total_value
                    FROM collection";
            $stmt = $pdo->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $stats = array_merge($stats, $result);
                debugLog("Statistics loaded successfully", $stats);
            }

            // Get wishlist count if table exists
            $wishlistTables = $pdo->query("SHOW TABLES LIKE 'wishlist'")->fetchAll();
            if (count($wishlistTables) > 0) {
                $wishlistStmt = $pdo->query("SELECT COUNT(*) as wishlist_items FROM wishlist WHERE date_acquired IS NULL");
                $wishlistResult = $wishlistStmt->fetch(PDO::FETCH_ASSOC);
                if ($wishlistResult) {
                    $stats['wishlist_items'] = $wishlistResult['wishlist_items'];
                }
            }
        } else {
            debugLog("Collection table does not exist - database may not be initialized");
        }
    } catch (Exception $e) {
        debugLog("Statistics error: " . $e->getMessage());
        // Don't add to errors array as this is not critical
    }
}

// RSS Feed Functions
function fetchRSSFeed($url, $limit = 5) {
    try {
        $timeout = defined('RSS_TIMEOUT') ? RSS_TIMEOUT : 10;
        $userAgent = (defined('APP_NAME') ? APP_NAME : 'Media Collection') . '/1.0';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => $userAgent,
                'method' => 'GET'
            ]
        ]);
        
        $rss = @file_get_contents($url, false, $context);
        if (!$rss) {
            return ['error' => 'Failed to fetch RSS feed'];
        }
        
        $xml = @simplexml_load_string($rss);
        if (!$xml) {
            return ['error' => 'Invalid RSS format'];
        }
        
        $items = [];
        $count = 0;
        
        foreach ($xml->channel->item as $item) {
            if ($count >= $limit) break;
            
            $items[] = [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'description' => strip_tags((string)$item->description),
                'pubDate' => (string)$item->pubDate,
                'date' => date('M j, Y', strtotime((string)$item->pubDate))
            ];
            $count++;
        }
        
        return ['items' => $items];
    } catch (Exception $e) {
        return ['error' => 'RSS feed error: ' . $e->getMessage()];
    }
}

// Criterion Collection API Integration
function fetchCriterionReleases($limit = 5) {
    try {
        // Check if we have local Criterion data (from Node.js scraper)
        $criterionFile = dirname(__DIR__) . '/data/criterion_latest.json';
        
        if (file_exists($criterionFile)) {
            $data = json_decode(file_get_contents($criterionFile), true);
            if ($data && isset($data['films'])) {
                return array_slice($data['films'], 0, $limit);
            }
        }
        
        // Fallback: Mock data for demonstration
        return [
            [
                'title' => 'The Rules of the Game',
                'director' => 'Jean Renoir',
                'spine_number' => '1234',
                'release_date' => '2024-01-15',
                'format' => 'Blu-ray',
                'url' => 'https://www.criterion.com/films/123-the-rules-of-the-game'
            ],
            [
                'title' => 'Seven Samurai',
                'director' => 'Akira Kurosawa',
                'spine_number' => '1235',
                'release_date' => '2024-01-22',
                'format' => '4K UHD',
                'url' => 'https://www.criterion.com/films/124-seven-samurai'
            ],
            [
                'title' => 'The 400 Blows',
                'director' => 'François Truffaut',
                'spine_number' => '1236',
                'release_date' => '2024-02-01',
                'format' => 'Blu-ray',
                'url' => 'https://www.criterion.com/films/125-the-400-blows'
            ]
        ];
    } catch (Exception $e) {
        debugLog("Criterion fetch error: " . $e->getMessage());
        return [];
    }
}

// Check if collecting system is accessible
function checkCollectingSystem() {
    if (!defined('COLLECTING_SYSTEM_URL')) return false;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'HEAD'
        ]
    ]);
    
    $headers = @get_headers(COLLECTING_SYSTEM_URL, 1, $context);
    return $headers && strpos($headers[0], '200') !== false;
}

// Fetch data for widgets (only for media tab)
$rssData = [];
$criterionReleases = [];
$collectingAvailable = false;

if ($currentTab === 'media' || !isset($_GET['tab'])) {
    $rssUrl = defined('RSS_THE_NUMBERS_URL') ? RSS_THE_NUMBERS_URL : 'https://www.the-numbers.com/news/rss.php';
    $rssData = fetchRSSFeed($rssUrl, 4);
    $criterionReleases = fetchCriterionReleases(4);
    debugLog("Widgets data loaded for media tab");
}

if ($currentTab === 'collecting' || (defined('ENABLE_COLLECTING_TAB') && ENABLE_COLLECTING_TAB)) {
    $collectingAvailable = checkCollectingSystem();
    $systemStatus['collecting'] = $collectingAvailable;
    debugLog("Collecting system status: " . ($collectingAvailable ? 'available' : 'unavailable'));
}

$currentUser = $_SESSION['admin_user'] ?? 'User';

// Set timezone if defined
if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('APP_NAME') ? APP_NAME : 'Media Collection Dashboard' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .header-subtitle {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .status-indicators {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }
        
        /* Tab Navigation */
        .tab-navigation {
            max-width: 1400px;
            margin: 0 auto 2rem;
            padding: 0 2rem;
        }
        
        .tab-buttons {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 0.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            flex-wrap: wrap;
        }
        
        .tab-button {
            flex: 1;
            min-width: 150px;
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }
        
        .tab-button.active,
        .tab-button:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }
        
        .tab-button.active {
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .tab-button i {
            font-size: 1.2rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 2rem;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .error-panel {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #721c24;
        }
        
        .error-panel ul {
            margin-left: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.2s;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
            color: #667eea;
        }
        
        .action-card h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .action-card p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Widgets Section */
        .widgets-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .widget {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .widget-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .widget-header i {
            font-size: 1.5rem;
            margin-right: 0.75rem;
            color: #667eea;
        }
        
        .widget-header h3 {
            color: #2c3e50;
            font-size: 1.25rem;
        }
        
        .rss-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .rss-item:last-child {
            border-bottom: none;
        }
        
        .rss-item h4 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .rss-item h4 a {
            color: inherit;
            text-decoration: none;
        }
        
        .rss-item h4 a:hover {
            color: #667eea;
        }
        
        .rss-item p {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        
        .rss-date {
            color: #adb5bd;
            font-size: 0.8rem;
        }
        
        .criterion-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .criterion-item:last-child {
            border-bottom: none;
        }
        
        .criterion-spine {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 1rem;
            min-width: 60px;
            text-align: center;
        }
        
        .criterion-details {
            flex: 1;
        }
        
        .criterion-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .criterion-director {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .criterion-format {
            color: #adb5bd;
            font-size: 0.8rem;
        }
        
        .widget-error {
            color: #dc3545;
            text-align: center;
            padding: 2rem;
            font-style: italic;
        }
        
        /* Collecting iframe integration */
        .collecting-iframe-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            height: 80vh;
            overflow: hidden;
        }
        
        .collecting-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 15px;
        }
        
        .collecting-status {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }
        
        .collecting-status i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* AI Chatbot preparation */
        .ai-coming-soon {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }
        
        .ai-coming-soon i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .ai-coming-soon h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .ai-coming-soon p {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                margin-top: 1rem;
                justify-content: center;
            }
            
            .tab-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tab-button {
                padding: 0.75rem 1rem;
                min-width: auto;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .widgets-section {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-film"></i> <?= defined('APP_NAME') ? APP_NAME : 'My Media Collection' ?></h1>
                <div class="header-subtitle">Personal Media & Collectibles Management</div>
                <div class="status-indicators">
                    <span class="status-badge <?= $systemStatus['config'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['config'] ? 'check' : 'times' ?>"></i> Config
                    </span>
                    <span class="status-badge <?= $systemStatus['database'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['database'] ? 'database' : 'times' ?>"></i> Database
                    </span>
                    <span class="status-badge <?= $collectingAvailable ? 'status-success' : 'status-warning' ?>">
                        <i class="fas fa-<?= $collectingAvailable ? 'link' : 'unlink' ?>"></i> Collecting
                    </span>
                    <span class="status-badge status-success">
                        <i class="fas fa-user-check"></i> <?= $currentUser ?>
                    </span>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="user_add_item.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Media
                </a>
                <a href="user_scanner.php" class="btn btn-secondary">
                    <i class="fas fa-qrcode"></i> Scan
                </a>
                <?php if (defined('ENABLE_MARKETPLACE_SYNC') && ENABLE_MARKETPLACE_SYNC): ?>
                <a href="user_marketplace_sync.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Sync
                </a>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <div class="tab-buttons">
            <a href="?tab=media" class="tab-button <?= $currentTab === 'media' ? 'active' : '' ?>">
                <i class="fas fa-film"></i>
                <span>Media Collection</span>
            </a>
            <?php if (defined('ENABLE_COLLECTING_TAB') && ENABLE_COLLECTING_TAB): ?>
            <a href="?tab=collecting" class="tab-button <?= $currentTab === 'collecting' ? 'active' : '' ?>">
                <i class="fas fa-star"></i>
                <span>Collectibles</span>
            </a>
            <?php endif; ?>
            <?php if (defined('ENABLE_AI_CHATBOT') && ENABLE_AI_CHATBOT): ?>
            <a href="?tab=ai" class="tab-button <?= $currentTab === 'ai' ? 'active' : '' ?>">
                <i class="fas fa-robot"></i>
                <span>AI Assistant</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="error-panel">
                <h3><i class="fas fa-exclamation-triangle"></i> System Issues</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$systemStatus['config']): ?>
                    <p><strong>Configuration Help:</strong> Ensure your config.php file exists in one of these locations:</p>
                    <ul>
                        <li>/private/config.php (recommended secure location)</li>
                        <li>../private/config.php</li>
                        <li>config.php (project root)</li>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Media Collection Tab -->
        <div id="media-tab" class="tab-content <?= $currentTab === 'media' ? 'active' : '' ?>">
            <!-- Statistics Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <span class="stat-number"><?= number_format($stats['movies']) ?></span>
                    <div class="stat-label">Movies</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <span class="stat-number"><?= number_format($stats['books']) ?></span>
                    <div class="stat-label">Books</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📖</div>
                    <span class="stat-number"><?= number_format($stats['comics']) ?></span>
                    <div class="stat-label">Comics</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎵</div>
                    <span class="stat-number"><?= number_format($stats['music']) ?></span>
                    <div class="stat-label">Music</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎮</div>
                    <span class="stat-number"><?= number_format($stats['games']) ?></span>
                    <div class="stat-label">Games</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <span class="stat-number">$<?= number_format($stats['total_value'], 2) ?></span>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="user_collection.php" class="action-card">
                    <span class="action-icon"><i class="fas fa-th-large"></i></span>
                    <h3>View Collection</h3>
                    <p>Browse your media library</p>
                </a>
                
                <a href="user_wishlist.php" class="action-card">
                    <span class="action-icon"><i class="fas fa-heart"></i></span>
                    <h3>Wishlist (<?= $stats['wishlist_items'] ?>)</h3>
                    <p>Items you want to acquire</p>
                </a>
                
                <a href="user_search.php" class="action-card">
                    <span class="action-icon"><i class="fas fa-search"></i></span>
                    <h3>Search</h3>
                    <p>Find specific items</p>
                </a>
                
                <a href="user_reports.php" class="action-card">
                    <span class="action-icon"><i class="fas fa-chart-bar"></i></span>
                    <h3>Reports</h3>
                    <p>Analytics and insights</p>
                </a>
            </div>

            <!-- Information Widgets -->
            <div class="widgets-section">
                <!-- Box Office News Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <i class="fas fa-newspaper"></i>
                        <h3>Box Office News</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (isset($rssData['error'])): ?>
                            <div class="widget-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p><?= htmlspecialchars($rssData['error']) ?></p>
                            </div>
                        <?php elseif (isset($rssData['items']) && count($rssData['items']) > 0): ?>
                            <?php foreach ($rssData['items'] as $item): ?>
                                <div class="rss-item">
                                    <h4><a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a></h4>
                                    <p><?= htmlspecialchars(substr($item['description'], 0, 150)) ?>...</p>
                                    <div class="rss-date"><?= htmlspecialchars($item['date']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="widget-error">
                                <i class="fas fa-rss"></i>
                                <p>No news items available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Criterion Collection Releases Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <i class="fas fa-film"></i>
                        <h3>Criterion Collection</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (count($criterionReleases) > 0): ?>
                            <?php foreach ($criterionReleases as $release): ?>
                                <div class="criterion-item">
                                    <div class="criterion-spine"><?= htmlspecialchars($release['spine_number']) ?></div>
                                    <div class="criterion-details">
                                        <div class="criterion-title"><?= htmlspecialchars($release['title']) ?></div>
                                        <div class="criterion-director">Dir. <?= htmlspecialchars($release['director']) ?></div>
                                        <div class="criterion-format"><?= htmlspecialchars($release['format']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="widget-error">
                                <i class="fas fa-film"></i>
                                <p>No Criterion releases available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collectibles Tab -->
        <?php if (defined('ENABLE_COLLECTING_TAB') && ENABLE_COLLECTING_TAB): ?>
        <div id="collecting-tab" class="tab-content <?= $currentTab === 'collecting' ? 'active' : '' ?>">
            <?php if ($collectingAvailable && defined('COLLECTING_SYSTEM_URL')): ?>
                <div class="collecting-iframe-container">
                    <iframe src="<?= COLLECTING_SYSTEM_URL ?>" class="collecting-iframe"></iframe>
                </div>
            <?php else: ?>
                <div class="collecting-status">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Collectibles System Unavailable</h2>
                    <p>The collectibles management system is currently not accessible.</p>
                    <?php if (defined('COLLECTING_SYSTEM_URL')): ?>
                        <p><strong>Configured URL:</strong> <?= htmlspecialchars(COLLECTING_SYSTEM_URL) ?></p>
                    <?php else: ?>
                        <p>Please configure COLLECTING_SYSTEM_URL in your config file.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- AI Assistant Tab -->
        <?php if (defined('ENABLE_AI_CHATBOT') && ENABLE_AI_CHATBOT): ?>
        <div id="ai-tab" class="tab-content <?= $currentTab === 'ai' ? 'active' : '' ?>">
            <div class="ai-coming-soon">
                <i class="fas fa-robot"></i>
                <h2>AI Assistant</h2>
                <p>Your personal media collection AI assistant is coming soon!</p>
                <p>This feature will help you:</p>
                <ul style="text-align: left; max-width: 500px; margin: 0 auto;">
                    <li>Find items in your collection</li>
                    <li>Get recommendations based on your taste</li>
                    <li>Analyze your collection trends</li>
                    <li>Discover new releases you might like</li>
                </ul>
                
                <?php if (defined('CHATBOT_SCRIPT_URL') && CHATBOT_SCRIPT_URL): ?>
                <div id="ai-chatbot-container" style="margin-top: 2rem;">
                    <!-- AI Chatbot will be loaded here when enabled -->
                    <script>
                        // Chatbot integration script would go here
                        console.log('AI Chatbot ready for integration');
                    </script>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle tab switching
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            function showTab(tabName) {
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected tab content
                const selectedTab = document.getElementById(tabName + '-tab');
                if (selectedTab) {
                    selectedTab.classList.add('active');
                }
                
                // Update button states
                tabButtons.forEach(button => {
                    button.classList.remove('active');
                    if (button.href.includes('tab=' + tabName)) {
                        button.classList.add('active');
                    }
                });
            }
            
            // Add click handlers for tab buttons
            tabButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const url = new URL(this.href);
                    const tabParam = url.searchParams.get('tab');
                    if (tabParam) {
                        e.preventDefault();
                        showTab(tabParam);
                        // Update URL without page reload
                        history.pushState(null, '', this.href);
                    }
                });
            });
            
            // Auto-refresh collecting iframe if enabled
            <?php if (defined('COLLECTING_IFRAME_REFRESH') && $currentTab === 'collecting'): ?>
            const collectingIframe = document.querySelector('.collecting-iframe');
            if (collectingIframe) {
                setInterval(function() {
                    collectingIframe.src = collectingIframe.src;
                }, <?= COLLECTING_IFRAME_REFRESH * 1000 ?>);
            }
            <?php endif; ?>
            
            // Status indicator updates
            function updateSystemStatus() {
                fetch('api/system_status.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update status indicators based on response
                        console.log('System status updated:', data);
                    })
                    .catch(error => {
                        console.log('Status update failed:', error);
                    });
            }
            
            // Update status every 30 seconds
            setInterval(updateSystemStatus, 30000);
            
            // Debug logging if enabled
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            console.log('Dashboard initialized successfully');
            console.log('Config loaded:', <?= json_encode($systemStatus['config']) ?>);
            console.log('Database connected:', <?= json_encode($systemStatus['database']) ?>);
            console.log('Current tab:', '<?= $currentTab ?>');
            <?php endif; ?>
        });
    </script>

    <!-- AI Chatbot Integration (if enabled) -->
    <?php if (defined('ENABLE_AI_CHATBOT') && ENABLE_AI_CHATBOT && defined('CHATBOT_SCRIPT_URL') && CHATBOT_SCRIPT_URL): ?>
    <script>
        // AI Chatbot configuration
        window.chatbotConfig = {
            agentId: '<?= defined('CHATBOT_AGENT_ID') ? CHATBOT_AGENT_ID : '' ?>',
            chatbotId: '<?= defined('CHATBOT_ID') ? CHATBOT_ID : '' ?>',
            primaryColor: '<?= defined('CHATBOT_PRIMARY_COLOR') ? CHATBOT_PRIMARY_COLOR : '#031B4E' ?>',
            secondaryColor: '<?= defined('CHATBOT_SECONDARY_COLOR') ? CHATBOT_SECONDARY_COLOR : '#E5E8ED' ?>',
            buttonColor: '<?= defined('CHATBOT_BUTTON_COLOR') ? CHATBOT_BUTTON_COLOR : '#0061EB' ?>'
        };
        
        // Load chatbot script
        const chatbotScript = document.createElement('script');
        chatbotScript.src = '<?= CHATBOT_SCRIPT_URL ?>';
        chatbotScript.async = true;
        document.head.appendChild(chatbotScript);
    </script>
    <?php endif; ?>
</body>
</html>