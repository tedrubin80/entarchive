<?php
/**
 * Optional Dashboard Enhancements
 * File: dashboard_enhancements.php
 * Additional features you can add to the integrated dashboard
 */

// 1. SHARED AUTHENTICATION ENHANCEMENT
// Add this to your enhanced dashboard to sync authentication with collecting system

function syncWithCollectingSystem($username, $sessionToken) {
    // This would sync login state with your collecting system
    // You could implement this by:
    // 1. Creating a shared session table
    // 2. Using API calls to sync authentication
    // 3. Setting cookies that both systems can read
    
    try {
        $collectingAuthUrl = "https://currentlytedcollects.com/api.php";
        $postData = [
            'action' => 'sync_auth',
            'username' => $username,
            'session_token' => $sessionToken,
            'source' => 'media_dashboard'
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($postData),
                'timeout' => 5
            ]
        ]);
        
        $result = file_get_contents($collectingAuthUrl, false, $context);
        return json_decode($result, true);
        
    } catch (Exception $e) {
        error_log("Auth sync failed: " . $e->getMessage());
        return false;
    }
}

// 2. CROSS-SYSTEM SEARCH ENHANCEMENT
// Add this function to search both systems simultaneously

function searchAcrossSystems($query) {
    $results = [
        'media' => [],
        'collecting' => [],
        'total' => 0
    ];
    
    try {
        // Search media system
        if (isset($pdo)) {
            $stmt = $pdo->prepare("
                SELECT id, title, category, 'media' as system_type 
                FROM collection 
                WHERE title LIKE ? OR description LIKE ? 
                LIMIT 10
            ");
            $stmt->execute(["%$query%", "%$query%"]);
            $results['media'] = $stmt->fetchAll();
        }
        
        // Search collecting system via API
        $collectingSearchUrl = "https://currentlytedcollects.com/api.php";
        $searchData = [
            'action' => 'search',
            'query' => $query,
            'limit' => 10
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($searchData),
                'timeout' => 5
            ]
        ]);
        
        $collectingResponse = file_get_contents($collectingSearchUrl, false, $context);
        $collectingData = json_decode($collectingResponse, true);
        
        if ($collectingData && isset($collectingData['items'])) {
            $results['collecting'] = $collectingData['items'];
        }
        
        $results['total'] = count($results['media']) + count($results['collecting']);
        
    } catch (Exception $e) {
        error_log("Cross-system search error: " . $e->getMessage());
    }
    
    return $results;
}

// 3. UNIFIED STATISTICS WIDGET
// Add this to show combined stats from both systems

function getCombinedStatistics() {
    $stats = [
        'media_items' => 0,
        'collecting_items' => 0,
        'total_items' => 0,
        'media_value' => 0,
        'collecting_value' => 0,
        'total_value' => 0,
        'recent_activity' => []
    ];
    
    try {
        // Get media system stats
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(purchase_price), 0) as value FROM collection");
            $mediaStats = $stmt->fetch();
            $stats['media_items'] = $mediaStats['count'];
            $stats['media_value'] = $mediaStats['value'];
        }
        
        // Get collecting system stats via API
        $collectingStatsUrl = "https://currentlytedcollects.com/api.php";
        $statsData = ['action' => 'get_stats'];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($statsData),
                'timeout' => 5
            ]
        ]);
        
        $collectingResponse = file_get_contents($collectingStatsUrl, false, $context);
        $collectingStats = json_decode($collectingResponse, true);
        
        if ($collectingStats) {
            $stats['collecting_items'] = $collectingStats['total_items'] ?? 0;
            $stats['collecting_value'] = $collectingStats['total_value'] ?? 0;
        }
        
        // Calculate totals
        $stats['total_items'] = $stats['media_items'] + $stats['collecting_items'];
        $stats['total_value'] = $stats['media_value'] + $stats['collecting_value'];
        
    } catch (Exception $e) {
        error_log("Combined stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// 4. NOTIFICATION SYSTEM
// Add this to show notifications from both systems

function getUnifiedNotifications() {
    $notifications = [];
    
    try {
        // Media system notifications
        if (isset($pdo)) {
            $stmt = $pdo->query("
                SELECT 'media' as type, 'New item added' as message, created_at 
                FROM collection 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $mediaNotifications = $stmt->fetchAll();
            $notifications = array_merge($notifications, $mediaNotifications);
        }
        
        // Collecting system notifications via API
        $notificationsUrl = "https://currentlytedcollects.com/api.php";
        $notifData = ['action' => 'get_recent_activity'];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($notifData),
                'timeout' => 5
            ]
        ]);
        
        $collectingResponse = file_get_contents($notificationsUrl, false, $context);
        $collectingNotifications = json_decode($collectingResponse, true);
        
        if ($collectingNotifications && isset($collectingNotifications['activities'])) {
            foreach ($collectingNotifications['activities'] as $activity) {
                $notifications[] = [
                    'type' => 'collecting',
                    'message' => $activity['description'] ?? 'Activity recorded',
                    'created_at' => $activity['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Sort by date
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($notifications, 0, 10);
        
    } catch (Exception $e) {
        error_log("Notifications error: " . $e->getMessage());
        return [];
    }
}

// 5. QUICK ADD WIDGET
// Add this HTML to your dashboard for quick item addition

function renderQuickAddWidget() {
    return '
    <div class="widget quick-add-widget">
        <h3><i class="fas fa-plus"></i> Quick Add</h3>
        <form id="quick-add-form" style="display: flex; flex-direction: column; gap: 0.75rem;">
            <select id="quick-system" style="padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
                <option value="media">Add to Media Collection</option>
                <option value="collecting">Add to Collectibles</option>
            </select>
            
            <input type="text" id="quick-item-name" placeholder="Item name..." 
                   style="padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
            
            <input type="text" id="quick-category" placeholder="Category..." 
                   style="padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
            
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem;">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </form>
    </div>
    
    <script>
    document.getElementById("quick-add-form").addEventListener("submit", function(e) {
        e.preventDefault();
        
        const system = document.getElementById("quick-system").value;
        const itemName = document.getElementById("quick-item-name").value;
        const category = document.getElementById("quick-category").value;
        
        if (!itemName.trim()) {
            alert("Please enter an item name");
            return;
        }
        
        if (system === "media") {
            // Redirect to media add item page with prefilled data
            window.open(`user_add_item.php?title=${encodeURIComponent(itemName)}&category=${encodeURIComponent(category)}`, "_blank");
        } else {
            // Open collecting system add form
            window.open("https://currentlytedcollects.com/index.php#add-item", "_blank");
        }
        
        // Clear form
        document.getElementById("quick-item-name").value = "";
        document.getElementById("quick-category").value = "";
    });
    </script>
    ';
}

// 6. BACKUP AND SYNC UTILITIES
// Add these functions for data management

function createUnifiedBackup() {
    $backup = [
        'timestamp' => date('Y-m-d H:i:s'),
        'media_system' => [],
        'collecting_system' => []
    ];
    
    try {
        // Backup media system
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT * FROM collection");
            $backup['media_system'] = $stmt->fetchAll();
        }
        
        // Request backup from collecting system
        $backupUrl = "https://currentlytedcollects.com/api.php";
        $backupData = ['action' => 'export_data'];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($backupData),
                'timeout' => 30
            ]
        ]);
        
        $collectingResponse = file_get_contents($backupUrl, false, $context);
        $collectingBackup = json_decode($collectingResponse, true);
        
        if ($collectingBackup) {
            $backup['collecting_system'] = $collectingBackup;
        }
        
        // Save unified backup
        $backupFile = 'backups/unified_backup_' . date('Y-m-d_H-i-s') . '.json';
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
        
        return $backupFile;
        
    } catch (Exception $e) {
        error_log("Unified backup error: " . $e->getMessage());
        return false;
    }
}

// 7. ADVANCED DASHBOARD WIDGET HTML
// Add this to your dashboard for enhanced functionality

function renderAdvancedWidgets() {
    return '
    <!-- Combined Statistics Widget -->
    <div class="widget combined-stats">
        <h3><i class="fas fa-chart-pie"></i> Combined Collection Stats</h3>
        <div class="combined-stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
            <div class="stat-item">
                <div class="stat-number" id="total-items">-</div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="total-value">-</div>
                <div class="stat-label">Total Value</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="media-items">-</div>
                <div class="stat-label">Media Items</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="collecting-items">-</div>
                <div class="stat-label">Collectibles</div>
            </div>
        </div>
    </div>
    
    <!-- Universal Search Widget -->
    <div class="widget universal-search">
        <h3><i class="fas fa-search"></i> Search All Collections</h3>
        <div style="display: flex; gap: 0.5rem;">
            <input type="text" id="universal-search-input" placeholder="Search across all collections..." 
                   style="flex: 1; padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
            <button onclick="performUniversalSearch()" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div id="search-results" style="margin-top: 1rem; max-height: 200px; overflow-y: auto;"></div>
    </div>
    
    <script>
    // Load combined statistics
    function loadCombinedStats() {
        // This would make AJAX calls to get stats from both systems
        // For now, showing placeholder implementation
        console.log("Loading combined statistics...");
    }
    
    // Perform universal search
    function performUniversalSearch() {
        const query = document.getElementById("universal-search-input").value;
        if (!query.trim()) return;
        
        const resultsDiv = document.getElementById("search-results");
        resultsDiv.innerHTML = "<p>Searching...</p>";
        
        // This would perform the actual cross-system search
        console.log("Searching for:", query);
        
        // Placeholder results
        setTimeout(() => {
            resultsDiv.innerHTML = `
                <div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                    <strong>Media:</strong> Found results in media collection
                </div>
                <div style="padding: 0.5rem;">
                    <strong>Collectibles:</strong> Found results in collectibles
                </div>
            `;
        }, 1000);
    }
    
    // Initialize widgets
    document.addEventListener("DOMContentLoaded", function() {
        loadCombinedStats();
        
        // Enter key search
        document.getElementById("universal-search-input").addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                performUniversalSearch();
            }
        });
    });
    </script>
    ';
}

// 8. CONFIGURATION OPTIONS
// Add these configuration constants at the top of your dashboard

define('ENABLE_CROSS_SYSTEM_SEARCH', true);
define('ENABLE_UNIFIED_STATS', true);
define('ENABLE_SHARED_AUTH', false); // Enable when ready to sync authentication
define('ENABLE_QUICK_ADD', true);
define('COLLECTING_SYSTEM_TIMEOUT', 5); // Seconds to wait for collecting system responses
define('AUTO_REFRESH_IFRAME', false); // Whether to auto-refresh the collecting iframe

// Usage in your dashboard:
// if (ENABLE_QUICK_ADD) {
//     echo renderQuickAddWidget();
// }
// 
// if (ENABLE_UNIFIED_STATS) {
//     $combinedStats = getCombinedStatistics();
//     // Display combined stats
// }

?>