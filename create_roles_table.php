<?php
require_once 'db.php';

echo "<h2>Creating Roles Table for Hierarchical Login</h2>";

// Create roles table
$create_roles_table = "
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_user_role (user_id, role)
)";

if ($conn->query($create_roles_table)) {
    echo "<p style='color: green;'>✓ user_roles table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating user_roles table: " . $conn->error . "</p>";
}

// Migrate existing users to the new roles system
echo "<h3>Migrating Existing Users to New Roles System</h3>";

// Get all existing users
$get_users = "SELECT id, role FROM users";
$result = $conn->query($get_users);

if ($result) {
    $migrated_count = 0;
    while ($user = $result->fetch_assoc()) {
        // Insert role into user_roles table
        $insert_role = "INSERT IGNORE INTO user_roles (user_id, role) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_role);
        $stmt->bind_param("is", $user['id'], $user['role']);
        
        if ($stmt->execute()) {
            $migrated_count++;
            echo "<p style='color: green;'>✓ Migrated user ID {$user['id']} with role '{$user['role']}'</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to migrate user ID {$user['id']}: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
    echo "<p style='color: green;'>✓ Migration complete! {$migrated_count} users migrated.</p>";
    
    // Ensure all existing HODs also have assistant role
    $add_assistant_for_hods = "INSERT IGNORE INTO user_roles (user_id, role)
        SELECT id, 'assistant' FROM users WHERE role = 'hod'";
    if ($conn->query($add_assistant_for_hods)) {
        echo "<p style='color: green;'>✓ Ensured all HODs also have assistant role.</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to assign assistant role to HODs: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Error fetching users: " . $conn->error . "</p>";
}

// Add a new HOD user with both roles for testing
echo "<h3>Creating Test HOD with Multiple Roles</h3>";
$test_hod_name = "Test HOD";
$test_hod_email = "testhod@example.com";
$test_hod_mobile = "+919999999999";
$test_hod_password = password_hash("Test123!", PASSWORD_DEFAULT);

// Check if test user already exists
$check_user = "SELECT id FROM users WHERE email = ? OR mobile = ?";
$stmt = $conn->prepare($check_user);
$stmt->bind_param("ss", $test_hod_email, $test_hod_mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Insert test user
    $insert_user = "INSERT INTO users (fullname, email, mobile, password, role, is_verified) VALUES (?, ?, ?, ?, 'hod', 1)";
    $stmt = $conn->prepare($insert_user);
    $stmt->bind_param("ssss", $test_hod_name, $test_hod_email, $test_hod_mobile, $test_hod_password);
    
    if ($stmt->execute()) {
        $test_user_id = $conn->insert_id;
        
        // Insert both roles for this user
        $roles = ['hod', 'assistant'];
        foreach ($roles as $role) {
            $insert_role = "INSERT INTO user_roles (user_id, role) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_role);
            $stmt->bind_param("is", $test_user_id, $role);
            $stmt->execute();
        }
        
        echo "<p style='color: green;'>✓ Test HOD user created with ID {$test_user_id} and both roles (hod, assistant)</p>";
        echo "<p><strong>Test Credentials:</strong></p>";
        echo "<p>Email: {$test_hod_email}</p>";
        echo "<p>Mobile: {$test_hod_mobile}</p>";
        echo "<p>Password: Test123!</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create test user: " . $stmt->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Test user already exists</p>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";

$conn->close();
?>

