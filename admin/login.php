<?php
$ip = $_SERVER['REMOTE_ADDR'];
$geo = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));

if (!in_array($geo->countryCode, ['US', 'CA'])) {
    die("Access restricted to users in the United States and Canada.");
}
?>
<?php
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Replace this with DB check if needed
    if ($user === 'admin' && $pass === 'password123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ../public/index.php');
        exit();
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<form method="post">
    <h2>Admin Login</h2>
    <label>Username: <input type="text" name="username"></label><br>
    <label>Password: <input type="password" name="password"></label><br>
    <button type="submit">Login</button>
    <p style="color:red;"><?php echo $error; ?></p>
</form>
