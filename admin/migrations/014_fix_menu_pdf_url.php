<?php
/**
 * Migration 014: Fix incorrect menu PDF URL settings
 * 
 * This migration fixes the issue where restaurant_menu_url and restaurant_menu_pdf_url
 * settings point to /api/menu-pdf.php (which doesn't exist) instead of menu-pdf.php
 */

require_once __DIR__ . '/../../config/database.php';

echo "=== Migration 014: Fix Menu PDF URL Settings ===\n";

try {
    // Update restaurant_menu_url if it points to /api/menu-pdf.php
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = 'menu-pdf.php' WHERE setting_key = 'restaurant_menu_url' AND (setting_value LIKE '%api/menu-pdf.php' OR setting_value LIKE '%/api/menu-pdf%')");
    $stmt->execute();
    $count1 = $stmt->rowCount();

    echo "- Updated restaurant_menu_url: " . ($count1 > 0 ? "YES ({$count1} row(s))" : "NO CHANGES NEEDED") . "\n";

    // Update restaurant_menu_pdf_url if it points to /api/menu-pdf.php
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = 'menu-pdf.php' WHERE setting_key = 'restaurant_menu_pdf_url' AND (setting_value LIKE '%api/menu-pdf.php' OR setting_value LIKE '%/api/menu-pdf%')");
    $stmt->execute();
    $count2 = $stmt->rowCount();

    echo "- Updated restaurant_menu_pdf_url: " . ($count2 > 0 ? "YES ({$count2} row(s))" : "NO CHANGES NEEDED") . "\n";

    // Also check for any other menu-related URLs that might be wrong
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = 'menu-pdf.php' WHERE setting_key IN ('restaurant_menu_url', 'restaurant_menu_pdf_url') AND setting_value = ''");
    $stmt->execute();
    $count3 = $stmt->rowCount();

    echo "- Fixed empty menu URLs: " . ($count3 > 0 ? "YES ({$count3} row(s))" : "NONE") . "\n";

    // Verify the fix
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('restaurant_menu_url', 'restaurant_menu_pdf_url')");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n=== Current Settings After Fix ===\n";
    foreach ($settings as $setting) {
        echo "- {$setting['setting_key']}: {$setting['setting_value']}\n";
    }

    // Verify menu-pdf.php exists
    $menuPdfPath = __DIR__ . '/../../menu-pdf.php';
    if (file_exists($menuPdfPath)) {
        echo "\n✓ menu-pdf.php exists in root directory\n";
    } else {
        echo "\n⚠ WARNING: menu-pdf.php NOT FOUND in root directory!\n";
    }

    echo "\n=== Migration Complete ===\n";
    echo "Please clear any browser cache and test the QR code on restaurant.php\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>