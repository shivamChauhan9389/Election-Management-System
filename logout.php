<?php
// Initialize the session
session_start();

// Log the logout action if user was logged in
if (isset($_SESSION['id'])) {
    require_once 'logger.php';
    log_action("User logged out", $_SESSION['id']);
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page
header("location: login.php");
exit;
?>

