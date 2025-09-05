<?php
session_start();

// Include the unified auth system
require_once 'user_auth.php';

// Destroy the user session
destroyUserSession();

// Redirect to the main page with a logout message
header("Location: index.php?logout=1");
exit();
?>
