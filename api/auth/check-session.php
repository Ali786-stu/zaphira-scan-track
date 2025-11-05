<?php
/**
 * Check Session Endpoint
 * Validates current user session and returns user data
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Check if user is authenticated
    if (!is_authenticated()) {
        send_error('No active session found.', 'NO_SESSION', 401);
    }

    // Get current user data
    $user = get_current_user();

    if (!$user) {
        // User not found in database, destroy session
        destroy_session();
        send_error('Invalid session. Please login again.', 'INVALID_SESSION', 401);
    }

    // Prepare user data for response (exclude password)
    $user_response = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'department_id' => $user['department_id'] ? (int)$user['department_id'] : null,
        'department_name' => $user['department_name'] ?? null,
        'created_at' => $user['created_at']
    ];

    // Get session information
    $session_info = get_session_info();

    // Log session check activity (optional, can be commented out for performance)
    log_activity($user['id'], 'SESSION_CHECK', 'IP: ' . get_client_ip());

    // Send success response
    send_success([
        'user' => $user_response,
        'session' => [
            'session_id' => $session_info['session_id'],
            'login_time' => date('Y-m-d H:i:s', $session_info['login_time']),
            'last_activity' => date('Y-m-d H:i:s', $session_info['last_activity']),
            'session_age_minutes' => round($session_info['session_age'] / 60, 1)
        ]
    ], 'Session is valid');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Check session endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while checking session.', 'SERVER_ERROR', 500);
}

?>