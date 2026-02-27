<?php
/**
 * Review Submission Form with Enhanced Security
 * Hotel Website - Guest Review Submission
*
 * Features:
 * - CSRF protection
 * - Secure session management
 * - Input validation
 */

// Start session first
session_start();

// Include base URL configuration for proper redirects
require_once 'config/base-url.php';

// Include database configuration
require_once 'config/database.php';

// Include alert system
require_once 'includes/alert.php';

// Include validation library
require_once 'includes/validation.php';

// ── CSRF Token Helpers (lightweight, public-page version) ──
if (!function_exists('generatePublicCsrfToken')) {
    function generatePublicCsrfToken(): string {
        if (empty($_SESSION['_csrf_review'])) {
            $_SESSION['_csrf_review'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_review'];
    }
    function validatePublicCsrfToken(string $token): bool {
        $expected = $_SESSION['_csrf_review'] ?? '';
        return $expected !== '' && hash_equals($expected, $token);
    }
}
$csrf_token = generatePublicCsrfToken();

// Initialize variables
$rooms = [];
$selected_room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$room_name = '';

// Brand assets
$site_name = getSetting('site_name');
$site_logo = getSetting('site_logo');

// Fetch available rooms for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get room name if room_id is provided
    if ($selected_room_id > 0) {
        $room_stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
        $room_stmt->execute([$selected_room_id]);
        $room_data = $room_stmt->fetch(PDO::FETCH_ASSOC);
        if ($room_data) {
            $room_name = $room_data['name'];
        }
    }
} catch (PDOException $e) {
    // Log error but continue
    error_log("Error fetching rooms: " . $e->getMessage());
}

// ── Reviews Overview (public, approved only) ───────────────────────────────
$reviews_summary = [
    'total' => 0,
    'approved' => 0,
    'responses' => 0,
    'avg_rating' => null,
];
$latest_reviews = [];

try {
    // Total reviews (all statuses)
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM reviews");
    $reviews_summary['total'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    // Approved reviews count
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM reviews WHERE status = 'approved'");
    $reviews_summary['approved'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    // Responses count
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM review_responses");
    $reviews_summary['responses'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    // Average rating on approved
    $stmt = $pdo->query("SELECT AVG(rating) AS avg_rating FROM reviews WHERE status='approved' AND rating IS NOT NULL");
    $avg = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? null;
    $reviews_summary['avg_rating'] = $avg !== null ? round((float)$avg, 2) : null;

    // Latest approved reviews with latest response (if any)
    $sql = "
        SELECT r.id, r.guest_name, r.title, r.comment, r.rating, r.created_at,
               (
                 SELECT rr.response FROM review_responses rr
                 WHERE rr.review_id = r.id
                 ORDER BY rr.created_at DESC
                 LIMIT 1
               ) AS latest_response,
               (
                 SELECT rr.created_at FROM review_responses rr
                 WHERE rr.review_id = r.id
                 ORDER BY rr.created_at DESC
                 LIMIT 1
               ) AS latest_response_date
        FROM reviews r
        WHERE r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 5";
    $stmt = $pdo->query($sql);
    $latest_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('submit-review overview error: ' . $e->getMessage());
}

// Handle form submission via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!validatePublicCsrfToken($_POST['_csrf_review'] ?? '')) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => 'Invalid security token. Please refresh the page and try again.'
        ];
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Initialize validation errors array
    $validation_errors = [];
    $sanitized_data = [];
    
    // Validate guest_name
    $name_validation = validateName($_POST['guest_name'] ?? '', 2, true);
    if (!$name_validation['valid']) {
        $validation_errors['guest_name'] = $name_validation['error'];
    } else {
        $sanitized_data['guest_name'] = sanitizeString($name_validation['value'], 100);
    }
    
    // Validate guest_email
    $guest_email_input = trim($_POST['guest_email'] ?? '');
    $email_validation = validateEmail($guest_email_input);
    if (!$email_validation['valid']) {
        $validation_errors['guest_email'] = $email_validation['error'];
    } else {
        $sanitized_data['guest_email'] = sanitizeString($guest_email_input, 254);
    }
    
    // Validate overall_rating
    $rating_validation = validateRating($_POST['overall_rating'] ?? '', true);
    if (!$rating_validation['valid']) {
        $validation_errors['overall_rating'] = $rating_validation['error'];
    } else {
        $sanitized_data['overall_rating'] = $rating_validation['value'];
    }
    
    // Validate review_title
    $title_validation = validateText($_POST['review_title'] ?? '', 5, 200, true);
    if (!$title_validation['valid']) {
        $validation_errors['review_title'] = $title_validation['error'];
    } else {
        $sanitized_data['review_title'] = sanitizeString($title_validation['value'], 200);
    }
    
    // Validate review_comment
    $comment_validation = validateText($_POST['review_comment'] ?? '', 20, 2000, true);
    if (!$comment_validation['valid']) {
        $validation_errors['review_comment'] = $comment_validation['error'];
    } else {
        $sanitized_data['review_comment'] = sanitizeString($comment_validation['value'], 2000);
    }
    
    // Validate review_type (optional)
    $allowed_review_types = ['', 'room', 'restaurant', 'spa', 'conference', 'gym', 'service'];
    $type_validation = validateSelectOption($_POST['review_type'] ?? '', $allowed_review_types, false);
    if (!$type_validation['valid']) {
        $validation_errors['review_type'] = $type_validation['error'];
    } else {
        $sanitized_data['review_type'] = $type_validation['value'] ?: 'general';
    }
    
    // Validate room_id (optional, only if review_type is 'room')
    $room_id = null;
    if ($sanitized_data['review_type'] === 'room' && !empty($_POST['room_id'])) {
        $room_validation = validateRoomId($_POST['room_id']);
        if (!$room_validation['valid']) {
            $validation_errors['room_id'] = $room_validation['error'];
        } else {
            $room_id = $room_validation['room']['id'];
        }
    }
    
    // Validate optional ratings
    $sanitized_data['service_rating'] = null;
    if (!empty($_POST['service_rating'])) {
        $service_validation = validateRating($_POST['service_rating'], false);
        if (!$service_validation['valid']) {
            $validation_errors['service_rating'] = $service_validation['error'];
        } else {
            $sanitized_data['service_rating'] = $service_validation['value'];
        }
    }
    
    $sanitized_data['cleanliness_rating'] = null;
    if (!empty($_POST['cleanliness_rating'])) {
        $cleanliness_validation = validateRating($_POST['cleanliness_rating'], false);
        if (!$cleanliness_validation['valid']) {
            $validation_errors['cleanliness_rating'] = $cleanliness_validation['error'];
        } else {
            $sanitized_data['cleanliness_rating'] = $cleanliness_validation['value'];
        }
    }
    
    $sanitized_data['location_rating'] = null;
    if (!empty($_POST['location_rating'])) {
        $location_validation = validateRating($_POST['location_rating'], false);
        if (!$location_validation['valid']) {
            $validation_errors['location_rating'] = $location_validation['error'];
        } else {
            $sanitized_data['location_rating'] = $location_validation['value'];
        }
    }
    
    $sanitized_data['value_rating'] = null;
    if (!empty($_POST['value_rating'])) {
        $value_validation = validateRating($_POST['value_rating'], false);
        if (!$value_validation['valid']) {
            $validation_errors['value_rating'] = $value_validation['error'];
        } else {
            $sanitized_data['value_rating'] = $value_validation['value'];
        }
    }
    
    // Check for validation errors
    if (!empty($validation_errors)) {
        $errors = [];
        foreach ($validation_errors as $field => $message) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . $message;
        }
    } else {
        $errors = [];
    }
    
    if (empty($errors)) {
        // Insert review directly into database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (
                    guest_name,
                    guest_email,
                    rating,
                    title,
                    comment,
                    review_type,
                    room_id,
                    service_rating,
                    cleanliness_rating,
                    location_rating,
                    value_rating,
                    status,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                )
            ");
            
            $stmt->execute([
                $sanitized_data['guest_name'],
                $sanitized_data['guest_email'],
                $sanitized_data['overall_rating'],
                $sanitized_data['review_title'],
                $sanitized_data['review_comment'],
                $sanitized_data['review_type'],
                $room_id,
                $sanitized_data['service_rating'],
                $sanitized_data['cleanliness_rating'],
                $sanitized_data['location_rating'],
                $sanitized_data['value_rating']
            ]);
            
            // Store review details for confirmation page
            $_SESSION['review_details'] = [
                'guest_name' => $sanitized_data['guest_name'],
                'review_title' => $sanitized_data['review_title'],
                'overall_rating' => $sanitized_data['overall_rating'],
                'room_id' => $room_id
            ];
            
            // Redirect to confirmation page
            header('Location: ' . BASE_URL . 'review-confirmation.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Error inserting review: " . $e->getMessage());
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => 'An error occurred while submitting your review. Please try again.'
            ];
        }
    } else {
        // Set error messages
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => implode(' ', $errors)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Review | <?php echo htmlspecialchars(getSetting('site_name', 'Hotel Website')); ?></title>
    <meta name="description" content="Share your experience at <?php echo htmlspecialchars(getSetting('site_name', 'Hotel Website')); ?>. Submit your review and help us improve our services.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main CSS - Loads all stylesheets in correct order -->
    <link rel="stylesheet" href="css/main.css">
    
    <!-- Custom CSS for Review Form -->
    <link rel="stylesheet" href="css/review-form.css">
    
    <style>
      /* Lightweight, page-scoped polish for overview and inline brand */
      .inline-brand { display:flex; align-items:center; gap:12px; padding:10px 16px; }
      .inline-brand .header__brand { display:inline-flex; align-items:center; gap:10px; text-decoration:none; }
      .inline-brand .header__brand img { height:36px; width:auto; border-radius:6px; }
      .inline-brand .header__brand span { font-weight:700; letter-spacing:.02em; }
      .reviews-overview { padding: 28px 0; }
      .reviews-overview .stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap:12px; margin: 14px 0 22px; }
      .reviews-overview .stat { background: #fff; border:1px solid #e7ebf1; border-radius:12px; padding:14px; text-align:center; }
      .reviews-overview .stat .label { color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
      .reviews-overview .stat .value { font-size:20px; font-weight:700; margin-top:6px; }
      .reviews-overview .list { display:grid; gap:12px; }
      .reviews-overview .card { background:#fff; border:1px solid #e7ebf1; border-radius:12px; padding:16px; }
      .reviews-overview .card h4 { margin:0 0 6px; font-size:15px; }
      .reviews-overview .card p { margin:0; color:#4b5563; line-height:1.6; }
      .reviews-overview .resp { background:#fff8e1; border-left:3px solid #c9a86c; padding:10px; border-radius:8px; margin-top:10px; color:#554a2f; }
    </style>
    </head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="review-page">
        <!-- Inline Brand (ensures brand visible even if header variant hides it) -->
        <div class="inline-brand container">
            <a class="header__brand" href="<?php echo htmlspecialchars(BASE_URL); ?>">
                <?php if (!empty($site_logo)): ?>
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>">
                <?php endif; ?>
                <span><?php echo htmlspecialchars($site_name); ?></span>
            </a>
        </div>

        <!-- Hero Section -->
        <section class="review-hero">
            <div class="container">
                <h1>Share Your Experience</h1>
                <p>Your feedback helps us improve and provide exceptional service to all our guests.</p>
            </div>
        </section>

        <!-- Reviews Overview (approved + responses) placed below the review form card -->
        <section class="reviews-overview rh-reveal is-revealed">
            <div class="container">
                <h2 class="section-title" style="margin:0 0 8px;">Community Feedback</h2>
                <p class="text-muted" style="margin:0 0 16px;">A quick snapshot of what guests are saying and our responses.</p>
                <div class="stats">
                    <div class="stat">
                        <div class="label">Approved Reviews</div>
                        <div class="value"><?php echo (int)$reviews_summary['approved']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="label">All Reviews</div>
                        <div class="value"><?php echo (int)$reviews_summary['total']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="label">Admin Responses</div>
                        <div class="value"><?php echo (int)$reviews_summary['responses']; ?></div>
                    </div>
                    <div class="stat">
                        <div class="label">Average Rating</div>
                        <div class="value"><?php echo $reviews_summary['avg_rating'] !== null ? number_format($reviews_summary['avg_rating'], 2) . ' / 5' : '—'; ?></div>
                    </div>
                </div>

                <?php if (!empty($latest_reviews)): ?>
                <div class="list">
                    <?php foreach ($latest_reviews as $r): ?>
                        <div class="card">
                            <h4>
                                <i class="fas fa-user-circle" style="color:#8B7355;"></i>
                                <?php echo htmlspecialchars($r['guest_name'] ?: 'Guest'); ?> · 
                                <strong><?php echo (int)$r['rating']; ?>/5</strong>
                                <span style="color:#6b7280; font-size:12px;"> · <?php echo date('M d, Y', strtotime((string)$r['created_at'])); ?></span>
                            </h4>
                            <p><strong><?php echo htmlspecialchars($r['title']); ?></strong></p>
                            <p><?php echo nl2br(htmlspecialchars(mb_strimwidth($r['comment'] ?? '', 0, 320, '…'))); ?></p>
                            <?php if (!empty($r['latest_response'])): ?>
                                <div class="resp">
                                    <strong><i class="fas fa-reply"></i> Our reply</strong>
                                    <div style="font-size:13px; margin-top:6px;">
                                        <?php echo nl2br(htmlspecialchars($r['latest_response'])); ?>
                                    </div>
                                    <?php if (!empty($r['latest_response_date'])): ?>
                                        <div style="color:#8b8b8b; font-size:12px; margin-top:6px;">
                                            <?php echo date('M d, Y g:i A', strtotime((string)$r['latest_response_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Review Form -->
        <section class="review-form-container">
            <div class="review-form-card">
                <?php if (isset($_SESSION['alert'])): ?>
                    <?php showSessionAlert(); ?>
                <?php endif; ?>
                
                <?php if ($room_name): ?>
                    <div class="room-selection-badge">
                        <i class="fas fa-bed"></i>
                        <span>Reviewing: <?php echo htmlspecialchars($room_name); ?></span>
                    </div>
                <?php endif; ?>
                
                <form id="reviewForm" method="POST" action="submit-review.php<?php echo $selected_room_id > 0 ? '?room_id=' . $selected_room_id : ''; ?>" novalidate>
                    <input type="hidden" name="_csrf_review" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <!-- Personal Information -->
                    <div class="form-section-title">
                        <i class="fas fa-user"></i>
                        <span>Your Information</span>
                    </div>
                    
                    <div class="form-row two-columns">
                        <div class="form-group">
                            <label for="guest_name">
                                Guest Name <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="guest_name" 
                                name="guest_name" 
                                placeholder="Enter your full name"
                                required
                                aria-required="true"
                                minlength="2"
                            >
                            <p class="form-hint">Your name will be displayed with your review</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="guest_email">
                                Email Address <span class="required">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="guest_email" 
                                name="guest_email" 
                                placeholder="your@email.com"
                                required
                                aria-required="true"
                            >
                            <p class="form-hint">We'll never share your email with anyone</p>
                        </div>
                    </div>
                    
                    <!-- What are you reviewing? -->
                    <div class="form-group">
                        <label for="review_type">
                            What are you reviewing? <span class="optional">(Optional)</span>
                        </label>
                        <select id="review_type" name="review_type">
                            <option value="">General Hotel Experience</option>
                            <option value="room" <?php echo $selected_room_id > 0 ? 'selected' : ''; ?>>Specific Room</option>
                            <option value="restaurant">Restaurant & Dining</option>
                            <option value="spa">Spa & Wellness</option>
                            <option value="conference">Conference & Events</option>
                            <option value="gym">Fitness Center</option>
                            <option value="service">Staff & Service</option>
                        </select>
                        <p class="form-hint">Select the specific area you're reviewing, or leave blank for a general hotel review</p>
                    </div>
                    
                    <!-- Room Selection (shown only when "Specific Room" is selected) -->
                    <div class="form-group" id="roomSelectionGroup" style="display: <?php echo $selected_room_id > 0 ? 'block' : 'none'; ?>;">
                        <label for="room_id">
                            Which room did you stay in? <span class="optional">(Optional)</span>
                        </label>
                        <select id="room_id" name="room_id">
                            <option value="">Select a room type...</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>" <?php echo $selected_room_id === (int)$room['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Help us understand which room you stayed in</p>
                    </div>
                    
                    <!-- Overall Rating -->
                    <div class="form-section-title mt-30">
                        <i class="fas fa-star"></i>
                        <span>Overall Rating <span class="required">*</span></span>
                    </div>
                    
                    <div class="form-group">
                        <div class="rating-group">
                            <div class="star-rating" id="overallRating" data-rating="0">
                                <span class="star" data-value="1" role="button" tabindex="0" aria-label="1 star" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="2" role="button" tabindex="0" aria-label="2 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="3" role="button" tabindex="0" aria-label="3 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="4" role="button" tabindex="0" aria-label="4 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="5" role="button" tabindex="0" aria-label="5 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                            </div>
                            <span class="star-rating-label" id="overallRatingLabel">Select a rating</span>
                        </div>
                        <input type="hidden" id="overall_rating" name="overall_rating" value="0" required>
                        <p class="form-hint">How would you rate your overall experience?</p>
                    </div>
                    
                    <!-- Review Content -->
                    <div class="form-section-title mt-30">
                        <i class="fas fa-comment-alt"></i>
                        <span>Your Review</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="review_title">
                            Review Title <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="review_title" 
                            name="review_title" 
                            placeholder="Summarize your experience in a few words"
                            required
                            aria-required="true"
                            minlength="5"
                        >
                        <p class="form-hint">A catchy title for your review (min. 5 characters)</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="review_comment">
                            Review Comment <span class="required">*</span>
                        </label>
                        <textarea 
                            id="review_comment" 
                            name="review_comment" 
                            placeholder="Tell us about your stay, what you loved, and how we can improve..."
                            required
                            aria-required="true"
                            minlength="20"
                        ></textarea>
                        <p class="form-hint">Share your detailed experience (min. 20 characters)</p>
                    </div>
                    
                    <!-- Optional Detailed Ratings -->
                    <div class="optional-ratings">
                        <div class="optional-ratings-title">
                            <i class="fas fa-chart-bar"></i>
                            <span>Detailed Ratings <span class="optional">(Optional)</span></span>
                        </div>
                        
                        <div class="optional-rating-item">
                            <span class="optional-rating-label">Service</span>
                            <div class="star-rating" id="serviceRating" data-rating="0">
                                <span class="star" data-value="1" role="button" tabindex="0" aria-label="1 star" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="2" role="button" tabindex="0" aria-label="2 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="3" role="button" tabindex="0" aria-label="3 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="4" role="button" tabindex="0" aria-label="4 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="5" role="button" tabindex="0" aria-label="5 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                            </div>
                            <input type="hidden" id="service_rating" name="service_rating" value="">
                        </div>
                        
                        <div class="optional-rating-item">
                            <span class="optional-rating-label">Cleanliness</span>
                            <div class="star-rating" id="cleanlinessRating" data-rating="0">
                                <span class="star" data-value="1" role="button" tabindex="0" aria-label="1 star" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="2" role="button" tabindex="0" aria-label="2 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="3" role="button" tabindex="0" aria-label="3 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="4" role="button" tabindex="0" aria-label="4 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="5" role="button" tabindex="0" aria-label="5 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                            </div>
                            <input type="hidden" id="cleanliness_rating" name="cleanliness_rating" value="">
                        </div>
                        
                        <div class="optional-rating-item">
                            <span class="optional-rating-label">Location</span>
                            <div class="star-rating" id="locationRating" data-rating="0">
                                <span class="star" data-value="1" role="button" tabindex="0" aria-label="1 star" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="2" role="button" tabindex="0" aria-label="2 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="3" role="button" tabindex="0" aria-label="3 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="4" role="button" tabindex="0" aria-label="4 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="5" role="button" tabindex="0" aria-label="5 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                            </div>
                            <input type="hidden" id="location_rating" name="location_rating" value="">
                        </div>
                        
                        <div class="optional-rating-item">
                            <span class="optional-rating-label">Value for Money</span>
                            <div class="star-rating" id="valueRating" data-rating="0">
                                <span class="star" data-value="1" role="button" tabindex="0" aria-label="1 star" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="2" role="button" tabindex="0" aria-label="2 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="3" role="button" tabindex="0" aria-label="3 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="4" role="button" tabindex="0" aria-label="4 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                                <span class="star" data-value="5" role="button" tabindex="0" aria-label="5 stars" aria-pressed="false"><i class="fas fa-star"></i></span>
                            </div>
                            <input type="hidden" id="value_rating" name="value_rating" value="">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="mt-30">
                        <button type="submit" class="submit-btn" id="submitBtn">
                            <span class="loading-spinner"></span>
                            <span class="btn-text">Submit Review</span>
                        </button>
                    </div>
                    
        <!-- Privacy Note -->
                    <p class="text-center mt-30 text-base text-gray-light">
                        <i class="fas fa-shield-alt"></i>
                        Your review will be published after moderation. We reserve the right to edit or remove inappropriate content.
                    </p>
                </form>
            </div>
        </section>
    </main>

    <script src="js/modal.js"></script>
    <script src="js/main.js"></script>

    <!-- JavaScript for Star Rating and Form Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Star Rating Functionality
            const ratingLabels = {
                1: 'Poor',
                2: 'Fair',
                3: 'Good',
                4: 'Very Good',
                5: 'Excellent'
            };

            function initStarRating(containerId, inputId, isRequired = false) {
                const container = document.getElementById(containerId);
                const input = document.getElementById(inputId);
                const stars = container.querySelectorAll('.star');
                const labelElement = document.getElementById(containerId + 'Label');
                
                if (!container || !input) return;
                
                // Handle star click
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const value = parseInt(this.dataset.value);
                        setRating(value);
                    });
                    
                    // Handle star hover
                    star.addEventListener('mouseenter', function() {
                        const value = parseInt(this.dataset.value);
                        highlightStars(value);
                        if (labelElement) {
                            labelElement.textContent = ratingLabels[value];
                        }
                    });
                    
                    // Handle keyboard navigation
                    star.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            const value = parseInt(this.dataset.value);
                            setRating(value);
                        }
                    });
                });
                
                // Reset on mouse leave
                container.addEventListener('mouseleave', function() {
                    const currentRating = parseInt(container.dataset.rating);
                    highlightStars(currentRating);
                    if (labelElement) {
                        if (currentRating > 0) {
                            labelElement.textContent = ratingLabels[currentRating];
                        } else {
                            labelElement.textContent = 'Select a rating';
                        }
                    }
                });
                
                function setRating(value) {
                    container.dataset.rating = value;
                    input.value = value;
                    highlightStars(value);
                    
                    // Update ARIA attributes
                    stars.forEach(star => {
                        const starValue = parseInt(star.dataset.value);
                        star.setAttribute('aria-pressed', starValue <= value ? 'true' : 'false');
                    });
                    
                    if (labelElement) {
                        labelElement.textContent = ratingLabels[value];
                    }
                    
                    // Trigger validation
                    validateField(input);
                }
                
                function highlightStars(value) {
                    stars.forEach(star => {
                        const starValue = parseInt(star.dataset.value);
                        if (starValue <= value) {
                            star.classList.add('active');
                            star.classList.add('filled');
                            star.classList.remove('empty');
                        } else {
                            star.classList.remove('active');
                            star.classList.remove('filled');
                            star.classList.add('empty');
                        }
                    });
                }
            }
            
            // Initialize all star ratings
            initStarRating('overallRating', 'overall_rating', true);
            initStarRating('serviceRating', 'service_rating', false);
            initStarRating('cleanlinessRating', 'cleanliness_rating', false);
            initStarRating('locationRating', 'location_rating', false);
            initStarRating('valueRating', 'value_rating', false);
            
            // Handle review type selection
            const reviewTypeSelect = document.getElementById('review_type');
            const roomSelectionGroup = document.getElementById('roomSelectionGroup');
            
            if (reviewTypeSelect && roomSelectionGroup) {
                reviewTypeSelect.addEventListener('change', function() {
                    if (this.value === 'room') {
                        roomSelectionGroup.style.display = 'block';
                    } else {
                        roomSelectionGroup.style.display = 'none';
                        document.getElementById('room_id').value = '';
                    }
                });
            }
            
            // Form Validation
            const form = document.getElementById('reviewForm');
            const submitBtn = document.getElementById('submitBtn');
            
            function validateField(field) {
                const formGroup = field.closest('.form-group');
                let isValid = true;
                
                if (field.hasAttribute('required') && !field.value.trim()) {
                    isValid = false;
                }
                
                if (field.type === 'email' && field.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        isValid = false;
                    }
                }
                
                if (field.getAttribute('minlength') && field.value.trim().length < parseInt(field.getAttribute('minlength'))) {
                    isValid = false;
                }
                
                // Special validation for overall rating
                if (field.id === 'overall_rating' && parseInt(field.value) < 1) {
                    isValid = false;
                }
                
                if (formGroup) {
                    if (isValid) {
                        formGroup.classList.remove('error');
                        formGroup.classList.add('success');
                    } else {
                        formGroup.classList.remove('success');
                        formGroup.classList.add('error');
                    }
                }
                
                return isValid;
            }
            
            // Real-time validation
            const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.closest('.form-group').classList.contains('error')) {
                        validateField(this);
                    }
                });
            });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate all fields
                let isFormValid = true;
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isFormValid = false;
                    }
                });
                
                // Validate overall rating
                const overallRating = document.getElementById('overall_rating');
                if (parseInt(overallRating.value) < 1) {
                    isFormValid = false;
                    overallRating.closest('.form-group').classList.add('error');
                }
                
                if (!isFormValid) {
                    // Scroll to first error
                    const firstError = form.querySelector('.form-group.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Prepare form data
                const formData = new FormData(form);
                
                // Submit via AJAX
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    return response.text();
                })
                .then(html => {
                    // Check if response contains success message
                    if (html.includes('alert-success') || html.includes('Thank you for your review')) {
                        // Parse the response to find redirect URL
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success';
                        alertDiv.style.cssText = 'background: #1f8f5f; color: white; padding: 16px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                        alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> Thank you for your review! Your feedback has been submitted successfully.';
                        
                        form.insertBefore(alertDiv, form.firstChild);
                        
                        // Redirect after 2 seconds
                        setTimeout(() => {
                            const roomId = document.getElementById('room_id').value;
                            if (roomId) {
                                window.location.href = 'room.php?id=' + roomId;
                            } else {
                                window.location.href = 'index.php';
                            }
                        }, 2000);
                    } else {
                        // Show error message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-error';
                        alertDiv.style.cssText = 'background: #c0392b; color: white; padding: 16px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred while submitting your review. Please try again.';
                        
                        form.insertBefore(alertDiv, form.firstChild);
                        
                        // Reset button
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error';
                    alertDiv.style.cssText = 'background: #c0392b; color: white; padding: 16px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred while submitting your review. Please try again.';
                    
                    form.insertBefore(alertDiv, form.firstChild);
                    
                    // Reset button
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                });
            });
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
