<?php
// api/handlers/import.php - Data Import Handler
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

class ImportAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function handleRequest() {
        requireAuth();
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'POST') {
            $this->sendError('Method not allowed', 405);
            return;
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload_csv':
                $this->uploadCSV();
                break;
            case 'process_csv':
                $this->processCSV();
                break;
            case 'import_batch':
                $this->importBatch();
                break;
            default:
                $this->sendError('Invalid action');
        }
    }
    
    private function uploadCSV() {
        if (!isset($_FILES['csv_file'])) {
            $this->sendError('No file uploaded');
            return;
        }
        
        $file = $_FILES['csv_file'];
        
        // Validate file
        $validation = SecureUpload::validateFile($file, 'document');
        if (!$validation['valid']) {
            $this->sendError(implode(', ', $validation['errors']));
            return;
        }
        
        // Save file
        $uploadResult = SecureUpload::saveFile($file, '../uploads/csv/', 'import_' . time());
        
        if ($uploadResult['success']) {
            // Parse CSV and return preview
            $preview = $this->parseCSVPreview($uploadResult['path']);
            
            echo json_encode([
                'success' => true,
                'file_id' => basename($uploadResult['filename'], '.csv'),
                'preview' => $preview
            ]);
        } else {
            $this->sendError(implode(', ', $uploadResult['errors']));
        }
    }
    
    private function parseCSVPreview($filePath) {
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        
        // Clean headers
        $headers = array_map(function($header) {
            return trim($header, " '\"\t\n\r\0\x0B");
        }, $headers);
        
        // Get first 5 rows for preview
        $preview = [];
        $rowCount = 0;
        
        while (($row = fgetcsv($handle)) && $rowCount < 5) {
            if (count($row) === count($headers)) {
                $preview[] = array_combine($headers, $row);
                $rowCount++;
            }
        }
        
        // Count total rows
        $totalRows = 0;
        while (fgetcsv($handle)) {
            $totalRows++;
        }
        
        fclose($handle);
        
        return [
            'headers' => $headers,
            'sample_rows' => $preview,
            'total_rows' => $totalRows + $rowCount,
            'suggested_mapping' => $this->suggestColumnMapping($headers)
        ];
    }
    
    private function suggestColumnMapping($headers) {
        $mapping = [];
        
        $fieldMappings = [
            'title' => ['title', 'name', 'movie_title', 'book_title'],
            'year' => ['year', 'release_year', 'publication_year', 'date'],
            'creator' => ['director', 'author', 'artist', 'creator'],
            'description' => ['description', 'plot', 'summary', 'synopsis'],
            'media_type' => ['type', 'media_type', 'category'],
            'condition_rating' => ['condition', 'grade', 'quality'],
            'purchase_price' => ['price', 'cost', 'purchase_price', 'value'],
            'identifier' => ['isbn', 'upc', 'barcode', 'id']
        ];
        
        foreach ($headers as $header) {
            $headerLower = strtolower($header);
            
            foreach ($fieldMappings as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($headerLower, $pattern) !== false) {
                        $mapping[$header] = $field;
                        break 2;
                    }
                }
            }
        }
        
        return $mapping;
    }
    
    private function processCSV() {
        $fileId = $_POST['file_id'] ?? '';
        $mapping = $_POST['column_mapping'] ?? [];
        $mediaType = $_POST['default_media_type'] ?? '';
        
        if (!$fileId) {
            $this->sendError('File ID required');
            return;
        }
        
        $filePath = "../uploads/csv/{$fileId}.csv";
        if (!file_exists($filePath)) {
            $this->sendError('File not found');
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $results = $this->importCSVData($filePath, $mapping, $mediaType);
            
            $this->pdo->commit();
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            logEvent('CSV import error: ' . $e->getMessage(), 'ERROR');
            $this->sendError('Import failed: ' . $e->getMessage());
        }
    }
    
    private function importCSVData($filePath, $mapping, $defaultMediaType) {
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        
        // Clean headers
        $headers = array_map(function($header) {
            return trim($header, " '\"\t\n\r\0\x0B");
        }, $headers);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                $skipped++;
                continue;
            }
            
            $data = array_combine($headers, $row);
            $mappedData = $this->mapRowData($data, $mapping, $defaultMediaType);
            
            try {
                if ($this->importSingleItem($mappedData)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported + $skipped + 1) . ": " . $e->getMessage();
                $skipped++;
            }
        }
        
        fclose($handle);
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    private function mapRowData($data, $mapping, $defaultMediaType) {
        $mapped = [
            'media_type' => $defaultMediaType,
            'status' => 'owned'
        ];
        
        foreach ($mapping as $csvColumn => $dbField) {
            if (isset($data[$csvColumn]) && !empty(trim($data[$csvColumn]))) {
                $value = trim($data[$csvColumn]);
                
                // Special handling for certain fields
                switch ($dbField) {
                    case 'year':
                        $mapped[$dbField] = (int)$value;
                        break;
                    case 'purchase_price':
                    case 'current_value':
                        $mapped[$dbField] = (float)$value;
                        break;
                    case 'media_type':
                        $mapped[$dbField] = strtolower($value);
                        break;
                    default:
                        $mapped[$dbField] = $value;
                }
            }
        }
        
        return $mapped;
    }
    
    private function importSingleItem($data) {
        // Check if title is provided
        if (empty($data['title'])) {
            return false;
        }
        
        // Check for duplicates
        $checkSql = "SELECT id FROM collection WHERE title = ? AND media_type = ?";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$data['title'], $data['media_type']]);
        
        if ($checkStmt->fetch()) {
            return false; // Skip duplicate
        }
        
        // Insert item
        $sql = "INSERT INTO collection (
            media_type, title, year, creator, description, 
            purchase_price, current_value, condition_rating, 
            identifier, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['media_type'],
            $data['title'],
            $data['year'] ?? null,
            $data['creator'] ?? null,
            $data['description'] ?? null,
            $data['purchase_price'] ?? null,
            $data['current_value'] ?? $data['purchase_price'] ?? null,
            $data['condition_rating'] ?? 'very_fine',
            $data['identifier'] ?? null,
            $data['status']
        ]);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

$api = new ImportAPI();
$api->handleRequest();

// api/handlers/export.php - Data Export Handler
<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

class ExportAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function handleRequest() {
        requireAuth();
        
        $format = $_GET['format'] ?? 'csv';
        $type = $_GET['type'] ?? 'collection';
        
        switch ($type) {
            case 'collection':
                $this->exportCollection($format);
                break;
            case 'wishlist':
                $this->exportWishlist($format);
                break;
            case 'categories':
                $this->exportCategories($format);
                break;
            case 'locations':
                $this->exportLocations($format);
                break;
            default:
                $this->sendError('Invalid export type');
        }
    }
    
    private function exportCollection($format) {
        $filters = $this->getFilters();
        
        $sql = "SELECT c.*, sl.name as location_name, 
                       GROUP_CONCAT(cat.name SEPARATOR ', ') as categories
                FROM collection c
                LEFT JOIN storage_locations sl ON c.primary_location_id = sl.id
                LEFT JOIN collection_categories cc ON c.id = cc.collection_id
                LEFT JOIN categories cat ON cc.category_id = cat.id
                WHERE c.status = 'owned'";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['media_type'])) {
            $sql .= " AND c.media_type = ?";
            $params[] = $filters['media_type'];
        }
        
        if (!empty($filters['location_id'])) {
            $sql .= " AND c.primary_location_id = ?";
            $params[] = $filters['location_id'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Add media-specific details
        foreach ($data as &$item) {
            $details = $this->getMediaDetails($item['id'], $item['media_type']);
            $item = array_merge($item, $details);
        }
        
        $this->outputData($data, $format, 'collection');
    }
    
    private function exportWishlist($format) {
        $sql = "SELECT w.*, GROUP_CONCAT(cat.name SEPARATOR ', ') as categories
                FROM wishlist w
                LEFT JOIN wishlist_categories wc ON w.id = wc.wishlist_id
                LEFT JOIN categories cat ON wc.category_id = cat.id
                WHERE w.date_acquired IS NULL
                GROUP BY w.id
                ORDER BY w.priority, w.date_added";
        
        $stmt = $this->pdo->query($sql);
        $data = $stmt->fetchAll();
        
        $this->outputData($data, $format, 'wishlist');
    }
    
    private function exportCategories($format) {
        $sql = "SELECT c.*, p.name as parent_name,
                       COUNT(cc.collection_id) as item_count
                FROM categories c
                LEFT JOIN categories p ON c.parent_id = p.id
                LEFT JOIN collection_categories cc ON c.id = cc.category_id
                GROUP BY c.id
                ORDER BY c.media_type, c.category_level, c.name";
        
        $stmt = $this->pdo->query($sql);
        $data = $stmt->fetchAll();
        
        $this->outputData($data, $format, 'categories');
    }
    
    private function exportLocations($format) {
        $sql = "SELECT l.*, p.name as parent_name,
                       COUNT(c.id) as item_count
                FROM storage_locations l
                LEFT JOIN storage_locations p ON l.parent_location_id = p.id
                LEFT JOIN collection c ON l.id = c.primary_location_id
                GROUP BY l.id
                ORDER BY l.name";
        
        $stmt = $this->pdo->query($sql);
        $data = $stmt->fetchAll();
        
        $this->outputData($data, $format, 'locations');
    }
    
    private function getMediaDetails($collectionId, $mediaType) {
        $table = $mediaType . '_details';
        
        try {
            $sql = "SELECT * FROM {$table} WHERE collection_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$collectionId]);
            return $stmt->fetch() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function outputData($data, $format, $filename) {
        $timestamp = date('Y-m-d_H-i-s');
        $fullFilename = "{$filename}_{$timestamp}";
        
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header("Content-Disposition: attachment; filename=\"{$fullFilename}.json\"");
                echo json_encode($data, JSON_PRETTY_PRINT);
                break;
                
            case 'xml':
                header('Content-Type: application/xml');
                header("Content-Disposition: attachment; filename=\"{$fullFilename}.xml\"");
                echo $this->arrayToXML($data, $filename);
                break;
                
            default: // CSV
                header('Content-Type: text/csv');
                header("Content-Disposition: attachment; filename=\"{$fullFilename}.csv\"");
                
                if (!empty($data)) {
                    $output = fopen('php://output', 'w');
                    fputcsv($output, array_keys($data[0]));
                    
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                    
                    fclose($output);
                }
                break;
        }
        exit;
    }
    
    private function arrayToXML($data, $rootElement = 'data') {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><{$rootElement}></{$rootElement}>");
        
        foreach ($data as $item) {
            $xmlItem = $xml->addChild('item');
            foreach ($item as $key => $value) {
                $xmlItem->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
    
    private function getFilters() {
        return [
            'media_type' => $_GET['media_type'] ?? '',
            'location_id' => $_GET['location_id'] ?? '',
            'category_id' => $_GET['category_id'] ?? ''
        ];
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

$api = new ExportAPI();
$api->handleRequest();

// api/handlers/loans.php - Loan Tracking Handler
<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

class LoansAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function handleRequest() {
        requireAuth();
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getLoans();
                break;
            case 'POST':
                $this->createLoan();
                break;
            case 'PUT':
                $this->updateLoan();
                break;
            case 'DELETE':
                $this->deleteLoan();
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function getLoans() {
        $status = $_GET['status'] ?? 'active';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(intval($_GET['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if ($status === 'active') {
            $whereClause = 'WHERE l.actual_return_date IS NULL';
        } elseif ($status === 'returned') {
            $whereClause = 'WHERE l.actual_return_date IS NOT NULL';
        } elseif ($status === 'overdue') {
            $whereClause = 'WHERE l.actual_return_date IS NULL AND l.expected_return_date < CURDATE()';
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM loans l
                     JOIN collection c ON l.collection_id = c.id
                     {$whereClause}";
        
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get loans
        $sql = "SELECT l.*, c.title, c.media_type, c.poster_url,
                       DATEDIFF(CURDATE(), l.expected_return_date) as days_overdue
                FROM loans l
                JOIN collection c ON l.collection_id = c.id
                {$whereClause}
                ORDER BY l.loan_date DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $loans = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'loans' => $loans,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    private function createLoan() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['collection_id', 'borrower_name', 'loan_date'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendError("Missing required field: {$field}");
                return;
            }
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if item is already loaned
            $checkSql = "SELECT id FROM loans WHERE collection_id = ? AND actual_return_date IS NULL";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$input['collection_id']]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('Item is already on loan');
            }
            
            // Create loan
            $sql = "INSERT INTO loans (
                collection_id, borrower_name, borrower_contact, borrower_address,
                loan_date, expected_return_date, loan_value, deposit_required,
                deposit_paid, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $input['collection_id'],
                $input['borrower_name'],
                $input['borrower_contact'] ?? null,
                $input['borrower_address'] ?? null,
                $input['loan_date'],
                $input['expected_return_date'] ?? null,
                $input['loan_value'] ?? null,
                $input['deposit_required'] ?? 0,
                $input['deposit_paid'] ?? 0,
                $input['notes'] ?? null
            ]);
            
            $loanId = $this->pdo->lastInsertId();
            
            // Update collection status
            $updateSql = "UPDATE collection SET status = 'loaned' WHERE id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$input['collection_id']]);
            
            $this->pdo->commit();
            
            echo json_encode([
                'success' => true,
                'loan_id' => $loanId,
                'message' => 'Loan created successfully'
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->sendError('Failed to create loan: ' . $e->getMessage());
        }
    }
    
    private function updateLoan() {
        $input = json_decode(file_get_contents('php://input'), true);
        $loanId = $_GET['id'] ?? 0;
        
        if (!$loanId) {
            $this->sendError('Loan ID required');
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // If returning item
            if (isset($input['actual_return_date'])) {
                $sql = "UPDATE loans SET 
                        actual_return_date = ?, 
                        return_condition = ?,
                        return_notes = ?
                        WHERE id = ?";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $input['actual_return_date'],
                    $input['return_condition'] ?? 'same',
                    $input['return_notes'] ?? null,
                    $loanId
                ]);
                
                // Get collection ID and update status
                $collectionSql = "SELECT collection_id FROM loans WHERE id = ?";
                $collectionStmt = $this->pdo->prepare($collectionSql);
                $collectionStmt->execute([$loanId]);
                $collectionId = $collectionStmt->fetch()['collection_id'];
                
                $updateSql = "UPDATE collection SET status = 'owned' WHERE id = ?";
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([$collectionId]);
                
            } else {
                // Update loan details
                $allowedFields = [
                    'borrower_name', 'borrower_contact', 'borrower_address',
                    'expected_return_date', 'loan_value', 'deposit_required',
                    'deposit_paid', 'notes'
                ];
                
                $updateFields = [];
                $params = [];
                
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateFields[] = "{$field} = ?";
                        $params[] = $input[$field];
                    }
                }
                
                if (!empty($updateFields)) {
                    $sql = "UPDATE loans SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $params[] = $loanId;
                    
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                }
            }
            
            $this->pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Loan updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->sendError('Failed to update loan: ' . $e->getMessage());
        }
    }
    
    private function deleteLoan() {
        $loanId = $_GET['id'] ?? 0;
        
        if (!$loanId) {
            $this->sendError('Loan ID required');
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Get collection ID before deleting
            $collectionSql = "SELECT collection_id FROM loans WHERE id = ?";
            $collectionStmt = $this->pdo->prepare($collectionSql);
            $collectionStmt->execute([$loanId]);
            $result = $collectionStmt->fetch();
            
            if (!$result) {
                throw new Exception('Loan not found');
            }
            
            $collectionId = $result['collection_id'];
            
            // Delete loan
            $deleteSql = "DELETE FROM loans WHERE id = ?";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->execute([$loanId]);
            
            // Update collection status back to owned
            $updateSql = "UPDATE collection SET status = 'owned' WHERE id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$collectionId]);
            
            $this->pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Loan deleted successfully'
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->sendError('Failed to delete loan: ' . $e->getMessage());
        }
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

$api = new LoansAPI();
$api->handleRequest();

// api/handlers/price_check.php - Price Monitoring Handler
<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/integrations/MediaAPIManager.php';

class PriceCheckAPI {
    private $pdo;
    private $apiManager;
    
    public function __construct() {
        $this->pdo = getDbConnection();
        $this->apiManager = new MediaAPIManager();
    }
    
    public function handleRequest() {
        requireAuth();
        
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'check_single':
                $this->checkSingleItem();
                break;
            case 'check_wishlist':
                $this->checkWishlistPrices();
                break;
            case 'update_values':
                $this->updateCollectionValues();
                break;
            case 'price_history':
                $this->getPriceHistory();
                break;
            default:
                $this->sendError('Invalid action');
        }
    }
    
    private function checkSingleItem() {
        $itemId = $_GET['item_id'] ?? $_POST['item_id'] ?? 0;
        
        if (!$itemId) {
            $this->sendError('Item ID required');
            return;
        }
        
        // Get item details
        $sql = "SELECT * FROM collection WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $this->sendError('Item not found');
            return;
        }
        
        $prices = $this->checkItemPrices($item);
        
        echo json_encode([
            'success' => true,
            'item' => $item,
            'prices' => $prices
        ]);
    }
    
    private function checkWishlistPrices() {
        $wishlistId = $_GET['wishlist_id'] ?? 0;
        
        if ($wishlistId) {
            // Check single wishlist item
            $sql = "SELECT * FROM wishlist WHERE id = ? AND date_acquired IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$wishlistId]);
            $items = [$stmt->fetch()];
        } else {
            // Check all active wishlist items with price alerts
            $sql = "SELECT * FROM wishlist 
                    WHERE date_acquired IS NULL AND price_alert_enabled = 1
                    ORDER BY priority, date_added";
            $stmt = $this->pdo->query($sql);
            $items = $stmt->fetchAll();
        }
        
        $results = [];
        
        foreach ($items as $item) {
            if (!$item) continue;
            
            $prices = $this->checkWishlistItemPrices($item);
            $results[] = [
                'item' => $item,
                'prices' => $prices,
                'alerts' => $this->checkPriceAlerts($item, $prices)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
    }
    
    private function checkItemPrices($item) {
        $sources = [];
        
        // Check eBay (simulation - you'd implement actual API calls)
        $sources['ebay'] = $this->simulateEbayPriceCheck($item);
        
        // Check Amazon (simulation)
        $sources['amazon'] = $this->simulateAmazonPriceCheck($item);
        
        // Check specialized sites based on media type
        switch ($item['media_type']) {
            case 'comic':
                $sources['gocollect'] = $this->simulateComicPriceCheck($item);
                break;
            case 'music':
                $sources['discogs'] = $this->simulateDiscogsPriceCheck($item);
                break;
        }
        
        return $sources;
    }
    
    private function checkWishlistItemPrices($item) {
        $prices = $this->checkItemPrices($item);
        
        // Store price history
        foreach ($prices as $source => $priceData) {
            if ($priceData && $priceData['price'] > 0) {
                $this->storePriceHistory($item['id'], $source, $priceData);
            }
        }
        
        return $prices;
    }
    
    private function storePriceHistory($wishlistId, $source, $priceData) {
        $sql = "INSERT INTO price_history (
            wishlist_id, price, source, source_url, 
            condition_offered, availability_status, checked_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $wishlistId,
            $priceData['price'],
            $source,
            $priceData['url'] ?? null,
            $priceData['condition'] ?? null,
            $priceData['availability'] ?? 'available'
        ]);
        
        // Update best found price in wishlist
        $updateSql = "UPDATE wishlist SET 
                      best_found_price = ?, 
                      best_price_url = ?,
                      last_price_check = NOW()
                      WHERE id = ? AND (best_found_price IS NULL OR best_found_price > ?)";
        
        $updateStmt = $this->pdo->prepare($updateSql);
        $updateStmt->execute([
            $priceData['price'],
            $priceData['url'] ?? null,
            $wishlistId,
            $priceData['price']
        ]);
    }
    
    private function checkPriceAlerts($item, $prices) {
        $alerts = [];
        
        foreach ($prices as $source => $priceData) {
            if (!$priceData || $priceData['price'] <= 0) continue;
            
            // Check if price is below target
            if ($item['target_price'] && $priceData['price'] <= $item['target_price']) {
                $alerts[] = [
                    'type' => 'target_reached',
                    'message' => "Target price reached on {$source}: $" . $priceData['price'],
                    'source' => $source,
                    'price' => $priceData['price'],
                    'url' => $priceData['url'] ?? null
                ];
            }
            
            // Check if price is below max price
            if ($item['max_price'] && $priceData['price'] <= $item['max_price']) {
                $alerts[] = [
                    'type' => 'good_deal',
                    'message' => "Good deal found on {$source}: $" . $priceData['price'],
                    'source' => $source,
                    'price' => $priceData['price'],
                    'url' => $priceData['url'] ?? null
                ];
            }
        }
        
        return $alerts;
    }
    
    // Simulation methods (replace with actual API implementations)
    private function simulateEbayPriceCheck($item) {
        // Simulate eBay price check
        return [
            'price' => rand(10, 100),
            'condition' => 'used',
            'availability' => 'available',
            'url' => 'https://ebay.com/...',
            'currency' => 'USD'
        ];
    }
    
    private function simulateAmazonPriceCheck($item) {
        // Simulate Amazon price check
        return [
            'price' => rand(15, 80),
            'condition' => 'new',
            'availability' => 'available',
            'url' => 'https://amazon.com/...',
            'currency' => 'USD'
        ];
    }
    
    private function simulateComicPriceCheck($item) {
        // Simulate comic-specific price check
        return [
            'price' => rand(5, 200),
            'condition' => 'near_mint',
            'availability' => 'available',
            'url' => 'https://gocollect.com/...',
            'currency' => 'USD'
        ];
    }
    
    private function simulateDiscogsPriceCheck($item) {
        // Simulate Discogs price check
        return [
            'price' => rand(8, 150),
            'condition' => 'very_good',
            'availability' => 'available',
            'url' => 'https://discogs.com/...',
            'currency' => 'USD'
        ];
    }
    
    private function updateCollectionValues() {
        // Bulk update collection values based on current market prices
        $sql = "SELECT id, title, media_type, identifier FROM collection 
                WHERE status = 'owned' 
                ORDER BY last_value_update ASC NULLS FIRST 
                LIMIT 50";
        
        $stmt = $this->pdo->query($sql);
        $items = $stmt->fetchAll();
        
        $updated = 0;
        
        foreach ($items as $item) {
            $prices = $this->checkItemPrices($item);
            
            // Calculate average market price
            $validPrices = array_filter(array_column($prices, 'price'), function($price) {
                return $price > 0;
            });
            
            if (!empty($validPrices)) {
                $avgPrice = array_sum($validPrices) / count($validPrices);
                
                // Update item value
                $updateSql = "UPDATE collection SET 
                              current_value = ?, 
                              last_value_update = NOW() 
                              WHERE id = ?";
                
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([$avgPrice, $item['id']]);
                
                $updated++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'updated' => $updated,
            'message' => "Updated values for {$updated} items"
        ]);
    }
    
    private function getPriceHistory() {
        $wishlistId = $_GET['wishlist_id'] ?? 0;
        
        if (!$wishlistId) {
            $this->sendError('Wishlist ID required');
            return;
        }
        
        $sql = "SELECT * FROM price_history 
                WHERE wishlist_id = ? 
                ORDER BY checked_at DESC 
                LIMIT 50";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$wishlistId]);
        $history = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

$api = new PriceCheckAPI();
$api->handleRequest();
?>