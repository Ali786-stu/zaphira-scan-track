<?php
/**
 * Update User Endpoint
 * Updates user information
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';
require_once __DIR__ . '/../utils/roleMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Require authentication
    require_auth();

    // Get current user
    $current_user = get_current_user();

    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // Validate JSON input
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON input.', 'INVALID_JSON', 400);
    }

    // Validate required field: user ID
    $required_fields = ['id'];
    $missing_fields = validate_required($data, $required_fields);

    if (!empty($missing_fields)) {
        send_error('Missing required fields: ' . implode(', ', $missing_fields), 'MISSING_FIELDS', 400);
    }

    $user_id = (int)$data['id'];

    if ($user_id <= 0) {
        send_error('Invalid user ID.', 'INVALID_USER_ID', 400);
    }

    // Check permission - user can update own profile or admin can update any
    if (!can_modify_resource($user_id)) {
        send_error('Access denied. You can only update your own profile.', 'ACCESS_DENIED', 403);
    }

    // Get existing user data
    $existing_user = get_user_by_id($user_id);

    if (!$existing_user) {
        send_error('User not found.', 'USER_NOT_FOUND', 404);
    }

    // Extract and validate update data
    $updates = [];
    $update_fields = [];

    // Name update
    if (isset($data['name'])) {
        $name = sanitize_input($data['name']);
        if (!validate_length($name, 2, 100)) {
            send_error('Name must be between 2 and 100 characters.', 'INVALID_NAME', 400);
        }
        $updates['name'] = $name;
        $update_fields[] = 'name = ?';
    }

    // Email update
    if (isset($data['email'])) {
        $email = sanitize_input($data['email']);
        if (!validate_email($email)) {
            send_error('Invalid email format.', 'INVALID_EMAIL', 400);
        }

        // Check if email is being changed to a different one
        if ($email !== $existing_user['email']) {
            // Check if new email already exists
            if (user_exists_by_email($email)) {
                send_error('Email already exists. Please use a different email.', 'EMAIL_EXISTS', 400);
            }
        }

        $updates['email'] = $email;
        $update_fields[] = 'email = ?';
    }

    // Role update (admin only)
    if (isset($data['role'])) {
        if (!is_admin()) {
            send_error('Only administrators can change user roles.', 'ROLE_CHANGE_DENIED', 403);
        }

        $role = sanitize_input($data['role']);
        $valid_roles = ['admin', 'employee'];
        if (!in_array($role, $valid_roles)) {
            send_error('Invalid role. Must be either admin or employee.', 'INVALID_ROLE', 400);
        }

        // Validate role assignment
        validate_role_assignment($role, $user_id);

        $updates['role'] = $role;
        $update_fields[] = 'role = ?';
    }

    // Department update
    if (isset($data['department_id'])) {
        $department_id = $data['department_id'] !== null ? (int)$data['department_id'] : null;

        if ($department_id !== null) {
            // Validate department exists
            $dept_sql = "SELECT id FROM departments WHERE id = ? LIMIT 1";
            $department = dbFetch($dept_sql, [$department_id]);

            if (!$department) {
                send_error('Invalid department specified.', 'INVALID_DEPARTMENT', 400);
            }
        }

        $updates['department_id'] = $department_id;
        $update_fields[] = 'department_id = ?';
    }

    // Password update (optional)
    if (isset($data['password'])) {
        // Only user themselves or admin can change password
        if ($current_user['id'] !== $user_id && !is_admin()) {
            send_error('You can only change your own password.', 'PASSWORD_CHANGE_DENIED', 403);
        }

        $password = $data['password'];
        if (!validate_password($password)) {
            send_error('Password must be at least 8 characters long and contain both letters and numbers.', 'INVALID_PASSWORD', 400);
        }

        // For users changing their own password, require current password
        if ($current_user['id'] === $user_id && !is_admin()) {
            if (!isset($data['current_password'])) {
                send_error('Current password is required to change password.', 'CURRENT_PASSWORD_REQUIRED', 400);
            }

            $current_password = $data['current_password'];
            if (!verify_password($current_password, $existing_user['password'])) {
                send_error('Current password is incorrect.', 'INVALID_CURRENT_PASSWORD', 401);
            }
        }

        $updates['password'] = hash_password($password);
        $update_fields[] = 'password = ?';
    }

    // Check if there are any updates
    if (empty($updates)) {
        send_error('No valid update fields provided.', 'NO_UPDATES', 400);
    }

    // Rate limiting check
    $rate_limit_key = 'update_user_' . $current_user['id'];
    if (!check_rate_limit($rate_limit_key, 10, 300)) { // 10 updates per 5 minutes
        send_error('Too many update attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Build UPDATE query
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $params = array_values($updates);
        $params[] = $user_id;

        $stmt = dbQuery($sql, $params);

        if (!$stmt) {
            throw new Exception('Failed to update user.');
        }

        // Get updated user data
        $updated_user = get_user_by_id($user_id);

        // Log user update activity
        $changes = [];
        foreach ($updates as $field => $value) {
            if ($field === 'password') {
                $changes[] = $field . ' (changed)';
            } else {
                $changes[] = $field . ': ' . $value;
            }
        }

        log_activity($current_user['id'], 'USER_UPDATE', "User ID: $user_id, Changes: " . implode(', ', $changes));

        // Commit transaction
        $db->commit();

        // Prepare user data for response (exclude password)
        $user_response = [
            'id' => (int)$updated_user['id'],
            'name' => $updated_user['name'],
            'email' => $updated_user['email'],
            'role' => $updated_user['role'],
            'department_id' => $updated_user['department_id'] ? (int)$updated_user['department_id'] : null,
            'department_name' => $updated_user['department_name'] ?? null,
            'created_at' => $updated_user['created_at'],
            'updated_fields' => array_keys($updates)
        ];

        // Send success response
        send_success($user_response, 'User updated successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Update user endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while updating user.', 'SERVER_ERROR', 500);
}

?>