<?php
/**
 * Enhanced Personal Media Management Dashboard
 * CLZ.com-style dashboard with robust error handling and user-specific features
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Configuration - Debug mode for development
define('DEBUG_MODE', true);
define('ALLOW_DEMO_LOGIN', true); // Set false in production

// Helper functions
function debugLog($message, $data = null) {
    if (DEBUG_MODE) {
        error_log("[DASHBOARD DEBUG] " . $message . ($data ? ' - ' . print_r($data, true) : ''));
    }
}

function safeInclude($file, $required = false) {
    $paths = [
        $file,
        __DIR__ . '/' . $file,
        __DIR__ . '/../' . $file,
        '../' . $file,
        'config/' . $file
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
    
    debugLog("File not found: " . $file);
    return false;
}

// Initialize system status variables
$systemStatus = [
    'database' => false,
    'auth' => false,
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

debugLog("Dashboard initialization started");

// 1. Load Configuration
try {
    if (safeInclude('config.php', true)) {
        $systemStatus['config'] = true;
        debugLog("Configuration loaded successfully");
    } else {
        $errors[] = "Configuration file (config.php) not found. Please check file structure.";
    }
} catch (Exception $e) {
    $errors[] = "Configuration error: " . $e->getMessage();
}

// 2. Database Connection
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
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        debugLog("Available tables: " . implode(', ', $tables));
        
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
        debugLog("Database error: " . $e->getMessage());
    }
}

// 3. Authentication Check
$isAuthenticated = isset($_SESSION['user_id']) || isset($_SESSION['admin_logged_in']);
if ($isAuthenticated) {
    $systemStatus['auth'] = true;
    $currentUser = $_SESSION['username'] ?? $_SESSION['admin_user'] ?? 'User';
    debugLog("User authenticated: " . $currentUser);
} else {
    debugLog("User not authenticated");
}

// 4. Handle demo/quick login (development only)
if (DEBUG_MODE && ALLOW_DEMO_LOGIN && isset($_POST['demo_login'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'Demo User';
    $systemStatus['auth'] = true;
    $isAuthenticated = true;
    $currentUser = 'Demo User';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 5. Load Statistics and Recent Items
if ($systemStatus['database'] && $systemStatus['auth']) {
    try {
        // Get collection statistics
        $statQuery = "
            SELECT 
                media_type,
                COUNT(*) as count,
                COALESCE(SUM(CAST(current_value AS DECIMAL(10,2))), 0) as total_value
            FROM collection 
            WHERE user_id = :user_id OR user_id IS NULL
            GROUP BY media_type
        ";
        
        $userId = $_SESSION['user_id'] ?? 1; // Default to 1 for single-user setups
        
        if (in_array('collection', $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN))) {
            $stmt = $pdo->prepare($statQuery);
            $stmt->execute(['user_id' => $userId]);
            
            while ($row = $stmt->fetch()) {
                $type = strtolower($row['media_type']);
                $stats[$type] = $row['count'];
                $stats['total_value'] += $row['total_value'];
                $stats['total_items'] += $row['count'];
            }
            
            // Get recent items
            $recentQuery = "
                SELECT id, title, media_type, creator, created_at, 
                       poster_url, current_value
                FROM collection 
                WHERE user_id = :user_id OR user_id IS NULL
                ORDER BY created_at DESC 
                LIMIT 8
            ";
            
            $stmt = $pdo->prepare($recentQuery);
            $stmt->execute(['user_id' => $userId]);
            $recentItems = $stmt->fetchAll();
            
            $stats['recently_added'] = count($recentItems);
        }
        
        // Get wishlist count
        if (in_array('wishlist', $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN))) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = :user_id OR user_id IS NULL");
            $stmt->execute(['user_id' => $userId]);
            $stats['wishlist_items'] = $stmt->fetch()['count'];
        }
        
        debugLog("Statistics loaded", $stats);
        
    } catch (PDOException $e) {
        $errors[] = "Failed to load statistics: " . $e->getMessage();
        debugLog("Statistics error: " . $e->getMessage());
    }
}

// 6. Check for system readiness
$systemReady = $systemStatus['database'] && $systemStatus['auth'] && $systemStatus['config'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
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
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .recent-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }
        
        .recent-item {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            transition: transform 0.2s ease;
        }
        
        .recent-item:hover {
            transform: scale(1.05);
        }
        
        .recent-item img {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .recent-item-title {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .recent-item-meta {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            background: #e9ecef;
            border-color: #3498db;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .demo-login {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .debug-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 4px;
            padding: 0.5rem;
            margin: 0.5rem 0;
            font-size: 0.8rem;
            font-family: 'Courier New', monospace;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .header-actions {
                margin-top: 1rem;
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-collections"></i> My Media Collection</h1>
                <div class="header-subtitle">Personal Media Management System</div>
                <div class="status-indicators">
                    <span class="status-badge <?= $systemStatus['config'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['config'] ? 'check' : 'times' ?>"></i> Config
                    </span>
                    <span class="status-badge <?= $systemStatus['database'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['database'] ? 'database' : 'times' ?>"></i> Database
                    </span>
                    <span class="status-badge <?= $systemStatus['auth'] ? 'status-success' : 'status-warning' ?>">
                        <i class="fas fa-<?= $systemStatus['auth'] ? 'user-check' : 'user-times' ?>"></i> 
                        <?= $systemStatus['auth'] ? ($currentUser ?? 'Authenticated') : 'Not Logged In' ?>
                    </span>
                </div>
            </div>
            
            <div class="header-actions">
                <?php if ($systemReady): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Media
                    </a>
                    <a href="scan.php" class="btn btn-secondary">
                        <i class="fas fa-qrcode"></i> Scan
                    </a>
                    <a href="search.php" class="btn btn-success">
                        <i class="fas fa-search"></i> Search
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <h4><i class="fas fa-exclamation-triangle"></i> System Issues Detected</h4>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$systemStatus['auth'] && DEBUG_MODE && ALLOW_DEMO_LOGIN): ?>
            <div class="demo-login">
                <h4><i class="fas fa-code"></i> Development Mode</h4>
                <p>Quick access for testing and development:</p>
                <form method="post" style="margin-top: 0.5rem;">
                    <button type="submit" name="demo_login" class="btn btn-warning">
                        <i class="fas fa-user-cog"></i> Demo Login
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($systemReady): ?>
            <!-- Statistics Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <div class="stat-number"><?= number_format($stats['movies']) ?></div>
                    <div class="stat-label">Movies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number"><?= number_format($stats['books']) ?></div>
                    <div class="stat-label">Books</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📖</div>
                    <div class="stat-number"><?= number_format($stats['comics']) ?></div>
                    <div class="stat-label">Comics</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎵</div>
                    <div class="stat-number"><?= number_format($stats['music']) ?></div>
                    <div class="stat-label">Music</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎮</div>
                    <div class="stat-number"><?= number_format($stats['games']) ?></div>
                    <div class="stat-label">Games</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">$<?= number_format($stats['total_value'], 2) ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-number"><?= number_format($stats['wishlist_items']) ?></div>
                    <div class="stat-label">Wishlist</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?= number_format($stats['total_items']) ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="main-content">
                <!-- Recent Items -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i> Recently Added
                    </h2>
                    
                    <?php if (!empty($recentItems)): ?>
                        <div class="recent-items">
                            <?php foreach ($recentItems as $item): ?>
                                <div class="recent-item">
                                    <img src="<?= htmlspecialchars($item['poster_url'] ?: 'assets/images/placeholder.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         onerror="this.src='assets/images/placeholder.jpg'">
                                    <div class="recent-item-title"><?= htmlspecialchars(substr($item['title'], 0, 20)) ?></div>
                                    <div class="recent-item-meta">
                                        <?= htmlspecialchars($item['media_type']) ?><br>
                                        <?= date('M j', strtotime($item['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Items Yet</h3>
                            <p>Start building your collection by adding your first item!</p>
                            <a href="add.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Add First Item
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h2>
                    
                    <div class="quick-actions">
                        <div class="action-card" onclick="window.location.href='add.php'">
                            <span class="action-icon">➕</span>
                            <h4>Add Item</h4>
                            <p>Manually add new media</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='scan.php'">
                            <span class="action-icon">📱</span>
                            <h4>Scan Barcode</h4>
                            <p>Quick barcode scanning</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='wishlist.php'">
                            <span class="action-icon">⭐</span>
                            <h4>Wishlist</h4>
                            <p>Manage wanted items</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='categories.php'">
                            <span class="action-icon">🏷️</span>
                            <h4>Categories</h4>
                            <p>Organize collection</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='reports.php'">
                            <span class="action-icon">📊</span>
                            <h4>Reports</h4>
                            <p>Collection analytics</p>
                        </div>
                        <div class="action-card" onclick="window.location.href='backup.php'">
                            <span class="action-icon">💾</span>
                            <h4>Backup</h4>
                            <p>Export collection data</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- System Not Ready -->
            <div class="content-section">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>System Setup Required</h3>
                    <p>Please resolve the issues above to access your media collection dashboard.</p>
                    
                    <?php if (!$systemStatus['config']): ?>
                        <div style="margin-top: 2rem; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
                            <h4>Configuration Setup</h4>
                            <ol>
                                <li>Create <code>config.php</code> with database settings</li>
                                <li>Set up database connection constants</li>
                                <li>Configure authentication settings</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$systemStatus['database']): ?>
                        <div style="margin-top: 2rem; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
                            <h4>Database Setup</h4>
                            <ol>
                                <li>Ensure MySQL/MariaDB is running</li>
                                <li>Create the database schema</li>
                                <li>Import the SQL tables</li>
                                <li>Verify connection settings</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📚 Media Collection Dashboard Loaded');
            
            <?php if (DEBUG_MODE): ?>
            console.log('🔧 Debug Mode Active');
            console.log('System Status:', <?= json_encode($systemStatus) ?>);
            console.log('Statistics:', <?= json_encode($stats) ?>);
            console.log('Recent Items:', <?= count($recentItems) ?>);
            <?php endif; ?>
            
            // Auto-refresh stats every 5 minutes if system is ready
            <?php if ($systemReady): ?>
            setInterval(function() {
                fetch('api/stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateStats(data.stats);
                        }
                    })
                    .catch(err => console.log('Stats refresh failed:', err));
            }, 300000); // 5 minutes
            <?php endif; ?>
        });
        
        function updateStats(newStats) {
            const statElements = document.querySelectorAll('.stat-number');
            const statTypes = ['movies', 'books', 'comics', 'music', 'games', 'total_value', 'wishlist_items', 'total_items'];
            
            statElements.forEach((element, index) => {
                if (statTypes[index] && newStats[statTypes[index]] !== undefined) {
                    const value = newStats[statTypes[index]];
                    const formattedValue = statTypes[index] === 'total_value' 
                        ? ' + Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                        : Number(value).toLocaleString();
                    
                    // Animate the change
                    element.style.transform = 'scale(1.1)';
                    element.style.color = '#27ae60';
                    setTimeout(() => {
                        element.textContent = formattedValue;
                        element.style.transform = 'scale(1)';
                        element.style.color = '#2c3e50';
                    }, 200);
                }
            });
        }
        
        // Handle quick action navigation
        function navigateToAction(url) {
            window.location.href = url;
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'a':
                        e.preventDefault();
                        navigateToAction('add.php');
                        break;
                    case 's':
                        e.preventDefault();
                        navigateToAction('search.php');
                        break;
                    case 'w':
                        e.preventDefault();
                        navigateToAction('wishlist.php');
                        break;
                }
            }
        });
        
        // Service worker for offline capability (optional)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').then(function(registration) {
                console.log('ServiceWorker registration successful');
            }).catch(function(err) {
                console.log('ServiceWorker registration failed');
            });
        }
    </script>
</body>
</html>