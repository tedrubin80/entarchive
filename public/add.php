<?php
// public/add.php - Enhanced Add Item Form with Barcode Integration
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

requireAuth();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::requireValidToken();
        
        $data = [
            'media_type' => sanitizeInput($_POST['media_type']),
            'title' => sanitizeInput($_POST['title']),
            'year' => sanitizeInput($_POST['year'], 'int'),
            'creator' => sanitizeInput($_POST['creator']),
            'identifier' => sanitizeInput($_POST['identifier']),
            'description' => sanitizeInput($_POST['description']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']),
            'purchase_price' => sanitizeInput($_POST['purchase_price'], 'float'),
            'current_value' => sanitizeInput($_POST['current_value'], 'float'),
            'condition_rating' => sanitizeInput($_POST['condition_rating']),
            'personal_rating' => sanitizeInput($_POST['personal_rating'], 'int'),
            'primary_location_id' => sanitizeInput($_POST['primary_location_id'], 'int'),
            'specific_location' => sanitizeInput($_POST['specific_location']),
            'acquisition_method' => sanitizeInput($_POST['acquisition_method']),
            'notes' => sanitizeInput($_POST['notes']),
            'tags' => sanitizeInput($_POST['tags'])
        ];
        
        // Handle media-specific details
        $mediaDetails = [];
        switch ($data['media_type']) {
            case 'movie':
                $mediaDetails = [
                    'format' => sanitizeInput($_POST['movie_format']),
                    'region' => sanitizeInput($_POST['region']),
                    'resolution' => sanitizeInput($_POST['resolution']),
                    'director' => sanitizeInput($_POST['director']),
                    'studio' => sanitizeInput($_POST['studio']),
                    'runtime_minutes' => sanitizeInput($_POST['runtime_minutes'], 'int'),
                    'mpaa_rating' => sanitizeInput($_POST['mpaa_rating']),
                    'media_type_detail' => sanitizeInput($_POST['media_type_detail'])
                ];
                break;
            case 'book':
                $mediaDetails = [
                    'format' => sanitizeInput($_POST['book_format']),
                    'isbn' => sanitizeInput($_POST['isbn']),
                    'isbn13' => sanitizeInput($_POST['isbn13']),
                    'genre' => sanitizeInput($_POST['genre']),
                    'publisher' => sanitizeInput($_POST['publisher']),
                    'page_count' => sanitizeInput($_POST['page_count'], 'int'),
                    'author' => sanitizeInput($_POST['author']),
                    'series_name' => sanitizeInput($_POST['series_name']),
                    'series_number' => sanitizeInput($_POST['series_number'], 'int')
                ];
                break;
            case 'comic':
                $mediaDetails = [
                    'format' => sanitizeInput($_POST['comic_format']),
                    'publisher' => sanitizeInput($_POST['publisher']),
                    'series_name' => sanitizeInput($_POST['series_name']),
                    'issue_number' => sanitizeInput($_POST['issue_number']),
                    'variant_type' => sanitizeInput($_POST['variant_type']),
                    'writer' => sanitizeInput($_POST['writer']),
                    'penciler' => sanitizeInput($_POST['penciler']),
                    'cover_artist' => sanitizeInput($_POST['cover_artist']),
                    'graded' => isset($_POST['graded']),
                    'grade_score' => sanitizeInput($_POST['grade_score'], 'float')
                ];
                break;
            case 'music':
                $mediaDetails = [
                    'format' => sanitizeInput($_POST['music_format']),
                    'artist' => sanitizeInput($_POST['artist']),
                    'record_label' => sanitizeInput($_POST['record_label']),
                    'catalog_number' => sanitizeInput($_POST['catalog_number']),
                    'genre' => sanitizeInput($_POST['music_genre']),
                    'album_type' => sanitizeInput($_POST['album_type'])
                ];
                break;
        }
        
        // Handle image upload
        $posterUrl = null;
        if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = SecureUpload::saveFile($_FILES['poster_image'], '../uploads/images/posters/');
            if ($uploadResult['success']) {
                $posterUrl = '/uploads/images/posters/' . $uploadResult['filename'];
                
                // Generate thumbnail
                generateThumbnail(
                    $uploadResult['path'],
                    '../uploads/images/thumbnails/' . $uploadResult['filename']
                );
            }
        }
        
        if ($posterUrl) {
            $data['poster_url'] = $posterUrl;
        }
        
        // API call to add item
        $apiData = [
            'action' => 'add_item',
            'data' => $data,
            'media_details' => $mediaDetails,
            'categories' => $_POST['categories'] ?? []
        ];
        
        $response = makeAPICall('collection', 'POST', $apiData);
        
        if ($response['success']) {
            $_SESSION['success_message'] = 'Item added successfully!';
            header('Location: index.php');
            exit;
        } else {
            $error = $response['error'] ?? 'Failed to add item';
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        logEvent('Add item error: ' . $e->getMessage(), 'ERROR');
    }
}

// Pre-fill data from barcode scan or external lookup
$prefillData = [];
if (isset($_GET['title'])) {
    $prefillData['title'] = sanitizeInput($_GET['title']);
    $prefillData['year'] = sanitizeInput($_GET['year'] ?? '');
    $prefillData['creator'] = sanitizeInput($_GET['creator'] ?? '');
    $prefillData['description'] = sanitizeInput($_GET['description'] ?? '');
    $prefillData['poster_url'] = sanitizeInput($_GET['poster_url'] ?? '');
    $prefillData['media_type'] = sanitizeInput($_GET['media_type'] ?? '');
    $prefillData['identifier'] = sanitizeInput($_GET['identifier'] ?? '');
}

// Load categories and locations
$categories = loadCategories();
$locations = loadLocations();

function loadCategories() {
    $response = makeAPICall('categories', 'GET', ['include_usage' => true]);
    return $response['success'] ? $response['categories'] : [];
}

function loadLocations() {
    $response = makeAPICall('locations', 'GET');
    return $response['success'] ? $response['locations'] : [];
}

function makeAPICall($endpoint, $method = 'GET', $data = []) {
    $url = '/api/index.php?endpoint=' . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $method !== 'GET' ? json_encode($data) : null
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true) ?: ['success' => false, 'error' => 'API call failed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Item - Media Collection</title>
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
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .navbar-nav {
            display: flex;
            gap: 1rem;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 1rem;
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
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .form-content {
            padding: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
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
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .media-specific {
            display: none;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .media-specific.active {
            display: block;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e1e5e9;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .category-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem;
        }
        
        .category-checkbox input[type="checkbox"] {
            width: auto;
        }
        
        .image-upload {
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
        }
        
        .image-upload:hover {
            border-color: #667eea;
        }
        
        .image-upload.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 1rem auto;
            border-radius: 8px;
            display: none;
        }
        
        .barcode-input {
            position: relative;
        }
        
        .barcode-lookup {
            position: absolute;
            right: 8px;
            top: 8px;
            background: #667eea;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📚 Media Collection</div>
            <div class="navbar-nav">
                <a href="index.php" class="nav-link">🏠 Collection</a>
                <a href="../admin/wishlist.php" class="nav-link">🎯 Wishlist</a>
                <a href="../api/barcode_scanner.php" class="nav-link">📱 Scanner</a>
                <a href="../admin/dashboard.php" class="nav-link">⚙️ Admin</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">📦 Add New Item</h1>
            <p class="page-subtitle">Add a new item to your media collection</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <div class="quick-actions">
                    <button type="button" class="btn" onclick="openBarcodeScanner()">
                        📱 Scan Barcode
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="openMetadataSearch()">
                        🔍 Search Metadata
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="openDuplicateCheck()">
                        🔍 Check Duplicates
                    </button>
                </div>
            </div>

            <div class="form-content">
                <form method="post" enctype="multipart/form-data" id="add-item-form">
                    <?php echo CSRFProtection::getTokenField(); ?>

                    <div class="form-grid">
                        <!-- Left Column -->
                        <div>
                            <div class="form-section">
                                <h3 class="section-title">Basic Information</h3>
                                
                                <div class="form-group">
                                    <label for="media_type">Media Type *</label>
                                    <select name="media_type" id="media_type" required onchange="updateMediaFields()">
                                        <option value="">Select Type</option>
                                        <option value="movie" <?php echo ($prefillData['media_type'] ?? '') === 'movie' ? 'selected' : ''; ?>>🎬 Movie/TV</option>
                                        <option value="book" <?php echo ($prefillData['media_type'] ?? '') === 'book' ? 'selected' : ''; ?>>📚 Book</option>
                                        <option value="comic" <?php echo ($prefillData['media_type'] ?? '') === 'comic' ? 'selected' : ''; ?>>📖 Comic</option>
                                        <option value="music" <?php echo ($prefillData['media_type'] ?? '') === 'music' ? 'selected' : ''; ?>>🎵 Music</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="title">Title *</label>
                                    <input type="text" name="title" id="title" required 
                                           value="<?php echo htmlspecialchars($prefillData['title'] ?? ''); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="year">Year</label>
                                        <input type="number" name="year" id="year" min="1800" max="2030"
                                               value="<?php echo htmlspecialchars($prefillData['year'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="creator">Creator/Director/Author</label>
                                        <input type="text" name="creator" id="creator"
                                               value="<?php echo htmlspecialchars($prefillData['creator'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description" rows="3"><?php echo htmlspecialchars($prefillData['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group barcode-input">
                                    <label for="identifier">Barcode/ISBN/UPC</label>
                                    <input type="text" name="identifier" id="identifier" placeholder="Enter or scan barcode"
                                           value="<?php echo htmlspecialchars($prefillData['identifier'] ?? ''); ?>">
                                    <button type="button" class="barcode-lookup" onclick="lookupMetadata()">Lookup</button>
                                </div>
                            </div>

                            <!-- Media-Specific Fields -->
                            <div id="movie-fields" class="media-specific">
                                <h4>Movie/TV Details</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="movie_format">Format</label>
                                        <select name="movie_format" id="movie_format">
                                            <option value="dvd">DVD</option>
                                            <option value="blu_ray">Blu-ray</option>
                                            <option value="4k_uhd">4K UHD</option>
                                            <option value="vhs">VHS</option>
                                            <option value="digital">Digital</option>
                                            <option value="streaming">Streaming</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="region">Region</label>
                                        <select name="region" id="region">
                                            <option value="region_1">Region 1 (US/Canada)</option>
                                            <option value="region_2">Region 2 (Europe/Japan)</option>
                                            <option value="region_free">Region Free</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="director">Director</label>
                                        <input type="text" name="director" id="director">
                                    </div>
                                    <div class="form-group">
                                        <label for="studio">Studio</label>
                                        <input type="text" name="studio" id="studio">
                                    </div>
                                </div>
                            </div>

                            <div id="book-fields" class="media-specific">
                                <h4>Book Details</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="book_format">Format</label>
                                        <select name="book_format" id="book_format">
                                            <option value="hardcover">Hardcover</option>
                                            <option value="paperback">Paperback</option>
                                            <option value="trade_paperback">Trade Paperback</option>
                                            <option value="ebook">eBook</option>
                                            <option value="audiobook">Audiobook</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="genre">Genre</label>
                                        <input type="text" name="genre" id="genre">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="isbn">ISBN-10</label>
                                        <input type="text" name="isbn" id="isbn">
                                    </div>
                                    <div class="form-group">
                                        <label for="isbn13">ISBN-13</label>
                                        <input type="text" name="isbn13" id="isbn13">
                                    </div>
                                </div>
                            </div>

                            <div id="comic-fields" class="media-specific">
                                <h4>Comic Details</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="publisher">Publisher</label>
                                        <select name="publisher" id="publisher">
                                            <option value="marvel">Marvel Comics</option>
                                            <option value="dc">DC Comics</option>
                                            <option value="image">Image Comics</option>
                                            <option value="dark_horse">Dark Horse Comics</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="issue_number">Issue Number</label>
                                        <input type="text" name="issue_number" id="issue_number">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="variant_type">Variant Type</label>
                                        <select name="variant_type" id="variant_type">
                                            <option value="regular">Regular Cover</option>
                                            <option value="variant">Variant Cover</option>
                                            <option value="sketch">Sketch Cover</option>
                                            <option value="virgin">Virgin Cover</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="graded">
                                            <input type="checkbox" name="graded" id="graded"> Graded
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div id="music-fields" class="media-specific">
                                <h4>Music Details</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="music_format">Format</label>
                                        <select name="music_format" id="music_format">
                                            <option value="cd">CD</option>
                                            <option value="vinyl_lp">Vinyl LP</option>
                                            <option value="vinyl_45">Vinyl 45</option>
                                            <option value="digital">Digital</option>
                                            <option value="cassette">Cassette</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="record_label">Record Label</label>
                                        <input type="text" name="record_label" id="record_label">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <div class="form-section">
                                <h3 class="section-title">Collection Details</h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="condition_rating">Condition</label>
                                        <select name="condition_rating" id="condition_rating">
                                            <option value="mint">Mint</option>
                                            <option value="near_mint">Near Mint</option>
                                            <option value="very_fine" selected>Very Fine</option>
                                            <option value="fine">Fine</option>
                                            <option value="very_good">Very Good</option>
                                            <option value="good">Good</option>
                                            <option value="fair">Fair</option>
                                            <option value="poor">Poor</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="personal_rating">Personal Rating (1-10)</label>
                                        <input type="number" name="personal_rating" id="personal_rating" min="1" max="10">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="purchase_price">Purchase Price</label>
                                        <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="current_value">Current Value</label>
                                        <input type="number" name="current_value" id="current_value" step="0.01" min="0">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="purchase_date">Purchase Date</label>
                                    <input type="date" name="purchase_date" id="purchase_date">
                                </div>

                                <div class="form-group">
                                    <label for="acquisition_method">How Acquired</label>
                                    <select name="acquisition_method" id="acquisition_method">
                                        <option value="purchased_new">Purchased New</option>
                                        <option value="purchased_used">Purchased Used</option>
                                        <option value="gift">Gift</option>
                                        <option value="trade">Trade</option>
                                        <option value="inherited">Inherited</option>
                                        <option value="found">Found</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">Storage Location</h3>
                                
                                <div class="form-group">
                                    <label for="primary_location_id">Primary Location</label>
                                    <select name="primary_location_id" id="primary_location_id">
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo $location['id']; ?>">
                                                <?php echo htmlspecialchars($location['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="specific_location">Specific Location</label>
                                    <input type="text" name="specific_location" id="specific_location" 
                                           placeholder="e.g., Shelf 3, Row 2">
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">Categories</h3>
                                <div class="categories-grid">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="category-checkbox">
                                            <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                                   id="cat_<?php echo $category['id']; ?>">
                                            <label for="cat_<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title">Image Upload</h3>
                                <div class="image-upload" id="image-upload">
                                    <p>📸 Drag & drop an image here or click to browse</p>
                                    <input type="file" name="poster_image" id="poster_image" accept="image/*" style="display: none;">
                                    <img id="image-preview" class="image-preview" alt="Preview">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Additional Information</h3>
                        
                        <div class="form-group">
                            <label for="tags">Tags (comma-separated)</label>
                            <input type="text" name="tags" id="tags" placeholder="action, adventure, classic">
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="3" 
                                      placeholder="Any additional notes about this item..."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-secondary" onclick="saveAsDraft()">Save as Draft</button>
                        <button type="submit" class="btn">Add to Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <script>
        function updateMediaFields() {
            const mediaType = document.getElementById('media_type').value;
            
            // Hide all media-specific fields
            document.querySelectorAll('.media-specific').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show relevant fields
            if (mediaType) {
                const section = document.getElementById(mediaType + '-fields');
                if (section) {
                    section.classList.add('active');
                }
            }
        }

        function openBarcodeScanner() {
            window.open('../api/barcode_scanner.php', '_blank', 'width=800,height=600');
        }

        function openMetadataSearch() {
            const title = document.getElementById('title').value;
            const mediaType = document.getElementById('media_type').value;
            
            let url = '../api/barcode_scanner.php?action=search';
            if (title) url += '&q=' + encodeURIComponent(title);
            if (mediaType) url += '&media_type=' + mediaType;
            
            window.open(url, '_blank', 'width=800,height=600');
        }

        async function lookupMetadata() {
            const identifier = document.getElementById('identifier').value.trim();
            const mediaType = document.getElementById('media_type').value;
            
            if (!identifier) {
                alert('Please enter a barcode or identifier');
                return;
            }
            
            showLoading();
            
            try {
                const params = new URLSearchParams({
                    action: 'lookup',
                    identifier: identifier
                });
                
                if (mediaType) {
                    params.append('media_type', mediaType);
                }
                
                const response = await fetch(`../api/handlers/metadata_lookup.php?${params}`);
                const data = await response.json();
                
                if (data.success && data.data.best_match) {
                    fillFormFromMetadata(data.data.best_match);
                } else {
                    alert('No metadata found for this identifier');
                }
                
            } catch (error) {
                console.error('Lookup error:', error);
                alert('Error looking up metadata: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function fillFormFromMetadata(metadata) {
            // Fill basic fields
            document.getElementById('title').value = metadata.title || '';
            document.getElementById('year').value = metadata.year || '';
            document.getElementById('creator').value = metadata.creator || '';
            document.getElementById('description').value = metadata.description || '';
            
            // Set media type and update fields
            if (metadata.media_type) {
                document.getElementById('media_type').value = metadata.media_type;
                updateMediaFields();
            }
            
            // Fill media-specific fields
            if (metadata.media_details) {
                Object.keys(metadata.media_details).forEach(key => {
                    const field = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
                    if (field && metadata.media_details[key]) {
                        field.value = metadata.media_details[key];
                    }
                });
            }
            
            // Set poster image if available
            if (metadata.poster_url) {
                // You could pre-fill the image preview here
                console.log('Poster URL:', metadata.poster_url);
            }
            
            // Auto-select relevant categories
            if (metadata.categories && Array.isArray(metadata.categories)) {
                metadata.categories.forEach(category => {
                    const checkbox = document.querySelector(`input[value="${category}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
        }

        // Image upload handling
        const imageUpload = document.getElementById('image-upload');
        const fileInput = document.getElementById('poster_image');
        const imagePreview = document.getElementById('image-preview');

        imageUpload.addEventListener('click', () => fileInput.click());

        imageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUpload.classList.add('dragover');
        });

        imageUpload.addEventListener('dragleave', () => {
            imageUpload.classList.remove('dragover');
        });

        imageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUpload.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                previewImage(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                previewImage(e.target.files[0]);
            }
        });

        function previewImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function saveAsDraft() {
            // Implementation for saving as draft
            alert('Draft save functionality coming soon!');
        }

        function openDuplicateCheck() {
            const title = document.getElementById('title').value;
            if (title) {
                window.open(`index.php?search=${encodeURIComponent(title)}`, '_blank');
            } else {
                alert('Please enter a title first');
            }
        }

        // Auto-update current value when purchase price changes
        document.getElementById('purchase_price').addEventListener('input', function() {
            const currentValueField = document.getElementById('current_value');
            if (!currentValueField.value) {
                currentValueField.value = this.value;
            }
        });

        // Initialize media fields if pre-filled
        document.addEventListener('DOMContentLoaded', function() {
            updateMediaFields();
        });
    </script>
</body>
</html>