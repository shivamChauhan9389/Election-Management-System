<?php
// Copy this file to config_local.php and fill in your local credentials.

return [
    'db' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'user_auth',
        'charset' => 'utf8',
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'Election',
        'encryption' => 'tls',
    ],
    'twilio' => [
        'account_sid' => 'your_twilio_account_sid',
        'auth_token' => 'your_twilio_auth_token',
        'from_number' => '+1234567890',
    ],
    'admin' => [
        'password' => 'change_this_admin_password',
    ],
];


