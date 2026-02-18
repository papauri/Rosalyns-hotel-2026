<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel API Test – Simulated Website</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #444;
        }
        input, select, button, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        button:hover {
            background: #2980b9;
        }
        button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .section {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        .response {
            margin-top: 20px;
            padding: 16px;
            border-radius: 8px;
            display: none;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .flex {
            display: flex;
            gap: 20px;
        }
        .flex > * {
            flex: 1;
        }
        .note {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            overflow: auto;
            font-size: 14px;
        }
        .room-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .room-card h3 {
            margin-top: 0;
            color: #2980b9;
        }
        .room-card p {
            margin: 5px 0;
        }
        .room-card .price {
            font-weight: bold;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hotel Booking API – Simulated Website</h1>
        <p>This page simulates a real website that consumes the hotel booking API. It uses PHP to call the API endpoints and display results.</p>

        <?php
        // Configuration
        $apiBaseUrl = 'http://localhost:8080';
        $apiKey = '00ac5aef5f653fea16dcb17669c61705';

        // Include database configuration
        require_once __DIR__ . '/../config/database.php';
        // $pdo is now available from database.php

        // Fetch active rooms from database
        $rooms = [];
        $dbError = null;
        try {
            $stmt = $pdo->query("
                SELECT id, name, price_per_night, max_guests, short_description
                FROM rooms
                WHERE is_active = 1
                ORDER BY display_order, id
            ");
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $dbError = $e->getMessage();
        }

        // Helper function to make API requests
        function callApi($url, $method = 'GET', $data = null) {
            global $apiKey;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['code' => $httpCode, 'body' => json_decode($response, true)];
        }

        // Process form submissions
        $availabilityResult = null;
        $bookingResult = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'check_availability') {
                $roomId = (int)$_POST['room_id'];
                $checkIn = $_POST['check_in'];
                $checkOut = $_POST['check_out'];
                $guests = (int)$_POST['guests'];
                $url = $apiBaseUrl . "/api/availability?room_id=$roomId&check_in=$checkIn&check_out=$checkOut&number_of_guests=$guests";
                $availabilityResult = callApi($url, 'GET');
            }
            if (isset($_POST['action']) && $_POST['action'] === 'create_booking') {
                $bookingData = [
                    'room_id' => (int)$_POST['book_room_id'],
                    'guest_name' => $_POST['guest_name'],
                    'guest_email' => $_POST['guest_email'],
                    'guest_phone' => $_POST['guest_phone'],
                    'check_in_date' => $_POST['book_check_in'],
                    'check_out_date' => $_POST['book_check_out'],
                    'number_of_guests' => (int)$_POST['book_guests'],
                    'special_requests' => $_POST['special_requests'] ?? '',
                    'booking_type' => 'standard',
                    'occupancy_type' => 'double'
                ];
                $url = $apiBaseUrl . "/api/bookings";
                $bookingResult = callApi($url, 'POST', $bookingData);
            }
        }
        ?>

        <div class="section">
            <h2>Available Rooms (Live from Database)</h2>
            <?php if ($dbError): ?>
                <div class="response error">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
            <?php elseif (empty($rooms)): ?>
                <div class="response info">
                    No active rooms found in the database.
                </div>
            <?php else: ?>
                <p>The following rooms are retrieved directly from the hotel database.</p>
                <?php foreach ($rooms as $room): ?>
                    <div class="room-card">
                        <h3><?php echo htmlspecialchars($room['name']); ?> (ID: <?php echo $room['id']; ?>)</h3>
                        <p><strong>Price per night:</strong> <span class="price">$<?php echo number_format($room['price_per_night'], 2); ?></span></p>
                        <p><strong>Max guests:</strong> <?php echo $room['max_guests']; ?></p>
                        <?php if (!empty($room['short_description'])): ?>
                            <p><?php echo htmlspecialchars($room['short_description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Check Availability</h2>
            <form method="POST">
                <input type="hidden" name="action" value="check_availability">
                <div class="flex">
                    <div class="form-group">
                        <label for="room_id">Room ID</label>
                        <select id="room_id" name="room_id" required>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?> (ID <?php echo $room['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="check_in">Check-in Date</label>
                        <input type="date" id="check_in" name="check_in" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="check_out">Check-out Date</label>
                        <input type="date" id="check_out" name="check_out" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="guests">Number of Guests</label>
                    <input type="number" id="guests" name="guests" value="2" min="1" required>
                </div>
                <button type="submit">Check Availability via API</button>
            </form>

            <?php if ($availabilityResult !== null): ?>
                <div class="response <?php echo $availabilityResult['code'] === 200 ? 'success' : 'error'; ?>">
                    <h3>API Response (HTTP <?php echo $availabilityResult['code']; ?>)</h3>
                    <pre><?php echo json_encode($availabilityResult['body'], JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Create a Booking</h2>
            <p>Fill in guest details to create a booking. The API will be called with a POST request.</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_booking">
                <div class="flex">
                    <div class="form-group">
                        <label for="book_room_id">Room ID</label>
                        <select id="book_room_id" name="book_room_id" required>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="book_check_in">Check-in Date</label>
                        <input type="date" id="book_check_in" name="book_check_in" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="book_check_out">Check-out Date</label>
                        <input type="date" id="book_check_out" name="book_check_out" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="book_guests">Number of Guests</label>
                    <input type="number" id="book_guests" name="book_guests" value="2" min="1" required>
                </div>
                <div class="flex">
                    <div class="form-group">
                        <label for="guest_name">Full Name</label>
                        <input type="text" id="guest_name" name="guest_name" value="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="guest_email">Email Address</label>
                        <input type="email" id="guest_email" name="guest_email" value="john@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="guest_phone">Phone Number</label>
                    <input type="tel" id="guest_phone" name="guest_phone" value="+1234567890" required>
                </div>
                <div class="form-group">
                    <label for="special_requests">Special Requests (optional)</label>
                    <textarea id="special_requests" name="special_requests" rows="2">Late check‑in requested</textarea>
                </div>
                <button type="submit">Create Booking via API</button>
            </form>

            <?php if ($bookingResult !== null): ?>
                <div class="response <?php echo $bookingResult['code'] === 200 || $bookingResult['code'] === 201 ? 'success' : 'error'; ?>">
                    <h3>API Response (HTTP <?php echo $bookingResult['code']; ?>)</h3>
                    <pre><?php echo json_encode($bookingResult['body'], JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <div class="section info">
            <h2>How It Works</h2>
            <ul>
                <li>This page is a <strong>PHP script</strong> that simulates a real website integrating the hotel booking API.</li>
                <li><strong>Rooms are fetched live</strong> from the hotel database (table <code>rooms</code>).</li>
                <li>All API calls are made server‑side using cURL with the <code>X‑API‑Key</code> header.</li>
                <li>The <strong>Check Availability</strong> form sends a GET request to <code>/api/availability</code> with query parameters.</li>
                <li>The <strong>Create Booking</strong> form sends a POST request to <code>/api/bookings</code> with a JSON payload.</li>
                <li>Responses are displayed in formatted JSON boxes.</li>
                <li>You can modify the API Base URL and API Key in the PHP source to test different environments.</li>
            </ul>
        </div>
    </div>

    <script>
        // Set today as minimum date for date inputs
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('check_in').min = today;
        document.getElementById('check_out').min = today;
        document.getElementById('book_check_in').min = today;
        document.getElementById('book_check_out').min = today;
    </script>
</body>
</html>