<?php
session_start();
require_once 'logger.php';

// If the user is already logged in, redirect them to the home page.
if (isset($_SESSION["username"])) {
    header("location: home/home.php");
    exit;
}

require_once "db.php";

$pass=$login_err = $captcha_err = "";

// Check for success messages from other pages (like registration or password reset)
$success_msg = "";
if(isset($_SESSION['login_message'])){
    $success_msg = $_SESSION['login_message'];
    unset($_SESSION['login_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = filter_input(INPUT_POST, "username", FILTER_SANITIZE_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, "password", FILTER_SANITIZE_SPECIAL_CHARS);
    $user_captcha = filter_input(INPUT_POST, "captcha", FILTER_SANITIZE_SPECIAL_CHARS);
    
    // Check if username is a phone number (10 digits starting with 6,7,8,9)
    $is_phone = preg_match('/^[6789]\d{9}$/', $username);
    if ($is_phone) {
        // Add +91 prefix for database comparison
        $username_with_prefix = '+91' . $username;
    } else {
        $username_with_prefix = $username;
    }
    
    // VERIFY CAPTCHA FIRST
    if ($user_captcha != $_SESSION['captcha']) {
        $captcha_err = "CAPTCHA does not match.";
        log_action("Failed login attempt: Incorrect CAPTCHA for identifier: " . ($_POST["username"] ?? 'N/A'));
    } else {
        // CAPTCHA is correct, proceed with login
        $sql = "SELECT id, fullname, email, mobile, password, role, is_verified FROM users WHERE email = ? OR mobile = ?";

        if ($stmt = $conn->prepare($sql)) {//prepare statement
            $stmt->bind_param("ss", $username_with_prefix, $username_with_prefix);//what to put at '?'
            
            if ($stmt->execute()) {
                $result = $stmt->get_result(); // Get the mysqli_result object
                
                if ($result->num_rows == 1) {//user exist?
                    $user = $result->fetch_assoc(); // Fetch the row of that user as an associative array
                    
                    $id = $user['id'];
                    $fullname_db = $user['fullname'];
                    $email_db = $user['email'];
                    $mobile_db = $user['mobile'];
                    $hashed_password = $user['password'];
                    $role_db = $user['role'];
                    $is_verified = $user['is_verified'];
                    
                    if ($is_verified == 1) {
                        if (password_verify($password, $hashed_password)) {//pass is correct
                                // Determine default active role (highest available)
                                require_once 'role_helper.php';
                                $active_role = get_highest_role($id);

                                // If assistant (or highest role resolves to assistant), ensure status is active
                                if ($active_role === 'assistant') {
                                    $status_sql = "SELECT status FROM user_details WHERE user_id = ? LIMIT 1";
                                    if ($status_stmt = $conn->prepare($status_sql)) {
                                        $status_stmt->bind_param("i", $id);
                                        if ($status_stmt->execute()) {
                                            $status_res = $status_stmt->get_result();
                                            $status_row = $status_res->fetch_assoc();
                                            if ($status_row && isset($status_row['status']) && $status_row['status'] === 'inactive') {
                                                $login_err = "Your assistant access is inactive. Please contact your HOD.";
                                                log_action("Blocked login for inactive assistant", $id);
                                            }
                                        }
                                        $status_stmt->close();
                                    }
                                }

                                if (empty($login_err)) {
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["id"] = $id;
                                    $_SESSION["username"] = !empty($email_db) ? $email_db : $mobile_db; ////to say hi
                                    $_SESSION["name"] = $fullname_db; 
                                    $_SESSION["phn"] = $mobile_db;
                                    $_SESSION["role"] = $active_role;
                                    log_action("Successful login with role: " . $active_role, $id);
                                    header("location: home/home.php");
                                    exit();
                                }
                            } else {//wrong pass
                                $pass = "Invalid password.";
                                log_action("Failed login attempt: Invalid password for user ID: " . $id);
                            }
                        } else {
                            $login_err = "Account not verified. <a href='verify.php' style='color: #3b82f6; text-decoration: none;'>Click here to resend verification OTP</a>";
                            log_action("Failed login attempt: Unverified account for user ID: " . $id);
                        }
                    } else {//no such user
                        $login_err = "Invalid email or phone number.";
                        log_action("Failed login attempt: User not found for identifier: " . $username);
                    }
                } else {//problem in executing stmt
                    $login_err = "Oops! Something went wrong. Please try again.";
                    log_action("Database error during login for identifier: " . $username . " - " . $conn->error);
                }
                $stmt->close();
            }
        }
    }
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uttarakhand Election - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="left">
        <img src="images/logo.png" alt="App Preview">
    </div>

    <div class="right">
        <div class="form-container">
            <h1 class="logo">Uttarakhand Election Portal</h1>

             <?php if (!empty($success_msg)): ?>
                <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                
                <input type="text" name="username" placeholder="Email or Phone Number (10 digits)" maxlength="50" required>
                <div style="color: red; margin-bottom: 10px; font-size: 0.85em; font-family:Arial, Helvetica, sans-serif"><?php echo $login_err; ?></div>

                <input type="password" name="password" placeholder="Password" required>
                <div style="color: red; margin-bottom: 10px; font-size: 0.85em; font-family:Arial, Helvetica, sans-serif"><?php echo $pass; ?></div>
                
                <!-- CAPTCHA section -->
                <div class="captcha">
                    <img src="captcha.php" alt="CAPTCHA Image" onclick="this.src='captcha.php?'+Math.random();" style="cursor:pointer; border-radius: 5px;">
                    <input type="text" name="captcha" placeholder="Enter CAPTCHA" required>
                    <div style="color: red; margin-bottom: 10px; font-size: 0.85em; font-family:Arial, Helvetica, sans-serif"><?php echo $captcha_err; ?></div>
                </div>

                <button type="submit">Log in</button>
            </form>

            <div class="divider">
                <div class="line"></div>
                <div class="line"></div>
            </div>
            <a href="forgot-password.php" class="forgot">Forgot password?</a>
            <a href="verify.php" class="forgot" style="margin-top: 10px; display: block;">Resend verification OTP</a>
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
            <p>&copy; <?php echo date("Y"); ?> Uttarakhand Election Commission</p>
        </footer>
    </div>
</div>

</body>
</html>
