<?php
/**
 * Media API Manager - Unified API Integration System
 * Prepares the system for OMDB, GoCollect, and other API integrations
 * 
 * File: api/integrations/MediaAPIManager.php
 */

class MediaAPIManager {
    private $config;
    private $cache;
    private $db;
    
    public function __construct($pdo = null) {
        $this->loadConfig();
        $this->db = $pdo;
        $this->initializeCache();
    }
    
    /**
     * Load API configuration
     */
    private function loadConfig() {
        $this->config = [
            'omdb' => [
                'api_key' => defined('OMDB_API_KEY') ? OMDB_API_KEY : '',
                'base_url' => 'http://www.omdbapi.com/',
                'rate_limit' => 1000, // requests per day
                'cache_duration' => 86400 * 7 // 1 week
            ],
            'gocollect' => [
                'api_key' => defined('GOCOLLECT_API_KEY') ? GOCOLLECT_API_KEY : '',
                'base_url' => 'https://api.gocollect.com/',
                'rate_limit' => 500, // requests per day
                'cache_duration' => 86400 * 30 // 1 month
            ],
            'google_books' => [
                'api_key' => defined('GOOGLE_BOOKS_API_KEY') ? GOOGLE_BOOKS_API_KEY : '',
                'base_url' => 'https://www.googleapis.com/books/v1/',
                'rate_limit' => 1000, // requests per day
                'cache_duration' => 86400 * 7 // 1 week
            ]
        ];
    }
    
    /**
     * Initialize caching system
     */
    private function initializeCache() {
        $cacheDir = dirname(__FILE__) . '/../../cache/api/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->cache = $cacheDir;
    }
    
    /**
     * Search for movie/TV metadata via OMDB
     * 
     * @param string $title Movie/TV show title
     * @param string $year Optional year
     * @param string $type Optional type (movie, series, episode)
     * @return array|false
     */
    public function searchOMDB($title, $year = null, $type = null) {
        if (empty($this->config['omdb']['api_key'])) {
            return ['error' => 'OMDB API key not configured'];
        }
        
        $cacheKey = 'omdb_' . md5($title . $year . $type);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey, $this->config['omdb']['cache_duration'])) {
            return $cached;
        }
        
        $params = [
            'apikey' => $this->config['omdb']['api_key'],
            't' => $title
        ];
        
        if ($year) $params['y'] = $year;
        if ($type) $params['type'] = $type;
        
        $url = $this->config['omdb']['base_url'] . '?' . http_build_query($params);
        
        $response = $this->makeAPICall($url, 'omdb');
        
        if ($response && !isset($response['Error'])) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return false;
    }
    
    /**
     * Search for comic book metadata and values via GoCollect
     * 
     * @param string $series Comic series name
     * @param int $issue Issue number
     * @param string $publisher Publisher name
     * @return array|false
     */
    public function searchGoCollect($series, $issue = null, $publisher = null) {
        if (empty($this->config['gocollect']['api_key'])) {
            return ['error' => 'GoCollect API key not configured'];
        }
        
        $cacheKey = 'gocollect_' . md5($series . $issue . $publisher);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey, $this->config['gocollect']['cache_duration'])) {
            return $cached;
        }
        
        // GoCollect API structure (to be implemented when API access is available)
        $params = [
            'api_key' => $this->config['gocollect']['api_key'],
            'series' => $series
        ];
        
        if ($issue) $params['issue'] = $issue;
        if ($publisher) $params['publisher'] = $publisher;
        
        $url = $this->config['gocollect']['base_url'] . 'search?' . http_build_query($params);
        
        $response = $this->makeAPICall($url, 'gocollect');
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return false;
    }
    
    /**
     * Search for book metadata via Google Books API
     * 
     * @param string $query Search query (title, author, ISBN)
     * @param string $type Type of search (title, author, isbn)
     * @return array|false
     */
    public function searchGoogleBooks($query, $type = 'title') {
        $cacheKey = 'books_' . md5($query . $type);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey, $this->config['google_books']['cache_duration'])) {
            return $cached;
        }
        
        $searchQuery = $query;
        if ($type === 'isbn') {
            $searchQuery = 'isbn:' . $query;
        } elseif ($type === 'author') {
            $searchQuery = 'inauthor:' . $query;
        }
        
        $params = [
            'q' => $searchQuery,
            'maxResults' => 10
        ];
        
        if (!empty($this->config['google_books']['api_key'])) {
            $params['key'] = $this->config['google_books']['api_key'];
        }
        
        $url = $this->config['google_books']['base_url'] . 'volumes?' . http_build_query($params);
        
        $response = $this->makeAPICall($url, 'google_books');
        
        if ($response && isset($response['items'])) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return false;
    }
    
    /**
     * Download and cache poster/cover image
     * 
     * @param string $imageUrl URL of the image
     * @param string $mediaType Type of media (movie, book, comic)
     * @param string $identifier Unique identifier for the item
     * @return string|false Local path to cached image or false on failure
     */
    public function downloadPoster($imageUrl, $mediaType, $identifier) {
        if (empty($imageUrl) || $imageUrl === 'N/A') {
            return false;
        }
        
        // Create directory structure
        $posterDir = dirname(__FILE__) . '/../../uploads/posters/' . $mediaType . '/';
        if (!is_dir($posterDir)) {
            mkdir($posterDir, 0755, true);
        }
        
        // Generate filename
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg'; // Default extension
        }
        
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier) . '.' . $extension;
        $localPath = $posterDir . $filename;
        $relativePath = 'uploads/posters/' . $mediaType . '/' . $filename;
        
        // Check if file already exists
        if (file_exists($localPath)) {
            return $relativePath;
        }
        
        // Download image
        $imageData = $this->downloadFile($imageUrl);
        if ($imageData) {
            if (file_put_contents($localPath, $imageData)) {
                // Optionally resize image
                $this->resizeImage($localPath, 300, 450); // Poster dimensions
                return $relativePath;
            }
        }
        
        return false;
    }
    
    /**
     * Make API call with rate limiting and error handling
     * 
     * @param string $url API endpoint URL
     * @param string $service Service name for rate limiting
     * @return array|false
     */
    private function makeAPICall($url, $service) {
        // Check rate limits
        if (!$this->checkRateLimit($service)) {
            return ['error' => 'Rate limit exceeded for ' . $service];
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Media Collection System/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("API call failed: $url");
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for: $url");
            return false;
        }
        
        // Update rate limit counter
        $this->updateRateLimit($service);
        
        return $data;
    }
    
    /**
     * Download file with proper error handling
     * 
     * @param string $url File URL
     * @return string|false File content or false on failure
     */
    private function downloadFile($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Media Collection System/1.0',
                'timeout' => 60
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    /**
     * Resize image to specified dimensions
     * 
     * @param string $imagePath Path to image file
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return bool Success status
     */
    private function resizeImage($imagePath, $maxWidth, $maxHeight) {
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio >= 1) {
            return true; // Image is already smaller
        }
        
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Create image resources
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save resized image
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($destination, $imagePath, 85);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($destination, $imagePath, 6);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($destination, $imagePath);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($destination);
        
        return $success;
    }
    
    /**
     * Check if API rate limit allows another call
     * 
     * @param string $service Service name
     * @return bool Whether call is allowed
     */
    private function checkRateLimit($service) {
        $rateLimitFile = $this->cache . 'rate_limit_' . $service . '.json';
        
        if (!file_exists($rateLimitFile)) {
            return true;
        }
        
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if (!$data) {
            return true;
        }
        
        // Check if it's a new day
        if (date('Y-m-d') !== $data['date']) {
            return true;
        }
        
        $limit = $this->config[$service]['rate_limit'] ?? 1000;
        return $data['count'] < $limit;
    }
    
    /**
     * Update rate limit counter
     * 
     * @param string $service Service name
     */
    private function updateRateLimit($service) {
        $rateLimitFile = $this->cache . 'rate_limit_' . $service . '.json';
        
        $data = ['date' => date('Y-m-d'), 'count' => 1];
        
        if (file_exists($rateLimitFile)) {
            $existing = json_decode(file_get_contents($rateLimitFile), true);
            if ($existing && $existing['date'] === date('Y-m-d')) {
                $data['count'] = $existing['count'] + 1;
            }
        }
        
        file_put_contents($rateLimitFile, json_encode($data));
    }
    
    /**
     * Get data from cache
     * 
     * @param string $key Cache key
     * @param int $maxAge Maximum age in seconds
     * @return mixed|false Cached data or false if not found/expired
     */
    private function getFromCache($key, $maxAge) {
        $cacheFile = $this->cache . $key . '.json';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        if (time() - filemtime($cacheFile) > $maxAge) {
            unlink($cacheFile);
            return false;
        }
        
        $data = file_get_contents($cacheFile);
        return $data ? json_decode($data, true) : false;
    }
    
    /**
     * Save data to cache
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    private function saveToCache($key, $data) {
        $cacheFile = $this->cache . $key . '.json';
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Get API status and usage statistics
     * 
     * @return array Status information for all APIs
     */
    public function getAPIStatus() {
        $status = [];
        
        foreach ($this->config as $service => $config) {
            $rateLimitFile = $this->cache . 'rate_limit_' . $service . '.json';
            $usage = 0;
            
            if (file_exists($rateLimitFile)) {
                $data = json_decode(file_get_contents($rateLimitFile), true);
                if ($data && $data['date'] === date('Y-m-d')) {
                    $usage = $data['count'];
                }
            }
            
            $status[$service] = [
                'configured' => !empty($config['api_key']),
                'daily_limit' => $config['rate_limit'],
                'usage_today' => $usage,
                'remaining' => $config['rate_limit'] - $usage,
                'percentage_used' => round(($usage / $config['rate_limit']) * 100, 1)
            ];
        }
        
        return $status;
    }
    
    /**
     * Clear all cache files
     * 
     * @param string $service Optional service name to clear specific cache
     */
    public function clearCache($service = null) {
        $pattern = $service ? $service . '_*' : '*';
        $files = glob($this->cache . $pattern . '.json');
        
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// =============================================================================
// USAGE EXAMPLES (for future implementation)
// =============================================================================

/*
// Example 1: Search for movie poster via OMDB
$apiManager = new MediaAPIManager($pdo);

$movieData = $apiManager->searchOMDB('The Matrix', '1999');
if ($movieData && $movieData['Poster'] !== 'N/A') {
    $posterPath = $apiManager->downloadPoster(
        $movieData['Poster'], 
        'movie', 
        'the_matrix_1999'
    );
    
    // Save to collection
    $stmt = $pdo->prepare("
        UPDATE collection 
        SET poster_url = ?, external_id = ?, external_source = 'omdb'
        WHERE id = ?
    ");
    $stmt->execute([$posterPath, $movieData['imdbID'], $collectionId]);
}

// Example 2: Search for book cover via Google Books
$bookData = $apiManager->searchGoogleBooks('978-0123456789', 'isbn');
if ($bookData && isset($bookData['items'][0]['volumeInfo']['imageLinks']['thumbnail'])) {
    $coverUrl = $bookData['items'][0]['volumeInfo']['imageLinks']['thumbnail'];
    $coverPath = $apiManager->downloadPoster($coverUrl, 'book', 'isbn_978-0123456789');
}

// Example 3: Get comic book value via GoCollect (when API is available)
$comicData = $apiManager->searchGoCollect('Amazing Spider-Man', 1, 'Marvel');
if ($comicData && isset($comicData['current_value'])) {
    $currentValue = $comicData['current_value'];
    // Update collection with current market value
}

// Example 4: Check API status
$status = $apiManager->getAPIStatus();
foreach ($status as $service => $info) {
    echo "{$service}: {$info['usage_today']}/{$info['daily_limit']} calls used today\n";
}
*/

?>

<?php
/**
 * Simple API Configuration Template
 * File: config/api_keys.php
 */

// OMDB API Configuration
// Get your free API key from: http://www.omdbapi.com/apikey.aspx
define('OMDB_API_KEY', ''); // Your OMDB API key here

// GoCollect API Configuration  
// Contact GoCollect for API access: https://www.gocollect.com/
define('GOCOLLECT_API_KEY', ''); // Your GoCollect API key here

// Google Books API Configuration
// Get your API key from: https://developers.google.com/books/docs/v1/using#APIKey
define('GOOGLE_BOOKS_API_KEY', ''); // Your Google Books API key here

// API Settings
define('ENABLE_API_CACHING', true);
define('API_CACHE_DURATION', 86400 * 7); // 1 week default
define('API_RATE_LIMIT_STRICT', true);
define('POSTER_QUALITY', 85); // JPEG quality for downloaded posters

// Poster/Image Settings
define('MAX_POSTER_WIDTH', 300);
define('MAX_POSTER_HEIGHT', 450);
define('POSTER_FORMATS', 'jpg,jpeg,png,gif,webp');
define('MAX_POSTER_SIZE', 5 * 1024 * 1024); // 5MB max

?>

<?php
/**
 * Future API Endpoint Structure
 * File: api/handlers/metadata_lookup.php
 */

header('Content-Type: application/json');
session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

require_once '../integrations/MediaAPIManager.php';
require_once '../../config.php';

$apiManager = new MediaAPIManager($pdo);
$action = $_GET['action'] ?? '';
$mediaType = $_GET['type'] ?? '';

switch ($action) {
    case 'search':
        $query = $_GET['q'] ?? '';
        $year = $_GET['year'] ?? null;
        
        switch ($mediaType) {
            case 'movie':
                $result = $apiManager->searchOMDB($query, $year, 'movie');
                break;
            case 'book':
                $result = $apiManager->searchGoogleBooks($query);
                break;
            case 'comic':
                $result = $apiManager->searchGoCollect($query);
                break;
            default:
                $result = ['error' => 'Invalid media type'];
        }
        
        echo json_encode($result);
        break;
        
    case 'poster':
        $url = $_GET['url'] ?? '';
        $identifier = $_GET['id'] ?? '';
        
        if ($url && $identifier) {
            $posterPath = $apiManager->downloadPoster($url, $mediaType, $identifier);
            echo json_encode(['poster_path' => $posterPath]);
        } else {
            echo json_encode(['error' => 'Missing URL or identifier']);
        }
        break;
        
    case 'status':
        $status = $apiManager->getAPIStatus();
        echo json_encode($status);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
        