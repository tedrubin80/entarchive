<?php
/**
 * Enhanced Admin Login Page
 * Compatible with existing admin table structure with security features
 */
session_start();

// Prevent access if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

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

// Load configuration
if (!safeInclude('config.php')) {
    die('Configuration file not found. Please ensure config.php exists.');
}

$error = '';
$success = '';
$lockoutTime = 0;

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if user is currently locked out
function isLockedOut($pdo, $username) {
    $stmt = $pdo->prepare("SELECT locked_until FROM admins WHERE username = ? AND locked_until > NOW()");
    $stmt->execute([$username]);
    $result = $stmt->fetch();
    
    if ($result) {
        return strtotime($result['locked_until']);
    }
    return false;
}

// Update login attempts
function updateLoginAttempts($pdo, $username, $success = false) {
    if ($success) {
        // Reset attempts on successful login
        $stmt = $pdo->prepare("
            UPDATE admins 
            SET login_attempts = 0, locked_until = NULL, last_login = NOW() 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    } else {
        // Increment attempts and check if we need to lock the account
        $stmt = $pdo->prepare("
            UPDATE admins 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE 
                    WHEN login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until 
                END
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check if user is locked out
        $lockoutTime = isLockedOut($pdo, $username);
        
        if ($lockoutTime) {
            $remainingTime = ceil(($lockoutTime - time()) / 60);
            $error = "Account is locked due to multiple failed login attempts. Please try again in {$remainingTime} minutes.";
        } else {
            // Attempt login
            $stmt = $pdo->prepare("
                SELECT id, username, password, email, full_name, role, login_attempts 
                FROM admins 
                WHERE username = ? AND role IN ('admin', 'editor', 'viewer')
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Successful login
                updateLoginAttempts($pdo, $username, true);
                
                // Set session variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['full_name'] ?: $admin['username'];
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true); // 30 days
                    
                    // Store token in database (you might want to add a remember_tokens table)
                    $stmt = $pdo->prepare("UPDATE admins SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $admin['id']]);
                }
                
                // Log successful login
                error_log("Successful login: {$admin['username']} from " . $_SERVER['REMOTE_ADDR']);
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                // Failed login
                updateLoginAttempts($pdo, $username, false);
                
                // Get current attempt count
                if ($admin) {
                    $attempts = $admin['login_attempts'] + 1;
                    $remaining = 5 - $attempts;
                    
                    if ($attempts >= 5) {
                        $error = "Account locked due to multiple failed login attempts. Please try again in 15 minutes.";
                    } elseif ($attempts >= 3) {
                        $error = "Invalid credentials. {$remaining} attempts remaining before account lockout.";
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
                
                // Log failed login attempt
                error_log("Failed login attempt: {$username} from " . $_SERVER['REMOTE_ADDR']);
            }
        }
    }
}

// Check for remember me cookie
if (!isset($_SESSION['admin_logged_in']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM admins WHERE remember_token = ?");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['full_name'] ?: $admin['username'];
        
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Media Collection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        .input-group .form-input {
            padding-left: 3rem;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-checkbox input {
            width: auto;
        }
        
        .form-checkbox label {
            color: #7f8c8d;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .security-info {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .security-info h4 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-links a {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
        
        .loading {
            display: none;
        }
        
        .loading.active {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-collections"></i> Media Collection</h1>
            <p>Admin Access Portal</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="loginForm">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required 
                           autocomplete="username">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Enter your password"
                           required 
                           autocomplete="current-password">
                </div>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="btn btn-primary" id="loginBtn">
                <span class="btn-text">Sign In</span>
                <span class="loading" id="loading"></span>
            </button>
        </form>
        
        <div class="security-info">
            <h4><i class="fas fa-shield-alt"></i> Security Information</h4>
            <ul style="margin: 0; padding-left: 1.5rem;">
                <li>Maximum 5 login attempts before 15-minute lockout</li>
                <li>All login attempts are logged for security</li>
                <li>Session expires after 24 hours of inactivity</li>
            </ul>
        </div>
        
        <div class="footer-links">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            const btnText = document.querySelector('.btn-text');
            
            btn.disabled = true;
            loading.classList.add('active');
            btnText.textContent = 'Signing In...';
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Clear error messages after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>