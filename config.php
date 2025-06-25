<?php
/**
 * Public Config Loader
 * This file safely loads the secure configuration from /private directory
 * Location: config.php (in web root)
 */

// Prevent direct access if not included properly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Load secure configuration from /private directory
$secure_config_path = __DIR__ . '/private/config.php';

// Alternative paths to check (in case directory structure varies)
$alternative_paths = [
    __DIR__ . '/private/config.php',
    dirname(__DIR__) . '/private/config.php',
    __DIR__ . '/../private/config.php'
];

$config_loaded = false;
foreach ($alternative_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die('Configuration file not found. Please contact system administrator.');
}

// Verify essential constants are defined
$required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_NAME'];
foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        die("Missing required configuration: $constant");
    }
}

// Log successful config load (if logging is available)
if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
    error_log("Configuration loaded successfully from: " . $path);
}

?>