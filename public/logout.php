<?php
/**
 * Logout Handler
 * File: public/logout.php
 * Handles user logout and redirects to login page
 */
session_start();

// Destroy all session data
session_destroy();

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Redirect to login page with logout message
header("Location: user_login.php?logout=1");
exit;
?>