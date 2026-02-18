<?php
try {
    require_once __DIR__ . '/../config/database.php';
    $siteName = getSetting('site_name');
} catch (Exception $e) {
    // DB is down - site name will be empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Database Connection Error | <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="css/main.css"></head>
<body>
    <div class="db-error-container">
        <svg class="sleeping-bear" viewBox="0 0 120 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="60" cy="70" rx="38" ry="10" fill="#e0e0e0"/>
            <ellipse cx="60" cy="48" rx="38" ry="28" fill="#b0b8c1"/>
            <ellipse cx="40" cy="38" rx="10" ry="8" fill="#a7a7a7"/>
            <ellipse cx="80" cy="38" rx="10" ry="8" fill="#a7a7a7"/>
            <ellipse cx="60" cy="60" rx="16" ry="10" fill="#fff"/>
            <ellipse cx="60" cy="62" rx="8" ry="4" fill="#b0b8c1"/>
            <ellipse cx="48" cy="30" rx="3" ry="2" fill="#fff"/>
            <ellipse cx="72" cy="30" rx="3" ry="2" fill="#fff"/>
            <ellipse cx="60" cy="54" rx="2" ry="1.2" fill="#444"/>
            <ellipse cx="52" cy="54" rx="1.2" ry="0.7" fill="#444"/>
            <ellipse cx="68" cy="54" rx="1.2" ry="0.7" fill="#444"/>
        </svg>
        <div class="zzz">Zzz...</div>
        <div class="db-error-title">The site is down.<br>Please contact the administrators.</div>
    </div>
    <script>
        // Print the real error to the console for admins
        <?php if (isset($errorMsg)): ?>
        console.error('Database Connection Error: <?php echo addslashes($errorMsg); ?>');
        <?php endif; ?>
    </script>
    </div>
</body>
</html>
