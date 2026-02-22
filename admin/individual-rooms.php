<?php
/**
 * Individual Rooms Management - Admin Panel
 * Manage individual rooms (specific rooms like "Executive 101", "VVIP Suite")
 */
require_once 'admin-init.php';

$message = '';
$error = '';

function saveRoomAmenities(PDO $pdo, int $roomId, string $amenitiesRaw): void {
    $pdo->prepare("DELETE FROM individual_room_amenities WHERE individual_room_id = ?")->execute([$roomId]);
    $amenities = preg_split('/\s*,\s*/', trim($amenitiesRaw));
    $amenities = array_filter($amenities, fn($a) => $a !== '');
    if (empty($amenities)) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO individual_room_amenities (individual_room_id, amenity_key, amenity_label, display_order) VALUES (?, ?, ?, ?)");
    $order = 0;
    foreach ($amenities as $amenity) {
        $key = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($amenity)));
        $stmt->execute([$roomId, $key, trim($amenity), $order++]);
    }
}

function saveRoomPhotos(PDO $pdo, int $roomId, string $photosRaw): void {
    $pdo->prepare("DELETE FROM individual_room_photos WHERE individual_room_id = ?")->execute([$roomId]);
    $photos = preg_split('/[\r\n,]+/', trim($photosRaw));
    $photos = array_filter(array_map('trim', $photos), fn($p) => $p !== '');
    if (empty($photos)) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO individual_room_photos (individual_room_id, image_path, caption, display_order, is_primary, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $order = 0;
    foreach ($photos as $idx => $photo) {
        $isPrimary = $idx === 0 ? 1 : 0;
        $stmt->execute([$roomId, $photo, null, $order++, $isPrimary]);
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_individual_room') {
            $room_type_id = (int)$_POST['room_type_id'];
            $room_number = trim($_POST['room_number']);
            $room_name = trim($_POST['room_name'] ?? '');
            $floor = trim($_POST['floor'] ?? '');
            $status = $_POST['status'] ?? 'available';
            $child_price_multiplier = ($_POST['child_price_multiplier'] ?? '') !== '' ? (float)$_POST['child_price_multiplier'] : null;
            if ($child_price_multiplier !== null && $child_price_multiplier < 0) {
                $child_price_multiplier = 0.0;
            }
            $single_override = array_key_exists('single_occupancy_enabled_override', $_POST) ? (int)$_POST['single_occupancy_enabled_override'] : null;
            $double_override = array_key_exists('double_occupancy_enabled_override', $_POST) ? (int)$_POST['double_occupancy_enabled_override'] : null;
            $triple_override = array_key_exists('triple_occupancy_enabled_override', $_POST) ? (int)$_POST['triple_occupancy_enabled_override'] : null;
            $children_override = array_key_exists('children_allowed_override', $_POST) ? (int)$_POST['children_allowed_override'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $display_order = (int)($_POST['display_order'] ?? 0);
            $amenities_list = trim($_POST['amenities_list'] ?? '');
            $photos_list = trim($_POST['photos_list'] ?? '');
            
            // Validate
            if (empty($room_type_id) || empty($room_number)) {
                $error = 'Room type and room number are required.';
            } else {
                // Check if room number already exists
                $check = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE room_number = ?");
                $check->execute([$room_number]);
                if ($check->fetchColumn() > 0) {
                    $error = 'Room number already exists. Please use a unique room number.';
                } else {
                    // Insert new individual room
                    $stmt = $pdo->prepare(" 
                        INSERT INTO individual_rooms (
                            room_type_id, room_number, room_name, floor, status, child_price_multiplier,
                            single_occupancy_enabled_override, double_occupancy_enabled_override, triple_occupancy_enabled_override, children_allowed_override,
                            notes, display_order
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $room_type_id, $room_number, $room_name, $floor, $status, $child_price_multiplier,
                        $single_override, $double_override, $triple_override, $children_override,
                        $notes, $display_order
                    ]);
                    
                    // Log the creation
                    $room_id = $pdo->lastInsertId();
                    $logStmt = $pdo->prepare("
                        INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, performed_by)
                        VALUES (?, NULL, ?, ?)
                    ");
                    $logStmt->execute([$room_id, $status, $user['id'] ?? null]);

                    saveRoomAmenities($pdo, (int)$room_id, $amenities_list);
                    saveRoomPhotos($pdo, (int)$room_id, $photos_list);
                    
                    $message = 'Individual room added successfully!';
                }
            }
            
        } elseif ($action === 'update_individual_room') {
            $id = (int)$_POST['id'];
            $room_type_id = (int)$_POST['room_type_id'];
            $room_number = trim($_POST['room_number']);
            $room_name = trim($_POST['room_name'] ?? '');
            $floor = trim($_POST['floor'] ?? '');
            $child_price_multiplier = ($_POST['child_price_multiplier'] ?? '') !== '' ? (float)$_POST['child_price_multiplier'] : null;
            if ($child_price_multiplier !== null && $child_price_multiplier < 0) {
                $child_price_multiplier = 0.0;
            }
            $single_override = array_key_exists('single_occupancy_enabled_override', $_POST) ? (int)$_POST['single_occupancy_enabled_override'] : null;
            $double_override = array_key_exists('double_occupancy_enabled_override', $_POST) ? (int)$_POST['double_occupancy_enabled_override'] : null;
            $triple_override = array_key_exists('triple_occupancy_enabled_override', $_POST) ? (int)$_POST['triple_occupancy_enabled_override'] : null;
            $children_override = array_key_exists('children_allowed_override', $_POST) ? (int)$_POST['children_allowed_override'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $display_order = (int)($_POST['display_order'] ?? 0);
            $amenities_list = trim($_POST['amenities_list'] ?? '');
            $photos_list = trim($_POST['photos_list'] ?? '');
            
            // Validate
            if (empty($room_type_id) || empty($room_number)) {
                $error = 'Room type and room number are required.';
            } else {
                // Check if room number already exists (excluding current room)
                $check = $pdo->prepare("SELECT COUNT(*) FROM individual_rooms WHERE room_number = ? AND id != ?");
                $check->execute([$room_number, $id]);
                if ($check->fetchColumn() > 0) {
                    $error = 'Room number already exists. Please use a unique room number.';
                } else {
                    $stmt = $pdo->prepare(" 
                        UPDATE individual_rooms 
                        SET room_type_id = ?, room_number = ?, room_name = ?, floor = ?, child_price_multiplier = ?,
                            single_occupancy_enabled_override = ?, double_occupancy_enabled_override = ?, triple_occupancy_enabled_override = ?, children_allowed_override = ?,
                            notes = ?, is_active = ?, display_order = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $room_type_id, $room_number, $room_name, $floor, $child_price_multiplier,
                        $single_override, $double_override, $triple_override, $children_override,
                        $notes, $is_active, $display_order, $id
                    ]);
                    saveRoomAmenities($pdo, $id, $amenities_list);
                    saveRoomPhotos($pdo, $id, $photos_list);
                    $message = 'Individual room updated successfully!';
                }
            }
            
        } elseif ($action === 'update_status') {
            $id = (int)$_POST['id'];
            $new_status = $_POST['new_status'];
            $reason = trim($_POST['reason'] ?? '');
            
            $validStatuses = ['available', 'occupied', 'maintenance', 'cleaning', 'out_of_order'];
            if (!in_array($new_status, $validStatuses)) {
                $error = 'Invalid status.';
            } else {
                // Get current status
                $currentStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
                $currentStmt->execute([$id]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current) {
                    $old_status = $current['status'];
                    
                    // Update status
                    $stmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $id]);
                    
                    // Log the change
                    $logStmt = $pdo->prepare("
                        INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $logStmt->execute([$id, $old_status, $new_status, $reason, $user['id'] ?? null]);
                    
                    $message = 'Room status updated successfully!';
                } else {
                    $error = 'Room not found.';
                }
            }
            
        } elseif ($action === 'delete_individual_room') {
            $id = (int)$_POST['id'];
            
            // Check for active bookings
            $bookingsCheck = $pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE individual_room_id = ? AND status IN ('pending', 'confirmed', 'checked-in') AND check_out_date >= CURDATE()
            ");
            $bookingsCheck->execute([$id]);
            if ($bookingsCheck->fetchColumn() > 0) {
                $error = 'Cannot delete room with active bookings.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM individual_rooms WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Individual room deleted successfully!';
            }
            
        } elseif ($action === 'bulk_status_change') {
            $room_ids = $_POST['room_ids'] ?? [];
            $new_status = $_POST['bulk_status'] ?? '';
            
            if (empty($room_ids) || empty($new_status)) {
                $error = 'Please select rooms and a status.';
            } else {
                $validStatuses = ['available', 'occupied', 'maintenance', 'cleaning', 'out_of_order'];
                if (!in_array($new_status, $validStatuses)) {
                    $error = 'Invalid status.';
                } else {
                    foreach ($room_ids as $room_id) {
                        $room_id = (int)$room_id;
                        
                        // Get current status
                        $currentStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
                        $currentStmt->execute([$room_id]);
                        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($current) {
                            // Update status
                            $stmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
                            $stmt->execute([$new_status, $room_id]);
                            
                            // Log the change
                            $logStmt = $pdo->prepare("
                                INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                                VALUES (?, ?, ?, 'Bulk status change', ?)
                            ");
                            $logStmt->execute([$room_id, $current['status'], $new_status, $user['id'] ?? null]);
                        }
                    }
                    $message = count($room_ids) . ' rooms updated successfully!';
                }
            }
        } elseif ($action === 'get_assignable_bookings') {
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                throw new Exception('Invalid request');
            }

            $room_type_id = (int)($_POST['room_type_id'] ?? 0);
            $individual_room_id = (int)($_POST['individual_room_id'] ?? 0);

            if ($room_type_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid room type']);
                exit;
            }

            // Fetch bookings that are eligible for assignment
            $stmt = $pdo->prepare("
                SELECT
                    b.id,
                    b.booking_reference,
                    b.guest_name,
                    b.check_in_date,
                    b.check_out_date,
                    b.status,
                    b.individual_room_id,
                    r.name as room_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                WHERE b.room_id = ?
                AND b.status IN ('pending', 'confirmed', 'checked-in')
                AND (b.individual_room_id IS NULL OR b.individual_room_id = ?)
                AND b.check_out_date >= CURDATE()
                ORDER BY b.check_in_date ASC
            ");
            $stmt->execute([$room_type_id, $individual_room_id]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalize for JSON
            $normalized = array_map(function ($booking) {
                return [
                    'id' => (int)$booking['id'],
                    'reference' => $booking['booking_reference'],
                    'guest_name' => $booking['guest_name'],
                    'check_in' => $booking['check_in_date'],
                    'check_out' => $booking['check_out_date'],
                    'status' => $booking['status'],
                    'room_name' => $booking['room_name'],
                    'already_assigned' => !empty($booking['individual_room_id'])
                ];
            }, $bookings);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Bookings loaded',
                'data' => $normalized
            ]);
            exit;
        }
        
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get filter parameters
$filter_room_type = isset($_GET['room_type']) ? (int)$_GET['room_type'] : null;
$filter_status = $_GET['status'] ?? null;
$filter_floor = $_GET['floor'] ?? null;

// Build query for individual rooms
$whereClauses = ['1=1'];
$params = [];

if ($filter_room_type) {
    $whereClauses[] = 'ir.room_type_id = ?';
    $params[] = $filter_room_type;
}
if ($filter_status) {
    $whereClauses[] = 'ir.status = ?';
    $params[] = $filter_status;
}
if ($filter_floor) {
    $whereClauses[] = 'ir.floor = ?';
    $params[] = $filter_floor;
}

$stmt = $pdo->prepare("
    SELECT
        ir.*,
        r.name as room_type_name,
        r.price_per_night,
        r.max_guests,
        r.single_occupancy_enabled,
        r.double_occupancy_enabled,
        r.triple_occupancy_enabled,
        r.children_allowed,
        r.child_price_multiplier as room_type_child_price_multiplier,
        COALESCE(ir.child_price_multiplier, r.child_price_multiplier) AS effective_child_price_multiplier,
        -- Active booking: guest has checked in (check_in <= today AND check_out >= today)
        active_b.booking_reference as active_booking_ref,
        active_b.id as active_booking_id,
        active_b.guest_name as active_guest,
        active_b.check_in_date as active_checkin,
        active_b.check_out_date as active_checkout,
        active_b.status as active_booking_status,
        -- Reserved booking: future confirmed booking (check_in > today)
        reserved_b.booking_reference as reserved_booking_ref,
        reserved_b.id as reserved_booking_id,
        reserved_b.guest_name as reserved_guest,
        reserved_b.check_in_date as reserved_checkin,
        reserved_b.check_out_date as reserved_checkout,
        reserved_b.status as reserved_booking_status,
        -- Next booking: after active/reserved booking ends
        next_b.booking_reference as next_booking_ref,
        next_b.id as next_booking_id,
        next_b.guest_name as next_guest,
        next_b.check_in_date as next_checkin,
        next_b.check_out_date as next_checkout,
        next_b.status as next_booking_status
    FROM individual_rooms ir
    LEFT JOIN rooms r ON ir.room_type_id = r.id
    -- Active booking: currently occupied (checked in and stay has started)
    LEFT JOIN bookings active_b ON ir.id = active_b.individual_room_id
        AND active_b.status IN ('confirmed', 'checked-in')
        AND active_b.check_in_date <= CURDATE()
        AND active_b.check_out_date >= CURDATE()
    -- Reserved booking: future confirmed booking (stay hasn't started yet)
    LEFT JOIN bookings reserved_b ON ir.id = reserved_b.individual_room_id
        AND reserved_b.status IN ('confirmed', 'checked-in')
        AND reserved_b.check_in_date > CURDATE()
        AND reserved_b.check_in_date = (
            SELECT MIN(check_in_date)
            FROM bookings b2
            WHERE b2.individual_room_id = ir.id
            AND b2.status IN ('confirmed', 'checked-in')
            AND b2.check_in_date > CURDATE()
        )
    -- Next booking: earliest future booking from today (includes reserved bookings)
    LEFT JOIN bookings next_b ON ir.id = next_b.individual_room_id
        AND next_b.status IN ('confirmed', 'checked-in')
        AND next_b.check_in_date > CURDATE()
        AND next_b.check_in_date = (
            SELECT MIN(check_in_date)
            FROM bookings b3
            WHERE b3.individual_room_id = ir.id
            AND b3.status IN ('confirmed', 'checked-in')
            AND b3.check_in_date > CURDATE()
        )
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY r.name ASC, ir.floor ASC, ir.room_number ASC
");
$stmt->execute($params);
$individualRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build amenities & photos maps for edit modal
$roomAmenitiesMap = [];
$amenitiesStmt = $pdo->query("SELECT individual_room_id, amenity_label, amenity_key FROM individual_room_amenities ORDER BY display_order ASC, id ASC");
foreach ($amenitiesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $label = $row['amenity_label'] ?: $row['amenity_key'];
    $roomAmenitiesMap[$row['individual_room_id']][] = $label;
}

$roomPhotosMap = [];
$photosStmt = $pdo->query("SELECT individual_room_id, image_path, is_primary FROM individual_room_photos WHERE is_active = 1 ORDER BY is_primary DESC, display_order ASC, id ASC");
foreach ($photosStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $roomPhotosMap[$row['individual_room_id']][] = $row['image_path'];
}

// Get room types for dropdown
$roomTypesStmt = $pdo->query("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name");
$roomTypes = $roomTypesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique floors for filter
$floorsStmt = $pdo->query("SELECT DISTINCT floor FROM individual_rooms WHERE floor IS NOT NULL AND floor != '' ORDER BY floor");
$floors = $floorsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get status summary
$summaryStmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM individual_rooms
    WHERE is_active = 1
    GROUP BY status
");
$statusSummary = [];
foreach ($summaryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusSummary[$row['status']] = $row['count'];
}

$currency = htmlspecialchars(getSetting('currency_symbol', 'MWK'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Rooms Management - Admin Panel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/individual-rooms.css"></head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>

    <div class="content">
        <div class="page-header">
            <h2><i class="fas fa-door-open"></i> Individual Rooms Management</h2>
            <button class="btn btn-primary" type="button" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Individual Room
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Status Summary -->
        <div class="status-summary">
            <div class="status-card status-available">
                <div class="icon"><i class="fas fa-check"></i></div>
                <div>
                    <div class="count"><?php echo $statusSummary['available'] ?? 0; ?></div>
                    <div class="label">Available</div>
                </div>
            </div>
            <div class="status-card status-occupied">
                <div class="icon"><i class="fas fa-user"></i></div>
                <div>
                    <div class="count"><?php echo $statusSummary['occupied'] ?? 0; ?></div>
                    <div class="label">Occupied</div>
                </div>
            </div>
            <div class="status-card status-cleaning">
                <div class="icon"><i class="fas fa-broom"></i></div>
                <div>
                    <div class="count"><?php echo $statusSummary['cleaning'] ?? 0; ?></div>
                    <div class="label">Cleaning</div>
                </div>
            </div>
            <div class="status-card status-maintenance">
                <div class="icon"><i class="fas fa-tools"></i></div>
                <div>
                    <div class="count"><?php echo $statusSummary['maintenance'] ?? 0; ?></div>
                    <div class="label">Maintenance</div>
                </div>
            </div>
            <div class="status-card status-out_of_order">
                <div class="icon"><i class="fas fa-ban"></i></div>
                <div>
                    <div class="count"><?php echo $statusSummary['out_of_order'] ?? 0; ?></div>
                    <div class="label">Out of Order</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
                <select name="room_type" onchange="this.form.submit()">
                    <option value="">All Room Types</option>
                    <?php foreach ($roomTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $filter_room_type == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="occupied" <?php echo $filter_status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                    <option value="cleaning" <?php echo $filter_status === 'cleaning' ? 'selected' : ''; ?>>Cleaning</option>
                    <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="out_of_order" <?php echo $filter_status === 'out_of_order' ? 'selected' : ''; ?>>Out of Order</option>
                </select>
                <?php if (!empty($floors)): ?>
                <select name="floor" onchange="this.form.submit()">
                    <option value="">All Floors</option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?php echo htmlspecialchars($floor); ?>" <?php echo $filter_floor === $floor ? 'selected' : ''; ?>>
                            Floor <?php echo htmlspecialchars($floor); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions hidden" id="bulkActions">
            <span id="selectedCount">0 selected</span>
            <select id="bulkStatus">
                <option value="">Change status to...</option>
                <option value="available">Available</option>
                <option value="occupied">Occupied</option>
                <option value="cleaning">Cleaning</option>
                <option value="maintenance">Maintenance</option>
                <option value="out_of_order">Out of Order</option>
            </select>
            <button class="btn btn-primary btn-sm" onclick="applyBulkStatus()">
                <i class="fas fa-check"></i> Apply
            </button>
        </div>

        <!-- Rooms Table -->
        <div class="rooms-table">
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_status_change">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Floor</th>
                            <th>Status</th>
                            <th>Current Booking</th>
                            <th>Next Booking</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($individualRooms)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-door-open" style="font-size: 48px; margin-bottom: 16px; display: block; color: #ddd;"></i>
                                    No individual rooms found. Click "Add Individual Room" to create one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($individualRooms as $room): ?>
                                <?php
                                // Compute timeline-aware display status
                                $displayStatus = $room['status'];
                                $statusIcon = 'check';
                                $statusLabel = ucfirst(str_replace('_', ' ', $room['status']));
                                $hasActiveBooking = !empty($room['active_booking_ref']);
                                $hasReservedBooking = !empty($room['reserved_booking_ref']);
                                
                                // Timeline-aware status override
                                if ($hasActiveBooking) {
                                    // Room has an active checked-in booking (stay in progress)
                                    $displayStatus = 'occupied';
                                    $statusIcon = 'user';
                                    $statusLabel = 'Occupied';
                                } elseif ($hasReservedBooking) {
                                    // Room has a future confirmed booking (reserved/upcoming)
                                    // Show as available now with future booking details in booking columns
                                    $displayStatus = 'available';
                                    $statusIcon = 'check';
                                    $statusLabel = 'Available';
                                } else {
                                    // No active or future bookings - use physical status
                                    switch ($room['status']) {
                                        case 'available':
                                            $statusIcon = 'check';
                                            $statusLabel = 'Available';
                                            break;
                                        case 'occupied':
                                            $statusIcon = 'user';
                                            $statusLabel = 'Occupied';
                                            break;
                                        case 'cleaning':
                                            $statusIcon = 'broom';
                                            $statusLabel = 'Cleaning';
                                            break;
                                        case 'maintenance':
                                            $statusIcon = 'tools';
                                            $statusLabel = 'Maintenance';
                                            break;
                                        case 'out_of_order':
                                            $statusIcon = 'ban';
                                            $statusLabel = 'Out of Order';
                                            break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="room_ids[]" value="<?php echo $room['id']; ?>" onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <div class="room-number"><?php echo htmlspecialchars($room['room_number']); ?></div>
                                        <?php if ($room['room_name']): ?>
                                            <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($room['room_type_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo $room['floor'] ? htmlspecialchars($room['floor']) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $displayStatus; ?>">
                                            <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($hasActiveBooking): ?>
                                            <div class="current-booking">
                                                <a href="booking-details.php?id=<?php echo $room['active_booking_id']; ?>">
                                                    <?php echo htmlspecialchars($room['active_booking_ref']); ?>
                                                </a>
                                                <br>
                                                <small><?php echo htmlspecialchars($room['active_guest']); ?></small>
                                                <br>
                                                <small><?php echo $room['active_checkin']; ?> &rarr; <?php echo $room['active_checkout']; ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($room['next_booking_ref']): ?>
                                            <div class="next-booking">
                                                <a href="booking-details.php?id=<?php echo $room['next_booking_id']; ?>">
                                                    <?php echo htmlspecialchars($room['next_booking_ref']); ?>
                                                </a>
                                                <br>
                                                <small><?php echo htmlspecialchars($room['next_guest']); ?></small>
                                                <br>
                                                <small><?php echo $room['next_checkin']; ?> &rarr; <?php echo $room['next_checkout']; ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <button class="btn btn-info btn-sm" type="button" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($room), ENT_QUOTES, "UTF-8"); ?>, <?php echo htmlspecialchars(json_encode($roomAmenitiesMap[$room['id']] ?? []), ENT_QUOTES, "UTF-8"); ?>, <?php echo htmlspecialchars(json_encode($roomPhotosMap[$room['id']] ?? []), ENT_QUOTES, "UTF-8"); ?>)'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-primary btn-sm" type="button" onclick="openAssignBookingModal(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>', <?php echo $room['room_type_id']; ?>)" title="Assign this room to a booking">
                                                <i class="fas fa-door-open"></i> Assign Booking
                                            </button>
                                            <button class="btn btn-success btn-sm" type="button" onclick="openStatusModal(<?php echo $room['id']; ?>, '<?php echo $room['status']; ?>', '<?php echo htmlspecialchars($room['room_number']); ?>')">
                                                <i class="fas fa-exchange-alt"></i> Status
                                            </button>
                                            <button class="btn btn-danger btn-sm" type="button" onclick="confirmDelete(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="roomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus"></i> Add Individual Room</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="roomForm">
                <input type="hidden" name="action" id="formAction" value="add_individual_room">
                <input type="hidden" name="id" id="roomId">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="room_type_id">Room Type *</label>
                            <select name="room_type_id" id="room_type_id" required>
                                <option value="">Select Room Type</option>
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="room_number">Room Number *</label>
                            <input type="text" name="room_number" id="room_number" placeholder="e.g., EXEC-101" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="room_name">Room Name</label>
                            <input type="text" name="room_name" id="room_name" placeholder="e.g., Executive Room 1">
                        </div>
                        <div class="form-group">
                            <label for="floor">Floor</label>
                            <input type="text" name="floor" id="floor" placeholder="e.g., 1">
                        </div>
                    </div>
                    <div class="form-row" id="statusRow" style="display: none;">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="out_of_order">Out of Order</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="display_order">Display Order</label>
                            <input type="number" name="display_order" id="display_order" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label for="child_price_multiplier">Child Supplement Override (%)</label>
                            <input type="number" name="child_price_multiplier" id="child_price_multiplier" step="0.01" min="0" placeholder="Leave blank to use room type default">
                            <div class="field-hint">If blank, booking uses room type child supplement from room-management.</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="single_occupancy_enabled_override">Single Occupancy Override</label>
                            <select name="single_occupancy_enabled_override" id="single_occupancy_enabled_override">
                                <option value="">Inherit room type</option>
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="double_occupancy_enabled_override">Double Occupancy Override</label>
                            <select name="double_occupancy_enabled_override" id="double_occupancy_enabled_override">
                                <option value="">Inherit room type</option>
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="triple_occupancy_enabled_override">Triple Occupancy Override</label>
                            <select name="triple_occupancy_enabled_override" id="triple_occupancy_enabled_override">
                                <option value="">Inherit room type</option>
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="children_allowed_override">Children Allowed Override</label>
                            <select name="children_allowed_override" id="children_allowed_override">
                                <option value="">Inherit room type</option>
                                <option value="1">Allowed</option>
                                <option value="0">Not allowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="Any special notes about this room..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="amenities_list">Amenities (comma separated)</label>
                        <textarea name="amenities_list" id="amenities_list" rows="2" placeholder="Wi-Fi, Air Conditioning, Sea View"></textarea>
                        <div class="field-hint">These will be stored per room and override any defaults.</div>
                    </div>
                    <div class="form-group">
                        <label for="photos_list">Photo URLs (one per line or comma separated)</label>
                        <textarea name="photos_list" id="photos_list" rows="3" placeholder="/images/rooms/room101-1.jpg"></textarea>
                        <div class="field-hint">First photo becomes the primary image for the room.</div>
                    </div>
                    <div class="form-group" id="activeRow" style="display: none;">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="is_active" id="is_active" checked>
                            <label for="is_active">Active (room is available for booking)</label>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Room</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exchange-alt"></i> Change Room Status</h3>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="statusRoomId">
                <div class="modal-body">
                    <p>Changing status for room: <strong id="statusRoomNumber"></strong></p>
                    <div class="form-group">
                        <label for="new_status">New Status</label>
                        <select name="new_status" id="new_status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="cleaning">Cleaning</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="out_of_order">Out of Order</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason (optional)</label>
                        <textarea name="reason" id="reason" rows="2" placeholder="Reason for status change..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Booking Modal -->
    <div class="modal-overlay" id="assignBookingModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-door-open"></i> Assign Room to Booking</h3>
                <button class="modal-close" onclick="closeAssignBookingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Room:</label>
                    <input type="text" id="assign_room_number" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Available Bookings:</label>
                    <div id="assign_booking_list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; padding: 10px;">
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <i class="fas fa-spinner fa-spin"></i> Loading bookings...
                        </div>
                    </div>
                </div>
                <input type="hidden" id="assign_room_id">
                <input type="hidden" id="assign_booking_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAssignBookingModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAssignBooking()"><i class="fas fa-check"></i> Assign to Selected Booking</button>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete_individual_room">
        <input type="hidden" name="id" id="deleteRoomId">
    </form>

    <script>
        // Add Modal
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Individual Room';
            document.getElementById('formAction').value = 'add_individual_room';
            document.getElementById('roomForm').reset();
            document.getElementById('roomId').value = '';
            document.getElementById('child_price_multiplier').value = '';
            document.getElementById('single_occupancy_enabled_override').value = '';
            document.getElementById('double_occupancy_enabled_override').value = '';
            document.getElementById('triple_occupancy_enabled_override').value = '';
            document.getElementById('children_allowed_override').value = '';
            document.getElementById('statusRow').style.display = 'grid';
            document.getElementById('activeRow').style.display = 'none';
            document.getElementById('roomModal').classList.add('active');
        }

        // Edit Modal
        function openEditModal(room, amenities, photos) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Individual Room';
            document.getElementById('formAction').value = 'update_individual_room';
            document.getElementById('roomId').value = room.id;
            document.getElementById('room_type_id').value = room.room_type_id;
            document.getElementById('room_number').value = room.room_number;
            document.getElementById('room_name').value = room.room_name || '';
            document.getElementById('floor').value = room.floor || '';
            document.getElementById('status').value = room.status;
            document.getElementById('display_order').value = room.display_order || 0;
            document.getElementById('child_price_multiplier').value = room.child_price_multiplier ?? '';
            document.getElementById('single_occupancy_enabled_override').value = room.single_occupancy_enabled_override ?? '';
            document.getElementById('double_occupancy_enabled_override').value = room.double_occupancy_enabled_override ?? '';
            document.getElementById('triple_occupancy_enabled_override').value = room.triple_occupancy_enabled_override ?? '';
            document.getElementById('children_allowed_override').value = room.children_allowed_override ?? '';
            document.getElementById('notes').value = room.notes || '';
            document.getElementById('amenities_list').value = (amenities || []).join(', ');
            document.getElementById('photos_list').value = (photos || []).join('\n');
            document.getElementById('is_active').checked = room.is_active == 1;
            document.getElementById('statusRow').style.display = 'grid';
            document.getElementById('activeRow').style.display = 'block';
            document.getElementById('roomModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('roomModal').classList.remove('active');
        }

        // Status Modal
        function openStatusModal(roomId, currentStatus, roomNumber) {
            document.getElementById('statusRoomId').value = roomId;
            document.getElementById('statusRoomNumber').textContent = roomNumber;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }

        // Delete
        function confirmDelete(roomId, roomNumber) {
            if (confirm('Are you sure you want to delete room "' + roomNumber + '"?\n\nThis action cannot be undone.')) {
                document.getElementById('deleteRoomId').value = roomId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Bulk Actions
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="room_ids[]"]');
            const selectAll = document.getElementById('selectAll').checked;
            checkboxes.forEach(cb => cb.checked = selectAll);
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('input[name="room_ids[]"]:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count + ' selected';
            document.getElementById('bulkActions').classList.toggle('hidden', count === 0);
        }

        function applyBulkStatus() {
            const status = document.getElementById('bulkStatus').value;
            if (!status) {
                alert('Please select a status.');
                return;
            }
            const checkboxes = document.querySelectorAll('input[name="room_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one room.');
                return;
            }
            if (confirm('Change status of ' + checkboxes.length + ' room(s) to "' + status + '"?')) {
                document.getElementById('bulkStatus').value = status;
                document.querySelector('#bulkForm input[name="bulk_status"]')?.remove();
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_status';
                input.value = status;
                document.getElementById('bulkForm').appendChild(input);
                document.getElementById('bulkForm').submit();
            }
        }

        // Assign Booking Modal Functions
        let selectedBookingId = null;

        function openAssignBookingModal(roomId, roomNumber, roomTypeId) {
            document.getElementById('assign_room_id').value = roomId;
            document.getElementById('assign_room_number').value = roomNumber;
            document.getElementById('assign_booking_id').value = '';
            selectedBookingId = null;
            document.getElementById('assignBookingModal').classList.add('active');
            loadAssignableBookings(roomId, roomTypeId);
        }

        function closeAssignBookingModal() {
            document.getElementById('assignBookingModal').classList.remove('active');
            document.getElementById('assign_booking_list').innerHTML = '';
            selectedBookingId = null;
        }

        function loadAssignableBookings(roomId, roomTypeId) {
            const bookingList = document.getElementById('assign_booking_list');
            bookingList.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Loading bookings...</div>';

            const formData = new FormData();
            formData.append('action', 'get_assignable_bookings');
            formData.append('room_type_id', roomTypeId);
            formData.append('individual_room_id', roomId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        bookingList.innerHTML = '';
                        data.data.forEach(booking => {
                            const bookingCard = document.createElement('div');
                            bookingCard.className = 'booking-assign-card';
                            bookingCard.dataset.bookingId = booking.id;
                            bookingCard.style.cssText = `
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding: 12px;
                                margin-bottom: 8px;
                                border: 2px solid ${booking.already_assigned ? '#ffc107' : '#28a745'};
                                border-radius: 8px;
                                cursor: pointer;
                                background: #fff;
                                transition: all 0.2s;
                            `;
                            
                            const bookingInfo = `
                                <div>
                                    <div style="font-weight: 600; color: var(--navy);">
                                        <i class="fas fa-calendar-check" style="color: var(--gold);"></i>
                                        ${booking.reference}
                                    </div>
                                    <small style="color: #666;">${booking.guest_name} • ${booking.room_name}</small><br>
                                    <small style="color: #666;">${booking.check_in} → ${booking.check_out} (${booking.status})</small>
                                </div>
                                <div>
                                    ${booking.already_assigned
                                        ? `<span class="badge" style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 11px;">Already Assigned</span>`
                                        : `<span class="badge" style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 11px;">Available</span>`
                                    }
                                </div>
                            `;
                            bookingCard.innerHTML = bookingInfo;
                            bookingCard.onclick = () => selectBookingForAssignment(booking.id, bookingCard);
                            bookingList.appendChild(bookingCard);
                        });
                    } else {
                        bookingList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> No assignable bookings found.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading bookings:', error);
                    bookingList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-circle"></i> Error loading bookings.</div>';
                });
        }

        function selectBookingForAssignment(bookingId, cardElement) {
            selectedBookingId = bookingId;
            document.getElementById('assign_booking_id').value = bookingId;
            
            // Remove previous selection
            document.querySelectorAll('.booking-assign-card').forEach(card => {
                card.style.background = '#fff';
                card.style.borderColor = card.dataset.alreadyAssigned === 'true' ? '#ffc107' : '#28a745';
            });
            
            // Highlight selected card
            cardElement.style.background = '#fff8e1';
            cardElement.style.borderColor = 'var(--gold)';
        }

        function submitAssignBooking() {
            if (!selectedBookingId) {
                alert('Please select a booking to assign.');
                return;
            }
            
            const roomId = document.getElementById('assign_room_id').value;
            const bookingId = selectedBookingId;

            const formData = new FormData();
            formData.append('action', 'assign_individual_room');
            formData.append('booking_id', bookingId);
            formData.append('individual_room_id', roomId);

            fetch('bookings.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Room assigned successfully!');
                        closeAssignBookingModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert(data.message || 'Failed to assign room.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error assigning room.');
                });
        }

        // Close modals on outside click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>

    <?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>
