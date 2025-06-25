<?php
/**
 * Complete Media API Manager
 * File: api/integrations/MediaAPIManager.php
 * Handles all external API integrations and poster downloads
 */

class MediaAPIManager {
    private $config;
    private $cacheDir;
    private $posterDir;
    private $db;
    private $errors = [];
    private $debug = true;
    
    public function __construct($pdo = null) {
        $this->db = $pdo;
        $this->loadConfig();
        $this->initializeDirectories();
    }
    
    /**
     * Load API configuration from config file
     */
    private function loadConfig() {
        $configFile = dirname(dirname(__DIR__)) . '/config/api_keys.php';
        
        // Load API keys if config file exists
        if (file_exists($configFile)) {
            require_once $configFile;
        }
        
        $this->config = [
            'omdb' => [
                'api_key' => defined('OMDB_API_KEY') ? OMDB_API_KEY : '',
                'base_url' => 'http://www.omdbapi.com/',
                'rate_limit' => 1000,
                'cache_duration' => defined('API_CACHE_DURATION') ? API_CACHE_DURATION : 604800
            ],
            'tmdb' => [
                'api_key' => defined('TMDB_API_KEY') ? TMDB_API_KEY : '',
                'base_url' => 'https://api.themoviedb.org/3/',
                'image_base' => 'https://image.tmdb.org/t/p/',
                'rate_limit' => 1000,
                'cache_duration' => defined('API_CACHE_DURATION') ? API_CACHE_DURATION : 604800
            ],
            'google_books' => [
                'api_key' => defined('GOOGLE_BOOKS_API_KEY') ? GOOGLE_BOOKS_API_KEY : '',
                'base_url' => 'https://www.googleapis.com/books/v1/',
                'rate_limit' => 1000,
                'cache_duration' => defined('API_CACHE_DURATION') ? API_CACHE_DURATION : 604800
            ],
            'poster' => [
                'max_width' => defined('MAX_POSTER_WIDTH') ? MAX_POSTER_WIDTH : 500,
                'max_height' => defined('MAX_POSTER_HEIGHT') ? MAX_POSTER_HEIGHT : 750,
                'quality' => defined('POSTER_QUALITY') ? POSTER_QUALITY : 85
            ]
        ];
    }
    
    /**
     * Initialize directories
     */
    private function initializeDirectories() {
        $rootDir = dirname(dirname(__DIR__));
        $this->cacheDir = $rootDir . '/cache/api/';
        $this->posterDir = $rootDir . '/uploads/posters/';
        
        // Create directories if they don't exist
        $dirs = [
            $this->cacheDir,
            $this->posterDir,
            $rootDir . '/uploads/',
            $rootDir . '/cache/'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Main lookup function - searches all APIs for metadata and posters
     */
    public function lookupMedia($query, $mediaType = null, $year = null) {
        $results = [
            'query' => $query,
            'media_type' => $mediaType,
            'year' => $year,
            'sources' => [],
            'best_match' => null,
            'posters' => [],
            'success' => false
        ];
        
        // Determine search strategy based on media type
        switch (strtolower($mediaType)) {
            case 'movie':
            case 'tv':
            case 'series':
                $results['sources']['omdb'] = $this->searchOMDB($query, $year);
                $results['sources']['tmdb'] = $this->searchTMDB($query, $year, $mediaType);
                break;
                
            case 'book':
                $results['sources']['google_books'] = $this->searchGoogleBooks($query);
                break;
                
            case 'music':
            case 'album':
                // Future: Add music APIs
                break;
                
            case 'game':
                // Future: Add IGDB integration
                break;
                
            default:
                // Search all available APIs
                $results['sources']['omdb'] = $this->searchOMDB($query, $year);
                $results['sources']['tmdb'] = $this->searchTMDB($query, $year);
                $results['sources']['google_books'] = $this->searchGoogleBooks($query);
                break;
        }
        
        // Find best match and collect posters
        $results['best_match'] = $this->selectBestMatch($results['sources']);
        $results['posters'] = $this->collectPosters($results['sources']);
        $results['success'] = !empty($results['best_match']);
        
        return $results;
    }
    
    /**
     * Search OMDB API
     */
    public function searchOMDB($title, $year = null, $imdbId = null) {
        if (empty($this->config['omdb']['api_key'])) {
            return ['error' => 'OMDB API key not configured'];
        }
        
        $cacheKey = 'omdb_' . md5($title . $year . $imdbId);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $params = [
            'apikey' => $this->config['omdb']['api_key'],
            'plot' => 'full'
        ];
        
        if ($imdbId) {
            $params['i'] = $imdbId;
        } else {
            $params['t'] = $title;
            if ($year) $params['y'] = $year;
        }
        
        $url = $this->config['omdb']['base_url'] . '?' . http_build_query($params);
        $data = $this->makeApiCall($url);
        
        if ($data && isset($data['Response']) && $data['Response'] === 'True') {
            // Normalize the data
            $normalized = [
                'source' => 'omdb',
                'title' => $data['Title'] ?? '',
                'year' => $data['Year'] ?? '',
                'plot' => $data['Plot'] ?? '',
                'genre' => $data['Genre'] ?? '',
                'director' => $data['Director'] ?? '',
                'actors' => $data['Actors'] ?? '',
                'poster' => $data['Poster'] ?? '',
                'imdb_id' => $data['imdbID'] ?? '',
                'imdb_rating' => $data['imdbRating'] ?? '',
                'runtime' => $data['Runtime'] ?? '',
                'raw_data' => $data
            ];
            
            $this->saveToCache($cacheKey, $normalized);
            return $normalized;
        }
        
        return ['error' => 'OMDB search failed: ' . ($data['Error'] ?? 'Unknown error')];
    }
    
    /**
     * Search TMDB API
     */
    public function searchTMDB($title, $year = null, $mediaType = 'movie') {
        if (empty($this->config['tmdb']['api_key'])) {
            return ['error' => 'TMDB API key not configured'];
        }
        
        $cacheKey = 'tmdb_' . md5($title . $year . $mediaType);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $params = [
            'api_key' => $this->config['tmdb']['api_key'],
            'query' => $title,
            'language' => 'en-US'
        ];
        
        if ($year) $params['year'] = $year;
        
        $endpoint = $mediaType === 'tv' ? 'search/tv' : 'search/movie';
        $url = $this->config['tmdb']['base_url'] . $endpoint . '?' . http_build_query($params);
        
        $data = $this->makeApiCall($url);
        
        if ($data && !empty($data['results'])) {
            $result = $data['results'][0];
            
            // Get additional images for this item
            $tmdbId = $result['id'];
            $images = $this->getTMDBImages($tmdbId, $mediaType);
            
            $normalized = [
                'source' => 'tmdb',
                'title' => $result['title'] ?? $result['name'] ?? '',
                'year' => substr($result['release_date'] ?? $result['first_air_date'] ?? '', 0, 4),
                'plot' => $result['overview'] ?? '',
                'poster' => $result['poster_path'] ? $this->config['tmdb']['image_base'] . 'w500' . $result['poster_path'] : '',
                'backdrop' => $result['backdrop_path'] ? $this->config['tmdb']['image_base'] . 'w1280' . $result['backdrop_path'] : '',
                'tmdb_id' => $tmdbId,
                'rating' => $result['vote_average'] ?? '',
                'genre_ids' => $result['genre_ids'] ?? [],
                'images' => $images,
                'raw_data' => $result
            ];
            
            $this->saveToCache($cacheKey, $normalized);
            return $normalized;
        }
        
        return ['error' => 'TMDB search failed'];
    }
    
    /**
     * Get TMDB images
     */
    private function getTMDBImages($tmdbId, $mediaType = 'movie') {
        $endpoint = $mediaType === 'tv' ? 'tv' : 'movie';
        $url = $this->config['tmdb']['base_url'] . $endpoint . '/' . $tmdbId . '/images?api_key=' . $this->config['tmdb']['api_key'];
        
        $data = $this->makeApiCall($url);
        
        if ($data && !empty($data['posters'])) {
            $images = [];
            foreach (array_slice($data['posters'], 0, 5) as $poster) { // Limit to 5 posters
                $images[] = [
                    'url' => $this->config['tmdb']['image_base'] . 'w500' . $poster['file_path'],
                    'url_large' => $this->config['tmdb']['image_base'] . 'original' . $poster['file_path'],
                    'aspect_ratio' => $poster['aspect_ratio'],
                    'vote_average' => $poster['vote_average']
                ];
            }
            return $images;
        }
        
        return [];
    }
    
    /**
     * Search Google Books API
     */
    public function searchGoogleBooks($query, $searchType = 'title') {
        if (empty($this->config['google_books']['api_key'])) {
            return ['error' => 'Google Books API key not configured'];
        }
        
        $cacheKey = 'gbooks_' . md5($query . $searchType);
        
        // Check cache first
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $params = [
            'key' => $this->config['google_books']['api_key'],
            'q' => $searchType === 'isbn' ? 'isbn:' . $query : $query,
            'maxResults' => 5
        ];
        
        $url = $this->config['google_books']['base_url'] . 'volumes?' . http_build_query($params);
        $data = $this->makeApiCall($url);
        
        if ($data && !empty($data['items'])) {
            $result = $data['items'][0];
            $volumeInfo = $result['volumeInfo'] ?? [];
            
            $normalized = [
                'source' => 'google_books',
                'title' => $volumeInfo['title'] ?? '',
                'subtitle' => $volumeInfo['subtitle'] ?? '',
                'authors' => $volumeInfo['authors'] ?? [],
                'publisher' => $volumeInfo['publisher'] ?? '',
                'published_date' => $volumeInfo['publishedDate'] ?? '',
                'description' => $volumeInfo['description'] ?? '',
                'isbn' => $this->extractISBN($volumeInfo['industryIdentifiers'] ?? []),
                'page_count' => $volumeInfo['pageCount'] ?? '',
                'categories' => $volumeInfo['categories'] ?? [],
                'language' => $volumeInfo['language'] ?? '',
                'poster' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
                'poster_large' => $volumeInfo['imageLinks']['medium'] ?? $volumeInfo['imageLinks']['large'] ?? '',
                'google_books_id' => $result['id'] ?? '',
                'raw_data' => $result
            ];
            
            $this->saveToCache($cacheKey, $normalized);
            return $normalized;
        }
        
        return ['error' => 'Google Books search failed'];
    }
    
    /**
     * Download and save poster
     */
    public function downloadPoster($imageUrl, $mediaType, $identifier, $title = '') {
        if (empty($imageUrl) || $imageUrl === 'N/A') {
            return false;
        }
        
        // Generate filename
        $extension = $this->getImageExtension($imageUrl);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
        $filename = $mediaType . '_' . $safeId . '_' . time() . '.' . $extension;
        $filepath = $this->posterDir . $filename;
        
        // Check if we already have this poster
        $existingFile = $this->findExistingPoster($mediaType, $identifier);
        if ($existingFile) {
            return 'uploads/posters/' . basename($existingFile);
        }
        
        // Download and process image
        $imageData = $this->downloadImage($imageUrl);
        if (!$imageData) {
            return false;
        }
        
        $processedImage = $this->processImage($imageData);
        if (file_put_contents($filepath, $processedImage)) {
            return 'uploads/posters/' . $filename;
        }
        
        return false;
    }
    
    /**
     * Download image from URL
     */
    private function downloadImage($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'MediaCollection/1.0 (Poster Download)'
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    /**
     * Process and resize image
     */
    private function processImage($imageData) {
        if (!extension_loaded('gd')) {
            return $imageData; // Return original if GD not available
        }
        
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return $imageData;
        }
        
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        
        // Calculate new dimensions
        $maxWidth = $this->config['poster']['max_width'];
        $maxHeight = $this->config['poster']['max_height'];
        
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        if ($ratio < 1) {
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            ob_start();
            imagejpeg($resized, null, $this->config['poster']['quality']);
            $processedData = ob_get_contents();
            ob_end_clean();
            
            imagedestroy($image);
            imagedestroy($resized);
            
            return $processedData;
        }
        
        imagedestroy($image);
        return $imageData;
    }
    
    /**
     * Helper functions
     */
    private function makeApiCall($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'MediaCollection/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function getFromCache($key) {
        if (!defined('ENABLE_API_CACHING') || !ENABLE_API_CACHING) {
            return false;
        }
        
        $cacheFile = $this->cacheDir . md5($key) . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = unserialize(file_get_contents($cacheFile));
            
            if ($data['expires'] > time()) {
                return $data['content'];
            } else {
                unlink($cacheFile);
            }
        }
        
        return false;
    }
    
    private function saveToCache($key, $data) {
        if (!defined('ENABLE_API_CACHING') || !ENABLE_API_CACHING) {
            return;
        }
        
        $cacheFile = $this->cacheDir . md5($key) . '.cache';
        $cacheData = [
            'content' => $data,
            'expires' => time() + $this->config['omdb']['cache_duration']
        ];
        
        file_put_contents($cacheFile, serialize($cacheData));
    }
    
    private function selectBestMatch($sources) {
        // Priority: TMDB > OMDB > Google Books
        if (isset($sources['tmdb']) && !isset($sources['tmdb']['error'])) {
            return $sources['tmdb'];
        }
        
        if (isset($sources['omdb']) && !isset($sources['omdb']['error'])) {
            return $sources['omdb'];
        }
        
        if (isset($sources['google_books']) && !isset($sources['google_books']['error'])) {
            return $sources['google_books'];
        }
        
        return null;
    }
    
    private function collectPosters($sources) {
        $posters = [];
        
        foreach ($sources as $source => $data) {
            if (isset($data['poster']) && !empty($data['poster'])) {
                $posters[] = [
                    'source' => $source,
                    'url' => $data['poster'],
                    'large_url' => $data['poster_large'] ?? $data['poster'] ?? ''
                ];
            }
            
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $posters[] = [
                        'source' => $source,
                        'url' => $image['url'],
                        'large_url' => $image['url_large'] ?? $image['url']
                    ];
                }
            }
        }
        
        return $posters;
    }
    
    private function extractISBN($identifiers) {
        foreach ($identifiers as $identifier) {
            if ($identifier['type'] === 'ISBN_13') {
                return $identifier['identifier'];
            }
        }
        foreach ($identifiers as $identifier) {
            if ($identifier['type'] === 'ISBN_10') {
                return $identifier['identifier'];
            }
        }
        return '';
    }
    
    private function getImageExtension($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? strtolower($ext) : 'jpg';
    }
    
    private function findExistingPoster($mediaType, $identifier) {
        $pattern = $this->posterDir . $mediaType . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier) . '_*';
        $files = glob($pattern);
        return !empty($files) ? $files[0] : false;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getLastError() {
        return end($this->errors);
    }
}
?>