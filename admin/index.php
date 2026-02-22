<?php
/**
 * Admin Directory Index
 * Redirects to login page if not authenticated, or dashboard if authenticated
 */

// Include base URL configuration for proper redirects
require_once __DIR__ . '/../config/base-url.php';

session_start();

// If user is logged in, redirect to dashboard
if (isset($_SESSION['admin_user_id'])) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

// Otherwise, redirect to login page
header('Location: ' . BASE_URL . 'admin/login.php');
exit;
?>
