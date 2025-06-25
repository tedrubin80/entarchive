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
        case 'add_item':
            handleAddItem();
            break;
        case 'import_csv':
            handleCSVImport();
            break;
        case 'add_category':
            handleAddCategory();
            break;
    }
}

function handleAddItem() {
    // Implementation for adding individual items
    // ... (detailed implementation would go here)
}

function handleCSVImport() {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        $uploadFile = $uploadDir . basename($_FILES['csv_file']['name']);
        
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadFile)) {
            // Process the CSV file
            exec("php ../scripts/migrate_csv.php " . escapeshellarg($uploadFile), $output, $return_var);
            
            if ($return_var === 0) {
                $message = "CSV import completed successfully!";
            } else {
                $error = "CSV import failed: " . implode("\n", $output);
            }
        } else {
            $error = "Failed to upload CSV file.";
        }
    }
}

function handleAddCategory() {
    // Implementation for adding categories
    // ... (detailed implementation would go here)
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Management - Admin</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 1rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
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
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
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
            border-radius: 8px;
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
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab {
            flex: 1;
            padding: 1rem;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        
        .tab.active,
        .tab:hover {
            background: #667eea;
            color: white;
        }
        
        .tab-content {
            background: white;
            padding: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📚 Enhanced Collection Management</h1>
        <p>Comprehensive Media Collection Administration</p>
    </div>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="totalMovies">0</div>
                <div class="stat-label">Movies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalBooks">0</div>
                <div class="stat-label">Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalComics">0</div>
                <div class="stat-label">Comics</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalMusic">0</div>
                <div class="stat-label">Music</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalValue">$0</div>
                <div class="stat-label">Collection Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="wishlistItems">0</div>
                <div class="stat-label">Wishlist Items</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('add-item')">Add Items</button>
            <button class="tab" onclick="showTab('import-data')">Import Data</button>
            <button class="tab" onclick="showTab('manage-categories')">Categories</button>
            <button class="tab" onclick="showTab('collection-tools')">Tools</button>
        </div>

        <!-- Add Item Tab -->
        <div id="add-item" class="tab-content active">
            <h3>Add New Item to Collection</h3>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_item">
                
                <div class="form-group">
                    <label for="media_type">Media Type</label>
                    <select id="media_type" name="media_type" required onchange="updateFormFields()">
                        <option value="">Select Type</option>
                        <option value="movie">Movie/TV</option>
                        <option value="book">Book</option>
                        <option value="comic">Comic</option>
                        <option value="music">Music</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" min="1800" max="2030">
                </div>
                
                <div class="form-group">
                    <label for="creator">Creator/Director/Author</label>
                    <input type="text" id="creator" name="creator">
                </div>
                
                <!-- Dynamic fields based on media type -->
                <div id="movie-fields" style="display: none;">
                    <div class="form-group">
                        <label for="movie_format">Format</label>
                        <select id="movie_format" name="movie_format">
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
                        <select id="region" name="region">
                            <option value="region_1">Region 1 (US/Canada)</option>
                            <option value="region_2">Region 2 (Europe/Japan)</option>
                            <option value="region_free">Region Free</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="studio">Studio</label>
                        <input type="text" id="studio" name="studio">
                    </div>
                </div>
                
                <div id="book-fields" style="display: none;">
                    <div class="form-group">
                        <label for="book_format">Format</label>
                        <select id="book_format" name="book_format">
                            <option value="hardcover">Hardcover</option>
                            <option value="paperback">Paperback</option>
                            <option value="ebook">eBook</option>
                            <option value="audiobook">Audiobook</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn">
                    </div>
                    
                    <div class="form-group">
                        <label for="genre">Genre</label>
                        <input type="text" id="genre" name="genre">
                    </div>
                </div>
                
                <div id="comic-fields" style="display: none;">
                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <select id="publisher" name="publisher">
                            <option value="marvel">Marvel Comics</option>
                            <option value="dc">DC Comics</option>
                            <option value="image">Image Comics</option>
                            <option value="dark_horse">Dark Horse Comics</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="issue_number">Issue Number</label>
                        <input type="text" id="issue_number" name="issue_number">
                    </div>
                    
                    <div class="form-group">
                        <label for="variant_type">Variant Type</label>
                        <select id="variant_type" name="variant_type">
                            <option value="regular">Regular Cover</option>
                            <option value="variant">Variant Cover</option>
                            <option value="sketch">Sketch Cover</option>
                            <option value="virgin">Virgin Cover</option>
                        </select>
                    </div>
                </div>
                
                <div id="music-fields" style="display: none;">
                    <div class="form-group">
                        <label for="music_format">Format</label>
                        <select id="music_format" name="music_format">
                            <option value="cd">CD</option>
                            <option value="vinyl_lp">Vinyl LP</option>
                            <option value="vinyl_45">Vinyl 45</option>
                            <option value="digital">Digital</option>
                            <option value="cassette">Cassette</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="record_label">Record Label</label>
                        <input type="text" id="record_label" name="record_label">
                    </div>
                    
                    <div class="form-group">
                        <label for="music_genre">Genre</label>
                        <input type="text" id="music_genre" name="music_genre">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition">
                        <option value="mint">Mint</option>
                        <option value="near_mint">Near Mint</option>
                        <option value="very_fine">Very Fine</option>
                        <option value="fine">Fine</option>
                        <option value="very_good">Very Good</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="purchase_price">Purchase Price</label>
                    <input type="number" id="purchase_price" name="purchase_price" step="0.01" min="0">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn">Add to Collection</button>
            </form>
        </div>

        <!-- Import Data Tab -->
        <div id="import-data" class="tab-content">
            <h3>Import Collection Data</h3>
            
            <div class="card">
                <h4>CSV Import</h4>
                <p>Upload a CSV file with your existing collection data. Supported formats include movie databases, book catalogs, and custom exports.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    
                    <div class="form-group">
                        <label for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="import_type">Data Type</label>
                        <select id="import_type" name="import_type">
                            <option value="movies">Movies</option>
                            <option value="books">Books</option>
                            <option value="comics">Comics</option>
                            <option value="music">Music</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Import CSV</button>
                </form>
            </div>
        </div>

        <!-- Manage Categories Tab -->
        <div id="manage-categories" class="tab-content">
            <h3>Manage Categories</h3>
            
            <div class="dashboard-grid">
                <div class="card">
                    <h4>Add New Category</h4>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_type">Media Type</label>
                            <select id="category_type" name="category_type">
                                <option value="all">All Media Types</option>
                                <option value="movie">Movies Only</option>
                                <option value="book">Books Only</option>
                                <option value="comic">Comics Only</option>
                                <option value="music">Music Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_category">Parent Category (Optional)</label>
                            <select id="parent_category" name="parent_category">
                                <option value="">None (Top Level)</option>
                                <!-- Options would be populated from database -->
                            </select>
                        </div>
                        
                        <button type="submit" class="btn">Add Category</button>
                    </form>
                </div>
                
                <div class="card">
                    <h4>Quick Category Setup</h4>
                    <p>Add common categories for each media type:</p>
                    
                    <button class="btn btn-secondary" onclick="setupMovieCategories()">Setup Movie Genres</button>
                    <button class="btn btn-secondary" onclick="setupBookCategories()">Setup Book Genres</button>
                    <button class="btn btn-secondary" onclick="setupComicCategories()">Setup Comic Publishers</button>
                    <button class="btn btn-secondary" onclick="setupMusicCategories()">Setup Music Genres</button>
                </div>
            </div>
        </div>

        <!-- Collection Tools Tab -->
        <div id="collection-tools" class="tab-content">
            <h3>Collection Management Tools</h3>
            
            <div class="dashboard-grid">
                <div class="card">
                    <h4>Database Maintenance</h4>
                    <button class="btn" onclick="runMaintenance()">Optimize Database</button>
                    <button class="btn btn-secondary" onclick="backupData()">Backup Collection</button>
                    <button class="btn btn-secondary" onclick="exportData()">Export to CSV</button>
                </div>
                
                <div class="card">
                    <h4>Value Assessment</h4>
                    <button class="btn" onclick="updateValues()">Update Market Values</button>
                    <button class="btn btn-secondary" onclick="generateReport()">Value Report</button>
                </div>
                
                <div class="card">
                    <h4>Collection Analytics</h4>
                    <button class="btn" onclick="showAnalytics()">View Analytics</button>
                    <button class="btn btn-secondary" onclick="findDuplicates()">Find Duplicates</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function updateFormFields() {
            const mediaType = document.getElementById('media_type').value;
            
            // Hide all specific fields
            document.getElementById('movie-fields').style.display = 'none';
            document.getElementById('book-fields').style.display = 'none';
            document.getElementById('comic-fields').style.display = 'none';
            document.getElementById('music-fields').style.display = 'none';
            
            // Show relevant fields
            if (mediaType) {
                document.getElementById(mediaType + '-fields').style.display = 'block';
            }
        }
        
        function setupMovieCategories() {
            // Implementation for setting up movie categories
            alert('Setting up movie categories...');
        }
        
        function setupBookCategories() {
            // Implementation for setting up book categories
            alert('Setting up book categories...');
        }
        
        function setupComicCategories() {
            // Implementation for setting up comic categories
            alert('Setting up comic categories...');
        }
        
        function setupMusicCategories() {
            // Implementation for setting up music categories
            alert('Setting up music categories...');
        }
        
        function runMaintenance() {
            alert('Running database maintenance...');
        }
        
        function backupData() {
            alert('Creating backup...');
        }
        
        function exportData() {
            alert('Exporting collection data...');
        }
        
        function updateValues() {
            alert('Updating market values...');
        }
        
        function generateReport() {
            alert('Generating value report...');
        }
        
        function showAnalytics() {
            alert('Loading analytics...');
        }
        
        function findDuplicates() {
            alert('Searching for duplicates...');
        }
        
        // Load statistics on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Implementation to fetch and display statistics
            // This would typically make AJAX calls to get actual data
        });
    </script>
</body>
</html>