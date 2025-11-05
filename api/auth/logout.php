<?php
/**
 * Logout Endpoint
 * Destroys user session and logs out user
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    send_error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Check if user is authenticated
    if (!is_authenticated()) {
        send_error('No active session found.', 'NO_SESSION', 401);
    }

    // Get user data before logout for logging
    $user = get_current_user();

    if (!$user) {
        // User not found, just destroy session
        destroy_session();
        send_error('Invalid session. Please login again.', 'INVALID_SESSION', 401);
    }

    // Logout user and destroy session
    logout_user();

    // Send success response
    send_success(null, 'Logged out successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Logout endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred during logout.', 'SERVER_ERROR', 500);
}

?>