<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Collection Browser</title>
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
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .search-input::placeholder {
            color: rgba(255,255,255,0.8);
        }
        
        .search-input:focus {
            outline: none;
            background: rgba(255,255,255,0.25);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }
        
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .search-btn:hover {
            background: rgba(255,255,255,0.2);
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
        
        .layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .content-header {
            padding: 2rem;
            border-bottom: 1px solid #e1e5e9;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .content-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .content-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .toolbar {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .view-controls {
            display: flex;
            gap: 0.5rem;
            background: #f8f9fa;
            padding: 4px;
            border-radius: 8px;
        }
        
        .view-btn {
            padding: 8px 12px;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn.active {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .sort-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .sort-select {
            padding: 8px 12px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            background: white;
            cursor: pointer;
        }
        
        .filter-section {
            margin-bottom: 2rem;
        }
        
        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-toggle {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
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
            font-weight: 500;
        }
        
        .filter-option.active .filter-count {
            background: #1565c0;
            color: white;
        }
        
        .quick-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
        
        .collection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }
        
        .collection-list {
            padding: 1rem 2rem;
        }
        
        .collection-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .collection-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .card-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 3rem;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .card-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .tag {
            background: #f8f9fa;
            color: #495057;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .tag.media-type {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .tag.condition {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .card-location {
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .card-value {
            font-weight: 600;
            color: #28a745;
        }
        
        .condition-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .condition-mint { background: #28a745; }
        .condition-near_mint { background: #20c997; }
        .condition-very_fine { background: #17a2b8; }
        .condition-fine { background: #007bff; }
        .condition-very_good { background: #6f42c1; }
        .condition-good { background: #fd7e14; }
        .condition-fair { background: #ffc107; }
        .condition-poor { background: #dc3545; }
        
        .list-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #e1e5e9;
            transition: background 0.3s ease;
            cursor: pointer;
        }
        
        .list-item:hover {
            background: #f8f9fa;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-image {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .list-content {
            flex: 1;
        }
        
        .list-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .list-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .list-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .list-actions {
            display: flex;
            flex-direction: column;
            align-items: end;
            gap: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 2rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .page-btn {
            padding: 8px 12px;
            border: 1px solid #e1e5e9;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
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
        
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: #666;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .empty-text {
            margin-bottom: 2rem;
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
        
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .suggestion-item:hover {
            background: #f8f9fa;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        .suggestion-text {
            font-weight: 500;
        }
        
        .suggestion-type {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 1024px) {
            .layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
                max-height: none;
            }
            
            .collection-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .navbar-search {
                order: 2;
                max-width: none;
            }
            
            .navbar-actions {
                order: 1;
            }
            
            .collection-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .content-header {
                padding: 1rem;
            }
            
            .content-title {
                font-size: 1.5rem;
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
                <input type="text" class="search-input" id="global-search" 
                       placeholder="Search your collection..." autocomplete="off">
                <button class="search-btn" onclick="performSearch()">🔍</button>
                <div id="search-suggestions" class="search-suggestions" style="display: none;"></div>
            </div>
            
            <div class="navbar-actions">
                <a href="add.php" class="nav-btn">+ Add Item</a>
                <a href="wishlist.php" class="nav-btn">🎯 Wishlist</a>
                <a href="locations.php" class="nav-btn">📍 Locations</a>
                <a href="admin.php" class="nav-btn">⚙️ Admin</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="layout">
            <aside class="sidebar">
                <div class="quick-stats">
                    <h3>Collection Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number" id="total-items">0</div>
                            <div class="stat-label">Total Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="total-value">$0</div>
                            <div class="stat-label">Total Value</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="recent-additions">0</div>
                            <div class="stat-label">Added This Month</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number" id="wishlist-items">0</div>
                            <div class="stat-label">Wishlist Items</div>
                        </div>
                    </div>
                </div>

                <!-- Media Type Filter -->
                <div class="filter-section">
                    <div class="filter-title">
                        Media Type
                        <button class="filter-toggle" onclick="clearFilter('media_type')">Clear</button>
                    </div>
                    <div class="filter-options" id="media-type-filters">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-section">
                    <div class="filter-title">
                        Categories
                        <button class="filter-toggle" onclick="clearFilter('category')">Clear</button>
                    </div>
                    <div class="filter-options" id="category-filters">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <!-- Location Filter -->
                <div class="filter-section">
                    <div class="filter-title">
                        Storage Location
                        <button class="filter-toggle" onclick="clearFilter('location')">Clear</button>
                    </div>
                    <div class="filter-options" id="location-filters">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <!-- Condition Filter -->
                <div class="filter-section">
                    <div class="filter-title">
                        Condition
                        <button class="filter-toggle" onclick="clearFilter('condition')">Clear</button>
                    </div>
                    <div class="filter-options" id="condition-filters">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="filter-section">
                    <div class="filter-title">Quick Actions</div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button class="btn" onclick="showRecentAdditions()">📅 Recent Additions</button>
                        <button class="btn" onclick="showHighValue()">💰 High Value Items</button>
                        <button class="btn" onclick="showNeedsAttention()">⚠️ Needs Attention</button>
                    </div>
                </div>
            </aside>

            <main class="main-content">
                <header class="content-header">
                    <h1 class="content-title" id="page-title">My Collection</h1>
                    <p class="content-subtitle" id="page-subtitle">Explore and manage your media collection</p>
                </header>

                <div class="toolbar">
                    <div class="view-controls">
                        <button class="view-btn active" data-view="grid" onclick="setView('grid')">
                            📱 Grid
                        </button>
                        <button class="view-btn" data-view="list" onclick="setView('list')">
                            📋 List
                        </button>
                    </div>

                    <div class="sort-controls">
                        <label for="sort-select">Sort by:</label>
                        <select id="sort-select" class="sort-select" onchange="applySorting()">
                            <option value="created_at">Date Added</option>
                            <option value="title">Title</option>
                            <option value="creator">Creator</option>
                            <option value="year">Year</option>
                            <option value="value">Value</option>
                            <option value="condition">Condition</option>
                        </select>
                        
                        <button id="sort-direction" class="view-btn" onclick="toggleSortDirection()">
                            ⬇️ Desc
                        </button>
                    </div>
                </div>

                <div id="collection-content">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading your collection...
                    </div>
                </div>

                <div id="pagination-container"></div>
            </main>
        </div>
    </div>

    <script>
        // Global state
        let currentView = 'grid';
        let currentSort = 'created_at';
        let currentDirection = 'desc';
        let currentPage = 1;
        let currentFilters = {};
        let collectionData = [];
        let searchTimeout = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadInitialData();
            setupSearchInput();
            loadFilters();
        });

        async function loadInitialData() {
            try {
                // Load collection stats
                const statsResponse = await fetch('../api/index.php?endpoint=stats&type=overview');
                const statsData = await statsResponse.json();
                
                if (statsData.success) {
                    updateQuickStats(statsData.stats);
                }
                
                // Load collection items
                await loadCollection();
                
            } catch (error) {
                console.error('Error loading initial data:', error);
                showError('Failed to load collection data');
            }
        }

        async function loadCollection() {
            try {
                showLoading();
                
                const params = new URLSearchParams({
                    page: currentPage,
                    limit: 20,
                    sort: currentSort,
                    direction: currentDirection,
                    ...currentFilters
                });

                const response = await fetch(`../api/index.php?endpoint=collection&${params}`);
                const data = await response.json();

                if (data.success) {
                    collectionData = data.items;
                    renderCollection(data.items);
                    renderPagination(data.pagination);
                    updatePageInfo(data);
                } else {
                    showError(data.error || 'Failed to load collection');
                }

            } catch (error) {
                console.error('Error loading collection:', error);
                showError('Failed to load collection');
            }
        }

        function renderCollection(items) {
            const container = document.getElementById('collection-content');
            
            if (items.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3 class="empty-title">No items found</h3>
                        <p class="empty-text">Try adjusting your filters or search terms</p>
                        <a href="add.php" class="btn">Add Your First Item</a>
                    </div>
                `;
                return;
            }

            if (currentView === 'grid') {
                container.innerHTML = `
                    <div class="collection-grid">
                        ${items.map(item => renderGridCard(item)).join('')}
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="collection-list">
                        ${items.map(item => renderListItem(item)).join('')}
                    </div>
                `;
            }
        }

        function renderGridCard(item) {
            const mediaTypeIcons = {
                movie: '🎬',
                book: '📚',
                comic: '📖',
                music: '🎵'
            };

            const conditionClass = `condition-${item.condition_rating || 'fine'}`;
            const categories = item.categories || [];
            const value = item.current_value ? `$${parseFloat(item.current_value).toFixed(2)}` : '';

            return `
                <div class="collection-card" onclick="viewItem(${item.id})">
                    <div class="condition-indicator ${conditionClass}"></div>
                    <div class="card-image">
                        ${item.poster_url ? 
                            `<img src="${item.poster_url}" alt="${item.title}" onerror="this.parentElement.innerHTML='${mediaTypeIcons[item.media_type] || '📄'}'">` :
                            mediaTypeIcons[item.media_type] || '📄'
                        }
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">${item.title}</h3>
                        <div class="card-meta">
                            ${item.creator ? `${item.creator} • ` : ''}${item.year || 'Unknown Year'}
                        </div>
                        <div class="card-tags">
                            <span class="tag media-type">${item.media_type}</span>
                            ${item.condition_rating ? `<span class="tag condition">${item.condition_rating.replace('_', ' ')}</span>` : ''}
                            ${categories.slice(0, 2).map(cat => `<span class="tag">${cat.name}</span>`).join('')}
                        </div>
                        <div class="card-footer">
                            <div class="card-location">
                                📍 ${item.full_location || 'Not specified'}
                            </div>
                            ${value ? `<div class="card-value">${value}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderListItem(item) {
            const mediaTypeIcons = {
                movie: '🎬',
                book: '📚', 
                comic: '📖',
                music: '🎵'
            };

            const categories = item.categories || [];
            const value = item.current_value ? `$${parseFloat(item.current_value).toFixed(2)}` : '';

            return `
                <div class="list-item" onclick="viewItem(${item.id})">
                    <div class="list-image">
                        ${item.poster_url ? 
                            `<img src="${item.poster_url}" alt="${item.title}" onerror="this.parentElement.innerHTML='${mediaTypeIcons[item.media_type] || '📄'}'">` :
                            mediaTypeIcons[item.media_type] || '📄'
                        }
                    </div>
                    <div class="list-content">
                        <h3 class="list-title">${item.title}</h3>
                        <div class="list-meta">
                            ${item.creator ? `${item.creator} • ` : ''}${item.year || 'Unknown Year'} • ${item.media_type}
                        </div>
                        <div class="list-tags">
                            ${item.condition_rating ? `<span class="tag condition">${item.condition_rating.replace('_', ' ')}</span>` : ''}
                            ${categories.slice(0, 3).map(cat => `<span class="tag">${cat.name}</span>`).join('')}
                        </div>
                    </div>
                    <div class="list-actions">
                        ${value ? `<div class="card-value">${value}</div>` : ''}
                        <div class="card-location">📍 ${item.full_location || 'Not specified'}</div>
                    </div>
                </div>
            `;
        }

        function updateQuickStats(stats) {
            document.getElementById('total-items').textContent = stats.collection?.total_items || 0;
            document.getElementById('total-value').textContent = stats.collection?.total_value ? 
                `$${parseFloat(stats.collection.total_value).toLocaleString()}` : '$0';
            document.getElementById('recent-additions').textContent = stats.collection?.added_last_month || 0;
            document.getElementById('wishlist-items').textContent = stats.wishlist?.total_items || 0;
        }

        function setView(view) {
            currentView = view;
            
            // Update button states
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === view);
            });
            
            // Re-render collection
            renderCollection(collectionData);
        }

        function applySorting() {
            currentSort = document.getElementById('sort-select').value;
            currentPage = 1;
            loadCollection();
        }

        function toggleSortDirection() {
            currentDirection = currentDirection === 'desc' ? 'asc' : 'desc';
            const btn = document.getElementById('sort-direction');
            btn.textContent = currentDirection === 'desc' ? '⬇️ Desc' : '⬆️ Asc';
            currentPage = 1;
            loadCollection();
        }

        function applyFilter(type, value) {
            if (currentFilters[type] === value) {
                delete currentFilters[type];
            } else {
                currentFilters[type] = value;
            }
            
            currentPage = 1;
            loadCollection();
            updateFilterDisplay();
        }

        function clearFilter(type) {
            delete currentFilters[type];
            currentPage = 1;
            loadCollection();
            updateFilterDisplay();
        }

        function updateFilterDisplay() {
            // Update active states for all filter options
            document.querySelectorAll('.filter-option').forEach(option => {
                const filterType = option.dataset.type;
                const filterValue = option.dataset.value;
                option.classList.toggle('active', currentFilters[filterType] === filterValue);
            });
        }

        async function loadFilters() {
            try {
                // Load filter options from API
                const response = await fetch('../api/index.php?endpoint=search&action=filters');
                const data = await response.json();
                
                if (data.success) {
                    renderFilters(data.options);
                }
            } catch (error) {
                console.error('Error loading filters:', error);
            }
        }

        function renderFilters(options) {
            // Render media type filters
            const mediaTypeContainer = document.getElementById('media-type-filters');
            if (options.media_types) {
                mediaTypeContainer.innerHTML = options.media_types.map(type => `
                    <div class="filter-option" data-type="media_type" data-value="${type.media_type}" 
                         onclick="applyFilter('media_type', '${type.media_type}')">
                        <span>${type.media_type.charAt(0).toUpperCase() + type.media_type.slice(1)}</span>
                        <span class="filter-count">${type.count}</span>
                    </div>
                `).join('');
            }

            // Render category filters
            const categoryContainer = document.getElementById('category-filters');
            if (options.categories) {
                categoryContainer.innerHTML = options.categories.slice(0, 10).map(cat => `
                    <div class="filter-option" data-type="category" data-value="${cat.slug}" 
                         onclick="applyFilter('category', '${cat.slug}')">
                        <span>${cat.name}</span>
                        <span class="filter-count">${cat.usage_count || 0}</span>
                    </div>
                `).join('');
            }

            // Render location filters
            const locationContainer = document.getElementById('location-filters');
            if (options.locations) {
                locationContainer.innerHTML = options.locations.map(loc => `
                    <div class="filter-option" data-type="location" data-value="${loc.id}" 
                         onclick="applyFilter('location', '${loc.id}')">
                        <span>${loc.name}</span>
                        <span class="filter-count">${loc.item_count}</span>
                    </div>
                `).join('');
            }

            // Render condition filters
            const conditionContainer = document.getElementById('condition-filters');
            const conditions = [
                { key: 'mint', label: 'Mint' },
                { key: 'near_mint', label: 'Near Mint' },
                { key: 'very_fine', label: 'Very Fine' },
                { key: 'fine', label: 'Fine' },
                { key: 'very_good', label: 'Very Good' },
                { key: 'good', label: 'Good' },
                { key: 'fair', label: 'Fair' },
                { key: 'poor', label: 'Poor' }
            ];

            conditionContainer.innerHTML = conditions.map(cond => `
                <div class="filter-option" data-type="condition" data-value="${cond.key}" 
                     onclick="applyFilter('condition', '${cond.key}')">
                    <span>${cond.label}</span>
                    <span class="filter-count">0</span>
                </div>
            `).join('');
        }

        function setupSearchInput() {
            const searchInput = document.getElementById('global-search');
            const suggestionsContainer = document.getElementById('search-suggestions');

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        getSuggestions(query);
                    }, 300);
                } else {
                    suggestionsContainer.style.display = 'none';
                }
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.navbar-search')) {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }

        async function getSuggestions(query) {
            try {
                const response = await fetch(`../api/index.php?endpoint=search&action=suggestions&q=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success && data.suggestions.length > 0) {
                    renderSuggestions(data.suggestions);
                } else {
                    document.getElementById('search-suggestions').style.display = 'none';
                }
            } catch (error) {
                console.error('Error getting suggestions:', error);
            }
        }

        function renderSuggestions(suggestions) {
            const container = document.getElementById('search-suggestions');
            
            container.innerHTML = suggestions.map(suggestion => `
                <div class="suggestion-item" onclick="selectSuggestion('${suggestion.suggestion}')">
                    <div class="suggestion-text">${suggestion.suggestion}</div>
                    <div class="suggestion-type">${suggestion.type} - ${suggestion.media_type}</div>
                </div>
            `).join('');
            
            container.style.display = 'block';
        }

        function selectSuggestion(suggestion) {
            document.getElementById('global-search').value = suggestion;
            document.getElementById('search-suggestions').style.display = 'none';
            performSearch();
        }

        async function performSearch() {
            const query = document.getElementById('global-search').value.trim();
            
            if (query) {
                currentFilters.search = query;
            } else {
                delete currentFilters.search;
            }
            
            currentPage = 1;
            document.getElementById('search-suggestions').style.display = 'none';
            
            // Update page title
            if (query) {
                document.getElementById('page-title').textContent = `Search Results for "${query}"`;
                document.getElementById('page-subtitle').textContent = 'Items matching your search criteria';
            } else {
                document.getElementById('page-title').textContent = 'My Collection';
                document.getElementById('page-subtitle').textContent = 'Explore and manage your media collection';
            }
            
            await loadCollection();
        }

        function showLoading() {
            document.getElementById('collection-content').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    Loading...
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('collection-content').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <h3 class="empty-title">Error</h3>
                    <p class="empty-text">${message}</p>
                    <button class="btn" onclick="loadCollection()">Try Again</button>
                </div>
            `;
        }

        function renderPagination(pagination) {
            const container = document.getElementById('pagination-container');
            
            if (pagination.pages <= 1) {
                container.innerHTML = '';
                return;
            }

            let paginationHTML = '<div class="pagination">';
            
            // Previous button
            paginationHTML += `
                <button class="page-btn" ${pagination.page <= 1 ? 'disabled' : ''} 
                        onclick="goToPage(${pagination.page - 1})">‹ Previous</button>
            `;
            
            // Page numbers
            const start = Math.max(1, pagination.page - 2);
            const end = Math.min(pagination.pages, pagination.page + 2);
            
            if (start > 1) {
                paginationHTML += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
                if (start > 2) {
                    paginationHTML += `<span class="page-btn" disabled>...</span>`;
                }
            }
            
            for (let i = start; i <= end; i++) {
                paginationHTML += `
                    <button class="page-btn ${i === pagination.page ? 'active' : ''}" 
                            onclick="goToPage(${i})">${i}</button>
                `;
            }
            
            if (end < pagination.pages) {
                if (end < pagination.pages - 1) {
                    paginationHTML += `<span class="page-btn" disabled>...</span>`;
                }
                paginationHTML += `<button class="page-btn" onclick="goToPage(${pagination.pages})">${pagination.pages}</button>`;
            }
            
            // Next button
            paginationHTML += `
                <button class="page-btn" ${pagination.page >= pagination.pages ? 'disabled' : ''} 
                        onclick="goToPage(${pagination.page + 1})">Next ›</button>
            `;
            
            paginationHTML += '</div>';
            container.innerHTML = paginationHTML;
        }

        function goToPage(page) {
            currentPage = page;
            loadCollection();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updatePageInfo(data) {
            // Update any page information display
            if (data.pagination) {
                const { page, total, limit } = data.pagination;
                const start = (page - 1) * limit + 1;
                const end = Math.min(page * limit, total);
                
                document.getElementById('page-subtitle').textContent = 
                    `Showing ${start}-${end} of ${total} items`;
            }
        }

        function viewItem(itemId) {
            // Open item detail view/modal
            window.open(`item.php?id=${itemId}`, '_blank');
        }

        function showRecentAdditions() {
            currentFilters = {};
            currentSort = 'created_at';
            currentDirection = 'desc';
            document.getElementById('page-title').textContent = 'Recent Additions';
            document.getElementById('page-subtitle').textContent = 'Items added to your collection recently';
            loadCollection();
        }

        function showHighValue() {
            currentFilters = {};
            currentSort = 'value';
            currentDirection = 'desc';
            document.getElementById('page-title').textContent = 'High Value Items';
            document.getElementById('page-subtitle').textContent = 'Your most valuable collection items';
            loadCollection();
        }

        function showNeedsAttention() {
            currentFilters = { condition: 'poor' };
            document.getElementById('page-title').textContent = 'Items Needing Attention';
            document.getElementById('page-subtitle').textContent = 'Items in poor condition or missing information';
            loadCollection();
        }
    </script>
</body>
</html>