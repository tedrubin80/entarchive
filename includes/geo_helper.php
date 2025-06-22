<?php
class GeoLocationHelper {
    private static $cache_duration = 3600; // 1 hour
    
    public static function checkAccess($ip) {
        // Check session cache first
        if (isset($_SESSION['geo_check']) && 
            $_SESSION['geo_check']['timestamp'] > time() - self::$cache_duration) {
            return $_SESSION['geo_check']['allowed'];
        }
        
        // Use cURL with timeout for better performance
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://ip-api.com/json/{$ip}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 second connection timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $allowed = false;
        
        if ($response && $httpCode == 200) {
            $geo = json_decode($response);
            $allowed = in_array($geo->countryCode ?? '', ['US', 'CA']);
        } else {
            // Fallback: allow access if API is down (or implement whitelist)
            error_log("Geolocation API failed for IP: {$ip}");
            $allowed = true; // or false for stricter security
        }
        
        // Cache result in session
        $_SESSION['geo_check'] = [
            'allowed' => $allowed,
            'timestamp' => time()
        ];
        
        return $allowed;
    }
}