<?php
// --- FINAL OTP VERIFICATION LOGIC USING 'otp_reset' TABLE ---
session_start();

require_once 'db.php';
require_once 'logger.php';

if (!isset($_SESSION['verification_user_id'])) {
    log_action("Unauthorized access attempt to verify-otp.php (no verification_user_id in session)");
    header("location: login.php");
    exit;
}

require_once "db.php";
$otp_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["otp"]))) {
        $otp_err = "Please enter the OTP.";
        log_action("OTP verification failed: Empty OTP submitted for user ID: " . $_SESSION['verification_user_id']);
    } else {
        $user_otp = trim($_POST["otp"]);
        $user_id = $_SESSION['verification_user_id'];
        
        // *** FIX: Look for the OTP in the 'otp_reset' table ***
        $sql = "SELECT otp_hash, expires_at FROM otp_reset WHERE user_id = ? AND verified = 0";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($otp_hash, $expires_at);
                    $stmt->fetch();
                    
                    $is_expired = new DateTime() > new DateTime($expires_at);

                    if ($is_expired) {
                        $otp_err = "OTP has expired. <a href='verify.php' style='color: #3b82f6; text-decoration: none;'>Click here to resend verification OTP</a>";
                        log_action("OTP verification failed: Expired OTP for user ID: " . $user_id);
                    } elseif (password_verify($user_otp, $otp_hash)) {
                        // OTP is correct!
                        // *** FIX: Mark the user as verified in the 'users' table ***
                        $update_sql = "UPDATE users SET is_verified = 1 WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("i", $user_id);
                            if ($update_stmt->execute()) {
                                log_action("User account successfully marked as verified", $user_id);
                            } else {
                                log_action("Database error: Failed to mark user as verified for user ID: " . $user_id . " - " . $conn->error);
                            }
                            $update_stmt->close();

                            // Clean up the used OTP from the otp_reset table
                            $delete_otp_sql = "DELETE FROM otp_reset WHERE user_id = ?";
                            $delete_stmt = $conn->prepare($delete_otp_sql);
                            $delete_stmt->bind_param("i", $user_id);
                            if ($delete_stmt->execute()) {
                                log_action("OTP record deleted after successful verification", $user_id);
                            } else {
                                log_action("Database error: Failed to delete OTP record for user ID: " . $user_id . " - " . $conn->error);
                            }
                            $delete_stmt->close();

                            // Verification successful!
                            unset($_SESSION['verification_user_id']);
                            $_SESSION['login_message'] = "Verification successful! You can now log in.";
                            log_action("Account successfully verified via OTP", $user_id);
                            header("location: login.php");
                            exit();
                        } else {
                            $general_err = "Oops! Something went wrong updating user status.";
                            log_action("Database error: Could not prepare user verification update statement for user ID: " . $user_id . " - " . $conn->error);
                        }
                    } else {
                        $otp_err = "The OTP you entered is incorrect.";
                        log_action("OTP verification failed: Incorrect OTP for user ID: " . $user_id);
                    }
                } else {
                    $general_err = "No pending OTP found. <a href='verify.php' style='color: #3b82f6; text-decoration: none;'>Click here to resend verification OTP</a>";
                    log_action("OTP verification failed: No pending OTP found for user ID: " . $user_id);
                }
            } else {
                $general_err = "Oops! Something went wrong.";
                log_action("Database error during OTP retrieval for user ID: " . $user_id . " - " . $conn->error);
            }
            $stmt->close();
        } else {
            $general_err = "Oops! Something went wrong preparing OTP retrieval statement.";
            log_action("Database error: Could not prepare OTP retrieval statement for user ID: " . $user_id . " - " . $conn->error);
        }
    }
    $conn->close();
}
?>
<!-- The HTML form part of the file remains exactly the same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="flex-direction: column; max-width: 500px;">
    <div class="form-container" style="width:100%;">
        <h2 style="font-size: 1.8em;">Verify Your Account</h2>
        <p class="text-center text-gray-600 mb-6" style="color: #ccc;">An OTP has been sent to you. Please enter it below.</p>
        
        <?php if(!empty($general_err)){ echo '<div style="color: #f87171; font-weight: bold; margin-bottom: 15px;">' . $general_err . '</div>'; } ?>
        <?php if(!empty($otp_err)){ echo '<div style="color: #f87171; font-weight: bold; margin-bottom: 15px;">' . $otp_err . '</div>'; } ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="otp" placeholder="Enter 6-Digit OTP" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button type="submit" style="margin-top: 15px;">Verify Account</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <button type="button" onclick="resendOTP()" style="background: none; border: none; color: #3b82f6; text-decoration: none; font-size: 0.9em; cursor: pointer; padding: 0;">Resend OTP</button>
            <div id="resendMessage" style="margin-top: 10px; font-size: 0.8em;"></div>
        </div>
        
        <script>
        let resendCountdown = 60;
        let canResend = true;
        
        function resendOTP() {
            if (!canResend) return;
            
            // Disable button and start countdown
            canResend = false;
            const button = event.target;
            const originalText = button.textContent;
            
            // Send AJAX request to resend OTP
            fetch('resend_otp_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=resend_otp'
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
