<?php
/**
 * WhatsApp Notification Functions for Hotel Booking System
 *
 * Supports multiple providers:
 *   - Meta WhatsApp Business Cloud API
 *   - Twilio WhatsApp API
 *   - CallMeBot (simple, free)
 *
 * All settings stored in site_settings table.
 * Feature can be toggled on/off by admin.
 */

if (!defined('WHATSAPP_FUNCTIONS_LOADED')) {
    define('WHATSAPP_FUNCTIONS_LOADED', true);
}

// ============================================================
// SETTINGS HELPERS
// ============================================================

/**
 * Check if WhatsApp notifications are enabled
 */
function isWhatsAppEnabled(): bool {
    return getSetting('whatsapp_notifications_enabled', '0') === '1';
}

/**
 * Get the configured WhatsApp provider
 */
function getWhatsAppProvider(): string {
    return getSetting('whatsapp_provider', 'callmebot');
}

/**
 * Get the hotel's WhatsApp number (E.164 format)
 */
function getHotelWhatsAppNumber(): string {
    return getSetting('whatsapp_hotel_number', getSetting('whatsapp_number', ''));
}

// ============================================================
// CORE SENDER
// ============================================================

/**
 * Send a WhatsApp message to a given phone number.
 *
 * @param string $to   Phone number in E.164 format (+353860081635)
 * @param string $body Plain text message body (max ~4096 chars)
 * @return array ['success'=>bool, 'message'=>string]
 */
function sendWhatsAppMessage(string $to, string $body): array {
    if (!isWhatsAppEnabled()) {
        return ['success' => false, 'message' => 'WhatsApp notifications are disabled'];
    }

    // Normalize number (ensure + prefix)
    $to = normaliseWhatsAppNumber($to);
    if (empty($to)) {
        return ['success' => false, 'message' => 'Invalid WhatsApp number'];
    }

    $provider = getWhatsAppProvider();

    switch ($provider) {
        case 'twilio':
            return sendWhatsAppViaTwilio($to, $body);
        case 'meta':
            return sendWhatsAppViaMeta($to, $body);
        case 'callmebot':
        default:
            return sendWhatsAppViaCallMeBot($to, $body);
    }
}

/**
 * Normalise a phone number to E.164 format
 */
function normaliseWhatsAppNumber(string $number): string {
    $number = trim($number);
    if (empty($number)) return '';
    // Keep only digits and leading +
    $number = preg_replace('/[^0-9+]/', '', $number);
    // Ensure leading +
    if ($number[0] !== '+') {
        $number = '+' . $number;
    }
    // Must be at least 8 digits
    if (strlen(preg_replace('/[^0-9]/', '', $number)) < 8) {
        return '';
    }
    return $number;
}

// ============================================================
// PROVIDER: CALLMEBOT (Free, simple, no registration for numbers
//            that have already sent /start to CallMeBot)
// ============================================================

function sendWhatsAppViaCallMeBot(string $to, string $body): array {
    $apiKey = getSetting('whatsapp_callmebot_api_key', '');
    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'CallMeBot API key not configured'];
    }

    $phone = ltrim($to, '+');
    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone'  => $phone,
        'text'   => $body,
        'apikey' => $apiKey,
    ]);

    $result = whatsAppHttpGet($url);
    $success = $result['code'] === 200 && stripos($result['body'], 'Message queued') !== false;

    logWhatsApp($to, 'callmebot', $success, $result['body']);

    return [
        'success' => $success,
        'message' => $success ? 'WhatsApp message sent via CallMeBot' : 'CallMeBot error: ' . $result['body'],
    ];
}

// ============================================================
// PROVIDER: TWILIO
// ============================================================

function sendWhatsAppViaTwilio(string $to, string $body): array {
    $accountSid = getSetting('whatsapp_twilio_account_sid', '');
    $authToken  = getSetting('whatsapp_twilio_auth_token', '');
    $from       = getSetting('whatsapp_twilio_from_number', '');

    if (empty($accountSid) || empty($authToken) || empty($from)) {
        return ['success' => false, 'message' => 'Twilio credentials not configured'];
    }

    $url  = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
    $data = [
        'From' => 'whatsapp:' . $from,
        'To'   => 'whatsapp:' . $to,
        'Body' => $body,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body_resp = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($body_resp, true);
    $success = $httpCode >= 200 && $httpCode < 300 && !empty($decoded['sid']);

    logWhatsApp($to, 'twilio', $success, $body_resp);

    return [
        'success' => $success,
        'message' => $success ? 'WhatsApp sent via Twilio (SID: ' . ($decoded['sid'] ?? '') . ')' : 'Twilio error: ' . ($decoded['message'] ?? $body_resp),
    ];
}

// ============================================================
// PROVIDER: META CLOUD API
// ============================================================

function sendWhatsAppViaMeta(string $to, string $body): array {
    $accessToken = getSetting('whatsapp_meta_access_token', '');
    $phoneNumberId = getSetting('whatsapp_meta_phone_number_id', '');

    if (empty($accessToken) || empty($phoneNumberId)) {
        return ['success' => false, 'message' => 'Meta WhatsApp credentials not configured'];
    }

    $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to'                => ltrim($to, '+'),
        'type'              => 'text',
        'text'              => ['body' => $body],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer {$accessToken}",
        ],
    ]);
    $body_resp = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($body_resp, true);
    $success = $httpCode === 200 && !empty($decoded['messages'][0]['id']);

    logWhatsApp($to, 'meta', $success, $body_resp);

    return [
        'success' => $success,
        'message' => $success ? 'WhatsApp sent via Meta Cloud API' : 'Meta error: ' . ($decoded['error']['message'] ?? $body_resp),
    ];
}

// ============================================================
// HELPER: HTTP GET
// ============================================================

function whatsAppHttpGet(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'HotelBookingSystem/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || !empty($err)) {
        return ['code' => 0, 'body' => 'cURL error: ' . $err];
    }
    return ['code' => $code, 'body' => $body];
}

// ============================================================
// LOGGING
// ============================================================

function logWhatsApp(string $to, string $provider, bool $success, string $response = ''): void {
    $logEnabled = getSetting('whatsapp_log_enabled', '1') === '1';
    if (!$logEnabled) return;

    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) mkdir($logDir, 0755, true);

    $status = $success ? 'SENT' : 'FAILED';
    $line = "[" . date('Y-m-d H:i:s') . "] [WhatsApp:{$provider}] [{$status}] To: {$to}";
    if (!$success) $line .= " | Response: " . substr($response, 0, 200);
    $line .= "\n";

    file_put_contents($logDir . '/whatsapp-log.txt', $line, FILE_APPEND | LOCK_EX);
}

// ============================================================
// TEMPLATE ENGINE
// ============================================================

/**
 * Get a WhatsApp message template from site_settings
 * with variable substitution.
 *
 * @param string $templateKey  e.g. 'booking_received', 'booking_confirmed'
 * @param array  $vars         Key=>value replacements for {{key}} placeholders
 * @return string  Rendered message text
 */
function renderWhatsAppTemplate(string $templateKey, array $vars): string {
    $settingKey = 'whatsapp_tpl_' . $templateKey;
    $template   = getSetting($settingKey, getDefaultWhatsAppTemplate($templateKey));

    foreach ($vars as $k => $v) {
        $template = str_replace('{{' . $k . '}}', (string)$v, $template);
    }

    return $template;
}

/**
 * Build standard booking variables for templates
 */
function buildWhatsAppBookingVars(array $booking, array $room = []): array {
    $siteName      = getSetting('site_name', 'Rosalyns Beach Hotel');
    $currency      = getSetting('currency_symbol', 'MWK');
    $checkInTime   = getSetting('check_in_time', '2:00 PM');
    $checkOutTime  = getSetting('check_out_time', '11:00 AM');
    $phoneMain     = getSetting('phone_main', '');
    $hotelWa       = getHotelWhatsAppNumber();

    return [
        'hotel_name'           => $siteName,
        'booking_reference'    => $booking['booking_reference'] ?? '',
        'guest_name'           => $booking['guest_name'] ?? '',
        'guest_phone'          => $booking['guest_phone'] ?? '',
        'room_name'            => $room['name'] ?? ($booking['room_name'] ?? 'Room'),
        'check_in_date'        => !empty($booking['check_in_date']) ? date('D, d M Y', strtotime($booking['check_in_date'])) : '',
        'check_out_date'       => !empty($booking['check_out_date']) ? date('D, d M Y', strtotime($booking['check_out_date'])) : '',
        'nights'               => (string)($booking['number_of_nights'] ?? 1),
        'guests'               => (string)($booking['number_of_guests'] ?? 1),
        'adults'               => (string)($booking['adult_guests'] ?? $booking['number_of_guests'] ?? 1),
        'children'             => (string)($booking['child_guests'] ?? 0),
        'total_amount'         => $currency . ' ' . number_format((float)($booking['total_amount'] ?? 0), 0),
        'check_in_time'        => $checkInTime,
        'check_out_time'       => $checkOutTime,
        'special_requests'     => !empty($booking['special_requests']) ? $booking['special_requests'] : 'None',
        'hotel_phone'          => $phoneMain,
        'hotel_whatsapp'       => $hotelWa,
        'occupancy_type'       => ucfirst($booking['occupancy_type'] ?? 'double'),
        'status'               => ucfirst($booking['status'] ?? 'pending'),
    ];
}

/**
 * Default WhatsApp message templates
 */
function getDefaultWhatsAppTemplate(string $key): string {
    $siteName = getSetting('site_name', 'Rosalyns Beach Hotel');
    $templates = [
        'booking_received' =>
            "🏨 *{{hotel_name}}*\n\n" .
            "✅ *New Booking Received!*\n\n" .
            "Hello {{guest_name}}, thank you for choosing us!\n\n" .
            "📋 *Booking Details*\n" .
            "Reference: *{{booking_reference}}*\n" .
            "Room: {{room_name}}\n" .
            "Check-in: {{check_in_date}} at {{check_in_time}}\n" .
            "Check-out: {{check_out_date}} at {{check_out_time}}\n" .
            "Nights: {{nights}}\n" .
            "Guests: {{guests}} (Adults: {{adults}}, Children: {{children}})\n" .
            "Total: *{{total_amount}}*\n\n" .
            "Special Requests: {{special_requests}}\n\n" .
            "Our team will review and confirm your booking shortly.\n" .
            "📞 {{hotel_phone}}",

        'booking_confirmed' =>
            "🏨 *{{hotel_name}}*\n\n" .
            "🎉 *Booking CONFIRMED!*\n\n" .
            "Dear {{guest_name}},\n" .
            "Your reservation has been confirmed!\n\n" .
            "📋 *Confirmed Booking*\n" .
            "Reference: *{{booking_reference}}*\n" .
            "Room: {{room_name}}\n" .
            "Check-in: {{check_in_date}} at {{check_in_time}}\n" .
            "Check-out: {{check_out_date}} at {{check_out_time}}\n" .
            "Nights: {{nights}} | Guests: {{guests}}\n" .
            "Total: *{{total_amount}}*\n\n" .
            "We look forward to welcoming you!\n" .
            "📞 {{hotel_phone}}",

        'booking_cancelled' =>
            "🏨 *{{hotel_name}}*\n\n" .
            "❌ *Booking Cancelled*\n\n" .
            "Dear {{guest_name}},\n" .
            "Your booking *{{booking_reference}}* has been cancelled.\n\n" .
            "Check-in: {{check_in_date}}\n" .
            "Check-out: {{check_out_date}}\n" .
            "Room: {{room_name}}\n\n" .
            "If this was a mistake, please contact us:\n" .
            "📞 {{hotel_phone}}",

        'tentative_created' =>
            "🏨 *{{hotel_name}}*\n\n" .
            "⏳ *Tentative Booking Placed*\n\n" .
            "Dear {{guest_name}},\n" .
            "Your room has been placed on tentative hold.\n\n" .
            "📋 *Details*\n" .
            "Reference: *{{booking_reference}}*\n" .
            "Room: {{room_name}}\n" .
            "Check-in: {{check_in_date}}\n" .
            "Check-out: {{check_out_date}}\n" .
            "Total: *{{total_amount}}*\n\n" .
            "⚠️ Please confirm within the hold period.\n" .
            "Reply to this message or call: 📞 {{hotel_phone}}",

        'checkin_reminder' =>
            "🏨 *{{hotel_name}}*\n\n" .
            "🔔 *Check-in Reminder*\n\n" .
            "Dear {{guest_name}},\n" .
            "Your stay begins tomorrow!\n\n" .
            "Reference: *{{booking_reference}}*\n" .
            "Check-in: {{check_in_date}} at {{check_in_time}}\n" .
            "Room: {{room_name}}\n\n" .
            "We look forward to seeing you!\n" .
            "📞 {{hotel_phone}}",

        // Admin notification templates
        'admin_new_booking' =>
            "🔔 *NEW BOOKING ALERT*\n" .
            "━━━━━━━━━━━━━━━━━━━\n" .
            "Hotel: {{hotel_name}}\n" .
            "Ref: *{{booking_reference}}*\n" .
            "Guest: {{guest_name}}\n" .
            "Phone: {{guest_phone}}\n" .
            "Room: {{room_name}}\n" .
            "In: {{check_in_date}} | Out: {{check_out_date}}\n" .
            "Nights: {{nights}} | Guests: {{guests}}\n" .
            "💰 Total: *{{total_amount}}*\n" .
            "Special: {{special_requests}}",

        'admin_booking_confirmed' =>
            "✅ *BOOKING CONFIRMED*\n" .
            "━━━━━━━━━━━━━━━━━━━\n" .
            "Ref: *{{booking_reference}}*\n" .
            "Guest: {{guest_name}} | {{guest_phone}}\n" .
            "Room: {{room_name}}\n" .
            "In: {{check_in_date}} | Out: {{check_out_date}}\n" .
            "💰 *{{total_amount}}*",

        'admin_booking_cancelled' =>
            "❌ *BOOKING CANCELLED*\n" .
            "━━━━━━━━━━━━━━━━━━━\n" .
            "Ref: *{{booking_reference}}*\n" .
            "Guest: {{guest_name}} | {{guest_phone}}\n" .
            "Room: {{room_name}}\n" .
            "Was: {{check_in_date}} → {{check_out_date}}\n" .
            "💰 *{{total_amount}}*",
    ];

    return $templates[$key] ?? "{{hotel_name}}: Booking {{booking_reference}} update.";
}

// ============================================================
// HIGH-LEVEL BOOKING NOTIFICATION FUNCTIONS
// ============================================================

/**
 * Send WhatsApp notifications for a new standard booking
 * - to guest (if guest_phone is set)
 * - to hotel WhatsApp number
 */
function sendBookingWhatsAppNotifications(array $booking, array $room = []): array {
    if (!isWhatsAppEnabled()) {
        return ['guest' => ['success' => false, 'message' => 'WhatsApp disabled'],
                'hotel' => ['success' => false, 'message' => 'WhatsApp disabled']];
    }

    $vars = buildWhatsAppBookingVars($booking, $room);

    // Guest notification
    $guestResult = ['success' => false, 'message' => 'No guest phone'];
    $guestPhone  = normaliseWhatsAppNumber($booking['guest_phone'] ?? '');
    if (!empty($guestPhone)) {
        $guestMsg   = renderWhatsAppTemplate('booking_received', $vars);
        $guestResult = sendWhatsAppMessage($guestPhone, $guestMsg);
    }

    // Hotel notification
    $hotelResult = ['success' => false, 'message' => 'No hotel WhatsApp number'];
    $hotelPhone  = normaliseWhatsAppNumber(getHotelWhatsAppNumber());
    if (!empty($hotelPhone)) {
        $adminMsg   = renderWhatsAppTemplate('admin_new_booking', $vars);
        $hotelResult = sendWhatsAppMessage($hotelPhone, $adminMsg);
    }

    return ['guest' => $guestResult, 'hotel' => $hotelResult];
}

/**
 * Send WhatsApp notifications when booking is confirmed by admin
 */
function sendBookingConfirmedWhatsApp(array $booking, array $room = []): array {
    if (!isWhatsAppEnabled()) {
        return ['guest' => ['success' => false, 'message' => 'WhatsApp disabled'],
                'hotel' => ['success' => false, 'message' => 'WhatsApp disabled']];
    }

    $vars = buildWhatsAppBookingVars($booking, $room);

    $guestResult = ['success' => false, 'message' => 'No guest phone'];
    $guestPhone  = normaliseWhatsAppNumber($booking['guest_phone'] ?? '');
    if (!empty($guestPhone)) {
        $msg = renderWhatsAppTemplate('booking_confirmed', $vars);
        $guestResult = sendWhatsAppMessage($guestPhone, $msg);
    }

    $hotelResult = ['success' => false, 'message' => 'No hotel WhatsApp'];
    $hotelPhone  = normaliseWhatsAppNumber(getHotelWhatsAppNumber());
    if (!empty($hotelPhone)) {
        $msg = renderWhatsAppTemplate('admin_booking_confirmed', $vars);
        $hotelResult = sendWhatsAppMessage($hotelPhone, $msg);
    }

    return ['guest' => $guestResult, 'hotel' => $hotelResult];
}

/**
 * Send WhatsApp notifications when booking is cancelled
 */
function sendBookingCancelledWhatsApp(array $booking, array $room = []): array {
    if (!isWhatsAppEnabled()) {
        return ['guest' => ['success' => false, 'message' => 'WhatsApp disabled'],
                'hotel' => ['success' => false, 'message' => 'WhatsApp disabled']];
    }

    $vars = buildWhatsAppBookingVars($booking, $room);

    $guestResult = ['success' => false, 'message' => 'No guest phone'];
    $guestPhone  = normaliseWhatsAppNumber($booking['guest_phone'] ?? '');
    if (!empty($guestPhone)) {
        $msg = renderWhatsAppTemplate('booking_cancelled', $vars);
        $guestResult = sendWhatsAppMessage($guestPhone, $msg);
    }

    $hotelResult = ['success' => false, 'message' => 'No hotel WhatsApp'];
    $hotelPhone  = normaliseWhatsAppNumber(getHotelWhatsAppNumber());
    if (!empty($hotelPhone)) {
        $msg = renderWhatsAppTemplate('admin_booking_cancelled', $vars);
        $hotelResult = sendWhatsAppMessage($hotelPhone, $msg);
    }

    return ['guest' => $guestResult, 'hotel' => $hotelResult];
}

/**
 * Send WhatsApp notifications for tentative booking created
 */
function sendTentativeWhatsAppNotifications(array $booking, array $room = []): array {
    if (!isWhatsAppEnabled()) {
        return ['guest' => ['success' => false, 'message' => 'WhatsApp disabled'],
                'hotel' => ['success' => false, 'message' => 'WhatsApp disabled']];
    }

    $vars = buildWhatsAppBookingVars($booking, $room);

    $guestResult = ['success' => false, 'message' => 'No guest phone'];
    $guestPhone  = normaliseWhatsAppNumber($booking['guest_phone'] ?? '');
    if (!empty($guestPhone)) {
        $msg = renderWhatsAppTemplate('tentative_created', $vars);
        $guestResult = sendWhatsAppMessage($guestPhone, $msg);
    }

    $hotelResult = ['success' => false, 'message' => 'No hotel WhatsApp'];
    $hotelPhone  = normaliseWhatsAppNumber(getHotelWhatsAppNumber());
    if (!empty($hotelPhone)) {
        $msg = renderWhatsAppTemplate('admin_new_booking', $vars);
        $hotelResult = sendWhatsAppMessage($hotelPhone, $msg);
    }

    return ['guest' => $guestResult, 'hotel' => $hotelResult];
}

/**
 * Send a test WhatsApp message (used from admin settings page)
 */
function sendWhatsAppTestMessage(string $to, string $message = ''): array {
    if (empty($message)) {
        $siteName = getSetting('site_name', 'Rosalyns Beach Hotel');
        $message  = "✅ *{$siteName}*\n\nWhatsApp test message successful!\nSent at: " . date('d M Y H:i:s');
    }
    return sendWhatsAppMessage($to, $message);
}
