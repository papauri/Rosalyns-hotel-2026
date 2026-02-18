<?php
/**
 * Booking System Functions
 * 
 * Modular booking functions that can be easily migrated to any website.
 * All booking logic is centralized here for easy maintenance and portability.
 * 
 * @package BookingSystem
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('BOOKING_SYSTEM_LOADED')) {
    define('BOOKING_SYSTEM_LOADED', true);
}

/**
 * Check if the booking system is enabled
 * 
 * @return bool True if booking system is enabled, false otherwise
 */
function isBookingEnabled(): bool {
    return getSetting('booking_system_enabled', '1') === '1';
}

function isConferenceEnabled(): bool {
    return getSetting('conference_system_enabled', '1') === '1';
}

function isGymEnabled(): bool {
    return getSetting('gym_system_enabled', '1') === '1';
}

function isRestaurantEnabled(): bool {
    return getSetting('restaurant_system_enabled', '1') === '1';
}

/**
 * Get the action to take when booking is disabled
 * 
 * @return string One of: 'message', 'contact', 'redirect'
 */
function getBookingDisabledAction(): string {
    return getSetting('booking_disabled_action', 'message');
}

/**
 * Get the custom message to display when booking is disabled
 * 
 * @return string HTML message
 */
function getBookingDisabledMessage(): string {
    $message = getSetting('booking_disabled_message', 'For booking inquiries, please contact us directly.');
    
    // Replace placeholders with actual contact info
    $phone = getSetting('phone_main', '');
    $email = getSetting('email_reservations', '');
    
    $message = str_replace('[contact info]', "Phone: {$phone} | Email: {$email}", $message);
    $message = str_replace('[phone]', $phone, $message);
    $message = str_replace('[email]', $email, $message);
    
    return $message;
}

/**
 * Render booking disabled content based on settings
 * 
 * @param string $size Size of the message: 'full', 'widget', or 'button'
 * @return string HTML content
 */
function renderBookingDisabledContent(string $size = 'full'): string {
    $action = getBookingDisabledAction();
    $message = getBookingDisabledMessage();
    $phone = getSetting('phone_main', '');
    $email = getSetting('email_reservations', '');
    $siteName = getSetting('site_name', 'Our Hotel');
    
    $html = '';
    
    if ($size === 'button') {
        // Compact button replacement
        $html = '<a href="mailto:' . htmlspecialchars($email) . '" class="btn btn-primary booking-disabled-btn">';
        $html .= '<i class="fas fa-envelope"></i> Contact for Availability';
        $html .= '</a>';
    } elseif ($size === 'widget') {
        // Widget-sized message
        $html = '<div class="booking-disabled-widget">';
        $html .= '<div class="booking-disabled-icon"><i class="fas fa-calendar-times"></i></div>';
        $html .= '<h4>Online Booking Temporarily Unavailable</h4>';
        $html .= '<p>' . $message . '</p>';
        if ($phone) {
            $html .= '<a href="tel:' . preg_replace('/[^0-9+]/', '', $phone) . '" class="booking-disabled-contact">';
            $html .= '<i class="fas fa-phone"></i> ' . htmlspecialchars($phone);
            $html .= '</a>';
        }
        if ($email) {
            $html .= '<a href="mailto:' . htmlspecialchars($email) . '" class="booking-disabled-contact">';
            $html .= '<i class="fas fa-envelope"></i> ' . htmlspecialchars($email);
            $html .= '</a>';
        }
        $html .= '</div>';
    } else {
        // Full page message
        $html = '<div class="booking-disabled-container">';
        $html .= '<div class="booking-disabled-content">';
        $html .= '<div class="booking-disabled-icon"><i class="fas fa-concierge-bell"></i></div>';
        $html .= '<h2>Reservations</h2>';
        $html .= '<p class="booking-disabled-subtitle">We\'d love to help you with your reservation</p>';
        $html .= '<div class="booking-disabled-message">' . $message . '</div>';
        
        if ($action === 'contact' || $action === 'message') {
            $html .= '<div class="booking-disabled-contacts">';
            if ($phone) {
                $html .= '<a href="tel:' . preg_replace('/[^0-9+]/', '', $phone) . '" class="booking-contact-card">';
                $html .= '<i class="fas fa-phone-alt"></i>';
                $html .= '<span>Call Us</span>';
                $html .= '<strong>' . htmlspecialchars($phone) . '</strong>';
                $html .= '</a>';
            }
            if ($email) {
                $html .= '<a href="mailto:' . htmlspecialchars($email) . '" class="booking-contact-card">';
                $html .= '<i class="fas fa-envelope"></i>';
                $html .= '<span>Email Us</span>';
                $html .= '<strong>' . htmlspecialchars($email) . '</strong>';
                $html .= '</a>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div></div>';
    }
    
    return $html;
}

/**
 * Get booking system settings
 * 
 * @return array All booking-related settings
 */
function getBookingSettings(): array {
    return [
        'enabled' => isBookingEnabled(),
        'disabled_action' => getBookingDisabledAction(),
        'disabled_message' => getBookingDisabledMessage(),
        'max_advance_days' => (int)getSetting('max_advance_booking_days', 30),
        'currency_symbol' => getSetting('currency_symbol', '$'),
        'payment_policy' => getSetting('payment_policy', ''),
        'tentative_duration_hours' => (int)getSetting('tentative_duration_hours', 48),
        'vat_enabled' => getSetting('vat_enabled', '0') === '1',
        'vat_rate' => (float)getSetting('vat_rate', 0),
        'booking_reference_prefix' => getSetting('booking_reference_prefix', 'BK'),
    ];
}

/**
 * Output booking button or disabled message
 * 
 * @param int $roomId Room ID to book
 * @param string $roomName Room name for display
 * @param string $class Additional CSS classes
 * @return void
 */
function renderBookingButton(int $roomId, string $roomName = '', string $class = ''): void {
    if (isBookingEnabled()) {
        $url = 'booking.php?room_id=' . $roomId;
        echo '<a href="' . htmlspecialchars($url) . '" class="btn btn-primary ' . htmlspecialchars($class) . '">';
        echo '<i class="fas fa-calendar-check"></i> Book Now';
        echo '</a>';
    }
}

/**
 * Output booking widget or disabled message
 * 
 * @return void
 */
function renderBookingWidget(): void {
    if (isBookingEnabled()) {
        include __DIR__ . '/booking-widget.php';
    }
}

/**
 * Check if user can access booking page
 * Redirects to home if booking is disabled
 * 
 * @return void
 */
function requireBookingEnabled(): void {
    if (!isBookingEnabled()) {
        // Log attempt
        error_log('Booking page accessed while booking system disabled');
        
        // Redirect based on action
        $action = getBookingDisabledAction();
        if ($action === 'redirect') {
            $redirectUrl = getSetting('booking_disabled_redirect_url', '/');
            header('Location: ' . $redirectUrl);
        } else {
            // Show message page
            http_response_code(503);
            echo '<!DOCTYPE html><html><head><title>Booking Unavailable</title>';
            echo '';
            echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
            echo '</head><body>';
            echo '<div class="container">';
            echo '<div class="icon"><i class="fas fa-calendar-times"></i></div>';
            echo '<h1>Online Booking Unavailable</h1>';
            echo '<div>' . getBookingDisabledMessage() . '</div>';
            echo '<p style="margin-top: 20px;"><a href="/"><i class="fas fa-arrow-left"></i> Return to Homepage</a></p>';
            echo '</div></body></html>';
        }
        exit;
    }
}

function requireConferenceEnabled(): void {
    if (!isConferenceEnabled()) {
        error_log('Conference page accessed while conference system disabled');
        http_response_code(503);
        header('Location: /');
        exit;
    }
}

function requireGymEnabled(): void {
    if (!isGymEnabled()) {
        error_log('Gym page accessed while gym system disabled');
        http_response_code(503);
        header('Location: /');
        exit;
    }
}

function requireRestaurantEnabled(): void {
    if (!isRestaurantEnabled()) {
        error_log('Restaurant page accessed while restaurant system disabled');
        http_response_code(503);
        header('Location: /');
        exit;
    }
}

/**
 * Output CSS for booking disabled states
 * 
 * @return void
 */
function outputBookingDisabledStyles(): void {
    if (isBookingEnabled()) {
        return;
    }
    ?><?php
}

// ============================================================================
// AVAILABILITY AND VALIDATION FUNCTIONS (Extracted for portability)
// ============================================================================

/**
 * Check room availability
 * This function wraps the main availability check for portability
 */
function checkAvailability(int $roomId, string $checkIn, string $checkOut): array {
    // Use existing function from database.php if available
    if (function_exists('checkRoomAvailability')) {
        return checkRoomAvailability($roomId, $checkIn, $checkOut);
    }
    
    // Fallback implementation
    global $pdo;
    
    $result = [
        'available' => true,
        'conflicts' => [],
        'room' => null
    ];
    
    try {
        // Get room details
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = 1");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $result['available'] = false;
            $result['error'] = 'Room not found';
            return $result;
        }
        
        $result['room'] = $room;
        
        // Check for blocked dates
        $blockedStmt = $pdo->prepare("
            SELECT COUNT(*) FROM room_blocked_dates
            WHERE block_date >= ? AND block_date < ?
            AND (room_id = ? OR room_id IS NULL)
        ");
        $blockedStmt->execute([$checkIn, $checkOut, $roomId]);
        if ($blockedStmt->fetchColumn() > 0) {
            $result['available'] = false;
            $result['error'] = 'Selected dates are blocked';
            return $result;
        }
        
        // Check for overlapping bookings
        $bookingsStmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE room_id = ?
            AND status IN ('pending', 'confirmed', 'checked-in')
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        ");
        $bookingsStmt->execute([$roomId, $checkIn, $checkOut]);
        $overlappingBookings = (int)$bookingsStmt->fetchColumn();
        
        if ($overlappingBookings >= $room['rooms_available']) {
            $result['available'] = false;
            $result['error'] = 'No rooms available for selected dates';
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Availability check error: " . $e->getMessage());
        $result['available'] = false;
        $result['error'] = 'Database error';
        return $result;
    }
}
