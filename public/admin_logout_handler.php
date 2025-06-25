<?php
/**
 * Admin Logout Handler
 * Securely logs out admin users and cleans up sessions
 */
session_start();

// Helper function to safely include config
function safeInclude($file) {
    $paths = [$file, '../' . $file, '../../' . $file];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
}

// Log the logout attempt
if (isset($_SESSION['admin_user'])) {
    error_log("Logout: {$_SESSION['admin_user']} from " . $_SERVER['REMOTE_ADDR']);
}

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    // Load config for database connection
    if (safeInclude('config.php')) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Clear remember token from database
            if (isset($_SESSION['admin_id'])) {
                $stmt = $pdo->prepare("UPDATE admins SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
            }
        } catch (PDOException $e) {
            // Log error but continue with logout
            error_log("Logout database error: " . $e->getMessage());
        }
    }
    
    // Clear the cookie
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy session
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
header("Location: login.php?logged_out=1");
exit;
?>