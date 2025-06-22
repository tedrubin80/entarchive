<?php
// includes/functions.php - Core Helper Functions

/**
 * Database connection with retry logic
 */
function getDbConnection($retries = 3) {
    $attempt = 0;
    
    while ($attempt < $retries) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                DB_OPTIONS ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            $attempt++;
            if ($attempt >= $retries) {
                error_log("Database connection failed after {$retries} attempts: " . $e->getMessage());
                throw $e;
            }
            usleep(100000); // Wait 100ms before retry
        }
    }
}

/**
 * Sanitize and validate input data
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Create slug from string
 */
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Format file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Validate image file
 */
function validateImageFile($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size too large. Maximum size is 10MB.'];
    }
    
    return ['valid' => true];
}

/**
 * Generate thumbnail
 */
function generateThumbnail($sourcePath, $targetPath, $width = 300, $height = 300) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceType = $imageInfo[2];
    
    // Calculate proportional dimensions
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $newWidth = round($sourceWidth * $ratio);
    $newHeight = round($sourceHeight * $ratio);
    
    // Create source image
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create target image
    $targetImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefill($targetImage, 0, 0, $transparent);
    }
    
    // Resize image
    imagecopyresampled(
        $targetImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $sourceWidth, $sourceHeight
    );
    
    // Save target image
    $result = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($targetImage, $targetPath, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($targetImage, $targetPath, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($targetImage, $targetPath);
            break;
    }
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($targetImage);
    
    return $result;
}

/**
 * Log application events
 */
function logEvent($message, $level = 'INFO', $context = []) {
    if (!defined('ENABLE_ERROR_LOGGING') || !ENABLE_ERROR_LOGGING) {
        return;
    }
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get user's IP address
 */
function getUserIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'USD') {
    if ($amount === null || $amount === '') {
        return '';
    }
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥'
    ];
    
    $symbol = $symbols[$currency] ?? '$';
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Time ago helper
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            sendJsonResponse(['error' => 'Authentication required'], 401);
        } else {
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect helper
 */
function redirect($url, $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Get media type icon
 */
function getMediaTypeIcon($mediaType) {
    $icons = [
        'movie' => '🎬',
        'book' => '📚',
        'comic' => '📖',
        'music' => '🎵'
    ];
    
    return $icons[$mediaType] ?? '📄';
}

/**
 * Format condition rating
 */
function formatCondition($condition) {
    $conditions = [
        'mint' => 'Mint',
        'near_mint' => 'Near Mint',
        'very_fine' => 'Very Fine',
        'fine' => 'Fine',
        'very_good' => 'Very Good',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor'
    ];
    
    return $conditions[$condition] ?? ucfirst(str_replace('_', ' ', $condition));
}

/**
 * Generate pagination array
 */
function generatePagination($currentPage, $totalPages, $maxLinks = 5) {
    $pagination = [];
    
    // Previous page
    if ($currentPage > 1) {
        $pagination[] = [
            'page' => $currentPage - 1,
            'label' => '‹ Previous',
            'type' => 'prev'
        ];
    }
    
    // Calculate start and end
    $start = max(1, $currentPage - floor($maxLinks / 2));
    $end = min($totalPages, $start + $maxLinks - 1);
    
    // Adjust start if we're near the end
    if ($end - $start + 1 < $maxLinks) {
        $start = max(1, $end - $maxLinks + 1);
    }
    
    // First page and ellipsis
    if ($start > 1) {
        $pagination[] = ['page' => 1, 'label' => '1', 'type' => 'page'];
        if ($start > 2) {
            $pagination[] = ['page' => null, 'label' => '...', 'type' => 'ellipsis'];
        }
    }
    
    // Page numbers
    for ($i = $start; $i <= $end; $i++) {
        $pagination[] = [
            'page' => $i,
            'label' => (string)$i,
            'type' => 'page',
            'current' => $i === $currentPage
        ];
    }
    
    // Last page and ellipsis
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination[] = ['page' => null, 'label' => '...', 'type' => 'ellipsis'];
        }
        $pagination[] = ['page' => $totalPages, 'label' => (string)$totalPages, 'type' => 'page'];
    }
    
    // Next page
    if ($currentPage < $totalPages) {
        $pagination[] = [
            'page' => $currentPage + 1,
            'label' => 'Next ›',
            'type' => 'next'
        ];
    }
    
    return $pagination;
}

/**
 * Clean up old cache files
 */
function cleanupCache($maxAge = 86400) {
    if (!defined('CACHE_DIR') || !is_dir(CACHE_DIR)) {
        return false;
    }
    
    $files = glob(CACHE_DIR . '*.cache');
    $cleaned = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < time() - $maxAge) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
    }
    
    return $cleaned;
}

/**
 * Get system statistics
 */
function getSystemStats() {
    $stats = [];
    
    // PHP version and memory
    $stats['php_version'] = PHP_VERSION;
    $stats['memory_usage'] = memory_get_usage(true);
    $stats['memory_peak'] = memory_get_peak_usage(true);
    $stats['memory_limit'] = ini_get('memory_limit');
    
    // Disk space
    $stats['disk_free'] = disk_free_space('./');
    $stats['disk_total'] = disk_total_space('./');
    
    // Cache info
    if (defined('CACHE_DIR') && is_dir(CACHE_DIR)) {
        $cacheFiles = glob(CACHE_DIR . '*.cache');
        $stats['cache_files'] = count($cacheFiles);
        $stats['cache_size'] = array_sum(array_map('filesize', $cacheFiles));
    }
    
    return $stats;
}
?>