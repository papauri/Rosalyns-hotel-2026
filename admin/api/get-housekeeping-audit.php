<?php
/**
 * API Endpoint: Get Housekeeping Audit Log
 * Returns audit history for a specific housekeeping assignment
 */

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Include required files
require_once '../admin-init.php';

// Check permissions
if (!hasPermission($user['id'], 'housekeeping')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get assignment ID
$assignmentId = (int)($_GET['id'] ?? 0);

if ($assignmentId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid assignment ID']);
    exit;
}

try {
    // Get audit log for the assignment
    $logs = getHousekeepingAuditLog($assignmentId);
    
    header('Content-Type: application/json');
    echo json_encode(['logs' => $logs, 'error' => null]);
} catch (Throwable $e) {
    error_log('get-housekeeping-audit.php error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to load audit history', 'logs' => []]);
}
