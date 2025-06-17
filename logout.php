<?php
// Start the session
session_start();

// Clear all session variables
$_SESSION = [];

// Regenerate the session ID to prevent session fixation attacks
session_regenerate_id(true);

// Destroy the session
session_destroy();

// Redirect to the landing page
header("Location: ../admin/index.php"); // Ensure this path is correct
exit();
?>