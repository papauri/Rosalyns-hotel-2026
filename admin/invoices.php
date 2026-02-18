<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

require_once '../includes/alert.php';
require_once 'includes/finance-schema.php';
$message = '';
$error = '';
$conferenceFields = finance_conference_fields($pdo);

// Handle invoice actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        
        if ($action === 'resend_invoice' && $payment_id > 0) {
            // Get payment details
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('Payment not found. It may have been deleted or does not exist.');
            }
            
            // Resend invoice email based on booking type
            require_once '../config/invoice.php';
            
            if ($payment['booking_type'] === 'room') {
                $result = sendPaymentInvoiceEmail($payment['booking_id']);
            } else {
                $result = sendConferenceInvoiceEmail($payment['booking_id']);
            }
            
            if ($result['success']) {
                $message = 'Invoice resent successfully!';
            } else {
                $error = 'Failed to resend invoice: ' . $result['message'];
            }
        }

        if ($action === 'send_reminder' && $payment_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('Payment not found. It may have been deleted or does not exist.');
            }

            require_once '../config/invoice.php';

            if ($payment['booking_type'] === 'room') {
                $result = sendPaymentInvoiceEmail($payment['booking_id']);
            } else {
                $result = sendConferenceInvoiceEmail($payment['booking_id']);
            }

            if ($result['success']) {
                $message = 'Payment reminder email sent successfully!';
            } else {
                $error = 'Failed to send reminder: ' . $result['message'];
            }
        }
        
        if ($action === 'regenerate_invoice' && $payment_id > 0) {
            // Get payment details
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('Payment not found. It may have been deleted or does not exist.');
            }
            
            // Regenerate invoice based on booking type
            require_once '../config/invoice.php';
            
            if ($payment['booking_type'] === 'room') {
                $result = generateInvoicePDF($payment['booking_id']);
            } else {
                $result = generateConferenceInvoicePDF($payment['booking_id']);
            }
            
            if ($result) {
                // Update payment record with new invoice path
                $update_stmt = $pdo->prepare("
                    UPDATE payments 
                    SET invoice_path = ?, invoice_number = ?, invoice_generated = 1
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $result['relative_path'],
                    $result['invoice_number'],
                    $payment_id
                ]);
                
                $message = 'Invoice regenerated successfully!';
            } else {
                $error = 'Failed to regenerate invoice';
            }
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_status = $_GET['filter_status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["p.deleted_at IS NULL"];
$params = [];

if ($filter_type !== 'all') {
    $where_conditions[] = "p.booking_type = ?";
    $params[] = $filter_type;
}

if ($filter_status !== 'all') {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = "(p.invoice_number LIKE ? OR p.payment_reference LIKE ? OR p.booking_reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';

// Fetch invoices with payment details
try {
    $sql = "
        SELECT p.*,
               CASE
                   WHEN p.booking_type = 'room' THEN CONCAT(b.guest_name, ' (', r.name, ')')
                   WHEN p.booking_type = 'conference' THEN CONCAT(ci.{$conferenceFields['company']}, ' (', cr.name, ')')
                   ELSE 'Unknown'
               END as customer_name,
               CASE
                   WHEN p.booking_type = 'room' THEN b.guest_email
                   WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['email']}
                   ELSE NULL
               END as customer_email
        FROM payments p
        LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
        LEFT JOIN rooms r ON p.booking_type = 'room' AND b.room_id = r.id
        LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
        LEFT JOIN conference_rooms cr ON p.booking_type = 'conference' AND ci.conference_room_id = cr.id";
    
    if (!empty($where_clause)) {
        $sql .= " WHERE $where_clause";
    }
    
    $sql .= " ORDER BY p.payment_date DESC, p.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_stmt = $pdo->query("
        SELECT
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN invoice_generated = 1 THEN 1 END) as invoices_generated,
            SUM(total_amount) as total_revenue
        FROM payments
        WHERE deleted_at IS NULL
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching invoices: ' . $e->getMessage();
    $invoices = [];
    $stats = ['total_invoices' => 0, 'invoices_generated' => 0, 'total_revenue' => 0];
}

$site_name = getSetting('site_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - Admin Panel</title>
    
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
    
    <div class="invoices-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-invoice-dollar"></i> Invoice Management
            </h1>
        </div>

        <?php if ($message): ?>
            <?php showAlert($message, 'success'); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php showAlert($error, 'error'); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Payments</h3>
                <div class="number"><?php echo number_format($stats['total_invoices'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Invoices Generated</h3>
                <div class="number"><?php echo number_format($stats['invoices_generated'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="number"><?php echo getSetting('currency_symbol', 'K'); ?> <?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Booking Type</label>
                        <select name="filter_type">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="room" <?php echo $filter_type === 'room' ? 'selected' : ''; ?>>Room Bookings</option>
                            <option value="conference" <?php echo $filter_type === 'conference' ? 'selected' : ''; ?>>Conference Bookings</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Payment Status</label>
                        <select name="filter_status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Invoice #, Payment #, Booking Ref..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="invoices.php" class="btn-action" style="background: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Invoices Table -->
        <div class="invoices-table">
            <?php if (!empty($invoices)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Payment #</th>
                                <th>Booking Ref</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Invoice</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($invoice['payment_reference']); ?></code>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invoice['booking_reference']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $invoice['booking_type']; ?>">
                                            <?php echo ucfirst($invoice['booking_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($invoice['customer_name'] ?? 'N/A'); ?>
                                        <?php if ($invoice['customer_email']): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($invoice['customer_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($invoice['payment_date'])); ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--gold); font-size: 16px;">
                                            <?php echo getSetting('currency_symbol', 'K'); ?> <?php echo number_format($invoice['total_amount'], 0); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $invoice['payment_status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $invoice['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($invoice['invoice_generated']): ?>
                                            <span class="badge badge-generated">
                                                <i class="fas fa-check"></i> Generated
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($invoice['invoice_path'] && file_exists(__DIR__ . '/../' . $invoice['invoice_path'])): ?>
                                                <a href="../<?php echo htmlspecialchars($invoice['invoice_path']); ?>" 
                                                   target="_blank" 
                                                   class="btn-action btn-view"
                                                   title="View Invoice">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($invoice['invoice_generated']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="resend_invoice">
                                                    <input type="hidden" name="payment_id" value="<?php echo $invoice['id']; ?>">
                                                    <button type="submit" class="btn-action btn-resend" onclick="return confirm('Resend invoice email?');">
                                                        <i class="fas fa-paper-plane"></i> Resend
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (in_array($invoice['payment_status'], ['pending', 'partial'], true)): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="send_reminder">
                                                    <input type="hidden" name="payment_id" value="<?php echo $invoice['id']; ?>">
                                                    <button type="submit" class="btn-action" onclick="return confirm('Send payment reminder email now?');">
                                                        <i class="fas fa-bell"></i> Reminder
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!empty($invoice['customer_email'])): ?>
                                                <a class="btn-action" href="mailto:<?php echo rawurlencode($invoice['customer_email']); ?>?subject=<?php echo rawurlencode('Payment reminder - ' . ($invoice['invoice_number'] ?: $invoice['payment_reference'])); ?>">
                                                    <i class="fas fa-envelope"></i> Email
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($invoice['booking_reference'])): ?>
                                                <a class="btn-action" href="https://wa.me/?text=<?php echo urlencode('Payment reminder for ' . $invoice['booking_reference'] . ' (' . $invoice['payment_reference'] . ')'); ?>" target="_blank" rel="noopener">
                                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                                </a>
                                            <?php endif; ?>
                                             
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="regenerate_invoice">
                                                <input type="hidden" name="payment_id" value="<?php echo $invoice['id']; ?>">
                                                <button type="submit" class="btn-action btn-regenerate" onclick="return confirm('Regenerate invoice? This will create a new invoice number.');">
                                                    <i class="fas fa-sync"></i> Regenerate
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <p>No invoices found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh filters when changed
        document.querySelectorAll('.filter-group select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    </script>
    <script src="js/admin-components.js"></script>
    <?php require_once 'includes/admin-footer.php'; ?>
