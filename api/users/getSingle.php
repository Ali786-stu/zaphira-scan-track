<?php
/**
 * Get Single User Endpoint
 * Returns details of a specific user
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/authMiddleware.php';
require_once __DIR__ . '/../utils/roleMiddleware.php';

// Handle CORS preflight request
handle_cors();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Require authentication
    require_auth();

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

    // Check permission - user can view own profile or admin can view any
    if (!can_access_resource($user_id)) {
        send_error('Access denied. You can only view your own profile.', 'ACCESS_DENIED', 403);
    }

    // Get user details with additional information
    $sql = "SELECT u.id, u.name, u.email, u.role, u.department_id, u.created_at,
                   d.name as department_name,
                   COUNT(a.id) as total_attendance_days,
                   COUNT(CASE WHEN a.checkin_time IS NOT NULL THEN 1 END) as days_present,
                   COUNT(CASE WHEN a.checkout_time IS NOT NULL THEN 1 END) as days_complete,
                   MAX(a.date) as last_attendance_date,
                   AVG(CASE WHEN a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL
                            THEN TIMESTAMPDIFF(HOUR, a.checkin_time, a.checkout_time) END) as avg_hours
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN attendance a ON u.id = a.user_id
            WHERE u.id = ?
            GROUP BY u.id, u.name, u.email, u.role, u.department_id, u.created_at, d.name
            LIMIT 1";

    $user = dbFetch($sql, [$user_id]);

    if (!$user) {
        send_error('User not found.', 'USER_NOT_FOUND', 404);
    }

    // Get recent attendance records (last 30 days)
    $recent_attendance_sql = "SELECT id, date, checkin_time, checkout_time, created_at,
                              CASE
                                  WHEN checkin_time IS NOT NULL AND checkout_time IS NOT NULL THEN 'complete'
                                  WHEN checkin_time IS NOT NULL THEN 'checked_in'
                                  ELSE 'absent'
                              END as status,
                              CASE
                                  WHEN checkin_time IS NOT NULL AND checkout_time IS NOT NULL THEN
                                      TIMESTAMPDIFF(HOUR, checkin_time, checkout_time)
                                  ELSE NULL
                              END as total_hours
                           FROM attendance
                           WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                           ORDER BY date DESC, checkin_time DESC
                           LIMIT 10";

    $recent_attendance = dbFetchAll($recent_attendance_sql, [$user_id]);

    // Get attendance summary for current month
    $month_summary_sql = "SELECT
                             COUNT(*) as total_days,
                             COUNT(CASE WHEN checkin_time IS NOT NULL THEN 1 END) as days_present,
                             COUNT(CASE WHEN checkout_time IS NOT NULL THEN 1 END) as days_complete,
                             AVG(CASE WHEN checkin_time IS NOT NULL AND checkout_time IS NOT NULL
                                      THEN TIMESTAMPDIFF(HOUR, checkin_time, checkout_time) END) as avg_hours
                          FROM attendance
                          WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";

    $month_summary = dbFetch($month_summary_sql, [$user_id]);

    // Format user data
    $user_data = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'department_id' => $user['department_id'] ? (int)$user['department_id'] : null,
        'department_name' => $user['department_name'],
        'created_at' => $user['created_at'],
        'statistics' => [
            'total_attendance_days' => (int)($user['total_attendance_days'] ?? 0),
            'days_present' => (int)($user['days_present'] ?? 0),
            'days_complete' => (int)($user['days_complete'] ?? 0),
            'last_attendance_date' => $user['last_attendance_date'],
            'average_hours' => $user['avg_hours'] ? round((float)$user['avg_hours'], 2) : 0
        ],
        'current_month_summary' => [
            'total_days' => (int)($month_summary['total_days'] ?? 0),
            'days_present' => (int)($month_summary['days_present'] ?? 0),
            'days_complete' => (int)($month_summary['days_complete'] ?? 0),
            'average_hours' => $month_summary['avg_hours'] ? round((float)$month_summary['avg_hours'], 2) : 0
        ],
        'recent_attendance' => []
    ];

    // Format recent attendance records
    foreach ($recent_attendance as $attendance) {
        $user_data['recent_attendance'][] = [
            'id' => (int)$attendance['id'],
            'date' => $attendance['date'],
            'checkin_time' => $attendance['checkin_time'] ? format_date($attendance['checkin_time'], 'H:i:s') : null,
            'checkout_time' => $attendance['checkout_time'] ? format_date($attendance['checkout_time'], 'H:i:s') : null,
            'status' => $attendance['status'],
            'total_hours' => $attendance['total_hours'] ? round((float)$attendance['total_hours'], 2) : null,
            'created_at' => $attendance['created_at']
        ];
    }

    // Add permissions info if viewing own profile
    if ($current_user['id'] == $user_id) {
        $user_data['permissions'] = [
            'can_update_profile' => true,
            'can_view_all_attendance' => is_admin(),
            'can_manage_users' => is_admin(),
            'can_manage_departments' => is_admin()
        ];
    }

    // Log user profile view activity
    log_activity($current_user['id'], 'VIEW_USER_PROFILE', "Viewed user ID: $user_id");

    // Send success response
    send_success($user_data, 'User details retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Get single user endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving user details.', 'SERVER_ERROR', 500);
}

?>