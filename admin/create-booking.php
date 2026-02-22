<?php
/**
 * Admin - Create Booking Manually
 * For walk-in guests, phone bookings, and agent bookings
 * Creates a booking directly in the system without guest interaction
 */

require_once 'admin-init.php';
require_once '../includes/validation.php';
require_once '../includes/booking-functions.php';

$message = '';
$error = '';

// Fetch available rooms
try {
    $rooms_stmt = $pdo->query(" 
        SELECT id, name, price_per_night, price_single_occupancy, price_double_occupancy, 
               price_triple_occupancy, child_price_multiplier, max_guests, rooms_available, total_rooms, short_description,
               single_occupancy_enabled, double_occupancy_enabled, triple_occupancy_enabled, children_allowed
        FROM rooms WHERE is_active = 1 ORDER BY display_order ASC
    ");
    $rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rooms = [];
    $error = 'Failed to load rooms.';
}

$currency_symbol = getSetting('currency_symbol', 'MK');

// Fetch available individual rooms
try {
    $individual_rooms_stmt = $pdo->query("
        SELECT ir.id, ir.room_number, ir.room_name, ir.room_type_id, ir.status,
               ir.child_price_multiplier as individual_child_price_multiplier,
               ir.single_occupancy_enabled_override, ir.double_occupancy_enabled_override,
               ir.triple_occupancy_enabled_override, ir.children_allowed_override,
               r.name as room_type_name, r.price_per_night, r.child_price_multiplier as room_type_child_price_multiplier,
               r.max_guests, r.single_occupancy_enabled, r.double_occupancy_enabled, r.triple_occupancy_enabled, r.children_allowed,
               COALESCE(ir.child_price_multiplier, r.child_price_multiplier) as effective_child_price_multiplier
        FROM individual_rooms ir
        JOIN rooms r ON ir.room_type_id = r.id
        WHERE ir.is_active = 1
        ORDER BY r.name, ir.room_number
    ");
    $all_individual_rooms = $individual_rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_individual_rooms = [];
}

// Handle booking creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    try {
        if (!isBookingEnabled()) {
            throw new Exception('Booking system is currently disabled. Re-enable it in Booking Settings to create new bookings.');
        }

        // Validate inputs
        $room_id = (int)($_POST['room_id'] ?? 0);
        $individual_room_id = !empty($_POST['individual_room_id']) ? (int)$_POST['individual_room_id'] : null;
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_email = trim($_POST['guest_email'] ?? '');
        $guest_phone = trim($_POST['guest_phone'] ?? '');
        $guest_country = trim($_POST['guest_country'] ?? '');
        $guest_address = trim($_POST['guest_address'] ?? '');
        $number_of_guests = (int)($_POST['number_of_guests'] ?? 1);
        $child_guests = (int)($_POST['child_guests'] ?? 0);
        $check_in_date = $_POST['check_in_date'] ?? '';
        $check_out_date = $_POST['check_out_date'] ?? '';
        $occupancy_type = $_POST['occupancy_type'] ?? 'double';
        $special_requests = trim($_POST['special_requests'] ?? '');
        $booking_status = $_POST['booking_status'] ?? 'confirmed';
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $send_email = isset($_POST['send_email']);

        // Basic validation
        $errors = [];
        if ($room_id <= 0) $errors[] = 'Please select a room';
        if (empty($guest_name)) $errors[] = 'Guest name is required';
        if (empty($guest_email) || !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
        if (empty($guest_phone)) $errors[] = 'Phone number is required';
        if ($number_of_guests < 1) $errors[] = 'At least 1 guest required';
        if ($child_guests < 0) $errors[] = 'Children count cannot be negative';
        if (empty($check_in_date)) $errors[] = 'Check-in date is required';
        if (empty($check_out_date)) $errors[] = 'Check-out date is required';
        
        if (empty($errors)) {
            $checkIn = new DateTime($check_in_date);
            $checkOut = new DateTime($check_out_date);
            
            if ($checkOut <= $checkIn) {
                $errors[] = 'Check-out must be after check-in';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('; ', $errors));
        }
        
        // Get room details
        $room_stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = 1");
        $room_stmt->execute([$room_id]);
        $room = $room_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) throw new Exception('Room not found');

        $occupancyPolicy = resolveOccupancyPolicy($room, null);
        if (
            ($occupancy_type === 'single' && empty($occupancyPolicy['single_enabled'])) ||
            ($occupancy_type === 'double' && empty($occupancyPolicy['double_enabled'])) ||
            ($occupancy_type === 'triple' && empty($occupancyPolicy['triple_enabled']))
        ) {
            throw new Exception('Selected occupancy type is disabled for this room type.');
        }

        if (empty($occupancyPolicy['children_allowed']) && $child_guests > 0) {
            throw new Exception('Children are not allowed for the selected room type.');
        }

        if ($child_guests >= $number_of_guests) {
            throw new Exception('At least 1 adult is required for every booking.');
        }

        $adult_guests = $number_of_guests - $child_guests;
        if ($adult_guests < 1) {
            throw new Exception('At least 1 adult is required for every booking.');
        }
        
        // Enforce maximum guest capacity
        if ($number_of_guests > (int)$room['max_guests']) {
            throw new Exception('Number of guests (' . $number_of_guests . ') exceeds room capacity of ' . $room['max_guests'] . ' guests. Please reduce guests or book an additional room.');
        }
        
        // Validate individual room if specified
        if ($individual_room_id) {
            $ir_check = $pdo->prepare("
                SELECT ir.id, ir.room_type_id, ir.status, ir.child_price_multiplier, r.child_price_multiplier AS room_type_child_price_multiplier
                FROM individual_rooms ir
                JOIN rooms r ON ir.room_type_id = r.id
                WHERE ir.id = ? AND ir.is_active = 1
            ");
            $ir_check->execute([$individual_room_id]);
            $ir_room = $ir_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$ir_room) {
                throw new Exception('Invalid individual room selected');
            }
            
            if ($ir_room['room_type_id'] != $room_id) {
                throw new Exception('Individual room does not match selected room type');
            }
            
            if ($ir_room['status'] !== 'available' && $booking_status === 'confirmed') {
                throw new Exception('Selected room is not available. Current status: ' . $ir_room['status']);
            }

            $occupancyPolicy = resolveOccupancyPolicy($room, $ir_room);
            if (
                ($occupancy_type === 'single' && empty($occupancyPolicy['single_enabled'])) ||
                ($occupancy_type === 'double' && empty($occupancyPolicy['double_enabled'])) ||
                ($occupancy_type === 'triple' && empty($occupancyPolicy['triple_enabled']))
            ) {
                throw new Exception('Selected occupancy type is disabled for this individual room.');
            }

            if (empty($occupancyPolicy['children_allowed']) && $child_guests > 0) {
                throw new Exception('Children are not allowed for the selected individual room.');
            }
        }
        
        // Calculate pricing
        if ($occupancy_type === 'single' && !empty($room['price_single_occupancy'])) {
            $room_price = $room['price_single_occupancy'];
        } elseif ($occupancy_type === 'double' && !empty($room['price_double_occupancy'])) {
            $room_price = $room['price_double_occupancy'];
        } elseif ($occupancy_type === 'triple' && !empty($room['price_triple_occupancy'])) {
            $room_price = $room['price_triple_occupancy'];
        } else {
            $room_price = $room['price_per_night'];
        }
        
        $checkIn = new DateTime($check_in_date);
        $checkOut = new DateTime($check_out_date);
        $number_of_nights = $checkIn->diff($checkOut)->days;

        if ($individual_room_id && !empty($ir_room)) {
            $child_price_multiplier = isset($ir_room['child_price_multiplier']) && $ir_room['child_price_multiplier'] !== null
                ? (float)$ir_room['child_price_multiplier']
                : (float)($ir_room['room_type_child_price_multiplier'] ?? $room['child_price_multiplier'] ?? getSetting('booking_child_price_multiplier', getSetting('child_guest_price_multiplier', 50)));
        } else {
            $child_price_multiplier = isset($room['child_price_multiplier'])
                ? (float)$room['child_price_multiplier']
                : (float)getSetting('booking_child_price_multiplier', getSetting('child_guest_price_multiplier', 50));
        }
        if ($child_price_multiplier < 0) {
            $child_price_multiplier = 0;
        }
        
        // Allow price override
        $price_override = isset($_POST['price_override']) && !empty($_POST['price_override']) 
            ? (float)$_POST['price_override'] : null;
        
        $base_amount = $room_price * $number_of_nights;
        $child_supplement_total = ($child_guests > 0)
            ? (($room_price * ($child_price_multiplier / 100)) * $child_guests * $number_of_nights)
            : 0;
        $calculated_total_amount = $base_amount + $child_supplement_total;
        $total_amount = $price_override !== null ? $price_override : $calculated_total_amount;
        
        // Generate unique booking reference
        $ref_prefix = getSetting('booking_reference_prefix', 'LSH');
        do {
            $booking_reference = $ref_prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $ref_check = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE booking_reference = ?");
            $ref_check->execute([$booking_reference]);
            $ref_exists = $ref_check->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        } while ($ref_exists);
        
        // Determine tentative settings
        $is_tentative = ($booking_status === 'tentative') ? 1 : 0;
        $tentative_expires_at = null;
        if ($is_tentative) {
            $tentative_hours = (int)getSetting('tentative_duration_hours', 48);
            $tentative_expires_at = date('Y-m-d H:i:s', strtotime("+{$tentative_hours} hours"));
        }
        
        // Insert booking
        $pdo->beginTransaction();
        
        $insert = $pdo->prepare("
            INSERT INTO bookings (
                booking_reference, room_id, individual_room_id, guest_name, guest_email, guest_phone,
                guest_country, guest_address, number_of_guests, adult_guests, child_guests,
                child_price_multiplier, check_in_date, check_out_date, number_of_nights,
                total_amount, child_supplement_total, special_requests, status,
                payment_status, is_tentative, tentative_expires_at, occupancy_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insert->execute([
            $booking_reference, $room_id, $individual_room_id, $guest_name, $guest_email, $guest_phone,
            $guest_country, $guest_address, $number_of_guests, $adult_guests, $child_guests,
            $child_price_multiplier, $check_in_date, $check_out_date, $number_of_nights,
            $total_amount, $child_supplement_total, $special_requests,
            $booking_status, $payment_status, $is_tentative, $tentative_expires_at, $occupancy_type
        ]);
        
        $new_booking_id = $pdo->lastInsertId();
        
        // Note: Individual room status is now handled by assignIndividualRoomToBooking()
        // which uses timeline-aware logic (only 'occupied' on/after check-in date)
        // No need to manually set status here
        
        // If confirmed, decrement room availability
        if ($booking_status === 'confirmed') {
            $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available - 1 WHERE id = ? AND rooms_available > 0")
                ->execute([$room_id]);
        }
        
        // If paid, create payment record
        if ($payment_status === 'paid') {
            $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
            $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
            $vatAmount = $vatEnabled ? ($total_amount * ($vatRate / 100)) : 0;
            $totalWithVat = $total_amount + $vatAmount;
            
            $payment_reference = 'PAY-' . date('Y') . '-' . str_pad($new_booking_id, 6, '0', STR_PAD_LEFT);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            
            $insert_payment = $pdo->prepare("
                INSERT INTO payments (
                    payment_reference, booking_type, booking_id, booking_reference,
                    payment_date, payment_amount, vat_rate, vat_amount, total_amount,
                    payment_method, payment_type, payment_status, invoice_generated,
                    status, recorded_by
                ) VALUES (?, 'room', ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'full_payment', 'completed', 1, 'completed', ?)
            ");
            $insert_payment->execute([
                $payment_reference, $new_booking_id, $booking_reference,
                $total_amount, $vatRate, $vatAmount, $totalWithVat,
                $payment_method, $user['id']
            ]);
            
            $pdo->prepare("
                UPDATE bookings SET amount_paid = ?, amount_due = 0, vat_rate = ?, vat_amount = ?,
                    total_with_vat = ?, last_payment_date = CURDATE() WHERE id = ?
            ")->execute([$totalWithVat, $vatRate, $vatAmount, $totalWithVat, $new_booking_id]);
        }
        
        // Add admin note if provided
        if (!empty($admin_notes)) {
            $pdo->prepare("INSERT INTO booking_notes (booking_id, note_text, created_by) VALUES (?, ?, ?)")
                ->execute([$new_booking_id, 'Admin booking: ' . $admin_notes, $user['id']]);
        }
        
        // Log creation source
        $pdo->prepare("INSERT INTO booking_notes (booking_id, note_text, created_by) VALUES (?, ?, ?)")
            ->execute([$new_booking_id, 'Booking created manually by admin (' . ($user['full_name'] ?? $user['username']) . ')', $user['id']]);
        
        $pdo->commit();
        
        // Send email if requested
        if ($send_email) {
            require_once '../config/email.php';
            $booking_data = [
                'id' => $new_booking_id,
                'booking_reference' => $booking_reference,
                'room_id' => $room_id,
                'guest_name' => $guest_name,
                'guest_email' => $guest_email,
                'guest_phone' => $guest_phone,
                'check_in_date' => $check_in_date,
                'check_out_date' => $check_out_date,
                'number_of_nights' => $number_of_nights,
                'number_of_guests' => $number_of_guests,
                'adult_guests' => $adult_guests,
                'child_guests' => $child_guests,
                'child_price_multiplier' => $child_price_multiplier,
                'child_supplement_total' => $child_supplement_total,
                'total_amount' => $total_amount,
                'special_requests' => $special_requests,
                'status' => $booking_status,
                'is_tentative' => $is_tentative,
                'tentative_expires_at' => $tentative_expires_at,
                'occupancy_type' => $occupancy_type,
                'room_name' => $room['name']
            ];
            
            if ($booking_status === 'confirmed') {
                $email_result = sendBookingConfirmedEmail($booking_data);
            } elseif ($is_tentative) {
                $email_result = sendTentativeBookingConfirmedEmail($booking_data);
            } else {
                $email_result = sendBookingReceivedEmail($booking_data);
            }
            
            $email_msg = $email_result['success'] ? ' Email sent to guest.' : ' (Email failed: ' . $email_result['message'] . ')';
        } else {
            $email_msg = '';
        }
        
        $message = "Booking <strong>{$booking_reference}</strong> created successfully!{$email_msg} <a href='booking-details.php?id={$new_booking_id}'>View Details</a>";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Build rooms JSON for JavaScript
$rooms_json = json_encode(array_map(function($r) {
    $policy = resolveOccupancyPolicy($r, null);
    return [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'max_guests' => (int)$r['max_guests'],
        'price_per_night' => (float)$r['price_per_night'],
        'price_single' => (float)($r['price_single_occupancy'] ?? $r['price_per_night']),
        'price_double' => (float)($r['price_double_occupancy'] ?? $r['price_per_night'] * 1.2),
        'price_triple' => (float)($r['price_triple_occupancy'] ?? $r['price_per_night'] * 1.4),
        'child_price_multiplier' => isset($r['child_price_multiplier']) ? (float)$r['child_price_multiplier'] : (float)getSetting('booking_child_price_multiplier', getSetting('child_guest_price_multiplier', 50)),
        'rooms_available' => (int)$r['rooms_available'],
        'single_enabled' => (int)$policy['single_enabled'],
        'double_enabled' => (int)$policy['double_enabled'],
        'triple_enabled' => (int)$policy['triple_enabled'],
        'children_allowed' => (int)$policy['children_allowed'],
    ];
}, $rooms));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css"></head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <div class="create-booking-container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-plus-circle" style="color: var(--gold);"></i> Create Booking</h1>
                <a href="bookings.php" style="color: var(--gold); text-decoration: none; font-weight: 600;"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="createBookingForm">
                <input type="hidden" name="create_booking" value="1">
                
                <!-- Room Selection -->
                <div class="form-card">
                    <h3><i class="fas fa-bed"></i> Room Selection</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Room <span class="required">*</span></label>
                            <select name="room_id" id="roomSelect" required onchange="updateRoomInfo()">
                                <option value="">-- Select Room --</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" 
                                            data-max-guests="<?php echo $room['max_guests']; ?>"
                                            data-available="<?php echo $room['rooms_available']; ?>"
                                            <?php echo ($room['rooms_available'] <= 0) ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($room['name']); ?> 
                                        (<?php echo $currency_symbol; ?><?php echo number_format($room['price_per_night']); ?>/night)
                                        <?php echo ($room['rooms_available'] <= 0) ? ' - FULLY BOOKED' : " - {$room['rooms_available']} available"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Individual Room (Optional)</label>
                            <select name="individual_room_id" id="individualRoomSelect">
                                <option value="">-- Auto-assign --</option>
                                <?php 
                                $grouped_rooms = [];
                                foreach ($all_individual_rooms as $ir) {
                                    $grouped_rooms[$ir['room_type_name']][] = $ir;
                                }
                                foreach ($grouped_rooms as $type_name => $rooms_list): 
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($type_name); ?>">
                                        <?php foreach ($rooms_list as $ir): ?>
                                            <option value="<?php echo $ir['id']; ?>" 
                                                    data-room-type-id="<?php echo $ir['room_type_id']; ?>"
                                                    data-status="<?php echo $ir['status']; ?>"
                                                    data-child-multiplier="<?php echo htmlspecialchars((string)($ir['effective_child_price_multiplier'] ?? 50)); ?>"
                                                    data-single-override="<?php echo $ir['single_occupancy_enabled_override'] === null ? '' : (int)$ir['single_occupancy_enabled_override']; ?>"
                                                    data-double-override="<?php echo $ir['double_occupancy_enabled_override'] === null ? '' : (int)$ir['double_occupancy_enabled_override']; ?>"
                                                    data-triple-override="<?php echo $ir['triple_occupancy_enabled_override'] === null ? '' : (int)$ir['triple_occupancy_enabled_override']; ?>"
                                                    data-children-override="<?php echo $ir['children_allowed_override'] === null ? '' : (int)$ir['children_allowed_override']; ?>"
                                                    <?php echo ($ir['status'] !== 'available') ? 'disabled' : ''; ?>>
                                                <?php echo htmlspecialchars($ir['room_number'] . ' - ' . ($ir['room_name'] ?: $ir['room_number'])); ?>
                                                <?php echo ($ir['status'] !== 'available') ? ' (' . ucfirst($ir['status']) . ')' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #666;">Assign to a specific room, or leave blank for auto-assignment</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Occupancy Type</label>
                            <select name="occupancy_type" id="occupancyType" onchange="calculateTotal()">
                                <option value="single">Single Occupancy</option>
                                <option value="double" selected>Double Occupancy</option>
                                <option value="triple">Triple Occupancy</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Guest Information -->
                <div class="form-card">
                    <h3><i class="fas fa-user"></i> Guest Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="guest_name" required value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="guest_email" required value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone <span class="required">*</span></label>
                            <input type="tel" name="guest_phone" required value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="guest_country" value="<?php echo htmlspecialchars($_POST['guest_country'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="guest_address" rows="2"><?php echo htmlspecialchars($_POST['guest_address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Stay Details -->
                <div class="form-card">
                    <h3><i class="fas fa-calendar-alt"></i> Stay Details</h3>
                    <div class="form-row three-col">
                        <div class="form-group">
                            <label>Check-in Date <span class="required">*</span></label>
                            <input type="date" name="check_in_date" id="checkInDate" required onchange="calculateTotal()" value="<?php echo htmlspecialchars($_POST['check_in_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Check-out Date <span class="required">*</span></label>
                            <input type="date" name="check_out_date" id="checkOutDate" required onchange="calculateTotal()" value="<?php echo htmlspecialchars($_POST['check_out_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Number of Guests <span class="required">*</span></label>
                            <input type="number" name="number_of_guests" id="numGuests" min="1" max="20" value="<?php echo htmlspecialchars($_POST['number_of_guests'] ?? '2'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Children (under 12)</label>
                            <input type="number" name="child_guests" id="childGuests" min="0" max="19" value="<?php echo htmlspecialchars($_POST['child_guests'] ?? '0'); ?>" onchange="calculateTotal()">
                            <small style="color: #666;">At least 1 adult is required.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Special Requests</label>
                        <textarea name="special_requests" rows="3" placeholder="Early check-in, extra pillows, etc."><?php echo htmlspecialchars($_POST['special_requests'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Booking & Payment Status -->
                <div class="form-card">
                    <h3><i class="fas fa-cog"></i> Booking & Payment</h3>
                    <div class="form-row three-col">
                        <div class="form-group">
                            <label>Booking Status</label>
                            <select name="booking_status" id="bookingStatus">
                                <option value="pending">Pending</option>
                                <option value="confirmed" selected>Confirmed</option>
                                <option value="tentative">Tentative</option>
                                <option value="checked-in">Checked In (Walk-in)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" id="paymentStatus" onchange="togglePaymentMethod()">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                        <div class="form-group" id="paymentMethodGroup" style="display: none;">
                            <label>Payment Method</label>
                            <select name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Price Override (leave blank for auto-calculated)</label>
                        <input type="number" name="price_override" id="priceOverride" step="0.01" min="0" 
                               placeholder="Total amount override" onchange="calculateTotal()">
                        <small style="color: #666;">Override the automatically calculated total</small>
                    </div>
                    <div class="form-group">
                        <label>Admin Notes (internal only)</label>
                        <textarea name="admin_notes" rows="2" placeholder="Walk-in guest, phone booking, special arrangement, etc."><?php echo htmlspecialchars($_POST['admin_notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="send_email" id="sendEmail" checked>
                        <label for="sendEmail" style="margin-bottom: 0;">Send confirmation email to guest</label>
                    </div>
                </div>
                
                <!-- Booking Summary -->
                <div class="form-card">
                    <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
                    <div class="summary-box" id="summaryBox">
                        <div class="summary-row">
                            <span>Room</span>
                            <span id="sumRoom">--</span>
                        </div>
                        <div class="summary-row">
                            <span>Individual Room</span>
                            <span id="sumIndividualRoom">--</span>
                        </div>
                        <div class="summary-row">
                            <span>Rate per Night</span>
                            <span id="sumRate">--</span>
                        </div>
                        <div class="summary-row">
                            <span>Nights</span>
                            <span id="sumNights">--</span>
                        </div>
                        <div class="summary-row">
                            <span>Guests</span>
                            <span id="sumGuests">--</span>
                        </div>
                        <div class="summary-row" id="sumChildSupplementRow" style="display: none;">
                            <span>Child Supplement</span>
                            <span id="sumChildSupplement">--</span>
                        </div>
                        <div class="summary-row">
                            <span>Total</span>
                            <span id="sumTotal">--</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-create">
                    <i class="fas fa-plus-circle"></i> Create Booking
                </button>
            </form>
        </div>
    </div>
    
    <script>
        const roomsData = <?php echo $rooms_json; ?>;
        const currency = '<?php echo $currency_symbol; ?>';
        
        function updateRoomInfo() {
            const roomId = parseInt(document.getElementById('roomSelect').value);
            const room = roomsData.find(r => r.id === roomId);
            const occupancySelect = document.getElementById('occupancyType');
            
            // Update individual room dropdown filter
            const irSelect = document.getElementById('individualRoomSelect');
            Array.from(irSelect.options).forEach(opt => {
                if (opt.value) {
                    const optRoomTypeId = parseInt(opt.getAttribute('data-room-type-id'));
                    if (roomId && optRoomTypeId !== roomId) {
                        opt.disabled = true;
                    } else if (roomId && opt.getAttribute('data-status') !== 'available') {
                        opt.disabled = true;
                    } else if (roomId) {
                        opt.disabled = false;
                    }
                }
            });
            
            // Reset individual room selection if it doesn't match
            if (roomId && irSelect.value) {
                const selectedOpt = irSelect.options[irSelect.selectedIndex];
                if (selectedOpt && parseInt(selectedOpt.getAttribute('data-room-type-id')) !== roomId) {
                    irSelect.value = '';
                }
            }
            
            if (room) {
                const guestsInput = document.getElementById('numGuests');
                const childInput = document.getElementById('childGuests');
                guestsInput.max = room.max_guests;
                // Clamp current value to max capacity
                if (parseInt(guestsInput.value) > room.max_guests) {
                    guestsInput.value = room.max_guests;
                }

                const maxChildren = Math.max(0, (parseInt(guestsInput.value || '1', 10) - 1));
                childInput.max = maxChildren;
                if (parseInt(childInput.value || '0', 10) > maxChildren) {
                    childInput.value = maxChildren;
                }

                const occupancyOptions = Array.from(occupancySelect.options);
                occupancyOptions.forEach(opt => {
                    if (opt.value === 'single') opt.disabled = Number(room.single_enabled || 0) !== 1;
                    if (opt.value === 'double') opt.disabled = Number(room.double_enabled || 0) !== 1;
                    if (opt.value === 'triple') opt.disabled = Number(room.triple_enabled || 0) !== 1;
                });
                if (occupancySelect.selectedOptions[0]?.disabled) {
                    const firstEnabled = occupancyOptions.find(opt => !opt.disabled);
                    if (firstEnabled) occupancySelect.value = firstEnabled.value;
                }

                const childrenAllowed = Number(room.children_allowed || 0) === 1;
                childInput.disabled = !childrenAllowed;
                if (!childrenAllowed) {
                    childInput.value = '0';
                }
            }
            applyIndividualRoomPolicyOverrides();
            calculateTotal();
        }

        function applyIndividualRoomPolicyOverrides() {
            const irSelect = document.getElementById('individualRoomSelect');
            const occupancySelect = document.getElementById('occupancyType');
            const roomId = parseInt(document.getElementById('roomSelect').value || '0', 10);
            const room = roomsData.find(r => r.id === roomId);
            if (!room || !occupancySelect) return;

            let singleEnabled = Number(room.single_enabled || 0) === 1;
            let doubleEnabled = Number(room.double_enabled || 0) === 1;
            let tripleEnabled = Number(room.triple_enabled || 0) === 1;
            let childrenAllowed = Number(room.children_allowed || 0) === 1;

            const selectedOpt = irSelect && irSelect.value ? irSelect.options[irSelect.selectedIndex] : null;
            if (selectedOpt) {
                const so = selectedOpt.getAttribute('data-single-override');
                const dox = selectedOpt.getAttribute('data-double-override');
                const to = selectedOpt.getAttribute('data-triple-override');
                const co = selectedOpt.getAttribute('data-children-override');
                if (so !== '') singleEnabled = Number(so) === 1;
                if (dox !== '') doubleEnabled = Number(dox) === 1;
                if (to !== '') tripleEnabled = Number(to) === 1;
                if (co !== '') childrenAllowed = Number(co) === 1;
            }

            Array.from(occupancySelect.options).forEach(opt => {
                if (opt.value === 'single') opt.disabled = !singleEnabled;
                if (opt.value === 'double') opt.disabled = !doubleEnabled;
                if (opt.value === 'triple') opt.disabled = !tripleEnabled;
            });
            if (occupancySelect.selectedOptions[0]?.disabled) {
                const firstEnabled = Array.from(occupancySelect.options).find(opt => !opt.disabled);
                if (firstEnabled) occupancySelect.value = firstEnabled.value;
            }

            const childInput = document.getElementById('childGuests');
            if (childInput) {
                childInput.disabled = !childrenAllowed;
                if (!childrenAllowed) childInput.value = '0';
            }
        }
        
        function calculateTotal() {
            const roomId = parseInt(document.getElementById('roomSelect').value);
            const room = roomsData.find(r => r.id === roomId);
            const checkIn = document.getElementById('checkInDate').value;
            const checkOut = document.getElementById('checkOutDate').value;
            const occupancy = document.getElementById('occupancyType').value;
            const totalGuests = parseInt(document.getElementById('numGuests').value || '1', 10);
            const childGuests = parseInt(document.getElementById('childGuests').value || '0', 10);
            const priceOverride = document.getElementById('priceOverride').value;
            const irSelect = document.getElementById('individualRoomSelect');
            
            // Update individual room display
            if (irSelect.value) {
                const selectedOpt = irSelect.options[irSelect.selectedIndex];
                document.getElementById('sumIndividualRoom').textContent = selectedOpt.text;
            } else {
                document.getElementById('sumIndividualRoom').textContent = 'Auto-assign';
            }
            
            if (!room || !checkIn || !checkOut) {
                document.getElementById('sumRoom').textContent = '--';
                document.getElementById('sumRate').textContent = '--';
                document.getElementById('sumNights').textContent = '--';
                document.getElementById('sumTotal').textContent = '--';
                return;
            }
            
            const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / (1000*60*60*24));
            if (nights <= 0) return;
            
            let rate = room.price_per_night;
            if (occupancy === 'single') rate = room.price_single;
            else if (occupancy === 'double') rate = room.price_double;
            else if (occupancy === 'triple') rate = room.price_triple;

            let activeChildMultiplier = Math.max(0, parseFloat(room.child_price_multiplier || 0) || 0);
            if (irSelect.value) {
                const selectedOpt = irSelect.options[irSelect.selectedIndex];
                if (selectedOpt) {
                    const irMultiplier = parseFloat(selectedOpt.getAttribute('data-child-multiplier') || '');
                    if (!Number.isNaN(irMultiplier)) {
                        activeChildMultiplier = Math.max(0, irMultiplier);
                    }
                }
            }
            const childMultiplier = activeChildMultiplier / 100;
            const safeChildren = Math.min(Math.max(childGuests, 0), Math.max(0, totalGuests - 1));
            const adults = Math.max(1, totalGuests - safeChildren);
            const childSupplement = safeChildren > 0 ? (rate * childMultiplier * safeChildren * nights) : 0;
            const total = priceOverride ? parseFloat(priceOverride) : ((rate * nights) + childSupplement);
            
            document.getElementById('sumRoom').textContent = room.name;
            document.getElementById('sumRate').textContent = currency + rate.toLocaleString();
            document.getElementById('sumNights').textContent = nights;
            document.getElementById('sumGuests').textContent = `${adults} adult${adults === 1 ? '' : 's'}${safeChildren > 0 ? ` + ${safeChildren} child${safeChildren === 1 ? '' : 'ren'}` : ''}`;

            const childRow = document.getElementById('sumChildSupplementRow');
            const childVal = document.getElementById('sumChildSupplement');
            if (safeChildren > 0) {
                childRow.style.display = '';
                childVal.textContent = currency + childSupplement.toLocaleString();
            } else {
                childRow.style.display = 'none';
                childVal.textContent = '--';
            }
            document.getElementById('sumTotal').textContent = currency + total.toLocaleString();
        }
        
        function togglePaymentMethod() {
            const status = document.getElementById('paymentStatus').value;
            document.getElementById('paymentMethodGroup').style.display = (status === 'paid') ? 'block' : 'none';
        }
        
        // Update summary when individual room changes
        document.getElementById('individualRoomSelect').addEventListener('change', function() {
            applyIndividualRoomPolicyOverrides();
            calculateTotal();
        });
        document.getElementById('numGuests').addEventListener('input', function() {
            const total = Math.max(1, parseInt(this.value || '1', 10));
            const childInput = document.getElementById('childGuests');
            const maxChildren = Math.max(0, total - 1);
            childInput.max = maxChildren;
            if (parseInt(childInput.value || '0', 10) > maxChildren) {
                childInput.value = maxChildren;
            }
            calculateTotal();
        });
        document.getElementById('childGuests').addEventListener('input', calculateTotal);
    </script>
    <script src="js/admin-components.js"></script>
    <?php require_once 'includes/admin-footer.php'; ?>
