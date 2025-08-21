<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['password_reset_authorized']) || !isset($_SESSION['reset_user_id'])) {
    log_action("Unauthorized access attempt to reset-password-form.php");
    header("Location: login.php");
    exit;
}

$password_err = $confirm_password_err = $general_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $user_id = $_SESSION['reset_user_id'];

    // Validate new password
    if (empty($new_password)) {
        $password_err = "Please enter a new password.";
        log_action("Password reset failed: Empty new password for user ID: " . $user_id);
    } elseif (strlen($new_password) < 6) {
        $password_err = "Password must have at least 6 characters.";
        log_action("Password reset failed: New password too short for user ID: " . $user_id);
    }

    // Validate confirm password
    if (empty($confirm_password)) {
        $confirm_password_err = "Please confirm the new password.";
        log_action("Password reset failed: Empty confirm password for user ID: " . $user_id);
    } elseif (empty($password_err) && $new_password != $confirm_password) {
        $confirm_password_err = "Passwords do not match.";
        log_action("Password reset failed: Passwords do not match for user ID: " . $user_id);
    }

    // If no errors, check against last 3 passwords
    if (empty($password_err) && empty($confirm_password_err)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 1. Get current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($current_password_hash);
        $stmt->fetch();
        $stmt->close();

        // Check if new password matches current
        if (password_verify($new_password, $current_password_hash)) {
            $password_err = "You cannot reuse your current password.";
        }

        // 2. Get last 3 passwords from password_history
        $stmt = $conn->prepare("
            SELECT password_hash 
            FROM password_history 
            WHERE user_id = ? 
            ORDER BY changed_at DESC 
            LIMIT 3
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (password_verify($new_password, $row['password_hash'])) {
                $password_err = "You cannot reuse any of your last 3 passwords.";
                break;
            }
        }
        $stmt->close();

        // If still no errors, proceed to update
        if (empty($password_err)) {
            $conn->begin_transaction();
            try {
                // Move current password to history
                $stmt = $conn->prepare("
                    INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)
                ");
                $stmt->bind_param("is", $user_id, $current_password_hash);
                $stmt->execute();
                $stmt->close();

                // Keep only last 3 history entries
                $conn->query("
                    DELETE FROM password_history 
                    WHERE user_id = $user_id 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM password_history 
                            WHERE user_id = $user_id 
                            ORDER BY changed_at DESC 
                            LIMIT 3
                        ) as temp
                    )
                ");

                // Update user's password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                unset($_SESSION['reset_user_id']);
                unset($_SESSION['password_reset_authorized']);

                $_SESSION['login_message'] = "Your password has been reset successfully. Please log in.";
                log_action("Password successfully reset", $user_id);
                header("Location: login.php");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $general_err = "Failed to update password. Please try again.";
                log_action("Database error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="flex-direction: column; max-width: 500px;">
    <div class="form-container" style="width: 100%;">
        <h1 class="logo">Set New Password</h1>
        
        <?php if ($general_err): ?>
            <div style="color: #f87171; font-weight: bold; margin-bottom: 15px;"><?php echo $general_err; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="password" name="new_password" placeholder="Enter New Password" required autofocus>
            <?php if ($password_err): ?>
                <div style="color: #f87171; font-size: 0.85em; margin-top: 5px;"><?php echo $password_err; ?></div>
            <?php endif; ?>
            
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required style="margin-top: 15px;">
            <?php if ($confirm_password_err): ?>
                <div style="color: #f87171; font-size: 0.85em; margin-top: 5px;"><?php echo $confirm_password_err; ?></div>
            <?php endif; ?>

            <button type="submit" style="margin-top: 20px;">Reset Password</button>
        </form>
    </div>
</div>
</body>
</html>