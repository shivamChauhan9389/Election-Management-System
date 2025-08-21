<?php
session_start();
require_once 'db.php';
require_once 'send_email.php';
require_once 'send_sms.php';
require_once 'logger.php';

// --- Main Logic ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    log_action("Unauthorized access attempt to handle_forgot_password.php (not POST request)");
    header("Location: forgot-password.php");
    exit;
}

// 1. Validate Captcha
if (!isset($_POST['captcha']) || $_POST['captcha'] != $_SESSION['captcha']) {
    $_SESSION['error_message'] = "Incorrect CAPTCHA answer.";
    log_action("Forgot password attempt failed: Incorrect CAPTCHA for identifier: " . ($_POST['identifier'] ?? 'N/A'));
    header("Location: forgot-password.php");
    exit;
}

$identifier = trim($_POST['identifier']);
$conn = $GLOBALS['conn']; // Use the connection from db_connect.php

// 2. Find the user by identifier and check verification status
$stmt = $conn->prepare("SELECT id, fullname, email, mobile, is_verified FROM users WHERE email = ? OR mobile = ?");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "No account found with that identifier.";
    log_action("Forgot password attempt failed: User not found for identifier: " . $identifier);
    header("Location: forgot-password.php");
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$email = $user['email'];
$mobile_from_db = $user['mobile']; // Get mobile number from database
$is_verified = $user['is_verified'];
$stmt->close();

// Check if user is verified
if ($is_verified == 0) {
    $_SESSION['error_message'] = "Your account is not verified. Please verify your account first before resetting your password.";
    log_action("Forgot password attempt failed: Unverified account for identifier: " . $identifier);
    header("Location: forgot-password.php");
    exit;
}

// --- Format mobile number for Twilio if necessary ---
$formatted_mobile = $mobile_from_db;
// Check if the mobile number from the database doesn't already start with '+'
// and if it's not empty. If so, prepend '+91'.
if (!empty($mobile_from_db) && substr($mobile_from_db, 0, 1) !== '+') {
    // Assuming all unformatted numbers are Indian numbers for this application
    $formatted_mobile = '+91' . $mobile_from_db;
    log_action("Formatted mobile number from DB for Twilio: " . $mobile_from_db . " -> " . $formatted_mobile, $user_id);
}


// 3. Generate and Store OTP
$otp = rand(100000, 999999);
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);
$expires_at = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

// Clear any old OTPs for this user first
$stmt_delete = $conn->prepare("DELETE FROM otp_reset WHERE user_id = ?");
$stmt_delete->bind_param("i", $user_id);
if ($stmt_delete->execute()) {
    log_action("Cleared old OTPs for password reset", $user_id);
} else {
    log_action("Database error: Failed to clear old OTPs for password reset for user ID: " . $user_id . " - " . $conn->error);
}
$stmt_delete->close();

// Insert the new OTP
$stmt_insert = $conn->prepare("INSERT INTO otp_reset (user_id, otp_hash, expires_at) VALUES (?, ?, ?)");
$stmt_insert->bind_param("iss", $user_id, $otp_hash, $expires_at);
if ($stmt_insert->execute()) {
    log_action("New OTP generated and stored for password reset", $user_id);
} else {
    log_action("Database error: Failed to store new OTP for password reset for user ID: " . $user_id . " - " . $conn->error);
}
$stmt_insert->close();

// 4. Send the OTP
$otp_sent = false;
$delivery_method = "";

// Prefer email if available, otherwise use SMS
if (!empty($email)) {
    if (send_otp_email($email, $otp)) {
        $otp_sent = true;
        $delivery_method = "your registered email.";
        log_action("Password reset OTP sent via email", $user_id);
    } else {
        log_action("Failed to send password reset OTP via email", $user_id);
    }
}

// Only try SMS if email wasn't sent or wasn't available, AND mobile is present
if (!$otp_sent && !empty($formatted_mobile)) { // Use formatted_mobile here
    if (send_otp_sms($formatted_mobile, $otp)) {
        $otp_sent = true;
        $delivery_method = "your registered phone number.";
        log_action("Password reset OTP sent via SMS", $user_id);
    } else {
        log_action("Failed to send password reset OTP via SMS", $user_id);
    }
}


// 5. Redirect to OTP verification page
if ($otp_sent) {
    $_SESSION['reset_user_id'] = $user_id; // Store user ID for the next step
    $_SESSION['success_message'] = "An OTP has been sent to " . $delivery_method;
    header("Location: verify-password-otp.php");
    exit;
} else {
    $_SESSION['error_message'] = "Failed to send OTP. Please try again later.";
    log_action("Failed to send password reset OTP by any method", $user_id);
    header("Location: forgot-password.php");
    exit;
}
