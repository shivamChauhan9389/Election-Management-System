<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';

// Validate token
if (empty($token)) {
    $error_message = "Invalid registration link. Please contact your HOD for a valid invitation.";
} else {
    // Check if token exists and is valid
    $sql = "SELECT * FROM pending_assistant_registrations WHERE registration_token = ? AND status = 'pending' AND token_expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Invalid or expired registration link. Please contact your HOD for a new invitation.";
    } else {
        $assistant_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token) && empty($error_message)) {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 7 || strlen($password) > 20) {
        $errors[] = 'Password length must be between 7 and 20 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9\s]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_user_sql = "INSERT INTO users (fullname, email, mobile, password, role, is_verified) VALUES (?, ?, ?, ?, 'assistant', 1)";
            $insert_user_stmt = $conn->prepare($insert_user_sql);
            $insert_user_stmt->bind_param("ssss", $assistant_data['fullname'], $assistant_data['email'], $assistant_data['mobile'], $hashed_password);
            
            if ($insert_user_stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Add role to user_roles table for assistants
                $insert_role_sql = "INSERT INTO user_roles (user_id, role) VALUES (?, 'assistant')";
                $insert_role_stmt = $conn->prepare($insert_role_sql);
                $insert_role_stmt->bind_param("i", $user_id);
                $insert_role_stmt->execute();
                $insert_role_stmt->close();
                
                // Update pending registration status
                $update_sql = "UPDATE pending_assistant_registrations SET status = 'completed' WHERE registration_token = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("s", $token);
                $update_stmt->execute();
                
                // Add to user_details table with supervisor_id set to HOD who invited
                $supervisor_id = (int)($assistant_data['added_by_hod_id'] ?? 0);
                $insert_details_sql = "INSERT INTO user_details (user_id, supervisor_id, status) VALUES (?, ?, 'active')";
                $insert_details_stmt = $conn->prepare($insert_details_sql);
                $insert_details_stmt->bind_param("ii", $user_id, $supervisor_id);
                $insert_details_stmt->execute();
                
                // Log the action
                log_action("Assistant registration completed: " . $assistant_data['fullname'], $user_id);
                
                $conn->commit();
                $success_message = "Registration completed successfully! You can now login with your email and password.";
                
                // Clear the token from URL
                $token = '';
            } else {
                throw new Exception("Failed to create user account");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "An error occurred during registration. Please try again.";
            log_action("Assistant registration failed: " . $e->getMessage(), null);
        }
    } else {
        $error_message = implode(' ', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant Registration Setup - Uttarakhand Election Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #385185;
            --primary-hover: #22396dff;
            --secondary-color: #fff;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-dark: #000;
            --text-light: #666;
            --border-color: #385185;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 2rem;
            background: var(--bg-white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }

        h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .assistant-info {
            background: rgba(56, 81, 133, 0.1);
            border: 1px solid rgba(56, 81, 133, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .assistant-info h3 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .info-item i {
            color: var(--primary-color);
            width: 16px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(56, 81, 133, 0.1);
        }

        .form-submit {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-submit:hover {
            background: var(--primary-hover);
        }

        .form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .password-requirements {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .password-requirements ul {
            margin: 0.5rem 0 0 1rem;
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-vote-yea"></i>
            </div>
            <h1>Assistant Registration Setup</h1>
            <p class="subtitle">Complete your registration to access the election portal</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($error_message) && empty($success_message) && !empty($assistant_data)): ?>
            <!-- Assistant Information -->
            <div class="assistant-info">
                <h3><i class="fas fa-user"></i> Assistant Details</h3>
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <span><strong>Name:</strong> <?php echo htmlspecialchars($assistant_data['fullname']); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <span><strong>Email:</strong> <?php echo htmlspecialchars($assistant_data['email']); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <span><strong>Phone:</strong> <?php echo htmlspecialchars($assistant_data['mobile']); ?></span>
                </div>
            </div>

            <!-- Password Setup Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Set Your Password *
                    </label>
                    <input type="password" id="password" name="password" class="form-input" required>
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>Length: 7-20 characters</li>
                            <li>At least one uppercase letter (A-Z)</li>
                            <li>At least one lowercase letter (a-z)</li>
                            <li>At least one number (0-9)</li>
                            <li>At least one special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>

                <button type="submit" class="form-submit">
                    <i class="fas fa-check"></i> Complete Registration
                </button>
            </form>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="login-link">
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>
