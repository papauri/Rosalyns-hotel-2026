<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';
require_once '../config/base-url.php';

require_once '../includes/modal.php';
require_once '../includes/alert.php';
require_once '../includes/booking-timeline.php';
$message = '';
$error = '';

function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'resend_email') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $email_type = $_POST['email_type'] ?? '';
            $cc_emails = $_POST['cc_emails'] ?? '';
            
            if ($booking_id <= 0) {
                throw new Exception('Invalid booking id');
            }
            
            // Get booking details
            $stmt = $pdo->prepare("
                SELECT b.*, r.name as room_name 
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            // Include email functions
            require_once '../config/email.php';
            
            // Parse CC emails
            $cc_array = [];
            if (!empty($cc_emails)) {
                $cc_array = array_filter(array_map('trim', explode(',', $cc_emails)));
                $cc_array = array_filter($cc_array, function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }
            
            // Send appropriate email based on type
            $email_result = ['success' => false, 'message' => 'Invalid email type'];
            
            switch ($email_type) {
                case 'booking_received':
                    $email_result = sendBookingReceivedEmail($booking);
                    break;
                case 'booking_confirmed':
                    $email_result = sendBookingConfirmedEmail($booking);
                    break;
                case 'tentative_confirmed':
                    $booking['tentative_expires_at'] = $booking['tentative_expires_at'] ?? date('Y-m-d H:i:s', strtotime('+48 hours'));
                    $email_result = sendTentativeBookingConfirmedEmail($booking);
                    break;
                case 'tentative_converted':
                    $email_result = sendTentativeBookingConvertedEmail($booking);
                    break;
                case 'booking_cancelled':
                    $cancellation_reason = 'Resent by admin';
                    $email_result = sendBookingCancelledEmail($booking, $cancellation_reason);
                    break;
                default:
                    throw new Exception('Invalid email type selected');
            }
            
            if ($email_result['success']) {
                $message = 'Email sent successfully to ' . htmlspecialchars($booking['guest_email']);
                if (!empty($cc_array)) {
                    $message .= ' (CC: ' . implode(', ', array_map(function($email) {
                        return htmlspecialchars($email);
                    }, $cc_array)) . ')';
                }
            } else {
                throw new Exception('Failed to send email: ' . $email_result['message']);
            }
            
        } elseif ($action === 'make_tentative') {
            $booking_id = (int)($_POST['id'] ?? 0);
            
            if ($booking_id <= 0) {
                throw new Exception('Invalid booking id');
            }
            
            // Get booking details
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            // Use centralized validation for tentative transition
            $validation = validateTentativeTransition($booking);
            if (!$validation['allowed']) {
                throw new Exception(getBookingActionErrorMessage('make_tentative', $validation['reason']));
            }
            
            // Get tentative duration setting
            $tentative_hours = (int)getSetting('tentative_duration_hours', 48);
            $expires_at = date('Y-m-d H:i:s', strtotime("+$tentative_hours hours"));
            $note = $_POST['note'] ?? '';
            
            // Convert to tentative status
            $update_stmt = $pdo->prepare("
                UPDATE bookings
                SET status = 'tentative',
                    is_tentative = 1,
                    tentative_expires_at = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$expires_at, $booking_id]);
            
            // Log the action
            $log_stmt = $pdo->prepare("
                INSERT INTO tentative_booking_log (
                    booking_id, action, new_expires_at, action_reason, performed_by, created_at
                ) VALUES (?, 'created', ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $booking_id,
                $expires_at,
                $note,
                $user['id']
            ]);
            
            // Send tentative booking email
            require_once '../config/email.php';
            $booking['tentative_expires_at'] = $expires_at;
            $email_result = sendTentativeBookingConfirmedEmail($booking);
            
            if ($email_result['success']) {
                $message = 'Booking converted to tentative! Confirmation email sent to guest.';
            } else {
                $message = 'Booking made tentative! (Email failed: ' . $email_result['message'] . ')';
            }
            
        } elseif ($action === 'convert_tentative') {
            $booking_id = (int)($_POST['id'] ?? 0);
            
            if ($booking_id <= 0) {
                throw new Exception('Invalid booking id');
            }
            
            // Get booking details WITH room information
            $stmt = $pdo->prepare("
                SELECT b.*, r.name as room_name, r.slug as room_slug
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            if ($booking['status'] !== 'tentative' || $booking['is_tentative'] != 1) {
                throw new Exception('This is not a tentative booking');
            }
            
            // Convert to confirmed status
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', is_tentative = 0 WHERE id = ?");
            $update_stmt->execute([$booking_id]);
            
            // Log the conversion
            $log_stmt = $pdo->prepare("
                INSERT INTO tentative_booking_log (
                    booking_id, action, action_reason, performed_by, created_at
                ) VALUES (?, 'converted', ?, ?, NOW())
            ");
            $log_stmt->execute([
                $booking_id,
                'Converted from tentative to confirmed by admin',
                $user['id']
            ]);
            
            // Send conversion email
            require_once '../config/email.php';
            $email_result = sendTentativeBookingConvertedEmail($booking);
            
            // Log email result for debugging
            error_log("Email sending result for booking {$booking_id}: " . json_encode($email_result));
            
            if ($email_result['success']) {
                if (isset($email_result['preview_url'])) {
                    $message = 'Tentative booking converted to confirmed! <a href="../' . htmlspecialchars($email_result['preview_url']) . '" target="_blank">View email preview</a> (Development Mode)';
                } else {
                    $message = 'Tentative booking converted to confirmed! Conversion email sent to ' . htmlspecialchars($booking['guest_email']);
                }
            } else {
                $message = 'Tentative booking converted! <strong>Email failed:</strong> ' . htmlspecialchars($email_result['message']);
                error_log("FAILED to send email for converted booking {$booking_id}: " . $email_result['message']);
            }
            
        } elseif ($action === 'convert_to_tentative') {
            $booking_id = (int)($_POST['id'] ?? 0);
            
            if ($booking_id <= 0) {
                throw new Exception('Invalid booking id');
            }
            
            // Get booking details
            $stmt = $pdo->prepare("
                SELECT b.*, r.name as room_name, r.slug as room_slug
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            // BLOCK: Confirmed bookings CANNOT be converted to tentative
            // This is a business rule to prevent accounting issues
            // Once a booking is confirmed or has any payment, it cannot revert to tentative
            $validation = validateTentativeTransition($booking);
            if (!$validation['allowed']) {
                throw new Exception(getBookingActionErrorMessage('make_tentative', $validation['reason']));
            }
            
            // If we reach here, the validation passed (shouldn't happen for confirmed bookings)
            throw new Exception('Operation not allowed: confirmed bookings cannot be converted to tentative');

        } elseif ($action === 'get_available_rooms') {
            if (!isAjaxRequest()) {
                throw new Exception('Invalid request');
            }

            $room_type_id = (int)($_POST['room_type_id'] ?? 0);
            $check_in = trim($_POST['check_in'] ?? '');
            $check_out = trim($_POST['check_out'] ?? '');
            $exclude_booking_id = !empty($_POST['exclude_booking_id']) ? (int)$_POST['exclude_booking_id'] : null;

            if ($room_type_id <= 0 || !$check_in || !$check_out) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required parameters',
                    'data' => []
                ]);
                exit;
            }

            $availableRooms = getAvailableIndividualRooms($room_type_id, $check_in, $check_out, $exclude_booking_id);

            $normalized = array_map(function ($room) {
                return [
                    'id' => (int)$room['id'],
                    'room_number' => $room['room_number'] ?? '',
                    'room_name' => $room['room_name'] ?? '',
                    'room_type_name' => $room['room_type_name'] ?? null,
                    'floor' => $room['floor'] ?? null,
                    'available' => true
                ];
            }, $availableRooms);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Available rooms loaded',
                'data' => $normalized
            ]);
            exit;

        } elseif ($action === 'assign_individual_room') {
            if (!isAjaxRequest()) {
                throw new Exception('Invalid request');
            }

            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $individual_room_id = (int)($_POST['individual_room_id'] ?? 0);

            if ($booking_id <= 0 || $individual_room_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid booking or room selection']);
                exit;
            }

            $bkStmt = $pdo->prepare("SELECT id, status, booking_reference FROM bookings WHERE id = ?");
            $bkStmt->execute([$booking_id]);
            $bookingToAssign = $bkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bookingToAssign) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                exit;
            }

            // Use centralized validation for room assignment
            $validation = validateRoomAssignment($bookingToAssign);
            if (!$validation['allowed']) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => getBookingActionErrorMessage('assign_room', $validation['reason'])]);
                exit;
            }

            $assigned = assignIndividualRoomToBooking($booking_id, $individual_room_id);

            if (!$assigned) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Selected room is not available for the booking dates']);
                exit;
            }

            // Note: assignIndividualRoomToBooking() already handles room status updates internally
            // for confirmed/checked-in bookings, so we don't need to duplicate it here

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Room assigned successfully']);
            exit;
             
        } elseif ($action === 'update_status') {
            $booking_id = (int)($_POST['id'] ?? 0);
            $new_status = $_POST['status'] ?? '';

            if ($booking_id <= 0) {
                throw new Exception('Invalid booking id');
            }

            // Enforce business rules:
            // - Check-in only allowed when confirmed AND paid
            // - Cancel check-in (undo) allowed only when currently checked-in
            if ($new_status === 'checked-in') {
                // Use centralized validation
                $check = $pdo->prepare("SELECT id, status, payment_status, individual_room_id, booking_reference, check_in_date FROM bookings WHERE id = ?");
                $check->execute([$booking_id]);
                $booking = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking) {
                    throw new Exception('Booking not found');
                }
                
                // Use the validation helper function
                $validation = validateCheckIn($booking);
                if (!$validation['allowed']) {
                    throw new Exception(getBookingActionErrorMessage('check_in', $validation['reason']));
                }
                
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'checked-in' WHERE id = ?");
                $stmt->execute([$booking_id]);

                $irStmt = $pdo->prepare("SELECT individual_room_id, booking_reference FROM bookings WHERE id = ?");
                $irStmt->execute([$booking_id]);
                $irData = $irStmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($irData['individual_room_id'])) {
                    updateIndividualRoomStatus(
                        (int)$irData['individual_room_id'],
                        'occupied',
                        'Guest checked in: ' . ($irData['booking_reference'] ?? ('Booking #' . $booking_id)),
                        $user['id'] ?? null
                    );
                }

                $message = 'Guest checked in!';

            } elseif ($new_status === 'cancel-checkin') {
                // Validate check-out first
                $check = $pdo->prepare("SELECT id, status, booking_reference, check_out_date FROM bookings WHERE id = ?");
                $check->execute([$booking_id]);
                $booking = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking) {
                    throw new Exception('Booking not found');
                }
                
                // Use the validation helper function (revert check-in = check-out validation)
                $validation = validateCheckOut($booking);
                if (!$validation['allowed']) {
                    throw new Exception("Cannot cancel check-in: " . $validation['reason']);
                }
                
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $stmt->execute([$booking_id]);

                $irStmt = $pdo->prepare("SELECT individual_room_id, booking_reference FROM bookings WHERE id = ?");
                $irStmt->execute([$booking_id]);
                $irData = $irStmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($irData['individual_room_id'])) {
                    updateIndividualRoomStatus(
                        (int)$irData['individual_room_id'],
                        'available',
                        'Check-in cancelled: ' . ($irData['booking_reference'] ?? ('Booking #' . $booking_id)),
                        $user['id'] ?? null
                    );
                }

                $message = 'Check-in cancelled (reverted to confirmed).';
            } else {
                $allowed = ['pending', 'confirmed', 'checked-out', 'cancelled'];
                if (!in_array($new_status, $allowed, true)) {
                    throw new Exception('Invalid status');
                }
                
                // Get current booking status and room_id before updating
                $check_stmt = $pdo->prepare("SELECT status, room_id, individual_room_id, booking_reference, check_in_date, check_out_date FROM bookings WHERE id = ?");
                $check_stmt->execute([$booking_id]);
                $current_booking = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_booking) {
                    throw new Exception('Booking not found');
                }
                
                $current_status = $current_booking['status'];
                $room_id = $current_booking['room_id'];
                
                // Validate status transition using helper function
                $transitionValidation = validateBookingStatusTransition($current_status, $new_status);
                if (!$transitionValidation['allowed']) {
                    throw new Exception($transitionValidation['reason']);
                }
                
                // Update booking status
                $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $booking_id]);
                $message = 'Booking status updated!';
                
                // Handle room availability changes
                if ($current_status === 'pending' && $new_status === 'confirmed') {
                    // Check availability before confirming
                    $availabilityCheck = checkRoomAvailability($room_id, $current_booking['check_in_date'], $current_booking['check_out_date'], $booking_id);
                    if (!$availabilityCheck['available']) {
                        // Rollback status change
                        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                        $stmt->execute([$current_status, $booking_id]);
                        $errorMsg = getBookingActionErrorMessage('confirm', $availabilityCheck['error'] ?? 'No rooms available');
                        throw new Exception($errorMsg);
                    }
                    
                    // Booking confirmed: decrement rooms_available
                    $update_room = $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available - 1 WHERE id = ? AND rooms_available > 0");
                    $update_room->execute([$room_id]);
                    
                    if ($update_room->rowCount() === 0) {
                        // This shouldn't happen if availability checks are working, but handle it
                        $message .= ' (Warning: Could not update room availability - room may be fully booked)';
                    } else {
                        $message .= ' Room availability updated.';
                    }
                    
                    // Send booking confirmed email
                    $booking_stmt = $pdo->prepare("
                        SELECT b.*, r.name as room_name 
                        FROM bookings b
                        LEFT JOIN rooms r ON b.room_id = r.id
                        WHERE b.id = ?
                    ");
                    $booking_stmt->execute([$booking_id]);
                    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($booking) {
                        // Include email functions
                        require_once '../config/email.php';
                        
                        // Send booking confirmed email
                        $email_result = sendBookingConfirmedEmail($booking);
                        
                        if ($email_result['success']) {
                            $message .= ' Confirmation email sent to guest.';
                        } else {
                            $message .= ' (Note: Confirmation email failed: ' . $email_result['message'] . ')';
                        }
                        
                        // Auto-assign individual room if not already assigned
                        if (empty($booking['individual_room_id'])) {
                            $autoAssignResult = autoAssignIndividualRoom($booking_id);
                            if ($autoAssignResult['success']) {
                                $message .= ' Room ' . htmlspecialchars($autoAssignResult['assigned_room_number']) . ' auto-assigned.';
                            } else {
                                $message .= ' (Note: Auto-assign skipped - ' . htmlspecialchars($autoAssignResult['message']) . ')';
                            }
                        }
                    }
                    
                } elseif ($current_status === 'confirmed' && $new_status === 'cancelled') {
                    // Booking cancelled: increment rooms_available
                    $update_room = $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms");
                    $update_room->execute([$room_id]);
                    
                    if ($update_room->rowCount() > 0) {
                        $message .= ' Room availability restored.';
                    }

                    if (!empty($current_booking['individual_room_id'])) {
                        updateIndividualRoomStatus(
                            (int)$current_booking['individual_room_id'],
                            'available',
                            'Booking cancelled: ' . ($current_booking['booking_reference'] ?? ('Booking #' . $booking_id)),
                            $user['id'] ?? null
                        );
                    }
                    
                    // Get booking details for email and logging
                    $booking_stmt = $pdo->prepare("
                        SELECT b.*, r.name as room_name
                        FROM bookings b
                        LEFT JOIN rooms r ON b.room_id = r.id
                        WHERE b.id = ?
                    ");
                    $booking_stmt->execute([$booking_id]);
                    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($booking) {
                        // Send cancellation email
                        require_once '../config/email.php';
                        $cancellation_reason = $_POST['cancellation_reason'] ?? 'Cancelled by admin';
                        $email_result = sendBookingCancelledEmail($booking, $cancellation_reason);
                        
                        // Log cancellation to database
                        $email_sent = $email_result['success'];
                        $email_status = $email_result['message'];
                        logCancellationToDatabase(
                            $booking['id'],
                            $booking['booking_reference'],
                            'room',
                            $booking['guest_email'],
                            $user['id'],
                            $cancellation_reason,
                            $email_sent,
                            $email_status
                        );
                        
                        // Log cancellation to file
                        logCancellationToFile(
                            $booking['booking_reference'],
                            'room',
                            $booking['guest_email'],
                            $user['full_name'] ?? $user['username'],
                            $cancellation_reason,
                            $email_sent,
                            $email_status
                        );
                        
                        if ($email_sent) {
                            $message .= ' Cancellation email sent.';
                        } else {
                            $message .= ' (Email failed: ' . $email_status . ')';
                        }
                    }
                }
            }

            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            }

        } elseif ($action === 'update_payment') {
            $payment_status = $_POST['payment_status'];
            $booking_id = $_POST['id'];
            
            // Get previous payment status and booking details
            $check = $pdo->prepare("SELECT payment_status, total_amount, booking_reference FROM bookings WHERE id = ?");
            $check->execute([$booking_id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                throw new Exception('Booking not found');
            }
            
            $previous_status = $row['payment_status'] ?? 'unpaid';
            $total_amount = (float)$row['total_amount'];
            $booking_reference = $row['booking_reference'];
            
            // Get VAT settings - more flexible check
            $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
            $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
            
            // Calculate amounts
            $vatAmount = $vatEnabled ? ($total_amount * ($vatRate / 100)) : 0;
            $totalWithVat = $total_amount + $vatAmount;
            
            // Update payment status
            $stmt = $pdo->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $booking_id]);
            $message = 'Payment status updated!';
            
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
                    $booking_reference,
                    $total_amount,
                    $vatRate,
                    $vatAmount,
                    $totalWithVat,
                    $user['id']
                ]);
                
                // Update booking payment tracking columns
                $update_amounts = $pdo->prepare("
                    UPDATE bookings
                    SET amount_paid = ?, amount_due = 0, vat_rate = ?, vat_amount = ?,
                        total_with_vat = ?, last_payment_date = CURDATE()
                    WHERE id = ?
                ");
                $update_amounts->execute([$totalWithVat, $vatRate, $vatAmount, $totalWithVat, $booking_id]);
                
                $message .= ' Payment recorded in accounting system.';
                
                // Send invoice email
                require_once '../config/invoice.php';
                $invoice_result = sendPaymentInvoiceEmail($booking_id);
                
                if ($invoice_result['success']) {
                    $message .= ' Invoice sent successfully!';
                } else {
                    error_log("Invoice email failed: " . $invoice_result['message']);
                    $message .= ' (Invoice email failed - check logs)';
                }
            }
        } elseif ($action === 'checkout') {
            // Checkout a checked-in booking
            $booking_id = intval($_POST['id'] ?? 0);
            if ($booking_id > 0) {
                // Get booking info
                $check_stmt = $pdo->prepare("SELECT id, status, room_id, individual_room_id, booking_reference FROM bookings WHERE id = ?");
                $check_stmt->execute([$booking_id]);
                $bk = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Use validation helper
                $validation = $bk ? validateCheckOut($bk) : ['allowed' => false, 'reason' => 'Booking not found'];
                
                if ($validation['allowed']) {
                    // Update status
                    $upd = $pdo->prepare("UPDATE bookings SET status = 'checked-out', updated_at = NOW() WHERE id = ?");
                    $upd->execute([$booking_id]);
                    
                    // Restore room availability
                    $restore = $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms");
                    $restore->execute([$bk['room_id']]);

                    if (!empty($bk['individual_room_id'])) {
                        updateIndividualRoomStatus(
                            (int)$bk['individual_room_id'],
                            'cleaning',
                            'Checkout completed: ' . ($bk['booking_reference'] ?? ('Booking #' . $booking_id)),
                            $user['id'] ?? null
                        );
                    }
                    
                    $message = 'Booking ' . htmlspecialchars($bk['booking_reference']) . ' checked out successfully. Room availability restored.';
                } else {
                    $error = getBookingActionErrorMessage('check_out', $validation['reason']);
                }
            }

        } elseif ($action === 'noshow') {
            // Mark a confirmed booking as no-show
            $booking_id = intval($_POST['id'] ?? 0);
            if ($booking_id > 0) {
                // Get booking info
                $check_stmt = $pdo->prepare("SELECT status, room_id, individual_room_id, booking_reference FROM bookings WHERE id = ?");
                $check_stmt->execute([$booking_id]);
                $bk = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bk) {
                    // Validate transition to no-show
                    $transitionValidation = validateBookingStatusTransition($bk['status'], 'no-show');
                    if (!$transitionValidation['allowed']) {
                        $error = getBookingActionErrorMessage('noshow', $transitionValidation['reason']);
                    } else {
                        // Update status to no-show
                        $upd = $pdo->prepare("UPDATE bookings SET status = 'no-show', updated_at = NOW() WHERE id = ?");
                        $upd->execute([$booking_id]);
                        
                        // Restore room availability (was decremented at confirmation)
                        $restore = $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms");
                        $restore->execute([$bk['room_id']]);

                        if (!empty($bk['individual_room_id'])) {
                            updateIndividualRoomStatus(
                                (int)$bk['individual_room_id'],
                                'available',
                                'Marked no-show: ' . ($bk['booking_reference'] ?? ('Booking #' . $booking_id)),
                                $user['id'] ?? null
                            );
                        }
                        
                        $message = 'Booking ' . htmlspecialchars($bk['booking_reference']) . ' marked as No-Show. Room availability restored.';
                    }
                } else {
                    $error = 'Booking not found.';
                }
            }
        } elseif ($action === 'get_booking_details') {
            if (!isAjaxRequest()) {
                throw new Exception('Invalid request');
            }

            $booking_id = (int)($_POST['booking_id'] ?? 0);
            if ($booking_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing booking ID'
                ]);
                exit;
            }

            // Fetch booking details with room and individual room info
            $stmt = $pdo->prepare("
                SELECT b.*,
                    r.name as room_name,
                    COALESCE(p.payment_status, b.payment_status) as actual_payment_status,
                    p.payment_reference,
                    ir.room_number as individual_room_number,
                    ir.room_name as individual_room_name,
                    ir.floor as individual_room_floor,
                    ir.status as individual_room_status
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                LEFT JOIN payments p ON b.id = p.booking_id AND p.booking_type = 'room' AND p.status = 'completed'
                LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Booking not found'
                ]);
                exit;
            }

            // Format dates and amounts
            $booking['check_in_date_formatted'] = date('M j, Y', strtotime($booking['check_in_date']));
            $booking['check_out_date_formatted'] = date('M j, Y', strtotime($booking['check_out_date']));
            $booking['created_at_formatted'] = date('M j, Y H:i', strtotime($booking['created_at']));
            $booking['total_formatted'] = number_format($booking['total_amount'], 2);
            $booking['status_label'] = ucfirst($booking['status']);
            $booking['payment_status_label'] = ucfirst($booking['actual_payment_status'] ?? $booking['payment_status']);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $booking
            ]);
            exit;
        } elseif ($action === 'get_all_room_types_for_upgrade') {
            if (!isAjaxRequest()) {
                throw new Exception('Invalid request');
            }

            $current_room_id = (int)($_POST['current_room_id'] ?? 0);
            $check_in = trim($_POST['check_in'] ?? '');
            $check_out = trim($_POST['check_out'] ?? '');

            // Fetch all active room types that are more expensive than current
            $stmt = $pdo->prepare("
                SELECT r.*,
                       (SELECT COUNT(*) FROM individual_rooms ir WHERE ir.room_type_id = r.id AND ir.is_active = 1 AND ir.status IN ('available', 'cleaning')) as available_count
                FROM rooms r
                WHERE r.is_active = 1
                AND r.id != ?
                AND r.price_per_night > (SELECT price_per_night FROM rooms WHERE id = ?)
                ORDER BY r.price_per_night ASC
            ");
            $stmt->execute([$current_room_id, $current_room_id]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filter rooms that have availability for the dates
            $availableRooms = [];
            foreach ($rooms as $room) {
                // Check if room type has availability
                $hasAvailability = checkRoomAvailability($room['id'], $check_in, $check_out);
                if ($hasAvailability['available']) {
                    $availableRooms[] = [
                        'id' => (int)$room['id'],
                        'name' => $room['name'],
                        'price_per_night' => (float)$room['price_per_night'],
                        'available_count' => (int)($room['available_count'] ?? 0)
                    ];
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $availableRooms
            ]);
            exit;
        } elseif ($action === 'upgrade_room_type') {
            if (!isAjaxRequest()) {
                throw new Exception('Invalid request');
            }

            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $new_room_id = (int)($_POST['new_room_id'] ?? 0);
            $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';

            if ($booking_id <= 0 || $new_room_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid booking or room selection']);
                exit;
            }

            // Get booking details
            $stmt = $pdo->prepare("
                SELECT b.*, r.name as old_room_name, r.price_per_night as old_price_per_night
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                exit;
            }

            // Get new room details
            $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$new_room_id]);
            $new_room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$new_room) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'New room type not found']);
                exit;
            }

            // Check if booking can be upgraded (only confirmed or pending bookings)
            if (!in_array($booking['status'], ['pending', 'confirmed'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Only pending or confirmed bookings can be upgraded']);
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Calculate new total based on new room price
                $nights = max(1, (int)$booking['number_of_nights']);
                $new_price_per_night = (float)$new_room['price_per_night'];
                $old_total = (float)$booking['total_amount'];
                $new_total = $new_price_per_night * $nights;

                // Calculate child supplement if applicable
                $child_supplement = 0.0;
                if (!empty($booking['child_guests']) && $booking['child_guests'] > 0) {
                    $child_multiplier = (float)($new_room['child_price_multiplier'] ?? 50);
                    $child_supplement = ($new_price_per_night * ($child_multiplier / 100) * $booking['child_guests'] * $nights);
                    $new_total += $child_supplement;
                }

                // Calculate price difference
                $price_difference = $new_total - $old_total;

                // Update booking
                $update_stmt = $pdo->prepare("
                    UPDATE bookings
                    SET room_id = ?,
                        total_amount = ?,
                        child_price_multiplier = ?,
                        child_supplement_total = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $new_room_id,
                    $new_total,
                    $new_room['child_price_multiplier'] ?? 50,
                    $child_supplement,
                    $booking_id
                ]);

                // Handle individual room reassignment
                $room_reassigned = false;
                $assigned_room_number = '';
                if (!empty($booking['individual_room_id'])) {
                    // Check if current individual room is compatible with new room type
                    $ir_stmt = $pdo->prepare("SELECT room_type_id, room_number FROM individual_rooms WHERE id = ?");
                    $ir_stmt->execute([$booking['individual_room_id']]);
                    $current_ir = $ir_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($current_ir && (int)$current_ir['room_type_id'] !== $new_room_id) {
                        // Current individual room doesn't match new room type, try to auto-assign
                        $autoAssignResult = autoAssignIndividualRoom($booking_id);
                        if ($autoAssignResult['success']) {
                            $room_reassigned = true;
                            $assigned_room_number = $autoAssignResult['assigned_room_number'];
                        } else {
                            // No available room, clear individual_room_id
                            $clear_stmt = $pdo->prepare("UPDATE bookings SET individual_room_id = NULL WHERE id = ?");
                            $clear_stmt->execute([$booking_id]);
                            
                            // Release old individual room
                            if ($booking['status'] === 'confirmed') {
                                updateIndividualRoomStatus(
                                    (int)$booking['individual_room_id'],
                                    'available',
                                    'Room type upgraded, room released',
                                    $user['id'] ?? null
                                );
                            }
                        }
                    }
                }

                // Log the upgrade
                $log_stmt = $pdo->prepare("
                    INSERT INTO booking_status_log (
                        booking_id, old_status, new_status, changed_by, change_reason, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $booking_id,
                    $booking['status'],
                    $booking['status'],
                    $user['id'] ?? null,
                    "Room type upgraded from {$booking['room_id']} ({$booking['old_room_name']}) to {$new_room_id} ({$new_room['name']}). Price difference: K " . number_format($price_difference, 2)
                ]);

                $pdo->commit();

                // Send upgrade email if requested
                $email_sent = false;
                $email_message = '';
                if ($send_email) {
                    require_once '../config/email.php';
                    $booking['old_room_name'] = $booking['old_room_name'];
                    $booking['new_room_name'] = $new_room['name'];
                    $booking['old_total'] = $old_total;
                    $booking['new_total'] = $new_total;
                    $booking['price_difference'] = $price_difference;
                    $email_result = sendBookingRoomUpgradeEmail($booking);
                    $email_sent = $email_result['success'];
                    $email_message = $email_result['message'];
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Room type upgraded successfully' .
                                ($room_reassigned ? ". New room {$assigned_room_number} assigned." : '') .
                                ($email_sent ? ' Upgrade email sent.' : ''),
                    'data' => [
                        'new_total' => $new_total,
                        'price_difference' => $price_difference,
                        'room_reassigned' => $room_reassigned,
                        'assigned_room_number' => $assigned_room_number,
                        'email_sent' => $email_sent,
                        'email_message' => $email_message
                    ]
                ]);
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Upgrade room type error: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error upgrading room type: ' . $e->getMessage()]);
                exit;
            }
        }

    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            // Return 200 OK with success: false so frontend can handle the error message gracefully
            // without browser console errors or fetch rejection
            http_response_code(200);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $export_stmt = $pdo->query("
            SELECT b.booking_reference, b.guest_name, b.guest_email, b.guest_phone, b.guest_country,
                   r.name as room_name, b.check_in_date, b.check_out_date, b.number_of_nights,
                   b.number_of_guests, b.total_amount, b.status, b.payment_status, b.occupancy_type,
                   b.special_requests, b.created_at
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            ORDER BY b.created_at DESC
        ");
        $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings-export-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Reference', 'Guest Name', 'Email', 'Phone', 'Country', 'Room', 
                          'Check-in', 'Check-out', 'Nights', 'Guests', 'Total', 'Status', 
                          'Payment', 'Occupancy', 'Special Requests', 'Created']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = 'Export failed: ' . $e->getMessage();
    }
}

// Handle search
$search_query = trim($_GET['search'] ?? '');
$filter_status = $_GET['filter_status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Fetch all bookings with room details and payment status from payments table
try {
    $where_clauses = [];
    $params = [];
    
    if (!empty($search_query)) {
        $where_clauses[] = "(b.booking_reference LIKE ? OR b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.guest_phone LIKE ? OR r.name LIKE ?)";
        $search_param = "%{$search_query}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }
    
    if (!empty($filter_status)) {
        $where_clauses[] = "b.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_date_from)) {
        $where_clauses[] = "b.check_in_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $where_clauses[] = "b.check_out_date <= ?";
        $params[] = $filter_date_to;
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $stmt = $pdo->prepare("
        SELECT b.*,
               r.name as room_name,
               COALESCE(p.payment_status, b.payment_status) as actual_payment_status,
               p.payment_reference,
               p.payment_date as last_payment_date,
               ir.room_number as individual_room_number,
               ir.room_name as individual_room_name,
               ir.floor as individual_room_floor,
               ir.status as individual_room_status,
               rt.name as room_type_name
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN payments p ON b.id = p.booking_id AND p.booking_type = 'room' AND p.status = 'completed'
        LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
        LEFT JOIN rooms rt ON ir.room_type_id = rt.id
        {$where_sql}
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also fetch conference inquiries
    $conf_stmt = $pdo->query("
        SELECT * FROM conference_inquiries 
        ORDER BY created_at DESC
    ");
    $conference_inquiries = $conf_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Error fetching bookings: ' . $e->getMessage();
    $bookings = [];
    $conference_inquiries = [];
}

// Count statistics
$total_bookings = count($bookings);
$pending = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$tentative = count(array_filter($bookings, fn($b) => $b['status'] === 'tentative' || $b['is_tentative'] == 1));
$confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
$checked_in = count(array_filter($bookings, fn($b) => $b['status'] === 'checked-in'));

// Additional statistics for new tabs
$checked_out = count(array_filter($bookings, fn($b) => $b['status'] === 'checked-out'));
$cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
$no_show = count(array_filter($bookings, fn($b) => $b['status'] === 'no-show'));

// Count paid/unpaid based on actual payment status from payments table
$paid = count(array_filter($bookings, fn($b) =>
    $b['actual_payment_status'] === 'paid' || $b['actual_payment_status'] === 'completed'
));
$unpaid = count(array_filter($bookings, fn($b) =>
    $b['actual_payment_status'] !== 'paid' && $b['actual_payment_status'] !== 'completed'
));

// Count expiring soon (tentative bookings expiring within 24 hours)
$now = new DateTime();
$expiring_soon = 0;
foreach ($bookings as $booking) {
    if (($booking['status'] === 'tentative' || $booking['is_tentative'] == 1) && $booking['tentative_expires_at']) {
        $expires_at = new DateTime($booking['tentative_expires_at']);
        $hours_until_expiry = ($expires_at->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($hours_until_expiry <= 24 && $hours_until_expiry > 0) {
            $expiring_soon++;
        }
    }
}

// Count today's check-ins (confirmed bookings with check-in today)
$today = new DateTime();
$today_str = $today->format('Y-m-d');
$today_checkins = count(array_filter($bookings, fn($b) =>
    $b['status'] === 'confirmed' && $b['check_in_date'] === $today_str
));

// Count today's check-outs (checked-in bookings with check-out today)
$today_checkouts = count(array_filter($bookings, fn($b) =>
    $b['status'] === 'checked-in' && $b['check_out_date'] === $today_str
));

// Count today's bookings (created today)
$today_bookings = count(array_filter($bookings, fn($b) =>
    date('Y-m-d', strtotime($b['created_at'])) === $today_str
));

// Count this week's bookings (created within the last 7 days)
$week_start = (clone $today)->modify('-7 days');
$week_bookings = count(array_filter($bookings, fn($b) =>
    strtotime($b['created_at']) >= $week_start->getTimestamp()
));

// Count this month's bookings (created this month)
$month_start = $today->format('Y-m-01');
$month_bookings = count(array_filter($bookings, fn($b) =>
    date('Y-m', strtotime($b['created_at'])) === date('Y-m')
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
</head>
<body>

    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="number"><?php echo $total_bookings; ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Pending</h3>
                <div class="number"><?php echo $pending; ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                <h3 style="color: var(--navy);">Tentative</h3>
                <div class="number" style="color: var(--gold);"><?php echo $tentative; ?></div>
            </div>
            <div class="stat-card confirmed">
                <h3>Confirmed</h3>
                <div class="number"><?php echo $confirmed; ?></div>
            </div>
            <div class="stat-card checked-in">
                <h3>Checked In</h3>
                <div class="number"><?php echo $checked_in; ?></div>
            </div>
        </div>

        <?php if ($message): ?>
            <?php showAlert($message, 'success'); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php showAlert($error, 'error'); ?>
        <?php endif; ?>

        <!-- Search & Tools Bar -->
        <div class="bookings-toolbar" style="background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; flex: 1; align-items: center;">
                <div style="position: relative; flex: 1; min-width: 200px;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search by name, reference, email, phone..."
                           style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Jost', sans-serif;">
                </div>
                <select name="filter_status" style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Jost', sans-serif; min-width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="tentative" <?php echo $filter_status === 'tentative' ? 'selected' : ''; ?>>Tentative</option>
                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="checked-in" <?php echo $filter_status === 'checked-in' ? 'selected' : ''; ?>>Checked In</option>
                    <option value="checked-out" <?php echo $filter_status === 'checked-out' ? 'selected' : ''; ?>>Checked Out</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="no-show" <?php echo $filter_status === 'no-show' ? 'selected' : ''; ?>>No-Show</option>
                </select>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" 
                       placeholder="From" title="Check-in from"
                       style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Jost', sans-serif;">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" 
                       placeholder="To" title="Check-out to"
                       style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Jost', sans-serif;">
                <button type="submit" style="padding: 10px 20px; background: var(--navy, #1A1A1A); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                    <a href="bookings.php" style="padding: 10px 16px; color: #666; text-decoration: none; font-size: 14px; border: 1px solid #ddd; border-radius: 8px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            <div style="display: flex; gap: 8px;">
                <a href="bookings.php?export=csv" class="quick-action" style="padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 13px;">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="create-booking.php" class="quick-action" style="padding: 10px 16px; background: var(--gold, #d4a843); color: var(--deep-navy, #0d0d1a); text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-plus"></i> New Booking
                </a>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" data-tab="all" data-count="<?php echo $total_bookings; ?>">
                    <i class="fas fa-list"></i>
                    All
                    <span class="tab-count"><?php echo $total_bookings; ?></span>
                </button>
                <button class="tab-button" data-tab="pending" data-count="<?php echo $pending; ?>">
                    <i class="fas fa-clock"></i>
                    Pending
                    <span class="tab-count"><?php echo $pending; ?></span>
                </button>
                <button class="tab-button" data-tab="tentative" data-count="<?php echo $tentative; ?>">
                    <i class="fas fa-hourglass-half"></i>
                    Tentative
                    <span class="tab-count"><?php echo $tentative; ?></span>
                </button>
                <button class="tab-button" data-tab="expiring-soon" data-count="<?php echo $expiring_soon; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    Expiring Soon
                    <span class="tab-count"><?php echo $expiring_soon; ?></span>
                </button>
                <button class="tab-button" data-tab="confirmed" data-count="<?php echo $confirmed; ?>">
                    <i class="fas fa-check-circle"></i>
                    Confirmed
                    <span class="tab-count"><?php echo $confirmed; ?></span>
                </button>
                <button class="tab-button" data-tab="today-checkins" data-count="<?php echo $today_checkins; ?>">
                    <i class="fas fa-calendar-day"></i>
                    Today's Check-ins
                    <span class="tab-count"><?php echo $today_checkins; ?></span>
                </button>
                <button class="tab-button" data-tab="today-checkouts" data-count="<?php echo $today_checkouts; ?>">
                    <i class="fas fa-calendar-times"></i>
                    Today's Check-outs
                    <span class="tab-count"><?php echo $today_checkouts; ?></span>
                </button>
                <button class="tab-button" data-tab="checked-in" data-count="<?php echo $checked_in; ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    Checked In
                    <span class="tab-count"><?php echo $checked_in; ?></span>
                </button>
                <button class="tab-button" data-tab="checked-out" data-count="<?php echo $checked_out; ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    Checked Out
                    <span class="tab-count"><?php echo $checked_out; ?></span>
                </button>
                <button class="tab-button" data-tab="cancelled" data-count="<?php echo $cancelled; ?>">
                    <i class="fas fa-times-circle"></i>
                    Cancelled
                    <span class="tab-count"><?php echo $cancelled; ?></span>
                </button>
                <button class="tab-button" data-tab="no-show" data-count="<?php echo $no_show; ?>">
                    <i class="fas fa-user-slash"></i>
                    No-Show
                    <span class="tab-count"><?php echo $no_show; ?></span>
                </button>
                <button class="tab-button" data-tab="paid" data-count="<?php echo $paid; ?>">
                    <i class="fas fa-dollar-sign"></i>
                    Paid
                    <span class="tab-count"><?php echo $paid; ?></span>
                </button>
                <button class="tab-button" data-tab="unpaid" data-count="<?php echo $unpaid; ?>">
                    <i class="fas fa-exclamation-circle"></i>
                    Unpaid
                    <span class="tab-count"><?php echo $unpaid; ?></span>
                </button>
                <button class="tab-button" data-tab="today-bookings" data-count="<?php echo $today_bookings; ?>">
                    <i class="fas fa-calendar-day"></i>
                    Today's Bookings
                    <span class="tab-count"><?php echo $today_bookings; ?></span>
                </button>
                <button class="tab-button" data-tab="week-bookings" data-count="<?php echo $week_bookings; ?>">
                    <i class="fas fa-calendar-week"></i>
                    This Week
                    <span class="tab-count"><?php echo $week_bookings; ?></span>
                </button>
                <button class="tab-button" data-tab="month-bookings" data-count="<?php echo $month_bookings; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    This Month
                    <span class="tab-count"><?php echo $month_bookings; ?></span>
                </button>
            </div>
        </div>

        <!-- Room Bookings -->
        <div class="bookings-section">
            <h3 class="section-title">
                <i class="fas fa-bed"></i> Room Bookings
                <span style="font-size: 14px; font-weight: normal; color: #666;">
                    (<?php echo count($bookings); ?> total)
                </span>
            </h3>

            <?php if (!empty($bookings)): ?>
                <div class="table-responsive">
                    <table class="booking-table bookings-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Ref</th>
                            <th style="width: 200px;">Guest Name</th>
                            <th style="width: 180px;">Room Type</th>
                            <th style="width: 140px;">Room Number</th>
                            <th style="width: 140px;">Check In</th>
                            <th style="width: 140px;">Check Out</th>
                            <th style="width: 80px;">Nights</th>
                            <th style="width: 80px;">Guests</th>
                            <th style="width: 120px;">Total</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 120px;">Payment</th>
                            <th style="width: 150px;">Created</th>
                            <th style="width: 350px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                                $is_tentative = ($booking['status'] === 'tentative' || $booking['is_tentative'] == 1);
                                $expires_soon = false;
                                if ($is_tentative && $booking['tentative_expires_at']) {
                                    $expires_at = new DateTime($booking['tentative_expires_at']);
                                    $now = new DateTime();
                                    $hours_until_expiry = ($expires_at->getTimestamp() - $now->getTimestamp()) / 3600;
                                    $expires_soon = $hours_until_expiry <= 24 && $hours_until_expiry > 0;
                                }
                            ?>
                            <tr <?php echo $is_tentative ? 'style="background: linear-gradient(90deg, rgba(139, 115, 85, 0.05) 0%, white 10%);"' : ''; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                    <?php if ($is_tentative): ?>
                                        <br><span class="tentative-indicator"><i class="fas fa-clock"></i> Tentative</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($booking['room_name']); ?>
                                </td>
                                <td>
                                    <?php if ($booking['individual_room_id']): ?>
                                        <span class="badge" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; font-weight: 600; font-size: 12px; padding: 6px 10px; border-radius: 6px; display: inline-block;">
                                            <i class="fas fa-door-open"></i>
                                            <?php if ($booking['individual_room_name']): ?>
                                                <?php echo htmlspecialchars($booking['individual_room_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($booking['individual_room_number']); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic; font-size: 12px;">
                                            <i class="fas fa-minus-circle"></i> Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                <td><?php echo $booking['number_of_nights']; ?></td>
                                <td><?php echo $booking['number_of_guests']; ?></td>
                                <td>
                                    <strong>K <?php echo number_format($booking['total_amount'], 0); ?></strong>
                                    <?php if ($is_tentative && $booking['tentative_expires_at']): ?>
                                        <?php if ($expires_soon): ?>
                                            <br><span class="expires-soon"><i class="fas fa-exclamation-triangle"></i> Expires soon!</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                    <?php if ($is_tentative && $booking['tentative_expires_at']): ?>
                                        <br><small style="color: #666; font-size: 10px;">
                                            <?php
                                                $expires = new DateTime($booking['tentative_expires_at']);
                                                echo 'Expires: ' . $expires->format('M d, H:i');
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $booking['actual_payment_status']; ?>">
                                        <?php
                                            $status = $booking['actual_payment_status'];
                                            // Map payment statuses to user-friendly labels
                                            $status_labels = [
                                                'paid' => 'Paid',
                                                'unpaid' => 'Unpaid',
                                                'partial' => 'Partial',
                                                'completed' => 'Paid',
                                                'pending' => 'Pending',
                                                'failed' => 'Failed',
                                                'refunded' => 'Refunded',
                                                'partially_refunded' => 'Partial Refund'
                                            ];
                                            echo $status_labels[$status] ?? ucfirst($status);
                                        ?>
                                    </span>
                                    <?php if ($booking['payment_reference']): ?>
                                        <br><small style="color: #666; font-size: 10px;">
                                            <?php echo htmlspecialchars($booking['payment_reference']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: #666; font-size: 11px;">
                                        <i class="fas fa-clock"></i> <?php echo date('M j, H:i', strtotime($booking['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <button class="quick-action view" onclick="openViewBookingModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="quick-action email" onclick="openResendEmailModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($booking['status']); ?>')">
                                        <i class="fas fa-envelope"></i> Email
                                    </button>
                                    <?php if (!$booking['individual_room_id'] && $booking['status'] === 'confirmed'): ?>
                                        <button class="quick-action assign" data-action="assign-room" data-booking-id="<?php echo $booking['id']; ?>" data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>" data-check-in="<?php echo htmlspecialchars($booking['check_in_date']); ?>" data-check-out="<?php echo htmlspecialchars($booking['check_out_date']); ?>" data-room-id="<?php echo $booking['room_id']; ?>">
                                            <i class="fas fa-door-open"></i> Assign Room
                                        </button>
                                    <?php elseif ($booking['individual_room_id'] && $booking['status'] === 'confirmed'): ?>
                                        <button class="quick-action assign" style="background: #28a745; color: white;" data-action="assign-room" data-booking-id="<?php echo $booking['id']; ?>" data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>" data-check-in="<?php echo htmlspecialchars($booking['check_in_date']); ?>" data-check-out="<?php echo htmlspecialchars($booking['check_out_date']); ?>" data-room-id="<?php echo $booking['room_id']; ?>">
                                            <i class="fas fa-edit"></i> Change Room
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($is_tentative): ?>
                                        <button class="quick-action confirm" onclick="convertTentativeBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-check"></i> Convert
                                        </button>
                                        <button class="quick-action cancel" onclick="openCancelBookingModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($booking['guest_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php elseif ($booking['status'] === 'pending'): ?>
                                        <?php
                                        // Only show "Make Tentative" button if no payment exists
                                        $can_make_tentative = !in_array($booking['payment_status'], ['paid', 'partial'], true);
                                        ?>
                                        <button class="quick-action confirm" onclick="updateStatus(<?php echo $booking['id']; ?>, 'confirmed')">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                        <?php if ($can_make_tentative): ?>
                                        <button class="quick-action tentative" data-action="make-tentative" data-booking-id="<?php echo $booking['id']; ?>" data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>" data-tentative-type="make_tentative">
                                            <i class="fas fa-clock"></i> Make Tentative
                                        </button>
                                        <?php endif; ?>
                                        <button class="quick-action cancel" onclick="openCancelBookingModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($booking['guest_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <?php
                                        $is_paid = ($booking['payment_status'] === 'paid');
                                        $room_assigned = !empty($booking['individual_room_id']);
                                        // Date-based validation: check-in only allowed on or after check-in date
                                        $checkin_date_obj = new DateTime($booking['check_in_date']);
                                        $checkin_date_obj->setTime(0, 0, 0);
                                        $today_dt = new DateTime('today');
                                        $checkin_date_reached = $checkin_date_obj <= $today_dt;
                                        $can_checkin = $is_paid && $room_assigned && $checkin_date_reached;
                                        $checkin_error = '';
                                        if (!$is_paid) {
                                            $checkin_error = 'Cannot check in: booking must be PAID first.';
                                        } elseif (!$room_assigned) {
                                            $checkin_error = 'Cannot check in: a room must be assigned first.';
                                        } elseif (!$checkin_date_reached) {
                                            $checkin_error = 'Cannot check in: Check-in date has not been reached yet (' . htmlspecialchars($booking['check_in_date']) . ').';
                                        }
                                        // Parameters for modal
                                        $guest_name = htmlspecialchars($booking['guest_name'], ENT_QUOTES);
                                        $check_in_date = htmlspecialchars($booking['check_in_date'], ENT_QUOTES);
                                        $payment_status = $booking['payment_status']; // 'paid', 'unpaid', etc
                                        $room_assigned_bool = $room_assigned ? 'true' : 'false';
                                        $booking_status = $booking['status'];
                                        ?>
                                        <button class="quick-action upgrade" data-action="upgrade-room" data-booking-id="<?php echo $booking['id']; ?>" data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>" data-current-room-id="<?php echo $booking['room_id']; ?>" data-current-room-name="<?php echo htmlspecialchars($booking['room_name'], ENT_QUOTES); ?>" data-guest-name="<?php echo $guest_name; ?>" data-check-in="<?php echo htmlspecialchars($booking['check_in_date'], ENT_QUOTES); ?>" data-check-out="<?php echo htmlspecialchars($booking['check_out_date'], ENT_QUOTES); ?>" data-total-amount="<?php echo $booking['total_amount']; ?>" data-payment-status="<?php echo $payment_status; ?>">
                                            <i class="fas fa-arrow-up"></i> Upgrade
                                        </button>
                                        <?php
                                        // NEVER show "Make Tentative" button for confirmed bookings
                                        // Once confirmed, a booking cannot revert to tentative (business rule)
                                        // This prevents accounting issues and maintains booking integrity
                                        ?>
                                        <button class="quick-action checkin <?php echo $can_checkin ? '' : 'disabled'; ?>" data-action="check-in" data-booking-id="<?php echo $booking['id']; ?>" data-booking-ref="<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>" data-guest-name="<?php echo $guest_name; ?>" data-check-in-date="<?php echo $check_in_date; ?>" data-payment-status="<?php echo $payment_status; ?>" data-room-assigned="<?php echo $room_assigned_bool; ?>" data-booking-status="<?php echo $booking_status; ?>"<?php if (!$can_checkin): ?> title="<?php echo htmlspecialchars($checkin_error); ?>"<?php endif; ?>>
                                            <i class="fas fa-sign-in-alt"></i> Check In
                                        </button>
                                        <?php
                                            // Show no-show button if check-in date has passed
                                            if ($checkin_date_obj < $today_dt):
                                        ?>
                                        <button class="quick-action" style="background: #795548; color: white;" onclick="markNoShow(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-user-slash"></i> No-Show
                                        </button>
                                        <?php endif; ?>
                                        <button class="quick-action cancel" onclick="openCancelBookingModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($booking['guest_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($booking['status'] === 'checked-in'): ?>
                                        <?php
                                        // Date-based validation for check-out (allow early checkout)
                                        $checkout_date_obj = new DateTime($booking['check_out_date']);
                                        $checkout_date_obj->setTime(0, 0, 0);
                                        $today_dt_checkout = new DateTime('today');
                                        // Allow checkout if today is on or after check-in date, or if check-out date is within 1 day
                                        $checkout_allowed = $checkout_date_obj <= $today_dt_checkout->modify('+1 day');
                                        ?>
                                        <button class="quick-action" style="background: #6c757d; color: white;" onclick="checkoutBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_reference'], ENT_QUOTES); ?>')"<?php if (!$checkout_allowed): ?> disabled title="Check-out date is too far in the future"<?php endif; ?>>
                                            <i class="fas fa-sign-out-alt"></i> Checkout
                                        </button>
                                        <button class="quick-action undo-checkin" onclick="updateStatus(<?php echo $booking['id']; ?>, 'cancel-checkin')">
                                            <i class="fas fa-undo"></i> Cancel Check-in
                                        </button>
                                        <!-- Note: Cancel button is hidden for checked-in bookings -->
                                    <?php endif; ?>
                                    <?php
                                    // Paid button: only show for pending or confirmed bookings that are not yet paid
                                    // Hide for tentative, checked-in, checked-out, cancelled, no-show
                                    $can_mark_paid = in_array($booking['status'], ['pending', 'confirmed']) && $booking['payment_status'] !== 'paid';
                                    ?>
                                    <?php if ($can_mark_paid): ?>
                                        <button class="quick-action paid" onclick="updatePayment(<?php echo $booking['id']; ?>, 'paid')">
                                            <i class="fas fa-dollar-sign"></i> Paid
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No room bookings yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Conference Inquiries -->
        <div class="bookings-section">
            <h3 class="section-title">
                <i class="fas fa-users"></i> Conference Inquiries
                <span style="font-size: 14px; font-weight: normal; color: #666;">
                    (<?php echo count($conference_inquiries); ?> total)
                </span>
            </h3>

            <?php if (!empty($conference_inquiries)): ?>
                <div class="table-responsive">
                    <table class="booking-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Date Received</th>
                            <th style="width: 220px;">Company/Name</th>
                            <th style="width: 220px;">Contact</th>
                            <th style="width: 180px;">Event Type</th>
                            <th style="width: 140px;">Expected Date</th>
                            <th style="width: 100px;">Attendees</th>
                            <th style="width: 140px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conference_inquiries as $inquiry): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($inquiry['company_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($inquiry['contact_person']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($inquiry['email']); ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($inquiry['phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($inquiry['event_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($inquiry['expected_date'])); ?></td>
                                <td><?php echo $inquiry['number_of_attendees']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $inquiry['status']; ?>">
                                        <?php echo ucfirst($inquiry['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No conference inquiries yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Ensure Alert is defined (fallback for early script execution)
        if (typeof Alert === 'undefined') {
            window.Alert = {
                show: function(message, type) {
                    console.log('Alert (fallback):', message, type);
                }
            };
        }
        // ============================================
        // LOADING STATE MANAGEMENT FOR BUTTONS
        // ============================================
        
        /**
         * Set loading state on a button to prevent double-clicks
         * @param {HTMLElement} button - The button element
         * @param {boolean} isLoading - Whether to show loading state
         * @param {string} originalContent - Original button content (optional)
         */
        function setButtonLoading(button, isLoading, originalContent = null) {
            if (!button) return;
            
            if (isLoading) {
                // Store original content if not already stored
                if (!button.dataset.originalContent) {
                    button.dataset.originalContent = originalContent || button.innerHTML;
                }
                
                // Disable button and show loading spinner
                button.disabled = true;
                button.classList.add('btn-loading');
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.style.pointerEvents = 'none';
                button.style.opacity = '0.7';
            } else {
                // Restore original state
                button.disabled = false;
                button.classList.remove('btn-loading');
                button.innerHTML = button.dataset.originalContent || originalContent;
                button.style.pointerEvents = '';
                button.style.opacity = '';
                delete button.dataset.originalContent;
            }
        }
        
        /**
         * Set loading state on all quick-action buttons in a container
         * @param {HTMLElement} container - Container element
         * @param {boolean} isLoading - Loading state
         * @param {HTMLElement} excludeButton - Button to exclude (the one clicked)
         */
        function setAllButtonsLoading(container, isLoading, excludeButton = null) {
            const buttons = container.querySelectorAll('.quick-action, .btn');
            buttons.forEach(btn => {
                if (btn !== excludeButton) {
                    if (isLoading) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.pointerEvents = 'none';
                    } else {
                        btn.disabled = false;
                        btn.style.opacity = '';
                        btn.style.pointerEvents = '';
                    }
                }
            });
        }
        
        /**
         * Show global loading overlay
         */
        function showLoadingOverlay(message = 'Processing...') {
            let overlay = document.getElementById('globalLoadingOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'globalLoadingOverlay';
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999;
                `;
                overlay.innerHTML = `
                    <div style="background: white; padding: 30px 40px; border-radius: 12px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--gold, #8B7355); margin-bottom: 16px; display: block;"></i>
                        <div id="loadingMessage" style="font-size: 16px; color: var(--navy, #1A1A1A); font-weight: 500;">${message}</div>
                    </div>
                `;
                document.body.appendChild(overlay);
            } else {
                overlay.style.display = 'flex';
                document.getElementById('loadingMessage').textContent = message;
            }
        }
        
        /**
         * Hide global loading overlay
         */
        function hideLoadingOverlay() {
            const overlay = document.getElementById('globalLoadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // Tab switching functionality
        let currentTab = 'all';

        function switchTab(tabName) {
            currentTab = tabName;
            
            // Update active tab button
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.tab === tabName) {
                    btn.classList.add('active');
                }
            });
            
            // Filter table rows
            filterBookingsTable(tabName);
            
            // Update section title
            updateSectionTitle(tabName);
        }

        function filterBookingsTable(tabName) {
            const table = document.querySelector('.booking-table tbody');
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            let visibleCount = 0;
            
            // Get today's date for comparison
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayStr = today.toISOString().split('T')[0];
            
            // Calculate week start (7 days ago)
            const weekStart = new Date(today);
            weekStart.setDate(weekStart.getDate() - 7);
            
            // Calculate month start (first day of current month)
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            
            rows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(10)'); // Status column
                const paymentCell = row.querySelector('td:nth-child(11)'); // Payment column
                const checkInCell = row.querySelector('td:nth-child(5)'); // Check-in date column
                const checkOutCell = row.querySelector('td:nth-child(6)'); // Check-out date column
                const createdCell = row.querySelector('td:nth-child(12)'); // Created timestamp column
                
                if (!statusCell || !paymentCell) {
                    row.style.display = 'none';
                    return;
                }
                
                const statusBadge = statusCell.querySelector('.badge');
                const paymentBadge = paymentCell.querySelector('.badge');
                
                if (!statusBadge || !paymentBadge) {
                    row.style.display = 'none';
                    return;
                }
                
                // Normalize status: convert to lowercase and replace spaces with hyphens
                // Handles: "Pending" -> "pending", "Checked In" -> "checked-in", "No-show" -> "no-show"
                let status = statusBadge.textContent.trim().toLowerCase();
                // Replace spaces with hyphens for multi-word statuses
                status = status.replace(/\s+/g, '-').replace(/'/g, '');
                
                const payment = paymentBadge.textContent.trim().toLowerCase();
                
                // Parse dates from table cells
                const checkInDate = checkInCell ? new Date(checkInCell.textContent.trim()) : null;
                const checkOutDate = checkOutCell ? new Date(checkOutCell.textContent.trim()) : null;
                
                // Parse created_at timestamp from column 12
                // Format: "Feb 1, 14:30" or similar
                let createdDate = null;
                if (createdCell) {
                    const createdText = createdCell.textContent.trim();
                    // Parse the date format "M j, H:i" (e.g., "Feb 1, 14:30")
                    const currentYear = today.getFullYear();
                    const createdMatch = createdText.match(/(\w+)\s+(\d+),\s+(\d+):(\d+)/);
                    if (createdMatch) {
                        const months = { 'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
                                        'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11 };
                        const month = months[createdMatch[1]];
                        const day = parseInt(createdMatch[2]);
                        const hour = parseInt(createdMatch[3]);
                        const minute = parseInt(createdMatch[4]);
                        createdDate = new Date(currentYear, month, day, hour, minute);
                    }
                }
                
                // Check if tentative booking is expiring soon (within 24 hours)
                const isExpiringSoon = row.innerHTML.includes('Expires soon') ||
                                      (status === 'tentative' && row.querySelector('.expires-soon'));
                
                // Check if check-in/check-out is today
                const isTodayCheckIn = checkInDate &&
                                      checkInDate.toISOString().split('T')[0] === todayStr &&
                                      status === 'confirmed';
                const isTodayCheckOut = checkOutDate &&
                                       checkOutDate.toISOString().split('T')[0] === todayStr &&
                                       status === 'checked-in';
                
                // Check time-based filters
                const isTodayBooking = createdDate &&
                                      createdDate.toISOString().split('T')[0] === todayStr;
                const isWeekBooking = createdDate &&
                                     createdDate >= weekStart;
                const isMonthBooking = createdDate &&
                                      createdDate >= monthStart;
                
                let isVisible = false;
                
                switch(tabName) {
                    case 'all':
                        isVisible = true;
                        break;
                    case 'pending':
                        isVisible = status === 'pending';
                        break;
                    case 'tentative':
                        isVisible = status === 'tentative' || row.innerHTML.includes('Tentative');
                        break;
                    case 'expiring-soon':
                        isVisible = isExpiringSoon;
                        break;
                    case 'confirmed':
                        isVisible = status === 'confirmed';
                        break;
                    case 'today-checkins':
                        isVisible = isTodayCheckIn;
                        break;
                    case 'today-checkouts':
                        isVisible = isTodayCheckOut;
                        break;
                    case 'checked-in':
                        isVisible = status === 'checked-in';
                        break;
                    case 'checked-out':
                        isVisible = status === 'checked-out';
                        break;
                    case 'cancelled':
                        isVisible = status === 'cancelled';
                        break;
                    case 'no-show':
                        isVisible = status === 'no-show';
                        break;
                    case 'paid':
                        isVisible = payment === 'paid' || payment === 'completed';
                        break;
                    case 'unpaid':
                        isVisible = payment !== 'paid' && payment !== 'completed';
                        break;
                    case 'today-bookings':
                        isVisible = isTodayBooking;
                        break;
                    case 'week-bookings':
                        isVisible = isWeekBooking;
                        break;
                    case 'month-bookings':
                        isVisible = isMonthBooking;
                        break;
                }
                
                if (isVisible) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count in section title
            const countSpan = document.querySelector('.section-title span');
            if (countSpan) {
                countSpan.textContent = `(${visibleCount} shown)`;
            }
        }

        function updateSectionTitle(tabName) {
            const titleElement = document.querySelector('.section-title');
            if (!titleElement) return;
            
            const tabTitles = {
                'all': 'All Room Bookings',
                'pending': 'Pending Bookings',
                'tentative': 'Tentative Bookings',
                'expiring-soon': 'Expiring Soon (Urgent)',
                'confirmed': 'Confirmed Bookings',
                'today-checkins': "Today's Check-ins",
                'today-checkouts': "Today's Check-outs",
                'checked-in': 'Checked In Guests',
                'checked-out': 'Checked Out Bookings',
                'cancelled': 'Cancelled Bookings',
                'no-show': 'No-Show Bookings',
                'paid': 'Paid Bookings',
                'unpaid': 'Unpaid Bookings',
                'today-bookings': "Today's Bookings",
                'week-bookings': "This Week's Bookings",
                'month-bookings': "This Month's Bookings"
            };
            
            const icon = titleElement.querySelector('i');
            const countSpan = titleElement.querySelector('span');
            
            let newTitle = tabTitles[tabName] || 'Room Bookings';
            let newIcon = 'fa-bed';
            
            if (tabName === 'pending') newIcon = 'fa-clock';
            if (tabName === 'tentative') newIcon = 'fa-hourglass-half';
            if (tabName === 'expiring-soon') newIcon = 'fa-exclamation-triangle';
            if (tabName === 'confirmed') newIcon = 'fa-check-circle';
            if (tabName === 'today-checkins') newIcon = 'fa-calendar-day';
            if (tabName === 'today-checkouts') newIcon = 'fa-calendar-times';
            if (tabName === 'checked-in') newIcon = 'fa-sign-in-alt';
            if (tabName === 'checked-out') newIcon = 'fa-sign-out-alt';
            if (tabName === 'cancelled') newIcon = 'fa-times-circle';
            if (tabName === 'no-show') newIcon = 'fa-user-slash';
            if (tabName === 'paid') newIcon = 'fa-dollar-sign';
            if (tabName === 'unpaid') newIcon = 'fa-exclamation-circle';
            if (tabName === 'today-bookings') newIcon = 'fa-calendar-day';
            if (tabName === 'week-bookings') newIcon = 'fa-calendar-week';
            if (tabName === 'month-bookings') newIcon = 'fa-calendar-alt';
            
            titleElement.innerHTML = `<i class="fas ${newIcon}"></i> ${newTitle} `;
            if (countSpan) {
                titleElement.appendChild(countSpan);
            }
        }

        // Initialize tab click handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any existing event listeners by cloning
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                // Create a new reference to avoid duplicate listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
                
                newButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const tabName = this.dataset.tab;
                    if (tabName) {
                        switchTab(tabName);
                    }
                });
            });
            
            // Initial filter - ensure "All" tab is active
            switchTab('all');
        });

        function makeTentative(id, button) {
            if (!confirm('Convert this pending booking to a tentative reservation? This will hold the room for 48 hours and send a confirmation email to the guest.')) {
                return;
            }
            
            // Show loading state
            if (button) {
                setButtonLoading(button, true);
            } else {
                const activeBtn = document.activeElement;
                if (activeBtn && activeBtn.classList.contains('quick-action')) {
                    setButtonLoading(activeBtn, true);
                }
            }
            showLoadingOverlay('Converting to tentative...');
            
            const formData = new FormData();
            formData.append('action', 'make_tentative');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (button) {
                        setButtonLoading(button, false);
                    }
                    Alert.show('Error converting booking to tentative', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (button) {
                    setButtonLoading(button, false);
                }
                Alert.show('Error converting booking to tentative', 'error');
            });
        }
        
        function convertTentativeBooking(id) {
            if (!confirm('Convert this tentative booking to a confirmed reservation? This will send a confirmation email to the guest.')) {
                return;
            }
            
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Converting booking to confirmed...');
            
            const formData = new FormData();
            formData.append('action', 'convert_tentative');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error converting booking', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error converting booking', 'error');
            });
        }
        
        function convertToTentative(id) {
            if (!confirm('Convert this confirmed booking to tentative? This will place the booking on hold for 48 hours and send an email to the guest.')) {
                return;
            }
            
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Converting booking to tentative...');
            
            const formData = new FormData();
            formData.append('action', 'convert_to_tentative');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error converting booking to tentative', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error converting booking to tentative', 'error');
            });
        }
        
        function updateStatus(id, status) {
            // Find the button that was clicked (if any)
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            // Show loading state on button
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            
            // Show global loading overlay
            showLoadingOverlay('Updating booking status...');
            
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', id);
            formData.append('status', status);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - page will reload
                    window.location.reload();
                } else {
                    // Error - restore button state
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show(data.message || 'Error updating status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error updating status', 'error');
            });
        }

        function updatePayment(id, payment_status) {
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Updating payment status...');
            
            const formData = new FormData();
            formData.append('action', 'update_payment');
            formData.append('id', id);
            formData.append('payment_status', payment_status);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error updating payment', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error updating payment', 'error');
            });
        }

        function cancelBooking(id, reference) {
            const reason = prompt('Enter cancellation reason (optional):');
            if (reason === null) {
                return; // User cancelled
            }
            
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Cancelling booking...');
            
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', id);
            formData.append('status', 'cancelled');
            formData.append('cancellation_reason', reason || 'Cancelled by admin');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error cancelling booking', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error cancelling booking', 'error');
            });
        }

        function checkoutBooking(id, reference) {
            if (!confirm('Check out booking ' + reference + '?')) return;
            
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Checking out booking...');
            
            const formData = new FormData();
            formData.append('action', 'checkout');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error checking out booking', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error checking out booking', 'error');
            });
        }

        function markNoShow(id, reference) {
            if (!confirm('Mark booking ' + reference + ' as No-Show? This will restore room availability.')) return;
            
            const activeBtn = document.activeElement;
            const isQuickAction = activeBtn && activeBtn.classList.contains('quick-action');
            
            if (isQuickAction) {
                setButtonLoading(activeBtn, true);
            }
            showLoadingOverlay('Marking booking as No-Show...');
            
            const formData = new FormData();
            formData.append('action', 'noshow');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (isQuickAction) {
                        setButtonLoading(activeBtn, false);
                    }
                    Alert.show('Error marking as no-show', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (isQuickAction) {
                    setButtonLoading(activeBtn, false);
                }
                Alert.show('Error marking as no-show', 'error');
            });
        }
    </script>
    
    <!-- Email Resend Modal -->
    <div id="resendEmailModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Resend Email</h3>
                <button class="close-modal" onclick="closeResendEmailModal()">&times;</button>
            </div>
            <form id="resendEmailForm" method="POST" action="">
                <input type="hidden" name="action" value="resend_email">
                <input type="hidden" name="booking_id" id="modal_booking_id" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                        <input type="text" id="modal_booking_reference" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    
                    <div class="form-group">
                        <label for="email_type"><i class="fas fa-envelope"></i> Email Type:</label>
                        <select name="email_type" id="email_type" class="form-control" required>
                            <option value="">-- Select Email Type --</option>
                            <option value="booking_received">Booking Received (Initial confirmation)</option>
                            <option value="booking_confirmed">Booking Confirmed</option>
                            <option value="tentative_confirmed">Tentative Booking Confirmed</option>
                            <option value="tentative_converted">Tentative Converted to Confirmed</option>
                            <option value="booking_cancelled">Booking Cancelled</option>
                        </select>
                        <small style="color: #666;">Select the type of email to resend based on current booking status</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="cc_emails"><i class="fas fa-users"></i> CC Emails (Optional):</label>
                        <input type="text" name="cc_emails" id="cc_emails" class="form-control" placeholder="email1@example.com, email2@example.com">
                        <small style="color: #666;">Comma-separated email addresses to CC (e.g., hotel manager, accounting)</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeResendEmailModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Email</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Quick Room Assignment Modal -->
    <div id="quickRoomAssignModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-door-open"></i> Assign Room</h3>
                <button class="close-modal" onclick="closeQuickRoomAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                    <input type="text" id="quick_assign_booking_ref" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Dates:</label>
                    <input type="text" id="quick_assign_dates" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-door-open"></i> Select Individual Room:</label>
                    <div id="quick_assign_room_list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; padding: 10px;">
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <i class="fas fa-spinner fa-spin"></i> Loading available rooms...
                        </div>
                    </div>
                </div>
                <input type="hidden" id="quick_assign_booking_id">
                <input type="hidden" id="quick_assign_room_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeQuickRoomAssignModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitQuickRoomAssign()"><i class="fas fa-check"></i> Assign Room</button>
            </div>
        </div>
    </div>
    
    <!-- Make Tentative Modal -->
    <div id="makeTentativeModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-clock"></i> Make Tentative</h3>
                <button class="close-modal" onclick="closeMakeTentativeModal()">&times;</button>
            </div>
            <form id="makeTentativeForm" method="POST" action="">
                <input type="hidden" name="action" id="make_tentative_action" value="">
                <input type="hidden" name="id" id="make_tentative_booking_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                        <input type="text" id="make_tentative_ref" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label for="tentative_note"><i class="fas fa-sticky-note"></i> Optional Note:</label>
                        <textarea name="note" id="tentative_note" class="form-control" rows="3" placeholder="Add a note about why this booking is being made tentative..."></textarea>
                        <small style="color: #666;">This note will be recorded in the booking log.</small>
                    </div>
                    <div class="form-group" style="background: #fff8e1; padding: 12px; border-radius: 8px;">
                        <p style="margin: 0; color: #8B7355; font-size: 13px;">
                            <i class="fas fa-info-circle"></i>
                            This will convert the booking to a tentative reservation, holding the room for
                            <strong id="tentative_duration_display">48</strong> hours. A confirmation email will be sent to the guest.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeMakeTentativeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Make Tentative</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Check In Modal -->
    <div id="checkInModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-sign-in-alt"></i> Check In Guest</h3>
                <button class="close-modal" onclick="closeCheckInModal()">&times;</button>
            </div>
            <form id="checkInForm" method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="checked-in">
                <input type="hidden" name="id" id="checkin_booking_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                        <input type="text" id="checkin_booking_ref" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Guest Name:</label>
                        <input type="text" id="checkin_guest_name" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Check-in Date:</label>
                        <input type="text" id="checkin_date" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label for="checkin_note"><i class="fas fa-sticky-note"></i> Optional Note:</label>
                        <textarea name="checkin_note" id="checkin_note" class="form-control" rows="2" placeholder="Add any check-in notes..."></textarea>
                    </div>
                    <div class="prerequisites" id="checkin_prerequisites" style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                        <h4 style="margin-top: 0; color: var(--navy); font-size: 14px;"><i class="fas fa-list-check"></i> Prerequisites</h4>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
                            <li id="prereq_payment"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Payment must be marked as PAID</li>
                            <li id="prereq_room"><i class="fas fa-times-circle" style="color: #dc3545;"></i> A room must be assigned</li>
                            <li id="prereq_status"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Booking must be CONFIRMED</li>
                        </ul>
                        <p id="checkin_error_message" style="color: #dc3545; font-size: 12px; margin: 8px 0 0 0; display: none;"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCheckInModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Check In</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div id="cancelBookingModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Booking</h3>
                <button class="close-modal" onclick="closeCancelBookingModal()">&times;</button>
            </div>
            <form id="cancelBookingForm" method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="cancelled">
                <input type="hidden" name="id" id="cancel_booking_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                        <input type="text" id="cancel_booking_ref" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Guest Name:</label>
                        <input type="text" id="cancel_guest_name" class="form-control" readonly style="background: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label for="cancellation_reason"><i class="fas fa-comment"></i> Cancellation Reason (Optional):</label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" placeholder="Reason for cancellation..."></textarea>
                        <small style="color: #666;">This reason will be included in the cancellation email and logs.</small>
                    </div>
                    <div class="form-group" style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <p style="margin: 0; color: #666; font-size: 13px;">
                            <i class="fas fa-info-circle"></i>
                            Cancelling this booking will restore room availability and send a cancellation email to the guest.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCancelBookingModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-times"></i> Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Booking Details Modal -->
    <div id="viewBookingModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Booking Details</h3>
                <button class="close-modal" onclick="closeViewBookingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="details-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div class="detail-item">
                        <label>Booking Reference:</label>
                        <div id="view_booking_ref" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Guest Name:</label>
                        <div id="view_guest_name" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <div id="view_guest_email" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Phone:</label>
                        <div id="view_guest_phone" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Room:</label>
                        <div id="view_room_name" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Individual Room:</label>
                        <div id="view_individual_room" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Check-in:</label>
                        <div id="view_check_in" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Check-out:</label>
                        <div id="view_check_out" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Nights:</label>
                        <div id="view_nights" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Guests:</label>
                        <div id="view_guests" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Total Amount:</label>
                        <div id="view_total" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <div id="view_status" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Payment Status:</label>
                        <div id="view_payment" class="detail-value"></div>
                    </div>
                    <div class="detail-item">
                        <label>Created At:</label>
                        <div id="view_created" class="detail-value"></div>
                    </div>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <label>Special Requests:</label>
                    <div id="view_special_requests" class="detail-value" style="background: #f5f5f5; padding: 8px; border-radius: 6px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewBookingModal()">Close</button>
                <a id="view_full_details_link" href="#" class="btn btn-primary" target="_blank"><i class="fas fa-external-link-alt"></i> Full Details</a>
            </div>
        </div>
    </div>
    
    <!-- Upgrade Room Type Modal -->
    <div id="upgradeRoomModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-arrow-up"></i> Upgrade Room Type</h3>
                <button class="close-modal" onclick="closeUpgradeRoomModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Booking Reference:</label>
                    <input type="text" id="upgrade_booking_ref" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Guest Name:</label>
                    <input type="text" id="upgrade_guest_name" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-bed"></i> Current Room Type:</label>
                    <input type="text" id="upgrade_current_room" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Dates:</label>
                    <input type="text" id="upgrade_dates" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Current Total:</label>
                    <input type="text" id="upgrade_current_total" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label for="upgrade_new_room"><i class="fas fa-arrow-up"></i> Select New Room Type:</label>
                    <select id="upgrade_new_room" class="form-control" required>
                        <option value="">-- Select Room Type --</option>
                    </select>
                    <small style="color: #666;">Choose a higher-tier room type to upgrade the booking</small>
                </div>
                <div id="upgrade_price_preview" style="background: #e7f3ff; padding: 12px; border-radius: 6px; margin: 16px 0; display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: bold; color: #666;">New Total:</span>
                        <span id="upgrade_new_total" style="color: #333; font-weight: bold;">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold; color: #666;">Price Difference:</span>
                        <span id="upgrade_price_diff" style="color: #666;">-</span>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="upgrade_send_email" value="1" checked>
                        <span><i class="fas fa-envelope"></i> Send upgrade confirmation email to guest</span>
                    </label>
                </div>
                <div class="form-group" style="background: #fff8e1; padding: 12px; border-radius: 8px;">
                    <p style="margin: 0; color: #8B7355; font-size: 13px;">
                        <i class="fas fa-info-circle"></i>
                        Upgrading will recalculate the booking total based on the new room type price.
                        If the price increases, the guest will need to pay the difference upon check-in.
                    </p>
                </div>
                <input type="hidden" id="upgrade_booking_id">
                <input type="hidden" id="upgrade_current_room_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpgradeRoomModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitUpgradeRoom()"><i class="fas fa-arrow-up"></i> Upgrade Room</button>
            </div>
        </div>
    </div>
    
    <script>
        function openViewBookingModal(bookingId, bookingReference) {
            const modal = document.getElementById('viewBookingModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            // Set full details link
            const fullDetailsLink = document.getElementById('view_full_details_link');
            fullDetailsLink.href = `booking-details.php?id=${bookingId}`;

            // Show loading state
            document.querySelectorAll('#viewBookingModal .detail-value').forEach(el => {
                el.innerHTML = '<span class="loading">...</span>';
            });

            // Fetch booking details via AJAX
            const formData = new FormData();
            formData.append('action', 'get_booking_details');
            formData.append('booking_id', bookingId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const booking = data.data;
                        // Populate fields
                        document.getElementById('view_booking_ref').textContent = booking.booking_reference || '';
                        document.getElementById('view_guest_name').textContent = booking.guest_name || '';
                        document.getElementById('view_guest_email').textContent = booking.guest_email || '';
                        document.getElementById('view_guest_phone').textContent = booking.guest_phone || '';
                        document.getElementById('view_room_name').textContent = booking.room_name || '';
                        const individualRoom = booking.individual_room_name ?
                            `${booking.individual_room_name} (${booking.individual_room_number})` :
                            (booking.individual_room_number ? `Room ${booking.individual_room_number}` : 'Not assigned');
                        document.getElementById('view_individual_room').textContent = individualRoom;
                        document.getElementById('view_check_in').textContent = booking.check_in_date_formatted || booking.check_in_date;
                        document.getElementById('view_check_out').textContent = booking.check_out_date_formatted || booking.check_out_date;
                        document.getElementById('view_nights').textContent = booking.number_of_nights || '';
                        document.getElementById('view_guests').textContent = booking.number_of_guests || '';
                        document.getElementById('view_total').textContent = booking.total_formatted || booking.total_amount;
                        document.getElementById('view_status').textContent = booking.status_label || booking.status;
                        document.getElementById('view_payment').textContent = booking.payment_status_label || booking.payment_status;
                        document.getElementById('view_created').textContent = booking.created_at_formatted || booking.created_at;
                        document.getElementById('view_special_requests').textContent = booking.special_requests || 'None';
                    } else {
                        Alert.show('Failed to load booking details: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching booking details:', error);
                    Alert.show('Error loading booking details. Please try again.', 'error');
                });
        }

        function closeViewBookingModal() {
            const modal = document.getElementById('viewBookingModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
        }

        // Close modal when clicking outside
        document.getElementById('viewBookingModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeViewBookingModal();
            }
        });

        function openResendEmailModal(bookingId, bookingReference, bookingStatus) {
            const modal = document.getElementById('resendEmailModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            document.getElementById('modal_booking_id').value = bookingId;
            document.getElementById('modal_booking_reference').value = bookingReference;
            
            // Set default email type based on booking status
            const emailTypeSelect = document.getElementById('email_type');
            emailTypeSelect.value = '';
            
            // Show/hide appropriate options based on status
            const options = emailTypeSelect.querySelectorAll('option');
            options.forEach(option => {
                option.style.display = '';
            });
            
            // Disable options that don't make sense for current status
            switch(bookingStatus) {
                case 'pending':
                    emailTypeSelect.value = 'booking_received';
                    break;
                case 'tentative':
                    emailTypeSelect.value = 'tentative_confirmed';
                    break;
                case 'confirmed':
                    emailTypeSelect.value = 'booking_confirmed';
                    break;
                case 'cancelled':
                    emailTypeSelect.value = 'booking_cancelled';
                    break;
            }
        }
        
        function closeResendEmailModal() {
            const modal = document.getElementById('resendEmailModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
            document.getElementById('resendEmailForm').reset();
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('resendEmailModal');
            if (event.target === modal) {
                closeResendEmailModal();
            }
            const modal2 = document.getElementById('makeTentativeModal');
            if (event.target === modal2) {
                closeMakeTentativeModal();
            }
            const modal3 = document.getElementById('checkInModal');
            if (event.target === modal3) {
                closeCheckInModal();
            }
            const modal4 = document.getElementById('cancelBookingModal');
            if (event.target === modal4) {
                closeCancelBookingModal();
            }
        });
        
        // Quick Room Assignment Modal Functions
        let selectedRoomId = null;
        
        function openQuickRoomAssignModal(bookingId, bookingReference, checkIn, checkOut, roomId) {
            const modal = document.getElementById('quickRoomAssignModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('modal--active');
            }
            document.getElementById('quick_assign_booking_id').value = bookingId;
            document.getElementById('quick_assign_booking_ref').value = bookingReference;
            document.getElementById('quick_assign_room_id').value = roomId;
            
            const checkInDate = new Date(checkIn);
            const checkOutDate = new Date(checkOut);
            document.getElementById('quick_assign_dates').value =
                checkInDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
                ' - ' +
                checkOutDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            // Load available rooms
            loadAvailableRooms(roomId, checkIn, checkOut, bookingId);
        }
        
        function closeQuickRoomAssignModal() {
            const modal = document.getElementById('quickRoomAssignModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('modal--active');
            }
            document.getElementById('quick_assign_room_list').innerHTML = '';
            selectedRoomId = null;
        }
        
        function loadAvailableRooms(roomId, checkIn, checkOut, bookingId) {
            const roomList = document.getElementById('quick_assign_room_list');
            roomList.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Loading available rooms...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_available_rooms');
            formData.append('room_type_id', roomId);
            formData.append('check_in', checkIn);
            formData.append('check_out', checkOut);
            formData.append('exclude_booking_id', bookingId);

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
                        roomList.innerHTML = '';
                        data.data.forEach(room => {
                            const roomCard = document.createElement('div');
                            roomCard.className = 'room-assign-card';
                            roomCard.dataset.available = room.available ? 'true' : 'false';
                            roomCard.style.cssText = `
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding: 12px;
                                margin-bottom: 8px;
                                border: 2px solid ${room.available ? '#28a745' : '#dc3545'};
                                border-radius: 8px;
                                cursor: ${room.available ? 'pointer' : 'not-allowed'};
                                background: ${room.available ? '#fff' : '#f8f8f8'};
                                transition: all 0.2s;
                            `;
                            
                            const roomName = room.room_name ||
                                (room.room_type_name ? `${room.room_type_name} ${room.room_number}` : `Room ${room.room_number}`);
                            
                            roomCard.innerHTML = `
                                <div>
                                    <div style="font-weight: 600; color: var(--navy);">
                                        <i class="fas fa-door-open" style="color: var(--gold);"></i>
                                        ${roomName}
                                    </div>
                                    ${room.floor ? `<small style="color: #666;"><i class="fas fa-layer-group"></i> Floor: ${room.floor}</small>` : ''}
                                </div>
                                <div>
                                    ${room.available
                                        ? `<span class="badge" style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 11px;">Available</span>`
                                        : `<span class="badge" style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 12px; font-size: 11px;">Unavailable</span>`
                                    }
                                </div>
                            `;
                            
                            if (room.available) {
                                roomCard.onclick = () => selectRoomForAssignment(room.id, roomCard);
                            }
                            
                            roomList.appendChild(roomCard);
                        });
                    } else {
                        roomList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> No available rooms found for these dates.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading rooms:', error);
                    roomList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-circle"></i> Error loading available rooms.</div>';
                });
        }
        
        function selectRoomForAssignment(roomId, cardElement) {
            selectedRoomId = roomId;
            
            // Remove previous selection
            document.querySelectorAll('.room-assign-card').forEach(card => {
                card.style.background = '#fff';
                card.style.borderColor = card.dataset.available === 'true' ? '#28a745' : '#dc3545';
            });
            
            // Highlight selected card
            cardElement.style.background = '#fff8e1';
            cardElement.style.borderColor = 'var(--gold)';
        }
        
        function submitQuickRoomAssign() {
            if (!selectedRoomId) {
                Alert.show('Please select a room to assign.', 'error');
                return;
            }
            
            const bookingId = document.getElementById('quick_assign_booking_id').value;

            const submitBtn = document.querySelector('#quickRoomAssignModal button[onclick="submitQuickRoomAssign()"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Assigning room...');

            const formData = new FormData();
            formData.append('action', 'assign_individual_room');
            formData.append('booking_id', bookingId);
            formData.append('individual_room_id', selectedRoomId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                if (data.success) {
                    Alert.show('Room assigned successfully!', 'success');
                    closeQuickRoomAssignModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    hideLoadingOverlay();
                    if (submitBtn) setButtonLoading(submitBtn, false);
                    Alert.show(data.message || 'Failed to assign room.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error assigning room.', 'error');
            });
        }
        
        // Make Tentative Modal Functions
        function openMakeTentativeModal(bookingId, bookingReference, actionType) {
            const modal = document.getElementById('makeTentativeModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            document.getElementById('make_tentative_booking_id').value = bookingId;
            document.getElementById('make_tentative_ref').value = bookingReference;
            document.getElementById('make_tentative_action').value = actionType;
            // Set modal title based on action
            const title = document.querySelector('#makeTentativeModal h3');
            if (actionType === 'make_tentative') {
                title.innerHTML = '<i class="fas fa-clock"></i> Make Tentative';
                document.querySelector('#makeTentativeForm button[type="submit"]').innerHTML = '<i class="fas fa-check"></i> Make Tentative';
            } else if (actionType === 'convert_to_tentative') {
                title.innerHTML = '<i class="fas fa-clock"></i> Convert to Tentative';
                document.querySelector('#makeTentativeForm button[type="submit"]').innerHTML = '<i class="fas fa-check"></i> Convert to Tentative';
            }
            // Clear note
            document.getElementById('tentative_note').value = '';
        }
        
        function closeMakeTentativeModal() {
            const modal = document.getElementById('makeTentativeModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
            document.getElementById('makeTentativeForm').reset();
        }
        
        // Check In Modal Functions
        function openCheckInModal(bookingId, bookingReference, guestName, checkInDate, paymentStatus, roomAssigned, bookingStatus) {
            const modal = document.getElementById('checkInModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            document.getElementById('checkin_booking_id').value = bookingId;
            document.getElementById('checkin_booking_ref').value = bookingReference;
            document.getElementById('checkin_guest_name').value = guestName;
            document.getElementById('checkin_date').value = checkInDate;
            
            // Update prerequisites UI
            const paymentOk = paymentStatus === 'paid' || paymentStatus === 'completed';
            const roomOk = roomAssigned === true || roomAssigned === '1';
            const statusOk = bookingStatus === 'confirmed';
            
            document.getElementById('prereq_payment').innerHTML = paymentOk
                ? '<i class="fas fa-check-circle" style="color: #28a745;"></i> Payment is PAID'
                : '<i class="fas fa-times-circle" style="color: #dc3545;"></i> Payment must be marked as PAID';
            document.getElementById('prereq_room').innerHTML = roomOk
                ? '<i class="fas fa-check-circle" style="color: #28a745;"></i> Room is assigned'
                : '<i class="fas fa-times-circle" style="color: #dc3545;"></i> A room must be assigned';
            document.getElementById('prereq_status').innerHTML = statusOk
                ? '<i class="fas fa-check-circle" style="color: #28a745;"></i> Booking is CONFIRMED'
                : '<i class="fas fa-times-circle" style="color: #dc3545;"></i> Booking must be CONFIRMED';
            
            const canCheckIn = paymentOk && roomOk && statusOk;
            const submitBtn = document.querySelector('#checkInForm button[type="submit"]');
            submitBtn.disabled = !canCheckIn;
            if (!canCheckIn) {
                submitBtn.innerHTML = '<i class="fas fa-ban"></i> Cannot Check In';
                submitBtn.style.background = '#6c757d';
                let errorMsg = 'Cannot check in because: ';
                const issues = [];
                if (!paymentOk) issues.push('payment not paid');
                if (!roomOk) issues.push('room not assigned');
                if (!statusOk) issues.push('booking not confirmed');
                errorMsg += issues.join(', ');
                document.getElementById('checkin_error_message').textContent = errorMsg;
                document.getElementById('checkin_error_message').style.display = 'block';
            } else {
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
                submitBtn.style.background = '';
                document.getElementById('checkin_error_message').style.display = 'none';
            }
        }
        
        function closeCheckInModal() {
            const modal = document.getElementById('checkInModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
            document.getElementById('checkInForm').reset();
        }
        
        // Cancel Booking Modal Functions
        function openCancelBookingModal(bookingId, bookingReference, guestName) {
            const modal = document.getElementById('cancelBookingModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            document.getElementById('cancel_booking_id').value = bookingId;
            document.getElementById('cancel_booking_ref').value = bookingReference;
            document.getElementById('cancel_guest_name').value = guestName;
            document.getElementById('cancellation_reason').value = '';
        }
        
        function closeCancelBookingModal() {
            const modal = document.getElementById('cancelBookingModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
            document.getElementById('cancelBookingForm').reset();
        }
        
        // Form submission for new modals
        document.getElementById('makeTentativeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            // Add note if provided
            const note = document.getElementById('tentative_note').value;
            if (note) {
                formData.append('note', note);
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Making booking tentative...');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (submitBtn) setButtonLoading(submitBtn, false);
                    Alert.show('Error making booking tentative', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error making booking tentative', 'error');
            });
        });
        
        document.getElementById('checkInForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const note = document.getElementById('checkin_note').value;
            if (note) {
                formData.append('checkin_note', note);
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Checking in guest...');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (submitBtn) setButtonLoading(submitBtn, false);
                    Alert.show('Error checking in guest', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error checking in guest', 'error');
            });
        });
        
        document.getElementById('cancelBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const reason = document.getElementById('cancellation_reason').value;
            if (reason) {
                formData.append('cancellation_reason', reason);
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Cancelling booking...');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    hideLoadingOverlay();
                    if (submitBtn) setButtonLoading(submitBtn, false);
                    Alert.show('Error cancelling booking', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error cancelling booking', 'error');
            });
        });
        
        // Form submission
        document.getElementById('resendEmailForm').addEventListener('submit', function(e) {
            e.preventDefault();
             
            const formData = new FormData(this);
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Sending email...');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reload page to see success/error message
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error sending email', 'error');
            });
        });
        
        // Upgrade Room Type Modal Functions
        let availableRoomsForUpgrade = [];
        
        function openUpgradeRoomModal(bookingId, bookingRef, currentRoomId, currentRoomName, guestName, checkIn, checkOut, totalAmount, paymentStatus) {
            const modal = document.getElementById('upgradeRoomModal');
            modal.style.display = 'flex';
            modal.classList.add('modal--active');
            document.getElementById('upgrade_booking_id').value = bookingId;
            document.getElementById('upgrade_booking_ref').value = bookingRef;
            document.getElementById('upgrade_guest_name').value = guestName;
            document.getElementById('upgrade_current_room').value = currentRoomName;
            document.getElementById('upgrade_current_room_id').value = currentRoomId;
            document.getElementById('upgrade_current_total').value = 'K ' + parseFloat(totalAmount).toLocaleString();
            
            const checkInDate = new Date(checkIn);
            const checkOutDate = new Date(checkOut);
            document.getElementById('upgrade_dates').value =
                checkInDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
                ' - ' +
                checkOutDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            // Load available room types for upgrade
            loadRoomTypesForUpgrade(currentRoomId, checkIn, checkOut);
        }
        
        function closeUpgradeRoomModal() {
            const modal = document.getElementById('upgradeRoomModal');
            modal.style.display = 'none';
            modal.classList.remove('modal--active');
            document.getElementById('upgrade_new_room').innerHTML = '<option value="">-- Select Room Type --</option>';
            document.getElementById('upgrade_price_preview').style.display = 'none';
            availableRoomsForUpgrade = [];
        }
        
        function loadRoomTypesForUpgrade(currentRoomId, checkIn, checkOut) {
            const roomSelect = document.getElementById('upgrade_new_room');
            roomSelect.innerHTML = '<option value="">Loading room types...</option>';
            
            // Fetch all active room types
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({
                    'action': 'get_all_room_types_for_upgrade',
                    'current_room_id': currentRoomId,
                    'check_in': checkIn,
                    'check_out': checkOut
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    availableRoomsForUpgrade = data.data;
                    roomSelect.innerHTML = '<option value="">-- Select Room Type --</option>';
                    data.data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name + ' (K ' + parseFloat(room.price_per_night).toLocaleString() + '/night)';
                        option.dataset.price = room.price_per_night;
                        option.dataset.name = room.name;
                        roomSelect.appendChild(option);
                    });
                } else {
                    roomSelect.innerHTML = '<option value="">No upgrade options available</option>';
                }
            })
            .catch(error => {
                console.error('Error loading room types:', error);
                roomSelect.innerHTML = '<option value="">Error loading room types</option>';
            });
            
            // Add change event listener for price preview
            roomSelect.onchange = function() {
                updateUpgradePricePreview();
            };
        }
        
        function updateUpgradePricePreview() {
            const roomSelect = document.getElementById('upgrade_new_room');
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            const previewDiv = document.getElementById('upgrade_price_preview');
            
            if (!selectedOption || !selectedOption.value) {
                previewDiv.style.display = 'none';
                return;
            }
            
            const currentTotal = parseFloat(document.getElementById('upgrade_current_total').value.replace(/[^0-9.-]+/g, ''));
            const newPricePerNight = parseFloat(selectedOption.dataset.price);
            
            // Calculate nights from dates
            const datesText = document.getElementById('upgrade_dates').value;
            const dateParts = datesText.split(' - ');
            if (dateParts.length === 2) {
                const checkIn = new Date(dateParts[0]);
                const checkOut = new Date(dateParts[1]);
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const newTotal = newPricePerNight * nights;
                const priceDiff = newTotal - currentTotal;
                
                document.getElementById('upgrade_new_total').textContent = 'K ' + newTotal.toLocaleString();
                const diffText = priceDiff >= 0 ? '+K ' + priceDiff.toLocaleString() : '-K ' + Math.abs(priceDiff).toLocaleString();
                document.getElementById('upgrade_price_diff').textContent = diffText;
                document.getElementById('upgrade_price_diff').style.color = priceDiff >= 0 ? '#dc3545' : '#28a745';
                previewDiv.style.display = 'block';
            }
        }
        
        function submitUpgradeRoom() {
            const bookingId = document.getElementById('upgrade_booking_id').value;
            const newRoomId = document.getElementById('upgrade_new_room').value;
            const sendEmail = document.getElementById('upgrade_send_email').checked ? '1' : '0';
            
            if (!newRoomId) {
                Alert.show('Please select a new room type.', 'error');
                return;
            }
            
            const submitBtn = document.querySelector('#upgradeRoomModal button[onclick="submitUpgradeRoom()"]');
            if (submitBtn) setButtonLoading(submitBtn, true);
            showLoadingOverlay('Upgrading room type...');
            
            const formData = new FormData();
            formData.append('action', 'upgrade_room_type');
            formData.append('booking_id', bookingId);
            formData.append('new_room_id', newRoomId);
            formData.append('send_email', sendEmail);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Alert.show(data.message || 'Room type upgraded successfully!', 'success');
                    closeUpgradeRoomModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    hideLoadingOverlay();
                    if (submitBtn) setButtonLoading(submitBtn, false);
                    Alert.show(data.message || 'Failed to upgrade room type.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingOverlay();
                if (submitBtn) setButtonLoading(submitBtn, false);
                Alert.show('Error upgrading room type.', 'error');
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('upgradeRoomModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeUpgradeRoomModal();
            }
        });
        
        // Delegated event listeners for action buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside for quickRoomAssignModal
            const quickRoomAssignModal = document.getElementById('quickRoomAssignModal');
            if (quickRoomAssignModal) {
                quickRoomAssignModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closeQuickRoomAssignModal();
                    }
                });
            }
            
            document.addEventListener('click', function(event) {
                const button = event.target.closest('[data-action]');
                if (!button) return;

                try {
                    const action = button.dataset.action;
                    const bookingId = button.dataset.bookingId;
                    const bookingRef = button.dataset.bookingRef || '';

                    if (!bookingId) {
                        console.warn('[Action Button] Missing booking ID', button);
                        return;
                    }

                    if (action === 'assign-room') {
                        // Prevent default behavior and stop propagation
                        event.preventDefault();
                        event.stopPropagation();
                        
                        const checkIn = button.dataset.checkIn;
                        const checkOut = button.dataset.checkOut;
                        const roomId = button.dataset.roomId;
                        
                        // bookingRef is optional for assign-room action
                        const effectiveBookingRef = bookingRef || button.dataset.bookingRef || 'N/A';
                        
                        if (!checkIn || !checkOut || !roomId) {
                            console.warn('[Assign Room] Missing required data attributes', { checkIn, checkOut, roomId });
                            return;
                        }
                        
                        openQuickRoomAssignModal(bookingId, effectiveBookingRef, checkIn, checkOut, roomId);
                    } else if (action === 'make-tentative') {
                        const tentativeType = button.dataset.tentativeType; // 'make_tentative' or 'convert_to_tentative'
                        if (typeof openMakeTentativeModal === 'function') {
                            openMakeTentativeModal(bookingId, bookingRef, tentativeType);
                        }
                    } else if (action === 'check-in') {
                        const guestName = button.dataset.guestName;
                        const checkInDate = button.dataset.checkInDate;
                        const paymentStatus = button.dataset.paymentStatus;
                        const roomAssigned = button.dataset.roomAssigned === 'true' || button.dataset.roomAssigned === '1';
                        const bookingStatus = button.dataset.bookingStatus;
                        if (typeof openCheckInModal === 'function') {
                            openCheckInModal(bookingId, bookingRef, guestName, checkInDate, paymentStatus, roomAssigned, bookingStatus);
                        }
                    } else if (action === 'upgrade-room') {
                        const currentRoomId = button.dataset.currentRoomId;
                        const currentRoomName = button.dataset.currentRoomName;
                        const guestName = button.dataset.guestName;
                        const checkIn = button.dataset.checkIn;
                        const checkOut = button.dataset.checkOut;
                        const totalAmount = button.dataset.totalAmount;
                        const paymentStatus = button.dataset.paymentStatus;
                        
                        if (typeof openUpgradeRoomModal === 'function') {
                            openUpgradeRoomModal(bookingId, bookingRef, currentRoomId, currentRoomName, guestName, checkIn, checkOut, totalAmount, paymentStatus);
                        }
                    }
                } catch (error) {
                    console.error('Error handling action button click:', error, button);
                    if (typeof Alert !== 'undefined' && Alert.show) {
                        Alert.show('An error occurred while opening the modal. Please try again.', 'error');
                    }
                }
            });
        });

    </script>
    <script src="js/admin-components.js"></script>

    <?php require_once 'includes/admin-footer.php'; ?>
