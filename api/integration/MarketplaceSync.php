<?php
/**
 * Marketplace Sync Integration System
 * Handles eBay, Amazon, and other marketplace integrations
 * File: api/integrations/MarketplaceSync.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/inc_functions.php';

class MarketplaceSync {
    private $pdo;
    private $config;
    private $logger;
    
    public function __construct() {
        $this->pdo = getDbConnection();
        $this->config = $this->loadMarketplaceConfig();
        $this->logger = new Logger('marketplace_sync');
    }
    
    /**
     * Load marketplace API configurations
     */
    private function loadMarketplaceConfig() {
        return [
            'ebay' => [
                'app_id' => EBAY_APP_ID ?? '',
                'cert_id' => EBAY_CERT_ID ?? '',
                'dev_id' => EBAY_DEV_ID ?? '',
                'user_token' => EBAY_USER_TOKEN ?? '',
                'sandbox' => EBAY_SANDBOX ?? true,
                'site_id' => EBAY_SITE_ID ?? 0, // 0 = US
                'enabled' => !empty(EBAY_APP_ID)
            ],
            'amazon' => [
                'access_key' => AMAZON_ACCESS_KEY ?? '',
                'secret_key' => AMAZON_SECRET_KEY ?? '',
                'associate_tag' => AMAZON_ASSOCIATE_TAG ?? '',
                'region' => AMAZON_REGION ?? 'US',
                'enabled' => !empty(AMAZON_ACCESS_KEY)
            ]
        ];
    }
    
    /**
     * Sync user's eBay watching list
     */
    public function syncEbayWatchlist($userId = null) {
        if (!$this->config['ebay']['enabled']) {
            throw new Exception('eBay API not configured');
        }
        
        try {
            $watchingItems = $this->getEbayWatchingItems();
            $imported = 0;
            
            foreach ($watchingItems as $item) {
                if ($this->importEbayWatchItem($item, $userId)) {
                    $imported++;
                }
            }
            
            $this->logger->info("Imported {$imported} items from eBay watchlist");
            return ['success' => true, 'imported' => $imported];
            
        } catch (Exception $e) {
            $this->logger->error("eBay sync failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get eBay watching items via API
     */
    private function getEbayWatchingItems() {
        $endpoint = $this->config['ebay']['sandbox'] 
            ? 'https://api.sandbox.ebay.com/ws/api.dll'
            : 'https://api.ebay.com/ws/api.dll';
        
        $requestXml = $this->buildEbayWatchlistRequest();
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => [
                'X-EBAY-API-SITEID: ' . $this->config['ebay']['site_id'],
                'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
                'X-EBAY-API-CALL-NAME: GetMyeBayBuying',
                'X-EBAY-API-APP-NAME: ' . $this->config['ebay']['app_id'],
                'X-EBAY-API-DEV-NAME: ' . $this->config['ebay']['dev_id'],
                'X-EBAY-API-CERT-NAME: ' . $this->config['ebay']['cert_id'],
                'Content-Type: text/xml',
                'Content-Length: ' . strlen($requestXml)
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestXml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("eBay API returned HTTP {$httpCode}");
        }
        
        return $this->parseEbayResponse($response);
    }
    
    /**
     * Build eBay watchlist request XML
     */
    private function buildEbayWatchlistRequest() {
        return '<?xml version="1.0" encoding="utf-8"?>
        <GetMyeBayBuyingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . $this->config['ebay']['user_token'] . '</eBayAuthToken>
            </RequesterCredentials>
            <WatchList>
                <Include>true</Include>
                <Pagination>
                    <EntriesPerPage>200</EntriesPerPage>
                    <PageNumber>1</PageNumber>
                </Pagination>
            </WatchList>
            <DetailLevel>ReturnAll</DetailLevel>
        </GetMyeBayBuyingRequest>';
    }
    
    /**
     * Parse eBay XML response
     */
    private function parseEbayResponse($xmlResponse) {
        $xml = simplexml_load_string($xmlResponse);
        
        if ($xml === false) {
            throw new Exception('Invalid XML response from eBay');
        }
        
        // Check for API errors
        if (isset($xml->Errors)) {
            $error = (string)$xml->Errors->LongMessage;
            throw new Exception("eBay API Error: {$error}");
        }
        
        $items = [];
        
        if (isset($xml->WatchList->ItemArray->Item)) {
            foreach ($xml->WatchList->ItemArray->Item as $item) {
                $items[] = [
                    'item_id' => (string)$item->ItemID,
                    'title' => (string)$item->Title,
                    'current_price' => (float)$item->SellingStatus->CurrentPrice,
                    'currency' => (string)$item->SellingStatus->CurrentPrice['currencyID'],
                    'end_time' => (string)$item->ListingDetails->EndTime,
                    'url' => (string)$item->ListingDetails->ViewItemURL,
                    'image_url' => (string)$item->PictureDetails->PictureURL ?? '',
                    'category' => (string)$item->PrimaryCategory->CategoryName ?? '',
                    'condition' => (string)$item->ConditionDisplayName ?? '',
                    'seller' => (string)$item->Seller->UserID ?? '',
                    'location' => (string)$item->Location ?? ''
                ];
            }
        }
        
        return $items;
    }
    
    /**
     * Import eBay watch item to wishlist
     */
    private function importEbayWatchItem($item, $userId = null) {
        // Check if item already exists
        $existingSql = "SELECT id FROM wishlist WHERE external_id = ? AND source = 'ebay'";
        $existingStmt = $this->pdo->prepare($existingSql);
        $existingStmt->execute([$item['item_id']]);
        
        if ($existingStmt->fetch()) {
            // Update existing item
            return $this->updateEbayWatchItem($item);
        }
        
        // Determine media type from title/category
        $mediaType = $this->detectMediaType($item['title'], $item['category']);
        
        // Insert new wishlist item
        $sql = "INSERT INTO wishlist (
            title, media_type, max_price, current_market_price, 
            external_id, external_url, source, priority, 
            notes, image_url, date_added, user_id
        ) VALUES (?, ?, ?, ?, ?, ?, 'ebay', 'medium', ?, ?, NOW(), ?)";
        
        $stmt = $this->pdo->prepare($sql);
        
        $notes = sprintf(
            "Imported from eBay\nSeller: %s\nLocation: %s\nCondition: %s\nEnds: %s",
            $item['seller'],
            $item['location'],
            $item['condition'],
            $item['end_time']
        );
        
        return $stmt->execute([
            $item['title'],
            $mediaType,
            $item['current_price'],
            $item['current_price'],
            $item['item_id'],
            $item['url'],
            $notes,
            $item['image_url'],
            $userId
        ]);
    }
    
    /**
     * Sync Amazon wishlist
     */
    public function syncAmazonWishlist($userId = null, $wishlistId = null) {
        if (!$this->config['amazon']['enabled']) {
            throw new Exception('Amazon API not configured');
        }
        
        try {
            // Note: Amazon doesn't provide direct wishlist API access
            // This would require screen scraping or Product Advertising API workarounds
            $amazonItems = $this->getAmazonWishlistItems($wishlistId);
            $imported = 0;
            
            foreach ($amazonItems as $item) {
                if ($this->importAmazonWishItem($item, $userId)) {
                    $imported++;
                }
            }
            
            $this->logger->info("Imported {$imported} items from Amazon wishlist");
            return ['success' => true, 'imported' => $imported];
            
        } catch (Exception $e) {
            $this->logger->error("Amazon sync failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get Amazon wishlist items (placeholder - requires specific implementation)
     */
    private function getAmazonWishlistItems($wishlistId) {
        // This would typically require:
        // 1. Amazon Product Advertising API
        // 2. Screen scraping (against TOS)
        // 3. Browser automation
        // 4. Manual CSV export
        
        // For demo purposes, return empty array
        // In production, implement based on chosen method
        return [];
    }
    
    /**
     * Monitor price changes for existing wishlist items
     */
    public function monitorPriceChanges() {
        // Get active wishlist items with external sources
        $sql = "SELECT * FROM wishlist 
                WHERE date_acquired IS NULL 
                AND external_id IS NOT NULL 
                AND source IN ('ebay', 'amazon')
                AND (last_price_check IS NULL OR last_price_check < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                ORDER BY priority DESC, date_added ASC
                LIMIT 50";
        
        $stmt = $this->pdo->query($sql);
        $items = $stmt->fetchAll();
        
        $updated = 0;
        
        foreach ($items as $item) {
            try {
                $newPrice = $this->getCurrentPrice($item);
                
                if ($newPrice && $newPrice !== $item['current_market_price']) {
                    $this->updateItemPrice($item['id'], $newPrice);
                    
                    // Check for price alerts
                    if ($item['max_price'] && $newPrice <= $item['max_price']) {
                        $this->triggerPriceAlert($item, $newPrice);
                    }
                    
                    $updated++;
                }
                
                // Update last check time
                $updateCheckSql = "UPDATE wishlist SET last_price_check = NOW() WHERE id = ?";
                $updateCheckStmt = $this->pdo->prepare($updateCheckSql);
                $updateCheckStmt->execute([$item['id']]);
                
            } catch (Exception $e) {
                $this->logger->error("Price check failed for item {$item['id']}: " . $e->getMessage());
            }
        }
        
        return ['success' => true, 'updated' => $updated];
    }
    
    /**
     * Get current price for an item
     */
    private function getCurrentPrice($item) {
        switch ($item['source']) {
            case 'ebay':
                return $this->getEbayCurrentPrice($item['external_id']);
            case 'amazon':
                return $this->getAmazonCurrentPrice($item['external_id']);
            default:
                return null;
        }
    }
    
    /**
     * Get current eBay price
     */
    private function getEbayCurrentPrice($itemId) {
        $endpoint = $this->config['ebay']['sandbox'] 
            ? 'https://api.sandbox.ebay.com/ws/api.dll'
            : 'https://api.ebay.com/ws/api.dll';
        
        $requestXml = '<?xml version="1.0" encoding="utf-8"?>
        <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . $this->config['ebay']['user_token'] . '</eBayAuthToken>
            </RequesterCredentials>
            <ItemID>' . $itemId . '</ItemID>
            <DetailLevel>ReturnAll</DetailLevel>
        </GetItemRequest>';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => [
                'X-EBAY-API-SITEID: ' . $this->config['ebay']['site_id'],
                'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
                'X-EBAY-API-CALL-NAME: GetItem',
                'X-EBAY-API-APP-NAME: ' . $this->config['ebay']['app_id'],
                'X-EBAY-API-DEV-NAME: ' . $this->config['ebay']['dev_id'],
                'X-EBAY-API-CERT-NAME: ' . $this->config['ebay']['cert_id'],
                'Content-Type: text/xml'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestXml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $xml = simplexml_load_string($response);
        if ($xml && isset($xml->Item->SellingStatus->CurrentPrice)) {
            return (float)$xml->Item->SellingStatus->CurrentPrice;
        }
        
        return null;
    }
    
    /**
     * Update item price and log history
     */
    private function updateItemPrice($itemId, $newPrice) {
        // Update current price
        $sql = "UPDATE wishlist SET 
                current_market_price = ?, 
                last_price_update = NOW() 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newPrice, $itemId]);
        
        // Log price history
        $historySql = "INSERT INTO price_history (
            wishlist_id, price, source, checked_at
        ) VALUES (?, ?, 'market_sync', NOW())";
        $historyStmt = $this->pdo->prepare($historySql);
        $historyStmt->execute([$itemId, $newPrice]);
    }
    
    /**
     * Trigger price alert
     */
    private function triggerPriceAlert($item, $newPrice) {
        // Log the alert
        $alertSql = "INSERT INTO price_alerts (
            wishlist_id, old_price, new_price, alert_type, created_at
        ) VALUES (?, ?, ?, 'price_drop', NOW())";
        $alertStmt = $this->pdo->prepare($alertSql);
        $alertStmt->execute([
            $item['id'], 
            $item['current_market_price'], 
            $newPrice
        ]);
        
        // Send notification if enabled
        if (ENABLE_EMAIL && function_exists('sendEmail')) {
            $this->sendPriceAlert($item, $newPrice);
        }
        
        $this->logger->info("Price alert triggered for item {$item['id']}: {$newPrice}");
    }
    
    /**
     * Send price alert email
     */
    private function sendPriceAlert($item, $newPrice) {
        $subject = "Price Alert: {$item['title']}";
        $message = "The price for '{$item['title']}' has dropped to ${newPrice}!\n\n";
        $message .= "Your target price: ${item['max_price']}\n";
        $message .= "Current price: ${newPrice}\n";
        $message .= "Savings: $" . ($item['max_price'] - $newPrice) . "\n\n";
        $message .= "View item: {$item['external_url']}";
        
        sendEmail('user@example.com', $subject, $message);
    }
    
    /**
     * Detect media type from title/category
     */
    private function detectMediaType($title, $category) {
        $title = strtolower($title);
        $category = strtolower($category);
        
        // Movie keywords
        if (preg_match('/\b(dvd|blu-ray|4k|movie|film)\b/', $title . ' ' . $category)) {
            return 'movie';
        }
        
        // Book keywords
        if (preg_match('/\b(book|novel|paperback|hardcover|isbn)\b/', $title . ' ' . $category)) {
            return 'book';
        }
        
        // Comic keywords
        if (preg_match('/\b(comic|graphic novel|manga|tpb|trade paperback)\b/', $title . ' ' . $category)) {
            return 'comic';
        }
        
        // Music keywords
        if (preg_match('/\b(cd|vinyl|album|lp|ep|soundtrack)\b/', $title . ' ' . $category)) {
            return 'music';
        }
        
        // Game keywords
        if (preg_match('/\b(game|xbox|playstation|nintendo|pc|video game)\b/', $title . ' ' . $category)) {
            return 'game';
        }
        
        return 'other';
    }
    
    /**
     * Bulk import from CSV (for manual exports)
     */
    public function importFromCSV($csvFile, $source = 'manual') {
        if (!file_exists($csvFile)) {
            throw new Exception('CSV file not found');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }
        
        $headers = fgetcsv($handle);
        $imported = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            try {
                $item = array_combine($headers, $data);
                if ($this->importCSVItem($item, $source)) {
                    $imported++;
                }
            } catch (Exception $e) {
                $this->logger->error("CSV import error: " . $e->getMessage());
            }
        }
        
        fclose($handle);
        return ['success' => true, 'imported' => $imported];
    }
    
    /**
     * Import individual CSV item
     */
    private function importCSVItem($item, $source) {
        $sql = "INSERT INTO wishlist (
            title, media_type, max_price, notes, source, date_added
        ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $item['title'] ?? 'Unknown Title',
            $this->detectMediaType($item['title'] ?? '', $item['category'] ?? ''),
            $item['price'] ?? null,
            "Imported from {$source}",
            $source
        ]);
    }
}

/**
 * Simple Logger class
 */
class Logger {
    private $logFile;
    
    public function __construct($name) {
        $this->logFile = __DIR__ . "/../../logs/{$name}.log";
    }
    
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
    
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>