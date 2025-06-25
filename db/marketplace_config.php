<?php
/**
 * Marketplace API Configuration
 * Add these to your config.php file
 */

// =============================================================================
// EBAY API CONFIGURATION
// =============================================================================

// eBay Developer Account Required: https://developer.ebay.com/
define('EBAY_APP_ID', '');           // Your App ID (Client ID)
define('EBAY_CERT_ID', '');          // Your Cert ID (Client Secret)  
define('EBAY_DEV_ID', '');           // Your Dev ID
define('EBAY_USER_TOKEN', '');       // User Token (for accessing user data)
define('EBAY_SANDBOX', true);        // Set to false for production
define('EBAY_SITE_ID', 0);           // 0 = US, 3 = UK, 77 = Germany, etc.

// =============================================================================
// AMAZON API CONFIGURATION
// =============================================================================

// Amazon Product Advertising API: https://webservices.amazon.com/paapi5/
define('AMAZON_ACCESS_KEY', '');     // Your Access Key
define('AMAZON_SECRET_KEY', '');     // Your Secret Key
define('AMAZON_ASSOCIATE_TAG', '');  // Your Associate Tag
define('AMAZON_REGION', 'US');       // US, UK, DE, JP, etc.

// =============================================================================
// MERCARI API CONFIGURATION (Optional)
// =============================================================================

define('MERCARI_API_KEY', '');
define('MERCARI_ENABLED', false);

// =============================================================================
// MARKETPLACE SYNC SETTINGS
// =============================================================================

define('MARKETPLACE_SYNC_ENABLED', true);
define('MARKETPLACE_SYNC_INTERVAL', 3600);    // How often to check prices (seconds)
define('MARKETPLACE_MAX_REQUESTS_PER_HOUR', 100);
define('MARKETPLACE_ENABLE_PRICE_ALERTS', true);
define('MARKETPLACE_ENABLE_AUTO_IMPORT', true);

// =============================================================================
// RATE LIMITING CONFIGURATION
// =============================================================================

define('EBAY_RATE_LIMIT', 5000);    // Requests per day
define('AMAZON_RATE_LIMIT', 8640);  // Requests per day
define('PRICE_CHECK_BATCH_SIZE', 20); // Items to check per batch
define('PRICE_CHECK_DELAY', 2);      // Seconds between requests

// =============================================================================
// NOTIFICATION SETTINGS
// =============================================================================

define('SEND_PRICE_ALERTS', true);
define('SEND_AUCTION_ENDING_ALERTS', true);
define('SEND_NEW_LISTING_ALERTS', false);
define('ALERT_EMAIL', 'your-email@example.com');
?>