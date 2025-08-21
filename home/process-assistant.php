<?php
session_start();
// --- FIX: Corrected paths to match the structure in home.php ---
require_once '../db.php'; 
require_once '../logger.php';

header('Content-Type: application/json');

// --- Basic Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['name']) || !isset($_POST['phone']) || !isset($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required form fields.']);
    exit;
}

$name = trim($_POST['name']);
$phone = trim($_POST['phone']);
$email = trim($_POST['email']);

if (empty($name) || empty($phone) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// More specific validation
if (!preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid name format.']);
    exit;
}

// Phone number validation - 10 digits starting with 6,7,8,9
if (!preg_match("/^[6789]\d{9}$/", $phone)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number starting with 6, 7, 8, or 9.']);
    exit;
} else {
    // Add +91 prefix for database storage
    $phone = '+91' . $phone;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
    exit;
}


// --- Insert into Database ---
try {
    // Using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO assistants (name, phone, email) VALUES (?, ?, ?)");
    
    // Bind the user-provided data to the statement
    $stmt->bind_param("sss", $name, $phone, $email);

    if ($stmt->execute()) {
        // Log the successful action
        log_action("New assistant added: " . $name, $_SESSION['id'] ?? null);
        echo json_encode(['success' => true, 'message' => 'Assistant successfully registered!']);
    } else {
        // Log the error
        log_action("Error adding assistant: " . $stmt->error, $_SESSION['id'] ?? null);
        echo json_encode(['success' => false, 'message' => 'Database error: Could not save assistant.']);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    log_action("Exception while adding assistant: " . $e->getMessage(), $_SESSION['id'] ?? null);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

?>