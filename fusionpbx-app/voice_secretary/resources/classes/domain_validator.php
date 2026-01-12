<?php
/**
 * Domain UUID Validator for Voice Secretary
 * 
 * ⚠️ MULTI-TENANT: This class ensures domain_uuid cannot be manipulated via request.
 * ALWAYS use $_SESSION['domain_uuid'] - NEVER trust user input.
 *
 * @package voice_secretary
 */

class domain_validator {
    
    /**
     * Get the current domain UUID from session.
     * 
     * ⚠️ NEVER use $_POST, $_GET, or $_REQUEST for domain_uuid!
     * 
     * @return string|null The domain UUID or null if not set
     */
    public static function get_domain_uuid() {
        if (isset($_SESSION['domain_uuid']) && !empty($_SESSION['domain_uuid'])) {
            return $_SESSION['domain_uuid'];
        }
        return null;
    }
    
    /**
     * Validate that domain_uuid is set and valid.
     * 
     * @throws Exception If domain_uuid is not set
     * @return string The domain UUID
     */
    public static function require_domain_uuid() {
        $domain_uuid = self::get_domain_uuid();
        
        if (!$domain_uuid) {
            throw new Exception("domain_uuid is required for multi-tenant isolation");
        }
        
        // Validate UUID format
        if (!self::is_valid_uuid($domain_uuid)) {
            throw new Exception("Invalid domain_uuid format");
        }
        
        return $domain_uuid;
    }
    
    /**
     * Validate UUID format.
     * 
     * @param string $uuid The UUID to validate
     * @return bool True if valid UUID format
     */
    public static function is_valid_uuid($uuid) {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }
    
    /**
     * Check if the request is trying to manipulate domain_uuid.
     * 
     * ⚠️ SECURITY: Logs and rejects any attempt to override domain_uuid via request.
     * 
     * @return bool True if manipulation was attempted
     */
    public static function check_manipulation_attempt() {
        $request_domain = null;
        
        // Check POST
        if (isset($_POST['domain_uuid']) && !empty($_POST['domain_uuid'])) {
            $request_domain = $_POST['domain_uuid'];
        }
        
        // Check GET
        if (isset($_GET['domain_uuid']) && !empty($_GET['domain_uuid'])) {
            $request_domain = $_GET['domain_uuid'];
        }
        
        // If request contains domain_uuid and it differs from session, it's manipulation
        if ($request_domain !== null) {
            $session_domain = self::get_domain_uuid();
            
            if ($session_domain && $request_domain !== $session_domain) {
                // Log the attempt
                error_log(
                    "[SECURITY] Domain UUID manipulation attempt detected! " .
                    "Session: {$session_domain}, Request: {$request_domain}, " .
                    "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                );
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add domain_uuid to SQL parameters safely.
     * 
     * @param array &$parameters Reference to parameters array
     * @param string $key Parameter key name (default: 'domain_uuid')
     * @return string The domain UUID that was added
     */
    public static function add_to_parameters(&$parameters, $key = 'domain_uuid') {
        $domain_uuid = self::require_domain_uuid();
        $parameters[$key] = $domain_uuid;
        return $domain_uuid;
    }
    
    /**
     * Build a WHERE clause fragment for domain_uuid.
     * 
     * @param string $table_alias Optional table alias (e.g., 's' for 's.domain_uuid')
     * @return string SQL fragment like "domain_uuid = :domain_uuid"
     */
    public static function where_clause($table_alias = '') {
        if (!empty($table_alias)) {
            return "{$table_alias}.domain_uuid = :domain_uuid";
        }
        return "domain_uuid = :domain_uuid";
    }
    
    /**
     * Validate request and initialize domain_uuid.
     * 
     * Call this at the start of every page.
     * 
     * @throws Exception If domain_uuid is invalid or manipulation detected
     */
    public static function init() {
        // Check for manipulation attempts
        if (self::check_manipulation_attempt()) {
            throw new Exception("Security: Domain UUID manipulation detected");
        }
        
        // Require valid domain_uuid
        self::require_domain_uuid();
    }
}

/**
 * Helper function for quick domain UUID access.
 * 
 * @return string The domain UUID
 * @throws Exception If not available
 */
function get_domain_uuid() {
    return domain_validator::require_domain_uuid();
}
?>
