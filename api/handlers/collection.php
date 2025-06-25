<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../config.php';

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

class CollectionAPI {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            $this->sendError('Database connection failed', 500);
        }
    }
    
    public function handleRequest() {
        session_start();
        
        // Check authentication
        if (!isset($_SESSION['admin_logged_in'])) {
            $this->sendError('Unauthorized', 401);
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getCollection();
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
            $page = intval($_GET['page'] ?? 1);
            $limit = min(intval($_GET['limit'] ?? 20), 100); // Max 100 items per page
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause
            $whereConditions = ['1=1'];
            $params = [];
            
            if (!empty($filters['type'])) {
                $whereConditions[] = 'media_type = :type';
                $params['type'] = $filters['type'];
            }
            
            if (!empty($filters['year'])) {
                $whereConditions[] = 'year = :year';
                $params['year'] = $filters['year'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = '(title LIKE :search OR creator LIKE :search)';
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM collection WHERE {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = $countStmt->fetch()['total'];
            
            // Get items with pagination
            $sql = "SELECT * FROM collection WHERE {$whereClause} 
                   ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $items = $stmt->fetchAll();
            
            // Get statistics
            $stats = $this->getStats();
            
            $response = [
                'success' => true,
                'items' => $items,
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalItems,
                    'pages' => ceil($totalItems / $limit)
                ]
            ];
            
            echo json_encode($response);
        } catch (Exception $e) {
            $this->sendError('Failed to fetch collection: ' . $e->getMessage());
        }
    }
    
    private function getStats() {
        try {
            $sql = "SELECT media_type, COUNT(*) as count FROM collection GROUP BY media_type";
            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll();
            
            $stats = ['movies' => 0, 'books' => 0, 'comics' => 0, 'music' => 0];
            
            foreach ($results as $row) {
                if (isset($stats[$row['media_type']])) {
                    $stats[$row['media_type']] = intval($row['count']);
                }
            }
            
            return $stats;
        } catch (Exception $e) {
            return ['movies' => 0, 'books' => 0, 'comics' => 0, 'music' => 0];
        }
    }
    
    private function addItem() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $this->sendError('Invalid JSON input', 400);
            }
            
            $requiredFields = ['media_type', 'title'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    $this->sendError("Missing required field: {$field}", 400);
                }
            }
            
            // Validate media type
            $validTypes = ['movie', 'book', 'comic', 'music'];
            if (!in_array($input['media_type'], $validTypes)) {
                $this->sendError('Invalid media type', 400);
            }
            
            $sql = "INSERT INTO collection (media_type, title, year, creator, identifier, 
                   source_id, poster_url, description) 
                   VALUES (:media_type, :title, :year, :creator, :identifier, 
                   :source_id, :poster_url, :description)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'media_type' => $input['media_type'],
                'title' => trim($input['title']),
                'year' => $input['year'] ?? null,
                'creator' => $input['creator'] ?? null,
                'identifier' => $input['identifier'] ?? null,
                'source_id' => $input['source_id'] ?? null,
                'poster_url' => $input['poster_url'] ?? null,
                'description' => $input['description'] ?? null
            ]);
            
            $newId = $this->pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully',
                'id' => $newId
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to add item: ' . $e->getMessage());
        }
    }
    
    private function updateItem() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($_GET['id'] ?? 0);
            
            if (!$id || !$input) {
                $this->sendError('Invalid input', 400);
            }
            
            // Check if item exists
            $checkSql = "SELECT id FROM collection WHERE id = :id";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute(['id' => $id]);
            
            if (!$checkStmt->fetch()) {
                $this->sendError('Item not found', 404);
            }
            
            $allowedFields = ['title', 'year', 'creator', 'poster_url', 'description'];
            $updateFields = [];
            $params = ['id' => $id];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = :{$field}";
                    $params[$field] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                $this->sendError('No valid fields to update', 400);
            }
            
            $sql = "UPDATE collection SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Item updated successfully'
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to update item: ' . $e->getMessage());
        }
    }
    
    private function deleteItem() {
        try {
            $id = intval($_GET['id'] ?? 0);
            
            if (!$id) {
                $this->sendError('Invalid item ID', 400);
            }
            
            $sql = "DELETE FROM collection WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('Item not found', 404);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Item deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to delete item: ' . $e->getMessage());
        }
    }
    
    private function sanitizeFilters($input) {
        $filters = [];
        
        if (isset($input['type']) && in_array($input['type'], ['movie', 'book', 'comic', 'music'])) {
            $filters['type'] = $input['type'];
        }
        
        if (isset($input['year']) && is_numeric($input['year'])) {
            $filters['year'] = $input['year'];
        }
        
        if (isset($input['search']) && is_string($input['search'])) {
            $filters['search'] = trim($input['search']);
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
?>