<?php
/**
 * Enhanced Personal Media Management Dashboard with RSS & Criterion Integration
 * File: public/enhanced_media_dashboard.php
 * Main dashboard with RSS feed and Criterion releases
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

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

// Helper functions
function debugLog($message, $data = null) {
    error_log("[DASHBOARD] " . $message . ($data ? ' - ' . print_r($data, true) : ''));
}

function safeInclude($file) {
    $paths = [__DIR__ . '/../' . $file, '../' . $file, $file];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            include_once $path;
            return true;
        }
    }
    return false;
}

// Initialize system status
$systemStatus = ['database' => false, 'auth' => true, 'config' => false];
$errors = [];
$stats = [
    'movies' => 0, 'books' => 0, 'comics' => 0, 
    'music' => 0, 'games' => 0, 'total_items' => 0,
    'total_value' => 0, 'wishlist_items' => 0
];

// Load configuration
try {
    if (safeInclude('config.php')) {
        $systemStatus['config'] = true;
    } else {
        $errors[] = "Configuration file not found";
    }
} catch (Exception $e) {
    $errors[] = "Configuration error: " . $e->getMessage();
}

// Database connection
$pdo = null;
if ($systemStatus['config']) {
    try {
        $dsn = "mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') . 
               ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'media_collection');
        $pdo = new PDO($dsn, 
            defined('DB_USER') ? DB_USER : 'root', 
            defined('DB_PASS') ? DB_PASS : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $systemStatus['database'] = true;
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
}

// Get statistics
if ($systemStatus['database']) {
    try {
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
        }

        // Get wishlist count
        $wishlistStmt = $pdo->query("SELECT COUNT(*) as wishlist_items FROM wishlist WHERE date_acquired IS NULL");
        $wishlistResult = $wishlistStmt->fetch(PDO::FETCH_ASSOC);
        if ($wishlistResult) {
            $stats['wishlist_items'] = $wishlistResult['wishlist_items'];
        }
    } catch (Exception $e) {
        debugLog("Statistics error: " . $e->getMessage());
    }
}

// RSS Feed Functions
function fetchRSSFeed($url, $limit = 5) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Media Collection Dashboard/1.0'
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
        $criterionFile = __DIR__ . '/../data/criterion_latest.json';
        
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
        return [];
    }
}

// Fetch data for widgets
$rssData = fetchRSSFeed('https://www.the-numbers.com/news/rss.php', 4);
$criterionReleases = fetchCriterionReleases(4);

$currentUser = $_SESSION['admin_user'] ?? 'User';
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
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .error-panel {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #721c24;
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
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                margin-top: 1rem;
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
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-film"></i> My Media Collection</h1>
                <div class="header-subtitle">Personal Media Management System</div>
                <div class="status-indicators">
                    <span class="status-badge <?= $systemStatus['config'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['config'] ? 'check' : 'times' ?>"></i> Config
                    </span>
                    <span class="status-badge <?= $systemStatus['database'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas fa-<?= $systemStatus['database'] ? 'database' : 'times' ?>"></i> Database
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
                <a href="user_marketplace_sync.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Sync
                </a>
                <a href="?logout=1" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="error-panel">
                <h3><i class="fas fa-exclamation-triangle"></i> System Issues</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

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
                <p>Collection analytics</p>
            </a>
            
            <a href="user_settings.php" class="action-card">
                <span class="action-icon"><i class="fas fa-cog"></i></span>
                <h3>Settings</h3>
                <p>Configure your account</p>
            </a>
            
            <a href="user_security_settings.php" class="action-card">
                <span class="action-icon"><i class="fas fa-shield-alt"></i></span>
                <h3>Security</h3>
                <p>2FA & account security</p>
            </a>
        </div>

        <!-- Bottom Widgets -->
        <div class="widgets-section">
            <!-- Left Widget: RSS Feed -->
            <div class="widget">
                <div class="widget-header">
                    <i class="fas fa-rss"></i>
                    <h3>Movie Industry News</h3>
                </div>
                
                <?php if (isset($rssData['error'])): ?>
                    <div class="widget-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($rssData['error']) ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($rssData['items'] as $item): ?>
                        <div class="rss-item">
                            <h4><a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a></h4>
                            <p><?= htmlspecialchars(substr($item['description'], 0, 120)) ?>...</p>
                            <div class="rss-date"><?= htmlspecialchars($item['date']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Right Widget: Criterion Collection -->
            <div class="widget">
                <div class="widget-header">
                    <i class="fas fa-award"></i>
                    <h3>Latest Criterion Releases</h3>
                </div>
                
                <?php if (empty($criterionReleases)): ?>
                    <div class="widget-error">
                        <i class="fas fa-film"></i>
                        No Criterion releases data available
                    </div>
                <?php else: ?>
                    <?php foreach ($criterionReleases as $film): ?>
                        <div class="criterion-item">
                            <div class="criterion-spine">#<?= htmlspecialchars($film['spine_number']) ?></div>
                            <div class="criterion-details">
                                <div class="criterion-title"><?= htmlspecialchars($film['title']) ?></div>
                                <div class="criterion-director">Directed by <?= htmlspecialchars($film['director']) ?></div>
                                <div class="criterion-format"><?= htmlspecialchars($film['format']) ?> • <?= date('M Y', strtotime($film['release_date'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh widgets every 30 minutes
        setTimeout(() => {
            window.location.reload();
        }, 1800000);
        
        // Add loading states for action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!this.href.includes('php')) return;
                
                const icon = this.querySelector('.action-icon i');
                icon.className = 'fas fa-spinner fa-spin';
                
                setTimeout(() => {
                    icon.className = icon.className.replace('fa-spinner fa-spin', 'fa-check');
                }, 500);
            });
        });
    </script>
</body>
</html>