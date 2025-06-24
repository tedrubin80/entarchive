<?php
/**
 * Main Entry Point
 * File: index.php (root directory)
 * Routes users to appropriate dashboard or login page
 */
session_start();

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    // User is logged in, redirect to dashboard
    header("Location: public/enhanced_media_dashboard.php");
    exit;
} else {
    // User not logged in, redirect to login page
    header("Location: public/user_login.php");
    exit;
}
?>