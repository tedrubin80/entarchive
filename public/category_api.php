<?php
/**
 * Category Management API Handler
 * Provides AJAX endpoints for dynamic category operations
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Include configuration
function safeInclude($file) {
    $paths = [$file, '../' . $file, '../../' . $file];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
}

safeInclude('config.php');

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') . 
        ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'collector'),
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Authentication check (uncomment in production)
// if (!isset($_SESSION['admin_logged_in'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Authentication required']);
//     exit;
// }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;
        case 'POST':
            handlePostRequest($action);
            break;
        case 'PUT':
            handlePutRequest($action);
            break;
        case 'DELETE':
            handleDeleteRequest($action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGetRequest($action) {
    global $pdo;
    
    switch ($action) {
        case 'get':
            getCategoryById();
            break;
        case 'list':
            getCategoryList();
            break;
        case 'hierarchy':
            getCategoryHierarchy();
            break;
        case 'stats':
            getCategoryStats();
            break;
        case 'search':
            searchCategories();
            break;
        case 'unused':
            getUnusedCategories();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($action) {
    global $pdo;
    
    switch ($action) {
        case 'create':
            createCategory();
            break;
        case 'bulk_create':
            bulkCreateCategories();
            break;
        case 'import_template':
            importTemplate();
            break;
        case 'cleanup_unused':
            cleanupUnusedCategories();
            break;
        case 'validate_hierarchy':
            validateHierarchy();
            break;
        case 'reorder':
            reorderCategories();
            break;
        case 'duplicate':
            duplicateCategory();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            updateCategory();
            break;
        case 'move':
            moveCategory();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            deleteCategory();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

// GET endpoints
function getCategoryById() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        throw new Exception('Category ID required');
    }
    
    $stmt = $pdo->prepare("
        SELECT c.*, p.name as parent_name,
               (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) as child_count,
               COALESCE(cs.usage_count, 0) as usage_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'category' => $category]);
}

function getCategoryList() {
    global $pdo;
    
    $mediaType = $_GET['media_type'] ?? '';
    $parentId = $_GET['parent_id'] ?? '';
    $level = $_GET['level'] ?? '';
    $active = $_GET['active'] ?? '1';
    
    $where = ['c.is_active = ?'];
    $params = [$active];
    
    if ($mediaType) {
        $where[] = 'c.media_type = ?';
        $params[] = $mediaType;
    }
    
    if ($parentId !== '') {
        $where[] = 'c.parent_id ' . ($parentId ? '= ?' : 'IS NULL');
        if ($parentId) $params[] = $parentId;
    }
    
    if ($level) {
        $where[] = 'c.category_level = ?';
        $params[] = $level;
    }
    
    $sql = "
        SELECT c.*, p.name as parent_name,
               (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) as child_count,
               COALESCE(cs.usage_count, 0) as usage_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.media_type, c.category_level, c.display_order, c.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function getCategoryHierarchy() {
    global $pdo;
    
    $mediaType = $_GET['media_type'] ?? '';
    
    $where = 'c.is_active = 1';
    $params = [];
    
    if ($mediaType) {
        $where .= ' AND c.media_type = ?';
        $params[] = $mediaType;
    }
    
    $sql = "
        SELECT c.*, p.name as parent_name,
               (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) as child_count,
               COALESCE(cs.usage_count, 0) as usage_count,
               c.category_path
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE {$where}
        ORDER BY c.media_type, c.category_level, c.display_order, c.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    // Build tree structure
    $tree = buildCategoryTree($categories);
    
    echo json_encode(['success' => true, 'hierarchy' => $tree]);
}

function buildCategoryTree($categories) {
    $tree = [];
    $indexed = [];
    
    // Index categories by ID
    foreach ($categories as $category) {
        $category['children'] = [];
        $indexed[$category['id']] = $category;
    }
    
    // Build tree
    foreach ($indexed as &$category) {
        if ($category['parent_id']) {
            if (isset($indexed[$category['parent_id']])) {
                $indexed[$category['parent_id']]['children'][] = &$category;
            }
        } else {
            $tree[] = &$category;
        }
    }
    
    return $tree;
}

function getCategoryStats() {
    global $pdo;
    
    $sql = "
        SELECT 
            c.media_type,
            COUNT(*) as total_categories,
            COUNT(CASE WHEN c.category_level = 1 THEN 1 END) as top_level,
            COUNT(CASE WHEN c.category_level = 2 THEN 1 END) as second_level,
            COUNT(CASE WHEN c.category_level = 3 THEN 1 END) as third_level,
            COUNT(CASE WHEN c.category_level >= 4 THEN 1 END) as deep_level,
            COUNT(CASE WHEN cs.usage_count > 0 THEN 1 END) as used_categories,
            COUNT(CASE WHEN cs.usage_count IS NULL OR cs.usage_count = 0 THEN 1 END) as unused_categories,
            COUNT(CASE WHEN c.is_featured = 1 THEN 1 END) as featured_categories,
            COALESCE(SUM(cs.usage_count), 0) as total_assignments
        FROM categories c
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE c.is_active = 1
        GROUP BY c.media_type
        ORDER BY c.media_type
    ";
    
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetchAll();
    
    // Get overall stats
    $overallSql = "
        SELECT 
            COUNT(*) as total_categories,
            COUNT(CASE WHEN cs.usage_count > 0 THEN 1 END) as used_categories,
            COUNT(CASE WHEN cs.usage_count IS NULL OR cs.usage_count = 0 THEN 1 END) as unused_categories,
            COALESCE(SUM(cs.usage_count), 0) as total_assignments,
            COUNT(DISTINCT c.media_type) as media_types
        FROM categories c
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE c.is_active = 1
    ";
    
    $overallStmt = $pdo->query($overallSql);
    $overall = $overallStmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats, 
        'overall' => $overall
    ]);
}

function searchCategories() {
    global $pdo;
    
    $query = $_GET['query'] ?? '';
    $mediaType = $_GET['media_type'] ?? '';
    $categoryType = $_GET['category_type'] ?? '';
    $limit = min(50, $_GET['limit'] ?? 20);
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'categories' => []]);
        return;
    }
    
    $where = ['c.is_active = 1'];
    $params = [];
    
    // Search in name, description, and path
    $where[] = '(c.name LIKE ? OR c.description LIKE ? OR c.category_path LIKE ?)';
    $searchTerm = '%' . $query . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    
    if ($mediaType) {
        $where[] = 'c.media_type = ?';
        $params[] = $mediaType;
    }
    
    if ($categoryType) {
        $where[] = 'c.category_type = ?';
        $params[] = $categoryType;
    }
    
    $sql = "
        SELECT c.*, p.name as parent_name,
               COALESCE(cs.usage_count, 0) as usage_count,
               MATCH(c.name, c.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE " . implode(' AND ', $where) . "
        ORDER BY relevance DESC, cs.usage_count DESC, c.name
        LIMIT ?
    ";
    
    $params[] = $query; // For MATCH AGAINST
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function getUnusedCategories() {
    global $pdo;
    
    $sql = "
        SELECT c.*, p.name as parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE c.is_active = 1 
        AND (cs.usage_count IS NULL OR cs.usage_count = 0)
        AND NOT EXISTS (SELECT 1 FROM categories child WHERE child.parent_id = c.id)
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

// POST endpoints
function createCategory() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $required = ['name', 'media_type', 'category_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }
    
    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    $level = 1;
    
    // Calculate level based on parent
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT category_level FROM categories WHERE id = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch();
        $level = $parent ? $parent['category_level'] + 1 : 2;
        
        if ($level > 5) {
            throw new Exception("Maximum nesting level (5) exceeded");
        }
    }
    
    $slug = generateSlug($data['name']);
    
    // Check for duplicate slug
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }
    
    $sql = "INSERT INTO categories (
        name, slug, parent_id, category_level, media_type, category_type, 
        description, display_order, color_code, icon_class, is_featured, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['name'],
        $slug,
        $parentId,
        $level,
        $data['media_type'],
        $data['category_type'],
        $data['description'] ?? null,
        (int)($data['display_order'] ?? 0),
        $data['color_code'] ?? null,
        $data['icon_class'] ?? null,
        !empty($data['is_featured']) ? 1 : 0,
        $_SESSION['user_id'] ?? 1
    ]);
    
    $categoryId = $pdo->lastInsertId();
    
    // Get the created category
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'message' => "Category '{$data['name']}' created successfully",
        'category' => $category
    ]);
}

function bulkCreateCategories() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $required = ['categories', 'media_type', 'category_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }
    
    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    $categories = is_array($data['categories']) ? $data['categories'] : array_filter(array_map('trim', explode("\n", $data['categories'])));
    
    $created = 0;
    $skipped = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    
    try {
        foreach ($categories as $index => $categoryName) {
            if (empty($categoryName)) continue;
            
            $slug = generateSlug($categoryName);
            
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
            
            $level = $parentId ? 2 : 1;
            if ($parentId) {
                $stmt = $pdo->prepare("SELECT category_level FROM categories WHERE id = ?");
                $stmt->execute([$parentId]);
                $parent = $stmt->fetch();
                $level = $parent ? $parent['category_level'] + 1 : 2;
            }
            
            $sql = "INSERT INTO categories (
                name, slug, parent_id, category_level, media_type, category_type, display_order, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $categoryName,
                $slug,
                $parentId,
                $level,
                $data['media_type'],
                $data['category_type'],
                $index,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $created++;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Created {$created} categories successfully. Skipped {$skipped} existing categories.",
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importTemplate() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $templateId = $data['template_id'] ?? 0;
    
    if (!$templateId) {
        throw new Exception('Template ID required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM category_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Template not found');
    }
    
    $templateData = json_decode($template['category_data'], true);
    $created = 0;
    
    $pdo->beginTransaction();
    
    try {
        $created = importCategoryData($templateData, $template['media_type']);
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Imported {$created} categories from template '{$template['template_name']}'",
            'created' => $created
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function importCategoryData($data, $mediaType, $parentId = null, $level = 1) {
    global $pdo;
    $created = 0;
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (isset($value['name'])) {
                // Create the category
                $categoryId = createCategoryFromTemplate($value['name'], $mediaType, 'genre', $parentId, $level);
                if ($categoryId) $created++;
                
                // Create subcategories if they exist
                if (isset($value['subs']) && is_array($value['subs'])) {
                    foreach ($value['subs'] as $subName) {
                        if (createCategoryFromTemplate($subName, $mediaType, 'genre', $categoryId, $level + 1)) {
                            $created++;
                        }
                    }
                }
            } else {
                // Handle different array structures
                foreach ($value as $item) {
                    if (is_string($item)) {
                        if (createCategoryFromTemplate($item, $mediaType, $key, $parentId, $level)) {
                            $created++;
                        }
                    } elseif (is_array($item) && isset($item['name'])) {
                        $categoryId = createCategoryFromTemplate($item['name'], $mediaType, $key, $parentId, $level);
                        if ($categoryId) $created++;
                        
                        if (isset($item['subs'])) {
                            foreach ($item['subs'] as $subName) {
                                if (createCategoryFromTemplate($subName, $mediaType, $key, $categoryId, $level + 1)) {
                                    $created++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    return $created;
}

function createCategoryFromTemplate($name, $mediaType, $categoryType, $parentId = null, $level = 1) {
    global $pdo;
    
    $slug = generateSlug($name);
    
    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        return null; // Skip duplicates
    }
    
    $sql = "INSERT INTO categories (name, slug, parent_id, category_level, media_type, category_type, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $slug, $parentId, $level, $mediaType, $categoryType, $_SESSION['user_id'] ?? 1]);
    
    return $pdo->lastInsertId();
}

function cleanupUnusedCategories() {
    global $pdo;
    
    // Get unused categories that have no children
    $sql = "
        SELECT c.id, c.name
        FROM categories c
        LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
        WHERE c.is_active = 1 
        AND (cs.usage_count IS NULL OR cs.usage_count = 0)
        AND NOT EXISTS (SELECT 1 FROM categories child WHERE child.parent_id = c.id)
    ";
    
    $stmt = $pdo->query($sql);
    $unusedCategories = $stmt->fetchAll();
    
    if (empty($unusedCategories)) {
        echo json_encode([
            'success' => true,
            'message' => 'No unused categories found to cleanup',
            'deleted' => 0
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        $deleted = 0;
        foreach ($unusedCategories as $category) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$category['id']]);
            $deleted++;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Deleted {$deleted} unused categories",
            'deleted' => $deleted
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function validateHierarchy() {
    global $pdo;
    
    $issues = [];
    
    // Check for circular references
    $sql = "
        WITH RECURSIVE category_path AS (
            SELECT id, parent_id, name, CAST(id AS CHAR(1000)) as path, 0 as level
            FROM categories WHERE parent_id IS NULL
            UNION ALL
            SELECT c.id, c.parent_id, c.name, 
                   CONCAT(cp.path, '->', c.id) as path, cp.level + 1
            FROM categories c
            INNER JOIN category_path cp ON c.parent_id = cp.id
            WHERE cp.level < 10 AND FIND_IN_SET(c.id, REPLACE(cp.path, '->', ',')) = 0
        )
        SELECT * FROM category_path WHERE level > 5
    ";
    
    try {
        $stmt = $pdo->query($sql);
        $deepCategories = $stmt->fetchAll();
        
        if (!empty($deepCategories)) {
            $issues[] = count($deepCategories) . " categories exceed maximum nesting level";
        }
    } catch (Exception $e) {
        // MySQL version might not support CTEs
        $issues[] = "Could not check for deep nesting (requires MySQL 8.0+)";
    }
    
    // Check for orphaned categories
    $sql = "
        SELECT COUNT(*) as orphaned_count
        FROM categories c
        WHERE c.parent_id IS NOT NULL 
        AND NOT EXISTS (SELECT 1 FROM categories p WHERE p.id = c.parent_id)
    ";
    
    $stmt = $pdo->query($sql);
    $orphanedCount = $stmt->fetch()['orphaned_count'];
    
    if ($orphanedCount > 0) {
        $issues[] = "{$orphanedCount} categories have invalid parent references";
    }
    
    // Check for incorrect levels
    $sql = "
        SELECT COUNT(*) as incorrect_level_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE (c.parent_id IS NULL AND c.category_level != 1)
        OR (c.parent_id IS NOT NULL AND c.category_level != p.category_level + 1)
    ";
    
    $stmt = $pdo->query($sql);
    $incorrectLevelCount = $stmt->fetch()['incorrect_level_count'];
    
    if ($incorrectLevelCount > 0) {
        $issues[] = "{$incorrectLevelCount} categories have incorrect level values";
    }
    
    if (empty($issues)) {
        echo json_encode([
            'success' => true,
            'message' => 'Category hierarchy validation passed - no issues found'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Category hierarchy issues found: ' . implode(', ', $issues),
            'issues' => $issues
        ]);
    }
}

function reorderCategories() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $orders = $data['orders'] ?? [];
    
    if (empty($orders)) {
        throw new Exception('No reorder data provided');
    }
    
    $pdo->beginTransaction();
    
    try {
        foreach ($orders as $order) {
            $stmt = $pdo->prepare("UPDATE categories SET display_order = ? WHERE id = ?");
            $stmt->execute([$order['display_order'], $order['id']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Categories reordered successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicateCategory() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $sourceId = $data['source_id'] ?? 0;
    $newName = $data['new_name'] ?? '';
    $includeChildren = !empty($data['include_children']);
    
    if (!$sourceId) {
        throw new Exception('Source category ID required');
    }
    
    // Get source category
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$sourceId]);
    $source = $stmt->fetch();
    
    if (!$source) {
        throw new Exception('Source category not found');
    }
    
    if (!$newName) {
        $newName = $source['name'] . ' (Copy)';
    }
    
    $pdo->beginTransaction();
    
    try {
        $newId = duplicateCategoryRecursive($source, $newName, $includeChildren);
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Category duplicated successfully",
            'new_id' => $newId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicateCategoryRecursive($category, $newName, $includeChildren, $newParentId = null) {
    global $pdo;
    
    $slug = generateSlug($newName);
    
    // Ensure unique slug
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }
    
    // Create new category
    $sql = "INSERT INTO categories (
        name, slug, parent_id, category_level, media_type, category_type, 
        description, display_order, color_code, icon_class, is_featured, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $newName,
        $slug,
        $newParentId ?? $category['parent_id'],
        $category['category_level'],
        $category['media_type'],
        $category['category_type'],
        $category['description'],
        $category['display_order'],
        $category['color_code'],
        $category['icon_class'],
        $category['is_featured'],
        $_SESSION['user_id'] ?? 1
    ]);
    
    $newId = $pdo->lastInsertId();
    
    // Duplicate children if requested
    if ($includeChildren) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY display_order");
        $stmt->execute([$category['id']]);
        $children = $stmt->fetchAll();
        
        foreach ($children as $child) {
            duplicateCategoryRecursive($child, $child['name'], true, $newId);
        }
    }
    
    return $newId;
}

// PUT endpoints
function updateCategory() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Category ID required');
    }
    
    $allowedFields = [
        'name', 'description', 'category_type', 'display_order', 
        'color_code', 'icon_class', 'is_featured', 'is_active'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "{$field} = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }
    
    $params[] = $id;
    
    $sql = "UPDATE categories SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Category updated successfully'
    ]);
}

function moveCategory() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $newParentId = $data['new_parent_id'] ?? null;
    
    if (!$id) {
        throw new Exception('Category ID required');
    }
    
    // Validate that we're not creating a circular reference
    if ($newParentId) {
        $stmt = $pdo->prepare("
            WITH RECURSIVE parent_chain AS (
                SELECT id, parent_id FROM categories WHERE id = ?
                UNION ALL
                SELECT c.id, c.parent_id FROM categories c
                INNER JOIN parent_chain pc ON c.id = pc.parent_id
            )
            SELECT COUNT(*) as count FROM parent_chain WHERE id = ?
        ");
        
        try {
            $stmt->execute([$newParentId, $id]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                throw new Exception('Cannot move category: would create circular reference');
            }
        } catch (Exception $e) {
            // Fall back to simple check for older MySQL versions
            $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
            $stmt->execute([$newParentId]);
            $parent = $stmt->fetch();
            if ($parent && $parent['parent_id'] == $id) {
                throw new Exception('Cannot move category: would create circular reference');
            }
        }
    }
    
    // Calculate new level
    $newLevel = 1;
    if ($newParentId) {
        $stmt = $pdo->prepare("SELECT category_level FROM categories WHERE id = ?");
        $stmt->execute([$newParentId]);
        $parent = $stmt->fetch();
        $newLevel = $parent ? $parent['category_level'] + 1 : 2;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Update the category
        $stmt = $pdo->prepare("UPDATE categories SET parent_id = ?, category_level = ? WHERE id = ?");
        $stmt->execute([$newParentId, $newLevel, $id]);
        
        // Update all descendant levels
        updateDescendantLevels($id);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Category moved successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateDescendantLevels($parentId) {
    global $pdo;
    
    // Get parent level
    $stmt = $pdo->prepare("SELECT category_level FROM categories WHERE id = ?");
    $stmt->execute([$parentId]);
    $parentLevel = $stmt->fetch()['category_level'];
    
    // Update children
    $stmt = $pdo->prepare("UPDATE categories SET category_level = ? WHERE parent_id = ?");
    $stmt->execute([$parentLevel + 1, $parentId]);
    
    // Recursively update grandchildren
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$parentId]);
    $children = $stmt->fetchAll();
    
    foreach ($children as $child) {
        updateDescendantLevels($child['id']);
    }
}

// DELETE endpoints
function deleteCategory() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    $force = !empty($_GET['force']);
    
    if (!$id) {
        throw new Exception('Category ID required');
    }
    
    // Check if category has children
    $stmt = $pdo->prepare("SELECT COUNT(*) as child_count FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    $childCount = $stmt->fetch()['child_count'];
    
    if ($childCount > 0 && !$force) {
        throw new Exception('Cannot delete category with subcategories. Use force=1 to delete recursively or move subcategories first.');
    }
    
    // Check if category is in use
    $stmt = $pdo->prepare("SELECT COUNT(*) as usage_count FROM collection_categories WHERE category_id = ?");
    $stmt->execute([$id]);
    $usageCount = $stmt->fetch()['usage_count'];
    
    if ($usageCount > 0 && !$force) {
        throw new Exception('Cannot delete category that is assigned to items. Use force=1 to delete anyway or remove assignments first.');
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($force && $childCount > 0) {
            // Recursively delete children
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
            $stmt->execute([$id]);
            $children = $stmt->fetchAll();
            
            foreach ($children as $child) {
                deleteCategoryRecursive($child['id']);
            }
        }
        
        // Delete category assignments if forcing
        if ($force && $usageCount > 0) {
            $stmt = $pdo->prepare("DELETE FROM collection_categories WHERE category_id = ?");
            $stmt->execute([$id]);
        }
        
        // Delete the category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteCategoryRecursive($id) {
    global $pdo;
    
    // Delete children first
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    $children = $stmt->fetchAll();
    
    foreach ($children as $child) {
        deleteCategoryRecursive($child['id']);
    }
    
    // Delete assignments
    $stmt = $pdo->prepare("DELETE FROM collection_categories WHERE category_id = ?");
    $stmt->execute([$id]);
    
    // Delete the category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
}

// Utility functions
function generateSlug($text) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    return substr($slug, 0, 100); // Limit length
}
?>