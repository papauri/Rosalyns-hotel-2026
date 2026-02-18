<?php
/**
 * Maintenance Schedules API
 *
 * Endpoints:
 * GET    /api/maintenance-schedules?room_id=    - List maintenance for a room
 * POST   /api/maintenance-schedules              - Create maintenance schedule
 * PUT    /api/maintenance-schedules/{id}         - Update maintenance schedule
 * DELETE /api/maintenance-schedules/{id}         - Delete maintenance schedule
 * PATCH  /api/maintenance-schedules/{id}/complete  - Mark as completed
 */

if (!defined('API_ACCESS_ALLOWED')) {
    http_response_code(403);
    exit;
}

global $pdo, $auth, $client;
$method = $_SERVER['REQUEST_METHOD'];
$path   = $_SERVER['PATH_INFO'] ?? '';
$id     = null;
$completePath = false;

if (preg_match('#^/(\d+)/complete$#', $path, $m)) {
    $id = (int)$m[1];
    $completePath = true;
} elseif (preg_match('#^/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
}

switch ($method) {
    case 'GET':
        listSchedules();
        break;
    case 'POST':
        createSchedule();
        break;
    case 'PUT':
        if (!$id) ApiResponse::error('Schedule ID required', 400);
        updateSchedule($id);
        break;
    case 'PATCH':
        if (!$id) ApiResponse::error('Schedule ID required', 400);
        if (!$completePath) ApiResponse::error('Use /{id}/complete for PATCH', 400);
        completeSchedule($id);
        break;
    case 'DELETE':
        if (!$id) ApiResponse::error('Schedule ID required', 400);
        deleteSchedule($id);
        break;
    default:
        ApiResponse::error('Method not allowed', 405);
}

function maintenanceApiTableExists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$table];
}

function maintenanceApiSetRoomStatus(PDO $pdo, int $roomId, string $newStatus, ?string $reason, ?int $performedBy): void {
    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $oldStatus = (string)$statusStmt->fetchColumn();
    if ($oldStatus === '' || $oldStatus === $newStatus) {
        return;
    }

    $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?")->execute([$newStatus, $roomId]);

    if (maintenanceApiTableExists($pdo, 'room_maintenance_log')) {
        $logStmt = $pdo->prepare("INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by) VALUES (?, ?, ?, ?, ?)");
        $logStmt->execute([$roomId, $oldStatus, $newStatus, $reason, $performedBy]);
    }
}

function maintenanceApiHasActiveBlockNow(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("\n+        SELECT COUNT(*)\n+        FROM room_maintenance_schedules\n+        WHERE individual_room_id = ?\n+          AND block_room = 1\n+          AND status IN ('planned', 'in_progress')\n+          AND start_date <= NOW()\n+          AND end_date > NOW()\n+    ");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

function maintenanceApiSyncRoomStatus(PDO $pdo, int $roomId, ?int $performedBy = null, string $reason = 'Maintenance schedule API sync'): void {
    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $current = (string)$statusStmt->fetchColumn();
    if ($current === '') {
        return;
    }

    $hasBlock = maintenanceApiHasActiveBlockNow($pdo, $roomId);
    if ($hasBlock) {
        if (!in_array($current, ['occupied', 'out_of_order', 'maintenance'], true)) {
            maintenanceApiSetRoomStatus($pdo, $roomId, 'maintenance', $reason, $performedBy);
        }
        return;
    }

    if ($current === 'maintenance') {
        maintenanceApiSetRoomStatus($pdo, $roomId, 'available', $reason, $performedBy);
    }
}

function maintenanceApiOverlaps(PDO $pdo, int $roomId, string $startDate, string $endDate, ?int $excludeId = null): bool {
    $sql = "\n+        SELECT COUNT(*)\n+        FROM room_maintenance_schedules\n+        WHERE individual_room_id = ?\n+          AND block_room = 1\n+          AND status IN ('planned', 'in_progress')\n+          AND NOT (end_date <= ? OR start_date >= ?)\n+    ";
    $params = [$roomId, $startDate, $endDate];
    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function listSchedules() {
    global $pdo;
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $status = $_GET['status'] ?? null;

    $sql = "SELECT * FROM room_maintenance_schedules WHERE 1=1";
    $params = [];

    if ($roomId) {
        $sql .= " AND individual_room_id = ?";
        $params[] = $roomId;
    }

    if ($status) {
        $validStatuses = ['planned','in_progress','completed','cancelled'];
        if (in_array($status, $validStatuses, true)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
    }

    $sql .= " ORDER BY start_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        if (!empty($row['assigned_to'])) {
            $u = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
            $u->execute([$row['assigned_to']]);
            $row['assigned_to_name'] = $u->fetchColumn();
        }
        if (!empty($row['created_by'])) {
            $u = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
            $u->execute([$row['created_by']]);
            $row['created_by_name'] = $u->fetchColumn();
        }
    }

    ApiResponse::success($rows, 'Schedules fetched');
}

function createSchedule() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) ApiResponse::error('Invalid JSON body', 400);

    $required = ['individual_room_id','title','start_date','end_date'];
    $errors = [];
    foreach ($required as $f) {
        if (empty($input[$f])) {
            $errors[$f] = ucfirst(str_replace('_', ' ', $f)) . ' is required';
        }
    }
    if ($errors) ApiResponse::validationError($errors);

    $chk = $pdo->prepare("SELECT id FROM individual_rooms WHERE id = ? AND is_active = 1");
    $chk->execute([(int)$input['individual_room_id']]);
    if (!$chk->fetch()) ApiResponse::error('Room not found or inactive', 404);

    $status = $input['status'] ?? 'planned';
    $priority = $input['priority'] ?? 'medium';
    $blockRoom = isset($input['block_room']) ? (int)$input['block_room'] : 1;
    $startDate = $input['start_date'];
    $endDate = $input['end_date'];
    $performedBy = isset($input['created_by']) ? (int)$input['created_by'] : null;

    if (!in_array($status, ['planned','in_progress','completed','cancelled'], true)) {
        ApiResponse::validationError(['status' => 'Invalid status']);
    }
    if (!in_array($priority, ['low','medium','high','urgent'], true)) {
        ApiResponse::validationError(['priority' => 'Invalid priority']);
    }
    if (strtotime($startDate) === false || strtotime($endDate) === false || strtotime($endDate) <= strtotime($startDate)) {
        ApiResponse::validationError(['date_range' => 'Invalid start/end date range']);
    }
    if ($blockRoom === 1 && in_array($status, ['planned', 'in_progress'], true) && maintenanceApiOverlaps($pdo, (int)$input['individual_room_id'], $startDate, $endDate)) {
        ApiResponse::validationError(['overlap' => 'Overlapping active maintenance block exists for this room']);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(" 
            INSERT INTO room_maintenance_schedules
            (individual_room_id, title, description, status, priority, block_room, start_date, end_date, assigned_to, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$input['individual_room_id'],
            $input['title'],
            $input['description'] ?? null,
            $status,
            $priority,
            $blockRoom,
            $startDate,
            $endDate,
            $input['assigned_to'] ?? null,
            $performedBy
        ]);

        maintenanceApiSyncRoomStatus($pdo, (int)$input['individual_room_id'], $performedBy, 'Maintenance schedule created via API');
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    ApiResponse::success(['id' => $newId], 'Maintenance schedule created', 201);
}

function updateSchedule($id) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) ApiResponse::error('Invalid JSON body', 400);

    $existingStmt = $pdo->prepare("SELECT * FROM room_maintenance_schedules WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        ApiResponse::error('Schedule not found', 404);
    }

    $allowed = ['individual_room_id','title','description','status','priority','block_room','start_date','end_date','assigned_to','created_by'];
    $fields = [];
    $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = ?";
            $params[] = $input[$f];
        }
    }
    if (!$fields) ApiResponse::error('No fields to update', 400);

    $roomId = (int)($input['individual_room_id'] ?? $existing['individual_room_id']);
    $status = (string)($input['status'] ?? $existing['status']);
    $blockRoom = (int)($input['block_room'] ?? $existing['block_room']);
    $startDate = (string)($input['start_date'] ?? $existing['start_date']);
    $endDate = (string)($input['end_date'] ?? $existing['end_date']);
    $performedBy = isset($input['created_by']) ? (int)$input['created_by'] : ((isset($existing['created_by']) && $existing['created_by'] !== null) ? (int)$existing['created_by'] : null);

    if (!in_array($status, ['planned','in_progress','completed','cancelled'], true)) {
        ApiResponse::validationError(['status' => 'Invalid status']);
    }
    if (strtotime($startDate) === false || strtotime($endDate) === false || strtotime($endDate) <= strtotime($startDate)) {
        ApiResponse::validationError(['date_range' => 'Invalid start/end date range']);
    }
    if ($blockRoom === 1 && in_array($status, ['planned','in_progress'], true) && maintenanceApiOverlaps($pdo, $roomId, $startDate, $endDate, $id)) {
        ApiResponse::validationError(['overlap' => 'Overlapping active maintenance block exists for this room']);
    }

    $pdo->beginTransaction();
    try {
        $params[] = $id;
        $sql = "UPDATE room_maintenance_schedules SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        maintenanceApiSyncRoomStatus($pdo, $roomId, $performedBy, 'Maintenance schedule updated via API');
        if ((int)$existing['individual_room_id'] !== $roomId) {
            maintenanceApiSyncRoomStatus($pdo, (int)$existing['individual_room_id'], $performedBy, 'Maintenance schedule moved via API');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    ApiResponse::success(null, 'Maintenance schedule updated');
}

function completeSchedule($id) {
    global $pdo;
    $rowStmt = $pdo->prepare("SELECT individual_room_id, created_by FROM room_maintenance_schedules WHERE id = ?");
    $rowStmt->execute([$id]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        ApiResponse::error('Schedule not found', 404);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE room_maintenance_schedules SET status = 'completed' WHERE id = ?");
        $stmt->execute([$id]);
        maintenanceApiSyncRoomStatus($pdo, (int)$row['individual_room_id'], isset($row['created_by']) ? (int)$row['created_by'] : null, 'Maintenance schedule completed via API');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    ApiResponse::success(null, 'Maintenance marked as completed');
}

function deleteSchedule($id) {
    global $pdo;
    $rowStmt = $pdo->prepare("SELECT individual_room_id, created_by FROM room_maintenance_schedules WHERE id = ?");
    $rowStmt->execute([$id]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        ApiResponse::error('Schedule not found', 404);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM room_maintenance_schedules WHERE id = ?");
        $stmt->execute([$id]);
        maintenanceApiSyncRoomStatus($pdo, (int)$row['individual_room_id'], isset($row['created_by']) ? (int)$row['created_by'] : null, 'Maintenance schedule deleted via API');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    ApiResponse::success(null, 'Maintenance schedule deleted');
}
