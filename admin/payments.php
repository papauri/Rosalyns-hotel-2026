<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

require_once '../includes/modal.php';
require_once '../includes/alert.php';
require_once 'includes/finance-schema.php';

$site_name = getSetting('site_name');
$currency_symbol = getSetting('currency_symbol');
$conferenceFields = finance_conference_fields($pdo);
$paymentTransactionColumn = finance_payment_transaction_column($pdo);

// Get filter parameters
$bookingType = isset($_GET['booking_type']) ? $_GET['booking_type'] : '';
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$paymentMethod = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$sql = "
    SELECT
        p.*,
        CASE
            WHEN p.booking_type = 'room' THEN CONCAT(b.guest_name, ' (', b.booking_reference, ')')
            WHEN p.booking_type = 'conference' THEN CONCAT(ci.{$conferenceFields['company']}, ' (', ci.{$conferenceFields['reference']}, ')')
            ELSE 'Unknown'
        END as booking_description,
        CASE
            WHEN p.booking_type = 'room' THEN b.booking_reference
            WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['reference']}
            ELSE NULL
        END as booking_reference,
        CASE
            WHEN p.booking_type = 'room' THEN b.guest_email
            WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['email']}
            ELSE NULL
        END as contact_email,
        CASE
            WHEN p.booking_type = 'room' THEN b.guest_phone
            WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['phone']}
            ELSE NULL
        END as contact_phone
    FROM payments p
    LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
    LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
";

$where_conditions = ["p.deleted_at IS NULL"];
$params = [];

if ($bookingType) {
    $where_conditions[] = "p.booking_type = ?";
    $params[] = $bookingType;
}

if ($bookingId) {
    $where_conditions[] = "p.booking_id = ?";
    $params[] = $bookingId;
}

if ($status) {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $status;
}

if ($paymentMethod) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $paymentMethod;
}

if ($startDate) {
    $where_conditions[] = "p.payment_date >= ?";
    $params[] = $startDate;
}

if ($endDate) {
    $where_conditions[] = "p.payment_date <= ?";
    $params[] = $endDate;
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

// Get total count
$countSql = "
    SELECT COUNT(*) as total
    FROM payments p
    LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
    LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
";

if (!empty($where_conditions)) {
    $countSql .= " WHERE " . implode(' AND ', $where_conditions);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Add ordering and pagination
$sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique payment methods for filter
$methodsStmt = $pdo->query("
    SELECT DISTINCT payment_method
    FROM payments
    WHERE deleted_at IS NULL
    ORDER BY payment_method
");
$paymentMethods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/admin-finance.css">
</head>
<body>

    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <div>
                <h2 class="section-title">Payments</h2>
                <p style="color: #666; margin-top: 4px;">Manage and track all payments</p>
            </div>
            <a href="payment-add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Record Payment
            </a>
        </div>

        <!-- Summary Cards -->
        <?php
        $summaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_payments,
                COALESCE(SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN total_amount ELSE 0 END), 0) as total_collected,
                COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'partial') THEN total_amount ELSE 0 END), 0) as total_pending,
                COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_amount ELSE 0 END), 0) as total_refunded
            FROM payments
            WHERE deleted_at IS NULL
        ");
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        ?>
        
        <div class="summary-cards">
            <div class="summary-card">
                <div class="label">Total Payments</div>
                <div class="value"><?php echo number_format($summary['total_payments'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Collected</div>
                <div class="value"><?php echo $currency_symbol; ?><?php echo number_format($summary['total_collected'] ?? 0, 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Pending</div>
                <div class="value"><?php echo $currency_symbol; ?><?php echo number_format($summary['total_pending'] ?? 0, 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Refunded</div>
                <div class="value"><?php echo $currency_symbol; ?><?php echo number_format($summary['total_refunded'] ?? 0, 0); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-section">
            <div class="filter-form">
                <div class="filter-group">
                    <label>Booking Type</label>
                    <select name="booking_type">
                        <option value="">All Types</option>
                        <option value="room" <?php echo $bookingType === 'room' ? 'selected' : ''; ?>>Room</option>
                        <option value="conference" <?php echo $bookingType === 'conference' ? 'selected' : ''; ?>>Conference</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Booking ID</label>
                    <input type="number" name="booking_id" value="<?php echo $bookingId; ?>" placeholder="Enter ID">
                </div>
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="">All Methods</option>
                        <?php foreach ($paymentMethods as $method): ?>
                            <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $paymentMethod === $method ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="payments.php" class="btn-reset" style="padding: 10px 20px; border-radius: var(--radius); text-decoration: none; display: inline-block;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Payments Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Booking</th>
                        <th>Type</th>
                        <th>Payment Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Receipt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($payment['payment_reference']); ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($payment['booking_description']); ?></div>
                                    <?php if ($payment['contact_email']): ?>
                                        <small style="color: #666;"><?php echo htmlspecialchars($payment['contact_email']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($payment['contact_phone'])): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($payment['contact_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $payment['booking_type']; ?>">
                                        <?php echo ucfirst($payment['booking_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    <br><small style="color: #666; font-size: 11px;">
                                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 0); ?></strong>
                                    <?php if ($payment['vat_amount'] > 0): ?>
                                        <br><small style="color: #666;">(incl. <?php echo $currency_symbol; ?><?php echo number_format($payment['vat_amount'], 0); ?> VAT)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: #666; font-size: 11px;">
                                        <i class="fas fa-clock"></i> <?php echo date('M j, H:i', strtotime($payment['created_at'])); ?>
                                    </small>
                                    <?php if ($payment['updated_at'] && $payment['updated_at'] != $payment['created_at']): ?>
                                        <br><small style="color: #999; font-size: 10px;">
                                            <i class="fas fa-edit"></i> <?php echo date('M j, H:i', strtotime($payment['updated_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment[$paymentTransactionColumn])): ?>
                                        <span style="color: #28a745;"><i class="fas fa-check"></i> <?php echo htmlspecialchars($payment[$paymentTransactionColumn]); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="quick-actions">
                                        <a href="payment-details.php?id=<?php echo $payment['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="invoices.php?search=<?php echo urlencode($payment['payment_reference']); ?>" class="btn btn-secondary btn-sm" title="Invoice">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <?php if (in_array($payment['payment_status'], ['completed', 'paid'], true) && $payment['payment_type'] != 'refund'): ?>
                                            <a href="payment-refund.php?id=<?php echo $payment['id']; ?>" class="btn btn-warning btn-sm" title="Process Refund" onclick="return confirm('Process refund for this payment?');">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!in_array($payment['payment_status'], ['completed', 'paid'], true)): ?>
                                            <a href="payment-add.php?edit=<?php echo $payment['id']; ?>" class="btn btn-info btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($payment['contact_phone'])): ?>
                                            <?php $waPhone = preg_replace('/[^0-9]/', '', (string)$payment['contact_phone']); ?>
                                            <?php if ($waPhone !== ''): ?>
                                                <a href="https://wa.me/<?php echo htmlspecialchars($waPhone); ?>?text=<?php echo urlencode('Hello, this is ' . $site_name . ' accounts. Payment reference: ' . $payment['payment_reference']); ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm" title="WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No payments found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($total > 0): ?>
            <p style="text-align: center; color: #666; margin-top: 16px;">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> payments
            </p>
        <?php endif; ?>
    </div>

    <script src="js/admin-components.js"></script>

    <?php require_once 'includes/admin-footer.php'; ?>
