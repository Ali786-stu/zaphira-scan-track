<?php
/**
 * Update Department Endpoint
 * Updates department information (admin only)
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

    // Validate required field: department ID
    $required_fields = ['id'];
    $missing_fields = validate_required($data, $required_fields);

    if (!empty($missing_fields)) {
        send_error('Missing required fields: ' . implode(', ', $missing_fields), 'MISSING_FIELDS', 400);
    }

    $department_id = (int)$data['id'];

    if ($department_id <= 0) {
        send_error('Invalid department ID.', 'INVALID_DEPARTMENT_ID', 400);
    }

    // Get existing department data
    $existing_dept_sql = "SELECT id, name, created_at FROM departments WHERE id = ? LIMIT 1";
    $existing_department = dbFetch($existing_dept_sql, [$department_id]);

    if (!$existing_department) {
        send_error('Department not found.', 'DEPARTMENT_NOT_FOUND', 404);
    }

    // Extract and validate update data
    $updates = [];
    $update_fields = [];

    // Name update
    if (isset($data['name'])) {
        $name = sanitize_input($data['name']);

        if (!validate_length($name, 2, 100)) {
            send_error('Department name must be between 2 and 100 characters.', 'INVALID_NAME_LENGTH', 400);
        }

        // Check if name is being changed to a different one
        if ($name !== $existing_department['name']) {
            // Check if new name already exists
            $name_check_sql = "SELECT id FROM departments WHERE name = ? AND id != ? LIMIT 1";
            $name_exists = dbFetch($name_check_sql, [$name, $department_id]);

            if ($name_exists) {
                send_error('Department with this name already exists.', 'DEPARTMENT_NAME_EXISTS', 400);
            }
        }

        $updates['name'] = $name;
        $update_fields[] = 'name = ?';
    }

    // Check if there are any updates
    if (empty($updates)) {
        send_error('No valid update fields provided.', 'NO_UPDATES', 400);
    }

    // Rate limiting check
    $rate_limit_key = 'update_department_' . $current_user['id'];
    if (!check_rate_limit($rate_limit_key, 20, 3600)) { // 20 updates per hour
        send_error('Too many department update attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Build UPDATE query
        $sql = "UPDATE departments SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $params = array_values($updates);
        $params[] = $department_id;

        $stmt = dbQuery($sql, $params);

        if (!$stmt) {
            throw new Exception('Failed to update department.');
        }

        // Get updated department data
        $updated_dept_sql = "SELECT id, name, created_at FROM departments WHERE id = ? LIMIT 1";
        $updated_department = dbFetch($updated_dept_sql, [$department_id]);

        // Log department update activity
        $changes = [];
        foreach ($updates as $field => $value) {
            $old_value = $existing_department[$field];
            $changes[] = "$field: '$old_value' → '$value'";
        }

        log_activity($current_user['id'], 'DEPARTMENT_UPDATE', "Updated department ID $department_id: " . implode(', ', $changes));

        // Commit transaction
        $db->commit();

        // Get employee count for updated department
        $employee_count_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = ?";
        $employee_count = dbFetch($employee_count_sql, [$department_id]);

        // Prepare response data
        $response_data = [
            'department' => [
                'id' => (int)$updated_department['id'],
                'name' => $updated_department['name'],
                'employee_count' => (int)($employee_count['count'] ?? 0),
                'created_at' => $updated_department['created_at']
            ],
            'updated_fields' => array_keys($updates),
            'updated_by' => [
                'id' => (int)$current_user['id'],
                'name' => $current_user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'Department updated successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Update department endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while updating department.', 'SERVER_ERROR', 500);
}

?>