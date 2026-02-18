<?php
// Include admin initialization (PHP-only, no HTML output)
require_once 'admin-init.php';

$user = [
    'id' => $_SESSION['admin_user_id'],
    'username' => $_SESSION['admin_username'],
    'role' => $_SESSION['admin_role'],
    'full_name' => $_SESSION['admin_full_name']
];
$message = '';
$error = '';

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $booking_id = (int)($_POST['id'] ?? 0);

        if ($booking_id <= 0) {
            throw new Exception('Invalid booking id');
        }

        if ($action === 'convert') {
            // Get booking details
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            if ($booking['status'] !== 'tentative' || $booking['is_tentative'] != 1) {
                throw new Exception('This is not a tentative booking');
            }
            
            // Convert to confirmed
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', is_tentative = 0 WHERE id = ?");
            $update_stmt->execute([$booking_id]);
            
            // Log the conversion
            $log_stmt = $pdo->prepare("
                INSERT INTO tentative_booking_log (
                    booking_id, action, action_by, action_at, notes
                ) VALUES (?, 'converted', ?, NOW(), ?)
            ");
            $log_stmt->execute([
                $booking_id,
                $user['id'],
                'Converted from tentative to confirmed by admin'
            ]);
            
            // Send conversion email
            require_once '../config/email.php';
            $email_result = sendTentativeBookingConvertedEmail($booking);
            
            if ($email_result['success']) {
                $message = 'Tentative booking converted to confirmed! Conversion email sent to guest.';
            } else {
                $message = 'Tentative booking converted! (Email failed: ' . $email_result['message'] . ')';
            }
            
        } elseif ($action === 'cancel') {
            // Get booking details
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            // Cancel booking
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $update_stmt->execute([$booking_id]);
            
            // Log the cancellation
            $log_stmt = $pdo->prepare("
                INSERT INTO tentative_booking_log (
                    booking_id, action, action_by, action_at, notes
                ) VALUES (?, 'cancelled', ?, NOW(), ?)
            ");
            $log_stmt->execute([
                $booking_id,
                $user['id'],
                'Cancelled by admin'
            ]);
            
            $message = 'Tentative booking cancelled successfully.';
        }

    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch tentative bookings
try {
    $stmt = $pdo->query("
        SELECT b.*, r.name as room_name 
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'tentative' OR b.is_tentative = 1
        ORDER BY b.tentative_expires_at ASC
    ");
    $tentative_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Error fetching tentative bookings: ' . $e->getMessage();
    $tentative_bookings = [];
}

$site_name = getSetting('site_name');
$currency_symbol = getSetting('currency_symbol');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentative Bookings | <?php echo htmlspecialchars($site_name); ?> Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css"></head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>

    <div class="tentative-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Tentative Bookings
            </h1>
            <a href="bookings.php" class="btn btn-view">
                <i class="fas fa-arrow-left"></i> Back to All Bookings
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Tentative</h3>
                <div class="number"><?php echo count($tentative_bookings); ?></div>
            </div>
            <?php
                $expires_soon = 0;
                $expired = 0;
                $now = new DateTime();
                foreach ($tentative_bookings as $booking) {
                    if ($booking['tentative_expires_at']) {
                        $expires_at = new DateTime($booking['tentative_expires_at']);
                        $hours_until_expiry = ($expires_at->getTimestamp() - $now->getTimestamp()) / 3600;
                        if ($hours_until_expiry <= 0) {
                            $expired++;
                        } elseif ($hours_until_expiry <= 24) {
                            $expires_soon++;
                        }
                    }
                }
            ?>
            <div class="stat-card" style="border-left-color: #dc3545;">
                <h3>Expires Soon (24h)</h3>
                <div class="number" style="color: #dc3545;"><?php echo $expires_soon; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #6c757d;">
                <h3>Expired</h3>
                <div class="number" style="color: #6c757d;"><?php echo $expired; ?></div>
            </div>
        </div>

        <div class="bookings-container">
            <?php if (!empty($tentative_bookings)): ?>
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Reference</th>
                            <th style="width: 200px;">Guest Name</th>
                            <th style="width: 180px;">Room</th>
                            <th style="width: 140px;">Check In</th>
                            <th style="width: 140px;">Check Out</th>
                            <th style="width: 80px;">Nights</th>
                            <th style="width: 80px;">Guests</th>
                            <th style="width: 120px;">Total</th>
                            <th style="width: 180px;">Expires At</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 250px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $now = new DateTime();
                            foreach ($tentative_bookings as $booking): 
                                $expires_at = new DateTime($booking['tentative_expires_at']);
                                $hours_until_expiry = ($expires_at->getTimestamp() - $now->getTimestamp()) / 3600;
                                $is_expired = $hours_until_expiry <= 0;
                                $expires_soon = !$is_expired && $hours_until_expiry <= 24;
                                $row_class = $is_expired ? 'expired' : ($expires_soon ? 'expires-soon' : '');
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($booking['guest_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                <td><?php echo $booking['number_of_nights']; ?></td>
                                <td><?php echo $booking['number_of_guests']; ?></td>
                                <td><strong><?php echo $currency_symbol; ?><?php echo number_format($booking['total_amount'], 0); ?></strong></td>
                                <td>
                                    <?php echo date('M d, H:i', strtotime($booking['tentative_expires_at'])); ?>
                                    <br>
                                    <?php if ($is_expired): ?>
                                        <span class="expires-badge expires-expired-badge">Expired</span>
                                    <?php elseif ($expires_soon): ?>
                                        <span class="expires-badge expires-soon-badge">Expires Soon!</span>
                                    <?php else: ?>
                                        <span class="expires-badge expires-normal-badge">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-tentative">Tentative</span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!$is_expired): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="convert">
                                                <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="btn btn-convert" onclick="return confirm('Convert this tentative booking to confirmed?')">
                                                    <i class="fas fa-check"></i> Convert
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this tentative booking?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                        <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h3>No Tentative Bookings</h3>
                    <p>There are currently no tentative bookings in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 5 minutes for expiring bookings
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>

    <?php require_once 'includes/admin-footer.php'; ?>
