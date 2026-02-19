<?php
/**
 * Database Schema Checker - Compare with code logic for production readiness
 */

require_once __DIR__ . '/../config/database.php';

$tables_to_check = [
    'bookings', 'rooms', 'payments', 'cancellation_log', 
    'booking_timeline_logs', 'booking_payments', 'blocked_dates',
    'gym_inquiries', 'conference_bookings', 'site_settings'
];

echo "=== DATABASE SCHEMA CHECK ===\n\n";

foreach ($tables_to_check as $table) {
    echo "=== {$table} ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $row) {
            $extra = trim(($row['Null'] == 'NO' ? 'NOT NULL ' : '') . ' ' . $row['Default'] . ' ' . $row['Extra']);
            echo "  {$row['Field']}: {$row['Type']} {$extra}\n";
        }
    } catch (PDOException $e) {
        echo "  TABLE NOT FOUND: {$e->getMessage()}\n";
    }
    echo "\n";
}

// Check for missing columns that code expects
echo "=== CODE vs DATABASE CHECK ===\n\n";

// Check bookings table for required columns
$required_booking_columns = [
    'id' => 'int unsigned',
    'booking_reference' => 'varchar',
    'room_id' => 'int unsigned',
    'guest_name' => 'varchar',
    'guest_email' => 'varchar',
    'guest_phone' => 'varchar',
    'check_in_date' => 'date',
    'check_out_date' => 'date',
    'number_of_nights' => 'int',
    'number_of_guests' => 'int',
    'adult_guests' => 'int',
    'child_guests' => 'int',
    'total_amount' => 'decimal',
    'status' => 'enum',
    'is_tentative' => 'tinyint',
    'tentative_expires_at' => 'datetime',
    'occupancy_type' => 'enum',
    'child_price_multiplier' => 'decimal',
    'child_supplement_total' => 'decimal'
];

echo "Checking bookings table for required columns:\n";
$stmt = $pdo->query("DESCRIBE bookings");
$existing_columns = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[$row['Field']] = $row['Type'];
}

foreach ($required_booking_columns as $col => $expected_type) {
    if (!isset($existing_columns[$col])) {
        echo "  MISSING: {$col} (expected {$expected_type})\n";
    } else {
        $found = $existing_columns[$col];
        if (strpos($found, $expected_type) === false && strpos(strtolower($expected_type), strtolower($found)) === false) {
            echo "  TYPE MISMATCH: {$col} - expected {$expected_type}, found {$found}\n";
        }
    }
}

// Check rooms table for occupancy pricing
echo "\nChecking rooms table for occupancy pricing columns:\n";
$required_room_columns = [
    'price_single_occupancy' => 'decimal',
    'price_double_occupancy' => 'decimal',
    'price_triple_occupancy' => 'decimal',
    'single_occupancy_enabled' => 'tinyint',
    'double_occupancy_enabled' => 'tinyint',
    'triple_occupancy_enabled' => 'tinyint',
    'children_allowed' => 'tinyint',
    'child_price_multiplier' => 'decimal'
];

$stmt = $pdo->query("DESCRIBE rooms");
$existing_columns = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[$row['Field']] = $row['Type'];
}

foreach ($required_room_columns as $col => $expected_type) {
    if (!isset($existing_columns[$col])) {
        echo "  MISSING: {$col} (expected {$expected_type})\n";
    }
}

// Check payments table
echo "\nChecking payments table:\n";
try {
    $stmt = $pdo->query("DESCRIBE payments");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[$row['Field']] = $row['Type'];
    }
    echo "  Columns found: " . implode(', ', array_keys($existing_columns)) . "\n";
} catch (PDOException $e) {
    echo "  TABLE NOT FOUND - may need to be created\n";
}

echo "\n=== SCHEMA CHECK COMPLETE ===\n";