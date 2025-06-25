<?php
/**
 * Complete Media API Manager
 * File: api/integrations/MediaAPIManager.php
 * Handles OMDB, TMDB, Google Books, and poster downloads
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
     * Load API configuration from constants
     */
    private function loadConfig() {
        $this->config = [
            'omdb' => [
                'api_key' => defined('OMDB_API_KEY') ? OMDB_API_KEY : '',
                'base_url' => 'http://www.omdbapi.com/',
                'rate_limit' => 1000,
                'cache_duration' => 86400 * 7
            ],
            'tmdb' => [
                'api_key' => defined('TMDB_API_KEY') ? TMDB_API_KEY : '',
                'base_url' => 'https://api.themoviedb.org/3/',
                'image_base' => 'https://image.tmdb.org/t/p/',
                'rate_limit' => 1000,
                'cache_duration' => 86400 * 7
            ],
            'google_books' => [
                'api_key' => defined('GOOGLE_BOOKS_API_KEY') ? GOOGLE_BOOKS_API_KEY : '',
                'base_url' => 'https://www.googleapis.com/books/v1/',
                'rate_limit' => 1000,
                'cache_duration' => 86400 * 7
            ]
        ];
    }
    
    /**
     * Initialize cache and poster directories
     */
    private function initializeDirectories() {
        $rootDir = dirname(dirname(__DIR__));
        $this->cacheDir = $rootDir . '/cache/api/';
        $this->posterDir = $rootDir . '/uploads/posters/';
        
        // Create directories if they don't exist
        $dirs = [$this->cacheDir, $this->posterDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Search for movie/TV show via OMDB
     */
    public function searchOMDB($title, $year = null, $imdbId = null) {
        if (empty($this->config['omdb']['api_key'])) {
            $this->errors[] = 'OMDB API key not configured';
            return false;
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
        
        if ($data && $data['Response'] === 'True') {
            $this->saveToCache($cacheKey, $data);
            return $data;
        }
        
        $this->errors[] = 'OMDB search failed: ' . ($data['Error'] ?? 'Unknown error');
        return false;
    }
    
    /**
     * Search for movie/TV show via TMDB (more poster options)
     */
    public function searchTMDB($title, $year = null, $mediaType = 'movie') {
        if (empty($this->config['tmdb']['api_key'])) {
            $this->errors[] = 'TMDB API key not configured';
            return false;
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
            $result = $data['results'][0]; // Get first result
            $this->saveToCache($cacheKey, $result);
            return $result;
        }
        
        $this->errors[] = 'TMDB search failed for: ' . $title;
        return false;
    }
    
    /**
     * Search for books via Google Books API
     */
    public function searchGoogleBooks($query, $searchType = 'title') {
        if (empty($this->config['google_books']['api_key'])) {
            $this->errors[] = 'Google Books API key not configured';
            return false;
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
            $result = $data['items'][0]; // Get first result
            $this->saveToCache($cacheKey, $result);
            return $result;
        }
        
        $this->errors[] = 'Google Books search failed for: ' . $query;
        return false;
    }
    
    /**
     * Download and save poster/cover image
     */
    public function downloadPoster($imageUrl, $mediaType, $identifier, $title = '') {
        if (empty($imageUrl)) {
            return false;
        }
        
        // Create filename
        $extension = $this->getImageExtension($imageUrl);
        $filename = $mediaType . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier) . '.' . $extension;
        $filepath = $this->posterDir . $filename;
        
        // Check if already exists
        if (file_exists($filepath)) {
            return 'uploads/posters/' . $filename;
        }
        
        // Download image
        $imageData = $this->downloadImage($imageUrl);
        if (!$imageData) {
            $this->errors[] = 'Failed to download image from: ' . $imageUrl;
            return false;
        }
        
        // Process and save image
        $processedImage = $this->processImage($imageData, $mediaType);
        if (file_put_contents($filepath, $processedImage)) {
            return 'uploads/posters/' . $filename;
        }
        
        $this->errors[] = 'Failed to save image: ' . $filename;
        return false;
    }
    
    /**
     * Get poster URLs from TMDB
     */
    public function getTMDBPosters($tmdbId, $mediaType = 'movie') {
        if (empty($this->config['tmdb']['api_key'])) {
            return [];
        }
        
        $endpoint = $mediaType === 'tv' ? 'tv' : 'movie';
        $url = $this->config['tmdb']['base_url'] . $endpoint . '/' . $tmdbId . '/images?api_key=' . $this->config['tmdb']['api_key'];
        
        $data = $this->makeApiCall($url);
        
        if ($data && !empty($data['posters'])) {
            $posters = [];
            foreach ($data['posters'] as $poster) {
                $posters[] = [
                    'url' => $this->config['tmdb']['image_base'] . 'w500' . $poster['file_path'],
                    'url_large' => $this->config['tmdb']['image_base'] . 'original' . $poster['file_path'],
                    'aspect_ratio' => $poster['aspect_ratio'],
                    'vote_average' => $poster['vote_average']
                ];
            }
            return $posters;
        }
        
        return [];
    }
    
    /**
     * Comprehensive metadata lookup by identifier
     */
    public function lookupMetadata($identifier, $mediaType = null) {
        $results = [
            'identifier' => $identifier,
            'type' => $this->detectIdentifierType($identifier),
            'metadata' => [],
            'posters' => [],
            'success' => false
        ];
        
        // Determine search strategy based on identifier type
        switch ($results['type']) {
            case 'imdb':
                $data = $this->searchOMDB('', null, $identifier);
                if ($data) {
                    $results['metadata'] = $this->normalizeOMDBData($data);
                    $results['posters'][] = $data['Poster'] ?? '';
                    $results['success'] = true;
                }
                break;
                
            case '