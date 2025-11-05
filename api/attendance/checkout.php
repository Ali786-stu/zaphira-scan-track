<?php
/**
 * Check-out Endpoint
 * Records employee check-out time
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
    $user = get_current_user();

    // Check if user is employee (admins can also check-out for testing)
    if ($user['role'] !== 'employee' && $user['role'] !== 'admin') {
        send_error('Only employees can check out.', 'ROLE_NOT_ALLOWED', 403);
    }

    // Check if checked in today
    if (!has_checked_in_today($user['id'])) {
        send_error('You must check in before checking out.', 'NOT_CHECKED_IN', 400);
    }

    // Check if already checked out today
    if (has_checked_out_today($user['id'])) {
        send_error('Already checked out today. You can only check out once per day.', 'ALREADY_CHECKED_OUT', 400);
    }

    // Get JSON input (optional data can be sent)
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true) ?? [];

    // Sanitize any additional data
    $notes = isset($data['notes']) ? sanitize_input($data['notes']) : null;

    // Rate limiting check (max 10 check-outs per hour per user)
    $rate_limit_key = 'checkout_' . $user['id'];

    if (!check_rate_limit($rate_limit_key, 10, 3600)) {
        send_error('Too many check-out attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Get today's attendance record
        $attendance_sql = "SELECT id, checkin_time, date
                          FROM attendance
                          WHERE user_id = ? AND date = CURDATE()
                          LIMIT 1";
        $attendance_record = dbFetch($attendance_sql, [$user['id']]);

        if (!$attendance_record) {
            throw new Exception('No check-in record found for today.');
        }

        $attendance_id = $attendance_record['id'];
        $checkin_time = $attendance_record['checkin_time'];

        // Update attendance record with checkout time
        $update_sql = "UPDATE attendance SET checkout_time = NOW()
                      WHERE id = ?";
        $update_stmt = dbQuery($update_sql, [$attendance_id]);

        if (!$update_stmt) {
            throw new Exception('Failed to record check-out.');
        }

        // Get the updated attendance record
        $updated_sql = "SELECT id, user_id, checkin_time, checkout_time, date, created_at
                       FROM attendance
                       WHERE id = ?
                       LIMIT 1";
        $updated_record = dbFetch($updated_sql, [$attendance_id]);

        // Calculate total hours worked
        $total_hours = calculate_hours($checkin_time, $updated_record['checkout_time']);

        // Log check-out activity
        log_activity($user['id'], 'CHECK_OUT', "Attendance ID: $attendance_id, Hours: $total_hours");

        // Commit transaction
        $db->commit();

        // Prepare response data
        $response_data = [
            'attendance_id' => (int)$updated_record['id'],
            'checkin_time' => format_date($updated_record['checkin_time'], 'H:i:s'),
            'checkout_time' => format_date($updated_record['checkout_time'], 'H:i:s'),
            'date' => $updated_record['date'],
            'total_hours' => $total_hours,
            'full_checkin_time' => $updated_record['checkin_time'],
            'full_checkout_time' => $updated_record['checkout_time'],
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'Check-out successful');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Check-out endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred during check-out.', 'SERVER_ERROR', 500);
}

?>