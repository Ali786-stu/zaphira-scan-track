<?php
/**
 * Delete User Endpoint
 * Deletes a user account (admin only)
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

    // Get user ID from query parameters
    if (!isset($_GET['id'])) {
        send_error('User ID is required.', 'MISSING_USER_ID', 400);
    }

    $user_id = (int)$_GET['id'];

    if ($user_id <= 0) {
        send_error('Invalid user ID.', 'INVALID_USER_ID', 400);
    }

    // Prevent self-deletion
    if ($user_id === $current_user['id']) {
        send_error('Administrators cannot delete their own accounts.', 'SELF_DELETION_DENIED', 403);
    }

    // Get user to be deleted
    $user_to_delete = get_user_by_id($user_id);

    if (!$user_to_delete) {
        send_error('User not found.', 'USER_NOT_FOUND', 404);
    }

    // Check if user has attendance records
    $attendance_check_sql = "SELECT COUNT(*) as count FROM attendance WHERE user_id = ?";
    $attendance_count = dbFetch($attendance_check_sql, [$user_id]);

    if ($attendance_count && $attendance_count['count'] > 0) {
        // Option 1: Prevent deletion if user has attendance records
        send_error('Cannot delete user with existing attendance records. Consider deactivating the account instead.', 'HAS_ATTENDANCE_RECORDS', 400);

        // Option 2: Soft delete (uncomment the code below if you prefer soft delete)
        /*
        // Soft delete approach: update user status instead of deleting
        $soft_delete_sql = "UPDATE users SET email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()), role = 'deleted' WHERE id = ?";
        $stmt = dbQuery($soft_delete_sql, [$user_id]);

        if (!$stmt) {
            throw new Exception('Failed to soft delete user.');
        }

        log_activity($current_user['id'], 'USER_SOFT_DELETE', "Soft deleted user: {$user_to_delete['name']} (ID: $user_id)");

        send_success([
            'deleted_user' => [
                'id' => (int)$user_to_delete['id'],
                'name' => $user_to_delete['name'],
                'email' => $user_to_delete['email'] . '_deleted',
                'role' => 'deleted'
            ]
        ], 'User account deactivated successfully');
        */
    }

    // Rate limiting check
    $rate_limit_key = 'delete_user_' . $current_user['id'];
    if (!check_rate_limit($rate_limit_key, 3, 3600)) { // 3 deletions per hour
        send_error('Too many deletion attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Delete user's activity logs first (foreign key constraint)
        $delete_logs_sql = "DELETE FROM activity_logs WHERE user_id = ?";
        dbQuery($delete_logs_sql, [$user_id]);

        // Delete user's attendance records (foreign key constraint)
        $delete_attendance_sql = "DELETE FROM attendance WHERE user_id = ?";
        dbQuery($delete_attendance_sql, [$user_id]);

        // Delete the user
        $delete_user_sql = "DELETE FROM users WHERE id = ?";
        $stmt = dbQuery($delete_user_sql, [$user_id]);

        if (!$stmt) {
            throw new Exception('Failed to delete user.');
        }

        // Log user deletion activity
        log_activity($current_user['id'], 'USER_DELETE', "Deleted user: {$user_to_delete['name']} (ID: $user_id, Email: {$user_to_delete['email']})");

        // Commit transaction
        $db->commit();

        // Prepare response data
        $response_data = [
            'deleted_user' => [
                'id' => (int)$user_to_delete['id'],
                'name' => $user_to_delete['name'],
                'email' => $user_to_delete['email'],
                'role' => $user_to_delete['role'],
                'department_name' => $user_to_delete['department_name']
            ],
            'deleted_by' => [
                'id' => (int)$current_user['id'],
                'name' => $current_user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'User deleted successfully');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Delete user endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while deleting user.', 'SERVER_ERROR', 500);
}

?>