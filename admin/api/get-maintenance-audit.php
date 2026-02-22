<?php
/**
 * API Endpoint: Get Maintenance Audit Log
 * Returns audit history for a specific maintenance schedule
 */

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Include required files
require_once '../admin-init.php';

// Check permissions
if (!hasPermission($user['id'], 'room_maintenance')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get maintenance ID
$maintenanceId = (int)($_GET['id'] ?? 0);

if ($maintenanceId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid maintenance ID']);
    exit;
}

try {
    // Get audit log for the maintenance schedule
    $logs = getMaintenanceAuditLog($maintenanceId);
    
    header('Content-Type: application/json');
    echo json_encode(['logs' => $logs, 'error' => null]);
} catch (Throwable $e) {
    error_log('get-maintenance-audit.php error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to load audit history', 'logs' => []]);
}
