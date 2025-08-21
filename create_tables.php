<?php
require_once 'db.php';

echo "<h2>Creating Essential Database Tables</h2>";

// Create user_details table
$create_user_details = "
CREATE TABLE IF NOT EXISTS user_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    date_of_birth DATE NULL,
    address_type ENUM('urban', 'rural') NULL,
    district VARCHAR(100) NULL,
    constituency VARCHAR(100) NULL,
    assembly_segment VARCHAR(100) NULL,
    polling_station VARCHAR(100) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    post_position VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    assistant_type ENUM('polling', 'counting', 'security', 'transport', 'other') NULL,
    assigned_location VARCHAR(200) NULL,
    supervisor_id INT NULL,
    employee_id VARCHAR(50) NULL,
    designation VARCHAR(100) NULL,
    joining_date DATE NULL,
    admin_level ENUM('super', 'regular', 'limited') NULL,
    permissions JSON NULL,
    custom_fields JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_district (district),
    INDEX idx_constituency (constituency),
    INDEX idx_department (department),
    INDEX idx_post_position (post_position),
    INDEX idx_assistant_type (assistant_type),
    INDEX idx_supervisor_id (supervisor_id),
    INDEX idx_created_at (created_at)
)";

if ($conn->query($create_user_details)) {
    echo "<p style='color: green;'>✓ user_details table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating user_details table: " . $conn->error . "</p>";
}

// Create system_logs table
$create_system_logs = "
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    session_id VARCHAR(255) NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(10) NULL,
    request_uri TEXT NULL,
    additional_data JSON NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_status (status),
    INDEX idx_timestamp (timestamp),
    INDEX idx_ip_address (ip_address)
)";

if ($conn->query($create_system_logs)) {
    echo "<p style='color: green;'>✓ system_logs table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating system_logs table: " . $conn->error . "</p>";
}

// Check if users table exists (required for foreign key)
$users_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($users_check->num_rows === 0) {
    echo "<p style='color: red;'>✗ users table does not exist! This is required for the foreign key constraint.</p>";
    echo "<p>Please run the complete database setup first.</p>";
} else {
    echo "<p style='color: green;'>✓ users table exists</p>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='home/hod-details.php'>Return to HOD Details Form</a></p>";

$conn->close();
?>

