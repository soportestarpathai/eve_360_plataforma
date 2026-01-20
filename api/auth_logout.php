<?php
// api/auth_logout.php
session_start();

// 1. Unset all session variables
$_SESSION = array();

// 2. Delete the session cookie (if it exists)
// This ensures the browser forgets the session ID
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Return JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>