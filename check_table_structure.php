<?php
require_once 'db.php';

echo "<h2>Database Table Structure Check</h2>";

// Check if user_details table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_details'");
if ($table_check->num_rows === 0) {
    echo "<p style='color: red;'>✗ user_details table does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>✓ user_details table exists</p>";

// Get table structure
$structure = $conn->query("DESCRIBE user_details");
if (!$structure) {
    echo "<p style='color: red;'>✗ Cannot describe user_details table: " . $conn->error . "</p>";
    exit;
}

echo "<h3>Current user_details table structure:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

$columns = [];
while ($row = $structure->fetch_assoc()) {
    $columns[] = $row['Field'];
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

echo "<h3>Columns found: " . implode(', ', $columns) . "</h3>";

// Check required columns for HOD form
$required_columns = [
    'id', 'user_id', 'gender', 'date_of_birth', 'post_position', 'department',
    'address_type', 'district', 'constituency', 'assembly_segment', 'polling_station',
    'created_at', 'updated_at'
];

echo "<h3>Required columns for HOD form:</h3>";
echo "<ul>";
foreach ($required_columns as $col) {
    if (in_array($col, $columns)) {
        echo "<li style='color: green;'>✓ " . $col . "</li>";
    } else {
        echo "<li style='color: red;'>✗ " . $col . " (MISSING)</li>";
    }
}
echo "</ul>";

// Test the insert SQL
echo "<h3>Testing Insert SQL:</h3>";
$test_insert_sql = "INSERT INTO user_details (
    user_id, gender, date_of_birth, post_position, department, 
    address_type, district, constituency, assembly_segment, polling_station
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

echo "<p>SQL: " . $test_insert_sql . "</p>";

$test_stmt = $conn->prepare($test_insert_sql);
if (!$test_stmt) {
    echo "<p style='color: red;'>✗ Prepare failed: " . $conn->error . "</p>";
} else {
    echo "<p style='color: green;'>✓ Prepare successful</p>";
    $test_stmt->close();
}

$conn->close();
?>

