<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
</head>
<body>
    <!-- Admin Header (Top Bar) -->
    <?php require_once 'includes/admin-header.php'; ?>
    
    <!-- Main Content Area (Sidebar + Content) -->
    <div class="admin-wrapper">
        <?php require_once 'admin/index-body.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="js/modal.js" defer></script>
    <script src="js/admin-components.js" defer></script>
    <script src="js/admin-main.js" defer></script>
</body>
</html>