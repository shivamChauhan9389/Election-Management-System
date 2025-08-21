<?php
session_start();
require_once '../db.php';
require_once '../logger.php';
require_once '../role_manager.php';

header('Content-Type: application/json');

// Check if user is logged in and is HOD or admin
if (!isset($_SESSION['id']) || !is_hod_or_admin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only HODs can add assistants.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Debug: Log all POST data
log_action("POST data received: " . json_encode($_POST), $_SESSION['id'] ?? null);

// Check if we're receiving any data at all
if (empty($_POST)) {
    echo json_encode(['success' => false, 'message' => 'No form data received. Please check your form submission.']);
    exit;
}

// Get form data
$hod_id = $_SESSION['id'];
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');

// Debug: Log the received data
log_action("Assistant registration attempt - Fullname: '$fullname', Email: '$email', Mobile: '$mobile'", $hod_id);

// Validation
$errors = [];

if (empty($fullname)) {
    $errors[] = 'Full name is required.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($mobile)) {
    $errors[] = 'Phone number is required.';
} elseif (!preg_match('/^[6789]\d{9}$/', $mobile)) {
    $errors[] = 'Please enter a valid 10-digit phone number starting with 6, 7, 8, or 9.';
} else {
    // Add +91 prefix for database storage
    $mobile = '+91' . $mobile;
}

// If there are validation errors, return them
if (!empty($errors)) {
    $error_message = implode(' ', $errors);
    log_action("Assistant registration validation failed: $error_message", $hod_id);
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

// Check if email or mobile already exists in users table
$check_user_sql = "SELECT id FROM users WHERE email = ? OR mobile = ?";
$check_user_stmt = $conn->prepare($check_user_sql);
$check_user_stmt->bind_param("ss", $email, $mobile);
$check_user_stmt->execute();
$check_user_result = $check_user_stmt->get_result();

if ($check_user_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A user with this email or phone number already exists.']);
    $check_user_stmt->close();
    exit;
}
$check_user_stmt->close();

// Check if email or mobile already exists in pending registrations
$check_pending_sql = "SELECT id FROM pending_assistant_registrations WHERE email = ? OR mobile = ?";
$check_pending_stmt = $conn->prepare($check_pending_sql);
$check_pending_stmt->bind_param("ss", $email, $mobile);
$check_pending_stmt->execute();
$check_pending_result = $check_pending_stmt->get_result();

if ($check_pending_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'An assistant with this email or phone number is already pending registration.']);
    $check_pending_stmt->close();
    exit;
}
$check_pending_stmt->close();

// Generate registration token
$registration_token = bin2hex(random_bytes(32));
$token_expires_at = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

// Insert into pending assistant registrations
$insert_sql = "INSERT INTO pending_assistant_registrations (
    fullname, email, mobile, added_by_hod_id, registration_token, token_expires_at
) VALUES (?, ?, ?, ?, ?, ?)";

$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("sssiss", $fullname, $email, $mobile, $hod_id, $registration_token, $token_expires_at);

if ($insert_stmt->execute()) {
    $pending_id = $conn->insert_id;
    
    // Log the action
    log_action("Added assistant to pending registrations: $fullname ($email)", $hod_id);
    
    // Send email to assistant with registration link
    $registration_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/../assistant-setup.php?token=" . $registration_token;
    
    // Email content
    $subject = "Complete Your Assistant Registration - Uttarakhand Election Portal";
    $message = "
    <html>
    <body>
        <h2>Assistant Registration Invitation</h2>
        <p>Hello $fullname,</p>
        <p>You have been added as an assistant to the Uttarakhand Election Portal by your HOD.</p>
        <p>To complete your registration and set up your account, please click the link below:</p>
        <p><a href='$registration_link' style='background: #385185; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Complete Registration</a></p>
        <p>Or copy and paste this link in your browser:</p>
        <p>$registration_link</p>
        <p><strong>Important:</strong> This link will expire in 7 days.</p>
        <p>If you did not expect this invitation, please ignore this email.</p>
        <br>
        <p>Best regards,<br>Uttarakhand Election Portal Team</p>
    </body>
    </html>
    ";
    
    // Send email to assistant
    require_once '../send_email.php';
    
    try {
        $mail_result = send_email($email, $subject, $message);
        if ($mail_result) {
            log_action("Registration email sent successfully to: $email", $hod_id);
        } else {
            log_action("Failed to send registration email to: $email", $hod_id);
        }
    } catch (Exception $e) {
        log_action("Email sending error: " . $e->getMessage(), $hod_id);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Assistant '$fullname' has been added successfully! They will receive an email to complete their registration."
    ]);
} else {
    log_action("Error adding assistant to pending registrations: " . $insert_stmt->error, $hod_id);
    echo json_encode(['success' => false, 'message' => 'Database error: Could not add assistant.']);
}

$insert_stmt->close();
$conn->close();
?>
