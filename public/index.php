<?php
$ip = $_SERVER['REMOTE_ADDR'];
$geo = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));

if (!in_array($geo->countryCode, ['US', 'CA'])) {
    die("Access restricted to users in the United States and Canada.");
}
?>
<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}
?>
<!-- Protected Dashboard -->
<h2>Welcome to your Media Collection</h2>
<a href="../admin/logout.php">Logout</a>
