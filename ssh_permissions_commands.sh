#!/bin/bash
# Complete File Permissions Fix for Media Management System
# Run these commands via SSH in your project root directory

echo "🔧 Starting file permissions fix..."

# ============================================================================
# DIRECTORY PERMISSIONS (755 = rwxr-xr-x)
# ============================================================================

echo "📁 Setting directory permissions..."

# Core directories - need to exist and be writable by web server
chmod 755 . 2>/dev/null || echo "⚠️  Could not set root directory permissions"
chmod 755 public/ 2>/dev/null || echo "⚠️  public/ directory missing"
chmod 755 api/ 2>/dev/null || echo "⚠️  api/ directory missing"
chmod 755 config/ 2>/dev/null || echo "⚠️  config/ directory missing"
chmod 755 includes/ 2>/dev/null || echo "⚠️  includes/ directory missing"

# Writable directories - CRITICAL for functionality
chmod 755 cache/ 2>/dev/null || mkdir -p cache/ && chmod 755 cache/
chmod 755 logs/ 2>/dev/null || mkdir -p logs/ && chmod 755 logs/
chmod 755 uploads/ 2>/dev/null || mkdir -p uploads/ && chmod 755 uploads/
chmod 755 db/ 2>/dev/null || mkdir -p db/ && chmod 755 db/
chmod 755 db/backups/ 2>/dev/null || mkdir -p db/backups/ && chmod 755 db/backups/

# Upload subdirectories
chmod 755 uploads/posters/ 2>/dev/null || mkdir -p uploads/posters/ && chmod 755 uploads/posters/
chmod 755 uploads/thumbnails/ 2>/dev/null || mkdir -p uploads/thumbnails/ && chmod 755 uploads/thumbnails/
chmod 755 uploads/temp/ 2>/dev/null || mkdir -p uploads/temp/ && chmod 755 uploads/temp/

# Cache subdirectories
chmod 755 cache/api/ 2>/dev/null || mkdir -p cache/api/ && chmod 755 cache/api/
chmod 755 cache/images/ 2>/dev/null || mkdir -p cache/images/ && chmod 755 cache/images/
chmod 755 cache/metadata/ 2>/dev/null || mkdir -p cache/metadata/ && chmod 755 cache/metadata/

echo "✅ Directory permissions set"

# ============================================================================
# CONFIGURATION FILES (644 = rw-r--r--)
# ============================================================================

echo "⚙️  Setting configuration file permissions..."

# Main configuration files
chmod 644 config.php 2>/dev/null || echo "⚠️  config.php missing"
chmod 644 index.php 2>/dev/null || echo "⚠️  index.php missing"

# Config directory files
chmod 644 config/*.php 2>/dev/null || echo "⚠️  No config/*.php files found"
chmod 644 config/config.php 2>/dev/null || echo "⚠️  config/config.php missing"
chmod 644 config/database.php 2>/dev/null || echo "⚠️  config/database.php missing"

# Sensitive configuration (600 = rw-------)
chmod 600 config/sensitive_config.php 2>/dev/null || echo "ℹ️  No sensitive_config.php found"
chmod 600 config/api_keys.php 2>/dev/null || echo "ℹ️  No api_keys.php found"

echo "✅ Configuration file permissions set"

# ============================================================================
# PUBLIC FILES (644 = rw-r--r--)
# ============================================================================

echo "🌐 Setting public file permissions..."

# Critical dashboard files
chmod 644 public/enhanced_media_dashboard.php 2>/dev/null || echo "❌ CRITICAL: enhanced_media_dashboard.php missing"
chmod 644 public/user_login.php 2>/dev/null || echo "❌ CRITICAL: user_login.php missing"
chmod 644 public/user_add_item.php 2>/dev/null || echo "⚠️  user_add_item.php missing"
chmod 644 public/user_scanner.php 2>/dev/null || echo "⚠️  user_scanner.php missing"

# Other user pages (may not exist yet)
chmod 644 public/user_collection.php 2>/dev/null || echo "ℹ️  user_collection.php not found (will create in next session)"
chmod 644 public/user_search.php 2>/dev/null || echo "ℹ️  user_search.php not found (will create in next session)"
chmod 644 public/user_stats.php 2>/dev/null || echo "ℹ️  user_stats.php not found (will create in next session)"
chmod 644 public/user_export.php 2>/dev/null || echo "ℹ️  user_export.php not found (will create in next session)"
chmod 644 public/user_settings.php 2>/dev/null || echo "ℹ️  user_settings.php not found (will create in next session)"
chmod 644 public/user_marketplace_sync.php 2>/dev/null || echo "ℹ️  user_marketplace_sync.php not found"
chmod 644 public/user_security_settings.php 2>/dev/null || echo "ℹ️  user_security_settings.php not found"

# All PHP files in public directory
chmod 644 public/*.php 2>/dev/null || echo "ℹ️  No additional PHP files in public/"

echo "✅ Public file permissions set"

# ============================================================================
# API FILES (644 = rw-r--r--)
# ============================================================================

echo "🔌 Setting API file permissions..."

# Core API files
chmod 644 api/*.php 2>/dev/null || echo "⚠️  No API files found"
chmod 644 api/api_router.php 2>/dev/null || echo "ℹ️  api_router.php not found"
chmod 644 api/api_collection.php 2>/dev/null || echo "ℹ️  api_collection.php not found"
chmod 644 api/api_search.php 2>/dev/null || echo "ℹ️  api_search.php not found"
chmod 644 api/api_integration_system.php 2>/dev/null || echo "ℹ️  api_integration_system.php not found"

# API subdirectories
chmod 644 api/integration/*.php 2>/dev/null || echo "ℹ️  No integration API files found"
chmod 644 api/handlers/*.php 2>/dev/null || echo "ℹ️  No handler API files found"

echo "✅ API file permissions set"

# ============================================================================
# INCLUDE FILES (644 = rw-r--r--)
# ============================================================================

echo "📦 Setting include file permissions..."

# Core include files
chmod 644 includes/*.php 2>/dev/null || echo "⚠️  No include files found"
chmod 644 includes/inc_functions.php 2>/dev/null || echo "ℹ️  inc_functions.php not found"
chmod 644 includes/inc_database.php 2>/dev/null || echo "ℹ️  inc_database.php not found"
chmod 644 includes/inc_auth.php 2>/dev/null || echo "ℹ️  inc_auth.php not found"
chmod 644 includes/inc_security.php 2>/dev/null || echo "ℹ️  inc_security.php not found"
chmod 644 includes/inc_2fa.php 2>/dev/null || echo "ℹ️  inc_2fa.php not found"

echo "✅ Include file permissions set"

# ============================================================================
# WEB SERVER FILES (644 = rw-r--r--)
# ============================================================================

echo "🌐 Setting web server configuration permissions..."

# Apache configuration
chmod 644 .htaccess 2>/dev/null || echo "ℹ️  .htaccess not found"

# Protect sensitive directories with .htaccess
echo "Deny from all" > cache/.htaccess 2>/dev/null
echo "Deny from all" > logs/.htaccess 2>/dev/null
echo "Deny from all" > config/.htaccess 2>/dev/null
echo "Deny from all" > includes/.htaccess 2>/dev/null

chmod 644 cache/.htaccess 2>/dev/null
chmod 644 logs/.htaccess 2>/dev/null
chmod 644 config/.htaccess 2>/dev/null
chmod 644 includes/.htaccess 2>/dev/null

# Git files (if present)
chmod 644 .gitignore 2>/dev/null || echo "ℹ️  .gitignore not found"

echo "✅ Web server file permissions set"

# ============================================================================
# DATABASE FILES (644 = rw-r--r--)
# ============================================================================

echo "🗄️  Setting database file permissions..."

# SQL files
chmod 644 db/*.sql 2>/dev/null || echo "ℹ️  No SQL files found"
chmod 644 db/schema.sql 2>/dev/null || echo "ℹ️  schema.sql not found"
chmod 644 db/migrations/*.sql 2>/dev/null || echo "ℹ️  No migration files found"

# Database backup files (more restrictive)
chmod 600 db/backups/*.sql 2>/dev/null || echo "ℹ️  No backup files found"

echo "✅ Database file permissions set"

# ============================================================================
# VERIFY CRITICAL FILES EXIST
# ============================================================================

echo ""
echo "🔍 Verifying critical files exist..."

CRITICAL_FILES=(
    "config.php"
    "public/enhanced_media_dashboard.php"
    "public/user_login.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ CRITICAL: $file missing!"
    fi
done

# ============================================================================
# OWNERSHIP FIX (if needed)
# ============================================================================

echo ""
echo "👤 Checking file ownership..."

# Get web server user (varies by system)
WEB_USER=""
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
else
    echo "⚠️  Could not determine web server user"
fi

if [ ! -z "$WEB_USER" ]; then
    echo "ℹ️  Web server user detected: $WEB_USER"
    echo "ℹ️  To fix ownership (run as root if needed):"
    echo "   chown -R $WEB_USER:$WEB_USER cache/ logs/ uploads/ db/backups/"
else
    echo "ℹ️  Manual ownership fix may be needed for writable directories"
fi

# ============================================================================
# SUMMARY REPORT
# ============================================================================

echo ""
echo "📋 PERMISSIONS FIX SUMMARY"
echo "=================================="
echo "✅ Directory permissions: 755 (rwxr-xr-x)"
echo "✅ PHP files: 644 (rw-r--r--)"
echo "✅ Sensitive config: 600 (rw-------)"
echo "✅ Writable directories created if missing"
echo "✅ Security .htaccess files added"
echo ""
echo "🔍 NEXT STEPS:"
echo "1. Test dashboard: public/enhanced_media_dashboard.php"
echo "2. Test login: public/user_login.php"
echo "3. Check error logs: tail -f logs/error.log"
echo "4. If still issues, check web server error logs"
echo ""
echo "🌐 WEB SERVER COMMANDS (if needed):"
echo "sudo systemctl restart apache2  # or nginx"
echo "sudo systemctl reload apache2   # or nginx"
echo ""

# ============================================================================
# QUICK TEST
# ============================================================================

echo "🧪 QUICK FUNCTIONALITY TEST"
echo "==========================="

# Test if we can write to writable directories
if touch cache/test_write.tmp 2>/dev/null; then
    rm cache/test_write.tmp
    echo "✅ Cache directory is writable"
else
    echo "❌ Cache directory is NOT writable"
fi

if touch logs/test_write.tmp 2>/dev/null; then
    rm logs/test_write.tmp
    echo "✅ Logs directory is writable"
else
    echo "❌ Logs directory is NOT writable"
fi

if touch uploads/test_write.tmp 2>/dev/null; then
    rm uploads/test_write.tmp
    echo "✅ Uploads directory is writable"
else
    echo "❌ Uploads directory is NOT writable"
fi

echo ""
echo "🎉 PERMISSIONS FIX COMPLETE!"
echo "Ready for development session to complete missing pages and scanner functionality."