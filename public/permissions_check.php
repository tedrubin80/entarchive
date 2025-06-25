<?php
/**
 * File Permissions Diagnostic Script
 * File: permissions_check.php
 * Run this script to diagnose file permission issues causing blank pages
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 File Permissions Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .permission-box { 
        background: #f5f5f5; 
        padding: 10px; 
        margin: 10px 0; 
        border-radius: 5px; 
        border-left: 5px solid #ddd;
    }
    .permission-box.success { border-left-color: green; }
    .permission-box.error { border-left-color: red; }
    .permission-box.warning { border-left-color: orange; }
    code { background: #eee; padding: 2px 5px; border-radius: 3px; }
</style>";

function checkPermissions($path, $expectedPerms = null, $needsWritable = false) {
    if (!file_exists($path)) {
        echo "<div class='permission-box error'>";
        echo "<strong>❌ MISSING:</strong> <code>$path</code> does not exist<br>";
        echo "<strong>Action:</strong> Create this file/directory<br>";
        echo "</div>";
        return false;
    }
    
    $perms = fileperms($path);
    $octal = substr(sprintf('%o', $perms), -3);
    $isWritable = is_writable($path);
    $isReadable = is_readable($path);
    
    $status = "success";
    $message = "✅ OK";
    
    if ($needsWritable && !$isWritable) {
        $status = "error";
        $message = "❌ NOT WRITABLE";
    } elseif (!$isReadable) {
        $status = "error";
        $message = "❌ NOT READABLE";
    } elseif ($expectedPerms && $octal !== $expectedPerms) {
        $status = "warning";
        $message = "⚠️ PERMISSIONS MISMATCH";
    }
    
    echo "<div class='permission-box $status'>";
    echo "<strong>$message:</strong> <code>$path</code><br>";
    echo "<strong>Current:</strong> $octal | ";
    echo "<strong>Readable:</strong> " . ($isReadable ? "Yes" : "No") . " | ";
    echo "<strong>Writable:</strong> " . ($isWritable ? "Yes" : "No") . "<br>";
    
    if ($expectedPerms && $octal !== $expectedPerms) {
        echo "<strong>Expected:</strong> $expectedPerms<br>";
        echo "<strong>Fix Command:</strong> <code>chmod $expectedPerms $path</code><br>";
    }
    
    if ($needsWritable && !$isWritable) {
        echo "<strong>Fix Command:</strong> <code>chmod 755 $path</code><br>";
    }
    
    echo "</div>";
    
    return $status === "success";
}

echo "<h2>📁 Directory Permissions Check</h2>";

$directories = [
    ['cache/', '755', true],
    ['logs/', '755', true],
    ['uploads/', '755', true],
    ['db/backups/', '755', true],
    ['config/', '755', false],
    ['public/', '755', false],
    ['api/', '755', false],
    ['includes/', '755', false]
];

foreach ($directories as $dir) {
    checkPermissions($dir[0], $dir[1], $dir[2]);
}

echo "<h2>📄 Critical File Permissions</h2>";

$files = [
    ['config.php', '644'],
    ['config/config.php', '644'],
    ['config/sensitive_config.php', '600'],
    ['public/enhanced_media_dashboard.php', '644'],
    ['public/user_login.php', '644'],
    ['public/user_add_item.php', '644'],
    ['public/user_scanner.php', '644'],
    ['.htaccess', '644']
];

foreach ($files as $file) {
    checkPermissions($file[0], $file[1], false);
}

echo "<h2>🔧 Quick Fix Commands</h2>";
echo "<div class='permission-box info'>";
echo "<strong>Run these commands to fix common permission issues:</strong><br><br>";
echo "<code># Make directories writable by web server</code><br>";
echo "<code>chmod 755 cache/ logs/ uploads/ db/backups/</code><br><br>";
echo "<code># Set proper file permissions</code><br>";
echo "<code>chmod 644 config/*.php public/*.php api/*.php</code><br><br>";
echo "<code># Secure sensitive config</code><br>";
echo "<code>chmod 600 config/sensitive_config.php</code><br><br>";
echo "<code># Fix .htaccess</code><br>";
echo "<code>chmod 644 .htaccess</code><br>";
echo "</div>";

echo "<h2>🐛 PHP Configuration Check</h2>";

$phpChecks = [
    ['display_errors', ini_get('display_errors') ? '✅ ON' : '❌ OFF'],
    ['error_reporting', error_reporting() === E_ALL ? '✅ E_ALL' : '⚠️ ' . error_reporting()],
    ['session.save_path', session_save_path() ?: '⚠️ Default'],
    ['upload_max_filesize', ini_get('upload_max_filesize')],
    ['post_max_size', ini_get('post_max_size')],
    ['memory_limit', ini_get('memory_limit')]
];

echo "<div class='permission-box info'>";
foreach ($phpChecks as $check) {
    echo "<strong>{$check[0]}:</strong> {$check[1]}<br>";
}
echo "</div>";

echo "<h2>📊 File System Test</h2>";

// Test file creation in writable directories
$testDirs = ['cache/', 'logs/', 'uploads/'];
foreach ($testDirs as $dir) {
    if (is_dir($dir)) {
        $testFile = $dir . 'test_' . time() . '.txt';
        if (file_put_contents($testFile, 'test') !== false) {
            unlink($testFile);
            echo "<div class='permission-box success'>✅ <code>$dir</code> is writable</div>";
        } else {
            echo "<div class='permission-box error'>❌ <code>$dir</code> write test failed</div>";
        }
    }
}

echo "<h2>🔍 Current Session Info</h2>";
session_start();
echo "<div class='permission-box info'>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "<br>";
echo "<strong>Current User:</strong> " . ($_SESSION['admin_user'] ?? 'Not logged in') . "<br>";
echo "<strong>Login Status:</strong> " . (($_SESSION['admin_logged_in'] ?? false) ? "Logged in" : "Not logged in") . "<br>";
echo "</div>";

echo "<h2>💡 Next Steps</h2>";
echo "<div class='permission-box info'>";
echo "<strong>If you're seeing blank pages:</strong><br>";
echo "1. Run the permission fix commands above<br>";
echo "2. Check your error logs: <code>tail -f logs/error.log</code><br>";
echo "3. Verify config.php exists and is readable<br>";
echo "4. Test dashboard: <a href='public/enhanced_media_dashboard.php' target='_blank'>enhanced_media_dashboard.php</a><br>";
echo "5. Test login: <a href='public/user_login.php' target='_blank'>user_login.php</a><br>";
echo "</div>";

echo "<p><em>Run this script from your project root directory for accurate results.</em></p>";
?>