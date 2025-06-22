<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            handleAddCategory();
            break;
        case 'update_category':
            handleUpdateCategory();
            break;
        case 'delete_category':
            handleDeleteCategory();
            break;
        case 'bulk_create':
            handleBulkCreate();
            break;
        case 'merge_categories':
            handleMergeCategories();
            break;
    }
}

function generateSlug($name) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
}

function handleAddCategory() {
    global $pdo;
    
    try {
        $slug = generateSlug($_POST['name']);
        
        // Check if slug already exists
        $checkSql = "SELECT id FROM categories WHERE slug = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$slug]);
        
        if ($checkStmt->fetch()) {
            throw new Exception("A category with this name already exists.");
        }
        
        // Determine category level
        $level = $_POST['parent_id'] ? 2 : 1;
        if ($_POST['parent_id']) {
            $parentSql = "SELECT category_level FROM categories WHERE id = ?";
            $parentStmt = $pdo->prepare($parentSql);
            $parentStmt->execute([$_POST['parent_id']]);
            $parent = $parentStmt->fetch();
            $level = $parent ? $parent['category_level'] + 1 : 2;
        }
        
        $sql = "INSERT INTO categories (
            name, slug, parent_id, category_level, media_type, category_type,
            description, display_order, color_code, icon_class
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $slug,
            $_POST['parent_id'] ?: null,
            $level,
            $_POST['media_type'],
            $_POST['category_type'],
            $_POST['description'] ?: null,
            $_POST['display_order'] ?: 0,
            $_POST['color_code'] ?: null,
            $_POST['icon_class'] ?: null
        ]);
        
        $success = "Category '{$_POST['name']}' added successfully!";
    } catch (Exception $e) {
        $error = "Error adding category: " . $e->getMessage();
    }
}

function handleBulkCreate() {
    global $pdo;
    
    try {
        $parentId = $_POST['bulk_parent_id'] ?: null;
        $mediaType = $_POST['bulk_media_type'];
        $categoryType = $_POST['bulk_category_type'];
        $categories = array_filter(array_map('trim', explode("\n", $_POST['bulk_categories'])));
        
        $created = 0;
        $skipped = 0;
        
        foreach ($categories as $categoryName) {
            $slug = generateSlug($categoryName);
            
            // Check if already exists
            $checkSql = "SELECT id FROM categories WHERE slug = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$slug]);
            
            if ($checkStmt->fetch()) {
                $skipped++;
                continue;
            }
            
            $level = $parentId ? 2 : 1;
            if ($parentId) {
                $parentSql = "SELECT category_level FROM categories WHERE id = ?";
                $parentStmt = $pdo->prepare($parentSql);
                $parentStmt->execute([$parentId]);
                $parent = $parentStmt->fetch();
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
                $created
            ]);
            
            $created++;
        }
        
        $success = "Created {$created} categories successfully. Skipped {$skipped} existing categories.";
    } catch (Exception $e) {
        $error = "Error in bulk creation: " . $e->getMessage();
    }
}

// Get category hierarchy
function getCategoryHierarchy($pdo) {
    $sql = "SELECT 
        c.*,
        p.name as parent_name,
        COUNT(cc.collection_id) as usage_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    LEFT JOIN collection_categories cc ON c.id = cc.category_id
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.media_type, c.category_level, c.display_order, c.name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

$categories = getCategoryHierarchy($pdo);

// Group categories by media type and level
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .category-controls {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .media-type-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .media-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #e9ecef;
            color: #495057;
        }
        
        .media-tab.active {
            background: #667eea;
            color: white;
        }
        
        .media-tab:hover {
            transform: translateY(-1px);
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .category-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .category-section h3 {
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .category-item {
            padding: 1rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .category-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        .category-item.level-1 {
            border-left: 4px solid #667eea;
            font-weight: 600;
        }
        
        .category-item.level-2 {
            margin-left: 1rem;
            border-left: 4px solid #28a745;
        }
        
        .category-item.level-3 {
            margin-left: 2rem;
            border-left: 4px solid #ffc107;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .category-name {
            font-size: 1rem;
            color: #333;
        }
        
        .category-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-genre { background: #e3f2fd; color: #1565c0; }
        .type-format { background: #f3e5f5; color: #7b1fa2; }
        .type-publisher { background: #fff3e0; color: #ef6c00; }
        .type-era { background: #e8f5e8; color: #2e7d32; }
        .type-quality { background: #fce4ec; color: #c2185b; }
        .type-custom { background: #f1f8e9; color: #558b2f; }
        
        .usage-count {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .category-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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
            background: white;
            margin: 3% auto;
            padding: 2rem;
            border-radius: 10px;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .color-picker-group {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .color-picker-group input[type="color"] {
            width: 60px;
            height: 40px;
            padding: 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .category-preview {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 2px solid #e1e5e9;
        }
        
        .bulk-input {
            min-height: 150px;
            font-family: monospace;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .category-grid {
                grid-template-columns: 1fr;
            }
            
            .media-type-tabs {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏷️ Category & Subcategory Management</h1>
        <p>Organize your collection with dynamic, hierarchical categories</p>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Total Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($categories, function($c) { return $c['category_level'] == 1; })); ?></div>
                <div class="stat-label">Main Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($categories, function($c) { return $c['category_level'] > 1; })); ?></div>
                <div class="stat-label">Subcategories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($categories, 'usage_count')); ?></div>
                <div class="stat-label">Total Usage</div>
            </div>
        </div>

        <div class="category-controls">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>Category Management</h2>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Category</button>
                    <button class="btn btn-success" onclick="openBulkModal()">📝 Bulk Create</button>
                    <button class="btn btn-warning" onclick="openTemplateModal()">📋 Templates</button>
                    <button class="btn btn-info" onclick="openMergeModal()">🔗 Merge</button>
                    <button class="btn btn-secondary" onclick="exportCategories()">📤 Export</button>
                </div>
            </div>

            <!-- Media Type Tabs -->
            <div class="media-type-tabs">
                <button class="media-tab active" onclick="showMediaType('all')">All Types</button>
                <button class="media-tab" onclick="showMediaType('movie')">🎬 Movies</button>
                <button class="media-tab" onclick="showMediaType('book')">📚 Books</button>
                <button class="media-tab" onclick="showMediaType('comic')">📖 Comics</button>
                <button class="media-tab" onclick="showMediaType('music')">🎵 Music</button>
            </div>
        </div>

        <!-- Category Display -->
        <div class="category-grid">
            <?php 
            $mediaTypes = ['all' => 'All Media', 'movie' => 'Movies', 'book' => 'Books', 'comic' => 'Comics', 'music' => 'Music'];
            
            foreach ($mediaTypes as $typeKey => $typeName):
                $typeCategories = $typeKey === 'all' ? $categoriesByType : [$typeKey => $categoriesByType[$typeKey] ?? []];
            ?>
                <div class="category-section" id="section-<?php echo $typeKey; ?>" <?php echo $typeKey !== 'all' ? 'style="display: none;"' : ''; ?>>
                    <h3><?php echo $typeName; ?></h3>
                    
                    <?php if ($typeKey === 'all'): ?>
                        <?php foreach ($categoriesByType as $mediaType => $levelGroups): ?>
                            <div style="margin-bottom: 2rem;">
                                <h4 style="color: #667eea; margin-bottom: 1rem;">
                                    <?php echo ucfirst($mediaType); ?> Categories
                                </h4>
                                <?php 
                                for ($level = 1; $level <= 3; $level++):
                                    if (isset($levelGroups[$level])):
                                        foreach ($levelGroups[$level] as $category):
                                ?>
                                    <div class="category-item level-<?php echo $category['category_level']; ?>" 
                                         style="<?php echo $category['color_code'] ? 'border-left-color: ' . $category['color_code'] . ';' : ''; ?>">
                                        <div class="category-header">
                                            <div>
                                                <span class="category-name">
                                                    <?php if ($category['icon_class']): ?>
                                                        <i class="<?php echo $category['icon_class']; ?>"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </span>
                                                <span class="category-badge type-<?php echo $category['category_type']; ?>">
                                                    <?php echo $category['category_type']; ?>
                                                </span>
                                            </div>
                                            <span class="usage-count"><?php echo $category['usage_count']; ?></span>
                                        </div>
                                        
                                        <?php if ($category['description']): ?>
                                            <div class="category-meta"><?php echo htmlspecialchars($category['description']); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="category-meta">
                                            Level <?php echo $category['category_level']; ?>
                                            <?php if ($category['parent_name']): ?>
                                                • Under: <?php echo htmlspecialchars($category['parent_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="category-actions">
                                            <button class="btn btn-primary" onclick="editCategory(<?php echo $category['id']; ?>)">✏️ Edit</button>
                                            <button class="btn btn-success" onclick="addSubcategory(<?php echo $category['id']; ?>)">+ Sub</button>
                                            <button class="btn btn-warning" onclick="viewItems(<?php echo $category['id']; ?>)">👁️ Items</button>
                                            <?php if ($category['usage_count'] == 0): ?>
                                                <button class="btn btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">🗑️</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php 
                                        endforeach;
                                    endif;
                                endfor;
                                ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php 
                        $currentTypeCategories = $categoriesByType[$typeKey] ?? [];
                        for ($level = 1; $level <= 3; $level++):
                            if (isset($currentTypeCategories[$level])):
                                foreach ($currentTypeCategories[$level] as $category):
                        ?>
                            <div class="category-item level-<?php echo $category['category_level']; ?>" 
                                 style="<?php echo $category['color_code'] ? 'border-left-color: ' . $category['color_code'] . ';' : ''; ?>">
                                <div class="category-header">
                                    <div>
                                        <span class="category-name">
                                            <?php if ($category['icon_class']): ?>
                                                <i class="<?php echo $category['icon_class']; ?>"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </span>
                                        <span class="category-badge type-<?php echo $category['category_type']; ?>">
                                            <?php echo $category['category_type']; ?>
                                        </span>
                                    </div>
                                    <span class="usage-count"><?php echo $category['usage_count']; ?></span>
                                </div>
                                
                                <?php if ($category['description']): ?>
                                    <div class="category-meta"><?php echo htmlspecialchars($category['description']); ?></div>
                                <?php endif; ?>
                                
                                <div class="category-meta">
                                    Level <?php echo $category['category_level']; ?>
                                    <?php if ($category['parent_name']): ?>
                                        • Under: <?php echo htmlspecialchars($category['parent_name']); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="category-actions">
                                    <button class="btn btn-primary" onclick="editCategory(<?php echo $category['id']; ?>)">✏️ Edit</button>
                                    <button class="btn btn-success" onclick="addSubcategory(<?php echo $category['id']; ?>)">+ Sub</button>
                                    <button class="btn btn-warning" onclick="viewItems(<?php echo $category['id']; ?>)">👁️ Items</button>
                                    <?php if ($category['usage_count'] == 0): ?>
                                        <button class="btn btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                                endforeach;
                            endif;
                        endfor;
                        ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div id="category-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Add Category</h3>
            
            <div class="category-preview" id="category-preview">
                <strong>Preview:</strong> <span id="preview-text">New Category</span>
            </div>
            
            <form id="category-form" method="post">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="category_id" id="category_id">
                
                <div class="form-group">
                    <label for="name">Category Name *</label>
                    <input type="text" name="name" id="name" required placeholder="e.g., Action Movies, Science Fiction" 
                           onkeyup="updatePreview()">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="media_type">Media Type *</label>
                        <select name="media_type" id="media_type" required onchange="updateParentOptions()">
                            <option value="all">All Media Types</option>
                            <option value="movie">Movies Only</option>
                            <option value="book">Books Only</option>
                            <option value="comic">Comics Only</option>
                            <option value="music">Music Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_type">Category Type *</label>
                        <select name="category_type" id="category_type" required>
                            <option value="genre">Genre</option>
                            <option value="format">Format</option>
                            <option value="publisher">Publisher</option>
                            <option value="era">Era/Period</option>
                            <option value="quality">Quality/Condition</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="parent_id">Parent Category (for subcategories)</label>
                    <select name="parent_id" id="parent_id">
                        <option value="">None (Main Category)</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="2" 
                              placeholder="Brief description of this category"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="display_order">Display Order</label>
                        <input type="number" name="display_order" id="display_order" min="0" value="0" 
                               placeholder="0 = first">
                    </div>
                    
                    <div class="form-group">
                        <label for="icon_class">Icon Class (optional)</label>
                        <input type="text" name="icon_class" id="icon_class" 
                               placeholder="e.g., fas fa-film, 📽️">
                    </div>
                </div>
                
                <div class="color-picker-group">
                    <div class="form-group" style="flex: 1;">
                        <label for="color_code">Category Color</label>
                        <input type="text" name="color_code" id="color_code" placeholder="#667eea">
                    </div>
                    <input type="color" id="color_picker" value="#667eea" onchange="updateColorCode()">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Create Modal -->
    <div id="bulk-modal" class="modal">
        <div class="modal-content">
            <h3>Bulk Create Categories</h3>
            
            <form method="post">
                <input type="hidden" name="action" value="bulk_create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bulk_media_type">Media Type</label>
                        <select name="bulk_media_type" id="bulk_media_type" required>
                            <option value="all">All Media Types</option>
                            <option value="movie">Movies</option>
                            <option value="book">Books</option>
                            <option value="comic">Comics</option>
                            <option value="music">Music</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_category_type">Category Type</label>
                        <select name="bulk_category_type" id="bulk_category_type" required>
                            <option value="genre">Genre</option>
                            <option value="format">Format</option>
                            <option value="publisher">Publisher</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bulk_parent_id">Parent Category (optional)</label>
                    <select name="bulk_parent_id" id="bulk_parent_id">
                        <option value="">None (Main Categories)</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bulk_categories">Categories (one per line) *</label>
                    <textarea name="bulk_categories" id="bulk_categories" required class="bulk-input"
                              placeholder="Action&#10;Adventure&#10;Comedy&#10;Drama&#10;Horror&#10;Sci-Fi&#10;Thriller"></textarea>
                    <small style="color: #666;">Enter one category name per line. Duplicates will be skipped.</small>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeBulkModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Categories</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentMediaType = 'all';

        function showMediaType(mediaType) {
            currentMediaType = mediaType;
            
            // Update tab appearance
            document.querySelectorAll('.media-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide sections
            document.querySelectorAll('.category-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById('section-' + mediaType).style.display = 'block';
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Category';
            document.getElementById('category-form').reset();
            document.querySelector('input[name="action"]').value = 'add_category';
            document.getElementById('category-modal').style.display = 'block';
            
            // Set media type if specific tab is active
            if (currentMediaType !== 'all') {
                document.getElementById('media_type').value = currentMediaType;
            }
            
            updateParentOptions();
            updatePreview();
        }

        function addSubcategory(parentId) {
            openAddModal();
            document.getElementById('parent_id').value = parentId;
            document.getElementById('modal-title').textContent = 'Add Subcategory';
        }

        function editCategory(id) {
            document.getElementById('modal-title').textContent = 'Edit Category';
            document.querySelector('input[name="action"]').value = 'update_category';
            document.getElementById('category_id').value = id;
            document.getElementById('category-modal').style.display = 'block';
            
            // Load category data (would typically fetch from API)
            // For now, just show the modal
        }

        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            document.getElementById('category-modal').style.display = 'none';
        }

        function openBulkModal() {
            document.getElementById('bulk-modal').style.display = 'block';
            updateBulkParentOptions();
        }

        function closeBulkModal() {
            document.getElementById('bulk-modal').style.display = 'none';
        }

        function updateParentOptions() {
            const mediaType = document.getElementById('media_type').value;
            const parentSelect = document.getElementById('parent_id');
            
            // This would typically fetch options from the server
            // For now, just clear and add placeholder
            parentSelect.innerHTML = '<option value="">None (Main Category)</option>';
        }

        function updateBulkParentOptions() {
            const mediaType = document.getElementById('bulk_media_type').value;
            const parentSelect = document.getElementById('bulk_parent_id');
            
            parentSelect.innerHTML = '<option value="">None (Main Categories)</option>';
        }

        function updatePreview() {
            const name = document.getElementById('name').value || 'New Category';
            const colorCode = document.getElementById('color_code').value || '#667eea';
            const iconClass = document.getElementById('icon_class').value;
            
            const previewText = document.getElementById('preview-text');
            previewText.textContent = (iconClass ? iconClass + ' ' : '') + name;
            previewText.style.color = colorCode;
        }

        function updateColorCode() {
            const colorPicker = document.getElementById('color_picker');
            const colorCode = document.getElementById('color_code');
            colorCode.value = colorPicker.value;
            updatePreview();
        }

        function viewItems(categoryId) {
            window.open(`../public/index.php?category=${categoryId}`, '_blank');
        }

        function openTemplateModal() {
            if (confirm('Load category templates? This will create standard categories for each media type.')) {
                // Implementation for loading templates
                alert('Loading category templates...');
            }
        }

        function openMergeModal() {
            alert('Merge categories feature coming soon...');
        }

        function exportCategories() {
            window.open('../api/export_categories.php', '_blank');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const categoryModal = document.getElementById('category-modal');
            const bulkModal = document.getElementById('bulk-modal');
            
            if (event.target === categoryModal) {
                closeModal();
            }
            if (event.target === bulkModal) {
                closeBulkModal();
            }
        }

        // Update color code when typing
        document.getElementById('color_code').addEventListener('input', function() {
            const colorPicker = document.getElementById('color_picker');
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                colorPicker.value = this.value;
            }
            updatePreview();
        });
    </script>
</body>
</html>