<?php
/**
 * Authentication Middleware
 * Handles session validation and user authentication
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403);
    exit('Direct access denied');
}

// Include required files
require_once __DIR__ . '/helpers.php';

/**
 * Authenticate user and set user data in global scope
 */
function authenticate() {
    // Initialize session
    init_session();

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        send_error('Authentication required. Please login.', 'AUTH_REQUIRED', 401);
    }

    // Get user ID from session
    $user_id = $_SESSION['user_id'];

    // Verify user still exists in database
    $user = get_user_by_id($user_id);
    if (!$user) {
        // User not found, destroy session
        destroy_session();
        send_error('User not found. Please login again.', 'USER_NOT_FOUND', 401);
    }

    // Set user data in global scope for use in endpoints
    $GLOBALS['current_user'] = $user;

    return $user;
}

/**
 * Get current authenticated user
 */
function get_current_user() {
    return $GLOBALS['current_user'] ?? null;
}

/**
 * Check if user is authenticated (returns boolean)
 */
function is_authenticated() {
    init_session();
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
}

/**
 * Require authentication - terminates script if not authenticated
 */
function require_auth() {
    authenticate();
}

/**
 * Get session ID for logging/debugging
 */
function get_session_id() {
    return session_id();
}

/**
 * Refresh session timeout
 */
function refresh_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['last_activity'] = time();

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if session has expired
 */
function is_session_expired() {
    $timeout = $_ENV['SESSION_LIFETIME'] ?? 3600; // Default 1 hour

    if (isset($_SESSION['last_activity'])) {
        return (time() - $_SESSION['last_activity']) > $timeout;
    }

    return true;
}

/**
 * Auto-logout if session expired
 */
function check_session_timeout() {
    init_session();

    if (is_session_expired()) {
        destroy_session();
        send_error('Session expired. Please login again.', 'SESSION_EXPIRED', 401);
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Create user session after successful login
 */
function create_user_session($user) {
    init_session();

    // Clear any existing session data
    $_SESSION = [];

    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['initiated'] = true;

    // Log activity
    log_activity($user['id'], 'LOGIN', 'IP: ' . get_client_ip());
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    $user_id = $_SESSION['user_id'] ?? null;

    // Log activity before destroying session
    if ($user_id) {
        log_activity($user_id, 'LOGOUT', 'IP: ' . get_client_ip());
    }

    destroy_session();
}

/**
 * Validate session integrity
 */
function validate_session_integrity() {
    if (!is_authenticated()) {
        return false;
    }

    $user_id = $_SESSION['user_id'];
    $stored_user = get_user_by_id($user_id);

    if (!$stored_user) {
        destroy_session();
        return false;
    }

    // Check if email matches (in case user was changed)
    if ($stored_user['email'] !== $_SESSION['user_email']) {
        destroy_session();
        return false;
    }

    return true;
}

/**
 * Get session information for debugging
 */
function get_session_info() {
    if (!is_authenticated()) {
        return null;
    }

    return [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'],
        'user_email' => $_SESSION['user_email'],
        'user_role' => $_SESSION['user_role'],
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'session_age' => time() - ($_SESSION['login_time'] ?? time())
    ];
}

/**
 * Check for suspicious activity
 */
function check_suspicious_activity() {
    if (!is_authenticated()) {
        return false;
    }

    $user_id = $_SESSION['user_id'];
    $current_ip = get_client_ip();

    // Check if IP changed
    if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== $current_ip) {
        // Log IP change
        log_activity($user_id, 'IP_CHANGE', "Old: {$_SESSION['last_ip']}, New: $current_ip");

        // Optional: Require re-authentication for IP changes
        // destroy_session();
        // send_error('Security alert: IP address changed. Please login again.', 'IP_CHANGED', 401);
    }

    // Store current IP
    $_SESSION['last_ip'] = $current_ip;

    // Check for rapid requests (potential DoS)
    $request_time_key = 'last_request_' . $user_id;
    $current_time = time();

    if (isset($_SESSION[$request_time_key])) {
        $time_diff = $current_time - $_SESSION[$request_time_key];
        if ($time_diff < 1) { // Less than 1 second between requests
            log_activity($user_id, 'RAPID_REQUESTS', "Time diff: $time_diff seconds");
        }
    }

    $_SESSION[$request_time_key] = $current_time;

    return true;
}

// Auto-run session validation for all requests that include this middleware
if (is_authenticated()) {
    check_session_timeout();
    refresh_session();
    validate_session_integrity();
    check_suspicious_activity();
}

?>