<?php
// Save this as geo_helper.php (or includes/geo_helper.php)
class GeoLocationHelper {
    private static $cache_duration = 3600; // 1 hour
    
    public static function checkAccess($ip) {
        // For debugging/development - temporarily always allow access
        // Remove this line once everything is working
        return true;
        
        // Check session cache first
        if (isset($_SESSION['geo_check']) && 
            $_SESSION['geo_check']['timestamp'] > time() - self::$cache_duration) {
            return $_SESSION['geo_check']['allowed'];
        }
        
        // Skip check for localhost/private IPs
        if (self::isLocalIP($ip)) {
            $_SESSION['geo_check'] = [
                'allowed' => true,
                'timestamp' => time()
            ];
            return true;
        }
        
        // Use cURL with timeout for better performance
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 second connection timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'Media Collection Manager v1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $allowed = false;
        
        if ($response && $httpCode == 200) {
            $geo = json_decode($response, true);
            if ($geo && isset($geo['countryCode'])) {
                $allowed = in_array($geo['countryCode'], ['US', 'CA']);
                
                // Log the result for debugging
                error_log("Geolocation check for IP {$ip}: Country={$geo['countryCode']}, Allowed=" . ($allowed ? 'true' : 'false'));
            } else {
                error_log("Geolocation API returned invalid data for IP: {$ip}");
                $allowed = true; // Default to allow if API returns bad data
            }
        } else {
            // Fallback: allow access if API is down
            error_log("Geolocation API failed for IP: {$ip}, HTTP Code: {$httpCode}, Error: {$error}");
            $allowed = true; // Default to allow if API is down
        }
        
        // Cache result in session
        $_SESSION['geo_check'] = [
            'allowed' => $allowed,
            'timestamp' => time(),
            'ip' => $ip,
            'country' => isset($geo['countryCode']) ? $geo['countryCode'] : 'unknown'
        ];
        
        return $allowed;
    }
    
    /**
     * Check if IP is local/private
     */
    private static function isLocalIP($ip) {
        // Common local/private IP patterns
        $localPatterns = [
            '127.0.0.1',
            '::1',
            'localhost'
        ];
        
        if (in_array($ip, $localPatterns)) {
            return true;
        }
        
        // Check private IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get cached geolocation info
     */
    public static function getCachedInfo() {
        return $_SESSION['geo_check'] ?? null;
    }
    
    /**
     * Clear geolocation cache
     */
    public static function clearCache() {
        unset($_SESSION['geo_check']);
    }
}