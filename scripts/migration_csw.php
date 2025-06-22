<?php
require_once 'config.php';

class DataMigration {
    private $pdo;
    
    public function __construct() {
        $this->pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    public function migrateMoviesFromCSV($csvFile) {
        echo "Starting migration of movies from CSV...\n";
        
        $file = fopen($csvFile, 'r');
        if (!$file) {
            die("Cannot open CSV file: $csvFile\n");
        }
        
        // Read header row
        $headers = fgetcsv($file);
        $this->cleanHeaders($headers);
        
        $processed = 0;
        $errors = 0;
        
        while (($row = fgetcsv($file)) !== FALSE) {
            try {
                $data = array_combine($headers, $row);
                $this->insertMovieRecord($data);
                $processed++;
                
                if ($processed % 100 == 0) {
                    echo "Processed: $processed movies\n";
                }
            } catch (Exception $e) {
                $errors++;
                echo "Error processing row: " . $e->getMessage() . "\n";
                echo "Data: " . print_r($row, true) . "\n";
            }
        }
        
        fclose($file);
        echo "Migration completed!\n";
        echo "Successfully processed: $processed movies\n";
        echo "Errors: $errors\n";
    }
    
    private function cleanHeaders(&$headers) {
        // Remove quotes and normalize header names
        $headers = array_map(function($header) {
            return trim($header, " '\"");
        }, $headers);
    }
    
    private function insertMovieRecord($data) {
        // Parse and clean the data
        $title = $this->cleanTitle($data['Title'] ?? '');
        $year = $this->extractYear($data['Release Year'] ?? '');
        $studio = $this->cleanField($data['Studios'] ?? '');
        $country = $data['Country'] ?? 'USA';
        $barcode = $this->cleanBarcode($data['Barcode'] ?? '');
        $format = $this->parseFormat($data['Format'] ?? '');
        $aspectRatio = $this->parseAspectRatio($data['Screen Ratios'] ?? '');
        $language = $data['Language'] ?? 'English';
        $imdbUrl = $this->cleanField($data['IMDb Url'] ?? '');
        $boxSet = $this->cleanField($data['Box Set'] ?? '');
        $subtitles = $this->cleanField($data['Subtitles'] ?? '');
        
        if (empty($title)) {
            throw new Exception("Title is required");
        }
        
        // Insert into main collection table
        $sql = "INSERT INTO collection (
            media_type, title, year, creator, identifier, 
            poster_url, description, created_at
        ) VALUES (
            'movie', :title, :year, :studio, :barcode,
            :poster_url, :description, NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'title' => $title,
            'year' => $year,
            'studio' => $studio,
            'barcode' => $barcode,
            'poster_url' => $this->generatePosterUrl($imdbUrl),
            'description' => $this->generateDescription($data)
        ]);
        
        $collectionId = $this->pdo->lastInsertId();
        
        // Insert into movie_details table
        $this->insertMovieDetails($collectionId, [
            'format' => $format['primary'],
            'digital_formats' => $format['digital'],
            'region' => $this->detectRegion($country),
            'aspect_ratio' => $aspectRatio,
            'studio' => $studio,
            'original_language' => $language,
            'subtitle_languages' => $subtitles,
            'box_set_name' => $boxSet,
            'imdb_url' => $imdbUrl,
            'barcode' => $barcode
        ]);
    }
    
    private function insertMovieDetails($collectionId, $details) {
        $sql = "INSERT INTO movie_details (
            collection_id, format, region, aspect_ratio, 
            studio, original_language, subtitle_languages,
            box_set_name, case_type
        ) VALUES (
            :collection_id, :format, :region, :aspect_ratio,
            :studio, :language, :subtitles, :box_set, :case_type
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'collection_id' => $collectionId,
            'format' => $details['format'],
            'region' => $details['region'],
            'aspect_ratio' => $details['aspect_ratio'],
            'studio' => $details['studio'],
            'language' => $details['original_language'],
            'subtitles' => $details['subtitle_languages'],
            'box_set' => $details['box_set_name'],
            'case_type' => $this->determineCaseType($details['format'])
        ]);
    }
    
    private function cleanTitle($title) {
        return trim($title, " '\"");
    }
    
    private function cleanField($field) {
        return empty($field) ? null : trim($field, " '\"");
    }
    
    private function extractYear($yearField) {
        if (empty($yearField) || !is_numeric($yearField)) {
            return null;
        }
        return (int)$yearField;
    }
    
    private function cleanBarcode($barcode) {
        if (empty($barcode) || !is_numeric($barcode)) {
            return null;
        }
        return (string)$barcode;
    }
    
    private function parseFormat($formatString) {
        $formatString = trim($formatString, " '\"");
        
        // Map common formats to our enum values
        $formatMap = [
            'DVD' => 'dvd',
            'Blu-ray' => 'blu_ray',
            '4K UHD' => '4k_uhd',
            'VHS' => 'vhs',
            'Digital' => 'digital',
            'LaserDisc' => 'laserdisc'
        ];
        
        $primary = 'dvd'; // default
        $digital = [];
        
        // Parse complex format strings like "4K UHD | Digital | UltraViolet"
        $parts = explode('|', $formatString);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (isset($formatMap[$part])) {
                $primary = $formatMap[$part];
            } elseif (strpos($part, 'Digital') !== false) {
                $digital[] = 'digital';
            } elseif (strpos($part, 'UltraViolet') !== false) {
                $digital[] = 'ultraviolet';
            }
        }
        
        return [
            'primary' => $primary,
            'digital' => implode(',', array_unique($digital))
        ];
    }
    
    private function parseAspectRatio($ratioString) {
        $ratioString = trim($ratioString, " '\"");
        
        // Extract the first/primary aspect ratio
        if (preg_match('/(\d+\.?\d*:\d+\.?\d*)/', $ratioString, $matches)) {
            return $matches[1];
        }
        
        // Handle common formats
        if (strpos($ratioString, 'Fullscreen') !== false) {
            return '4:3';
        } elseif (strpos($ratioString, 'Widescreen') !== false) {
            return '16:9';
        }
        
        return null;
    }
    
    private function detectRegion($country) {
        $regionMap = [
            'USA' => 'region_1',
            'Canada' => 'region_1',
            'UK' => 'region_2',
            'Europe' => 'region_2',
            'Japan' => 'region_2',
            'Australia' => 'region_4'
        ];
        
        return $regionMap[$country] ?? 'region_1';
    }
    
    private function determineCaseType($format) {
        switch ($format) {
            case 'blu_ray':
            case '4k_uhd':
                return 'standard';
            case 'dvd':
                return 'standard';
            case 'vhs':
                return 'other';
            default:
                return 'standard';
        }
    }
    
    private function generatePosterUrl($imdbUrl) {
        if (empty($imdbUrl)) return null;
        
        // Extract IMDB ID and use it to construct poster URL
        if (preg_match('/tt(\d+)/', $imdbUrl, $matches)) {
            return "https://img.omdbapi.com/?i=tt{$matches[1]}&apikey=" . OMDB_API_KEY;
        }
        
        return null;
    }
    
    private function generateDescription($data) {
        $parts = [];
        
        if (!empty($data['Studios'])) {
            $parts[] = "Studio: " . trim($data['Studios'], " '\"");
        }
        
        if (!empty($data['Country'])) {
            $parts[] = "Country: " . $data['Country'];
        }
        
        if (!empty($data['Language']) && $data['Language'] !== 'English') {
            $parts[] = "Language: " . $data['Language'];
        }
        
        return empty($parts) ? null : implode(' | ', $parts);
    }
}

// Usage example:
if (php_sapi_name() === 'cli') {
    try {
        $migration = new DataMigration();
        $csvFile = 'export_movies2.csv';
        
        if (!file_exists($csvFile)) {
            die("CSV file not found: $csvFile\n");
        }
        
        $migration->migrateMoviesFromCSV($csvFile);
        
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "This script should be run from command line\n";
}
?>