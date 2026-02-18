<?php
/**
 * Review Submission Confirmation
 * Hotel Website - Professional Confirmation Page
 */

// Start session
session_start();

// Get review details from session if available
$review_details = $_SESSION['review_details'] ?? null;
unset($_SESSION['review_details']);

// Get site name
$site_name = 'Hotel Website';
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
    $site_name = getSetting('site_name', 'Hotel Website');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submitted | <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="Thank you for sharing your experience at <?php echo htmlspecialchars($site_name); ?>.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main CSS - Loads all stylesheets in correct order -->
    <link rel="stylesheet" href="css/main.css">
    <!-- Review form CSS (confirmation classes) -->
    <link rel="stylesheet" href="css/review-form.css">
    <style>
        /* Confirmation page layout — standalone styles */
        .confirmation-container {
            min-height: 70vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 24px;
            max-width: 640px;
            margin: 0 auto;
        }
        .success-icon {
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #1f8f5f 0%, #27ae60 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            box-shadow: 0 8px 24px rgba(31, 143, 95, 0.3);
        }
        .success-icon i {
            font-size: 42px;
            color: #fff;
        }
        .confirmation-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 42px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            margin: 0 0 16px;
        }
        .confirmation-message {
            font-size: 16px;
            color: #555;
            line-height: 1.7;
            margin-bottom: 32px;
            max-width: 480px;
        }
        .confirmation-details {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px 28px;
            margin-bottom: 36px;
            text-align: left;
            width: 100%;
        }
        .confirmation-details p {
            margin: 0 0 12px;
            color: #444;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .confirmation-details p:last-child {
            margin-bottom: 0;
        }
        .confirmation-details p i {
            color: var(--gold, #8B7355);
            width: 18px;
            flex-shrink: 0;
        }
        .confirmation-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 36px;
        }
        .confirmation-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .confirmation-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .confirmation-actions .btn-primary {
            background: linear-gradient(135deg, var(--gold, #8B7355), #6f5b43);
            color: #fff;
        }
        .confirmation-actions .btn-secondary {
            background: #fff;
            color: var(--navy, #1A1A1A);
            border: 2px solid var(--gold, #8B7355);
        }
        .footer-note {
            color: #aaa;
            font-size: 13px;
            margin: 0;
        }
        @media (max-width: 480px) {
            .confirmation-title { font-size: 30px; }
            .confirmation-actions { flex-direction: column; align-items: stretch; }
            .confirmation-actions .btn { justify-content: center; }
        }
    </style>
    </head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="confirmation-title">Thank You!</h1>
        
        <p class="confirmation-message">
            Your review has been successfully submitted and is pending moderation. 
            We appreciate you taking the time to share your experience.
        </p>
        
        <div class="confirmation-details">
            <p>
                <i class="fas fa-clock"></i>
                Your review will be visible within 24-48 hours
            </p>
            <p>
                <i class="fas fa-shield-alt"></i>
                All reviews are verified before publication
            </p>
            <p>
                <i class="fas fa-heart"></i>
                Your feedback helps us improve our services
            </p>
        </div>
        
        <div class="confirmation-actions">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Return Home
            </a>
            <a href="rooms-gallery.php" class="btn btn-secondary">
                <i class="fas fa-bed"></i> View Rooms
            </a>
        </div>
        
        <p class="footer-note">
            <?php echo htmlspecialchars($site_name); ?> &copy; <?php echo date('Y'); ?>
        </p>
    </div>
</body>
</html>
