<?php
/**
 * Role Helper Functions for Hierarchical Login System
 * Handles fetching user roles from the new user_roles table
 */

/**
 * Get all roles for a specific user
 * @param int $user_id - The user ID
 * @return array - Array of roles for the user
 */
function get_user_roles($user_id) {
    global $conn;
    
    if (!isset($conn)) {
        require_once 'db.php';
    }
    
    $roles = [];
    $sql = "SELECT role FROM user_roles WHERE user_id = ? AND is_active = 1 ORDER BY role";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row['role'];
        }
        $stmt->close();
    }
    
    return $roles;
}

/**
 * Check if user has a specific role
 * @param int $user_id - The user ID
 * @param string $role - The role to check
 * @return bool - True if user has the role, false otherwise
 */
function user_has_role($user_id, $role) {
    $user_roles = get_user_roles($user_id);
    return in_array($role, $user_roles);
}

/**
 * Get the highest priority role for a user
 * @param int $user_id - The user ID
 * @return string - The highest priority role
 */
function get_highest_role($user_id) {
    $user_roles = get_user_roles($user_id);
    
    // Role hierarchy (higher index = higher priority)
    $role_hierarchy = ['assistant', 'employee', 'hod', 'admin'];
    
    $highest_role = 'assistant'; // Default
    foreach ($role_hierarchy as $role) {
        if (in_array($role, $user_roles)) {
            $highest_role = $role;
        }
    }
    
    return $highest_role;
}

/**
 * Get available roles for role selection popup
 * @param int $user_id - The user ID
 * @return array - Array of available roles with display names
 */
function get_available_roles_for_selection($user_id) {
    $user_roles = get_user_roles($user_id);
    $available_roles = [];
    
    $role_display_names = [
        'admin' => 'Administrator',
        'hod' => 'Head of Department',
        'employee' => 'Employee',
        'assistant' => 'Assistant'
    ];
    
    foreach ($user_roles as $role) {
        if (isset($role_display_names[$role])) {
            $available_roles[] = [
                'value' => $role,
                'display' => $role_display_names[$role]
            ];
        }
    }
    
    // Sort by hierarchy to show highest first (admin > hod > employee > assistant)
    $weight = ['admin' => 4, 'hod' => 3, 'employee' => 2, 'assistant' => 1];
    usort($available_roles, function($a, $b) use ($weight) {
        return ($weight[$b['value']] ?? 0) <=> ($weight[$a['value']] ?? 0);
    });

    return $available_roles;
}
?>

