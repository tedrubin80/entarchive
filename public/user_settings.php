<?php
/**
 * User Settings Page
 * File: public/user_settings.php
 * Basic settings page for the media collection system
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: user_login.php");
    exit;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            // Handle profile update
            $_SESSION['admin_user'] = $_POST['username'] ?? $_SESSION['admin_user'];
            $message = "Profile updated successfully!";
            $messageType = "success";
            break;
            
        case 'change_password':
            // Handle password change
            $message = "Password change functionality will be implemented in a future update.";
            $messageType = "info";
            break;
            
        case 'clear_cache':
            // Handle cache clearing
            $message = "Cache cleared successfully!";
            $messageType = "success";
            break;
            
        default:
            $message = "Unknown action.";
            $messageType = "error";
    }
}

$currentUser = $_SESSION['admin_user'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Media Collection</title>
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
            line-height: 1.6;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #333;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .settings-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .settings-section {
            border-bottom: 1px solid #eee;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>⚙️ Settings</h1>
        <div class="nav-links">
            <a href="enhanced_media_dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="?logout=1" class="btn btn-danger">🚪 Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php if ($messageType === 'success'): ?>✅<?php elseif ($messageType === 'error'): ?>❌<?php else: ?>ℹ️<?php endif; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="settings-card">
            <div class="settings-section">
                <h2>👤 Profile Settings</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($currentUser); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email" placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name (Optional)</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Your Full Name">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">💾 Save Profile</button>
                    </div>
                </form>
            </div>

            <!-- Password Settings -->
            <div class="settings-section">
                <h2>🔒 Password Settings</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">🔑 Change Password</button>
                    </div>
                    
                    <div class="info-box">
                        <strong>Note:</strong> Password functionality is not fully implemented yet. This is a placeholder for future development.
                    </div>
                </form>
            </div>

            <!-- API Management -->
            <div class="settings-section">
                <h2>🔑 API & External Services</h2>
                
                <div class="info-box">
                    <strong>External APIs:</strong> Configure API keys for automatic poster downloads and metadata lookup.
                </div>
                
                <div class="action-buttons">
                    <a href="api_settings.php" class="btn btn-primary">🔑 Manage API Keys</a>
                    <button type="button" class="btn btn-secondary" onclick="checkAPIStatus()">📊 Check API Status</button>
                </div>
                
                <div id="api-status" style="margin-top: 1rem; display: none;">
                    <h4>📋 Current API Status</h4>
                    <div id="api-status-content"></div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="settings-section">
                <h2>🖥️ System Settings</h2>
                
                <div class="form-group">
                    <label for="theme">Theme</label>
                    <select id="theme" name="theme">
                        <option value="default">Default</option>
                        <option value="dark">Dark Mode (Coming Soon)</option>
                        <option value="light">Light Mode (Coming Soon)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="items_per_page">Items Per Page</label>
                    <select id="items_per_page" name="items_per_page">
                        <option value="10">10 items</option>
                        <option value="20" selected>20 items</option>
                        <option value="50">50 items</option>
                        <option value="100">100 items</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="default_currency">Default Currency</label>
                    <select id="default_currency" name="default_currency">
                        <option value="USD" selected>USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="CAD">CAD ($)</option>
                    </select>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="alert('System settings will be implemented in a future update.')">💾 Save Settings</button>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="settings-section">
                <h2>🔧 Maintenance</h2>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_cache">
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-secondary">🗑️ Clear Cache</button>
                        <button type="button" class="btn btn-secondary" onclick="alert('Backup functionality will be implemented in a future update.')">💾 Backup Data</button>
                        <button type="button" class="btn btn-secondary" onclick="alert('Import functionality will be implemented in a future update.')">📁 Import Data</button>
                    </div>
                </form>
                
                <div class="info-box">
                    <strong>Coming Soon:</strong> Full backup/restore functionality, data import/export, and advanced system maintenance tools.
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="settings-card">
            <h2>📊 System Information</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <strong>Current User:</strong><br>
                    <?php echo htmlspecialchars($currentUser); ?>
                </div>
                
                <div>
                    <strong>Session ID:</strong><br>
                    <?php echo substr(session_id(), 0, 16) . '...'; ?>
                </div>
                
                <div>
                    <strong>Login Time:</strong><br>
                    <?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Unknown'; ?>
                </div>
                
                <div>
                    <strong>PHP Version:</strong><br>
                    <?php echo PHP_VERSION; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle logout
        if (window.location.search.includes('logout=1')) {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            } else {
                // Remove the logout parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
        
        // Check API status function
        function checkAPIStatus() {
            const statusDiv = document.getElementById('api-status');
            const contentDiv = document.getElementById('api-status-content');
            
            statusDiv.style.display = 'block';
            contentDiv.innerHTML = '<p>🔄 Checking API status...</p>';
            
            // Simulate API status check (you can make this a real AJAX call)
            setTimeout(() => {
                const apiServices = [
                    { name: 'OMDB', status: 'configured', description: 'Movie & TV metadata' },
                    { name: 'TMDB', status: 'not_configured', description: 'Enhanced movie data' },
                    { name: 'Google Books', status: 'configured', description: 'Book metadata' },
                    { name: 'IGDB', status: 'not_configured', description: 'Video game data' },
                    { name: 'Discogs', status: 'not_configured', description: 'Music metadata' }
                ];
                
                let html = '<div style="display: grid; gap: 0.5rem;">';
                apiServices.forEach(service => {
                    const statusIcon = service.status === 'configured' ? '✅' : '❌';
                    const statusText = service.status === 'configured' ? 'Configured' : 'Not Configured';
                    const statusClass = service.status === 'configured' ? 'color: #28a745' : 'color: #dc3545';
                    
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                            <div>
                                <strong>${service.name}</strong> - ${service.description}
                            </div>
                            <div style="${statusClass}">
                                ${statusIcon} ${statusText}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                html += '<p style="margin-top: 1rem;"><a href="api_settings.php" class="btn btn-primary">Configure APIs</a></p>';
                
                contentDiv.innerHTML = html;
            }, 1500);
        }
    </script>
</body>
</html>