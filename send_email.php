<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

// Load configuration
$config = [];
if (file_exists(__DIR__ . '/config_local.php')) {
    $config = require __DIR__ . '/config_local.php';
} else {
    $config = require __DIR__ . '/config.example.php';
}

// Create a function to send emails
function send_otp_email($recipient_email, $otp)
{
    // Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        // --- Server settings ---
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for troubleshooting
        $mail->isSMTP();
        $mail->Host       = $config['mail']['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail']['username'] ?? '';
        $mail->Password   = $config['mail']['password'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['mail']['port'] ?? 587;

        // --- Recipients ---
        $mail->setFrom($config['mail']['from_email'] ?? 'no-reply@example.com', $config['mail']['from_name'] ?? 'Election');
        $mail->addAddress($recipient_email);           // Add a recipient

        // --- Content ---
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'Your Account Verification OTP';
        $mail->Body    = 'Hi there, <br><br> Thank you for registering. Your One-Time Password (OTP) for account verification is: <b>' . $otp . '</b><br><br>This OTP is valid for 10 minutes.<br><br>Regards,<br>Your App Team';
        $mail->AltBody = 'Your One-Time Password (OTP) for account verification is: ' . $otp;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // You can log the error for debugging purposes
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to send general emails
function send_email($recipient_email, $subject, $message)
{
    // Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        // --- Server settings ---
        $mail->isSMTP();
        $mail->Host       = $config['mail']['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail']['username'] ?? '';
        $mail->Password   = $config['mail']['password'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['mail']['port'] ?? 587;

        // --- Recipients ---
        $mail->setFrom($config['mail']['from_email'] ?? 'no-reply@example.com', $config['mail']['from_name'] ?? 'Uttarakhand Election Portal');
        $mail->addAddress($recipient_email);           // Add a recipient

        // --- Content ---
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message); // Plain text version

        $mail->send();
        return true;
    } catch (Exception $e) {
        // You can log the error for debugging purposes
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
