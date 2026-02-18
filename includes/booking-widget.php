<?php
/**
 * Booking Widget Component
 * Standalone booking form with glassmorphism styling
 * 
 * Usage: include 'includes/booking-widget.php';
 * Can be included on any page that needs booking functionality
 */

// Get base URL for form action
$base_url = getSetting('base_url', '');

// Check if booking system is enabled
if (!function_exists('isBookingEnabled')) {
    require_once __DIR__ . '/../includes/booking-functions.php';
}

if (!function_exists('renderSectionHeader')) {
    require_once __DIR__ . '/section-headers.php';
}

// Check if booking is enabled before showing widget
if (!isBookingEnabled()) {
    // Show disabled message
    echo renderBookingDisabledContent('widget');
    return;
}
?>

<!-- Booking Section -->
<section class="booking-section landing-section" data-lazy-reveal>
    <div class="booking-widget-container">
        <div class="booking-widget__intro scroll-reveal">
            <?php renderSectionHeader('booking_widget', 'index', [
                'label' => 'Reserve',
                'title' => 'Begin Your Stay',
                'description' => 'Select your dates and preferences for a seamless luxury booking experience.'
            ], 'editorial-header section-header--editorial'); ?>
            <p class="booking-widget__meta">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                Secure booking • Instant confirmation
            </p>
        </div>
        <form class="editorial-booking-form" action="<?php echo htmlspecialchars($base_url); ?>/booking.php" method="GET">
            <div class="editorial-booking-field">
                <label class="editorial-booking-label" for="check-in">Check In</label>
                <input type="date" class="editorial-booking-input" id="check-in" name="check_in" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="editorial-booking-field">
                <label class="editorial-booking-label" for="check-out">Check Out</label>
                <input type="date" class="editorial-booking-input" id="check-out" name="check_out" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            <div class="editorial-booking-field">
                <label class="editorial-booking-label" for="guests">Guests</label>
                <select class="editorial-booking-input" id="guests" name="guests">
                    <option value="1">1 Guest</option>
                    <option value="2" selected>2 Guests</option>
                    <option value="3">3 Guests</option>
                    <option value="4">4 Guests</option>
                    <option value="5">5+ Guests</option>
                </select>
            </div>
            <div class="editorial-booking-field">
                <label class="editorial-booking-label" for="room-type">Room Type</label>
                <select class="editorial-booking-input" id="room-type" name="room_type">
                    <option value="">Any Room</option>
                    <option value="standard">Standard Room</option>
                    <option value="deluxe">Deluxe Room</option>
                    <option value="suite">Suite</option>
                    <option value="family">Family Room</option>
                </select>
            </div>
            <button type="submit" class="editorial-booking-submit">
                <span>Check Availability</span>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</section>
