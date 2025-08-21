<?php
session_start();
require_once 'db.php';
require_once 'send_email.php';
require_once 'send_sms.php';
require_once 'logger.php';

header('Content-Type: application/json');

// Check if user is in verification process
if (!isset($_SESSION['verification_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No verification session found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
    $user_id = $_SESSION['verification_user_id'];
    
    // Get user details
    $sql = "SELECT mobile, email FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $mobile = $user['mobile'];
                $email = $user['email'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Clear existing OTP
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
                        
                        // Send OTP via SMS (preferred method)
                        $otp_sent = false;
                        if (send_otp_sms($mobile, $otp)) {
                            $otp_sent = true;
                            log_action("OTP resent via AJAX for verification", $user_id);
                        } elseif (!empty($email) && send_otp_email($email, $otp)) {
                            $otp_sent = true;
                            log_action("OTP resent via email via AJAX for verification", $user_id);
                        }
                        
                        if ($otp_sent) {
                            $conn->commit();
                            echo json_encode(['success' => true, 'message' => 'OTP resent successfully']);
                        } else {
                            throw new Exception("Failed to send OTP");
                        }
                    } else {
                        throw new Exception("Failed to store OTP");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    log_action("AJAX OTP resend failed: " . $e->getMessage() . " for user ID: " . $user_id);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
