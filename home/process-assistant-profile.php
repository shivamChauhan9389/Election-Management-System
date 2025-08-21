<?php
session_start();
require_once '../logger.php';
require_once '../db.php';
require_once '../role_manager.php';

header('Content-Type: application/json');

// Check if user is logged in and is assistant
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'assistant') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only assistants can update their profile.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get user ID
$user_id = $_SESSION['id'];

// Check database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Check if user_details table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_details'");
if ($table_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Database table not found.']);
    exit;
}

// Check table structure
$structure_check = $conn->query("DESCRIBE user_details");
if (!$structure_check) {
    echo json_encode(['success' => false, 'message' => 'Could not check table structure.']);
    exit;
}

// Log table structure for debugging
$columns = [];
while ($row = $structure_check->fetch_assoc()) {
    $columns[] = $row['Field'];
}
try {
    log_action("user_details table structure: " . implode(', ', $columns), $user_id, 'DEBUG', 'INFO');
} catch (Exception $e) {
    // Log error silently
}

// Get form data
$full_name = trim($_POST['firstName'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$date_of_birth = trim($_POST['dateOfBirth'] ?? '');
$post_position = trim($_POST['post'] ?? '');
$custom_post = trim($_POST['customPost'] ?? '');
$department = trim($_POST['department'] ?? '');
$address_type = trim($_POST['addressType'] ?? '');

// Handle custom post/position
if ($post_position === 'Other' && !empty($custom_post)) {
    $post_position = $custom_post;
}

// Check if this is an update (user already has details)
$is_update = false;
if (isset($_SESSION['id'])) {
    $check_sql = "SELECT id FROM user_details WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $_SESSION['id']);
        $check_stmt->execute();
        $is_update = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
    }
}

// Debug: Log received data
try {
    log_action("Form data received", $user_id, 'DEBUG', 'INFO', [
        'full_name' => $full_name,
        'phone' => $phone,
        'email' => $email,
        'gender' => $gender,
        'date_of_birth' => $date_of_birth,
        'post_position' => $post_position,
        'department' => $department,
        'address_type' => $address_type
    ]);
} catch (Exception $e) {
    // Log error silently
}

// Initialize location variables
$district = '';
$constituency = '';
$assembly_segment = '';
$polling_station = '';

// Set location details based on address type (simplified for assistant profile)
if ($address_type === 'urban') {
    $district = 'Urban Area';
    $constituency = 'Urban Zone';
    $assembly_segment = 'Urban Area';
    $polling_station = 'Urban Polling Station';
} elseif ($address_type === 'rural') {
    $district = 'Rural Area';
    $constituency = 'Rural Zone';
    $assembly_segment = 'Rural Area';
    $polling_station = 'Rural Polling Station';
}

// Comprehensive Validation
$errors = [];

// Full Name Validation (only for new registrations)
if (!$is_update) {
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s\.]+$/', $full_name)) {
        $errors[] = 'Full name can only contain letters, spaces, and dots.';
    }
}

// Phone Number Validation (only for new registrations)
if (!$is_update) {
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[6789]\d{9}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit phone number starting with 6, 7, 8, or 9.';
    } else {
        // Add +91 prefix for database storage
        $phone = '+91' . $phone;
    }
}

// Email Validation (Optional)
if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email address cannot exceed 100 characters.';
    }
}

// Gender Validation
if (empty($gender)) {
    $errors[] = 'Gender is required.';
} elseif (!in_array($gender, ['male', 'female', 'other'])) {
    $errors[] = 'Please select a valid gender.';
}

// Date of Birth and Age Validation
if (empty($date_of_birth)) {
    $errors[] = 'Date of birth is required.';
} else {
    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d', $date_of_birth);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date_of_birth) {
        $errors[] = 'Please enter a valid date of birth.';
    } else {
        // Calculate age
        $today = new DateTime();
        $age = $today->diff($date_obj)->y;
        
        if ($age < 18) {
            $errors[] = 'You must be at least 18 years old to register. Current age: ' . $age . ' years.';
        } elseif ($age > 100) {
            $errors[] = 'Please enter a valid date of birth. Age cannot exceed 100 years.';
        }
    }
}

// Post/Position Validation
if (empty($post_position)) {
    $errors[] = 'Post/Position is required.';
} else {
    $valid_positions = [
        'Assistant', 'Senior Assistant', 'Junior Assistant', 'Field Assistant', 'Office Assistant', 'Other'
    ];
    
    if ($post_position === 'Other') {
        if (empty($custom_post)) {
            $errors[] = 'Please specify your post/position when selecting "Other".';
        } elseif (strlen($custom_post) < 3 || strlen($custom_post) > 100) {
            $errors[] = 'Custom post/position must be between 3 and 100 characters.';
        }
    } elseif (!in_array($post_position, $valid_positions)) {
        $errors[] = 'Please select a valid post/position.';
    }
}

// Department Validation
if (empty($department)) {
    $errors[] = 'Department is required.';
} elseif (!in_array($department, ['law-order', 'cid', 'traffic'])) {
    $errors[] = 'Please select a valid department.';
}

// Address Type Validation
if (empty($address_type)) {
    $errors[] = 'Address type is required.';
} elseif (!in_array($address_type, ['urban', 'rural'])) {
    $errors[] = 'Please select a valid address type.';
}

// Location Details Validation (simplified for assistant profile)
if (empty($address_type)) {
    $errors[] = 'Address type is required.';
} elseif (!in_array($address_type, ['urban', 'rural'])) {
    $errors[] = 'Please select a valid address type.';
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Debug: Log before database operations
try {
    log_action("Starting database operations", $user_id, 'DEBUG', 'INFO');
} catch (Exception $e) {
    // Log error silently
}

// Check if assistant details already exist for this user
try {
    $check_sql = "SELECT id FROM user_details WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        try {
            log_action("Database prepare error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
        } catch (Exception $e) {
            // Log error silently
        }
        echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare statement.']);
        exit;
    }
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_stmt->close();

    if ($check_result->num_rows > 0) {
        // UPDATE existing record
        // For updates, use session values for name and phone since they're readonly
        $full_name = $_SESSION['name'] ?? '';
        $phone = $_SESSION['phn'] ?? '';
        
        $update_sql = "UPDATE user_details SET 
            email = ?,
            gender = ?, 
            date_of_birth = ?, 
            post_position = ?, 
            department = ?, 
            address_type = ?, 
            district = ?, 
            constituency = ?, 
            assembly_segment = ?, 
            polling_station = ?, 
            updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            try {
                log_action("Database prepare error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare update statement.']);
            exit;
        }
        $update_stmt->bind_param("ssssssssssi", 
            $email, $gender, $date_of_birth, $post_position, $department, $address_type,
            $district, $constituency, $assembly_segment, $polling_station, $user_id
        );
        
        if ($update_stmt->execute()) {
            try {
                log_action("Assistant details updated successfully", $user_id, 'SUCCESS', 'INFO');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => true, 'message' => 'Assistant details updated successfully!']);
        } else {
            try {
                log_action("Database update error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not update assistant details.']);
        }
        $update_stmt->close();
    } else {
        // INSERT new record
        $insert_sql = "INSERT INTO user_details (
            user_id, email, gender, date_of_birth, post_position, department, 
            address_type, district, constituency, assembly_segment, polling_station
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            try {
                log_action("Database prepare error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare insert statement.']);
            exit;
        }
        $insert_stmt->bind_param("issssssssss", 
            $user_id, $email, $gender, $date_of_birth, $post_position, $department,
            $address_type, $district, $constituency, $assembly_segment, $polling_station
        );
        
        if ($insert_stmt->execute()) {
            try {
                log_action("Assistant details inserted successfully", $user_id, 'SUCCESS', 'INFO');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => true, 'message' => 'Assistant details submitted successfully!']);
        } else {
            try {
                log_action("Database insert error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not insert assistant details.']);
        }
        $insert_stmt->close();
    }
} catch (Exception $e) {
    try {
        log_action("Unexpected error: " . $e->getMessage(), $user_id, 'ERROR', 'FAILURE');
    } catch (Exception $logError) {
        // Log error silently
    }
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>
