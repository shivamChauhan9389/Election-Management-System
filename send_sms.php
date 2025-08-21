<?php
// --- Twilio SMS Sending Logic ---

// Load Composer's autoloader
require 'vendor/autoload.php';

// Use the Twilio REST client
use Twilio\Rest\Client;

// Load configuration
$config = [];
if (file_exists(__DIR__ . '/config_local.php')) {
    $config = require __DIR__ . '/config_local.php';
} else {
    $config = require __DIR__ . '/config.example.php';
}

function send_otp_sms($recipient_phone, $otp)
{
    // --- Twilio Account Credentials from config ---
    $account_sid = $config['twilio']['account_sid'] ?? '';
    $auth_token = $config['twilio']['auth_token'] ?? '';
    $twilio_phone_number = $config['twilio']['from_number'] ?? '';

    try {
        // Create a new Twilio client
        $client = new Client($account_sid, $auth_token);

        // IMPORTANT: Format the recipient's phone number with a country code
        // Example for India: +91XXXXXXXXXX
        // You might need to add logic to format this correctly based on user input
        $formatted_recipient_phone = $recipient_phone; // Assuming user enters it in E.164 format for now

        // Use the client to send the message
        $client->messages->create(
            $formatted_recipient_phone,
            [
                'from' => $twilio_phone_number,
                'body' => 'Your verification OTP is: ' . $otp
            ]
        );

        return true;
    } catch (Exception $e) {
        // You can log the error for debugging
        // error_log('Twilio Error: ' . $e->getMessage());
        return false;
    }
}
