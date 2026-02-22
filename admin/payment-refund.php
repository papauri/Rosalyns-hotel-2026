<?php
/**
 * Payment Refund Processing
 * Handles refund creation and processing for existing payments
 */

// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

require_once '../includes/alert.php';
require_once 'includes/finance-schema.php';

$message = '';
$error = '';
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get the original payment details
if ($payment_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   CASE WHEN p.booking_type = 'room' THEN b.guest_name
                        WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['company']}
                   END as customer_name,
                   CASE WHEN p.booking_type = 'room' THEN b.guest_email
                        WHEN p.booking_type = 'conference' THEN ci.{$conferenceFields['email']}
                   END as customer_email
            FROM payments p
            LEFT JOIN bookings b ON p.booking_type = 'room' AND p.booking_id = b.id
            LEFT JOIN conference_inquiries ci ON p.booking_type = 'conference' AND p.booking_id = ci.id
            WHERE p.id = ? AND p.deleted_at IS NULL
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $error = 'Payment not found.';
        } elseif (!in_array($payment['payment_status'], ['completed', 'paid'], true)) {
            $error = 'Refunds can only be processed for completed or paid payments.';
        } elseif ($payment['payment_type'] === 'refund') {
            $error = 'Cannot refund a refund transaction.';
        }
    } catch (PDOException $e) {
        $error = 'Error loading payment: ' . $e->getMessage();
        $payment = null;
    }
} else {
    $error = 'Invalid payment ID.';
}

$site_name = getSetting('site_name');
$currency_symbol = getSetting('currency_symbol', 'K');

// Handle refund form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $payment) {
    if ($_POST['action'] === 'create_refund') {
        try {
            $refund_amount = floatval($_POST['refund_amount'] ?? 0);
            $refund_reason = $_POST['refund_reason'] ?? '';
            $refund_notes = $_POST['refund_notes'] ?? '';
            $refund_status = $_POST['refund_status'] ?? 'pending';

            // Validate inputs
            if ($refund_amount <= 0) {
                throw new Exception('Refund amount must be greater than zero.');
            }
            if ($refund_amount > $payment['total_amount']) {
                throw new Exception('Refund amount cannot exceed original payment amount.');
            }
            if (!in_array($refund_reason, ['early_checkout', 'late_checkout_charge', 'cancellation', 'service_issue', 'overpayment', 'other'], true)) {
                throw new Exception('Invalid refund reason.');
            }
            if (!in_array($refund_status, ['pending', 'processing', 'completed', 'failed'], true)) {
                throw new Exception('Invalid refund status.');
            }

            // Calculate VAT portion of refund (pro-rated)
            $vat_rate = $payment['vat_rate'] ?? 0;
            $vat_amount = round($refund_amount * ($vat_rate / (100 + $vat_rate)), 2);
            $payment_amount = $refund_amount - $vat_amount;

            // Generate refund reference
            $year = date('Y');
            $refundRef = 'REF-' . $year . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);

            // Start transaction
            $pdo->beginTransaction();

            // Insert refund record
            $insertStmt = $pdo->prepare("
                INSERT INTO payments (
                    payment_reference, booking_type, booking_id, booking_reference,
                    payment_date, payment_amount, vat_rate, vat_amount, total_amount,
                    payment_method, payment_type, payment_status, original_payment_id,
                    refund_reason, refund_status, refund_amount, refund_notes,
                    recorded_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'refund', ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $insertStmt->execute([
                $refundRef,
                $payment['booking_type'],
                $payment['booking_id'],
                $payment['booking_reference'],
                date('Y-m-d'),
                $payment_amount,
                $vat_rate,
                $vat_amount,
                $refund_amount,
                $payment['payment_method'],
                $refund_status,
                $payment_id,
                $refund_reason,
                $refund_status,
                $refund_amount,
                $refund_notes,
                $_SESSION['admin_user_id'] ?? null
            ]);

            // Update original payment status if full refund
            if ($refund_amount >= $payment['total_amount']) {
                $updateStmt = $pdo->prepare("
                    UPDATE payments 
                    SET payment_status = 'refunded', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$payment_id]);
            }

            $pdo->commit();

            $message = 'Refund created successfully! Reference: ' . $refundRef;

            // Log the action to admin_activity_log
            $logStmt = $pdo->prepare("
                INSERT INTO admin_activity_log (user_id, username, action, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['admin_user_id'] ?? null,
                $_SESSION['admin_username'] ?? 'system',
                'refund_created',
                "Refund {$refundRef} created for payment {$payment['payment_reference']}, amount: {$currency_symbol}{$refund_amount}",
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error creating refund: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Refund | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
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
                <a href="payments.php" class="btn btn-secondary" style="margin-bottom: 10px;">
                    <i class="fas fa-arrow-left"></i> Back to Payments
                </a>
                <h2 class="section-title">Process Refund</h2>
                <p style="color: #666; margin-top: 4px;">Create a refund for an existing payment</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="background: var(--finance-danger-bg); border: 1px solid var(--finance-danger-border); color: var(--finance-danger); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success" style="background: var(--finance-success-bg); border: 1px solid var(--finance-success-border); color: var(--finance-success); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <a href="payments.php" class="btn btn-primary" style="margin-left: 10px;">Return to Payments</a>
            </div>
        <?php endif; ?>

        <?php if ($payment && !$message): ?>
            <!-- Original Payment Details -->
            <div class="section-card">
                <h3><i class="fas fa-receipt"></i> Original Payment Details</h3>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="label">Payment Reference</div>
                        <div class="value"><?php echo htmlspecialchars($payment['payment_reference']); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Customer</div>
                        <div class="value"><?php echo htmlspecialchars($payment['customer_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Original Amount</div>
                        <div class="value"><?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 0); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Payment Method</div>
                        <div class="value"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Refund Form -->
            <div class="section-card">
                <h3><i class="fas fa-undo"></i> Refund Details</h3>
                <form method="POST" class="form-container">
                    <input type="hidden" name="action" value="create_refund">
                    
                    <div class="filter-form">
                        <div class="filter-group">
                            <label for="refund_amount">Refund Amount *</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666;"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="refund_amount" name="refund_amount" 
                                       step="0.01" min="0.01" max="<?php echo $payment['total_amount']; ?>" 
                                       value="<?php echo $payment['total_amount']; ?>" required
                                       style="padding-left: 30px;">
                            </div>
                            <small style="color: #666;">Maximum: <?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 0); ?></small>
                        </div>

                        <div class="filter-group">
                            <label for="refund_reason">Refund Reason *</label>
                            <select id="refund_reason" name="refund_reason" required>
                                <option value="">Select a reason</option>
                                <option value="early_checkout">Early Checkout</option>
                                <option value="late_checkout_charge">Late Checkout Charge</option>
                                <option value="cancellation">Cancellation</option>
                                <option value="service_issue">Service Issue</option>
                                <option value="overpayment">Overpayment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="refund_status">Refund Status *</label>
                            <select id="refund_status" name="refund_status" required>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-group" style="margin-top: 14px;">
                        <label for="refund_notes">Refund Notes</label>
                        <textarea id="refund_notes" name="refund_notes" rows="3" 
                                  placeholder="Additional details about this refund..."></textarea>
                    </div>

                    <!-- Refund Summary -->
                    <div class="section-card" style="background: var(--finance-bg); margin-top: 18px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px;">Refund Summary</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 13px;">
                            <div>
                                <span style="color: var(--finance-muted);">Refund Amount (excl. VAT):</span>
                                <div id="summary_excl_vat" style="font-weight: 600;"><?php echo $currency_symbol; ?>0.00</div>
                            </div>
                            <div>
                                <span style="color: var(--finance-muted);">VAT Amount:</span>
                                <div id="summary_vat" style="font-weight: 600;"><?php echo $currency_symbol; ?>0.00</div>
                            </div>
                            <div>
                                <span style="color: var(--finance-muted);">Total Refund:</span>
                                <div id="summary_total" style="font-weight: 700; color: var(--finance-danger);"><?php echo $currency_symbol; ?>0.00</div>
                            </div>
                            <div>
                                <span style="color: var(--finance-muted);">Remaining Balance:</span>
                                <div id="summary_remaining" style="font-weight: 600;"><?php echo $currency_symbol; ?><?php echo number_format($payment['total_amount'], 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Process Refund
                        </button>
                        <a href="payments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once 'includes/admin-footer.php'; ?>

    <script>
        // Calculate refund summary in real-time
        const originalAmount = <?php echo $payment['total_amount'] ?? 0; ?>;
        const vatRate = <?php echo $payment['vat_rate'] ?? 0; ?>;
        const currencySymbol = '<?php echo $currency_symbol; ?>';

        function updateSummary() {
            const refundAmount = parseFloat(document.getElementById('refund_amount').value) || 0;
            
            // Calculate VAT portion (pro-rated)
            const vatAmount = refundAmount * (vatRate / (100 + vatRate));
            const exclVat = refundAmount - vatAmount;
            const remaining = originalAmount - refundAmount;

            document.getElementById('summary_excl_vat').textContent = currencySymbol + exclVat.toFixed(2);
            document.getElementById('summary_vat').textContent = currencySymbol + vatAmount.toFixed(2);
            document.getElementById('summary_total').textContent = currencySymbol + refundAmount.toFixed(2);
            document.getElementById('summary_remaining').textContent = currencySymbol + remaining.toFixed(2);
        }

        document.getElementById('refund_amount').addEventListener('input', updateSummary);
        updateSummary(); // Initial calculation
    </script>
</body>
</html>
