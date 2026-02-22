<?php
/**
 * Blocked Dates Management Page
 * Hotel Website - Admin Panel
 *
 * Allows administrators to block/unblock room dates at two levels:
 * 1. Room Type Level (blocks all rooms of a type)
 * 2. Individual Room Level (blocks specific individual rooms)
 */

// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

require_once '../includes/modal.php';
require_once '../includes/alert.php';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'block_date') {
        $block_scope = $_POST['block_scope'] ?? 'type'; // 'type' or 'individual'
        
        if ($block_scope === 'individual') {
            // Individual room blocking
            $individual_room_id = !empty($_POST['individual_room_id']) ? (int)$_POST['individual_room_id'] : null;
            $block_date = $_POST['block_date'] ?? '';
            $block_type = $_POST['block_type'] ?? 'manual';
            $reason = $_POST['reason'] ?? null;
            $created_by = $user['id'] ?? null;
            
            if (empty($individual_room_id) || empty($block_date)) {
                $message = 'Please select a room and date to block';
                $messageType = 'error';
            } else {
                $result = blockIndividualRoomDate($individual_room_id, $block_date, $block_type, $reason, $created_by);
                
                if ($result) {
                    $message = 'Individual room date blocked successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to block date';
                    $messageType = 'error';
                }
            }
        } else {
            // Room type blocking
            $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            $block_date = $_POST['block_date'] ?? '';
            $block_type = $_POST['block_type'] ?? 'manual';
            $reason = $_POST['reason'] ?? null;
            $created_by = $user['id'] ?? null;
            
            if (empty($block_date)) {
                $message = 'Please select a date to block';
                $messageType = 'error';
            } else {
                $result = blockRoomDate($room_id, $block_date, $block_type, $reason, $created_by);
                
                if ($result) {
                    $message = 'Room type date blocked successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to block date';
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'unblock_date') {
        $id = (int)$_POST['id'] ?? 0;
        $block_scope = $_POST['block_scope'] ?? 'type';
        
        if ($id > 0) {
            // Get blocked date details
            $blocked_dates = getBlockedDates(null, null, null);
            $target_date = null;
            
            foreach ($blocked_dates as $bd) {
                if ($bd['id'] == $id) {
                    $target_date = $bd;
                    break;
                }
            }
            
            if ($target_date) {
                if ($block_scope === 'individual' && !empty($target_date['individual_room_id'])) {
                    $result = unblockIndividualRoomDate($target_date['individual_room_id'], $target_date['block_date']);
                } else {
                    $result = unblockRoomDate($target_date['room_id'], $target_date['block_date']);
                }
                
                if ($result) {
                    $message = 'Date unblocked successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to unblock date';
                    $messageType = 'error';
                }
            } else {
                $message = 'Blocked date not found';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'block_multiple') {
        $block_scope = $_POST['block_scope'] ?? 'type';
        
        if ($block_scope === 'individual') {
            // Individual room blocking
            $individual_room_id = !empty($_POST['individual_room_id']) ? (int)$_POST['individual_room_id'] : null;
            $dates_json = $_POST['dates'] ?? '';
            $block_type = $_POST['block_type'] ?? 'manual';
            $reason = $_POST['reason'] ?? null;
            $created_by = $user['id'] ?? null;
            
            // Decode JSON dates array
            $dates = !empty($dates_json) ? json_decode($dates_json, true) : [];
            
            if (empty($individual_room_id) || empty($dates) || !is_array($dates)) {
                $message = 'Please select a room and at least one date to block';
                $messageType = 'error';
            } else {
                $blocked_count = blockIndividualRoomDates($individual_room_id, $dates, $block_type, $reason, $created_by);
                
                if ($blocked_count > 0) {
                    $message = "Successfully blocked {$blocked_count} date(s) for individual room";
                    $messageType = 'success';
                } else {
                    $message = 'Failed to block dates';
                    $messageType = 'error';
                }
            }
        } else {
            // Room type blocking
            $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            $dates_json = $_POST['dates'] ?? '';
            $block_type = $_POST['block_type'] ?? 'manual';
            $reason = $_POST['reason'] ?? null;
            $created_by = $user['id'] ?? null;
            
            // Decode JSON dates array
            $dates = !empty($dates_json) ? json_decode($dates_json, true) : [];
            
            if (empty($dates) || !is_array($dates)) {
                $message = 'Please select at least one date to block';
                $messageType = 'error';
            } else {
                $blocked_count = blockRoomDates($room_id, $dates, $block_type, $reason, $created_by);
                
                if ($blocked_count > 0) {
                    $message = "Successfully blocked {$blocked_count} date(s)";
                    $messageType = 'success';
                } else {
                    $message = 'Failed to block dates';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get filter parameters
$filter_room_id = isset($_GET['room_id']) ? ($_GET['room_id'] === 'all' ? null : (int)$_GET['room_id']) : null;
$filter_individual_room_id = isset($_GET['individual_room_id']) ? ($_GET['individual_room_id'] === 'all' ? null : (int)$_GET['individual_room_id']) : null;
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Get blocked dates (both type and individual level)
$blocked_dates = getBlockedDates($filter_room_id, $filter_start_date, $filter_end_date, $filter_individual_room_id);

// Get all rooms for dropdown
$rooms = getCachedRooms();

// Get all individual rooms for dropdown
$individual_rooms = [];
try {
    $stmt = $pdo->query("
        SELECT ir.id, ir.room_number, r.name as room_type_name
        FROM individual_rooms ir
        JOIN rooms r ON ir.room_type_id = r.id
        WHERE ir.is_active = 1
        ORDER BY r.name, ir.room_number
    ");
    $individual_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching individual rooms: " . $e->getMessage());
}

// Get blocked dates for calendar display
$calendar_start = date('Y-m-d', strtotime('-3 months'));
$calendar_end = date('Y-m-d', strtotime('+6 months'));
$calendar_blocked_dates = getBlockedDates(null, $calendar_start, $calendar_end);

// Format blocked dates for calendar - simple array of dates
$blocked_dates_array = [];
foreach ($calendar_blocked_dates as $bd) {
    $blocked_dates_array[] = $bd['block_date'];
}

$site_name = getSetting('site_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Dates | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"></head>
<body>

    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <h2 class="section-title">Blocked Dates Management</h2>
        
        <!-- Info Box -->
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> How to Block Dates</h4>
            <p>Use this page to block specific dates from being booked at two levels:</p>
            <ul>
                <li><strong>Room Type Level:</strong> Blocks all rooms of a specific type (e.g., all Deluxe Rooms) on selected dates</li>
                <li><strong>Individual Room Level:</strong> Blocks a specific individual room (e.g., "Deluxe 101") on selected dates</li>
                <li><strong>Block Single Date:</strong> Click "Block Single Date" button, select level (type/individual), room, date, and reason</li>
                <li><strong>Block Date Range:</strong> Click "Block Date Range" button, select level (type/individual), room, start/end dates, and reason</li>
                <li><strong>Unblock:</strong> Click the "Unblock" button next to any blocked date in the list below</li>
            </ul>
            <p class="mb-0"><strong>Note:</strong> Individual room blocks take precedence over room type blocks. If both exist for the same date, the individual room block will be enforced.</p>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="page-actions">
            <button class="btn btn-primary" data-modal-open="blockSingleDateModal">
                <i class="fas fa-calendar-day"></i> Block Single Date
            </button>
            <button class="btn btn-primary" data-modal-open="blockDateRangeModal">
                <i class="fas fa-calendar-week"></i> Block Date Range
            </button>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filter Blocked Dates
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Room Type</label>
                        <select name="room_id" class="form-select">
                            <option value="all">All Room Types</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>" <?php echo $filter_room_id === $room['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Individual Room</label>
                        <select name="individual_room_id" class="form-select">
                            <option value="all">All Individual Rooms</option>
                            <?php foreach ($individual_rooms as $ir): ?>
                                <option value="<?php echo $ir['id']; ?>" <?php echo $filter_individual_room_id === $ir['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ir['room_type_name'] . ' - ' . $ir['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Blocked Dates List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Blocked Dates 
                <span class="badge bg-secondary"><?php echo count($blocked_dates); ?> dates</span>
            </div>
            <div class="card-body">
                <?php if (empty($blocked_dates)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No blocked dates found</h5>
                        <p class="text-muted">Use the buttons above to block dates</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Scope</th>
                                    <th>Room</th>
                                    <th>Block Type</th>
                                    <th>Reason</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocked_dates as $bd): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar-day text-muted"></i>
                                            <?php echo date('M j, Y', strtotime($bd['block_date'])); ?>
                                        </td>
                                        <td>
                                            <?php if (($bd['block_scope'] ?? 'type') === 'individual'): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-door-open"></i> Individual Room
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-layer-group"></i> Room Type
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($bd['block_scope'] ?? 'type') === 'individual'): ?>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($bd['room_name'] . ' - ' . $bd['individual_room_number']); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php if ($bd['room_id']): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($bd['room_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-dark">All Room Types</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($bd['block_type'] ?? 'manual'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($bd['reason']): ?>
                                                <?php echo htmlspecialchars($bd['reason']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($bd['created_by_name'] ?? 'System'); ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y g:i A', strtotime($bd['created_at'])); ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="unblock_date">
                                                <input type="hidden" name="id" value="<?php echo $bd['id']; ?>">
                                                <input type="hidden" name="block_scope" value="<?php echo $bd['block_scope'] ?? 'type'; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to unblock this date?');">
                                                    <i class="fas fa-unlock"></i> Unblock
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Block Single Date Modal -->
    <div class="modal-overlay" id="blockSingleDateModal-overlay" data-modal-overlay data-close-on-overlay="true"></div>
    <div class="modal" id="blockSingleDateModal" data-modal>
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-day"></i> Block Single Date
                </h5>
                <button type="button" class="btn-close" data-modal-close="blockSingleDateModal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="blockSingleDateForm">
                    <input type="hidden" name="action" value="block_date">
                    <input type="hidden" name="block_scope" id="blockSingleScope" value="type">
                    
                    <div class="mb-3">
                        <label class="form-label">Block Level</label>
                        <select name="block_scope" id="blockSingleScopeSelect" class="form-select" required onchange="toggleBlockSingleLevel()">
                            <option value="type">Room Type Level (All rooms of type)</option>
                            <option value="individual">Individual Room Level (Specific room)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="blockSingleRoomTypeGroup">
                        <label class="form-label">Room Type</label>
                        <select name="room_id" id="blockSingleRoomType" class="form-select">
                            <option value="">All Room Types</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select "All Room Types" to block all room types for this date</small>
                    </div>
                    
                    <div class="mb-3 d-none" id="blockSingleIndividualRoomGroup">
                        <label class="form-label">Individual Room</label>
                        <select name="individual_room_id" id="blockSingleIndividualRoom" class="form-select">
                            <?php foreach ($individual_rooms as $ir): ?>
                                <option value="<?php echo $ir['id']; ?>">
                                    <?php echo htmlspecialchars($ir['room_type_name'] . ' - ' . $ir['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select a specific individual room to block</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date to Block</label>
                        <input type="date" name="block_date" id="singleDateInput" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Block Type</label>
                        <select name="block_type" class="form-select" required>
                            <option value="manual">Manual Block</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="event">Event</option>
                            <option value="full">Fully Booked</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for blocking this date..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-modal-close="blockSingleDateModal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-ban"></i> Block Date
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Block Date Range Modal -->
    <div class="modal-overlay" id="blockDateRangeModal-overlay" data-modal-overlay data-close-on-overlay="true"></div>
    <div class="modal" id="blockDateRangeModal" data-modal>
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-week"></i> Block Date Range
                </h5>
                <button type="button" class="btn-close" data-modal-close="blockDateRangeModal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="blockRangeForm">
                    <input type="hidden" name="action" value="block_multiple">
                    <input type="hidden" name="dates" id="selectedDatesArray">
                    <input type="hidden" name="block_scope" id="blockRangeScope" value="type">
                    
                    <div class="mb-3">
                        <label class="form-label">Block Level</label>
                        <select name="block_scope" id="blockRangeScopeSelect" class="form-select" required onchange="toggleBlockRangeLevel()">
                            <option value="type">Room Type Level (All rooms of type)</option>
                            <option value="individual">Individual Room Level (Specific room)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="blockRangeRoomTypeGroup">
                        <label class="form-label">Room Type</label>
                        <select name="room_id" id="blockRangeRoomType" class="form-select">
                            <option value="">All Room Types</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select "All Room Types" to block all room types for these dates</small>
                    </div>
                    
                    <div class="mb-3 d-none" id="blockRangeIndividualRoomGroup">
                        <label class="form-label">Individual Room</label>
                        <select name="individual_room_id" id="blockRangeIndividualRoom" class="form-select">
                            <?php foreach ($individual_rooms as $ir): ?>
                                <option value="<?php echo $ir['id']; ?>">
                                    <?php echo htmlspecialchars($ir['room_type_name'] . ' - ' . $ir['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select a specific individual room to block</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <input type="text" id="dateRangeInput" class="form-control" placeholder="Select start and end dates">
                        <small class="text-muted">All dates in the range will be blocked</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Selected Dates</label>
                        <div id="selectedDatesDisplay" class="alert alert-info" >
                            <span class="text-muted">No dates selected</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Block Type</label>
                        <select name="block_type" class="form-select" required>
                            <option value="manual">Manual Block</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="event">Event</option>
                            <option value="full">Fully Booked</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for blocking these dates..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-modal-close="blockDateRangeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-ban"></i> Block Dates
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Blocked dates array for disabling in calendar
    const blockedDates = <?php echo json_encode($blocked_dates_array); ?>;
    
    // Initialize single date picker
    flatpickr('#singleDateInput', {
        minDate: 'today',
        dateFormat: 'Y-m-d',
        disable: blockedDates
    });
    
    // Initialize date range picker
    const rangePicker = flatpickr('#dateRangeInput', {
        mode: 'range',
        minDate: 'today',
        dateFormat: 'Y-m-d',
        disable: blockedDates,
        onChange: function(selectedDates, dateStr, instance) {
            const display = document.getElementById('selectedDatesDisplay');
            const input = document.getElementById('selectedDatesArray');
            
            if (selectedDates.length === 2) {
                const startDate = new Date(selectedDates[0]);
                const endDate = new Date(selectedDates[1]);
                const dates = [];
                
                // Generate all dates in range
                const currentDate = new Date(startDate);
                while (currentDate <= endDate) {
                    dates.push(instance.formatDate(currentDate, 'Y-m-d'));
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                
                input.value = JSON.stringify(dates);
                
                if (dates.length <= 10) {
                    display.innerHTML = '<strong>' + dates.length + ' dates:</strong> ' + dates.join(', ');
                } else {
                    display.innerHTML = '<strong>' + dates.length + ' dates:</strong> ' + dates.slice(0, 5).join(', ') + ' ... ' + dates.slice(-2).join(', ');
                }
            } else {
                input.value = '';
                display.innerHTML = '<span class="text-muted">Select start and end dates</span>';
            }
        }
    });
    
    // Toggle between room type and individual room blocking (single date)
    function toggleBlockSingleLevel() {
        const scope = document.getElementById('blockSingleScopeSelect').value;
        const roomTypeGroup = document.getElementById('blockSingleRoomTypeGroup');
        const individualRoomGroup = document.getElementById('blockSingleIndividualRoomGroup');
        const roomTypeSelect = document.getElementById('blockSingleRoomType');
        const individualRoomSelect = document.getElementById('blockSingleIndividualRoom');
        const scopeInput = document.getElementById('blockSingleScope');
        
        scopeInput.value = scope;
        
        if (scope === 'individual') {
            roomTypeGroup.classList.add('d-none');
            individualRoomGroup.classList.remove('d-none');
            roomTypeSelect.removeAttribute('required');
            individualRoomSelect.setAttribute('required', 'required');
        } else {
            roomTypeGroup.classList.remove('d-none');
            individualRoomGroup.classList.add('d-none');
            roomTypeSelect.setAttribute('required', 'required');
            individualRoomSelect.removeAttribute('required');
        }
    }
    
    // Toggle between room type and individual room blocking (date range)
    function toggleBlockRangeLevel() {
        const scope = document.getElementById('blockRangeScopeSelect').value;
        const roomTypeGroup = document.getElementById('blockRangeRoomTypeGroup');
        const individualRoomGroup = document.getElementById('blockRangeIndividualRoomGroup');
        const roomTypeSelect = document.getElementById('blockRangeRoomType');
        const individualRoomSelect = document.getElementById('blockRangeIndividualRoom');
        const scopeInput = document.getElementById('blockRangeScope');
        
        scopeInput.value = scope;
        
        if (scope === 'individual') {
            roomTypeGroup.classList.add('d-none');
            individualRoomGroup.classList.remove('d-none');
            roomTypeSelect.removeAttribute('required');
            individualRoomSelect.setAttribute('required', 'required');
        } else {
            roomTypeGroup.classList.remove('d-none');
            individualRoomGroup.classList.add('d-none');
            roomTypeSelect.setAttribute('required', 'required');
            individualRoomSelect.removeAttribute('required');
        }
    }
    </script>
    <script src="js/admin-components.js"></script>

    <?php require_once 'includes/admin-footer.php'; ?>
