<?php
/**
 * API Index/Info Endpoint
 * Provides information about the API and available endpoints
 */

// Define access for included files
define('ALLOW_ACCESS', true);

// Include required files
require_once __DIR__ . '/utils/helpers.php';

// Handle CORS preflight request
handle_cors();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED', 405);
}

try {
    // Get API information
    $api_info = [
        'name' => 'Zaphira Attendance System API',
        'version' => '1.0.0',
        'description' => 'Complete PHP backend API for attendance management with user authentication and role-based access control.',
        'status' => 'active',
        'timestamp' => date('c'),
        'environment' => $_ENV['APP_ENV'] ?? 'development'
    ];

    // Get available endpoints
    $endpoints = [
        'authentication' => [
            'POST /auth/login.php' => 'Authenticate user and create session',
            'POST /auth/register.php' => 'Register new user account',
            'GET /auth/check-session.php' => 'Validate current session',
            'GET /auth/logout.php' => 'Destroy user session'
        ],
        'attendance' => [
            'POST /attendance/checkin.php' => 'Record employee check-in',
            'POST /attendance/checkout.php' => 'Record employee check-out',
            'GET /attendance/viewSelf.php' => 'Get personal attendance history',
            'GET /attendance/viewAll.php' => 'Get all attendance records (admin only)'
        ],
        'users' => [
            'GET /users/list.php' => 'List all users (admin only)',
            'GET /users/getSingle.php' => 'Get single user details',
            'POST /users/update.php' => 'Update user information',
            'DELETE /users/delete.php' => 'Delete user (admin only)'
        ],
        'departments' => [
            'GET /departments/list.php' => 'List all departments',
            'POST /departments/create.php' => 'Create new department (admin only)',
            'POST /departments/update.php' => 'Update department (admin only)',
            'DELETE /departments/delete.php' => 'Delete department (admin only)'
        ],
        'utility' => [
            'GET /index.php' => 'API information (this page)',
            'GET /error.php' => 'API error handler'
        ]
    ];

    // Get system information (limited in production)
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'timezone' => date_default_timezone_get(),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    ];

    // Add database status (if configured)
    $database_status = [
        'configured' => false,
        'connected' => false,
        'tables' => []
    ];

    if (file_exists(__DIR__ . '/.env')) {
        $database_status['configured'] = true;

        // Try to connect to database (without exposing credentials)
        try {
            // Load environment variables
            $env_file = __DIR__ . '/.env';
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env_vars = [];

            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $env_vars[trim($key)] = trim($value, '"\'');
            }

            if (isset($env_vars['DB_HOST'], $env_vars['DB_NAME'], $env_vars['DB_USER'])) {
                $dsn = "mysql:host=" . $env_vars['DB_HOST'] . ";dbname=" . $env_vars['DB_NAME'];
                $pdo = new PDO($dsn, $env_vars['DB_USER'], $env_vars['DB_PASS'] ?? '');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $database_status['connected'] = true;

                // Check for required tables
                $required_tables = ['users', 'departments', 'attendance', 'activity_logs'];
                foreach ($required_tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    $database_status['tables'][$table] = $stmt->rowCount() > 0;
                }

                // Get basic statistics
                $stats = [];
                $stats['users'] = $pdo->query("SELECT COUNT(*) as count FROM users")->fetchColumn();
                $stats['departments'] = $pdo->query("SELECT COUNT(*) as count FROM departments")->fetchColumn();
                $stats['attendance_records'] = $pdo->query("SELECT COUNT(*) as count FROM attendance")->fetchColumn();
                $stats['activity_logs'] = $pdo->query("SELECT COUNT(*) as count FROM activity_logs")->fetchColumn();

                $database_status['statistics'] = $stats;
            }
        } catch (Exception $e) {
            $database_status['error'] = 'Connection failed';
        }
    }

    // Prepare response data
    $response_data = [
        'api' => $api_info,
        'endpoints' => $endpoints,
        'system' => $system_info,
        'database' => $database_status
    ];

    // Add security notes in development
    if (($env_vars['APP_ENV'] ?? 'development') === 'development') {
        $response_data['security_notes'] = [
            '⚠️ Development Mode: Sensitive information may be exposed',
            '💡 Ensure DEBUG=false in production',
            '🔒 Change default admin password immediately',
            '🛡️ Configure CORS_ORIGIN to your frontend domain',
            '📝 Use HTTPS in production'
        ];
    }

    // Send success response
    send_success($response_data, 'API information retrieved successfully');

} catch (Exception $e) {
    // Log unexpected errors
    error_log("API index endpoint error: " . $e->getMessage());
    send_error('An unexpected error occurred while retrieving API information.', 'SERVER_ERROR', 500);
}

?>