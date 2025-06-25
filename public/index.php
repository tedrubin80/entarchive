<?php
// public/index.php - Collection Display (No Geolocation)
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Check if user is logged in
// Admin check temporarily disabled for testing
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: ../admin/login.php");
//     exit();
// }

// Initialize database connection
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    die("Database connection failed. Please check your configuration.");
}

// Get filters from query string
$mediaType = $_GET['type'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$categoryId = $_GET['category'] ?? '';
$locationId = $_GET['location'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = 20;
$offset = ($page - 1) * $itemsPerPage;

// Build query
$whereConditions = ['1=1'];
$params = [];

if ($mediaType) {
    $whereConditions[] = 'c.media_type = :media_type';
    $params['media_type'] = $mediaType;
}

if ($searchQuery) {
    $whereConditions[] = '(c.title LIKE :search OR c.creator LIKE :search OR c.description LIKE :search)';
    $params['search'] = '%' . $searchQuery . '%';
}

if ($categoryId) {
    $whereConditions[] = 'EXISTS (SELECT 1 FROM collection_categories cc WHERE cc.collection_id = c.id AND cc.category_id = :category_id)';
    $params['category_id'] = $categoryId;
}

if ($locationId) {
    $whereConditions[] = 'c.primary_location_id = :location_id';
    $params['location_id'] = $locationId;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countSql = "SELECT COUNT(*) as total FROM collection c WHERE {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Get collection items
$sql = "SELECT c.*, 
        sl.name as location_name,
        GROUP_CONCAT(cat.name SEPARATOR ', ') as categories
        FROM collection c
        LEFT JOIN storage_locations sl ON c.primary_location_id = sl.id
        LEFT JOIN collection_categories cc ON c.id = cc.collection_id
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE {$whereClause}
        GROUP BY c.id
        ORDER BY c.{$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// Get statistics
$statsSql = "SELECT 
    COUNT(CASE WHEN media_type = 'movie' THEN 1 END) as movies,
    COUNT(CASE WHEN media_type = 'book' THEN 1 END) as books,
    COUNT(CASE WHEN media_type = 'comic' THEN 1 END) as comics,
    COUNT(CASE WHEN media_type = 'music' THEN 1 END) as music,
    COUNT(*) as total_items,
    COALESCE(SUM(current_value), 0) as total_value
    FROM collection";
$statsStmt = $pdo->query($statsSql);
$stats = $statsStmt->fetch();

// Get categories for filter
$categoriesSql = "SELECT c.*, COUNT(cc.collection_id) as item_count 
                  FROM categories c 
                  LEFT JOIN collection_categories cc ON c.id = cc.category_id 
                  WHERE c.is_active = 1 
                  GROUP BY c.id 
                  ORDER BY c.name";
$categoriesStmt = $pdo->query($categoriesSql);
$categories = $categoriesStmt->fetchAll();

// Get locations for filter
$locationsSql = "SELECT l.*, COUNT(c.id) as item_count 
                 FROM storage_locations l 
                 LEFT JOIN collection c ON l.id = c.primary_location_id 
                 GROUP BY l.id 
                 ORDER BY l.name";
$locationsStmt = $pdo->query($locationsSql);
$locations = $locationsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Media Collection</title>
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
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-search {
            flex: 1;
            max-width: 500px;
        }
        
        .search-form {
            display: flex;
            gap: 0.5rem;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .search-input::placeholder {
            color: rgba(255,255,255,0.8);
        }
        
        .search-input:focus {
            outline: none;
            background: rgba(255,255,255,0.25);
        }
        
        .navbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .content-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 100px;
        }
        
        .filter-section {
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background 0.3s ease;
            text-decoration: none;
            color: #333;
        }
        
        .filter-option:hover {
            background: #f8f9fa;
        }
        
        .filter-option.active {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .filter-count {
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .view-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .view-btn {
            padding: 8px 12px;
            border: 1px solid #e1e5e9;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn:hover,
        .view-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .collection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .item-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .item-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #666;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-content {
            padding: 1rem;
        }
        
        .item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .item-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .item-tags {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .tag {
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .tag.media-type {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-btn {
            padding: 8px 12px;
            border: 1px solid #e1e5e9;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }
        
        .page-btn:hover,
        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
            }
            
            .navbar-search {
                order: 2;
                max-width: none;
                width: 100%;
            }
            
            .collection-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                📚 My Collection
            </div>
            
            <div class="navbar-search">
                <form class="search-form" method="get">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search your collection..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="nav-btn">🔍</button>
                </form>
            </div>
            
            <div class="navbar-actions">
                <a href="add.php" class="nav-btn">+ Add Item</a>
                <a href="../admin/wishlist.php" class="nav-btn">🎯 Wishlist</a>
                <a href="../api/barcode_scanner.php" class="nav-btn">📱 Scan</a>
                <a href="../admin/dashboard.php" class="nav-btn">⚙️ Admin</a>
                <a href="../admin/logout.php" class="nav-btn">🚪 Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['movies']); ?></div>
                <div class="stat-label">🎬 Movies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['books']); ?></div>
                <div class="stat-label">📚 Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['comics']); ?></div>
                <div class="stat-label">📖 Comics</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['music']); ?></div>
                <div class="stat-label">🎵 Music</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_value'], 2); ?></div>
                <div class="stat-label">💰 Total Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_items']); ?></div>
                <div class="stat-label">📦 Total Items</div>
            </div>
        </div>

        <div class="content-layout">
            <!-- Sidebar Filters -->
            <aside class="sidebar">
                <div class="filter-section">
                    <h3 class="filter-title">Media Type</h3>
                    <div class="filter-options">
                        <a href="?" class="filter-option <?php echo !$mediaType ? 'active' : ''; ?>">
                            <span>All Types</span>
                            <span class="filter-count"><?php echo $stats['total_items']; ?></span>
                        </a>
                        <a href="?type=movie" class="filter-option <?php echo $mediaType === 'movie' ? 'active' : ''; ?>">
                            <span>🎬 Movies</span>
                            <span class="filter-count"><?php echo $stats['movies']; ?></span>
                        </a>
                        <a href="?type=book" class="filter-option <?php echo $mediaType === 'book' ? 'active' : ''; ?>">
                            <span>📚 Books</span>
                            <span class="filter-count"><?php echo $stats['books']; ?></span>
                        </a>
                        <a href="?type=comic" class="filter-option <?php echo $mediaType === 'comic' ? 'active' : ''; ?>">
                            <span>📖 Comics</span>
                            <span class="filter-count"><?php echo $stats['comics']; ?></span>
                        </a>
                        <a href="?type=music" class="filter-option <?php echo $mediaType === 'music' ? 'active' : ''; ?>">
                            <span>🎵 Music</span>
                            <span class="filter-count"><?php echo $stats['music']; ?></span>
                        </a>
                    </div>
                </div>

                <?php if (count($categories) > 0): ?>
                <div class="filter-section">
                    <h3 class="filter-title">Categories</h3>
                    <div class="filter-options">
                        <?php foreach (array_slice($categories, 0, 10) as $category): ?>
                            <a href="?category=<?php echo $category['id']; ?>" 
                               class="filter-option <?php echo $categoryId == $category['id'] ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <span class="filter-count"><?php echo $category['item_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($locations) > 0): ?>
                <div class="filter-section">
                    <h3 class="filter-title">Storage Location</h3>
                    <div class="filter-options">
                        <?php foreach (array_slice($locations, 0, 10) as $location): ?>
                            <a href="?location=<?php echo $location['id']; ?>" 
                               class="filter-option <?php echo $locationId == $location['id'] ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($location['name']); ?></span>
                                <span class="filter-count"><?php echo $location['item_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <!-- Main Content Area -->
            <main class="main-content">
                <div class="content-header">
                    <h2>
                        <?php 
                        if ($searchQuery) {
                            echo 'Search Results for "' . htmlspecialchars($searchQuery) . '"';
                        } elseif ($mediaType) {
                            echo ucfirst($mediaType) . ' Collection';
                        } else {
                            echo 'My Collection';
                        }
                        ?>
                        <small style="color: #666; font-size: 0.8em;">
                            (<?php echo number_format($totalItems); ?> items)
                        </small>
                    </h2>
                    
                    <div class="view-controls">
                        <select onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => this.value])); ?>'">
                            <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                            <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title</option>
                            <option value="year" <?php echo $sortBy === 'year' ? 'selected' : ''; ?>>Year</option>
                            <option value="current_value" <?php echo $sortBy === 'current_value' ? 'selected' : ''; ?>>Value</option>
                        </select>
                    </div>
                </div>

                <?php if (count($items) > 0): ?>
                    <div class="collection-grid">
                        <?php foreach ($items as $item): 
                            $mediaIcons = [
                                'movie' => '🎬',
                                'book' => '📚',
                                'comic' => '📖',
                                'music' => '🎵'
                            ];
                        ?>
                            <div class="item-card" onclick="window.location.href='item.php?id=<?php echo $item['id']; ?>'">
                                <div class="item-image">
                                    <?php if ($item['poster_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['poster_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo $mediaIcons[$item['media_type']] ?? '📄'; ?>'">
                                    <?php else: ?>
                                        <?php echo $mediaIcons[$item['media_type']] ?? '📄'; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="item-meta">
                                        <?php if ($item['creator']): ?>
                                            <?php echo htmlspecialchars($item['creator']); ?> • 
                                        <?php endif; ?>
                                        <?php echo $item['year'] ?: 'Unknown Year'; ?>
                                    </div>
                                    <div class="item-tags">
                                        <span class="tag media-type"><?php echo $item['media_type']; ?></span>
                                        <?php if ($item['condition_rating']): ?>
                                            <span class="tag"><?php echo str_replace('_', ' ', $item['condition_rating']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['categories']): ?>
                                        <div class="item-meta" style="margin-top: 0.5rem;">
                                            📁 <?php echo htmlspecialchars($item['categories']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['location_name']): ?>
                                        <div class="item-meta">
                                            📍 <?php echo htmlspecialchars($item['location_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">‹ Previous</a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            if ($start > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">1</a>
                                <?php if ($start > 2): ?><span>...</span><?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?><span>...</span><?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="page-btn"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Next ›</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No items found</h3>
                        <p>
                            <?php if ($searchQuery || $categoryId || $locationId): ?>
                                Try adjusting your filters or search terms
                            <?php else: ?>
                                Start building your collection by adding your first item!
                            <?php endif; ?>
                        </p>
                        <a href="add.php" class="btn" style="margin-top: 1rem;">+ Add Your First Item</a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>