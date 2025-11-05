<?php
/**
 * Delete Department Endpoint
 * Deletes a department (admin only)
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';
require_once __DIR__ . '/../utils/roleMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_error('Method not allowed. Use DELETE.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Require admin authentication
    require_admin();

    // Get current user
    $current_user = get_current_user();

    // Get department ID from query parameters
    if (!isset($_GET['id'])) {
        send_error('Department ID is required.', 'MISSING_DEPARTMENT_ID', 400);
    }

    $department_id = (int)$_GET['id'];

    if ($department_id <= 0) {
        send_error('Invalid department ID.', 'INVALID_DEPARTMENT_ID', 400);
    }

    // Get department to be deleted
    $department_sql = "SELECT id, name, created_at FROM departments WHERE id = ? LIMIT 1";
    $department_to_delete = dbFetch($department_sql, [$department_id]);

    if (!$department_to_delete) {
        send_error('Department not found.', 'DEPARTMENT_NOT_FOUND', 404);
    }

    // Check if department has users assigned
    $users_check_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = ?";
    $users_count = dbFetch($users_check_sql, [$department_id]);

    if ($users_count && $users_count['count'] > 0) {
        send_error('Cannot delete department with assigned users. Please reassign or remove users first.', 'HAS_ASSIGNED_USERS', 400);
    }

    // Rate limiting check
    $rate_limit_key = 'delete_department_' . $current_user['id'];
    if (!check_rate_limit($rate_limit_key, 5, 3600)) { // 5 deletions per hour
        send_error('Too many department deletion attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Delete the department
        $delete_sql = "DELETE FROM departments WHERE id = ?";
        $stmt = dbQuery($delete_sql, [$department_id]);

        if (!$stmt) {
            throw new Exception('Failed to delete department.');
        }

        // Log department deletion activity
        log_activity($current_user['id'], 'DEPARTMENT_DELETE', "Deleted department: {$department_to_delete['name']} (ID: $department_id)");

        // Commit transaction
        $db->commit();

        // Prepare response data
        $response_data = [
            'deleted_department' => [
                'id' => (int)$department_to_delete['id'],
                'name' => $department_to_delete['name'],
                'created_at' => $department_to_delete['created_at']
            ],
            'deleted_by' => [
                'id' => (int)$current_user['id'],
                'name' => $current_user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'Department deleted successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Delete department endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while deleting department.', 'SERVER_ERROR', 500);
}

?>