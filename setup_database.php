<?php
require_once 'db.php';

echo "<h2>Database Setup</h2>";

// Check if user_details table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_details'");
if ($table_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ user_details table already exists</p>";
} else {
    echo "<p style='color: orange;'>⚠ user_details table does not exist. Creating it now...</p>";
    
    // Read and execute the migration script
    $migration_sql = file_get_contents('migrate_to_unified_table.sql');
    
    // Split the SQL into individual statements
    $statements = explode(';', $migration_sql);
    
    $success = true;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            if (!$conn->query($statement)) {
                echo "<p style='color: red;'>✗ Error executing: " . substr($statement, 0, 50) . "... - " . $conn->error . "</p>";
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo "<p style='color: green;'>✓ Database tables created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Some errors occurred during database setup</p>";
    }
}

// Check if system_logs table exists
$logs_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($logs_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ system_logs table exists</p>";
} else {
    echo "<p style='color: orange;'>⚠ system_logs table does not exist. Creating it now...</p>";
    
    $create_logs_sql = "
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
    
    if ($conn->query($create_logs_sql)) {
        echo "<p style='color: green;'>✓ system_logs table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating system_logs table: " . $conn->error . "</p>";
    }
}

// Final check
$final_check = $conn->query("SHOW TABLES LIKE 'user_details'");
if ($final_check->num_rows > 0) {
    echo "<h3 style='color: green;'>✓ Database setup completed successfully!</h3>";
    echo "<p>You can now <a href='home/hod-details.php'>return to the HOD details form</a>.</p>";
} else {
    echo "<h3 style='color: red;'>✗ Database setup failed!</h3>";
    echo "<p>Please check the error messages above and try again.</p>";
}

$conn->close();
?>

