<?php
require_once 'db.php';

echo "<h2>Fixing user_details Table Structure</h2>";

// Drop the existing table if it exists
echo "<p>Dropping existing user_details table...</p>";
$conn->query("DROP TABLE IF EXISTS user_details");

// Create the table with correct structure
$create_table_sql = "
CREATE TABLE user_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    date_of_birth DATE NULL,
    address_type ENUM('urban', 'rural') NULL,
    district VARCHAR(100) NULL,
    constituency VARCHAR(100) NULL,
    assembly_segment VARCHAR(100) NULL,
    polling_station VARCHAR(100) NULL,
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

if ($conn->query($create_table_sql)) {
    echo "<p style='color: green;'>✓ user_details table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating user_details table: " . $conn->error . "</p>";
    exit;
}

// Verify the table structure
$structure = $conn->query("DESCRIBE user_details");
if ($structure) {
    echo "<h3>New table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test the insert SQL
echo "<h3>Testing Insert SQL:</h3>";
$test_insert_sql = "INSERT INTO user_details (
    user_id, gender, date_of_birth, post_position, department, 
    address_type, district, constituency, assembly_segment, polling_station
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$test_stmt = $conn->prepare($test_insert_sql);
if (!$test_stmt) {
    echo "<p style='color: red;'>✗ Prepare failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color: green;'>✓ Prepare successful</p>";
    $test_stmt->close();
}

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='home/hod-details.php'>Return to HOD Details Form</a></p>";

$conn->close();
?>

