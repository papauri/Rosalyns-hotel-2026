<?php
/**
 * Invoice Generation and Email System
 * Generates professional PDF invoices for booking payments
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Try to load TCPDF if available
$tcpdf_loaded = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Try Composer autoload
    $autoload = include __DIR__ . '/../vendor/autoload.php';
    if (class_exists('TCPDF')) {
        // Check if constants are defined (they might not be with autoload only)
        if (!defined('PDF_PAGE_ORIENTATION') || !defined('PDF_UNIT') || !defined('PDF_PAGE_FORMAT')) {
            // Try to include tcpdf.php directly to get constants
            if (file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php')) {
                require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
            }
        }
        // Verify constants are now defined
        if (defined('PDF_PAGE_ORIENTATION') && defined('PDF_UNIT') && defined('PDF_PAGE_FORMAT')) {
            $tcpdf_loaded = true;
        }
    }
} elseif (file_exists(__DIR__ . '/../TCPDF/tcpdf.php')) {
    // Try direct TCPDF include
    require_once __DIR__ . '/../TCPDF/tcpdf.php';
    if (class_exists('TCPDF') && defined('PDF_PAGE_ORIENTATION') && defined('PDF_UNIT') && defined('PDF_PAGE_FORMAT')) {
        $tcpdf_loaded = true;
    }
}

// Define fallback constants if TCPDF is not loaded or constants are missing
if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
}
if (!defined('PDF_UNIT')) {
    define('PDF_UNIT', 'mm');
}
if (!defined('PDF_PAGE_FORMAT')) {
    define('PDF_PAGE_FORMAT', 'A4');
}

/**
 * Generate PDF invoice for a booking
 * 
 * @param int $booking_id Booking ID
 * @return string PDF file path or false on failure
 */
function generateInvoicePDF($booking_id) {
    global $pdo, $tcpdf_loaded;
    
    try {
        // Get booking details
        $stmt = $pdo->prepare("
            SELECT b.*, r.name as room_name, r.image_url,
                   s.setting_value as site_name
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN site_settings s ON s.setting_key = 'site_name'
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Get hotel contact details
        $site_name = getSetting('site_name');
        $email_address = getSetting('email_from_email');
        $phone_number = getSetting('phone_main');
        $address = getSetting('address_line1') . ', ' .
                   getSetting('address_line2') . ', ' .
                   getSetting('address_country');
        $currency_symbol = getSetting('currency_symbol');
        
        // Create invoice directory if it doesn't exist
        $invoiceDir = __DIR__ . '/../invoices';
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        
        // Generate unique invoice filename - use sequential invoice number from settings
        $invoice_prefix = getSetting('invoice_prefix', 'INV');
        $invoice_start = (int)getSetting('invoice_start_number', 1000);
        
        // Get the next invoice number
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) as max_inv FROM payments WHERE invoice_number LIKE ?");
        $stmt->execute([$invoice_prefix . '-' . date('Y') . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_number = max($invoice_start, ($result['max_inv'] ?? 0) + 1);
        
        $invoice_number = $invoice_prefix . '-' . date('Y') . '-' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
        $filename = $invoice_number . '.pdf';
        $filepath = $invoiceDir . '/' . $filename;
        
        if ($tcpdf_loaded) {
            // Use TCPDF for professional PDF generation
            $tcpdfClass = 'TCPDF';
            $pdf = new $tcpdfClass(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator($site_name);
            $pdf->SetAuthor($site_name);
            $pdf->SetTitle('Invoice ' . $invoice_number);
            $pdf->SetSubject('Payment Invoice');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Build HTML content
            $html = buildInvoiceHTML($booking, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol);
            
            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Save PDF
            $pdf->Output($filepath, 'F');
            
        } else {
            // Fallback: Generate HTML invoice and save as file
            $html = buildInvoiceHTML($booking, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol);
            
            // Wrap in complete HTML document
            $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . $invoice_number . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; }
        .invoice-header { background: linear-gradient(135deg, #1A1A1A 0%, #2A2A2A 100%); color: white; padding: 30px; }
        .invoice-header h1 { margin: 0; color: #8B7355; }
        .invoice-body { padding: 30px; }
        .invoice-details { margin-bottom: 30px; }
        .invoice-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .invoice-label { font-weight: bold; color: #333; }
        .invoice-value { color: #666; }
        .total-section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #8B7355; }
        .footer { text-align: center; padding: 20px; background: #f8f9fa; border-top: 1px solid #ddd; }
    </style>
</head>
<body>' . $html . '</body></html>';
            
            // Save as HTML (can be opened in browser and printed as PDF)
            $htmlFilepath = str_replace('.pdf', '.html', $filepath);
            file_put_contents($htmlFilepath, $fullHtml);
            
            // Return array with both paths and invoice number
            return [
                'filepath' => $htmlFilepath,
                'invoice_number' => $invoice_number,
                'relative_path' => 'invoices/' . basename($htmlFilepath)
            ];
        }
        
        // Return array with both paths and invoice number
        return [
            'filepath' => $filepath,
            'invoice_number' => $invoice_number,
            'relative_path' => 'invoices/' . $filename
        ];
        
    } catch (Exception $e) {
        error_log("Generate Invoice PDF Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get hotel logo URL for invoices
 */
function getInvoiceLogoUrl() {
    $logo_url = getSetting('logo_url', '');
    $site_url = getSetting('site_url', '');
    
    // Fallback to default logo if not set
    if (empty($logo_url)) {
        $logo_url = 'images/logo/logo.png';
    }
    
    // If logo is a relative path, make it absolute
    if (!empty($logo_url) && strpos($logo_url, 'http') !== 0) {
        $logo_url = rtrim($site_url, '/') . '/' . ltrim($logo_url, '/');
    }
    
    return $logo_url;
}

/**
 * Build HTML content for invoice - Stunning State-of-the-Art PDF Design
 */
function buildInvoiceHTML($booking, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol) {
    global $pdo;
    
    $check_in = date('F j, Y', strtotime($booking['check_in_date']));
    $check_out = date('F j, Y', strtotime($booking['check_out_date']));
    
    // Get logo URL
    $logo_url = getInvoiceLogoUrl();
    $logo_html = '';
    if (!empty($logo_url)) {
        $logo_html = '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($site_name) . '" style="max-width: 200px; height: auto; display: block;">';
    }
    $childGuests = (int)($booking['child_guests'] ?? 0);
    $adultGuests = (int)($booking['adult_guests'] ?? max(1, ((int)($booking['number_of_guests'] ?? 1)) - $childGuests));
    $childSupplementTotal = (float)($booking['child_supplement_total'] ?? 0);
    $baseAmount = max(0, (float)$booking['total_amount'] - $childSupplementTotal);
    $childMultiplier = (float)($booking['child_price_multiplier'] ?? getSetting('booking_child_price_multiplier', getSetting('child_guest_price_multiplier', 50)));
    
    // Get VAT settings - more flexible check
    $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
    $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
    $vatNumber = getSetting('vat_number');
    
    // Get folio charges for this booking
    $folioCharges = [];
    $folioTotal = 0;
    $folioVat = 0;
    try {
        $chargesStmt = $pdo->prepare("
            SELECT charge_type, description, quantity, unit_price, line_subtotal, vat_amount, line_total, posted_at
            FROM booking_charges
            WHERE booking_id = ? AND voided = 0
            ORDER BY posted_at ASC, id ASC
        ");
        $chargesStmt->execute([$booking['id']]);
        $folioCharges = $chargesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($folioCharges as $charge) {
            $folioTotal += (float)$charge['line_total'];
            $folioVat += (float)$charge['vat_amount'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching folio charges: " . $e->getMessage());
    }
    
    // Get payment details for this booking
    $paymentsStmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE booking_type = 'room' AND booking_id = ?
        AND payment_status = 'completed' AND deleted_at IS NULL
        ORDER BY payment_date ASC
    ");
    $paymentsStmt->execute([$booking['id']]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $roomSubtotal = (float)$booking['total_amount'];
    $extrasSubtotal = $folioTotal - $folioVat;
    $subtotal = $roomSubtotal + $extrasSubtotal;
    $vatAmount = ($vatEnabled ? ($roomSubtotal * ($vatRate / 100)) : 0) + $folioVat;
    $totalWithVat = $roomSubtotal + $folioTotal + ($vatEnabled ? ($roomSubtotal * ($vatRate / 100)) : 0);
    
    // Calculate amount paid and balance due
    $amountPaid = 0;
    foreach ($payments as $payment) {
        $amountPaid += (float)$payment['total_amount'];
    }
    $balanceDue = max(0, $totalWithVat - $amountPaid);
    
    // Build folio items HTML
    $folioItemsHTML = '';
    if (!empty($folioCharges)) {
        $folioItemsHTML = '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h4 style="color: #1A1A1A; margin-top: 0; border-bottom: 2px solid #8B7355; padding-bottom: 10px;">Folio Items / Extras</h4>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd; font-weight: bold; color: #666; font-size: 12px;">
                        <span style="flex: 0 0 80px;">Date</span>
                        <span style="flex: 1;">Description</span>
                        <span style="flex: 0 0 100px; text-align: right;">Amount</span>
                    </div>';
        
        foreach ($folioCharges as $charge) {
            $chargeDate = date('M j, Y', strtotime($charge['posted_at']));
            $chargeLabel = htmlspecialchars($charge['description']);
            if ($charge['quantity'] != 1) {
                $chargeLabel .= ' x ' . number_format($charge['quantity'], 0);
            }
            
            $folioItemsHTML .= '<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                        <span style="flex: 0 0 80px; font-size: 12px; color: #666;">' . $chargeDate . '</span>
                        <span style="flex: 1;">' . $chargeLabel . '</span>
                        <span style="flex: 0 0 100px; text-align: right;">' . $currency_symbol . ' ' . number_format($charge['line_total'], 2) . '</span>
                    </div>';
        }
        
        $folioItemsHTML .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-top: 1px solid #ddd; margin-top: 10px; font-weight: bold;">
                    <span style="flex: 0 0 80px;"></span>
                    <span style="flex: 1;">Folio Subtotal:</span>
                    <span style="flex: 0 0 100px; text-align: right;">' . $currency_symbol . ' ' . number_format($folioTotal, 2) . '</span>
                </div>
            </div>';
    }
    
    // Build payment details HTML
    $paymentDetailsHTML = '';
    if (!empty($payments)) {
        $paymentDetailsHTML = '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h4 style="color: #1A1A1A; margin-top: 0;">Payment History</h4>';
        
        foreach ($payments as $payment) {
            $paymentDetailsHTML .= '<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd;">
                        <span>' . date('M j, Y', strtotime($payment['payment_date'])) . ' (' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . ')</span>
                        <span>' . $currency_symbol . ' ' . number_format($payment['total_amount'], 2) . '</span>
                    </div>';
        }
        
        $paymentDetailsHTML .= '</div>';
    }
    
    // Build VAT section HTML
    $vatSectionHTML = '';
    if ($vatEnabled && $vatAmount > 0) {
        $vatSectionHTML = '<div class="invoice-row">
                    <span class="invoice-label">Subtotal (excl. VAT):</span>
                    <span class="invoice-value">' . $currency_symbol . ' ' . number_format($subtotal, 2) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">VAT (' . number_format($vatRate, 2) . '%):</span>
                    <span class="invoice-value">' . $currency_symbol . ' ' . number_format($vatAmount, 2) . '</span>
                </div>';
        if ($vatNumber) {
            $vatSectionHTML .= '<div class="invoice-row">
                    <span class="invoice-label">VAT Number:</span>
                    <span class="invoice-value">' . htmlspecialchars($vatNumber) . '</span>
                </div>';
        }
    }
    
    // Payment status styling
    $statusColor = $balanceDue <= 0 ? '#2E7D32' : '#C62828';
    $statusBgColor = $balanceDue <= 0 ? '#E8F5E9' : '#FFEBEE';
    $statusText = $balanceDue <= 0 ? 'PAID IN FULL' : 'PARTIAL PAYMENT';
    
    // Build charges table rows
    $chargesTableRows = '';
    // Room charge row
    $chargesTableRows .= '
        <tr>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; color: #424242;">Room Accommodation</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: center; color: #616161;">' . $booking['number_of_nights'] . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; color: #616161;">' . $currency_symbol . ' ' . number_format($roomSubtotal / max(1, $booking['number_of_nights']), 2) . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 500; color: #424242;">' . $currency_symbol . ' ' . number_format($roomSubtotal, 2) . '</td>
        </tr>';
    
    // Child supplement row
    if ($childGuests > 0) {
        $chargesTableRows .= '
        <tr>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; color: #424242;">Child Supplement (' . $childGuests . ' child' . ($childGuests > 1 ? 'ren' : '') . ')</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: center; color: #616161;">' . $childGuests . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; color: #616161;">' . $currency_symbol . ' ' . number_format($childSupplementTotal / max(1, $childGuests), 2) . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 500; color: #424242;">' . $currency_symbol . ' ' . number_format($childSupplementTotal, 2) . '</td>
        </tr>';
    }
    
    // Folio charges rows
    foreach ($folioCharges as $charge) {
        $chargesTableRows .= '
        <tr>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; color: #424242;">' . htmlspecialchars($charge['description']) . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: center; color: #616161;">' . number_format($charge['quantity'], 0) . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; color: #616161;">' . $currency_symbol . ' ' . number_format($charge['unit_price'], 2) . '</td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; font-weight: 500; color: #424242;">' . $currency_symbol . ' ' . number_format($charge['line_total'], 2) . '</td>
        </tr>';
    }
    
    // Build payments table
    $paymentsTableHTML = '';
    if (!empty($payments)) {
        $paymentsTableHTML = '<div style="margin-top: 25px;">
            <h3 style="color: #8B7355; font-size: 14px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Payment History</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F5F5F5;">
                        <th style="padding: 10px 8px; text-align: left; font-size: 11px; color: #757575; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Date</th>
                        <th style="padding: 10px 8px; text-align: left; font-size: 11px; color: #757575; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Method</th>
                        <th style="padding: 10px 8px; text-align: right; font-size: 11px; color: #757575; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Amount</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($payments as $payment) {
            $paymentsTableHTML .= '
                    <tr>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #E0E0E0; color: #424242;">' . date('M j, Y', strtotime($payment['payment_date'])) . '</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #E0E0E0; color: #616161;">' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</td>
                        <td style="padding: 10px 8px; border-bottom: 1px solid #E0E0E0; text-align: right; color: #424242;">' . $currency_symbol . ' ' . number_format($payment['total_amount'], 2) . '</td>
                    </tr>';
        }
        
        $paymentsTableHTML .= '
                </tbody>
            </table>
        </div>';
    }

    return '
    <!-- STUNNING MODERN INVOICE DESIGN -->
    <div style="font-family: Helvetica, Arial, sans-serif; color: #333; max-width: 100%;">
        
        <!-- HEADER SECTION -->
        <div style="background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%); padding: 30px 25px; margin: -15px -15px 0 -15px;">
            <table style="width: 100%;">
                <tr>
                    <td style="vertical-align: middle; width: 50%;">
                        ' . $logo_html . '
                    </td>
                    <td style="vertical-align: middle; text-align: right; width: 50%;">
                        <h1 style="color: #8B7355; font-size: 28px; margin: 0 0 8px 0; font-weight: 300; letter-spacing: 3px;">INVOICE</h1>
                        <p style="color: #FFFFFF; font-size: 13px; margin: 0; opacity: 0.9;">' . htmlspecialchars($invoice_number) . '</p>
                        <p style="color: #FFFFFF; font-size: 12px; margin: 5px 0 0 0; opacity: 0.7;">Issued: ' . date('F j, Y') . '</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- STATUS BANNER -->
        <div style="background: ' . $statusBgColor . '; padding: 12px 25px; margin: 0 -15px 25px -15px; text-align: center;">
            <span style="color: ' . $statusColor . '; font-weight: 600; font-size: 14px; letter-spacing: 2px;">✓ ' . $statusText . '</span>
        </div>
        
        <!-- GUEST & HOTEL INFO -->
        <table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                    <div style="background: #FAFAFA; border-left: 4px solid #8B7355; padding: 20px;">
                        <h3 style="color: #8B7355; font-size: 11px; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">Billed To</h3>
                        <p style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600; color: #212121;">' . htmlspecialchars($booking['guest_name']) . '</p>
                        <p style="margin: 0 0 3px 0; font-size: 12px; color: #616161;">' . htmlspecialchars($booking['guest_email']) . '</p>
                        <p style="margin: 0; font-size: 12px; color: #616161;">' . htmlspecialchars($booking['guest_phone']) . '</p>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                    <div style="background: #FAFAFA; border-left: 4px solid #8B7355; padding: 20px;">
                        <h3 style="color: #8B7355; font-size: 11px; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">From</h3>
                        <p style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600; color: #212121;">' . htmlspecialchars($site_name) . '</p>
                        <p style="margin: 0 0 3px 0; font-size: 12px; color: #616161;">' . htmlspecialchars($address) . '</p>
                        <p style="margin: 0; font-size: 12px; color: #616161;">' . htmlspecialchars($email_address) . '</p>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- BOOKING DETAILS -->
        <div style="background: #F8F6F3; border-radius: 8px; padding: 20px 25px; margin-bottom: 30px;">
            <h3 style="color: #8B7355; font-size: 11px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">Booking Details</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Reference</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: #8B7355;">' . htmlspecialchars($booking['booking_reference']) . '</p>
                    </td>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Room Type</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 500; color: #424242;">' . htmlspecialchars($booking['room_name']) . '</p>
                    </td>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Check-in</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 500; color: #424242;">' . $check_in . '</p>
                    </td>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Check-out</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 500; color: #424242;">' . $check_out . '</p>
                    </td>
                </tr>
                <tr>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Nights</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 500; color: #424242;">' . $booking['number_of_nights'] . '</p>
                    </td>
                    <td style="width: 25%; padding: 8px 0;">
                        <span style="font-size: 10px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Guests</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 500; color: #424242;">' . $adultGuests . ' Adult' . ($childGuests > 0 ? ', ' . $childGuests . ' Child' . ($childGuests > 1 ? 'ren' : '') : '') . '</p>
                    </td>
                    <td colspan="2" style="width: 50%; padding: 8px 0;"></td>
                </tr>
            </table>
        </div>
        
        <!-- CHARGES TABLE -->
        <div style="margin-bottom: 25px;">
            <h3 style="color: #8B7355; font-size: 11px; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Itemized Charges</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #8B7355;">
                        <th style="padding: 12px 8px; text-align: left; font-size: 11px; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Description</th>
                        <th style="padding: 12px 8px; text-align: center; font-size: 11px; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; width: 60px;">Qty</th>
                        <th style="padding: 12px 8px; text-align: right; font-size: 11px; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; width: 90px;">Unit Price</th>
                        <th style="padding: 12px 8px; text-align: right; font-size: 11px; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; width: 100px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $chargesTableRows . '
                </tbody>
            </table>
        </div>
        
        <!-- TOTALS SECTION -->
        <table style="width: 100%; margin-bottom: 30px;">
            <tr>
                <td style="width: 60%;"></td>
                <td style="width: 40%;">
                    <div style="background: #FAFAFA; border: 1px solid #E0E0E0; border-radius: 4px; padding: 15px 20px;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 6px 0; font-size: 13px; color: #616161;">Subtotal:</td>
                                <td style="padding: 6px 0; font-size: 13px; text-align: right; color: #424242;">' . $currency_symbol . ' ' . number_format($subtotal, 2) . '</td>
                            </tr>' . ($vatEnabled && $vatAmount > 0 ? '
                            <tr>
                                <td style="padding: 6px 0; font-size: 13px; color: #616161;">VAT (' . number_format($vatRate, 2) . '%):</td>
                                <td style="padding: 6px 0; font-size: 13px; text-align: right; color: #424242;">' . $currency_symbol . ' ' . number_format($vatAmount, 2) . '</td>
                            </tr>' : '') . '
                            <tr style="border-top: 2px solid #8B7355;">
                                <td style="padding: 12px 0 6px 0; font-size: 15px; font-weight: 600; color: #212121;">Total Amount:</td>
                                <td style="padding: 12px 0 6px 0; font-size: 15px; font-weight: 600; text-align: right; color: #8B7355;">' . $currency_symbol . ' ' . number_format($totalWithVat, 2) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-size: 13px; color: #616161;">Amount Paid:</td>
                                <td style="padding: 6px 0; font-size: 13px; text-align: right; color: #2E7D32; font-weight: 500;">' . $currency_symbol . ' ' . number_format($amountPaid, 2) . '</td>
                            </tr>' . ($balanceDue > 0 ? '
                            <tr>
                                <td style="padding: 6px 0; font-size: 13px; color: #C62828; font-weight: 600;">Balance Due:</td>
                                <td style="padding: 6px 0; font-size: 13px; text-align: right; color: #C62828; font-weight: 600;">' . $currency_symbol . ' ' . number_format($balanceDue, 2) . '</td>
                            </tr>' : '') . '
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        
        ' . $paymentsTableHTML . '
        
        <!-- FOOTER -->
        <div style="margin-top: 40px; padding-top: 25px; border-top: 2px solid #E0E0E0; text-align: center;">
            <p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #212121;">' . htmlspecialchars($site_name) . '</p>
            <p style="margin: 0 0 4px 0; font-size: 12px; color: #757575;">' . htmlspecialchars($address) . '</p>
            <p style="margin: 0 0 4px 0; font-size: 12px; color: #757575;">Tel: ' . htmlspecialchars($phone_number) . ' | Email: ' . htmlspecialchars($email_address) . '</p>
            <p style="margin: 20px 0 0 0; font-size: 11px; color: #9E9E9E; font-style: italic;">Thank you for choosing ' . htmlspecialchars($site_name) . '. We look forward to welcoming you!</p>
        </div>
    </div>';
}

/**
 * Send payment invoice email to guest and copy recipients
 * 
 * @param int $booking_id Booking ID
 * @return array Result array with success status and message
 */
function sendPaymentInvoiceEmail($booking_id) {
    global $pdo;
    
    try {
        // Check if invoice emails are enabled
        $send_invoices = (bool)getEmailSetting('send_invoice_emails', 0);
        if (!$send_invoices) {
            return ['success' => true, 'message' => 'Invoice emails disabled'];
        }
        
        // Get booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Generate invoice PDF/HTML
        $invoice_result = generateInvoicePDF($booking_id);
        if (!$invoice_result) {
            throw new Exception("Failed to generate invoice");
        }
        
        $invoice_file = $invoice_result['filepath'];
        $invoice_number = $invoice_result['invoice_number'];
        $invoice_path = $invoice_result['relative_path'];
        
        // Update the payment record with invoice path and invoice number
        $update_stmt = $pdo->prepare("
            UPDATE payments
            SET invoice_path = ?, invoice_number = ?, invoice_generated = 1
            WHERE booking_type = 'room' AND booking_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $update_stmt->execute([$invoice_path, $invoice_number, $booking_id]);
        
        // Get invoice recipients (comma-separated)
        $invoice_recipients = getEmailSetting('invoice_recipients', '');
        $smtp_username = getEmailSetting('smtp_username', '');
        
        // Parse recipients from comma-separated string
        $cc_recipients = array_filter(array_map('trim', explode(',', $invoice_recipients)));
        
        // Always add SMTP username to CC list
        if (!empty($smtp_username) && !in_array($smtp_username, $cc_recipients)) {
            $cc_recipients[] = $smtp_username;
        }
        
        // Send invoice to guest with CC recipients
        $result = sendInvoiceEmailToGuestWithCC($booking, $invoice_file, $cc_recipients);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'invoice_file' => $invoice_file,
            'invoice_number' => $invoice_number,
            'invoice_path' => $invoice_path,
            'cc_recipients' => $cc_recipients
        ];
        
    } catch (Exception $e) {
        error_log("Send Payment Invoice Email Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Send payment invoice email with custom CC recipients
 *
 * @param int $booking_id Booking ID
 * @param array $ccRecipients Array of CC email addresses
 * @return array Result array with success status and message
 */
function sendPaymentInvoiceEmailWithCC($booking_id, $ccRecipients = []) {
    global $pdo;
    
    try {
        // Check if invoice emails are enabled
        $send_invoices = (bool)getEmailSetting('send_invoice_emails', 0);
        if (!$send_invoices) {
            return ['success' => true, 'message' => 'Invoice emails disabled'];
        }
        
        // Get booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Generate invoice PDF/HTML
        $invoice_result = generateInvoicePDF($booking_id);
        if (!$invoice_result) {
            throw new Exception("Failed to generate invoice");
        }
        
        $invoice_file = $invoice_result['filepath'];
        $invoice_number = $invoice_result['invoice_number'];
        $invoice_path = $invoice_result['relative_path'];
        
        // Update the payment record with invoice path and invoice number
        $update_stmt = $pdo->prepare("
            UPDATE payments
            SET invoice_path = ?, invoice_number = ?, invoice_generated = 1
            WHERE booking_type = 'room' AND booking_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $update_stmt->execute([$invoice_path, $invoice_number, $booking_id]);
        
        // Send invoice to guest with custom CC recipients
        $result = sendInvoiceEmailToGuestWithCC($booking, $invoice_file, $ccRecipients);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'invoice_file' => $invoice_file,
            'invoice_number' => $invoice_number,
            'invoice_path' => $invoice_path,
            'cc_recipients' => $ccRecipients
        ];
        
    } catch (Exception $e) {
        error_log("Send Payment Invoice Email with CC Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Send invoice email to guest with CC recipients
 */
function sendInvoiceEmailToGuestWithCC($booking, $invoice_file, $cc_recipients = []) {
    global $pdo, $email_from_name, $email_from_email, $email_site_name, $email_site_url;
    
    try {
        // Get room details
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$booking['room_id']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        $templateVars = function_exists('buildBookingEmailVariables')
            ? buildBookingEmailVariables($booking, $room)
            : [];
        $dbTemplate = function_exists('renderBookingEmailTemplate')
            ? renderBookingEmailTemplate('payment_invoice', $templateVars)
            : null;
        
        $currency_symbol = getSetting('currency_symbol');
        
        // Get logo for email
        $logo_url = getInvoiceLogoUrl();
        $logo_html_email = '';
        if (!empty($logo_url)) {
            $logo_html_email = '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($email_site_name) . '" style="max-width: 180px; height: auto; display: block; margin: 0 auto 15px auto;">';
        }
        
        // Prepare email content - Stunning State-of-the-Art Design
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; background-color: #F5F5F5;">
            <div style="font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #FFFFFF; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                
                <!-- HEADER -->
                <div style="background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%); padding: 40px 30px; text-align: center;">
                    ' . $logo_html_email . '
                    <h1 style="color: #8B7355; margin: 0 0 10px 0; font-size: 28px; font-weight: 300; letter-spacing: 4px;">PAYMENT CONFIRMED</h1>
                    <p style="color: #FFFFFF; margin: 0; font-size: 16px; opacity: 0.9;">Thank you for your payment</p>
                </div>
                
                <!-- SUCCESS BANNER -->
                <div style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); padding: 20px 30px; text-align: center;">
                    <span style="color: #2E7D32; font-size: 18px; font-weight: 600;">✓ Your booking is confirmed</span>
                </div>
                
                <!-- CONTENT -->
                <div style="padding: 40px 30px;">
                    
                    <p style="margin: 0 0 20px 0; font-size: 16px; color: #424242; line-height: 1.6;">
                        Dear <strong>' . htmlspecialchars($booking['guest_name']) . '</strong>,
                    </p>
                    
                    <p style="margin: 0 0 30px 0; font-size: 15px; color: #616161; line-height: 1.7;">
                        We are pleased to confirm that your payment has been received. Your booking reference is <span style="color: #8B7355; font-weight: 600;">' . htmlspecialchars($booking['booking_reference']) . '</span>. Please find your detailed invoice attached to this email.
                    </p>
                    
                    <!-- BOOKING DETAILS CARD -->
                    <div style="background: #FAFAFA; border-radius: 12px; padding: 25px; margin: 0 0 30px 0; border: 1px solid #E0E0E0;">
                        <h3 style="color: #8B7355; font-size: 12px; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">Booking Summary</h3>
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid #E0E0E0;">
                                    <span style="font-size: 11px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Room Type</span>
                                    <p style="margin: 5px 0 0 0; font-size: 15px; font-weight: 600; color: #212121;">' . htmlspecialchars($room['name']) . '</p>
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #E0E0E0;">
                                    <span style="font-size: 11px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Guests</span>
                                    <p style="margin: 5px 0 0 0; font-size: 15px; font-weight: 600; color: #212121;">' . (int)$booking['number_of_guests'] . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid #E0E0E0;">
                                    <span style="font-size: 11px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Check-in</span>
                                    <p style="margin: 5px 0 0 0; font-size: 15px; font-weight: 500; color: #424242;">' . date('F j, Y', strtotime($booking['check_in_date'])) . '</p>
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #E0E0E0;">
                                    <span style="font-size: 11px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.5px;">Check-out</span>
                                    <p style="margin: 5px 0 0 0; font-size: 15px; font-weight: 500; color: #424242;">' . date('F j, Y', strtotime($booking['check_out_date'])) . '</p>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- TOTAL -->
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #8B7355; text-align: center;">
                            <span style="font-size: 12px; color: #9E9E9E; text-transform: uppercase; letter-spacing: 1px;">Total Amount Paid</span>
                            <p style="margin: 8px 0 0 0; font-size: 28px; font-weight: 600; color: #8B7355;">' . $currency_symbol . ' ' . number_format($booking['total_amount'], 2) . '</p>
                        </div>
                    </div>
                    
                    <!-- NEXT STEPS -->
                    <div style="background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%); border-radius: 12px; padding: 25px; margin: 0 0 30px 0;">
                        <h3 style="color: #1565C0; font-size: 14px; margin: 0 0 15px 0; font-weight: 600;">📋 Important Information</h3>
                        <ul style="margin: 0; padding-left: 20px; color: #1565C0;">
                            <li style="margin-bottom: 8px; font-size: 14px; line-height: 1.5;">Check-in time: <strong>' . getSetting('check_in_time', '2:00 PM') . '</strong></li>
                            <li style="margin-bottom: 8px; font-size: 14px; line-height: 1.5;">Check-out time: <strong>' . getSetting('check_out_time', '11:00 AM') . '</strong></li>
                            <li style="margin-bottom: 0; font-size: 14px; line-height: 1.5;">Please bring a valid ID for registration</li>
                        </ul>
                    </div>
                    
                    <!-- CONTACT -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #616161;">
                            Questions? Contact us at 
                            <a href="mailto:' . htmlspecialchars($email_from_email) . '" style="color: #8B7355; text-decoration: none; font-weight: 600;">' . htmlspecialchars($email_from_email) . '</a>
                        </p>
                    </div>
                    
                </div>
                
                <!-- FOOTER -->
                <div style="background: #1A1A1A; padding: 30px; text-align: center;">
                    <p style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; color: #FFFFFF;">' . htmlspecialchars($email_site_name) . '</p>
                    <p style="margin: 0 0 5px 0; font-size: 13px; color: #9E9E9E;">' . htmlspecialchars(getSetting('address_line1') . ', ' . getSetting('address_line2')) . '</p>
                    <p style="margin: 0 0 20px 0; font-size: 13px; color: #9E9E9E;">' . htmlspecialchars(getSetting('address_country')) . '</p>
                    <a href="' . htmlspecialchars($email_site_url) . '" style="display: inline-block; background: #8B7355; color: #FFFFFF; padding: 12px 30px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600;">Visit Our Website</a>
                </div>
                
            </div>
        </body>
        </html>';
        
        $subject = 'Payment Invoice - ' . htmlspecialchars($email_site_name) . ' [' . $booking['booking_reference'] . ']';
        $textBody = '';
        if ($dbTemplate) {
            $subject = $dbTemplate['subject'];
            $htmlBody = $dbTemplate['html_body'];
            $textBody = $dbTemplate['text_body'] ?? '';
        }

        // Send email with attachment and CC recipients
        return sendEmailWithAttachmentAndCC(
            $booking['guest_email'],
            $booking['guest_name'],
            $subject,
            $htmlBody,
            $invoice_file,
            $cc_recipients,
            $textBody
        );
        
    } catch (Exception $e) {
        error_log("Send Invoice to Guest Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send invoice copy emails
 */
function sendInvoiceCopyEmails($booking, $invoice_file, $recipients) {
    if (empty($recipients)) {
        return ['success' => true, 'message' => 'No copy recipients'];
    }
    
    global $email_site_name;
    $currency_symbol = getSetting('currency_symbol');
    
    $htmlBody = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background: #1A1A1A; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: #8B7355; margin: 0; font-size: 24px;">INVOICE COPY</h1>
            <p style="color: white; margin: 10px 0 0 0;">Administrative Copy</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
            <p>A payment has been received for booking <strong>' . htmlspecialchars($booking['booking_reference']) . '</strong>.</p>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #1A1A1A; margin-top: 0;">Payment Details</h3>
                <p><strong>Guest:</strong> ' . htmlspecialchars($booking['guest_name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($booking['guest_email']) . '</p>
                <p><strong>Amount Paid:</strong> <span style="color: #8B7355; font-weight: bold;">' . $currency_symbol . ' ' . number_format($booking['total_amount'], 0) . '</span></p>
                <p><strong>Payment Date:</strong> ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <p>Please find the invoice attached for your records.</p>
        </div>
    </div>';
    
    // Send to all recipients
    $all_success = true;
    foreach ($recipients as $recipient) {
        $result = sendEmailWithAttachment(
            $recipient,
            'Accounts Team',
            'Invoice Copy - ' . htmlspecialchars($email_site_name) . ' [' . $booking['booking_reference'] . ']',
            $htmlBody,
            $invoice_file
        );
        if (!$result['success']) {
            $all_success = false;
            error_log("Failed to send invoice copy to $recipient: " . $result['message']);
        }
    }
    
    return ['success' => $all_success, 'message' => $all_success ? 'All copies sent' : 'Some copies failed'];
}

/**
 * Send email with attachment (wrapper for sendEmailWithAttachmentAndCC)
 *
 * @param string $to Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $attachmentPath Path to attachment file
 * @return array Result array with success status and message
 */
function sendEmailWithAttachment($to, $toName, $subject, $htmlBody, $attachmentPath) {
    // Call the CC version with empty CC array
    return sendEmailWithAttachmentAndCC($to, $toName, $subject, $htmlBody, $attachmentPath, []);
}

/**
 * Send email with attachment and CC recipients
 * Uses the same email configuration as config/email.php
 */
function sendEmailWithAttachmentAndCC($to, $toName, $subject, $htmlBody, $attachmentPath, $ccRecipients = [], $textBody = '') {
    global $email_from_name, $email_from_email, $email_admin_email;
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_secure, $smtp_timeout, $smtp_debug, $email_site_name;
    global $email_bcc_admin, $development_mode, $email_log_enabled, $email_preview_enabled;
    
    // Check if we're on localhost
    $is_localhost = isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
        strpos($_SERVER['HTTP_HOST'], '.local') !== false
    );
    
    // Development mode: show previews on localhost unless explicitly disabled
    $dev_mode = $is_localhost && $development_mode;
    
    // If in development mode and no password or preview enabled, show preview
    if ($dev_mode && (empty($smtp_password) || $email_preview_enabled)) {
        return createEmailPreview($to, $toName, $subject, $htmlBody);
    }
    
    try {
        $mail = new PHPMailer(true);

        $smtpSecureNormalized = strtolower(trim((string)$smtp_secure));
        if ($smtpSecureNormalized === '' && (int)$smtp_port === 587) {
            $smtpSecureNormalized = 'tls';
        } elseif ($smtpSecureNormalized === '' && (int)$smtp_port === 465) {
            $smtpSecureNormalized = 'ssl';
        }
        
        /**
         * Generate and send final invoice at checkout
         * Includes idempotency safeguards to avoid duplicate invoice generation
         *
         * @param int $booking_id Booking ID
         * @param int|null $processed_by Admin user ID processing the checkout
         * @return array Result with success status, invoice details, and any warnings
         */
        function generateAndSendFinalInvoice(int $booking_id, ?int $processed_by = null): array {
            global $pdo;
            
            try {
                // Check if booking exists
                $stmt = $pdo->prepare("SELECT id, booking_reference, guest_name, guest_email, status, final_invoice_generated FROM bookings WHERE id = ?");
                $stmt->execute([$booking_id]);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking) {
                    return ['success' => false, 'message' => 'Booking not found'];
                }
                
                // Check if final invoice already generated (idempotency)
                if ($booking['final_invoice_generated']) {
                    // Return existing invoice details
                    $existingStmt = $pdo->prepare("SELECT final_invoice_path, final_invoice_number, final_invoice_sent_at FROM bookings WHERE id = ?");
                    $existingStmt->execute([$booking_id]);
                    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                    
                    return [
                        'success' => true,
                        'message' => 'Final invoice already generated',
                        'invoice_path' => $existing['final_invoice_path'],
                        'invoice_number' => $existing['final_invoice_number'],
                        'sent_at' => $existing['final_invoice_sent_at'],
                        'idempotent' => true
                    ];
                }
                
                // Recalculate folio before generating invoice
                recalculateBookingFinancials($booking_id);
                
                // Generate final invoice
                $invoice_result = generateInvoicePDF($booking_id);
                if (!$invoice_result) {
                    return ['success' => false, 'message' => 'Failed to generate final invoice'];
                }
                
                $invoice_file = $invoice_result['filepath'];
                $invoice_number = $invoice_result['invoice_number'];
                $invoice_path = $invoice_result['relative_path'];
                
                // Update booking with final invoice details
                $updateStmt = $pdo->prepare("
                    UPDATE bookings
                    SET final_invoice_generated = 1,
                        final_invoice_path = ?,
                        final_invoice_number = ?,
                        final_invoice_sent_at = NULL,
                        checkout_processed_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$invoice_path, $invoice_number, $processed_by, $booking_id]);
                
                // Send final invoice email
                $email_sent = false;
                $email_error = null;
                
                try {
                    // Get invoice recipients
                    $invoice_recipients = getEmailSetting('invoice_recipients', '');
                    $smtp_username = getEmailSetting('smtp_username', '');
                    
                    // Parse recipients
                    $cc_recipients = array_filter(array_map('trim', explode(',', $invoice_recipients)));
                    
                    // Always add SMTP username to CC
                    if (!empty($smtp_username) && !in_array($smtp_username, $cc_recipients)) {
                        $cc_recipients[] = $smtp_username;
                    }
                    
                    // Send email
                    $email_result = sendFinalInvoiceEmail($booking, $invoice_file, $cc_recipients);
                    $email_sent = $email_result['success'];
                    
                    if ($email_sent) {
                        // Update sent timestamp
                        $pdo->prepare("UPDATE bookings SET final_invoice_sent_at = NOW() WHERE id = ?")
                            ->execute([$booking_id]);
                    } else {
                        $email_error = $email_result['message'];
                    }
                    
                } catch (Exception $e) {
                    $email_error = $e->getMessage();
                    error_log("Final invoice email error: " . $email_error);
                }
                
                return [
                    'success' => true,
                    'message' => 'Final invoice generated' . ($email_sent ? ' and sent' : ' (email failed)'),
                    'invoice_file' => $invoice_file,
                    'invoice_number' => $invoice_number,
                    'invoice_path' => $invoice_path,
                    'email_sent' => $email_sent,
                    'email_error' => $email_error,
                    'idempotent' => false
                ];
                
            } catch (Exception $e) {
                error_log("Generate and send final invoice error: " . $e->getMessage());
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        /**
         * Send final invoice email at checkout
         *
         * @param array $booking Booking details
         * @param string $invoice_file Path to invoice file
         * @param array $cc_recipients CC recipients
         * @return array Result with success status
         */
        function sendFinalInvoiceEmail($booking, $invoice_file, $cc_recipients = []) {
            global $pdo, $email_from_name, $email_from_email, $email_site_name, $email_site_url;
            
            try {
                // Get room details
                $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
                $stmt->execute([$booking['room_id']]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $currency_symbol = getSetting('currency_symbol');
                
                // Build email content
                $htmlBody = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #1A1A1A 0%, #2A2A2A 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: #8B7355; margin: 0; font-size: 32px;">✓ CHECKOUT COMPLETE</h1>
                        <p style="color: white; margin: 10px 0 0 0; font-size: 18px;">Thank you for your stay!</p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                        <p>Dear ' . htmlspecialchars($booking['guest_name']) . ',</p>
                        
                        <p>We hope you enjoyed your stay at <strong>' . htmlspecialchars($email_site_name) . '</strong>. Your checkout has been completed and your final invoice is ready.</p>
                        
                        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #8B7355;">
                            <h3 style="color: #1A1A1A; margin-top: 0;">Final Invoice Details</h3>
                            
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span style="font-weight: bold; color: #333;">Booking Reference:</span>
                                <span style="color: #666;">' . htmlspecialchars($booking['booking_reference']) . '</span>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span style="font-weight: bold; color: #333;">Room:</span>
                                <span style="color: #666;">' . htmlspecialchars($room['name']) . '</span>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span style="font-weight: bold; color: #333;">Check-out Date:</span>
                                <span style="color: #666;">' . date('F j, Y') . '</span>
                            </div>
                        </div>
                        
                        <div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; border-radius: 5px; margin: 20px 0;">
                            <h3 style="color: #155724; margin-top: 0;">✅ Final Invoice Attached</h3>
                            <p style="color: #155724; margin: 0;">Please find your final invoice attached to this email. It includes all room charges, extras, and payments made during your stay.</p>
                        </div>
                        
                        <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0d6efd; border-radius: 5px;">
                            <h3 style="color: #0d6efd; margin-top: 0;">We Hope to See You Again!</h3>
                            <p style="color: #0d6efd; margin: 0;">Thank you for choosing ' . htmlspecialchars($email_site_name) . '. We look forward to welcoming you back soon.</p>
                        </div>
                        
                        <p style="margin-top: 30px;">If you have any questions about your invoice or your stay, please contact us at <a href="mailto:' . htmlspecialchars($email_from_email) . '">' . htmlspecialchars($email_from_email) . '</a>.</p>
                        
                        <p style="margin-top: 20px;">Safe travels!</p>
                        
                        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #1A1A1A;">
                            <p style="color: #666; font-size: 14px; margin: 5px 0;"><strong>The ' . htmlspecialchars($email_site_name) . ' Team</strong></p>
                            <p style="color: #666; font-size: 14px; margin: 5px 0;"><a href="' . htmlspecialchars($email_site_url) . '">' . htmlspecialchars($email_site_url) . '</a></p>
                        </div>
                    </div>
                </div>';
                
                $subject = 'Final Invoice - ' . htmlspecialchars($email_site_name) . ' [' . $booking['booking_reference'] . ']';
                
                // Send email with attachment
                return sendEmailWithAttachmentAndCC(
                    $booking['guest_email'],
                    $booking['guest_name'],
                    $subject,
                    $htmlBody,
                    $invoice_file,
                    $cc_recipients
                );
                
            } catch (Exception $e) {
                error_log("Send final invoice email error: " . $e->getMessage());
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        // Many SMTP relays require From to match authenticated mailbox.
        $fromAddress = $smtp_username;
        
        // Server settings - loaded from database
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        if ($smtpSecureNormalized !== '') {
            $mail->SMTPSecure = $smtpSecureNormalized;
        }
        $mail->Port = $smtp_port;
        $mail->Timeout = $smtp_timeout;
        
        if ($smtp_debug > 0) {
            $mail->SMTPDebug = $smtp_debug;
        }
        
        // Recipients
        $mail->setFrom($fromAddress, $email_from_name ?: $email_site_name);
        $mail->addAddress($to, $toName);
        if (!empty($email_from_email) && filter_var($email_from_email, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($email_from_email, $email_from_name ?: $email_site_name);
        }
        
        // Add CC recipients from invoice_recipients setting
        foreach ($ccRecipients as $cc) {
            if (!empty($cc) && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
            }
        }
        
        // Add BCC for admin if enabled
        if ($email_bcc_admin && !empty($email_admin_email)) {
            $mail->addBCC($email_admin_email);
        }
        
        // Add attachment
        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, basename($attachmentPath));
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = wrapEmailTemplate($htmlBody, $subject);
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        
        $mail->send();
        
        // Log email if enabled
        if ($email_log_enabled) {
            $cc_list = implode(', ', $ccRecipients);
            logEmail($to, $toName, $subject, 'sent', '', "CC: $cc_list");
        }
        
        return [
            'success' => true,
            'message' => 'Email sent successfully via SMTP with ' . count($ccRecipients) . ' CC recipients'
        ];
        
    } catch (Exception $e) {
        error_log("PHPMailer Error (sendEmailWithAttachmentAndCC): " . $e->getMessage());
        
        // Log error if enabled
        if ($email_log_enabled) {
            $cc_list = implode(', ', $ccRecipients);
            logEmail($to, $toName, $subject, 'failed', $e->getMessage(), "CC: $cc_list");
        }
        
        // If development mode, show preview instead of failing
        if ($dev_mode) {
            return createEmailPreview($to, $toName, $subject, $htmlBody);
        }
        
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ];
}
}

/**
 * Generate PDF invoice for a conference enquiry
 *
 * @param int $enquiry_id Conference enquiry ID
 * @return string PDF file path or false on failure
 */
function generateConferenceInvoicePDF($enquiry_id) {
    global $pdo, $tcpdf_loaded;
    
    try {
        // Get enquiry details
        $stmt = $pdo->prepare("
            SELECT ci.*, cr.name as room_name,
                   s.setting_value as site_name
            FROM conference_inquiries ci
            LEFT JOIN conference_rooms cr ON ci.conference_room_id = cr.id
            JOIN site_settings s ON s.setting_key = 'site_name'
            WHERE ci.id = ?
        ");
        $stmt->execute([$enquiry_id]);
        $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enquiry) {
            throw new Exception("Conference enquiry not found");
        }
        
        // Get hotel contact details
        $site_name = getSetting('site_name');
        $email_address = getSetting('email_from_email');
        $phone_number = getSetting('phone_main');
        $address = getSetting('address_line1') . ', ' .
                   getSetting('address_line2') . ', ' .
                   getSetting('address_country');
        $currency_symbol = getSetting('currency_symbol');
        
        // Create invoice directory if it doesn't exist
        $invoiceDir = __DIR__ . '/../invoices';
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        
        // Generate unique invoice filename - use sequential invoice number from settings
        $invoice_prefix = getSetting('invoice_prefix', 'INV');
        $invoice_start = (int)getSetting('invoice_start_number', 1000);
        
        // Get the next invoice number for conference invoices
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) as max_inv FROM payments WHERE invoice_number LIKE ?");
        $stmt->execute(['CONF-' . $invoice_prefix . '-' . date('Y') . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_number = max($invoice_start, ($result['max_inv'] ?? 0) + 1);
        
        $invoice_number = 'CONF-' . $invoice_prefix . '-' . date('Y') . '-' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
        $filename = $invoice_number . '.pdf';
        $filepath = $invoiceDir . '/' . $filename;
        
        if ($tcpdf_loaded) {
            // Use TCPDF for professional PDF generation
            $tcpdfClass = 'TCPDF';
            $pdf = new $tcpdfClass(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator($site_name);
            $pdf->SetAuthor($site_name);
            $pdf->SetTitle('Invoice ' . $invoice_number);
            $pdf->SetSubject('Conference Payment Invoice');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Build HTML content
            $html = buildConferenceInvoiceHTML($enquiry, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol);
            
            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Save PDF
            $pdf->Output($filepath, 'F');
            
        } else {
            // Fallback: Generate HTML invoice and save as file
            $html = buildConferenceInvoiceHTML($enquiry, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol);
            
            // Wrap in complete HTML document
            $fullHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . $invoice_number . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; border:1px solid #ddd; }
        .invoice-header { background: linear-gradient(135deg, #1A1A1A 0%, #2A2A2A 100%); color: white; padding: 30px; }
        .invoice-header h1 { margin: 0; color: #8B7355; }
        .invoice-body { padding: 30px; }
        .invoice-details { margin-bottom: 30px; }
        .invoice-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom:1px solid #eee; }
        .invoice-label { font-weight: bold; color: #333; }
        .invoice-value { color: #666; }
        .total-section { background: #f8f9fa; padding: 20px; border-radius:5px; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #8B7355; }
        .footer { text-align: center; padding: 20px; background: #f8f9fa; border-top: 1px solid #ddd; }
    </style>
</head>
<body>' . $html . '</body></html>';
            
            // Save as HTML (can be opened in browser and printed as PDF)
            $htmlFilepath = str_replace('.pdf', '.html', $filepath);
            file_put_contents($htmlFilepath, $fullHtml);
            
            // Return array with both paths and invoice number
            return [
                'filepath' => $htmlFilepath,
                'invoice_number' => $invoice_number,
                'relative_path' => 'invoices/' . basename($htmlFilepath)
            ];
        }
        
        // Return array with both paths and invoice number
        return [
            'filepath' => $filepath,
            'invoice_number' => $invoice_number,
            'relative_path' => 'invoices/' . $filename
        ];
        
    } catch (Exception $e) {
        error_log("Generate Conference Invoice PDF Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Build HTML content for conference invoice
 */
function buildConferenceInvoiceHTML($enquiry, $invoice_number, $site_name, $email_address, $phone_number, $address, $currency_symbol) {
    global $pdo;
    
    $event_date = date('F j, Y', strtotime($enquiry['event_date']));
    
    // Get logo URL
    $logo_url = getInvoiceLogoUrl();
    $logo_html = '';
    if (!empty($logo_url)) {
        $logo_html = '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($site_name) . '" style="max-width: 280px; height: auto; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto;">';
    }
    
    // Get VAT settings - more flexible check
    $vatEnabled = in_array(getSetting('vat_enabled'), ['1', 1, true, 'true', 'on'], true);
    $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
    $vatNumber = getSetting('vat_number');
    
    // Get payment details for this conference enquiry
    $paymentsStmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE booking_type = 'conference' AND booking_id = ?
        AND payment_status = 'completed' AND deleted_at IS NULL
        ORDER BY payment_date ASC
    ");
    $paymentsStmt->execute([$enquiry['id']]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $subtotal = (float)$enquiry['total_amount'];
    $vatAmount = $vatEnabled ? ($subtotal * ($vatRate / 100)) : 0;
    $totalWithVat = $subtotal + $vatAmount;
    
    // Build payment details HTML
    $paymentDetailsHTML = '';
    if (!empty($payments)) {
        $paymentDetailsHTML = '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h4 style="color: #1A1A1A; margin-top: 0;">Payment History</h4>';
        
        foreach ($payments as $payment) {
            $paymentDetailsHTML .= '<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd;">
                        <span>' . date('M j, Y', strtotime($payment['payment_date'])) . ' (' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . ')</span>
                        <span>' . $currency_symbol . ' ' . number_format($payment['total_amount'], 2) . '</span>
                    </div>';
        }
        
        $paymentDetailsHTML .= '</div>';
    }
    
    // Build deposit section HTML
    $depositSectionHTML = '';
    if (!empty($enquiry['deposit_required']) && $enquiry['deposit_required'] > 0) {
        $depositSectionHTML = '<div class="invoice-row">
                    <span class="invoice-label">Deposit Required:</span>
                    <span class="invoice-value">' . $currency_symbol . ' ' . number_format($enquiry['deposit_amount'], 2) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Deposit Paid:</span>
                    <span class="invoice-value" style="color: ' . ($enquiry['deposit_paid'] >= $enquiry['deposit_amount'] ? '#28a745' : '#dc3545') . '; font-weight: bold;">' . $currency_symbol . ' ' . number_format($enquiry['deposit_paid'] ?? 0, 2) . '</span>
                </div>';
    }
    
    // Build VAT section HTML
    $vatSectionHTML = '';
    if ($vatEnabled && $vatAmount > 0) {
        $vatSectionHTML = '<div class="invoice-row">
                    <span class="invoice-label">Subtotal (excl. VAT):</span>
                    <span class="invoice-value">' . $currency_symbol . ' ' . number_format($subtotal, 2) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">VAT (' . number_format($vatRate, 2) . '%):</span>
                    <span class="invoice-value">' . $currency_symbol . ' ' . number_format($vatAmount, 2) . '</span>
                </div>';
        if ($vatNumber) {
            $vatSectionHTML .= '<div class="invoice-row">
                    <span class="invoice-label">VAT Number:</span>
                    <span class="invoice-value">' . htmlspecialchars($vatNumber) . '</span>
                </div>';
        }
    }
    
    return '
    <div class="invoice-container">
        <div class="invoice-header" style="text-align: center;">
            ' . $logo_html . '
            <h1 style="color: #8B7355; margin: 0 0 10px 0; font-size: 32px;">CONFERENCE INVOICE</h1>
            <p style="margin: 5px 0; font-size: 18px;">' . htmlspecialchars($site_name) . '</p>
            <p style="margin: 5px 0;">Invoice Number: <strong>' . htmlspecialchars($invoice_number) . '</strong></p>
            <p style="margin: 5px 0;">Date: ' . date('F j, Y') . '</p>
        </div>
        
        <div class="invoice-body">
            <div class="invoice-details">
                <h3 style="color: #1A1A1A; border-bottom: 2px solid #8B7355; padding-bottom: 10px; margin-bottom: 20px;">Client Information</h3>
                
                <div class="invoice-row">
                    <span class="invoice-label">Company:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['company_name']) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Contact Person:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['contact_person']) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Email:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['email']) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Phone:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['phone']) . '</span>
                </div>
            </div>
            
            <div class="invoice-details">
                <h3 style="color: #1A1A1A; border-bottom: 2px solid #8B7355; padding-bottom: 10px; margin-bottom: 20px;">Event Details</h3>
                
                <div class="invoice-row">
                    <span class="invoice-label">Reference:</span>
                    <span class="invoice-value" style="color: #8B7355; font-weight: bold; font-size: 16px;">' . htmlspecialchars($enquiry['inquiry_reference']) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Conference Room:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['room_name']) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Event Date:</span>
                    <span class="invoice-value">' . $event_date . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Event Time:</span>
                    <span class="invoice-value">' . date('H:i', strtotime($enquiry['start_time'])) . ' - ' . date('H:i', strtotime($enquiry['end_time'])) . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Number of Attendees:</span>
                    <span class="invoice-value">' . (int) $enquiry['number_of_attendees'] . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Event Type:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['event_type'] ?? 'N/A') . '</span>
                </div>
            </div>
            
            <div class="invoice-details">
                <h3 style="color: #1A1A1A; border-bottom: 2px solid #8B7355; padding-bottom: 10px; margin-bottom: 20px;">Services</h3>
                
                <div class="invoice-row">
                    <span class="invoice-label">Catering:</span>
                    <span class="invoice-value">' . ($enquiry['catering_required'] ? 'Yes' : 'No') . '</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">AV Equipment:</span>
                    <span class="invoice-value">' . htmlspecialchars($enquiry['av_equipment'] ?? 'None') . '</span>
                </div>
            </div>
            
            <div class="total-section">
                ' . $depositSectionHTML . '
                ' . $vatSectionHTML . '
                <div class="total-row">
                    <span>Total Amount' . ($vatEnabled ? ' (incl. VAT)' : '') . ':</span>
                    <span>' . $currency_symbol . ' ' . number_format($totalWithVat, 2) . '</span>
                </div>
                <p style="margin: 15px 0 0 0; color: #666; font-size: 14px;">
                    <strong>Payment Status:</strong> <span style="color: #28a745; font-weight: bold;">PAID</span>
                </p>
                <p style="margin: 5px 0; color: #666; font-size: 14px;">
                    <strong>Amount Paid:</strong> ' . $currency_symbol . ' ' . number_format($enquiry['amount_paid'] ?? $totalWithVat, 2) . '
                </p>
                ' . ($enquiry['amount_due'] > 0 ? '<p style="margin: 5px 0; color: #dc3545; font-size: 14px;">
                    <strong>Balance Due:</strong> ' . $currency_symbol . ' ' . number_format($enquiry['amount_due'], 2) . '
                </p>' : '') . '
            </div>
            
            ' . $paymentDetailsHTML . '
        </div>
        
        <div class="footer">
            <p style="margin: 10px 0;"><strong>' . htmlspecialchars($site_name) . '</strong></p>
            <p style="margin: 5px 0;">' . htmlspecialchars($address) . '</p>
            <p style="margin: 5px 0;">Email: ' . htmlspecialchars($email_address) . ' | Phone: ' . htmlspecialchars($phone_number) . '</p>
            <p style="margin: 15px 0 0 0; color: #999; font-size: 12px;">
                Thank you for your payment! We look forward to hosting your event.
            </p>
        </div>
    </div>';
}

/**
 * Send conference payment invoice email
 *
 * @param int $enquiry_id Conference enquiry ID
 * @return array Result array with success status and message
 */
function sendConferenceInvoiceEmail($enquiry_id) {
    global $pdo;
    
    try {
        // Check if invoice emails are enabled
        $send_invoices = (bool)getEmailSetting('send_invoice_emails', 0);
        if (!$send_invoices) {
            return ['success' => true, 'message' => 'Invoice emails disabled'];
        }
        
        // Get enquiry details
        $stmt = $pdo->prepare("SELECT * FROM conference_inquiries WHERE id = ?");
        $stmt->execute([$enquiry_id]);
        $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enquiry) {
            throw new Exception("Conference enquiry not found");
        }
        
        // Generate invoice PDF/HTML
        $invoice_result = generateConferenceInvoicePDF($enquiry_id);
        if (!$invoice_result) {
            throw new Exception("Failed to generate invoice");
        }
        
        $invoice_file = $invoice_result['filepath'];
        $invoice_number = $invoice_result['invoice_number'];
        $invoice_path = $invoice_result['relative_path'];
        
        // Update the payment record with invoice path and invoice number
        $update_stmt = $pdo->prepare("
            UPDATE payments
            SET invoice_path = ?, invoice_number = ?, invoice_generated = 1
            WHERE booking_type = 'conference' AND booking_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $update_stmt->execute([$invoice_path, $invoice_number, $enquiry_id]);
        
        // Get invoice recipients (comma-separated)
        $invoice_recipients = getEmailSetting('invoice_recipients', '');
        $smtp_username = getEmailSetting('smtp_username', '');
        
        // Parse recipients from comma-separated string
        $cc_recipients = array_filter(array_map('trim', explode(',', $invoice_recipients)));
        
        // Always add SMTP username to CC list
        if (!empty($smtp_username) && !in_array($smtp_username, $cc_recipients)) {
            $cc_recipients[] = $smtp_username;
        }
        
        // Send invoice to client with CC recipients
        $result = sendConferenceInvoiceEmailToClient($enquiry, $invoice_file, $cc_recipients);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'invoice_file' => $invoice_file,
            'invoice_number' => $invoice_number,
            'invoice_path' => $invoice_path,
            'cc_recipients' => $cc_recipients
        ];
        
    } catch (Exception $e) {
        error_log("Send Conference Invoice Email Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Send conference payment invoice email with custom CC recipients
 *
 * @param int $enquiry_id Conference enquiry ID
 * @param array $ccRecipients Array of CC email addresses
 * @return array Result array with success status and message
 */
function sendConferenceInvoiceEmailWithCC($enquiry_id, $ccRecipients = []) {
    global $pdo;
    
    try {
        // Check if invoice emails are enabled
        $send_invoices = (bool)getEmailSetting('send_invoice_emails', 0);
        if (!$send_invoices) {
            return ['success' => true, 'message' => 'Invoice emails disabled'];
        }
        
        // Get enquiry details
        $stmt = $pdo->prepare("SELECT * FROM conference_inquiries WHERE id = ?");
        $stmt->execute([$enquiry_id]);
        $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enquiry) {
            throw new Exception("Conference enquiry not found");
        }
        
        // Generate invoice PDF/HTML
        $invoice_result = generateConferenceInvoicePDF($enquiry_id);
        if (!$invoice_result) {
            throw new Exception("Failed to generate invoice");
        }
        
        $invoice_file = $invoice_result['filepath'];
        $invoice_number = $invoice_result['invoice_number'];
        $invoice_path = $invoice_result['relative_path'];
        
        // Update the payment record with invoice path and invoice number
        $update_stmt = $pdo->prepare("
            UPDATE payments
            SET invoice_path = ?, invoice_number = ?, invoice_generated = 1
            WHERE booking_type = 'conference' AND booking_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $update_stmt->execute([$invoice_path, $invoice_number, $enquiry_id]);
        
        // Send invoice to client with custom CC recipients
        $result = sendConferenceInvoiceEmailToClient($enquiry, $invoice_file, $ccRecipients);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'invoice_file' => $invoice_file,
            'invoice_number' => $invoice_number,
            'invoice_path' => $invoice_path,
            'cc_recipients' => $ccRecipients
        ];
        
    } catch (Exception $e) {
        error_log("Send Conference Invoice Email with CC Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Send conference invoice email to client with CC recipients
 */
function sendConferenceInvoiceEmailToClient($enquiry, $invoice_file, $cc_recipients = []) {
    global $pdo, $email_from_name, $email_from_email, $email_site_name, $email_site_url;
    
    try {
        // Get conference room details
        $stmt = $pdo->prepare("SELECT * FROM conference_rooms WHERE id = ?");
        $stmt->execute([$enquiry['conference_room_id']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currency_symbol = getSetting('currency_symbol');
        
        // Prepare email content
        $htmlBody = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #1A1A1A 0%, #2A2A2A 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: #8B7355; margin: 0; font-size: 32px;">✓ PAYMENT CONFIRMED</h1>
                <p style="color: white; margin: 10px 0 0 0; font-size: 18px;">Thank you for your conference payment!</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 30px; border:1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <p>Dear ' . htmlspecialchars($enquiry['contact_person']) . ',</p>
                
                <p>We are pleased to confirm that your payment has been received. Please find attached your official invoice/receipt for conference booking <strong>' . htmlspecialchars($enquiry['inquiry_reference']) . '</strong>.</p>
                
                <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #8B7355;">
                    <h3 style="color: #1A1A1A; margin-top: 0;">Conference Summary</h3>
                    
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom:1px solid #eee;">
                        <span style="font-weight: bold; color: #333;">Conference Room:</span>
                        <span style="color: #666;">' . htmlspecialchars($room['name']) . '</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                        <span style="font-weight: bold; color: #333;">Event Date:</span>
                        <span style="color: #666;">' . date('F j, Y', strtotime($enquiry['event_date'])) . '</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                        <span style="font-weight: bold; color: #333;">Event Time:</span>
                        <span style="color: #666;">' . date('H:i', strtotime($enquiry['start_time'])) . ' - ' . date('H:i', strtotime($enquiry['end_time'])) . '</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 15px 0;">
                        <span style="font-weight: bold; color: #8B7355; font-size: 18px;">Total Paid:</span>
                        <span style="color: #8B7355; font-weight: bold; font-size: 18px;">' . $currency_symbol . ' ' . number_format($enquiry['total_amount'], 0) . '</span>
                    </div>
                </div>
                
                <div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #155724; margin-top: 0;">✅ Payment Status: PAID</h3>
                    <p style="color: #155724; margin: 0;">Your conference booking is now fully paid and confirmed. We look forward to hosting your event!</p>
                </div>
                
                <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0d6efd; border-radius: 5px;">
                    <h3 style="color: #0d6efd; margin-top: 0;">Next Steps</h3>
                    <ul style="color: #0d6efd; margin: 10px 0; padding-left: 20px;">
                        <li>Please save your booking reference: <strong>' . htmlspecialchars($enquiry['inquiry_reference']) . '</strong></li>
                        <li>Arrive at least 30 minutes before your event start time</li>
                        <li>Contact us if you need to make any changes</li>
                    </ul>
                </div>
                
                <p style="margin-top: 30px;">If you have any questions, please contact us at <a href="mailto:' . htmlspecialchars($email_from_email) . '">' . htmlspecialchars($email_from_email) . '</a>.</p>
                
                <p style="margin-top: 20px;">We look forward to hosting your event at <strong>' . htmlspecialchars($email_site_name) . '</strong>!</p>
                
                <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #1A1A1A;">
                    <p style="color: #666; font-size: 14px; margin: 5px 0;"><strong>The ' . htmlspecialchars($email_site_name) . ' Team</strong></p>
                    <p style="color: #666; font-size: 14px; margin: 5px 0;"><a href="' . htmlspecialchars($email_site_url) . '">' . htmlspecialchars($email_site_url) . '</a></p>
                </div>
            </div>
        </div>';
        
        // Send email with attachment and CC recipients
        return sendEmailWithAttachmentAndCC(
            $enquiry['email'],
            $enquiry['contact_person'],
            'Conference Payment Invoice - ' . htmlspecialchars($email_site_name) . ' [' . $enquiry['inquiry_reference'] . ']',
            $htmlBody,
            $invoice_file,
            $cc_recipients
        );
        
    } catch (Exception $e) {
        error_log("Send Conference Invoice to Client Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
