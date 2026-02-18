<?php
/**
 * Housekeeping Assignments API
 *
 * Endpoints:
 * GET    /api/housekeeping?room_id=    - List housekeeping assignments
 * POST   /api/housekeeping              - Create assignment
 * PUT    /api/housekeeping/{id}         - Update assignment
 * PUT    /api/housekeeping/{id}/status  - Update status
 * DELETE /api/housekeeping/{id}         - Delete assignment
 */

if (!defined('API_ACCESS_ALLOWED')) {
    http_response_code(403);
    exit;
}

global $pdo, $auth, $client;
$method = $_SERVER['REQUEST_METHOD'];
$path   = $_SERVER['PATH_INFO'] ?? '';
$id     = null;
$statusPath = false;

if (preg_match('#^/(\d+)/status$#', $path, $m)) {
    $id = (int)$m[1];
    $statusPath = true;
} elseif (preg_match('#^/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
}

switch ($method) {
    case 'GET':
        listAssignments();
        break;
    case 'POST':
        createAssignment();
        break;
    case 'PUT':
        if (!$id) ApiResponse::error('Assignment ID required', 400);
        if ($statusPath) {
            updateStatus($id);
        } else {
            updateAssignment($id);
        }
        break;
    case 'DELETE':
        if (!$id) ApiResponse::error('Assignment ID required', 400);
        deleteAssignment($id);
        break;
    default:
        ApiResponse::error('Method not allowed', 405);
}

function listAssignments() {
    global $pdo;
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $status = $_GET['status'] ?? null;
    $sql = "SELECT * FROM housekeeping_assignments WHERE 1=1";
    $params = [];

    if ($roomId) {
        $sql .= " AND individual_room_id = ?";
        $params[] = $roomId;
    }
    if ($status) {
        $validStatuses = ['pending','in_progress','completed','blocked'];
        if (in_array($status, $validStatuses, true)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
    }

    $sql .= " ORDER BY due_date ASC, created_at DESC";
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

    ApiResponse::success($rows, 'Housekeeping assignments fetched');
}

function createAssignment() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) ApiResponse::error('Invalid JSON body', 400);

    $required = ['individual_room_id','due_date'];
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

    $stmt = $pdo->prepare("INSERT INTO housekeeping_assignments (individual_room_id, status, due_date, assigned_to, created_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int)$input['individual_room_id'],
        $input['status'] ?? 'pending',
        $input['due_date'],
        $input['assigned_to'] ?? null,
        $input['created_by'] ?? null,
        $input['notes'] ?? null
    ]);

    ApiResponse::success(['id' => $pdo->lastInsertId()], 'Assignment created', 201);
}

function updateAssignment($id) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) ApiResponse::error('Invalid JSON body', 400);

    $allowed = ['status','due_date','assigned_to','created_by','notes','individual_room_id'];
    $fields = [];
    $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = ?";
            $params[] = $input[$f];
        }
    }
    if (!$fields) ApiResponse::error('No fields to update', 400);

    $params[] = $id;
    $sql = "UPDATE housekeeping_assignments SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ApiResponse::success(null, 'Assignment updated');
}

function updateStatus($id) {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) ApiResponse::error('Invalid JSON body', 400);
    $status = $input['status'] ?? null;
    $validStatuses = ['pending','in_progress','completed','blocked'];
    if (!$status || !in_array($status, $validStatuses, true)) {
        ApiResponse::validationError(['status' => 'Invalid status']);
    }

    $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE housekeeping_assignments SET status = ?, completed_at = ? WHERE id = ?");
    $stmt->execute([$status, $completedAt, $id]);
    ApiResponse::success(null, 'Status updated');
}

function deleteAssignment($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM housekeeping_assignments WHERE id = ?");
    $stmt->execute([$id]);
    ApiResponse::success(null, 'Assignment deleted');
}
