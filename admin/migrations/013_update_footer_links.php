<?php
/**
 * Migration Script: Auto-Map Footer Links with Anchor IDs
 * 
 * This script automatically maps footer link titles to their corresponding
 * anchor IDs based on a predefined mapping. It updates both link_url and
 * secondary_link_url columns in the footer_links table.
 * 
 * Usage: Access this file directly in a browser or run via CLI:
 *        php admin/migrations/013_update_footer_links.php
 * 
 * This script is idempotent - safe to run multiple times.
 */

// Load database configuration
require_once __DIR__ . '/../../config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Footer Links Migration - Auto-Map Links</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .success { color: #27ae60; }
        .warning { color: #f39c12; }
        .info { color: #3498db; }
        .error { color: #e74c3c; }
        code { background: #ecf0f1; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:hover { background: #f8f9fa; }
        .mapping-list { background: #ecf0f1; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .mapping-list ul { margin: 0; padding-left: 20px; }
        .mapping-list li { margin: 5px 0; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-box { flex: 1; background: #3498db; color: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-box.updated { background: #27ae60; }
        .stat-box.skipped { background: #95a5a6; }
        .stat-number { font-size: 2em; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔗 Footer Links Auto-Mapper Migration</h1>
        <p>This script automatically maps footer link titles to their corresponding anchor IDs.</p>
";

// Define the mapping array of titles to anchor IDs
// Format: 'Title' => 'page.php#anchor'
$linkMappings = [
    // Main navigation sections
    'About' => 'index.php#about',
    'About Us' => 'index.php#about',
    'Rooms' => 'index.php#rooms',
    'Accommodation' => 'index.php#rooms',
    'Room Types' => 'index.php#rooms',
    'Gym' => 'gym.php#wellness',
    'Fitness Center' => 'gym.php#wellness',
    'Wellness' => 'gym.php#wellness',
    'Gym Facilities' => 'gym.php#facilities',
    'Fitness Classes' => 'gym.php#classes',
    'Personal Training' => 'gym.php#personal-training',
    'Wellness Packages' => 'gym.php#packages',
    'Restaurant' => 'restaurant.php#menu',
    'Dining' => 'restaurant.php#menu',
    'Menu' => 'restaurant.php#menu',
    'Restaurant Gallery' => 'restaurant.php#gallery',
    'Dining Experience' => 'restaurant.php#experience',
    'Conference' => 'conference.php#conference',
    'Conferences' => 'conference.php#conference',
    'Conference Facilities' => 'conference.php#conference',
    'Events' => 'events.php#events',
    'Upcoming Events' => 'events.php#events',
    'Guest Services' => 'guest-services.php#services',
    'Services' => 'guest-services.php#services',
    'Contact' => 'contact-us.php#contact-info',
    'Contact Us' => 'contact-us.php#contact-info',
    'Book Now' => 'booking.php',
    'Booking' => 'booking.php',
    'Reservations' => 'booking.php',
    
    // Additional common titles
    'Gallery' => 'index.php#gallery',
    'Reviews' => 'index.php#testimonials',
    'Testimonials' => 'index.php#testimonials',
    'Facilities' => 'index.php#facilities',
    'Location' => 'contact-us.php#map',
    'Find Us' => 'contact-us.php#map',
    'Map' => 'contact-us.php#map',
    'Terms' => '#',
    'Terms & Conditions' => '#',
    'Privacy' => '#',
    'Privacy Policy' => '#',
];

// Reverse mapping for case-insensitive lookups
$linkMappingsLower = array_change_key_case($linkMappings, CASE_LOWER);

echo "<h2>📋 Title-to-Anchor Mapping Configuration</h2>";
echo "<div class='mapping-list'>";
echo "<p><strong>" . count($linkMappings) . " mappings defined:</strong></p>";
echo "<ul>";
foreach ($linkMappings as $title => $url) {
    echo "<li><code>'$title'</code> → <code>$url</code></li>";
}
echo "</ul>";
echo "</div>";

try {
    // Check if footer_links table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'footer_links'");
    if ($checkTable->rowCount() === 0) {
        echo "<h2 class='error'>❌ Table Not Found</h2>";
        echo "<p>The <code>footer_links</code> table does not exist in the database.</p>";
        echo "<p>Please run the footer links table creation migration first.</p>";
        echo "</div></body></html>";
        exit;
    }

    // Fetch all footer links
    $stmt = $pdo->query("SELECT id, link_text, link_url, secondary_link_url FROM footer_links ORDER BY id");
    $footerLinks = $stmt->fetchAll();

    $totalLinks = count($footerLinks);
    $updatedCount = 0;
    $skippedCount = 0;
    $changes = [];

    echo "<h2>🔍 Processing Footer Links</h2>";
    echo "<p>Found <strong>$totalLinks</strong> footer link(s) in the database.</p>";

    if ($totalLinks === 0) {
        echo "<p class='warning'>⚠️ No footer links found to process.</p>";
    } else {
        echo "<table>";
        echo "<thead><tr><th>ID</th><th>Title</th><th>Old URL</th><th>New URL</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($footerLinks as $link) {
            $id = $link['id'];
            $title = $link['link_text'];
            $oldUrl = $link['link_url'];
            $oldSecondaryUrl = $link['secondary_link_url'] ?? '';
            
            $titleLower = strtolower(trim($title));
            $newUrl = $oldUrl;
            $newSecondaryUrl = $oldSecondaryUrl;
            $status = 'No Change';
            $statusClass = 'info';

            // Check if title matches any mapping
            if (isset($linkMappingsLower[$titleLower])) {
                $mappedUrl = $linkMappingsLower[$titleLower];
                
                // Update link_url if it's empty or doesn't match the expected pattern
                if (empty($oldUrl) || $oldUrl === '#' || strpos($oldUrl, '#') === false) {
                    $newUrl = $mappedUrl;
                    $status = 'Updated';
                    $statusClass = 'success';
                } else {
                    // Check if the current URL already has the correct anchor
                    $currentAnchor = strpos($oldUrl, '#') !== false ? substr($oldUrl, strpos($oldUrl, '#')) : '';
                    $expectedAnchor = strpos($mappedUrl, '#') !== false ? substr($mappedUrl, strpos($mappedUrl, '#')) : '';
                    
                    if ($currentAnchor !== $expectedAnchor) {
                        $newUrl = $mappedUrl;
                        $status = 'Updated';
                        $statusClass = 'success';
                    }
                }

                // Update secondary_link_url if it's empty or doesn't match
                if (!empty($oldSecondaryUrl) && ($oldSecondaryUrl === '#' || strpos($oldSecondaryUrl, '#') === false)) {
                    $newSecondaryUrl = $mappedUrl;
                    $status = 'Updated';
                    $statusClass = 'success';
                }
            }

            // Apply updates if changed
            if ($newUrl !== $oldUrl || $newSecondaryUrl !== $oldSecondaryUrl) {
                $updateSql = "UPDATE footer_links SET link_url = :link_url, secondary_link_url = :secondary_url WHERE id = :id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':link_url' => $newUrl,
                    ':secondary_url' => $newSecondaryUrl,
                    ':id' => $id
                ]);

                $updatedCount++;
                $changes[] = [
                    'id' => $id,
                    'link_text' => $title,
                    'old_url' => $oldUrl,
                    'new_url' => $newUrl,
                    'old_secondary' => $oldSecondaryUrl,
                    'new_secondary' => $newSecondaryUrl
                ];

                echo "<tr>";
                echo "<td>$id</td>";
                echo "<td><strong>" . htmlspecialchars($link['link_text']) . "</strong></td>";
                echo "<td><code>" . htmlspecialchars($oldUrl ?: '(empty)') . "</code></td>";
                echo "<td><code>" . htmlspecialchars($newUrl) . "</code></td>";
                echo "<td class='$statusClass'>$status</td>";
                echo "</tr>";
            } else {
                $skippedCount++;
            }
        }

        echo "</tbody>";
        echo "</table>";
    }

    // Display statistics
    echo "<h2>📊 Migration Summary</h2>";
    echo "<div class='stats'>";
    echo "<div class='stat-box'>";
    echo "<div class='stat-number'>$totalLinks</div>";
    echo "<div>Total Links</div>";
    echo "</div>";
    echo "<div class='stat-box updated'>";
    echo "<div class='stat-number'>$updatedCount</div>";
    echo "<div>Links Updated</div>";
    echo "</div>";
    echo "<div class='stat-box skipped'>";
    echo "<div class='stat-number'>$skippedCount</div>";
    echo "<div>Links Skipped</div>";
    echo "</div>";
    echo "</div>";

    // Display detailed changes if any
    if (!empty($changes)) {
        echo "<h2>✅ Detailed Changes Applied</h2>";
        echo "<pre>";
        foreach ($changes as $change) {
            echo "ID: {$change['id']}\n";
            echo "  Title: {$change['link_text']}\n";
            if ($change['old_url'] !== $change['new_url']) {
                echo "  link_url: '{$change['old_url']}' → '{$change['new_url']}'\n";
            }
            if ($change['old_secondary'] !== $change['new_secondary']) {
                echo "  secondary_link_url: '{$change['old_secondary']}' → '{$change['new_secondary']}'\n";
            }
            echo "\n";
        }
        echo "</pre>";
    }

    echo "<h2 class='success'>✅ Migration Completed Successfully!</h2>";
    echo "<p><strong>Results:</strong></p>";
    echo "<ul>";
    echo "<li><span class='success'>$updatedCount</span> footer link(s) updated with anchor IDs</li>";
    echo "<li><span class='info'>$skippedCount</span> footer link(s) already had correct URLs or no mapping found</li>";
    echo "</ul>";
    echo "<p class='info'><em>💡 This script is idempotent - you can safely run it again to verify or update any new links.</em></p>";

} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Database Error</h2>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    echo "<p class='error'>Migration failed. Please check the error message above and try again.</p>";
    exit;
}

echo "
    </div>
</body>
</html>
";
