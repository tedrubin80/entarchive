<?php
// includes/cache.php - Cache Management System

/**
 * Simple file-based cache implementation
 */
class SimpleCache {
    private $cacheDir;
    private $defaultTtl;
    
    public function __construct($cacheDir = null, $defaultTtl = 3600) {
        $this->cacheDir = $cacheDir ?: (defined('CACHE_DIR') ? CACHE_DIR : sys_get_temp_dir() . '/cache/');
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get item from cache
     */
    public function get($key, $default = null) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if (!$data || !isset($data['expires']) || !isset($data['value'])) {
            $this->delete($key);
            return $default;
        }
        
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * Set item in cache
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $data = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        $file = $this->getFilePath($key);
        $result = file_put_contents($file, serialize($data), LOCK_EX) !== false;
        
        if ($result) {
            chmod($file, 0644);
        }
        
        return $result;
    }
    
    /**
     * Delete item from cache
     */
    public function delete($key) {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Check if item exists in cache
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Get multiple items from cache
     */
    public function getMultiple($keys, $default = null) {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }
    
    /**
     * Set multiple items in cache
     */
    public function setMultiple($values, $ttl = null) {
        $results = [];
        
        foreach ($values as $key => $value) {
            $results[$key] = $this->set($key, $value, $ttl);
        }
        
        return $results;
    }
    
    /**
     * Delete multiple items from cache
     */
    public function deleteMultiple($keys) {
        $results = [];
        
        foreach ($keys as $key) {
            $results[$key] = $this->delete($key);
        }
        
        return $results;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $data = unserialize(file_get_contents($file));
            if ($data && isset($data['expires'])) {
                if ($data['expires'] > 0 && $data['expires'] < time()) {
                    $expiredCount++;
                } else {
                    $validCount++;
                }
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $validCount,
            'expired_files' => $expiredCount,
            'total_size' => $totalSize,
            'cache_dir' => $this->cacheDir
        ];
    }
    
    /**
     * Cleanup expired cache files
     */
    public function cleanup() {
        $files = glob($this->cacheDir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            
            if (!$data || !isset($data['expires'])) {
                unlink($file);
                $cleaned++;
                continue;
            }
            
            if ($data['expires'] > 0 && $data['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get or set with callback
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Increment a cached value
     */
    public function increment($key, $step = 1) {
        $value = $this->get($key, 0);
        $newValue = (int)$value + $step;
        $this->set($key, $newValue);
        
        return $newValue;
    }
    
    /**
     * Decrement a cached value
     */
    public function decrement($key, $step = 1) {
        $value = $this->get($key, 0);
        $newValue = (int)$value - $step;
        $this->set($key, $newValue);
        
        return $newValue;
    }
    
    /**
     * Get file path for cache key
     */
    private function getFilePath($key) {
        $hashedKey = hash('sha256', $key);
        return $this->cacheDir . $hashedKey . '.cache';
    }
}

/**
 * Cache manager with different cache drivers
 */
class CacheManager {
    private static $instance = null;
    private $driver;
    private $config;
    
    private function __construct($config = []) {
        $this->config = array_merge([
            'driver' => 'file',
            'ttl' => 3600,
            'prefix' => 'app_',
            'file_cache_dir' => defined('CACHE_DIR') ? CACHE_DIR : sys_get_temp_dir() . '/cache/'
        ], $config);
        
        $this->initializeDriver();
    }
    
    public static function getInstance($config = []) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }
    
    private function initializeDriver() {
        switch ($this->config['driver']) {
            case 'redis':
                if (class_exists('Redis')) {
                    $this->driver = new RedisCache($this->config);
                } else {
                    // Fallback to file cache
                    $this->driver = new SimpleCache($this->config['file_cache_dir'], $this->config['ttl']);
                }
                break;
            
            case 'memcached':
                if (class_exists('Memcached')) {
                    $this->driver = new MemcachedCache($this->config);
                } else {
                    // Fallback to file cache
                    $this->driver = new SimpleCache($this->config['file_cache_dir'], $this->config['ttl']);
                }
                break;
            
            default:
                $this->driver = new SimpleCache($this->config['file_cache_dir'], $this->config['ttl']);
        }
    }
    
    public function get($key, $default = null) {
        return $this->driver->get($this->prefixKey($key), $default);
    }
    
    public function set($key, $value, $ttl = null) {
        return $this->driver->set($this->prefixKey($key), $value, $ttl);
    }
    
    public function delete($key) {
        return $this->driver->delete($this->prefixKey($key));
    }
    
    public function has($key) {
        return $this->driver->has($this->prefixKey($key));
    }
    
    public function clear() {
        return $this->driver->clear();
    }
    
    public function remember($key, $callback, $ttl = null) {
        return $this->driver->remember($this->prefixKey($key), $callback, $ttl);
    }
    
    public function tags($tags) {
        return new TaggedCache($this->driver, $tags, $this->config['prefix']);
    }
    
    private function prefixKey($key) {
        return $this->config['prefix'] . $key;
    }
    
    public function getStats() {
        if (method_exists($this->driver, 'getStats')) {
            return $this->driver->getStats();
        }
        
        return [];
    }
}

/**
 * Tagged cache for better cache invalidation
 */
class TaggedCache {
    private $cache;
    private $tags;
    private $prefix;
    
    public function __construct($cache, $tags, $prefix = '') {
        $this->cache = $cache;
        $this->tags = is_array($tags) ? $tags : [$tags];
        $this->prefix = $prefix;
    }
    
    public function get($key, $default = null) {
        return $this->cache->get($this->taggedKey($key), $default);
    }
    
    public function set($key, $value, $ttl = null) {
        $result = $this->cache->set($this->taggedKey($key), $value, $ttl);
        
        // Update tag indices
        foreach ($this->tags as $tag) {
            $this->addToTagIndex($tag, $key);
        }
        
        return $result;
    }
    
    public function delete($key) {
        return $this->cache->delete($this->taggedKey($key));
    }
    
    public function flush() {
        foreach ($this->tags as $tag) {
            $this->flushTag($tag);
        }
    }
    
    private function flushTag($tag) {
        $indexKey = $this->prefix . 'tag_index_' . $tag;
        $keys = $this->cache->get($indexKey, []);
        
        foreach ($keys as $key) {
            $this->cache->delete($this->taggedKey($key));
        }
        
        $this->cache->delete($indexKey);
    }
    
    private function addToTagIndex($tag, $key) {
        $indexKey = $this->prefix . 'tag_index_' . $tag;
        $keys = $this->cache->get($indexKey, []);
        
        if (!in_array($key, $keys)) {
            $keys[] = $key;
            $this->cache->set($indexKey, $keys);
        }
    }
    
    private function taggedKey($key) {
        $tagString = implode(':', $this->tags);
        return $this->prefix . 'tagged_' . hash('md5', $tagString) . '_' . $key;
    }
}

/**
 * Cache warming utility
 */
class CacheWarmer {
    private $cache;
    
    public function __construct($cache = null) {
        $this->cache = $cache ?: CacheManager::getInstance();
    }
    
    public function warmCollection() {
        $pdo = getDbConnection();
        
        // Warm collection stats
        $stats = $this->calculateCollectionStats($pdo);
        $this->cache->set('collection_stats', $stats, 3600);
        
        // Warm popular searches
        $this->warmPopularSearches($pdo);
        
        // Warm category counts
        $this->warmCategoryCounts($pdo);
        
        logEvent('Cache warmed successfully', 'INFO');
    }
    
    private function calculateCollectionStats($pdo) {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    COUNT(DISTINCT media_type) as media_types,
                    SUM(COALESCE(current_value, 0)) as total_value,
                    AVG(COALESCE(current_value, 0)) as avg_value
                FROM collection 
                WHERE status = 'owned'";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetch();
    }
    
    private function warmPopularSearches($pdo) {
        $sql = "SELECT title, creator FROM collection 
                WHERE status = 'owned' 
                ORDER BY times_accessed DESC 
                LIMIT 50";
        
        $stmt = $pdo->query($sql);
        $popular = $stmt->fetchAll();
        
        $this->cache->set('popular_items', $popular, 7200);
    }
    
    private function warmCategoryCounts($pdo) {
        $sql = "SELECT c.id, c.name, COUNT(cc.collection_id) as count
                FROM categories c
                LEFT JOIN collection_categories cc ON c.id = cc.category_id
                WHERE c.is_active = 1
                GROUP BY c.id";
        
        $stmt = $pdo->query($sql);
        $counts = $stmt->fetchAll();
        
        foreach ($counts as $category) {
            $this->cache->set("category_count_{$category['id']}", $category['count'], 3600);
        }
    }
}

// Global cache instance
if (!function_exists('cache')) {
    function cache() {
        return CacheManager::getInstance();
    }
}
?>