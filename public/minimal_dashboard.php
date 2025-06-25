<?php
// Minimal dashboard without auth/geo checks - save as minimal_dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Skip auth check for now
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: ../admin/login.php");
//     exit();
// }

// Skip geo check for now
// require_once 'geo_helper.php';
// $ip = $_SERVER['REMOTE_ADDR'];
// if (!GeoLocationHelper::checkAccess($ip)) {
//     session_destroy();
//     die("Access restricted to users in the United States and Canada.");
// }

// Try to connect to database
try {
    require_once '../config.php';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dbStatus = "Connected";
} catch (Exception $e) {
    $dbStatus = "Error: " . $e->getMessage();
    $pdo = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Media Collection Dashboard</h1>
            <p>Simplified version for testing</p>
            
            <div class="status <?php echo $pdo ? 'success' : 'error'; ?>">
                Database Status: <?php echo $dbStatus; ?>
            </div>
        </div>

        <div class="stats-grid">
            <?php if ($pdo): ?>
                <?php
                // Get basic stats
                try {
                    $stats = [
                        'movies' => $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'movie'")->fetchColumn(),
                        'books' => $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'book'")->fetchColumn(),
                        'comics' => $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'comic'")->fetchColumn(),
                        'music' => $pdo->query("SELECT COUNT(*) FROM collection WHERE media_type = 'music'")->fetchColumn(),
                    ];
                } catch (Exception $e) {
                    $stats = ['movies' => 0, 'books' => 0, 'comics' => 0, 'music' => 0];
                    echo "<div class='status error'>Error loading stats: " . $e->getMessage() . "</div>";
                }
                ?>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['movies']; ?></div>
                    <div class="stat-label">Movies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['books']; ?></div>
                    <div class="stat-label">Books</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['comics']; ?></div>
                    <div class="stat-label">Comics</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['music']; ?></div>
                    <div class="stat-label">Music</div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-number">--</div>
                    <div class="stat-label">Database Required</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>