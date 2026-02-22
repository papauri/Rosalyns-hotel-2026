<?php
/**
 * Room Maintenance Management - Admin Panel
 * Enhanced with priority-based assignments, rooms needing maintenance auto-fetch,
 * staff workload tracking, and verification workflow
 * Mirrors housekeeping.php implementation patterns
 */
require_once 'admin-init.php';
require_once 'includes/admin-modal.php';

if (!hasPermission($user['id'], 'room_maintenance')) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php?error=access_denied');
    exit;
}

$message = '';
$error = '';

// Extended status workflow with verification
$validMaintenanceStatuses = ['pending', 'in_progress', 'completed', 'verified', 'cancelled'];
$validPriorities = ['high', 'medium', 'low', 'urgent'];
$validMaintenanceTypes = ['repair', 'replacement', 'inspection', 'upgrade', 'emergency'];
$validRecurringPatterns = ['daily', 'weekly', 'monthly'];

// Priority order for sorting (urgent first)
$priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

/**
 * Check if a room exists and is active
 */
function maintenanceRoomExists(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE id = ? AND is_active = 1");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Check if a user exists and is active
 */
function maintenanceUserExists(PDO $pdo, ?int $userId): bool {
    if (empty($userId)) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Check if a table exists in the database
 */
function maintenanceTableExists(PDO $pdo, string $table): bool {
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
 * Check if a column exists in the room_maintenance_schedules table
 * Caches results for performance
 */
function maintenanceColumnExists(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'room_maintenance_schedules' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    $cache[$column] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$column];
}

/**
 * Set room status and log the change
 */
function maintenanceSetRoomStatus(PDO $pdo, int $roomId, string $newStatus, ?string $reason, ?int $performedBy): void {
    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $oldStatus = (string)$statusStmt->fetchColumn();
    if ($oldStatus === '' || $oldStatus === $newStatus) {
        return;
    }

    $updateStmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $roomId]);

    if (maintenanceTableExists($pdo, 'room_maintenance_log')) {
        $logStmt = $pdo->prepare("INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by) VALUES (?, ?, ?, ?, ?)");
        $logStmt->execute([$roomId, $oldStatus, $newStatus, $reason, $performedBy]);
    }
}

/**
 * Validate due date - cannot be in the past
 */
function validateMaintenanceDueDate(string $dueDate): bool {
    $today = date('Y-m-d');
    $dueTimestamp = strtotime($dueDate);
    $todayTimestamp = strtotime($today);
    
    if ($dueTimestamp === false) {
        return false;
    }
    
    // Due date must be today or in the future
    return $dueTimestamp >= $todayTimestamp;
}

/**
 * Get rooms that need maintenance (based on reported issues or status)
 */
function getRoomsNeedingMaintenance(PDO $pdo): array {
    $sql = "
        SELECT DISTINCT 
            ir.id,
            ir.room_number,
            ir.room_name,
            ir.status as room_status,
            b.id as booking_id,
            b.guest_name,
            b.check_out_date,
            b.status as booking_status,
            CASE 
                WHEN ir.status = 'out_of_order' THEN 'out_of_order'
                WHEN b.status = 'checked-in' THEN 'occupied'
                ELSE 'available'
            END as room_condition
        FROM individual_rooms ir
        LEFT JOIN bookings b ON b.individual_room_id = ir.id 
            AND b.status IN ('checked-in', 'checked-out')
            AND b.check_out_date >= CURDATE()
        WHERE ir.is_active = 1
          AND (
              ir.status = 'out_of_order'
              OR ir.status = 'maintenance'
              OR b.status = 'checked-in'
          )
        ORDER BY 
            CASE ir.status WHEN 'out_of_order' THEN 1 WHEN 'maintenance' THEN 2 ELSE 3 END,
            ir.room_number ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get rooms that need maintenance but have no pending/in-progress tasks
 */
function getMaintenanceNeededRooms(PDO $pdo): array {
    $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
    $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
    
    // Build the NOT EXISTS clause conditionally based on available columns
    $notExistsConditions = [
        "rms.individual_room_id = ir.id",
        "rms.status IN ('pending', 'in_progress')"
    ];
    
    $notExistsClause = implode(' AND ', $notExistsConditions);
    
    $sql = "
        SELECT DISTINCT
            ir.id,
            ir.room_number,
            ir.room_name,
            ir.status as room_status,
            b.id as booking_id,
            b.guest_name
        FROM individual_rooms ir
        LEFT JOIN bookings b ON b.individual_room_id = ir.id
            AND b.status = 'checked-in'
        WHERE ir.is_active = 1
          AND (
              ir.status IN ('out_of_order', 'maintenance')
              OR b.status = 'checked-in'
          )
          AND NOT EXISTS (
              SELECT 1 FROM room_maintenance_schedules rms
              WHERE {$notExistsClause}
          )
        ORDER BY ir.room_number ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get staff workload (number of pending/in-progress assignments per staff)
 * Backward compatible: works with or without migration 005 columns
 */
function getMaintenanceStaffWorkload(PDO $pdo): array {
    $hasPriority = maintenanceColumnExists($pdo, 'priority');
    
    // Build the high_priority_pending conditionally
    $highPriorityCase = $hasPriority
        ? "COUNT(CASE WHEN rms.status = 'pending' AND rms.priority IN ('urgent', 'high') THEN 1 END) as high_priority_pending"
        : "0 as high_priority_pending";
    
    $sql = "
        SELECT
            u.id,
            u.username,
            COUNT(CASE WHEN rms.status IN ('pending', 'in_progress') THEN 1 END) as active_tasks,
            {$highPriorityCase},
            COUNT(CASE WHEN rms.status = 'completed' AND DATE(rms.completed_at) = CURDATE() THEN 1 END) as completed_today
        FROM admin_users u
        LEFT JOIN room_maintenance_schedules rms ON rms.assigned_to = u.id
            AND (rms.status IN ('pending', 'in_progress') OR (rms.status = 'completed' AND DATE(rms.completed_at) = CURDATE()))
        WHERE u.is_active = 1
        GROUP BY u.id, u.username
        ORDER BY active_tasks DESC, u.username ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Auto-create maintenance tasks for rooms that need them
 * Backward compatible: works with or without migration 005 columns
 */
function autoCreateMaintenanceTasks(PDO $pdo, int $performedBy): int {
    $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
    $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
    $hasPriority = maintenanceColumnExists($pdo, 'priority');
    $hasAutoCreated = maintenanceColumnExists($pdo, 'auto_created');
    $hasLinkedBookingId = maintenanceColumnExists($pdo, 'linked_booking_id');
    
    $maintenanceRooms = getMaintenanceNeededRooms($pdo);
    $created = 0;
    
    foreach ($maintenanceRooms as $room) {
        // Check if assignment already exists
        $checkStmt = $pdo->prepare("
            SELECT id FROM room_maintenance_schedules
            WHERE individual_room_id = ? AND status IN ('pending', 'in_progress')
        ");
        $checkStmt->execute([$room['id']]);
        if ($checkStmt->fetch()) {
            continue;
        }
        
        // Determine maintenance type based on room status
        $maintenanceType = 'inspection';
        $priority = 'medium';
        if ($room['room_status'] === 'out_of_order') {
            $maintenanceType = 'repair';
            $priority = 'high';
        } elseif ($room['room_status'] === 'maintenance') {
            $maintenanceType = 'repair';
            $priority = 'urgent';
        }
        
        // Build INSERT columns and values based on available columns
        $insertColumns = ['individual_room_id', 'title', 'status', 'start_date', 'end_date', 'assigned_to', 'created_by'];
        $insertValues = ['?', '?', '?', '?', '?', '?', '?'];
        $insertParams = [
            $room['id'],
            'Auto-generated maintenance task',
            'pending',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', strtotime('+1 day')),
            null,
            $performedBy
        ];
        
        if ($hasDueDate) {
            $insertColumns[] = 'due_date';
            $insertValues[] = '?';
            $insertParams[] = date('Y-m-d');
        }
        if ($hasMaintenanceType) {
            $insertColumns[] = 'maintenance_type';
            $insertValues[] = '?';
            $insertParams[] = $maintenanceType;
        }
        if ($hasPriority) {
            $insertColumns[] = 'priority';
            $insertValues[] = '?';
            $insertParams[] = $priority;
        }
        if ($hasAutoCreated) {
            $insertColumns[] = 'auto_created';
            $insertValues[] = '?';
            $insertParams[] = 1;
        }
        if ($hasLinkedBookingId && !empty($room['booking_id'])) {
            $insertColumns[] = 'linked_booking_id';
            $insertValues[] = '?';
            $insertParams[] = $room['booking_id'];
        }
        
        $insertSql = "INSERT INTO room_maintenance_schedules (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute($insertParams);
        $newMaintenanceId = (int)$pdo->lastInsertId();
        $created++;
        
        // Log audit trail for auto-created maintenance task
        $newData = [
            'individual_room_id' => $room['id'],
            'title' => 'Auto-generated maintenance task',
            'status' => 'pending',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'assigned_to' => null,
            'created_by' => $performedBy,
        ];
        if ($hasDueDate) $newData['due_date'] = date('Y-m-d');
        if ($hasMaintenanceType) $newData['maintenance_type'] = $maintenanceType;
        if ($hasPriority) $newData['priority'] = $priority;
        if ($hasAutoCreated) $newData['auto_created'] = 1;
        if ($hasLinkedBookingId && !empty($room['booking_id'])) $newData['linked_booking_id'] = $room['booking_id'];
        
        logMaintenanceAction($newMaintenanceId, 'created', null, $newData, $performedBy);
    }
    
    return $created;
}

/**
 * Reconcile individual room maintenance status
 * Backward compatible: works with or without migration 005 columns
 */
function reconcileMaintenanceRoomStatus(PDO $pdo, int $roomId, ?int $performedBy = null): void {
    $hasPriority = maintenanceColumnExists($pdo, 'priority');
    $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
    
    // Build SELECT columns based on available columns
    $selectColumns = ['status', 'title'];
    if ($hasPriority) {
        $selectColumns[] = 'priority';
    }
    if ($hasDueDate) {
        $selectColumns[] = 'due_date';
    }
    
    // Build ORDER BY clause based on available columns
    $orderByClauses = [];
    
    if ($hasPriority) {
        $orderByClauses[] = "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END";
    }
    
    if ($hasDueDate) {
        $orderByClauses[] = "due_date ASC";
    }
    
    $orderByClauses[] = "created_at DESC";
    
    $sql = "
        SELECT " . implode(', ', $selectColumns) . "
        FROM room_maintenance_schedules
        WHERE individual_room_id = ?
          AND status IN ('pending','in_progress')
        ORDER BY " . implode(', ', $orderByClauses) . "
        LIMIT 1
    ";
    $openStmt = $pdo->prepare($sql);
    $openStmt->execute([$roomId]);
    $open = $openStmt->fetch(PDO::FETCH_ASSOC);

    if ($open) {
        $roomStatusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
        $roomStatusStmt->execute([$roomId]);
        $roomStatus = (string)$roomStatusStmt->fetchColumn();
        
        // Only set to maintenance if not occupied
        if (!in_array($roomStatus, ['occupied', 'out_of_order'], true)) {
            maintenanceSetRoomStatus($pdo, $roomId, 'maintenance', 'Maintenance assignment active', $performedBy);
        }
        return;
    }

    // No active maintenance - check if room should be available
    $roomStatusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $roomStatusStmt->execute([$roomId]);
    $roomStatus = (string)$roomStatusStmt->fetchColumn();
    
    if ($roomStatus === 'maintenance') {
        maintenanceSetRoomStatus($pdo, $roomId, 'available', 'Maintenance assignment cleared', $performedBy);
    }
}

/**
 * Create recurring maintenance assignments
 * Backward compatible: works with or without migration 005 columns
 */
function createRecurringMaintenance(PDO $pdo, int $performedBy): int {
    $hasIsRecurring = maintenanceColumnExists($pdo, 'is_recurring');
    $hasRecurringPattern = maintenanceColumnExists($pdo, 'recurring_pattern');
    $hasRecurringEndDate = maintenanceColumnExists($pdo, 'recurring_end_date');
    $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
    $hasPriority = maintenanceColumnExists($pdo, 'priority');
    $hasEstimatedDuration = maintenanceColumnExists($pdo, 'estimated_duration');
    $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
    
    // If we don't have the required columns for recurring assignments, return early
    if (!$hasIsRecurring || !$hasRecurringPattern) {
        return 0;
    }
    
    $today = date('Y-m-d');
    $created = 0;
    
    // Build WHERE clause based on available columns
    $whereConditions = [
        "is_recurring = 1",
        "recurring_pattern IS NOT NULL"
    ];
    
    if ($hasRecurringEndDate) {
        $whereConditions[] = "(recurring_end_date IS NULL OR recurring_end_date >= ?)";
    }
    
    $whereConditions[] = "status IN ('completed', 'verified')";
    
    $sql = "SELECT * FROM room_maintenance_schedules WHERE " . implode(' AND ', $whereConditions);
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    if ($hasRecurringEndDate) {
        $params[] = $today;
    }
    
    $stmt->execute($params);
    $recurring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recurring as $assignment) {
        $lastCreated = $assignment['created_at'];
        $shouldCreate = false;
        
        switch ($assignment['recurring_pattern']) {
            case 'daily':
                $shouldCreate = (date('Y-m-d', strtotime($lastCreated)) < $today);
                break;
            case 'weekly':
                $shouldCreate = (strtotime($lastCreated) < strtotime('-7 days'));
                break;
            case 'monthly':
                $shouldCreate = (strtotime($lastCreated) < strtotime('-30 days'));
                break;
        }
        
        if ($shouldCreate) {
            // Calculate new dates
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime('+1 day'));
            $dueDate = $today;
            
            // Build INSERT columns and values based on available columns
            $insertColumns = ['individual_room_id', 'title', 'description', 'status', 'start_date', 'end_date', 'assigned_to', 'created_by'];
            $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
            $insertParams = [
                $assignment['individual_room_id'],
                $assignment['title'],
                $assignment['description'],
                'pending',
                $startDate,
                $endDate,
                $assignment['assigned_to'],
                $performedBy
            ];
            
            if ($hasDueDate) {
                $insertColumns[] = 'due_date';
                $insertValues[] = '?';
                $insertParams[] = $dueDate;
            }
            if ($hasMaintenanceType) {
                $insertColumns[] = 'maintenance_type';
                $insertValues[] = '?';
                $insertParams[] = $assignment['maintenance_type'] ?? 'inspection';
            }
            if ($hasPriority) {
                $insertColumns[] = 'priority';
                $insertValues[] = '?';
                $insertParams[] = $assignment['priority'] ?? 'medium';
            }
            if ($hasIsRecurring) {
                $insertColumns[] = 'is_recurring';
                $insertValues[] = '?';
                $insertParams[] = 1;
            }
            if ($hasRecurringPattern) {
                $insertColumns[] = 'recurring_pattern';
                $insertValues[] = '?';
                $insertParams[] = $assignment['recurring_pattern'];
            }
            if ($hasRecurringEndDate) {
                $insertColumns[] = 'recurring_end_date';
                $insertValues[] = '?';
                $insertParams[] = $assignment['recurring_end_date'];
            }
            if ($hasEstimatedDuration) {
                $insertColumns[] = 'estimated_duration';
                $insertValues[] = '?';
                $insertParams[] = $assignment['estimated_duration'] ?? 60;
            }
            
            $insertSql = "INSERT INTO room_maintenance_schedules (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $newStmt = $pdo->prepare($insertSql);
            $newStmt->execute($insertParams);
            $newMaintenanceId = (int)$pdo->lastInsertId();
            $created++;
            
            // Log audit trail for recurring maintenance creation
            $newData = [
                'individual_room_id' => $assignment['individual_room_id'],
                'title' => $assignment['title'],
                'description' => $assignment['description'],
                'status' => 'pending',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'assigned_to' => $assignment['assigned_to'],
                'created_by' => $performedBy,
            ];
            if ($hasDueDate) $newData['due_date'] = $dueDate;
            if ($hasMaintenanceType) $newData['maintenance_type'] = $assignment['maintenance_type'] ?? 'inspection';
            if ($hasPriority) $newData['priority'] = $assignment['priority'] ?? 'medium';
            if ($hasIsRecurring) $newData['is_recurring'] = 1;
            if ($hasRecurringPattern) $newData['recurring_pattern'] = $assignment['recurring_pattern'];
            if ($hasRecurringEndDate) $newData['recurring_end_date'] = $assignment['recurring_end_date'];
            if ($hasEstimatedDuration) $newData['estimated_duration'] = $assignment['estimated_duration'] ?? 60;
            
            logMaintenanceAction($newMaintenanceId, 'recurring_created', null, $newData, $performedBy);
        }
    }
    
    return $created;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new RuntimeException('Invalid security token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_schedule') {
            $room_id = (int)($_POST['individual_room_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $maintenance_type = $_POST['maintenance_type'] ?? 'repair';
            $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
            $recurring_pattern = $is_recurring ? ($_POST['recurring_pattern'] ?? null) : null;
            $recurring_end_date = $is_recurring ? ($_POST['recurring_end_date'] ?? null) : null;
            $estimated_duration = (int)($_POST['estimated_duration'] ?? 60);
            $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
            $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+1 day'));
            $block_room = isset($_POST['block_room']) ? 1 : 0;
            $linked_booking_id = !empty($_POST['linked_booking_id']) ? (int)$_POST['linked_booking_id'] : null;

            // Check which columns exist for backward compatibility
            $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
            $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
            $hasPriority = maintenanceColumnExists($pdo, 'priority');
            $hasIsRecurring = maintenanceColumnExists($pdo, 'is_recurring');
            $hasRecurringPattern = maintenanceColumnExists($pdo, 'recurring_pattern');
            $hasRecurringEndDate = maintenanceColumnExists($pdo, 'recurring_end_date');
            $hasEstimatedDuration = maintenanceColumnExists($pdo, 'estimated_duration');
            $hasActualDuration = maintenanceColumnExists($pdo, 'actual_duration');
            $hasVerifiedAt = maintenanceColumnExists($pdo, 'verified_at');
            $hasVerifiedBy = maintenanceColumnExists($pdo, 'verified_by');
            $hasCompletedAt = maintenanceColumnExists($pdo, 'completed_at');
            $hasLinkedBookingId = maintenanceColumnExists($pdo, 'linked_booking_id');
            $hasAutoCreated = maintenanceColumnExists($pdo, 'auto_created');

            // Validation
            if (!$room_id) {
                $error = 'Room is required.';
            } elseif (!$title) {
                $error = 'Title is required.';
            } elseif ($hasDueDate && !$due_date) {
                $error = 'Due date is required.';
            } elseif ($hasDueDate && !validateMaintenanceDueDate($due_date)) {
                $error = 'Due date cannot be in the past. Please select today or a future date.';
            } elseif (!in_array($status, $validMaintenanceStatuses, true)) {
                $error = 'Invalid maintenance status.';
            } elseif ($hasPriority && !in_array($priority, $validPriorities, true)) {
                $error = 'Invalid priority level.';
            } elseif ($hasMaintenanceType && !in_array($maintenance_type, $validMaintenanceTypes, true)) {
                $error = 'Invalid maintenance type.';
            } elseif ($hasIsRecurring && $is_recurring && !in_array($recurring_pattern, $validRecurringPatterns, true)) {
                $error = 'Invalid recurring pattern.';
            } elseif (!maintenanceRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!maintenanceUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($start_date) === false || strtotime($end_date) === false) {
                $error = 'Invalid date format.';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $error = 'End date must be after start date.';
            } else {
                $pdo->beginTransaction();
                
                $completedAt = in_array($status, ['completed', 'verified'], true) ? date('Y-m-d H:i:s') : null;
                $verifiedAt = ($hasVerifiedAt && $status === 'verified') ? date('Y-m-d H:i:s') : null;
                $verifiedBy = ($hasVerifiedBy && $status === 'verified') ? ($user['id'] ?? null) : null;
                
                // Build INSERT columns and values based on available columns
                $insertColumns = ['individual_room_id', 'title', 'description', 'status', 'start_date', 'end_date', 'assigned_to', 'created_by'];
                $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
                $insertParams = [$room_id, $title, $description, $status, $start_date, $end_date, $assigned_to, $user['id'] ?? null];
                
                if ($hasDueDate) {
                    $insertColumns[] = 'due_date';
                    $insertValues[] = '?';
                    $insertParams[] = $due_date;
                }
                if ($hasPriority) {
                    $insertColumns[] = 'priority';
                    $insertValues[] = '?';
                    $insertParams[] = $priority;
                }
                if ($hasMaintenanceType) {
                    $insertColumns[] = 'maintenance_type';
                    $insertValues[] = '?';
                    $insertParams[] = $maintenance_type;
                }
                if ($hasIsRecurring) {
                    $insertColumns[] = 'is_recurring';
                    $insertValues[] = '?';
                    $insertParams[] = $is_recurring;
                }
                if ($hasRecurringPattern) {
                    $insertColumns[] = 'recurring_pattern';
                    $insertValues[] = '?';
                    $insertParams[] = $recurring_pattern;
                }
                if ($hasRecurringEndDate) {
                    $insertColumns[] = 'recurring_end_date';
                    $insertValues[] = '?';
                    $insertParams[] = $recurring_end_date;
                }
                if ($hasEstimatedDuration) {
                    $insertColumns[] = 'estimated_duration';
                    $insertValues[] = '?';
                    $insertParams[] = $estimated_duration;
                }
                if ($hasCompletedAt) {
                    $insertColumns[] = 'completed_at';
                    $insertValues[] = '?';
                    $insertParams[] = $completedAt;
                }
                if ($hasVerifiedBy) {
                    $insertColumns[] = 'verified_by';
                    $insertValues[] = '?';
                    $insertParams[] = $verifiedBy;
                }
                if ($hasVerifiedAt) {
                    $insertColumns[] = 'verified_at';
                    $insertValues[] = '?';
                    $insertParams[] = $verifiedAt;
                }
                if ($hasLinkedBookingId) {
                    $insertColumns[] = 'linked_booking_id';
                    $insertValues[] = '?';
                    $insertParams[] = $linked_booking_id;
                }
                if ($hasAutoCreated) {
                    $insertColumns[] = 'auto_created';
                    $insertValues[] = '?';
                    $insertParams[] = 0;
                }
                if (maintenanceColumnExists($pdo, 'block_room')) {
                    $insertColumns[] = 'block_room';
                    $insertValues[] = '?';
                    $insertParams[] = $block_room;
                }
                
                $insertSql = "INSERT INTO room_maintenance_schedules (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertParams);
                $newMaintenanceId = (int)$pdo->lastInsertId();

                reconcileMaintenanceRoomStatus($pdo, $room_id, $user['id'] ?? null);
                
                // Log audit trail
                $newData = [
                    'individual_room_id' => $room_id,
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'assigned_to' => $assigned_to,
                    'created_by' => $user['id'] ?? null,
                ];
                if ($hasDueDate) $newData['due_date'] = $due_date;
                if ($hasPriority) $newData['priority'] = $priority;
                if ($hasMaintenanceType) $newData['maintenance_type'] = $maintenance_type;
                if ($hasIsRecurring) $newData['is_recurring'] = $is_recurring;
                if ($hasRecurringPattern) $newData['recurring_pattern'] = $recurring_pattern;
                if ($hasRecurringEndDate) $newData['recurring_end_date'] = $recurring_end_date;
                if ($hasEstimatedDuration) $newData['estimated_duration'] = $estimated_duration;
                if ($hasCompletedAt) $newData['completed_at'] = $completedAt;
                if ($hasVerifiedBy) $newData['verified_by'] = $verifiedBy;
                if ($hasVerifiedAt) $newData['verified_at'] = $verifiedAt;
                if ($hasLinkedBookingId) $newData['linked_booking_id'] = $linked_booking_id;
                if ($hasAutoCreated) $newData['auto_created'] = 0;
                if (maintenanceColumnExists($pdo, 'block_room')) $newData['block_room'] = $block_room;
                
                logMaintenanceAction($newMaintenanceId, 'created', null, $newData, $user['id'] ?? null, $user['username'] ?? null);
                
                $pdo->commit();
                $message = 'Maintenance schedule created successfully.';
            }
        } elseif ($action === 'update_schedule') {
            $id = (int)($_POST['id'] ?? 0);
            $room_id = (int)($_POST['individual_room_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $maintenance_type = $_POST['maintenance_type'] ?? 'repair';
            $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
            $recurring_pattern = $is_recurring ? ($_POST['recurring_pattern'] ?? null) : null;
            $recurring_end_date = $is_recurring ? ($_POST['recurring_end_date'] ?? null) : null;
            $estimated_duration = (int)($_POST['estimated_duration'] ?? 60);
            $actual_duration = !empty($_POST['actual_duration']) ? (int)$_POST['actual_duration'] : null;
            $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
            $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+1 day'));
            $block_room = isset($_POST['block_room']) ? 1 : 0;
            $linked_booking_id = !empty($_POST['linked_booking_id']) ? (int)$_POST['linked_booking_id'] : null;

            // Check which columns exist for backward compatibility
            $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
            $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
            $hasPriority = maintenanceColumnExists($pdo, 'priority');
            $hasIsRecurring = maintenanceColumnExists($pdo, 'is_recurring');
            $hasRecurringPattern = maintenanceColumnExists($pdo, 'recurring_pattern');
            $hasRecurringEndDate = maintenanceColumnExists($pdo, 'recurring_end_date');
            $hasEstimatedDuration = maintenanceColumnExists($pdo, 'estimated_duration');
            $hasActualDuration = maintenanceColumnExists($pdo, 'actual_duration');
            $hasVerifiedAt = maintenanceColumnExists($pdo, 'verified_at');
            $hasVerifiedBy = maintenanceColumnExists($pdo, 'verified_by');
            $hasCompletedAt = maintenanceColumnExists($pdo, 'completed_at');
            $hasLinkedBookingId = maintenanceColumnExists($pdo, 'linked_booking_id');

            // Validation
            if (!$id || !$room_id || !$title) {
                $error = 'Room and title are required.';
            } elseif ($hasDueDate && !$due_date) {
                $error = 'Due date is required.';
            } elseif ($hasDueDate && !validateMaintenanceDueDate($due_date)) {
                $error = 'Due date cannot be in the past. Please select today or a future date.';
            } elseif (!in_array($status, $validMaintenanceStatuses, true)) {
                $error = 'Invalid maintenance status.';
            } elseif ($hasPriority && !in_array($priority, $validPriorities, true)) {
                $error = 'Invalid priority level.';
            } elseif ($hasMaintenanceType && !in_array($maintenance_type, $validMaintenanceTypes, true)) {
                $error = 'Invalid maintenance type.';
            } elseif ($hasIsRecurring && $is_recurring && !in_array($recurring_pattern, $validRecurringPatterns, true)) {
                $error = 'Invalid recurring pattern.';
            } elseif (!maintenanceRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!maintenanceUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($start_date) === false || strtotime($end_date) === false) {
                $error = 'Invalid date format.';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $error = 'End date must be after start date.';
            } else {
                $pdo->beginTransaction();
                $existsStmt = $pdo->prepare("SELECT id, individual_room_id, status, verified_by FROM room_maintenance_schedules WHERE id = ?");
                $existsStmt->execute([$id]);
                $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new RuntimeException('Maintenance schedule does not exist.');
                }

                // Auto-set verified_by when status changes to verified
                $verifiedBy = $existing['verified_by'] ?? null;
                $verifiedAt = null;
                if ($hasVerifiedBy && $hasVerifiedAt && $status === 'verified' && ($existing['status'] ?? '') !== 'verified') {
                    $verifiedBy = $user['id'] ?? null;
                    $verifiedAt = date('Y-m-d H:i:s');
                } elseif ($status !== 'verified' && $hasVerifiedAt) {
                    $verifiedAt = null;
                    if ($hasVerifiedBy) {
                        $verifiedBy = null;
                    }
                }

                $completedAt = in_array($status, ['completed', 'verified'], true) ? date('Y-m-d H:i:s') : null;
                if (($existing['status'] ?? '') === 'completed' && $status !== 'completed' && $status !== 'verified') {
                    $completedAt = null;
                }

                // Build UPDATE SET clause based on available columns
                $setColumns = ['individual_room_id=?', 'title=?', 'description=?', 'status=?', 'start_date=?', 'end_date=?', 'assigned_to=?'];
                $updateParams = [$room_id, $title, $description, $status, $start_date, $end_date, $assigned_to];
                
                if ($hasDueDate) {
                    $setColumns[] = 'due_date=?';
                    $updateParams[] = $due_date;
                }
                if ($hasPriority) {
                    $setColumns[] = 'priority=?';
                    $updateParams[] = $priority;
                }
                if ($hasMaintenanceType) {
                    $setColumns[] = 'maintenance_type=?';
                    $updateParams[] = $maintenance_type;
                }
                if ($hasIsRecurring) {
                    $setColumns[] = 'is_recurring=?';
                    $updateParams[] = $is_recurring;
                }
                if ($hasRecurringPattern) {
                    $setColumns[] = 'recurring_pattern=?';
                    $updateParams[] = $recurring_pattern;
                }
                if ($hasRecurringEndDate) {
                    $setColumns[] = 'recurring_end_date=?';
                    $updateParams[] = $recurring_end_date;
                }
                if ($hasEstimatedDuration) {
                    $setColumns[] = 'estimated_duration=?';
                    $updateParams[] = $estimated_duration;
                }
                if ($hasActualDuration) {
                    $setColumns[] = 'actual_duration=?';
                    $updateParams[] = $actual_duration;
                }
                if ($hasCompletedAt) {
                    $setColumns[] = 'completed_at=?';
                    $updateParams[] = $completedAt;
                }
                if ($hasVerifiedBy) {
                    $setColumns[] = 'verified_by=?';
                    $updateParams[] = $verifiedBy;
                }
                if ($hasVerifiedAt) {
                    $setColumns[] = 'verified_at=?';
                    $updateParams[] = $verifiedAt;
                }
                if ($hasLinkedBookingId) {
                    $setColumns[] = 'linked_booking_id=?';
                    $updateParams[] = $linked_booking_id;
                }
                if (maintenanceColumnExists($pdo, 'block_room')) {
                    $setColumns[] = 'block_room=?';
                    $updateParams[] = $block_room;
                }
                
                $updateParams[] = $id; // WHERE id=?

                $updateSql = "UPDATE room_maintenance_schedules SET " . implode(', ', $setColumns) . " WHERE id=?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($updateParams);

                // Get updated data for audit log
                $updatedStmt = $pdo->prepare("SELECT * FROM room_maintenance_schedules WHERE id = ?");
                $updatedStmt->execute([$id]);
                $newData = $updatedStmt->fetch(PDO::FETCH_ASSOC);
                
                // Determine action type
                $action = 'updated';
                if (($existing['status'] ?? '') !== $status) {
                    $action = 'status_changed';
                }
                if (($existing['assigned_to'] ?? null) != $assigned_to) {
                    $action = $assigned_to ? 'assigned' : 'unassigned';
                }
                if ($hasPriority && ($existing['priority'] ?? null) !== $priority) {
                    $action = 'priority_changed';
                }
                if ($hasMaintenanceType && ($existing['maintenance_type'] ?? null) !== $maintenance_type) {
                    $action = 'type_changed';
                }
                
                logMaintenanceAction($id, $action, $existing, $newData, $user['id'] ?? null, $user['username'] ?? null);

                reconcileMaintenanceRoomStatus($pdo, $room_id, $user['id'] ?? null);
                if ((int)($existing['individual_room_id'] ?? 0) !== $room_id) {
                    reconcileMaintenanceRoomStatus($pdo, (int)($existing['individual_room_id'] ?? 0), $user['id'] ?? null);
                }
                $pdo->commit();
                $message = 'Maintenance schedule updated successfully.';
            }
        } elseif ($action === 'delete_schedule') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid schedule selected.';
            } else {
                $pdo->beginTransaction();
                $rowStmt = $pdo->prepare("SELECT individual_room_id FROM room_maintenance_schedules WHERE id = ?");
                $rowStmt->execute([$id]);
                $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Schedule not found.');
                }

                // Get schedule data before deletion for audit log
                $dataStmt = $pdo->prepare("SELECT * FROM room_maintenance_schedules WHERE id = ?");
                $dataStmt->execute([$id]);
                $deletedData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                
                $pdo->prepare("DELETE FROM room_maintenance_schedules WHERE id = ?")->execute([$id]);

                reconcileMaintenanceRoomStatus($pdo, (int)($row['individual_room_id'] ?? 0), $user['id'] ?? null);
                
                // Log audit trail
                logMaintenanceAction($id, 'deleted', $deletedData, null, $user['id'] ?? null, $user['username'] ?? null);

                $pdo->commit();
                $message = 'Maintenance schedule deleted successfully.';
            }
        } elseif ($action === 'auto_create_tasks') {
            $pdo->beginTransaction();
            $created = autoCreateMaintenanceTasks($pdo, $user['id'] ?? null);
            $pdo->commit();
            $message = "Auto-created {$created} maintenance tasks.";
        } elseif ($action === 'bulk_assign_rooms') {
            $room_ids = $_POST['room_ids'] ?? [];
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $priority = $_POST['priority'] ?? 'medium';
            $maintenance_type = $_POST['maintenance_type'] ?? 'inspection';
            
            // Check which columns exist for backward compatibility
            $hasDueDate = maintenanceColumnExists($pdo, 'due_date');
            $hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
            $hasPriority = maintenanceColumnExists($pdo, 'priority');
            $hasAutoCreated = maintenanceColumnExists($pdo, 'auto_created');
            
            if (empty($room_ids)) {
                $error = 'No rooms selected.';
            } else {
                $pdo->beginTransaction();
                $created = 0;
                $today = date('Y-m-d');
                
                foreach ($room_ids as $room_id) {
                    $room_id = (int)$room_id;
                    if (!maintenanceRoomExists($pdo, $room_id)) {
                        continue;
                    }
                    
                    // Check if pending assignment already exists
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM room_maintenance_schedules
                        WHERE individual_room_id = ? AND status IN ('pending', 'in_progress')
                    ");
                    $checkStmt->execute([$room_id]);
                    if ($checkStmt->fetch()) {
                        continue;
                    }
                    
                    // Build INSERT columns and values based on available columns
                    $insertColumns = ['individual_room_id', 'title', 'status', 'start_date', 'end_date', 'assigned_to', 'created_by'];
                    $insertValues = ['?', '?', '?', '?', '?', '?', '?'];
                    $insertParams = [
                        $room_id,
                        'Bulk assigned maintenance task',
                        'pending',
                        date('Y-m-d H:i:s'),
                        date('Y-m-d H:i:s', strtotime('+1 day')),
                        $assigned_to,
                        $user['id'] ?? null
                    ];
                    
                    if ($hasDueDate) {
                        $insertColumns[] = 'due_date';
                        $insertValues[] = '?';
                        $insertParams[] = $today;
                    }
                    if ($hasMaintenanceType) {
                        $insertColumns[] = 'maintenance_type';
                        $insertValues[] = '?';
                        $insertParams[] = $maintenance_type;
                    }
                    if ($hasPriority) {
                        $insertColumns[] = 'priority';
                        $insertValues[] = '?';
                        $insertParams[] = $priority;
                    }
                    if ($hasAutoCreated) {
                        $insertColumns[] = 'auto_created';
                        $insertValues[] = '?';
                        $insertParams[] = 1;
                    }
                    
                    $insertSql = "INSERT INTO room_maintenance_schedules (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
                    $stmt = $pdo->prepare($insertSql);
                    $stmt->execute($insertParams);
                    $newMaintenanceId = (int)$pdo->lastInsertId();
                    $created++;
                    
                    // Log audit trail for bulk created maintenance task
                    $newData = [
                        'individual_room_id' => $room_id,
                        'title' => 'Bulk assigned maintenance task',
                        'status' => 'pending',
                        'start_date' => date('Y-m-d H:i:s'),
                        'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                        'assigned_to' => $assigned_to,
                        'created_by' => $user['id'] ?? null,
                    ];
                    if ($hasDueDate) $newData['due_date'] = $today;
                    if ($hasMaintenanceType) $newData['maintenance_type'] = $maintenance_type;
                    if ($hasPriority) $newData['priority'] = $priority;
                    if ($hasAutoCreated) $newData['auto_created'] = 1;
                    
                    logMaintenanceAction($newMaintenanceId, 'created', null, $newData, $user['id'] ?? null, $user['username'] ?? null);
                    
                    reconcileMaintenanceRoomStatus($pdo, $room_id, $user['id'] ?? null);
                }
                
                $pdo->commit();
                $message = "Bulk assigned {$created} rooms for maintenance.";
            }
        } elseif ($action === 'verify_schedule') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid schedule selected.';
            } else {
                $hasVerifiedBy = maintenanceColumnExists($pdo, 'verified_by');
                $hasVerifiedAt = maintenanceColumnExists($pdo, 'verified_at');
                
                // If we don't have the required columns for verification, show error
                if (!$hasVerifiedBy || !$hasVerifiedAt) {
                    $error = 'Verification feature requires database migration 005. Please contact administrator.';
                } else {
                    $pdo->beginTransaction();
                    // Get schedule data before verification for audit log
                    $dataStmt = $pdo->prepare("SELECT * FROM room_maintenance_schedules WHERE id = ?");
                    $dataStmt->execute([$id]);
                    $beforeData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("
                        UPDATE room_maintenance_schedules
                        SET status = 'verified', verified_by = ?, verified_at = NOW()
                        WHERE id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$user['id'] ?? null, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Get updated data for audit log
                        $dataStmt->execute([$id]);
                        $afterData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log audit trail
                        logMaintenanceAction($id, 'verified', $beforeData, $afterData, $user['id'] ?? null, $user['username'] ?? null);
                        
                        $message = 'Maintenance verified successfully.';
                    } else {
                        $error = 'Schedule not found or not in completed status.';
                    }
                    
                    $pdo->commit();
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Reconcile all rooms with open assignments
try {
    $roomRows = $pdo->query("SELECT DISTINCT individual_room_id FROM room_maintenance_schedules")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roomRows as $roomId) {
        reconcileMaintenanceRoomStatus($pdo, (int)$roomId, $user['id'] ?? null);
    }
} catch (Throwable $syncError) {
    error_log('Maintenance reconciliation warning: ' . $syncError->getMessage());
}

// Get data for the page
$roomsStmt = $pdo->query("SELECT id, room_number, room_name, status FROM individual_rooms WHERE is_active = 1 ORDER BY room_number ASC");
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query("SELECT id, username FROM admin_users WHERE is_active = 1 ORDER BY username ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get bookings for linking
$bookingsStmt = $pdo->query("
    SELECT b.id, b.booking_reference, b.guest_name, ir.room_number, b.check_in_date, b.check_out_date, b.status
    FROM bookings b
    LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
    WHERE b.status IN ('pending', 'confirmed', 'checked-in')
    ORDER BY b.check_in_date DESC
    LIMIT 100
");
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get rooms needing maintenance
$roomsNeedingMaintenance = getRoomsNeedingMaintenance($pdo);

// Get staff workload
$staffWorkload = getMaintenanceStaffWorkload($pdo);

// Get all schedules with enhanced sorting
// Backward compatible: works with or without migration 005 columns
$hasPriority = maintenanceColumnExists($pdo, 'priority');
$hasVerifiedBy = maintenanceColumnExists($pdo, 'verified_by');
$hasMaintenanceType = maintenanceColumnExists($pdo, 'maintenance_type');
$hasDueDate = maintenanceColumnExists($pdo, 'due_date');

// Build ORDER BY clause based on available columns
$orderByClauses = [
    "CASE rms.status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 WHEN 'verified' THEN 4 WHEN 'cancelled' THEN 5 ELSE 99 END"
];

if ($hasPriority) {
    $orderByClauses[] = "CASE rms.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END";
}

if ($hasDueDate) {
    $orderByClauses[] = "rms.due_date ASC";
} else {
    $orderByClauses[] = "rms.start_date ASC";
}

$orderByClauses[] = "rms.created_at DESC";

$scheduleStmt = $pdo->query("
    SELECT rms.*, ir.room_number, ir.room_name, u.username as assigned_to_name, creator.username as created_by_name" .
    ($hasVerifiedBy ? ", verifier.username as verified_by_name" : "") . "
    FROM room_maintenance_schedules rms
    LEFT JOIN individual_rooms ir ON rms.individual_room_id = ir.id
    LEFT JOIN admin_users u ON rms.assigned_to = u.id
    LEFT JOIN admin_users creator ON rms.created_by = creator.id" .
    ($hasVerifiedBy ? "
    LEFT JOIN admin_users verifier ON rms.verified_by = verifier.id" : "") . "
    ORDER BY " . implode(', ', $orderByClauses)
);
$schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
// Backward compatible: works with or without migration 005 columns
$stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'verified' => 0,
    'cancelled' => 0,
    'high_priority' => 0,
    'urgent_priority' => 0,
    'emergency_type' => 0,
];
foreach ($schedules as $s) {
    $stats[$s['status']] = ($stats[$s['status']] ?? 0) + 1;
    if ($hasPriority && $s['priority'] === 'high') $stats['high_priority']++;
    if ($hasPriority && $s['priority'] === 'urgent') $stats['urgent_priority']++;
    if ($hasMaintenanceType && ($s['maintenance_type'] ?? null) === 'emergency') $stats['emergency_type']++;
}

// Maintenance logs
$maintenanceLogs = [];
try {
    if (maintenanceTableExists($pdo, 'room_maintenance_log')) {
        $logStmt = $pdo->prepare("
            SELECT rml.*, ir.room_number, ir.room_name, au.username AS performed_by_name
            FROM room_maintenance_log rml
            LEFT JOIN individual_rooms ir ON rml.individual_room_id = ir.id
            LEFT JOIN admin_users au ON rml.performed_by = au.id
            ORDER BY rml.created_at DESC, rml.id DESC
            LIMIT 50
        ");
        $logStmt->execute();
        $maintenanceLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Unable to load maintenance logs: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Maintenance - Admin Panel</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/room-maintenance.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .rm-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .rm-stat-card { background: white; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .rm-stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--navy, #1f2d3d); }
        .rm-stat-card .stat-label { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .rm-stat-card.pending { border-left: 4px solid #3747a5; }
        .rm-stat-card.in_progress { border-left: 4px solid #a46200; }
        .rm-stat-card.completed { border-left: 4px solid #1f7a45; }
        .rm-stat-card.high_priority { border-left: 4px solid #dc2626; }
        .rm-stat-card.urgent_priority { border-left: 4px solid #7c2d12; }
        .rm-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .rm-section h3 { margin: 0 0 16px; font-size: 18px; color: var(--navy, #1f2d3d); display: flex; align-items: center; gap: 8px; }
        .rooms-needing-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
        .room-needs-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fafafa; }
        .room-needs-card.out-of-order { border-color: #dc2626; background: #fef2f2; }
        .room-needs-card.maintenance { border-color: #ea580c; background: #fff7ed; }
        .room-needs-card .room-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .room-needs-card .room-number { font-weight: 600; font-size: 16px; }
        .room-needs-card .room-condition { font-size: 11px; padding: 2px 8px; border-radius: 99px; background: #e5e7eb; }
        .room-needs-card.out-of-order .room-condition { background: #fecaca; color: #991b1b; }
        .room-needs-card.maintenance .room-condition { background: #fed7aa; color: #9a3412; }
        .room-needs-card .guest-info { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
        .staff-workload-table { width: 100%; border-collapse: collapse; }
        .staff-workload-table th, .staff-workload-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .staff-workload-table th { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600; }
        .priority-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .priority-badge.urgent { background: #7c2d12; color: #fef2f2; }
        .priority-badge.high { background: #fee2e2; color: #dc2626; }
        .priority-badge.medium { background: #fef3c7; color: #d97706; }
        .priority-badge.low { background: #e0e7ff; color: #4f46e5; }
        .type-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #4b5563; }
        .type-badge.repair { background: #fee2e2; color: #dc2626; }
        .type-badge.replacement { background: #fef3c7; color: #d97706; }
        .type-badge.inspection { background: #dbeafe; color: #2563eb; }
        .type-badge.upgrade { background: #e0e7ff; color: #7c3aed; }
        .type-badge.emergency { background: #7c2d12; color: #fef2f2; }
        .bulk-actions { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .btn-quick { padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #d1d5db; background: white; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-quick:hover { background: #f9fafb; border-color: #9ca3af; }
        .btn-quick i { font-size: 12px; }
        .table-card th { position: relative; }
        .table-card th .sort-indicator { margin-left: 4px; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
<?php require_once 'includes/admin-header.php'; ?>
<div class="content">
    <div class="page-header">
        <h2><i class="fas fa-tools"></i> Room Maintenance Management</h2>
        <div style="display: flex; gap: 10px;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="auto_create_tasks">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button class="btn btn-warning" type="submit" onclick="return confirm('Auto-create maintenance tasks for all rooms that need them?')">
                    <i class="fas fa-magic"></i> Auto-Create Tasks
                </button>
            </form>
            <button class="btn btn-primary" type="button" onclick="openModal()"><i class="fas fa-plus"></i> Add Maintenance</button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:16px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Statistics -->
    <div class="rm-dashboard">
        <div class="rm-stat-card pending">
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label"><i class="fas fa-clock"></i> Pending Tasks</div>
        </div>
        <div class="rm-stat-card in_progress">
            <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label"><i class="fas fa-spinner"></i> In Progress</div>
        </div>
        <div class="rm-stat-card completed">
            <div class="stat-value"><?php echo $stats['completed']; ?></div>
            <div class="stat-label"><i class="fas fa-check"></i> Completed</div>
        </div>
        <div class="rm-stat-card high_priority">
            <div class="stat-value"><?php echo $stats['high_priority'] + $stats['urgent_priority']; ?></div>
            <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> High/Urgent Priority</div>
        </div>
        <div class="rm-stat-card">
            <div class="stat-value"><?php echo $stats['emergency_type']; ?></div>
            <div class="stat-label"><i class="fas fa-bolt"></i> Emergency</div>
        </div>
    </div>

    <!-- Rooms Needing Maintenance Section -->
    <?php if (!empty($roomsNeedingMaintenance)): ?>
    <div class="rm-section">
        <h3><i class="fas fa-exclamation-circle"></i> Rooms Needing Maintenance (<?php echo count($roomsNeedingMaintenance); ?>)</h3>
        <form method="POST" id="bulkAssignForm">
            <input type="hidden" name="action" value="bulk_assign_rooms">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="room_ids" id="selectedRoomIds">
            <div class="bulk-actions">
                <select name="assigned_to" id="bulkAssignTo" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db;">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="maintenance_type" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db;">
                    <option value="inspection">Inspection</option>
                    <option value="repair">Repair</option>
                    <option value="replacement">Replacement</option>
                    <option value="upgrade">Upgrade</option>
                    <option value="emergency">Emergency</option>
                </select>
                <select name="priority" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db;">
                    <option value="medium">Medium Priority</option>
                    <option value="high">High Priority</option>
                    <option value="urgent">Urgent Priority</option>
                    <option value="low">Low Priority</option>
                </select>
                <button type="button" class="btn-quick" onclick="selectAllRooms()">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;">
                    <i class="fas fa-plus"></i> Assign Selected
                </button>
            </div>
            <div class="rooms-needing-list">
                <?php foreach ($roomsNeedingMaintenance as $room): ?>
                <div class="room-needs-card <?php echo $room['room_status'] === 'out_of_order' ? 'out-of-order' : ($room['room_status'] === 'maintenance' ? 'maintenance' : ''); ?>" data-room-id="<?php echo $room['id']; ?>">
                    <div class="room-header">
                        <span class="room-number"><?php echo htmlspecialchars($room['room_number'] . ' ' . ($room['room_name'] ?? '')); ?></span>
                        <span class="room-condition"><?php echo ucfirst(str_replace('_', ' ', $room['room_status'] ?? 'available')); ?></span>
                    </div>
                    <?php if (!empty($room['guest_name'])): ?>
                    <div class="guest-info"><i class="fas fa-user"></i> <?php echo htmlspecialchars($room['guest_name']); ?></div>
                    <?php endif; ?>
                    <div style="margin-top: 8px;">
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="checkbox" class="room-checkbox" value="<?php echo $room['id']; ?>">
                            Select for assignment
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Staff Workload Section -->
    <?php if (!empty($staffWorkload)): ?>
    <div class="rm-section">
        <h3><i class="fas fa-users"></i> Staff Workload</h3>
        <table class="staff-workload-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Active Tasks</th>
                    <th>High Priority Pending</th>
                    <th>Completed Today</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffWorkload as $staff): ?>
                <tr>
                    <td><?php echo htmlspecialchars($staff['username']); ?></td>
                    <td><?php echo $staff['active_tasks']; ?></td>
                    <td><?php echo $staff['high_priority_pending']; ?></td>
                    <td><?php echo $staff['completed_today']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Maintenance Schedules Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($schedules)): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">No maintenance schedules.</td></tr>
            <?php else: ?>
                <?php foreach ($schedules as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['room_number'] . ' ' . ($row['room_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td>
                        <?php if ($hasMaintenanceType): ?>
                        <span class="type-badge <?php echo $row['maintenance_type']; ?>"><?php echo ucfirst($row['maintenance_type']); ?></span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasPriority): ?>
                        <span class="priority-badge <?php echo $row['priority']; ?>"><?php echo ucfirst($row['priority']); ?></span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><span class="status-pill <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($hasDueDate ? ($row['due_date'] ?? '-') : date('Y-m-d', strtotime($row['start_date']))); ?></td>
                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?? '-'); ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" type="button" onclick='editSchedule(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-secondary btn-sm" type="button" onclick='viewAuditLog(<?php echo $row['id']; ?>, "<?php echo htmlspecialchars($row['title']); ?>")' title="View History"><i class="fas fa-history"></i></button>
                        <?php if ($row['status'] === 'completed'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="verify_schedule">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button class="btn btn-success btn-sm" type="submit" title="Verify" onclick="return confirm('Mark this maintenance as verified?')"><i class="fas fa-check-double"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_schedule">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Delete this schedule?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Maintenance Log -->
    <?php if (!empty($maintenanceLogs)): ?>
    <div class="table-card" style="margin-top:16px;">
        <div style="padding:12px 16px;border-bottom:1px solid #eef2f7;font-weight:700;color:#1f2d3d;">
            <i class="fas fa-history"></i> Maintenance Log
        </div>
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Reason</th>
                    <th>By</th>
                    <th>At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenanceLogs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($log['room_number'] ?? '-') . ' ' . ($log['room_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($log['status_from'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($log['status_to'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($log['performed_by_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($log['created_at'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php renderAdminModalStart('scheduleModal', 'Add Maintenance', 'maintenance-modal-content'); ?>
    <form method="POST" id="scheduleForm">
        <input type="hidden" name="action" id="formAction" value="add_schedule">
        <input type="hidden" name="id" id="scheduleId">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-group">
            <label>Room *</label>
            <select name="individual_room_id" id="roomSelect" required>
                <option value="">Select room</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r['id']; ?>" data-status="<?php echo $r['status']; ?>">
                        <?php echo htmlspecialchars($r['room_number'] . ' ' . ($r['room_name'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #6b7280; font-size: 12px;">Room status will be shown when selected</small>
        </div>
        <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" id="title" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" id="description" rows="2"></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Maintenance Type *</label>
                <select name="maintenance_type" id="maintenance_type" required>
                    <option value="repair">Repair</option>
                    <option value="replacement">Replacement</option>
                    <option value="inspection">Inspection</option>
                    <option value="upgrade">Upgrade</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>
            <div class="form-group">
                <label>Priority *</label>
                <select name="priority" id="priority" required>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" id="due_date" required min="<?php echo date('Y-m-d'); ?>">
                <small style="color: #6b7280; font-size: 12px;">Due date cannot be in the past</small>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="status">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="verified">Verified</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="datetime-local" name="start_date" id="start_date">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="datetime-local" name="end_date" id="end_date">
            </div>
        </div>
        <div class="form-group">
            <label>Assigned To</label>
            <select name="assigned_to" id="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Linked Booking</label>
            <select name="linked_booking_id" id="linked_booking_id">
                <option value="">None</option>
                <?php foreach ($bookings as $b): ?>
                    <option value="<?php echo $b['id']; ?>">
                        <?php echo htmlspecialchars($b['booking_reference'] . ' - ' . $b['guest_name'] . ' (' . $b['room_number'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Estimated Duration (minutes)</label>
            <input type="number" name="estimated_duration" id="estimated_duration" value="60" min="5" step="5">
        </div>
        <div class="form-group">
            <label>Actual Duration (minutes)</label>
            <input type="number" name="actual_duration" id="actual_duration" min="1" step="1" placeholder="Fill when completed">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="block_room" id="block_room" value="1" checked> Block room during maintenance</label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_recurring" id="is_recurring" value="1">
                Recurring Task
            </label>
        </div>
        <div id="recurringOptions" style="display: none;">
            <div class="form-group">
                <label>Recurring Pattern</label>
                <select name="recurring_pattern" id="recurring_pattern">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div class="form-group">
                <label>Recurring End Date</label>
                <input type="date" name="recurring_end_date" id="recurring_end_date">
                <small style="color: #6b7280; font-size: 12px;">Leave empty for no end date</small>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" id="notes" rows="2"></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
<?php renderAdminModalEnd(); ?>

<?php renderAdminModalStart('auditLogModal', 'Audit History', 'audit-log-modal-content'); ?>
    <div id="auditLogContent">
        <div style="text-align: center; padding: 20px;">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    </div>
    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top: 16px;">
        <button type="button" class="btn btn-secondary" onclick="closeAuditLogModal()">Close</button>
    </div>
<?php renderAdminModalEnd(); ?>

<?php renderAdminModalScript(); ?>

<script>
    // Set minimum date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const dueDateInput = document.getElementById('due_date');
        if (dueDateInput) {
            dueDateInput.min = today;
        }
        
        // Auto-set urgent priority for emergency type
        const maintenanceTypeSelect = document.getElementById('maintenance_type');
        const prioritySelect = document.getElementById('priority');
        if (maintenanceTypeSelect && prioritySelect) {
            maintenanceTypeSelect.addEventListener('change', function() {
                if (this.value === 'emergency') {
                    prioritySelect.value = 'urgent';
                }
            });
        }
        
        // Toggle recurring options
        const isRecurringCheckbox = document.getElementById('is_recurring');
        const recurringOptions = document.getElementById('recurringOptions');
        if (isRecurringCheckbox && recurringOptions) {
            isRecurringCheckbox.addEventListener('change', function() {
                recurringOptions.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        // Show room status when selecting a room
        const roomSelect = document.getElementById('roomSelect');
        if (roomSelect) {
            roomSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const roomStatus = selectedOption.getAttribute('data-status');
                const small = this.parentElement.querySelector('small');
                if (small && this.value) {
                    small.textContent = 'Room status: ' + (roomStatus || 'unknown');
                }
            });
        }
        
        // Set default dates
        const now = new Date();
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        if (startDateInput) {
            startDateInput.value = now.toISOString().slice(0, 16);
        }
        if (endDateInput) {
            endDateInput.value = tomorrow.toISOString().slice(0, 16);
        }
    });

    function openModal() {
        document.getElementById('scheduleModal-title').textContent = 'Add Maintenance';
        document.getElementById('formAction').value = 'add_schedule';
        document.getElementById('scheduleForm').reset();
        document.getElementById('scheduleId').value = '';
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
        document.getElementById('recurringOptions').style.display = 'none';
        
        // Set default dates
        const now = new Date();
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('start_date').value = now.toISOString().slice(0, 16);
        document.getElementById('end_date').value = tomorrow.toISOString().slice(0, 16);
        
        openAdminModal('scheduleModal');
    }
    
    function closeModal() {
        closeAdminModal('scheduleModal');
    }
    
    function editSchedule(data) {
        document.getElementById('scheduleModal-title').textContent = 'Edit Maintenance';
        document.getElementById('formAction').value = 'update_schedule';
        document.getElementById('scheduleId').value = data.id;
        document.getElementById('roomSelect').value = data.individual_room_id;
        document.getElementById('title').value = data.title;
        document.getElementById('description').value = data.description || '';
        document.getElementById('maintenance_type').value = data.maintenance_type || 'repair';
        document.getElementById('priority').value = data.priority || 'medium';
        document.getElementById('due_date').value = data.due_date || '';
        document.getElementById('status').value = data.status;
        document.getElementById('assigned_to').value = data.assigned_to || '';
        document.getElementById('linked_booking_id').value = data.linked_booking_id || '';
        document.getElementById('estimated_duration').value = data.estimated_duration || 60;
        document.getElementById('actual_duration').value = data.actual_duration || '';
        document.getElementById('block_room').checked = data.block_room == 1;
        
        // Set dates
        if (data.start_date) {
            document.getElementById('start_date').value = toDatetimeLocal(data.start_date);
        }
        if (data.end_date) {
            document.getElementById('end_date').value = toDatetimeLocal(data.end_date);
        }
        
        const isRecurringCheckbox = document.getElementById('is_recurring');
        isRecurringCheckbox.checked = data.is_recurring == 1;
        document.getElementById('recurringOptions').style.display = data.is_recurring == 1 ? 'block' : 'none';
        document.getElementById('recurring_pattern').value = data.recurring_pattern || 'daily';
        document.getElementById('recurring_end_date').value = data.recurring_end_date || '';
        
        openAdminModal('scheduleModal');
    }
    
    function selectAllRooms() {
        const checkboxes = document.querySelectorAll('.room-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
    
    // Update selected room IDs when form is submitted
    const bulkAssignForm = document.getElementById('bulkAssignForm');
    if (bulkAssignForm) {
        bulkAssignForm.addEventListener('submit', function() {
            const selectedIds = Array.from(document.querySelectorAll('.room-checkbox:checked')).map(cb => cb.value);
            document.getElementById('selectedRoomIds').value = JSON.stringify(selectedIds);
            if (selectedIds.length === 0) {
                alert('Please select at least one room');
                return false;
            }
        });
    }
    
    function toDatetimeLocal(value) {
        if (!value) return '';
        return String(value).replace(' ', 'T').slice(0, 16);
    }
    
    bindAdminModal('scheduleModal');
    bindAdminModal('auditLogModal');
    
    function viewAuditLog(maintenanceId, title) {
        document.getElementById('auditLogModal-title').textContent = 'Audit History - ' + title + ' (ID: ' + maintenanceId + ')';
        document.getElementById('auditLogContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        openAdminModal('auditLogModal');
        
        // Fetch audit log via AJAX
        fetch('api/get-maintenance-audit.php?id=' + encodeURIComponent(maintenanceId), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('auditLogContent').innerHTML = '<div style="color: #dc2626; padding: 20px;">' + data.error + '</div>';
                    return;
                }
                
                if (data.logs.length === 0) {
                    document.getElementById('auditLogContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7280;">No audit history available.</div>';
                    return;
                }
                
                let html = '<div style="max-height: 400px; overflow-y: auto;">';
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="background: #f9fafb; position: sticky; top: 0;">';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 12px;">Action</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 12px;">Performed By</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 12px;">When</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb; font-size: 12px;">Changes</th>';
                html += '</tr></thead><tbody>';
                
                data.logs.forEach(log => {
                    const actionBadge = getMaintenanceActionBadge(log.action);
                    const formattedDate = new Date(log.performed_at).toLocaleString();
                    const changes = formatMaintenanceChanges(log);
                    
                    html += '<tr style="border-bottom: 1px solid #e5e7eb;">';
                    html += '<td style="padding: 10px;">' + actionBadge + '</td>';
                    html += '<td style="padding: 10px; font-size: 13px;">' + (log.performed_by_name || 'System') + '</td>';
                    html += '<td style="padding: 10px; font-size: 12px; color: #6b7280;">' + formattedDate + '</td>';
                    html += '<td style="padding: 10px; font-size: 12px;">' + changes + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                document.getElementById('auditLogContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching audit log:', error);
                document.getElementById('auditLogContent').innerHTML = '<div style="color: #dc2626; padding: 20px;">Failed to load audit history.</div>';
            });
    }
    
    function getMaintenanceActionBadge(action) {
        const badges = {
            'created': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #d1fae5; color: #065f46;">Created</span>',
            'updated': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af;">Updated</span>',
            'deleted': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #991b1b;">Deleted</span>',
            'verified': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #e0e7ff; color: #3730a3;">Verified</span>',
            'status_changed': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #92400e;">Status Changed</span>',
            'assigned': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3e8ff; color: #6b21a8;">Assigned</span>',
            'unassigned': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #374151;">Unassigned</span>',
            'priority_changed': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #ffedd5; color: #9a3412;">Priority Changed</span>',
            'type_changed': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #fce7f3; color: #9d174d;">Type Changed</span>',
            'recurring_created': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #ecfdf5; color: #064e3b;">Recurring Created</span>',
        };
        return badges[action] || '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #374151;">' + action + '</span>';
    }
    
    function formatMaintenanceChanges(log) {
        if (!log.changed_fields || log.changed_fields.length === 0) {
            return '<span style="color: #9ca3af;">No field changes</span>';
        }
        
        let changes = '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
        log.changed_fields.forEach(field => {
            changes += '<span style="display: inline; padding: 2px 6px; border-radius: 4px; background: #f3f4f6; color: #4b5563; font-size: 11px;">' + field + '</span>';
        });
        changes += '</div>';
        
        return changes;
    }
    
    function closeAuditLogModal() {
        closeAdminModal('auditLogModal');
    }
</script>

<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>
