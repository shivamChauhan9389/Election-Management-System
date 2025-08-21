<?php
require_once 'db.php';

echo "<h2>Adding Email Column to user_details Table</h2>";

// Check if email column already exists
$check_column = $conn->query("SHOW COLUMNS FROM user_details LIKE 'email'");
if ($check_column->num_rows > 0) {
    echo "<p style='color: green;'>✓ Email column already exists in user_details table</p>";
} else {
    echo "<p style='color: orange;'>⚠ Email column does not exist. Adding it now...</p>";
    
    // Add email column
    $add_email_sql = "ALTER TABLE user_details ADD COLUMN email VARCHAR(100) NULL AFTER user_id";
    
    if ($conn->query($add_email_sql)) {
        echo "<p style='color: green;'>✓ Email column added successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding email column: " . $conn->error . "</p>";
        exit;
    }
}

// Verify the table structure
$structure = $conn->query("DESCRIBE user_details");
if ($structure) {
    echo "<h3>Updated table structure:</h3>";
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

// Test the updated insert SQL
echo "<h3>Testing Updated Insert SQL:</h3>";
$test_insert_sql = "INSERT INTO user_details (
    user_id, email, gender, date_of_birth, post_position, department, 
    address_type, district, constituency, assembly_segment, polling_station
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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

