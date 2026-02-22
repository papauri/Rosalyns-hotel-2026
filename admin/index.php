<?php
/**
 * Admin Directory Index
 * Redirects to login page if not authenticated, or dashboard if authenticated
 */

// Include base URL override (if configured) before auto-detection
$override_file = __DIR__ . '/../config/base-url-override.php';
if (file_exists($override_file)) {
    require_once $override_file;
}

// Include base URL configuration for proper redirects
require_once __DIR__ . '/../config/base-url.php';

session_start();

// If user is logged in, redirect to dashboard
if (isset($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Otherwise, redirect to login page
header('Location: login.php');
exit;
?>
