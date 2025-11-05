<?php
/**
 * Check-in Endpoint
 * Records employee check-in time
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

    // Check if user is employee (admins can also check-in for testing)
    if ($user['role'] !== 'employee' && $user['role'] !== 'admin') {
        send_error('Only employees can check in.', 'ROLE_NOT_ALLOWED', 403);
    }

    // Check if already checked in today
    if (has_checked_in_today($user['id'])) {
        send_error('Already checked in today. You can only check in once per day.', 'ALREADY_CHECKED_IN', 400);
    }

    // Get JSON input (optional data can be sent)
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true) ?? [];

    // Sanitize any additional data
    $notes = isset($data['notes']) ? sanitize_input($data['notes']) : null;

    // Rate limiting check (max 10 check-ins per hour per user)
    $rate_limit_key = 'checkin_' . $user['id'];

    if (!check_rate_limit($rate_limit_key, 10, 3600)) {
        send_error('Too many check-in attempts. Please try again later.', 'RATE_LIMIT_EXCEEDED', 429);
    }

    // Start transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Insert attendance record
        $sql = "INSERT INTO attendance (user_id, checkin_time, date) VALUES (?, NOW(), CURDATE())";
        $stmt = dbQuery($sql, [$user['id']]);

        if (!$stmt) {
            throw new Exception('Failed to record check-in.');
        }

        $attendance_id = dbLastInsertId();

        // Get the created attendance record
        $attendance_sql = "SELECT id, user_id, checkin_time, date, created_at
                          FROM attendance
                          WHERE id = ?
                          LIMIT 1";
        $attendance_record = dbFetch($attendance_sql, [$attendance_id]);

        // Log check-in activity
        log_activity($user['id'], 'CHECK_IN', "Attendance ID: $attendance_id, Time: " . $attendance_record['checkin_time']);

        // Commit transaction
        $db->commit();

        // Prepare response data
        $response_data = [
            'attendance_id' => (int)$attendance_record['id'],
            'checkin_time' => format_date($attendance_record['checkin_time'], 'H:i:s'),
            'date' => $attendance_record['date'],
            'full_checkin_time' => $attendance_record['checkin_time'],
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name']
            ]
        ];

        // Send success response
        send_success($response_data, 'Check-in successful');

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Check-in endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred during check-in.', 'SERVER_ERROR', 500);
}

?>