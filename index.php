<?php
session_start();
require_once 'config.php';
require_once 'includes/db.php';

// Check login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/login.php");
    exit;
}
?>

<h2>Welcome to your Media Collection</h2>
<a href="admin/logout.php">Logout</a>
