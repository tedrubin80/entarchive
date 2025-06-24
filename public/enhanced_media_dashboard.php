        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">🔍</div>
                <div class="action-title">Search Collection</div>
                <div class="action-desc">Find items in your collection</div>
                <a href="placeholder.php?page=user_search" class="btn btn-primary">Search</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">View Reports</div>
                <div class="action-desc">Statistics and analytics</div>
                <a href="placeholder.php?page=user_stats" class="btn btn-primary">Reports</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">💾</div>
                <div class="action-title">Export Data</div>
                <div class="action-desc">Backup your collection</div>
                <?php
/**
 * Enhanced Personal Media Management Dashboard (FIXED)
 * File: public/enhanced_media_dashboard.php
 * Main dashboard with robust error handling and proper authentication flow
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Configuration - Debug mode for development
define('DEBUG_MODE', true);
define('ALLOW_DEMO_LOGIN', true); // Set false in production

// Check authentication FIRST - redirect if not logged in
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

// Helper functions
function debugLog($message, $data = null) {
    if (DEBUG_MODE) {
        error_log("[DASHBOARD DEBUG] " . $message . ($data ? ' - ' . print_r($data, true) : ''));
    }
}

function safeInclude($file, $required = false) {
    // FIXED: Updated paths to properly look in root directory from public folder
    $paths = [
        $file,                          // Current directory
        __DIR__ . '/' . $file,         // Same directory as this file
        __DIR__ . '/../' . $file,      // Parent directory (ROOT) - This is the key fix!
        '../' . $file,                 // Relative parent
        '../../' . $file,              // Two levels up
        dirname(__DIR__) . '/' . $file // Parent directory using dirname
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            if ($required) {
                require_once $path;
            } else {
                include_once $path;
            }
            debugLog("Loaded file: " . $path);
            return true;
        }
    }
    
    debugLog("File not found: " . $file . " (tried paths: " . implode(', ', $paths) . ")");
    return false;
}

// Initialize system status variables
$systemStatus = [
    'database' => false,
    'auth' => true, // Already verified above
    'config' => false,
    'geo' => true // Default to true unless geo restrictions needed
];

$errors = [];
$stats = [
    'movies' => 0,
    'books' => 0,
    'comics' => 0,
    'music' => 0,
    'games' => 0,
    'total_items' => 0,
    'total_value' => 0,
    'wishlist_items' => 0,
    'recently_added' => 0
];
$recentItems = [];
$pdo = null;

debugLog("Dashboard initialization started for user: " . ($_SESSION['admin_user'] ?? 'unknown'));

// 1. Load Configuration - FIXED to handle missing config gracefully
try {
    if (safeInclude('config.php')) {
        $systemStatus['config'] = true;
        debugLog("Configuration loaded successfully");
    } else {
        $errors[] = "Configuration file (config.php) not found in root directory. Please ensure config.php exists in the project root.";
        debugLog("Config file search failed");
    }
} catch (Exception $e) {
    $errors[] = "Configuration error: " . $e->getMessage();
    debugLog("Config loading exception: " . $e->getMessage());
}

// 2. Database Connection - Only try if config loaded
if ($systemStatus['config']) {
    try {
        $dsn = "mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') . 
               ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'media_collection') . 
               ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO(
            $dsn,
            defined('DB_USER') ? DB_USER : 'root',
            defined('DB_PASS') ? DB_PASS : '',
            $options
        );
        
        $systemStatus['database'] = true;
        debugLog("Database connection successful");
        
        // Test database schema
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            debugLog("Available tables: " . implode(', ', $tables));
        } catch (PDOException $e) {
            debugLog("Cannot show tables (database may be empty): " . $e->getMessage());
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
        debugLog("Database error: " . $e->getMessage());
    }
} else {
    $errors[] = "Cannot connect to database: Configuration not loaded";
}

// 3. Load Statistics and Recent Items - Only if database connected
if ($systemStatus['database']) {
    try {
        // Get collection statistics - handle missing tables gracefully
        $statQueries = [
            'movies' => "SELECT COUNT(*) FROM collection WHERE media_type = 'movie'",
            'books' => "SELECT COUNT(*) FROM collection WHERE media_type = 'book'",
            'comics' => "SELECT COUNT(*) FROM collection WHERE media_type = 'comic'",
            'music' => "SELECT COUNT(*) FROM collection WHERE media_type = 'music'",
            'games' => "SELECT COUNT(*) FROM collection WHERE media_type = 'game'",
            'total_items' => "SELECT COUNT(*) FROM collection",
            'total_value' => "SELECT COALESCE(SUM(CAST(current_value AS DECIMAL(10,2))), 0) FROM collection",
            'wishlist_items' => "SELECT COUNT(*) FROM wishlist",
            'recently_added' => "SELECT COUNT(*) FROM collection WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        ];
        
        foreach ($statQueries as $key => $query) {
            try {
                $result = $pdo->query($query);
                $stats[$key] = $result->fetchColumn() ?: 0;
            } catch (PDOException $e) {
                debugLog("Query failed for {$key}: " . $e->getMessage());
                // Keep default value of 0
            }
        }
        
        // Get recent items
        try {
            $recentQuery = "SELECT title, media_type, created_at FROM collection ORDER BY created_at DESC LIMIT 5";
            $recentItems = $pdo->query($recentQuery)->fetchAll();
        } catch (PDOException $e) {
            debugLog("Recent items query failed: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $errors[] = "Statistics loading error: " . $e->getMessage();
    }
}

$currentUser = $_SESSION['admin_user'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection Dashboard</title>
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
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .status-indicators {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #007bff;
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
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .error-panel {
            background: rgba(248, 215, 218, 0.95);
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .error-list {
            list-style: none;
            margin: 1rem 0;
        }
        
        .error-list li {
            margin: 0.5rem 0;
            padding-left: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .recent-items {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .recent-items h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex-grow: 1;
        }
        
        .item-title {
            font-weight: 500;
            color: #333;
        }
        
        .item-type {
            font-size: 0.8rem;
            color: #666;
            text-transform: capitalize;
        }
        
        .item-date {
            font-size: 0.8rem;
            color: #999;
        }
        
        .debug-info {
            background: rgba(255, 243, 205, 0.95);
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #856404;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .action-desc {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .setup-guide {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .setup-guide h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .setup-guide ol {
            margin-left: 1.5rem;
        }
        
        .setup-guide li {
            margin: 0.5rem 0;
            color: #555;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <h1>📚 Media Collection</h1>
            <div class="user-info">
                👤 Welcome, <?php echo htmlspecialchars($currentUser); ?>
            </div>
        </div>
        
        <div class="status-indicators">
            <span class="status-badge <?php echo $systemStatus['config'] ? 'status-success' : 'status-error'; ?>">
                <?php echo $systemStatus['config'] ? '✓' : '✗'; ?> Config
            </span>
            <span class="status-badge <?php echo $systemStatus['database'] ? 'status-success' : 'status-error'; ?>">
                <?php echo $systemStatus['database'] ? '✓' : '✗'; ?> Database
            </span>
            <span class="status-badge status-success">
                ✓ Auth
            </span>
        </div>
        
        <div class="header-actions">
            <a href="placeholder.php?page=user_add_item" class="btn btn-primary">➕ Add Media</a>
            <a href="placeholder.php?page=user_scanner" class="btn btn-secondary">📱 Scan</a>
            <a href="?logout=1" class="btn btn-danger">🚪 Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="error-panel">
                <h2>🚨 System Status Issues</h2>
                <p>The following issues were detected:</p>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li>• <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (!$systemStatus['config']): ?>
                    <div class="setup-guide">
                        <h3>🔧 Configuration Setup Required</h3>
                        <p>Your config.php file was not found. Please:</p>
                        <ol>
                            <li>Copy <code>public/config_template.php</code> to the root directory as <code>config.php</code></li>
                            <li>Edit the database settings in config.php</li>
                            <li>Ensure the file is in the same directory as index.php</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎬</div>
                <div class="stat-number"><?php echo number_format($stats['movies']); ?></div>
                <div class="stat-label">Movies</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo number_format($stats['books']); ?></div>
                <div class="stat-label">Books</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📖</div>
                <div class="stat-number"><?php echo number_format($stats['comics']); ?></div>
                <div class="stat-label">Comics</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎵</div>
                <div class="stat-number"><?php echo number_format($stats['music']); ?></div>
                <div class="stat-label">Music</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎮</div>
                <div class="stat-number"><?php echo number_format($stats['games']); ?></div>
                <div class="stat-label">Games</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$<?php echo number_format($stats['total_value'], 2); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number"><?php echo number_format($stats['wishlist_items']); ?></div>
                <div class="stat-label">Wishlist</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-number"><?php echo number_format($stats['recently_added']); ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>

        <!-- Recent Items -->
        <?php if (!empty($recentItems)): ?>
        <div class="recent-items">
            <h3>📋 Recently Added Items</h3>
            <?php foreach ($recentItems as $item): ?>
            <div class="recent-item">
                <div class="item-info">
                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="item-type"><?php echo htmlspecialchars($item['media_type']); ?></div>
                </div>
                <div class="item-date">
                    <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">🔍</div>
                <div class="action-title">Search Collection</div>
                <div class="action-desc">Find items in your collection</div>
                <a href="user_search.php" class="btn btn-primary">Search</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">View Reports</div>
                <div class="action-desc">Statistics and analytics</div>
                <a href="user_stats.php" class="btn btn-primary">Reports</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">💾</div>
                <div class="action-title">Export Data</div>
                <div class="action-desc">Backup your collection</div>
                <a href="placeholder.php?page=user_export" class="btn btn-primary">Export</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">⚙️</div>
                <div class="action-title">Settings</div>
                <div class="action-desc">Configure your preferences</div>
                <a href="user_settings.php" class="btn btn-primary">Settings</a>
            </div>
        </div>

        <?php if (DEBUG_MODE): ?>
        <div class="debug-info">
            <strong>🔧 Debug Information:</strong><br>
            Session ID: <?php echo session_id(); ?><br>
            User: <?php echo htmlspecialchars($currentUser); ?><br>
            Login Time: <?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Unknown'; ?><br>
            Database Status: <?php echo $systemStatus['database'] ? 'Connected' : 'Disconnected'; ?><br>
            Config Status: <?php echo $systemStatus['config'] ? 'Loaded' : 'Not Found'; ?><br>
            Current File: <?php echo __FILE__; ?><br>
            Root Directory: <?php echo dirname(__DIR__); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>