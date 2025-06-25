<?php
/**
 * Criterion Collection API Integration
 * File: api/integrations/CriterionAPI.php
 * Integrates with Node.js Critnodejs scraper
 */

class CriterionAPI {
    private $nodeScriptPath;
    private $dataPath;
    private $cacheFile;
    private $cacheTimeout;
    
    public function __construct() {
        $this->nodeScriptPath = dirname(__DIR__, 2) . '/scripts/criterion_scraper/';
        $this->dataPath = dirname(__DIR__, 2) . '/data/';
        $this->cacheFile = $this->dataPath . 'criterion_cache.json';
        $this->cacheTimeout = 3600; // 1 hour cache
        
        // Ensure data directory exists
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }
    
    /**
     * Get latest Criterion releases
     */
    public function getLatestReleases($limit = 10, $forceRefresh = false) {
        try {
            // Check cache first
            if (!$forceRefresh && $this->isCacheValid()) {
                $cached = $this->getCachedData();
                if ($cached && isset($cached['films'])) {
                    return array_slice($cached['films'], 0, $limit);
                }
            }
            
            // Try to get fresh data
            $freshData = $this->fetchFreshData();
            if ($freshData && isset($freshData['films'])) {
                $this->cacheData($freshData);
                return array_slice($freshData['films'], 0, $limit);
            }
            
            // Fallback to cached data even if expired
            $cached = $this->getCachedData();
            if ($cached && isset($cached['films'])) {
                return array_slice($cached['films'], 0, $limit);
            }
            
            // Ultimate fallback: mock data
            return $this->getMockData($limit);
            
        } catch (Exception $e) {
            error_log("Criterion API Error: " . $e->getMessage());
            return $this->getMockData($limit);
        }
    }
    
    /**
     * Trigger manual scrape using Node.js script
     */
    public function triggerScrape() {
        try {
            $command = "cd " . escapeshellarg($this->nodeScriptPath) . " && npm run scrape 2>&1";
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception("Failed to execute Node.js scraper");
            }
            
            // Check if scrape was successful
            $dataFile = $this->nodeScriptPath . 'data/criterion_releases.db';
            if (file_exists($dataFile)) {
                // Convert SQLite data to JSON format
                $this->convertSQLiteToJSON($dataFile);
                return ['success' => true, 'message' => 'Scrape completed successfully'];
            } else {
                return ['success' => false, 'error' => 'Scrape completed but no data found'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Convert SQLite database to JSON format
     */
    private function convertSQLiteToJSON($dbFile) {
        try {
            if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers())) {
                throw new Exception("SQLite PDO driver not available");
            }
            
            $pdo = new PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("
                SELECT title, director, spine_number, release_date, 
                       format, price, url, cover_art_url, created_at
                FROM films 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            
            $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $jsonData = [
                'last_updated' => date('Y-m-d H:i:s'),
                'total_films' => count($films),
                'films' => $films
            ];
            
            file_put_contents($this->cacheFile, json_encode($jsonData, JSON_PRETTY_PRINT));
            
        } catch (Exception $e) {
            error_log("SQLite conversion error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if cache is valid
     */
    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        $cacheTime = filemtime($this->cacheFile);
        return (time() - $cacheTime) < $this->cacheTimeout;
    }
    
    /**
     * Get cached data
     */
    private function getCachedData() {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        
        $data = file_get_contents($this->cacheFile);
        return json_decode($data, true);
    }
    
    /**
     * Cache data to file
     */
    private function cacheData($data) {
        file_put_contents($this->cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Fetch fresh data from various sources
     */
    private function fetchFreshData() {
        // Try Node.js generated JSON first
        $nodeJsonFile = $this->nodeScriptPath . 'exports/criterion_latest.json';
        if (file_exists($nodeJsonFile)) {
            $data = file_get_contents($nodeJsonFile);
            $parsed = json_decode($data, true);
            if ($parsed) {
                return $parsed;
            }
        }
        
        // Try SQLite database
        $dbFile = $this->nodeScriptPath . 'data/criterion_releases.db';
        if (file_exists($dbFile)) {
            $this->convertSQLiteToJSON($dbFile);
            return $this->getCachedData();
        }
        
        return null;
    }
    
    /**
     * Get mock data for testing/fallback
     */
    private function getMockData($limit) {
        $mockData = [
            [
                'title' => 'The Rules of the Game',
                'director' => 'Jean Renoir',
                'spine_number' => '1050',
                'release_date' => '2024-01-15',
                'format' => 'Blu-ray',
                'price' => '$39.95',
                'url' => 'https://www.criterion.com/films/184-the-rules-of-the-game',
                'cover_art_url' => '',
                'created_at' => '2024-01-15 10:00:00'
            ],
            [
                'title' => 'Seven Samurai',
                'director' => 'Akira Kurosawa',
                'spine_number' => '1051',
                'release_date' => '2024-01-22',
                'format' => '4K UHD + Blu-ray',
                'price' => '$49.95',
                'url' => 'https://www.criterion.com/films/1041-seven-samurai',
                'cover_art_url' => '',
                'created_at' => '2024-01-22 10:00:00'
            ],
            [
                'title' => 'The 400 Blows',
                'director' => 'François Truffaut',
                'spine_number' => '1052',
                'release_date' => '2024-02-01',
                'format' => 'Blu-ray',
                'price' => '$39.95',
                'url' => 'https://www.criterion.com/films/678-the-400-blows',
                'cover_art_url' => '',
                'created_at' => '2024-02-01 10:00:00'
            ],
            [
                'title' => 'Bicycle Thieves',
                'director' => 'Vittorio De Sica',
                'spine_number' => '1053',
                'release_date' => '2024-02-08',
                'format' => 'Blu-ray',
                'price' => '$39.95',
                'url' => 'https://www.criterion.com/films/374-bicycle-thieves',
                'cover_art_url' => '',
                'created_at' => '2024-02-08 10:00:00'
            ],
            [
                'title' => 'Tokyo Story',
                'director' => 'Yasujirō Ozu',
                'spine_number' => '1054',
                'release_date' => '2024-02-15',
                'format' => 'Blu-ray',
                'price' => '$39.95',
                'url' => 'https://www.criterion.com/films/4093-tokyo-story',
                'cover_art_url' => '',
                'created_at' => '2024-02-15 10:00:00'
            ]
        ];
        
        return array_slice($mockData, 0, $limit);
    }
    
    /**
     * Search Criterion collection
     */
    public function searchFilms($query, $limit = 20) {
        try {
            $cached = $this->getCachedData();
            if (!$cached || !isset($cached['films'])) {
                return [];
            }
            
            $results = [];
            $query = strtolower($query);
            
            foreach ($cached['films'] as $film) {
                if (strpos(strtolower($film['title']), $query) !== false ||
                    strpos(strtolower($film['director']), $query) !== false) {
                    $results[] = $film;
                    
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Criterion search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get film by spine number
     */
    public function getFilmBySpine($spineNumber) {
        try {
            $cached = $this->getCachedData();
            if (!$cached || !isset($cached['films'])) {
                return null;
            }
            
            foreach ($cached['films'] as $film) {
                if ($film['spine_number'] == $spineNumber) {
                    return $film;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Criterion spine lookup error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get statistics about Criterion collection
     */
    public function getStats() {
        try {
            $cached = $this->getCachedData();
            if (!$cached || !isset($cached['films'])) {
                return [
                    'total_films' => 0,
                    'latest_spine' => 0,
                    'last_updated' => 'Never',
                    'formats' => []
                ];
            }
            
            $films = $cached['films'];
            $formats = [];
            $maxSpine = 0;
            
            foreach ($films as $film) {
                // Count formats
                $format = $film['format'] ?? 'Unknown';
                $formats[$format] = ($formats[$format] ?? 0) + 1;
                
                // Find highest spine number
                $spine = intval($film['spine_number'] ?? 0);
                if ($spine > $maxSpine) {
                    $maxSpine = $spine;
                }
            }
            
            return [
                'total_films' => count($films),
                'latest_spine' => $maxSpine,
                'last_updated' => $cached['last_updated'] ?? 'Unknown',
                'formats' => $formats
            ];
            
        } catch (Exception $e) {
            error_log("Criterion stats error: " . $e->getMessage());
            return [
                'total_films' => 0,
                'latest_spine' => 0,
                'last_updated' => 'Error',
                'formats' => []
            ];
        }
    }
    
    /**
     * Export Criterion data to different formats
     */
    public function exportData($format = 'json') {
        try {
            $cached = $this->getCachedData();
            if (!$cached || !isset($cached['films'])) {
                throw new Exception("No data available to export");
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $exportDir = $this->dataPath . 'exports/';
            
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($cached['films'], $exportDir, $timestamp);
                    
                case 'xml':
                    return $this->exportToXML($cached['films'], $exportDir, $timestamp);
                    
                case 'json':
                default:
                    $filename = "criterion_export_{$timestamp}.json";
                    $filepath = $exportDir . $filename;
                    file_put_contents($filepath, json_encode($cached, JSON_PRETTY_PRINT));
                    return ['filename' => $filename, 'path' => $filepath];
            }
            
        } catch (Exception $e) {
            error_log("Criterion export error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Export to CSV format
     */
    private function exportToCSV($films, $exportDir, $timestamp) {
        $filename = "criterion_export_{$timestamp}.csv";
        $filepath = $exportDir . $filename;
        
        $fp = fopen($filepath, 'w');
        
        // Write header
        fputcsv($fp, ['Title', 'Director', 'Spine Number', 'Release Date', 'Format', 'Price', 'URL']);
        
        // Write data
        foreach ($films as $film) {
            fputcsv($fp, [
                $film['title'] ?? '',
                $film['director'] ?? '',
                $film['spine_number'] ?? '',
                $film['release_date'] ?? '',
                $film['format'] ?? '',
                $film['price'] ?? '',
                $film['url'] ?? ''
            ]);
        }
        
        fclose($fp);
        
        return ['filename' => $filename, 'path' => $filepath];
    }
    
    /**
     * Export to XML format
     */
    private function exportToXML($films, $exportDir, $timestamp) {
        $filename = "criterion_export_{$timestamp}.xml";
        $filepath = $exportDir . $filename;
        
        $xml = new SimpleXMLElement('<criterion_collection/>');
        $xml->addAttribute('exported', date('Y-m-d H:i:s'));
        $xml->addAttribute('total_films', count($films));
        
        foreach ($films as $film) {
            $filmNode = $xml->addChild('film');
            
            foreach ($film as $key => $value) {
                $filmNode->addChild($key, htmlspecialchars($value ?? ''));
            }
        }
        
        $xml->asXML($filepath);
        
        return ['filename' => $filename, 'path' => $filepath];
    }
    
    /**
     * Setup Node.js scraper environment
     */
    public function setupNodeScraper() {
        try {
            $scraperDir = $this->nodeScriptPath;
            
            // Create scraper directory if it doesn't exist
            if (!is_dir($scraperDir)) {
                mkdir($scraperDir, 0755, true);
            }
            
            // Create package.json if it doesn't exist
            $packageJson = $scraperDir . 'package.json';
            if (!file_exists($packageJson)) {
                $package = [
                    'name' => 'criterion-scraper',
                    'version' => '1.0.0',
                    'description' => 'Criterion Collection scraper integration',
                    'main' => 'scraper.js',
                    'scripts' => [
                        'start' => 'node scraper.js',
                        'scrape' => 'node scraper.js --scrape'
                    ],
                    'dependencies' => [
                        'puppeteer' => '^21.0.0',
                        'sqlite3' => '^5.1.0'
                    ]
                ];
                
                file_put_contents($packageJson, json_encode($package, JSON_PRETTY_PRINT));
            }
            
            return ['success' => true, 'message' => 'Node.js scraper environment setup complete'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}