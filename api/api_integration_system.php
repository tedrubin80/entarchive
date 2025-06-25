<?php
// api/integrations/MediaAPIManager.php
class MediaAPIManager {
    private $config;
    private $cache;
    
    public function __construct() {
        $this->config = [
            'omdb_key' => OMDB_API_KEY,
            'google_books_key' => GOOGLE_BOOKS_KEY,
            'discogs_token' => DISCOGS_TOKEN,
            'comicvine_key' => COMICVINE_KEY
        ];
        $this->cache = new SimpleCache();
    }
    
    /**
     * Main barcode/identifier lookup function
     */
    public function lookupByIdentifier($identifier, $mediaType = null) {
        $results = [];
        
        // Determine what type of identifier this is
        $identifierType = $this->detectIdentifierType($identifier);
        
        switch ($identifierType) {
            case 'isbn':
                $results[] = $this->searchGoogleBooks($identifier);
                break;
            case 'upc':
                if (!$mediaType || $mediaType === 'movie') {
                    $results[] = $this->searchOMDB('', '', $identifier);
                }
                if (!$mediaType || $mediaType === 'music') {
                    $results[] = $this->searchDiscogs($identifier, 'barcode');
                }
                break;
            case 'imdb':
                $results[] = $this->searchOMDB('', '', '', $identifier);
                break;
            default:
                // Try all APIs if identifier type is unclear
                if (!$mediaType || $mediaType === 'movie') {
                    $results[] = $this->searchOMDB($identifier);
                }
                if (!$mediaType || $mediaType === 'book') {
                    $results[] = $this->searchGoogleBooks($identifier);
                }
                if (!$mediaType || $mediaType === 'music') {
                    $results[] = $this->searchDiscogs($identifier);
                }
                if (!$mediaType || $mediaType === 'comic') {
                    $results[] = $this->searchComicVine($identifier);
                }
        }
        
        // Filter out null results and return best matches
        $validResults = array_filter($results, function($result) {
            return $result !== null && !empty($result);
        });
        
        return [
            'identifier' => $identifier,
            'identifier_type' => $identifierType,
            'results' => $validResults,
            'best_match' => $this->selectBestMatch($validResults)
        ];
    }
    
    /**
     * Search movies/TV via OMDB API
     */
    public function searchOMDB($title = '', $year = '', $upc = '', $imdbId = '') {
        if (empty($this->config['omdb_key']) || $this->config['omdb_key'] === 'YOUR_OMDB_KEY') {
            return null;
        }
        
        $cacheKey = "omdb_" . md5($title . $year . $upc . $imdbId);
        $cached = $this->cache->get($cacheKey);
        if ($cached) return $cached;
        
        $baseUrl = 'http://www.omdbapi.com/';
        $params = ['apikey' => $this->config['omdb_key']];
        
        if ($imdbId) {
            $params['i'] = $imdbId;
        } elseif ($upc) {
            // OMDB doesn't directly support UPC, try title search
            $params['s'] = $title ?: 'movie';
        } else {
            $params['t'] = $title;
            if ($year) $params['y'] = $year;
        }
        
        $url = $baseUrl . '?' . http_build_query($params);
        $response = $this->makeRequest($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!$data || $data['Response'] === 'False') return null;
        
        // If search results, get details for first result
        if (isset($data['Search'])) {
            $firstResult = $data['Search'][0];
            return $this->searchOMDB('', '', '', $firstResult['imdbID']);
        }
        
        $result = $this->formatOMDBResult($data);
        $this->cache->set($cacheKey, $result, 86400); // Cache for 24 hours
        
        return $result;
    }
    
    /**
     * Search books via Google Books API
     */
    public function searchGoogleBooks($query, $searchType = 'isbn') {
        if (empty($this->config['google_books_key']) || $this->config['google_books_key'] === 'YOUR_GOOGLE_BOOKS_KEY') {
            return null;
        }
        
        $cacheKey = "books_" . md5($query . $searchType);
        $cached = $this->cache->get($cacheKey);
        if ($cached) return $cached;
        
        $baseUrl = 'https://www.googleapis.com/books/v1/volumes';
        
        // Format query based on search type
        if ($searchType === 'isbn') {
            $searchQuery = 'isbn:' . $query;
        } else {
            $searchQuery = $query;
        }
        
        $params = [
            'q' => $searchQuery,
            'key' => $this->config['google_books_key'],
            'maxResults' => 1
        ];
        
        $url = $baseUrl . '?' . http_build_query($params);
        $response = $this->makeRequest($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!$data || empty($data['items'])) return null;
        
        $result = $this->formatGoogleBooksResult($data['items'][0]);
        $this->cache->set($cacheKey, $result, 86400);
        
        return $result;
    }
    
    /**
     * Search music via Discogs API
     */
    public function searchDiscogs($query, $searchType = 'title') {
        if (empty($this->config['discogs_token']) || $this->config['discogs_token'] === 'YOUR_DISCOGS_TOKEN') {
            return null;
        }
        
        $cacheKey = "discogs_" . md5($query . $searchType);
        $cached = $this->cache->get($cacheKey);
        if ($cached) return $cached;
        
        $baseUrl = 'https://api.discogs.com/database/search';
        
        $params = [
            'token' => $this->config['discogs_token'],
            'per_page' => 1
        ];
        
        if ($searchType === 'barcode') {
            $params['barcode'] = $query;
        } else {
            $params['q'] = $query;
            $params['type'] = 'release';
        }
        
        $url = $baseUrl . '?' . http_build_query($params);
        $response = $this->makeRequest($url, [
            'User-Agent: MediaCollectionApp/1.0 +http://localhost'
        ]);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!$data || empty($data['results'])) return null;
        
        $result = $this->formatDiscogsResult($data['results'][0]);
        $this->cache->set($cacheKey, $result, 86400);
        
        return $result;
    }
    
    /**
     * Search comics via ComicVine API
     */
    public function searchComicVine($query) {
        if (empty($this->config['comicvine_key']) || $this->config['comicvine_key'] === 'YOUR_COMICVINE_KEY') {
            return null;
        }
        
        $cacheKey = "comicvine_" . md5($query);
        $cached = $this->cache->get($cacheKey);
        if ($cached) return $cached;
        
        $baseUrl = 'https://comicvine.gamespot.com/api/search/';
        
        $params = [
            'api_key' => $this->config['comicvine_key'],
            'format' => 'json',
            'query' => $query,
            'resources' => 'issue',
            'limit' => 1
        ];
        
        $url = $baseUrl . '?' . http_build_query($params);
        $response = $this->makeRequest($url, [
            'User-Agent: MediaCollectionApp/1.0'
        ]);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!$data || $data['status_code'] !== 1 || empty($data['results'])) return null;
        
        $result = $this->formatComicVineResult($data['results'][0]);
        $this->cache->set($cacheKey, $result, 86400);
        
        return $result;
    }
    
    /**
     * Auto-detect identifier type
     */
    private function detectIdentifierType($identifier) {
        // Remove any spaces or dashes
        $clean = preg_replace('/[\s\-]/', '', $identifier);
        
        // ISBN (10 or 13 digits)
        if (preg_match('/^\d{10}(\d{3})?$/', $clean)) {
            return 'isbn';
        }
        
        // IMDB ID
        if (preg_match('/^tt\d+$/', $identifier)) {
            return 'imdb';
        }
        
        // UPC (12 digits)
        if (preg_match('/^\d{12}$/', $clean)) {
            return 'upc';
        }
        
        // EAN (13 digits, but not ISBN)
        if (preg_match('/^\d{13}$/', $clean) && !preg_match('/^97[89]/', $clean)) {
            return 'ean';
        }
        
        return 'unknown';
    }
    
    /**
     * Format OMDB API response
     */
    private function formatOMDBResult($data) {
        return [
            'media_type' => 'movie',
            'source' => 'omdb',
            'title' => $data['Title'] ?? '',
            'year' => $data['Year'] ?? '',
            'creator' => $data['Director'] ?? '',
            'description' => $data['Plot'] ?? '',
            'poster_url' => $data['Poster'] !== 'N/A' ? $data['Poster'] : null,
            'external_id' => $data['imdbID'] ?? '',
            'media_details' => [
                'director' => $data['Director'] ?? '',
                'runtime_minutes' => $this->parseRuntime($data['Runtime'] ?? ''),
                'mpaa_rating' => $data['Rated'] ?? '',
                'studio' => $data['Production'] ?? '',
                'original_language' => $data['Language'] ?? '',
                'media_type_detail' => strtolower($data['Type'] ?? 'movie'),
            ],
            'categories' => $this->parseGenres($data['Genre'] ?? ''),
            'additional_data' => $data
        ];
    }
    
    /**
     * Format Google Books API response
     */
    private function formatGoogleBooksResult($data) {
        $volumeInfo = $data['volumeInfo'] ?? [];
        
        return [
            'media_type' => 'book',
            'source' => 'google_books',
            'title' => $volumeInfo['title'] ?? '',
            'year' => $this->extractYear($volumeInfo['publishedDate'] ?? ''),
            'creator' => implode(', ', $volumeInfo['authors'] ?? []),
            'description' => $volumeInfo['description'] ?? '',
            'poster_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            'external_id' => $data['id'] ?? '',
            'media_details' => [
                'isbn' => $this->extractISBN($volumeInfo['industryIdentifiers'] ?? []),
                'isbn13' => $this->extractISBN13($volumeInfo['industryIdentifiers'] ?? []),
                'publisher' => $volumeInfo['publisher'] ?? '',
                'publication_date' => $volumeInfo['publishedDate'] ?? '',
                'page_count' => $volumeInfo['pageCount'] ?? null,
                'language' => $volumeInfo['language'] ?? 'en',
                'author' => implode(', ', $volumeInfo['authors'] ?? []),
            ],
            'categories' => $volumeInfo['categories'] ?? [],
            'additional_data' => $data
        ];
    }
    
    /**
     * Format Discogs API response
     */
    private function formatDiscogsResult($data) {
        return [
            'media_type' => 'music',
            'source' => 'discogs',
            'title' => $data['title'] ?? '',
            'year' => $data['year'] ?? '',
            'creator' => $data['artist'] ?? '',
            'description' => '',
            'poster_url' => $data['cover_image'] ?? null,
            'external_id' => $data['id'] ?? '',
            'media_details' => [
                'artist' => $data['artist'] ?? '',
                'record_label' => implode(', ', $data['label'] ?? []),
                'catalog_number' => $data['catno'] ?? '',
                'format' => $this->parseDiscogsFormat($data['format'] ?? []),
                'genre' => implode(', ', $data['genre'] ?? []),
                'album_type' => $data['type'] ?? 'release',
            ],
            'categories' => $data['genre'] ?? [],
            'additional_data' => $data
        ];
    }
    
    /**
     * Format ComicVine API response
     */
    private function formatComicVineResult($data) {
        return [
            'media_type' => 'comic',
            'source' => 'comicvine',
            'title' => $data['name'] ?? '',
            'year' => $this->extractYear($data['date_added'] ?? ''),
            'creator' => '',
            'description' => strip_tags($data['description'] ?? ''),
            'poster_url' => $data['image']['medium_url'] ?? null,
            'external_id' => $data['id'] ?? '',
            'media_details' => [
                'issue_number' => $data['issue_number'] ?? '',
                'volume_name' => $data['volume']['name'] ?? '',
                'publisher' => '',
                'cover_date' => $data['cover_date'] ?? '',
            ],
            'categories' => [],
            'additional_data' => $data
        ];
    }
    
    /**
     * Make HTTP request with error handling
     */
    private function makeRequest($url, $headers = []) {
        $defaultHeaders = [
            'Accept: application/json',
            'User-Agent: MediaCollectionApp/1.0'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            error_log("API Request failed: {$url} - HTTP {$httpCode} - {$error}");
            return null;
        }
        
        return $response;
    }
    
    /**
     * Select best match from multiple API results
     */
    private function selectBestMatch($results) {
        if (empty($results)) return null;
        
        // Simple scoring system - prioritize results with more complete data
        $scored = array_map(function($result) {
            $score = 0;
            if (!empty($result['title'])) $score += 10;
            if (!empty($result['year'])) $score += 5;
            if (!empty($result['creator'])) $score += 5;
            if (!empty($result['description'])) $score += 3;
            if (!empty($result['poster_url'])) $score += 3;
            if (!empty($result['media_details'])) $score += 2;
            
            return ['result' => $result, 'score' => $score];
        }, $results);
        
        // Sort by score descending
        usort($scored, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $scored[0]['result'];
    }
    
    // Helper functions
    private function parseRuntime($runtime) {
        if (preg_match('/(\d+)/', $runtime, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
    
    private function parseGenres($genres) {
        return array_map('trim', explode(',', $genres));
    }
    
    private function extractYear($date) {
        if (preg_match('/(\d{4})/', $date, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractISBN($identifiers) {
        foreach ($identifiers as $id) {
            if ($id['type'] === 'ISBN_10') {
                return $id['identifier'];
            }
        }
        return null;
    }
    
    private function extractISBN13($identifiers) {
        foreach ($identifiers as $id) {
            if ($id['type'] === 'ISBN_13') {
                return $id['identifier'];
            }
        }
        return null;
    }
    
    private function parseDiscogsFormat($formats) {
        if (empty($formats)) return 'cd';
        
        $format = strtolower($formats[0]);
        $mapping = [
            'vinyl' => 'vinyl_lp',
            'cd' => 'cd',
            'cassette' => 'cassette',
            'digital' => 'digital'
        ];
        
        return $mapping[$format] ?? 'cd';
    }
}

// api/handlers/metadata_lookup.php
require_once '../integrations/MediaAPIManager.php';

class MetadataLookupAPI {
    private $apiManager;
    
    public function __construct() {
        $this->apiManager = new MediaAPIManager();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'GET' && $method !== 'POST') {
            $this->sendError('Method not allowed', 405);
            return;
        }
        
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'lookup':
                $this->lookupMetadata();
                break;
            case 'search':
                $this->searchMetadata();
                break;
            case 'barcode':
                $this->barcodeSearch();
                break;
            default:
                $this->sendError('Invalid action');
        }
    }
    
    private function lookupMetadata() {
        $identifier = $_GET['identifier'] ?? $_POST['identifier'] ?? '';
        $mediaType = $_GET['media_type'] ?? $_POST['media_type'] ?? null;
        
        if (empty($identifier)) {
            $this->sendError('Identifier is required');
            return;
        }
        
        try {
            $results = $this->apiManager->lookupByIdentifier($identifier, $mediaType);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Lookup failed: ' . $e->getMessage());
        }
    }
    
    private function searchMetadata() {
        $query = $_GET['q'] ?? $_POST['q'] ?? '';
        $mediaType = $_GET['media_type'] ?? $_POST['media_type'] ?? '';
        
        if (empty($query)) {
            $this->sendError('Search query is required');
            return;
        }
        
        try {
            $results = [];
            
            switch ($mediaType) {
                case 'movie':
                    $results[] = $this->apiManager->searchOMDB($query);
                    break;
                case 'book':
                    $results[] = $this->apiManager->searchGoogleBooks($query, 'title');
                    break;
                case 'music':
                    $results[] = $this->apiManager->searchDiscogs($query);
                    break;
                case 'comic':
                    $results[] = $this->apiManager->searchComicVine($query);
                    break;
                default:
                    // Search all if no specific type
                    $results[] = $this->apiManager->searchOMDB($query);
                    $results[] = $this->apiManager->searchGoogleBooks($query, 'title');
                    $results[] = $this->apiManager->searchDiscogs($query);
                    $results[] = $this->apiManager->searchComicVine($query);
            }
            
            $validResults = array_filter($results, function($result) {
                return $result !== null;
            });
            
            echo json_encode([
                'success' => true,
                'query' => $query,
                'media_type' => $mediaType,
                'results' => array_values($validResults)
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Search failed: ' . $e->getMessage());
        }
    }
    
    private function barcodeSearch() {
        $barcode = $_GET['barcode'] ?? $_POST['barcode'] ?? '';
        
        if (empty($barcode)) {
            $this->sendError('Barcode is required');
            return;
        }
        
        try {
            // Barcode lookup tries UPC/EAN across multiple APIs
            $results = $this->apiManager->lookupByIdentifier($barcode);
            
            echo json_encode([
                'success' => true,
                'barcode' => $barcode,
                'data' => $results
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Barcode lookup failed: ' . $e->getMessage());
        }
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}

// Handle the request
$api = new MetadataLookupAPI();
$api->handleRequest();

// api/barcode_scanner.php - Frontend interface for barcode scanning
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner - Media Collection</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
        }
        
        .scanner-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .scanner-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .scanner-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .scanner-subtitle {
            color: #666;
        }
        
        #video {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border-radius: 10px;
            background: #000;
            display: block;
            margin: 0 auto 1rem;
        }
        
        .scanner-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .manual-input {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .input-group input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .result-container {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }
        
        .result-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .result-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .result-meta {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .result-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-header">
            <h1 class="scanner-title">📱 Barcode Scanner</h1>
            <p class="scanner-subtitle">Scan barcodes to automatically add items to your collection</p>
        </div>
        
        <video id="video" playsinline></video>
        
        <div class="scanner-controls">
            <button id="start-btn" class="btn" onclick="startScanner()">Start Camera</button>
            <button id="stop-btn" class="btn" onclick="stopScanner()" disabled>Stop Camera</button>
        </div>
        
        <div class="manual-input">
            <h3>Manual Entry</h3>
            <p>Or enter a barcode/identifier manually:</p>
            
            <div class="input-group">
                <input type="text" id="manual-code" placeholder="Enter barcode, ISBN, UPC, or IMDB ID..." maxlength="20">
                <button class="btn" onclick="lookupManual()">Lookup</button>
            </div>
            
            <div class="input-group">
                <select id="media-type-select">
                    <option value="">Auto-detect</option>
                    <option value="movie">Movie/TV</option>
                    <option value="book">Book</option>
                    <option value="comic">Comic</option>
                    <option value="music">Music</option>
                </select>
            </div>
        </div>
        
        <div id="result-container" class="result-container">
            <!-- Results will be displayed here -->
        </div>
    </div>

    <!-- Include QuaggaJS for barcode scanning -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    
    <script>
        let scanner = null;
        let isScanning = false;

        function startScanner() {
            if (isScanning) return;
            
            // Check for camera support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera not supported in this browser');
                return;
            }
            
            isScanning = true;
            document.getElementById('start-btn').disabled = true;
            document.getElementById('stop-btn').disabled = false;
            
            // Initialize QuaggaJS
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#video'),
                    constraints: {
                        width: 400,
                        height: 300,
                        facingMode: "environment"
                    }
                },
                decoder: {
                    readers: [
                        "code_128_reader",
                        "ean_reader",
                        "ean_8_reader",
                        "code_39_reader",
                        "code_39_vin_reader",
                        "codabar_reader",
                        "upc_reader",
                        "upc_e_reader"
                    ]
                }
            }, function(err) {
                if (err) {
                    console.error('QuaggaJS initialization failed:', err);
                    alert('Failed to start camera: ' + err.message);
                    stopScanner();
                    return;
                }
                Quagga.start();
            });
            
            // Listen for successful scans
            Quagga.onDetected(onBarcodeDetected);
        }

        function stopScanner() {
            if (!isScanning) return;
            
            isScanning = false;
            document.getElementById('start-btn').disabled = false;
            document.getElementById('stop-btn').disabled = true;
            
            if (Quagga) {
                Quagga.stop();
            }
        }

        function onBarcodeDetected(result) {
            const code = result.codeResult.code;
            console.log('Barcode detected:', code);
            
            // Stop scanner after successful detection
            stopScanner();
            
            // Lookup the barcode
            lookupCode(code);
        }

        function lookupManual() {
            const code = document.getElementById('manual-code').value.trim();
            const mediaType = document.getElementById('media-type-select').value;
            
            if (!code) {
                alert('Please enter a barcode or identifier');
                return;
            }
            
            lookupCode(code, mediaType);
        }

        async function lookupCode(code, mediaType = '') {
            const resultContainer = document.getElementById('result-container');
            
            // Show loading
            resultContainer.style.display = 'block';
            resultContainer.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    Looking up ${code}...
                </div>
            `;
            
            try {
                const params = new URLSearchParams({
                    action: 'lookup',
                    identifier: code
                });
                
                if (mediaType) {
                    params.append('media_type', mediaType);
                }
                
                const response = await fetch(`metadata_lookup.php?${params}`);
                const data = await response.json();
                
                if (data.success && data.data.results.length > 0) {
                    displayResults(data.data);
                } else {
                    resultContainer.innerHTML = `
                        <div class="result-item">
                            <div class="result-title">No Results Found</div>
                            <div class="result-meta">
                                Could not find information for: ${code}<br>
                                Try entering the title manually or check if the barcode is correct.
                            </div>
                            <div class="result-actions">
                                <button class="btn-small" onclick="addManually('${code}')">Add Manually</button>
                            </div>
                        </div>
                    `;
                }
                
            } catch (error) {
                console.error('Lookup error:', error);
                resultContainer.innerHTML = `
                    <div class="result-item">
                        <div class="result-title">Lookup Failed</div>
                        <div class="result-meta">
                            Error looking up ${code}: ${error.message}
                        </div>
                        <div class="result-actions">
                            <button class="btn-small" onclick="lookupCode('${code}', '${mediaType}')">Try Again</button>
                        </div>
                    </div>
                `;
            }
        }

        function displayResults(data) {
            const resultContainer = document.getElementById('result-container');
            const results = data.results;
            
            let html = '<h3>Found Results:</h3>';
            
            results.forEach((result, index) => {
                const mediaTypeIcons = {
                    movie: '🎬',
                    book: '📚',
                    comic: '📖',
                    music: '🎵'
                };
                
                html += `
                    <div class="result-item">
                        <div class="result-title">
                            ${mediaTypeIcons[result.media_type] || '📄'} ${result.title}
                        </div>
                        <div class="result-meta">
                            ${result.creator ? `${result.creator} • ` : ''}
                            ${result.year || 'Unknown Year'} • 
                            ${result.source.toUpperCase()} • 
                            ${result.media_type}
                        </div>
                        ${result.description ? `<p style="margin: 0.5rem 0; color: #666;">${result.description.substring(0, 200)}${result.description.length > 200 ? '...' : ''}</p>` : ''}
                        <div class="result-actions">
                            <button class="btn-small" onclick="addToCollection(${index})">
                                Add to Collection
                            </button>
                            <button class="btn-small" style="background: #ffc107; color: #000;" onclick="addToWishlist(${index})">
                                Add to Wishlist
                            </button>
                        </div>
                    </div>
                `;
            });
            
            resultContainer.innerHTML = html;
            
            // Store results for later use
            window.currentResults = results;
        }

        function addToCollection(index) {
            const result = window.currentResults[index];
            
            // Create form data and submit to add item endpoint
            const formData = {
                action: 'add_item',
                ...result,
                source: 'barcode_scan'
            };
            
            // Redirect to add form with pre-filled data
            const params = new URLSearchParams(formData);
            window.location.href = `../public/add.php?${params}`;
        }

        function addToWishlist(index) {
            const result = window.currentResults[index];
            
            // Redirect to wishlist form with pre-filled data
            const params = new URLSearchParams({
                action: 'add_wishlist',
                ...result,
                source: 'barcode_scan'
            });
            
            window.location.href = `../admin/wishlist.php?${params}`;
        }

        function addManually(code) {
            // Redirect to manual add form
            window.location.href = `../public/add.php?identifier=${code}`;
        }

        // Handle Enter key in manual input
        document.getElementById('manual-code').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                lookupManual();
            }
        });

        // Clean up scanner when page unloads
        window.addEventListener('beforeunload', function() {
            stopScanner();
        });
    </script>
</body>
</html>