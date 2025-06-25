<?php
// api/index.php - Main API Router
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../config.php';

// Check authentication for most endpoints
$publicEndpoints = ['health', 'search_external'];
$endpoint = $_GET['endpoint'] ?? '';

if (!in_array($endpoint, $publicEndpoints) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Route to appropriate handler
switch ($endpoint) {
    case 'collection':
        require_once 'handlers/collection.php';
        break;
    case 'wishlist':
        require_once 'handlers/wishlist.php';
        break;
    case 'categories':
        require_once 'handlers/categories.php';
        break;
    case 'locations':
        require_once 'handlers/locations.php';
        break;
    case 'search':
        require_once 'handlers/search.php';
        break;
    case 'stats':
        require_once 'handlers/stats.php';
        break;
    case 'import':
        require_once 'handlers/import.php';
        break;
    case 'export':
        require_once 'handlers/export.php';
        break;
    case 'loans':
        require_once 'handlers/loans.php';
        break;
    case 'price_check':
        require_once 'handlers/price_check.php';
        break;
    case 'health':
        echo json_encode(['status' => 'ok', 'timestamp' => time()]);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}

// api/handlers/collection.php
class CollectionAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                if (isset($_GET['id'])) {
                    $this->getItem($_GET['id']);
                } else {
                    $this->getCollection();
                }
                break;
            case 'POST':
                $this->addItem();
                break;
            case 'PUT':
                $this->updateItem();
                break;
            case 'DELETE':
                $this->deleteItem();
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function getCollection() {
        try {
            $filters = $this->sanitizeFilters($_GET);
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = ($page - 1) * $limit;
            
            // Build query
            $whereConditions = ['c.status = "owned"'];
            $params = [];
            $joins = [];
            
            // Add joins for location and category info
            $joins[] = 'LEFT JOIN storage_locations sl ON c.primary_location_id = sl.id';
            
            // Media type filter
            if (!empty($filters['media_type'])) {
                $whereConditions[] = 'c.media_type = :media_type';
                $params['media_type'] = $filters['media_type'];
            }
            
            // Category filter
            if (!empty($filters['category_id'])) {
                $joins[] = 'LEFT JOIN collection_categories cc ON c.id = cc.collection_id';
                $whereConditions[] = 'cc.category_id = :category_id';
                $params['category_id'] = $filters['category_id'];
            }
            
            // Location filter
            if (!empty($filters['location_id'])) {
                $whereConditions[] = 'c.primary_location_id = :location_id';
                $params['location_id'] = $filters['location_id'];
            }
            
            // Year filter
            if (!empty($filters['year'])) {
                $whereConditions[] = 'c.year = :year';
                $params['year'] = $filters['year'];
            }
            
            // Condition filter
            if (!empty($filters['condition'])) {
                $whereConditions[] = 'c.condition_rating = :condition';
                $params['condition'] = $filters['condition'];
            }
            
            // Search filter
            if (!empty($filters['search'])) {
                $whereConditions[] = '(c.title LIKE :search OR c.creator LIKE :search OR c.description LIKE :search)';
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            // Price range filter
            if (!empty($filters['min_price'])) {
                $whereConditions[] = 'c.current_value >= :min_price';
                $params['min_price'] = $filters['min_price'];
            }
            if (!empty($filters['max_price'])) {
                $whereConditions[] = 'c.current_value <= :max_price';
                $params['max_price'] = $filters['max_price'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            $joinClause = implode(' ', array_unique($joins));
            
            // Get total count
            $countSql = "SELECT COUNT(DISTINCT c.id) as total 
                        FROM collection c {$joinClause} 
                        WHERE {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = $countStmt->fetch()['total'];
            
            // Get items with details
            $sql = "SELECT DISTINCT c.*, 
                           sl.name as location_name,
                           sl.location_type,
                           CONCAT(sl.name, 
                             CASE WHEN c.specific_location IS NOT NULL 
                             THEN CONCAT(' - ', c.specific_location) 
                             ELSE '' END
                           ) as full_location
                    FROM collection c {$joinClause}
                    WHERE {$whereClause}
                    ORDER BY c.created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $items = $stmt->fetchAll();
            
            // Add media-specific details and categories for each item
            foreach ($items as &$item) {
                $item['media_details'] = $this->getMediaDetails($item['id'], $item['media_type']);
                $item['categories'] = $this->getItemCategories($item['id']);
            }
            
            // Get statistics
            $stats = $this->getCollectionStats($filters);
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalItems,
                    'pages' => ceil($totalItems / $limit)
                ],
                'filters_applied' => array_keys(array_filter($filters))
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to fetch collection: ' . $e->getMessage());
        }
    }
    
    private function getItem($id) {
        try {
            $sql = "SELECT c.*, 
                           sl.name as location_name,
                           sl.location_type,
                           sl.description as location_description
                    FROM collection c
                    LEFT JOIN storage_locations sl ON c.primary_location_id = sl.id
                    WHERE c.id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $this->sendError('Item not found', 404);
                return;
            }
            
            // Add media-specific details
            $item['media_details'] = $this->getMediaDetails($id, $item['media_type']);
            $item['categories'] = $this->getItemCategories($id);
            $item['loans'] = $this->getItemLoans($id);
            
            echo json_encode([
                'success' => true,
                'item' => $item
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to fetch item: ' . $e->getMessage());
        }
    }
    
    private function addItem() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $this->sendError('Invalid JSON input', 400);
                return;
            }
            
            // Validate required fields
            $required = ['media_type', 'title'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->sendError("Missing required field: {$field}", 400);
                    return;
                }
            }
            
            // Insert main record
            $sql = "INSERT INTO collection (
                media_type, title, year, creator, identifier, 
                poster_url, description, purchase_date, purchase_price, 
                current_value, condition_rating, personal_rating, 
                primary_location_id, specific_location, status, 
                acquisition_method, notes, tags
            ) VALUES (
                :media_type, :title, :year, :creator, :identifier,
                :poster_url, :description, :purchase_date, :purchase_price,
                :current_value, :condition_rating, :personal_rating,
                :primary_location_id, :specific_location, :status,
                :acquisition_method, :notes, :tags
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'media_type' => $input['media_type'],
                'title' => $input['title'],
                'year' => $input['year'] ?? null,
                'creator' => $input['creator'] ?? null,
                'identifier' => $input['identifier'] ?? null,
                'poster_url' => $input['poster_url'] ?? null,
                'description' => $input['description'] ?? null,
                'purchase_date' => $input['purchase_date'] ?? null,
                'purchase_price' => $input['purchase_price'] ?? null,
                'current_value' => $input['current_value'] ?? $input['purchase_price'] ?? null,
                'condition_rating' => $input['condition_rating'] ?? 'very_fine',
                'personal_rating' => $input['personal_rating'] ?? null,
                'primary_location_id' => $input['primary_location_id'] ?? null,
                'specific_location' => $input['specific_location'] ?? null,
                'status' => $input['status'] ?? 'owned',
                'acquisition_method' => $input['acquisition_method'] ?? 'purchased_new',
                'notes' => $input['notes'] ?? null,
                'tags' => $input['tags'] ?? null
            ]);
            
            $collectionId = $this->pdo->lastInsertId();
            
            // Add media-specific details
            if (isset($input['media_details'])) {
                $this->addMediaDetails($collectionId, $input['media_type'], $input['media_details']);
            }
            
            // Add categories
            if (isset($input['categories']) && is_array($input['categories'])) {
                $this->addItemCategories($collectionId, $input['categories']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully',
                'id' => $collectionId
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to add item: ' . $e->getMessage());
        }
    }
    
    private function getMediaDetails($collectionId, $mediaType) {
        $table = $mediaType . '_details';
        $sql = "SELECT * FROM {$table} WHERE collection_id = :id";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $collectionId]);
            return $stmt->fetch() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getItemCategories($collectionId) {
        $sql = "SELECT c.id, c.name, c.slug, c.category_type, c.color_code
                FROM categories c
                JOIN collection_categories cc ON c.id = cc.category_id
                WHERE cc.collection_id = :id AND c.is_active = 1
                ORDER BY c.category_level, c.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $collectionId]);
        return $stmt->fetchAll();
    }
    
    private function getItemLoans($collectionId) {
        $sql = "SELECT * FROM loans 
                WHERE collection_id = :id AND actual_return_date IS NULL
                ORDER BY loan_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $collectionId]);
        return $stmt->fetchAll();
    }
    
    private function getCollectionStats($filters = []) {
        // Basic stats
        $stats = [
            'total_items' => 0,
            'by_media_type' => [],
            'by_condition' => [],
            'total_value' => 0,
            'average_value' => 0,
            'recent_additions' => 0
        ];
        
        // Total items by media type
        $sql = "SELECT media_type, COUNT(*) as count, 
                       COALESCE(SUM(current_value), 0) as total_value,
                       COALESCE(AVG(current_value), 0) as avg_value
                FROM collection 
                WHERE status = 'owned'
                GROUP BY media_type";
        
        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $stats['by_media_type'][$row['media_type']] = [
                'count' => (int)$row['count'],
                'total_value' => (float)$row['total_value'],
                'average_value' => (float)$row['avg_value']
            ];
            $stats['total_items'] += $row['count'];
            $stats['total_value'] += $row['total_value'];
        }
        
        $stats['average_value'] = $stats['total_items'] > 0 ? $stats['total_value'] / $stats['total_items'] : 0;
        
        // Items by condition
        $conditionSql = "SELECT condition_rating, COUNT(*) as count
                        FROM collection 
                        WHERE status = 'owned' AND condition_rating IS NOT NULL
                        GROUP BY condition_rating";
        
        $conditionStmt = $this->pdo->query($conditionSql);
        $conditionResults = $conditionStmt->fetchAll();
        
        foreach ($conditionResults as $row) {
            $stats['by_condition'][$row['condition_rating']] = (int)$row['count'];
        }
        
        // Recent additions (last 30 days)
        $recentSql = "SELECT COUNT(*) as count
                     FROM collection 
                     WHERE status = 'owned' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $recentStmt = $this->pdo->query($recentSql);
        $stats['recent_additions'] = (int)$recentStmt->fetch()['count'];
        
        return $stats;
    }
    
    private function sanitizeFilters($input) {
        $filters = [];
        
        $allowedFilters = [
            'media_type', 'category_id', 'location_id', 'year', 
            'condition', 'search', 'min_price', 'max_price'
        ];
        
        foreach ($allowedFilters as $filter) {
            if (isset($input[$filter]) && $input[$filter] !== '') {
                $filters[$filter] = $input[$filter];
            }
        }
        
        return $filters;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// Handle the request
$api = new CollectionAPI();
$api->handleRequest();

// api/handlers/wishlist.php
class WishlistAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                if (isset($_GET['id'])) {
                    $this->getWishlistItem($_GET['id']);
                } else {
                    $this->getWishlist();
                }
                break;
            case 'POST':
                $this->addWishlistItem();
                break;
            case 'PUT':
                $this->updateWishlistItem();
                break;
            case 'DELETE':
                $this->deleteWishlistItem();
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function getWishlist() {
        try {
            $filters = $this->sanitizeFilters($_GET);
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(intval($_GET['limit'] ?? 20), 50);
            $offset = ($page - 1) * $limit;
            
            // Build query
            $whereConditions = ['w.date_acquired IS NULL'];
            $params = [];
            
            if (!empty($filters['media_type'])) {
                $whereConditions[] = 'w.media_type = :media_type';
                $params['media_type'] = $filters['media_type'];
            }
            
            if (!empty($filters['priority'])) {
                $whereConditions[] = 'w.priority = :priority';
                $params['priority'] = $filters['priority'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = '(w.title LIKE :search OR w.creator LIKE :search)';
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['max_price'])) {
                $whereConditions[] = '(w.max_price <= :max_price OR w.max_price IS NULL)';
                $params['max_price'] = $filters['max_price'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM wishlist w WHERE {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = $countStmt->fetch()['total'];
            
            // Get items
            $orderBy = $this->getOrderBy($_GET['sort'] ?? 'date_added');
            $sql = "SELECT w.*, 
                           GROUP_CONCAT(c.name SEPARATOR ', ') as categories
                    FROM wishlist w
                    LEFT JOIN wishlist_categories wc ON w.id = wc.wishlist_id
                    LEFT JOIN categories c ON wc.category_id = c.id
                    WHERE {$whereClause}
                    GROUP BY w.id
                    ORDER BY {$orderBy}
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $items = $stmt->fetchAll();
            
            // Add price history for each item
            foreach ($items as &$item) {
                $item['price_history'] = $this->getPriceHistory($item['id']);
            }
            
            // Get statistics
            $stats = $this->getWishlistStats();
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalItems,
                    'pages' => ceil($totalItems / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to fetch wishlist: ' . $e->getMessage());
        }
    }
    
    private function getPriceHistory($wishlistId, $limit = 5) {
        $sql = "SELECT * FROM price_history 
                WHERE wishlist_id = :id 
                ORDER BY checked_at DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $wishlistId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    private function getWishlistStats() {
        $stats = [
            'total_items' => 0,
            'by_priority' => [],
            'by_media_type' => [],
            'total_target_value' => 0,
            'price_alerts_active' => 0
        ];
        
        // Basic stats
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN price_alert_enabled = 1 THEN 1 ELSE 0 END) as price_alerts,
                    SUM(target_price) as total_target_value
                FROM wishlist 
                WHERE date_acquired IS NULL";
        
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();
        
        $stats['total_items'] = (int)$result['total_items'];
        $stats['price_alerts_active'] = (int)$result['price_alerts'];
        $stats['total_target_value'] = (float)($result['total_target_value'] ?? 0);
        
        // By priority
        $prioritySql = "SELECT priority, COUNT(*) as count 
                       FROM wishlist 
                       WHERE date_acquired IS NULL 
                       GROUP BY priority";
        
        $priorityStmt = $this->pdo->query($prioritySql);
        $priorityResults = $priorityStmt->fetchAll();
        
        foreach ($priorityResults as $row) {
            $stats['by_priority'][$row['priority']] = (int)$row['count'];
        }
        
        // By media type
        $typeSql = "SELECT media_type, COUNT(*) as count 
                   FROM wishlist 
                   WHERE date_acquired IS NULL 
                   GROUP BY media_type";
        
        $typeStmt = $this->pdo->query($typeSql);
        $typeResults = $typeStmt->fetchAll();
        
        foreach ($typeResults as $row) {
            $stats['by_media_type'][$row['media_type']] = (int)$row['count'];
        }
        
        return $stats;
    }
    
    private function getOrderBy($sort) {
        $sortOptions = [
            'date_added' => 'w.date_added DESC',
            'priority' => 'FIELD(w.priority, "urgent", "high", "medium", "low", "dream_item"), w.date_added DESC',
            'title' => 'w.title ASC',
            'price' => 'w.max_price ASC, w.target_price ASC'
        ];
        
        return $sortOptions[$sort] ?? $sortOptions['date_added'];
    }
    
    private function sanitizeFilters($input) {
        $filters = [];
        
        $allowedFilters = ['media_type', 'priority', 'search', 'max_price'];
        
        foreach ($allowedFilters as $filter) {
            if (isset($input[$filter]) && $input[$filter] !== '') {
                $filters[$filter] = $input[$filter];
            }
        }
        
        return $filters;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// Handle the request
$api = new WishlistAPI();
$api->handleRequest();
?>