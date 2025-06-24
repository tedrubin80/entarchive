<?php
/**
 * Universal Placeholder Page
 * File: public/placeholder.php
 * Handles all missing pages with a helpful message
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: user_login.php");
    exit;
}

// Get the requested page from the URL
$requestedPage = $_GET['page'] ?? 'unknown';
$currentUser = $_SESSION['admin_user'] ?? 'User';

// Define page information
$pageInfo = [
    'user_add_item' => [
        'title' => 'Add Media Item',
        'icon' => '➕',
        'description' => 'Add new movies, books, comics, music, or games to your collection.',
        'features' => [
            'Barcode scanning for quick entry',
            'Automatic metadata lookup',
            'Custom categories and tags',
            'Photo upload for covers'
        ]
    ],
    'user_scanner' => [
        'title' => 'Barcode Scanner',
        'icon' => '📱',
        'description' => 'Quickly add items to your collection by scanning barcodes.',
        'features' => [
            'Camera-based barcode scanning',
            'Support for UPC, ISBN, and more',
            'Instant metadata lookup',
            'Bulk scanning mode'
        ]
    ],
    'user_search' => [
        'title' => 'Search Collection',
        'icon' => '🔍',
        'description' => 'Search and filter your media collection.',
        'features' => [
            'Advanced search filters',
            'Search by title, author, genre',
            'Sort by various criteria',
            'Save search queries'
        ]
    ],
    'user_stats' => [
        'title' => 'Statistics & Reports',
        'icon' => '📊',
        'description' => 'View detailed statistics and reports about your collection.',
        'features' => [
            'Collection value tracking',
            'Genre distribution charts',
            'Collection growth over time',
            'Wishlist analytics'
        ]
    ],
    'user_export' => [
        'title' => 'Export Data',
        'icon' => '💾',
        'description' => 'Export your collection data for backup or sharing.',
        'features' => [
            'CSV export for spreadsheets',
            'PDF reports generation',
            'XML data export',
            'Backup scheduling'
        ]
    ]
];

$currentPageInfo = $pageInfo[$requestedPage] ?? [
    'title' => 'Page Not Found',
    'icon' => '❓',
    'description' => 'This page is not yet implemented.',
    'features' => ['Coming soon!']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentPageInfo['title']); ?> - Media Collection</title>
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
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #333;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .coming-soon-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .page-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .page-description {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .features-list {
            text-align: left;
            max-width: 500px;
            margin: 0 auto 2rem;
        }
        
        .features-list h3 {
            color: #333;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .features-list ul {
            list-style: none;
            padding: 0;
        }
        
        .features-list li {
            padding: 0.5rem 0;
            color: #555;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .features-list li:before {
            content: "✨";
            margin-right: 0.5rem;
        }
        
        .status-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 2rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .development-info {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .development-info h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .priority-list {
            list-style: none;
            padding: 0;
        }
        
        .priority-list li {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .priority-high {
            background: #fee;
            border-left: 4px solid #dc3545;
        }
        
        .priority-medium {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .priority-low {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .page-description {
                font-size: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><?php echo $currentPageInfo['icon']; ?> <?php echo htmlspecialchars($currentPageInfo['title']); ?></h1>
        <div class="nav-links">
            <a href="enhanced_media_dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="user_settings.php" class="nav-link">⚙️ Settings</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="coming-soon-card">
            <div class="page-icon"><?php echo $currentPageInfo['icon']; ?></div>
            <h1 class="page-title"><?php echo htmlspecialchars($currentPageInfo['title']); ?></h1>
            
            <div class="status-badge">
                🚧 Coming Soon - In Development
            </div>
            
            <p class="page-description">
                <?php echo htmlspecialchars($currentPageInfo['description']); ?>
            </p>
            
            <div class="features-list">
                <h3>🎯 Planned Features</h3>
                <ul>
                    <?php foreach ($currentPageInfo['features'] as $feature): ?>
                        <li><?php echo htmlspecialchars($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="enhanced_media_dashboard.php" class="btn btn-primary">🏠 Back to Dashboard</a>
                <a href="user_settings.php" class="btn btn-primary">⚙️ Go to Settings</a>
            </div>
        </div>

        <div class="development-info">
            <h3>🛠️ Development Status</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">
                We're actively working on building these features. Here's our current development priority:
            </p>
            
            <ul class="priority-list">
                <li class="priority-high">
                    <span>🔴</span>
                    <div>
                        <strong>High Priority:</strong> Core dashboard functionality, user authentication, basic media management
                    </div>
                </li>
                <li class="priority-medium">
                    <span>🟡</span>
                    <div>
                        <strong>Medium Priority:</strong> Add/Edit items, Search functionality, Categories management
                    </div>
                </li>
                <li class="priority-low">
                    <span>🟢</span>
                    <div>
                        <strong>Future Features:</strong> Barcode scanning, Advanced reporting, Data export, API integrations
                    </div>
                </li>
            </ul>
            
            <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                <p style="color: #6c757d; font-size: 0.9rem; margin: 0;">
                    <strong>Note:</strong> You can continue using the dashboard and settings pages. 
                    New features will be added regularly as development progresses.
                </p>
            </div>
        </div>
    </div>
</body>
</html>