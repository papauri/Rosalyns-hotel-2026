<?php
require_once 'config/database.php';
require_once 'config/base-url.php';
require_once 'includes/page-guard.php';
require_once 'includes/booking-functions.php';
require_once 'includes/section-headers.php';

requireRestaurantEnabled();

/**
 * Build safe menu URL from DB setting with fallback.
 */
function buildValidatedMenuLink(string $candidate, string $fallback): string {
    $candidate = trim($candidate);

    if ($candidate === '') {
        return $fallback;
    }

    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
        $parts = parse_url($candidate);
        $scheme = strtolower($parts['scheme'] ?? '');
        return in_array($scheme, ['http', 'https'], true) ? $candidate : $fallback;
    }

    // Allow only safe relative links (no protocol-relative //, no javascript:)
    if (preg_match('#^(?!//)[A-Za-z0-9_./?=&%\-]+$#', $candidate)) {
        return siteUrl(ltrim($candidate, '/'));
    }

    return $fallback;
}

/**
 * Reject cross-domain menu URLs when running in local/dev.
 */
function enforceMenuHost(string $url, string $currentHost): string {
    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }

    $urlHost = strtolower((string)($parts['host'] ?? ''));
    $normalizedCurrentHost = strtolower($currentHost);

    if ($urlHost !== '' && $urlHost !== $normalizedCurrentHost) {
        return '';
    }

    return $url;
}

function menuCategorySlug(string $category): string {
    $slug = strtolower(trim($category));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'uncategorized';
}

// AJAX Endpoint - Handle menu data requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'menu') {
    header('Content-Type: application/json');

    $menu_type = isset($_GET['menu_type']) ? strtolower($_GET['menu_type']) : 'food';
    $currency_symbol = getSetting('currency_symbol');
    $currency_code = getSetting('currency_code');

    $response = [
        'success' => false,
        'menu_type' => $menu_type,
        'categories' => [],
        'currency' => [
            'symbol' => $currency_symbol,
            'code' => $currency_code
        ]
    ];

    try {
        if ($menu_type === 'food') {
            $stmt = $pdo->query("SELECT id, item_name, description, price, is_featured, is_vegetarian, is_vegan, allergens, category FROM food_menu WHERE is_available = 1 ORDER BY category ASC, display_order ASC, id ASC");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $category = trim((string)($item['category'] ?? '')); 
                if ($category === '') {
                    $category = 'Uncategorized';
                }
                $slug = menuCategorySlug($category);

                if (!isset($response['categories'][$slug])) {
                    $response['categories'][$slug] = [
                        'name' => $category,
                        'slug' => $slug,
                        'items' => []
                    ];
                }
                $response['categories'][$slug]['items'][] = [
                    'id' => $item['id'],
                    'name' => trim((string)($item['item_name'] ?? '')),
                    'description' => trim((string)($item['description'] ?? '')),
                    'price' => (float)$item['price'],
                    'is_featured' => (bool)$item['is_featured'],
                    'is_vegetarian' => (bool)$item['is_vegetarian'],
                    'is_vegan' => (bool)$item['is_vegan'],
                    'allergens' => trim((string)($item['allergens'] ?? ''))
                ];
            }
            $response['success'] = true;

        } elseif ($menu_type === 'coffee') {
            $stmt = $pdo->query("SELECT id, item_name, description, price, tags, category FROM drink_menu WHERE is_available = 1 AND LOWER(category) = 'coffee' ORDER BY category ASC, display_order ASC, id ASC");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $category = trim((string)($item['category'] ?? 'Coffee'));
                $slug = menuCategorySlug($category);
                if (!isset($response['categories'][$slug])) {
                    $response['categories'][$slug] = [
                        'name' => $category,
                        'slug' => $slug,
                        'items' => []
                    ];
                }

                $response['categories'][$slug]['items'][] = [
                    'id' => $item['id'],
                    'name' => trim((string)($item['item_name'] ?? '')),
                    'description' => trim((string)($item['description'] ?? '')),
                    'price' => (float)$item['price'],
                    'tags' => !empty($item['tags']) ? array_map('trim', explode(',', $item['tags'])) : []
                ];
            }
            $response['success'] = true;

        } elseif ($menu_type === 'bar') {
            $stmt = $pdo->query("SELECT id, item_name, description, price, tags, category FROM drink_menu WHERE is_available = 1 AND LOWER(category) != 'coffee' ORDER BY category ASC, display_order ASC, id ASC");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by category
            foreach ($items as $item) {
                $category = trim((string)($item['category'] ?? ''));
                if ($category === '') {
                    $category = 'Uncategorized';
                }
                $slug = menuCategorySlug($category);

                if (!isset($response['categories'][$slug])) {
                    $response['categories'][$slug] = [
                        'name' => $category,
                        'slug' => $slug,
                        'items' => []
                    ];
                }
                $response['categories'][$slug]['items'][] = [
                    'id' => $item['id'],
                    'name' => trim((string)($item['item_name'] ?? '')),
                    'description' => trim((string)($item['description'] ?? '')),
                    'price' => (float)$item['price'],
                    'tags' => !empty($item['tags']) ? array_map('trim', explode(',', $item['tags'])) : []
                ];
            }
            $response['success'] = true;
        }

        if (empty($response['categories'])) {
            $response['admin_hint'] = 'No active menu items found. Add items in Admin > Menu Management and mark them available.';
        }
    } catch (PDOException $e) {
        error_log("Error fetching menu: " . $e->getMessage());
        $response['error'] = 'Failed to load menu data';
    }

    echo json_encode($response);
    exit;
}

// Fetch site settings
$site_name = getSetting('site_name');
$site_logo = getSetting('site_logo');
$currency_symbol = getSetting('currency_symbol');
$currency_code = getSetting('currency_code');
$restaurant_contact_email = getSetting('email_restaurant', getSetting('email_reservations', ''));
$restaurant_phone = getSetting('phone_main', '');
// Dynamic menu page (pulls live data from DB) and env detection
$menu_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$menu_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$is_local_env = in_array($menu_host, ['localhost', '127.0.0.1']) || str_contains($menu_host, 'local');
$current_origin = $menu_protocol . '://' . $menu_host;

$menu_view_setting = (string)getSetting('restaurant_menu_url', '');
$menu_pdf_setting = (string)getSetting('restaurant_menu_pdf_url', '');

$menu_page_fallback = $current_origin . siteUrl('menu-pdf.php');

if ($is_local_env) {
    // In local/dev, always use current environment URL to avoid stale cross-site DB settings.
    $menu_view_url = $menu_page_fallback;
    $menu_pdf_url = $menu_page_fallback;
} else {
    $menu_view_candidate = buildValidatedMenuLink($menu_view_setting, $menu_page_fallback);
    $menu_view_candidate = enforceMenuHost($menu_view_candidate, $menu_host);
    $menu_view_url = $menu_view_candidate !== '' ? $menu_view_candidate : $menu_page_fallback;

    $menu_pdf_candidate = buildValidatedMenuLink($menu_pdf_setting, $menu_view_url);
    $menu_pdf_candidate = enforceMenuHost($menu_pdf_candidate, $menu_host);
    $menu_pdf_url = $menu_pdf_candidate !== '' ? $menu_pdf_candidate : $menu_view_url;
}

// QR image – same URL for prod/uat/local (content is already correct),
// but styling will be lighter so it's always visible.
$menu_qr_image = 'https://api.qrserver.com/v1/create-qr-code/?size=520x520&data=' . urlencode($menu_view_url) . '&margin=0&bgcolor=ffffff&color=000000';

// Use different QR background color for local vs production
$qr_bg_color = $is_local_env ? '#f5f5f5' : '#ffffff';
$qr_text_color = $is_local_env ? '#2d2d2d' : '#1b1b1b';

// Fetch restaurant gallery
$gallery_images = [];
try {
    if (function_exists('getManagedMediaItems')) {
        $managed = getManagedMediaItems('restaurant_gallery_media', ['limit' => 24]);
        if (!empty($managed)) {
            $gallery_images = array_map(static function ($item) {
                $mediaUrl = $item['media_url'] ?? '';
                $isVideo = ($item['media_type'] ?? '') === 'video';
                return [
                    'image_path' => $isVideo ? '' : $mediaUrl,
                    'caption' => $item['caption'] ?? ($item['title'] ?? ''),
                    'video_path' => $isVideo ? $mediaUrl : null,
                    'video_type' => $isVideo ? ($item['mime_type'] ?? 'url') : null,
                ];
            }, $managed);
        }
    }

    if (empty($gallery_images)) {
        $stmt = $pdo->query("SELECT * FROM restaurant_gallery WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($gallery_images) && function_exists('applyManagedMediaOverrides')) {
            foreach ($gallery_images as &$restaurantGalleryItem) {
                $restaurantGalleryItem = applyManagedMediaOverrides($restaurantGalleryItem, 'restaurant_gallery', $restaurantGalleryItem['id'] ?? '', ['image_path']);
            }
            unset($restaurantGalleryItem);
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching gallery: " . $e->getMessage());
}


// Fetch policies for footer modals
$policies = [];
try {
    $policyStmt = $pdo->query("SELECT slug, title, summary, content FROM policies WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $policies = $policyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching policies: " . $e->getMessage());
}

// Fetch Matuwi Kitchen welcome section
$matuwi_welcome = [];
try {
    $welcomeStmt = $pdo->prepare("SELECT * FROM section_headers WHERE section_key = ? AND page = ? AND is_active = 1");
    $welcomeStmt->execute(['matuwi_welcome', 'restaurant']);
    $matuwi_welcome = $welcomeStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching Matuwi welcome: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#1A1A1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=yes">
    <title>Fine Dining Restaurant - <?php echo htmlspecialchars($site_name); ?> | Gourmet Cuisine</title>
    <meta name="description" content="Experience exquisite fine dining at <?php echo htmlspecialchars($site_name); ?>. Fresh local cuisine, international dishes, craft cocktails, and premium bar service in an elegant setting.">
    <meta name="keywords" content="<?php echo htmlspecialchars(getSetting('default_keywords', 'fine dining, gourmet restaurant, international cuisine, luxury restaurant')); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/restaurant.php">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="restaurant">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/restaurant.php">
    <meta property="og:title" content="Fine Dining Restaurant - <?php echo htmlspecialchars($site_name); ?>">
    <meta property="og:description" content="Experience exquisite fine dining with fresh local cuisine, international dishes, and premium bar service.">
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/images/restaurant/hero.jpg">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/restaurant.php">
    <meta property="twitter:title" content="Fine Dining Restaurant - <?php echo htmlspecialchars($site_name); ?>">
    <meta property="twitter:description" content="Experience exquisite fine dining with fresh local cuisine, international dishes, and premium bar service.">
    <meta property="twitter:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/images/restaurant/hero.jpg">
    
    <!-- REMOVED: Preload Critical Resources - Preventing aggressive caching for page transitions -->
    <!-- Preloading disabled to allow smooth page transition animations -->
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet"></noscript>
    
    <!-- Font Awesome --><noscript></noscript>
    
    <!-- Main CSS - Loads all stylesheets in correct order -->
    <link rel="stylesheet" href="css/base/critical.css">
    <link rel="stylesheet" href="css/main.css">
    
    <!-- Structured Data - Restaurant Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Restaurant",
      "name": "<?php echo htmlspecialchars($site_name); ?> Restaurant",
      "image": "https://<?php echo $_SERVER['HTTP_HOST']; ?>/images/restaurant/hero.jpg",
      "description": "Fine dining restaurant offering fresh local cuisine, international dishes, and premium bar service",
      "servesCuisine": ["International", "African", "Continental"],
      "priceRange": "$$$",
      "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>/restaurant.php"
    }
    </script>
    
</head>
<body>
    <?php include 'includes/loader.php'; ?>
    
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <?php include 'includes/hero.php'; ?>

    <main>

    <!-- Matuwi Kitchen Welcome Section -->
    <?php if (!empty($matuwi_welcome)): ?>
    <section class="matuwi-welcome-section section-padding">
        <div class="container">
            <div class="matuwi-welcome-content">
                <?php if (!empty($matuwi_welcome['section_label'])): ?>
                <span class="matuwi-welcome-label">
                    <?php echo htmlspecialchars($matuwi_welcome['section_label']); ?>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($matuwi_welcome['section_title'])): ?>
                <h2 class="matuwi-welcome-title">
                    <?php echo htmlspecialchars($matuwi_welcome['section_title']); ?>
                </h2>
                <?php endif; ?>
                
                <?php if (!empty($matuwi_welcome['section_description'])): ?>
                <div class="matuwi-welcome-text">
                    <?php echo nl2br(htmlspecialchars($matuwi_welcome['section_description'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Menu Section -->
    <section class="restaurant-menu section-padding">
        <div class="container">
            <?php renderSectionHeader('restaurant_menu', 'restaurant', [
                'label' => 'Culinary Delights',
                'title' => 'Our Menu',
                'description' => 'Discover our carefully curated selection of dishes and beverages'
            ], 'text-center'); ?>

            <!-- Menu Container -->
            <div class="menu-container">
                <!-- Restaurant Hero Actions (moved here) -->
                <div class="restaurant-hero-actions">
                    <a href="<?php echo !empty($restaurant_contact_email) ? 'mailto:' . rawurlencode($restaurant_contact_email) : '#contact'; ?>" class="btn btn-primary"><i class="fas fa-utensils"></i> Reserve a Table</a>
                    <a href="<?php echo !empty($restaurant_phone) ? 'tel:' . preg_replace('/[^0-9+]/', '', $restaurant_phone) : '#contact'; ?>" class="btn btn-outline"><i class="fas fa-phone"></i> Call Restaurant</a>
                </div>

                <!-- Menu Control Bar: QR + Tabs -->
                <div class="menu-control-bar" data-aos="fade-up">
                    <!-- Left: QR panel -->
                    <div class="qr-menu-panel">
                        <div class="qr-menu-brand">
                            <span class="qr-menu-mark"><i class="fas fa-qrcode"></i> Scan &amp; Dine</span>
                            <h3 class="qr-menu-title">Digital Menu</h3>
                            <p class="qr-menu-desc">Browse our full menu on your phone. Scan the QR code or tap below to view dishes, prices, and save a copy as PDF.</p>
                            <div class="qr-menu-actions">
                                <a class="btn" href="<?php echo htmlspecialchars($menu_view_url); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> View Menu</a>
                                <a class="btn btn-outline" href="<?php echo htmlspecialchars($menu_pdf_url); ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Save as PDF</a>
                            </div>
                            <div class="qr-menu-meta">
                                <i class="fas fa-sync-alt"></i>
                                <span>Always up to date</span>
                                <span aria-hidden="true">·</span>
                                <span>Live from our kitchen</span>
                            </div>
                        </div>
                        <div class="qr-menu-qr">
                            <div class="qr-wrap">
                                <div class="qr-pulse-ring"></div>
                                <div class="qr-code-container">
                                    <img src="<?php echo $menu_qr_image; ?>" alt="QR code to view the restaurant menu" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22%3E%3Crect fill=%22%23ffffff%22 width=%22200%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22monospace%22 font-size=%2214%22 fill=%22%23000%22%3EScan QR Code%3C/text%3E%3Crect x=%2240%22 y=%2240%22 width=%2230%22 height=%2230%22 fill=%22none%22 stroke=%22%23000%22 stroke-width=%224%22/%3E%3Crect x=%22130%22 y=%2240%22 width=%2230%22 height=%2230%22 fill=%22none%22 stroke=%22%23000%22 stroke-width=%224%22/%3E%3Crect x=%2240%22 y=%22130%22 width=%2230%22 height=%2230%22 fill=%22none%22 stroke=%22%23000%22 stroke-width=%224%22/%3E%3Crect x=%2275%22 y=%2275%22 width=%2250%22 height=%2250%22 fill=%22%23000%22/%3E%3C/svg%3E';">
                                    <div class="scan-corners"><span></span></div>
                                </div>
                                <span class="qr-label"><i class="fas fa-qrcode"></i> Scan to View Menu</span>
                                <span class="qr-subtitle">Instant access on your device</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Segmented tabs -->
                    <div class="menu-type-tabs-wrap">
                        <div class="menu-type-tabs">
                            <button type="button" class="menu-type-tab active" data-type="food">
                                <i class="fas fa-utensils"></i> Food Menu
                            </button>
                            <button type="button" class="menu-type-tab" data-type="coffee">
                                <i class="fas fa-coffee"></i> Coffee
                            </button>
                            <button type="button" class="menu-type-tab" data-type="bar">
                                <i class="fas fa-glass-martini-alt"></i> Bar & Drinks
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div class="menu-loading" id="menuLoading">
                    <div class="menu-loading-spinner"></div>
                    <p class="menu-loading-text">Loading menu...</p>
                </div>

                <!-- Menu Categories Wrapper -->
                <div class="menu-categories-wrapper" id="menuCategoriesWrapper" aria-live="polite" aria-busy="false">
                    <!-- Category Tabs -->
                    <div class="menu-tabs" id="menuTabs" role="tablist" aria-label="Menu categories"></div>
                    
                    <!-- Menu Content -->
                    <div id="menuContent" class="menu-content-panels"></div>
                </div>
            </div>
        </div>
    </section>


        <!-- Passalacqua-Inspired Editorial Restaurant Gallery Grid (moved below menu) -->
        <section class="editorial-gallery-section section-padding">
            <div class="container">
                <?php renderSectionHeader('restaurant_gallery', 'restaurant', [
                    'label' => 'Visual Journey',
                    'title' => 'Our Dining Spaces',
                    'description' => 'From elegant interiors to breathtaking views, every detail creates the perfect ambiance'
                ], 'text-center'); ?>
                <div class="editorial-gallery-grid">
                    <?php if (!empty($gallery_images)): ?>
                        <?php foreach ($gallery_images as $index => $image): ?>
                            <div class="editorial-gallery-item">
                                <?php if (!empty($image['video_path'])): ?>
                                    <?php echo renderVideoEmbed($image['video_path'], $image['video_type'] ?? null, [
                                        'autoplay' => false,
                                        'muted' => true,
                                        'controls' => true,
                                        'loop' => false,
                                        'lazy' => true,
                                        'preload' => 'metadata',
                                        'class' => 'restaurant-gallery-video',
                                        'style' => 'width:100%;height:100%;object-fit:cover;'
                                    ]); ?>
                                <?php else: ?>
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($image['caption']); ?>" loading="lazy">
                                <?php endif; ?>
                                <div class="editorial-gallery-caption"><?php echo htmlspecialchars($image['caption']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback images if database is empty -->
                        <div class="editorial-gallery-item"><img src="images/restaurant/dining-area-1.jpg" alt="Elegant Dining Area" loading="lazy"><div class="editorial-gallery-caption">Elegant Dining Area</div></div>
                        <div class="editorial-gallery-item"><img src="images/restaurant/dining-area-2.jpg" alt="Intimate Indoor Seating" loading="lazy"><div class="editorial-gallery-caption">Intimate Indoor Seating</div></div>
                        <div class="editorial-gallery-item"><img src="images/restaurant/bar-area.jpg" alt="Premium Bar" loading="lazy"><div class="editorial-gallery-caption">Premium Bar</div></div>
                        <div class="editorial-gallery-item"><img src="images/restaurant/food-platter.jpg" alt="Fresh Seafood" loading="lazy"><div class="editorial-gallery-caption">Fresh Seafood</div></div>
                        <div class="editorial-gallery-item"><img src="images/restaurant/fine-dining.jpg" alt="Fine Dining Experience" loading="lazy"><div class="editorial-gallery-caption">Fine Dining Experience</div></div>
                        <div class="editorial-gallery-item"><img src="images/restaurant/outdoor-terrace.jpg" alt="Alfresco Terrace" loading="lazy"><div class="editorial-gallery-caption">Alfresco Terrace</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>



        <!-- Passalacqua-Inspired Editorial Restaurant Experience Section -->
        <section class="editorial-experience-section section-padding">
            <div class="container">
                <div class="editorial-experience-grid">
                    <div class="editorial-experience-item">
                        <div class="editorial-experience-icon"><i class="fas fa-utensils"></i></div>
                        <h3>Fine Dining</h3>
                        <div class="editorial-experience-divider"></div>
                        <p>Experience culinary artistry with our carefully crafted menu featuring local Malawian flavors and international cuisine</p>
                    </div>
                    <div class="editorial-experience-item">
                        <div class="editorial-experience-icon"><i class="fas fa-cocktail"></i></div>
                        <h3>Premium Bar</h3>
                        <div class="editorial-experience-divider"></div>
                        <p>Enjoy handcrafted cocktails, fine wines, and premium spirits in our elegant bar lounge</p>
                    </div>
                    <div class="editorial-experience-item">
                        <div class="editorial-experience-icon"><i class="fas fa-fish"></i></div>
                        <h3>Fresh Local Ingredients</h3>
                        <div class="editorial-experience-divider"></div>
                        <p>We source the freshest chambo from Lake Malawi and seasonal produce from local farms</p>
                    </div>
                    <div class="editorial-experience-item">
                        <div class="editorial-experience-icon"><i class="fas fa-sun"></i></div>
                        <h3>Alfresco Dining</h3>
                        <div class="editorial-experience-divider"></div>
                        <p>Dine under the stars on our terrace with breathtaking views of the surrounding landscape</p>
                    </div>
                </div>
            </div>
        </section>

    </main>
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <!-- Unified Navigation System - MUST LOAD FIRST -->
    <script src="js/navigation-unified.js" defer></script>
    <script src="js/modal.js"></script>
    <script src="js/main.js"></script>
    <script src="js/spatial-loading.js" defer></script>
    <script>
        // Currency settings (from PHP)
        const currencySymbol = '<?php echo $currency_symbol; ?>';
        const currencyCode = '<?php echo $currency_code; ?>';
        
        // Current menu state
        let currentMenuType = 'food';
        let currentCategory = null;
        let menuData = null;
        
        // DOM Elements
        const menuTypeTabs = document.querySelectorAll('.menu-type-tab');
        const menuTabs = document.getElementById('menuTabs');
        const menuContent = document.getElementById('menuContent');
        const menuLoading = document.getElementById('menuLoading');
        const menuCategoriesWrapper = document.getElementById('menuCategoriesWrapper');
        
        // Fetch menu data via AJAX
        async function fetchMenuData(menuType) {
            showLoading();
            
            try {
                const response = await fetch(`restaurant.php?ajax=menu&menu_type=${menuType}`);
                const data = await response.json();
                
                if (data.success) {
                    menuData = data;
                    renderMenu(data);
                } else {
                    showError(data.error || 'Failed to load menu');
                }
            } catch (error) {
                console.error('Error fetching menu:', error);
                showError('An error occurred while loading the menu');
            } finally {
                hideLoading();
            }
        }
        
        // Show loading state
        function showLoading() {
            menuLoading.classList.add('active');
            menuCategoriesWrapper.classList.add('is-loading');
            menuCategoriesWrapper.setAttribute('aria-busy', 'true');
        }
        
        // Hide loading state
        function hideLoading() {
            menuLoading.classList.remove('active');
            menuCategoriesWrapper.classList.remove('is-loading');
            menuCategoriesWrapper.setAttribute('aria-busy', 'false');
        }
        
        // Show error state
        function showError(message) {
            menuContent.innerHTML = `
                <div class="menu-empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Unable to Load Menu</h3>
                    <p>${message}</p>
                    <button class="btn btn-primary mt-30" onclick="fetchMenuData('${currentMenuType}')">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
            menuTabs.innerHTML = '';
        }
        
        // Render menu
        function renderMenu(data) {
            const categories = Object.values(data.categories);
            
            if (categories.length === 0) {
                menuTabs.innerHTML = '';
                const adminHint = data.admin_hint
                    ? `<p class="menu-empty-admin-hint">${escapeHtml(data.admin_hint)}</p>`
                    : '';
                menuContent.innerHTML = `
                    <div class="menu-empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No Items Available</h3>
                        <p>Menu items for this category are coming soon. Please contact our restaurant for current offerings.</p>
                        ${adminHint}
                    </div>
                `;
                return;
            }
            
            // Render category tabs
            menuTabs.innerHTML = categories.map((cat, index) => `
                <button
                    type="button"
                    class="menu-tab ${index === 0 ? 'active' : ''}"
                    data-category="${cat.slug}"
                    role="tab"
                    aria-selected="${index === 0 ? 'true' : 'false'}"
                    aria-controls="menu-panel-${cat.slug}"
                    id="menu-tab-${cat.slug}"
                >
                    <span class="menu-tab-name">${cat.name}</span>
                    <span class="menu-tab-count">${cat.items.length}</span>
                </button>
            `).join('');
            
            // Render menu content
            menuContent.innerHTML = categories.map((cat, index) => `
                <div
                    class="menu-panel ${index === 0 ? 'active' : ''}"
                    data-category="${cat.slug}"
                    id="menu-panel-${cat.slug}"
                    role="tabpanel"
                    aria-labelledby="menu-tab-${cat.slug}"
                >
                    <div class="menu-panel-header">
                        <h3 class="menu-panel-title">${cat.name}</h3>
                        <p class="menu-panel-subtitle">${cat.items.length} ${cat.items.length === 1 ? 'item' : 'items'}</p>
                    </div>
                    <div class="menu-items-grid">
                        ${cat.items.map(item => renderMenuItem(item, data.menu_type)).join('')}
                    </div>
                </div>
            `).join('');
            
            // Set current category to first one
            currentCategory = categories[0].slug;
            
            // Add event listeners to category tabs
            menuTabs.querySelectorAll('.menu-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    switchCategory(category);
                });
            });
        }
        
        // Render single menu item
        function renderMenuItem(item, menuType) {
            if (menuType === 'food') {
                return `
                    <div class="menu-item ${item.is_featured ? 'featured' : ''}">
                        <div class="menu-item-header">
                            <div class="menu-item-title">
                                <h3 class="menu-item-name">${escapeHtml(item.name)}</h3>
                                ${item.is_featured ? '<span class="featured-badge"><i class="fas fa-star"></i> Chef\'s Special</span>' : ''}
                            </div>
                            <span class="menu-item-price">${currencySymbol}${item.price.toFixed(2)}</span>
                        </div>
                        ${item.description ? `<p class="menu-item-description">${escapeHtml(item.description)}</p>` : ''}
                        <div class="menu-item-tags">
                            ${item.is_vegetarian ? '<span class="tag tag-vegetarian"><i class="fas fa-leaf"></i> Vegetarian</span>' : ''}
                            ${item.is_vegan ? '<span class="tag tag-vegan"><i class="fas fa-seedling"></i> Vegan</span>' : ''}
                            ${item.allergens ? `<span class="tag tag-allergen"><i class="fas fa-exclamation-triangle"></i> ${escapeHtml(item.allergens)}</span>` : ''}
                        </div>
                    </div>
                `;
            }

            return `
                <div class="menu-item">
                    <div class="menu-item-header">
                        <h3 class="menu-item-name">${escapeHtml(item.name)}</h3>
                        <span class="menu-item-price">${currencySymbol}${item.price.toFixed(2)}</span>
                    </div>
                    ${item.description ? `<p class="menu-item-description">${escapeHtml(item.description)}</p>` : ''}
                    ${item.tags && item.tags.length > 0 ? `
                        <div class="menu-item-tags">
                            ${item.tags.map(tag => `<span class="tag tag-drink"><i class="fas fa-tag"></i> ${escapeHtml(tag)}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Switch category
        function switchCategory(category) {
            currentCategory = category;
            
            // Update active tab
            menuTabs.querySelectorAll('.menu-tab').forEach(tab => {
                tab.classList.toggle('active', tab.getAttribute('data-category') === category);
                tab.setAttribute('aria-selected', tab.getAttribute('data-category') === category ? 'true' : 'false');
            });
            
            // Update active category
            menuContent.querySelectorAll('.menu-panel').forEach(cat => {
                cat.classList.toggle('active', cat.getAttribute('data-category') === category);
            });
        }
        
        // Switch menu type
        function switchMenuType(menuType) {
            if (currentMenuType === menuType) return;
            
            currentMenuType = menuType;
            currentCategory = null;
            
            // Update active type tab
            menuTypeTabs.forEach(tab => {
                tab.classList.toggle('active', tab.getAttribute('data-type') === menuType);
            });
            
            // Fetch new menu data
            fetchMenuData(menuType);
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Initialize menu type tabs
        menuTypeTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const menuType = this.getAttribute('data-type');
                switchMenuType(menuType);
            });
        });
        
        // Load default menu on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetchMenuData('food');
        });
        
        // Page loader
        window.addEventListener('load', function() {
            const pageLoader = document.querySelector('.page-loader');
            if (pageLoader) {
                pageLoader.classList.add('fade-out');
                setTimeout(() => {
                    pageLoader.style.display = 'none';
                }, 500);
            }
        });
    </script>

    <!-- Gallery scroll-reveal animations -->
    <script>
        (function () {
            var galleryItems = document.querySelectorAll('.editorial-gallery-item');
            if (!galleryItems.length) return;

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var item = entry.target;
                            var delay = Array.from(galleryItems).indexOf(item) * 80;
                            setTimeout(function () {
                                item.classList.add('visible');
                            }, delay);
                            observer.unobserve(item);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

                galleryItems.forEach(function (item) { observer.observe(item); });
            } else {
                galleryItems.forEach(function (item, index) {
                    setTimeout(function () { item.classList.add('visible'); }, index * 100);
                });
            }
        })();
    </script>

    <?php include 'includes/scroll-to-top.php'; ?>
</body>
</html>
