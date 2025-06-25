<?php
// Save this as login_test.php in your root directory
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$message = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_test.php');
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<div style='padding: 15px; background: #e8f4fd; margin: 10px 0; border-left: 4px solid #007bff;'>";
    echo "<strong>Login Attempt:</strong><br>";
    echo "Username entered: " . htmlspecialchars($username) . "<br>";
    echo "Password length: " . strlen($password) . " characters<br>";
    echo "Session ID: " . session_id() . "<br>";
    
    // Simple hardcoded check (replace with your actual logic)
    if ($username === 'admin' && $password === 'password123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $message = "✅ Login successful!";
        echo "<span style='color: green;'>✅ Login successful!</span><br>";
        echo "Session admin_logged_in set to: " . ($_SESSION['admin_logged_in'] ? 'true' : 'false') . "<br>";
    } else {
        $message = "❌ Invalid credentials. Try: admin / password123";
        echo "<span style='color: red;'>❌ Invalid credentials</span><br>";
    }
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test & Debug</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        button:hover {
            background: #5a6fd8;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .btn-link {
            color: #667eea;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #667eea;
            border-radius: 4px;
            display: inline-block;
            margin: 5px;
        }
        .btn-link:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Login Test & Debug</h1>
        
        <?php if ($message): ?>
            <div class="status <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="debug-info">
            <strong>Current Session Status:</strong><br>
            Session ID: <?php echo session_id(); ?><br>
            Admin Logged In: <?php echo isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'YES' : 'NO') : 'NOT SET'; ?><br>
            Session Data: <?php echo !empty($_SESSION) ? json_encode($_SESSION, JSON_PRETTY_PRINT) : 'Empty'; ?>
        </div>
        
        <?php if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']): ?>
            <h2>Login Required</h2>
            <div class="status info">
                Use credentials: <strong>admin</strong> / <strong>password123</strong>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" value="password123" required>
                </div>
                
                <button type="submit">Login</button>
            </form>
        <?php else: ?>
            <div class="status success">
                ✅ You are logged in!
            </div>
            
            <h2>Test Navigation</h2>
            <a href="debug_dashboard.php" class="btn-link">🔍 Test Debug Dashboard</a>
            <a href="minimal_dashboard.php" class="btn-link">📊 Test Minimal Dashboard</a>
            <a href="?logout=1" class="btn-link">🚪 Logout</a>
            
            <h3>Next Steps:</h3>
            <ol>
                <li>Click "Test Debug Dashboard" to see if authentication works</li>
                <li>If that works, your original dashboard should work too</li>
                <li>If not, check file paths and session configuration</li>
            </ol>
        <?php endif; ?>
        
        <div class="debug-info">
            <strong>File Path Debug:</strong><br>
            Current file: <?php echo __FILE__; ?><br>
            Document root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Not set'; ?><br>
            Request URI: <?php echo $_SERVER['REQUEST_URI'] ?? 'Not set'; ?><br>
            Session save path: <?php echo session_save_path(); ?><br>
            Session cookie path: <?php echo session_get_cookie_params()['path']; ?>
        </div>
    </div>
</body>
</html>