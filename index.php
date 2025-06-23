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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Media Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Media Collection</h2>
        <a href="admin/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
    <p>This will be your main dashboard.</p>
</div>
</body>
</html>
