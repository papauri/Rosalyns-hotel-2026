-- WhatsApp Notification Settings Migration
-- Run this migration to add WhatsApp notification support
-- Date: 2026-02-19

-- Insert WhatsApp settings into site_settings table
-- These settings control the WhatsApp notification feature

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
-- Feature toggle
('whatsapp_notifications_enabled', '0', 'boolean', 'Enable/disable WhatsApp notifications globally', 0),

-- Provider configuration
('whatsapp_provider', 'callmebot', 'string', 'WhatsApp provider: callmebot, twilio, or meta', 0),
('whatsapp_hotel_number', '+353860081635', 'string', 'Hotel WhatsApp number for notifications (E.164 format)', 0),

-- CallMeBot settings (free option)
('whatsapp_callmebot_api_key', '', 'string', 'CallMeBot API key for WhatsApp', 0),

-- Twilio settings
('whatsapp_twilio_account_sid', '', 'string', 'Twilio Account SID', 0),
('whatsapp_twilio_auth_token', '', 'string', 'Twilio Auth Token', 0),
('whatsapp_twilio_from_number', '', 'string', 'Twilio WhatsApp from number', 0),

-- Meta Cloud API settings
('whatsapp_meta_access_token', '', 'string', 'Meta WhatsApp Cloud API access token', 0),
('whatsapp_meta_phone_number_id', '', 'string', 'Meta WhatsApp phone number ID', 0),
('whatsapp_meta_business_account_id', '', 'string', 'Meta WhatsApp business account ID', 0),

-- Logging
('whatsapp_log_enabled', '1', 'boolean', 'Enable WhatsApp message logging', 0),

-- Guest notification templates
('whatsapp_tpl_booking_received', '🏨 *{{hotel_name}}*\n\n✅ *New Booking Received!*\n\nHello {{guest_name}}, thank you for choosing us!\n\n📋 *Booking Details*\nReference: *{{booking_reference}}*\nRoom: {{room_name}}\nCheck-in: {{check_in_date}} at {{check_in_time}}\nCheck-out: {{check_out_date}} at {{check_out_time}}\nNights: {{nights}}\nGuests: {{guests}} (Adults: {{adults}}, Children: {{children}})\nTotal: *{{total_amount}}*\n\nSpecial Requests: {{special_requests}}\n\nOur team will review and confirm your booking shortly.\n📞 {{hotel_phone}}', 'text', 'WhatsApp template for booking received notification', 0),

('whatsapp_tpl_booking_confirmed', '🏨 *{{hotel_name}}*\n\n🎉 *Booking CONFIRMED!*\n\nDear {{guest_name}},\nYour reservation has been confirmed!\n\n📋 *Confirmed Booking*\nReference: *{{booking_reference}}*\nRoom: {{room_name}}\nCheck-in: {{check_in_date}} at {{check_in_time}}\nCheck-out: {{check_out_date}} at {{check_out_time}}\nNights: {{nights}} | Guests: {{guests}}\nTotal: *{{total_amount}}*\n\nWe look forward to welcoming you!\n📞 {{hotel_phone}}', 'text', 'WhatsApp template for booking confirmed notification', 0),

('whatsapp_tpl_booking_cancelled', '🏨 *{{hotel_name}}*\n\n❌ *Booking Cancelled*\n\nDear {{guest_name}},\nYour booking *{{booking_reference}}* has been cancelled.\n\nCheck-in: {{check_in_date}}\nCheck-out: {{check_out_date}}\nRoom: {{room_name}}\n\nIf this was a mistake, please contact us:\n📞 {{hotel_phone}}', 'text', 'WhatsApp template for booking cancelled notification', 0),

('whatsapp_tpl_tentative_created', '🏨 *{{hotel_name}}*\n\n⏳ *Tentative Booking Placed*\n\nDear {{guest_name}},\nYour room has been placed on tentative hold.\n\n📋 *Details*\nReference: *{{booking_reference}}*\nRoom: {{room_name}}\nCheck-in: {{check_in_date}}\nCheck-out: {{check_out_date}}\nTotal: *{{total_amount}}*\n\n⚠️ Please confirm within the hold period.\nReply to this message or call: 📞 {{hotel_phone}}', 'text', 'WhatsApp template for tentative booking created', 0),

('whatsapp_tpl_checkin_reminder', '🏨 *{{hotel_name}}*\n\n🔔 *Check-in Reminder*\n\nDear {{guest_name}},\nYour stay begins tomorrow!\n\nReference: *{{booking_reference}}*\nCheck-in: {{check_in_date}} at {{check_in_time}}\nRoom: {{room_name}}\n\nWe look forward to seeing you!\n📞 {{hotel_phone}}', 'text', 'WhatsApp template for check-in reminder', 0),

-- Admin notification templates
('whatsapp_tpl_admin_new_booking', '🔔 *NEW BOOKING ALERT*\n━━━━━━━━━━━━━━━━━━━\nHotel: {{hotel_name}}\nRef: *{{booking_reference}}*\nGuest: {{guest_name}}\nPhone: {{guest_phone}}\nRoom: {{room_name}}\nIn: {{check_in_date}} | Out: {{check_out_date}}\nNights: {{nights}} | Guests: {{guests}}\n💰 Total: *{{total_amount}}*\nSpecial: {{special_requests}}', 'text', 'WhatsApp template for admin new booking alert', 0),

('whatsapp_tpl_admin_booking_confirmed', '✅ *BOOKING CONFIRMED*\n━━━━━━━━━━━━━━━━━━━\nRef: *{{booking_reference}}*\nGuest: {{guest_name}} | {{guest_phone}}\nRoom: {{room_name}}\nIn: {{check_in_date}} | Out: {{check_out_date}}\n💰 *{{total_amount}}*', 'text', 'WhatsApp template for admin booking confirmed', 0),

('whatsapp_tpl_admin_booking_cancelled', '❌ *BOOKING CANCELLED*\n━━━━━━━━━━━━━━━━━━━\nRef: *{{booking_reference}}*\nGuest: {{guest_name}} | {{guest_phone}}\nRoom: {{room_name}}\nWas: {{check_in_date}} → {{check_out_date}}\n💰 *{{total_amount}}*', 'text', 'WhatsApp template for admin booking cancelled', 0)

ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- Create WhatsApp log table for detailed tracking (optional but recommended)
CREATE TABLE IF NOT EXISTS `whatsapp_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT UNSIGNED NULL,
    `phone_to` VARCHAR(30) NOT NULL,
    `message_type` VARCHAR(50) NOT NULL COMMENT 'booking_received, booking_confirmed, etc.',
    `provider` VARCHAR(20) NOT NULL COMMENT 'callmebot, twilio, meta',
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `response_body` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL,
    INDEX `idx_booking_id` (`booking_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log of WhatsApp notifications sent';