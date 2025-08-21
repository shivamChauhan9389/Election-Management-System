<?php
require_once 'db.php';
require_once 'role_helper.php';

echo "<h2>Adding Assistant Role to HOD Users</h2>";

// Get all HOD users
$get_hod_users = "SELECT id, fullname, email FROM users WHERE role = 'hod'";
$result = $conn->query($get_hod_users);

if ($result) {
    $updated_count = 0;
    while ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        
        // Check if user already has assistant role
        if (!user_has_role($user_id, 'assistant')) {
            // Add assistant role
            $insert_role = "INSERT INTO user_roles (user_id, role) VALUES (?, 'assistant')";
            $stmt = $conn->prepare($insert_role);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $updated_count++;
                echo "<p style='color: green;'>✓ Added assistant role to HOD: {$user['fullname']} ({$user['email']})</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to add assistant role to {$user['fullname']}: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: blue;'>ℹ HOD {$user['fullname']} already has assistant role</p>";
        }
    }
    
    if ($updated_count > 0) {
        echo "<p style='color: green;'>✓ Successfully added assistant role to {$updated_count} HOD users!</p>";
        echo "<p><strong>Now these HOD users can login and choose between HOD or Assistant role!</strong></p>";
    } else {
        echo "<p style='color: blue;'>ℹ All HOD users already have assistant role</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Error fetching HOD users: " . $conn->error . "</p>";
}

echo "<h3>Current User Roles Summary:</h3>";

// Show current roles for all users
$get_all_users = "SELECT u.id, u.fullname, u.email, u.role as primary_role FROM users u ORDER BY u.role, u.fullname";
$result = $conn->query($get_all_users);

if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 20px;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Email</th><th>Primary Role</th><th>All Roles</th></tr>";
    
    while ($user = $result->fetch_assoc()) {
        $user_roles = get_user_roles($user['id']);
        $roles_display = implode(', ', $user_roles);
        
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['fullname']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['primary_role']}</td>";
        echo "<td>{$roles_display}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ Error fetching users: " . $conn->error . "</p>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "<p><a href='create_roles_table.php'>Re-run Roles Table Creation</a></p>";

$conn->close();
?>

