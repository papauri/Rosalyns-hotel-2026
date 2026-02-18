<?php
/**
 * Database Migration: WhatsApp Settings
 * Adds WhatsApp notification settings to site_settings table
 * 
 * Run this script once to add all WhatsApp configuration options
 */

require_once __DIR__ . '/../../config/database.php';

echo "<h1>WhatsApp Settings Migration</h1>";
echo "<pre>";

try {
    $pdo->beginTransaction();
    
    // WhatsApp settings to add
    $settings = [
        // API Configuration
        'whatsapp_enabled' => '0',
        'whatsapp_api_token' => '',
        'whatsapp_phone_id' => '',
        'whatsapp_business_id' => '',
        'whatsapp_number' => '+353860081635',  // Hotel's WhatsApp number
        
        // Notification Triggers (default all to enabled)
        'whatsapp_notify_on_booking' => '1',
        'whatsapp_notify_on_confirmation' => '1',
        'whatsapp_notify_on_cancellation' => '1',
        'whatsapp_notify_on_checkin' => '1',
        'whatsapp_notify_on_checkout' => '1',
        
        // Recipients
        'whatsapp_guest_notifications' => '1',
        'whatsapp_hotel_notifications' => '1',
        'whatsapp_admin_numbers' => '',
        
        // Message Templates
        'whatsapp_confirmed_template' => 'booking_confirmed',
        'whatsapp_cancelled_template' => 'booking_cancelled',
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO site_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    $added = 0;
    $updated = 0;
    
    foreach ($settings as $key => $value) {
        // Check if setting exists
        $checkStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $checkStmt->execute([$key]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt->execute([$key, $value]);
        
        if ($existing) {
            echo "✓ Updated: $key = '$value'\n";
            $updated++;
        } else {
            echo "✓ Added: $key = '$value'\n";
            $added++;
        }
    }
    
    $pdo->commit();
    
    echo "\n========================================\n";
    echo "Migration completed successfully!\n";
    echo "Added: $added settings\n";
    echo "Updated: $updated settings\n";
    echo "========================================\n";
    
    echo "\nNext Steps:\n";
    echo "1. Go to Meta Business Suite (business.facebook.com)\n";
    echo "2. Get your WhatsApp Phone Number ID\n";
    echo "3. Get your WhatsApp Business Account ID\n";
    echo "4. Create a System User and generate a Permanent Access Token\n";
    echo "5. Create message templates in WhatsApp Manager:\n";
    echo "   - booking_confirmed\n";
    echo "   - booking_cancelled\n";
    echo "6. Enter these values in Admin > WhatsApp Settings\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Rollback completed.\n";
}

echo "</pre>";
echo '<p><a href="../dashboard.php">Return to Dashboard</a> | <a href="../whatsapp-settings.php">Go to WhatsApp Settings</a></p>';
?>