<?php
/**
 * Helper Functions Utility
 * Common utility functions for the API
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403);
    exit('Direct access denied');
}

// Include database connection
require_once __DIR__ . '/db.php';

/**
 * Send standardized JSON response
 */
function send_json_response($success, $data = null, $message = null, $error_code = null, $http_code = 200) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();

    // Set headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');

    // Set HTTP status code
    http_response_code($http_code);

    // Build response
    $response = ['success' => (bool)$success];

    if ($success && $data !== null) {
        $response['data'] = $data;
    }

    if (!$success) {
        $response['error'] = $data; // In error case, data contains error message
        if ($error_code) {
            $response['error_code'] = $error_code;
        }
    }

    if ($message) {
        $response['message'] = $message;
    }

    // Send JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send success response
 */
function send_success($data = null, $message = null, $http_code = 200) {
    send_json_response(true, $data, $message, null, $http_code);
}

/**
 * Send error response
 */
function send_error($message, $error_code = null, $http_code = 400) {
    send_json_response(false, $message, null, $error_code, $http_code);
}

/**
 * Handle OPTIONS requests for CORS
 */
function handle_cors() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        exit(0);
    }
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate required fields
 */
function validate_required($data, $required_fields) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Validate string length
 */
function validate_length($value, $min = 0, $max = 255) {
    $length = strlen(trim($value));
    return $length >= $min && $length <= $max;
}

/**
 * Validate password strength
 */
function validate_password($password) {
    if (strlen($password) < 8) {
        return false;
    }

    // Check for at least one letter and one number
    return preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

/**
 * Hash password using bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log user activity
 */
function log_activity($user_id, $action, $details = null) {
    try {
        $sql = "INSERT INTO activity_logs (user_id, action) VALUES (?, ?)";
        dbQuery($sql, [$user_id, $action]);

        // Optional: Log details to file for debugging
        if ($details && ($_ENV['DEBUG'] ?? false)) {
            error_log("Activity: User $user_id - $action - $details");
        }
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Get pagination parameters
 */
function get_pagination_params($default_limit = 50, $max_limit = 100) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $default_limit;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Validate and clamp values
    $limit = max(1, min($limit, $max_limit));
    $offset = max(0, $offset);

    return ['limit' => $limit, 'offset' => $offset];
}

/**
 * Get date range from parameters
 */
function get_date_range() {
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;

    if ($start_date) {
        $start_date = date('Y-m-d', strtotime($start_date));
    }

    if ($end_date) {
        $end_date = date('Y-m-d', strtotime($end_date));
    }

    return ['start_date' => $start_date, 'end_date' => $end_date];
}

/**
 * Format date for display
 */
function format_date($date, $format = 'Y-m-d H:i:s') {
    if (!$date) return null;
    return date($format, strtotime($date));
}

/**
 * Calculate time difference in hours
 */
function calculate_hours($start_time, $end_time) {
    if (!$start_time || !$end_time) return null;

    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $diff = $start->diff($end);

    return round($diff->h + ($diff->i / 60), 2);
}

/**
 * Check if user exists by email
 */
function user_exists_by_email($email) {
    $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $result = dbFetch($sql, [$email]);
    return $result !== false;
}

/**
 * Get user by email
 */
function get_user_by_email($email) {
    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    return dbFetch($sql, [$email]);
}

/**
 * Get user by ID
 */
function get_user_by_id($id) {
    $sql = "SELECT u.*, d.name as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ? LIMIT 1";
    return dbFetch($sql, [$id]);
}

/**
 * Check if user has checked in today
 */
function has_checked_in_today($user_id) {
    $sql = "SELECT id FROM attendance
            WHERE user_id = ? AND date = CURDATE() AND checkin_time IS NOT NULL
            LIMIT 1";
    $result = dbFetch($sql, [$user_id]);
    return $result !== false;
}

/**
 * Check if user has checked out today
 */
function has_checked_out_today($user_id) {
    $sql = "SELECT id FROM attendance
            WHERE user_id = ? AND date = CURDATE() AND checkout_time IS NOT NULL
            LIMIT 1";
    $result = dbFetch($sql, [$user_id]);
    return $result !== false;
}

/**
 * Initialize session securely
 */
function init_session() {
    // Prevent session fixation
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');

        session_start();

        // Regenerate session ID for security
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

/**
 * Destroy session securely
 */
function destroy_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Unset all session variables
        $_SESSION = [];

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();
    }
}

/**
 * Rate limiting helper
 */
function check_rate_limit($key, $limit = 60, $window = 3600) {
    // Simple file-based rate limiting
    $file = sys_get_temp_dir() . '/rate_limit_' . md5($key);
    $current = time();

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data['reset_time'] < $current) {
            // Reset window
            $data = ['count' => 1, 'reset_time' => $current + $window];
        } else {
            // Increment counter
            $data['count']++;

            if ($data['count'] > $limit) {
                return false; // Rate limit exceeded
            }
        }
    } else {
        $data = ['count' => 1, 'reset_time' => $current + $window];
    }

    file_put_contents($file, json_encode($data));
    return true;
}

/**
 * Generate random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

?>