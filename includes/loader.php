<?php
if (function_exists('getSetting')) {
    $siteName = getSetting('site_name');
}

// Auto-detect current page slug from filename
$page_slug = '';
if (isset($_SERVER['SCRIPT_FILENAME'])) {
    $page_slug = strtolower(pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME));
    $page_slug = str_replace('_', '-', $page_slug);
}

// Fetch loader subtext from database
$loaderSubtext = '';
if (function_exists('getPageLoader') && $page_slug) {
    $loaderSubtext = getPageLoader($page_slug);
}
?>
<!-- Elegant Page Loader -->
<div id="page-loader" class="loader loader--active">
    <div class="loader__content">
        <div class="loader__spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-center"></div>
        </div>
        <div class="loader__title"><?php echo htmlspecialchars($siteName); ?></div>
        <div class="loader__subtitle"><?php echo htmlspecialchars($loaderSubtext); ?></div>
        <div class="loader__progress">
            <div class="loader__progress-bar"></div>
        </div>
    </div>
</div>

<script>
(function() {
    // Hide loader when page is fully loaded
    function hideLoader() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.classList.remove('loader--active');
            loader.classList.add('loader--hiding');
            setTimeout(function() {
                loader.classList.remove('loader--hiding');
                loader.classList.add('loader--hidden');
            }, 500);
        }
    }
    
    // Hide on window load
    if (document.readyState === 'complete') {
        hideLoader();
    } else {
        window.addEventListener('load', hideLoader);
    }
    
    // Fallback: hide after 3 seconds regardless
    setTimeout(hideLoader, 3000);
})();
</script>
