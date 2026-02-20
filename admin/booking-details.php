<?php
// Include admin initialization (PHP-only, no HTML Output)
require_once 'admin-init.php';

$user = [
    'id' => $_SESSION['admin_user_id'],
    'username' => $_SESSION['admin_username'],
    'role' => $_SESSION['admin_role'],
    'full_name' => $_SESSION['admin_full_name']
];
$booking_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$booking_id) {
    header('Location: dashboard.php');
    exit;
}

// Include timeline functions
require_once '../includes/booking-timeline.php';

// Handle status changes (POST-only for CSRF protection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
    $action = $_POST['booking_action'];
    
    try {
        switch ($action) {
            case 'convert':
                // Convert tentative booking to confirmed
                $stmt = $pdo->prepare("
                    SELECT b.*, r.name as room_name, r.slug as room_slug
                    FROM bookings b
                    LEFT JOIN rooms r ON b.room_id = r.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$booking_id]);
                $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking_data) {
                    $_SESSION['error_message'] = 'Booking not found.';
                } elseif ($booking_data['status'] !== 'tentative' || $booking_data['is_tentative'] != 1) {
                    $_SESSION['error_message'] = 'This is not a tentative booking.';
                } else {
                    // Convert to confirmed
                    $update = $pdo->prepare("UPDATE bookings SET status = 'confirmed', is_tentative = 0, updated_at = NOW() WHERE id = ?");
                    $update->execute([$booking_id]);
                    
                    // Log to timeline
                    logTentativeConversion($booking_id, $booking_data['booking_reference'], 'admin', $user['id'], $user['full_name']);
                    
                    // Log the conversion
                    try {
                        $log_stmt = $pdo->prepare("
                            INSERT INTO tentative_booking_log (
                                booking_id, action, action_by, action_at, notes
                            ) VALUES (?, 'converted', ?, NOW(), ?)
                        ");
                        $log_stmt->execute([
                            $booking_id,
                            $user['id'],
                            'Converted from tentative to confirmed by admin'
                        ]);
                    } catch (PDOException $logError) {
                        error_log("Tentative log error: " . $logError->getMessage());
                    }
                    
                    // Send conversion email
                    require_once '../config/email.php';
                    $email_result = sendTentativeBookingConvertedEmail($booking_data);
                    
                    if ($email_result['success']) {
                        $_SESSION['success_message'] = 'Tentative booking converted to confirmed! Conversion email sent to guest.';
                    } else {
                        $_SESSION['success_message'] = 'Tentative booking converted! (Email failed: ' . $email_result['message'] . ')';
                    }
                }
                break;
            
            case 'confirm':
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$booking_id]);
                
                // Decrement room availability
                $room_stmt = $pdo->prepare("SELECT room_id, booking_reference FROM bookings WHERE id = ?");
                $room_stmt->execute([$booking_id]);
                $booking_room = $room_stmt->fetch(PDO::FETCH_ASSOC);
                if ($booking_room) {
                    $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available - 1 WHERE id = ? AND rooms_available > 0")
                        ->execute([$booking_room['room_id']]);
                    
                    // Log to timeline
                    logBookingStatusChange($booking_id, $booking_room['booking_reference'], 'pending', 'confirmed', 'admin', $user['id'], $user['full_name']);
                }
                
                // Send confirmation email
                require_once '../config/email.php';
                $conf_stmt = $pdo->prepare("SELECT b.*, r.name as room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
                $conf_stmt->execute([$booking_id]);
                $conf_booking = $conf_stmt->fetch(PDO::FETCH_ASSOC);
                if ($conf_booking) {
                    $email_result = sendBookingConfirmedEmail($conf_booking);
                    $_SESSION['success_message'] = 'Booking confirmed.' . ($email_result['success'] ? ' Confirmation email sent.' : '');
                } else {
                    $_SESSION['success_message'] = 'Booking confirmed successfully.';
                }
                break;
            
            case 'checkin':
                // Enforce payment check on check-in
                $check_stmt = $pdo->prepare("SELECT b.status, b.payment_status, b.individual_room_id, b.room_id, b.booking_reference, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $check_stmt->execute([$booking_id]);
                $check_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$check_row) {
                    $_SESSION['error_message'] = 'Booking not found.';
                } elseif ($check_row['status'] !== 'confirmed') {
                    $_SESSION['error_message'] = 'Only confirmed bookings can be checked in.';
                } elseif ($check_row['payment_status'] !== 'paid') {
                    $_SESSION['error_message'] = 'Cannot check in: booking must be PAID first.';
                } else {
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'checked-in', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Log to timeline
                    logBookingCheckIn($booking_id, $check_row['booking_reference'], 'admin', $user['id'], $user['full_name']);
                    
                    // Update individual room status to occupied if assigned
                    if (!empty($check_row['individual_room_id'])) {
                        $old_room_status = $check_row['room_status'];
                        $pdo->prepare("UPDATE individual_rooms SET status = 'occupied' WHERE id = ?")->execute([$check_row['individual_room_id']]);
                        
                        // Log the room status change
                        $logStmt = $pdo->prepare("
                            INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                            VALUES (?, ?, 'occupied', 'Guest checked in', ?)
                        ");
                        $logStmt->execute([$check_row['individual_room_id'], $old_room_status ?: 'available', $user['id']]);
                    }
                    
                    $_SESSION['success_message'] = 'Guest checked in successfully.' . (!empty($check_row['individual_room_id']) ? ' Room marked as occupied.' : '');
                }
                break;
            
            case 'checkout':
                // Get booking and individual room info before checkout
                $checkout_stmt = $pdo->prepare("SELECT b.room_id, b.individual_room_id, b.booking_reference, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $checkout_stmt->execute([$booking_id]);
                $checkout_row = $checkout_stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'checked-out', updated_at = NOW() WHERE id = ? AND status = 'checked-in'");
                $stmt->execute([$booking_id]);
                if ($stmt->rowCount() > 0) {
                    // Log to timeline
                    logBookingCheckOut($booking_id, $checkout_row['booking_reference'], 'admin', $user['id'], $user['full_name']);
                    
                    // Restore room availability
                    if ($checkout_row) {
                        $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms")
                            ->execute([$checkout_row['room_id']]);
                        
                        // Update individual room status to cleaning if assigned
                        if (!empty($checkout_row['individual_room_id'])) {
                            $old_room_status = $checkout_row['room_status'];
                            $pdo->prepare("UPDATE individual_rooms SET status = 'cleaning' WHERE id = ?")->execute([$checkout_row['individual_room_id']]);
                            
                            // Log the room status change
                            $logStmt = $pdo->prepare("
                                INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                                VALUES (?, ?, 'cleaning', 'Guest checked out', ?)
                            ");
                            $logStmt->execute([$checkout_row['individual_room_id'], $old_room_status ?: 'occupied', $user['id']]);
                        }
                    }
                    $_SESSION['success_message'] = 'Guest checked out successfully. Room availability restored.' . (!empty($checkout_row['individual_room_id']) ? ' Individual room marked for cleaning.' : '');
                } else {
                    $_SESSION['error_message'] = 'Only checked-in guests can be checked out.';
                }
                break;
            
            case 'noshow':
                $check_stmt = $pdo->prepare("SELECT b.status, b.room_id, b.individual_room_id, b.booking_reference, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $check_stmt->execute([$booking_id]);
                $noshow_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($noshow_row && in_array($noshow_row['status'], ['confirmed', 'pending'])) {
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'no-show', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Log to timeline
                    logBookingEvent(
                        $booking_id,
                        $noshow_row['booking_reference'],
                        'Guest marked as no-show',
                        'status_change',
                        'Guest did not arrive - marked as no-show',
                        $noshow_row['status'],
                        'no-show',
                        'admin',
                        $user['id'],
                        $user['full_name']
                    );
                    
                    // Restore room availability if was confirmed
                    if ($noshow_row['status'] === 'confirmed') {
                        $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms")
                            ->execute([$noshow_row['room_id']]);
                    }
                    
                    // Release individual room if assigned
                    if (!empty($noshow_row['individual_room_id'])) {
                        $old_room_status = $noshow_row['room_status'];
                        $pdo->prepare("UPDATE individual_rooms SET status = 'available' WHERE id = ?")->execute([$noshow_row['individual_room_id']]);
                        
                        // Log the room status change
                        $logStmt = $pdo->prepare("
                            INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                            VALUES (?, ?, 'available', 'Guest no-show', ?)
                        ");
                        $logStmt->execute([$noshow_row['individual_room_id'], $old_room_status ?: 'occupied', $user['id']]);
                    }
                    
                    $_SESSION['success_message'] = 'Booking marked as no-show.' . (!empty($noshow_row['individual_room_id']) ? ' Individual room released.' : '');
                } else {
                    $_SESSION['error_message'] = 'Cannot mark as no-show from current status.';
                }
                break;
            
            case 'cancel':
                // Get booking details before cancelling
                $booking_stmt = $pdo->prepare("
                    SELECT b.*, r.name as room_name, ir.status as individual_room_status
                    FROM bookings b
                    LEFT JOIN rooms r ON b.room_id = r.id
                    LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
                    WHERE b.id = ?
                ");
                $booking_stmt->execute([$booking_id]);
                $booking_to_cancel = $booking_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($booking_to_cancel) {
                    $previous_status = $booking_to_cancel['status'];
                    $cancellation_reason = $_POST['cancellation_reason'] ?? 'Cancelled by admin';
                    
                    // Update booking status
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Log to timeline
                    logBookingStatusChange($booking_id, $booking_to_cancel['booking_reference'], $previous_status, 'cancelled', 'admin', $user['id'], $user['full_name'], $cancellation_reason);
                    
                    // Restore room availability
                    if ($previous_status === 'confirmed') {
                        $update_room = $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms");
                        $update_room->execute([$booking_to_cancel['room_id']]);
                    }
                    
                    // Release individual room if assigned
                    if (!empty($booking_to_cancel['individual_room_id'])) {
                        $old_room_status = $booking_to_cancel['individual_room_status'];
                        $pdo->prepare("UPDATE individual_rooms SET status = 'available' WHERE id = ?")->execute([$booking_to_cancel['individual_room_id']]);
                        
                        // Log the room status change
                        $logStmt = $pdo->prepare("
                            INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                            VALUES (?, ?, 'available', 'Booking cancelled', ?)
                        ");
                        $logStmt->execute([$booking_to_cancel['individual_room_id'], $old_room_status ?: 'occupied', $user['id']]);
                    }
                    
                    // Send cancellation email
                    require_once '../config/email.php';
                    $email_result = sendBookingCancelledEmail($booking_to_cancel, $cancellation_reason);
                    
                    // Log cancellation to database
                    $email_sent = $email_result['success'];
                    $email_status = $email_result['message'];
                    logCancellationToDatabase(
                        $booking_to_cancel['id'],
                        $booking_to_cancel['booking_reference'],
                        'room',
                        $booking_to_cancel['guest_email'],
                        $user['id'],
                        $cancellation_reason,
                        $email_sent,
                        $email_status
                    );
                    
                    // Log cancellation to file
                    logCancellationToFile(
                        $booking_to_cancel['booking_reference'],
                        'room',
                        $booking_to_cancel['guest_email'],
                        $user['full_name'] ?? $user['username'],
                        $cancellation_reason,
                        $email_sent,
                        $email_status
                    );
                    
                    $room_released_msg = !empty($booking_to_cancel['individual_room_id']) ? ' Individual room released.' : '';
                    if ($email_sent) {
                        $_SESSION['success_message'] = 'Booking cancelled. Cancellation email sent to guest.' . $room_released_msg;
                    } else {
                        $_SESSION['success_message'] = 'Booking cancelled. (Email failed: ' . $email_status . ')' . $room_released_msg;
                    }
                } else {
                    $_SESSION['error_message'] = 'Booking not found.';
                }
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Action failed. Please try again.';
        error_log("Booking action error: " . $e->getMessage());
    }
    
    header("Location: booking-details.php?id=$booking_id");
    exit;
}

// Fetch booking details with payment status from payments table
try {
    $stmt = $pdo->prepare("
        SELECT b.*,
               r.name as room_name,
               r.price_per_night,
               COALESCE(p.payment_status, b.payment_status) as actual_payment_status,
               p.payment_reference,
               p.payment_date as last_payment_date,
               p.payment_amount,
               p.vat_rate,
               p.vat_amount,
               p.total_amount as payment_total_with_vat,
               ir.room_number as individual_room_number,
               ir.room_name as individual_room_name,
               ir.floor as individual_room_floor,
               ir.status as individual_room_status,
               rt.name as room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        LEFT JOIN payments p ON b.id = p.booking_id AND p.booking_type = 'room' AND p.status = 'completed'
        LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
        LEFT JOIN rooms rt ON ir.room_type_id = rt.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['error_message'] = 'Booking not found.';
        header('Location: dashboard.php');
        exit;
    }

    // Fetch booking notes
    $notes_stmt = $pdo->prepare("
        SELECT n.*, u.full_name as created_by_name 
        FROM booking_notes n
        LEFT JOIN admin_users u ON n.created_by = u.id
        WHERE n.booking_id = ?
        ORDER BY n.created_at DESC
    ");
    $notes_stmt->execute([$booking_id]);
    $notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch booking timeline
    $timeline = getBookingTimeline($booking_id);

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Unable to load booking details.';
    header('Location: dashboard.php');
    exit;
}

// Handle note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = trim($_POST['note_text'] ?? '');
    
    if ($note_text) {
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO booking_notes (booking_id, note_text, created_by) VALUES (?, ?, ?)");
            $insert_stmt->execute([$booking_id, $note_text, $user['id']]);
            
            // Log to timeline
            logBookingNote($booking_id, $booking['booking_reference'], $note_text, $user['id'], $user['full_name']);
            
            $_SESSION['success_message'] = 'Note added successfully.';
            header("Location: booking-details.php?id=$booking_id");
            exit;
        } catch (PDOException $e) {
            $error_message = 'Failed to add note.';
        }
    }
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_status = $_POST['payment_status'];
    $previous_status = $booking['payment_status'];
    
    try {
        // Get VAT settings - more flexible check
        $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
        $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
        
        // Calculate amounts
        $totalAmount = (float)$booking['total_amount'];
        $vatAmount = $vatEnabled ? ($totalAmount * ($vatRate / 100)) : 0;
        $totalWithVat = $totalAmount + $vatAmount;
        
        // Update booking payment status
        $update_stmt = $pdo->prepare("UPDATE bookings SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$payment_status, $booking_id]);
        
        // If marking as paid, insert into payments table and update booking amounts
        if ($payment_status === 'paid' && $previous_status !== 'paid') {
            // Generate payment reference
            $payment_reference = 'PAY-' . date('Y') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
            
            // Insert into payments table
            $insert_payment = $pdo->prepare("
                INSERT INTO payments (
                    payment_reference, booking_type, booking_id, booking_reference,
                    payment_date, payment_amount, vat_rate, vat_amount, total_amount,
                    payment_method, payment_type, payment_status, invoice_generated,
                    status, recorded_by
                ) VALUES (?, 'room', ?, ?, CURDATE(), ?, ?, ?, ?, 'cash', 'full_payment', 'completed', 1, 'completed', ?)
            ");
            $insert_payment->execute([
                $payment_reference,
                $booking_id,
                $booking['booking_reference'],
                $totalAmount,
                $vatRate,
                $vatAmount,
                $totalWithVat,
                $user['id']
            ]);
            
            // Log to timeline
            logBookingPayment($booking_id, $booking['booking_reference'], $totalWithVat, 'full_payment', 'cash', 'completed', $user['id'], $payment_reference);
            
            // Update booking payment tracking columns
            $update_amounts = $pdo->prepare("
                UPDATE bookings
                SET amount_paid = ?, amount_due = 0, vat_rate = ?, vat_amount = ?,
                    total_with_vat = ?, last_payment_date = CURDATE()
                WHERE id = ?
            ");
            $update_amounts->execute([$totalWithVat, $vatRate, $vatAmount, $totalWithVat, $booking_id]);
            
            // Send invoice email
            require_once '../config/invoice.php';
            $invoice_result = sendPaymentInvoiceEmail($booking_id);
            
            if ($invoice_result['success']) {
                $_SESSION['success_message'] = 'Payment status updated. Payment recorded. Invoice sent successfully!';
            } else {
                error_log("Invoice email failed: " . $invoice_result['message']);
                $_SESSION['success_message'] = 'Payment status updated. Payment recorded. (Invoice email failed - check logs)';
            }
        } else {
            $_SESSION['success_message'] = 'Payment status updated.';
        }
        
        header("Location: booking-details.php?id=$booking_id");
        exit;
    } catch (PDOException $e) {
        $error_message = 'Failed to update payment status: ' . $e->getMessage();
        error_log("Payment update error: " . $e->getMessage());
    }
}

$site_name = getSetting('site_name');
$currency_symbol = getSetting('currency_symbol');

// Status styling
$status_colors = [
    'pending' => ['bg' => '#fef9ec', 'color' => '#92690a', 'icon' => 'fa-clock'],
    'tentative' => ['bg' => '#fff8e1', 'color' => '#8B7355', 'icon' => 'fa-hourglass-half'],
    'confirmed' => ['bg' => '#ecf8fd', 'color' => '#1a7a96', 'icon' => 'fa-check-circle'],
    'checked-in' => ['bg' => '#edf7f0', 'color' => '#1f7a42', 'icon' => 'fa-sign-in-alt'],
    'checked-out' => ['bg' => '#f3f4f5', 'color' => '#555c66', 'icon' => 'fa-sign-out-alt'],
    'cancelled' => ['bg' => '#fef2f2', 'color' => '#a03030', 'icon' => 'fa-times-circle'],
    'no-show' => ['bg' => '#f3e8e8', 'color' => '#6b4423', 'icon' => 'fa-user-slash'],
];
$current_status = $status_colors[$booking['status']] ?? ['bg' => '#f5f5f5', 'color' => '#666', 'icon' => 'fa-question'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <style>
        /* Booking Details Page - Premium Card Design */
        .booking-details-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        
        /* Hero Section */
        .booking-hero {
            background: linear-gradient(135deg, var(--deep-navy, #111111) 0%, var(--navy, #1A1A1A) 100%);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .booking-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 50%;
            background: linear-gradient(135deg, transparent 0%, rgba(212, 168, 67, 0.08) 100%);
            pointer-events: none;
        }
        
        .booking-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 24px;
        }
        
        .booking-hero-left h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            margin: 0 0 8px 0;
            color: var(--gold, #d4a843);
        }
        
        .booking-hero-left .reference {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 16px;
        }
        
        .booking-hero-left .reference strong {
            color: white;
            font-weight: 600;
        }
        
        .booking-hero-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hero-meta-item i {
            color: var(--gold, #d4a843);
            font-size: 16px;
        }
        
        .hero-meta-item span {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .booking-hero-right {
            text-align: right;
        }
        
        .hero-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 12px;
            background: <?php echo $current_status['bg']; ?>;
            color: <?php echo $current_status['color']; ?>;
        }
        
        .hero-status-badge i {
            font-size: 18px;
        }
        
        .hero-dates {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Cards Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1200px) {
            .details-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Info Card */
        .info-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        
        .info-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-card-header .icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .info-card-header .icon.guest { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .info-card-header .icon.room { background: linear-gradient(135deg, var(--gold, #8B7355) 0%, #6f5b43 100%); color: white; }
        .info-card-header .icon.stay { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .info-card-header .icon.payment { background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); color: white; }
        .info-card-header .icon.timeline { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .info-card-header .icon.notes { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
        
        .info-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
        }
        
        .info-card-body {
            padding: 20px 22px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f8f8f8;
        }
        
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .info-row:first-child {
            padding-top: 0;
        }
        
        .info-label {
            font-size: 13px;
            color: #888;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 14px;
            color: var(--navy, #1A1A1A);
            font-weight: 500;
            text-align: right;
        }
        
        .info-value.highlight {
            font-size: 20px;
            color: var(--gold, #8B7355);
            font-weight: 700;
        }
        
        .info-value.status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .info-value.status-badge.paid { background: #edf7f0; color: #1f7a42; }
        .info-value.status-badge.unpaid { background: #fef2f2; color: #a03030; }
        .info-value.status-badge.partial { background: #fef9ec; color: #92690a; }
        
        /* Guest Contact Actions */
        .guest-contact-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        
        .guest-contact-actions a {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .guest-contact-actions a.email {
            background: #ecf8fd;
            color: #1a7a96;
        }
        
        .guest-contact-actions a.email:hover {
            background: #1a7a96;
            color: white;
        }
        
        .guest-contact-actions a.phone {
            background: #edf7f0;
            color: #1f7a42;
        }
        
        .guest-contact-actions a.phone:hover {
            background: #1f7a42;
            color: white;
        }
        
        /* Room Card */
        .room-info-display {
            text-align: center;
            padding: 10px 0;
        }
        
        .room-name-display {
            font-size: 18px;
            font-weight: 700;
            color: var(--navy, #1A1A1A);
            margin-bottom: 4px;
        }
        
        .room-type-display {
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
        }
        
        .assigned-room-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--gold, #8B7355) 0%, #6f5b43 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .assigned-room-badge i {
            font-size: 16px;
        }
        
        .room-status-indicator {
            margin-top: 12px;
            font-size: 12px;
            color: #888;
        }
        
        .room-status-indicator .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .room-status-indicator .status-dot.available { background: #1f7a42; }
        .room-status-indicator .status-dot.occupied { background: #a03030; }
        .room-status-indicator .status-dot.cleaning { background: #92690a; }
        .room-status-indicator .status-dot.maintenance { background: #6c757d; }
        
        /* Stay Duration Display */
        .stay-duration-display {
            text-align: center;
            padding: 16px 0;
        }
        
        .date-range {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .date-box {
            text-align: center;
        }
        
        .date-box .day {
            font-size: 28px;
            font-weight: 700;
            color: var(--navy, #1A1A1A);
            line-height: 1;
        }
        
        .date-box .month-year {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        
        .date-box .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gold, #8B7355);
            margin-top: 8px;
            font-weight: 600;
        }
        
        .date-arrow {
            color: var(--gold, #8B7355);
            font-size: 20px;
        }
        
        .nights-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fb;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--navy, #1A1A1A);
            font-weight: 600;
        }
        
        .nights-display i {
            color: var(--gold, #8B7355);
        }
        
        /* Payment Summary */
        .payment-summary {
            text-align: center;
            padding: 10px 0;
        }
        
        .payment-amount {
            font-size: 32px;
            font-weight: 700;
            color: var(--navy, #1A1A1A);
            margin-bottom: 4px;
        }
        
        .payment-amount .currency {
            font-size: 18px;
            color: var(--gold, #8B7355);
        }
        
        .payment-reference {
            font-size: 11px;
            color: #aaa;
            margin-top: 8px;
        }
        
        /* Actions Card */
        .actions-card {
            grid-column: 1 / -1;
        }
        
        .actions-card .info-card-body {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .action-btn.confirm { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .action-btn.checkin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .action-btn.checkout { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .action-btn.cancel { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; }
        .action-btn.noshow { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; }
        .action-btn.edit { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .action-btn.back { background: #f0f0f0; color: #333; }
        .action-btn.convert { background: linear-gradient(135deg, var(--gold, #8B7355) 0%, #6f5b43 100%); color: white; }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Timeline Card */
        .timeline-card {
            grid-column: 1 / 2;
        }
        
        @media (max-width: 768px) {
            .timeline-card {
                grid-column: 1;
            }
        }
        
        .timeline-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .timeline-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .timeline-icon.create { background: #edf7f0; color: #1f7a42; }
        .timeline-icon.status_change { background: #ecf8fd; color: #1a7a96; }
        .timeline-icon.payment { background: #fef9ec; color: #92690a; }
        .timeline-icon.email { background: #f3e8ff; color: #7c3aed; }
        .timeline-icon.check_in { background: #edf7f0; color: #1f7a42; }
        .timeline-icon.check_out { background: #f3f4f5; color: #555c66; }
        .timeline-icon.conversion { background: #fef2f2; color: #a03030; }
        .timeline-icon.note { background: #fff7ed; color: #c2410c; }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            margin-bottom: 2px;
        }
        
        .timeline-meta {
            font-size: 11px;
            color: #888;
        }
        
        .timeline-time {
            font-size: 11px;
            color: #aaa;
            white-space: nowrap;
        }
        
        .timeline-empty {
            text-align: center;
            padding: 32px;
            color: #aaa;
        }
        
        .timeline-empty i {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }
        
        /* Notes Card */
        .notes-card {
            grid-column: 2 / 3;
        }
        
        @media (max-width: 768px) {
            .notes-card {
                grid-column: 1;
            }
        }
        
        .notes-form {
            margin-bottom: 16px;
        }
        
        .notes-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
            font-family: 'Jost', sans-serif;
        }
        
        .notes-form textarea:focus {
            outline: none;
            border-color: var(--gold, #8B7355);
            box-shadow: 0 0 0 3px rgba(139, 115, 85, 0.1);
        }
        
        .notes-form button {
            margin-top: 10px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--gold, #8B7355) 0%, #6f5b43 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .notes-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 115, 85, 0.3);
        }
        
        .notes-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .note-item {
            padding: 12px;
            background: #f8f9fb;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .note-item:last-child {
            margin-bottom: 0;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        
        .note-author {
            font-size: 12px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
        }
        
        .note-time {
            font-size: 10px;
            color: #aaa;
        }
        
        .note-text {
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
        
        .notes-empty {
            text-align: center;
            padding: 24px;
            color: #aaa;
        }
        
        .notes-empty i {
            font-size: 28px;
            margin-bottom: 8px;
            display: block;
        }
        
        /* Tentative Banner */
        .tentative-banner {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border: 1px solid var(--gold, #8B7355);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .tentative-banner i {
            font-size: 24px;
            color: var(--gold, #8B7355);
        }
        
        .tentative-banner-content h4 {
            margin: 0 0 4px 0;
            font-size: 15px;
            color: var(--navy, #1A1A1A);
        }
        
        .tentative-banner-content p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }
        
        .tentative-banner-content .expires {
            margin-top: 6px;
            font-size: 12px;
            color: #a03030;
            font-weight: 600;
        }
        
        /* Special Requests */
        .special-requests {
            background: #f8f9fb;
            border-radius: 10px;
            padding: 14px;
            margin-top: 12px;
        }
        
        .special-requests-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 6px;
        }
        
        .special-requests-text {
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
        
        /* Payment Form */
        .payment-form {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        
        .payment-form select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }
        
        .payment-form select:focus {
            outline: none;
            border-color: var(--gold, #8B7355);
        }
        
        /* Payment Disabled Notice */
        .payment-disabled-notice {
            margin-top: 12px;
            padding: 10px 14px;
            background: #edf7f0;
            border-radius: 10px;
            font-size: 12px;
            color: #1f7a42;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .payment-disabled-notice i {
            font-size: 14px;
        }
    </style>
</head>
<body>

<?php require_once 'includes/admin-header.php'; ?>

<div class="booking-details-page">
    
    <!-- Hero Section -->
    <div class="booking-hero">
        <div class="booking-hero-content">
            <div class="booking-hero-left">
                <h1><i class="fas fa-calendar-check"></i> Booking Details</h1>
                <div class="reference">Reference: <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></div>
                <div class="booking-hero-meta">
                    <div class="hero-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Created: <?php echo date('M j, Y \a\t g:i A', strtotime($booking['created_at'])); ?></span>
                    </div>
                    <?php if ($booking['updated_at'] && $booking['updated_at'] != $booking['created_at']): ?>
                    <div class="hero-meta-item">
                        <i class="fas fa-edit"></i>
                        <span>Updated: <?php echo date('M j, Y \a\t g:i A', strtotime($booking['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="booking-hero-right">
                <div class="hero-status-badge">
                    <i class="fas <?php echo $current_status['icon']; ?>"></i>
                    <?php echo ucfirst($booking['status']); ?>
                </div>
                <div class="hero-dates">
                    <?php echo date('M j', strtotime($booking['check_in_date'])); ?> - <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    $is_tentative = ($booking['status'] === 'tentative' || $booking['is_tentative'] == 1);
    if ($is_tentative && $booking['tentative_expires_at']):
    ?>
    <!-- Tentative Banner -->
    <div class="tentative-banner">
        <i class="fas fa-hourglass-half"></i>
        <div class="tentative-banner-content">
            <h4>Tentative Booking</h4>
            <p>This booking is on hold pending guest confirmation.</p>
            <div class="expires">
                <i class="fas fa-exclamation-triangle"></i> Expires: <?php echo date('M j, Y \a\t g:i A', strtotime($booking['tentative_expires_at'])); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Details Grid -->
    <div class="details-grid">
        
        <!-- Guest Information Card -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="icon guest"><i class="fas fa-user"></i></div>
                <h3>Guest Information</h3>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Country</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['guest_country'] ?: 'N/A'); ?></span>
                </div>
                <?php
                $child_guests = (int)($booking['child_guests'] ?? 0);
                $adult_guests = (int)($booking['adult_guests'] ?? max(1, ((int)$booking['number_of_guests']) - $child_guests));
                ?>
                <div class="info-row">
                    <span class="info-label">Guests</span>
                    <span class="info-value">
                        <?php echo $adult_guests; ?> adult<?php echo $adult_guests === 1 ? '' : 's'; ?>
                        <?php if ($child_guests > 0): ?>
                            + <?php echo $child_guests; ?> child<?php echo $child_guests === 1 ? '' : 'ren'; ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="guest-contact-actions">
                    <a href="mailto:<?php echo htmlspecialchars($booking['guest_email']); ?>" class="email">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                    <a href="tel:<?php echo htmlspecialchars($booking['guest_phone']); ?>" class="phone">
                        <i class="fas fa-phone"></i> Call
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Room Information Card -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="icon room"><i class="fas fa-bed"></i></div>
                <h3>Room Details</h3>
            </div>
            <div class="info-card-body">
                <div class="room-info-display">
                    <div class="room-name-display"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                    <div class="room-type-display">Room Type</div>
                    
                    <?php if ($booking['individual_room_id']): ?>
                    <div class="assigned-room-badge">
                        <i class="fas fa-door-open"></i>
                        <?php if ($booking['individual_room_name']): ?>
                            <?php echo htmlspecialchars($booking['individual_room_name']); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($booking['room_type_name'] ?: 'Room'); ?> <?php echo htmlspecialchars($booking['individual_room_number']); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($booking['individual_room_floor']): ?>
                    <div style="font-size: 12px; color: #888; margin-top: 8px;">
                        <i class="fas fa-layer-group"></i> Floor: <?php echo htmlspecialchars($booking['individual_room_floor']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="room-status-indicator">
                        <span class="status-dot <?php echo $booking['individual_room_status']; ?>"></span>
                        <?php echo ucfirst($booking['individual_room_status']); ?>
                    </div>
                    <?php else: ?>
                    <div style="color: #aaa; font-size: 13px; margin-top: 12px;">
                        <i class="fas fa-info-circle"></i> No specific room assigned
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stay Duration Card -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="icon stay"><i class="fas fa-calendar-alt"></i></div>
                <h3>Stay Duration</h3>
            </div>
            <div class="info-card-body">
                <div class="stay-duration-display">
                    <div class="date-range">
                        <div class="date-box">
                            <div class="day"><?php echo date('d', strtotime($booking['check_in_date'])); ?></div>
                            <div class="month-year"><?php echo date('M Y', strtotime($booking['check_in_date'])); ?></div>
                            <div class="label">Check-in</div>
                        </div>
                        <div class="date-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="date-box">
                            <div class="day"><?php echo date('d', strtotime($booking['check_out_date'])); ?></div>
                            <div class="month-year"><?php echo date('M Y', strtotime($booking['check_out_date'])); ?></div>
                            <div class="label">Check-out</div>
                        </div>
                    </div>
                    <div class="nights-display">
                        <i class="fas fa-moon"></i>
                        <?php echo $booking['number_of_nights']; ?> night<?php echo $booking['number_of_nights'] == 1 ? '' : 's'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Information Card -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="icon payment"><i class="fas fa-credit-card"></i></div>
                <h3>Payment Information</h3>
            </div>
            <div class="info-card-body">
                <div class="payment-summary">
                    <div class="payment-amount">
                        <span class="currency"><?php echo $currency_symbol; ?></span>
                        <?php echo number_format($booking['total_amount'], 0); ?>
                    </div>
                    <?php
                    $payment_status = $booking['actual_payment_status'];
                    $status_class = in_array($payment_status, ['paid', 'completed']) ? 'paid' : (in_array($payment_status, ['partial']) ? 'partial' : 'unpaid');
                    $status_labels = [
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partial',
                        'completed' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ];
                    ?>
                    <div class="info-value status-badge <?php echo $status_class; ?>" style="margin-top: 8px;">
                        <i class="fas <?php echo $status_class === 'paid' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo $status_labels[$payment_status] ?? ucfirst($payment_status); ?>
                    </div>
                    <?php if ($booking['payment_reference']): ?>
                    <div class="payment-reference">
                        <i class="fas fa-receipt"></i> <?php echo htmlspecialchars($booking['payment_reference']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($booking['payment_status'] !== 'paid'): ?>
                <div class="payment-form">
                    <form method="POST">
                        <input type="hidden" name="update_payment" value="1">
                        <select name="payment_status" onchange="this.form.submit()">
                            <option value="">Mark as Paid...</option>
                            <option value="paid">Mark as Paid</option>
                        </select>
                    </form>
                </div>
                <?php else: ?>
                <div class="payment-disabled-notice">
                    <i class="fas fa-check-circle"></i>
                    Payment received - Thank you!
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Timeline Card -->
        <div class="info-card timeline-card">
            <div class="info-card-header">
                <div class="icon timeline"><i class="fas fa-history"></i></div>
                <h3>Activity Timeline</h3>
            </div>
            <div class="info-card-body">
                <div class="timeline-list">
                    <?php if (empty($timeline)): ?>
                    <div class="timeline-empty">
                        <i class="fas fa-history"></i>
                        No activity recorded yet
                    </div>
                    <?php else: ?>
                    <?php foreach (array_slice($timeline, 0, 10) as $event): 
                        $type_info = formatActionType($event['action_type']);
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?php echo $event['action_type']; ?>">
                            <i class="fas <?php echo $type_info['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title"><?php echo htmlspecialchars($event['action']); ?></div>
                            <div class="timeline-meta">
                                <?php if ($event['performed_by_name']): ?>
                                by <?php echo htmlspecialchars($event['performed_by_name']); ?>
                                <?php else: ?>
                                System
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="timeline-time"><?php echo date('M j, H:i', strtotime($event['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Notes Card -->
        <div class="info-card notes-card">
            <div class="info-card-header">
                <div class="icon notes"><i class="fas fa-sticky-note"></i></div>
                <h3>Internal Notes</h3>
            </div>
            <div class="info-card-body">
                <div class="notes-form">
                    <form method="POST">
                        <textarea name="note_text" placeholder="Add a note about this booking..." required></textarea>
                        <button type="submit" name="add_note">
                            <i class="fas fa-plus"></i> Add Note
                        </button>
                    </form>
                </div>
                <div class="notes-list">
                    <?php if (empty($notes)): ?>
                    <div class="notes-empty">
                        <i class="fas fa-sticky-note"></i>
                        No notes yet
                    </div>
                    <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                    <div class="note-item">
                        <div class="note-header">
                            <span class="note-author"><?php echo htmlspecialchars($note['created_by_name'] ?? 'Unknown'); ?></span>
                            <span class="note-time"><?php echo date('M j, H:i', strtotime($note['created_at'])); ?></span>
                        </div>
                        <div class="note-text"><?php echo nl2br(htmlspecialchars($note['note_text'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($booking['special_requests']): ?>
        <!-- Special Requests (Full Width) -->
        <div class="info-card" style="grid-column: 1 / -1;">
            <div class="info-card-header">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;"><i class="fas fa-comment-dots"></i></div>
                <h3>Special Requests</h3>
            </div>
            <div class="info-card-body">
                <div class="special-requests-text">
                    <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions Card -->
        <div class="info-card actions-card">
            <div class="info-card-header">
                <div class="icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;"><i class="fas fa-bolt"></i></div>
                <h3>Quick Actions</h3>
            </div>
            <div class="info-card-body">
                <?php if ($booking['status'] == 'tentative' || $booking['is_tentative'] == 1): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Convert this tentative booking to confirmed?')">
                    <input type="hidden" name="booking_action" value="convert">
                    <button type="submit" class="action-btn convert"><i class="fas fa-check"></i> Convert to Confirmed</button>
                </form>
                <?php endif; ?>
                
                <?php if ($booking['status'] == 'pending'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm this booking?')">
                    <input type="hidden" name="booking_action" value="confirm">
                    <button type="submit" class="action-btn confirm"><i class="fas fa-check"></i> Confirm Booking</button>
                </form>
                <?php endif; ?>

                <?php if ($booking['status'] == 'confirmed'): ?>
                <?php $can_checkin = ($booking['actual_payment_status'] === 'paid' || $booking['actual_payment_status'] === 'completed'); ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Check in this guest?')">
                    <input type="hidden" name="booking_action" value="checkin">
                    <button type="submit" class="action-btn checkin" <?php echo $can_checkin ? '' : 'disabled title="Guest must pay before check-in"'; ?>>
                        <i class="fas fa-sign-in-alt"></i> Check In
                    </button>
                </form>
                <?php if (!$can_checkin): ?>
                    <small style="color: #dc3545; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle"></i> Payment required before check-in
                    </small>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($booking['status'] == 'checked-in'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Check out this guest?')">
                    <input type="hidden" name="booking_action" value="checkout">
                    <button type="submit" class="action-btn checkout"><i class="fas fa-sign-out-alt"></i> Check Out</button>
                </form>
                <?php endif; ?>
                
                <?php if (in_array($booking['status'], ['confirmed', 'pending']) && strtotime($booking['check_in_date']) < strtotime('today')): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this booking as no-show? The room will be released.')">
                    <input type="hidden" name="booking_action" value="noshow">
                    <button type="submit" class="action-btn noshow"><i class="fas fa-user-slash"></i> No-Show</button>
                </form>
                <?php endif; ?>

                <?php if (!in_array($booking['status'], ['checked-out', 'cancelled', 'no-show'])): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking? This cannot be undone.')">
                    <input type="hidden" name="booking_action" value="cancel">
                    <input type="hidden" name="cancellation_reason" value="Cancelled by admin">
                    <button type="submit" class="action-btn cancel"><i class="fas fa-times"></i> Cancel Booking</button>
                </form>
                <?php endif; ?>
                
                <a href="bookings.php" class="action-btn back"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
                <a href="edit-booking.php?id=<?php echo $booking_id; ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit Booking</a>
            </div>
        </div>
    </div>
</div>

<script src="js/admin-components.js"></script>
<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>