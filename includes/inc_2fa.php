<?php
/**
 * 2FA Security Scaffolding System
 * File: includes/inc_2fa.php
 * Complete framework for Two-Factor Authentication
 */

/**
 * Time-based One-Time Password (TOTP) Generator
 * Compatible with Google Authenticator, Authy, etc.
 */
class TOTPGenerator {
    private $secretLength;
    private $base32Chars;
    
    public function __construct() {
        $this->secretLength = 32;
        $this->base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }
    
    /**
     * Generate a new secret key
     */
    public function generateSecret() {
        $secret = '';
        for ($i = 0; $i < $this->secretLength; $i++) {
            $secret .= $this->base32Chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Generate TOTP code for given secret
     */
    public function generateCode($secret, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $timeSlice = intval($timestamp / 30);
        $secretBinary = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $timeSlice), $secretBinary, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return sprintf('%06d', $code);
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyCode($secret, $code, $window = 1) {
        $timestamp = time();
        
        // Check current time slice and surrounding windows
        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $timestamp + ($i * 30);
            if ($this->generateCode($secret, $testTime) === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for authenticator app setup
     */
    public function getQRCodeURL($user, $secret, $appName = 'Media Collection') {
        $url = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($appName),
            urlencode($user),
            $secret,
            urlencode($appName)
        );
        
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
    }
    
    /**
     * Base32 decode function
     */
    private function base32Decode($secret) {
        if (empty($secret)) return '';
        
        $base32chars = $this->base32Chars;
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
                return false;
            }
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], $base32chars)) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        
        return $binaryString;
    }
}

/**
 * Backup Codes Manager
 */
class BackupCodesManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate backup codes for user
     */
    public function generateBackupCodes($userId, $count = 10) {
        $codes = [];
        
        // Generate random 8-character codes
        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= random_int(0, 9);
            }
            // Format as XXXX-XXXX
            $formattedCode = substr($code, 0, 4) . '-' . substr($code, 4, 4);
            $codes[] = $formattedCode;
        }
        
        // Store encrypted in database
        $this->storeBackupCodes($userId, $codes);
        
        return $codes;
    }
    
    /**
     * Store backup codes in database
     */
    private function storeBackupCodes($userId, $codes) {
        // Delete existing codes
        $deleteStmt = $this->pdo->prepare("DELETE FROM backup_codes WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        
        // Insert new codes
        $insertStmt = $this->pdo->prepare("
            INSERT INTO backup_codes (user_id, code_hash, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        foreach ($codes as $code) {
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $insertStmt->execute([$userId, $hash]);
        }
    }
    
    /**
     * Verify backup code
     */
    public function verifyBackupCode($userId, $code) {
        $stmt = $this->pdo->prepare("
            SELECT id, code_hash FROM backup_codes 
            WHERE user_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$userId]);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($codes as $storedCode) {
            if (password_verify($code, $storedCode['code_hash'])) {
                // Mark code as used
                $updateStmt = $this->pdo->prepare("
                    UPDATE backup_codes SET used_at = NOW() WHERE id = ?
                ");
                $updateStmt->execute([$storedCode['id']]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get remaining backup codes count
     */
    public function getRemainingCodesCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM backup_codes 
            WHERE user_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    }
}

/**
 * Two-Factor Authentication Manager
 */
class TwoFactorAuth {
    private $pdo;
    private $totpGenerator;
    private $backupCodes;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->totpGenerator = new TOTPGenerator();
        $this->backupCodes = new BackupCodesManager($pdo);
    }
    
    /**
     * Enable 2FA for user
     */
    public function enable2FA($userId) {
        $secret = $this->totpGenerator->generateSecret();
        
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                two_factor_enabled = 1,
                two_factor_secret = ?,
                two_factor_enabled_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$secret, $userId]);
        
        // Generate backup codes
        $backupCodes = $this->backupCodes->generateBackupCodes($userId);
        
        return [
            'secret' => $secret,
            'backup_codes' => $backupCodes
        ];
    }
    
    /**
     * Disable 2FA for user
     */
    public function disable2FA($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                two_factor_enabled = 0,
                two_factor_secret = NULL,
                two_factor_disabled_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$userId]);
        
        // Delete backup codes
        $deleteStmt = $this->pdo->prepare("DELETE FROM backup_codes WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        
        return true;
    }
    
    /**
     * Verify 2FA code
     */
    public function verifyCode($userId, $code) {
        $user = $this->getUser2FAInfo($userId);
        
        if (!$user || !$user['two_factor_enabled']) {
            return false;
        }
        
        // Try TOTP code first
        if ($this->totpGenerator->verifyCode($user['two_factor_secret'], $code)) {
            $this->updateLastUsed($userId);
            return true;
        }
        
        // Try backup code
        if ($this->backupCodes->verifyBackupCode($userId, $code)) {
            $this->updateLastUsed($userId);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user 2FA information
     */
    public function getUser2FAInfo($userId) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_enabled, two_factor_secret, 
                   two_factor_enabled_at, last_2fa_login
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update last 2FA usage
     */
    private function updateLastUsed($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET last_2fa_login = NOW() WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Get QR code for setup
     */
    public function getSetupQRCode($userId, $username) {
        $user = $this->getUser2FAInfo($userId);
        
        if (!$user || !$user['two_factor_secret']) {
            return null;
        }
        
        return $this->totpGenerator->getQRCodeURL(
            $username, 
            $user['two_factor_secret']
        );
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public function is2FAEnabled($userId) {
        $user = $this->getUser2FAInfo($userId);
        return $user && $user['two_factor_enabled'];
    }
    
    /**
     * Get 2FA statistics
     */
    public function get2FAStats() {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN two_factor_enabled = 1 THEN 1 END) as users_with_2fa,
                COUNT(CASE WHEN last_2fa_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_2fa_users
            FROM users
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/**
 * Session Security Manager
 */
class SessionSecurity {
    public static function regenerateSessionId() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    public static function validateSession() {
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        }
        
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        
        // Session timeout (24 hours)
        if (time() - $_SESSION['last_activity'] > 86400) {
            session_destroy();
            return false;
        }
        
        // Regenerate session ID every hour
        if (time() - $_SESSION['created_at'] > 3600) {
            self::regenerateSessionId();
            $_SESSION['created_at'] = time();
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function createSecureSession($userId, $username) {
        self::regenerateSessionId();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    public static function destroySecureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
    }
}

/**
 * Device Fingerprinting
 */
class DeviceFingerprint {
    public static function generateFingerprint() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    public static function isTrustedDevice($userId, $pdo) {
        $fingerprint = self::generateFingerprint();
        
        $stmt = $pdo->prepare("
            SELECT id FROM trusted_devices 
            WHERE user_id = ? AND fingerprint = ? AND expires_at > NOW()
        ");
        $stmt->execute([$userId, $fingerprint]);
        
        return $stmt->fetch() !== false;
    }
    
    public static function trustDevice($userId, $pdo, $days = 30) {
        $fingerprint = self::generateFingerprint();
        
        $stmt = $pdo->prepare("
            INSERT INTO trusted_devices (user_id, fingerprint, created_at, expires_at)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))
            ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$userId, $fingerprint, $days, $days]);
    }
}

/**
 * Rate Limiting for Login Attempts
 */
class LoginRateLimit {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
        // Clean old attempts
        $cleanStmt = $this->pdo->prepare("
            DELETE FROM login_attempts 
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $cleanStmt->execute([$timeWindow]);
        
        // Count recent attempts
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts FROM login_attempts 
            WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $countStmt->execute([$identifier, $timeWindow]);
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempts'] < $maxAttempts;
    }
    
    public function recordAttempt($identifier, $success = false) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (identifier, success, attempted_at, ip_address)
            VALUES (?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $identifier, 
            $success ? 1 : 0, 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
    
    public function getRemainingAttempts($identifier, $maxAttempts = 5, $timeWindow = 900) {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts FROM login_attempts 
            WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $countStmt->execute([$identifier, $timeWindow]);
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        return max(0, $maxAttempts - $result['attempts']);
    }
}

/**
 * Security Event Logger
 */
class SecurityEventLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function logEvent($eventType, $userId = null, $details = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_events (
                event_type, user_id, ip_address, user_agent, 
                details, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $eventType,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            json_encode($details)
        ]);
    }
    
    public function getRecentEvents($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT se.*, u.username 
            FROM security_events se
            LEFT JOIN users u ON se.user_id = u.id
            ORDER BY se.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Main Security Manager - Orchestrates all security features
 */
class SecurityManager {
    private $pdo;
    private $twoFactorAuth;
    private $rateLimit;
    private $eventLogger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->twoFactorAuth = new TwoFactorAuth($pdo);
        $this->rateLimit = new LoginRateLimit($pdo);
        $this->eventLogger = new SecurityEventLogger($pdo);
    }
    
    /**
     * Complete login process with security checks
     */
    public function authenticateUser($username, $password, $totpCode = null) {
        $identifier = $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
        
        // Check rate limiting
        if (!$this->rateLimit->checkRateLimit($identifier)) {
            $this->eventLogger->logEvent('RATE_LIMIT_EXCEEDED', null, [
                'username' => $username,
                'remaining_attempts' => 0
            ]);
            
            return [
                'success' => false,
                'error' => 'Too many login attempts. Please try again later.',
                'rate_limited' => true
            ];
        }
        
        // Verify credentials
        $user = $this->verifyCredentials($username, $password);
        if (!$user) {
            $this->rateLimit->recordAttempt($identifier, false);
            $this->eventLogger->logEvent('LOGIN_FAILED', null, [
                'username' => $username,
                'reason' => 'invalid_credentials'
            ]);
            
            return [
                'success' => false,
                'error' => 'Invalid username or password.',
                'remaining_attempts' => $this->rateLimit->getRemainingAttempts($identifier)
            ];
        }
        
        // Check if 2FA is required
        if ($this->twoFactorAuth->is2FAEnabled($user['id'])) {
            if (empty($totpCode)) {
                return [
                    'success' => false,
                    'requires_2fa' => true,
                    'user_id' => $user['id']
                ];
            }
            
            // Verify 2FA code
            if (!$this->twoFactorAuth->verifyCode($user['id'], $totpCode)) {
                $this->rateLimit->recordAttempt($identifier, false);
                $this->eventLogger->logEvent('2FA_FAILED', $user['id'], [
                    'code_provided' => !empty($totpCode)
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Invalid 2FA code.',
                    'requires_2fa' => true,
                    'user_id' => $user['id']
                ];
            }
        }
        
        // Successful login
        $this->rateLimit->recordAttempt($identifier, true);
        $this->eventLogger->logEvent('LOGIN_SUCCESS', $user['id'], [
            'used_2fa' => $this->twoFactorAuth->is2FAEnabled($user['id'])
        ]);
        
        // Create secure session
        SessionSecurity::createSecureSession($user['id'], $user['username']);
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    /**
     * Verify user credentials
     */
    private function verifyCredentials($username, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, password, email, full_name, role, 
                   account_locked, last_login
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        
        if ($user['account_locked']) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * Get comprehensive security dashboard data
     */
    public function getSecurityDashboard() {
        return [
            '2fa_stats' => $this->twoFactorAuth->get2FAStats(),
            'recent_events' => $this->eventLogger->getRecentEvents(10),
            'active_sessions' => $this->getActiveSessions(),
            'security_score' => $this->calculateSecurityScore()
        ];
    }
    
    /**
     * Get active sessions
     */
    private function getActiveSessions() {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM user_sessions 
            WHERE expires_at > NOW()
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    }
    
    /**
     * Calculate security score
     */
    private function calculateSecurityScore() {
        $score = 0;
        $maxScore = 100;
        
        // 2FA adoption rate (40 points max)
        $stats = $this->twoFactorAuth->get2FAStats();
        if ($stats['total_users'] > 0) {
            $adoptionRate = $stats['users_with_2fa'] / $stats['total_users'];
            $score += $adoptionRate * 40;
        }
        
        // Recent security events (30 points max)
        $recentEvents = $this->eventLogger->getRecentEvents(20);
        $failedLogins = array_filter($recentEvents, function($event) {
            return in_array($event['event_type'], ['LOGIN_FAILED', '2FA_FAILED']);
        });
        
        $failureRate = count($recentEvents) > 0 ? count($failedLogins) / count($recentEvents) : 0;
        $score += (1 - $failureRate) * 30;
        
        // Password policy compliance (30 points max)
        // This would need to be implemented based on your password policy
        $score += 25; // Placeholder
        
        return min($maxScore, round($score));
    }
    
    /**
     * Force password reset for user
     */
    public function forcePasswordReset($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                password_reset_required = 1,
                password_reset_token = ?,
                password_reset_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE id = ?
        ");
        
        $token = bin2hex(random_bytes(32));
        $stmt->execute([$token, $userId]);
        
        $this->eventLogger->logEvent('PASSWORD_RESET_FORCED', $userId);
        
        return $token;
    }
    
    /**
     * Lock user account
     */
    public function lockAccount($userId, $reason = '') {
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                account_locked = 1,
                locked_at = NOW(),
                locked_reason = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$reason, $userId]);
        
        $this->eventLogger->logEvent('ACCOUNT_LOCKED', $userId, [
            'reason' => $reason
        ]);
    }
    
    /**
     * Unlock user account
     */
    public function unlockAccount($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                account_locked = 0,
                locked_at = NULL,
                locked_reason = NULL
            WHERE id = ?
        ");
        
        $stmt->execute([$userId]);
        
        $this->eventLogger->logEvent('ACCOUNT_UNLOCKED', $userId);
    }
}

// Helper functions for templates

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Secure header output
 */
function outputSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Rate limit API calls
 */
function checkAPIRateLimit($apiKey, $limit = 100, $window = 3600) {
    // Implementation would depend on your caching system
    // This is a placeholder for the concept
    return true;
}
?>