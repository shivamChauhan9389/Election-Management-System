<?php
session_start();

// Log the logout action
if (isset($_SESSION['id'])) {
    require_once '../logger.php';
    log_action("User logged out", $_SESSION['id']);
}

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: ../login.php");
exit;
?>
