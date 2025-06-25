<?php
/**
 * Dashboard Functionality Diagnostic
 * File: dashboard_diagnostic.php
 * Comprehensive test of all dashboard components and links
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h1>🔍 Dashboard Functionality Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    .test-section { 
        background: #f8f9fa; 
        padding: 20px; 
        margin: 20px 0; 
        border-radius: 8px; 
        border-left: 5px solid #ddd;
    }
    .test-section.success { border-left-color: #28a745; }
    .test-section.error { border-left-color: #dc3545; }
    .test-section.warning { border-left-color: #ffc107; }
    .test-result { 
        display: inline-block; 
        padding: 4px 8px; 
        border-radius: 4px; 
        margin: 2px; 
        color: white; 
        font-weight: bold;
    }
    .success { background: #28a745; }
    .error { background: #dc3545; }
    .warning { background: #ffc107; color: #000; }
    .info { background: #17a2b8; }
    code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    .file-list { 
        background: white; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        padding: 15px; 
        margin: 10px 0;
    }
    .link-test {
        display: inline-block;
        margin: 5px;
        padding: 8px 12px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
    }
    .link-test:hover {
        background: #0056b3;
        color: white;
        text-decoration: none;
    }
</style>";

function testFileExists($file, $description = null) {
    $exists = file_exists($file);
    $readable = $exists ? is_readable($file) : false;
    $size = $exists ? filesize($file) : 0;
    
    $status = $exists ? ($readable ? 'success' : 'warning') : 'error';
    $message = $exists ? 
        ($readable ? "✅ EXISTS" : "⚠️ NOT READABLE") : 
        "❌ MISSING";
    
    echo "<div class='test-result $status'>$message</div> ";
    echo "<strong>" . ($description ?: basename($file)) . "</strong> ";
    echo "<code>$file</code>";
    if ($exists) {
        echo " <small>(" . round($size/1024, 1) . " KB)</small>";
    }
    echo "<br>";
    
    return $exists && $readable;
}

function testDashboardLink($path, $name) {
    $fullPath = "public/$path";
    $exists = file_exists($fullPath);
    $working = $exists && filesize($fullPath) > 100; // Basic size check
    
    echo "<a href='$fullPath' target='_blank' class='link-test' style='background: " . 
         ($working ? "#28a745" : ($exists ? "#ffc107" : "#dc3545")) . "'>";
    echo ($working ? "✅" : ($exists ? "⚠️" : "❌")) . " $name";
    echo "</a>";
    
    return $working;
}

// 1. SESSION STATUS
echo "<div class='test-section " . (session_status() === PHP_SESSION_ACTIVE ? 'success' : 'error') . "'>";
echo "<h2>🔐 Session Status</h2>";
echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Active" : "❌ Inactive") . "<br>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>User Logged In:</strong> " . (($_SESSION['admin_logged_in'] ?? false) ? "✅ Yes" : "❌ No") . "<br>";
echo "<strong>Current User:</strong> " . ($_SESSION['admin_user'] ?? 'Not set') . "<br>";
echo "<strong>Login Time:</strong> " . (isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Not set') . "<br>";
echo "</div>";

// 2. CRITICAL FILES CHECK
echo "<div class='test-section'>";
echo "<h2>📁 Critical Files Status</h2>";

$criticalFiles = [
    'config.php' => 'Main Configuration',
    'public/enhanced_media_dashboard.php' => 'Enhanced Dashboard',
    'public/user_login.php' => 'Login Page',
    'public/user_add_item.php' => 'Add Item Page',
    'public/user_scanner.php' => 'Barcode Scanner',
    'api/api_integration_system.php' => 'API Integration',
    'includes/inc_functions.php' => 'Core Functions',
    'includes/inc_database.php' => 'Database Functions'
];

$criticalFilesWorking = 0;
foreach ($criticalFiles as $file => $desc) {
    if (testFileExists($file, $desc)) {
        $criticalFilesWorking++;
    }
}

echo "<div style='margin-top: 15px;'>";
echo "<strong>Critical Files Status:</strong> ";
echo "<span class='test-result " . ($criticalFilesWorking >= 6 ? 'success' : 'warning') . "'>";
echo "$criticalFilesWorking/" . count($criticalFiles) . " Working";
echo "</span>";
echo "</div>";
echo "</div>";

// 3. DASHBOARD LINKS TEST
echo "<div class='test-section'>";
echo "<h2>🔗 Dashboard Links Test</h2>";
echo "<p>Click these links to test each dashboard component:</p>";

$dashboardLinks = [
    'enhanced_media_dashboard.php' => 'Main Dashboard',
    'user_login.php' => 'Login Page',
    'user_add_item.php' => 'Add Items',
    'user_scanner.php' => 'Barcode Scanner',
    'user_collection.php' => 'View Collection',
    'user_wishlist.php' => 'Wishlist',
    'user_search.php' => 'Search',
    'user_stats.php' => 'Statistics',
    'user_export.php' => 'Export Data',
    'user_marketplace_sync.php' => 'Marketplace Sync',
    'user_security_settings.php' => '2FA Settings'
];

$workingLinks = 0;
foreach ($dashboardLinks as $file => $name) {
    if (testDashboardLink($file, $name)) {
        $workingLinks++;
    }
}

echo "<div style='margin-top: 15px;'>";
echo "<strong>Dashboard Links Status:</strong> ";
echo "<span class='test-result " . ($workingLinks >= 5 ? 'success' : 'warning') . "'>";
echo "$workingLinks/" . count($dashboardLinks) . " Working";
echo "</span>";
echo "</div>";
echo "</div>";

// 4. DIRECTORY STRUCTURE
echo "<div class='test-section'>";
echo "<h2>📂 Directory Structure</h2>";

$requiredDirs = [
    'public/' => 'User Interface',
    'api/' => 'API Endpoints',
    'config/' => 'Configuration',
    'includes/' => 'Shared Components',
    'cache/' => 'Cache Storage',
    'logs/' => 'Log Files',
    'uploads/' => 'File Uploads',
    'db/' => 'Database Files'
];

$workingDirs = 0;
foreach ($requiredDirs as $dir => $desc) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    
    $status = $exists ? ($writable ? 'success' : 'warning') : 'error';
    $message = $exists ? 
        ($writable ? "✅ EXISTS & WRITABLE" : "⚠️ NOT WRITABLE") : 
        "❌ MISSING";
    
    echo "<div class='test-result $status'>$message</div> ";
    echo "<strong>$desc</strong> <code>$dir</code><br>";
    
    if ($exists) $workingDirs++;
}

echo "<div style='margin-top: 15px;'>";
echo "<strong>Directory Structure:</strong> ";
echo "<span class='test-result " . ($workingDirs >= 6 ? 'success' : 'warning') . "'>";
echo "$workingDirs/" . count($requiredDirs) . " Present";
echo "</span>";
echo "</div>";
echo "</div>";

// 5. BARCODE SCANNER TEST
echo "<div class='test-section'>";
echo "<h2>📱 Barcode Scanner Status</h2>";

$scannerFiles = [
    'public/user_scanner.php' => 'Scanner Interface',
    'api/api_integration_system.php' => 'Barcode API Handler',
    'api/integration/MediaAPIManager.php' => 'Media API Manager'
];

$scannerWorking = 0;
foreach ($scannerFiles as $file => $desc) {
    if (testFileExists($file, $desc)) {
        $scannerWorking++;
    }
}

echo "<div style='margin-top: 15px;'>";
echo "<strong>Scanner Components:</strong> ";
echo "<span class='test-result " . ($scannerWorking >= 2 ? 'success' : 'warning') . "'>";
echo "$scannerWorking/" . count($scannerFiles) . " Present";
echo "</span>";
echo "</div>";

// Test scanner page specifically
if (file_exists('public/user_scanner.php')) {
    echo "<p><a href='public/user_scanner.php' target='_blank' class='link-test' style='background: #17a2b8'>🧪 Test Scanner Page</a></p>";
    
    // Check for QuaggaJS dependency
    $scannerContent = file_get_contents('public/user_scanner.php');
    $hasQuagga = strpos($scannerContent, 'quagga') !== false;
    echo "<strong>QuaggaJS Integration:</strong> " . ($hasQuagga ? "✅ Found" : "❌ Missing") . "<br>";
}
echo "</div>";

// 6. DATABASE CONNECTION TEST
echo "<div class='test-section'>";
echo "<h2>🗄️ Database Connection Test</h2>";

try {
    if (file_exists('config.php')) {
        include_once 'config.php';
        
        $dsn = "mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') . 
               ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'media_collection');
        
        $pdo = new PDO(
            $dsn,
            defined('DB_USER') ? DB_USER : 'root',
            defined('DB_PASS') ? DB_PASS : '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "<div class='test-result success'>✅ DATABASE CONNECTED</div><br>";
        echo "<strong>Host:</strong> " . (defined('DB_HOST') ? DB_HOST : 'localhost') . "<br>";
        echo "<strong>Database:</strong> " . (defined('DB_NAME') ? DB_NAME : 'media_collection') . "<br>";
        
        // Test tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<strong>Tables Found:</strong> " . count($tables) . " (" . implode(', ', array_slice($tables, 0, 5)) . 
             (count($tables) > 5 ? '...' : '') . ")<br>";
        
    } else {
        echo "<div class='test-result error'>❌ CONFIG FILE MISSING</div><br>";
        echo "Create config.php with database credentials.<br>";
    }
} catch (Exception $e) {
    echo "<div class='test-result error'>❌ DATABASE CONNECTION FAILED</div><br>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
}
echo "</div>";

// 7. NEXT STEPS
echo "<div class='test-section info'>";
echo "<h2>🎯 Next Steps Recommendations</h2>";

$recommendations = [];

if ($criticalFilesWorking < 6) {
    $recommendations[] = "📁 <strong>Fix missing critical files</strong> - Some core system files are missing";
}

if ($workingLinks < 5) {
    $recommendations[] = "🔗 <strong>Create missing dashboard pages</strong> - Several user interface pages need implementation";
}

if ($workingDirs < 6) {
    $recommendations[] = "📂 <strong>Create required directories</strong> - Run mkdir commands for missing folders";
}

if ($scannerWorking < 2) {
    $recommendations[] = "📱 <strong>Fix barcode scanner</strong> - Scanner components need attention";
}

if (empty($recommendations)) {
    echo "<div class='test-result success'>🎉 SYSTEM HEALTHY</div><br>";
    echo "Your media management system appears to be working well! All critical components are present.";
} else {
    echo "<ol>";
    foreach ($recommendations as $rec) {
        echo "<li>$rec</li>";
    }
    echo "</ol>";
}

echo "<h3>🔧 Quick Development Commands</h3>";
echo "<div class='file-list'>";
echo "<strong>Fix Permissions:</strong><br>";
echo "<code>chmod 755 cache/ logs/ uploads/ db/backups/</code><br>";
echo "<code>chmod 644 config/*.php public/*.php</code><br><br>";

echo "<strong>Test Dashboard:</strong><br>";
echo "<code>php -S localhost:8000</code> (then visit http://localhost:8000/public/enhanced_media_dashboard.php)<br><br>";

echo "<strong>Check Error Logs:</strong><br>";
echo "<code>tail -f logs/error.log</code><br>";
echo "</div>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0; color: #666;'>";
echo "<em>Dashboard Diagnostic completed at " . date('Y-m-d H:i:s') . "</em>";
echo "</div>";
?>