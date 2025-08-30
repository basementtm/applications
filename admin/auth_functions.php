<?php
// Shared authentication functions

function checkUserStatus($conn) {
    // Check if user is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false; // Not logged in
    }
    
    // Check if user account is still active
    if (isset($_SESSION['admin_id'])) {
        $user_id = $_SESSION['admin_id'];
        $check_sql = "SELECT active FROM admin_users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 1) {
            $user_data = $check_result->fetch_assoc();
            if ($user_data['active'] != 1) {
                // User account has been disabled - log them out
                session_destroy();
                header("Location: login.php?error=account_disabled");
                exit();
            }
        } else {
            // User no longer exists - log them out
            session_destroy();
            header("Location: login.php?error=account_not_found");
            exit();
        }
        $check_stmt->close();
    }
    
    return true; // User is active and logged in
}

function requireLogin($conn) {
    if (!checkUserStatus($conn)) {
        header("Location: login.php");
        exit();
    }
}
?>
