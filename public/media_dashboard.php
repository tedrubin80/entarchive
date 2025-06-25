<?php
/**
 * Enhanced Media Collection Dashboard
 * Handles authentication, database connection issues, and provides comprehensive debugging
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Debug mode flag - set to false in production
define('DEBUG_MODE', true);

function debugLog($message, $data = null) {
    if (DEBUG_MODE) {
        echo "<div class='debug-info'>";
        echo "<strong>DEBUG:</strong> " . htmlspecialchars($message);
        if ($data !== null) {
            echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
        }
        echo "</div>";
    }
}

// Helper function to safely include files
function safeInclude($file, $required = false) {
    $paths = [
        $file,
        '../' . $file,
        '../../' . $file,
        __DIR__ . '/' . $file,
        __DIR__ . '/../' . $file
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            debugLog("Including file: " . $path);
            if ($required) {
                require_once $path;
            } else {
                include_once $path;
            }
            return true;
        }
    }
    
    debugLog("File not found: " . $file . " (tried " . count($paths) . " paths)", $paths);
    return false;
}

// Initialize variables
$dbConnection = null;
$authError = null;
$dbError = null;
$geoError = null;
$stats = [
    'movies' => 0,
    'books' => 0,
    'comics' => 0,
    'music' => 0,
    'total_value' => 0,
    'wishlist_items' => 0
];
$recentItems = [];

debugLog("Dashboard initialization started");

// Try to load configuration
if (!safeInclude('config.php')) {
    $dbError = "Configuration file not found. Please ensure config.php exists.";
} else {
    debugLog("Configuration loaded successfully");
    
    // Try database connection
    try {
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ];
            
            if (defined('DB_PASS')) {
                $dbConnection = new PDO($dsn, DB_USER, DB_PASS, $options);
            } else {
                $dbConnection = new PDO($dsn, DB_USER, '', $options);
            }
            
            debugLog("Database connection successful");
            
            // Test database schema
            try {
                $tables = $dbConnection->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                debugLog("Database tables found", $tables);
                
                // Get collection statistics if tables exist
                if (in_array('collection', $tables)) {
                    $stmt = $dbConnection->query("
                        SELECT 
                            media_type,
                            COUNT(*) as count,
                            COALESCE(SUM(CAST(current_value AS DECIMAL(10,2))), 0) as total_value
                        FROM collection 
                        GROUP BY media_type
                    ");
                    
                    while ($row = $stmt->fetch()) {
                        $stats[$row['media_type'] . 's'] = $row['count'];
                        $stats['total_value'] += $row['total_value'];
                    }
                    
                    // Get recent items
                    $stmt = $dbConnection->prepare("
                        SELECT id, title, media_type, creator, created_at, poster_url
                        FROM collection 
                        ORDER BY created_at DESC 
                        LIMIT 6
                    ");
                    $stmt->execute();
                    $recentItems = $stmt->fetchAll();
                    
                    debugLog("Statistics loaded", $stats);
                }
                
                // Get wishlist count if table exists
                if (in_array('wishlist', $tables)) {
                    $stmt = $dbConnection->query("SELECT COUNT(*) as count FROM wishlist");
                    $stats['wishlist_items'] = $stmt->fetch()['count'];
                }
                
            } catch (PDOException $e) {
                debugLog("Database schema error: " . $e->getMessage());
            }
            
        } else {
            $dbError = "Database configuration constants not defined.";
        }
    } catch (PDOException $e) {
        $dbError = "Database connection failed: " . $e->getMessage();
        debugLog("Database error", ['error' => $e->getMessage(), 'code' => $e->getCode()]);
    }
}

// Try to load geolocation helper
$geoHelper = safeInclude('includes/geo_helper.php') || safeInclude('geo_helper.php');
if ($geoHelper && class_exists('GeoLocationHelper')) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $geoAccess = GeoLocationHelper::checkAccess($ip);
        if (!$geoAccess) {
            $geoError = "Geographic access restriction: Access limited to US/Canada.";
        }
        debugLog("Geolocation check completed", ['ip' => $ip, 'access' => $geoAccess]);
    } catch (Exception $e) {
        $geoError = "Geolocation service error: " . $e->getMessage();
        debugLog("Geolocation error", $e->getMessage());
    }
}

// Check authentication
$isAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$isAuthenticated) {
    $authError = "User not authenticated. Login required.";
    debugLog("Authentication failed - user not logged in");
}

// Handle quick login for development (remove in production)
if (DEBUG_MODE && isset($_POST['quick_login'])) {
    $_SESSION['admin_logged_in'] = true;
    $isAuthenticated = true;
    $authError = null;
    debugLog("Quick login activated (DEBUG MODE)");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

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
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 5px 0;
            font-size: 12px;
            color: #856404;
        }
        
        .debug-info pre {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            overflow-x: auto;
            margin: 5px 0;
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
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .error-panel {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .error-list {
            list-style: none;
            margin-top: 1rem;
        }
        
        .error-list li {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid #dc3545;
        }
        
        .quick-login {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid #ffeaa7;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .content-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .recent-items {
            display: grid;
            gap: 1rem;
        }
        
        .recent-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .recent-item:hover {
            background: #e9ecef;
            transform: translateX(4px);
        }
        
        .item-poster {
            width: 60px;
            height: 80px;
            background: #dee2e6;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .item-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .item-info p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: grid;
            gap: 1rem;
        }
        
        .action-card {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .status-indicators {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php if (DEBUG_MODE): ?>
        <div style="background: #1a1a1a; color: #00ff00; padding: 10px; font-family: monospace; font-size: 12px;">
            <strong>🔧 DEBUG MODE ENABLED</strong> - Remove DEBUG_MODE flag in production
        </div>
    <?php endif; ?>

    <header class="header">
        <div>
            <h1>📚 Media Collection Dashboard</h1>
            <div class="status-indicators">
                <span class="status-badge <?= $dbConnection ? 'status-success' : 'status-error' ?>">
                    <?= $dbConnection ? '✓' : '✗' ?> Database
                </span>
                <span class="status-badge <?= $isAuthenticated ? 'status-success' : 'status-error' ?>">
                    <?= $isAuthenticated ? '✓' : '✗' ?> Auth
                </span>
                <span class="status-badge <?= $geoError ? 'status-error' : 'status-success' ?>">
                    <?= $geoError ? '✗' : '✓' ?> Geo
                </span>
            </div>
        </div>
        
        <div class="header-actions">
            <?php if ($isAuthenticated): ?>
                <a href="add.php" class="btn btn-primary">➕ Add Media</a>
                <a href="scan.php" class="btn btn-secondary">📱 Scan</a>
                <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">🔐 Login</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <?php if ($authError || $dbError || $geoError): ?>
            <div class="error-panel">
                <h2>🚨 System Status Issues</h2>
                <p>The following issues were detected:</p>
                <ul class="error-list">
                    <?php if ($authError): ?>
                        <li><strong>Authentication:</strong> <?= htmlspecialchars($authError) ?></li>
                    <?php endif; ?>
                    <?php if ($dbError): ?>
                        <li><strong>Database:</strong> <?= htmlspecialchars($dbError) ?></li>
                    <?php endif; ?>
                    <?php if ($geoError): ?>
                        <li><strong>Geolocation:</strong> <?= htmlspecialchars($geoError) ?></li>
                    <?php endif; ?>
                </ul>
                
                <?php if (DEBUG_MODE && !$isAuthenticated): ?>
                    <div class="quick-login">
                        <h4>🔧 Debug: Quick Login</h4>
                        <p>Development mode detected. You can bypass authentication for testing:</p>
                        <form method="post" style="margin-top: 10px;">
                            <button type="submit" name="quick_login" class="btn btn-success">
                                Enable Debug Session
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isAuthenticated && $dbConnection): ?>
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['movies']) ?></div>
                    <div class="stat-label">Movies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['books']) ?></div>
                    <div class="stat-label">Books</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['comics']) ?></div>
                    <div class="stat-label">Comics</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['music']) ?></div>
                    <div class="stat-label">Music</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?= number_format($stats['total_value'], 0) ?></div>
                    <div class="stat-label">Collection Value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['wishlist_items']) ?></div>
                    <div class="stat-label">Wishlist Items</div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="content-section">
                    <h2>📖 Recent Additions</h2>
                    <div class="recent-items">
                        <?php if (!empty($recentItems)): ?>
                            <?php foreach ($recentItems as $item): ?>
                                <div class="recent-item">
                                    <div class="item-poster">
                                        <?php if ($item['poster_url']): ?>
                                            <img src="<?= htmlspecialchars($item['poster_url']) ?>" 
                                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <?= $item['media_type'] === 'movie' ? '🎬' : 
                                                ($item['media_type'] === 'book' ? '📚' : 
                                                ($item['media_type'] === 'comic' ? '📖' : '🎵')) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <h4><?= htmlspecialchars($item['title']) ?></h4>
                                        <p><?= htmlspecialchars($item['creator'] ?? 'Unknown') ?></p>
                                        <p><small><?= date('M j, Y', strtotime($item['created_at'])) ?></small></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="recent-item">
                                <div class="item-poster">📦</div>
                                <div class="item-info">
                                    <h4>No items yet</h4>
                                    <p>Start building your collection!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-section">
                    <h2>⚡ Quick Actions</h2>
                    <div class="quick-actions">
                        <div class="action-card" onclick="window.location.href='add.php'">
                            <span class="action-icon">➕</span>
                            <h4>Add New Item</h4>
                            <p>Manually add to collection</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='scan.php'">
                            <span class="action-icon">📱</span>
                            <h4>Scan Barcode</h4>
                            <p>Quick barcode scanning</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='search.php'">
                            <span class="action-icon">🔍</span>
                            <h4>Search Collection</h4>
                            <p>Find specific items</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='wishlist.php'">
                            <span class="action-icon">⭐</span>
                            <h4>Manage Wishlist</h4>
                            <p>Items to acquire</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Guest/Error View -->
            <div class="content-section">
                <h2>🔐 Access Required</h2>
                <p>Please resolve the system issues above to access your media collection dashboard.</p>
                
                <?php if (!$isAuthenticated): ?>
                    <div style="margin-top: 1rem;">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($dbError): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                        <h4>Database Setup Help</h4>
                        <p>To resolve database issues:</p>
                        <ol>
                            <li>Ensure MySQL/MariaDB is running</li>
                            <li>Check database credentials in config.php</li>
                            <li>Create the database if it doesn't exist</li>
                            <li>Run the schema.sql file to create tables</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds if authenticated
        <?php if ($isAuthenticated && $dbConnection): ?>
        setInterval(function() {
            // Simple AJAX refresh for stats
            fetch('api/stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.stat-number').forEach((el, index) => {
                            const keys = ['movies', 'books', 'comics', 'music', 'total_value', 'wishlist_items'];
                            if (keys[index]) {
                                const value = data.stats[keys[index]];
                                el.textContent = keys[index] === 'total_value' 
                                    ? '$' + Number(value).toLocaleString() 
                                    : Number(value).toLocaleString();
                            }
                        });
                    }
                })
                .catch(err => console.log('Stats refresh failed:', err));
        }, 30000);
        <?php endif; ?>

        // Debug mode indicator
        <?php if (DEBUG_MODE): ?>
        console.log('🔧 Media Collection Dashboard - Debug Mode Active');
        console.log('Database Status:', <?= $dbConnection ? 'true' : 'false' ?>);
        console.log('Authentication Status:', <?= $isAuthenticated ? 'true' : 'false' ?>);
        console.log('Statistics:', <?= json_encode($stats) ?>);
        <?php endif; ?>
    </script>
</body>
</html>