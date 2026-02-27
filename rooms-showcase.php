<?php
require_once 'config/database.php';
require_once 'includes/page-guard.php';
require_once 'includes/reviews-display.php';
require_once 'includes/section-headers.php';
require_once 'includes/booking-functions.php';

// Initialize required variables
$site_name = getSetting('site_name', 'Your Hotel Name');
$currency_symbol = getSetting('currency_symbol', '$');
$phone_main = getSetting('phone_main', '');
$email_reservations = getSetting('email_reservations', '');

// Fetch rooms from database
$rooms = [];
try {
    $stmt = $pdo->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rooms = [];
}

// Set hero room to first room if available
$hero_room = isset($rooms[0]) ? $rooms[0] : null;

?>
    <!-- Passalacqua-Inspired Editorial Room Hero Section -->
    <?php if ($hero_room): ?>
    <section class="editorial-events-section editorial-room-hero-section">
        <div class="container">
            <?php
            // Fetch gallery images for hero room
            $hero_gallery = [];
            try {
                $stmt = $pdo->prepare("SELECT title, image_url FROM gallery WHERE room_id = ? AND is_active = 1 AND image_url IS NOT NULL AND image_url != '' ORDER BY display_order ASC, id ASC LIMIT 4");
                $stmt->execute([$hero_room['id']]);
                $hero_gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($hero_gallery) && !empty($hero_room['image_url'])) {
                    $hero_gallery = [['image_url' => $hero_room['image_url'], 'title' => $hero_room['name']]];
                }
            } catch (PDOException $e) {
                $hero_gallery = [['image_url' => $hero_room['image_url'], 'title' => $hero_room['name']]];
            }
            ?>
            <?php if (!empty($hero_gallery)): ?>
            <div class="editorial-rooms-gallery-grid">
                <?php foreach ($hero_gallery as $img): ?>
                <div class="editorial-room-image-container">
                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="<?php echo htmlspecialchars($img['title'] ?? $hero_room['name']); ?>" class="editorial-room-image">
                    <div class="gallery-item-label"><?php echo htmlspecialchars($img['title'] ?? $hero_room['name']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="editorial-room-content">
                <h2 class="editorial-room-title"><?php echo htmlspecialchars($hero_room['name']); ?></h2>
                <p class="editorial-room-description"><?php echo htmlspecialchars($hero_room['description'] ?? $hero_room['short_description']); ?></p>
                <div class="editorial-room-meta">
                    <span><i class="fas fa-users"></i> Up to <?php echo htmlspecialchars($hero_room['max_guests'] ?? 2); ?> guests</span>
                    <span><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($hero_room['size_sqm'] ?? 40); ?> sqm</span>
                    <span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($hero_room['bed_type']); ?></span>
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($currency_symbol); ?><?php echo number_format($hero_room['price_per_night'], 0); ?> per night</span>
                </div>
                <div class="editorial-room-amenities">
                    <?php 
                    $amenities = explode(',', $hero_room['amenities'] ?? '');
                    foreach ($amenities as $amenity): 
                    ?>
                    <span class="editorial-room-amenity"><i class="fas fa-check"></i> <?php echo htmlspecialchars(trim($amenity)); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
<?php
// Extract unique badges for filters
$badges = ['All Rooms'];
$badge_counts = [];
foreach ($rooms as $room) {
    if (!empty($room['badge'])) {
        if (!in_array($room['badge'], $badges)) {
            $badges[] = $room['badge'];
        }
        $badge_counts[$room['badge']] = isset($badge_counts[$room['badge']]) ? $badge_counts[$room['badge']] + 1 : 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    $seo_data = [
        'title' => $site_name . ' | Rooms & Suites',
        'description' => "Explore contemporary rooms and suites at {$site_name} with seamless booking integration.",
        'type' => 'website'
    ];
    require_once 'includes/seo-meta.php';
    ?>
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=yes">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Main CSS - Loads all stylesheets in correct order -->
    <link rel="stylesheet" href="css/main.css">
    </head>
<body class="rooms-page">
    <?php include 'includes/loader.php'; ?>
    
    <?php include 'includes/header.php'; ?>

    <main>
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" role="presentation"></div>

    <!-- Hero Section -->
    <?php include 'includes/hero.php'; ?>

    <!-- Room Images Grid Section -->
    <section class="section section-compact">
        <div class="container">
            <!-- Desktop: Grid Layout - Mobile: Stack Layout -->
            <?php
            // Fetch gallery images for hero room
            $hero_gallery = [];
            try {
                $stmt = $pdo->prepare("SELECT title, image_url FROM gallery WHERE room_id = ? AND is_active = 1 AND image_url IS NOT NULL AND image_url != '' ORDER BY display_order ASC, id ASC LIMIT 4");
                $stmt->execute([$hero_room['id']]);
                $hero_gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fallback to featured image if no gallery images
                if (empty($hero_gallery) && !empty($hero_room['image_url'])) {
                    $hero_gallery = [['image_url' => $hero_room['image_url'], 'title' => $hero_room['name']]];
                }
            } catch (PDOException $e) {
                $hero_gallery = [['image_url' => $hero_room['image_url'], 'title' => $hero_room['name']]];
            }
            ?>
            <?php if (!empty($hero_gallery)): ?>
            <div class="room-gallery-grid">
                <?php foreach ($hero_gallery as $img): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="<?php echo htmlspecialchars($img['title'] ?? $hero_room['name']); ?>">
                    <div class="gallery-item-label"><?php echo htmlspecialchars($img['title'] ?? $hero_room['name']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="room-detail-info">
                <h2 class="room-detail-title"><?php echo htmlspecialchars($hero_room['name']); ?></h2>
                <p class="room-detail-description"><?php echo htmlspecialchars($hero_room['description'] ?? $hero_room['short_description']); ?></p>
                
                <div class="room-detail-specs">
                    <div class="spec-item">
                        <i class="fas fa-users"></i>
                        <div class="spec-label">Guests</div>
                        <div class="spec-value">Up to <?php echo htmlspecialchars($hero_room['max_guests'] ?? 2); ?></div>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-ruler-combined"></i>
                        <div class="spec-label">Floor Space</div>
                        <div class="spec-value"><?php echo htmlspecialchars($hero_room['size_sqm'] ?? 40); ?> sqm</div>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-bed"></i>
                        <div class="spec-label">Bed Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars($hero_room['bed_type']); ?></div>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-tag"></i>
                        <div class="spec-label">Nightly Rate</div>
                        <div class="spec-value"><?php echo htmlspecialchars($currency_symbol); ?><?php echo number_format($hero_room['price_per_night'], 0); ?></div>
                    </div>
                </div>

                <div class="amenities-list">
                    <h3 class="amenities-title">Room Amenities</h3>
                    <div class="amenities-list">
                        <?php
                        $amenities = explode(',', $hero_room['amenities'] ?? '');
                        foreach ($amenities as $amenity):
                        ?>
                        <span class="amenity-tag">
                            <i class="fas fa-check"></i><?php echo htmlspecialchars(trim($amenity)); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Modern Image Gallery Grid -->
            <div class="room-gallery-grid">
                <?php
                // Fetch gallery images for this room from database
                try {
                    $stmt = $pdo->prepare("
                        SELECT title, description, image_url 
                        FROM gallery 
                        WHERE room_id = ? AND category = 'rooms' AND is_active = 1
                        ORDER BY display_order ASC, created_at ASC
                        LIMIT 4
                    ");
                    $stmt->execute([$hero_room['id']]);
                    $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // If we have gallery images, show them
                    if (!empty($gallery_images)) {
                        foreach ($gallery_images as $gallery_img) {
                            ?>
                            <div class="gallery-item">
                                <img src="<?php echo htmlspecialchars($gallery_img['image_url']); ?>" alt="<?php echo htmlspecialchars($gallery_img['title']); ?>">
                                <div class="gallery-item-label"><?php echo htmlspecialchars($gallery_img['title']); ?></div>
                            </div>
                            <?php
                        }
                    } else {
                        // Fallback to hero image if no gallery images
                        ?>
                        <div class="gallery-item">
                            <img src="<?php echo htmlspecialchars($hero_room['image_url']); ?>" alt="<?php echo htmlspecialchars($hero_room['name']); ?> - Main View">
                            <div class="gallery-item-label">Main View</div>
                        </div>
                        <?php
                    }
                } catch (PDOException $e) {
                    // Fallback on error
                    ?>
                    <div class="gallery-item">
                        <img src="<?php echo htmlspecialchars($hero_room['image_url']); ?>" alt="<?php echo htmlspecialchars($hero_room['name']); ?> - Main View">
                        <div class="gallery-item-label">Main View</div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </section>

    <section class="section" id="collection">
            <div class="container">
                <?php renderSectionHeader('rooms_collection', 'rooms-showcase', [
                    'label' => 'Stay Collection',
                    'title' => 'Pick Your Perfect Space',
                    'description' => 'Suites and rooms crafted for business, romance, and family stays with direct booking flows'
                ]); ?>

                <div class="rooms-filter">
                    <?php foreach ($badges as $badge): ?>
                    <span class="chip <?php echo $badge === 'All Rooms' ? 'active' : ''; ?>" data-filter="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $badge))); ?>">
                        <?php echo htmlspecialchars($badge); ?>
                    </span>
                    <?php endforeach; ?>
                </div>

                <div class="card-grid">
                    <?php
                    $roomIndex = 0;
                    foreach ($rooms as $room):
                        $amenities = array_slice(explode(',', $room['amenities']), 0, 4);
                        $isFeatured = !empty($room['is_featured']) || !empty($room['badge']);
                        $spanClass = $isFeatured && $roomIndex === 0 ? 'span-2' : '';
                        $aspectClass = 'aspect-square';
                    ?>
                    <article class="card room-card <?php echo $isFeatured ? 'featured-card' : ''; ?>"
                             tabindex="0"
                             data-room-id="<?php echo (int)$room['id']; ?>"
                             data-room-slug="<?php echo htmlspecialchars($room['slug']); ?>"
                             data-badge="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $room['badge'] ?? 'all-rooms'))); ?>"
                             data-filter="all-rooms <?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $room['badge'] ?? ''))); ?>">
                        <div class="room-card-image-bg"></div>
                        <a class="room-card-image" href="rooms-showcase.php?room=<?php echo urlencode($room['slug']); ?>#book" aria-label="Open booking for <?php echo htmlspecialchars($room['name']); ?>">
                            <img src="<?php echo htmlspecialchars($room['image_url']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" class="room-card-image-img">
                            <?php if (!empty($room['badge'])): ?>
                            <span class="room-card-badge"><?php echo htmlspecialchars($room['badge']); ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="room-card-content">
                            <h3 class="room-card-title"><?php echo htmlspecialchars($room['name']); ?></h3>
                            <p class="room-card-description"><?php echo htmlspecialchars($room['short_description']); ?></p>
                                </div>
                                <div class="room-tile__price">
                                    <span class="amount"><?php echo htmlspecialchars($currency_symbol); ?><?php echo number_format($room['price_per_night'], 0); ?></span>
                                    <small>per night</small>
                                </div>
                            </div>
                            <!-- Compact Rating Display -->
                            <div class="room-tile__rating" data-room-id="<?php echo (int)$room['id']; ?>">
                                <div class="compact-rating compact-rating--loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                            <div class="room-card-meta">
                                <span class="room-card-meta-item"><i class="fas fa-user-friends"></i> <?php echo htmlspecialchars($room['max_guests']); ?> guests</span>
                                <span class="room-card-meta-item"><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($room['size_sqm']); ?> sqm</span>
                                <span class="room-card-meta-item"><i class="fas fa-bed"></i> <?php echo htmlspecialchars($room['bed_type']); ?></span>
                            </div>
                            <div class="room-tile__amenities">
                                <?php foreach ($amenities as $amenity): ?>
                                <span class="pill-small"><?php echo htmlspecialchars(trim($amenity)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="room-tile__actions">
                                <a class="btn btn-outline" href="rooms-showcase.php?room=<?php echo urlencode($room['slug']); ?>">View Details</a>
                                <?php if (isBookingEnabled()): ?>
                                <a class="btn btn-primary" href="index.php?room=<?php echo urlencode($room['slug']); ?>#book" data-room-book="<?php echo htmlspecialchars($room['slug']); ?>">Book Now</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php
                        $roomIndex++;
                    endforeach;
                    ?>
                </div>
                <script>
                // 3D card tilt effect for room tiles
                document.querySelectorAll('.fancy-3d-card').forEach(card => {
                  card.addEventListener('mousemove', function(e) {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = ((y - centerY) / centerY) * 10;
                    const rotateY = ((x - centerX) / centerX) * 10;
                    card.style.transform = `perspective(900px) rotateX(${-rotateX}deg) rotateY(${rotateY}deg) scale(1.04)`;
                  });
                  card.addEventListener('mouseleave', function() {
                    card.style.transform = '';
                  });
                  card.addEventListener('focus', function() {
                    card.style.boxShadow = '0 0 0 4px var(--gold)';
                  });
                  card.addEventListener('blur', function() {
                    card.style.boxShadow = '';
                  });
                });
                </script>

                <!-- Fetch and display room ratings -->
                <script>
                (function() {
                    const ratingContainers = document.querySelectorAll('.room-tile__rating');
                    
                    ratingContainers.forEach(container => {
                        const roomId = container.dataset.roomId;
                        
                        fetch(`admin/api/reviews.php?room_id=${roomId}&status=approved`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.averages) {
                                    const avgRating = data.averages.avg_rating || 0;
                                    const totalCount = data.total_count || 0;
                                    
                                    if (totalCount > 0) {
                                        let starsHtml = '';
                                        const fullStars = Math.floor(avgRating);
                                        const hasHalfStar = (avgRating - fullStars) >= 0.5;
                                        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

                                        for (let i = 0; i < fullStars; i++) {
                                            starsHtml += '<i class="fas fa-star"></i>';
                                        }
                                        if (hasHalfStar) {
                                            starsHtml += '<i class="fas fa-star-half-alt"></i>';
                                        }
                                        for (let i = 0; i < emptyStars; i++) {
                                            starsHtml += '<i class="far fa-star"></i>';
                                        }

                                        container.innerHTML = `
                                            <div class="compact-rating">
                                                <div class="compact-rating__stars">${starsHtml}</div>
                                                <div class="compact-rating__info">
                                                    <span class="compact-rating__score">${avgRating.toFixed(1)}</span>
                                                    <span class="compact-rating__count">(${totalCount})</span>
                                                </div>
                                            </div>
                                        `;
                                    } else {
                                        container.innerHTML = `
                                            <div class="compact-rating compact-rating--no-reviews">
                                                <i class="far fa-star"></i>
                                                <span>No reviews</span>
                                            </div>
                                        `;
                                    }
                                } else {
                                    container.innerHTML = `
                                        <div class="compact-rating compact-rating--no-reviews">
                                            <i class="far fa-star"></i>
                                            <span>No reviews</span>
                                        </div>
                                    `;
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching room rating:', error);
                                container.innerHTML = `
                                    <div class="compact-rating compact-rating--no-reviews">
                                        <i class="far fa-star"></i>
                                        <span>No reviews</span>
                                    </div>
                                `;
                            });
                    });
                })();
                </script>
            </div>
        </section>

        <section class="booking-cta" id="book">
            <div class="container booking-cta__grid">
                <div class="booking-cta__content">
                    <div class="pill">Direct Booking</div>
                    <h2>Ready to reserve your stay?</h2>
                    <p>Pick your preferred suite and we will secure it instantly. Share your dates and guest count and our team will confirm right away.</p>
                    <div class="booking-cta__actions">
                        <a class="btn btn-primary" href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone_main)); ?>"><i class="fas fa-phone"></i> Call Reservations</a>
                        <a class="btn btn-outline" href="mailto:<?php echo htmlspecialchars($email_reservations); ?>?subject=Room%20Reservation"><i class="fas fa-envelope"></i> Email Booking</a>
                    </div>
                </div>
                <div class="booking-cta__card">
                    <div class="booking-cta__row">
                        <span>Featured Room</span>
                        <strong><?php echo htmlspecialchars($hero_room['name']); ?></strong>
                    </div>
                    <div class="booking-cta__row">
                        <span>Nightly Rate</span>
                        <strong><?php echo htmlspecialchars($currency_symbol); ?><?php echo number_format($hero_room['price_per_night'], 0); ?></strong>
                    </div>
                    <div class="booking-cta__row">
                        <span>Capacity</span>
                        <strong><?php echo htmlspecialchars($hero_room['max_guests'] ?? 2); ?> guests</strong>
                    </div>
                    <div class="booking-cta__row">
                        <span>Floor Space</span>
                        <strong><?php echo htmlspecialchars($hero_room['size_sqm'] ?? 40); ?> sqm</strong>
                    </div>
                    <a class="btn btn-primary" href="index.php?room=<?php echo urlencode($hero_room['slug'] ?? ''); ?>#book">Proceed to Booking</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts -->
    <script src="js/modal.js"></script>
    <script src="js/main.js"></script>
    <!-- Phase 3: Parallax Effects -->
    <script src="js/parallax-cards.js" defer></script>
    <script src="js/cursor-follower.js" defer></script>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>
