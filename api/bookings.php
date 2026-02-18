<?php
/**
 * Bookings API Endpoint
 * POST /api/bookings
 *
 * Creates a new booking
 * Requires permission: bookings.create
 *
 * Request body (JSON):
 * {
 *   "room_id": 1,
 *   "guest_name": "John Doe",
 *   "guest_email": "john@example.com",
 *   "guest_phone": "+265123456789",
 *   "guest_country": "Malawi",
 *   "guest_address": "123 Street",
 *   "number_of_guests": 2,
 *   "child_guests": 0,
 *   "occupancy_type": "double",
 *   "check_in_date": "2026-02-01",
 *   "check_out_date": "2026-02-03",
 *   "special_requests": "Early check-in please",
 *   "booking_type": "standard" // Optional: "standard" or "tentative" (default: "standard")
 * }
 *
 * SECURITY: This file must only be accessed through api/index.php
 * Direct access is blocked to prevent authentication bypass
 */

// Prevent direct access - must be accessed through api/index.php router
if (!defined('API_ACCESS_ALLOWED') || !isset($auth) || !isset($client)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Direct access to this endpoint is not allowed',
        'code' => 403,
        'message' => 'Please use the API router at /api/bookings'
    ]);
    exit;
}

// Check permission
if (!$auth->checkPermission($client, 'bookings.create')) {
    ApiResponse::error('Permission denied: bookings.create', 403);
}

require_once __DIR__ . '/../includes/booking-functions.php';
require_once __DIR__ . '/../includes/whatsapp-functions.php';
if (!isBookingEnabled()) {
    ApiResponse::error('Booking system is currently disabled', 503);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

try {
    // Get request body
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        ApiResponse::error('Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $requiredFields = [
        'room_id', 'guest_name', 'guest_email', 'guest_phone',
        'number_of_guests', 'check_in_date', 'check_out_date'
    ];
    
    // Validate booking type (optional, defaults to 'standard')
    $bookingType = isset($input['booking_type']) ? trim($input['booking_type']) : 'standard';
    if (!in_array($bookingType, ['standard', 'tentative'])) {
        ApiResponse::validationError(['booking_type' => 'Invalid booking type. Must be "standard" or "tentative"']);
    }
    
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (!empty($missingFields)) {
        ApiResponse::validationError($missingFields);
    }
    
    // Sanitize and validate data
    $bookingData = [
        'room_id' => (int)$input['room_id'],
        'guest_name' => trim($input['guest_name']),
        'guest_email' => trim($input['guest_email']),
        'guest_phone' => trim($input['guest_phone']),
        'guest_country' => isset($input['guest_country']) ? trim($input['guest_country']) : '',
        'guest_address' => isset($input['guest_address']) ? trim($input['guest_address']) : '',
        'number_of_guests' => (int)$input['number_of_guests'],
        'child_guests' => isset($input['child_guests']) ? (int)$input['child_guests'] : 0,
        'occupancy_type' => isset($input['occupancy_type']) ? trim((string)$input['occupancy_type']) : 'double',
        'check_in_date' => $input['check_in_date'],
        'check_out_date' => $input['check_out_date'],
        'special_requests' => isset($input['special_requests']) ? trim($input['special_requests']) : ''
    ];

    if (!in_array($bookingData['occupancy_type'], ['single', 'double', 'triple'], true)) {
        ApiResponse::validationError(['occupancy_type' => 'Invalid occupancy type. Must be single, double, or triple']);
    }

    if ($bookingData['child_guests'] < 0) {
        ApiResponse::validationError(['child_guests' => 'Children count cannot be negative']);
    }

    if ($bookingData['child_guests'] >= $bookingData['number_of_guests']) {
        ApiResponse::validationError(['child_guests' => 'At least 1 adult is required']);
    }

    $adultGuests = $bookingData['number_of_guests'] - $bookingData['child_guests'];
    if ($adultGuests < 1) {
        ApiResponse::validationError(['number_of_guests' => 'At least 1 adult is required']);
    }
    
    // Email validation
    if (!filter_var($bookingData['guest_email'], FILTER_VALIDATE_EMAIL)) {
        ApiResponse::validationError(['guest_email' => 'Invalid email address']);
    }
    
    // Date validation
    $checkInDate = new DateTime($bookingData['check_in_date']);
    $checkOutDate = new DateTime($bookingData['check_out_date']);
    $today = new DateTime('today');
    
    if ($checkInDate < $today) {
        ApiResponse::error('Check-in date cannot be in the past', 400);
    }
    
    if ($checkOutDate <= $checkInDate) {
        ApiResponse::error('Check-out date must be after check-in date', 400);
    }
    
    // Check advance booking restriction
    $maxAdvanceDays = (int)getSetting('max_advance_booking_days');
    $maxAdvanceDate = new DateTime();
    $maxAdvanceDate->modify('+' . $maxAdvanceDays . ' days');
    
    if ($checkInDate > $maxAdvanceDate) {
        ApiResponse::error("Bookings can only be made up to {$maxAdvanceDays} days in advance. Please select an earlier check-in date.", 400);
    }
    
    // Check if room exists and is active
    $roomStmt = $pdo->prepare(" 
        SELECT id, name, price_per_night,
               COALESCE(price_single_occupancy, price_per_night) AS price_single_occupancy,
               COALESCE(price_double_occupancy, price_per_night) AS price_double_occupancy,
               COALESCE(price_triple_occupancy, price_per_night) AS price_triple_occupancy,
               COALESCE(child_price_multiplier, 50.00) AS child_price_multiplier,
               max_guests,
               single_occupancy_enabled,
               double_occupancy_enabled,
               triple_occupancy_enabled,
               children_allowed
        FROM rooms 
        WHERE id = ? AND is_active = 1
    ");
    $roomStmt->execute([$bookingData['room_id']]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        ApiResponse::error('Room not found or not available', 404);
    }
    
    // Check capacity
    if ($bookingData['number_of_guests'] > $room['max_guests']) {
        ApiResponse::error("This room can accommodate maximum {$room['max_guests']} guests", 400);
    }

    $occupancyPolicy = resolveOccupancyPolicy($room, null);
    if (
        ($bookingData['occupancy_type'] === 'single' && empty($occupancyPolicy['single_enabled'])) ||
        ($bookingData['occupancy_type'] === 'double' && empty($occupancyPolicy['double_enabled'])) ||
        ($bookingData['occupancy_type'] === 'triple' && empty($occupancyPolicy['triple_enabled']))
    ) {
        ApiResponse::validationError(['occupancy_type' => 'Selected occupancy type is disabled for this room']);
    }

    if (empty($occupancyPolicy['children_allowed']) && $bookingData['child_guests'] > 0) {
        ApiResponse::validationError(['child_guests' => 'Children are not allowed for this room']);
    }
    
    // Check availability using existing function from config/database.php
    // isRoomAvailable() is already loaded via config/database.php
    $available = isRoomAvailable($bookingData['room_id'], $bookingData['check_in_date'], $bookingData['check_out_date']);
    
    if (!$available) {
        ApiResponse::error('This room is not available for the selected dates. Please choose different dates.', 409);
    }
    
    // Calculate nights and total
    $nights = $checkInDate->diff($checkOutDate)->days;
    if ($bookingData['occupancy_type'] === 'single') {
        $roomRate = (float)$room['price_single_occupancy'];
    } elseif ($bookingData['occupancy_type'] === 'triple') {
        $roomRate = (float)$room['price_triple_occupancy'];
    } else {
        $roomRate = (float)$room['price_double_occupancy'];
    }

    $childPriceMultiplier = (float)($room['child_price_multiplier'] ?? 50);
    if ($childPriceMultiplier < 0) {
        $childPriceMultiplier = 0;
    }

    $baseAmount = $roomRate * $nights;
    $childSupplementTotal = $bookingData['child_guests'] > 0
        ? ($roomRate * ($childPriceMultiplier / 100) * $bookingData['child_guests'] * $nights)
        : 0;
    $totalAmount = $baseAmount + $childSupplementTotal;
    
    // Generate unique booking reference
    $refPrefix = getSetting('booking_reference_prefix', 'LSH');
    do {
        $bookingReference = $refPrefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $refCheck = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE booking_reference = ?");
        $refCheck->execute([$bookingReference]);
        $refExists = $refCheck->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    } while ($refExists);
    
    // Determine status and tentative expiration
    $bookingStatus = ($bookingType === 'tentative') ? 'tentative' : 'pending';
    $isTentative = ($bookingType === 'tentative') ? 1 : 0;
    $tentativeExpiresAt = null;
    
    if ($bookingType === 'tentative') {
        // Get tentative duration from settings (default 48 hours)
        $tentativeDurationHours = (int)getSetting('tentative_duration_hours', 48);
        $tentativeExpiresAt = date('Y-m-d H:i:s', strtotime("+{$tentativeDurationHours} hours"));
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert booking
        $insertStmt = $pdo->prepare("
            INSERT INTO bookings (
                booking_reference, room_id, guest_name, guest_email, guest_phone,
                guest_country, guest_address, number_of_guests, adult_guests, child_guests,
                child_price_multiplier, check_in_date, check_out_date, number_of_nights,
                total_amount, child_supplement_total, special_requests, status,
                is_tentative, tentative_expires_at, occupancy_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertStmt->execute([
            $bookingReference,
            $bookingData['room_id'],
            $bookingData['guest_name'],
            $bookingData['guest_email'],
            $bookingData['guest_phone'],
            $bookingData['guest_country'],
            $bookingData['guest_address'],
            $bookingData['number_of_guests'],
            $adultGuests,
            $bookingData['child_guests'],
            $childPriceMultiplier,
            $bookingData['check_in_date'],
            $bookingData['check_out_date'],
            $nights,
            $totalAmount,
            $childSupplementTotal,
            $bookingData['special_requests'],
            $bookingStatus,
            $isTentative,
            $tentativeExpiresAt,
            $bookingData['occupancy_type']
        ]);
        
        $bookingId = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        // Prepare booking data for email
        $bookingForEmail = [
            'id' => $bookingId,
            'booking_reference' => $bookingReference,
            'room_id' => $bookingData['room_id'],
            'guest_name' => $bookingData['guest_name'],
            'guest_email' => $bookingData['guest_email'],
            'guest_phone' => $bookingData['guest_phone'],
            'check_in_date' => $bookingData['check_in_date'],
            'check_out_date' => $bookingData['check_out_date'],
            'number_of_nights' => $nights,
            'number_of_guests' => $bookingData['number_of_guests'],
            'adult_guests' => $adultGuests,
            'child_guests' => $bookingData['child_guests'],
            'child_price_multiplier' => $childPriceMultiplier,
            'child_supplement_total' => $childSupplementTotal,
            'occupancy_type' => $bookingData['occupancy_type'],
            'total_amount' => $totalAmount,
            'special_requests' => $bookingData['special_requests'],
            'status' => $bookingStatus,
            'is_tentative' => $isTentative,
            'tentative_expires_at' => $tentativeExpiresAt
        ];
        
        // Send appropriate email based on booking type
        if ($bookingType === 'tentative') {
            // Send tentative booking confirmation email
            $emailResult = sendTentativeBookingConfirmedEmail($bookingForEmail);
        } else {
            // Send standard booking received email
            $emailResult = sendBookingReceivedEmail($bookingForEmail);
        }
        
        // Send notification to admin
        $adminResult = sendAdminNotificationEmail($bookingForEmail);
        
        // Send WhatsApp notifications (to both guest and hotel)
        $bookingForWhatsApp = $bookingForEmail;
        $bookingForWhatsApp['room_name'] = $room['name'];
        
        if ($bookingType === 'tentative') {
            $whatsappResult = sendTentativeWhatsAppNotifications($bookingForWhatsApp, $room);
        } else {
            $whatsappResult = sendBookingWhatsAppNotifications($bookingForWhatsApp, $room);
        }
        
        // Fetch the created booking
        $fetchStmt = $pdo->prepare("
            SELECT 
                b.*,
                r.name as room_name,
                r.price_per_night,
                r.max_guests
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.id = ?
        ");
        $fetchStmt->execute([$bookingId]);
        $booking = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        // Format response
        $response = [
            'booking' => [
                'id' => (int)$booking['id'],
                'booking_reference' => $booking['booking_reference'],
                'status' => $booking['status'],
                'is_tentative' => (bool)$booking['is_tentative'],
                'room' => [
                    'id' => (int)$booking['room_id'],
                    'name' => $booking['room_name'],
                    'price_per_night' => (float)$booking['price_per_night'],
                    'max_guests' => (int)$booking['max_guests']
                ],
                'guest' => [
                    'name' => $booking['guest_name'],
                    'email' => $booking['guest_email'],
                    'phone' => $booking['guest_phone'],
                    'country' => $booking['guest_country'],
                    'address' => $booking['guest_address']
                ],
                'dates' => [
                    'check_in' => $booking['check_in_date'],
                    'check_out' => $booking['check_out_date'],
                    'nights' => (int)$booking['number_of_nights']
                ],
                'details' => [
                    'number_of_guests' => (int)$booking['number_of_guests'],
                    'adult_guests' => (int)($booking['adult_guests'] ?? $adultGuests),
                    'child_guests' => (int)($booking['child_guests'] ?? $bookingData['child_guests']),
                    'occupancy_type' => $booking['occupancy_type'] ?? $bookingData['occupancy_type'],
                ],
                'pricing' => [
                    'base_amount' => (float)($booking['total_amount'] - ($booking['child_supplement_total'] ?? 0)),
                    'child_supplement_total' => (float)($booking['child_supplement_total'] ?? $childSupplementTotal),
                    'child_price_multiplier' => (float)($booking['child_price_multiplier'] ?? $childPriceMultiplier),
                    'total_amount' => (float)$booking['total_amount'],
                    'currency' => getSetting('currency_symbol'),
                    'currency_code' => getSetting('currency_code')
                ],
                'special_requests' => $booking['special_requests'],
                'created_at' => $booking['created_at']
            ],
            'notifications' => [
                'guest_email_sent' => $emailResult['success'],
                'admin_email_sent' => $adminResult['success'],
                'guest_email_message' => $emailResult['message'],
                'admin_email_message' => $adminResult['message'],
                'whatsapp_guest_sent' => $whatsappResult['guest']['success'] ?? false,
                'whatsapp_hotel_sent' => $whatsappResult['hotel']['success'] ?? false,
                'whatsapp_guest_message' => $whatsappResult['guest']['message'] ?? '',
                'whatsapp_hotel_message' => $whatsappResult['hotel']['message'] ?? ''
            ],
            'next_steps' => []
        ];
        
        // Add tentative booking specific information
        if ($bookingType === 'tentative' && $tentativeExpiresAt) {
            $response['booking']['tentative_expires_at'] = $tentativeExpiresAt;
            $response['next_steps'] = [
                'booking_status' => 'Your room has been placed on tentative hold. You will receive a confirmation email with expiration details.',
                'email_notification' => $emailResult['success']
                    ? 'A tentative booking confirmation email has been sent to ' . $booking['guest_email']
                    : 'Email notification pending - System will send confirmation once email service is configured',
                'expiration' => 'This tentative booking will expire on ' . date('F j, Y \a\t g:i A', strtotime($tentativeExpiresAt)) . '. Please confirm your booking before this time.',
                'reminder' => 'You will receive a reminder email 24 hours before expiration.',
                'confirmation' => 'Your booking reference is ' . $bookingReference . '. Use this reference when confirming your booking.',
                'contact' => 'To confirm your booking, please contact us at ' . getSetting('email_reservations') . ' or call us.',
                'payment' => 'Payment will be required when you confirm your tentative booking.'
            ];
        } else {
            $response['next_steps'] = [
                'booking_status' => 'Your booking has been created successfully and is now in the system.',
                'email_notification' => $emailResult['success']
                    ? 'A confirmation email has been sent to ' . $booking['guest_email']
                    : 'Email notification pending - System will send confirmation once email service is configured',
                'payment' => getSetting('payment_policy'),
                'confirmation' => 'Your booking reference is ' . $bookingReference . '. Keep this reference for check-in.',
                'contact' => 'If you have any questions, please contact us at ' . getSetting('email_reservations')
            ];
        }
        
        ApiResponse::success($response, 'Booking created successfully', 201);
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Booking API Database Error: " . $e->getMessage());
    ApiResponse::error('Database error occurred while creating booking', 500);
} catch (Exception $e) {
    error_log("Booking API Error: " . $e->getMessage());
    ApiResponse::error('Failed to create booking: ' . $e->getMessage(), 500);
}
