<?php
/**
 * Conference Rooms Management - Admin Panel
 * Card-based layout with modal editing, matching room-management.php style
 */
require_once 'admin-init.php';

require_once '../config/email.php';
require_once '../config/invoice.php';
require_once '../includes/alert.php';

function syncConferenceRoomManagedMedia(array $room): void
{
    if (!function_exists('upsertManagedMediaForSource')) {
        return;
    }

    $roomId = $room['id'] ?? null;
    if (!$roomId) {
        return;
    }

    upsertManagedMediaForSource('conference_rooms', $roomId, 'image_path', $room['image_path'] ?? null, [
        'title' => ($room['name'] ?? 'Conference Room') . ' (Image)',
        'description' => $room['description'] ?? null,
        'caption' => $room['description'] ?? null,
        'alt_text' => $room['name'] ?? 'Conference room image',
        'placement_key' => 'conference_rooms.image_path',
        'page_slug' => 'conference',
        'section_key' => 'conference_rooms',
        'entity_type' => 'conference_room',
        'entity_id' => (int)$roomId,
        'display_order' => (int)($room['display_order'] ?? 0),
        'use_case' => 'card_image',
        'media_type' => 'image',
    ]);
}

$message = '';
$error = '';

function uploadConferenceImage(array $fileInput): ?string
{
    if (empty($fileInput) || ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = __DIR__ . '/../images/conference/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    $filename = 'conference_' . time() . '_' . random_int(1000, 9999) . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($fileInput['tmp_name'], $destination)) {
        return 'images/conference/' . $filename;
    }

    return null;
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $imagePath = uploadConferenceImage($_FILES['image'] ?? []);

        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO conference_rooms (
                    name, description, capacity, size_sqm, daily_rate,
                    amenities, image_path, is_active, display_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['capacity'],
                $_POST['size_sqm'] ?: null,
                $_POST['daily_rate'],
                $_POST['amenities'] ?? '',
                $imagePath,
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['display_order'] ?? 0
            ]);

            $newConferenceRoomId = (int)$pdo->lastInsertId();
            if ($newConferenceRoomId > 0) {
                syncConferenceRoomManagedMedia([
                    'id' => $newConferenceRoomId,
                    'name' => $_POST['name'] ?? null,
                    'description' => $_POST['description'] ?? null,
                    'display_order' => $_POST['display_order'] ?? 0,
                    'image_path' => $imagePath,
                ]);
            }

            $message = 'Conference room added successfully!';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            }
        }

        if ($action === 'update') {
            if ($imagePath) {
                $stmt = $pdo->prepare("
                    UPDATE conference_rooms
                    SET name = ?, description = ?, capacity = ?, size_sqm = ?, daily_rate = ?,
                        amenities = ?, image_path = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['capacity'],
                    $_POST['size_sqm'] ?: null,
                    $_POST['daily_rate'],
                    $_POST['amenities'] ?? '',
                    $imagePath,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'] ?? 0,
                    $_POST['id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE conference_rooms
                    SET name = ?, description = ?, capacity = ?, size_sqm = ?, daily_rate = ?,
                        amenities = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['capacity'],
                    $_POST['size_sqm'] ?: null,
                    $_POST['daily_rate'],
                    $_POST['amenities'] ?? '',
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'] ?? 0,
                    $_POST['id']
                ]);
            }

            $roomId = (int)($_POST['id'] ?? 0);
            if ($roomId > 0) {
                $mediaStmt = $pdo->prepare("SELECT id, name, description, display_order, image_path FROM conference_rooms WHERE id = ? LIMIT 1");
                $mediaStmt->execute([$roomId]);
                $roomForMedia = $mediaStmt->fetch(PDO::FETCH_ASSOC);
                if ($roomForMedia) {
                    syncConferenceRoomManagedMedia($roomForMedia);
                }
            }

            $message = 'Conference room updated successfully!';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            }
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM conference_rooms WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = 'Conference room deleted successfully!';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }

        if ($action === 'toggle_active') {
            $stmt = $pdo->prepare("UPDATE conference_rooms SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = 'Status updated!';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

// Fetch conference rooms
try {
    $stmt = $pdo->query("SELECT * FROM conference_rooms ORDER BY display_order ASC, name ASC");
    $conference_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($conference_rooms) && function_exists('applyManagedMediaOverrides')) {
        foreach ($conference_rooms as &$conferenceRoomRow) {
            $conferenceRoomRow = applyManagedMediaOverrides($conferenceRoomRow, 'conference_rooms', $conferenceRoomRow['id'] ?? '', ['image_path']);
        }
        unset($conferenceRoomRow);
    }
} catch (PDOException $e) {
    $conference_rooms = [];
    $error = 'Error fetching conference rooms: ' . $e->getMessage();
}

// Handle enquiry status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enquiry_action'])) {
    try {
        $enquiry_id = $_POST['enquiry_id'] ?? 0;
        $action = $_POST['enquiry_action'];
        
        $stmt = $pdo->prepare("SELECT * FROM conference_inquiries WHERE id = ?");
        $stmt->execute([$enquiry_id]);
        $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($action === 'confirm') {
            $stmt = $pdo->prepare("UPDATE conference_inquiries SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$enquiry_id]);
            
            if ($enquiry) {
                $email_result = sendConferenceConfirmedEmail($enquiry);
                if ($email_result['success']) {
                    $message = 'Conference enquiry confirmed successfully! Confirmation email sent.';
                } else {
                    $message = 'Conference enquiry confirmed successfully! (Email not sent: ' . $email_result['message'] . ')';
                }
            } else {
                $message = 'Conference enquiry confirmed successfully!';
            }
        } elseif ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE conference_inquiries SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$enquiry_id]);
            
            if ($enquiry) {
                $email_result = sendConferenceCancelledEmail($enquiry);
                
                $email_sent = $email_result['success'];
                $email_status = $email_result['message'];
                logCancellationToDatabase(
                    $enquiry['id'],
                    $enquiry['inquiry_reference'],
                    'conference',
                    $enquiry['email'],
                    $user['id'],
                    'Cancelled by admin',
                    $email_sent,
                    $email_status
                );
                
                logCancellationToFile(
                    $enquiry['inquiry_reference'],
                    'conference',
                    $enquiry['email'],
                    $user['full_name'] ?? $user['username'],
                    'Cancelled by admin',
                    $email_sent,
                    $email_status
                );
                
                if ($email_sent) {
                    $message = 'Conference enquiry cancelled successfully! Cancellation email sent.';
                } else {
                    $message = 'Conference enquiry cancelled successfully! (Email not sent: ' . $email_status . ')';
                }
            } else {
                $message = 'Conference enquiry cancelled successfully!';
            }
        } elseif ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE conference_inquiries SET status = 'completed' WHERE id = ?");
            $stmt->execute([$enquiry_id]);
            $message = 'Conference marked as completed!';
        } elseif ($action === 'send_invoice') {
            if ($enquiry) {
                try {
                    $vatEnabled = getSetting('vat_enabled') === '1';
                    $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
                    
                    $totalAmount = (float)$enquiry['total_amount'];
                    $vatAmount = $vatEnabled ? ($totalAmount * ($vatRate / 100)) : 0;
                    $totalWithVat = $totalAmount + $vatAmount;
                    
                    $payment_reference = 'PAY-' . date('Y') . '-' . str_pad($enquiry_id, 6, '0', STR_PAD_LEFT);
                    
                    $insert_payment = $pdo->prepare("
                        INSERT INTO payments (
                            payment_reference, booking_type, booking_id, booking_reference,
                            payment_date, payment_amount, vat_rate, vat_amount, total_amount,
                            payment_method, payment_type, payment_status, invoice_generated,
                            status, recorded_by
                        ) VALUES (?, 'conference', ?, ?, CURDATE(), ?, ?, ?, ?, 'cash', 'full_payment', 'completed', 1, 'completed', ?)
                    ");
                    $insert_payment->execute([
                        $payment_reference,
                        $enquiry_id,
                        $enquiry['inquiry_reference'],
                        $totalAmount,
                        $vatRate,
                        $vatAmount,
                        $totalWithVat,
                        $user['id']
                    ]);
                    
                    $update_amounts = $pdo->prepare("
                        UPDATE conference_inquiries
                        SET amount_paid = ?, amount_due = 0, vat_rate = ?, vat_amount = ?,
                            total_with_vat = ?, last_payment_date = CURDATE(), payment_status = 'full_paid'
                        WHERE id = ?
                    ");
                    $update_amounts->execute([$totalWithVat, $vatRate, $vatAmount, $totalWithVat, $enquiry_id]);
                    
                    $invoice_result = sendConferenceInvoiceEmail($enquiry_id);
                    if ($invoice_result['success']) {
                        $message = 'Payment recorded successfully! Invoice sent to ' . htmlspecialchars($enquiry['email']);
                    } else {
                        $message = 'Payment recorded successfully! (Invoice email failed: ' . $invoice_result['message'] . ')';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to record payment: ' . $e->getMessage();
                    error_log("Conference payment error: " . $e->getMessage());
                }
            } else {
                $error = 'Enquiry not found!';
            }
        } elseif ($action === 'update_amount') {
            $amount = $_POST['total_amount'] ?? 0;
            $stmt = $pdo->prepare("UPDATE conference_inquiries SET total_amount = ? WHERE id = ?");
            $stmt->execute([$amount, $enquiry_id]);
            $message = 'Total amount updated successfully!';
        } elseif ($action === 'update_notes') {
            $notes = $_POST['notes'] ?? '';
            $stmt = $pdo->prepare("UPDATE conference_inquiries SET notes = ? WHERE id = ?");
            $stmt->execute([$notes, $enquiry_id]);
            $message = 'Notes updated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error updating enquiry: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch conference enquiries
try {
    $enquiries_stmt = $pdo->query("
        SELECT ci.*, cr.name as room_name
        FROM conference_inquiries ci
        LEFT JOIN conference_rooms cr ON ci.conference_room_id = cr.id
        ORDER BY ci.event_date DESC, ci.created_at DESC
    ");
    $conference_enquiries = $enquiries_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $conference_enquiries = [];
}

$currency = htmlspecialchars(getSetting('currency_symbol', 'MWK'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conference Rooms - Admin Panel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/conference-management.css">
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>
    
    <div class="content">
        <div class="page-header-row">
            <h2 class="page-title"><i class="fas fa-users"></i> Conference Rooms Management</h2>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn-action" type="button" style="background:var(--gold,#8B7355); color:var(--deep-navy,#111111); padding:12px 24px; font-size:14px; border-radius:8px;" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Room
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <?php showAlert($message, 'success'); ?>
        <?php endif; ?>
        <?php if ($error): ?>
            <?php showAlert($error, 'error'); ?>
        <?php endif; ?>

        <!-- Conference Rooms Cards -->
        <?php if (!empty($conference_rooms)): ?>
        <div class="conference-cards-grid" id="conferenceGrid">
            <?php foreach ($conference_rooms as $room): ?>
            <div class="conference-card" data-id="<?php echo $room['id']; ?>">
                <?php if ($room['display_order'] > 0): ?>
                    <span class="order-badge">#<?php echo $room['display_order']; ?></span>
                <?php endif; ?>

                <?php if (!empty($room['image_path'])): ?>
                    <?php $imgSrc = preg_match('#^https?://#i', $room['image_path']) ? $room['image_path'] : '../' . $room['image_path']; ?>
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                         alt="<?php echo htmlspecialchars($room['name']); ?>" 
                         class="conference-card-image"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="no-image-placeholder" style="display:none;"><i class="fas fa-users"></i><span>No Image</span></div>
                <?php else: ?>
                    <div class="no-image-placeholder"><i class="fas fa-users"></i><span>No Image</span></div>
                <?php endif; ?>

                <div class="conference-card-body">
                    <div class="conference-card-title">
                        <?php echo htmlspecialchars($room['name']); ?>
                    </div>
                    <div class="conference-card-desc"><?php echo htmlspecialchars($room['description'] ?? ''); ?></div>

                    <div class="conference-card-details">
                        <div class="detail-item detail-item-price"><i class="fas fa-tag"></i> <?php echo $currency; ?> <?php echo number_format($room['daily_rate'], 0); ?>/day</div>
                        <div class="detail-item"><i class="fas fa-users"></i> <?php echo $room['capacity']; ?> guests</div>
                        <div class="detail-item"><i class="fas fa-expand-arrows-alt"></i> <?php echo number_format($room['size_sqm'] ?? 0, 0); ?> sqm</div>
                    </div>

                    <?php if (!empty($room['amenities'])): ?>
                    <div style="font-size:11px; color:#888; margin-bottom:10px;">
                        <i class="fas fa-concierge-bell"></i> <?php echo htmlspecialchars(substr($room['amenities'], 0, 60)); ?><?php echo strlen($room['amenities']) > 60 ? '...' : ''; ?>
                    </div>
                    <?php endif; ?>

                    <div class="conference-card-meta">
                        <?php if ($room['is_active']): ?>
                            <span class="conference-badge active"><i class="fas fa-check"></i> Active</span>
                        <?php else: ?>
                            <span class="conference-badge inactive"><i class="fas fa-times"></i> Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="conference-card-actions">
                        <button class="btn-action btn-edit" type="button" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($room), ENT_QUOTES, "UTF-8"); ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-action btn-toggle-active" type="button" onclick="toggleActive(<?php echo $room['id']; ?>)" title="Toggle Active">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="btn-action btn-delete" type="button" onclick="if(confirm('Delete this conference room permanently?')) deleteRoom(<?php echo $room['id']; ?>)" title="Delete Room">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No conference rooms found. Click "Add New Room" to get started.</p>
        </div>
        <?php endif; ?>

        <!-- Conference Enquiries Section -->
        <div class="card" style="margin-top: 24px;">
            <h2><i class="fas fa-calendar-check"></i> Conference Enquiries</h2>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Event Date</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Attendees</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conference_enquiries)): ?>
                        <tr>
                            <td colspan="10" style="text-align:center; padding:40px; color:#999;">
                                <i class="fas fa-inbox" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                                No conference enquiries found
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($conference_enquiries as $enquiry): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($enquiry['inquiry_reference']); ?></strong></td>
                            <td><?php echo htmlspecialchars($enquiry['company_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($enquiry['contact_person']); ?><br>
                                <small><?php echo htmlspecialchars($enquiry['email']); ?></small><br>
                                <small><?php echo htmlspecialchars($enquiry['phone']); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($enquiry['event_date'])); ?></td>
                            <td>
                                <?php echo date('H:i', strtotime($enquiry['start_time'])); ?> -
                                <?php echo date('H:i', strtotime($enquiry['end_time'])); ?>
                            </td>
                            <td><?php echo htmlspecialchars($enquiry['room_name'] ?? 'N/A'); ?></td>
                            <td><?php echo (int) $enquiry['number_of_attendees']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $enquiry['status']; ?>">
                                    <?php echo ucfirst($enquiry['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($enquiry['total_amount']): ?>
                                    <?php echo $currency; ?> <?php echo number_format($enquiry['total_amount'], 0); ?>
                                <?php else: ?>
                                    <em>Pending</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="quick-actions">
                                    <?php if ($enquiry['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="enquiry_action" value="confirm">
                                            <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Confirm this conference enquiry?');">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($enquiry['status'] === 'confirmed'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="enquiry_action" value="complete">
                                            <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Mark this conference as completed?');">
                                                <i class="fas fa-check-circle"></i> Complete
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="enquiry_action" value="send_invoice">
                                            <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['id']; ?>">
                                            <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Generate and send invoice for this conference?');">
                                                <i class="fas fa-file-invoice-dollar"></i> Paid
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($enquiry['status'], ['pending', 'confirmed'])): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="enquiry_action" value="cancel">
                                            <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Cancel this conference enquiry?');">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showEnquiryDetails(<?php echo htmlspecialchars(json_encode($enquiry)); ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Conference Room Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Conference Room</h3>
                <button class="modal-close" type="button" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addForm">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Room Information</div>
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Featured Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-cog"></i> Room Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Capacity *</label>
                                <input type="number" name="capacity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Size (sqm)</label>
                                <input type="number" step="0.01" name="size_sqm">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Day Rate *</label>
                                <input type="number" step="0.01" name="daily_rate" required>
                            </div>
                            <div class="form-group">
                                <label>Display Order</label>
                                <input type="number" name="display_order" value="0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Amenities (comma separated)</label>
                            <textarea name="amenities" rows="2" placeholder="Projector, Whiteboard, WiFi, Catering"></textarea>
                        </div>
                    </div>

                    <div class="form-section" style="border-bottom:none;">
                        <div class="form-section-title"><i class="fas fa-toggle-on"></i> Status</div>
                        <div class="checkbox-row">
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked> Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="closeAddModal()" style="padding:10px 24px; border:1px solid #ddd; border-radius:6px; background:white; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 24px; border:none; border-radius:6px; background:var(--gold,#8B7355); color:var(--deep-navy,#111111); font-weight:600; cursor:pointer;">
                        <i class="fas fa-plus"></i> Add Room
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Conference Room Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editModalTitle"><i class="fas fa-edit"></i> Edit Conference Room</h3>
                <button class="modal-close" type="button" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                
                <div class="modal-body">
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Room Information</div>
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" id="editName" required>
                        </div>
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" id="editDescription" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Replace Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-cog"></i> Room Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Capacity *</label>
                                <input type="number" name="capacity" id="editCapacity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Size (sqm)</label>
                                <input type="number" step="0.01" name="size_sqm" id="editSize">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Day Rate *</label>
                                <input type="number" step="0.01" name="daily_rate" id="editRate" required>
                            </div>
                            <div class="form-group">
                                <label>Display Order</label>
                                <input type="number" name="display_order" id="editOrder" value="0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Amenities (comma separated)</label>
                            <textarea name="amenities" id="editAmenities" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="form-section" style="border-bottom:none;">
                        <div class="form-section-title"><i class="fas fa-toggle-on"></i> Status</div>
                        <div class="checkbox-row">
                            <label>
                                <input type="checkbox" name="is_active" id="editIsActive" value="1"> Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="closeEditModal()" style="padding:10px 24px; border:1px solid #ddd; border-radius:6px; background:white; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 24px; border:none; border-radius:6px; background:var(--gold,#8B7355); color:var(--deep-navy,#111111); font-weight:600; cursor:pointer;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enquiry Details Modal -->
    <div id="enquiryModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-alt"></i> Conference Enquiry Details</h3>
                <button class="modal-close" type="button" onclick="closeEnquiryModal()">&times;</button>
            </div>
            <div class="modal-body" id="enquiryModalBody">
            </div>
        </div>
    </div>

    <script>
    // ===== ADD MODAL =====
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }
    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    // ===== EDIT MODAL =====
    function openEditModal(room) {
        document.getElementById('editModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit: ' + escapeHtml(room.name);
        document.getElementById('editId').value = room.id;
        document.getElementById('editName').value = room.name || '';
        document.getElementById('editDescription').value = room.description || '';
        document.getElementById('editCapacity').value = room.capacity || '';
        document.getElementById('editSize').value = room.size_sqm || '';
        document.getElementById('editRate').value = room.daily_rate || '';
        document.getElementById('editOrder').value = room.display_order || 0;
        document.getElementById('editAmenities').value = room.amenities || '';
        document.getElementById('editIsActive').checked = room.is_active == 1;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // ===== TOGGLE & DELETE =====
    function toggleActive(id) {
        var fd = new FormData();
        fd.append('action', 'toggle_active');
        fd.append('id', id);
        fetch(window.location.href, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(r) { if (r.ok) window.location.reload(); else alert('Error'); })
            .catch(function() { alert('Error'); });
    }

    function deleteRoom(id) {
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch(window.location.href, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(r) { if (r.ok) window.location.reload(); else alert('Error'); })
            .catch(function() { alert('Error'); });
    }

    // ===== ENQUIRY MODAL =====
    function showEnquiryDetails(enquiry) {
        const modal = document.getElementById('enquiryModal');
        const body = document.getElementById('enquiryModalBody');
        
        body.innerHTML = `
            <div class="enquiry-details">
                <div class="detail-row">
                    <strong>Reference:</strong>
                    <span>${escapeHtml(enquiry.inquiry_reference)}</span>
                </div>
                <div class="detail-row">
                    <strong>Company:</strong>
                    <span>${escapeHtml(enquiry.company_name)}</span>
                </div>
                <div class="detail-row">
                    <strong>Contact Person:</strong>
                    <span>${escapeHtml(enquiry.contact_person)}</span>
                </div>
                <div class="detail-row">
                    <strong>Email:</strong>
                    <span>${escapeHtml(enquiry.email)}</span>
                </div>
                <div class="detail-row">
                    <strong>Phone:</strong>
                    <span>${escapeHtml(enquiry.phone)}</span>
                </div>
                <div class="detail-row">
                    <strong>Event Date:</strong>
                    <span>${new Date(enquiry.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                </div>
                <div class="detail-row">
                    <strong>Time:</strong>
                    <span>${enquiry.start_time} - ${enquiry.end_time}</span>
                </div>
                <div class="detail-row">
                    <strong>Conference Room:</strong>
                    <span>${escapeHtml(enquiry.room_name || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <strong>Number of Attendees:</strong>
                    <span>${enquiry.number_of_attendees}</span>
                </div>
                <div class="detail-row">
                    <strong>Event Type:</strong>
                    <span>${escapeHtml(enquiry.event_type || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <strong>Status:</strong>
                    <span class="badge badge-${enquiry.status}">${enquiry.status.charAt(0).toUpperCase() + enquiry.status.slice(1)}</span>
                </div>
                <div class="detail-row">
                    <strong>Catering Required:</strong>
                    <span>${enquiry.catering_required ? 'Yes' : 'No'}</span>
                </div>
                <div class="detail-row">
                    <strong>AV Equipment:</strong>
                    <span>${escapeHtml(enquiry.av_equipment || 'None')}</span>
                </div>
                <div class="detail-row">
                    <strong>Special Requirements:</strong>
                    <span>${escapeHtml(enquiry.special_requirements || 'None')}</span>
                </div>
                <div class="detail-row">
                    <strong>Total Amount:</strong>
                    <span>${enquiry.total_amount ? '<?php echo $currency; ?> ' + Number(enquiry.total_amount).toLocaleString() : 'Pending'}</span>
                </div>
                <div class="detail-row">
                    <strong>Notes:</strong>
                    <span>${escapeHtml(enquiry.notes || 'None')}</span>
                </div>
                
                <div class="modal-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="enquiry_action" value="update_amount">
                        <input type="hidden" name="enquiry_id" value="${enquiry.id}">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label>Update Total Amount (<?php echo $currency; ?>):</label>
                            <input type="number" name="total_amount" step="0.01" value="${enquiry.total_amount || ''}" style="width: 150px;">
                        </div>
                        <button type="submit" class="btn">Update Amount</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="enquiry_action" value="update_notes">
                        <input type="hidden" name="enquiry_id" value="${enquiry.id}">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label>Update Notes:</label>
                            <textarea name="notes" rows="3" style="width: 100%; max-width: 400px;">${escapeHtml(enquiry.notes || '')}</textarea>
                        </div>
                        <button type="submit" class="btn">Update Notes</button>
                    </form>
                </div>
            </div>
        `;
        
        modal.style.display = 'flex';
    }

    function closeEnquiryModal() {
        document.getElementById('enquiryModal').style.display = 'none';
    }

    // ===== CLOSE MODALS ON OUTSIDE CLICK =====
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    // Helper
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    </script>

    <?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>