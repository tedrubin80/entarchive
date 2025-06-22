<?php
session_start();
require_once '../config.php';
require_once 'geo_helper.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

// Non-blocking geolocation check
$ip = $_SERVER['REMOTE_ADDR'];
if (!GeoLocationHelper::checkAccess($ip)) {
    session_destroy();
    die("Access restricted to users in the United States and Canada.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection Dashboard</title>
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
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card .label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }
        
        .content-area {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .filter-group {
            margin-bottom: 1.5rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .filter-group select, 
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .media-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .media-card:hover {
            transform: translateY(-2px);
        }
        
        .media-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .media-card-content {
            padding: 1rem;
        }
        
        .media-card h3 {
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 1rem;
        }
        
        .media-card .meta {
            color: #666;
            font-size: 0.85rem;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: -1;
            }
            
            .container {
                padding: 0 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 1rem;
            }
            
            .content-area, .sidebar {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>📚 Media Collection</h1>
        <div class="header-actions">
            <a href="add.php" class="btn btn-primary">+ Add Media</a>
            <a href="scan.php" class="btn btn-secondary">📱 Scan</a>
            <a href="../admin/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number" id="totalMovies">0</div>
                <div class="label">Movies</div>
            </div>
            <div class="stat-card">
                <div class="number" id="totalBooks">0</div>
                <div class="label">Books</div>
            </div>
            <div class="stat-card">
                <div class="number" id="totalComics">0</div>
                <div class="label">Comics</div>
            </div>
            <div class="stat-card">
                <div class="number" id="totalMusic">0</div>
                <div class="label">Music</div>
            </div>
        </div>

        <div class="main-content">
            <div class="content-area">
                <h2>Your Collection</h2>
                <div id="mediaGrid" class="media-grid">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading your collection...
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <h3>Filters</h3>
                <div class="filter-group">
                    <label for="typeFilter">Media Type</label>
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="movie">Movies</option>
                        <option value="book">Books</option>
                        <option value="comic">Comics</option>
                        <option value="music">Music</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="yearFilter">Year</label>
                    <input type="number" id="yearFilter" placeholder="e.g. 2024">
                </div>
                
                <div class="filter-group">
                    <label for="searchFilter">Search</label>
                    <input type="text" id="searchFilter" placeholder="Title or creator...">
                </div>
                
                <button class="btn btn-primary" style="width: 100%;" onclick="applyFilters()">
                    Apply Filters
                </button>
            </div>
        </div>
    </div>

    <script>
        // Debounce function for search
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Load collection data
        async function loadCollection(filters = {}) {
            const mediaGrid = document.getElementById('mediaGrid');
            mediaGrid.innerHTML = '<div class="loading"><div class="spinner"></div>Loading...</div>';

            try {
                const queryParams = new URLSearchParams(filters).toString();
                const response = await fetch(`../api/collection.php?${queryParams}`);
                const data = await response.json();

                updateStats(data.stats || {});
                renderMediaGrid(data.items || []);
            } catch (error) {
                console.error('Error loading collection:', error);
                mediaGrid.innerHTML = '<div class="loading">Error loading collection. Please try again.</div>';
            }
        }

        function updateStats(stats) {
            document.getElementById('totalMovies').textContent = stats.movies || 0;
            document.getElementById('totalBooks').textContent = stats.books || 0;
            document.getElementById('totalComics').textContent = stats.comics || 0;
            document.getElementById('totalMusic').textContent = stats.music || 0;
        }

        function renderMediaGrid(items) {
            const mediaGrid = document.getElementById('mediaGrid');
            
            if (items.length === 0) {
                mediaGrid.innerHTML = '<div class="loading">No items found. <a href="add.php">Add your first item!</a></div>';
                return;
            }

            mediaGrid.innerHTML = items.map(item => `
                <div class="media-card">
                    <img src="${item.poster_url || '/placeholder.jpg'}" alt="${item.title}" 
                         onerror="this.src='/placeholder.jpg'">
                    <div class="media-card-content">
                        <h3>${item.title}</h3>
                        <div class="meta">
                            ${item.creator ? `${item.creator} • ` : ''}${item.year || 'Unknown Year'}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function applyFilters() {
            const filters = {
                type: document.getElementById('typeFilter').value,
                year: document.getElementById('yearFilter').value,
                search: document.getElementById('searchFilter').value
            };

            // Remove empty filters
            Object.keys(filters).forEach(key => {
                if (!filters[key]) delete filters[key];
            });

            loadCollection(filters);
        }

        // Auto-apply filters with debouncing
        const debouncedFilter = debounce(applyFilters, 300);

        document.getElementById('searchFilter').addEventListener('input', debouncedFilter);
        document.getElementById('yearFilter').addEventListener('input', debouncedFilter);
        document.getElementById('typeFilter').addEventListener('change', applyFilters);

        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadCollection();
        });
    </script>
</body>
</html>