<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';
require_once 'includes/finance-schema.php';

$user = [
    'id' => $_SESSION['admin_user_id'],
    'username' => $_SESSION['admin_username'],
    'role' => $_SESSION['admin_role'],
    'full_name' => $_SESSION['admin_full_name']
];
$site_name = getSetting('site_name');
$currency_symbol = getSetting('currency_symbol', 'K');
$conferenceFields = finance_conference_fields($pdo);
$today = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear = date('Y');

// Get date filters - support "all" for no date filtering
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : ($showAll ? '2000-01-01' : date('Y-m-01'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : ($showAll ? '2099-12-31' : date('Y-m-t'));

// Fetch accounting statistics
try {
    // Overall financial summary
    $financialStmt = $pdo->prepare(" 
        SELECT
            COUNT(*) as total_payments,
            COALESCE(SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN total_amount ELSE 0 END), 0) as total_collected,
            COALESCE(SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN payment_amount ELSE 0 END), 0) as total_collected_excl_vat,
            COALESCE(SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN vat_amount ELSE 0 END), 0) as total_vat_collected,
            COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'partial') THEN total_amount ELSE 0 END), 0) as total_pending,
            COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_amount ELSE 0 END), 0) as total_refunded,
            COALESCE(SUM(CASE WHEN payment_status = 'cancelled' THEN total_amount ELSE 0 END), 0) as total_cancelled
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
          AND deleted_at IS NULL
    ");
    $financialStmt->execute([$startDate, $endDate]);
    $financialSummary = $financialStmt->fetch(PDO::FETCH_ASSOC);

    // Room bookings financial summary
    $roomStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT p.booking_id) as total_bookings_with_payments,
            COALESCE(SUM(CASE WHEN p.payment_status IN ('completed', 'paid') THEN p.total_amount ELSE 0 END), 0) as room_collected,
            COALESCE(SUM(CASE WHEN p.payment_status IN ('completed', 'paid') THEN p.vat_amount ELSE 0 END), 0) as room_vat_collected,
            COALESCE(SUM(b.amount_due), 0) as total_room_outstanding
        FROM payments p
        LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
        WHERE p.booking_type = 'room'
        AND p.payment_date BETWEEN ? AND ?
        AND p.deleted_at IS NULL
    ");
    $roomStmt->execute([$startDate, $endDate]);
    $roomSummary = $roomStmt->fetch(PDO::FETCH_ASSOC);

    // Conference bookings financial summary
    $confStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT p.booking_id) as total_conferences_with_payments,
            COALESCE(SUM(CASE WHEN p.payment_status IN ('completed', 'paid') THEN p.total_amount ELSE 0 END), 0) as conf_collected,
            COALESCE(SUM(CASE WHEN p.payment_status IN ('completed', 'paid') THEN p.vat_amount ELSE 0 END), 0) as conf_vat_collected,
            COALESCE(SUM(ci.amount_due), 0) as total_conf_outstanding
        FROM payments p
        LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
        WHERE p.booking_type = 'conference'
        AND p.payment_date BETWEEN ? AND ?
        AND p.deleted_at IS NULL
    ");
    $confStmt->execute([$startDate, $endDate]);
    $confSummary = $confStmt->fetch(PDO::FETCH_ASSOC);

    // Payment method breakdown
    $methodStmt = $pdo->prepare("
        SELECT
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN total_amount ELSE 0 END), 0) as total
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
          AND deleted_at IS NULL
        GROUP BY payment_method
        ORDER BY total DESC
    ");
    $methodStmt->execute([$startDate, $endDate]);
    $paymentMethods = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent payments (last 20)
    $recentStmt = $pdo->prepare("
        SELECT
            p.*,
            CASE
                WHEN p.booking_type = 'room' THEN CONCAT(b.guest_name, ' (', b.booking_reference, ')')
                WHEN p.booking_type = 'conference' THEN CONCAT(ci.{$conferenceFields['company']}, ' (', ci.{$conferenceFields['reference']}, ')')
                ELSE 'Unknown'
            END as booking_description
        FROM payments p
        LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
        LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
        WHERE p.deleted_at IS NULL
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT 20
    ");
    $recentStmt->execute();
    $recentPayments = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Outstanding payments summary
    $outstandingStmt = $pdo->query("
        SELECT 
            'room' as type,
            COUNT(*) as count,
            SUM(amount_due) as total_outstanding
        FROM bookings
        WHERE amount_due > 0
        UNION ALL
        SELECT 
            'conference' as type,
            COUNT(*) as count,
            SUM(amount_due) as total_outstanding
        FROM conference_inquiries
        WHERE amount_due > 0
    ");
    $outstandingSummary = $outstandingStmt->fetchAll(PDO::FETCH_ASSOC);

    // VAT settings - more flexible check
    $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
    $vatRate = getSetting('vat_rate');
    $vatNumber = getSetting('vat_number');

} catch (PDOException $e) {
    $error = "Unable to load accounting data.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
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
        <div class="accounting-header">
            <div>
                <h2 class="section-title">Accounting Dashboard</h2>
                <p style="color: #666; margin-top: 4px;">Financial overview and payment tracking</p>
            </div>
            
            <form method="GET" class="date-filter">
                <label>
                    From: <input type="date" name="start_date" value="<?php echo htmlspecialchars($showAll ? '' : $startDate); ?>">
                </label>
                <label>
                    To: <input type="date" name="end_date" value="<?php echo htmlspecialchars($showAll ? '' : $endDate); ?>">
                </label>
                <button type="submit">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <a href="accounting-dashboard.php?show_all=1" style="color: var(--navy); text-decoration: none; font-size: 14px; margin-left: 10px;">
                    <i class="fas fa-calendar-alt"></i> Show All Time
                </a>
                <a href="accounting-dashboard.php" style="color: var(--navy); text-decoration: none; font-size: 14px; margin-left: 10px;">Reset</a>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin-bottom: 24px;">
            <a href="payments.php">
                <i class="fas fa-list"></i> View All Payments
            </a>
            <a href="payment-add.php" class="secondary">
                <i class="fas fa-plus"></i> Record Payment
            </a>
            <a href="reports.php" class="secondary">
                <i class="fas fa-chart-bar"></i> Financial Reports
            </a>
            <a href="booking-settings.php#vat" class="secondary">
                <i class="fas fa-cog"></i> VAT Settings
            </a>
        </div>

        <!-- VAT Information -->
        <div class="vat-info">
            <p><strong>VAT Status:</strong> <?php echo $vatEnabled ? 'Enabled' : 'Disabled'; ?></p>
            <?php if ($vatEnabled): ?>
                <p><strong>VAT Rate:</strong> <?php echo htmlspecialchars($vatRate); ?>%</p>
                <?php if ($vatNumber): ?>
                    <p><strong>VAT Number:</strong> <?php echo htmlspecialchars($vatNumber); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Financial Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-label">Total Collected</div>
                <div class="stat-value"><?php echo $currency_symbol; ?><?php echo number_format($financialSummary['total_collected'] ?? 0, 0); ?></div>
                <div class="stat-sub">
                    <?php echo $financialSummary['total_payments'] ?? 0; ?> payments
                    <?php if ($vatEnabled && ($financialSummary['total_vat_collected'] ?? 0) > 0): ?>
                        <br>(incl. <?php echo $currency_symbol; ?><?php echo number_format($financialSummary['total_vat_collected'] ?? 0, 0); ?> VAT)
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value"><?php echo $currency_symbol; ?><?php echo number_format($financialSummary['total_pending'] ?? 0, 0); ?></div>
                <div class="stat-sub">Awaiting confirmation</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-label">Refunded / Cancelled</div>
                <div class="stat-value"><?php echo $currency_symbol; ?><?php echo number_format(($financialSummary['total_refunded'] ?? 0) + ($financialSummary['total_cancelled'] ?? 0), 0); ?></div>
                <div class="stat-sub">Refunded or cancelled amount</div>
            </div>

            <div class="stat-card success">
                <div class="stat-label">Room Revenue</div>
                <div class="stat-value"><?php echo $currency_symbol; ?><?php echo number_format($roomSummary['room_collected'] ?? 0, 0); ?></div>
                <div class="stat-sub">
                    <?php echo $roomSummary['total_bookings_with_payments'] ?? 0; ?> bookings with payments
                    <?php if ($vatEnabled && ($roomSummary['room_vat_collected'] ?? 0) > 0): ?>
                        <br>(incl. <?php echo $currency_symbol; ?><?php echo number_format($roomSummary['room_vat_collected'] ?? 0, 0); ?> VAT)
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-label">Conference Revenue</div>
                <div class="stat-value"><?php echo $currency_symbol; ?><?php echo number_format($confSummary['conf_collected'] ?? 0, 0); ?></div>
                <div class="stat-sub">
                    <?php echo $confSummary['total_conferences_with_payments'] ?? 0; ?> conferences with payments
                    <?php if ($vatEnabled && ($confSummary['conf_vat_collected'] ?? 0) > 0): ?>
                        <br>(incl. <?php echo $currency_symbol; ?><?php echo number_format($confSummary['conf_vat_collected'] ?? 0, 0); ?> VAT)
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Methods & Outstanding -->
        <div class="section-grid">
            <div class="section-card">
                <h3>
                    <i class="fas fa-credit-card"></i> Payment Methods
                </h3>
                <?php if (!empty($paymentMethods)): ?>
                    <?php foreach ($paymentMethods as $method): ?>
                        <div class="payment-method-item">
                            <div class="method-name">
                                <div class="method-icon">
                                    <?php
                                    $icon = 'fa-money-bill';
                                    switch ($method['payment_method']) {
                                        case 'cash': $icon = 'fa-money-bill-wave'; break;
                                        case 'bank_transfer': $icon = 'fa-building-columns'; break;
                                        case 'credit_card': $icon = 'fa-credit-card'; break;
                                        case 'debit_card': $icon = 'fa-credit-card'; break;
                                        case 'mobile_money': $icon = 'fa-mobile-screen'; break;
                                        case 'cheque': $icon = 'fa-file-invoice-dollar'; break;
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?>
                            </div>
                            <div class="method-stats">
                                <div class="method-count"><?php echo $method['count']; ?> transactions</div>
                                <div class="method-total"><?php echo $currency_symbol; ?><?php echo number_format($method['total'], 0); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No payment data for selected period</p>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i> Outstanding Payments
                </h3>
                <?php if (!empty($outstandingSummary)): ?>
                    <?php foreach ($outstandingSummary as $outstanding): ?>
                        <?php if ($outstanding['total_outstanding'] > 0): ?>
                            <div class="outstanding-item">
                                <div class="outstanding-type">
                                    <?php echo ucfirst($outstanding['type']); ?> Bookings
                                </div>
                                <div>
                                    <div class="method-count"><?php echo $outstanding['count']; ?> outstanding</div>
                                    <div class="outstanding-amount"><?php echo $currency_symbol; ?><?php echo number_format($outstanding['total_outstanding'], 0); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #28a745; text-align: center; padding: 20px;">
                        <i class="fas fa-check-circle"></i> All payments up to date!
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments -->
        <h3 class="section-title">Recent Payments</h3>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentPayments)): ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($payment['payment_reference']); ?></strong></td>
                                <td><?php echo htmlspecialchars($payment['booking_description']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $payment['booking_type']; ?>">
                                        <?php echo ucfirst($payment['booking_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    <br><small style="color: #666; font-size: 11px;">
                                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($payment['payment_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 0); ?>
                                    <?php if ($payment['vat_amount'] > 0): ?>
                                        <small style="color: #666;">(incl. VAT)</small>
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
                                    <a href="payment-details.php?id=<?php echo $payment['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No payments recorded yet</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($recentPayments) >= 20): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="payments.php" class="btn btn-primary">
                    View All Payments <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>

    <?php require_once 'includes/admin-footer.php'; ?>
