<?php
/**
 * View Self Attendance Endpoint
 * Returns personal attendance history for the authenticated user
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
    $user = get_current_user();

    // Get query parameters
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $pagination = get_pagination_params(30, 100);

    // Validate month and year if provided
    if ($month !== null && ($month < 1 || $month > 12)) {
        send_error('Invalid month. Must be between 1 and 12.', 'INVALID_MONTH', 400);
    }

    if ($year !== null && ($year < 2020 || $year > 2030)) {
        send_error('Invalid year. Must be between 2020 and 2030.', 'INVALID_YEAR', 400);
    }

    // Default to current month and year if not provided
    if ($month === null) $month = (int)date('n');
    if ($year === null) $year = (int)date('Y');

    // Build SQL query with filters
    $sql = "SELECT a.id, a.checkin_time, a.checkout_time, a.date, a.created_at,
                   CASE
                       WHEN a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL THEN 'complete'
                       WHEN a.checkin_time IS NOT NULL THEN 'checked_in'
                       ELSE 'absent'
                   END as status,
                   CASE
                       WHEN a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL THEN
                           TIMESTAMPDIFF(HOUR, a.checkin_time, a.checkout_time)
                       ELSE NULL
                   END as total_hours
            FROM attendance a
            WHERE a.user_id = ? AND MONTH(a.date) = ? AND YEAR(a.date) = ?";

    $params = [$user['id'], $month, $year];

    // Add date range filter if specified
    $date_range = get_date_range();
    if ($date_range['start_date']) {
        $sql .= " AND a.date >= ?";
        $params[] = $date_range['start_date'];
    }
    if ($date_range['end_date']) {
        $sql .= " AND a.date <= ?";
        $params[] = $date_range['end_date'];
    }

    // Add ordering and pagination
    $sql .= " ORDER BY a.date DESC, a.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];

    // Execute query
    $attendance_records = dbFetchAll($sql, $params);

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total
                 FROM attendance a
                 WHERE a.user_id = ? AND MONTH(a.date) = ? AND YEAR(a.date) = ?";

    $count_params = [$user['id'], $month, $year];

    if ($date_range['start_date']) {
        $count_sql .= " AND a.date >= ?";
        $count_params[] = $date_range['start_date'];
    }
    if ($date_range['end_date']) {
        $count_sql .= " AND a.date <= ?";
        $count_params[] = $date_range['end_date'];
    }

    $count_result = dbFetch($count_sql, $count_params);
    $total_records = $count_result ? (int)$count_result['total'] : 0;

    // Format attendance records
    $formatted_records = [];
    foreach ($attendance_records as $record) {
        $formatted_records[] = [
            'id' => (int)$record['id'],
            'date' => $record['date'],
            'checkin_time' => $record['checkin_time'] ? format_date($record['checkin_time'], 'H:i:s') : null,
            'checkout_time' => $record['checkout_time'] ? format_date($record['checkout_time'], 'H:i:s') : null,
            'status' => $record['status'],
            'total_hours' => $record['total_hours'] ? round((float)$record['total_hours'], 2) : null,
            'full_checkin_time' => $record['checkin_time'],
            'full_checkout_time' => $record['checkout_time'],
            'created_at' => $record['created_at']
        ];
    }

    // Calculate summary statistics
    $summary_sql = "SELECT
                       COUNT(*) as total_days,
                       COUNT(CASE WHEN checkin_time IS NOT NULL THEN 1 END) as days_present,
                       COUNT(CASE WHEN checkout_time IS NOT NULL THEN 1 END) as days_complete,
                       AVG(CASE WHEN checkin_time IS NOT NULL AND checkout_time IS NOT NULL
                                THEN TIMESTAMPDIFF(HOUR, checkin_time, checkout_time) END) as avg_hours
                   FROM attendance
                   WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";

    $summary_params = [$user['id'], $month, $year];
    $summary = dbFetch($summary_sql, $summary_params);

    // Prepare response data
    $response_data = [
        'attendance' => $formatted_records,
        'summary' => [
            'month' => $month,
            'year' => $year,
            'total_days' => (int)($summary['total_days'] ?? 0),
            'days_present' => (int)($summary['days_present'] ?? 0),
            'days_complete' => (int)($summary['days_complete'] ?? 0),
            'average_hours' => $summary['avg_hours'] ? round((float)$summary['avg_hours'], 2) : 0
        ],
        'pagination' => [
            'total' => $total_records,
            'limit' => $pagination['limit'],
            'offset' => $pagination['offset'],
            'has_more' => ($pagination['offset'] + $pagination['limit']) < $total_records
        ],
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ];

    // Log attendance view activity
    log_activity($user['id'], 'VIEW_SELF_ATTENDANCE', "Period: $month/$year, Records: " . count($formatted_records));

    // Send success response
    send_success($response_data, 'Attendance records retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("View self attendance endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving attendance records.', 'SERVER_ERROR', 500);
}

?>