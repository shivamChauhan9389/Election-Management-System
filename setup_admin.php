<?php
// Setup script to create admin user and verify database
require_once 'db.php';

echo "<h2>Admin Setup and Database Verification</h2>";

// Check if database connection works
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
echo "✓ Database connection successful<br>";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows > 0) {
    echo "✓ Users table exists<br>";
} else {
    echo "✗ Users table does not exist. Please run the database setup first.<br>";
    exit;
}

// Check if system_logs table exists
$result = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($result && $result->num_rows > 0) {
    echo "✓ System logs table exists<br>";
} else {
    echo "✗ System logs table does not exist. Please run the database setup first.<br>";
    exit;
}

// Check if admin user exists
$stmt = $conn->prepare("SELECT id, fullname, email, mobile, role, is_verified FROM users WHERE role = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "✓ Admin user(s) found:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "&nbsp;&nbsp;- ID: {$row['id']}, Name: {$row['fullname']}, Email: {$row['email']}, Verified: " . ($row['is_verified'] ? 'Yes' : 'No') . "<br>";
    }
} else {
    echo "✗ No admin user found. Creating one...<br>";
    
    // Create admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, mobile, password, role, is_verified) VALUES (?, ?, ?, ?, 'admin', 1)");
    $stmt->bind_param("ssss", 
        'System Administrator',
        'admin@election.gov.in',
        '+919876543210',
        $admin_password
    );
    
    if ($stmt->execute()) {
        echo "✓ Admin user created successfully!<br>";
        echo "&nbsp;&nbsp;- Email: admin@election.gov.in<br>";
        echo "&nbsp;&nbsp;- Password: admin123<br>";
    } else {
        echo "✗ Failed to create admin user: " . $stmt->error . "<br>";
    }
}

// Check if there are any logs
$result = $conn->query("SELECT COUNT(*) as count FROM system_logs");
$count = $result->fetch_assoc()['count'];
echo "✓ System logs count: $count<br>";

// Test login functionality
echo "<h3>Testing Login:</h3>";
echo "1. Go to: <a href='login.php' target='_blank'>login.php</a><br>";
echo "2. Login with: admin@election.gov.in / admin123<br>";
echo "3. After login, you should be able to access: <a href='admin_logs.php' target='_blank'>admin_logs.php</a><br>";

// Show current session info
echo "<h3>Current Session Info:</h3>";
session_start();
if (isset($_SESSION['username'])) {
    echo "✓ Logged in as: " . $_SESSION['username'] . "<br>";
    echo "✓ Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
    echo "✓ User ID: " . ($_SESSION['id'] ?? 'Not set') . "<br>";
} else {
    echo "✗ Not logged in<br>";
}

$conn->close();
echo "<hr>";
echo "<p><strong>Setup completed. Try logging in now!</strong></p>";
?>

