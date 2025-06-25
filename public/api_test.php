<?php
/**
 * API Test Endpoint
 * File: public/test_api.php
 * Test the API integrations and poster downloads
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: user_login.php");
    exit;
}

// Include the API Manager
require_once dirname(__DIR__) . '/api/integrations/MediaAPIManager.php';

$message = '';
$messageType = '';
$testResults = [];
$posterDownloads = [];

// Initialize API Manager
try {
    $apiManager = new MediaAPIManager();
} catch (Exception $e) {
    $message = "Failed to initialize API Manager: " . $e->getMessage();
    $messageType = "error";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($apiManager)) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_search':
            $query = trim($_POST['query'] ?? '');
            $mediaType = $_POST['media_type'] ?? '';
            $year = $_POST['year'] ?? '';
            
            if (!empty($query)) {
                $testResults = $apiManager->lookupMedia($query, $mediaType, $year);
                $message = "Search completed for: " . htmlspecialchars($query);
                $messageType = "success";
            } else {
                $message = "Please enter a search query";
                $messageType = "error";
            }
            break;
            
        case 'download_poster':
            $posterUrl = $_POST['poster_url'] ?? '';
            $mediaType = $_POST['dl_media_type'] ?? 'movie';
            $identifier = $_POST['identifier'] ?? uniqid();
            
            if (!empty($posterUrl)) {
                $downloadResult = $apiManager->downloadPoster($posterUrl, $mediaType, $identifier);
                
                if ($downloadResult) {
                    $posterDownloads[] = [
                        'success' => true,
                        'path' => $downloadResult,
                        'url' => $posterUrl
                    ];
                    $message = "Poster downloaded successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to download poster: " . $apiManager->getLastError();
                    $messageType = "error";
                }
            } else {
                $message = "Please enter a poster URL";
                $messageType = "error";
            }
            break;
    }
}

$currentUser = $_SESSION['admin_user'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Center - Media Collection</title>
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
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #333;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .test-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .test-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .result-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            background: #f8f9fa;
        }
        
        .result-card h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .result-poster {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        
        .result-info {
            font-size: 0.9rem;
            color: #666;
        }
        
        .result-info strong {
            color: #333;
        }
        
        .poster-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .poster-item {
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }
        
        .poster-item img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .json-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .status-indicator {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>🧪 API Test Center</h1>
        <div class="nav-links">
            <a href="enhanced_media_dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="api_settings.php" class="nav-link">🔑 API Settings</a>
            <a href="user_settings.php" class="nav-link">⚙️ Settings</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php if ($messageType === 'success'): ?>✅<?php else: ?>❌<?php endif; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Search Test -->
        <div class="test-card">
            <h2>🔍 Media Search Test</h2>
            <p style="color: #666; margin-bottom: 1.5rem;">
                Test the API integrations by searching for movies, TV shows, books, or other media.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_search">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="query">Search Query</label>
                        <input type="text" id="query" name="query" 
                               placeholder="e.g., The Matrix, Harry Potter, The Beatles"
                               value="<?php echo htmlspecialchars($_POST['query'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="media_type">Media Type</label>
                        <select id="media_type" name="media_type">
                            <option value="">Auto-detect</option>
                            <option value="movie" <?php echo ($_POST['media_type'] ?? '') === 'movie' ? 'selected' : ''; ?>>Movie</option>
                            <option value="tv" <?php echo ($_POST['media_type'] ?? '') === 'tv' ? 'selected' : ''; ?>>TV Show</option>
                            <option value="book" <?php echo ($_POST['media_type'] ?? '') === 'book' ? 'selected' : ''; ?>>Book</option>
                            <option value="music" <?php echo ($_POST['media_type'] ?? '') === 'music' ? 'selected' : ''; ?>>Music</option>
                            <option value="game" <?php echo ($_POST['media_type'] ?? '') === 'game' ? 'selected' : ''; ?>>Game</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="year">Year (Optional)</label>
                        <input type="number" id="year" name="year" 
                               placeholder="2023" min="1900" max="2030"
                               value="<?php echo htmlspecialchars($_POST['year'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">🔍 Search APIs</button>
            </form>
        </div>

        <!-- Search Results -->
        <?php if (!empty($testResults)): ?>
        <div class="test-card">
            <h2>📊 Search Results</h2>
            
            <div class="results-grid">
                <?php foreach ($testResults['sources'] as $source => $data): ?>
                <div class="result-card">
                    <h3>
                        <?php
                        $icons = ['omdb' => '🎬', 'tmdb' => '🎭', 'google_books' => '📚'];
                        echo ($icons[$source] ?? '🔍') . ' ' . strtoupper($source);
                        ?>
                        <span class="status-indicator <?php echo isset($data['error']) ? 'status-error' : 'status-success'; ?>">
                            <?php echo isset($data['error']) ? 'Error' : 'Success'; ?>
                        </span>
                    </h3>
                    
                    <?php if (isset($data['error'])): ?>
                        <p style="color: #dc3545;"><?php echo htmlspecialchars($data['error']); ?></p>
                    <?php else: ?>
                        <?php if (!empty($data['poster'])): ?>
                            <img src="<?php echo htmlspecialchars($data['poster']); ?>" 
                                 alt="Poster" class="result-poster" style="max-width: 150px;">
                        <?php endif; ?>
                        
                        <div class="result-info">
                            <?php if (!empty($data['title'])): ?>
                                <p><strong>Title:</strong> <?php echo htmlspecialchars($data['title']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($data['year'])): ?>
                                <p><strong>Year:</strong> <?php echo htmlspecialchars($data['year']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($data['plot']) && strlen($data['plot']) > 10): ?>
                                <p><strong>Plot:</strong> <?php echo htmlspecialchars(substr($data['plot'], 0, 200)) . '...'; ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($data['imdb_rating'])): ?>
                                <p><strong>IMDB Rating:</strong> <?php echo htmlspecialchars($data['imdb_rating']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($data['authors'])): ?>
                                <p><strong>Authors:</strong> <?php echo htmlspecialchars(implode(', ', $data['authors'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary" style="margin-top: 1rem;" 
                                onclick="toggleJSON('json-<?php echo $source; ?>')">
                            📄 View Raw Data
                        </button>
                        
                        <div id="json-<?php echo $source; ?>" class="json-output" style="display: none;">
                            <?php echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Available Posters -->
            <?php if (!empty($testResults['posters'])): ?>
            <div style="margin-top: 2rem;">
                <h3>🖼️ Available Posters</h3>
                <div class="poster-grid">
                    <?php foreach ($testResults['posters'] as $index => $poster): ?>
                    <div class="poster-item">
                        <img src="<?php echo htmlspecialchars($poster['url']); ?>" 
                             alt="Poster from <?php echo htmlspecialchars($poster['source']); ?>">
                        <p><strong><?php echo strtoupper($poster['source']); ?></strong></p>
                        
                        <form method="POST" style="margin-top: 0.5rem;">
                            <input type="hidden" name="action" value="download_poster">
                            <input type="hidden" name="poster_url" value="<?php echo htmlspecialchars($poster['url']); ?>">
                            <input type="hidden" name="dl_media_type" value="<?php echo htmlspecialchars($testResults['media_type'] ?? 'movie'); ?>">
                            <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($testResults['query'] . '_' . $index); ?>">
                            <button type="submit" class="btn btn-success" style="font-size: 0.8rem;">
                                💾 Download
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Poster Download Test -->
        <div class="test-card">
            <h2>💾 Poster Download Test</h2>
            <p style="color: #666; margin-bottom: 1.5rem;">
                Test downloading and processing poster images from URLs.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="download_poster">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="poster_url">Poster URL</label>
                        <input type="url" id="poster_url" name="poster_url" 
                               placeholder="https://example.com/poster.jpg"
                               value="<?php echo htmlspecialchars($_POST['poster_url'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="dl_media_type">Media Type</label>
                        <select id="dl_media_type" name="dl_media_type">
                            <option value="movie">Movie</option>
                            <option value="book">Book</option>
                            <option value="music">Music</option>
                            <option value="game">Game</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="identifier">Identifier</label>
                        <input type="text" id="identifier" name="identifier" 
                               placeholder="unique_id" value="test_<?php echo time(); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">💾 Download Poster</button>
            </form>
        </div>

        <!-- Downloaded Posters -->
        <?php if (!empty($posterDownloads)): ?>
        <div class="test-card">
            <h2>📁 Downloaded Posters</h2>
            
            <div class="poster-grid">
                <?php foreach ($posterDownloads as $download): ?>
                <div class="poster-item">
                    <?php if ($download['success']): ?>
                        <img src="<?php echo htmlspecialchars($download['path']); ?>" alt="Downloaded Poster">
                        <p><strong>✅ Success</strong></p>
                        <p style="font-size: 0.8rem; color: #666;">
                            <?php echo htmlspecialchars($download['path']); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #dc3545;"><strong>❌ Failed</strong></p>
                        <p style="font-size: 0.8rem;">
                            <?php echo htmlspecialchars($download['error'] ?? 'Unknown error'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="test-card">
            <h2>ℹ️ System Information</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h4>📋 API Configuration</h4>
                    <p><strong>OMDB:</strong> <?php echo defined('OMDB_API_KEY') && !empty(OMDB_API_KEY) ? '✅ Configured' : '❌ Not configured'; ?></p>
                    <p><strong>TMDB:</strong> <?php echo defined('TMDB_API_KEY') && !empty(TMDB_API_KEY) ? '✅ Configured' : '❌ Not configured'; ?></p>
                    <p><strong>Google Books:</strong> <?php echo defined('GOOGLE_BOOKS_API_KEY') && !empty(GOOGLE_BOOKS_API_KEY) ? '✅ Configured' : '❌ Not configured'; ?></p>
                    <p><strong>Caching:</strong> <?php echo defined('ENABLE_API_CACHING') && ENABLE_API_CACHING ? '✅ Enabled' : '❌ Disabled'; ?></p>
                </div>
                
                <div>
                    <h4>🔧 System Requirements</h4>
                    <p><strong>PHP cURL:</strong> <?php echo extension_loaded('curl') ? '✅ Available' : '❌ Missing'; ?></p>
                    <p><strong>PHP GD:</strong> <?php echo extension_loaded('gd') ? '✅ Available' : '❌ Missing'; ?></p>
                    <p><strong>Cache Directory:</strong> <?php echo is_dir(dirname(__DIR__) . '/cache/api/') ? '✅ Exists' : '❌ Missing'; ?></p>
                    <p><strong>Poster Directory:</strong> <?php echo is_dir(dirname(__DIR__) . '/uploads/posters/') ? '✅ Exists' : '❌ Missing'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleJSON(elementId) {
            const element = document.getElementById(elementId);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }
        
        // Auto-fill some example searches
        const examples = [
            { query: 'The Matrix', type: 'movie', year: '1999' },
            { query: 'Breaking Bad', type: 'tv', year: '2008' },
            { query: 'Harry Potter', type: 'book', year: '1997' },
            { query: 'The Beatles', type: 'music', year: '1960' }
        ];
        
        function fillExample(index) {
            const example = examples[index];
            document.getElementById('query').value = example.query;
            document.getElementById('media_type').value = example.type;
            document.getElementById('year').value = example.year;
        }
        
        // Add example buttons
        document.addEventListener('DOMContentLoaded', function() {
            const searchCard = document.querySelector('.test-card');
            const exampleDiv = document.createElement('div');
            exampleDiv.style.marginTop = '1rem';
            exampleDiv.innerHTML = '<p style="margin-bottom: 0.5rem;"><strong>Quick Examples:</strong></p>';
            
            examples.forEach((example, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-secondary';
                button.style.marginRight = '0.5rem';
                button.style.marginBottom = '0.5rem';
                button.style.fontSize = '0.8rem';
                button.textContent = example.query;
                button.onclick = () => fillExample(index);
                exampleDiv.appendChild(button);
            });
            
            searchCard.appendChild(exampleDiv);
        });
    </script>
</body>
</html>