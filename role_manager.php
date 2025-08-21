<?php
/**
 * Role Management Helper Functions
 * Handles role-based access control for the election portal
 */

/**
 * Check if user has required role
 * @param string $required_role - The role required to access the page
 * @param string $user_role - The user's current role
 * @return bool - True if user has access, false otherwise
 */
function has_role_access($required_role, $user_role = null) {
    if ($user_role === null) {
        $user_role = $_SESSION['role'] ?? '';
    }
    
    // Role hierarchy (higher roles have access to lower role pages)
    $role_hierarchy = [
        'admin' => ['admin', 'hod', 'employee', 'assistant'],
        'hod' => ['hod', 'employee', 'assistant'],
        'employee' => ['employee'],
        'assistant' => ['assistant']
    ];
    
    return in_array($required_role, $role_hierarchy[$user_role] ?? []);
}

/**
 * Check if user has access to a specific role (for role switching)
 * @param int $user_id - The user ID
 * @param string $required_role - The role required
 * @return bool - True if user has access to the role, false otherwise
 */
function user_can_access_role($user_id, $required_role) {
    require_once 'role_helper.php';
    $user_roles = get_user_roles($user_id);
    return in_array($required_role, $user_roles);
}

/**
 * Get role display name
 * @param string $role - The role code
 * @return string - The display name for the role
 */
function get_role_display_name($role) {
    $role_names = [
        'admin' => 'Administrator',
        'hod' => 'Head of Department',
        'employee' => 'Employee',
        'assistant' => 'Assistant'
    ];
    
    return $role_names[$role] ?? ucfirst($role);
}

/**
 * Get role description
 * @param string $role - The role code
 * @return string - The description for the role
 */
function get_role_description($role) {
    $role_descriptions = [
        'admin' => 'Full system access with user management capabilities',
        'hod' => 'Department head with employee management access',
        'employee' => 'Standard employee with basic form access',
        'assistant' => 'Assistant with limited form access'
    ];
    
    return $role_descriptions[$role] ?? 'No description available';
}

/**
 * Check if user can access a specific page
 * @param string $page_name - The name of the page to check
 * @return bool - True if user can access, false otherwise
 */
function can_access_page($page_name) {
    $user_role = $_SESSION['role'] ?? '';
    
    // Define page access rules
    $page_access = [
        'admin_logs' => ['admin'],
        'hod_details' => ['admin', 'hod'],
        'employee_details' => ['admin', 'hod', 'employee'],
        'assistant_details' => ['admin', 'hod', 'assistant'],
        'assistant_profile' => ['assistant'],
        'election_data' => ['admin', 'hod', 'employee'],
        'user_management' => ['admin'],
        'system_settings' => ['admin']
    ];
    
    $required_roles = $page_access[$page_name] ?? ['employee'];
    return in_array($user_role, $required_roles);
}

/**
 * Redirect user if they don't have access
 * @param string $required_role - The role required
 * @param string $redirect_url - Where to redirect if access denied
 */
function require_role($required_role, $redirect_url = 'home/home.php') {
    if (!has_role_access($required_role)) {
        $_SESSION['access_denied'] = "You don't have permission to access this page. Required role: " . get_role_display_name($required_role);
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Get user's accessible pages based on their role
 * @param string $user_role - The user's role
 * @return array - Array of accessible page names
 */
function get_accessible_pages($user_role = null) {
    if ($user_role === null) {
        $user_role = $_SESSION['role'] ?? '';
    }
    
    $all_pages = [
        'admin_logs' => ['admin'],
        'hod_details' => ['admin', 'hod'],
        'employee_details' => ['admin', 'hod', 'employee'],
        'assistant_details' => ['admin', 'hod', 'assistant'],
        'assistant_profile' => ['assistant'],
        'user_management' => ['admin'],
        'system_settings' => ['admin']
    ];
    
    $accessible_pages = [];
    foreach ($all_pages as $page => $allowed_roles) {
        if (in_array($user_role, $allowed_roles)) {
            $accessible_pages[] = $page;
        }
    }
    
    return $accessible_pages;
}

/**
 * Get navigation menu items based on user role
 * @param string $user_role - The user's role
 * @return array - Array of menu items
 */
function get_navigation_menu($user_role = null) {
    if ($user_role === null) {
        $user_role = $_SESSION['role'] ?? '';
    }
    
    $menu_items = [
        'admin' => [
            ['name' => 'Dashboard', 'url' => 'home/home.php', 'icon' => 'fas fa-tachometer-alt'],
            ['name' => 'Admin Logs', 'url' => '../admin_logs.php', 'icon' => 'fas fa-chart-line'],
            ['name' => 'HOD Details', 'url' => 'hod-details.php', 'icon' => 'fas fa-user-tie'],
            ['name' => 'Employee Details', 'url' => 'employee-details.php', 'icon' => 'fas fa-users'],
            ['name' => 'Assistant Management', 'url' => 'assistant-details.php', 'icon' => 'fas fa-user-plus'],
            ['name' => 'User Management', 'url' => 'user-management.php', 'icon' => 'fas fa-user-cog'],
            ['name' => 'System Settings', 'url' => 'system-settings.php', 'icon' => 'fas fa-cog']
        ],
        'hod' => [
            ['name' => 'Dashboard', 'url' => 'home/home.php', 'icon' => 'fas fa-tachometer-alt'],
            ['name' => 'HOD Details', 'url' => 'hod-details.php', 'icon' => 'fas fa-user-tie'],
            ['name' => 'Employee Details', 'url' => 'employee-details.php', 'icon' => 'fas fa-users'],
            ['name' => 'Assistant Management', 'url' => 'assistant-details.php', 'icon' => 'fas fa-user-plus']
        ],
        'employee' => [
            ['name' => 'Dashboard', 'url' => 'home/home.php', 'icon' => 'fas fa-tachometer-alt'],
            ['name' => 'Employee Details', 'url' => 'employee-details.php', 'icon' => 'fas fa-users']
        ],
        'assistant' => [
            ['name' => 'Dashboard', 'url' => 'home/home.php', 'icon' => 'fas fa-tachometer-alt'],
            ['name' => 'My Profile', 'url' => 'assistant-profile.php', 'icon' => 'fas fa-user']
        ]
    ];
    
    $menu = $menu_items[$user_role] ?? $menu_items['employee'];
    
    // Append role switch actions as direct links (full redirect)
    if ($user_role === 'hod') {
        $menu[] = ['name' => 'Switch to Assistant', 'url' => 'home.php?switch_role=assistant', 'icon' => 'fas fa-exchange-alt'];
    } elseif ($user_role === 'assistant') {
        $user_id = $_SESSION['id'] ?? null;
        if ($user_id && user_can_access_role($user_id, 'hod')) {
            $menu[] = ['name' => 'Switch to HOD', 'url' => 'home.php?switch_role=hod', 'icon' => 'fas fa-exchange-alt'];
        }
    }
    
    return $menu;
}

/**
 * Check if user is admin
 * @return bool - True if user is admin
 */
function is_admin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if user is HOD or admin
 * @return bool - True if user is HOD or admin
 */
function is_hod_or_admin() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['admin', 'hod']);
}

/**
 * Check if user is employee or higher
 * @return bool - True if user is employee or higher
 */
function is_employee_or_higher() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['admin', 'hod', 'employee']);
}
?>
