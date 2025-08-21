<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../logger.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    try {
        log_action("Session check failed - no user ID", null, 'AUTH', 'FAILURE', $_SESSION);
    } catch (Exception $e) {
        // Log error silently
    }
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Debug: Log session info
try {
    log_action("Session check passed", $_SESSION['id'], 'AUTH', 'SUCCESS', [
        'session_id' => session_id(),
        'user_id' => $_SESSION['id'],
        'username' => $_SESSION['username'] ?? 'N/A'
    ]);
} catch (Exception $e) {
    // Log error silently
}

// Debug: Log the request
try {
    log_action("HOD form submission received", $_SESSION['id'] ?? null, 'FORM_SUBMISSION', 'INFO', [
        'post_data' => $_POST,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A'
    ]);
} catch (Exception $e) {
    // Log error silently
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get user_id first
    $user_id = $_SESSION['id'];
    
    // Test database connection
    if (!$conn->ping()) {
        try {
            log_action("Database connection lost", $user_id, 'ERROR', 'FAILURE');
        } catch (Exception $e) {
            // Log error silently
        }
        echo json_encode(['success' => false, 'message' => 'Database connection error. Please try again.']);
        exit;
    }
    
    // Check if user_details table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_details'");
    if ($table_check->num_rows === 0) {
        try {
            log_action("user_details table does not exist", $user_id, 'ERROR', 'FAILURE');
        } catch (Exception $e) {
            // Log error silently
        }
        echo json_encode(['success' => false, 'message' => 'Database configuration error. Please contact administrator.']);
        exit;
    }
    
    // Check if users table exists (required for foreign key)
    $users_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($users_check->num_rows === 0) {
        try {
            log_action("users table does not exist", $user_id, 'ERROR', 'FAILURE');
        } catch (Exception $e) {
            // Log error silently
        }
        echo json_encode(['success' => false, 'message' => 'Database configuration error: users table missing.']);
        exit;
    }
    
    // Check if the user exists in users table
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if ($user_check) {
        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_result = $user_check->get_result();
        if ($user_result->num_rows === 0) {
            try {
                log_action("User ID " . $user_id . " does not exist in users table", $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'User not found in database.']);
            exit;
        }
        $user_check->close();
    }
    
    // Check table structure
    $structure_check = $conn->query("DESCRIBE user_details");
    if (!$structure_check) {
        try {
            log_action("Cannot describe user_details table: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
        } catch (Exception $e) {
            // Log error silently
        }
        echo json_encode(['success' => false, 'message' => 'Database configuration error. Please contact administrator.']);
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

// Set location details based on address type
if ($address_type === 'urban') {
    $district = $_POST['urbanCity'] ?? '';
    $constituency = $_POST['urbanWard'] ?? '';
    $assembly_segment = 'Urban Area';
    $polling_station = $_POST['urbanCity'] . ' - ' . $_POST['urbanWard'];
} elseif ($address_type === 'rural') {
    $district = $_POST['ruralDistrict'] ?? '';
    $constituency = $_POST['ruralTehsil'] ?? '';
    $assembly_segment = 'Rural Area';
    $polling_station = $_POST['ruralDistrict'] . ' - ' . $_POST['ruralTehsil'];
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
        'Superintendent of Police', 'Deputy Superintendent of Police', 'Assistant Superintendent of Police',
        'Inspector General of Police', 'Deputy Inspector General of Police', 'Senior Superintendent of Police',
        'Additional Superintendent of Police', 'Circle Officer', 'Station House Officer', 'Sub-Inspector',
        'Head Constable', 'Senior Constable', 'Constable', 'Traffic Inspector', 'Traffic Sub-Inspector',
        'CID Inspector', 'CID Sub-Inspector', 'Other'
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

// Location Details Validation
if (empty($district) || empty($constituency)) {
    $errors[] = 'Location details are required.';
} else {
    // Validate district and constituency based on address type
    if ($address_type === 'urban') {
        $valid_urban_cities = ['dehradun', 'haridwar', 'roorkee', 'haldwani', 'rudrapur', 'kashipur', 'rishikesh'];
        $valid_urban_wards = ['zone1', 'zone2', 'zone3', 'zone4'];
        
        if (!in_array($district, $valid_urban_cities)) {
            $errors[] = 'Please select a valid urban city.';
        }
        if (!in_array($constituency, $valid_urban_wards)) {
            $errors[] = 'Please select a valid urban ward.';
        }
    } elseif ($address_type === 'rural') {
        $valid_rural_districts = ['almora', 'bageshwar', 'chamoli', 'champawat', 'dehradun', 'haridwar', 'nainital', 'pauri', 'pithoragarh', 'rudraprayag', 'tehri', 'udhamsingh', 'uttarkashi'];
        $valid_rural_tehsils = ['tehsil1', 'tehsil2', 'tehsil3'];
        
        if (!in_array($district, $valid_rural_districts)) {
            $errors[] = 'Please select a valid rural district.';
        }
        if (!in_array($constituency, $valid_rural_tehsils)) {
            $errors[] = 'Please select a valid rural tehsil.';
        }
    }
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Check if HOD details already exist for this user
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
                log_action("HOD details updated successfully", $user_id, 'SUCCESS', 'INFO');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => true, 'message' => 'HOD details updated successfully!']);
        } else {
            try {
                log_action("Database update error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not update HOD details.']);
        }
        $update_stmt->close();
    } else {
        // INSERT new record
        $insert_sql = "INSERT INTO user_details (
            user_id, email, gender, date_of_birth, post_position, department, 
            address_type, district, constituency, assembly_segment, polling_station, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
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
                log_action("HOD details inserted successfully", $user_id, 'SUCCESS', 'INFO');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => true, 'message' => 'HOD details submitted successfully!']);
        } else {
            try {
                log_action("Database insert error: " . $conn->error, $user_id, 'ERROR', 'FAILURE');
            } catch (Exception $e) {
                // Log error silently
            }
            echo json_encode(['success' => false, 'message' => 'Database error: Could not insert HOD details.']);
        }
        $insert_stmt->close();
    }

$conn->close();

} catch (Exception $e) {
    try {
        log_action("Unexpected error in HOD form processing: " . $e->getMessage(), $_SESSION['id'] ?? null, 'ERROR', 'FAILURE');
    } catch (Exception $log_e) {
        // Log error silently
    }
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
?>
