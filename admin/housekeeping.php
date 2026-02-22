<?php
/**
 * Housekeeping Management - Admin Panel
 * Enhanced with priority-based assignments, occupied rooms auto-fetch,
 * staff workload tracking, and checkout cleanup automation
 */
require_once 'admin-init.php';
require_once 'includes/admin-modal.php';

if (!hasPermission($user['id'], 'housekeeping')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$error = '';

// Extended status workflow with verification
$validHousekeepingStatuses = ['pending', 'in_progress', 'completed', 'verified', 'blocked'];
$validPriorities = ['high', 'medium', 'low'];
$validAssignmentTypes = ['checkout_cleanup', 'regular_cleaning', 'maintenance', 'deep_clean', 'turn_down'];
$validRecurringPatterns = ['daily', 'weekly', 'monthly'];

// Priority order for sorting (high first)
$priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];

/**
 * Check if a room exists and is active
 */
function housekeepingRoomExists(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE id = ? AND is_active = 1");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Check if a user exists and is active
 */
function housekeepingUserExists(PDO $pdo, ?int $userId): bool {
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
function housekeepingTableExists(PDO $pdo, string $table): bool {
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
 * Check if a column exists in the housekeeping_assignments table
 * Caches results for performance
 */
function housekeepingColumnExists(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'housekeeping_assignments' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    $cache[$column] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$column];
}

/**
 * Set room status and log the change
 */
function housekeepingSetRoomStatus(PDO $pdo, int $roomId, string $newStatus, ?string $reason, ?int $performedBy): void {
    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $oldStatus = (string)$statusStmt->fetchColumn();
    if ($oldStatus === '' || $oldStatus === $newStatus) {
        return;
    }

    $updateStmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $roomId]);

    if (housekeepingTableExists($pdo, 'room_maintenance_log')) {
        $logStmt = $pdo->prepare("INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by) VALUES (?, ?, ?, ?, ?)");
        $logStmt->execute([$roomId, $oldStatus, $newStatus, $reason, $performedBy]);
    }
}

/**
 * Validate due date - cannot be in the past
 */
function validateDueDate(string $dueDate): bool {
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
 * Get all occupied rooms that need housekeeping
 */
function getOccupiedRooms(PDO $pdo): array {
    $sql = "
        SELECT DISTINCT 
            ir.id,
            ir.room_number,
            ir.room_name,
            ir.status as room_status,
            ir.housekeeping_status,
            b.id as booking_id,
            b.guest_name,
            b.check_out_date,
            b.status as booking_status,
            CASE 
                WHEN b.check_out_date = CURDATE() THEN 'checkout_today'
                WHEN b.check_out_date < CURDATE() THEN 'overdue_checkout'
                ELSE 'occupied'
            END as occupancy_type
        FROM individual_rooms ir
        INNER JOIN bookings b ON b.individual_room_id = ir.id
        WHERE b.status = 'checked-in'
          AND b.check_in_date <= CURDATE()
          AND b.check_out_date >= CURDATE()
          AND ir.is_active = 1
        ORDER BY 
            CASE 
                WHEN b.check_out_date = CURDATE() THEN 1
                ELSE 2
            END,
            ir.room_number ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get rooms that need checkout cleanup
 * Backward compatible: works with or without migration 004 columns
 */
function getCheckoutCleanupRooms(PDO $pdo): array {
    $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
    $hasLinkedBookingId = housekeepingColumnExists($pdo, 'linked_booking_id');
    
    // Build the NOT EXISTS clause conditionally based on available columns
    $notExistsConditions = [
        "ha.individual_room_id = ir.id",
        "ha.status IN ('pending', 'in_progress')"
    ];
    
    if ($hasAssignmentType) {
        $notExistsConditions[] = "ha.assignment_type = 'checkout_cleanup'";
    }
    if ($hasLinkedBookingId) {
        $notExistsConditions[] = "ha.linked_booking_id = b.id";
    }
    
    $notExistsClause = implode(' AND ', $notExistsConditions);
    
    $sql = "
        SELECT DISTINCT
            ir.id,
            ir.room_number,
            ir.room_name,
            b.id as booking_id,
            b.guest_name,
            b.check_out_date
        FROM individual_rooms ir
        INNER JOIN bookings b ON b.individual_room_id = ir.id
        WHERE b.status IN ('checked-out', 'checked-in')
          AND b.check_out_date <= CURDATE()
          AND ir.is_active = 1
          AND NOT EXISTS (
              SELECT 1 FROM housekeeping_assignments ha
              WHERE {$notExistsClause}
          )
        ORDER BY b.check_out_date ASC, ir.room_number ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get staff workload (number of pending/in-progress assignments per staff)
 * Backward compatible: works with or without migration 004 columns
 */
function getStaffWorkload(PDO $pdo): array {
    $hasPriority = housekeepingColumnExists($pdo, 'priority');
    
    // Build the high_priority_pending conditionally
    $highPriorityCase = $hasPriority
        ? "COUNT(CASE WHEN ha.status = 'pending' AND ha.priority = 'high' THEN 1 END) as high_priority_pending"
        : "0 as high_priority_pending";
    
    $sql = "
        SELECT
            u.id,
            u.username,
            COUNT(CASE WHEN ha.status IN ('pending', 'in_progress') THEN 1 END) as active_tasks,
            {$highPriorityCase},
            COUNT(CASE WHEN ha.status = 'completed' AND DATE(ha.completed_at) = CURDATE() THEN 1 END) as completed_today
        FROM admin_users u
        LEFT JOIN housekeeping_assignments ha ON ha.assigned_to = u.id
            AND (ha.status IN ('pending', 'in_progress') OR (ha.status = 'completed' AND DATE(ha.completed_at) = CURDATE()))
        WHERE u.is_active = 1
        GROUP BY u.id, u.username
        ORDER BY active_tasks DESC, u.username ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Auto-create checkout cleanup assignments
 * Backward compatible: works with or without migration 004 columns
 */
function autoCreateCheckoutCleanup(PDO $pdo, int $performedBy): int {
    $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
    $hasPriority = housekeepingColumnExists($pdo, 'priority');
    $hasAutoCreated = housekeepingColumnExists($pdo, 'auto_created');
    $hasLinkedBookingId = housekeepingColumnExists($pdo, 'linked_booking_id');
    
    // If we don't have the required columns for checkout cleanup, return early
    if (!$hasAssignmentType || !$hasLinkedBookingId) {
        return 0;
    }
    
    $checkoutRooms = getCheckoutCleanupRooms($pdo);
    $created = 0;
    
    foreach ($checkoutRooms as $room) {
        // Check if assignment already exists
        $checkConditions = [
            "individual_room_id = ?",
            "status IN ('pending', 'in_progress')"
        ];
        $checkParams = [$room['id']];
        
        if ($hasAssignmentType) {
            $checkConditions[] = "assignment_type = 'checkout_cleanup'";
        }
        if ($hasLinkedBookingId) {
            $checkConditions[] = "linked_booking_id = ?";
            $checkParams[] = $room['booking_id'];
        }
        
        $checkSql = "SELECT id FROM housekeeping_assignments WHERE " . implode(' AND ', $checkConditions);
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($checkParams);
        if ($checkStmt->fetch()) {
            continue;
        }
        
        // Build INSERT columns and values based on available columns
        $insertColumns = ['individual_room_id', 'status', 'due_date', 'assigned_to', 'created_by', 'notes'];
        $insertValues = ['?', '?', '?', '?', '?', '?'];
        $insertParams = [$room['id'], 'pending', date('Y-m-d'), null, $performedBy, 'Checkout cleanup required'];
        
        if ($hasAssignmentType) {
            $insertColumns[] = 'assignment_type';
            $insertValues[] = '?';
            $insertParams[] = 'checkout_cleanup';
        }
        if ($hasPriority) {
            $insertColumns[] = 'priority';
            $insertValues[] = '?';
            $insertParams[] = 'high';
        }
        if ($hasAutoCreated) {
            $insertColumns[] = 'auto_created';
            $insertValues[] = '?';
            $insertParams[] = 1;
        }
        if ($hasLinkedBookingId) {
            $insertColumns[] = 'linked_booking_id';
            $insertValues[] = '?';
            $insertParams[] = $room['booking_id'];
        }
        
        $insertSql = "INSERT INTO housekeeping_assignments (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute($insertParams);
        $newAssignmentId = (int)$pdo->lastInsertId();
        $created++;
        
        // Log audit trail for auto-created checkout cleanup
        $newData = [
            'individual_room_id' => $room['id'],
            'status' => 'pending',
            'due_date' => date('Y-m-d'),
            'assigned_to' => null,
            'created_by' => $performedBy,
            'notes' => 'Checkout cleanup required',
        ];
        if ($hasAssignmentType) $newData['assignment_type'] = 'checkout_cleanup';
        if ($hasPriority) $newData['priority'] = 'high';
        if ($hasAutoCreated) $newData['auto_created'] = 1;
        if ($hasLinkedBookingId) $newData['linked_booking_id'] = $room['booking_id'];
        
        logHousekeepingAction($newAssignmentId, 'created', null, $newData, $performedBy);
        
        // Update room status
        reconcileIndividualRoomHousekeeping($pdo, $room['id'], $performedBy);
    }
    
    return $created;
}

/**
 * Reconcile individual room housekeeping status
 * Backward compatible: works with or without migration 004 columns
 */
function reconcileIndividualRoomHousekeeping(PDO $pdo, int $roomId, ?int $performedBy = null): void {
    $hasPriority = housekeepingColumnExists($pdo, 'priority');
    
    // Build SELECT columns based on available columns
    $selectColumns = ['status', 'notes'];
    if ($hasPriority) {
        $selectColumns[] = 'priority';
    }
    
    // Build ORDER BY clause based on available columns
    $orderByClauses = [
        "CASE status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 WHEN 'blocked' THEN 3 ELSE 99 END"
    ];
    
    if ($hasPriority) {
        array_unshift($orderByClauses, "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END");
    }
    
    $orderByClauses[] = "due_date ASC";
    $orderByClauses[] = "id DESC";
    
    $sql = "
        SELECT " . implode(', ', $selectColumns) . "
        FROM housekeeping_assignments
        WHERE individual_room_id = ?
          AND status IN ('pending','in_progress','blocked')
        ORDER BY " . implode(', ', $orderByClauses) . "
        LIMIT 1
    ";
    $openStmt = $pdo->prepare($sql);
    $openStmt->execute([$roomId]);
    $open = $openStmt->fetch(PDO::FETCH_ASSOC);

    if ($open) {
        $mapped = in_array($open['status'], ['pending', 'in_progress'], true) ? $open['status'] : 'pending';
        $notes = (string)($open['notes'] ?? '');
        if (($open['status'] ?? '') === 'blocked') {
            $notes = trim('Blocked assignment. ' . $notes);
        }

        $pdo->prepare("UPDATE individual_rooms SET housekeeping_status = ?, housekeeping_notes = ? WHERE id = ?")
            ->execute([$mapped, $notes !== '' ? $notes : null, $roomId]);

        $roomStatusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
        $roomStatusStmt->execute([$roomId]);
        $roomStatus = (string)$roomStatusStmt->fetchColumn();
        if ($roomStatus === 'available') {
            housekeepingSetRoomStatus($pdo, $roomId, 'cleaning', 'Housekeeping assignment active', $performedBy);
        }
        return;
    }

    $pdo->prepare("UPDATE individual_rooms SET housekeeping_status = 'completed', housekeeping_notes = NULL, last_cleaned_at = COALESCE(last_cleaned_at, NOW()) WHERE id = ?")
        ->execute([$roomId]);

    $roomStatusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $roomStatusStmt->execute([$roomId]);
    $roomStatus = (string)$roomStatusStmt->fetchColumn();
    if ($roomStatus === 'cleaning') {
        housekeepingSetRoomStatus($pdo, $roomId, 'available', 'Housekeeping assignment cleared', $performedBy);
    }
}

/**
 * Create recurring housekeeping assignments
 * Backward compatible: works with or without migration 004 columns
 */
function createRecurringAssignments(PDO $pdo, int $performedBy): int {
    $hasIsRecurring = housekeepingColumnExists($pdo, 'is_recurring');
    $hasRecurringPattern = housekeepingColumnExists($pdo, 'recurring_pattern');
    $hasRecurringEndDate = housekeepingColumnExists($pdo, 'recurring_end_date');
    $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
    $hasPriority = housekeepingColumnExists($pdo, 'priority');
    $hasEstimatedDuration = housekeepingColumnExists($pdo, 'estimated_duration');
    
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
    
    // Build SELECT columns based on available columns
    $selectColumns = ['*'];
    
    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM housekeeping_assignments WHERE " . implode(' AND ', $whereConditions);
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
                // Create if last assignment was before today
                $shouldCreate = (date('Y-m-d', strtotime($lastCreated)) < $today);
                break;
            case 'weekly':
                // Create if last assignment was more than 7 days ago
                $shouldCreate = (strtotime($lastCreated) < strtotime('-7 days'));
                break;
            case 'monthly':
                // Create if last assignment was more than 30 days ago
                $shouldCreate = (strtotime($lastCreated) < strtotime('-30 days'));
                break;
        }
        
        if ($shouldCreate) {
            // Build INSERT columns and values based on available columns
            $insertColumns = ['individual_room_id', 'status', 'due_date', 'assigned_to', 'created_by', 'notes'];
            $insertValues = ['?', '?', '?', '?', '?', '?'];
            $insertParams = [
                $assignment['individual_room_id'],
                'pending',
                $today,
                $assignment['assigned_to'],
                $performedBy,
                $assignment['notes']
            ];
            
            if ($hasAssignmentType) {
                $insertColumns[] = 'assignment_type';
                $insertValues[] = '?';
                $insertParams[] = $assignment['assignment_type'] ?? 'regular_cleaning';
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
                $insertParams[] = $assignment['estimated_duration'] ?? 30;
            }
            
            $insertSql = "INSERT INTO housekeeping_assignments (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $newStmt = $pdo->prepare($insertSql);
            $newStmt->execute($insertParams);
            $newAssignmentId = (int)$pdo->lastInsertId();
            $created++;
            
            // Log audit trail for recurring assignment creation
            $newData = [
                'individual_room_id' => $assignment['individual_room_id'],
                'status' => 'pending',
                'due_date' => $today,
                'assigned_to' => $assignment['assigned_to'],
                'created_by' => $performedBy,
                'notes' => $assignment['notes'],
            ];
            if ($hasAssignmentType) $newData['assignment_type'] = $assignment['assignment_type'] ?? 'regular_cleaning';
            if ($hasPriority) $newData['priority'] = $assignment['priority'] ?? 'medium';
            if ($hasIsRecurring) $newData['is_recurring'] = 1;
            if ($hasRecurringPattern) $newData['recurring_pattern'] = $assignment['recurring_pattern'];
            if ($hasRecurringEndDate) $newData['recurring_end_date'] = $assignment['recurring_end_date'];
            if ($hasEstimatedDuration) $newData['estimated_duration'] = $assignment['estimated_duration'] ?? 30;
            
            logHousekeepingAction($newAssignmentId, 'recurring_created', null, $newData, $performedBy);
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
        
        if ($action === 'add_assignment') {
            $room_id = (int)($_POST['individual_room_id'] ?? 0);
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $assignment_type = $_POST['assignment_type'] ?? 'regular_cleaning';
            $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
            $recurring_pattern = $is_recurring ? ($_POST['recurring_pattern'] ?? null) : null;
            $recurring_end_date = $is_recurring ? ($_POST['recurring_end_date'] ?? null) : null;
            $estimated_duration = (int)($_POST['estimated_duration'] ?? 30);

            // Check which columns exist for backward compatibility
            $hasPriority = housekeepingColumnExists($pdo, 'priority');
            $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
            $hasIsRecurring = housekeepingColumnExists($pdo, 'is_recurring');
            $hasRecurringPattern = housekeepingColumnExists($pdo, 'recurring_pattern');
            $hasRecurringEndDate = housekeepingColumnExists($pdo, 'recurring_end_date');
            $hasEstimatedDuration = housekeepingColumnExists($pdo, 'estimated_duration');
            $hasVerifiedAt = housekeepingColumnExists($pdo, 'verified_at');

            // Validation
            if (!$room_id) {
                $error = 'Room is required.';
            } elseif (!$due_date) {
                $error = 'Due date is required.';
            } elseif (!validateDueDate($due_date)) {
                $error = 'Due date cannot be in the past. Please select today or a future date.';
            } elseif (!in_array($status, $validHousekeepingStatuses, true)) {
                $error = 'Invalid housekeeping status.';
            } elseif ($hasPriority && !in_array($priority, $validPriorities, true)) {
                $error = 'Invalid priority level.';
            } elseif ($hasAssignmentType && !in_array($assignment_type, $validAssignmentTypes, true)) {
                $error = 'Invalid assignment type.';
            } elseif ($hasIsRecurring && $is_recurring && !in_array($recurring_pattern, $validRecurringPatterns, true)) {
                $error = 'Invalid recurring pattern.';
            } elseif (!housekeepingRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!housekeepingUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($due_date) === false) {
                $error = 'Invalid due date format.';
            } else {
                $pdo->beginTransaction();
                $completedAt = in_array($status, ['completed', 'verified'], true) ? date('Y-m-d H:i:s') : null;
                $verifiedAt = ($hasVerifiedAt && $status === 'verified') ? date('Y-m-d H:i:s') : null;
                
                // Build INSERT columns and values based on available columns
                $insertColumns = ['individual_room_id', 'status', 'due_date', 'assigned_to', 'created_by', 'notes', 'completed_at'];
                $insertValues = ['?', '?', '?', '?', '?', '?', '?'];
                $insertParams = [$room_id, $status, $due_date, $assigned_to, $user['id'] ?? null, $notes, $completedAt];
                
                if ($hasPriority) {
                    $insertColumns[] = 'priority';
                    $insertValues[] = '?';
                    $insertParams[] = $priority;
                }
                if ($hasAssignmentType) {
                    $insertColumns[] = 'assignment_type';
                    $insertValues[] = '?';
                    $insertParams[] = $assignment_type;
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
                if ($hasVerifiedAt) {
                    $insertColumns[] = 'verified_at';
                    $insertValues[] = '?';
                    $insertParams[] = $verifiedAt;
                }
                
                $insertSql = "INSERT INTO housekeeping_assignments (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertParams);
                $newAssignmentId = (int)$pdo->lastInsertId();

                reconcileIndividualRoomHousekeeping($pdo, $room_id, $user['id'] ?? null);
                
                // Log audit trail
                $newData = [
                    'individual_room_id' => $room_id,
                    'status' => $status,
                    'due_date' => $due_date,
                    'assigned_to' => $assigned_to,
                    'notes' => $notes,
                    'completed_at' => $completedAt,
                ];
                if ($hasPriority) $newData['priority'] = $priority;
                if ($hasAssignmentType) $newData['assignment_type'] = $assignment_type;
                if ($hasIsRecurring) $newData['is_recurring'] = $is_recurring;
                if ($hasRecurringPattern) $newData['recurring_pattern'] = $recurring_pattern;
                if ($hasRecurringEndDate) $newData['recurring_end_date'] = $recurring_end_date;
                if ($hasEstimatedDuration) $newData['estimated_duration'] = $estimated_duration;
                if ($hasVerifiedAt) $newData['verified_at'] = $verifiedAt;
                
                logHousekeepingAction($newAssignmentId, 'created', null, $newData, $user['id'] ?? null, $user['username'] ?? null);
                
                $pdo->commit();
                $message = 'Assignment created successfully.';
            }
        } elseif ($action === 'update_assignment') {
            $id = (int)($_POST['id'] ?? 0);
            $room_id = (int)($_POST['individual_room_id'] ?? 0);
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $assignment_type = $_POST['assignment_type'] ?? 'regular_cleaning';
            $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
            $recurring_pattern = $is_recurring ? ($_POST['recurring_pattern'] ?? null) : null;
            $recurring_end_date = $is_recurring ? ($_POST['recurring_end_date'] ?? null) : null;
            $estimated_duration = (int)($_POST['estimated_duration'] ?? 30);
            $actual_duration = !empty($_POST['actual_duration']) ? (int)$_POST['actual_duration'] : null;

            // Check which columns exist for backward compatibility
            $hasPriority = housekeepingColumnExists($pdo, 'priority');
            $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
            $hasIsRecurring = housekeepingColumnExists($pdo, 'is_recurring');
            $hasRecurringPattern = housekeepingColumnExists($pdo, 'recurring_pattern');
            $hasRecurringEndDate = housekeepingColumnExists($pdo, 'recurring_end_date');
            $hasEstimatedDuration = housekeepingColumnExists($pdo, 'estimated_duration');
            $hasActualDuration = housekeepingColumnExists($pdo, 'actual_duration');
            $hasVerifiedBy = housekeepingColumnExists($pdo, 'verified_by');
            $hasVerifiedAt = housekeepingColumnExists($pdo, 'verified_at');

            // Validation
            if (!$id || !$room_id || !$due_date) {
                $error = 'Room and due date are required.';
            } elseif (!validateDueDate($due_date)) {
                $error = 'Due date cannot be in the past. Please select today or a future date.';
            } elseif (!in_array($status, $validHousekeepingStatuses, true)) {
                $error = 'Invalid housekeeping status.';
            } elseif ($hasPriority && !in_array($priority, $validPriorities, true)) {
                $error = 'Invalid priority level.';
            } elseif ($hasAssignmentType && !in_array($assignment_type, $validAssignmentTypes, true)) {
                $error = 'Invalid assignment type.';
            } elseif ($hasIsRecurring && $is_recurring && !in_array($recurring_pattern, $validRecurringPatterns, true)) {
                $error = 'Invalid recurring pattern.';
            } elseif (!housekeepingRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!housekeepingUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($due_date) === false) {
                $error = 'Invalid due date format.';
            } else {
                $pdo->beginTransaction();
                $existsStmt = $pdo->prepare("SELECT id, individual_room_id, status FROM housekeeping_assignments WHERE id = ?");
                $existsStmt->execute([$id]);
                $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new RuntimeException('Assignment does not exist.');
                }

                // Auto-set verified_by when status changes to verified
                $verifiedBy = null;
                $verifiedAt = null;
                if ($hasVerifiedBy && $hasVerifiedAt && $status === 'verified' && $existing['status'] !== 'verified') {
                    $verifiedBy = $user['id'] ?? null;
                    $verifiedAt = date('Y-m-d H:i:s');
                } elseif ($status !== 'verified') {
                    $verifiedAt = null;
                }

                $completedAt = in_array($status, ['completed', 'verified'], true) ? date('Y-m-d H:i:s') : null;
                if ($existing['status'] === 'completed' && $status !== 'completed' && $status !== 'verified') {
                    $completedAt = null;
                }

                // Build UPDATE SET clause based on available columns
                $setColumns = ['individual_room_id=?', 'status=?', 'due_date=?', 'assigned_to=?', 'notes=?', 'completed_at=?'];
                $updateParams = [$room_id, $status, $due_date, $assigned_to, $notes, $completedAt];
                
                if ($hasPriority) {
                    $setColumns[] = 'priority=?';
                    $updateParams[] = $priority;
                }
                if ($hasAssignmentType) {
                    $setColumns[] = 'assignment_type=?';
                    $updateParams[] = $assignment_type;
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
                if ($hasVerifiedBy) {
                    $setColumns[] = 'verified_by=?';
                    $updateParams[] = $verifiedBy;
                }
                if ($hasVerifiedAt) {
                    $setColumns[] = 'verified_at=?';
                    $updateParams[] = $verifiedAt;
                }
                
                $updateParams[] = $id; // WHERE id=?

                $updateSql = "UPDATE housekeeping_assignments SET " . implode(', ', $setColumns) . " WHERE id=?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($updateParams);

                // Get updated data for audit log
                $updatedStmt = $pdo->prepare("SELECT * FROM housekeeping_assignments WHERE id = ?");
                $updatedStmt->execute([$id]);
                $newData = $updatedStmt->fetch(PDO::FETCH_ASSOC);
                
                // Determine action type
                $action = 'updated';
                if ($existing['status'] !== $status) {
                    $action = 'status_changed';
                }
                if (($existing['assigned_to'] ?? null) != $assigned_to) {
                    $action = $assigned_to ? 'assigned' : 'unassigned';
                }
                if ($hasPriority && ($existing['priority'] ?? null) !== $priority) {
                    $action = 'priority_changed';
                }
                
                logHousekeepingAction($id, $action, $existing, $newData, $user['id'] ?? null, $user['username'] ?? null);

                reconcileIndividualRoomHousekeeping($pdo, $room_id, $user['id'] ?? null);
                if ((int)$existing['individual_room_id'] !== $room_id) {
                    reconcileIndividualRoomHousekeeping($pdo, (int)$existing['individual_room_id'], $user['id'] ?? null);
                }
                $pdo->commit();
                $message = 'Assignment updated successfully.';
            }
        } elseif ($action === 'delete_assignment') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid assignment selected.';
            } else {
                $pdo->beginTransaction();
                $rowStmt = $pdo->prepare("SELECT individual_room_id FROM housekeeping_assignments WHERE id = ?");
                $rowStmt->execute([$id]);
                $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Assignment not found.');
                }

                // Get assignment data before deletion for audit log
                $dataStmt = $pdo->prepare("SELECT * FROM housekeeping_assignments WHERE id = ?");
                $dataStmt->execute([$id]);
                $deletedData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                
                $pdo->prepare("DELETE FROM housekeeping_assignments WHERE id = ?")->execute([$id]);

                reconcileIndividualRoomHousekeeping($pdo, (int)$row['individual_room_id'], $user['id'] ?? null);
                
                // Log audit trail
                logHousekeepingAction($id, 'deleted', $deletedData, null, $user['id'] ?? null, $user['username'] ?? null);

                $pdo->commit();
                $message = 'Assignment deleted successfully.';
            }
        } elseif ($action === 'auto_create_checkout') {
            $pdo->beginTransaction();
            $created = autoCreateCheckoutCleanup($pdo, $user['id'] ?? null);
            $pdo->commit();
            $message = "Auto-created {$created} checkout cleanup assignments.";
        } elseif ($action === 'bulk_assign_occupied') {
            $room_ids = $_POST['room_ids'] ?? [];
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $priority = $_POST['priority'] ?? 'medium';
            
            // Check which columns exist for backward compatibility
            $hasAssignmentType = housekeepingColumnExists($pdo, 'assignment_type');
            $hasPriority = housekeepingColumnExists($pdo, 'priority');
            
            if (empty($room_ids)) {
                $error = 'No rooms selected.';
            } else {
                $pdo->beginTransaction();
                $created = 0;
                $today = date('Y-m-d');
                
                foreach ($room_ids as $room_id) {
                    $room_id = (int)$room_id;
                    if (!housekeepingRoomExists($pdo, $room_id)) {
                        continue;
                    }
                    
                    // Check if pending assignment already exists
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM housekeeping_assignments
                        WHERE individual_room_id = ? AND status IN ('pending', 'in_progress')
                    ");
                    $checkStmt->execute([$room_id]);
                    if ($checkStmt->fetch()) {
                        continue;
                    }
                    
                    // Build INSERT columns and values based on available columns
                    $insertColumns = ['individual_room_id', 'status', 'due_date', 'assigned_to', 'created_by'];
                    $insertValues = ['?', '?', '?', '?', '?'];
                    $insertParams = [$room_id, 'pending', $today, $assigned_to, $user['id'] ?? null];
                    
                    if ($hasAssignmentType) {
                        $insertColumns[] = 'assignment_type';
                        $insertValues[] = '?';
                        $insertParams[] = 'regular_cleaning';
                    }
                    if ($hasPriority) {
                        $insertColumns[] = 'priority';
                        $insertValues[] = '?';
                        $insertParams[] = $priority;
                    }
                    
                    $insertSql = "INSERT INTO housekeeping_assignments (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
                    $stmt = $pdo->prepare($insertSql);
                    $stmt->execute($insertParams);
                    $newAssignmentId = (int)$pdo->lastInsertId();
                    $created++;
                    
                    // Log audit trail for bulk created assignment
                    $newData = [
                        'individual_room_id' => $room_id,
                        'status' => 'pending',
                        'due_date' => $today,
                        'assigned_to' => $assigned_to,
                        'created_by' => $user['id'] ?? null,
                    ];
                    if ($hasAssignmentType) $newData['assignment_type'] = 'regular_cleaning';
                    if ($hasPriority) $newData['priority'] = $priority;
                    
                    logHousekeepingAction($newAssignmentId, 'created', null, $newData, $user['id'] ?? null, $user['username'] ?? null);
                    
                    reconcileIndividualRoomHousekeeping($pdo, $room_id, $user['id'] ?? null);
                }
                
                $pdo->commit();
                $message = "Bulk assigned {$created} rooms for housekeeping.";
            }
        } elseif ($action === 'verify_assignment') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid assignment selected.';
            } else {
                $hasVerifiedBy = housekeepingColumnExists($pdo, 'verified_by');
                $hasVerifiedAt = housekeepingColumnExists($pdo, 'verified_at');
                
                // If we don't have the required columns for verification, show error
                if (!$hasVerifiedBy || !$hasVerifiedAt) {
                    $error = 'Verification feature requires database migration 004. Please contact administrator.';
                } else {
                    $pdo->beginTransaction();
                    // Get assignment data before verification for audit log
                    $dataStmt = $pdo->prepare("SELECT * FROM housekeeping_assignments WHERE id = ?");
                    $dataStmt->execute([$id]);
                    $beforeData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("
                        UPDATE housekeeping_assignments
                        SET status = 'verified', verified_by = ?, verified_at = NOW()
                        WHERE id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$user['id'] ?? null, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Get updated data for audit log
                        $dataStmt->execute([$id]);
                        $afterData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log audit trail
                        logHousekeepingAction($id, 'verified', $beforeData, $afterData, $user['id'] ?? null, $user['username'] ?? null);
                        
                        $message = 'Assignment verified successfully.';
                    } else {
                        $error = 'Assignment not found or not in completed status.';
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
    $roomRows = $pdo->query("SELECT DISTINCT individual_room_id FROM housekeeping_assignments")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roomRows as $roomId) {
        reconcileIndividualRoomHousekeeping($pdo, (int)$roomId, $user['id'] ?? null);
    }
} catch (Throwable $syncError) {
    error_log('Housekeeping reconciliation warning: ' . $syncError->getMessage());
}

// Get data for the page
$roomsStmt = $pdo->query("SELECT id, room_number, room_name, status, housekeeping_status FROM individual_rooms WHERE is_active = 1 ORDER BY room_number ASC");
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query("SELECT id, username FROM admin_users WHERE is_active = 1 ORDER BY username ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get occupied rooms for quick assignment
$occupiedRooms = getOccupiedRooms($pdo);

// Get rooms needing checkout cleanup
$checkoutCleanupRooms = getCheckoutCleanupRooms($pdo);

// Get staff workload
$staffWorkload = getStaffWorkload($pdo);

// Get all assignments with enhanced sorting
// Backward compatible: works with or without migration 004 columns
$hasPriority = housekeepingColumnExists($pdo, 'priority');
$hasVerifiedBy = housekeepingColumnExists($pdo, 'verified_by');

// Build ORDER BY clause based on available columns
$orderByClauses = [
    "CASE ha.status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 WHEN 'verified' THEN 4 WHEN 'blocked' THEN 5 ELSE 99 END"
];

if ($hasPriority) {
    $orderByClauses[] = "CASE ha.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END";
}

$orderByClauses[] = "ha.due_date ASC";
$orderByClauses[] = "ha.created_at DESC";

$assignmentsStmt = $pdo->query("
    SELECT ha.*, ir.room_number, ir.room_name, u.username as assigned_to_name, creator.username as created_by_name" .
    ($hasVerifiedBy ? ", verifier.username as verified_by_name" : "") . "
    FROM housekeeping_assignments ha
    LEFT JOIN individual_rooms ir ON ha.individual_room_id = ir.id
    LEFT JOIN admin_users u ON ha.assigned_to = u.id
    LEFT JOIN admin_users creator ON ha.created_by = creator.id" .
    ($hasVerifiedBy ? "
    LEFT JOIN admin_users verifier ON ha.verified_by = verifier.id" : "") . "
    ORDER BY " . implode(', ', $orderByClauses)
);
$assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
// Backward compatible: works with or without migration 004 columns
$stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'verified' => 0,
    'blocked' => 0,
    'high_priority' => 0,
    'checkout_cleanup' => 0,
];
foreach ($assignments as $a) {
    $stats[$a['status']] = ($stats[$a['status']] ?? 0) + 1;
    if ($hasPriority && $a['priority'] === 'high') $stats['high_priority']++;
    if ($a['assignment_type'] ?? null === 'checkout_cleanup') $stats['checkout_cleanup']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housekeeping - Admin Panel</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/housekeeping.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hk-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .hk-stat-card { background: white; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .hk-stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--navy, #1f2d3d); }
        .hk-stat-card .stat-label { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .hk-stat-card.pending { border-left: 4px solid #3747a5; }
        .hk-stat-card.in_progress { border-left: 4px solid #a46200; }
        .hk-stat-card.completed { border-left: 4px solid #1f7a45; }
        .hk-stat-card.high_priority { border-left: 4px solid #dc2626; }
        .hk-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .hk-section h3 { margin: 0 0 16px; font-size: 18px; color: var(--navy, #1f2d3d); display: flex; align-items: center; gap: 8px; }
        .occupied-rooms-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
        .occupied-room-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fafafa; }
        .occupied-room-card.checkout-today { border-color: #f59e0b; background: #fffbeb; }
        .occupied-room-card .room-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .occupied-room-card .room-number { font-weight: 600; font-size: 16px; }
        .occupied-room-card .occupancy-badge { font-size: 11px; padding: 2px 8px; border-radius: 99px; background: #e5e7eb; }
        .occupied-room-card.checkout-today .occupancy-badge { background: #fcd34d; color: #92400e; }
        .occupied-room-card .guest-info { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
        .occupied-room-card .date-info { font-size: 12px; color: #9ca3af; }
        .staff-workload-table { width: 100%; border-collapse: collapse; }
        .staff-workload-table th, .staff-workload-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .staff-workload-table th { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600; }
        .priority-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .priority-badge.high { background: #fee2e2; color: #dc2626; }
        .priority-badge.medium { background: #fef3c7; color: #d97706; }
        .priority-badge.low { background: #e0e7ff; color: #4f46e5; }
        .type-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #4b5563; }
        .type-badge.checkout_cleanup { background: #fee2e2; color: #dc2626; }
        .type-badge.deep_clean { background: #ede9fe; color: #7c3aed; }
        .type-badge.turn_down { background: #dbeafe; color: #2563eb; }
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
        <h2><i class="fas fa-broom"></i> Housekeeping Management</h2>
        <div style="display: flex; gap: 10px;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="auto_create_checkout">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button class="btn btn-warning" type="submit" onclick="return confirm('Auto-create checkout cleanup assignments for all rooms that need it?')">
                    <i class="fas fa-magic"></i> Auto-Create Checkout Cleanup
                </button>
            </form>
            <button class="btn btn-primary" type="button" onclick="openModal()"><i class="fas fa-plus"></i> Add Assignment</button>
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
    <div class="hk-dashboard">
        <div class="hk-stat-card pending">
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label"><i class="fas fa-clock"></i> Pending Tasks</div>
        </div>
        <div class="hk-stat-card in_progress">
            <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label"><i class="fas fa-spinner"></i> In Progress</div>
        </div>
        <div class="hk-stat-card completed">
            <div class="stat-value"><?php echo $stats['completed']; ?></div>
            <div class="stat-label"><i class="fas fa-check"></i> Completed Today</div>
        </div>
        <div class="hk-stat-card high_priority">
            <div class="stat-value"><?php echo $stats['high_priority']; ?></div>
            <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> High Priority</div>
        </div>
        <div class="hk-stat-card">
            <div class="stat-value"><?php echo $stats['checkout_cleanup']; ?></div>
            <div class="stat-label"><i class="fas fa-door-open"></i> Checkout Cleanup</div>
        </div>
    </div>

    <!-- Occupied Rooms Section -->
    <?php if (!empty($occupiedRooms)): ?>
    <div class="hk-section">
        <h3><i class="fas fa-bed"></i> Occupied Rooms (<?php echo count($occupiedRooms); ?>)</h3>
        <form method="POST" id="bulkAssignForm">
            <input type="hidden" name="action" value="bulk_assign_occupied">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="room_ids" id="selectedRoomIds">
            <div class="bulk-actions">
                <select name="assigned_to" id="bulkAssignTo" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db;">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="priority" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db;">
                    <option value="medium">Medium Priority</option>
                    <option value="high">High Priority</option>
                    <option value="low">Low Priority</option>
                </select>
                <button type="button" class="btn-quick" onclick="selectAllOccupied()">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;">
                    <i class="fas fa-plus"></i> Assign Selected
                </button>
            </div>
            <div class="occupied-rooms-list">
                <?php foreach ($occupiedRooms as $room): ?>
                <div class="occupied-room-card <?php echo $room['occupancy_type'] === 'checkout_today' ? 'checkout-today' : ''; ?>" data-room-id="<?php echo $room['id']; ?>">
                    <div class="room-header">
                        <span class="room-number"><?php echo htmlspecialchars($room['room_number'] . ' ' . ($room['room_name'] ?? '')); ?></span>
                        <span class="occupancy-badge"><?php echo $room['occupancy_type'] === 'checkout_today' ? 'Checkout Today' : 'Occupied'; ?></span>
                    </div>
                    <div class="guest-info"><i class="fas fa-user"></i> <?php echo htmlspecialchars($room['guest_name'] ?? 'Guest'); ?></div>
                    <div class="date-info"><i class="fas fa-calendar"></i> Checkout: <?php echo htmlspecialchars($room['check_out_date']); ?></div>
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
    <div class="hk-section">
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

    <!-- Assignments Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Assigned To</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($assignments)): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">No housekeeping assignments.</td></tr>
            <?php else: ?>
                <?php foreach ($assignments as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['room_number'] . ' ' . ($row['room_name'] ?? '')); ?></td>
                    <td><span class="type-badge <?php echo $row['assignment_type']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['assignment_type'])); ?></span></td>
                    <td><span class="priority-badge <?php echo $row['priority']; ?>"><?php echo ucfirst($row['priority']); ?></span></td>
                    <td><span class="status-pill <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars(mb_strimwidth($row['notes'] ?? '', 0, 30, '...')); ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" type="button" onclick='editAssignment(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-secondary btn-sm" type="button" onclick='viewAuditLog(<?php echo $row['id']; ?>, "<?php echo htmlspecialchars($row['room_number'] . ' ' . ($row['room_name'] ?? '')); ?>")' title="View History"><i class="fas fa-history"></i></button>
                        <?php if ($row['status'] === 'completed'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="verify_assignment">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button class="btn btn-success btn-sm" type="submit" title="Verify" onclick="return confirm('Mark this assignment as verified?')"><i class="fas fa-check-double"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_assignment">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Delete this assignment?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminModalStart('assignmentModal', 'Add Assignment', 'housekeeping-modal-content'); ?>
    <form method="POST" id="assignmentForm">
        <input type="hidden" name="action" id="formAction" value="add_assignment">
        <input type="hidden" name="id" id="assignmentId">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-group">
            <label>Room *</label>
            <select name="individual_room_id" id="roomSelect" required>
                <option value="">Select room</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r['id']; ?>" data-status="<?php echo $r['status']; ?>" data-hk-status="<?php echo $r['housekeeping_status'] ?? ''; ?>">
                        <?php echo htmlspecialchars($r['room_number'] . ' ' . ($r['room_name'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #6b7280; font-size: 12px;">Room status will be shown when selected</small>
        </div>
        <div class="form-group">
            <label>Assignment Type *</label>
            <select name="assignment_type" id="assignmentType" required>
                <option value="regular_cleaning">Regular Cleaning</option>
                <option value="checkout_cleanup">Checkout Cleanup (High Priority)</option>
                <option value="deep_clean">Deep Clean</option>
                <option value="maintenance">Maintenance</option>
                <option value="turn_down">Turn Down Service</option>
            </select>
        </div>
        <div class="form-group">
            <label>Priority *</label>
            <select name="priority" id="priority" required>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="low">Low</option>
            </select>
        </div>
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
                <option value="blocked">Blocked</option>
            </select>
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
            <label>Estimated Duration (minutes)</label>
            <input type="number" name="estimated_duration" id="estimated_duration" value="30" min="5" step="5">
        </div>
        <div class="form-group">
            <label>Actual Duration (minutes)</label>
            <input type="number" name="actual_duration" id="actual_duration" min="1" step="1" placeholder="Fill when completed">
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
        
        // Auto-set high priority for checkout cleanup
        const assignmentTypeSelect = document.getElementById('assignmentType');
        const prioritySelect = document.getElementById('priority');
        if (assignmentTypeSelect && prioritySelect) {
            assignmentTypeSelect.addEventListener('change', function() {
                if (this.value === 'checkout_cleanup') {
                    prioritySelect.value = 'high';
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
                const hkStatus = selectedOption.getAttribute('data-hk-status');
                const small = this.parentElement.querySelector('small');
                if (small && this.value) {
                    small.textContent = 'Room: ' + (roomStatus || 'unknown') + ' | Housekeeping: ' + (hkStatus || 'none');
                }
            });
        }
    });

    function openModal() {
        document.getElementById('assignmentModal-title').textContent = 'Add Assignment';
        document.getElementById('formAction').value = 'add_assignment';
        document.getElementById('assignmentForm').reset();
        document.getElementById('assignmentId').value = '';
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
        document.getElementById('recurringOptions').style.display = 'none';
        openAdminModal('assignmentModal');
    }
    
    function closeModal() {
        closeAdminModal('assignmentModal');
    }
    
    function editAssignment(data) {
        document.getElementById('assignmentModal-title').textContent = 'Edit Assignment';
        document.getElementById('formAction').value = 'update_assignment';
        document.getElementById('assignmentId').value = data.id;
        document.getElementById('roomSelect').value = data.individual_room_id;
        document.getElementById('assignmentType').value = data.assignment_type || 'regular_cleaning';
        document.getElementById('priority').value = data.priority || 'medium';
        document.getElementById('due_date').value = data.due_date;
        document.getElementById('status').value = data.status;
        document.getElementById('assigned_to').value = data.assigned_to || '';
        document.getElementById('estimated_duration').value = data.estimated_duration || 30;
        document.getElementById('actual_duration').value = data.actual_duration || '';
        document.getElementById('notes').value = data.notes || '';
        
        const isRecurringCheckbox = document.getElementById('is_recurring');
        isRecurringCheckbox.checked = data.is_recurring == 1;
        document.getElementById('recurringOptions').style.display = data.is_recurring == 1 ? 'block' : 'none';
        document.getElementById('recurring_pattern').value = data.recurring_pattern || 'daily';
        document.getElementById('recurring_end_date').value = data.recurring_end_date || '';
        
        openAdminModal('assignmentModal');
    }
    
    function selectAllOccupied() {
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
    
    bindAdminModal('assignmentModal');
    bindAdminModal('auditLogModal');
    
    function viewAuditLog(assignmentId, roomName) {
        document.getElementById('auditLogModal-title').textContent = 'Audit History - ' + roomName + ' (ID: ' + assignmentId + ')';
        document.getElementById('auditLogContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        openAdminModal('auditLogModal');
        
        // Fetch audit log via AJAX
        fetch('api/get-housekeeping-audit.php?id=' + encodeURIComponent(assignmentId), {
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
                    const actionBadge = getActionBadge(log.action);
                    const formattedDate = new Date(log.performed_at).toLocaleString();
                    const changes = formatChanges(log);
                    
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
    
    function getActionBadge(action) {
        const badges = {
            'created': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #d1fae5; color: #065f46;">Created</span>',
            'updated': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #dbeafe; color: #1e40af;">Updated</span>',
            'deleted': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #991b1b;">Deleted</span>',
            'verified': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #e0e7ff; color: #3730a3;">Verified</span>',
            'status_changed': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #92400e;">Status Changed</span>',
            'assigned': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3e8ff; color: #6b21a8;">Assigned</span>',
            'unassigned': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #374151;">Unassigned</span>',
            'priority_changed': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #ffedd5; color: #9a3412;">Priority Changed</span>',
            'recurring_created': '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #ecfdf5; color: #064e3b;">Recurring Created</span>',
        };
        return badges[action] || '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #374151;">' + action + '</span>';
    }
    
    function formatChanges(log) {
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
