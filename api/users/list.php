<?php
/**
 * List Users Endpoint
 * Returns list of all users (admin only)
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
    $current_user = get_current_user();

    // Get query parameters
    $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    $role = isset($_GET['role']) ? sanitize_input($_GET['role']) : null;
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : null;
    $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : null; // active/inactive
    $pagination = get_pagination_params(50, 200);

    // Validate parameters
    if ($department_id !== null && $department_id <= 0) {
        send_error('Invalid department ID.', 'INVALID_DEPARTMENT_ID', 400);
    }

    if ($role !== null && !in_array($role, ['admin', 'employee'])) {
        send_error('Invalid role. Must be either admin or employee.', 'INVALID_ROLE', 400);
    }

    if ($status !== null && !in_array($status, ['active', 'inactive'])) {
        send_error('Invalid status. Must be either active or inactive.', 'INVALID_STATUS', 400);
    }

    // Build SQL query with filters
    $sql = "SELECT u.id, u.name, u.email, u.role, u.department_id, u.created_at,
                   d.name as department_name,
                   COUNT(a.id) as attendance_count,
                   MAX(a.date) as last_attendance_date,
                   CASE
                       WHEN MAX(a.date) = CURDATE() THEN 'present'
                       WHEN MAX(a.date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 'recent'
                       ELSE 'inactive'
                   END as activity_status
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN attendance a ON u.id = a.user_id";

    $where_clauses = [];
    $params = [];

    // Add department filter
    if ($department_id) {
        $where_clauses[] = "u.department_id = ?";
        $params[] = $department_id;
    }

    // Add role filter
    if ($role) {
        $where_clauses[] = "u.role = ?";
        $params[] = $role;
    }

    // Add search filter (search by name or email)
    if ($search) {
        $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Add WHERE clause if there are filters
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql .= " GROUP BY u.id, u.name, u.email, u.role, u.department_id, u.created_at, d.name";

    // Add status filter (based on activity)
    if ($status) {
        $having_clauses = [];
        switch ($status) {
            case 'active':
                $having_clauses[] = "MAX(a.date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'inactive':
                $having_clauses[] = "(MAX(a.date) < DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR MAX(a.date) IS NULL)";
                break;
        }

        if (!empty($having_clauses)) {
            $sql .= " HAVING " . implode(' AND ', $having_clauses);
        }
    }

    // Add ordering and pagination
    $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];

    // Execute query
    $users = dbFetchAll($sql, $params);

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM users u";

    $count_where_clauses = [];
    $count_params = [];

    if ($department_id) {
        $count_where_clauses[] = "u.department_id = ?";
        $count_params[] = $department_id;
    }
    if ($role) {
        $count_where_clauses[] = "u.role = ?";
        $count_params[] = $role;
    }
    if ($search) {
        $count_where_clauses[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }

    if (!empty($count_where_clauses)) {
        $count_sql .= " WHERE " . implode(' AND ', $count_where_clauses);
    }

    $count_result = dbFetch($count_sql, $count_params);
    $total_records = $count_result ? (int)$count_result['total'] : 0;

    // Format user records
    $formatted_users = [];
    foreach ($users as $user) {
        $formatted_users[] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'] ? (int)$user['department_id'] : null,
            'department_name' => $user['department_name'],
            'attendance_count' => (int)($user['attendance_count'] ?? 0),
            'last_attendance_date' => $user['last_attendance_date'],
            'activity_status' => $user['activity_status'] ?? 'inactive',
            'created_at' => $user['created_at']
        ];
    }

    // Get summary statistics
    $summary_sql = "SELECT
                       COUNT(*) as total_users,
                       COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
                       COUNT(CASE WHEN role = 'employee' THEN 1 END) as employee_count,
                       COUNT(CASE WHEN department_id IS NOT NULL THEN 1 END) as with_department,
                       COUNT(DISTINCT department_id) as departments_used
                   FROM users";

    $summary = dbFetch($summary_sql);

    // Prepare response data
    $response_data = [
        'users' => $formatted_users,
        'summary' => [
            'total_users' => (int)($summary['total_users'] ?? 0),
            'admin_count' => (int)($summary['admin_count'] ?? 0),
            'employee_count' => (int)($summary['employee_count'] ?? 0),
            'with_department' => (int)($summary['with_department'] ?? 0),
            'departments_used' => (int)($summary['departments_used'] ?? 0)
        ],
        'filters' => [
            'department_id' => $department_id,
            'role' => $role,
            'search' => $search,
            'status' => $status
        ],
        'pagination' => [
            'total' => $total_records,
            'limit' => $pagination['limit'],
            'offset' => $pagination['offset'],
            'has_more' => ($pagination['offset'] + $pagination['limit']) < $total_records
        ]
    ];

    // Log user list view activity
    log_activity($current_user['id'], 'VIEW_ALL_USERS', "Records: " . count($formatted_users));

    // Send success response
    send_success($response_data, 'Users list retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("List users endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving users list.', 'SERVER_ERROR', 500);
}

?>