<?php
// Initialize the session if it hasn't been started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, clear it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a logout message (optional)
session_start();
$_SESSION['logout_message'] = "You have been successfully logged out.";

// Redirect to home page
header("Location: home.php");
exit();
?>