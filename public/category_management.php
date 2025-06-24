<?php
/**
 * Dynamic Category Management System
 * Supports unlimited hierarchical categories, subcategories, and sections
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files (adjust paths as needed)
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

// Check authentication (uncomment in production)
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }

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
    die("Database connection failed: " . $e->getMessage());
}

$success = '';
$error = '';

// Helper function to generate URL-friendly slugs
function generateSlug($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            handleAddCategory();
            break;
        case 'edit_category':
            handleEditCategory();
            break;
        case 'delete_category':
            handleDeleteCategory();
            break;
        case 'bulk_create':
            handleBulkCreate();
            break;
        case 'import_template':
            handleImportTemplate();
            break;
        case 'reorder_categories':
            handleReorderCategories();
            break;
    }
}

function handleAddCategory() {
    global $pdo, $success, $error;
    
    try {
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $level = 1;
        
        // Calculate level based on parent
        if ($parentId) {
            $stmt = $pdo->prepare("SELECT category_level FROM categories WHERE id = ?");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch();
            $level = $parent ? $parent['category_level'] + 1 : 2;
        }
        
        $slug = generateSlug($_POST['name']);
        
        // Check for duplicate slug
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug .= '-' . time();
        }
        
        $sql = "INSERT INTO categories (
            name, slug, parent_id, category_level, media_type, category_type, 
            description, display_order, color_code, icon_class, is_featured
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $slug,
            $parentId,
            $level,
            $_POST['media_type'],
            $_POST['category_type'],
            $_POST['description'] ?: null,
            (int)($_POST['display_order'] ?: 0),
            $_POST['color_code'] ?: null,
            $_POST['icon_class'] ?: null,
            isset($_POST['is_featured']) ? 1 : 0
        ]);
        
        $success = "Category '{$_POST['name']}' added successfully!";
    } catch (Exception $e) {
        $error = "Error adding category: " . $e->getMessage();
    }
}

function handleEditCategory() {
    global $pdo, $success, $error;
    
    try {
        $sql = "UPDATE categories SET 
            name = ?, description = ?, category_type = ?, display_order = ?, 
            color_code = ?, icon_class = ?, is_featured = ?, is_active = ?
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?: null,
            $_POST['category_type'],
            (int)($_POST['display_order'] ?: 0),
            $_POST['color_code'] ?: null,
            $_POST['icon_class'] ?: null,
            isset($_POST['is_featured']) ? 1 : 0,
            isset($_POST['is_active']) ? 1 : 0,
            (int)$_POST['category_id']
        ]);
        
        $success = "Category updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating category: " . $e->getMessage();
    }
}

function handleDeleteCategory() {
    global $pdo, $success, $error;
    
    try {
        $categoryId = (int)$_POST['category_id'];
        
        // Check if category has children
        $stmt = $pdo->prepare("SELECT COUNT(*) as child_count FROM categories WHERE parent_id = ?");
        $stmt->execute([$categoryId]);
        $childCount = $stmt->fetch()['child_count'];
        
        if ($childCount > 0) {
            $error = "Cannot delete category with subcategories. Delete or move subcategories first.";
            return;
        }
        
        // Check if category is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) as usage_count FROM collection_categories WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $usageCount = $stmt->fetch()['usage_count'];
        
        if ($usageCount > 0) {
            $error = "Cannot delete category that is assigned to items. Remove assignments first.";
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        $success = "Category deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting category: " . $e->getMessage();
    }
}

function handleBulkCreate() {
    global $pdo, $success, $error;
    
    try {
        $parentId = !empty($_POST['bulk_parent_id']) ? (int)$_POST['bulk_parent_id'] : null;
        $mediaType = $_POST['bulk_media_type'];
        $categoryType = $_POST['bulk_category_type'];
        $categories = array_filter(array_map('trim', explode("\n", $_POST['bulk_categories'])));
        
        $created = 0;
        $skipped = 0;
        
        foreach ($categories as $index => $categoryName) {
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
                name, slug, parent_id, category_level, media_type, category_type, display_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $categoryName,
                $slug,
                $parentId,
                $level,
                $mediaType,
                $categoryType,
                $index
            ]);
            
            $created++;
        }
        
        $success = "Created {$created} categories successfully. Skipped {$skipped} existing categories.";
    } catch (Exception $e) {
        $error = "Error in bulk creation: " . $e->getMessage();
    }
}

function handleImportTemplate() {
    global $pdo, $success, $error;
    
    try {
        $templateId = (int)$_POST['template_id'];
        $stmt = $pdo->prepare("SELECT * FROM category_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            $error = "Template not found.";
            return;
        }
        
        $data = json_decode($template['category_data'], true);
        $created = importCategoryData($data, $template['media_type']);
        
        $success = "Imported {$created} categories from template '{$template['template_name']}'";
    } catch (Exception $e) {
        $error = "Error importing template: " . $e->getMessage();
    }
}

function importCategoryData($data, $mediaType, $parentId = null, $level = 1) {
    global $pdo;
    $created = 0;
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (isset($value['name'])) {
                // Create the category
                $categoryId = createCategory($value['name'], $mediaType, 'genre', $parentId, $level);
                $created++;
                
                // Create subcategories if they exist
                if (isset($value['subs']) && is_array($value['subs'])) {
                    foreach ($value['subs'] as $subName) {
                        createCategory($subName, $mediaType, 'genre', $categoryId, $level + 1);
                        $created++;
                    }
                }
            } else {
                // Handle different array structures
                foreach ($value as $item) {
                    if (is_string($item)) {
                        createCategory($item, $mediaType, $key, $parentId, $level);
                        $created++;
                    } elseif (is_array($item) && isset($item['name'])) {
                        $categoryId = createCategory($item['name'], $mediaType, $key, $parentId, $level);
                        $created++;
                        
                        if (isset($item['subs'])) {
                            foreach ($item['subs'] as $subName) {
                                createCategory($subName, $mediaType, $key, $categoryId, $level + 1);
                                $created++;
                            }
                        }
                    }
                }
            }
        }
    }
    
    return $created;
}

function createCategory($name, $mediaType, $categoryType, $parentId = null, $level = 1) {
    global $pdo;
    
    $slug = generateSlug($name);
    
    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        return null; // Skip duplicates
    }
    
    $sql = "INSERT INTO categories (name, slug, parent_id, category_level, media_type, category_type) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $slug, $parentId, $level, $mediaType, $categoryType]);
    
    return $pdo->lastInsertId();
}

// Get all categories with hierarchy
function getCategoryHierarchy() {
    global $pdo;
    
    $sql = "SELECT c.*, p.name as parent_name, 
            (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) as child_count,
            COALESCE(cs.usage_count, 0) as usage_count
            FROM categories c
            LEFT JOIN categories p ON c.parent_id = p.id
            LEFT JOIN category_stats cs ON c.id = cs.category_id AND cs.media_type = c.media_type
            WHERE c.is_active = 1
            ORDER BY c.media_type, c.category_level, c.display_order, c.name";
    
    return $pdo->query($sql)->fetchAll();
}

// Get available templates
function getTemplates() {
    global $pdo;
    return $pdo->query("SELECT * FROM category_templates WHERE is_active = 1 ORDER BY template_name")->fetchAll();
}

$categories = getCategoryHierarchy();
$templates = getTemplates();

// Group categories for display
$categoriesByType = [];
foreach ($categories as $category) {
    $type = $category['media_type'];
    $level = $category['category_level'];
    
    if (!isset($categoriesByType[$type])) {
        $categoriesByType[$type] = [];
    }
    if (!isset($categoriesByType[$type][$level])) {
        $categoriesByType[$type][$level] = [];
    }
    
    $categoriesByType[$type][$level][] = $category;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Media Collection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #333;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .control-panel {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            background: #f8f9fa;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
        }
        
        .tab:hover {
            background: #6c757d;
            color: white;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
        }
        
        .form-section h3 {
            margin-bottom: 1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control.textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .category-display {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .media-type-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .media-tab {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .media-tab.active {
            background: white;
            border-bottom: 3px solid #667eea;
        }
        
        .category-content {
            padding: 2rem;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .category-section {
            margin-bottom: 2rem;
        }
        
        .category-section h3 {
            color: #667eea;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .category-tree {
            display: grid;
            gap: 0.75rem;
        }
        
        .category-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.2s ease;
        }
        
        .category-item:hover {
            background: #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .category-item.level-1 {
            border-left-color: #667eea;
            margin-left: 0;
        }
        
        .category-item.level-2 {
            border-left-color: #28a745;
            margin-left: 2rem;
            background: #f0fff4;
        }
        
        .category-item.level-3 {
            border-left-color: #ffc107;
            margin-left: 4rem;
            background: #fffbf0;
        }
        
        .category-item.level-4 {
            border-left-color: #17a2b8;
            margin-left: 6rem;
            background: #f0fdff;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .category-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .category-name i {
            width: 20px;
            text-align: center;
        }
        
        .category-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .category-badge {
            padding: 0.25rem 0.75rem;
            background: #e9ecef;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .category-badge.type-genre { background: #d4edda; color: #155724; }
        .category-badge.type-format { background: #d1ecf1; color: #0c5460; }
        .category-badge.type-theme { background: #ffeaa7; color: #856404; }
        .category-badge.type-collection { background: #f8d7da; color: #721c24; }
        .category-badge.type-series { background: #e2e3e5; color: #383d41; }
        
        .usage-count {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .category-meta {
            color: #666;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }
        
        .category-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .color-picker {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .icon-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-left: 0.5rem;
        }
        
        .bulk-input {
            min-height: 150px;
            font-family: monospace;
        }
        
        .template-selector {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .template-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .template-card:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        
        .template-card.selected {
            border-color: #667eea;
            background: #e7f3ff;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .search-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .media-type-tabs {
                flex-direction: column;
            }
            
            .category-item {
                margin-left: 0 !important;
            }
            
            .category-item.level-2::before {
                content: "└ ";
                color: #28a745;
            }
            
            .category-item.level-3::before {
                content: "  └ ";
                color: #ffc107;
            }
            
            .search-controls {
                flex-direction: column;
            }
            
            .search-input {
                min-width: auto;
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><i class="fas fa-tags"></i> Category Management</h1>
        <p>Organize your media collection with unlimited hierarchical categories</p>
    </header>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php
            $stats = [
                'total' => count($categories),
                'movies' => count(array_filter($categories, fn($c) => $c['media_type'] === 'movie')),
                'books' => count(array_filter($categories, fn($c) => $c['media_type'] === 'book')),
                'comics' => count(array_filter($categories, fn($c) => $c['media_type'] === 'comic')),
                'music' => count(array_filter($categories, fn($c) => $c['media_type'] === 'music')),
                'used' => count(array_filter($categories, fn($c) => $c['usage_count'] > 0))
            ];
            ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['movies'] ?></div>
                <div class="stat-label">Movie Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['books'] ?></div>
                <div class="stat-label">Book Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['music'] ?></div>
                <div class="stat-label">Music Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['comics'] ?></div>
                <div class="stat-label">Comic Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['used'] ?></div>
                <div class="stat-label">In Use</div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <div class="tabs">
                <button class="tab active" onclick="showTab('add')">
                    <i class="fas fa-plus"></i> Add Category
                </button>
                <button class="tab" onclick="showTab('bulk')">
                    <i class="fas fa-list"></i> Bulk Create
                </button>
                <button class="tab" onclick="showTab('templates')">
                    <i class="fas fa-template"></i> Import Templates
                </button>
                <button class="tab" onclick="showTab('manage')">
                    <i class="fas fa-cog"></i> Manage
                </button>
            </div>

            <!-- Add Single Category -->
            <div id="tab-add" class="tab-content">
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="form-section">
                        <h3><i class="fas fa-tag"></i> Basic Information</h3>
                        
                        <div class="form-group">
                            <label for="name">Category Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required placeholder="e.g., Action, Science Fiction">
                        </div>
                        
                        <div class="form-group">
                            <label for="media_type">Media Type *</label>
                            <select id="media_type" name="media_type" class="form-control" required>
                                <option value="">Select Media Type</option>
                                <option value="movie">Movies</option>
                                <option value="book">Books</option>
                                <option value="comic">Comics</option>
                                <option value="music">Music</option>
                                <option value="game">Games</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_type">Category Type *</label>
                            <select id="category_type" name="category_type" class="form-control" required>
                                <option value="genre">Genre</option>
                                <option value="format">Format</option>
                                <option value="theme">Theme</option>
                                <option value="collection">Collection</option>
                                <option value="series">Series</option>
                                <option value="era">Era/Period</option>
                                <option value="location">Location</option>
                                <option value="condition">Condition</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_id">Parent Category</label>
                            <select id="parent_id" name="parent_id" class="form-control">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <?php if ($cat['category_level'] < 3): // Limit nesting to 3 levels ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= str_repeat('&nbsp;&nbsp;', $cat['category_level'] - 1) ?>
                                            <?= htmlspecialchars($cat['name']) ?> 
                                            (<?= ucfirst($cat['media_type']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-palette"></i> Appearance & Settings</h3>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control textarea" 
                                    placeholder="Optional description for this category"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="color_code">Color Code</label>
                            <div style="display: flex; align-items: center;">
                                <input type="color" id="color_code" name="color_code" class="color-picker" value="#667eea">
                                <span style="margin-left: 1rem; color: #666;">Used for visual organization</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="icon_class">Icon Class</label>
                            <div style="display: flex; align-items: center;">
                                <input type="text" id="icon_class" name="icon_class" class="form-control" 
                                       placeholder="e.g., fas fa-film" style="flex: 1;">
                                <div class="icon-preview" id="icon-preview">
                                    <i class="fas fa-tag"></i>
                                </div>
                            </div>
                            <small style="color: #666;">FontAwesome icon class (optional)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_order">Display Order</label>
                            <input type="number" id="display_order" name="display_order" class="form-control" 
                                   placeholder="0" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_featured"> Featured Category
                            </label>
                            <small style="display: block; color: #666;">Featured categories appear prominently in listings</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Category
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Create -->
            <div id="tab-bulk" class="tab-content hidden">
                <form method="post">
                    <input type="hidden" name="action" value="bulk_create">
                    
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-list"></i> Bulk Category Creation</h3>
                            
                            <div class="form-group">
                                <label for="bulk_media_type">Media Type *</label>
                                <select id="bulk_media_type" name="bulk_media_type" class="form-control" required>
                                    <option value="">Select Media Type</option>
                                    <option value="movie">Movies</option>
                                    <option value="book">Books</option>
                                    <option value="comic">Comics</option>
                                    <option value="music">Music</option>
                                    <option value="game">Games</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="bulk_category_type">Category Type *</label>
                                <select id="bulk_category_type" name="bulk_category_type" class="form-control" required>
                                    <option value="genre">Genre</option>
                                    <option value="format">Format</option>
                                    <option value="theme">Theme</option>
                                    <option value="collection">Collection</option>
                                    <option value="series">Series</option>
                                    <option value="era">Era/Period</option>
                                    <option value="location">Location</option>
                                    <option value="condition">Condition</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="bulk_parent_id">Parent Category</label>
                                <select id="bulk_parent_id" name="bulk_parent_id" class="form-control">
                                    <option value="">None (Top Level)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <?php if ($cat['category_level'] < 3): ?>
                                            <option value="<?= $cat['id'] ?>">
                                                <?= str_repeat('&nbsp;&nbsp;', $cat['category_level'] - 1) ?>
                                                <?= htmlspecialchars($cat['name']) ?> 
                                                (<?= ucfirst($cat['media_type']) ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-edit"></i> Categories to Create</h3>
                            
                            <div class="form-group">
                                <label for="bulk_categories">Category Names (one per line) *</label>
                                <textarea id="bulk_categories" name="bulk_categories" class="form-control bulk-input" required
                                        placeholder="Action&#10;Adventure&#10;Comedy&#10;Drama&#10;Horror&#10;Sci-Fi&#10;Fantasy"></textarea>
                                <small style="color: #666;">Enter one category name per line. Empty lines will be ignored.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-magic"></i> Create All Categories
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Templates -->
            <div id="tab-templates" class="tab-content hidden">
                <div class="form-section">
                    <h3><i class="fas fa-download"></i> Import Category Templates</h3>
                    <p>Pre-built category structures for quick setup:</p>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="import_template">
                        <input type="hidden" name="template_id" id="selected_template_id">
                        
                        <div class="template-selector">
                            <?php foreach ($templates as $template): ?>
                                <div class="template-card" onclick="selectTemplate(<?= $template['id'] ?>)">
                                    <h4><?= htmlspecialchars($template['template_name']) ?></h4>
                                    <p><strong>Media Type:</strong> <?= ucfirst($template['media_type']) ?></p>
                                    <p><?= htmlspecialchars($template['description']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary" id="import_template_btn" disabled>
                                <i class="fas fa-download"></i> Import Selected Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Manage -->
            <div id="tab-manage" class="tab-content hidden">
                <div class="form-section">
                    <h3><i class="fas fa-tools"></i> Category Management Tools</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <button class="btn btn-secondary" onclick="exportCategories()">
                            <i class="fas fa-download"></i> Export Categories
                        </button>
                        <button class="btn btn-warning" onclick="cleanupUnused()">
                            <i class="fas fa-broom"></i> Cleanup Unused
                        </button>
                        <button class="btn btn-primary" onclick="reorderCategories()">
                            <i class="fas fa-sort"></i> Reorder Categories
                        </button>
                        <button class="btn btn-success" onclick="validateHierarchy()">
                            <i class="fas fa-check"></i> Validate Hierarchy
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Display -->
        <div class="category-display">
            <div class="media-type-tabs">
                <button class="media-tab active" onclick="showMediaType('all')">All Categories</button>
                <button class="media-tab" onclick="showMediaType('movie')">Movies</button>
                <button class="media-tab" onclick="showMediaType('book')">Books</button>
                <button class="media-tab" onclick="showMediaType('comic')">Comics</button>
                <button class="media-tab" onclick="showMediaType('music')">Music</button>
                <button class="media-tab" onclick="showMediaType('game')">Games</button>
            </div>
            
            <div class="category-content">
                <div class="search-controls">
                    <input type="text" class="form-control search-input" placeholder="Search categories..." 
                           onkeyup="filterCategories(this.value)">
                    <div class="filter-group">
                        <label>Type:</label>
                        <select class="form-control" onchange="filterByType(this.value)">
                            <option value="">All Types</option>
                            <option value="genre">Genre</option>
                            <option value="format">Format</option>
                            <option value="theme">Theme</option>
                            <option value="collection">Collection</option>
                            <option value="series">Series</option>
                            <option value="era">Era</option>
                            <option value="location">Location</option>
                            <option value="condition">Condition</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Usage:</label>
                        <select class="form-control" onchange="filterByUsage(this.value)">
                            <option value="">All</option>
                            <option value="used">Used</option>
                            <option value="unused">Unused</option>
                        </select>
                    </div>
                </div>

                <?php
                $mediaTypes = [
                    'all' => 'All Categories',
                    'movie' => 'Movie Categories',
                    'book' => 'Book Categories', 
                    'comic' => 'Comic Categories',
                    'music' => 'Music Categories',
                    'game' => 'Game Categories'
                ];
                
                foreach ($mediaTypes as $typeKey => $typeName):
                    $displayCategories = $typeKey === 'all' ? $categoriesByType : [$typeKey => $categoriesByType[$typeKey] ?? []];
                ?>
                    <div class="category-section" id="section-<?= $typeKey ?>" <?= $typeKey !== 'all' ? 'style="display: none;"' : '' ?>>
                        
                        <?php if ($typeKey === 'all'): ?>
                            <?php foreach ($categoriesByType as $mediaType => $levelGroups): ?>
                                <div style="margin-bottom: 2rem;">
                                    <h3><?= ucfirst($mediaType) ?> Categories</h3>
                                    <div class="category-tree">
                                        <?php 
                                        for ($level = 1; $level <= 4; $level++):
                                            if (isset($levelGroups[$level])):
                                                foreach ($levelGroups[$level] as $category):
                                        ?>
                                            <div class="category-item level-<?= $category['category_level'] ?>" 
                                                 data-category-id="<?= $category['id'] ?>"
                                                 data-media-type="<?= $category['media_type'] ?>"
                                                 data-category-type="<?= $category['category_type'] ?>"
                                                 data-usage-count="<?= $category['usage_count'] ?>"
                                                 style="<?= $category['color_code'] ? 'border-left-color: ' . $category['color_code'] . ';' : '' ?>">
                                                
                                                <div class="category-header">
                                                    <div>
                                                        <div class="category-name">
                                                            <?php if ($category['icon_class']): ?>
                                                                <i class="<?= htmlspecialchars($category['icon_class']) ?>"></i>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($category['name']) ?>
                                                        </div>
                                                        <div class="category-badges">
                                                            <span class="category-badge type-<?= $category['category_type'] ?>">
                                                                <?= $category['category_type'] ?>
                                                            </span>
                                                            <?php if ($category['is_featured']): ?>
                                                                <span class="category-badge" style="background: #ffd700; color: #333;">
                                                                    <i class="fas fa-star"></i> Featured
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="usage-count"><?= $category['usage_count'] ?></div>
                                                </div>
                                                
                                                <?php if ($category['description']): ?>
                                                    <div class="category-meta"><?= htmlspecialchars($category['description']) ?></div>
                                                <?php endif; ?>
                                                
                                                <div class="category-meta">
                                                    Level <?= $category['category_level'] ?>
                                                    <?php if ($category['parent_name']): ?>
                                                        • Under: <?= htmlspecialchars($category['parent_name']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($category['child_count'] > 0): ?>
                                                        • <?= $category['child_count'] ?> subcategories
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="category-actions">
                                                    <button class="btn btn-primary btn-sm" onclick="editCategory(<?= $category['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="addSubcategory(<?= $category['id'] ?>)">
                                                        <i class="fas fa-plus"></i> Add Sub
                                                    </button>
                                                    <button class="btn btn-warning btn-sm" onclick="viewItems(<?= $category['id'] ?>)">
                                                        <i class="fas fa-eye"></i> View Items
                                                    </button>
                                                    <?php if ($category['usage_count'] == 0 && $category['child_count'] == 0): ?>
                                                        <button class="btn btn-danger btn-sm" onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php 
                                                endforeach;
                                            endif;
                                        endfor;
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php 
                            $currentTypeCategories = $categoriesByType[$typeKey] ?? [];
                            if (empty($currentTypeCategories)):
                            ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h3>No categories found</h3>
                                    <p>Start by creating your first <?= $typeName ?> category above.</p>
                                </div>
                            <?php else: ?>
                                <div class="category-tree">
                                    <?php 
                                    for ($level = 1; $level <= 4; $level++):
                                        if (isset($currentTypeCategories[$level])):
                                            foreach ($currentTypeCategories[$level] as $category):
                                    ?>
                                        <div class="category-item level-<?= $category['category_level'] ?>" 
                                             data-category-id="<?= $category['id'] ?>"
                                             data-media-type="<?= $category['media_type'] ?>"
                                             data-category-type="<?= $category['category_type'] ?>"
                                             data-usage-count="<?= $category['usage_count'] ?>"
                                             style="<?= $category['color_code'] ? 'border-left-color: ' . $category['color_code'] . ';' : '' ?>">
                                            
                                            <div class="category-header">
                                                <div>
                                                    <div class="category-name">
                                                        <?php if ($category['icon_class']): ?>
                                                            <i class="<?= htmlspecialchars($category['icon_class']) ?>"></i>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </div>
                                                    <div class="category-badges">
                                                        <span class="category-badge type-<?= $category['category_type'] ?>">
                                                            <?= $category['category_type'] ?>
                                                        </span>
                                                        <?php if ($category['is_featured']): ?>
                                                            <span class="category-badge" style="background: #ffd700; color: #333;">
                                                                <i class="fas fa-star"></i> Featured
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="usage-count"><?= $category['usage_count'] ?></div>
                                            </div>
                                            
                                            <?php if ($category['description']): ?>
                                                <div class="category-meta"><?= htmlspecialchars($category['description']) ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="category-meta">
                                                Level <?= $category['category_level'] ?>
                                                <?php if ($category['parent_name']): ?>
                                                    • Under: <?= htmlspecialchars($category['parent_name']) ?>
                                                <?php endif; ?>
                                                <?php if ($category['child_count'] > 0): ?>
                                                    • <?= $category['child_count'] ?> subcategories
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="category-actions">
                                                <button class="btn btn-primary btn-sm" onclick="editCategory(<?= $category['id'] ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="addSubcategory(<?= $category['id'] ?>)">
                                                    <i class="fas fa-plus"></i> Add Sub
                                                </button>
                                                <button class="btn btn-warning btn-sm" onclick="viewItems(<?= $category['id'] ?>)">
                                                    <i class="fas fa-eye"></i> View Items
                                                </button>
                                                <?php if ($category['usage_count'] == 0 && $category['child_count'] == 0): ?>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php 
                                            endforeach;
                                        endif;
                                    endfor;
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Category</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form id="editForm" method="post">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label for="edit_name">Category Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_category_type">Category Type</label>
                    <select id="edit_category_type" name="category_type" class="form-control">
                        <option value="genre">Genre</option>
                        <option value="format">Format</option>
                        <option value="theme">Theme</option>
                        <option value="collection">Collection</option>
                        <option value="series">Series</option>
                        <option value="era">Era/Period</option>
                        <option value="location">Location</option>
                        <option value="condition">Condition</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_color_code">Color Code</label>
                    <input type="color" id="edit_color_code" name="color_code" class="color-picker">
                </div>
                
                <div class="form-group">
                    <label for="edit_icon_class">Icon Class</label>
                    <input type="text" id="edit_icon_class" name="icon_class" class="form-control" placeholder="e.g., fas fa-film">
                </div>
                
                <div class="form-group">
                    <label for="edit_display_order">Display Order</label>
                    <input type="number" id="edit_display_order" name="display_order" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_featured" name="is_featured"> Featured Category
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active" checked> Active
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }

        // Media type switching
        function showMediaType(mediaType) {
            // Hide all sections
            document.querySelectorAll('.category-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all media tabs
            document.querySelectorAll('.media-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById('section-' + mediaType).style.display = 'block';
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }

        // Icon preview
        document.getElementById('icon_class').addEventListener('input', function() {
            const iconPreview = document.getElementById('icon-preview');
            const iconClass = this.value.trim();
            
            if (iconClass) {
                iconPreview.innerHTML = `<i class="${iconClass}"></i>`;
            } else {
                iconPreview.innerHTML = '<i class="fas fa-tag"></i>';
            }
        });

        // Template selection
        function selectTemplate(templateId) {
            // Remove selected class from all templates
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked template
            event.target.classList.add('selected');
            
            // Set hidden input value
            document.getElementById('selected_template_id').value = templateId;
            
            // Enable import button
            document.getElementById('import_template_btn').disabled = false;
        }

        // Category management functions
        function editCategory(categoryId) {
            // Fetch category data and populate modal
            fetch(`api/categories.php?action=get&id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const category = data.category;
                        document.getElementById('edit_category_id').value = category.id;
                        document.getElementById('edit_name').value = category.name;
                        document.getElementById('edit_description').value = category.description || '';
                        document.getElementById('edit_category_type').value = category.category_type;
                        document.getElementById('edit_color_code').value = category.color_code || '#667eea';
                        document.getElementById('edit_icon_class').value = category.icon_class || '';
                        document.getElementById('edit_display_order').value = category.display_order || 0;
                        document.getElementById('edit_is_featured').checked = category.is_featured == 1;
                        document.getElementById('edit_is_active').checked = category.is_active == 1;
                        
                        document.getElementById('editModal').style.display = 'block';
                    }
                })
                .catch(error => {
                    // Fallback: open modal with current data from the page
                    const categoryItem = document.querySelector(`[data-category-id="${categoryId}"]`);
                    if (categoryItem) {
                        document.getElementById('edit_category_id').value = categoryId;
                        document.getElementById('editModal').style.display = 'block';
                    }
                });
        }

        function addSubcategory(parentId) {
            // Set parent category and switch to add tab
            document.getElementById('parent_id').value = parentId;
            showTab('add');
            document.getElementById('name').focus();
        }

        function deleteCategory(categoryId, categoryName) {
            if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewItems(categoryId) {
            window.open(`../public/index.php?category=${categoryId}`, '_blank');
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Search and filter functions
        function filterCategories(searchTerm) {
            const categoryItems = document.querySelectorAll('.category-item');
            const term = searchTerm.toLowerCase();
            
            categoryItems.forEach(item => {
                const categoryName = item.querySelector('.category-name').textContent.toLowerCase();
                const description = item.querySelector('.category-meta')?.textContent.toLowerCase() || '';
                
                if (categoryName.includes(term) || description.includes(term)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function filterByType(categoryType) {
            const categoryItems = document.querySelectorAll('.category-item');
            
            categoryItems.forEach(item => {
                if (!categoryType || item.dataset.categoryType === categoryType) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function filterByUsage(usageFilter) {
            const categoryItems = document.querySelectorAll('.category-item');
            
            categoryItems.forEach(item => {
                const usageCount = parseInt(item.dataset.usageCount);
                
                if (!usageFilter || 
                    (usageFilter === 'used' && usageCount > 0) ||
                    (usageFilter === 'unused' && usageCount === 0)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Management tools
        function exportCategories() {
            window.location.href = 'api/export.php?type=categories&format=csv';
        }

        function cleanupUnused() {
            if (confirm('This will delete all unused categories. Are you sure?')) {
                fetch('api/categories.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=cleanup_unused'
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }

        function reorderCategories() {
            // Implement drag-and-drop reordering
            alert('Drag and drop reordering feature coming soon!');
        }

        function validateHierarchy() {
            fetch('api/categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=validate_hierarchy'
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            });
        }

        // Auto-save display order when changed
        document.addEventListener('change', function(e) {
            if (e.target.name === 'display_order') {
                // Auto-save display order changes
                console.log('Display order changed for category');
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Category Management System initialized');
            
            // Set up any real-time features
            setInterval(function() {
                // Optionally refresh usage counts
            }, 30000);
        });
    </script>
</body>
</html>
                                                