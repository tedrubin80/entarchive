# 📁 Complete File & Folder Structure for Media Collection System

## Root Directory Structure

```
media-collection/
├── 📁 admin/                          # Admin interface files
├── 📁 api/                            # API endpoints and handlers
├── 📁 assets/                         # Static assets (CSS, JS, images)
├── 📁 cache/                          # File-based cache storage
├── 📁 config/                         # Configuration files
├── 📁 db/                             # Database files and migrations
├── 📁 logs/                           # Application logs
├── 📁 public/                         # Public-facing pages
├── 📁 scripts/                        # Utility and maintenance scripts
├── 📁 uploads/                        # User uploaded files
├── 📁 vendor/                         # Third-party libraries (if using Composer)
├── 📄 .htaccess                       # Apache configuration
├── 📄 .gitignore                      # Git ignore rules
├── 📄 composer.json                   # PHP dependencies (optional)
├── 📄 config.php                      # Main configuration file
├── 📄 index.php                       # Main entry point
└── 📄 README.md                       # Project documentation
```

## Detailed File Structure

### 📁 `/admin/` - Administrative Interface
```
admin/
├── 📄 dashboard.php                   # Main admin dashboard
├── 📄 login.php                       # Admin login page
├── 📄 logout.php                      # Admin logout handler
├── 📄 categories.php                  # Category management interface
├── 📄 locations.php                   # Storage location management
├── 📄 wishlist.php                    # Wishlist management interface
├── 📄 users.php                       # User management (if multi-user)
├── 📄 settings.php                    # System settings
├── 📄 reports.php                     # Analytics and reports
├── 📄 import.php                      # Data import interface
├── 📄 export.php                      # Data export interface
├── 📄 maintenance.php                 # System maintenance tools
└── 📄 api_config.php                  # API key configuration
```

### 📁 `/api/` - API Layer
```
api/
├── 📄 index.php                       # Main API router
├── 📁 handlers/                       # API endpoint handlers
│   ├── 📄 collection.php              # Collection CRUD operations
│   ├── 📄 wishlist.php                # Wishlist operations
│   ├── 📄 categories.php              # Category operations
│   ├── 📄 locations.php               # Storage location operations
│   ├── 📄 search.php                  # Search functionality
│   ├── 📄 stats.php                   # Statistics and analytics
│   ├── 📄 import.php                  # Data import handlers
│   ├── 📄 export.php                  # Data export handlers
│   ├── 📄 loans.php                   # Loan tracking
│   ├── 📄 price_check.php             # Price monitoring
│   └── 📄 metadata_lookup.php         # External API metadata lookup
├── 📁 integrations/                   # External API integrations
│   ├── 📄 MediaAPIManager.php         # Main API integration manager
│   ├── 📄 OMDBIntegration.php         # OMDB API integration
│   ├── 📄 GoogleBooksIntegration.php  # Google Books API
│   ├── 📄 DiscogsIntegration.php      # Discogs API
│   ├── 📄 ComicVineIntegration.php    # ComicVine API
│   └── 📄 BarcodeProcessor.php        # Barcode processing utilities
├── 📄 barcode_scanner.php             # Barcode scanning interface
├── 📄 books.php                       # Book-specific API operations
├── 📄 comics.php                      # Comic-specific API operations  
├── 📄 movies.php                      # Movie-specific API operations
├── 📄 music.php                       # Music-specific API operations
├── 📄 fetch.php                       # Generic fetch operations
└── 📄 filter.php                      # Filtering operations
```

### 📁 `/assets/` - Static Assets
```
assets/
├── 📁 css/                           # Stylesheets
│   ├── 📄 admin.css                  # Admin interface styles
│   ├── 📄 public.css                 # Public interface styles
│   ├── 📄 scanner.css                # Barcode scanner styles
│   └── 📄 components.css             # Reusable component styles
├── 📁 js/                            # JavaScript files
│   ├── 📄 admin.js                   # Admin interface functionality
│   ├── 📄 collection.js              # Collection management
│   ├── 📄 search.js                  # Search functionality
│   ├── 📄 scanner.js                 # Barcode scanning
│   ├── 📄 wishlist.js                # Wishlist management
│   └── 📄 utils.js                   # Utility functions
├── 📁 images/                        # Static images
│   ├── 📄 logo.png                   # Application logo
│   ├── 📄 placeholder.jpg            # Default poster placeholder
│   ├── 📄 icons/                     # Icon files
│   └── 📄 backgrounds/               # Background images
└── 📁 fonts/                         # Custom fonts (if any)
```

### 📁 `/cache/` - Cache Storage
```
cache/
├── 📄 .htaccess                      # Prevent direct access
├── 📁 api/                           # API response cache
├── 📁 images/                        # Cached/resized images
├── 📁 metadata/                      # External API metadata cache
└── 📄 README.md                      # Cache directory info
```

### 📁 `/config/` - Configuration Files
```
config/
├── 📄 database.php                   # Database configuration
├── 📄 api_keys.php                   # External API keys
├── 📄 security.php                   # Security settings
├── 📄 cache.php                      # Cache configuration
├── 📄 email.php                      # Email settings (for notifications)
└── 📄 app_settings.php               # Application-specific settings
```

### 📁 `/db/` - Database Files
```
db/
├── 📄 schema.sql                     # Complete database schema
├── 📄 initial_data.sql               # Initial/seed data
├── 📄 sample_data.sql                # Sample data for testing
├── 📁 migrations/                    # Database migration files
│   ├── 📄 001_create_initial_tables.sql
│   ├── 📄 002_add_wishlist_features.sql
│   ├── 📄 003_add_location_management.sql
│   └── 📄 004_add_category_enhancements.sql
├── 📁 backups/                       # Database backup storage
└── 📄 migration_runner.php           # Migration execution script
```

### 📁 `/logs/` - Application Logs
```
logs/
├── 📄 .htaccess                      # Prevent direct access
├── 📄 app.log                        # General application log
├── 📄 api.log                        # API request/response log
├── 📄 error.log                      # Error log
├── 📄 security.log                   # Security events log
└── 📄 performance.log                # Performance metrics log
```

### 📁 `/public/` - Public Interface
```
public/
├── 📄 index.php                      # Main collection browser
├── 📄 add.php                        # Add new item form
├── 📄 edit.php                       # Edit item form
├── 📄 item.php                       # Individual item details
├── 📄 search.php                     # Advanced search interface
├── 📄 scan.php                       # Quick barcode scan access
├── 📄 reports.php                    # Collection reports
├── 📄 export.php                     # Data export interface
└── 📄 login.php                      # Public login (if needed)
```

### 📁 `/scripts/` - Utility Scripts
```
scripts/
├── 📄 migrate_csv.php                # CSV migration script
├── 📄 backup_database.php            # Database backup utility
├── 📄 cleanup_cache.php              # Cache cleanup utility
├── 📄 update_values.php              # Bulk value update script
├── 📄 generate_thumbnails.php        # Image thumbnail generator
├── 📄 price_monitor.php              # Automated price checking
├── 📄 send_notifications.php         # Notification sender
└── 📄 maintenance_runner.php         # Maintenance task runner
```

### 📁 `/uploads/` - User Uploads
```
uploads/
├── 📄 .htaccess                      # Upload security rules
├── 📁 csv/                           # CSV import files
├── 📁 images/                        # User uploaded images
│   ├── 📁 posters/                   # Movie/book posters
│   ├── 📁 covers/                    # Album covers
│   └── 📁 thumbnails/                # Generated thumbnails
└── 📁 temp/                          # Temporary upload processing
```

## 📋 Core Configuration Files

### 📄 `config.php` - Main Configuration
```php
<?php
// Main application configuration
define('APP_NAME', 'Media Collection Manager');
define('APP_VERSION', '2.0.0');
define('BASE_URL', 'http://localhost/media-collection');

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'media_collection');

// External API keys
define('OMDB_API_KEY', 'your_omdb_key_here');
define('GOOGLE_BOOKS_KEY', 'your_google_books_key_here');
define('DISCOGS_TOKEN', 'your_discogs_token_here');
define('COMICVINE_KEY', 'your_comicvine_key_here');

// Security settings
define('SESSION_TIMEOUT', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('ENABLE_2FA', false);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600);
define('CACHE_DIR', __DIR__ . '/cache/');

// Upload settings
define('MAX_UPLOAD_SIZE', '10M');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Feature flags
define('ENABLE_BARCODE_SCANNING', true);
define('ENABLE_PRICE_MONITORING', true);
define('ENABLE_LOAN_TRACKING', true);
define('ENABLE_WISHLIST', true);

// Load helper functions
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/cache.php';
?>
```

### 📄 `.htaccess` - Apache Configuration
```apache
# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Hide sensitive files
<FilesMatch "\.(sql|log|md|json|lock)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect configuration directories
<DirectoryMatch "^.*/\.(git|cache|logs|config)">
    Order allow,deny
    Deny from all
</DirectoryMatch>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    ExpiresByType application/x-shockwave-flash "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
</IfModule>

# URL rewriting for clean URLs
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php?endpoint=$1 [QSA,L]
```

### 📄 `.gitignore` - Git Ignore Rules
```gitignore
# Configuration files with sensitive data
config/api_keys.php
config/database.php

# Cache and temporary files
cache/
logs/
uploads/temp/

# User uploaded content
uploads/images/
uploads/csv/

# Database backups
db/backups/

# Vendor directory (if using Composer)
vendor/

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS generated files
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db

# Environment files
.env
.env.local
.env.production

# Node modules (if using any frontend build tools)
node_modules/
npm-debug.log

# Backup files
*.bak
*.backup
*.old
```

## 🚀 Installation Order

### Phase 1: Basic Setup
1. Create directory structure
2. Set up `config.php` with database credentials
3. Create database and run `schema.sql`
4. Configure web server (Apache/Nginx)

### Phase 2: API Integration
1. Obtain API keys for external services
2. Configure API integrations
3. Test barcode scanning functionality
4. Set up cache directories with proper permissions

### Phase 3: Data Migration
1. Run CSV migration script for existing data
2. Set up initial categories and locations
3. Configure admin user accounts
4. Test core functionality

### Phase 4: Advanced Features
1. Configure automated price monitoring
2. Set up backup and maintenance scripts
3. Configure email notifications (if needed)
4. Optimize performance and caching

## 📝 Required Permissions

### Directory Permissions
- `/cache/` → 755 (web server writable)
- `/logs/` → 755 (web server writable) 
- `/uploads/` → 755 (web server writable)
- `/db/backups/` → 755 (web server writable)

### File Permissions
- Configuration files → 644
- PHP scripts → 644
- Sensitive config → 600 (owner only)
- Cache files → 644

## 🔑 External Dependencies

### Required PHP Extensions
- `pdo_mysql` - Database connectivity
- `curl` - External API requests
- `gd` or `imagick` - Image processing
- `json` - JSON processing
- `mbstring` - String handling

### Optional Dependencies
- Composer (for advanced package management)
- Redis (for improved caching)
- Elasticsearch (for advanced search)
- Node.js (for frontend build tools)

This complete file structure provides a robust, scalable foundation for your media collection management system with all the advanced features you requested!