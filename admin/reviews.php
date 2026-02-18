<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

$user = [
    'id' => $_SESSION['admin_user_id'],
    'username' => $_SESSION['admin_username'],
    'role' => $_SESSION['admin_role'],
    'full_name' => $_SESSION['admin_full_name']
];
$site_name = getSetting('site_name');

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Validate status filter
$valid_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build query
$sql = "
    SELECT
        r.*,
        (SELECT COUNT(*) FROM review_responses rr WHERE rr.review_id = r.id) as response_count,
        (SELECT response FROM review_responses rr WHERE rr.review_id = r.id ORDER BY rr.created_at DESC LIMIT 1) as latest_response,
        (SELECT created_at FROM review_responses rr WHERE rr.review_id = r.id ORDER BY rr.created_at DESC LIMIT 1) as latest_response_date,
        rm.name as room_name
    FROM reviews r
    LEFT JOIN rooms rm ON r.room_id = rm.id
    WHERE 1=1
";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (r.guest_name LIKE ? OR r.guest_email LIKE ? OR r.title LIKE ? OR r.comment LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY r.created_at DESC";

// Get total count
$count_sql = str_replace("SELECT r.*, (SELECT COUNT(*) FROM review_responses rr WHERE rr.review_id = r.id) as response_count, (SELECT response FROM review_responses rr WHERE rr.review_id = r.id ORDER BY rr.created_at DESC LIMIT 1) as latest_response, (SELECT created_at FROM review_responses rr WHERE rr.review_id = r.id ORDER BY rr.created_at DESC LIMIT 1) as latest_response_date, rm.name as room_name", "SELECT COUNT(*) as total", $sql);
$count_sql = preg_replace('/ORDER BY.*$/', '', $count_sql);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_reviews = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_reviews / $per_page);

// Get reviews for current page
$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending reviews count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM reviews WHERE status = 'pending'");
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews Management | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css"><!-- Admin Components (Alert, Modal) -->
    <script src="js/admin-components.js"></script>
    <style>
        /* ── Reviews Management Page Styles ── */
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .reviews-header h2 { margin: 0; }
        .pending-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 8px;
        }

        /* Filters bar */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            background: #fff;
            border: 1px solid #e7ebf1;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy, #1A1A1A);
            white-space: nowrap;
        }
        .filter-group select,
        .filter-group input[type="search"] {
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: inherit;
            min-width: 160px;
        }
        .filter-group input[type="search"] { min-width: 240px; }

        /* Review cards */
        .review-card {
            background: #fff;
            border: 1px solid #e7ebf1;
            border-left: 4px solid #dbe2ea;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: box-shadow 0.2s ease, opacity 0.3s ease, transform 0.3s ease;
        }
        .review-card:hover { box-shadow: 0 4px 16px rgba(15,23,42,0.08); }
        .review-card.pending  { border-left-color: #ffc107; }
        .review-card.approved { border-left-color: #28a745; }
        .review-card.rejected { border-left-color: #dc3545; }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }
        .review-guest-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .review-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold, #8B7355), #6f5b43);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .review-guest-details h4 { margin: 0 0 2px; font-size: 15px; color: #1f2d3d; }
        .review-guest-details p  { margin: 0; font-size: 12px; color: #6b7280; }

        .review-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .review-rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .review-rating .stars i { color: #ffc107; font-size: 13px; }
        .review-rating .rating-value { font-size: 12px; color: #6b7280; font-weight: 600; margin-left: 4px; }

        .badge-pending  { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }

        .review-date, .review-room {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .review-title {
            font-size: 17px;
            font-weight: 700;
            color: #1f2d3d;
            margin: 0 0 10px;
        }
        .review-comment {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.65;
            margin-bottom: 14px;
        }

        /* Category ratings */
        .category-ratings {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .category-rating {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .category-rating i { color: #ffc107; }
        .category-rating span { font-weight: 700; }

        /* Admin response */
        .admin-response {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 3px solid #22c55e;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .admin-response-header {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #16a34a;
            margin-bottom: 8px;
        }
        .admin-response-header span { color: #6b7280; }
        .admin-response-content {
            font-size: 13px;
            color: #374151;
            line-height: 1.6;
        }

        /* Response form */
        .response-form {
            border: 1px solid #e7ebf1;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 14px;
            background: #fafbfd;
        }
        .response-form textarea {
            width: 100%;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            font-family: inherit;
            min-height: 100px;
            resize: vertical;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        .response-form textarea:focus {
            outline: none;
            border-color: var(--gold, #8B7355);
            box-shadow: 0 0 0 3px rgba(139,115,85,0.12);
        }

        /* Review actions */
        .review-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            background: #fff;
            border: 1px solid #e7ebf1;
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 48px;
            color: #d1d5db;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 { color: #374151; margin: 0 0 8px; }
        .empty-state p  { font-size: 14px; margin: 0; }

        /* Pagination */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 13px;
            text-decoration: none;
            color: #374151;
            background: #fff;
        }
        .pagination a:hover { background: #f5f7fa; }
        .pagination span.active { background: var(--navy, #1A1A1A); color: #fff; border-color: var(--navy, #1A1A1A); }
        .pagination span.disabled { color: #aaa; }

        /* Button variants used on this page */
        .btn-success { background: #28a745; color: #fff; border: none; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; border: none; }
        .btn-warning:hover { background: #e0a800; }
        .btn-dark { background: #343a40; color: #fff; border: none; }
        .btn-dark:hover { background: #23272b; }
        .btn-light { background: #f8f9fa; color: #374151; border: 1px solid #dee2e6; }
        .btn-light:hover { background: #e9ecef; }
        .btn-info { background: #17a2b8; color: #fff; border: none; }
        .btn-info:hover { background: #138496; }

        @media (max-width: 640px) {
            .review-header { flex-direction: column; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; align-items: flex-start; }
            .filter-group select,
            .filter-group input[type="search"] { width: 100%; min-width: unset; }
        }
    </style>
</head>
<body>

    <?php require_once 'includes/admin-header.php'; ?>

    <div class="content">
        <div class="reviews-header">
            <div>
                <h2 class="section-title">Reviews Management</h2>
                <?php if ($pending_count > 0): ?>
                    <div class="pending-badge">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $pending_count; ?> Pending Review<?php echo $pending_count > 1 ? 's' : ''; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filter-group">
                <label for="status-filter"><i class="fas fa-filter"></i> Status:</label>
                <select id="status-filter" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search-input"><i class="fas fa-search"></i> Search:</label>
                <input type="search" id="search-input" placeholder="Guest name, email, or comment..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" 
                       onkeypress="if(event.key === 'Enter') applyFilters()">
            </div>
            
            <button onclick="applyFilters()" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            
            <button onclick="clearFilters()" class="btn btn-light">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>

        <!-- Reviews List -->
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Reviews Found</h3>
                <p><?php echo !empty($search_query) ? 'Try adjusting your search or filters.' : 'No reviews have been submitted yet.'; ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card <?php echo $review['status']; ?>" id="review-<?php echo $review['id']; ?>">
                    <div class="review-header">
                        <div class="review-guest-info">
                            <div class="review-avatar">
                                <?php echo strtoupper(substr($review['guest_name'], 0, 1)); ?>
                            </div>
                            <div class="review-guest-details">
                                <h4><?php echo htmlspecialchars($review['guest_name']); ?></h4>
                                <p><?php echo htmlspecialchars($review['guest_email']); ?></p>
                            </div>
                        </div>
                        <div class="review-meta">
                            <div class="review-rating">
                                <span class="stars">
                                    <?php echo str_repeat('<i class="fas fa-star"></i>', $review['rating']); ?>
                                    <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $review['rating']); ?>
                                </span>
                                <span class="rating-value"><?php echo $review['rating']; ?>/5</span>
                            </div>
                            <span class="badge badge-<?php echo $review['status']; ?>">
                                <?php echo ucfirst($review['status']); ?>
                            </span>
                            <span class="review-date">
                                <i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                            </span>
                            <?php if ($review['room_name']): ?>
                                <span class="review-room">
                                    <i class="fas fa-bed"></i> <?php echo htmlspecialchars($review['room_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3 class="review-title"><?php echo htmlspecialchars($review['title']); ?></h3>
                    
                    <div class="review-comment">
                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                    </div>
                    
                    <?php if ($review['service_rating'] || $review['cleanliness_rating'] || $review['location_rating'] || $review['value_rating']): ?>
                        <div class="category-ratings">
                            <?php if ($review['service_rating']): ?>
                                <div class="category-rating">
                                    <i class="fas fa-star"></i> Service: <span><?php echo $review['service_rating']; ?>/5</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($review['cleanliness_rating']): ?>
                                <div class="category-rating">
                                    <i class="fas fa-star"></i> Cleanliness: <span><?php echo $review['cleanliness_rating']; ?>/5</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($review['location_rating']): ?>
                                <div class="category-rating">
                                    <i class="fas fa-star"></i> Location: <span><?php echo $review['location_rating']; ?>/5</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($review['value_rating']): ?>
                                <div class="category-rating">
                                    <i class="fas fa-star"></i> Value: <span><?php echo $review['value_rating']; ?>/5</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($review['latest_response']): ?>
                        <div class="admin-response">
                            <div class="admin-response-header">
                                <i class="fas fa-reply"></i>
                                <strong>Admin Response</strong>
                                <span>• <?php echo date('M d, Y g:i A', strtotime($review['latest_response_date'])); ?></span>
                            </div>
                            <div class="admin-response-content">
                                <?php echo nl2br(htmlspecialchars($review['latest_response'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="response-form" id="response-form-<?php echo $review['id']; ?>" style="display: none;">
                        <textarea id="response-text-<?php echo $review['id']; ?>" placeholder="Write your response to this review..."></textarea>
                        <div class="review-actions">
                            <button onclick="submitResponse(<?php echo $review['id']; ?>)" class="btn btn-primary btn-sm">
                                <i class="fas fa-paper-plane"></i> Submit Response
                            </button>
                            <button onclick="toggleResponseForm(<?php echo $review['id']; ?>)" class="btn btn-light btn-sm">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    
                    <div class="review-actions">
                        <?php if ($review['status'] === 'pending'): ?>
                            <button onclick="updateReviewStatus(<?php echo $review['id']; ?>, 'approved')" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button onclick="updateReviewStatus(<?php echo $review['id']; ?>, 'rejected')" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        <?php elseif ($review['status'] === 'approved'): ?>
                            <button onclick="updateReviewStatus(<?php echo $review['id']; ?>, 'rejected')" class="btn btn-warning btn-sm">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        <?php elseif ($review['status'] === 'rejected'): ?>
                            <button onclick="updateReviewStatus(<?php echo $review['id']; ?>, 'approved')" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        <?php endif; ?>
                        
                        <button onclick="toggleResponseForm(<?php echo $review['id']; ?>)" class="btn btn-info btn-sm">
                            <i class="fas fa-reply"></i> Respond
                        </button>
                        
                        <button onclick="deleteReview(<?php echo $review['id']; ?>)" class="btn btn-dark btn-sm">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php elseif ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Apply filters
        function applyFilters() {
            const status = document.getElementById('status-filter').value;
            const search = document.getElementById('search-input').value.trim();
            
            let url = '?status=' + encodeURIComponent(status);
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            
            window.location.href = url;
        }
        
        // Clear filters
        function clearFilters() {
            window.location.href = '?';
        }
        
        // Toggle response form
        function toggleResponseForm(reviewId) {
            const form = document.getElementById('response-form-' + reviewId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            
            if (form.style.display === 'block') {
                document.getElementById('response-text-' + reviewId).focus();
            }
        }
        
        // Submit admin response
        function submitResponse(reviewId) {
            const responseText = document.getElementById('response-text-' + reviewId).value.trim();
            
            if (!responseText) {
                Alert.show('Please enter a response', 'error');
                return;
            }
            
            if (responseText.length < 10) {
                Alert.show('Response must be at least 10 characters long', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('review_id', reviewId);
            formData.append('response', responseText);
            
            fetch('api/review-responses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = 'Response added successfully';
                    if (data.email_sent) {
                        message += '. Email notification sent to guest.';
                    } else if (data.email_status === 'failed') {
                        message += '. Email could not be sent: ' + (data.email_error || 'Check email configuration');
                    } else if (data.email_status === 'no_guest_email') {
                        message += '. No guest email on file.';
                    }
                    Alert.show(message, data.email_sent ? 'success' : 'warning');
                    location.reload();
                } else {
                    Alert.show('Error: ' + (data.message || 'Failed to add response'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Alert.show('An error occurred while adding response', 'error');
            });
        }
        
        // Update review status
        function updateReviewStatus(reviewId, newStatus) {
            const statusText = newStatus === 'approved' ? 'approve' : 'reject';
            if (!confirm('Are you sure you want to ' + statusText + ' this review?')) {
                return;
            }
            
            const data = {
                review_id: reviewId,
                status: newStatus
            };
            
            fetch('api/reviews.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Alert.show('Review ' + statusText + 'd successfully', 'success');
                    location.reload();
                } else {
                    Alert.show('Error: ' + (data.message || 'Failed to update review'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Alert.show('An error occurred while updating review', 'error');
            });
        }
        
        // Delete review
        function deleteReview(reviewId) {
            if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
                return;
            }
            
            fetch('api/reviews.php?review_id=' + reviewId, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Alert.show('Review deleted successfully', 'success');
                    const reviewCard = document.getElementById('review-' + reviewId);
                    reviewCard.style.opacity = '0';
                    reviewCard.style.transform = 'translateX(-100%)';
                    setTimeout(() => {
                        reviewCard.remove();
                        // Check if no reviews left
                        if (document.querySelectorAll('.review-card').length === 0) {
                            location.reload();
                        }
                    }, 300);
                } else {
                    Alert.show('Error: ' + (data.message || 'Failed to delete review'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Alert.show('An error occurred while deleting review', 'error');
            });
        }
    </script>

    <?php require_once 'includes/admin-footer.php'; ?>
