<?php
session_start();
require_once '../db.php';
require_once '../logger.php';
require_once '../role_manager.php';

header('Content-Type: application/json');

// Authorization: only HOD or Admin can deactivate assistants
if (!isset($_SESSION['id']) || !is_hod_or_admin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;
if ($assistant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assistant identifier.']);
    exit;
}

try {
    // Verify the target user exists and has assistant role
    $verify_sql = "SELECT u.id FROM users u 
                   LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.is_active = 1
                   WHERE u.id = ? AND (u.role = 'assistant' OR ur.role = 'assistant')";
    $verify_stmt = $conn->prepare($verify_sql);
    if (!$verify_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }
    $verify_stmt->bind_param('i', $assistant_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    $verify_stmt->close();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Assistant not found.']);
        exit;
    }

    // Deactivate assistant in user_details (create the row if missing)
    $conn->begin_transaction();
    try {
        $update_sql = "UPDATE user_details SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new Exception('Database error.');
        }
        $update_stmt->bind_param('i', $assistant_id);
        $update_stmt->execute();
        $affected = $update_stmt->affected_rows;
        $update_stmt->close();

        if ($affected === 0) {
            // Insert a minimal row with inactive status
            $insert_sql = "INSERT INTO user_details (user_id, status) VALUES (?, 'inactive')";
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                throw new Exception('Database error.');
            }
            $insert_stmt->bind_param('i', $assistant_id);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        $conn->commit();
        log_action("Assistant deactivated by HOD", $_SESSION['id'] ?? null, 'ASSISTANT', 'SUCCESS', ['assistant_id' => $assistant_id]);
        echo json_encode(['success' => true, 'message' => 'Assistant deactivated successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate assistant.']);
    }
} catch (Exception $e) {
    log_action('Error deactivating assistant: ' . $e->getMessage(), $_SESSION['id'] ?? null, 'ASSISTANT', 'FAILURE', ['assistant_id' => $assistant_id]);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

$conn->close();
?>


