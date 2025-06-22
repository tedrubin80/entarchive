<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
?>

<h2>Welcome to the Admin Dashboard</h2>
<a href="logout.php">Logout</a>
