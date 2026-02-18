<?php
/**
 * Booking Timeline Functions
 * Tracks all booking activities from creation to completion
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Log a booking timeline event
 * 
 * @param int $booking_id Booking ID
 * @param string $booking_reference Booking reference
 * @param string $action Action description (e.g., 'Booking created', 'Status changed to confirmed')
 * @param string $action_type Action type enum value
 * @param string|null $description Detailed description
 * @param string|null $old_value Previous value (for status changes)
 * @param string|null $new_value New value (for status changes)
 * @param string $performed_by_type Who performed the action
 * @param int|null $performed_by_id User ID (if admin)
 * @param string|null $performed_by_name User name
 * @param array|null $metadata Additional metadata
 * @return bool Success status
 */
function logBookingEvent(
    int $booking_id,
    string $booking_reference,
    string $action,
    string $action_type,
    ?string $description = null,
    ?string $old_value = null,
    ?string $new_value = null,
    string $performed_by_type = 'system',
    ?int $performed_by_id = null,
    ?string $performed_by_name = null,
    ?array $metadata = null
): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO booking_timeline_logs (
                booking_id, booking_reference, action, action_type, description,
                old_value, new_value, performed_by_type, performed_by_id,
                performed_by_name, ip_address, user_agent, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->execute([
            $booking_id,
            $booking_reference,
            $action,
            $action_type,
            $description,
            $old_value,
            $new_value,
            $performed_by_type,
            $performed_by_id,
            $performed_by_name,
            $ip_address,
            $user_agent,
            $metadata ? json_encode($metadata) : null
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log booking event: " . $e->getMessage());
        return false;
    }
}

/**
 * Log booking creation
 */
function logBookingCreated(array $booking, string $performed_by_type = 'guest', ?int $performed_by_id = null, ?string $performed_by_name = null): bool {
    return logBookingEvent(
        $booking['id'],
        $booking['booking_reference'],
        'Booking created',
        'create',
        "New booking created for {$booking['number_of_nights']} night(s) - Total: {$booking['total_amount']}",
        null,
        $booking['status'],
        $performed_by_type,
        $performed_by_id,
        $performed_by_name,
        [
            'room_id' => $booking['room_id'] ?? null,
            'check_in' => $booking['check_in_date'] ?? null,
            'check_out' => $booking['check_out_date'] ?? null,
            'guests' => $booking['number_of_guests'] ?? null,
            'total' => $booking['total_amount'] ?? null,
            'is_tentative' => $booking['is_tentative'] ?? false
        ]
    );
}

/**
 * Log booking status change
 */
function logBookingStatusChange(int $booking_id, string $booking_reference, string $old_status, string $new_status, string $performed_by_type = 'admin', ?int $performed_by_id = null, ?string $performed_by_name = null, ?string $reason = null): bool {
    $description = "Status changed from '{$old_status}' to '{$new_status}'";
    if ($reason) {
        $description .= " - Reason: {$reason}";
    }
    
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        "Status changed: {$old_status} → {$new_status}",
        'status_change',
        $description,
        $old_status,
        $new_status,
        $performed_by_type,
        $performed_by_id,
        $performed_by_name,
        ['reason' => $reason]
    );
}

/**
 * Log booking cancellation with payment reconciliation
 */
function logBookingCancellation(
    array $booking,
    string $cancelled_by_type = 'admin',
    ?int $cancelled_by_id = null,
    ?string $cancelled_by_name = null,
    ?string $reason = null,
    float $amount_paid = 0.00,
    float $refund_amount = 0.00,
    string $refund_status = 'not_required'
): bool {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Log to cancellation_log table
        $stmt = $pdo->prepare("
            INSERT INTO cancellation_log (
                booking_id, booking_reference, booking_type, guest_email, guest_name,
                cancelled_by_type, cancelled_by_id, cancelled_by_name, cancellation_reason,
                total_amount, amount_paid, refund_amount, refund_status,
                ip_address, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $booking['id'],
            $booking['booking_reference'],
            'room',
            $booking['guest_email'],
            $booking['guest_name'],
            $cancelled_by_type,
            $cancelled_by_id,
            $cancelled_by_name,
            $reason,
            $booking['total_amount'] ?? 0,
            $amount_paid,
            $refund_amount,
            $refund_status,
            $_SERVER['REMOTE_ADDR'] ?? null,
            json_encode([
                'room_name' => $booking['room_name'] ?? null,
                'check_in' => $booking['check_in_date'] ?? null,
                'check_out' => $booking['check_out_date'] ?? null
            ])
        ]);
        
        // Log to timeline
        logBookingStatusChange(
            $booking['id'],
            $booking['booking_reference'],
            $booking['status'],
            'cancelled',
            $cancelled_by_type,
            $cancelled_by_id,
            $cancelled_by_name,
            $reason
        );
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Failed to log booking cancellation: " . $e->getMessage());
        return false;
    }
}

/**
 * Log email sent
 */
function logBookingEmail(int $booking_id, string $booking_reference, string $email_type, string $recipient, bool $success, ?string $error = null): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        "Email sent: {$email_type}",
        'email',
        $success ? "Email '{$email_type}' sent to {$recipient}" : "Failed to send '{$email_type}' to {$recipient}: {$error}",
        null,
        $success ? 'sent' : 'failed',
        'system',
        null,
        null,
        [
            'email_type' => $email_type,
            'recipient' => $recipient,
            'success' => $success,
            'error' => $error
        ]
    );
}

/**
 * Log check-in
 */
function logBookingCheckIn(int $booking_id, string $booking_reference, string $performed_by_type = 'admin', ?int $performed_by_id = null, ?string $performed_by_name = null): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        'Guest checked in',
        'check_in',
        'Guest has been checked in to the room',
        'confirmed',
        'checked-in',
        $performed_by_type,
        $performed_by_id,
        $performed_by_name
    );
}

/**
 * Log check-out
 */
function logBookingCheckOut(int $booking_id, string $booking_reference, string $performed_by_type = 'admin', ?int $performed_by_id = null, ?string $performed_by_name = null): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        'Guest checked out',
        'check_out',
        'Guest has checked out from the room',
        'checked-in',
        'checked-out',
        $performed_by_type,
        $performed_by_id,
        $performed_by_name
    );
}

/**
 * Log tentative booking conversion
 */
function logTentativeConversion(int $booking_id, string $booking_reference, string $performed_by_type = 'admin', ?int $performed_by_id = null, ?string $performed_by_name = null): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        'Tentative booking converted to confirmed',
        'conversion',
        'Tentative booking has been converted to a confirmed booking',
        'tentative',
        'pending',
        $performed_by_type,
        $performed_by_id,
        $performed_by_name
    );
}

/**
 * Log tentative booking expiry
 */
function logTentativeExpiry(int $booking_id, string $booking_reference): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        'Tentative booking expired',
        'expiry',
        'Tentative booking has expired and been automatically cancelled',
        'tentative',
        'expired',
        'system'
    );
}

/**
 * Log payment
 */
function logBookingPayment(int $booking_id, string $booking_reference, float $amount, string $payment_type, string $payment_method, string $status = 'completed', ?int $processed_by = null, ?string $reference = null): bool {
    global $pdo;
    
    try {
        // Insert into payments table
        $stmt = $pdo->prepare("
            INSERT INTO booking_payments (
                booking_id, booking_reference, payment_type, amount, payment_method,
                payment_reference, payment_status, processed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $booking_id,
            $booking_reference,
            $payment_type,
            $amount,
            $payment_method,
            $reference,
            $status,
            $processed_by
        ]);
        
        // Log to timeline
        logBookingEvent(
            $booking_id,
            $booking_reference,
            "Payment recorded: {$payment_type}",
            'payment',
            ucfirst($payment_type) . " payment of " . number_format($amount, 2) . " via {$payment_method} - Status: {$status}",
            null,
            $status,
            $processed_by ? 'admin' : 'system',
            $processed_by
        );
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to log booking payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Log admin note
 */
function logBookingNote(int $booking_id, string $booking_reference, string $note, int $admin_id, string $admin_name): bool {
    return logBookingEvent(
        $booking_id,
        $booking_reference,
        'Note added',
        'note',
        $note,
        null,
        null,
        'admin',
        $admin_id,
        $admin_name
    );
}

/**
 * Get booking timeline
 */
function getBookingTimeline(int $booking_id): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM booking_timeline_logs 
            WHERE booking_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get booking timeline: " . $e->getMessage());
        return [];
    }
}

/**
 * Get cancellation logs
 */
function getCancellationLogs(int $limit = 50): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cl.*, r.name as room_name 
            FROM cancellation_log cl
            LEFT JOIN bookings b ON cl.booking_id = b.id
            LEFT JOIN rooms r ON b.room_id = r.id
            ORDER BY cl.cancellation_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get cancellation logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pending refunds
 */
function getPendingRefunds(): array {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT cl.*, b.room_id, r.name as room_name 
            FROM cancellation_log cl
            JOIN bookings b ON cl.booking_id = b.id
            JOIN rooms r ON b.room_id = r.id
            WHERE cl.refund_status IN ('pending', 'processing')
            ORDER BY cl.cancellation_date ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get pending refunds: " . $e->getMessage());
        return [];
    }
}

/**
 * Format timeline action type for display
 */
function formatActionType(string $action_type): string {
    $types = [
        'create' => ['label' => 'Created', 'icon' => 'fa-plus-circle', 'color' => '#28a745'],
        'update' => ['label' => 'Updated', 'icon' => 'fa-edit', 'color' => '#17a2b8'],
        'status_change' => ['label' => 'Status Change', 'icon' => 'fa-exchange-alt', 'color' => '#8B7355'],
        'payment' => ['label' => 'Payment', 'icon' => 'fa-credit-card', 'color' => '#28a745'],
        'cancellation' => ['label' => 'Cancellation', 'icon' => 'fa-times-circle', 'color' => '#dc3545'],
        'email' => ['label' => 'Email', 'icon' => 'fa-envelope', 'color' => '#6c757d'],
        'check_in' => ['label' => 'Check-in', 'icon' => 'fa-sign-in-alt', 'color' => '#28a745'],
        'check_out' => ['label' => 'Check-out', 'icon' => 'fa-sign-out-alt', 'color' => '#6c757d'],
        'conversion' => ['label' => 'Conversion', 'icon' => 'fa-check-double', 'color' => '#28a745'],
        'reminder' => ['label' => 'Reminder', 'icon' => 'fa-bell', 'color' => '#ffc107'],
        'expiry' => ['label' => 'Expired', 'icon' => 'fa-hourglass-end', 'color' => '#dc3545'],
        'note' => ['label' => 'Note', 'icon' => 'fa-sticky-note', 'color' => '#17a2b8']
    ];
    
    return $types[$action_type] ?? ['label' => ucfirst($action_type), 'icon' => 'fa-circle', 'color' => '#6c757d'];
}