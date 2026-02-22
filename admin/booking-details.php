<?php
/**
 * Booking Details Page
 * Comprehensive booking management with folio/charges and invoice generation
 */
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

// Get folio charges for this booking
$folio_charges = getBookingCharges($booking_id, true); // Include voided for display
$folio_summary = getBookingFolioSummary($booking_id);

// Get menu items for quick-add (grouped by category)
$food_menu_items = getMenuItemsForFolio('food');
$drink_menu_items = getMenuItemsForFolio('drink');

// Handle charge actions (POST-only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['charge_action'])) {
    $action = $_POST['charge_action'];
    
    try {
        switch ($action) {
            case 'add_charge':
                $description = trim($_POST['description'] ?? '');
                $charge_type = $_POST['charge_type'] ?? 'custom';
                $quantity = (float)($_POST['quantity'] ?? 1);
                $unit_price = (float)($_POST['unit_price'] ?? 0);
                
                if (empty($description)) {
                    $_SESSION['error_message'] = 'Please provide a description for the charge.';
                } elseif ($unit_price < 0) {
                    $_SESSION['error_message'] = 'Unit price cannot be negative.';
                } else {
                    $result = addBookingCharge($booking_id, $charge_type, $description, $quantity, $unit_price, null, $user['id']);
                    if ($result['success']) {
                        $_SESSION['success_message'] = "Charge added successfully. Line total: {$currency_symbol}" . number_format($result['line_total'], 2);
                    } else {
                        $_SESSION['error_message'] = 'Failed to add charge: ' . $result['message'];
                    }
                }
                break;
                
            case 'add_menu_item':
                $menu_type = $_POST['menu_type'] ?? 'food';
                $menu_item_id = (int)($_POST['menu_item_id'] ?? 0);
                $quantity = (float)($_POST['quantity'] ?? 1);
                
                if ($menu_item_id <= 0) {
                    $_SESSION['error_message'] = 'Please select a menu item.';
                } elseif ($quantity <= 0) {
                    $_SESSION['error_message'] = 'Quantity must be greater than 0.';
                } else {
                    $result = addBookingChargeFromMenu($booking_id, $menu_type, $menu_item_id, $quantity, $user['id']);
                    if ($result['success']) {
                        $_SESSION['success_message'] = "Menu item added to folio. Line total: {$currency_symbol}" . number_format($result['line_total'], 2);
                    } else {
                        $_SESSION['error_message'] = 'Failed to add menu item: ' . $result['message'];
                    }
                }
                break;
                
            case 'void_charge':
                $charge_id = (int)($_POST['charge_id'] ?? 0);
                $void_reason = trim($_POST['void_reason'] ?? '');
                
                if ($charge_id <= 0) {
                    $_SESSION['error_message'] = 'Invalid charge ID.';
                } elseif (empty($void_reason)) {
                    $_SESSION['error_message'] = 'Please provide a reason for voiding the charge.';
                } else {
                    $result = voidBookingCharge($charge_id, $void_reason, $user['id']);
                    if ($result['success']) {
                        $_SESSION['success_message'] = 'Charge voided successfully.';
                    } else {
                        $_SESSION['error_message'] = 'Failed to void charge: ' . $result['message'];
                    }
                }
                break;
        }
        
        // Refresh data after changes
        $folio_charges = getBookingCharges($booking_id, true);
        $folio_summary = getBookingFolioSummary($booking_id);
        
        // Redirect to prevent form resubmission
        header('Location: booking-details.php?id=' . $booking_id . '#folio');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

// Handle invoice generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_action'])) {
    $invoice_action = $_POST['invoice_action'];
    
    try {
        switch ($invoice_action) {
            case 'generate_invoice':
                // Generate invoice without sending
                $invoice_result = generateInvoicePDF($booking_id);
                if ($invoice_result) {
                    // Update booking with invoice details if not exists
                    $updateStmt = $pdo->prepare("
                        UPDATE bookings 
                        SET final_invoice_path = ?, 
                            final_invoice_number = COALESCE(final_invoice_number, ?),
                            updated_at = NOW()
                        WHERE id = ? AND final_invoice_path IS NULL
                    ");
                    $updateStmt->execute([$invoice_result['relative_path'], $invoice_result['invoice_number'], $booking_id]);
                    
                    $_SESSION['success_message'] = "Invoice generated successfully: {$invoice_result['invoice_number']}";
                } else {
                    $_SESSION['error_message'] = 'Failed to generate invoice.';
                }
                break;
                
            case 'send_invoice':
                // Generate and send invoice
                require_once '../config/invoice.php';
                
                // Get invoice recipients
                $cc_recipients = [];
                $invoice_recipients = getEmailSetting('invoice_recipients', '');
                $smtp_username = getEmailSetting('smtp_username', '');
                
                if (!empty($invoice_recipients)) {
                    $cc_recipients = array_filter(array_map('trim', explode(',', $invoice_recipients)));
                }
                if (!empty($smtp_username) && !in_array($smtp_username, $cc_recipients)) {
                    $cc_recipients[] = $smtp_username;
                }
                
                $result = sendPaymentInvoiceEmailWithCC($booking_id, $cc_recipients);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = "Invoice sent successfully to guest." . 
                        (!empty($result['cc_recipients']) ? " CC: " . implode(', ', $result['cc_recipients']) : '');
                } else {
                    $_SESSION['error_message'] = 'Failed to send invoice: ' . $result['message'];
                }
                break;
                
            case 'regenerate_invoice':
                // Force regenerate invoice
                $invoice_result = generateInvoicePDF($booking_id);
                if ($invoice_result) {
                    // Update booking with new invoice
                    $updateStmt = $pdo->prepare("
                        UPDATE bookings 
                        SET final_invoice_path = ?, 
                            final_invoice_number = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$invoice_result['relative_path'], $invoice_result['invoice_number'], $booking_id]);
                    
                    $_SESSION['success_message'] = "Invoice regenerated: {$invoice_result['invoice_number']}";
                } else {
                    $_SESSION['error_message'] = 'Failed to regenerate invoice.';
                }
                break;
        }
        
        header('Location: booking-details.php?id=' . $booking_id . '#invoices');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Invoice error: ' . $e->getMessage();
    }
}

// Handle status changes (POST-only)
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
                    
                    // Send conversion email
                    require_once '../config/email.php';
                    $email_result = sendTentativeBookingConvertedEmail($booking_data);
                    
                    $_SESSION['success_message'] = 'Tentative booking converted to confirmed!' . 
                        ($email_result['success'] ? ' Confirmation email sent.' : ' (Email failed: ' . $email_result['message'] . ')');
                }
                break;
            
            case 'confirm':
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$booking_id]);
                
                // Decrement room availability and get booking details
                $room_stmt = $pdo->prepare("SELECT room_id, booking_reference, individual_room_id FROM bookings WHERE id = ?");
                $room_stmt->execute([$booking_id]);
                $booking_room = $room_stmt->fetch(PDO::FETCH_ASSOC);
                if ($booking_room) {
                    $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available - 1 WHERE id = ? AND rooms_available > 0")
                        ->execute([$booking_room['room_id']]);
                    
                    // Auto-assign individual room if not already assigned
                    $auto_assign_msg = '';
                    if (empty($booking_room['individual_room_id'])) {
                        $autoAssignResult = autoAssignIndividualRoom($booking_id);
                        if ($autoAssignResult['success']) {
                            $auto_assign_msg = ' Room ' . htmlspecialchars($autoAssignResult['assigned_room_number']) . ' auto-assigned.';
                        }
                    }
                    
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
                    $_SESSION['success_message'] = 'Booking confirmed.' . ($email_result['success'] ? ' Confirmation email sent.' : '') . $auto_assign_msg;
                }
                break;
            
            case 'checkin':
                $check_stmt = $pdo->prepare("SELECT b.status, b.payment_status, b.individual_room_id, b.room_id, b.booking_reference, b.check_in_date, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $check_stmt->execute([$booking_id]);
                $check_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$check_row) {
                    $_SESSION['error_message'] = 'Booking not found.';
                } else {
                    $validation = validateCheckIn($check_row);
                    if (!$validation['allowed']) {
                        $_SESSION['error_message'] = getBookingActionErrorMessage('check_in', $validation['reason']);
                    } else {
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'checked-in', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        
                        logBookingCheckIn($booking_id, $check_row['booking_reference'], 'admin', $user['id'], $user['full_name']);
                        
                        // Update individual room status
                        if (!empty($check_row['individual_room_id'])) {
                            $old_room_status = $check_row['room_status'];
                            $pdo->prepare("UPDATE individual_rooms SET status = 'occupied' WHERE id = ?")->execute([$check_row['individual_room_id']]);
                            
                            $logStmt = $pdo->prepare("
                                INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                                VALUES (?, ?, 'occupied', 'Guest checked in', ?)
                            ");
                            $logStmt->execute([$check_row['individual_room_id'], $old_room_status ?: 'available', $user['id']]);
                        }
                        
                        $_SESSION['success_message'] = 'Guest checked in successfully.' . (!empty($check_row['individual_room_id']) ? ' Room marked as occupied.' : '');
                    }
                }
                break;
            
            case 'checkout':
                $checkout_stmt = $pdo->prepare("SELECT b.room_id, b.individual_room_id, b.booking_reference, b.check_out_date, b.status, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $checkout_stmt->execute([$booking_id]);
                $checkout_row = $checkout_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$checkout_row) {
                    $_SESSION['error_message'] = 'Booking not found.';
                } else {
                    $validation = validateCheckOut($checkout_row);
                    if (!$validation['allowed']) {
                        $_SESSION['error_message'] = getBookingActionErrorMessage('check_out', $validation['reason']);
                    } else {
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'checked-out', checkout_completed_at = NOW(), updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        
                        logBookingCheckOut($booking_id, $checkout_row['booking_reference'], 'admin', $user['id'], $user['full_name']);
                        
                        // Restore room availability
                        $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms")
                            ->execute([$checkout_row['room_id']]);
                        
                        // Update individual room status
                        if (!empty($checkout_row['individual_room_id'])) {
                            $old_room_status = $checkout_row['room_status'];
                            $pdo->prepare("UPDATE individual_rooms SET status = 'cleaning' WHERE id = ?")->execute([$checkout_row['individual_room_id']]);
                            
                            $logStmt = $pdo->prepare("
                                INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
                                VALUES (?, ?, 'cleaning', 'Guest checked out', ?)
                            ");
                            $logStmt->execute([$checkout_row['individual_room_id'], $old_room_status ?: 'occupied', $user['id']]);
                        }
                        
                        // Generate and send final invoice
                        require_once '../config/invoice.php';
                        $invoice_result = generateAndSendFinalInvoice($booking_id, $user['id']);
                        
                        $checkout_message = 'Guest checked out successfully. Room availability restored.' . 
                            (!empty($checkout_row['individual_room_id']) ? ' Individual room marked for cleaning.' : '');
                        
                        if ($invoice_result['success']) {
                            if (!$invoice_result['idempotent']) {
                                $checkout_message .= ' Final invoice generated.';
                            }
                            if (!$invoice_result['email_sent']) {
                                $checkout_message .= ' Note: Final invoice email could not be sent.';
                            }
                        } else {
                            $checkout_message .= ' Warning: Failed to generate final invoice.';
                        }
                        
                        $_SESSION['success_message'] = $checkout_message;
                    }
                }
                break;
            
            case 'noshow':
                $check_stmt = $pdo->prepare("SELECT b.status, b.room_id, b.individual_room_id, b.booking_reference, ir.status as room_status FROM bookings b LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id WHERE b.id = ?");
                $check_stmt->execute([$booking_id]);
                $noshow_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($noshow_row && in_array($noshow_row['status'], ['confirmed', 'pending'])) {
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'no-show', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    
                    logBookingEvent(
                        $booking_id, $noshow_row['booking_reference'], 'Guest marked as no-show',
                        'status_change', 'Guest did not arrive - marked as no-show',
                        $noshow_row['status'], 'no-show', 'admin', $user['id'], $user['full_name']
                    );
                    
                    if ($noshow_row['status'] === 'confirmed') {
                        $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms")
                            ->execute([$noshow_row['room_id']]);
                    }
                    
                    if (!empty($noshow_row['individual_room_id'])) {
                        $pdo->prepare("UPDATE individual_rooms SET status = 'available' WHERE id = ?")->execute([$noshow_row['individual_room_id']]);
                    }
                    
                    $_SESSION['success_message'] = 'Booking marked as no-show.';
                } else {
                    $_SESSION['error_message'] = 'Cannot mark as no-show from current status.';
                }
                break;
            
            case 'cancel':
                $booking_stmt = $pdo->prepare("
                    SELECT b.*, r.name as room_name, ir.status as individual_room_status
                    FROM bookings b
                    LEFT JOIN rooms r ON b.room_id = r.id
                    LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
                    WHERE b.id = ?
                ");
                $booking_stmt->execute([$booking_id]);
                $booking_to_cancel = $booking_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking_to_cancel) {
                    $_SESSION['error_message'] = 'Booking not found.';
                } else {
                    $validation = validateBookingCancellation($booking_to_cancel);
                    if (!$validation['allowed']) {
                        $_SESSION['error_message'] = getBookingActionErrorMessage('cancel', $validation['reason']);
                    } else {
                        $previous_status = $booking_to_cancel['status'];
                        $cancellation_reason = $_POST['cancellation_reason'] ?? 'Cancelled by admin';
                        
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        
                        logBookingStatusChange($booking_id, $booking_to_cancel['booking_reference'], $previous_status, 'cancelled', 'admin', $user['id'], $user['full_name'], $cancellation_reason);
                        
                        if ($previous_status === 'confirmed') {
                            $pdo->prepare("UPDATE rooms SET rooms_available = rooms_available + 1 WHERE id = ? AND rooms_available < total_rooms")
                                ->execute([$booking_to_cancel['room_id']]);
                        }
                        
                        if (!empty($booking_to_cancel['individual_room_id'])) {
                            $pdo->prepare("UPDATE individual_rooms SET status = 'available' WHERE id = ?")->execute([$booking_to_cancel['individual_room_id']]);
                        }
                        
                        require_once '../config/email.php';
                        $email_result = sendBookingCancelledEmail($booking_to_cancel, $cancellation_reason);
                        
                        $_SESSION['success_message'] = 'Booking cancelled.' . 
                            ($email_result['success'] ? ' Cancellation email sent.' : ' (Email failed)');
                    }
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

// Fetch booking details
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

    // Derive room status from booking status if no individual room assigned
    if (empty($booking['individual_room_status'])) {
        $booking_status = $booking['status'];
        $status_mapping = [
            'pending' => 'available',
            'confirmed' => 'available',
            'checked-in' => 'occupied',
            'checked-out' => 'cleaning',
            'cancelled' => 'available',
            'no-show' => 'available'
        ];
        $booking['derived_room_status'] = $status_mapping[$booking_status] ?? 'available';
    } else {
        $booking['derived_room_status'] = $booking['individual_room_status'];
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
    
    // Fetch existing invoices for this booking
    $invoices_stmt = $pdo->prepare("
        SELECT id, invoice_number, invoice_path, invoice_generated, created_at
        FROM payments
        WHERE booking_type = 'room' AND booking_id = ? AND invoice_generated = 1
        ORDER BY created_at DESC
    ");
    $invoices_stmt->execute([$booking_id]);
    $existing_invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

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
            
            logBookingNote($booking_id, $booking['booking_reference'], $note_text, $user['id'], $user['full_name']);
            
            $_SESSION['success_message'] = 'Note added successfully.';
            header("Location: booking-details.php?id=$booking_id");
            exit;
        } catch (PDOException $e) {
            $error_message = 'Failed to add note.';
        }
    }
}

// Handle date adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_dates'])) {
    $new_check_in = trim($_POST['new_check_in'] ?? '');
    $new_check_out = trim($_POST['new_check_out'] ?? '');
    $adjustment_reason = trim($_POST['adjustment_reason'] ?? '');
    
    if (empty($new_check_in) || empty($new_check_out)) {
        $_SESSION['error_message'] = 'Please provide both check-in and check-out dates.';
    } elseif (empty($adjustment_reason)) {
        $_SESSION['error_message'] = 'Please provide a reason for the date adjustment.';
    } else {
        $result = processBookingDateAdjustment(
            $booking_id,
            $new_check_in,
            $new_check_out,
            $adjustment_reason,
            $user['id'],
            $user['full_name']
        );
        
        if ($result['success']) {
            $delta = $result['calculation']['amount_delta'];
            $delta_text = $delta >= 0
                ? "+{$currency_symbol}" . number_format(abs($delta), 2) . " additional amount due"
                : "-{$currency_symbol}" . number_format(abs($delta), 2) . " refund/credit";
            
            $message = "Stay dates adjusted successfully. {$delta_text}";
            
            // Add credit balance notification if applicable
            if (isset($result['credit_balance']) && $result['credit_balance'] > 0) {
                $message .= " Guest has a credit balance of {$currency_symbol}" . number_format($result['credit_balance'], 2) . ".";
            }
            
            $_SESSION['success_message'] = $message;
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    }
    
    header("Location: booking-details.php?id=$booking_id");
    exit;
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_status = $_POST['payment_status'];
    $previous_status = $booking['payment_status'];
    
    try {
        $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
        $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
        
        $totalAmount = (float)$booking['total_amount'];
        $vatAmount = $vatEnabled ? ($totalAmount * ($vatRate / 100)) : 0;
        $totalWithVat = $totalAmount + $vatAmount;
        
        $update_stmt = $pdo->prepare("UPDATE bookings SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$payment_status, $booking_id]);
        
        if ($payment_status === 'paid' && $previous_status !== 'paid') {
            $payment_reference = 'PAY-' . date('Y') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
            
            $insert_payment = $pdo->prepare("
                INSERT INTO payments (
                    payment_reference, booking_type, booking_id, booking_reference,
                    payment_date, payment_amount, vat_rate, vat_amount, total_amount,
                    payment_method, payment_type, payment_status, invoice_generated,
                    status, recorded_by
                ) VALUES (?, 'room', ?, ?, CURDATE(), ?, ?, ?, ?, 'cash', 'full_payment', 'completed', 1, 'completed', ?)
            ");
            $insert_payment->execute([
                $payment_reference, $booking_id, $booking['booking_reference'],
                $totalAmount, $vatRate, $vatAmount, $totalWithVat, $user['id']
            ]);
            
            logBookingPayment($booking_id, $booking['booking_reference'], $totalWithVat, 'full_payment', 'cash', 'completed', $user['id'], $payment_reference);
            
            $update_amounts = $pdo->prepare("
                UPDATE bookings SET amount_paid = ?, amount_due = 0, vat_rate = ?, vat_amount = ?,
                    total_with_vat = ?, last_payment_date = CURDATE() WHERE id = ?
            ");
            $update_amounts->execute([$totalWithVat, $vatRate, $vatAmount, $totalWithVat, $booking_id]);
            
            require_once '../config/invoice.php';
            $invoice_result = sendPaymentInvoiceEmail($booking_id);
            
            $_SESSION['success_message'] = 'Payment status updated. Payment recorded.' . 
                ($invoice_result['success'] ? ' Invoice sent!' : ' (Invoice email failed)');
        } else {
            $_SESSION['success_message'] = 'Payment status updated.';
        }
        
        header("Location: booking-details.php?id=$booking_id");
        exit;
    } catch (PDOException $e) {
        $error_message = 'Failed to update payment status: ' . $e->getMessage();
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
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1200px) {
            .details-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .details-grid { grid-template-columns: 1fr; }
        }
        
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
        .info-card-header .icon.folio { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .info-card-header .icon.invoice { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        
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
        
        .folio-card, .invoices-card {
            grid-column: span 3;
        }
        
        @media (max-width: 1200px) {
            .folio-card, .invoices-card { grid-column: span 2; }
        }
        
        @media (max-width: 768px) {
            .folio-card, .invoices-card { grid-column: span 1; }
        }
        
        .folio-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .folio-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .folio-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        
        .folio-btn.primary {
            background: var(--gold, #8B7355);
            color: white;
        }
        
        .folio-btn.primary:hover {
            background: #6f5b43;
        }
        
        .folio-btn.secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .folio-btn.secondary:hover {
            background: #e0e0e0;
        }
        
        .folio-btn.success {
            background: #28a745;
            color: white;
        }
        
        .folio-btn.success:hover {
            background: #208637;
        }
        
        .folio-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .folio-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }
        
        .folio-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f8f8f8;
            font-size: 13px;
        }
        
        .folio-table tr:last-child td {
            border-bottom: none;
        }
        
        .folio-table .charge-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .folio-table .charge-type.food { background: #edf7f0; color: #1f7a42; }
        .folio-table .charge-type.drink { background: #fef3ec; color: #a03030; }
        .folio-table .charge-type.service { background: #ecf8fd; color: #1a7a96; }
        .folio-table .charge-type.custom { background: #f5f5f5; color: #666; }
        .folio-table .charge-type.room { background: #fef9ec; color: #92690a; }
        .folio-table .charge-type.minibar { background: #f3e8ff; color: #7c3aed; }
        .folio-table .charge-type.laundry { background: #fff7ed; color: #c2410c; }
        .folio-table .charge-type.room_service { background: #ecfdf5; color: #059669; }
        .folio-table .charge-type.breakfast { background: #fef9ec; color: #92690a; }
        
        .folio-table .voided {
            opacity: 0.5;
            text-decoration: line-through;
        }
        
        .folio-table .void-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #fef2f2;
            color: #a03030;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .folio-summary {
            display: flex;
            justify-content: flex-end;
            gap: 40px;
            padding-top: 16px;
            border-top: 2px solid #f0f0f0;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .folio-summary-item {
            text-align: right;
        }
        
        .folio-summary-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
        }
        
        .folio-summary-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--navy, #1A1A1A);
        }
        
        .folio-summary-value.total {
            color: var(--gold, #8B7355);
            font-size: 24px;
        }
        
        .folio-summary-value.balance {
            color: #a03030;
        }
        
        .folio-summary-value.paid {
            color: #1f7a42;
        }
        
        .void-charge-btn {
            padding: 4px 8px;
            background: #fef2f2;
            color: #a03030;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .void-charge-btn:hover {
            background: #a03030;
            color: white;
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-content.wide {
            max-width: 800px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--navy, #1A1A1A);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            padding: 0;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: var(--navy, #1A1A1A);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            margin-bottom: 6px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Jost', sans-serif;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold, #8B7355);
            box-shadow: 0 0 0 3px rgba(139, 115, 85, 0.1);
        }
        
        .menu-category-section {
            margin-bottom: 20px;
        }
        
        .menu-category-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--gold, #8B7355);
        }
        
        .menu-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }
        
        .menu-item-card {
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .menu-item-card:hover {
            border-color: var(--gold, #8B7355);
            background: #fef9ec;
        }
        
        .menu-item-card.selected {
            border-color: var(--gold, #8B7355);
            background: #fef9ec;
        }
        
        .menu-item-name {
            font-weight: 600;
            font-size: 13px;
            color: var(--navy, #1A1A1A);
            margin-bottom: 4px;
        }
        
        .menu-item-price {
            font-weight: 700;
            color: var(--gold, #8B7355);
            font-size: 14px;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        
        .modal-actions .btn-primary {
            background: var(--gold, #8B7355);
            color: white;
        }
        
        .modal-actions .btn-primary:hover {
            background: #6f5b43;
        }
        
        .modal-actions .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .modal-actions .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-actions .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .modal-actions .btn-danger {
            background: #a03030;
            color: white;
        }
        
        .modal-actions .btn-danger:hover {
            background: #8b0000;
        }
        
        /* Timeline Card */
        .timeline-card {
            grid-column: 1 / 2;
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
        .timeline-icon.date_adjustment { background: #fef2f2; color: #f5576c; }
        
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
        
        /* Notes Card */
        .notes-card {
            grid-column: 2 / 3;
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
        
        /* Invoices Section */
        .invoice-list {
            margin-top: 16px;
        }
        
        .invoice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: #f8f9fb;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .invoice-item:last-child {
            margin-bottom: 0;
        }
        
        .invoice-info {
            flex: 1;
        }
        
        .invoice-number {
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            margin-bottom: 4px;
        }
        
        .invoice-date {
            font-size: 12px;
            color: #888;
        }
        
        .invoice-actions {
            display: flex;
            gap: 8px;
        }
        
        .invoice-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        
        .invoice-btn.view {
            background: #ecf8fd;
            color: #1a7a96;
        }
        
        .invoice-btn.view:hover {
            background: #1a7a96;
            color: white;
        }
        
        .invoice-btn.send {
            background: #edf7f0;
            color: #1f7a42;
        }
        
        .invoice-btn.send:hover {
            background: #1f7a42;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #aaa;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        
        .empty-state p {
            font-size: 14px;
            margin: 0;
        }
        
        .empty-state small {
            font-size: 12px;
            color: #999;
        }
        
        /* Tab navigation for charges */
        .tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }
        
        .tab-btn {
            padding: 8px 16px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #888;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .tab-btn:hover {
            background: #f5f5f5;
        }
        
        .tab-btn.active {
            background: var(--gold, #8B7355);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
                    <?php echo ucfirst(str_replace('-', ' ', $booking['status'])); ?>
                </div>
                <div class="hero-dates">
                    <?php echo date('M j', strtotime($booking['check_in_date'])); ?> - <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>
                </div>
            </div>
        </div>
    </div>
    
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
                        <span class="status-dot <?php echo htmlspecialchars($booking['derived_room_status']); ?>"></span>
                        <?php echo ucfirst($booking['derived_room_status']); ?>
                    </div>
                    <?php else: ?>
                    <div style="color: #aaa; font-size: 13px; margin-top: 12px;">
                        <i class="fas fa-info-circle"></i> No specific room assigned
                    </div>
                    <div class="room-status-indicator" style="margin-top: 12px;">
                        <span class="status-dot <?php echo htmlspecialchars($booking['derived_room_status']); ?>"></span>
                        <?php echo ucfirst($booking['derived_room_status']); ?>
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
                    <?php
                    $display_total = $folio_summary['grand_total'] ?? $booking['total_amount'];
                    ?>
                    <div class="payment-amount">
                        <span class="currency"><?php echo $currency_symbol; ?></span>
                        <?php echo number_format($display_total, 0); ?>
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
                <div style="margin-top: 12px; padding: 10px 14px; background: #edf7f0; border-radius: 10px; font-size: 12px; color: #1f7a42; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i>
                    Payment received - Thank you!
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Folio/Charges Card -->
        <div class="info-card folio-card" id="folio">
            <div class="info-card-header">
                <div class="icon folio"><i class="fas fa-receipt"></i></div>
                <h3>Folio / Charges</h3>
            </div>
            <div class="info-card-body">
                <div class="folio-header">
                    <div class="folio-actions">
                        <button class="folio-btn primary" onclick="openAddChargeModal()">
                            <i class="fas fa-plus"></i> Add Charge
                        </button>
                        <button class="folio-btn secondary" onclick="openMenuModal()">
                            <i class="fas fa-utensils"></i> Add Menu Item
                        </button>
                    </div>
                </div>
                
                <?php 
                // Filter out voided charges for active display
                $active_charges = array_filter($folio_charges, function($c) { return !$c['voided']; });
                ?>
                
                <?php if (!empty($active_charges)): ?>
                <table class="folio-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th style="text-align: right;">Qty</th>
                            <th style="text-align: right;">Unit Price</th>
                            <th style="text-align: right;">VAT</th>
                            <th style="text-align: right;">Line Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folio_charges as $charge): ?>
                        <tr class="<?php echo $charge['voided'] ? 'voided' : ''; ?>">
                            <td>
                                <span style="font-size: 12px; color: #666;">
                                    <?php echo date('M j, Y', strtotime($charge['posted_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="charge-type <?php echo $charge['charge_type']; ?>">
                                    <?php echo htmlspecialchars($charge['charge_type']); ?>
                                </span>
                                <?php if ($charge['voided']): ?>
                                <span class="void-badge"><i class="fas fa-ban"></i> Voided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($charge['description']); ?>
                                <?php if ($charge['voided'] && $charge['void_reason']): ?>
                                <br><small style="color: #a03030;">Reason: <?php echo htmlspecialchars($charge['void_reason']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;"><?php echo number_format($charge['quantity'], 0); ?></td>
                            <td style="text-align: right;"><?php echo $currency_symbol; ?><?php echo number_format($charge['unit_price'], 2); ?></td>
                            <td style="text-align: right;"><?php echo $charge['vat_rate'] > 0 ? number_format($charge['vat_amount'], 2) : '-'; ?></td>
                            <td style="text-align: right; font-weight: 600;"><?php echo $currency_symbol; ?><?php echo number_format($charge['line_total'], 2); ?></td>
                            <td>
                                <?php if (!$charge['voided']): ?>
                                <button class="void-charge-btn" onclick="openVoidChargeModal(<?php echo $charge['id']; ?>, '<?php echo htmlspecialchars($charge['description'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-ban"></i> Void
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="folio-summary">
                    <div class="folio-summary-item">
                        <div class="folio-summary-label">Room Base</div>
                        <div class="folio-summary-value"><?php echo $currency_symbol; ?><?php echo number_format($booking['total_amount'], 2); ?></div>
                    </div>
                    <div class="folio-summary-item">
                        <div class="folio-summary-label">Extras</div>
                        <div class="folio-summary-value"><?php echo $currency_symbol; ?><?php echo number_format($folio_summary['extras_total'] ?? 0, 2); ?></div>
                    </div>
                    <div class="folio-summary-item">
                        <div class="folio-summary-label">Total Due</div>
                        <div class="folio-summary-value total"><?php echo $currency_symbol; ?><?php echo number_format($folio_summary['grand_total'] ?? $booking['total_amount'], 2); ?></div>
                    </div>
                    <?php if (($folio_summary['balance_due'] ?? 0) > 0): ?>
                    <div class="folio-summary-item">
                        <div class="folio-summary-label">Balance Due</div>
                        <div class="folio-summary-value balance"><?php echo $currency_symbol; ?><?php echo number_format($folio_summary['balance_due'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (($folio_summary['amount_paid'] ?? 0) > 0): ?>
                    <div class="folio-summary-item">
                        <div class="folio-summary-label">Amount Paid</div>
                        <div class="folio-summary-value paid"><?php echo $currency_symbol; ?><?php echo number_format($folio_summary['amount_paid'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No folio charges yet</p>
                    <small>Click "Add Charge" or "Add Menu Item" to add items to the guest folio.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Invoices Card -->
        <div class="info-card invoices-card" id="invoices">
            <div class="info-card-header">
                <div class="icon invoice"><i class="fas fa-file-invoice"></i></div>
                <h3>Invoices</h3>
            </div>
            <div class="info-card-body">
                <div class="folio-header">
                    <div class="folio-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="invoice_action" value="generate_invoice">
                            <button type="submit" class="folio-btn primary">
                                <i class="fas fa-file-pdf"></i> Generate Invoice
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="invoice_action" value="send_invoice">
                            <button type="submit" class="folio-btn success">
                                <i class="fas fa-paper-plane"></i> Send Invoice
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($existing_invoices)): ?>
                <div class="invoice-list">
                    <?php foreach ($existing_invoices as $invoice): ?>
                    <div class="invoice-item">
                        <div class="invoice-info">
                            <div class="invoice-number">
                                <i class="fas fa-file-invoice" style="color: var(--gold, #8B7355); margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            </div>
                            <div class="invoice-date">
                                Generated: <?php echo date('M j, Y \a\t g:i A', strtotime($invoice['created_at'])); ?>
                            </div>
                        </div>
                        <div class="invoice-actions">
                            <?php if ($invoice['invoice_path']): ?>
                            <a href="../<?php echo htmlspecialchars($invoice['invoice_path']); ?>" target="_blank" class="invoice-btn view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <p>No invoices generated yet</p>
                    <small>Click "Generate Invoice" to create an invoice for this booking.</small>
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
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-history" style="font-size: 32px;"></i>
                        <p>No activity recorded yet</p>
                    </div>
                    <?php else: ?>
                    <?php foreach (array_slice($timeline, 0, 10) as $event):
                        $type_info = formatActionType($event['action_type']);
                        $event_metadata = !empty($event['metadata']) ? json_decode($event['metadata'], true) : [];
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
                            <?php if ($event['action_type'] === 'date_adjustment' && !empty($event_metadata)): ?>
                            <div class="timeline-adjustment-details" style="margin-top: 8px; padding: 8px; background: #fef2f2; border-radius: 6px; font-size: 11px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #666;">Nights:</span>
                                    <span style="font-weight: 600;">
                                        <?php echo htmlspecialchars($event_metadata['old']['nights'] ?? '?'); ?> →
                                        <?php echo htmlspecialchars($event_metadata['new']['nights'] ?? '?'); ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #666;">Amount Delta:</span>
                                    <span style="font-weight: 600; color: <?php echo ($event_metadata['amount_delta'] ?? 0) >= 0 ? '#a03030' : '#1f7a42'; ?>;">
                                        <?php echo ($event_metadata['amount_delta'] ?? 0) >= 0 ? '+' : '-'; ?>
                                        <?php echo $currency_symbol . number_format(abs($event_metadata['amount_delta'] ?? 0), 2); ?>
                                    </span>
                                </div>
                                <?php if (!empty($event_metadata['reason'])): ?>
                                <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #f0f0f0; color: #888; font-style: italic;">
                                    "<?php echo htmlspecialchars($event_metadata['reason']); ?>"
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-sticky-note" style="font-size: 28px;"></i>
                        <p>No notes yet</p>
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
        <!-- Special Requests -->
        <div class="info-card" style="grid-column: 1 / -1;">
            <div class="info-card-header">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;"><i class="fas fa-comment-dots"></i></div>
                <h3>Special Requests</h3>
            </div>
            <div class="info-card-body">
                <div style="font-size: 14px; color: #555; line-height: 1.6;">
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
                <?php
                    $can_checkin = ($booking['actual_payment_status'] === 'paid' || $booking['actual_payment_status'] === 'completed');
                    $room_assigned = !empty($booking['individual_room_id']);
                    $check_in_date = new DateTime($booking['check_in_date']);
                    $check_in_date->setTime(0, 0, 0);
                    $today = new DateTime('today');
                    $checkin_date_reached = $check_in_date <= $today;
                    $checkin_disabled_reason = '';
                    if (!$can_checkin) {
                        $checkin_disabled_reason = 'Payment required before check-in';
                    } elseif (!$room_assigned) {
                        $checkin_disabled_reason = 'Room must be assigned before check-in';
                    } elseif (!$checkin_date_reached) {
                        $checkin_disabled_reason = 'Check-in date has not been reached yet (' . htmlspecialchars($booking['check_in_date']) . ')';
                    }
                ?>
                
                <?php if (!$room_assigned): ?>
                <a href="bookings.php?action=assign-room&booking_id=<?php echo $booking_id; ?>" class="action-btn" style="background: linear-gradient(135deg, #8B7355 0%, #6f5b43 100%); color: white;">
                    <i class="fas fa-door-open"></i> Assign Room
                </a>
                <?php else: ?>
                <a href="bookings.php?action=assign-room&booking_id=<?php echo $booking_id; ?>" class="action-btn" style="background: linear-gradient(135deg, #28a745 0%, #208637 100%); color: white;">
                    <i class="fas fa-exchange-alt"></i> Change Room
                </a>
                <?php endif; ?>
                
                <form method="POST" style="display:inline;" onsubmit="return confirm('Check in this guest?')">
                    <input type="hidden" name="booking_action" value="checkin">
                    <button type="submit" class="action-btn checkin" <?php echo ($can_checkin && $room_assigned && $checkin_date_reached) ? '' : 'disabled title="' . htmlspecialchars($checkin_disabled_reason) . '"'; ?>>
                        <i class="fas fa-sign-in-alt"></i> Check In
                    </button>
                </form>
                <?php if ($checkin_disabled_reason): ?>
                    <small style="color: #dc3545; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($checkin_disabled_reason); ?>
                    </small>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($booking['status'] == 'checked-in'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Check out this guest?')">
                    <input type="hidden" name="booking_action" value="checkout">
                    <button type="submit" class="action-btn checkout">
                        <i class="fas fa-sign-out-alt"></i> Check Out
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if (in_array($booking['status'], ['confirmed', 'pending']) && strtotime($booking['check_in_date']) < strtotime('today')): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this booking as no-show? The room will be released.')">
                    <input type="hidden" name="booking_action" value="noshow">
                    <button type="submit" class="action-btn noshow"><i class="fas fa-user-slash"></i> No-Show</button>
                </form>
                <?php endif; ?>

                <?php
                    $can_cancel = !in_array($booking['status'], ['checked-in', 'checked-out', 'cancelled', 'no-show']);
                ?>
                <?php if ($can_cancel): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking? This cannot be undone.')">
                    <input type="hidden" name="booking_action" value="cancel">
                    <input type="hidden" name="cancellation_reason" value="Cancelled by admin">
                    <button type="submit" class="action-btn cancel"><i class="fas fa-times"></i> Cancel Booking</button>
                </form>
                <?php endif; ?>
                
                <a href="bookings.php" class="action-btn back"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
                <a href="edit-booking.php?id=<?php echo $booking_id; ?>" class="action-btn edit"><i class="fas fa-edit"></i> Edit Booking</a>
                <?php
                    // Check if booking is eligible for date adjustment
                    $can_adjust_dates = !in_array($booking['status'], ['cancelled', 'checked-out', 'no-show']);
                ?>
                <?php if ($can_adjust_dates): ?>
                <button type="button" class="action-btn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;" onclick="openDateAdjustModal()">
                    <i class="fas fa-calendar-alt"></i> Adjust Stay Dates
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Charge Modal -->
<div class="modal-overlay" id="addChargeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus" style="color: var(--gold, #8B7355);"></i> Add Custom Charge</h3>
            <button class="modal-close" onclick="closeAddChargeModal()">&times;</button>
        </div>
        <form method="POST" id="addChargeForm">
            <input type="hidden" name="charge_action" value="add_charge">
            <div class="form-group">
                <label>Charge Type</label>
                <select name="charge_type" required>
                    <option value="custom">Custom</option>
                    <option value="service">Service</option>
                    <option value="minibar">Minibar</option>
                    <option value="laundry">Laundry</option>
                    <option value="room_service">Room Service</option>
                    <option value="breakfast">Breakfast</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="e.g., Late checkout fee, Airport transfer" required>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" value="1" min="0.01" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Unit Price (<?php echo $currency_symbol; ?>)</label>
                <input type="number" name="unit_price" placeholder="0.00" min="0" step="0.01" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddChargeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add Charge</button>
            </div>
        </form>
    </div>
</div>

<!-- Menu Quick Add Modal -->
<div class="modal-overlay" id="menuModal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h3><i class="fas fa-utensils" style="color: var(--gold, #8B7355);"></i> Add Menu Item to Folio</h3>
            <button class="modal-close" onclick="closeMenuModal()">&times;</button>
        </div>
        <form method="POST" id="menuForm">
            <input type="hidden" name="charge_action" value="add_menu_item">
            <input type="hidden" name="menu_type" id="menuType" value="food">
            <input type="hidden" name="menu_item_id" id="menuItemId" value="">
            <input type="hidden" name="quantity" id="menuQuantity" value="1">
            
            <div class="tab-nav">
                <button type="button" class="tab-btn active" onclick="switchMenuTab('food')">🍽️ Food Menu</button>
                <button type="button" class="tab-btn" onclick="switchMenuTab('drink')">🍹 Drinks</button>
            </div>
            
            <div id="foodMenuTab" class="tab-content active">
                <?php if (!empty($food_menu_items)): ?>
                    <?php foreach ($food_menu_items as $category => $items): ?>
                    <div class="menu-category-section">
                        <div class="menu-category-title"><?php echo htmlspecialchars($category); ?></div>
                        <div class="menu-items-grid">
                            <?php foreach ($items as $item): ?>
                            <div class="menu-item-card" data-item-id="<?php echo $item['id']; ?>" data-item-price="<?php echo $item['price']; ?>" data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>" data-menu-type="food" onclick="selectMenuItem(this)">
                                <div class="menu-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="menu-item-price"><?php echo $currency_symbol; ?><?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state" style="padding: 20px;">
                    <i class="fas fa-utensils"></i>
                    <p>No food menu items available</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="drinkMenuTab" class="tab-content">
                <?php if (!empty($drink_menu_items)): ?>
                    <?php foreach ($drink_menu_items as $category => $items): ?>
                    <div class="menu-category-section">
                        <div class="menu-category-title"><?php echo htmlspecialchars($category); ?></div>
                        <div class="menu-items-grid">
                            <?php foreach ($items as $item): ?>
                            <div class="menu-item-card" data-item-id="<?php echo $item['id']; ?>" data-item-price="<?php echo $item['price']; ?>" data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>" data-menu-type="drink" onclick="selectMenuItem(this)">
                                <div class="menu-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="menu-item-price"><?php echo $currency_symbol; ?><?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state" style="padding: 20px;">
                    <i class="fas fa-cocktail"></i>
                    <p>No drink menu items available</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label>Quantity</label>
                <input type="number" id="menuQuantityInput" value="1" min="1" step="1" onchange="updateMenuQuantity()">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeMenuModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="menuAddBtn" disabled>Add to Folio</button>
            </div>
        </form>
    </div>
</div>

<!-- Void Charge Modal -->
<div class="modal-overlay" id="voidChargeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ban" style="color: #a03030;"></i> Void Charge</h3>
            <button class="modal-close" onclick="closeVoidChargeModal()">&times;</button>
        </div>
        <form method="POST" id="voidChargeForm">
            <input type="hidden" name="charge_action" value="void_charge">
            <input type="hidden" name="charge_id" id="voidChargeId" value="">
            <div class="form-group">
                <label>Charge to Void</label>
                <input type="text" id="voidChargeDescription" readonly style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>Reason for Voiding</label>
                <textarea name="void_reason" placeholder="e.g., Item not consumed, Error in charging, Guest complaint" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeVoidChargeModal()">Cancel</button>
                <button type="submit" class="btn-danger">Void Charge</button>
            </div>
        </form>
    </div>
</div>

<!-- Date Adjustment Modal -->
<div class="modal-overlay" id="dateAdjustModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt" style="color: #f5576c;"></i> Adjust Stay Dates</h3>
            <button class="modal-close" onclick="closeDateAdjustModal()">&times;</button>
        </div>
        <form method="POST" id="dateAdjustForm">
            <input type="hidden" name="adjust_dates" value="1">
            
            <div style="background: #f8f9fb; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #888;">Current Check-in:</span>
                    <span style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($booking['check_in_date']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #888;">Current Check-out:</span>
                    <span style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($booking['check_out_date']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 12px; color: #888;">Current Nights:</span>
                    <span style="font-size: 14px; font-weight: 600;"><?php echo (int)$booking['number_of_nights']; ?> nights</span>
                </div>
            </div>
            
            <div class="form-group">
                <label>New Check-in Date *</label>
                <input type="date" name="new_check_in" id="newCheckIn" value="<?php echo htmlspecialchars($booking['check_in_date']); ?>" required onchange="previewDateAdjustment()">
            </div>
            
            <div class="form-group">
                <label>New Check-out Date *</label>
                <input type="date" name="new_check_out" id="newCheckOut" value="<?php echo htmlspecialchars($booking['check_out_date']); ?>" required onchange="previewDateAdjustment()">
            </div>
            
            <div id="dateAdjustPreview" style="background: #f0f8ff; padding: 16px; border-radius: 12px; margin-bottom: 16px; display: none;">
                <div style="font-size: 13px; font-weight: 600; color: #1a7a96; margin-bottom: 12px;">
                    <i class="fas fa-calculator"></i> Adjustment Preview
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #666;">New Nights:</span>
                    <span id="previewNights" style="font-size: 13px; font-weight: 600;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #666;">Nights Change:</span>
                    <span id="previewNightsDelta" style="font-size: 13px; font-weight: 600;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 12px; color: #666;">New Total:</span>
                    <span id="previewNewTotal" style="font-size: 13px; font-weight: 600;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid #d0e8ff;">
                    <span style="font-size: 12px; font-weight: 600; color: #1a7a96;">Amount Delta:</span>
                    <span id="previewDelta" style="font-size: 14px; font-weight: 700; color: #f5576c;">-</span>
                </div>
            </div>
            
            <div id="dateAdjustError" style="background: #fef2f2; color: #a03030; padding: 12px; border-radius: 8px; margin-bottom: 16px; display: none;"></div>
            
            <div id="dateAdjustWarning" style="background: #fff7ed; color: #c2410c; padding: 12px; border-radius: 8px; margin-bottom: 16px; display: none;"></div>
            
            <div class="form-group">
                <label>Reason for Adjustment *</label>
                <textarea name="adjustment_reason" placeholder="e.g., Guest requested early check-in, Extended stay due to flight delay, Guest checked out early" required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeDateAdjustModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="dateAdjustSubmitBtn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">Confirm Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
const currencySymbol = '<?php echo $currency_symbol; ?>';

function openAddChargeModal() {
    document.getElementById('addChargeModal').classList.add('active');
}

function closeAddChargeModal() {
    document.getElementById('addChargeModal').classList.remove('active');
    document.getElementById('addChargeForm').reset();
}

function openMenuModal() {
    document.getElementById('menuModal').classList.add('active');
}

function closeMenuModal() {
    document.getElementById('menuModal').classList.remove('active');
    document.getElementById('menuForm').reset();
    document.getElementById('menuAddBtn').disabled = true;
    document.querySelectorAll('.menu-item-card').forEach(card => {
        card.classList.remove('selected');
    });
}

function switchMenuTab(type) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(type + 'MenuTab').classList.add('active');
    
    // Update hidden field
    document.getElementById('menuType').value = type;
    
    // Clear selection
    document.querySelectorAll('.menu-item-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.getElementById('menuItemId').value = '';
    document.getElementById('menuAddBtn').disabled = true;
}

function selectMenuItem(card) {
    // Clear previous selection
    document.querySelectorAll('.menu-item-card').forEach(c => {
        c.classList.remove('selected');
    });
    
    // Select this card
    card.classList.add('selected');
    
    // Update form
    document.getElementById('menuItemId').value = card.dataset.itemId;
    document.getElementById('menuType').value = card.dataset.menuType;
    document.getElementById('menuAddBtn').disabled = false;
}

function updateMenuQuantity() {
    const qty = document.getElementById('menuQuantityInput').value || 1;
    document.getElementById('menuQuantity').value = qty;
}

function openVoidChargeModal(chargeId, description) {
    document.getElementById('voidChargeId').value = chargeId;
    document.getElementById('voidChargeDescription').value = description;
    document.getElementById('voidChargeModal').classList.add('active');
}

function closeVoidChargeModal() {
    document.getElementById('voidChargeModal').classList.remove('active');
    document.getElementById('voidChargeForm').reset();
}

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Update menu quantity before submit
document.getElementById('menuForm').addEventListener('submit', function() {
    updateMenuQuantity();
});

// Date Adjustment Functions
const currentCheckIn = '<?php echo htmlspecialchars($booking['check_in_date']); ?>';
const currentCheckOut = '<?php echo htmlspecialchars($booking['check_out_date']); ?>';
const currentNights = <?php echo (int)$booking['number_of_nights']; ?>;
const currentTotal = <?php echo (float)$booking['total_amount']; ?>;
const currentChildSupplement = <?php echo (float)($booking['child_supplement_total'] ?? 0); ?>;
const pricePerNight = <?php echo (float)($booking['price_per_night'] ?? 0); ?>;
const vatRate = <?php echo (float)getSetting('vat_enabled') === '1' ? (float)getSetting('vat_rate') : 0; ?>;

function openDateAdjustModal() {
    document.getElementById('dateAdjustModal').classList.add('active');
    document.getElementById('newCheckIn').value = currentCheckIn;
    document.getElementById('newCheckOut').value = currentCheckOut;
    previewDateAdjustment();
}

function closeDateAdjustModal() {
    document.getElementById('dateAdjustModal').classList.remove('active');
    document.getElementById('dateAdjustForm').reset();
    document.getElementById('dateAdjustPreview').style.display = 'none';
    document.getElementById('dateAdjustError').style.display = 'none';
    const warningDiv = document.getElementById('dateAdjustWarning');
    if (warningDiv) {
        warningDiv.style.display = 'none';
    }
}

function previewDateAdjustment() {
    const newCheckIn = document.getElementById('newCheckIn').value;
    const newCheckOut = document.getElementById('newCheckOut').value;
    const preview = document.getElementById('dateAdjustPreview');
    const error = document.getElementById('dateAdjustError');
    const submitBtn = document.getElementById('dateAdjustSubmitBtn');
    
    // Reset
    error.style.display = 'none';
    submitBtn.disabled = false;
    
    // Validate dates
    if (!newCheckIn || !newCheckOut) {
        preview.style.display = 'none';
        return;
    }
    
    const checkInDate = new Date(newCheckIn);
    const checkOutDate = new Date(newCheckOut);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Check for past dates (only if original check-in is today or future)
    const originalCheckIn = new Date(currentCheckIn);
    if (originalCheckIn >= today && checkInDate < today) {
        error.textContent = 'Cannot adjust dates to the past. The new check-in date must be today or in the future.';
        error.style.display = 'block';
        preview.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    if (checkInDate >= checkOutDate) {
        error.textContent = 'Check-out date must be after check-in date.';
        error.style.display = 'block';
        preview.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    // Calculate nights
    const newNights = Math.round((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
    
    if (newNights <= 0) {
        error.textContent = 'Booking must be for at least one night.';
        error.style.display = 'block';
        preview.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    // Maximum stay validation (30 nights)
    const maxStayNights = 30;
    if (newNights > maxStayNights) {
        error.textContent = 'Booking cannot exceed ' + maxStayNights + ' nights. Please contact management for extended stays.';
        error.style.display = 'block';
        preview.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }
    
    // Calculate new total with child supplement
    const newBaseAmount = pricePerNight * newNights;
    const newVatAmount = newBaseAmount * (vatRate / 100);
    
    // Calculate child supplement adjustment (proportional to nights change)
    let newChildSupplement = 0;
    if (currentNights > 0 && currentChildSupplement > 0) {
        const nightRatio = newNights / currentNights;
        newChildSupplement = currentChildSupplement * nightRatio;
    }
    
    const newTotal = newBaseAmount + newVatAmount + newChildSupplement;
    const amountDelta = newTotal - currentTotal;
    const nightsDelta = newNights - currentNights;
    
    // Update preview
    document.getElementById('previewNights').textContent = newNights + ' night' + (newNights !== 1 ? 's' : '');
    
    const nightsDeltaEl = document.getElementById('previewNightsDelta');
    if (nightsDelta > 0) {
        nightsDeltaEl.textContent = '+' + nightsDelta + ' night' + (nightsDelta !== 1 ? 's' : '');
        nightsDeltaEl.style.color = '#1f7a42';
    } else if (nightsDelta < 0) {
        nightsDeltaEl.textContent = nightsDelta + ' night' + (nightsDelta !== -1 ? 's' : '');
        nightsDeltaEl.style.color = '#a03030';
    } else {
        nightsDeltaEl.textContent = 'No change';
        nightsDeltaEl.style.color = '#666';
    }
    
    document.getElementById('previewNewTotal').textContent = currencySymbol + newTotal.toFixed(2);
    
    const deltaEl = document.getElementById('previewDelta');
    if (amountDelta > 0) {
        deltaEl.textContent = '+' + currencySymbol + Math.abs(amountDelta).toFixed(2) + ' additional charge';
        deltaEl.style.color = '#a03030';
    } else if (amountDelta < 0) {
        deltaEl.textContent = '-' + currencySymbol + Math.abs(amountDelta).toFixed(2) + ' refund/credit';
        deltaEl.style.color = '#1f7a42';
    } else {
        deltaEl.textContent = 'No change';
        deltaEl.style.color = '#666';
    }
    
    // Warning for significant changes (more than 50% increase or decrease)
    const changePercent = Math.abs(amountDelta / currentTotal * 100);
    if (changePercent > 50 && amountDelta !== 0) {
        const warningDiv = document.getElementById('dateAdjustWarning');
        if (warningDiv) {
            warningDiv.style.display = 'block';
            warningDiv.textContent = 'Warning: This adjustment represents a ' + changePercent.toFixed(0) + '% change in the booking total.';
        }
    }
    
    preview.style.display = 'block';
}
</script>

<script src="js/admin-components.js"></script>
<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>