<?php
/**
 * Housekeeping Management - Admin Panel
 */
require_once 'admin-init.php';
require_once 'includes/admin-modal.php';

if (!hasPermission($user['id'], 'housekeeping')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$error = '';

$validHousekeepingStatuses = ['pending', 'in_progress', 'completed', 'blocked'];

function housekeepingRoomExists(PDO $pdo, int $roomId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE id = ? AND is_active = 1");
    $stmt->execute([$roomId]);
    return (int)$stmt->fetchColumn() > 0;
}

function housekeepingUserExists(PDO $pdo, ?int $userId): bool {
    if (empty($userId)) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

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

function reconcileIndividualRoomHousekeeping(PDO $pdo, int $roomId, ?int $performedBy = null): void {
    $openStmt = $pdo->prepare("\n        SELECT status, notes\n        FROM housekeeping_assignments\n        WHERE individual_room_id = ?\n          AND status IN ('pending','in_progress','blocked')\n        ORDER BY\n            CASE status\n                WHEN 'in_progress' THEN 1\n                WHEN 'pending' THEN 2\n                WHEN 'blocked' THEN 3\n                ELSE 99\n            END,\n            due_date ASC,\n            id DESC\n        LIMIT 1\n    ");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new RuntimeException('Invalid security token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'add_assignment') {
            $room_id = (int)$_POST['individual_room_id'];
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');

            if (!$room_id || !$due_date) {
                $error = 'Room and due date are required.';
            } elseif (!in_array($status, $validHousekeepingStatuses, true)) {
                $error = 'Invalid housekeeping status.';
            } elseif (!housekeepingRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!housekeepingUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($due_date) === false) {
                $error = 'Invalid due date.';
            } else {
                $pdo->beginTransaction();
                $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare("INSERT INTO housekeeping_assignments (individual_room_id, status, due_date, assigned_to, created_by, notes, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$room_id, $status, $due_date, $assigned_to, $user['id'] ?? null, $notes, $completedAt]);

                reconcileIndividualRoomHousekeeping($pdo, $room_id, $user['id'] ?? null);
                $pdo->commit();
                $message = 'Assignment created.';
            }
        } elseif ($action === 'update_assignment') {
            $id = (int)$_POST['id'];
            $room_id = (int)$_POST['individual_room_id'];
            $due_date = $_POST['due_date'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            $assigned_to = $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null;
            $notes = trim($_POST['notes'] ?? '');

            if (!$id || !$room_id || !$due_date) {
                $error = 'Room and due date are required.';
            } elseif (!in_array($status, $validHousekeepingStatuses, true)) {
                $error = 'Invalid housekeeping status.';
            } elseif (!housekeepingRoomExists($pdo, $room_id)) {
                $error = 'Selected room is invalid or inactive.';
            } elseif (!housekeepingUserExists($pdo, $assigned_to)) {
                $error = 'Assigned user is invalid.';
            } elseif (strtotime($due_date) === false) {
                $error = 'Invalid due date.';
            } else {
                $pdo->beginTransaction();
                $existsStmt = $pdo->prepare("SELECT id, individual_room_id FROM housekeeping_assignments WHERE id = ?");
                $existsStmt->execute([$id]);
                $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new RuntimeException('Assignment does not exist.');
                }

                $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare("UPDATE housekeeping_assignments SET individual_room_id=?, status=?, due_date=?, assigned_to=?, notes=?, completed_at=? WHERE id=?");
                $stmt->execute([$room_id, $status, $due_date, $assigned_to, $notes, $completedAt, $id]);

                reconcileIndividualRoomHousekeeping($pdo, $room_id, $user['id'] ?? null);
                if ((int)$existing['individual_room_id'] !== $room_id) {
                    reconcileIndividualRoomHousekeeping($pdo, (int)$existing['individual_room_id'], $user['id'] ?? null);
                }
                $pdo->commit();
                $message = 'Assignment updated.';
            }
        } elseif ($action === 'delete_assignment') {
            $id = (int)$_POST['id'];
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

                $pdo->prepare("DELETE FROM housekeeping_assignments WHERE id = ?")->execute([$id]);

                reconcileIndividualRoomHousekeeping($pdo, (int)$row['individual_room_id'], $user['id'] ?? null);

                $pdo->commit();
                $message = 'Assignment deleted.';
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
    $roomRows = $pdo->query("SELECT DISTINCT individual_room_id FROM housekeeping_assignments")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roomRows as $roomId) {
        reconcileIndividualRoomHousekeeping($pdo, (int)$roomId, $user['id'] ?? null);
    }
} catch (Throwable $syncError) {
    error_log('Housekeeping reconciliation warning: ' . $syncError->getMessage());
}

$roomsStmt = $pdo->query("SELECT id, room_number, room_name FROM individual_rooms WHERE is_active = 1 ORDER BY room_number ASC");
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query("SELECT id, username FROM admin_users ORDER BY username ASC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$assignmentsStmt = $pdo->query("
    SELECT ha.*, ir.room_number, ir.room_name
    FROM housekeeping_assignments ha
    LEFT JOIN individual_rooms ir ON ha.individual_room_id = ir.id
    ORDER BY ha.due_date DESC, ha.created_at DESC
");
$assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
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
</head>
<body>
<?php require_once 'includes/admin-header.php'; ?>
<div class="content">
    <div class="page-header">
        <h2><i class="fas fa-broom"></i> Housekeeping</h2>
        <button class="btn btn-primary" type="button" onclick="openModal()"><i class="fas fa-plus"></i> Add Assignment</button>
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

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Assigned To</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($assignments)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;">No housekeeping assignments.</td></tr>
            <?php else: ?>
                <?php foreach ($assignments as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['room_number'] . ' ' . ($row['room_name'] ?? '')); ?></td>
                    <td><span class="status-pill <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                    <td>
                        <?php
                            $assignedName = '';
                            if (!empty($row['assigned_to'])) {
                                foreach ($users as $u) {
                                    if ((int)$u['id'] === (int)$row['assigned_to']) {
                                        $assignedName = $u['username'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars($assignedName ?: '-');
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" type="button" onclick='editAssignment(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
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
                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_number'] . ' ' . ($r['room_name'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Due Date *</label>
            <input type="date" name="due_date" id="due_date" required>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" id="status">
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
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
            <label>Notes</label>
            <textarea name="notes" id="notes" rows="2"></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
<?php renderAdminModalEnd(); ?>

<script>
    function openModal() {
        document.getElementById('assignmentModal-title').textContent = 'Add Assignment';
        document.getElementById('formAction').value = 'add_assignment';
        document.getElementById('assignmentForm').reset();
        document.getElementById('assignmentId').value = '';
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
        document.getElementById('due_date').value = data.due_date;
        document.getElementById('status').value = data.status;
        document.getElementById('assigned_to').value = data.assigned_to || '';
        document.getElementById('notes').value = data.notes || '';
        openAdminModal('assignmentModal');
    }
    bindAdminModal('assignmentModal');
</script>

<?php renderAdminModalScript(); ?>

<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>
