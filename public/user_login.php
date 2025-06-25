<?php
/**
 * User Login Page
 * File: public/user_login.php
 * Redirects to enhanced_media_dashboard.php after successful login
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header("Location: enhanced_media_dashboard.php");
    exit;
}

$error = '';
$success = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    $success = "You have been logged out successfully.";
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Simple authentication for development
        // Replace this with proper database authentication in production
        if ($username === 'admin' && $password === 'password123') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_role'] = 'admin';
            $_SESSION['login_time'] = time();
            
            // Redirect to dashboard
            header("Location: enhanced_media_dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        
        .login-btn:hover {
            opacity: 0.9;
        }
        
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .message.success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .demo-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .demo-info h4 {
            color: #495057;
            margin-bottom: 8px;
        }
        
        .demo-info p {
            color: #6c757d;
            margin: 4px 0;
        }
        
        .demo-info strong {
            color: #495057;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 12px;
            margin-top: 20px;
            font-size: 12px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>📚 Media Collection</h1>
            <p>Personal Media Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="admin" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="password123" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="login-btn">🔐 Login to Dashboard</button>
        </form>
        
        <div class="demo-info">
            <h4>🔧 Development Mode</h4>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> password123</p>
            <p>These credentials are pre-filled for testing.</p>
        </div>
        
        <div class="debug-info">
            <strong>Session Debug:</strong><br>
            Session ID: <?php echo session_id(); ?><br>
            Current Time: <?php echo date('Y-m-d H:i:s'); ?><br>
            Redirect Target: enhanced_media_dashboard.php
        </div>
        
        <div class="footer">
            <p>© 2025 Personal Media Management System</p>
        </div>
    </div>
</body>
</html>