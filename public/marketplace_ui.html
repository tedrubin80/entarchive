<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Sync - Media Collection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .sync-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .sync-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .sync-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .platform-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .platform-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .ebay { background: linear-gradient(135deg, #e53238, #0064d2); }
        .amazon { background: linear-gradient(135deg, #ff9900, #232f3e); }
        .mercari { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
        .platform-info h3 {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .sync-options {
            margin: 1.5rem 0;
        }
        
        .option {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .option:hover {
            background: #e9ecef;
        }
        
        .option i {
            margin-right: 1rem;
            color: #6c757d;
            width: 20px;
        }
        
        .option-content {
            flex: 1;
        }
        
        .option-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .option-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .sync-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .activity-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .activity-success { background: #d4edda; color: #155724; }
        .activity-warning { background: #fff3cd; color: #856404; }
        .activity-info { background: #d1ecf1; color: #0c5460; }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .activity-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #adb5bd;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .sync-grid {
                grid-template-columns: 1fr;
            }
            
            .sync-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-sync-alt"></i> Marketplace Sync</h1>
            <p>Sync your wishlists and monitor prices across eBay, Amazon, and more</p>
        </div>
        
        <div class="sync-grid">
            <!-- eBay Sync Card -->
            <div class="sync-card">
                <div class="platform-header">
                    <div class="platform-icon ebay">
                        <i class="fab fa-ebay"></i>
                    </div>
                    <div class="platform-info">
                        <h3>eBay</h3>
                        <span class="status-badge status-enabled">
                            <i class="fas fa-check"></i> Connected
                        </span>
                    </div>
                </div>
                
                <div class="sync-options">
                    <div class="option">
                        <i class="fas fa-heart"></i>
                        <div class="option-content">
                            <div class="option-title">Watch List Sync</div>
                            <div class="option-desc">Import items from your eBay watch list</div>
                        </div>
                    </div>
                    
                    <div class="option">
                        <i class="fas fa-chart-line"></i>
                        <div class="option-content">
                            <div class="option-title">Price Monitoring</div>
                            <div class="option-desc">Track price changes on watched items</div>
                        </div>
                    </div>
                    
                    <div class="option">
                        <i class="fas fa-bell"></i>
                        <div class="option-content">
                            <div class="option-title">Ending Soon Alerts</div>
                            <div class="option-desc">Get notified when auctions are ending</div>
                        </div>
                    </div>
                </div>
                
                <div class="sync-actions">
                    <button class="btn btn-primary" onclick="syncEbayWatchlist()">
                        <i class="fas fa-download"></i> Sync Now
                    </button>
                    <button class="btn btn-secondary" onclick="configureEbay()">
                        <i class="fas fa-cog"></i> Configure
                    </button>
                </div>
            </div>
            
            <!-- Amazon Sync Card -->
            <div class="sync-card">
                <div class="platform-header">
                    <div class="platform-icon amazon">
                        <i class="fab fa-amazon"></i>
                    </div>
                    <div class="platform-info">
                        <h3>Amazon</h3>
                        <span class="status-badge status-disabled">
                            <i class="fas fa-times"></i> Not Configured
                        </span>
                    </div>
                </div>
                
                <div class="sync-options">
                    <div class="option">
                        <i class="fas fa-list"></i>
                        <div class="option-content">
                            <div class="option-title">Wishlist Import</div>
                            <div class="option-desc">Import from Amazon wishlists (requires setup)</div>
                        </div>
                    </div>
                    
                    <div class="option">
                        <i class="fas fa-tags"></i>
                        <div class="option-content">
                            <div class="option-title">Price Tracking</div>
                            <div class="option-desc">Monitor Amazon price changes</div>
                        </div>
                    </div>
                    
                    <div class="option">
                        <i class="fas fa-upload"></i>
                        <div class="option-content">
                            <div class="option-title">CSV Import</div>
                            <div class="option-desc">Upload exported wishlist CSV</div>
                        </div>
                    </div>
                </div>
                
                <div class="sync-actions">
                    <button class="btn btn-warning" onclick="setupAmazon()">
                        <i class="fas fa-plus"></i> Setup API
                    </button>
                    <button class="btn btn-secondary" onclick="importCSV('amazon')">
                        <i class="fas fa-file-csv"></i> Import CSV
                    </button>
                </div>
            </div>
            
            <!-- Mercari Sync Card -->
            <div class="sync-card">
                <div class="platform-header">
                    <div class="platform-icon mercari">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="platform-info">
                        <h3>Mercari</h3>
                        <span class="status-badge status-disabled">
                            <i class="fas fa-times"></i> Coming Soon
                        </span>
                    </div>
                </div>
                
                <div class="sync-options">
                    <div class="option">
                        <i class="fas fa-search"></i>
                        <div class="option-content">
                            <div class="option-title">Search Monitoring</div>
                            <div class="option-desc">Get alerts for new listings matching your criteria</div>
                        </div>
                    </div>
                    
                    <div class="option">
                        <i class="fas fa-star"></i>
                        <div class="option-content">
                            <div class="option-title">Liked Items</div>
                            <div class="option-desc">Import your liked items to wishlist</div>
                        </div>
                    </div>
                </div>
                
                <div class="sync-actions">
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-clock"></i> Coming Soon
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Stats Section -->
        <div class="stats-section">
            <h2><i class="fas fa-chart-bar"></i> Sync Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="total-synced">0</div>
                    <div class="stat-label">Items Synced Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="price-alerts">0</div>
                    <div class="stat-label">Price Alerts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="tracked-items">0</div>
                    <div class="stat-label">Items Being Tracked</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="avg-savings">$0</div>
                    <div class="stat-label">Average Savings</div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="activity-section">
            <div class="activity-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <button class="btn btn-secondary" onclick="refreshActivity()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
            
            <div id="activity-list">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading recent activity...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CSV Import Modal (Hidden by default) -->
    <div id="csv-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 16px; max-width: 500px; width: 90%;">
            <h3>Import CSV Wishlist</h3>
            <p>Upload a CSV file exported from your marketplace wishlist.</p>
            <input type="file" id="csv-file" accept=".csv" style="margin: 1rem 0; width: 100%;">
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeCsvModal()">Cancel</button>
                <button class="btn btn-primary" onclick="uploadCSV()">Upload</button>
            </div>
        </div>
    </div>

    <script>
        // Sample data for demonstration
        const sampleActivity = [
            {
                type: 'success',
                icon: 'fas fa-download',
                title: 'eBay Sync Completed',
                desc: 'Imported 5 new items from watch list',
                time: '2 minutes ago'
            },
            {
                type: 'warning',
                icon: 'fas fa-exclamation-triangle',
                title: 'Price Alert',
                desc: 'Batman Vol. 1 dropped to $12.99 (Target: $15.00)',
                time: '15 minutes ago'
            },
            {
                type: 'info',
                icon: 'fas fa-chart-line',
                title: 'Price Check Complete',
                desc: 'Updated prices for 25 items',
                time: '1 hour ago'
            },
            {
                type: 'success',
                icon: 'fas fa-bell',
                title: 'Auction Ending Soon',
                desc: 'Star Wars Blu-ray set ending in 2 hours',
                time: '2 hours ago'
            }
        ];
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadActivity();
        });
        
        function loadStats() {
            // Simulate loading stats
            setTimeout(() => {
                document.getElementById('total-synced').textContent = '12';
                document.getElementById('price-alerts').textContent = '3';
                document.getElementById('tracked-items').textContent = '47';
                document.getElementById('avg-savings').textContent = '$8.50';
            }, 1000);
        }
        
        function loadActivity() {
            setTimeout(() => {
                const activityList = document.getElementById('activity-list');
                activityList.innerHTML = '';
                
                sampleActivity.forEach(activity => {
                    const activityItem = document.createElement('div');
                    activityItem.className = 'activity-item';
                    activityItem.innerHTML = `
                        <div class="activity-icon activity-${activity.type}">
                            <i class="${activity.icon}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.title}</div>
                            <div class="activity-desc">${activity.desc}</div>
                        </div>
                        <div class="activity-time">${activity.time}</div>
                    `;
                    activityList.appendChild(activityItem);
                });
            }, 1500);
        }
        
        function syncEbayWatchlist() {
            showLoading('Syncing eBay watch list...');
            
            // Simulate API call
            setTimeout(() => {
                showAlert('success', 'eBay sync completed! Imported 5 new items.');
                loadActivity();
                loadStats();
            }, 3000);
        }
        
        function configureEbay() {
            alert('eBay configuration panel would open here');
        }
        
        function setupAmazon() {
            alert('Amazon API setup wizard would open here');
        }
        
        function importCSV(platform) {
            document.getElementById('csv-modal').style.display = 'block';
        }
        
        function closeCsvModal() {
            document.getElementById('csv-modal').style.display = 'none';
        }
        
        function uploadCSV() {
            const fileInput = document.getElementById('csv-file');
            if (fileInput.files.length === 0) {
                alert('Please select a CSV file');
                return;
            }
            
            showLoading('Processing CSV file...');
            closeCsvModal();
            
            // Simulate upload
            setTimeout(() => {
                showAlert('success', 'CSV imported successfully! Added 8 items to wishlist.');
                loadActivity();
                loadStats();
            }, 2000);
        }
        
        function refreshActivity() {
            document.getElementById('activity-list').innerHTML = '<div class="loading"><i class="fas fa-spinner"></i><p>Refreshing...</p></div>';
            loadActivity();
        }
        
        function showLoading(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info';
            alert.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
            
            document.querySelector('.container').insertBefore(alert, document.querySelector('.sync-grid'));
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        function showAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'info'}"></i> ${message}`;
            
            document.querySelector('.container').insertBefore(alert, document.querySelector('.sync-grid'));
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>