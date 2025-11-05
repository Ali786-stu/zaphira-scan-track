<?php
/**
 * Create Department Endpoint
 * Creates a new department (admin only)
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
    // Require admin authentication
    require_admin();

    // Get current user
    $current_user = get_current_user();

    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // Validate JSON input
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON input.', 'INVALID_JSON', 400);
    }

    // Validate required field: department name
    $required_fields = ['name'];
    $missing_fields = validate_required($data, $required_fields);

    if (!empty($missing_fields)) {
        send_error('Missing required fields: ' . implode(', ', $missing_fields), 'MISSING_FIELDS', 400);
    }

    // Sanitize and validate department name
    $name = sanitize_input($data['name']);

    if (!validate_length($name, 2, 100)) {
        send_error('Department name must be between 2 and 100 characters.', 'INVALID_NAME_LENGTH', 400);
    }

    // Check if department name already exists
    $existing_dept_sql = "SELECT id FROM departments WHERE name = ? LIMIT 1";
    $existing_dept = dbFetch($existing_dept_sql, [$name]);

    if ($existing_dept) {
        send_error('Department with this name already exists.', 'DEPARTMENT_EXISTS', 400);
    }

    // Rate limiting check
    $rate_limit_key = 'create_department_' . $current_user['id'];
    if (!check_rate_limit($rate_limit_key, 10, 3600)) { // 10 departments per hour
        send_error('Too many department creation attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Optional: Description field (if you want to extend the functionality)
    $description = isset($data['description']) ? sanitize_input($data['description']) : null;

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Insert new department
        $sql = "INSERT INTO departments (name) VALUES (?)";
        $stmt = dbQuery($sql, [$name]);

        if (!$stmt) {
            throw new Exception('Failed to create department.');
        }

        $department_id = dbLastInsertId();

        // Get the created department
        $created_dept_sql = "SELECT id, name, created_at FROM departments WHERE id = ? LIMIT 1";
        $created_department = dbFetch($created_dept_sql, [$department_id]);

        // Log department creation activity
        log_activity($current_user['id'], 'DEPARTMENT_CREATE', "Created department: $name (ID: $department_id)");

        // Commit transaction
        $db->commit();

        // Prepare response data
        $response_data = [
            'department' => [
                'id' => (int)$created_department['id'],
                'name' => $created_department['name'],
                'created_at' => $created_department['created_at']
            ],
            'created_by' => [
                'id' => (int)$current_user['id'],
                'name' => $current_user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'Department created successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Create department endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while creating department.', 'SERVER_ERROR', 500);
}

?>