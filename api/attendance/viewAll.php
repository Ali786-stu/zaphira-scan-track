<?php
/**
 * View All Attendance Endpoint
 * Returns all attendance records (admin only)
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
    // Require admin authentication
    require_admin();

    // Get current user
    $user = get_current_user();

    // Get query parameters
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : null;
    $pagination = get_pagination_params(50, 200);

    // Validate parameters
    if ($user_id !== null && $user_id <= 0) {
        send_error('Invalid user ID.', 'INVALID_USER_ID', 400);
    }

    if ($department_id !== null && $department_id <= 0) {
        send_error('Invalid department ID.', 'INVALID_DEPARTMENT_ID', 400);
    }

    if ($month !== null && ($month < 1 || $month > 12)) {
        send_error('Invalid month. Must be between 1 and 12.', 'INVALID_MONTH', 400);
    }

    if ($year !== null && ($year < 2020 || $year > 2030)) {
        send_error('Invalid year. Must be between 2020 and 2030.', 'INVALID_YEAR', 400);
    }

    if ($status !== null && !in_array($status, ['complete', 'checked_in', 'absent'])) {
        send_error('Invalid status. Must be: complete, checked_in, or absent.', 'INVALID_STATUS', 400);
    }

    // Default to current month and year if not provided
    if ($month === null) $month = (int)date('n');
    if ($year === null) $year = (int)date('Y');

    // Build SQL query with filters and joins
    $sql = "SELECT a.id, a.user_id, a.checkin_time, a.checkout_time, a.date, a.created_at,
                   u.name as user_name, u.email as user_email,
                   d.name as department_name,
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
            JOIN users u ON a.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?";

    $params = [$month, $year];

    // Add user filter
    if ($user_id) {
        $sql .= " AND a.user_id = ?";
        $params[] = $user_id;
    }

    // Add department filter
    if ($department_id) {
        $sql .= " AND u.department_id = ?";
        $params[] = $department_id;
    }

    // Add status filter
    if ($status) {
        switch ($status) {
            case 'complete':
                $sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL";
                break;
            case 'checked_in':
                $sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NULL";
                break;
            case 'absent':
                $sql .= " AND a.checkin_time IS NULL";
                break;
        }
    }

    // Add search filter (search by user name or email)
    if ($search) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Add date range filter
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
    $sql .= " ORDER BY a.date DESC, u.name ASC, a.checkin_time ASC LIMIT ? OFFSET ?";
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];

    // Execute query
    $attendance_records = dbFetchAll($sql, $params);

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 LEFT JOIN departments d ON u.department_id = d.id
                 WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?";

    $count_params = [$month, $year];

    if ($user_id) {
        $count_sql .= " AND a.user_id = ?";
        $count_params[] = $user_id;
    }
    if ($department_id) {
        $count_sql .= " AND u.department_id = ?";
        $count_params[] = $department_id;
    }
    if ($status) {
        switch ($status) {
            case 'complete':
                $count_sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL";
                break;
            case 'checked_in':
                $count_sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NULL";
                break;
            case 'absent':
                $count_sql .= " AND a.checkin_time IS NULL";
                break;
        }
    }
    if ($search) {
        $count_sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
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
            'user_id' => (int)$record['user_id'],
            'user_name' => $record['user_name'],
            'user_email' => $record['user_email'],
            'department_name' => $record['department_name'],
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

    // Get summary statistics
    $summary_sql = "SELECT
                       COUNT(*) as total_days,
                       COUNT(CASE WHEN a.checkin_time IS NOT NULL THEN 1 END) as days_present,
                       COUNT(CASE WHEN a.checkout_time IS NOT NULL THEN 1 END) as days_complete,
                       COUNT(DISTINCT a.user_id) as unique_users,
                       AVG(CASE WHEN a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL
                                THEN TIMESTAMPDIFF(HOUR, a.checkin_time, a.checkout_time) END) as avg_hours
                   FROM attendance a
                   JOIN users u ON a.user_id = u.id
                   LEFT JOIN departments d ON u.department_id = d.id
                   WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?";

    $summary_params = [$month, $year];

    if ($user_id) {
        $summary_sql .= " AND a.user_id = ?";
        $summary_params[] = $user_id;
    }
    if ($department_id) {
        $summary_sql .= " AND u.department_id = ?";
        $summary_params[] = $department_id;
    }
    if ($status) {
        switch ($status) {
            case 'complete':
                $summary_sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NOT NULL";
                break;
            case 'checked_in':
                $summary_sql .= " AND a.checkin_time IS NOT NULL AND a.checkout_time IS NULL";
                break;
            case 'absent':
                $summary_sql .= " AND a.checkin_time IS NULL";
                break;
        }
    }
    if ($date_range['start_date']) {
        $summary_sql .= " AND a.date >= ?";
        $summary_params[] = $date_range['start_date'];
    }
    if ($date_range['end_date']) {
        $summary_sql .= " AND a.date <= ?";
        $summary_params[] = $date_range['end_date'];
    }

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
            'unique_users' => (int)($summary['unique_users'] ?? 0),
            'average_hours' => $summary['avg_hours'] ? round((float)$summary['avg_hours'], 2) : 0
        ],
        'filters' => [
            'user_id' => $user_id,
            'department_id' => $department_id,
            'status' => $status,
            'search' => $search,
            'start_date' => $date_range['start_date'],
            'end_date' => $date_range['end_date']
        ],
        'pagination' => [
            'total' => $total_records,
            'limit' => $pagination['limit'],
            'offset' => $pagination['offset'],
            'has_more' => ($pagination['offset'] + $pagination['limit']) < $total_records
        ]
    ];

    // Log attendance view activity
    log_activity($user['id'], 'VIEW_ALL_ATTENDANCE', "Period: $month/$year, Records: " . count($formatted_records));

    // Send success response
    send_success($response_data, 'All attendance records retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("View all attendance endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving attendance records.', 'SERVER_ERROR', 500);
}

?>