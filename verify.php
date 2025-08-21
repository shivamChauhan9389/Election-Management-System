<?php
session_start();
require_once 'db.php';
require_once 'send_email.php';
require_once 'send_sms.php';
require_once 'logger.php';

$identifier = "";
$identifier_err = $captcha_err = $general_err = $success_msg = "";

// Check for success messages from other pages
if(isset($_SESSION['verify_message'])){
    $success_msg = $_SESSION['verify_message'];
    unset($_SESSION['verify_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['captcha_input']) || $_POST['captcha_input'] != $_SESSION['captcha']) {
        $captcha_err = "CAPTCHA does not match.";
        log_action("Verification attempt failed: Incorrect CAPTCHA for identifier: " . ($_POST["identifier"] ?? 'N/A'));
    } else {
        $identifier = trim($_POST["identifier"]);
        
        // Check if input is email or phone number
        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $is_mobile = preg_match('/^[6789]\d{9}$/', $identifier);
        
        if (!$is_email && !$is_mobile) {
            $identifier_err = "Please enter a valid email or 10-digit phone number starting with 6, 7, 8, or 9.";
        } else {
            // Add +91 prefix for phone numbers for database comparison
            $db_identifier = $identifier;
            if ($is_mobile) {
                $db_identifier = '+91' . $identifier;
            }
            
            // Check if user exists and is not verified
            $sql = "SELECT id, fullname, email, mobile, is_verified FROM users WHERE email = ? OR mobile = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ss", $db_identifier, $db_identifier);
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 1) {
                        $user = $result->fetch_assoc();
                        $user_id = $user['id'];
                        $fullname = $user['fullname'];
                        $email = $user['email'];
                        $mobile = $user['mobile'];
                        $is_verified = $user['is_verified'];
                        
                        if ($is_verified == 0) {
                            // User exists but not verified, proceed with OTP
                            $conn->begin_transaction();
                            
                            try {
                                // Clear any existing OTP for this user
                                $delete_sql = "DELETE FROM otp_reset WHERE user_id = ?";
                                $delete_stmt = $conn->prepare($delete_sql);
                                $delete_stmt->bind_param("i", $user_id);
                                $delete_stmt->execute();
                                $delete_stmt->close();
                                
                                // Generate new OTP
                                $otp = rand(100000, 999999);
                                $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                                $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
                                
                                // Store new OTP
                                $otp_sql = "INSERT INTO otp_reset (user_id, otp_hash, expires_at) VALUES (?, ?, ?)";
                                $otp_stmt = $conn->prepare($otp_sql);
                                $otp_stmt->bind_param("iss", $user_id, $otp_hash, $expires_at);
                                
                                if ($otp_stmt->execute()) {
                                    $otp_stmt->close();
                                    
                                    // Send OTP via email or SMS based on what was provided
                                    $otp_sent = false;
                                    if ($is_email && !empty($email)) {
                                        // Send via email if email was provided and user has email
                                        if (send_otp_email($email, $otp)) {
                                            $otp_sent = true;
                                            log_action("OTP resent via email for verification", $user_id);
                                        }
                                    }
                                    
                                    if (!$otp_sent && !empty($mobile)) {
                                        // Send via SMS if email failed or not available
                                        if (send_otp_sms($mobile, $otp)) {
                                            $otp_sent = true;
                                            log_action("OTP resent via SMS for verification", $user_id);
                                        }
                                    }
                                    
                                    if ($otp_sent) {
                                        $conn->commit();
                                        $_SESSION['verification_user_id'] = $user_id;
                                        header("location: verify-otp.php");
                                        exit();
                                    } else {
                                        throw new Exception("Could not send OTP via any method.");
                                    }
                                } else {
                                    throw new Exception("Could not store OTP.");
                                }
                            } catch (Exception $e) {
                                $conn->rollback();
                                $general_err = $e->getMessage();
                                log_action("Verification OTP resend failed: " . $e->getMessage() . " for mobile " . $mobile);
                            }
                        } else {
                            $general_err = "This account is already verified. You can login directly.";
                            log_action("Verification attempt failed: Account already verified for identifier " . $identifier);
                        }
                    } else {
                        $general_err = "No account found with this email or phone number.";
                        log_action("Verification attempt failed: No account found for identifier " . $identifier);
                    }
                } else {
                    $general_err = "Something went wrong. Please try again.";
                    log_action("Database error during verification for identifier " . $identifier . " - " . $conn->error);
                }
                $stmt->close();
            } else {
                $general_err = "Something went wrong. Please try again.";
                log_action("Database error: Could not prepare verification statement for identifier " . $identifier . " - " . $conn->error);
            }
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification</title>
    <link rel="stylesheet" href="style.css">
         <script>
         function handleIdentifierInput(input) {
             let value = input.value;
             
             // Check if it looks like a phone number (starts with +)
             if (value.startsWith('+')) {
                 // Remove any non-digit characters except +
                 value = value.replace(/[^\d+]/g, '');
                 
                 // Limit to 13 characters (+91 + 10 digits)
                 if (value.length > 13) {
                     value = value.substring(0, 13);
                 }
             } else {
                 // For email, just limit length
                 if (value.length > 50) {
                     value = value.substring(0, 50);
                 }
             }
             
             input.value = value;
         }
         
         // Initialize input handling when page loads
         document.addEventListener('DOMContentLoaded', function() {
             const identifierInput = document.querySelector('input[name="identifier"]');
             
             if (identifierInput) {
                 identifierInput.addEventListener('input', function() {
                     handleIdentifierInput(this);
                 });
             }
         });
     </script>
</head>
<body>
    <div class="container">
        <div class="left">
            <img src="images/logo.png" alt="App Preview">
        </div>

        <div class="right">
            <div class="form-container">
                <h1 class="logo">Resend Verification</h1>
                
                                 <p style="color: #ccc; margin-bottom: 20px; text-align: center;">
                     Enter your email or phone number to receive a new verification OTP.
                 </p>

                <?php if (!empty($success_msg)): ?>
                    <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?php echo $success_msg; ?></div>
                <?php endif; ?>

                <?php if (!empty($general_err)): ?>
                    <div style="color: red; font-weight: bold; margin-bottom: 10px;"><?php echo $general_err; ?></div>
                <?php endif; ?>

                                 <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                     <input type="text" name="identifier" placeholder="Email or Phone Number (e.g. +911234567890)" 
                            value="<?php echo htmlspecialchars($identifier); ?>" maxlength="50" required>
                     <div style="color: red; margin-bottom: 10px; font-size: 0.85em;"><?php echo $identifier_err; ?></div>
                    
                    <!-- CAPTCHA section -->
                    <div class="captcha">
                        <img src="captcha.php" alt="CAPTCHA Image" onclick="this.src='captcha.php?'+Math.random();" 
                             style="cursor:pointer; border-radius: 5px;">
                        <input type="text" name="captcha_input" placeholder="Enter CAPTCHA" required>
                        <div style="color: red; margin-bottom: 10px; font-size: 0.85em;"><?php echo $captcha_err; ?></div>
                    </div>

                    <button type="submit">Send OTP</button>
                </form>
                
                <div style="text-align: center; margin-top: 15px;">
                    <p style="color: #ccc; font-size: 0.9em; margin: 0;">
                        Didn't receive OTP? Check your spam folder or try again.
                    </p>
                </div>

                <div class="divider">
                    <div class="line"></div>
                    <div class="line"></div>
                </div>
                
                <a href="login.php" class="forgot">Back to Login</a>
            </div>

            <div class="signup">
                Don't have an account? <a href="register.php">Register!</a>
            </div>

            <footer>
                <ul>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Help</a></li>
                    <li><a href="#">API</a></li>
                    <li><a href="#">Jobs</a></li>
                </ul>
                <p>&copy; <?php echo date("Y"); ?> Election</p>
            </footer>
        </div>
    </div>
</body>
</html>
