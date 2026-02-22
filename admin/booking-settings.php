<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';
require_once __DIR__ . '/../includes/booking-functions.php';

$user = [
    'id' => $_SESSION['admin_user_id'],
    'username' => $_SESSION['admin_username'],
    'role' => $_SESSION['admin_role'],
    'full_name' => $_SESSION['admin_full_name']
];
$message = '';
$error = '';
$template_preview = null;

// Handle enable/disable via GET parameter
if (isset($_GET['enable'])) {
    updateSetting('booking_system_enabled', '1');
    header('Location: ' . BASE_URL . 'admin/booking-settings.php');
    exit;
}

// Handle one-switch booking system toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_booking_system'])) {
    $enabled = isset($_POST['booking_system_enabled']) ? '1' : '0';
    updateSetting('booking_system_enabled', $enabled);
    $message = $enabled === '1'
        ? 'Booking system enabled successfully!'
        : 'Booking system disabled successfully!';
}

// Handle disable via POST
if (isset($_POST['disable_booking'])) {
    updateSetting('booking_system_enabled', '0');
    $message = "Booking system disabled successfully!";
}

// Handle disabled mode settings
if (isset($_POST['booking_disabled_action'])) {
    updateSetting('booking_disabled_action', $_POST['booking_disabled_action']);
    updateSetting('booking_disabled_message', $_POST['booking_disabled_message'] ?? '');
    if (isset($_POST['booking_disabled_redirect_url'])) {
        updateSetting('booking_disabled_redirect_url', $_POST['booking_disabled_redirect_url']);
    }
    $message = "Disabled mode settings updated successfully!";
}

// Get booking system settings
$booking_enabled = isBookingEnabled();
$disabled_action = getBookingDisabledAction();
$disabled_message = getBookingDisabledMessage();
$disabled_redirect_url = getSetting('booking_disabled_redirect_url', '/');

// Handle setting updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check which form was submitted
        if (isset($_POST['max_advance_booking_days'])) {
            // Booking settings form
            $max_advance_days = (int)($_POST['max_advance_booking_days'] ?? 30);
            
            // Validate input
            if ($max_advance_days < 1) {
                throw new Exception('Maximum advance booking days must be at least 1');
            }
            
            if ($max_advance_days > 365) {
                throw new Exception('Maximum advance booking days cannot exceed 365 (one year)');
            }
            
            // Update setting in database
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'max_advance_booking_days'");
            $stmt->execute([$max_advance_days]);
            
            // Clear the setting cache (both in-memory and file cache)
            global $_SITE_SETTINGS;
            if (isset($_SITE_SETTINGS['max_advance_booking_days'])) {
                unset($_SITE_SETTINGS['max_advance_booking_days']);
            }
            // Clear the file cache
            deleteCache("setting_max_advance_booking_days");
            
            $message = "Maximum advance booking days updated to {$max_advance_days} days successfully!";
            
        } elseif (isset($_POST['booking_notification_settings'])) {
            $booking_notification_email = trim($_POST['booking_notification_email'] ?? '');
            $booking_notification_cc_emails = trim($_POST['booking_notification_cc_emails'] ?? '');

            if (!empty($booking_notification_email) && !filter_var($booking_notification_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Booking notification email address is invalid');
            }

            if (!empty($booking_notification_cc_emails)) {
                $ccList = array_filter(array_map('trim', explode(',', $booking_notification_cc_emails)));
                foreach ($ccList as $ccEmail) {
                    if (!filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('One or more booking CC email addresses are invalid');
                    }
                }
            }

            $savedPrimary = updateSetting('booking_notification_email', $booking_notification_email);
            $savedCc = updateSetting('booking_notification_cc_emails', $booking_notification_cc_emails);
            if (!$savedPrimary || !$savedCc) {
                throw new Exception('Failed to save booking notification email settings');
            }

            $message = "Booking notification email updated successfully!";

        } elseif (isset($_POST['service_channel_settings'])) {
            $conference_enabled = isset($_POST['conference_system_enabled']) ? '1' : '0';
            $gym_enabled = isset($_POST['gym_system_enabled']) ? '1' : '0';
            $restaurant_enabled = isset($_POST['restaurant_system_enabled']) ? '1' : '0';

            $conference_email = trim((string)($_POST['conference_email'] ?? ''));
            $gym_email = trim((string)($_POST['gym_email'] ?? ''));
            $email_restaurant = trim((string)($_POST['email_restaurant'] ?? ''));

            if ($conference_email !== '' && !filter_var($conference_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Conference notification email is invalid');
            }
            if ($gym_email !== '' && !filter_var($gym_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Gym notification email is invalid');
            }
            if ($email_restaurant !== '' && !filter_var($email_restaurant, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Restaurant notification email is invalid');
            }

            updateSetting('conference_system_enabled', $conference_enabled);
            updateSetting('gym_system_enabled', $gym_enabled);
            updateSetting('restaurant_system_enabled', $restaurant_enabled);

            updateSetting('conference_email', $conference_email);
            updateSetting('gym_email', $gym_email);
            updateSetting('email_restaurant', $email_restaurant);

            $message = 'Service channels and notification emails updated successfully!';

        } elseif (isset($_POST['booking_email_template_preview'])) {
            $templateKey = trim((string)($_POST['booking_email_template_preview'] ?? ''));

            $templateDefs = [
                'booking_confirmed' => 'Booking Confirmed (Customer)',
                'booking_cancelled' => 'Booking Cancelled (Customer)',
                'payment_invoice' => 'Payment Invoice (Customer)'
            ];

            if (!isset($templateDefs[$templateKey])) {
                throw new Exception('Invalid template selected for preview');
            }

            $templateName = $templateDefs[$templateKey];
            $subjectRaw = trim((string)($_POST[$templateKey . '_subject'] ?? ''));
            $htmlBodyRaw = trim((string)($_POST[$templateKey . '_html_body'] ?? ''));
            $textBodyRaw = trim((string)($_POST[$templateKey . '_text_body'] ?? ''));

            if ($subjectRaw === '' || $htmlBodyRaw === '') {
                throw new Exception('Template subject and HTML body are required for preview');
            }

            $previewVars = [
                '{{site_name}}' => (string)getSetting('site_name', 'Rosalyns Hotel'),
                '{{booking_reference}}' => 'RBH-2026-TEST-001',
                '{{guest_name}}' => 'Jane Doe',
                '{{room_name}}' => 'Deluxe Ocean View',
                '{{check_in_date_formatted}}' => date('F j, Y', strtotime('+14 days')),
                '{{check_out_date_formatted}}' => date('F j, Y', strtotime('+16 days')),
                '{{total_amount_formatted}}' => number_format(4500, 0),
                '{{currency_symbol}}' => (string)getSetting('currency_symbol', 'K'),
                '{{contact_email}}' => (string)getSetting('email_from_email', getSetting('contact_email', 'reservations@example.com')),
                '{{phone_main}}' => (string)getSetting('phone_main', '+61 2 9000 0000'),
                '{{payment_policy}}' => 'Full payment due 48 hours before check-in.',
            ];

            $template_preview = [
                'template_key' => $templateKey,
                'template_name' => $templateName,
                'subject' => strtr($subjectRaw, $previewVars),
                'html_body' => strtr($htmlBodyRaw, $previewVars),
                'text_body' => strtr($textBodyRaw, $previewVars),
                'variables' => $previewVars,
            ];

            $message = 'Template preview generated successfully.';

        } elseif (isset($_POST['booking_email_templates'])) {
            if (!function_exists('upsertBookingEmailTemplateConfig')) {
                throw new Exception('Booking template storage is not available');
            }

            $templateDefs = [
                'booking_confirmed' => 'Booking Confirmed (Customer)',
                'booking_cancelled' => 'Booking Cancelled (Customer)',
                'payment_invoice' => 'Payment Invoice (Customer)'
            ];

            foreach ($templateDefs as $templateKey => $templateName) {
                $subject = trim($_POST[$templateKey . '_subject'] ?? '');
                $htmlBody = trim($_POST[$templateKey . '_html_body'] ?? '');
                $textBody = trim($_POST[$templateKey . '_text_body'] ?? '');
                $isActive = isset($_POST[$templateKey . '_is_active']) ? 1 : 0;

                if ($subject === '' || $htmlBody === '') {
                    throw new Exception("{$templateName}: subject and HTML body are required");
                }

                if (!upsertBookingEmailTemplateConfig($templateKey, $templateName, $subject, $htmlBody, $textBody, $isActive)) {
                    throw new Exception("Failed to save template: {$templateName}");
                }
            }

            $message = "Booking email templates updated successfully!";

        } elseif (isset($_POST['email_settings'])) {
            // Email settings form
            $email_settings = [
                'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')),
                'smtp_port' => trim((string)($_POST['smtp_port'] ?? '')),
                'smtp_username' => trim((string)($_POST['smtp_username'] ?? '')),
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_secure' => strtolower(trim((string)($_POST['smtp_secure'] ?? 'ssl'))),
                'email_from_name' => trim((string)($_POST['email_from_name'] ?? '')),
                'email_from_email' => trim((string)($_POST['email_from_email'] ?? '')),
                'email_admin_email' => trim((string)($_POST['email_admin_email'] ?? '')),
                'email_bcc_admin' => isset($_POST['email_bcc_admin']) ? '1' : '0',
                'email_development_mode' => isset($_POST['email_development_mode']) ? '1' : '0',
                'email_log_enabled' => isset($_POST['email_log_enabled']) ? '1' : '0',
                'email_preview_enabled' => isset($_POST['email_preview_enabled']) ? '1' : '0',
            ];

            if (!in_array($email_settings['smtp_secure'], ['ssl', 'tls', ''], true)) {
                $email_settings['smtp_secure'] = 'ssl';
            }
            
            // Validate required fields
            $required_fields = ['smtp_host', 'smtp_port', 'smtp_username', 'email_from_name', 'email_from_email'];
            foreach ($required_fields as $field) {
                if (empty($email_settings[$field])) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
                }
            }
            
            // Validate port
            if (!is_numeric($email_settings['smtp_port']) || $email_settings['smtp_port'] < 1 || $email_settings['smtp_port'] > 65535) {
                throw new Exception('SMTP port must be a valid port number (1-65535)');
            }
            
            // Validate emails
            if (!filter_var($email_settings['email_from_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('From email address is invalid');
            }
            
            if (!empty($email_settings['email_admin_email']) && !filter_var($email_settings['email_admin_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Admin email address is invalid');
            }
            
            // Update email settings in database
            foreach ($email_settings as $key => $value) {
                if ($key === 'smtp_password' && empty($value)) {
                    // Keep existing encrypted password if field is left blank intentionally.
                    continue;
                }

                $is_encrypted = ($key === 'smtp_password' && !empty($value));
                if (!updateEmailSetting($key, $value, '', $is_encrypted)) {
                    throw new Exception('Failed to save SMTP/email setting: ' . $key);
                }
            }
            
            // Clear email cache so changes take effect immediately
            require_once __DIR__ . '/../config/cache.php';
            clearEmailCache();
            
            $message = "Email settings updated successfully!";
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get current setting
$current_max_days = (int)getSetting('max_advance_booking_days', 30);
$current_booking_notification_email = getSetting('booking_notification_email', getSetting('admin_notification_email', ''));
$current_booking_notification_cc_emails = getSetting('booking_notification_cc_emails', '');

$current_conference_system_enabled = getSetting('conference_system_enabled', '1') === '1';
$current_gym_system_enabled = getSetting('gym_system_enabled', '1') === '1';
$current_restaurant_system_enabled = getSetting('restaurant_system_enabled', '1') === '1';

$current_conference_email = getSetting('conference_email', getSetting('email_reservations', ''));
$current_gym_email = getSetting('gym_email', getSetting('email_reservations', ''));
$current_restaurant_email = getSetting('email_restaurant', getSetting('email_reservations', ''));

$booking_template_defs = [
    'booking_confirmed' => 'Booking Confirmed (Customer)',
    'booking_cancelled' => 'Booking Cancelled (Customer)',
    'payment_invoice' => 'Payment Invoice (Customer)'
];

$booking_templates = [];
foreach ($booking_template_defs as $template_key => $template_name) {
    $booking_templates[$template_key] = function_exists('getBookingEmailTemplateConfig')
        ? getBookingEmailTemplateConfig($template_key, [
            'template_key' => $template_key,
            'template_name' => $template_name,
            'subject' => '',
            'html_body' => '',
            'text_body' => '',
            'is_active' => 1
        ])
        : [
            'template_key' => $template_key,
            'template_name' => $template_name,
            'subject' => '',
            'html_body' => '',
            'text_body' => '',
            'is_active' => 1
        ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Settings - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/admin-booking-settings.css">
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>

    <div class="content">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-cog" style="color: #8B7355; margin-right: 10px;"></i>
                Booking Settings
            </h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="settings-card">
            <h2><i class="fas fa-toggle-on" style="color: #8B7355;"></i> Booking System Status</h2>

            <div class="current-value">
                <i class="fas fa-<?php echo $booking_enabled ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo $booking_enabled ? '#28a745' : '#dc3545'; ?>;"></i>
                <div class="current-value-info">
                    <h3>Booking System is <?php echo $booking_enabled ? 'Enabled' : 'Disabled'; ?></h3>
                    <div class="value"><?php echo $booking_enabled ? 'Active' : 'Inactive'; ?></div>
                </div>
            </div>

            <form method="POST" action="booking-settings.php" id="booking-system-toggle-form">
                <input type="hidden" name="toggle_booking_system" value="1">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" 
                               id="booking_system_enabled" 
                               name="booking_system_enabled" 
                               value="1" 
                               <?php echo $booking_enabled ? 'checked' : ''; ?>
                               onchange="toggleBookingSettings()">
                        <span style="font-weight: 600; color: #1A1A1A;">Enable Online Booking System</span>
                    </label>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        When disabled, all booking forms, buttons, and widgets will be hidden.
                        Guests will see a message instead of booking options.
                    </p>
                </div>
            </form>

            <?php if (!$booking_enabled): ?>
            <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-top: 25px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;"><i class="fas fa-exclamation-triangle"></i> Booking System Disabled</h4>
                <p style="color: #856404; margin-bottom: 15px;">
                    The booking system is currently turned off. Enable it above to allow guests to make online reservations.
                </p>
                <div style="display: flex; gap: 15px;">
                    <a href="#disabled-settings" style="color: #ffc107; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-cog"></i> Configure Disabled Message
                    </a>
                    <a href="https://github.com/papauri/Rosalyns_Beach_Hotel_2026" target="_blank" style="color: #ffc107; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-external-link-alt"></i> Setup on Another Website
                    </a>
                </div>
            </div>
            
            <div id="disabled-settings" style="display: none; margin-top: 25px;">
                <h3 style="color: #8B7355; margin-bottom: 20px;"><i class="fas fa-sliders-h"></i> Disabled Mode Settings</h3>
                
                <form method="POST" action="booking-settings.php">
                    <div class="form-group">
                        <label for="booking_disabled_action"><strong>Action When Disabled</strong></label>
                        <select id="booking_disabled_action" name="booking_disabled_action" class="form-control" onchange="toggleDisabledAction()">
                            <option value="message" <?php echo $disabled_action === 'message' ? 'selected' : ''; ?>>Show Custom Message</option>
                            <option value="contact" <?php echo $disabled_action === 'contact' ? 'selected' : ''; ?>>Show Contact Information</option>
                            <option value="redirect" <?php echo $disabled_action === 'redirect' ? 'selected' : ''; ?>>Redirect to URL</option>
                        </select>
                        <p class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Choose what happens when guests try to access booking features
                        </p>
                    </div>
                    
                    <div class="form-group" id="redirect-url-group" style="display: <?php echo $disabled_action === 'redirect' ? 'block' : 'none'; ?>;">
                        <label for="booking_disabled_redirect_url"><strong>Redirect URL</strong></label>
                        <input type="text" 
                               id="booking_disabled_redirect_url" 
                               name="booking_disabled_redirect_url" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($disabled_redirect_url ?? '/'); ?>"
                               placeholder="/">
                        <p class="help-text">
                            <i class="fas fa-info-circle"></i>
                            URL to redirect to (e.g., /contact or https://external-booking.com)
                        </p>
                    </div>
                    
                    <div class="form-group" id="message-group" style="display: <?php echo ($disabled_action === 'message' || $disabled_action === 'contact') ? 'block' : 'none'; ?>;">
                        <label for="booking_disabled_message"><strong>Custom Message</strong></label>
                        <textarea id="booking_disabled_message" 
                                  name="booking_disabled_message" 
                                  class="form-control" 
                                  rows="4"
                                  placeholder="For booking inquiries, please contact us at [phone] or [email]"><?php echo htmlspecialchars($disabled_message); ?></textarea>
                        <p class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Use [phone], [email], or [contact info] placeholders to insert your contact info automatically
                        </p>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
            
            <script>
                function toggleBookingSettings() {
                    const enabled = document.getElementById('booking_system_enabled').checked;

                    // Persist immediately with one switch
                    document.getElementById('booking-system-toggle-form').submit();
                }
                
                function toggleDisabledAction() {
                    const action = document.getElementById('booking_disabled_action').value;
                    document.getElementById('redirect-url-group').style.display = action === 'redirect' ? 'block' : 'none';
                    document.getElementById('message-group').style.display = (action === 'message' || action === 'contact') ? 'block' : 'none';
                }
                
                // Initialize
                toggleDisabledAction();
            </script>
            
            <hr style="margin: 30px 0; border-top: 2px solid #eee;">
        <?php endif; ?>
        
        <div class="settings-card">
            <h2><i class="fas fa-calendar-alt" style="color: #8B7355;"></i> Advance Booking Configuration</h2>

            <form method="POST" action="booking-settings.php">
                <div class="form-group">
                    <label for="max_advance_booking_days">Maximum Advance Booking Days</label>
                    <input type="number" 
                           id="max_advance_booking_days" 
                           name="max_advance_booking_days" 
                           class="form-control" 
                           value="<?php echo $current_max_days; ?>" 
                           min="1" 
                           max="365" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Guests can only make bookings up to this many days in advance. 
                        Default is 30 days (one month). Minimum is 1 day, maximum is 365 days.
                    </p>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>

            <div class="info-box">
                <h4><i class="fas fa-lightbulb"></i> How This Affects Your Website</h4>
                <ul>
                    <li><strong>Booking Form:</strong> Date pickers will only allow dates within this limit</li>
                    <li><strong>Validation:</strong> Server-side validation will reject bookings beyond this date</li>
                    <li><strong>User Experience:</strong> Users will see a clear message about the booking window</li>
                    <li><strong>Flexibility:</strong> Change this value anytime to adjust your booking policy</li>
                </ul>
            </div>
        </div>

        <div class="settings-card">
            <h2><i class="fas fa-envelope" style="color: #8B7355;"></i> Email Configuration</h2>
            
            <?php
            // Get current email settings
            $email_settings = getAllEmailSettings();
            $current_settings = [];
            foreach ($email_settings as $key => $setting) {
                $current_settings[$key] = $setting['value'];
            }
            ?>
            
            <form method="POST" action="booking-settings.php">
                <input type="hidden" name="email_settings" value="1">
                
                <h3 style="color: #1A1A1A; margin-top: 25px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;">
                    <i class="fas fa-server"></i> SMTP Server Settings
                </h3>
                
                <div class="form-group">
                    <label for="smtp_host">SMTP Host *</label>
                    <input type="text" 
                           id="smtp_host" 
                           name="smtp_host" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_host'] ?? ''); ?>" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Your SMTP server hostname (e.g., mail.yourdomain.com, smtp.gmail.com)
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="smtp_port">SMTP Port *</label>
                    <input type="number" 
                           id="smtp_port" 
                           name="smtp_port" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_port'] ?? ''); ?>" 
                           min="1" 
                           max="65535" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Common ports: 465 (SSL), 587 (TLS), 25 (Standard)
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="smtp_username">SMTP Username *</label>
                    <input type="text" 
                           id="smtp_username" 
                           name="smtp_username" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_username'] ?? ''); ?>" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Usually your full email address
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="smtp_password">SMTP Password</label>
                    <input type="password" 
                           id="smtp_password" 
                           name="smtp_password" 
                           class="form-control" 
                           value="" 
                           placeholder="Leave blank to keep current password">
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Your email account password. Only enter if you want to change it.
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="smtp_secure">SMTP Security</label>
                    <select id="smtp_secure" name="smtp_secure" class="form-control">
                        <option value="ssl" <?php echo ($current_settings['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="tls" <?php echo ($current_settings['smtp_secure'] ?? 'ssl') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="" <?php echo empty($current_settings['smtp_secure'] ?? '') ? 'selected' : ''; ?>>None</option>
                    </select>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Security protocol for SMTP connection
                    </p>
                </div>
                
                <h3 style="color: #1A1A1A; margin-top: 30px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;">
                    <i class="fas fa-user"></i> Email Identity
                </h3>
                
                <div class="form-group">
                    <label for="email_from_name">From Name *</label>
                    <input type="text" 
                           id="email_from_name" 
                           name="email_from_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['email_from_name'] ?? ''); ?>" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Name that appears as the sender of emails
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="email_from_email">From Email *</label>
                    <input type="email" 
                           id="email_from_email" 
                           name="email_from_email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['email_from_email'] ?? ''); ?>" 
                           required>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Email address that appears as the sender
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="email_admin_email">Admin Notification Email</label>
                    <input type="email" 
                           id="email_admin_email" 
                           name="email_admin_email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['email_admin_email'] ?? ''); ?>">
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Email address to receive booking notifications (optional)
                    </p>
                </div>
                
                <h3 style="color: #1A1A1A; margin-top: 30px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;">
                    <i class="fas fa-sliders-h"></i> Email Settings
                </h3>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" 
                               id="email_bcc_admin" 
                               name="email_bcc_admin" 
                               value="1" 
                               <?php echo ($current_settings['email_bcc_admin'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>BCC Admin on all emails</span>
                    </label>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Send a blind carbon copy of all emails to the admin email address
                    </p>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" 
                               id="email_development_mode" 
                               name="email_development_mode" 
                               value="1" 
                               <?php echo ($current_settings['email_development_mode'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>Development Mode (Preview Only)</span>
                    </label>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        When checked, emails will be saved as preview files instead of being sent. 
                        Useful for testing on localhost.
                    </p>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" 
                               id="email_log_enabled" 
                               name="email_log_enabled" 
                               value="1" 
                               <?php echo ($current_settings['email_log_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>Enable Email Logging</span>
                    </label>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Log all email activity to logs/email-log.txt
                    </p>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" 
                               id="email_preview_enabled" 
                               name="email_preview_enabled" 
                               value="1" 
                               <?php echo ($current_settings['email_preview_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>Enable Email Previews</span>
                    </label>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Save HTML previews of emails in logs/email-previews/ folder
                    </p>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Email Settings
                </button>
            </form>
            
            <div class="info-box" style="margin-top: 30px;">
                <h4><i class="fas fa-lightbulb"></i> Email Configuration Tips</h4>
                <ul>
                    <li><strong>Testing:</strong> Use Development Mode to test emails without actually sending them</li>
                    <li><strong>Security:</strong> Passwords are encrypted in the database for security</li>
                    <li><strong>Logs:</strong> Check logs/email-log.txt for email activity history</li>
                    <li><strong>Preview:</strong> View email previews in logs/email-previews/ folder</li>
                    <li><strong>Backup:</strong> Your previous email settings were backed up during migration</li>
                </ul>
            </div>
        </div>

        <div class="settings-card">
            <h2><i class="fas fa-bell" style="color: #8B7355;"></i> Booking Notification Email</h2>
            <form method="POST" action="booking-settings.php">
                <input type="hidden" name="booking_notification_settings" value="1">

                <div class="form-group">
                    <label for="booking_notification_email">Notification Recipient Email</label>
                    <input type="email"
                           id="booking_notification_email"
                           name="booking_notification_email"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_booking_notification_email ?? ''); ?>"
                           placeholder="reservations@example.com">
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        New booking notifications are sent to this email first, with fallback to Admin Notification Email.
                    </p>
                </div>

                <div class="form-group">
                    <label for="booking_notification_cc_emails">Booking Notification CC Emails</label>
                    <input type="text"
                           id="booking_notification_cc_emails"
                           name="booking_notification_cc_emails"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_booking_notification_cc_emails ?? ''); ?>"
                           placeholder="accounts@example.com, manager@example.com">
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Optional comma-separated CC recipients for all new booking admin notifications.
                    </p>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Notification Email
                </button>
            </form>
        </div>

        <div class="settings-card">
            <h2><i class="fas fa-sliders-h" style="color: #8B7355;"></i> Service Modules & Dedicated Notification Emails</h2>
            <form method="POST" action="booking-settings.php">
                <input type="hidden" name="service_channel_settings" value="1">

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="conference_system_enabled" value="1" <?php echo $current_conference_system_enabled ? 'checked' : ''; ?>>
                        <span>Enable Conference page & conference enquiry functionality</span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="conference_email">Conference Notification Email</label>
                    <input type="email" id="conference_email" name="conference_email" class="form-control" value="<?php echo htmlspecialchars($current_conference_email ?? ''); ?>" placeholder="conference@example.com">
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="gym_system_enabled" value="1" <?php echo $current_gym_system_enabled ? 'checked' : ''; ?>>
                        <span>Enable Gym page & gym booking/enquiry functionality</span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="gym_email">Gym Notification Email</label>
                    <input type="email" id="gym_email" name="gym_email" class="form-control" value="<?php echo htmlspecialchars($current_gym_email ?? ''); ?>" placeholder="gym@example.com">
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="restaurant_system_enabled" value="1" <?php echo $current_restaurant_system_enabled ? 'checked' : ''; ?>>
                        <span>Enable Restaurant page and related restaurant functionality</span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="email_restaurant">Restaurant Notification Email</label>
                    <input type="email" id="email_restaurant" name="email_restaurant" class="form-control" value="<?php echo htmlspecialchars($current_restaurant_email ?? ''); ?>" placeholder="restaurant@example.com">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Service Module Settings
                </button>
            </form>
        </div>

        <div class="settings-card">
            <h2><i class="fas fa-file-alt" style="color: #8B7355;"></i> Booking Email Templates (Database-Driven)</h2>
            <p class="help-text" style="margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i>
                Use placeholders like <code>{{site_name}}</code>, <code>{{booking_reference}}</code>, <code>{{guest_name}}</code>,
                <code>{{room_name}}</code>, <code>{{check_in_date_formatted}}</code>, <code>{{check_out_date_formatted}}</code>,
                <code>{{total_amount_formatted}}</code>, <code>{{currency_symbol}}</code>, <code>{{contact_email}}</code>,
                <code>{{phone_main}}</code>, and <code>{{payment_policy}}</code>.
            </p>

            <form method="POST" action="booking-settings.php">
                <input type="hidden" name="booking_email_templates" value="1">

                <?php foreach ($booking_template_defs as $template_key => $template_name): ?>
                    <?php $tpl = $booking_templates[$template_key]; ?>
                    <div class="info-box" style="margin-bottom: 18px; background: #f8f9fa;">
                        <h4 style="margin-bottom: 12px;"><?php echo htmlspecialchars($template_name); ?></h4>

                        <div class="form-group">
                            <label for="<?php echo $template_key; ?>_subject">Subject</label>
                            <input type="text"
                                   id="<?php echo $template_key; ?>_subject"
                                   name="<?php echo $template_key; ?>_subject"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($tpl['subject'] ?? ''); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="<?php echo $template_key; ?>_html_body">HTML Body</label>
                            <textarea id="<?php echo $template_key; ?>_html_body"
                                      name="<?php echo $template_key; ?>_html_body"
                                      class="form-control"
                                      rows="7"
                                      required><?php echo htmlspecialchars($tpl['html_body'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="<?php echo $template_key; ?>_text_body">Plain Text Body (optional)</label>
                            <textarea id="<?php echo $template_key; ?>_text_body"
                                      name="<?php echo $template_key; ?>_text_body"
                                      class="form-control"
                                      rows="4"><?php echo htmlspecialchars($tpl['text_body'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox"
                                       name="<?php echo $template_key; ?>_is_active"
                                       value="1"
                                       <?php echo ((int)($tpl['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                <span>Template active</span>
                            </label>
                        </div>

                        <div class="template-preview-actions">
                            <button type="submit"
                                    class="btn-submit btn-preview"
                                    name="booking_email_template_preview"
                                    value="<?php echo htmlspecialchars($template_key); ?>"
                                    formaction="booking-settings.php"
                                    formmethod="POST">
                                <i class="fas fa-eye"></i> Preview <?php echo htmlspecialchars($template_name); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Booking Email Templates
                </button>
            </form>

            <?php if (is_array($template_preview)): ?>
                <div class="template-preview-panel">
                    <h3><i class="fas fa-eye"></i> Preview: <?php echo htmlspecialchars($template_preview['template_name']); ?></h3>
                    <p class="help-text"><strong>Subject:</strong> <?php echo htmlspecialchars($template_preview['subject']); ?></p>

                    <div class="template-preview-html">
                        <?php echo $template_preview['html_body']; ?>
                    </div>

                    <?php if (!empty($template_preview['text_body'])): ?>
                        <details class="template-preview-text">
                            <summary>Plain text preview</summary>
                            <pre><?php echo htmlspecialchars($template_preview['text_body']); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <h4><i class="fas fa-exclamation-triangle"></i> Important Security Note</h4>
            <p style="color: #856404; margin: 0;">
                <strong>All email settings are now stored in the database.</strong> No more hardcoded passwords in files. 
                Your SMTP password is encrypted for security. You can update it anytime in this admin panel.
            </p>
        </div>

    <?php require_once 'includes/admin-footer.php'; ?>
