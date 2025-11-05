<?php
/**
 * List Departments Endpoint
 * Returns list of all departments
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

    // Get query parameters
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : null;
    $include_employee_count = isset($_GET['include_count']) && $_GET['include_count'] === 'true';
    $pagination = get_pagination_params(100, 500);

    // Build SQL query
    if ($include_employee_count) {
        $sql = "SELECT d.id, d.name, d.created_at,
                       COUNT(u.id) as employee_count
                FROM departments d
                LEFT JOIN users u ON d.id = u.department_id";
    } else {
        $sql = "SELECT d.id, d.name, d.created_at
                FROM departments d";
    }

    $params = [];
    $where_clauses = [];

    // Add search filter
    if ($search) {
        $where_clauses[] = "d.name LIKE ?";
        $params[] = "%$search%";
    }

    // Add WHERE clause if there are filters
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // Add GROUP BY if we're counting employees
    if ($include_employee_count) {
        $sql .= " GROUP BY d.id, d.name, d.created_at";
    }

    // Add ordering and pagination
    $sql .= " ORDER BY d.name ASC LIMIT ? OFFSET ?";
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];

    // Execute query
    $departments = dbFetchAll($sql, $params);

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM departments d";

    $count_params = [];
    $count_where_clauses = [];

    if ($search) {
        $count_where_clauses[] = "d.name LIKE ?";
        $count_params[] = "%$search%";
    }

    if (!empty($count_where_clauses)) {
        $count_sql .= " WHERE " . implode(' AND ', $count_where_clauses);
    }

    $count_result = dbFetch($count_sql, $count_params);
    $total_records = $count_result ? (int)$count_result['total'] : 0;

    // Format department records
    $formatted_departments = [];
    foreach ($departments as $dept) {
        $formatted_departments[] = [
            'id' => (int)$dept['id'],
            'name' => $dept['name'],
            'employee_count' => $include_employee_count ? (int)($dept['employee_count'] ?? 0) : null,
            'created_at' => $dept['created_at']
        ];
    }

    // Get summary statistics
    $summary_sql = "SELECT COUNT(*) as total_departments,
                           SUM(CASE WHEN employee_count > 0 THEN 1 ELSE 0 END) as departments_with_employees
                    FROM (
                        SELECT d.id, COUNT(u.id) as employee_count
                        FROM departments d
                        LEFT JOIN users u ON d.id = u.department_id
                        GROUP BY d.id
                    ) as dept_counts";

    $summary = dbFetch($summary_sql);

    // Prepare response data
    $response_data = [
        'departments' => $formatted_departments,
        'summary' => [
            'total_departments' => (int)($summary['total_departments'] ?? 0),
            'departments_with_employees' => (int)($summary['departments_with_employees'] ?? 0)
        ],
        'filters' => [
            'search' => $search,
            'include_employee_count' => $include_employee_count
        ],
        'pagination' => [
            'total' => $total_records,
            'limit' => $pagination['limit'],
            'offset' => $pagination['offset'],
            'has_more' => ($pagination['offset'] + $pagination['limit']) < $total_records
        ]
    ];

    // Log department list view activity
    log_activity($current_user['id'], 'VIEW_DEPARTMENTS', "Records: " . count($formatted_departments));

    // Send success response
    send_success($response_data, 'Departments list retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("List departments endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving departments list.', 'SERVER_ERROR', 500);
}

?>