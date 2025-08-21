<?php
session_start();

require_once 'db.php';
require_once 'send_email.php';
require_once 'send_sms.php';
require_once 'logger.php'; 

$email = $mobile = $password = $otp_method = $fullname = "";
$email_err = $mobile_err = $password_err = $otp_method_err = $captcha_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['captcha_input']) || $_POST['captcha_input'] != $_SESSION['captcha']) {
        $captcha_err = "CAPTCHA does not match.";
        log_action("Registration attempt failed: Incorrect CAPTCHA for email: " . ($_POST["email"] ?? 'N/A'));
    } else {
        $fullname = trim($_POST["fullname"]);
        $email = trim($_POST["email"]);
        $mobile = trim($_POST["mobile"]);
        $password = trim($_POST["password"]);
        $otp_method = $_POST["otp_option"] ?? '';

        // FORM Validations
        if (preg_match('/[0-9]/', $fullname)) {//no numb 
            $general_err = "Full Name should not contain numbers.";
        }
        
        // Email validation - only if email is provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email.";
        }
        
        if (!preg_match('/^[6789]\d{9}$/', $mobile)) {//start with 6,7,8,9 and 10 digits
            $mobile_err = "Please enter a valid 10-digit phone number starting with 6, 7, 8, or 9 (e.g., 9876543210).";
        } else {
            // Add +91 prefix for database storage
            $mobile = '+91' . $mobile;
        }

        // Password: At least 1 uppercase, 1 lowercase, 1 special character, 1 number and Length: 7 <= length <= 20
        if (empty($password)) {
            $password_err = "Please enter a password.";
        } elseif (strlen($password) < 7 || strlen($password) > 20) {
            $password_err = "Password length must be between 7 and 20 characters.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $password_err = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $password_err = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $password_err = "Password must contain at least one number.";
        } elseif (!preg_match('/[^a-zA-Z0-9\s]/', $password)) { // Matches any character that is NOT a letter, number, or whitespace
            $password_err = "Password must contain at least one special character.";
        }

        if (empty($otp_method)) {
            $otp_method_err = "Please choose an OTP method.";
        }

        if (empty($general_err) && empty($email_err) && empty($mobile_err) && empty($password_err) && empty($otp_method_err)) {//if no form error

            // Check for existing users - only check email if provided
            $sql_check = "SELECT id FROM users WHERE mobile = ?";
            $params = [$mobile];
            $types = "s";
            
            if (!empty($email)) {
                $sql_check .= " OR email = ?";
                $params[] = $email;
                $types .= "s";
            }
            
            if ($stmt_check = $conn->prepare($sql_check)) {
                $stmt_check->bind_param($types, ...$params);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $general_err = "A user with this mobile number" . (!empty($email) ? " or email" : "") . " already exists.";
                    log_action("Registration attempt failed: User already exists for mobile: " . $mobile);
                }
                $stmt_check->close();
            }

            if (empty($general_err)) {//if user dont exist already
                // Start transaction to ensure data consistency
                $conn->begin_transaction();
                
                try {
                    $role = 'hod';
                    $sql_insert_user = "INSERT INTO users (fullname, email, mobile, password, role) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt_user = $conn->prepare($sql_insert_user)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_user->bind_param("sssss", $fullname, $email, $mobile, $hashed_password, $role);

                        if ($stmt_user->execute()) {
                            $user_id = $conn->insert_id; // Get the ID of the new user
                            
                            // Insert role into user_roles table
                            $sql_insert_role = "INSERT INTO user_roles (user_id, role) VALUES (?, ?)";
                            $stmt_role = $conn->prepare($sql_insert_role);
                            $stmt_role->bind_param("is", $user_id, $role);
                            $stmt_role->execute();
                            $stmt_role->close();

                            // Also assign assistant role to HODs for hierarchy login
                            if ($role === 'hod') {
                                $assistant_role = 'assistant';
                                $stmt_role2 = $conn->prepare($sql_insert_role);
                                $stmt_role2->bind_param("is", $user_id, $assistant_role);
                                $stmt_role2->execute();
                                $stmt_role2->close();
                            }

                            //Now, generate and store OTP in the 'otp_reset' table 
                            $otp = rand(100000, 999999);
                            $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

                            $sql_otp = "INSERT INTO otp_reset (user_id, otp_hash, expires_at) VALUES (?, ?, ?)";
                            if ($stmt_otp = $conn->prepare($sql_otp)) {
                                $stmt_otp->bind_param("iss", $user_id, $otp_hash, $expires_at);
                                $stmt_otp->execute();
                                $stmt_otp->close();

                                // Now send the email/SMS
                                $otp_sent = false;
                                if ($otp_method == 'email') {
                                    if (!empty($email) && send_otp_email($email, $otp)) {
                                        $otp_sent = true;
                                        log_action("OTP sent via email for new registration", $user_id);
                                    } else {
                                        throw new Exception("Could not send verification email.");
                                    }
                                } elseif ($otp_method == 'phone') {
                                    if (send_otp_sms($mobile, $otp)) {
                                        $otp_sent = true;
                                        log_action("OTP sent via SMS for new registration", $user_id);
                                    } else {
                                        throw new Exception("Could not send SMS.");
                                    }
                                }

                                if ($otp_sent) {
                                    // Commit transaction only if OTP was sent successfully
                                    $conn->commit();
                                    $_SESSION['verification_user_id'] = $user_id;
                                    $_SESSION['phn'] = $mobile;
                                    log_action("New user registered successfully, awaiting OTP verification", $user_id);
                                    header("location: verify-otp.php");
                                    exit();
                                } else {
                                    throw new Exception("Failed to send OTP by any method.");
                                }
                            } else {
                                throw new Exception("Something went wrong preparing OTP statement.");
                            }
                        } else {
                            throw new Exception("Something went wrong creating the user.");
                        }
                        $stmt_user->close();
                    } else {
                        throw new Exception("Something went wrong preparing user insertion statement.");
                    }
                } catch (Exception $e) {
                    // Rollback transaction if anything fails
                    $conn->rollback();
                    $general_err = $e->getMessage();
                    log_action("Registration failed: " . $e->getMessage() . " for mobile " . $mobile);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Uttarakhand Election - Register</title>
    <style>
        body {
            background-color: #000;
            color: white;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 420px;
            background-color: #111;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.1);
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 20px;
            font-size: 2em;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 5px;
            border: 1px solid #333;
            background-color: #1c1c1c;
            color: white;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .radio-group {
            margin-top: 15px;
            margin-bottom: 15px;
            display: flex;
            gap: 20px;
            padding-left: 5px;
        }

        label {
            font-size: 14px;
            color: #ccc;
        }

        .error {
            color: #f87171;
            font-size: 0.85em;
            margin-top: 2px;
            margin-bottom: 10px;
            height: 1em;
        }

        button {
            background-color: #1e3a8a;
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }

        button:hover {
            background-color: #1d4ed8;
        }

        .bottom-text {
            text-align: center;
            margin-top: 20px;
            color: #ccc;
        }

        .bottom-text a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: bold;
        }

        .bottom-text a:hover {
            text-decoration: underline;
        }

        .captcha-img {
            border-radius: 5px;
            margin-bottom: 10px;
        }
        

    </style>
    
    <script>
        function limitPhoneNumber(input) {
            // Remove any non-digit characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Check if first digit is valid (6, 7, 8, or 9)
            if (value.length > 0 && !/^[6789]/.test(value)) {
                value = value.substring(1);
            }
            
            input.value = value;
        }
        
        function limitName(input) {
            // Limit to 20 characters
            if (input.value.length > 20) {
                input.value = input.value.substring(0, 20);
            }
        }
        
        // Initialize input limits when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const fullnameInput = document.querySelector('input[name="fullname"]');
            const mobileInput = document.querySelector('input[name="mobile"]');
            
            if (fullnameInput) {
                fullnameInput.addEventListener('input', function() {
                    limitName(this);
                });
            }
            
            if (mobileInput) {
                mobileInput.addEventListener('input', function() {
                    limitPhoneNumber(this);
                });
            }
        });
    </script>
</head>

<body>
    <div class="container">
        <h2>Registration Form</h2>
        
        <?php if (!empty($general_err)): ?>
            <div class="error" style="text-align: center; font-weight: bold; margin-bottom: 15px;"><?php echo $general_err; ?></div>
        <?php endif; ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" name="fullname" placeholder="Full Name" value="<?php echo htmlspecialchars($fullname); ?>" maxlength="20" required>
            <div class="error"></div>
            
            <input type="email" name="email" placeholder="Email (Optional)" value="<?php echo htmlspecialchars($email); ?>">
            <div class="error"><?php echo $email_err; ?></div>
            
            <input type="tel" name="mobile" placeholder="Phone Number (e.g. 9876543210)" value="<?php 
                $display_mobile = $mobile;
                // Remove +91 prefix if present for display
                if (strpos($display_mobile, '+91') === 0) {
                    $display_mobile = substr($display_mobile, 3);
                }
                echo htmlspecialchars($display_mobile); 
            ?>" maxlength="10" pattern="[6789][0-9]{9}" required>
            <div class="error"><?php echo $mobile_err; ?></div>
            
            <input type="password" name="password" placeholder="Password" required>
            <div class="error"><?php echo $password_err; ?></div>
            
            <label>Send OTP on:</label>
            <div class="radio-group">
                <label><input type="radio" name="otp_option" value="email"> Email</label>
                <label><input type="radio" name="otp_option" value="phone"> Phone</label>
            </div>
            
            <div class="error"><?php echo $otp_method_err; ?></div>
            
            <img src="captcha.php" alt="CAPTCHA Image" class="captcha-img" onclick="this.src='captcha.php?'+Math.random();" style="cursor:pointer;">
            <input type="text" name="captcha_input" placeholder="Enter CAPTCHA" required>
            <div class="error"><?php echo $captcha_err; ?></div>
            
            <button type="submit">Register for Election</button>
            <div class="bottom-text">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </form>
    </div>
</body>

</html>