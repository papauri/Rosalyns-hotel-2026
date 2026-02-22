# Booking System Migration Guide

## Overview

This booking system has been designed as a **modular, portable API component** that can be easily integrated into any hotel or accommodation website. All hotel-specific details (name, contact info, branding, etc.) are retrieved from the database, making this a completely generic template that can be customized for any property.

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Requirements](#requirements)
3. [Installation Steps](#installation-steps)
4. [Configuration](#configuration)
5. [Integration Methods](#integration-methods)
6. [Customization](#customization)
7. [API Reference](#api-reference)
8. [Troubleshooting](#troubleshooting)
9. [Support](#support)

---

## System Architecture

### Core Components

The booking system consists of these modular components:

```
booking-system/
├── includes/
│   └── booking-functions.php      # Main booking API functions
├── config/
│   ├── database.php               # Database connection
│   └── email.php                  # Email configuration
├── api/
│   ├── availability.php           # Availability checking API
│   ├── bookings.php               # Booking submission API
│   └── blocked-dates.php          # Blocked dates API
├── booking.php                    # Booking form page
├── booking-confirmation.php       # Booking confirmation page
└── check-availability.php         # Availability check endpoint
```

### Key Features

- ✅ **Enable/Disable Toggle**: Turn off booking system without removing code
- ✅ **Modular Design**: Use only the components you need
- ✅ **API-First**: Can be integrated via REST API
- ✅ **No Hardcoded Values**: All settings stored in database
- ✅ **Responsive**: Works on all devices
- ✅ **Multi-Language Ready**: Easy to translate
- ✅ **Secure**: CSRF protection, input validation, SQL injection prevention

---

## Requirements

### Server Requirements

- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.7 or higher / MariaDB 10.2+
- **Web Server**: Apache (with mod_rewrite) or Nginx
- **SSL Certificate**: Required for secure form submissions

### PHP Extensions

```php
// Required extensions:
- pdo_mysql
- mbstring
- json
- curl (for API calls)
- mail (for email notifications)
```

### Database Tables Required

The system requires these database tables (included in `Database/p601229_hotels.sql`):

- `rooms` - Room information
- `bookings` - Booking records
- `blocked_dates` - Date blocking
- `site_settings` - System settings
- `reviews` - Customer reviews
- `policies` - Hotel policies

---

## Installation Steps

### Step 1: Copy Core Files

Copy these files to your target website:

```bash
# Create booking system directory
mkdir -p your-website/includes
mkdir -p your-website/api
mkdir -p your-website/config

# Copy core files
cp includes/booking-functions.php your-website/includes/
cp config/database.php your-website/config/
cp config/email.php your-website/config/

# Copy API endpoints (if using REST API)
cp api/availability.php your-website/api/
cp api/bookings.php your-website/api/
cp api/blocked-dates.php your-website/api/
```

### Step 2: Import Database Schema

Import the booking system tables into your database:

```bash
mysql -u username -p database_name < Database/p601229_hotels.sql
```

Or use the migration file:

```bash
mysql -u username -p database_name < Database/migrations/add-booking-system-toggle.sql
```

### Step 3: Configure Database Connection

Edit `config/database.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}
?>
```

### Step 4: Configure Email Settings

Edit `config/email.php`:

```php
<?php
// Email Configuration
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'bookings@yourdomain.com');
define('SMTP_PASSWORD', 'your_email_password');
define('EMAIL_FROM_NAME', 'Your Hotel Name');
define('EMAIL_FROM_EMAIL', 'bookings@yourdomain.com');
define('EMAIL_ADMIN_EMAIL', 'admin@yourdomain.com');
?>
```

### Step 5: Add Room Data

Add your rooms to the database:

```sql
INSERT INTO rooms (name, slug, price_per_night, max_guests, rooms_available, 
                   short_description, description, is_active, display_order)
VALUES 
('Deluxe Room', 'deluxe-room', 150.00, 2, 5, 
 'Comfortable deluxe room with city views', 
 'Full room description...', 1, 1),
('Suite', 'suite', 250.00, 4, 3,
 'Luxurious suite with separate living area',
 'Full suite description...', 1, 2);
```

---

## Configuration

### Site Settings

Configure these settings in the `site_settings` table:

```sql
-- Booking system settings
INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
('booking_system_enabled', '1', 'booking'),
('booking_disabled_message', 'For booking inquiries, please contact us directly at [phone] or [email]', 'booking'),
('booking_disabled_action', 'message', 'booking'),
('max_advance_booking_days', '30', 'booking'),
('tentative_duration_hours', '48', 'booking'),
('currency_symbol', '$', 'general'),
('site_name', 'Your Hotel Name', 'general'),
('phone_main', '+1234567890', 'contact'),
('email_reservations', 'reservations@yourdomain.com', 'contact');
```

### Enable/Disable Booking System

You can toggle the booking system on/off without removing code:

```php
// Check if booking is enabled
if (isBookingEnabled()) {
    // Show booking button/widget
    renderBookingButton($room_id, $room_name, 'btn-primary');
} else {
    // Show disabled message
    echo renderBookingDisabledContent('button');
}
```

---

## How to Use the API on Another Website (Step‑by‑Step)

This section explains in simple terms how to connect any website (WordPress, Wix, custom HTML, etc.) to the hotel booking API.

### What You Need
1. **API Base URL** – The web address where your API is hosted (e.g., `https://your‑hotel‑domain.com`).
2. **API Key** – A secret key that identifies your website (like a password). You can generate one in the admin area or using the provided script.
3. **A few lines of JavaScript** – To send requests and handle responses.

### Step 1: Get Your API Key
- If you already have an API key (like the test key `00ac5aef5f653fea16dcb17669c61705`), note it down.
- To generate a new key, run the script `generate‑api‑key.php` on your server or ask your developer to create one in the `api_keys` database table.

### Step 2: Test the API
Use the ready‑made HTML test page (`api_key/test‑api‑key.html`) to verify everything works:
1. Upload the `api_key` folder to your server (or keep it locally).
2. Open `http://your‑server/api_key/test‑api‑key.html` in a browser.
3. Enter the **API Base URL** (the root of your API) and your **API Key**.
4. Click **Check Availability** – you should see a green success box.
5. Click **Create Booking** – you may see a database error (that’s okay; it means the API is reachable).

### Step 3: Add the API to Your Website
Copy and paste this JavaScript snippet into your website’s HTML, replacing the placeholder values with your own:

```html
<script>
const apiBaseUrl = 'https://your‑hotel‑domain.com';   // Change this
const apiKey = 'your‑api‑key‑here';                  // Change this

// Function to check room availability
async function checkAvailability(roomId, checkIn, checkOut, guests) {
    const response = await fetch(`${apiBaseUrl}/api/availability`, {
        method: 'GET',
        headers: {
            'X‑API‑Key': apiKey,
            'Accept': 'application/json'
        }
    });
    const data = await response.json();
    return data;
}

// Function to create a booking
async function createBooking(bookingData) {
    const response = await fetch(`${apiBaseUrl}/api/bookings`, {
        method: 'POST',
        headers: {
            'X‑API‑Key': apiKey,
            'Content‑Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(bookingData)
    });
    const result = await response.json();
    return result;
}

// Example usage (call these from your own buttons/forms)
// checkAvailability(2, '2026‑02‑20', '2026‑02‑22', 2).then(console.log);
</script>
```

### Step 4: Build Your Own Form
Create HTML input fields for:
- Room selection
- Check‑in / check‑out dates
- Number of guests
- Guest details (name, email, phone)

When the user submits the form, call `createBooking()` with the collected data.

### Step 5: Handle the Response
- If the booking succeeds, show a confirmation message with the booking reference.
- If it fails, display the error message from the API (e.g., “Room not available” or “Invalid dates”).

### Need More Help?
- Use the **test HTML page** as a working reference.
- Check the **API Reference** section below for detailed endpoint specifications.
- Look at the browser’s Developer Tools (F12) to see what requests are being sent.

## Integration Methods

### Method 1: Direct PHP Integration (Recommended)

For PHP-based websites, include the booking functions directly:

```php
<?php
// At the top of your page
require_once 'includes/booking-functions.php';

// Check if booking is enabled
if (isBookingEnabled()) {
    // Display booking button
    renderBookingButton($room_id, $room_name, 'your-button-class');
} else {
    // Display disabled message
    echo renderBookingDisabledContent('button');
}
?>
```

**Add Booking Widget:**

```php
<?php
// Include booking widget
include 'includes/booking-widget.php';
?>
```

**Add Booking Page:**

```php
<?php
require_once 'includes/booking-functions.php';

// Check if booking system is enabled
requireBookingEnabled();

// Rest of your booking page code...
include 'booking.php';
?>
```

### Method 2: REST API Integration

For non-PHP websites (React, Vue, Angular, etc.), use the REST API:

**Check Availability:**

```javascript
// Fetch availability (GET request with query parameters)
const apiBaseUrl = 'https://yourdomain.com';
const apiKey = 'your-api-key-here';

const response = await fetch(`${apiBaseUrl}/api/availability?room_id=1&check_in=2026-02-15&check_out=2026-02-18&number_of_guests=2`, {
    method: 'GET',
    headers: {
        'X-API-Key': apiKey,
        'Accept': 'application/json'
    }
});

const data = await response.json();
if (data.available) {
    // Show booking form
} else {
    // Show unavailable message
}
```

**Submit Booking:**

```javascript
// Submit booking (POST with API key header)
const apiBaseUrl = 'https://yourdomain.com';
const apiKey = 'your-api-key-here';

const response = await fetch(`${apiBaseUrl}/api/bookings`, {
    method: 'POST',
    headers: {
        'X-API-Key': apiKey,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        room_id: 1,
        guest_name: 'John Doe',
        guest_email: 'john@example.com',
        guest_phone: '+1234567890',
        check_in_date: '2026-02-15',
        check_out_date: '2026-02-18',
        number_of_guests: 2,
        special_requests: 'Late check-in requested'
    })
});

const result = await response.json();
if (result.success) {
    // Show confirmation
    console.log('Booking Reference:', result.booking_reference);
}
```

### Method 3: Iframe Integration

For simple integration without code changes:

```html
<iframe 
    src="https://yourdomain.com/booking.php?room_id=1" 
    width="100%" 
    height="800" 
    frameborder="0"
    style="border: none; border-radius: 12px;">
</iframe>
```

---

## Customization

### Styling

The booking system uses CSS variables for easy theming:

```css
:root {
    --navy: #1A1A1A;
    --gold: #8B7355;
    --gold-light: #c49b2e;
    --white: #ffffff;
    --gray-light: #f5f5f5;
    --gray-medium: #999999;
    --gray-dark: #333333;
}
```

Override these in your stylesheet to match your brand.

### Custom Messages

Customize the disabled booking message:

```php
updateSetting('booking_disabled_message', 
    'For bookings, please call our 24/7 reservations line at [phone] or email us at [email]');
```

### Redirect URL

Set a custom redirect when booking is disabled:

```php
updateSetting('booking_disabled_redirect_url', 'https://external-booking-site.com');
updateSetting('booking_disabled_action', 'redirect');
```

---

## API Reference

### Check Availability

**Endpoint:** `GET /api/availability`

**Authentication:** API Key required in `X-API-Key` header.

**Parameters (query string):**
- `room_id` (required): Room ID to check
- `check_in` (required): Check-in date (YYYY-MM-DD)
- `check_out` (required): Check-out date (YYYY-MM-DD)
- `number_of_guests` (optional): Number of guests

**Example Request:**
```bash
curl -X GET "https://yourdomain.com/api/availability?room_id=1&check_in=2026-02-15&check_out=2026-02-18&number_of_guests=2" \
  -H "X-API-Key: your-api-key-here" \
  -H "Accept: application/json"
```

**Response:**
```json
{
    "success": true,
    "available": true,
    "room": {
        "id": 1,
        "name": "Deluxe Room",
        "price_per_night": 150.00
    },
    "nights": 3,
    "total_amount": 450.00
}
```

**Note:** The currency symbol and pricing format are pulled from the `site_settings` table (`currency_symbol`, `currency_code`).

### Submit Booking

**Endpoint:** `POST /api/bookings`

**Authentication:** API Key required in `X-API-Key` header.

**Request Body (JSON):**
```json
{
    "room_id": 1,
    "guest_name": "John Doe",
    "guest_email": "john@example.com",
    "guest_phone": "+1234567890",
    "check_in_date": "2026-02-15",
    "check_out_date": "2026-02-18",
    "number_of_guests": 2,
    "special_requests": "Late check-in",
    "booking_type": "standard",
    "occupancy_type": "double"
}
```

**Example Request:**
```bash
curl -X POST "https://yourdomain.com/api/bookings" \
  -H "X-API-Key: your-api-key-here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "room_id": 1,
    "guest_name": "John Doe",
    "guest_email": "john@example.com",
    "guest_phone": "+1234567890",
    "check_in_date": "2026-02-15",
    "check_out_date": "2026-02-18",
    "number_of_guests": 2,
    "special_requests": "Late check-in",
    "booking_type": "standard",
    "occupancy_type": "double"
  }'
```

**Response:**
```json
{
    "success": true,
    "booking_reference": "LSH20261234",
    "message": "Booking confirmed! Check your email for details."
}
```

### Get Blocked Dates

**Endpoint:** `GET /api/blocked-dates.php?room_id=1`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "block_date": "2026-02-15",
            "reason": "Maintenance"
        }
    ]
}
```

---

## Troubleshooting

### Booking buttons not appearing

**Problem:** Booking buttons don't show on your pages.

**Solution:**
1. Check if booking system is enabled:
   ```php
   var_dump(isBookingEnabled()); // Should return true
   ```
2. Verify `booking-functions.php` is included
3. Check database connection
4. Ensure `booking_system_enabled` setting is set to '1'

### Emails not sending

**Problem:** Booking confirmation emails aren't sent.

**Solution:**
1. Check SMTP settings in `config/email.php`
2. Verify SMTP credentials are correct
3. Check if port 465/587 is open on your server
4. Review email logs in `logs/email-log.txt`
5. Test email configuration:
   ```php
   $test = sendTestEmail();
   var_dump($test);
   ```

### Database connection errors

**Problem:** "Could not connect to database" error.

**Solution:**
1. Verify database credentials in `config/database.php`
2. Check if database server is running
3. Ensure database user has proper permissions
4. Test connection manually:
   ```bash
   mysql -h your_host -u your_user -p your_database
   ```

### Booking system showing as disabled

**Problem:** Booking disabled message appears even when enabled.

**Solution:**
1. Check `site_settings` table for `booking_system_enabled` value
2. Clear cache:
   ```php
   deleteCache("setting_booking_system_enabled");
   ```
3. Verify settings are loaded correctly

---

## Support

### Documentation

- Full API documentation: [Link to docs]
- Video tutorials: [Link to videos]
- Live demos: [Link to demo]

### Getting Help

- GitHub Issues: [Report bugs in your repository]
- Email Support: [Your support email]
- Community Forum: [Your community forum link]

### Premium Services

- Custom integrations
- White-glove installation
- Custom feature development
- Priority support

---

## License

This booking system is a modular template designed for hotel and accommodation websites.

- **Personal Use**: Free
- **Commercial Use**: Free to use and modify
- **Redistribution**: You may redistribute modified versions with attribution

## Database-Driven Configuration

All hotel-specific information is stored in the `site_settings` table and retrieved dynamically:

### Essential Settings to Configure

```sql
-- Hotel/Property Information
UPDATE site_settings SET setting_value = 'Your Hotel Name' WHERE setting_key = 'site_name';
UPDATE site_settings SET setting_value = 'Your Tagline Here' WHERE setting_key = 'site_tagline';
UPDATE site_settings SET setting_value = 'https://yourdomain.com' WHERE setting_key = 'base_url';

-- Contact Information
UPDATE site_settings SET setting_value = '+1234567890' WHERE setting_key = 'phone_main';
UPDATE site_settings SET setting_value = 'reservations@yourdomain.com' WHERE setting_key = 'email_reservations';
UPDATE site_settings SET setting_value = 'Your Address Line 1' WHERE setting_key = 'address_line1';
UPDATE site_settings SET setting_value = 'Your City' WHERE setting_key = 'address_line2';
UPDATE site_settings SET setting_value = 'Your Country' WHERE setting_key = 'address_country';

-- Currency & Pricing
UPDATE site_settings SET setting_value = '$' WHERE setting_key = 'currency_symbol';
UPDATE site_settings SET setting_value = 'USD' WHERE setting_key = 'currency_code';

-- Booking Settings
UPDATE site_settings SET setting_value = '1' WHERE setting_key = 'booking_system_enabled';
UPDATE site_settings SET setting_value = '30' WHERE setting_key = 'max_advance_booking_days';
```

### Customizing Email Templates

Email templates use the `[site_name]` placeholder which is automatically replaced with your hotel name:

```php
// In email templates
$subject = "Booking Confirmation - " . getSetting('site_name');
```

### Branding & Theme

The system uses CSS variables that you can override in `css/style.css` or your custom stylesheet:

```css
:root {
    --primary-color: #your-color;
    --secondary-color: #your-color;
    /* Override any variables to match your brand */
}
```

---

## Changelog

### Version 1.0.0 (2026-02-12)
- Initial release
- Enable/disable toggle feature
- Modular booking functions
- REST API endpoints
- Comprehensive documentation

---

## Credits

This is a generic hotel booking system template.  
Architecture & Development: Cline AI Assistant  
Database Design: Based on industry best practices  
UI/UX: Inspired by luxury hotel websites worldwide

**Version:** 1.0.0 - Generic Template for Any Hotel/Accommodation Website

---

**Last Updated:** February 12, 2026  
**Version:** 1.0.0