<?php
/**
 * Audit Functions for Housekeeping and Maintenance
 * 
 * This file provides functions for logging and retrieving audit trails
 * for housekeeping assignments and maintenance schedules.
 * 
 * Audit tables track:
 * - Who made the change (performed_by, performed_by_name)
 * - What action was performed (created, updated, deleted, verified, etc.)
 * - When it was performed (performed_at timestamp)
 * - What changed (old_values, new_values, changed_fields)
 * 
 * Requires: admin-init.php must be included first (provides $pdo global)
 */

/**
 * Check if a table exists in the database
 * 
 * @param PDO $pdo Database connection
 * @param string $table Table name to check
 * @return bool True if table exists, false otherwise
 */
function auditTableExists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$table];
}

/**
 * Log an action for a housekeeping assignment
 *
 * @param int $assignmentId The ID of the housekeeping assignment
 * @param string $action The action performed (created, updated, deleted, verified, etc.)
 * @param array|null $oldValues The values before the change (null for create)
 * @param array|null $newValues The values after the change (null for delete)
 * @param int|null $performedBy The user ID who performed the action
 * @param string|null $performedByName The username for historical accuracy
 * @return bool True if logged successfully, false otherwise
 */
if (!function_exists('logHousekeepingAction')) {
    function logHousekeepingAction(int $assignmentId, string $action, ?array $oldValues, ?array $newValues, ?int $performedBy = null, ?string $performedByName = null): bool {
    global $pdo;
    
    // Check if audit table exists
    if (!auditTableExists($pdo, 'housekeeping_audit_log')) {
        error_log('housekeeping_audit_log table does not exist - cannot log action');
        return false;
    }
    
    // Validate action type
    $validActions = ['created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created'];
    if (!in_array($action, $validActions, true)) {
        error_log('Invalid housekeeping audit action: ' . $action);
        return false;
    }
    
    try {
        // Calculate changed fields
        $changedFields = [];
        if ($oldValues !== null && $newValues !== null) {
            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changedFields[] = $key;
                }
            }
        } elseif ($oldValues !== null) {
            $changedFields = array_keys($oldValues);
        } elseif ($newValues !== null) {
            $changedFields = array_keys($newValues);
        }
        
        // Prepare data for JSON storage (remove sensitive data)
        $safeOldValues = $oldValues !== null ? json_encode($oldValues) : null;
        $safeNewValues = $newValues !== null ? json_encode($newValues) : null;
        $safeChangedFields = !empty($changedFields) ? json_encode($changedFields) : null;
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Insert audit log entry
        $stmt = $pdo->prepare("
            INSERT INTO housekeeping_audit_log 
            (assignment_id, action, old_values, new_values, changed_fields, performed_by, performed_by_name, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $assignmentId,
            $action,
            $safeOldValues,
            $safeNewValues,
            $safeChangedFields,
            $performedBy,
            $performedByName,
            $ipAddress,
            mb_substr($userAgent, 0, 500)
        ]);
        
        return true;
    } catch (Throwable $e) {
        error_log('Failed to log housekeeping action: ' . $e->getMessage());
        return false;
    }
    }
}

/**
 * Get audit log for a specific housekeeping assignment
 *
 * @param int $assignmentId The ID of the housekeeping assignment
 * @return array Array of audit log entries with performed_by_name
 */
if (!function_exists('getHousekeepingAuditLog')) {
    function getHousekeepingAuditLog(int $assignmentId): array {
    global $pdo;
    
    // Check if audit table exists
    if (!auditTableExists($pdo, 'housekeeping_audit_log')) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                hal.id,
                hal.assignment_id,
                hal.action,
                hal.old_values,
                hal.new_values,
                hal.changed_fields,
                hal.performed_by,
                hal.performed_by_name,
                hal.performed_at,
                hal.ip_address,
                COALESCE(hal.performed_by_name, au.username) as performed_by_display
            FROM housekeeping_audit_log hal
            LEFT JOIN admin_users au ON hal.performed_by = au.id
            WHERE hal.assignment_id = ?
            ORDER BY hal.performed_at DESC, hal.id DESC
        ");
        
        $stmt->execute([$assignmentId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] !== null ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] !== null ? json_decode($log['new_values'], true) : null;
            $log['changed_fields'] = $log['changed_fields'] !== null ? json_decode($log['changed_fields'], true) : [];
        }
        
        return $logs;
    } catch (Throwable $e) {
        error_log('Failed to get housekeeping audit log: ' . $e->getMessage());
        return [];
    }
    }
}

/**
 * Log an action for a maintenance schedule
 *
 * @param int $maintenanceId The ID of the maintenance schedule
 * @param string $action The action performed (created, updated, deleted, verified, etc.)
 * @param array|null $oldValues The values before the change (null for create)
 * @param array|null $newValues The values after the change (null for delete)
 * @param int|null $performedBy The user ID who performed the action
 * @param string|null $performedByName The username for historical accuracy
 * @return bool True if logged successfully, false otherwise
 */
if (!function_exists('logMaintenanceAction')) {
    function logMaintenanceAction(int $maintenanceId, string $action, ?array $oldValues, ?array $newValues, ?int $performedBy = null, ?string $performedByName = null): bool {
    global $pdo;
    
    // Check if audit table exists
    if (!auditTableExists($pdo, 'maintenance_audit_log')) {
        error_log('maintenance_audit_log table does not exist - cannot log action');
        return false;
    }
    
    // Validate action type
    $validActions = ['created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created', 'type_changed'];
    if (!in_array($action, $validActions, true)) {
        error_log('Invalid maintenance audit action: ' . $action);
        return false;
    }
    
    try {
        // Calculate changed fields
        $changedFields = [];
        if ($oldValues !== null && $newValues !== null) {
            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changedFields[] = $key;
                }
            }
        } elseif ($oldValues !== null) {
            $changedFields = array_keys($oldValues);
        } elseif ($newValues !== null) {
            $changedFields = array_keys($newValues);
        }
        
        // Prepare data for JSON storage (remove sensitive data)
        $safeOldValues = $oldValues !== null ? json_encode($oldValues) : null;
        $safeNewValues = $newValues !== null ? json_encode($newValues) : null;
        $safeChangedFields = !empty($changedFields) ? json_encode($changedFields) : null;
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Insert audit log entry
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_audit_log 
            (maintenance_id, action, old_values, new_values, changed_fields, performed_by, performed_by_name, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $maintenanceId,
            $action,
            $safeOldValues,
            $safeNewValues,
            $safeChangedFields,
            $performedBy,
            $performedByName,
            $ipAddress,
            mb_substr($userAgent, 0, 500)
        ]);
        
        return true;
    } catch (Throwable $e) {
        error_log('Failed to log maintenance action: ' . $e->getMessage());
        return false;
    }
    }
}

/**
 * Get audit log for a specific maintenance schedule
 *
 * @param int $maintenanceId The ID of the maintenance schedule
 * @return array Array of audit log entries with performed_by_name
 */
if (!function_exists('getMaintenanceAuditLog')) {
    function getMaintenanceAuditLog(int $maintenanceId): array {
    global $pdo;
    
    // Check if audit table exists
    if (!auditTableExists($pdo, 'maintenance_audit_log')) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                mal.id,
                mal.maintenance_id,
                mal.action,
                mal.old_values,
                mal.new_values,
                mal.changed_fields,
                mal.performed_by,
                mal.performed_by_name,
                mal.performed_at,
                mal.ip_address,
                COALESCE(mal.performed_by_name, au.username) as performed_by_display
            FROM maintenance_audit_log mal
            LEFT JOIN admin_users au ON mal.performed_by = au.id
            WHERE mal.maintenance_id = ?
            ORDER BY mal.performed_at DESC, mal.id DESC
        ");
        
        $stmt->execute([$maintenanceId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] !== null ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] !== null ? json_decode($log['new_values'], true) : null;
            $log['changed_fields'] = $log['changed_fields'] !== null ? json_decode($log['changed_fields'], true) : [];
        }
        
        return $logs;
    } catch (Throwable $e) {
        error_log('Failed to get maintenance audit log: ' . $e->getMessage());
        return [];
    }
    }
}

/**
 * Get recent audit activity across all housekeeping assignments
 * Useful for dashboard widgets and activity feeds
 * 
 * @param int $limit Maximum number of entries to return
 * @return array Array of recent audit log entries
 */
function getRecentHousekeepingActivity(int $limit = 20): array {
    global $pdo;
    
    if (!auditTableExists($pdo, 'housekeeping_audit_log')) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                hal.id,
                hal.assignment_id,
                hal.action,
                hal.performed_by_name,
                hal.performed_at,
                ir.room_number,
                ir.room_name
            FROM housekeeping_audit_log hal
            LEFT JOIN housekeeping_assignments ha ON hal.assignment_id = ha.id
            LEFT JOIN individual_rooms ir ON ha.individual_room_id = ir.id
            ORDER BY hal.performed_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Failed to get recent housekeeping activity: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get recent audit activity across all maintenance schedules
 * Useful for dashboard widgets and activity feeds
 * 
 * @param int $limit Maximum number of entries to return
 * @return array Array of recent audit log entries
 */
function getRecentMaintenanceActivity(int $limit = 20): array {
    global $pdo;
    
    if (!auditTableExists($pdo, 'maintenance_audit_log')) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                mal.id,
                mal.maintenance_id,
                mal.action,
                mal.performed_by_name,
                mal.performed_at,
                rms.title,
                ir.room_number,
                ir.room_name
            FROM maintenance_audit_log mal
            LEFT JOIN room_maintenance_schedules rms ON mal.maintenance_id = rms.id
            LEFT JOIN individual_rooms ir ON rms.individual_room_id = ir.id
            ORDER BY mal.performed_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Failed to get recent maintenance activity: ' . $e->getMessage());
        return [];
    }
}
