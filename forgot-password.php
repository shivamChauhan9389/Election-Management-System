<?php
session_start();
require_once 'logger.php'; // Include the logger

$error_msg = $_SESSION['error_message'] ?? null;
$success_msg = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Log access to forgot password page
log_action("Accessed forgot password page");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container" style="flex-direction: column; max-width: 500px;">
        <div class="form-container" style="width: 100%;">
            <h1 class="logo">Reset Password</h1>
            <p style="color: #ccc; margin-bottom: 20px;">Enter your email or phone number to receive an OTP.</p>

            <?php if ($error_msg): ?>
                <div style="color: #f87171; font-weight: bold; margin-bottom: 15px;"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div style="color: #4ade80; font-weight: bold; margin-bottom: 15px;"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <form action="handle_forgot_password.php" method="post">
                <input type="text" name="identifier" placeholder="Email or Phone Number" maxlength="50" required>

                <div class="captcha" style="margin-top: 15px;">
                    <img src="captcha.php" alt="CAPTCHA" onclick="this.src='captcha.php?'+Math.random();" style="cursor:pointer; border-radius: 5px;">
                    <input type="text" name="captcha" placeholder="Enter CAPTCHA" required>
                </div>

                <button type="submit">Send OTP</button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php" style="color: #3b82f6; text-decoration: none;">Back to Login</a>
            </p>
        </div>
    </div>
</body>

</html>