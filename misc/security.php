<?php
// includes/security.php - Security Utilities

/**
 * Enhanced session management with security features
 */
class SecureSession {
    private static $started = false;
    
    public static function start() {
        if (self::$started) {
            return;
        }
        
        // Configure session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT ?? 7200);
        
        session_start();
        self::$started = true;
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > (SESSION_TIMEOUT ?? 7200))) {
            self::destroy();
            return;
        }
        
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        // Validate session fingerprint
        self::validateFingerprint();
    }
    
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        self::$started = false;
    }
    
    private static function validateFingerprint() {
        $fingerprint = self::generateFingerprint();
        
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $fingerprint;
        } else if ($_SESSION['fingerprint'] !== $fingerprint) {
            // Potential session hijacking
            logEvent('Session fingerprint mismatch', 'WARNING', [
                'ip' => getUserIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_id' => session_id()
            ]);
            self::destroy();
        }
    }
    
    private static function generateFingerprint() {
        $data = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
        
        return hash('sha256', implode('|', $data));
    }
}

/**
 * Rate limiting for login attempts and API calls
 */
class RateLimiter {
    private static $attempts = [];
    
    public static function checkLimit($identifier, $maxAttempts = 5, $window = 900) {
        $now = time();
        
        if (!isset(self::$attempts[$identifier])) {
            self::$attempts[$identifier] = [];
        }
        
        // Remove old attempts outside the window
        self::$attempts[$identifier] = array_filter(
            self::$attempts[$identifier],
            function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            }
        );
        
        return count(self::$attempts[$identifier]) < $maxAttempts;
    }
    
    public static function recordAttempt($identifier) {
        if (!isset(self::$attempts[$identifier])) {
            self::$attempts[$identifier] = [];
        }
        self::$attempts[$identifier][] = time();
    }
    
    public static function getRemainingAttempts($identifier, $maxAttempts = 5) {
        if (!isset(self::$attempts[$identifier])) {
            return $maxAttempts;
        }
        
        return max(0, $maxAttempts - count(self::$attempts[$identifier]));
    }
    
    public static function getTimeUntilReset($identifier, $window = 900) {
        if (!isset(self::$attempts[$identifier]) || empty(self::$attempts[$identifier])) {
            return 0;
        }
        
        $oldestAttempt = min(self::$attempts[$identifier]);
        $resetTime = $oldestAttempt + $window;
        
        return max(0, $resetTime - time());
    }
}

/**
 * CSRF token management
 */
class CSRFProtection {
    const TOKEN_NAME = 'csrf_token';
    const TOKEN_LENGTH = 32;
    
    public static function generateToken() {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        return $token;
    }
    
    public static function validateToken($token) {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        $valid = hash_equals($_SESSION[self::TOKEN_NAME], $token);
        
        // Remove token after validation (one-time use)
        unset($_SESSION[self::TOKEN_NAME]);
        
        return $valid;
    }
    
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . $token . '">';
    }
    
    public static function requireValidToken() {
        $token = $_POST[self::TOKEN_NAME] ?? $_GET[self::TOKEN_NAME] ?? '';
        
        if (!self::validateToken($token)) {
            logEvent('CSRF token validation failed', 'WARNING', [
                'ip' => getUserIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? ''
            ]);
            
            if (isAjaxRequest()) {
                sendJsonResponse(['error' => 'Invalid security token'], 403);
            } else {
                die('Invalid security token. Please refresh the page and try again.');
            }
        }
    }
}

/**
 * Password security utilities
 */
class PasswordSecurity {
    const MIN_LENGTH = 8;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SYMBOLS = true;
    
    public static function hash($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
    
    public static function validate($password) {
        $errors = [];
        
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . self::MIN_LENGTH . ' characters long';
        }
        
        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (self::REQUIRE_SYMBOLS && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculateStrength($password)
        ];
    }
    
    private static function calculateStrength($password) {
        $score = 0;
        $length = strlen($password);
        
        // Length bonus
        $score += min($length * 4, 25);
        
        // Character variety bonus
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Repetition penalty
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10;
        
        // Common patterns penalty
        if (preg_match('/123|abc|qwe|password/i', $password)) $score -= 15;
        
        if ($score < 30) return 'weak';
        if ($score < 60) return 'medium';
        if ($score < 80) return 'strong';
        return 'very_strong';
    }
    
    public static function generateSecure($length = 16) {
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $charset[random_int(0, strlen($charset) - 1)];
        }
        
        return $password;
    }
}

/**
 * Input validation and sanitization
 */
class InputValidator {
    public static function validateEmail($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateURL($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return false;
        }
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
    
    public static function validateFloat($value, $min = null, $max = null) {
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if ($float === false) {
            return false;
        }
        
        if ($min !== null && $float < $min) {
            return false;
        }
        
        if ($max !== null && $float > $max) {
            return false;
        }
        
        return $float;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function validateEnum($value, array $allowedValues, $caseSensitive = true) {
        if (!$caseSensitive) {
            $value = strtolower($value);
            $allowedValues = array_map('strtolower', $allowedValues);
        }
        
        return in_array($value, $allowedValues, true);
    }
    
    public static function sanitizeFileName($filename) {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
}

/**
 * File upload security
 */
class SecureUpload {
    private static $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'txt', 'csv'],
        'archive' => ['zip']
    ];
    
    private static $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'js', 'exe', 'bat', 'cmd', 'scr'
    ];
    
    public static function validateFile($file, $category = 'image') {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . self::getUploadErrorMessage($file['error']);
        }
        
        // Check file size
        $maxSize = self::getMaxFileSize($category);
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . formatFileSize($maxSize);
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, self::$dangerousExtensions)) {
            $errors[] = 'File type not allowed for security reasons';
        }
        
        if (!empty(self::$allowedTypes[$category]) && 
            !in_array($extension, self::$allowedTypes[$category])) {
            $errors[] = 'File type not allowed. Allowed types: ' . 
                       implode(', ', self::$allowedTypes[$category]);
        }
        
        // Validate MIME type
        if ($category === 'image') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'Invalid image file format';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function saveFile($file, $targetDir, $newName = null) {
        $validation = self::validateFile($file);
        
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ['success' => false, 'errors' => ['Unable to create target directory']];
            }
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $newName ? $newName . '.' . $extension : 
                   uniqid() . '_' . InputValidator::sanitizeFileName($file['name']);
        
        $targetPath = $targetDir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Set secure permissions
            chmod($targetPath, 0644);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath,
                'size' => $file['size']
            ];
        } else {
            return ['success' => false, 'errors' => ['Failed to move uploaded file']];
        }
    }
    
    private static function getUploadErrorMessage($error) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$error] ?? 'Unknown upload error';
    }
    
    private static function getMaxFileSize($category) {
        $sizes = [
            'image' => 10 * 1024 * 1024,    // 10MB
            'document' => 50 * 1024 * 1024, // 50MB
            'archive' => 100 * 1024 * 1024  // 100MB
        ];
        
        return $sizes[$category] ?? 5 * 1024 * 1024; // Default 5MB
    }
}

/**
 * Audit logging for security events
 */
class SecurityAudit {
    public static function logLogin($username, $success, $details = []) {
        $event = [
            'event_type' => 'login',
            'username' => $username,
            'success' => $success,
            'ip_address' => getUserIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        ];
        
        self::writeAuditLog($event);
    }
    
    public static function logPasswordChange($username, $success = true) {
        $event = [
            'event_type' => 'password_change',
            'username' => $username,
            'success' => $success,
            'ip_address' => getUserIP(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeAuditLog($event);
    }
    
    public static function logPrivilegedAction($action, $username, $details = []) {
        $event = [
            'event_type' => 'privileged_action',
            'action' => $action,
            'username' => $username,
            'ip_address' => getUserIP(),
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        ];
        
        self::writeAuditLog($event);
    }
    
    public static function logSecurityViolation($violation, $details = []) {
        $event = [
            'event_type' => 'security_violation',
            'violation' => $violation,
            'ip_address' => getUserIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        ];
        
        self::writeAuditLog($event);
    }
    
    private static function writeAuditLog($event) {
        $logFile = defined('SECURITY_LOG_FILE') ? SECURITY_LOG_FILE : 
                  (defined('LOG_DIR') ? LOG_DIR . '/security.log' : 'logs/security.log');
        
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = json_encode($event) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to main application log
        logEvent("Security Event: {$event['event_type']}", 'SECURITY', $event);
    }
}

// Initialize secure session on include
SecureSession::start();
?>