<?php
/**
 * Guest Services Page
 * Central hub for all hotel guest services - links to gym, conference, restaurant,
 * events, booking, and other amenities. Each section includes a hero, description,
 * and proper redirect to the dedicated service page.
 */

require_once 'config/database.php';
require_once 'includes/page-guard.php';
require_once 'includes/modal.php';
require_once 'includes/section-headers.php';
require_once 'includes/image-proxy-helper.php';

// Fetch site settings
$site_name      = getSetting('site_name');
$site_logo      = getSetting('site_logo');
$email_main     = getSetting('email_main');
$phone_main     = getSetting('phone_main');
$currency_symbol = getSetting('currency_symbol');

// Feature toggles
$bookingEnabled    = function_exists('isBookingEnabled') ? isBookingEnabled() : true;
$conferenceEnabled = function_exists('isConferenceEnabled') ? isConferenceEnabled() : true;
$gymEnabled        = function_exists('isGymEnabled') ? isGymEnabled() : true;
$restaurantEnabled = function_exists('isRestaurantEnabled') ? isRestaurantEnabled() : true;

// Ensure guest_services table exists for configurable service cards
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS guest_services (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        service_key VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        icon_class VARCHAR(100) DEFAULT 'fas fa-concierge-bell',
        image_path VARCHAR(500) DEFAULT NULL,
        link_url VARCHAR(500) DEFAULT NULL,
        link_text VARCHAR(100) DEFAULT 'Learn More',
        display_order INT UNSIGNED DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_service_key (service_key),
        KEY idx_active_order (is_active, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log("guest_services table check: " . $e->getMessage());
}

// Fetch services from DB
$dbServices = [];
try {
    $stmt = $pdo->query("SELECT * FROM guest_services WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $dbServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may be empty or just created
}

// Fallback services if DB is empty
if (empty($dbServices)) {
    $dbServices = [];

    if ($restaurantEnabled) {
        $dbServices[] = [
            'service_key' => 'restaurant',
            'title'       => 'Fine Dining Restaurant',
            'description' => 'Experience exquisite cuisine crafted by our talented chefs. From local delicacies to international dishes, our restaurant offers a culinary journey like no other.',
            'icon_class'  => 'fas fa-utensils',
            'image_path'  => 'images/restaurant/image.png',
            'link_url'    => 'restaurant.php',
            'link_text'   => 'View Restaurant',
        ];
    }

    if ($gymEnabled) {
        $dbServices[] = [
            'service_key' => 'gym',
            'title'       => 'Fitness & Wellness Center',
            'description' => 'Stay fit during your stay with our fully equipped fitness center. Personal training, group classes, and wellness packages available for all guests.',
            'icon_class'  => 'fas fa-dumbbell',
            'image_path'  => 'images/gym/fitness-center.jpg',
            'link_url'    => 'gym.php',
            'link_text'   => 'Explore Gym',
        ];
    }

    if ($conferenceEnabled) {
        $dbServices[] = [
            'service_key' => 'conference',
            'title'       => 'Conference & Meeting Rooms',
            'description' => 'Host your corporate events, meetings, and conferences in our state-of-the-art venues. Full AV equipment, catering, and dedicated event coordination.',
            'icon_class'  => 'fas fa-briefcase',
            'image_path'  => 'images/conference/conference_room.jpeg',
            'link_url'    => 'conference.php',
            'link_text'   => 'Book Conference',
        ];
    }

    $dbServices[] = [
        'service_key' => 'events',
        'title'       => 'Events & Entertainment',
        'description' => 'Discover upcoming events, live entertainment, and special occasions at our hotel. From business breakfasts to gala dinners, there is always something happening.',
        'icon_class'  => 'fas fa-calendar-alt',
        'image_path'  => 'images/hero/slide1.jpeg',
        'link_url'    => 'events.php',
        'link_text'   => 'View Events',
    ];

    if ($bookingEnabled) {
        $dbServices[] = [
            'service_key' => 'rooms',
            'title'       => 'Rooms & Accommodation',
            'description' => 'Discover our luxurious rooms and suites, each designed for comfort and elegance. Book your perfect stay with us today.',
            'icon_class'  => 'fas fa-bed',
            'image_path'  => 'images/rooms/Deluxe_Room.jpg',
            'link_url'    => 'booking.php',
            'link_text'   => 'Book a Room',
        ];
    }

    $dbServices[] = [
        'service_key' => 'concierge',
        'title'       => 'Concierge Services',
        'description' => 'Our dedicated concierge team is available 24/7 to assist with transportation, tours, restaurant reservations, and any special requests to make your stay memorable.',
        'icon_class'  => 'fas fa-concierge-bell',
        'image_path'  => null,
        'link_url'    => 'contact-us.php',
        'link_text'   => 'Contact Concierge',
    ];
}

// Contact info for CTA
$contact_phone = getSetting('phone_main');
$contact_email = getSetting('email_main');
$whatsapp      = getSetting('whatsapp_number');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#1A1A1A">
    <title>Guest Services - <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="Explore all guest services at <?php echo htmlspecialchars($site_name); ?>. Restaurant, gym, conference rooms, events, concierge, and more.">
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/guest-services.php">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet"></noscript>

    <!-- Main CSS -->
    <link rel="stylesheet" href="css/base/critical.css">
    <link rel="stylesheet" href="css/main.css">

    <style>
        /* Guest Services page styles */
        .gs-section { padding: 80px 0; }
        .gs-section .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        .gs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 32px;
            margin-top: 40px;
        }

        .gs-card {
            background: var(--color-surface, #fff);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid var(--color-border-light, #eee);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .gs-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
        }

        .gs-card__media {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(139, 115, 85, 0.15), rgba(139, 115, 85, 0.05));
        }
        .gs-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .gs-card:hover .gs-card__media img { transform: scale(1.05); }

        .gs-card__media-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 3rem;
            color: var(--gold, #8B7355);
            background: linear-gradient(135deg, rgba(139, 115, 85, 0.08), rgba(139, 115, 85, 0.02));
        }

        .gs-card__body {
            padding: 28px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .gs-card__icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(139, 115, 85, 0.1);
            color: var(--gold, #8B7355);
            border-radius: 14px;
            font-size: 1.3rem;
            margin-bottom: 16px;
        }

        .gs-card__title {
            font-family: var(--font-serif, 'Cormorant Garamond', serif);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--color-text-primary, #1A1A1A);
        }

        .gs-card__desc {
            color: var(--color-text-secondary, #666);
            font-size: 0.95rem;
            line-height: 1.6;
            flex: 1;
            margin-bottom: 20px;
        }

        .gs-card__cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--gold, #8B7355);
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            align-self: flex-start;
        }
        .gs-card__cta:hover { background: #7a6548; }
        .gs-card__cta i { font-size: 0.85rem; }

        /* Quick contact banner */
        .gs-contact-banner {
            background: linear-gradient(135deg, var(--navy, #1A1A1A), #2a2a2a);
            padding: 60px 0;
            text-align: center;
            color: #fff;
        }
        .gs-contact-banner .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .gs-contact-banner h2 {
            font-family: var(--font-serif, 'Cormorant Garamond', serif);
            font-size: 2rem;
            font-weight: 400;
            margin-bottom: 12px;
        }
        .gs-contact-banner p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 28px;
            font-size: 1rem;
        }
        .gs-contact-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .gs-contact-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .gs-contact-actions .btn-primary-gold {
            background: var(--gold, #8B7355);
            color: #fff;
        }
        .gs-contact-actions .btn-primary-gold:hover { background: #7a6548; }
        .gs-contact-actions .btn-outline-light {
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
        }
        .gs-contact-actions .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.5);
        }

        @media (max-width: 768px) {
            .gs-grid { grid-template-columns: 1fr; }
            .gs-contact-actions { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body class="guest-services-page">
    <?php include 'includes/loader.php'; ?>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <?php include 'includes/hero.php'; ?>

    <!-- Services Grid -->
    <section class="gs-section" id="services">
        <div class="container">
            <?php renderSectionHeader('guest_services_main', 'guest-services', [
                'label' => 'What We Offer',
                'title' => 'Our Guest Services',
                'description' => 'Everything you need for a comfortable and memorable stay'
            ], 'text-center'); ?>

            <div class="gs-grid">
                <?php foreach ($dbServices as $service): ?>
                <div class="gs-card">
                    <div class="gs-card__media">
                        <?php if (!empty($service['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars(function_exists('proxyImageUrl') ? proxyImageUrl($service['image_path']) : $service['image_path']); ?>"
                                 alt="<?php echo htmlspecialchars($service['title']); ?>"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="gs-card__media-placeholder">
                                <i class="<?php echo htmlspecialchars($service['icon_class']); ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="gs-card__body">
                        <div class="gs-card__icon">
                            <i class="<?php echo htmlspecialchars($service['icon_class']); ?>"></i>
                        </div>
                        <h3 class="gs-card__title"><?php echo htmlspecialchars($service['title']); ?></h3>
                        <p class="gs-card__desc"><?php echo htmlspecialchars($service['description']); ?></p>
                        <?php if (!empty($service['link_url'])): ?>
                        <a href="<?php echo htmlspecialchars($service['link_url']); ?>" class="gs-card__cta">
                            <i class="fas fa-arrow-right"></i>
                            <?php echo htmlspecialchars($service['link_text'] ?? 'Learn More'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact Banner -->
    <section class="gs-contact-banner">
        <div class="container">
            <h2>Need Assistance?</h2>
            <p>Our team is available around the clock to help with any request, big or small.</p>
            <div class="gs-contact-actions">
                <a href="contact-us.php" class="btn-primary-gold">
                    <i class="fas fa-envelope"></i> Contact Us
                </a>
                <?php if (!empty($contact_phone)): ?>
                <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $contact_phone)); ?>" class="btn-outline-light">
                    <i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($contact_phone); ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($contact_email)): ?>
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="btn-outline-light">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact_email); ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($whatsapp)): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $whatsapp)); ?>" target="_blank" rel="noopener" class="btn-outline-light">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/scroll-to-top.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script src="js/modal.js" defer></script>
</body>
</html>
