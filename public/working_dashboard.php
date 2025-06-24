<?php
// Save this as working_dashboard.php in the SAME directory as login_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication first
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    // Redirect to login test instead of admin login
    header("Location: login_test.php");
    exit();
}

// Include config - adjust path as needed
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    die("Config file not found. Please make sure config.php exists.");
}

// Include geo helper
if (file_exists('geo_helper.php')) {
    require_once 'geo_helper.php';
} elseif (file_exists('includes/geo_helper.php')) {
    require_once 'includes/geo_helper.php';
} else {
    // Create fallback
    class GeoLocationHelper {
        public static function checkAccess($ip) { return true; }
    }
}

// Geolocation check
$ip = $_SERVER['REMOTE_ADDR'];
if (!GeoLocationHelper::checkAccess($ip)) {
    session_destroy();
    die("Access restricted to users in the United States and Canada.");
}

// Database connection with better error handling
$dbConnected = false;
$dbError = '';
$stats = ['movies' => 0, 'books' => 0, 'comics' => 0, 'music' => 0, 'total_value' => 0, 'wishlist' => 0];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $dbConnected = true;
    
    // Try to get stats - handle missing tables gracefully
    try {
        $stats['movies'] = $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'movie'")->fetchColumn() ?: 0;
        $stats['books'] = $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'book'")->fetchColumn() ?: 0;
        $stats['comics'] = $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'comic'")->fetchColumn() ?: 0;
        $stats['music'] = $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'music'")->fetchColumn() ?: 0;
        $stats['total_value'] = $pdo->query("SELECT COALESCE(SUM(estimated_value), 0) FROM collection")->fetchColumn() ?: 0;
        $stats['wishlist'] = $pdo->query("SELECT COUNT(*) FROM wishlist")->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $dbError = "Tables may not exist yet: " . $e->getMessage();
    }
    
} catch (PDOException $e) {
    $dbError = "Database connection failed: " . $e->getMessage();
    if (strpos($e->getMessage(), 'No such file or directory') !== false) {
        $dbError .= " (Database server may not be running)";
    }
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
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
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
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .status-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .status-success {
            border-left: 4px solid #28a745;
            background: #f8fff9;
        }
        
        .status-warning {
            border-left: 4px solid #ffc107;
            background: #fffdf0;
        }
        
        .status-error {
            border-left: 4px solid #dc3545;
            background: #fff8f8;
        }
        
        .status-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>📚 Media Collection Dashboard</h1>
            <div class="header-actions">
                <a href="login_test.php?logout=1" class="btn">🚪 Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Authentication Status -->
        <div class="status-card status-success">
            <div class="status-title">✅ Authentication Success</div>
            <p>You are successfully logged in as admin!</p>
            <div class="debug-info">
                Session ID: <?php echo session_id(); ?><br>
                Login Status: <?php echo $_SESSION['admin_logged_in'] ? 'Active' : 'Inactive'; ?>
            </div>
        </div>

        <!-- Database Status -->
        <?php if ($dbConnected): ?>
            <div class="status-card status-success">
                <div class="status-title">✅ Database Connected</div>
                <p>Successfully connected to database: <?php echo DB_NAME; ?></p>
                <?php if ($dbError): ?>
                    <div class="debug-info">
                        Warning: <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="status-card status-error">
                <div class="status-title">❌ Database Connection Failed</div>
                <p><?php echo htmlspecialchars($dbError); ?></p>
                <div class="debug-info">
                    <strong>How to fix:</strong><br>
                    1. Start your database server (XAMPP/MAMP/etc.)<br>
                    2. Make sure database "<?php echo DB_NAME; ?>" exists<br>
                    3. Check your config.php credentials
                </div>
            </div>
        <?php endif; ?>

        <!-- Collection Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['movies']); ?></div>
                <div class="stat-label">Movies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['books']); ?></div>
                <div class="stat-label">Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['comics']); ?></div>
                <div class="stat-label">Comics</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['music']); ?></div>
                <div class="stat-label">Music Albums</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_value'], 0); ?></div>
                <div class="stat-label">Collection Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['wishlist']); ?></div>
                <div class="stat-label">Wishlist Items</div>
            </div>
        </div>

        <?php if (!$dbConnected): ?>
            <div class="status-card status-warning">
                <div class="status-title">⚠️ Next Steps</div>
                <p>To get your dashboard fully working:</p>
                <ol style="margin-left: 1.5rem; margin-top: 1rem;">
                    <li>Start your database server (XAMPP, MAMP, etc.)</li>
                    <li>Create the database and tables if they don't exist</li>
                    <li>Add some sample data to see statistics</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>