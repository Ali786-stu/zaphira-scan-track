<?php
/**
 * Register Endpoint
 * Creates new user accounts
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
    $required_fields = ['name', 'email', 'password'];
    $missing_fields = validate_required($data, $required_fields);

    if (!empty($missing_fields)) {
        send_error('Missing required fields: ' . implode(', ', $missing_fields), 'MISSING_FIELDS', 400);
    }

    // Sanitize input
    $name = sanitize_input($data['name']);
    $email = sanitize_input($data['email']);
    $password = $data['password']; // Don't sanitize password
    $role = sanitize_input($data['role'] ?? 'employee');
    $department_id = isset($data['department_id']) ? (int)$data['department_id'] : null;

    // Validate name
    if (!validate_length($name, 2, 100)) {
        send_error('Name must be between 2 and 100 characters.', 'INVALID_NAME', 400);
    }

    // Validate email format
    if (!validate_email($email)) {
        send_error('Invalid email format.', 'INVALID_EMAIL', 400);
    }

    // Validate password strength
    if (!validate_password($password)) {
        send_error('Password must be at least 8 characters long and contain both letters and numbers.', 'INVALID_PASSWORD', 400);
    }

    // Validate role
    $valid_roles = ['admin', 'employee'];
    if (!in_array($role, $valid_roles)) {
        send_error('Invalid role. Must be either admin or employee.', 'INVALID_ROLE', 400);
    }

    // Check if email already exists
    if (user_exists_by_email($email)) {
        send_error('Email already registered. Please use a different email or login.', 'EMAIL_EXISTS', 400);
    }

    // Validate department if provided
    if ($department_id) {
        $dept_sql = "SELECT id FROM departments WHERE id = ? LIMIT 1";
        $department = dbFetch($dept_sql, [$department_id]);

        if (!$department) {
            send_error('Invalid department specified.', 'INVALID_DEPARTMENT', 400);
        }
    }

    // Rate limiting check (max 3 registrations per IP per hour)
    $client_ip = get_client_ip();
    $rate_limit_key = 'register_' . md5($client_ip);

    if (!check_rate_limit($rate_limit_key, 3, 3600)) {
        send_error('Too many registration attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Hash password
    $hashed_password = hash_password($password);

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Insert new user
        $sql = "INSERT INTO users (name, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = dbQuery($sql, [$name, $email, $hashed_password, $role, $department_id]);

        if (!$stmt) {
            throw new Exception('Failed to create user account.');
        }

        $user_id = dbLastInsertId();

        // Get created user data
        $new_user = get_user_by_id($user_id);

        // Log registration activity
        log_activity($user_id, 'REGISTER', "Name: $name, Email: $email, Role: $role");

        // Commit transaction
        $db->commit();

        // Prepare user data for response (exclude password)
        $user_response = [
            'id' => (int)$new_user['id'],
            'name' => $new_user['name'],
            'email' => $new_user['email'],
            'role' => $new_user['role'],
            'department_id' => $new_user['department_id'] ? (int)$new_user['department_id'] : null,
            'department_name' => $new_user['department_name'] ?? null,
            'created_at' => $new_user['created_at']
        ];

        // Send success response
        send_success([
            'user' => $user_response
        ], 'Registration successful. You can now login.');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Registration endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred during registration. Please try again.', 'SERVER_ERROR', 500);
}

?>