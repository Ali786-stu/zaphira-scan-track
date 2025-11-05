<?php
/**
 * Role-based Access Control Middleware
 * Handles role-based permissions and access control
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    http_response_code(403);
    exit('Direct access denied');
}

// Include required files
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/authMiddleware.php';

/**
 * Check if current user has specific role
 */
function has_role($role) {
    $user = get_current_user();
    return $user && $user['role'] === $role;
}

/**
 * Check if current user is admin
 */
function is_admin() {
    return has_role('admin');
}

/**
 * Check if current user is employee
 */
function is_employee() {
    return has_role('employee');
}

/**
 * Require specific role - terminates script if user doesn't have role
 */
function require_role($required_role) {
    // Ensure user is authenticated first
    if (!is_authenticated()) {
        send_error('Authentication required.', 'AUTH_REQUIRED', 401);
    }

    // Check if user has required role
    if (!has_role($required_role)) {
        log_activity($_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', "Required role: $required_role, User role: " . $_SESSION['user_role']);
        send_error('Access denied. Insufficient permissions.', 'INSUFFICIENT_PERMISSIONS', 403);
    }
}

/**
 * Require admin role
 */
function require_admin() {
    require_role('admin');
}

/**
 * Require employee role
 */
function require_employee() {
    require_role('employee');
}

/**
 * Check if user can access resource (owner or admin)
 */
function can_access_resource($resource_user_id) {
    $current_user = get_current_user();

    // Admin can access all resources
    if ($current_user['role'] === 'admin') {
        return true;
    }

    // User can access their own resources
    return $current_user['id'] == $resource_user_id;
}

/**
 * Require access to specific resource (owner or admin)
 */
function require_resource_access($resource_user_id) {
    // Ensure user is authenticated first
    if (!is_authenticated()) {
        send_error('Authentication required.', 'AUTH_REQUIRED', 401);
    }

    if (!can_access_resource($resource_user_id)) {
        log_activity($_SESSION['user_id'], 'FORBIDDEN_RESOURCE_ACCESS', "Resource owner: $resource_user_id");
        send_error('Access denied. You can only access your own resources.', 'RESOURCE_ACCESS_DENIED', 403);
    }
}

/**
 * Check if user can modify resource (owner or admin)
 */
function can_modify_resource($resource_user_id) {
    return can_access_resource($resource_user_id);
}

/**
 * Require modification access to specific resource (owner or admin)
 */
function require_modification_access($resource_user_id) {
    require_resource_access($resource_user_id);
}

/**
 * Check if current user can view other users' data
 */
function can_view_all_users() {
    return is_admin();
}

/**
 * Require ability to view all users
 */
function require_view_all_users() {
    require_admin();
}

/**
 * Check if current user can modify other users
 */
function can_modify_users() {
    return is_admin();
}

/**
 * Require ability to modify users
 */
function require_modify_users() {
    require_admin();
}

/**
 * Check if current user can manage departments
 */
function can_manage_departments() {
    return is_admin();
}

/**
 * Require ability to manage departments
 */
function require_manage_departments() {
    require_admin();
}

/**
 * Check if current user can view all attendance records
 */
function can_view_all_attendance() {
    return is_admin();
}

/**
 * Require ability to view all attendance
 */
function require_view_all_attendance() {
    require_admin();
}

/**
 * Check if current user can manage system settings
 */
function can_manage_system() {
    return is_admin();
}

/**
 * Require ability to manage system
 */
function require_manage_system() {
    require_admin();
}

/**
 * Get role-based filter for database queries
 */
function get_role_filter($table_alias = '') {
    $user = get_current_user();
    $prefix = $table_alias ? $table_alias . '.' : '';

    if ($user['role'] === 'admin') {
        return ''; // Admin can see all records
    }

    // Employee can only see their own records
    return $prefix . 'user_id = ' . (int)$user['id'];
}

/**
 * Apply role-based filter to SQL query
 */
function apply_role_filter($sql, $table_alias = '') {
    $filter = get_role_filter($table_alias);
    if ($filter) {
        $sql .= ' WHERE ' . $filter;
    }
    return $sql;
}

/**
 * Check if action is allowed for user role
 */
function is_action_allowed($action, $resource_type = null) {
    $user = get_current_user();
    $role = $user['role'];

    // Define permissions matrix
    $permissions = [
        'admin' => [
            'users' => ['create', 'read', 'update', 'delete', 'list'],
            'departments' => ['create', 'read', 'update', 'delete', 'list'],
            'attendance' => ['read_all', 'read_own', 'create', 'update'],
            'system' => ['read', 'update']
        ],
        'employee' => [
            'users' => ['read_own', 'update_own'],
            'departments' => ['read'],
            'attendance' => ['read_own', 'create', 'update'],
            'system' => []
        ]
    ];

    // System actions (not resource-specific)
    if (!$resource_type) {
        return in_array($action, $permissions[$role]['system'] ?? []);
    }

    // Resource-specific actions
    $resource_permissions = $permissions[$role][$resource_type] ?? [];
    return in_array($action, $resource_permissions);
}

/**
 * Require permission for specific action
 */
function require_permission($action, $resource_type = null) {
    // Ensure user is authenticated
    if (!is_authenticated()) {
        send_error('Authentication required.', 'AUTH_REQUIRED', 401);
    }

    if (!is_action_allowed($action, $resource_type)) {
        $user = get_current_user();
        log_activity($_SESSION['user_id'], 'PERMISSION_DENIED', "Action: $action, Resource: $resource_type, Role: {$user['role']}");
        send_error('Access denied. Insufficient permissions for this action.', 'PERMISSION_DENIED', 403);
    }
}

/**
 * Log role-based access attempts
 */
function log_role_access($action, $resource_type, $resource_id = null, $allowed = true) {
    if (!is_authenticated()) return;

    $user = get_current_user();
    $status = $allowed ? 'ALLOWED' : 'DENIED';
    $details = "Action: $action, Resource: $resource_type";

    if ($resource_id) {
        $details .= ", ID: $resource_id";
    }

    $details .= ", Role: {$user['role']}, Status: $status";

    log_activity($user['id'], 'ROLE_ACCESS_CHECK', $details);
}

/**
 * Get user permissions summary (for debugging/audit)
 */
function get_user_permissions() {
    if (!is_authenticated()) {
        return [];
    }

    $user = get_current_user();
    $role = $user['role'];

    return [
        'user_id' => $user['id'],
        'role' => $role,
        'permissions' => [
            'can_view_all_users' => can_view_all_users(),
            'can_modify_users' => can_modify_users(),
            'can_manage_departments' => can_manage_departments(),
            'can_view_all_attendance' => can_view_all_attendance(),
            'can_manage_system' => can_manage_system()
        ]
    ];
}

/**
 * Check for privilege escalation attempts
 */
function check_privilege_escalation($target_role) {
    if (!is_authenticated()) return false;

    $current_user = get_current_user();
    $current_role = $current_user['role'];

    // Only admins can assign admin roles
    if ($target_role === 'admin' && $current_role !== 'admin') {
        log_activity($current_user['id'], 'PRIVILEGE_ESCALATION_ATTEMPT', "Target role: $target_role");
        return true;
    }

    return false;
}

/**
 * Validate role assignment
 */
function validate_role_assignment($target_role, $target_user_id = null) {
    if (!is_authenticated()) {
        send_error('Authentication required.', 'AUTH_REQUIRED', 401);
    }

    $current_user = get_current_user();

    // Only admins can assign roles
    if ($current_user['role'] !== 'admin') {
        send_error('Only administrators can assign roles.', 'ROLE_ASSIGNMENT_DENIED', 403);
    }

    // Validate role value
    $valid_roles = ['admin', 'employee'];
    if (!in_array($target_role, $valid_roles)) {
        send_error('Invalid role specified.', 'INVALID_ROLE', 400);
    }

    // Prevent self-demotion (admin can't remove their own admin role)
    if ($target_user_id == $current_user['id'] && $target_role !== 'admin') {
        send_error('Administrators cannot remove their own admin role.', 'SELF_DEMOTION_DENIED', 403);
    }

    return true;
}

?>