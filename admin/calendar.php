<?php
/**
 * Calendar-Based Room Management
 * Hotel Website - Admin Panel
 */

// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

// Get date parameters
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Validate month
if ($currentMonth < 1) {
    $currentMonth = 12;
    $currentYear--;
} elseif ($currentMonth > 12) {
    $currentMonth = 1;
    $currentYear++;
}

// Get previous and next month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get all individual rooms with room type info
try {
    $stmt = $pdo->query("
        SELECT ir.*, r.name as room_type_name, r.price_per_night, r.slug as room_type_slug
        FROM individual_rooms ir
        INNER JOIN rooms r ON ir.room_type_id = r.id
        WHERE ir.is_active = 1 AND r.is_active = 1
        ORDER BY r.name, ir.display_order ASC, ir.room_number ASC
    ");
    $individualRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching individual rooms: " . $e->getMessage();
    $individualRooms = [];
}

// Get all room types for grouping (optional)
try {
    $stmt = $pdo->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY name");
    $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching room types: " . $e->getMessage();
    $roomTypes = [];
}

// Get blocked dates for current month
$blockedDatesByDate = [];
try {
    $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
    $endDate = sprintf('%04d-%02d-31', $currentYear, $currentMonth);

    $stmt = $pdo->prepare("
        SELECT bd.*, r.name as room_name
        FROM blocked_dates bd
        LEFT JOIN rooms r ON bd.room_id = r.id
        WHERE bd.block_date >= :start_date AND bd.block_date <= :end_date
        ORDER BY bd.block_date ASC, bd.room_id ASC
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $blockedDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group blocked dates by date
    foreach ($blockedDates as $blocked) {
        $dateKey = $blocked['block_date'];
        if (!isset($blockedDatesByDate[$dateKey])) {
            $blockedDatesByDate[$dateKey] = [];
        }
        $blockedDatesByDate[$dateKey][] = $blocked;
    }
} catch (PDOException $e) {
    $error = "Error fetching blocked dates: " . $e->getMessage();
}

// Get bookings for the current month with individual room info
$bookingsByDate = [];
$bookingsByIndividualRoom = []; // Also index by individual room for easier lookup
try {
    $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
    $endDate = sprintf('%04d-%02d-31', $currentYear, $currentMonth);
    
    $stmt = $pdo->prepare("
        SELECT b.*, r.name as room_name, r.id as room_id, r.price_per_night,
               ir.id as individual_room_id, ir.room_number as individual_room_number,
               ir.room_name as individual_room_name, ir.floor as individual_room_floor,
               ir.status as individual_room_status
        FROM bookings b
        INNER JOIN rooms r ON b.room_id = r.id
        LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
        WHERE b.status != 'cancelled'
        AND b.status != 'checked-out'
        AND (
            (b.check_in_date <= :end_date AND b.check_out_date >= :start_date)
        )
        ORDER BY b.check_in_date ASC, r.name ASC, ir.room_number ASC
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group bookings by date and individual room (or room type if no individual room assigned)
    foreach ($bookings as $booking) {
        $checkIn = new DateTime($booking['check_in_date']);
        $checkOut = new DateTime($booking['check_out_date']);
        
        $currentDate = clone $checkIn;
        while ($currentDate < $checkOut) {
            $dateKey = $currentDate->format('Y-m-d');
            
            // Use individual room ID if assigned, otherwise use room type ID
            $roomKey = !empty($booking['individual_room_id'])
                ? 'ir_' . $booking['individual_room_id']
                : 'rt_' . $booking['room_id'];
            
            if (!isset($bookingsByDate[$dateKey])) {
                $bookingsByDate[$dateKey] = [];
            }
            
            if (!isset($bookingsByDate[$dateKey][$roomKey])) {
                $bookingsByDate[$dateKey][$roomKey] = [];
            }
            
            $bookingsByDate[$dateKey][$roomKey][] = $booking;
            $currentDate->modify('+1 day');
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching bookings: " . $e->getMessage();
}

// Helper function to determine timeline-aware status for a room on a specific date
function getTimelineAwareRoomStatus($room, $date, $bookingsByDate) {
    $today = date('Y-m-d');
    $dateKey = $date;
    $roomKey = 'ir_' . $room['id'];
    
    // Check if there's a booking for this room on this date
    if (isset($bookingsByDate[$dateKey][$roomKey])) {
        foreach ($bookingsByDate[$dateKey][$roomKey] as $booking) {
            $checkIn = $booking['check_in_date'];
            $checkOut = $booking['check_out_date'];
            $status = $booking['status'];
            
            // Timeline-aware status logic
            if ($date < $checkIn) {
                // Before check-in date - room is reserved but available
                return 'reserved';
            } elseif ($date >= $checkIn && $date < $checkOut) {
                // During stay - determine status based on booking status and date
                if ($status === 'checked-in' || ($status === 'confirmed' && $date <= $today)) {
                    return 'occupied';
                } else {
                    // Future confirmed booking - reserved
                    return 'reserved';
                }
            }
        }
    }
    
    // No booking - use current physical status
    return $room['status'];
}

// Get days in month
$daysInMonth = date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$firstDayOfWeek = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Month names
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Today's date for highlighting
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Calendar - Admin Panel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/admin-components.css?v=<?php echo time(); ?>">
<body>

    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <h2 class="section-title">📅 Room Calendar</h2>
        
        <div class="calendar-actions mb-3">
            <a href="bookings.php">← Back to Bookings</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="calendar-container">
            <div class="calendar-header">
                <h2><?php echo $monthNames[$currentMonth] . ' ' . $currentYear; ?></h2>
                <div class="calendar-nav">
                    <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>">← Previous</a>
                    <span class="current">Current Month</span>
                    <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>">Next →</a>
                </div>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color reserved"></div>
                    <span>Reserved (Future Booking)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color occupied"></div>
                    <span>Occupied</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color cleaning"></div>
                    <span>Cleaning</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color maintenance"></div>
                    <span>Maintenance</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color blocked"></div>
                    <span>Blocked</span>
                </div>
            </div>
            
            <?php if (!empty($individualRooms)): ?>
                <div class="room-calendars">
                    <?php foreach ($individualRooms as $indRoom): ?>
                        <?php
                            $roomKey = 'ir_' . $indRoom['id'];
                            $roomNumber = htmlspecialchars($indRoom['room_number']);
                            $roomName = htmlspecialchars($indRoom['room_name'] ?? '');
                            $roomTypeName = htmlspecialchars($indRoom['room_type_name']);
                            $floor = htmlspecialchars($indRoom['floor'] ?? '');
                            $displayTitle = $roomNumber . ($roomName ? ' - ' . $roomName : '') . ' (' . $roomTypeName . ')';
                            if ($floor) {
                                $displayTitle .= ' [Floor ' . $floor . ']';
                            }
                        ?>
                        <div class="room-calendar individual-room-calendar">
                            <div class="room-header">
                                <h3><?php echo $displayTitle; ?></h3>
                                <span class="room-price">
                                    <?php echo getSetting('currency_symbol', 'MWK') . ' ' . number_format($indRoom['price_per_night'], 0); ?>/night
                                </span>
                                <span class="current-status-badge status-<?php echo $indRoom['status']; ?>">
                                    <?php echo ucfirst($indRoom['status']); ?>
                                </span>
                            </div>
                            
                            <div class="calendar-grid">
                                <!-- Day headers -->
                                <div class="calendar-day-header">Sun</div>
                                <div class="calendar-day-header">Mon</div>
                                <div class="calendar-day-header">Tue</div>
                                <div class="calendar-day-header">Wed</div>
                                <div class="calendar-day-header">Thu</div>
                                <div class="calendar-day-header">Fri</div>
                                <div class="calendar-day-header">Sat</div>
                                
                                <!-- Empty days before first day of month -->
                                <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                                    <div class="calendar-day empty"></div>
                                <?php endfor; ?>
                                
                                <!-- Days of the month -->
                                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                    <?php
                                        $dateKey = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                        $isToday = ($dateKey === $today);
                                        // Get timeline-aware status for this date
                                        $timelineStatus = getTimelineAwareRoomStatus($indRoom, $dateKey, $bookingsByDate);
                                        $statusClass = 'status-' . $timelineStatus;
                                    ?>
                                    <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?> <?php echo $statusClass; ?>">
                                        <div class="day-number"><?php echo $day; ?></div>
                                          
                                        <?php
                                            // Check if this date is blocked for this room type or all rooms
                                            $isBlocked = false;
                                            if (isset($blockedDatesByDate[$dateKey])) {
                                                foreach ($blockedDatesByDate[$dateKey] as $blocked) {
                                                    // Check if blocked for this specific room type or all rooms
                                                    if ($blocked['room_id'] == $indRoom['room_type_id'] || $blocked['room_id'] === null) {
                                                        $isBlocked = true;
                                                        $blockType = htmlspecialchars($blocked['block_type']);
                                                        $blockReason = htmlspecialchars($blocked['reason'] ?? 'No reason provided');
                                                        ?>
                                                        <div class="blocked-indicator"
                                                             title="Blocked: <?php echo $blockType; ?> - <?php echo $blockReason; ?>"
                                                             onclick="window.location.href='blocked-dates.php'">
                                                            <?php echo ucfirst($blockType); ?>
                                                        </div>
                                                        <?php
                                                    }
                                                }
                                            }
                                            
                                            // Show bookings if date is not blocked and has bookings
                                            if (!$isBlocked && isset($bookingsByDate[$dateKey][$roomKey])) {
                                                $dayBookings = $bookingsByDate[$dateKey][$roomKey];
                                                foreach ($dayBookings as $booking) {
                                                    $statusClass = strtolower(str_replace('-', '_', $booking['status']));
                                                    $guestName = htmlspecialchars($booking['guest_name'], ENT_QUOTES, 'UTF-8');
                                                    $ref = htmlspecialchars($booking['booking_reference'], ENT_QUOTES, 'UTF-8');
                                                    $checkInDate = $booking['check_in_date'];
                                                    $checkOutDate = $booking['check_out_date'];
                                                    $checkIn = date('M j, Y', strtotime($checkInDate));
                                                    $checkOut = date('M j, Y', strtotime($checkOutDate));
                                                    
                                                    // Calculate nights
                                                    $checkInObj = new DateTime($checkInDate);
                                                    $checkOutObj = new DateTime($checkOutDate);
                                                    $nights = $checkInObj->diff($checkOutObj)->days;
                                                    
                                                    // Room info
                                                    $roomName = htmlspecialchars($booking['room_name'], ENT_QUOTES, 'UTF-8');
                                                    $individualRoomNumber = !empty($booking['individual_room_number'])
                                                        ? htmlspecialchars($booking['individual_room_number'], ENT_QUOTES, 'UTF-8')
                                                        : 'Not assigned';
                                                    $individualRoomName = !empty($booking['individual_room_name'])
                                                        ? htmlspecialchars($booking['individual_room_name'], ENT_QUOTES, 'UTF-8')
                                                        : '';
                                                    
                                                    // Status
                                                    $status = ucfirst(str_replace('-', ' ', $booking['status']));
                                                    
                                                    // Payment info
                                                    $paymentStatus = !empty($booking['payment_status'])
                                                        ? ucfirst(htmlspecialchars($booking['payment_status'], ENT_QUOTES, 'UTF-8'))
                                                        : 'Pending';
                                                    $totalAmount = !empty($booking['total_amount'])
                                                        ? number_format(floatval($booking['total_amount']), 2)
                                                        : '0.00';
                                                    $currencySymbol = getSetting('currency_symbol', 'MWK');
                                                    
                                                    // Build tooltip data attributes (all properly escaped)
                                                    // Use actual newlines (%0A encoded) for white-space: pre-line to work
                                                    $tooltipText = $ref . ' - ' . $guestName . "\n" . 
                                                                  $individualRoomNumber . ' | ' . $checkIn . ' to ' . $checkOut . ' (' . $nights . ' nights)' . "\n" . 
                                                                  'Status: ' . $status . ' | Payment: ' . $paymentStatus;
                                                    
                                                    $dataAttrs = [
                                                        'data-booking-ref' => $ref,
                                                        'data-guest-name' => $guestName,
                                                        'data-room-name' => $roomName,
                                                        'data-room-number' => $individualRoomNumber,
                                                        'data-room-display' => $individualRoomNumber . ($individualRoomName ? ' - ' . $individualRoomName : ''),
                                                        'data-status' => $status,
                                                        'data-check-in' => $checkIn,
                                                        'data-check-out' => $checkOut,
                                                        'data-nights' => $nights,
                                                        'data-payment-status' => $paymentStatus,
                                                        'data-amount' => $currencySymbol . ' ' . $totalAmount,
                                                        'data-booking-id' => intval($booking['id']),
                                                        // CSS-only fallback tooltip (simple text for when JS fails)
                                                        'data-tooltip' => htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8')
                                                    ];
                                            ?>
                                                <div class="booking-indicator <?php echo $statusClass; ?> calendar-booking-tooltip-trigger"
                                                     <?php foreach ($dataAttrs as $attr => $value): echo $attr . '="' . $value . '" '; endforeach; ?>
                                                     tabindex="0"
                                                     role="button"
                                                     aria-label="Booking details for <?php echo $guestName; ?>"
                                                     onclick="window.location.href='booking-details.php?id=<?php echo intval($booking['id']); ?>'">
                                                    <?php echo substr($guestName, 0, 12); ?>
                                                </div>
                                            <?php
                                                }
                                            }
                                        ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No individual rooms found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/admin-components.js"></script>

    <?php require_once 'includes/admin-footer.php'; ?>