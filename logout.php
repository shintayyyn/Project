<?php
session_start();

// Clear session variables
$_SESSION = array();

// If there is a session cookie, clear it too
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
?>
