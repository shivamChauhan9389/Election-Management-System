<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

// Redirect if user ID isn't set
if (!isset($_SESSION['reset_user_id'])) {
    log_action("Unauthorized access attempt to verify-password-otp.php (no reset_user_id in session)");
    header("Location: forgot-password.php");
    exit;
}

$error_msg = '';
$success_msg = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = trim($_POST['otp']);
    $user_id = $_SESSION['reset_user_id'];

    // Get the stored OTP from the new table
    $stmt = $conn->prepare("SELECT otp_hash, expires_at FROM otp_reset WHERE user_id = ? AND verified = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $otp_data = $result->fetch_assoc();
        $is_expired = new DateTime() > new DateTime($otp_data['expires_at']);

        if ($is_expired) {
            $error_msg = "OTP has expired. Please request a new one.";
            log_action("Password reset OTP verification failed: Expired OTP for user ID: " . $user_id);
        } elseif (password_verify($user_otp, $otp_data['otp_hash'])) {
            // OTP is correct! Mark it as verified and set session for final step
            $stmt_update = $conn->prepare("UPDATE otp_reset SET verified = 1 WHERE user_id = ?");
            $stmt_update->bind_param("i", $user_id);
            if ($stmt_update->execute()) {
                log_action("Password reset OTP successfully verified", $user_id);
            } else {
                log_action("Database error: Failed to mark OTP as verified for user ID: " . $user_id . " - " . $conn->error);
            }
            $stmt_update->close();
            
            $_SESSION['password_reset_authorized'] = true;
            header("Location: reset-password-form.php"); // Redirect to the final form
            exit;
        } else {
            $error_msg = "Invalid OTP entered.";
            log_action("Password reset OTP verification failed: Incorrect OTP for user ID: " . $user_id);
        }
    } else {
        $error_msg = "No pending OTP found. Please request a new one.";
        log_action("Password reset OTP verification failed: No pending OTP found for user ID: " . $user_id);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="flex-direction: column; max-width: 500px;">
    <div class="form-container" style="width: 100%;">
        <h1 class="logo">Enter OTP</h1>
        
        <?php if ($error_msg): ?>
            <div style="color: #f87171; font-weight: bold; margin-bottom: 15px;"><?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <div style="color: #4ade80; font-weight: bold; margin-bottom: 15px;"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="otp" placeholder="Enter 6-Digit OTP" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button type="submit" style="margin-top: 15px;">Verify OTP</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <button type="button" onclick="resendPasswordOTP()" style="background: none; border: none; color: #3b82f6; text-decoration: none; font-size: 0.9em; cursor: pointer; padding: 0;">Resend OTP</button>
            <div id="resendMessage" style="margin-top: 10px; font-size: 0.8em;"></div>
        </div>
        
        <script>
        let resendCountdown = 60;
        let canResend = true;
        
        function resendPasswordOTP() {
            if (!canResend) return;
            
            // Disable button and start countdown
            canResend = false;
            const button = event.target;
            const originalText = button.textContent;
            
            // Send AJAX request to resend OTP
            fetch('resend_password_otp_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=resend_password_otp'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('resendMessage').innerHTML = '<span style="color: green;">OTP resent successfully!</span>';
                    startCountdown(button, originalText);
                } else {
                    document.getElementById('resendMessage').innerHTML = '<span style="color: red;">Failed to resend OTP. Please try again.</span>';
                    canResend = true;
                }
            })
            .catch(error => {
                document.getElementById('resendMessage').innerHTML = '<span style="color: red;">Error occurred. Please try again.</span>';
                canResend = true;
            });
        }
        
        function startCountdown(button, originalText) {
            let countdown = resendCountdown;
            button.textContent = `Resend OTP (${countdown}s)`;
            
            const timer = setInterval(() => {
                countdown--;
                button.textContent = `Resend OTP (${countdown}s)`;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    button.textContent = originalText;
                    canResend = true;
                    document.getElementById('resendMessage').innerHTML = '';
                }
            }, 1000);
        }
        
        // Add OTP input validation
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.querySelector('input[name="otp"]');
            
            if (otpInput) {
                otpInput.addEventListener('input', function() {
                    // Only allow numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Limit to 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.substring(0, 6);
                    }
                });
            }
        });
        </script>
    </div>
</div>
</body>
</html>
