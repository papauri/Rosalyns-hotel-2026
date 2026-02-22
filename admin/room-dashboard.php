<?php
/**
 * Room Management Dashboard
 * Comprehensive room status and housekeeping overview
 */
require_once 'admin-init.php';
require_once '../includes/room-management.php';

$message = '';
$error = '';

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_clean':
                $room_id = (int)$_POST['room_id'];
                $result = markRoomClean($room_id, $user['id'], ['notes' => $_POST['notes'] ?? '']);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'pass_inspection':
                $room_id = (int)$_POST['room_id'];
                $result = passRoomInspection($room_id, $user['id'], ['notes' => $_POST['notes'] ?? '']);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'fail_inspection':
                $room_id = (int)$_POST['room_id'];
                $result = failRoomInspection($room_id, $user['id'], $_POST['reason'] ?? 'Failed inspection');
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_status':
                $room_id = (int)$_POST['room_id'];
                $new_status = $_POST['new_status'];
                $result = updateRoomStatus($room_id, $new_status, $_POST['reason'] ?? 'Manual update', $user['id']);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get dashboard data
$summary = getRoomDashboardSummary();
$cleaningQueue = getRoomsRequiringHousekeeping('cleaning');
$inspectionQueue = getRoomsRequiringInspection();
$allRooms = getRoomsRequiringHousekeeping('all');

// Get room statuses for display
$roomStatuses = getRoomStatuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management Dashboard - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <style>
        .room-dashboard {
            padding: 24px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            margin-bottom: 24px;
        }
        
        .dashboard-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            color: var(--navy);
            margin: 0 0 8px 0;
        }
        
        .dashboard-header p {
            color: #666;
            margin: 0;
        }
        
        /* Status Overview Cards */
        .status-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .status-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        
        .status-card .icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .status-card .info h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: var(--navy);
        }
        
        .status-card .info span {
            font-size: 13px;
            color: #666;
        }
        
        .status-card.available .icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-card.occupied .icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .status-card.cleaning .icon { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .status-card.inspection .icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .status-card.maintenance .icon { background: rgba(253, 126, 20, 0.1); color: #fd7e14; }
        .status-card.out_of_order .icon { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        
        /* Today's Stats */
        .today-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 900px) {
            .today-stats { grid-template-columns: repeat(2, 1fr); }
        }
        
        .today-stat {
            background: linear-gradient(135deg, var(--navy) 0%, #2A2A2A 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        
        .today-stat .value {
            font-size: 36px;
            font-weight: 700;
            color: var(--gold);
        }
        
        .today-stat .label {
            font-size: 13px;
            opacity: 0.8;
        }
        
        /* Queue Sections */
        .queue-section {
            background: white;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .queue-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .queue-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .queue-header h2 .badge {
            font-family: 'Jost', sans-serif;
            font-size: 12px;
            background: var(--gold);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
        }
        
        .queue-body {
            padding: 0;
        }
        
        /* Room Queue Item */
        .room-queue-item {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #f8f8f8;
            gap: 16px;
            transition: background 0.2s;
        }
        
        .room-queue-item:hover {
            background: #f8f9fb;
        }
        
        .room-queue-item:last-child {
            border-bottom: none;
        }
        
        .room-number-badge {
            width: 80px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .room-number-badge .number {
            font-size: 18px;
            font-weight: 700;
            color: var(--navy);
        }
        
        .room-number-badge .type {
            font-size: 11px;
            color: #888;
        }
        
        .room-info {
            flex: 1;
        }
        
        .room-info .guest {
            font-weight: 500;
            color: var(--navy);
        }
        
        .room-info .details {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .room-status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .room-status-badge.cleaning { background: #fff3cd; color: #856404; }
        .room-status-badge.inspection { background: #d1ecf1; color: #0c5460; }
        .room-status-badge.available { background: #d4edda; color: #155724; }
        .room-status-badge.occupied { background: #f8d7da; color: #721c24; }
        .room-status-badge.urgent { background: #dc3545; color: white; }
        
        .room-actions {
            display: flex;
            gap: 8px;
        }
        
        .room-actions button {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .room-actions .btn-success {
            background: #28a745;
            color: white;
        }
        
        .room-actions .btn-success:hover {
            background: #208637;
        }
        
        .room-actions .btn-primary {
            background: var(--gold);
            color: white;
        }
        
        .room-actions .btn-primary:hover {
            background: #6f5b43;
        }
        
        .room-actions .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .room-actions .btn-danger:hover {
            background: #c82333;
        }
        
        .room-actions .btn-secondary {
            background: #e9ecef;
            color: #495057;
        }
        
        .room-actions .btn-secondary:hover {
            background: #dee2e6;
        }
        
        /* Priority indicator */
        .priority-indicator {
            width: 4px;
            height: 40px;
            border-radius: 2px;
            flex-shrink: 0;
        }
        
        .priority-indicator.urgent { background: #dc3545; }
        .priority-indicator.high { background: #fd7e14; }
        .priority-indicator.normal { background: #28a745; }
        
        /* Empty state */
        .empty-queue {
            padding: 48px 24px;
            text-align: center;
            color: #888;
        }
        
        .empty-queue i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #ddd;
        }
        
        .empty-queue p {
            margin: 0;
        }
        
        /* Time since */
        .time-since {
            font-size: 11px;
            color: #888;
        }
        
        /* Occupancy bar */
        .occupancy-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        
        .occupancy-bar {
            height: 24px;
            background: #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
        }
        
        .occupancy-segment {
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .occupancy-legend {
            display: flex;
            gap: 24px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--navy);
        }
        
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Jost', sans-serif;
        }
        
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-submit {
            background: var(--gold);
            color: white;
        }
    </style>
</head>
<body>
<?php require_once 'includes/admin-header.php'; ?>

<div class="room-dashboard">
    <div class="dashboard-header">
        <h1><i class="fas fa-door-open"></i> Room Management Dashboard</h1>
        <p>Real-time room status and housekeeping management</p>
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
    
    <!-- Today's Stats -->
    <div class="today-stats">
        <div class="today-stat">
            <div class="value"><?php echo $summary['checkouts_today'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-sign-out-alt"></i> Check-outs Today</div>
        </div>
        <div class="today-stat">
            <div class="value"><?php echo $summary['checkins_today'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-sign-in-alt"></i> Check-ins Today</div>
        </div>
        <div class="today-stat">
            <div class="value"><?php echo $summary['cleaning_queue'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-broom"></i> Rooms to Clean</div>
        </div>
        <div class="today-stat">
            <div class="value"><?php echo $summary['available_now'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-check-circle"></i> Available Now</div>
        </div>
    </div>
    
    <!-- Room Status Overview -->
    <div class="status-overview">
        <?php foreach ($roomStatuses as $status => $info): ?>
        <?php $count = $summary['status_counts'][$status] ?? 0; ?>
        <div class="status-card <?php echo $status; ?>">
            <div class="icon">
                <i class="fas <?php echo $info['icon']; ?>"></i>
            </div>
            <div class="info">
                <h3><?php echo $count; ?></h3>
                <span><?php echo $info['label']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Occupancy Bar -->
    <div class="occupancy-section">
        <h3 style="margin: 0 0 16px 0; font-family: 'Cormorant Garamond', serif; font-size: 20px;">
            Room Occupancy: <?php echo $summary['occupancy_rate'] ?? 0; ?>%
        </h3>
        <div class="occupancy-bar">
            <?php 
            $total = array_sum($summary['status_counts'] ?? []);
            if ($total > 0):
                $colors = [
                    'occupied' => '#dc3545',
                    'available' => '#28a745', 
                    'cleaning' => '#ffc107',
                    'inspection' => '#17a2b8',
                    'maintenance' => '#fd7e14',
                    'out_of_order' => '#6c757d'
                ];
                foreach ($summary['status_counts'] ?? [] as $status => $count):
                    $percent = ($count / $total) * 100;
            ?>
            <div class="occupancy-segment" style="width: <?php echo $percent; ?>%; background: <?php echo $colors[$status] ?? '#ccc'; ?>;"></div>
            <?php 
                endforeach;
            endif;
            ?>
        </div>
        <div class="occupancy-legend">
            <?php foreach ($roomStatuses as $status => $info): ?>
            <div class="legend-item">
                <span class="legend-dot" style="background: <?php echo $info['color']; ?>;"></span>
                <span><?php echo $info['label']; ?>: <?php echo $summary['status_counts'][$status] ?? 0; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Cleaning Queue -->
    <div class="queue-section">
        <div class="queue-header">
            <h2>
                <i class="fas fa-broom" style="color: var(--gold);"></i>
                Cleaning Queue
                <span class="badge"><?php echo count($cleaningQueue); ?></span>
            </h2>
        </div>
        <div class="queue-body">
            <?php if (empty($cleaningQueue)): ?>
            <div class="empty-queue">
                <i class="fas fa-check-circle"></i>
                <p>All rooms are clean!</p>
            </div>
            <?php else: ?>
            <?php foreach ($cleaningQueue as $room): ?>
            <div class="room-queue-item">
                <div class="priority-indicator <?php echo $room['priority'] ?? 'normal'; ?>"></div>
                <div class="room-number-badge">
                    <div class="number"><?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="type"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></div>
                </div>
                <div class="room-info">
                    <div class="guest">
                        <?php if ($room['last_guest']): ?>
                            Last: <?php echo htmlspecialchars($room['last_guest']); ?>
                        <?php else: ?>
                            No recent guest
                        <?php endif; ?>
                    </div>
                    <div class="details">
                        <?php if ($room['last_checkout']): ?>
                            Checked out: <?php echo date('M j, g:i A', strtotime($room['last_checkout'])); ?>
                        <?php endif; ?>
                        <?php if ($room['hk_notes']): ?>
                            <br><em><?php echo htmlspecialchars($room['hk_notes']); ?></em>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($room['hk_status']): ?>
                <span class="room-status-badge <?php echo $room['hk_status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $room['hk_status'])); ?>
                </span>
                <?php else: ?>
                <span class="room-status-badge cleaning">Needs Cleaning</span>
                <?php endif; ?>
                <?php if ($room['assigned_to_name']): ?>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($room['assigned_to_name']); ?>
                </div>
                <?php endif; ?>
                <div class="room-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_clean">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        <button type="submit" class="btn-success" onclick="return confirm('Mark this room as clean?')">
                            <i class="fas fa-check"></i> Mark Clean
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Inspection Queue -->
    <div class="queue-section">
        <div class="queue-header">
            <h2>
                <i class="fas fa-clipboard-check" style="color: #17a2b8;"></i>
                Inspection Queue
                <span class="badge" style="background: #17a2b8;"><?php echo count($inspectionQueue); ?></span>
            </h2>
        </div>
        <div class="queue-body">
            <?php if (empty($inspectionQueue)): ?>
            <div class="empty-queue">
                <i class="fas fa-clipboard-check"></i>
                <p>No rooms awaiting inspection</p>
            </div>
            <?php else: ?>
            <?php foreach ($inspectionQueue as $room): ?>
            <div class="room-queue-item">
                <div class="room-number-badge">
                    <div class="number"><?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="type"><?php echo htmlspecialchars($room['room_type'] ?? ''); ?></div>
                </div>
                <div class="room-info">
                    <div class="guest">Awaiting Inspection</div>
                    <div class="details">
                        <?php if ($room['cleaning_completed']): ?>
                            Cleaned: <?php echo date('M j, g:i A', strtotime($room['cleaning_completed'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="room-status-badge inspection">Inspection Pending</span>
                <div class="room-actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirmPass(this)">
                        <input type="hidden" name="action" value="pass_inspection">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        <input type="hidden" name="notes" value="">
                        <button type="submit" class="btn-success">
                            <i class="fas fa-check"></i> Pass
                        </button>
                    </form>
                    <button type="button" class="btn-danger" onclick="openFailModal(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')">
                        <i class="fas fa-times"></i> Fail
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Fail Inspection Modal -->
<div class="modal-overlay" id="failModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> Fail Inspection</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="fail_inspection">
            <input type="hidden" name="room_id" id="failRoomId">
            <p>Room: <strong id="failRoomNumber"></strong></p>
            <div class="form-group">
                <label>Reason for Failure *</label>
                <textarea name="reason" rows="3" required placeholder="e.g., Bathroom not properly cleaned, stains on carpet..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeFailModal()">Cancel</button>
                <button type="submit" class="btn-submit" style="background: #dc3545;">Fail & Send to Reclean</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFailModal(roomId, roomNumber) {
    document.getElementById('failRoomId').value = roomId;
    document.getElementById('failRoomNumber').textContent = roomNumber;
    document.getElementById('failModal').classList.add('active');
}

function closeFailModal() {
    document.getElementById('failModal').classList.remove('active');
}

function confirmPass(form) {
    return confirm('Pass inspection and make this room available?');
}

// Close modal on outside click
document.getElementById('failModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeFailModal();
    }
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>