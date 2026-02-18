<?php
/**
 * Room Maintenance Management - Admin Panel
 * Track maintenance schedules and blocks
 */
require_once 'admin-init.php';
require_once 'includes/admin-modal.php';

if (!hasPermission($user['id'], 'room_maintenance')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$error = '';

$validMaintenanceStatuses = ['planned', 'in_progress', 'completed', 'cancelled'];
$validMaintenancePriorities = ['low', 'medium', 'high', 'urgent'];

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

function maintenanceRoomExists(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE id = ? AND is_active = 1");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

function maintenanceUserExists(PDO $pdo, ?int $userId): bool {
    if (empty($userId)) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function shouldBlockRoomBySchedule(string $status, int $blockRoom): bool {
    return $blockRoom === 1 && in_array($status, ['planned', 'in_progress'], true);
}

function setRoomStatusFromMaintenance(PDO $pdo, int $roomId, string $newStatus, ?string $reason, ?int $performedBy): void {
    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $oldStatus = (string)$statusStmt->fetchColumn();

    if ($oldStatus === '' || $oldStatus === $newStatus) {
        return;
    }

    $updateStmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $roomId]);

    if (!maintenanceTableExists($pdo, 'room_maintenance_log')) {
        return;
    }

    $logStmt = $pdo->prepare("INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by) VALUES (?, ?, ?, ?, ?)");
    $logStmt->execute([$roomId, $oldStatus, $newStatus, $reason, $performedBy]);
}

function roomHasActiveMaintenanceBlockNow(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM room_maintenance_schedules\n        WHERE individual_room_id = ?\n          AND block_room = 1\n          AND status IN ('planned', 'in_progress')\n          AND start_date <= NOW()\n          AND end_date > NOW()\n    ");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

function hasMaintenanceScheduleOverlap(PDO $pdo, int $roomId, string $startDate, string $endDate, ?int $excludeId = null): bool {
    $sql = "\n        SELECT COUNT(*)\n        FROM room_maintenance_schedules\n        WHERE individual_room_id = ?\n          AND block_room = 1\n          AND status IN ('planned', 'in_progress')\n          AND NOT (end_date <= ? OR start_date >= ?)\n    ";
    $params = [$roomId, $startDate, $endDate];

    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function syncRoomStatusFromMaintenance(PDO $pdo, int $roomId, ?int $performedBy = null, string $contextReason = 'Maintenance schedule sync'): void {
    $currentStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $currentStmt->execute([$roomId]);
    $currentStatus = (string)$currentStmt->fetchColumn();

    if ($currentStatus === '') {
        return;
    }

    $hasActiveBlock = roomHasActiveMaintenanceBlockNow($pdo, $roomId);
    if ($hasActiveBlock) {
        if (!in_array($currentStatus, ['occupied', 'out_of_order', 'maintenance'], true)) {
            setRoomStatusFromMaintenance($pdo, $roomId, 'maintenance', $contextReason, $performedBy);
        }
        return;
    }

    if ($currentStatus === 'maintenance') {
        setRoomStatusFromMaintenance($pdo, $roomId, 'available', $contextReason, $performedBy);
    }
}

function writeMaintenanceScheduleAuditLog(PDO $pdo, int $roomId, string $reason, ?int $performedBy = null): void {
    if (!maintenanceTableExists($pdo, 'room_maintenance_log')) {
        return;
    }

    $statusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
    $statusStmt->execute([$roomId]);
    $currentStatus = (string)$statusStmt->fetchColumn();
    if ($currentStatus === '') {
        return;
    }

    $logStmt = $pdo->prepare("INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by) VALUES (?, ?, ?, ?, ?)");
    $logStmt->execute([$roomId, $currentStatus, $currentStatus, $reason, $performedBy]);
}

function reconcileMaintenanceStatuses(PDO $pdo, ?int $performedBy = null): void {
    $roomIds = [];
    $stmt = $pdo->query("SELECT DISTINCT individual_room_id FROM room_maintenance_schedules WHERE block_room = 1 AND status IN ('planned','in_progress')");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $roomId) {
        $roomIds[(int)$roomId] = true;
    }

    $maintenanceRooms = $pdo->query("SELECT id FROM individual_rooms WHERE status = 'maintenance'");
    foreach ($maintenanceRooms->fetchAll(PDO::FETCH_COLUMN) as $roomId) {
        $roomIds[(int)$roomId] = true;
    }

    foreach (array_keys($roomIds) as $roomId) {
        syncRoomStatusFromMaintenance($pdo, (int)$roomId, $performedBy, 'Maintenance schedule reconciliation');
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new RuntimeException('Invalid security token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'add_schedule') {
            $room_id = (int)$_POST['individual_room_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $status = $_POST['status'] ?? 'planned';
            $block_room = isset($_POST['block_room']) ? 1 : 0;
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;

            if (!$room_id || !$title || !$start_date || !$end_date) {
                $error = 'Room, title, start date and end date are required.';
            } elseif (!in_array($status, $validMaintenanceStatuses, true)) {
                $error = 'Invalid maintenance status.';
            } elseif (!in_array($priority, $validMaintenancePriorities, true)) {
                $error = 'Invalid maintenance priority.';
            } elseif (!maintenanceRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!maintenanceUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($start_date) === false || strtotime($end_date) === false) {
                $error = 'Invalid maintenance dates.';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $error = 'End date must be after start date.';
            } elseif ($block_room === 1 && in_array($status, ['planned', 'in_progress'], true) && hasMaintenanceScheduleOverlap($pdo, $room_id, $start_date, $end_date)) {
                $error = 'This room already has an overlapping active maintenance block.';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(" 
                    INSERT INTO room_maintenance_schedules
                    (individual_room_id, title, description, status, priority, block_room, start_date, end_date, assigned_to, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $room_id, $title, $description, $status, $priority, $block_room,
                    $start_date, $end_date, $assigned_to, $user['id'] ?? null
                ]);

                writeMaintenanceScheduleAuditLog($pdo, $room_id, 'Maintenance schedule created: ' . $title, $user['id'] ?? null);
                syncRoomStatusFromMaintenance($pdo, $room_id, $user['id'] ?? null, 'Maintenance schedule created');
                $pdo->commit();
                $message = 'Maintenance schedule added.';
            }
        } elseif ($action === 'update_schedule') {
            $id = (int)$_POST['id'];
            $room_id = (int)$_POST['individual_room_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $status = $_POST['status'] ?? 'planned';
            $block_room = isset($_POST['block_room']) ? 1 : 0;
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;

            if (!$id || !$room_id || !$title || !$start_date || !$end_date) {
                $error = 'Room, title, start date and end date are required.';
            } elseif (!in_array($status, $validMaintenanceStatuses, true)) {
                $error = 'Invalid maintenance status.';
            } elseif (!in_array($priority, $validMaintenancePriorities, true)) {
                $error = 'Invalid maintenance priority.';
            } elseif (!maintenanceRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!maintenanceUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($start_date) === false || strtotime($end_date) === false) {
                $error = 'Invalid maintenance dates.';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $error = 'End date must be after start date.';
            } elseif ($block_room === 1 && in_array($status, ['planned', 'in_progress'], true) && hasMaintenanceScheduleOverlap($pdo, $room_id, $start_date, $end_date, $id)) {
                $error = 'This room already has an overlapping active maintenance block.';
            } else {
                $pdo->beginTransaction();
                $existsStmt = $pdo->prepare("SELECT id, individual_room_id, title FROM room_maintenance_schedules WHERE id = ?");
                $existsStmt->execute([$id]);
                $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new RuntimeException('Maintenance schedule not found.');
                }

                $stmt = $pdo->prepare(" 
                    UPDATE room_maintenance_schedules
                    SET individual_room_id=?, title=?, description=?, status=?, priority=?, block_room=?, start_date=?, end_date=?, assigned_to=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $room_id, $title, $description, $status, $priority, $block_room,
                    $start_date, $end_date, $assigned_to, $id
                ]);

                writeMaintenanceScheduleAuditLog($pdo, $room_id, 'Maintenance schedule updated: ' . $title, $user['id'] ?? null);
                syncRoomStatusFromMaintenance($pdo, $room_id, $user['id'] ?? null, 'Maintenance schedule updated');
                if ((int)$existing['individual_room_id'] !== $room_id) {
                    syncRoomStatusFromMaintenance($pdo, (int)$existing['individual_room_id'], $user['id'] ?? null, 'Maintenance schedule moved to another room');
                }
                $pdo->commit();
                $message = 'Maintenance schedule updated.';
            }
        } elseif ($action === 'delete_schedule') {
            $id = (int)$_POST['id'];
            if ($id <= 0) {
                $error = 'Invalid schedule selected.';
            } else {
                $pdo->beginTransaction();
                $rowStmt = $pdo->prepare("SELECT individual_room_id, title FROM room_maintenance_schedules WHERE id = ?");
                $rowStmt->execute([$id]);
                $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Schedule not found.');
                }

                $pdo->prepare("DELETE FROM room_maintenance_schedules WHERE id = ?")->execute([$id]);

                writeMaintenanceScheduleAuditLog($pdo, (int)$row['individual_room_id'], 'Maintenance schedule deleted: ' . ($row['title'] ?? ('#' . $id)), $user['id'] ?? null);
                syncRoomStatusFromMaintenance($pdo, (int)$row['individual_room_id'], $user['id'] ?? null, 'Maintenance schedule deleted');

                $pdo->commit();
                $message = 'Maintenance schedule deleted.';
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Database error: ' . $e->getMessage();
    }
}

try {
    reconcileMaintenanceStatuses($pdo, $user['id'] ?? null);
} catch (Throwable $syncError) {
    error_log('Maintenance reconciliation warning: ' . $syncError->getMessage());
}

// Data for UI
$roomsStmt = $pdo->query("SELECT id, room_number, room_name FROM individual_rooms WHERE is_active = 1 ORDER BY room_number ASC");
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query("SELECT id, username FROM admin_users ORDER BY username ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Filters
$filterRoom = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
$filterStatus = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($filterRoom) {
    $where[] = 'rms.individual_room_id = ?';
    $params[] = $filterRoom;
}
if ($filterStatus) {
    $where[] = 'rms.status = ?';
    $params[] = $filterStatus;
}

$scheduleStmt = $pdo->prepare("
    SELECT rms.*, ir.room_number, ir.room_name, u.username as assigned_name
    FROM room_maintenance_schedules rms
    LEFT JOIN individual_rooms ir ON rms.individual_room_id = ir.id
    LEFT JOIN admin_users u ON rms.assigned_to = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY rms.start_date DESC
");
$scheduleStmt->execute($params);
$schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

$maintenanceLogs = [];
try {
    if (maintenanceTableExists($pdo, 'room_maintenance_log')) {
        $logWhere = ['1=1'];
        $logParams = [];
        if ($filterRoom) {
            $logWhere[] = 'rml.individual_room_id = ?';
            $logParams[] = $filterRoom;
        }

        $logStmt = $pdo->prepare("\n            SELECT rml.*, ir.room_number, ir.room_name, au.username AS performed_by_name\n            FROM room_maintenance_log rml\n            LEFT JOIN individual_rooms ir ON rml.individual_room_id = ir.id\n            LEFT JOIN admin_users au ON rml.performed_by = au.id\n            WHERE " . implode(' AND ', $logWhere) . "\n            ORDER BY rml.created_at DESC, rml.id DESC\n            LIMIT 100\n        ");
        $logStmt->execute($logParams);
        $maintenanceLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Unable to load maintenance logs: ' . $e->getMessage());
    $maintenanceLogs = [];
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
</head>
<body>
<?php require_once 'includes/admin-header.php'; ?>
<div class="content">
    <div class="page-header">
        <h2><i class="fas fa-tools"></i> Room Maintenance</h2>
        <button class="btn btn-primary" type="button" onclick="openModal()"><i class="fas fa-plus"></i> Add Maintenance</button>
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

    <div class="filters-bar" style="background:#fff;padding:12px 16px;border-radius:10px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <select name="room_id" onchange="this.form.submit()">
                <option value="">All Rooms</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php echo $filterRoom == $r['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['room_number'] . ' ' . ($r['room_name'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['planned','in_progress','completed','cancelled'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_',' ', $st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="room-maintenance.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Assigned</th>
                    <th>Block</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($schedules)): ?>
                <tr><td colspan="9" style="text-align:center;padding:24px;">No maintenance scheduled.</td></tr>
            <?php else: ?>
                <?php foreach ($schedules as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['room_number'] . ' ' . ($row['room_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><span class="status-pill <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></span></td>
                    <td><?php echo ucfirst($row['priority']); ?></td>
                    <td><?php echo htmlspecialchars($row['assigned_name'] ?: '-'); ?></td>
                    <td><?php echo $row['block_room'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" type="button" onclick='editSchedule(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
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
            <?php if (empty($maintenanceLogs)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;">No maintenance log entries found.</td></tr>
            <?php else: ?>
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
            <?php endif; ?>
            </tbody>
        </table>
    </div>
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
                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_number'] . ' ' . ($r['room_name'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
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
                <label>Status</label>
                <select name="status" id="status">
                    <option value="planned">Planned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" id="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start *</label>
                <input type="datetime-local" name="start_date" id="start_date" required>
            </div>
            <div class="form-group">
                <label>End *</label>
                <input type="datetime-local" name="end_date" id="end_date" required>
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
            <label><input type="checkbox" name="block_room" id="block_room"> Block room during maintenance</label>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
<?php renderAdminModalEnd(); ?>

<script>
    function openModal() {
        document.getElementById('scheduleModal-title').textContent = 'Add Maintenance';
        document.getElementById('formAction').value = 'add_schedule';
        document.getElementById('scheduleForm').reset();
        document.getElementById('scheduleId').value = '';
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
        document.getElementById('status').value = data.status;
        document.getElementById('priority').value = data.priority;
        document.getElementById('block_room').checked = data.block_room == 1;
        document.getElementById('start_date').value = toDatetimeLocal(data.start_date);
        document.getElementById('end_date').value = toDatetimeLocal(data.end_date);
        document.getElementById('assigned_to').value = data.assigned_to || '';
        openAdminModal('scheduleModal');
    }

    function toDatetimeLocal(value) {
        if (!value) return '';
        return String(value).replace(' ', 'T').slice(0, 16);
    }

    bindAdminModal('scheduleModal');
</script>

<?php renderAdminModalScript(); ?>

<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>
