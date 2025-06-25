<?php
// Debug version of dashboard - saves to debug_dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

// Test session
session_start();
echo "Session started successfully<br>";

// Test config file
echo "Testing config file...<br>";
if (file_exists('../config.php')) {
    echo "Config file exists<br>";
    require_once '../config.php';
    echo "Config file loaded successfully<br>";
    
    // Test database connection
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "Database connection successful<br>";
    } catch (PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Config file not found!<br>";
}

// Test geo helper
echo "Testing geo helper...<br>";
if (file_exists('geo_helper.php')) {
    echo "geo_helper.php exists in current directory<br>";
    require_once 'geo_helper.php';
} elseif (file_exists('includes/geo_helper.php')) {
    echo "geo_helper.php exists in includes directory<br>";
    require_once 'includes/geo_helper.php';
} else {
    echo "geo_helper.php not found!<br>";
}

// Test authentication
echo "Checking authentication...<br>";
if (isset($_SESSION['admin_logged_in'])) {
    echo "User is logged in<br>";
} else {
    echo "User is NOT logged in - this would cause redirect<br>";
}

// Test geolocation (if geo_helper is loaded)
if (class_exists('GeoLocationHelper')) {
    echo "Testing geolocation...<br>";
    $ip = $_SERVER['REMOTE_ADDR'];
    echo "Your IP: " . $ip . "<br>";
    
    try {
        $access = GeoLocationHelper::checkAccess($ip);
        if ($access) {
            echo "Geolocation check passed<br>";
        } else {
            echo "Geolocation check FAILED - access denied<br>";
        }
    } catch (Exception $e) {
        echo "Geolocation check error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "GeoLocationHelper class not available<br>";
}

echo "<br>Debug complete. If you see this message, PHP is working.";
?>