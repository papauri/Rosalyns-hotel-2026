<?php
/**
 * Migration Script: Fix Navigation File Paths
 * 
 * This script fixes the file_path values in the site_pages table.
 * It removes the 'api/' prefix from file_path values that should point
 * to root-level files like conference.php, restaurant.php, gym.php, events.php.
 * 
 * Usage: Access this file directly in a browser or run via CLI:
 *        php Database/migrations/fix_navigation_file_paths.php
 */

// Load database configuration
require_once __DIR__ . '/../../config/database.php';

echo "<h2>Navigation File Path Fix Migration</h2>";
echo "<p>Checking site_pages table for incorrect file paths...</p>";

try {
    // Get all pages from site_pages
    $stmt = $pdo->query("SELECT id, page_key, title, file_path FROM site_pages");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pages)) {
        echo "<p><strong>No pages found in site_pages table.</strong></p>";
        echo "<p>The table may be empty or doesn't exist yet.</p>";
        exit;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Page Key</th><th>Title</th><th>Old Path</th><th>New Path</th><th>Status</th></tr>";
    
    $fixedCount = 0;
    $alreadyCorrectCount = 0;
    
    // Pages that should be in root directory (not in api/)
    $rootPages = [
        'conference' => 'conference.php',
        'restaurant' => 'restaurant.php',
        'gym' => 'gym.php',
        'events' => 'events.php',
        'index' => 'index.php',
        'home' => 'index.php',
        'rooms-gallery' => 'rooms-gallery.php',
        'booking' => 'booking.php',
    ];
    
    foreach ($pages as $page) {
        $id = $page['id'];
        $pageKey = $page['page_key'];
        $currentPath = $page['file_path'];
        $newPath = $currentPath;
        $status = 'No change needed';
        $rowStyle = '';
        
        // Check if this is a root page with incorrect api/ prefix
        if (isset($rootPages[$pageKey])) {
            $expectedPath = $rootPages[$pageKey];
            
            // If current path has api/ prefix, remove it
            if (strpos($currentPath, 'api/') === 0) {
                $newPath = substr($currentPath, 4); // Remove 'api/' prefix
                
                // Update the database
                $updateStmt = $pdo->prepare("UPDATE site_pages SET file_path = ? WHERE id = ?");
                $updateStmt->execute([$newPath, $id]);
                
                $status = '✓ FIXED';
                $rowStyle = 'background-color: #d4edda;';
                $fixedCount++;
            } elseif ($currentPath === $expectedPath) {
                $status = '✓ Already correct';
                $rowStyle = 'background-color: #fff3cd;';
                $alreadyCorrectCount++;
            } else {
                $status = '⚠ Different path';
                $rowStyle = 'background-color: #f8d7da;';
            }
        }
        
        echo "<tr style='$rowStyle'>";
        echo "<td>{$id}</td>";
        echo "<td>{$pageKey}</td>";
        echo "<td>{$page['title']}</td>";
        echo "<td>{$currentPath}</td>";
        echo "<td>{$newPath}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>Total pages checked: " . count($pages) . "</li>";
    echo "<li>Fixed: <strong>{$fixedCount}</strong></li>";
    echo "<li>Already correct: {$alreadyCorrectCount}</li>";
    echo "</ul>";
    
    if ($fixedCount > 0) {
        echo "<p style='color: green; font-weight: bold;'>Successfully fixed {$fixedCount} navigation link(s)!</p>";
        echo "<p>The links now point to the correct root-level files instead of the api/ folder.</p>";
    } else {
        echo "<p style='color: orange;'>No fixes were needed. All file paths appear to be correct.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>The site_pages table may not exist yet. It will be created automatically when you visit the admin page management.</p>";
}
?>
