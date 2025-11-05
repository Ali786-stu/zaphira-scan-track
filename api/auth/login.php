<?php
/**
 * Login Endpoint
 * Authenticates users and creates session
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // Validate JSON input
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON input.', 'INVALID_JSON', 400);
    }

    // Validate required fields
    $required_fields = ['email', 'password'];
    $missing_fields = validate_required($data, $required_fields);

    if (!empty($missing_fields)) {
        send_error('Missing required fields: ' . implode(', ', $missing_fields), 'MISSING_FIELDS', 400);
    }

    // Sanitize input
    $email = sanitize_input($data['email']);
    $password = $data['password']; // Don't sanitize password

    // Validate email format
    if (!validate_email($email)) {
        send_error('Invalid email format.', 'INVALID_EMAIL', 400);
    }

    // Rate limiting check (max 5 attempts per minute per IP)
    $client_ip = get_client_ip();
    $rate_limit_key = 'login_' . md5($client_ip . $email);

    if (!check_rate_limit($rate_limit_key, 5, 60)) {
        send_error('Too many login attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Get user from database
    $user = get_user_by_email($email);

    if (!$user) {
        // Log failed attempt
        error_log("Login failed: User not found - Email: $email, IP: $client_ip");
        send_error('Invalid email or password.', 'INVALID_CREDENTIALS', 401);
    }

    // Verify password
    if (!verify_password($password, $user['password'])) {
        // Log failed attempt
        error_log("Login failed: Invalid password - Email: $email, IP: $client_ip");
        send_error('Invalid email or password.', 'INVALID_CREDENTIALS', 401);
    }

    // Check if user is active (optional: you can add an 'active' field to users table)
    // For now, all users are considered active

    // Create user session
    create_user_session($user);

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

    // Log successful login
    log_activity($user['id'], 'LOGIN_SUCCESS', 'IP: ' . $client_ip);

    // Send success response
    send_success([
        'user' => $user_response,
        'session_info' => [
            'session_id' => session_id(),
            'login_time' => date('Y-m-d H:i:s')
        ]
    ], 'Login successful');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Login endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
}

?>