<?php
/**
 * Database Configuration
 * Hotel Website - Database Connection
 * Supports both LOCAL and PRODUCTION environments
 */

// Include caching system first
require_once __DIR__ . '/cache.php';

// Database configuration - multiple security options
// Priority: 1. Local config file, 2. Environment variables, 3. Hardcoded fallback

// Option 1: Check for local config file (for cPanel/production)
if (file_exists(__DIR__ . '/database.local.php')) {
    include __DIR__ . '/database.local.php';
} else {
    // Option 2: Use environment variables (for development)
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: '';
    $db_user = getenv('DB_USER') ?: '';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_port = getenv('DB_PORT') ?: '3306';
    $db_charset = 'utf8mb4';
}

// Validate that credentials are set
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    die('Database credentials not configured. Please create config/database.local.php with your database credentials.');
}

// Define database constants
define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_CHARSET', $db_charset);

// Create PDO connection with performance optimizations
try {
    // Diagnostic logging
    error_log("Database Connection Attempt:");
    error_log("  Host: " . DB_HOST);
    error_log("  Port: " . DB_PORT);
    error_log("  Database: " . DB_NAME);
    error_log("  User: " . DB_USER);
    error_log("  Environment Variables Set: " . (getenv('DB_HOST') ? 'YES' : 'NO'));
    
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Disabled for remote DB to prevent connection pooling issues
        PDO::ATTR_TIMEOUT => 10, // Connection timeout in seconds (increased for remote DB)
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Buffer results for better performance
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Set timezone after connection
    $pdo->exec("SET time_zone = '+00:00'");

    // Ensure child-pricing columns exist for backward-compatible deployments
    ensureChildPricingColumns($pdo);

    // Ensure housekeeping + maintenance operational tables/columns exist
    ensureOperationsSupportTables($pdo);

    // Ensure occupancy/children policy columns exist for room type + individual room overrides
    ensureOccupancyPolicyColumns($pdo);
    
    // Ensure API keys retrievable storage column exists
    ensureApiKeyRetrievableColumn($pdo);
    
    // Ensure individual room blocked dates table exists
    ensureIndividualRoomBlockedDatesTable($pdo);
    
    // Ensure housekeeping enhancements columns exist (migration 004)
    ensureHousekeepingEnhancementsColumns($pdo);
    
    // Ensure audit log tables exist for housekeeping and maintenance (migration 006)
    ensureAuditLogTables($pdo);
    
    error_log("Database Connection Successful!");
    
} catch (PDOException $e) {
    // Always show a beautiful custom error page (sleeping bear)
    $errorMsg = htmlspecialchars($e->getMessage());
    error_log("Database Connection Error: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    include_once __DIR__ . '/../includes/db-error.php';
    exit;
}

/**
 * Ensure child guest/pricing columns exist in rooms + bookings tables.
 * This keeps older databases compatible with new booking logic.
 */
function ensureChildPricingColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $ensure = function (string $table, string $column, string $alterSql) use ($pdo, $columnExistsStmt): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        // Rooms table: source-of-truth for child pricing multiplier
        $ensure(
            'rooms',
            'child_price_multiplier',
            "ALTER TABLE rooms ADD COLUMN child_price_multiplier DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER price_triple_occupancy"
        );

        // Individual rooms: optional override for specific room child pricing
        $ensure(
            'individual_rooms',
            'child_price_multiplier',
            "ALTER TABLE individual_rooms ADD COLUMN child_price_multiplier DECIMAL(5,2) NULL DEFAULT NULL AFTER status"
        );

        // Bookings table: store guest split + calculated child supplement
        $ensure(
            'bookings',
            'adult_guests',
            "ALTER TABLE bookings ADD COLUMN adult_guests INT NOT NULL DEFAULT 1 AFTER number_of_guests"
        );
        $ensure(
            'bookings',
            'child_guests',
            "ALTER TABLE bookings ADD COLUMN child_guests INT NOT NULL DEFAULT 0 AFTER adult_guests"
        );
        $ensure(
            'bookings',
            'child_price_multiplier',
            "ALTER TABLE bookings ADD COLUMN child_price_multiplier DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER child_guests"
        );
        $ensure(
            'bookings',
            'child_supplement_total',
            "ALTER TABLE bookings ADD COLUMN child_supplement_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount"
        );

        // Backfill bookings guest split from legacy total guests data
        $pdo->exec("UPDATE bookings SET adult_guests = CASE WHEN number_of_guests < 1 THEN 1 ELSE number_of_guests END WHERE adult_guests IS NULL OR adult_guests < 1");
        $pdo->exec("UPDATE bookings SET child_guests = 0 WHERE child_guests IS NULL OR child_guests < 0");

        // Backfill booking multiplier from rooms where possible
        $pdo->exec("UPDATE bookings b LEFT JOIN rooms r ON b.room_id = r.id SET b.child_price_multiplier = COALESCE(r.child_price_multiplier, b.child_price_multiplier, 50.00) WHERE b.child_price_multiplier IS NULL OR b.child_price_multiplier <= 0");
    } catch (Throwable $e) {
        error_log('ensureChildPricingColumns warning: ' . $e->getMessage());
    }
}

/**
 * Ensure housekeeping + room-maintenance tables/columns exist for operational flows.
 */
function ensureOperationsSupportTables(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $tableExists = function (string $table) use ($tableExistsStmt): bool {
            $tableExistsStmt->execute([$table]);
            return (int)$tableExistsStmt->fetchColumn() > 0;
        };

        $ensureColumn = function (string $table, string $column, string $alterSql) use ($columnExistsStmt, $pdo): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        if (!$tableExists('housekeeping_assignments')) {
            $pdo->exec("CREATE TABLE housekeeping_assignments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                individual_room_id INT UNSIGNED NOT NULL,
                status ENUM('pending','in_progress','completed','blocked') DEFAULT 'pending',
                due_date DATE DEFAULT NULL,
                assigned_to INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                notes TEXT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_housekeeping_room (individual_room_id),
                KEY idx_housekeeping_status_due (status, due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!$tableExists('room_maintenance_schedules')) {
            $pdo->exec("CREATE TABLE room_maintenance_schedules (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                individual_room_id INT UNSIGNED NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                status ENUM('planned','in_progress','completed','cancelled') DEFAULT 'planned',
                priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
                block_room TINYINT(1) DEFAULT 1,
                start_date DATETIME NOT NULL,
                end_date DATETIME NOT NULL,
                assigned_to INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_maintenance_room (individual_room_id),
                KEY idx_maintenance_status_dates (status, start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!$tableExists('room_maintenance_log')) {
            $pdo->exec("CREATE TABLE room_maintenance_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                individual_room_id INT UNSIGNED NOT NULL,
                status_from VARCHAR(50) NULL,
                status_to VARCHAR(50) NOT NULL,
                reason TEXT NULL,
                performed_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_maintenance_log_room (individual_room_id),
                KEY idx_maintenance_log_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Backward-compatible column guards
        if ($tableExists('housekeeping_assignments')) {
            $ensureColumn('housekeeping_assignments', 'completed_at', "ALTER TABLE housekeeping_assignments ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER notes");
        }

        if ($tableExists('individual_rooms')) {
            $ensureColumn('individual_rooms', 'housekeeping_status', "ALTER TABLE individual_rooms ADD COLUMN housekeeping_status ENUM('pending','in_progress','completed') DEFAULT 'pending' AFTER status");
            $ensureColumn('individual_rooms', 'housekeeping_notes', "ALTER TABLE individual_rooms ADD COLUMN housekeeping_notes TEXT NULL AFTER housekeeping_status");
            $ensureColumn('individual_rooms', 'last_cleaned_at', "ALTER TABLE individual_rooms ADD COLUMN last_cleaned_at DATETIME DEFAULT NULL AFTER housekeeping_notes");
        }
    } catch (Throwable $e) {
        error_log('ensureOperationsSupportTables warning: ' . $e->getMessage());
    }
}

/**
 * Ensure occupancy/children policy columns exist on rooms + individual_rooms.
 *
 * Rules:
 * - room.max_guests >= 1 => single occupancy enabled
 * - room.max_guests >= 2 => double occupancy enabled
 * - room.max_guests >= 3 => triple occupancy may be enabled/disabled (manual toggle)
 * - children allowed is explicitly configurable
 * - individual room can optionally override each policy via nullable override fields
 */
function ensureOccupancyPolicyColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $ensure = function (string $table, string $column, string $alterSql) use ($pdo, $columnExistsStmt): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        // Room type policy flags
        $ensure(
            'rooms',
            'single_occupancy_enabled',
            "ALTER TABLE rooms ADD COLUMN single_occupancy_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER max_guests"
        );
        $ensure(
            'rooms',
            'double_occupancy_enabled',
            "ALTER TABLE rooms ADD COLUMN double_occupancy_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER single_occupancy_enabled"
        );
        $ensure(
            'rooms',
            'triple_occupancy_enabled',
            "ALTER TABLE rooms ADD COLUMN triple_occupancy_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER double_occupancy_enabled"
        );
        $ensure(
            'rooms',
            'children_allowed',
            "ALTER TABLE rooms ADD COLUMN children_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER triple_occupancy_enabled"
        );

        // Individual room override flags (nullable = inherit room type)
        $ensure(
            'individual_rooms',
            'single_occupancy_enabled_override',
            "ALTER TABLE individual_rooms ADD COLUMN single_occupancy_enabled_override TINYINT(1) NULL DEFAULT NULL AFTER child_price_multiplier"
        );
        $ensure(
            'individual_rooms',
            'double_occupancy_enabled_override',
            "ALTER TABLE individual_rooms ADD COLUMN double_occupancy_enabled_override TINYINT(1) NULL DEFAULT NULL AFTER single_occupancy_enabled_override"
        );
        $ensure(
            'individual_rooms',
            'triple_occupancy_enabled_override',
            "ALTER TABLE individual_rooms ADD COLUMN triple_occupancy_enabled_override TINYINT(1) NULL DEFAULT NULL AFTER double_occupancy_enabled_override"
        );
        $ensure(
            'individual_rooms',
            'children_allowed_override',
            "ALTER TABLE individual_rooms ADD COLUMN children_allowed_override TINYINT(1) NULL DEFAULT NULL AFTER triple_occupancy_enabled_override"
        );

        // Backfill/enforce baseline occupancy behavior from max_guests
        $pdo->exec("UPDATE rooms SET single_occupancy_enabled = 1 WHERE max_guests >= 1");
        $pdo->exec("UPDATE rooms SET single_occupancy_enabled = 0 WHERE max_guests < 1");
        $pdo->exec("UPDATE rooms SET double_occupancy_enabled = 1 WHERE max_guests >= 2");
        $pdo->exec("UPDATE rooms SET double_occupancy_enabled = 0 WHERE max_guests < 2");
        $pdo->exec("UPDATE rooms SET triple_occupancy_enabled = 0 WHERE max_guests < 3");
    } catch (Throwable $e) {
        error_log('ensureOccupancyPolicyColumns warning: ' . $e->getMessage());
    }
}

/**
 * Ensure individual room blocked dates table exists for dual-layer blocking.
 * This allows blocking specific individual rooms on specific dates,
 * separate from room-type level blocks.
 */
function ensureIndividualRoomBlockedDatesTable(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $tableExists = function (string $table) use ($tableExistsStmt): bool {
            $tableExistsStmt->execute([$table]);
            return (int)$tableExistsStmt->fetchColumn() > 0;
        };

        $ensureColumn = function (string $table, string $column, string $alterSql) use ($columnExistsStmt, $pdo): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        // Create individual_room_blocked_dates table if not exists
        if (!$tableExists('individual_room_blocked_dates')) {
            $pdo->exec("CREATE TABLE individual_room_blocked_dates (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                individual_room_id INT UNSIGNED NOT NULL,
                block_date DATE NOT NULL,
                block_type ENUM('manual', 'maintenance', 'event', 'full') DEFAULT 'manual',
                reason TEXT NULL,
                blocked_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_individual_room_date (individual_room_id, block_date),
                KEY idx_individual_room_block_date (block_date),
                KEY idx_individual_room_block_type (block_type),
                KEY idx_individual_room_blocked_by (blocked_by),
                CONSTRAINT fk_irbd_individual_room FOREIGN KEY (individual_room_id) REFERENCES individual_rooms (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_irbd_blocked_by FOREIGN KEY (blocked_by) REFERENCES admin_users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Ensure block_type column exists in blocked_dates table for consistency
        if ($tableExists('blocked_dates')) {
            $ensureColumn(
                'blocked_dates',
                'block_type',
                "ALTER TABLE blocked_dates ADD COLUMN block_type ENUM('manual', 'maintenance', 'event', 'full') DEFAULT 'manual' AFTER block_date"
            );
            
            // Ensure block_type index exists (check if index exists before creating)
            try {
                $indexCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
                $indexCheckStmt->execute(['blocked_dates', 'idx_blocked_dates_block_type']);
                $indexExists = (int)$indexCheckStmt->fetchColumn() > 0;
                if (!$indexExists) {
                    $pdo->exec("ALTER TABLE blocked_dates ADD INDEX idx_blocked_dates_block_type (block_type)");
                }
            } catch (Throwable $e) {
                // Ignore index creation errors
            }
        }
    } catch (Throwable $e) {
        error_log('ensureIndividualRoomBlockedDatesTable warning: ' . $e->getMessage());
    }
}

/**
 * Ensure housekeeping enhancements columns exist (migration 004).
 * This adds priority, assignment_type, recurring settings, and verification workflow to housekeeping.
 * The function is idempotent - safe to run multiple times.
 */
function ensureHousekeepingEnhancementsColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $indexExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $constraintExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");

        $tableExists = function (string $table) use ($tableExistsStmt): bool {
            $tableExistsStmt->execute([$table]);
            return (int)$tableExistsStmt->fetchColumn() > 0;
        };

        $ensureColumn = function (string $table, string $column, string $alterSql) use ($columnExistsStmt, $pdo): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        $ensureIndex = function (string $table, string $index, string $createSql) use ($indexExistsStmt, $pdo): void {
            $indexExistsStmt->execute([$table, $index]);
            $exists = (int)$indexExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($createSql);
            }
        };

        $ensureConstraint = function (string $table, string $constraint, string $alterSql) use ($constraintExistsStmt, $pdo): void {
            $constraintExistsStmt->execute([$table, $constraint]);
            $exists = (int)$constraintExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        // Only proceed if housekeeping_assignments table exists
        if (!$tableExists('housekeeping_assignments')) {
            return;
        }

        // Add priority column (high, medium, low)
        $ensureColumn(
            'housekeeping_assignments',
            'priority',
            "ALTER TABLE housekeeping_assignments ADD COLUMN priority ENUM('high', 'medium', 'low') DEFAULT 'medium'"
        );

        // Add assignment_type column (checkout_cleanup, regular_cleaning, maintenance, deep_clean, turn_down)
        $ensureColumn(
            'housekeeping_assignments',
            'assignment_type',
            "ALTER TABLE housekeeping_assignments ADD COLUMN assignment_type ENUM('checkout_cleanup', 'regular_cleaning', 'maintenance', 'deep_clean', 'turn_down') DEFAULT 'regular_cleaning'"
        );

        // Add is_recurring column for recurring tasks
        $ensureColumn(
            'housekeeping_assignments',
            'is_recurring',
            "ALTER TABLE housekeeping_assignments ADD COLUMN is_recurring TINYINT(1) DEFAULT 0"
        );

        // Add recurring_pattern column (daily, weekly, monthly)
        $ensureColumn(
            'housekeeping_assignments',
            'recurring_pattern',
            "ALTER TABLE housekeeping_assignments ADD COLUMN recurring_pattern ENUM('daily', 'weekly', 'monthly') DEFAULT NULL"
        );

        // Add recurring_end_date for when recurring tasks should stop
        $ensureColumn(
            'housekeeping_assignments',
            'recurring_end_date',
            "ALTER TABLE housekeeping_assignments ADD COLUMN recurring_end_date DATE DEFAULT NULL"
        );

        // Add verified_by column for supervisor verification
        $ensureColumn(
            'housekeeping_assignments',
            'verified_by',
            "ALTER TABLE housekeeping_assignments ADD COLUMN verified_by INT DEFAULT NULL"
        );

        // Add verified_at column for verification timestamp
        $ensureColumn(
            'housekeeping_assignments',
            'verified_at',
            "ALTER TABLE housekeeping_assignments ADD COLUMN verified_at DATETIME DEFAULT NULL"
        );

        // Add estimated_duration in minutes
        $ensureColumn(
            'housekeeping_assignments',
            'estimated_duration',
            "ALTER TABLE housekeeping_assignments ADD COLUMN estimated_duration INT DEFAULT 30 COMMENT 'Estimated duration in minutes'"
        );

        // Add actual_duration in minutes
        $ensureColumn(
            'housekeeping_assignments',
            'actual_duration',
            "ALTER TABLE housekeeping_assignments ADD COLUMN actual_duration INT DEFAULT NULL COMMENT 'Actual duration in minutes'"
        );

        // Add auto_created flag for automatically created assignments (e.g., checkout cleanup)
        $ensureColumn(
            'housekeeping_assignments',
            'auto_created',
            "ALTER TABLE housekeeping_assignments ADD COLUMN auto_created TINYINT(1) DEFAULT 0"
        );

        // Add linked_booking_id for checkout cleanup assignments
        $ensureColumn(
            'housekeeping_assignments',
            'linked_booking_id',
            "ALTER TABLE housekeeping_assignments ADD COLUMN linked_booking_id BIGINT DEFAULT NULL"
        );

        // Add indexes for better query performance
        $ensureIndex('housekeeping_assignments', 'idx_housekeeping_priority',
            "CREATE INDEX idx_housekeeping_priority ON housekeeping_assignments(priority)");
        $ensureIndex('housekeeping_assignments', 'idx_housekeeping_status_priority',
            "CREATE INDEX idx_housekeeping_status_priority ON housekeeping_assignments(status, priority)");
        $ensureIndex('housekeeping_assignments', 'idx_housekeeping_assigned_to',
            "CREATE INDEX idx_housekeeping_assigned_to ON housekeeping_assignments(assigned_to)");
        $ensureIndex('housekeeping_assignments', 'idx_housekeeping_due_date',
            "CREATE INDEX idx_housekeeping_due_date ON housekeeping_assignments(due_date)");
        $ensureIndex('housekeeping_assignments', 'idx_housekeeping_type',
            "CREATE INDEX idx_housekeeping_type ON housekeeping_assignments(assignment_type)");

        // Add foreign key constraint for verified_by
        $ensureConstraint('housekeeping_assignments', 'fk_housekeeping_verified_by',
            "ALTER TABLE housekeeping_assignments ADD CONSTRAINT fk_housekeeping_verified_by FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL");

        // Add foreign key constraint for linked_booking_id
        $ensureConstraint('housekeeping_assignments', 'fk_housekeeping_booking',
            "ALTER TABLE housekeeping_assignments ADD CONSTRAINT fk_housekeeping_booking FOREIGN KEY (linked_booking_id) REFERENCES bookings(id) ON DELETE SET NULL");

        // Update existing records to have default priority
        $pdo->exec("UPDATE housekeeping_assignments SET priority = 'medium' WHERE priority IS NULL");

        // Update existing records to have default assignment type
        $pdo->exec("UPDATE housekeeping_assignments SET assignment_type = 'regular_cleaning' WHERE assignment_type IS NULL");

    } catch (Throwable $e) {
        error_log('ensureHousekeepingEnhancementsColumns warning: ' . $e->getMessage());
    }
}

/**
 * Resolve effective occupancy + children policy for a room type (and optional individual room override).
 */
function resolveOccupancyPolicy(array $room, ?array $individualRoom = null): array {
    $maxGuests = max(0, (int)($room['max_guests'] ?? 0));

    $single = (int)($room['single_occupancy_enabled'] ?? 1);
    $double = (int)($room['double_occupancy_enabled'] ?? 1);
    $triple = (int)($room['triple_occupancy_enabled'] ?? 1);
    $childrenAllowed = (int)($room['children_allowed'] ?? 1);

    if ($individualRoom !== null) {
        if (array_key_exists('single_occupancy_enabled_override', $individualRoom) && $individualRoom['single_occupancy_enabled_override'] !== null) {
            $single = (int)$individualRoom['single_occupancy_enabled_override'];
        }
        if (array_key_exists('double_occupancy_enabled_override', $individualRoom) && $individualRoom['double_occupancy_enabled_override'] !== null) {
            $double = (int)$individualRoom['double_occupancy_enabled_override'];
        }
        if (array_key_exists('triple_occupancy_enabled_override', $individualRoom) && $individualRoom['triple_occupancy_enabled_override'] !== null) {
            $triple = (int)$individualRoom['triple_occupancy_enabled_override'];
        }
        if (array_key_exists('children_allowed_override', $individualRoom) && $individualRoom['children_allowed_override'] !== null) {
            $childrenAllowed = (int)$individualRoom['children_allowed_override'];
        }
    }

    // Capacity always takes precedence
    if ($maxGuests < 1) {
        $single = 0;
    }
    if ($maxGuests < 2) {
        $double = 0;
    }
    if ($maxGuests < 3) {
        $triple = 0;
    }

    // Pricing policy: If occupancy pricing is NULL, use base price (allows booking up to max_guests)
    // Only disable occupancy if explicitly set to 0 (not NULL)
    if (array_key_exists('price_double_occupancy', $room)) {
        if ($room['price_double_occupancy'] === '0' || $room['price_double_occupancy'] === 0) {
            // Explicitly disabled
            $double = 0;
        }
        // NULL or positive value means enabled (NULL will use base price as fallback)
    }
    if (array_key_exists('price_triple_occupancy', $room)) {
        if ($room['price_triple_occupancy'] === '0' || $room['price_triple_occupancy'] === 0) {
            // Explicitly disabled
            $triple = 0;
        }
        // NULL or positive value means enabled (NULL will use base price as fallback)
    }

    return [
        'max_guests' => $maxGuests,
        'single_enabled' => $single ? 1 : 0,
        'double_enabled' => $double ? 1 : 0,
        'triple_enabled' => $triple ? 1 : 0,
        'children_allowed' => $childrenAllowed ? 1 : 0,
    ];
}

// Settings cache to avoid repeated queries
$_SITE_SETTINGS = [];

/**
 * Helper function to get setting value with file-based caching
 * DRAMATICALLY reduces database queries and remote connection overhead
 */
function getSetting($key, $default = '') {
    global $pdo, $_SITE_SETTINGS;
    
    // Check in-memory cache first (fastest)
    if (isset($_SITE_SETTINGS[$key])) {
        return $_SITE_SETTINGS[$key];
    }
    
    // Check file cache (much faster than database query)
    $cachedValue = getCache("setting_{$key}", null);
    if ($cachedValue !== null) {
        $_SITE_SETTINGS[$key] = $cachedValue;
        return $cachedValue;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $value = $result ? $result['setting_value'] : $default;
        
        // Cache in memory
        $_SITE_SETTINGS[$key] = $value;
        
        // Cache in file for next request (1 hour TTL)
        setCache("setting_{$key}", $value, 3600);
        
        return $value;
    } catch (PDOException $e) {
        error_log("Error fetching setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Helper function to get email setting value with caching
 * Handles encrypted settings like passwords
 */
function getEmailSetting($key, $default = '') {
    global $pdo;
    
    try {
        // Check if email_settings table exists (cached)
        $table_exists = getCache("table_email_settings", null);
        if ($table_exists === null) {
            $table_exists = $pdo->query("SHOW TABLES LIKE 'email_settings'")->rowCount() > 0;
            setCache("table_email_settings", $table_exists, 86400); // Cache for 24 hours
        }
        
        if (!$table_exists) {
            // Fallback to site_settings for backward compatibility
            return getSetting($key, $default);
        }
        
        // Try file cache first
        $cachedValue = getCache("email_setting_{$key}", null);
        if ($cachedValue !== null) {
            return $cachedValue;
        }
        
        $stmt = $pdo->prepare("SELECT setting_value, is_encrypted FROM email_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        $is_encrypted = (bool)$result['is_encrypted'];
        
        // Handle encrypted values (like passwords)
        if ($is_encrypted && !empty($value)) {
            try {
                // Try to decrypt using database function
                $stmt = $pdo->prepare("SELECT decrypt_setting(?) as decrypted_value");
                $stmt->execute([$value]);
                $decrypted = $stmt->fetch();
                if ($decrypted && !empty($decrypted['decrypted_value'])) {
                    $value = $decrypted['decrypted_value'];
                } else {
                    // Fallback: use raw stored value when DB decrypt function is unavailable
                    // or returns empty unexpectedly. This keeps SMTP credentials usable.
                    $value = $result['setting_value'];
                }
            } catch (Exception $e) {
                // Fallback to raw stored value when decrypt function is missing/invalid.
                $value = $result['setting_value'];
            }
        }
        
        // Cache the result (1 hour TTL for encrypted, 6 hours for unencrypted)
        $ttl = $is_encrypted ? 3600 : 21600;
        setCache("email_setting_{$key}", $value, $ttl);
        
        return $value;
    } catch (PDOException $e) {
        error_log("Error fetching email setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Helper function to get all email settings
 */
function getAllEmailSettings() {
    global $pdo;
    
    $settings = [];
    try {
        // Check if email_settings table exists
        $table_exists = $pdo->query("SHOW TABLES LIKE 'email_settings'")->rowCount() > 0;
        
        if (!$table_exists) {
            return $settings;
        }
        
        $stmt = $pdo->query("SELECT setting_key, setting_value, is_encrypted, description FROM email_settings ORDER BY setting_group, setting_key");
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            $is_encrypted = (bool)$row['is_encrypted'];
            
            // Handle encrypted values
            if ($is_encrypted && !empty($value)) {
                try {
                    $stmt2 = $pdo->prepare("SELECT decrypt_setting(?) as decrypted_value");
                    $stmt2->execute([$value]);
                    $decrypted = $stmt2->fetch();
                    if ($decrypted && !empty($decrypted['decrypted_value'])) {
                        $value = $decrypted['decrypted_value'];
                    } else {
                        $value = ''; // Don't expose encrypted data
                    }
                } catch (Exception $e) {
                    $value = ''; // Don't expose encrypted data on error
                }
            }
            
            $settings[$key] = [
                'value' => $value,
                'encrypted' => $is_encrypted,
                'description' => $row['description']
            ];
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Error fetching all email settings: " . $e->getMessage());
        return $settings;
    }
}

/**
 * Helper function to update email setting
 */
function updateEmailSetting($key, $value, $description = null, $is_encrypted = false) {
    global $pdo;
    
    try {
        // Check if email_settings table exists
        $table_exists = $pdo->query("SHOW TABLES LIKE 'email_settings'")->rowCount() > 0;
        
        if (!$table_exists) {
            // Fallback to site_settings for backward compatibility
            return updateSetting($key, $value);
        }
        
        // Handle encryption if needed
        $final_value = $value;
        $final_is_encrypted = $is_encrypted ? 1 : 0;
        if ($is_encrypted && !empty($value)) {
            try {
                $stmt = $pdo->prepare("SELECT encrypt_setting(?) as encrypted_value");
                $stmt->execute([$value]);
                $encrypted = $stmt->fetch();
                if ($encrypted && !empty($encrypted['encrypted_value'])) {
                    $final_value = $encrypted['encrypted_value'];
                    $final_is_encrypted = 1;
                } else {
                    // Encryption function exists but returned empty. Store plaintext to avoid
                    // breaking SMTP authentication flows.
                    $final_value = $value;
                    $final_is_encrypted = 0;
                }
            } catch (Exception $e) {
                // If database encryption functions are unavailable, preserve operability by
                // storing plaintext instead of failing save.
                error_log("Email setting encryption unavailable for {$key}, saving plaintext: " . $e->getMessage());
                $final_value = $value;
                $final_is_encrypted = 0;
            }
        }
        
        // Update or insert
        $sql = "INSERT INTO email_settings (setting_key, setting_value, is_encrypted, description) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                is_encrypted = VALUES(is_encrypted),
                description = VALUES(description),
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $final_value, $final_is_encrypted, $description]);
        
        // Clear cache for this setting
        global $_SITE_SETTINGS;
        if (isset($_SITE_SETTINGS[$key])) {
            unset($_SITE_SETTINGS[$key]);
        }

        // Clear file cache entries so new SMTP/email settings apply immediately
        deleteCache("email_setting_{$key}");
        deleteCache("setting_{$key}");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating email setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to update setting (for backward compatibility)
 */
function updateSetting($key, $value) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $value]);
        
        // Clear cache for this setting
        global $_SITE_SETTINGS;
        if (isset($_SITE_SETTINGS[$key])) {
            unset($_SITE_SETTINGS[$key]);
        }

        // Clear file cache copy so getSetting() returns fresh value immediately
        deleteCache("setting_{$key}");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if booking email templates table exists (cached)
 */
function bookingEmailTemplatesTableExists() {
    global $pdo;

    $cacheKey = 'table_booking_email_templates';
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return (bool)$cached;
    }

    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'booking_email_templates'")->rowCount() > 0;
        setCache($cacheKey, $exists ? 1 : 0, 86400);
        return $exists;
    } catch (PDOException $e) {
        error_log("Error checking booking_email_templates table: " . $e->getMessage());
        return false;
    }
}

/**
 * Ensure booking email templates table exists
 */
function ensureBookingEmailTemplatesTable() {
    global $pdo;

    if (bookingEmailTemplatesTableExists()) {
        return true;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_email_templates (
            id INT NOT NULL AUTO_INCREMENT,
            template_key VARCHAR(100) NOT NULL,
            template_name VARCHAR(150) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            html_body MEDIUMTEXT NOT NULL,
            text_body TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_template_key (template_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        setCache('table_booking_email_templates', 1, 86400);
        return true;
    } catch (PDOException $e) {
        error_log("Error creating booking_email_templates table: " . $e->getMessage());
        return false;
    }
}

/**
 * Get booking email template configuration
 */
function getBookingEmailTemplateConfig($templateKey, $defaults = []) {
    global $pdo;

    $fallback = array_merge([
        'template_key' => $templateKey,
        'template_name' => $templateKey,
        'subject' => '',
        'html_body' => '',
        'text_body' => '',
        'is_active' => 1
    ], $defaults);

    if (!bookingEmailTemplatesTableExists()) {
        return $fallback;
    }

    $cacheKey = 'booking_email_template_' . $templateKey;
    $cached = getCache($cacheKey, null);
    if (is_array($cached)) {
        return array_merge($fallback, $cached);
    }

    try {
        $stmt = $pdo->prepare("SELECT template_key, template_name, subject, html_body, text_body, is_active FROM booking_email_templates WHERE template_key = ? LIMIT 1");
        $stmt->execute([$templateKey]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return $fallback;
        }

        $template = array_merge($fallback, $template);
        setCache($cacheKey, $template, 1800);
        return $template;
    } catch (PDOException $e) {
        error_log("Error fetching booking email template {$templateKey}: " . $e->getMessage());
        return $fallback;
    }
}

/**
 * Insert or update booking email template configuration
 */
function upsertBookingEmailTemplateConfig($templateKey, $templateName, $subject, $htmlBody, $textBody = '', $isActive = 1) {
    global $pdo;

    if (!ensureBookingEmailTemplatesTable()) {
        return false;
    }

    try {
        $sql = "INSERT INTO booking_email_templates (template_key, template_name, subject, html_body, text_body, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    template_name = VALUES(template_name),
                    subject = VALUES(subject),
                    html_body = VALUES(html_body),
                    text_body = VALUES(text_body),
                    is_active = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $templateKey,
            $templateName,
            $subject,
            $htmlBody,
            $textBody,
            (int)$isActive
        ]);

        if ($ok) {
            deleteCache('booking_email_template_' . $templateKey);
        }

        return $ok;
    } catch (PDOException $e) {
        error_log("Error upserting booking email template {$templateKey}: " . $e->getMessage());
        return false;
    }
}

/**
 * Preload common settings for better performance
 */
function preloadCommonSettings() {
    $common_settings = [
        'site_name', 'site_description', 'currency_symbol',
        'phone_main', 'email_reservations', 'email_info',
        'social_facebook', 'social_instagram', 'social_twitter'
    ];
    
    foreach ($common_settings as $setting) {
        getSetting($setting);
    }
}

// Preload common settings for faster page loads
preloadCommonSettings();

/**
 * Helper function to get all settings by group
 */
function getSettingsByGroup($group) {
    global $pdo;
    
    // Check cache first
    $cached = getCache("settings_group_{$group}", null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_group = ?");
        $stmt->execute([$group]);
        $result = $stmt->fetchAll();
        
        // Cache for 30 minutes
        setCache("settings_group_{$group}", $result, 1800);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to get cached rooms with optional filters
 * Dramatically reduces database queries for room listings
 */
function getCachedRooms($filters = []) {
    global $pdo;
    
    // Create cache key from filters
    $cacheKey = 'rooms_' . md5(json_encode($filters));
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $sql = "SELECT * FROM rooms WHERE is_active = 1";
        $params = [];
        
        if (!empty($filters['is_featured'])) {
            $sql .= " AND is_featured = 1";
        }
        
        $sql .= " ORDER BY display_order ASC, id ASC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (mediaTablesAvailable() && !empty($rooms)) {
            foreach ($rooms as &$roomRow) {
                $roomRow = applyManagedMediaOverrides($roomRow, 'rooms', $roomRow['id'] ?? '', ['image_url', 'video_path']);
            }
            unset($roomRow);
        }
        
        // Cache for 15 minutes
        setCache($cacheKey, $rooms, 900);
        
        return $rooms;
    } catch (PDOException $e) {
        error_log("Error fetching rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to get cached facilities
 */
function getCachedFacilities($filters = []) {
    global $pdo;
    
    $cacheKey = 'facilities_' . md5(json_encode($filters));
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $sql = "SELECT * FROM facilities WHERE is_active = 1";
        $params = [];
        
        if (!empty($filters['is_featured'])) {
            $sql .= " AND is_featured = 1";
        }
        
        $sql .= " ORDER BY display_order ASC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache for 30 minutes
        setCache($cacheKey, $facilities, 1800);
        
        return $facilities;
    } catch (PDOException $e) {
        error_log("Error fetching facilities: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to get cached gallery images
 */
function getCachedGalleryImages() {
    global $pdo;
    
    $cacheKey = 'gallery_images';
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT id, title, description, image_url, video_path, video_type, category, display_order 
            FROM hotel_gallery 
            WHERE is_active = 1 
            ORDER BY display_order ASC
        ");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (mediaTablesAvailable() && !empty($images)) {
            foreach ($images as &$imageRow) {
                $imageRow = applyManagedMediaOverrides($imageRow, 'hotel_gallery', $imageRow['id'] ?? '', ['image_url', 'video_path']);
            }
            unset($imageRow);
        }
        
        // Cache for 1 hour
        setCache($cacheKey, $images, 3600);
        
        return $images;
    } catch (PDOException $e) {
        error_log("Error fetching gallery images: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch active managed media items by placement key.
 *
 * Backward compatibility:
 * - $group_key remains the public API parameter name.
 * - It maps to managed_media_catalog.placement_key.
 */
function getManagedMediaItems(string $group_key, array $options = []): array {
    global $pdo;

    $limit = isset($options['limit']) ? (int)$options['limit'] : 0;
    $mediaType = $options['media_type'] ?? null; // image|video|null

    // Unified catalog path (canonical)
    try {
        $sql = "
            SELECT
                c.id,
                NULL AS group_id,
                c.title,
                c.description,
                c.media_type,
                c.source_type,
                CASE WHEN c.source_type = 'upload' THEN c.media_url ELSE NULL END AS file_path,
                CASE WHEN c.source_type = 'url' THEN c.media_url ELSE NULL END AS external_url,
                c.mime_type,
                c.alt_text,
                c.caption,
                c.display_order,
                c.is_active,
                c.placement_key,
                c.page_slug,
                c.section_key,
                c.media_url
            FROM managed_media_catalog c
            WHERE c.placement_key = ?
              AND c.is_active = 1
              AND c.media_url IS NOT NULL
              AND c.media_url <> ''
        ";

        $params = [$group_key];

        if ($mediaType === 'image' || $mediaType === 'video') {
            $sql .= " AND c.media_type = ?";
            $params[] = $mediaType;
        }

        $sql .= " ORDER BY c.display_order ASC, c.id ASC";

        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching managed media ({$group_key}): " . $e->getMessage());
        return [];
    }
}

/**
 * Returns true when unified media mapping tables are available.
 */
function mediaTablesAvailable(): bool {
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    global $pdo;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'managed_media_links'");
        $hasLinks = (bool)$stmt->fetchColumn();
        $stmt = $pdo->query("SHOW TABLES LIKE 'managed_media_catalog'");
        $hasCatalog = (bool)$stmt->fetchColumn();
        $available = $hasLinks && $hasCatalog;
    } catch (Throwable $e) {
        $available = false;
    }

    return $available;
}

/**
 * Returns true when managed_media_catalog has legacy tracking columns.
 */
function mediaCatalogHasLegacyColumns(): bool {
    static $hasLegacyColumns = null;
    if ($hasLegacyColumns !== null) {
        return $hasLegacyColumns;
    }

    global $pdo;

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM managed_media_catalog LIKE 'legacy_source'");
        $hasLegacySource = (bool)$stmt->fetchColumn();
        $stmt = $pdo->query("SHOW COLUMNS FROM managed_media_catalog LIKE 'legacy_id'");
        $hasLegacyId = (bool)$stmt->fetchColumn();
        $hasLegacyColumns = $hasLegacySource && $hasLegacyId;
    } catch (Throwable $e) {
        $hasLegacyColumns = false;
    }

    return $hasLegacyColumns;
}

/**
 * Infer media type from source column/path.
 */
function inferMediaTypeFromSource(string $sourceColumn, ?string $mediaUrl = null): string {
    $column = strtolower($sourceColumn);
    $url = strtolower((string)$mediaUrl);

    if (strpos($column, 'video') !== false) {
        return 'video';
    }

    if (preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?|$)/i', $url)) {
        return 'video';
    }

    return 'image';
}

/**
 * Return source type for a media URL/path.
 */
function inferSourceTypeFromMediaUrl(?string $mediaUrl): string {
    return preg_match('#^https?://#i', trim((string)$mediaUrl)) ? 'url' : 'upload';
}

/**
 * Upsert canonical media + source mapping.
 *
 * @param string $sourceTable Legacy source table name
 * @param int|string $sourceRecordId Legacy record identifier
 * @param string $sourceColumn Legacy media column name
 * @param string|null $mediaUrl Media path/URL from source
 * @param array $context Optional context keys: title, description, caption, alt_text,
 *                       page_slug, section_key, placement_key, entity_type, entity_id,
 *                       display_order, source_context, use_case, media_type
 */
function upsertManagedMediaForSource(
    string $sourceTable,
    $sourceRecordId,
    string $sourceColumn,
    ?string $mediaUrl,
    array $context = []
): bool {
    global $pdo;

    if (!mediaTablesAvailable()) {
        return false;
    }

    $sourceRecordId = (string)$sourceRecordId;
    $mediaUrl = trim((string)$mediaUrl);
    $sourceContext = trim((string)($context['source_context'] ?? ''));

    try {
        // If source media was removed, keep mapping row but mark inactive.
        if ($mediaUrl === '') {
            $deactivate = $pdo->prepare("UPDATE managed_media_links SET is_active = 0 WHERE source_table = ? AND source_record_id = ? AND source_column = ? AND source_context = ?");
            $deactivate->execute([$sourceTable, $sourceRecordId, $sourceColumn, $sourceContext]);
            return true;
        }

        $mediaType = (($context['media_type'] ?? '') === 'video' || ($context['media_type'] ?? '') === 'image')
            ? $context['media_type']
            : inferMediaTypeFromSource($sourceColumn, $mediaUrl);
        $sourceType = inferSourceTypeFromMediaUrl($mediaUrl);

        $legacySource = $sourceTable . '.' . $sourceColumn;
        $legacyId = ctype_digit($sourceRecordId) ? (int)$sourceRecordId : null;
        $catalogHasLegacyColumns = mediaCatalogHasLegacyColumns();

        $catalogId = null;

        if ($legacyId !== null && $catalogHasLegacyColumns) {
            $lookup = $pdo->prepare("SELECT id FROM managed_media_catalog WHERE legacy_source = ? AND legacy_id = ? LIMIT 1");
            $lookup->execute([$legacySource, $legacyId]);
            $catalogId = (int)($lookup->fetchColumn() ?: 0);
        }

        if ($catalogId <= 0) {
            $lookupByLink = $pdo->prepare("SELECT media_catalog_id FROM managed_media_links WHERE source_table = ? AND source_record_id = ? AND source_column = ? AND source_context = ? LIMIT 1");
            $lookupByLink->execute([$sourceTable, $sourceRecordId, $sourceColumn, $sourceContext]);
            $catalogId = (int)($lookupByLink->fetchColumn() ?: 0);
        }

        if ($catalogId > 0) {
            $updateCatalog = $pdo->prepare("UPDATE managed_media_catalog SET title = ?, description = ?, media_type = ?, source_type = ?, media_url = ?, mime_type = COALESCE(?, mime_type), alt_text = ?, caption = ?, placement_key = ?, page_slug = ?, section_key = ?, entity_type = ?, entity_id = ?, is_active = 1, display_order = ? WHERE id = ?");
            $updateCatalog->execute([
                (string)($context['title'] ?? ucfirst(str_replace('_', ' ', $sourceColumn))),
                (string)($context['description'] ?? ''),
                $mediaType,
                $sourceType,
                $mediaUrl,
                $context['mime_type'] ?? null,
                $context['alt_text'] ?? null,
                $context['caption'] ?? null,
                $context['placement_key'] ?? null,
                $context['page_slug'] ?? null,
                $context['section_key'] ?? null,
                $context['entity_type'] ?? null,
                $context['entity_id'] ?? null,
                (int)($context['display_order'] ?? 0),
                $catalogId,
            ]);
        } else {
            if ($catalogHasLegacyColumns) {
                $insertCatalog = $pdo->prepare("INSERT INTO managed_media_catalog (title, description, media_type, source_type, media_url, mime_type, alt_text, caption, placement_key, page_slug, section_key, entity_type, entity_id, is_active, display_order, legacy_source, legacy_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)");
                $insertCatalog->execute([
                    (string)($context['title'] ?? ucfirst(str_replace('_', ' ', $sourceColumn))),
                    (string)($context['description'] ?? ''),
                    $mediaType,
                    $sourceType,
                    $mediaUrl,
                    $context['mime_type'] ?? null,
                    $context['alt_text'] ?? null,
                    $context['caption'] ?? null,
                    $context['placement_key'] ?? null,
                    $context['page_slug'] ?? null,
                    $context['section_key'] ?? null,
                    $context['entity_type'] ?? null,
                    $context['entity_id'] ?? null,
                    (int)($context['display_order'] ?? 0),
                    $legacySource,
                    $legacyId,
                    $context['created_by'] ?? null,
                ]);
            } else {
                $insertCatalog = $pdo->prepare("INSERT INTO managed_media_catalog (title, description, media_type, source_type, media_url, mime_type, alt_text, caption, placement_key, page_slug, section_key, entity_type, entity_id, is_active, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
                $insertCatalog->execute([
                    (string)($context['title'] ?? ucfirst(str_replace('_', ' ', $sourceColumn))),
                    (string)($context['description'] ?? ''),
                    $mediaType,
                    $sourceType,
                    $mediaUrl,
                    $context['mime_type'] ?? null,
                    $context['alt_text'] ?? null,
                    $context['caption'] ?? null,
                    $context['placement_key'] ?? null,
                    $context['page_slug'] ?? null,
                    $context['section_key'] ?? null,
                    $context['entity_type'] ?? null,
                    $context['entity_id'] ?? null,
                    (int)($context['display_order'] ?? 0),
                    $context['created_by'] ?? null,
                ]);
            }
            $catalogId = (int)$pdo->lastInsertId();
        }

        $linkLookup = $pdo->prepare("SELECT id FROM managed_media_links WHERE source_table = ? AND source_record_id = ? AND source_column = ? AND source_context = ? LIMIT 1");
        $linkLookup->execute([$sourceTable, $sourceRecordId, $sourceColumn, $sourceContext]);
        $linkId = (int)($linkLookup->fetchColumn() ?: 0);

        if ($linkId > 0) {
            $updateLink = $pdo->prepare("UPDATE managed_media_links SET media_catalog_id = ?, media_type = ?, placement_key = ?, page_slug = ?, section_key = ?, entity_type = ?, entity_id = ?, use_case = ?, display_order = ?, is_active = 1 WHERE id = ?");
            $updateLink->execute([
                $catalogId,
                $mediaType,
                $context['placement_key'] ?? null,
                $context['page_slug'] ?? null,
                $context['section_key'] ?? null,
                $context['entity_type'] ?? null,
                $context['entity_id'] ?? null,
                $context['use_case'] ?? null,
                (int)($context['display_order'] ?? 0),
                $linkId,
            ]);
        } else {
            $insertLink = $pdo->prepare("INSERT INTO managed_media_links (media_catalog_id, source_table, source_record_id, source_column, source_context, media_type, placement_key, page_slug, section_key, entity_type, entity_id, use_case, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $insertLink->execute([
                $catalogId,
                $sourceTable,
                $sourceRecordId,
                $sourceColumn,
                $sourceContext,
                $mediaType,
                $context['placement_key'] ?? null,
                $context['page_slug'] ?? null,
                $context['section_key'] ?? null,
                $context['entity_type'] ?? null,
                $context['entity_id'] ?? null,
                $context['use_case'] ?? null,
                (int)($context['display_order'] ?? 0),
            ]);
        }

        return true;
    } catch (Throwable $e) {
        error_log('upsertManagedMediaForSource failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch media mapping by source table/record keyed by source_column.
 */
function getManagedMediaMapForRecord(string $sourceTable, $sourceRecordId): array {
    global $pdo;

    if (!mediaTablesAvailable()) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT l.source_column, l.media_type, c.media_url, c.mime_type FROM managed_media_links l INNER JOIN managed_media_catalog c ON c.id = l.media_catalog_id WHERE l.source_table = ? AND l.source_record_id = ? AND l.is_active = 1 AND c.is_active = 1");
        $stmt->execute([$sourceTable, (string)$sourceRecordId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[$row['source_column']] = $row;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Apply managed media overrides onto a legacy record with fallback.
 */
function applyManagedMediaOverrides(array $record, string $sourceTable, $sourceRecordId, array $columns): array {
    $map = getManagedMediaMapForRecord($sourceTable, $sourceRecordId);
    if (empty($map)) {
        return $record;
    }

    foreach ($columns as $column) {
        if (!empty($map[$column]['media_url'])) {
            $record[$column] = $map[$column]['media_url'];
        }
    }

    return $record;
}

/**
 * Fetch first active managed media item for a group key.
 */
function getManagedMediaPrimary(string $group_key, ?string $media_type = null): ?array {
    $items = getManagedMediaItems($group_key, [
        'media_type' => $media_type,
        'limit' => 1,
    ]);
    return $items[0] ?? null;
}

/**
 * Helper function to get cached testimonials
 */
function getCachedTestimonials($limit = 3) {
    global $pdo;
    
    $cacheKey = "testimonials_{$limit}";
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM testimonials
            WHERE is_featured = 1 AND is_approved = 1
            ORDER BY display_order ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache for 30 minutes
        setCache($cacheKey, $testimonials, 1800);
        
        return $testimonials;
    } catch (PDOException $e) {
        error_log("Error fetching testimonials: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to get cached policies
 */
function getCachedPolicies() {
    global $pdo;
    
    $cacheKey = 'policies';
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT slug, title, summary, content 
            FROM policies 
            WHERE is_active = 1 
            ORDER BY display_order ASC, id ASC
        ");
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache for 1 hour
        setCache($cacheKey, $policies, 3600);
        
        return $policies;
    } catch (PDOException $e) {
        error_log("Error fetching policies: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper function to get cached About Us content
 */
function getCachedAboutUs() {
    global $pdo;
    
    $cacheKey = 'about_us';
    $cached = getCache($cacheKey, null);
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        // Get main about content
        $stmt = $pdo->prepare("SELECT * FROM about_us WHERE section_type = 'main' AND is_active = 1 ORDER BY display_order LIMIT 1");
        $stmt->execute();
        $about_content = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($about_content)) {
            $about_content = applyManagedMediaOverrides($about_content, 'about_us', $about_content['id'] ?? '', ['image_url']);
        }
        
        // Get features
        $stmt = $pdo->prepare("SELECT * FROM about_us WHERE section_type = 'feature' AND is_active = 1 ORDER BY display_order");
        $stmt->execute();
        $about_features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($about_features)) {
            foreach ($about_features as &$featureRow) {
                $featureRow = applyManagedMediaOverrides($featureRow, 'about_us', $featureRow['id'] ?? '', ['image_url']);
            }
            unset($featureRow);
        }
        
        // Get stats
        $stmt = $pdo->prepare("SELECT * FROM about_us WHERE section_type = 'stat' AND is_active = 1 ORDER BY display_order");
        $stmt->execute();
        $about_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($about_stats)) {
            foreach ($about_stats as &$statsRow) {
                $statsRow = applyManagedMediaOverrides($statsRow, 'about_us', $statsRow['id'] ?? '', ['image_url']);
            }
            unset($statsRow);
        }
        
        $result = [
            'content' => $about_content,
            'features' => $about_features,
            'stats' => $about_stats
        ];
        
        // Cache for 1 hour
        setCache($cacheKey, $result, 3600);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching about us content: " . $e->getMessage());
        return ['content' => null, 'features' => [], 'stats' => []];
    }
}

/**
 * Invalidate all data caches when content changes
 */
function invalidateDataCaches() {
    // Clear all data caches
    $patterns = [
        'rooms_*',
        'facilities_*',
        'gallery_images',
        'page_hero*',
        'testimonials_*',
        'policies',
        'about_us',
        'settings_group_*'
    ];
    
    foreach ($patterns as $pattern) {
        $files = glob(CACHE_DIR . '/' . md5(str_replace('*', '', $pattern)) . '*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}

/**
 * Helper: fetch active page hero by page slug.
 * Returns associative array or null.
 */
function getPageHero(string $page_slug): ?array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM page_heroes
            WHERE page_slug = ? AND is_active = 1
            ORDER BY display_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$page_slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // page_heroes is the canonical source-of-truth for hero media.
        // Avoid managed_media override precedence here so DB edits to page_heroes
        // are reflected immediately and predictably on the frontend.
        return $row ?: null;
    } catch (PDOException $e) {
        error_log("Error fetching page hero ({$page_slug}): " . $e->getMessage());
        return null;
    }
}

/**
 * Helper: fetch active page hero by exact page URL (e.g. /restaurant.php).
 * Returns associative array or null.
 */
function getPageHeroByUrl(string $page_url): ?array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM page_heroes
            WHERE page_url = ? AND is_active = 1
            ORDER BY display_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$page_url]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // page_heroes is the canonical source-of-truth for hero media.
        // Avoid managed_media override precedence here so DB edits to page_heroes
        // are reflected immediately and predictably on the frontend.
        return $row ?: null;
    } catch (PDOException $e) {
        error_log("Error fetching page hero by url ({$page_url}): " . $e->getMessage());
        return null;
    }
}

/**
 * Helper: get hero for the current request without hardcoding per-page slugs.
 * Strategy:
 *  1) Try exact match on page_url (SCRIPT_NAME).
 *  2) Fallback to slug derived from current filename (basename without .php).
 */
function getCurrentPageHero(): ?array {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script) {
        $byUrl = getPageHeroByUrl($script);
        if ($byUrl) return $byUrl;
    }

    $path = $_SERVER['SCRIPT_FILENAME'] ?? $script;
    if (!$path) return null;

    $slug = strtolower(pathinfo($path, PATHINFO_FILENAME));
    $slug = str_replace('_', '-', $slug);

    return getPageHero($slug);
}

/**
 * Helper: fetch active page loader subtext by page slug.
 * Returns the subtext string if found and active, null otherwise.
 */
function getPageLoader(string $page_slug): ?string {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT subtext
            FROM page_loaders
            WHERE page_slug = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$page_slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['subtext'] : null;
    } catch (PDOException $e) {
        error_log("Error fetching page loader ({$page_slug}): " . $e->getMessage());
        return null;
    }
}

/**
 * Booking Lifecycle Constants and Helper Functions
 *
 * These functions standardize booking status transitions and validation
 * across the entire application to ensure consistent behavior.
 */

/**
 * Get booking statuses that consume room inventory (block availability)
 *
 * For room type availability: includes 'pending' as they hold inventory
 * For individual room availability: only 'confirmed' and 'checked-in' as they have assigned rooms
 *
 * NOTE: 'tentative' bookings do NOT block availability (they can be overwritten)
 * NOTE: 'cancelled' bookings do NOT block availability (they free up the room)
 *
 * @param bool $forIndividualRoom If true, returns stricter list for individual rooms
 * @return array List of statuses that block availability
 */
function getBookingStatusesThatBlockAvailability(bool $forIndividualRoom = false): array {
    if ($forIndividualRoom) {
        // Individual rooms are only blocked by confirmed/checked-in bookings
        // (pending bookings don't have individual rooms assigned yet)
        return ['confirmed', 'checked-in'];
    }
    // Room type availability considers pending bookings as blocking
    // (they hold a room from the inventory pool)
    // Tentative bookings do NOT block - they can be overwritten by confirmed bookings
    // Cancelled bookings do NOT block - they free up the room
    return ['pending', 'confirmed', 'checked-in'];
}

/**
 * Get booking statuses that are considered "active" (not cancelled, expired, no-show)
 *
 * @return array List of active booking statuses
 */
function getActiveBookingStatuses(): array {
    return ['pending', 'tentative', 'confirmed', 'checked-in', 'checked-out'];
}

/**
 * Get booking statuses that are considered "terminal" (cannot transition to other states)
 *
 * @return array List of terminal booking statuses
 */
function getTerminalBookingStatuses(): array {
    return ['cancelled', 'expired', 'no-show', 'checked-out'];
}

/**
 * Validate if a booking status transition is allowed
 *
 * @param string $currentStatus Current booking status
 * @param string $newStatus Desired new status
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateBookingStatusTransition(string $currentStatus, string $newStatus): array {
    // Define valid transitions
    // NOTE: 'tentative' is NOT allowed from 'confirmed' - once confirmed or paid, cannot revert to tentative
    $validTransitions = [
        'pending' => ['tentative', 'confirmed', 'cancelled', 'expired'],
        'tentative' => ['confirmed', 'cancelled', 'expired'],
        'confirmed' => ['checked-in', 'cancelled', 'no-show'], // NO 'tentative' - confirmed bookings cannot revert
        'checked-in' => ['checked-out', 'confirmed'], // Can cancel check-in (revert to confirmed)
        'checked-out' => [], // Terminal state
        'cancelled' => [], // Terminal state
        'expired' => [], // Terminal state
        'no-show' => [], // Terminal state
    ];
    
    // Same status is always allowed (idempotent)
    if ($currentStatus === $newStatus) {
        return ['allowed' => true, 'reason' => ''];
    }
    
    // Check if transition is valid
    if (!isset($validTransitions[$currentStatus])) {
        return ['allowed' => false, 'reason' => "Unknown current status: {$currentStatus}"];
    }
    
    if (!in_array($newStatus, $validTransitions[$currentStatus], true)) {
        return ['allowed' => false, 'reason' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'"];
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Validate if a booking can be checked in
 *
 * @param array $booking Booking record (must include status, payment_status, individual_room_id, check_in_date)
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateCheckIn(array $booking): array {
    $requiredFields = ['status', 'payment_status', 'individual_room_id', 'check_in_date'];
    foreach ($requiredFields as $field) {
        if (!isset($booking[$field])) {
            return ['allowed' => false, 'reason' => "Missing required field: {$field}"];
        }
    }
    
    if ($booking['status'] !== 'confirmed') {
        return ['allowed' => false, 'reason' => "Booking must be CONFIRMED to check in (current: {$booking['status']})"];
    }
    
    if ($booking['payment_status'] !== 'paid') {
        return ['allowed' => false, 'reason' => "Booking must be PAID to check in (current: {$booking['payment_status']})"];
    }
    
    if (empty($booking['individual_room_id'])) {
        return ['allowed' => false, 'reason' => "A room must be assigned before check-in"];
    }
    
    // Date-based validation: check-in only allowed on or after check-in date
    $check_in_date = new DateTime($booking['check_in_date']);
    $check_in_date->setTime(0, 0, 0);
    $today = new DateTime('today');
    
    if ($check_in_date > $today) {
        return ['allowed' => false, 'reason' => "Check-in date has not been reached yet (check-in: {$booking['check_in_date']})"];
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Validate if a booking can be checked out
 *
 * @param array $booking Booking record (must include status, check_out_date)
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateCheckOut(array $booking): array {
    $requiredFields = ['status', 'check_out_date'];
    foreach ($requiredFields as $field) {
        if (!isset($booking[$field])) {
            return ['allowed' => false, 'reason' => "Missing required field: {$field}"];
        }
    }
    
    if ($booking['status'] !== 'checked-in') {
        return ['allowed' => false, 'reason' => "Booking must be CHECKED-IN to check out (current: {$booking['status']})"];
    }
    
    // Date-based validation: check-out only allowed on or after check-out date
    // (hotel policy may allow early checkout, but date must not be in the future beyond scheduled checkout)
    $check_out_date = new DateTime($booking['check_out_date']);
    $check_out_date->setTime(0, 0, 0);
    $today = new DateTime('today');
    
    // Allow checkout if today is on or after the check-in date (early checkout is OK)
    // but prevent checkout if check-out date is far in the future (more than 1 day ahead)
    // This allows same-day checkout and early checkout
    if ($check_out_date > $today->modify('+1 day')) {
        return ['allowed' => false, 'reason' => "Check-out date is too far in the future (scheduled: {$booking['check_out_date']})"];
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Validate if a room can be assigned to a booking
 *
 * @param array $booking Booking record (must include status)
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateRoomAssignment(array $booking): array {
    if (!isset($booking['status'])) {
        return ['allowed' => false, 'reason' => "Missing required field: status"];
    }
    
    if ($booking['status'] !== 'confirmed') {
        return ['allowed' => false, 'reason' => "Rooms can only be assigned to CONFIRMED bookings (current: {$booking['status']})"];
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Validate if a booking can be cancelled
 *
 * Cancellation is only allowed before guest checks in.
 * Once checked-in, booking cannot be cancelled (must use check-out instead).
 *
 * @param array $booking Booking record (must include status)
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateBookingCancellation(array $booking): array {
    if (!isset($booking['status'])) {
        return ['allowed' => false, 'reason' => "Missing required field: status"];
    }
    
    // Block cancellation for checked-in, checked-out, cancelled, no-show bookings
    $nonCancellableStatuses = ['checked-in', 'checked-out', 'cancelled', 'no-show'];
    if (in_array($booking['status'], $nonCancellableStatuses, true)) {
        if ($booking['status'] === 'checked-in') {
            return ['allowed' => false, 'reason' => "Cannot cancel booking: guest has already checked in (use check-out instead)"];
        }
        if ($booking['status'] === 'checked-out') {
            return ['allowed' => false, 'reason' => "Cannot cancel booking: guest has already checked out"];
        }
        if ($booking['status'] === 'cancelled') {
            return ['allowed' => false, 'reason' => "Booking is already cancelled"];
        }
        if ($booking['status'] === 'no-show') {
            return ['allowed' => false, 'reason' => "Cannot cancel booking: marked as no-show"];
        }
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Validate if a booking can be converted to tentative status
 *
 * Business rules:
 * - Only pending bookings can be made tentative (not confirmed, checked-in, checked-out, cancelled)
 * - Bookings with any payment (paid or partial) cannot be made tentative
 * - Once confirmed or paid, a booking cannot revert to tentative
 *
 * @param array $booking Booking record (must include status, payment_status)
 * @return array ['allowed' => bool, 'reason' => string]
 */
function validateTentativeTransition(array $booking): array {
    $requiredFields = ['status', 'payment_status'];
    foreach ($requiredFields as $field) {
        if (!isset($booking[$field])) {
            return ['allowed' => false, 'reason' => "Missing required field: {$field}"];
        }
    }
    
    // Only pending bookings can be made tentative
    if ($booking['status'] !== 'pending') {
        $statusMap = [
            'tentative' => 'Booking is already tentative',
            'confirmed' => 'Confirmed bookings cannot be made tentative',
            'checked-in' => 'Checked-in bookings cannot be made tentative',
            'checked-out' => 'Checked-out bookings cannot be made tentative',
            'cancelled' => 'Cancelled bookings cannot be made tentative',
            'no-show' => 'No-show bookings cannot be made tentative',
            'expired' => 'Expired bookings cannot be made tentative',
        ];
        return ['allowed' => false, 'reason' => $statusMap[$booking['status']] ?? "Cannot make booking tentative from current status: {$booking['status']}"];
    }
    
    // Block if any payment exists (paid or partial)
    if (in_array($booking['payment_status'], ['paid', 'partial'], true)) {
        return ['allowed' => false, 'reason' => "Bookings with payments cannot be made tentative (current payment status: {$booking['payment_status']})"];
    }
    
    return ['allowed' => true, 'reason' => ''];
}

/**
 * Get user-friendly error message for booking actions
 *
 * @param string $action Action being attempted
 * @param string $reason Technical reason from validation
 * @return string User-friendly error message
 */
function getBookingActionErrorMessage(string $action, string $reason): string {
    $messages = [
        'check_in' => [
            'status' => 'Cannot check in: Booking must be confirmed first.',
            'payment' => 'Cannot check in: Payment must be completed first.',
            'room' => 'Cannot check in: Please assign a room first.',
            'date' => 'Cannot check in: Check-in date has not been reached yet.',
        ],
        'check_out' => [
            'status' => 'Cannot check out: Guest must be checked in first.',
            'date' => 'Cannot check out: Check-out date is too far in the future.',
        ],
        'cancel' => [
            'status' => 'Cannot cancel booking: Invalid status for cancellation.',
            'checked_in' => 'Cannot cancel booking: Guest has already checked in. Use check-out instead.',
            'checked_out' => 'Cannot cancel booking: Guest has already checked out.',
            'cancelled' => 'Booking is already cancelled.',
            'noshow' => 'Cannot cancel booking: Marked as no-show.',
        ],
        'assign_room' => [
            'status' => 'Cannot assign room: Booking must be confirmed first.',
        ],
        'confirm' => [
            'availability' => 'Cannot confirm: No rooms available for the selected dates.',
        ],
        'make_tentative' => [
            'status' => 'Cannot make tentative: Booking must be in pending status.',
            'payment' => 'Cannot make tentative: Bookings with payments cannot be made tentative.',
            'confirmed' => 'Cannot make tentative: Confirmed bookings cannot revert to tentative status.',
        ],
    ];
    
    // Parse the reason to determine the error type
    if (strpos($reason, 'CONFIRMED') !== false || strpos($reason, 'confirmed') !== false) {
        return $messages[$action]['status'] ?? $reason;
    }
    if (strpos($reason, 'PAID') !== false || strpos($reason, 'paid') !== false) {
        return $messages[$action]['payment'] ?? $reason;
    }
    if (strpos($reason, 'room') !== false) {
        return $messages[$action]['room'] ?? $reason;
    }
    if (strpos($reason, 'available') !== false) {
        return $messages[$action]['availability'] ?? $reason;
    }
    
    // Default to the original reason if no specific mapping
    return $reason;
}

/**
 * Helper function to check room availability
 * Returns true if room is available, false if booked or blocked
 */
function isRoomAvailable($room_id, $check_in_date, $check_out_date, $exclude_booking_id = null) {
    global $pdo;
    try {
        // First check if there are any rooms available at all
        $room_stmt = $pdo->prepare("SELECT rooms_available, total_rooms FROM rooms WHERE id = ?");
        $room_stmt->execute([$room_id]);
        $room = $room_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room || $room['rooms_available'] <= 0) {
            return false; // No rooms available
        }
        
        // Check for blocked dates (both room-specific and global blocks)
        $blocked_sql = "
            SELECT COUNT(*) as blocked_dates
            FROM blocked_dates
            WHERE block_date >= ? AND block_date < ?
            AND (room_id = ? OR room_id IS NULL)
        ";
        $blocked_stmt = $pdo->prepare($blocked_sql);
        $blocked_stmt->execute([$check_in_date, $check_out_date, $room_id]);
        $blocked_result = $blocked_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blocked_result['blocked_dates'] > 0) {
            return false; // Date is blocked
        }
        
        // Then check for overlapping bookings (use standardized status list)
        $blockingStatuses = getBookingStatusesThatBlockAvailability(false);
        $placeholders = str_repeat('?,', count($blockingStatuses) - 1) . '?';
        
        $sql = "
            SELECT COUNT(*) as bookings
            FROM bookings
            WHERE room_id = ?
            AND status IN ({$placeholders})
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        ";
        $params = array_merge([$room_id], $blockingStatuses, [$check_in_date, $check_out_date]);
        
        // Exclude a specific booking (useful when updating existing bookings)
        if ($exclude_booking_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_booking_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if number of overlapping bookings is less than total capacity
        // Note: rooms_available is a counter of (total - confirmed), so comparing overlapping (which includes confirmed)
        // against rooms_available would double-count confirmed bookings. We must compare against total_rooms.
        $overlapping_bookings = $result['bookings'];
        $capacity = (int)($room['total_rooms'] ?? 1);
        
        return $overlapping_bookings < $capacity;
    } catch (PDOException $e) {
        error_log("Error checking room availability: " . $e->getMessage());
        return false; // Assume unavailable on error
    }
}

/**
 * Enhanced function to check room availability with detailed conflict information
 * Returns array with availability status and conflict details
 */
function checkRoomAvailability($room_id, $check_in_date, $check_out_date, $exclude_booking_id = null) {
    global $pdo;
    
    $result = [
        'available' => true,
        'conflicts' => [],
        'blocked_dates' => [],
        'room_exists' => false,
        'room' => null
    ];
    
    try {
        // Check if room exists and get details
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = 1");
        $stmt->execute([$room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $result['room_exists'] = false;
            $result['error'] = 'Room not found or inactive';
            return $result;
        }
        
        $result['room'] = $room;
        $result['room_exists'] = true;
        
        // Validate dates
        $check_in = new DateTime($check_in_date);
        $check_out = new DateTime($check_out_date);
        $today = new DateTime();
        
        if ($check_in < $today) {
            $result['available'] = false;
            $result['error'] = 'Check-in date cannot be in the past';
            return $result;
        }
        
        if ($check_out <= $check_in) {
            $result['available'] = false;
            $result['error'] = 'Check-out date must be after check-in date';
            return $result;
        }
        
        // Get total capacity
        $total_capacity = (int)($room['total_rooms'] ?? 1);
        
        // Sanity check for capacity
        if ($total_capacity <= 0) {
            $result['available'] = false;
            $result['error'] = 'No rooms of this type are currently available (capacity is 0)';
            return $result;
        }
        
        // Check for blocked dates at room-type level (both room-specific and global blocks)
        $blocked_sql = "
            SELECT
                bd.id,
                bd.room_id,
                bd.block_date,
                COALESCE(bd.block_type, 'manual') as block_type,
                bd.reason,
                'type' as block_scope,
                r.name as scope_name
            FROM blocked_dates bd
            LEFT JOIN rooms r ON bd.room_id = r.id
            WHERE bd.block_date >= ? AND bd.block_date < ?
            AND (bd.room_id = ? OR bd.room_id IS NULL)
            ORDER BY bd.block_date ASC
        ";
        $blocked_stmt = $pdo->prepare($blocked_sql);
        $blocked_stmt->execute([$check_in_date, $check_out_date, $room_id]);
        $type_blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($type_blocked_dates)) {
            $result['available'] = false;
            $result['blocked_dates'] = $type_blocked_dates;
            $result['block_scope'] = 'type';
            $result['error'] = 'Selected dates are not available for booking';
            
            // Build blocked dates message
            $blocked_details = [];
            foreach ($type_blocked_dates as $blocked) {
                $blocked_date = new DateTime($blocked['block_date']);
                $room_name = $blocked['room_id'] ? $blocked['scope_name'] : 'All rooms';
                $blocked_details[] = sprintf(
                    "%s on %s (%s)",
                    $room_name,
                    $blocked_date->format('M j, Y'),
                    $blocked['block_type']
                );
            }
            $result['blocked_message'] = implode('; ', $blocked_details);
            return $result;
        }
        
        // Check for overlapping bookings (use standardized status list)
        $blockingStatuses = getBookingStatusesThatBlockAvailability(false);
        $placeholders = str_repeat('?,', count($blockingStatuses) - 1) . '?';
        
        $sql = "
            SELECT
                id,
                booking_reference,
                check_in_date,
                check_out_date,
                status,
                guest_name
            FROM bookings
            WHERE room_id = ?
            AND status IN ({$placeholders})
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        ";
        $params = array_merge([$room_id], $blockingStatuses, [$check_in_date, $check_out_date]);
        
        // Exclude specific booking for updates
        if ($exclude_booking_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_booking_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check availability by counting overlapping bookings
        // The rooms_available field is a general inventory count, not specific to requested dates
        // So we need to count actual bookings for the requested dates
        $overlapping_bookings = count($conflicts);
        
        // Calculate remaining rooms for the requested dates
        $remaining_rooms = $total_capacity - $overlapping_bookings;
        
        // Room is unavailable if no rooms remain for the requested dates
        if ($remaining_rooms <= 0) {
            $result['available'] = false;
            $result['conflicts'] = $conflicts;
            $result['error'] = 'Room is not available for the selected dates';
            
            // Build detailed conflict message
            $conflict_details = [];
            foreach ($conflicts as $conflict) {
                $conflict_check_in = new DateTime($conflict['check_in_date']);
                $conflict_check_out = new DateTime($conflict['check_out_date']);
                $conflict_details[] = sprintf(
                    "Booking %s (%s) from %s to %s",
                    $conflict['booking_reference'],
                    $conflict['guest_name'],
                    $conflict_check_in->format('M j, Y'),
                    $conflict_check_out->format('M j, Y')
                );
            }
            $result['conflict_message'] = implode('; ', $conflict_details);
        }
        
        // Calculate number of nights
        $interval = $check_in->diff($check_out);
        $result['nights'] = $interval->days;
        
        // Check if room has enough capacity for requested dates
        $max_guests = (int)$room['max_guests'];
        if ($max_guests > 0) {
            $result['max_guests'] = $max_guests;
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error checking room availability: " . $e->getMessage());
        $result['available'] = false;
        $result['error'] = 'Database error while checking availability';
        return $result;
    } catch (Exception $e) {
        error_log("Error checking room availability: " . $e->getMessage());
        $result['available'] = false;
        $result['error'] = 'Invalid date format';
        return $result;
    }
}

/**
 * Get all booked dates for a room within a date range.
 * Returns dates that are fully booked (no remaining capacity).
 * Uses the same blocking status logic as checkRoomAvailability.
 *
 * @param int $room_id Room ID
 * @param string $start_date Start date (Y-m-d format)
 * @param string $end_date End date (Y-m-d format)
 * @return array Array of booked dates in Y-m-d format
 */
function getBookedDatesForRoom(int $room_id, string $start_date, string $end_date): array {
    global $pdo;
    $bookedDates = [];
    
    try {
        // Get room details to check capacity
        $stmt = $pdo->prepare("SELECT total_rooms FROM rooms WHERE id = ? AND is_active = 1");
        $stmt->execute([$room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            return [];
        }
        
        $total_capacity = (int)($room['total_rooms'] ?? 1);
        
        if ($total_capacity <= 0) {
            // No capacity - all dates are booked
            $current = new DateTime($start_date);
            $end = new DateTime($end_date);
            while ($current < $end) {
                $bookedDates[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }
            return $bookedDates;
        }
        
        // Get blocking statuses (excludes 'tentative' and 'cancelled')
        $blockingStatuses = getBookingStatusesThatBlockAvailability(false);
        $placeholders = str_repeat('?,', count($blockingStatuses) - 1) . '?';
        
        // Get all overlapping bookings for the date range
        $sql = "
            SELECT
                check_in_date,
                check_out_date
            FROM bookings
            WHERE room_id = ?
            AND status IN ({$placeholders})
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
            ORDER BY check_in_date ASC
        ";
        $params = array_merge([$room_id], $blockingStatuses, [$start_date, $end_date]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each date in the range, count overlapping bookings
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current < $end) {
            $dateStr = $current->format('Y-m-d');
            $nextDay = clone $current;
            $nextDay->modify('+1 day');
            
            // Count bookings that overlap with this date
            $overlappingCount = 0;
            foreach ($bookings as $booking) {
                $bookingStart = new DateTime($booking['check_in_date']);
                $bookingEnd = new DateTime($booking['check_out_date']);
                
                // Check if the date falls within the booking range
                // A date is booked if: date >= check_in AND date < check_out
                if ($current >= $bookingStart && $current < $bookingEnd) {
                    $overlappingCount++;
                }
            }
            
            // If overlapping bookings >= capacity, the date is fully booked
            if ($overlappingCount >= $total_capacity) {
                $bookedDates[] = $dateStr;
            }
            
            $current->modify('+1 day');
        }
        
        return $bookedDates;
        
    } catch (PDOException $e) {
        error_log("Error getting booked dates for room: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("Error getting booked dates for room: " . $e->getMessage());
        return [];
    }
}

/**
 * Function to validate booking data before insertion/update
 * Returns array with validation status and error messages
 */
function validateBookingData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['room_id', 'guest_name', 'guest_email', 'guest_phone', 'check_in_date', 'check_out_date', 'number_of_guests'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Email validation
    if (!empty($data['guest_email'])) {
        if (!filter_var($data['guest_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['guest_email'] = 'Invalid email address';
        }
    }
    
    // Phone number validation (basic)
    if (!empty($data['guest_phone'])) {
        $phone = preg_replace('/[^0-9+]/', '', $data['guest_phone']);
        if (strlen($phone) < 8) {
            $errors['guest_phone'] = 'Phone number is too short';
        }
    }
    
    // Number of guests validation
    if (!empty($data['number_of_guests'])) {
        $guests = (int)$data['number_of_guests'];
        if ($guests < 1) {
            $errors['number_of_guests'] = 'At least 1 guest is required';
        } elseif ($guests > 20) {
            $errors['number_of_guests'] = 'Maximum 20 guests allowed';
        }
    }
    
    // Date validation
    if (!empty($data['check_in_date']) && !empty($data['check_out_date'])) {
        try {
            $check_in = new DateTime($data['check_in_date']);
            $check_out = new DateTime($data['check_out_date']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($check_in < $today) {
                $errors['check_in_date'] = 'Check-in date cannot be in the past';
            }
            
            if ($check_out <= $check_in) {
                $errors['check_out_date'] = 'Check-out date must be after check-in date';
            }
            
    // Maximum stay duration (30 days)
    $max_stay = new DateTime();
    $max_stay->modify('+30 days');
    if ($check_out > $max_stay) {
        $errors['check_out_date'] = 'Maximum stay duration is 30 days';
    }
    
    // Maximum advance booking days (configurable setting)
    $max_advance_days = (int)getSetting('max_advance_booking_days', 30);
    $max_advance_date = new DateTime();
    $max_advance_date->modify('+' . $max_advance_days . ' days');
    if ($check_in > $max_advance_date) {
        $errors['check_in_date'] = "Bookings can only be made up to {$max_advance_days} days in advance. Please select an earlier check-in date.";
    }
    
        } catch (Exception $e) {
            $errors['dates'] = 'Invalid date format';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Function to validate booking with room availability check
 * Combines data validation and availability checking
 */
function validateBookingWithAvailability($data, $exclude_booking_id = null) {
    // First validate data
    $validation = validateBookingData($data);
    if (!$validation['valid']) {
        return [
            'valid' => false,
            'errors' => $validation['errors'],
            'type' => 'validation'
        ];
    }

    $availability = null;

    // If individual room requested, validate against individual room availability
    if (!empty($data['individual_room_id'])) {
        $availability = checkIndividualRoomAvailability(
            $data['individual_room_id'],
            $data['check_in_date'],
            $data['check_out_date'],
            $exclude_booking_id
        );

        if (!$availability['available']) {
            return [
                'valid' => false,
                'errors' => [
                    'availability' => $availability['error'],
                    'conflicts' => $availability['conflict_message'] ?? 'No specific conflicts found'
                ],
                'type' => 'availability',
                'conflicts' => $availability['conflicts'] ?? []
            ];
        }

        if (!empty($availability['room_type_id']) && (int)$availability['room_type_id'] !== (int)$data['room_id']) {
            return [
                'valid' => false,
                'errors' => [
                    'individual_room_id' => 'Selected room does not match the chosen room type.'
                ],
                'type' => 'validation'
            ];
        }
    } else {
        // Then check room availability
        $availability = checkRoomAvailability(
            $data['room_id'],
            $data['check_in_date'],
            $data['check_out_date'],
            $exclude_booking_id
        );
    }

    if (!$availability['available']) {
        return [
            'valid' => false,
            'errors' => [
                'availability' => $availability['error'],
                'conflicts' => $availability['conflict_message'] ?? 'No specific conflicts found'
            ],
            'type' => 'availability',
            'conflicts' => $availability['conflicts'] ?? []
        ];
    }

    // Check if number of guests exceeds room capacity
    if (isset($availability['max_guests']) && isset($data['number_of_guests'])) {
        if ((int)$data['number_of_guests'] > (int)$availability['max_guests']) {
            return [
                'valid' => false,
                'errors' => [
                    'number_of_guests' => "Room capacity is {$availability['max_guests']} guests"
                ],
                'type' => 'capacity'
            ];
        }
    }

    return [
        'valid' => true,
        'availability' => $availability
    ];
}

/**
 * Get blocked dates for a specific room type, individual room, or all
 * Supports dual-layer blocking: room-type level and individual-room level
 * Returns array of blocked date records with scope indicator
 *
 * @param int|null $room_id Room type ID (rooms table)
 * @param int|null $individual_room_id Individual room ID (individual_rooms table)
 * @param string|null $start_date Filter by start date
 * @param string|null $end_date Filter by end date
 * @return array Array of blocked date records
 */
function getBlockedDates($room_id = null, $start_date = null, $end_date = null, $individual_room_id = null) {
    global $pdo;
    
    try {
        $blocked_dates = [];
        
        // Get room-type level blocks
        $sql = "
            SELECT
                bd.id,
                bd.room_id,
                NULL as individual_room_id,
                r.name as room_name,
                NULL as individual_room_number,
                bd.block_date,
                COALESCE(bd.block_type, 'manual') as block_type,
                bd.reason,
                bd.blocked_by as created_by,
                au.username as created_by_name,
                bd.created_at,
                'type' as block_scope
            FROM blocked_dates bd
            LEFT JOIN rooms r ON bd.room_id = r.id
            LEFT JOIN admin_users au ON bd.blocked_by = au.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($room_id !== null) {
            $sql .= " AND (bd.room_id = ? OR bd.room_id IS NULL)";
            $params[] = $room_id;
        }
        
        if ($start_date !== null) {
            $sql .= " AND bd.block_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date !== null) {
            $sql .= " AND bd.block_date <= ?";
            $params[] = $end_date;
        }
        
        $sql .= " ORDER BY bd.block_date ASC, bd.room_id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blocked_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get individual-room level blocks if requested or if no room_id filter
        if ($individual_room_id !== null || $room_id === null) {
            $ir_sql = "
                SELECT
                    irbd.id,
                    NULL as room_id,
                    irbd.individual_room_id,
                    rt.name as room_name,
                    ir.room_number as individual_room_number,
                    irbd.block_date,
                    irbd.block_type,
                    irbd.reason,
                    irbd.blocked_by as created_by,
                    au.username as created_by_name,
                    irbd.created_at,
                    'individual' as block_scope
                FROM individual_room_blocked_dates irbd
                INNER JOIN individual_rooms ir ON irbd.individual_room_id = ir.id
                INNER JOIN rooms rt ON ir.room_type_id = rt.id
                LEFT JOIN admin_users au ON irbd.blocked_by = au.id
                WHERE 1=1
            ";
            $ir_params = [];
            
            if ($individual_room_id !== null) {
                $ir_sql .= " AND irbd.individual_room_id = ?";
                $ir_params[] = $individual_room_id;
            }
            
            if ($room_id !== null) {
                $ir_sql .= " AND ir.room_type_id = ?";
                $ir_params[] = $room_id;
            }
            
            if ($start_date !== null) {
                $ir_sql .= " AND irbd.block_date >= ?";
                $ir_params[] = $start_date;
            }
            
            if ($end_date !== null) {
                $ir_sql .= " AND irbd.block_date <= ?";
                $ir_params[] = $end_date;
            }
            
            $ir_sql .= " ORDER BY irbd.block_date ASC, irbd.individual_room_id ASC";
            
            $ir_stmt = $pdo->prepare($ir_sql);
            $ir_stmt->execute($ir_params);
            $individual_blocked_dates = $ir_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge both types of blocks
            $blocked_dates = array_merge($blocked_dates, $individual_blocked_dates);
        }
        
        // Sort combined results by date
        usort($blocked_dates, function($a, $b) {
            return strcmp($a['block_date'], $b['block_date']);
        });
        
        return $blocked_dates;
    } catch (PDOException $e) {
        error_log("Error fetching blocked dates: " . $e->getMessage());
        return [];
    }
}

/**
 * Get blocked dates specifically for an individual room
 * Returns array of blocked date records for the individual room
 *
 * @param int $individual_room_id Individual room ID
 * @param string|null $start_date Filter by start date
 * @param string|null $end_date Filter by end date
 * @return array Array of blocked date records
 */
function getIndividualRoomBlockedDates($individual_room_id, $start_date = null, $end_date = null) {
    return getBlockedDates(null, $start_date, $end_date, $individual_room_id);
}

/**
 * Check if a specific individual room is blocked on a given date
 *
 * @param int $individual_room_id Individual room ID
 * @param string $date Date to check (Y-m-d format)
 * @return bool True if blocked, false otherwise
 */
function isIndividualRoomBlocked($individual_room_id, $date) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM individual_room_blocked_dates
            WHERE individual_room_id = ? AND block_date = ?
        ");
        $stmt->execute([$individual_room_id, $date]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking individual room block: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available dates for a specific room within a date range
 * Returns array of available dates
 */
function getAvailableDates($room_id, $start_date, $end_date) {
    global $pdo;
    
    try {
        $available_dates = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        // Get room details
        $stmt = $pdo->prepare("SELECT rooms_available FROM rooms WHERE id = ?");
        $stmt->execute([$room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room || $room['rooms_available'] <= 0) {
            return [];
        }
        
        $rooms_available = $room['rooms_available'];
        
        // Get blocked dates
        $blocked_sql = "
            SELECT block_date
            FROM blocked_dates
            WHERE block_date >= ? AND block_date <= ?
            AND (room_id = ? OR room_id IS NULL)
        ";
        $blocked_stmt = $pdo->prepare($blocked_sql);
        $blocked_stmt->execute([$start_date, $end_date, $room_id]);
        $blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get booked dates
        $booked_sql = "
            SELECT DISTINCT DATE(check_in_date) as date
            FROM bookings
            WHERE room_id = ?
            AND status IN ('pending', 'confirmed', 'checked-in')
            AND check_in_date <= ?
            AND check_out_date > ?
        ";
        $booked_stmt = $pdo->prepare($booked_sql);
        $booked_stmt->execute([$room_id, $end_date, $start_date]);
        $booked_dates = $booked_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Count bookings per date
        $booking_counts = [];
        foreach ($booked_dates as $date) {
            if (!isset($booking_counts[$date])) {
                $booking_counts[$date] = 0;
            }
            $booking_counts[$date]++;
        }
        
        // Build available dates array
        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            
            // Check if date is blocked
            if (in_array($date_str, $blocked_dates)) {
                $current->modify('+1 day');
                continue;
            }
            
            // Check if date has available rooms
            $bookings_on_date = isset($booking_counts[$date_str]) ? $booking_counts[$date_str] : 0;
            
            if ($bookings_on_date < $rooms_available) {
                $available_dates[] = [
                    'date' => $date_str,
                    'available' => true,
                    'rooms_left' => $rooms_available - $bookings_on_date
                ];
            }
            
            $current->modify('+1 day');
        }
        
        return $available_dates;
    } catch (PDOException $e) {
        error_log("Error fetching available dates: " . $e->getMessage());
        return [];
    }
}

/**
 * Block a specific date for a room type or all rooms
 * Returns true on success, false on failure
 *
 * @param int|null $room_id Room type ID (null for all rooms)
 * @param string $block_date Date to block (Y-m-d format)
 * @param string $block_type Type of block (manual, maintenance, event, full)
 * @param string|null $reason Optional reason for the block
 * @param int|null $created_by Admin user ID who created the block
 * @return bool True on success, false on failure
 */
function blockRoomDate($room_id, $block_date, $block_type = 'manual', $reason = null, $created_by = null) {
    global $pdo;
    
    try {
        // Check if date is already blocked
        $check_sql = "
            SELECT id FROM blocked_dates
            WHERE room_id " . ($room_id === null ? "IS NULL" : "= ?") . "
            AND block_date = ?
        ";
        $check_params = $room_id === null ? [$block_date] : [$room_id, $block_date];
        
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute($check_params);
        
        if ($check_stmt->fetch()) {
            // Date already blocked, update instead
            $update_sql = "
                UPDATE blocked_dates
                SET block_type = ?, reason = ?, blocked_by = ?
                WHERE room_id " . ($room_id === null ? "IS NULL" : "= ?") . "
                AND block_date = ?
            ";
            $update_params = [$block_type, $reason, $created_by];
            if ($room_id !== null) {
                $update_params[] = $room_id;
            }
            $update_params[] = $block_date;
            
            $update_stmt = $pdo->prepare($update_sql);
            return $update_stmt->execute($update_params);
        }
        
        // Insert new blocked date
        $sql = "
            INSERT INTO blocked_dates (room_id, block_date, block_type, reason, blocked_by)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$room_id, $block_date, $block_type, $reason, $created_by]);
    } catch (PDOException $e) {
        error_log("Error blocking room date: " . $e->getMessage());
        return false;
    }
}

/**
 * Unblock a specific date for a room type or all rooms
 * Returns true on success, false on failure
 *
 * @param int|null $room_id Room type ID (null for all rooms)
 * @param string $block_date Date to unblock (Y-m-d format)
 * @return bool True on success, false on failure
 */
function unblockRoomDate($room_id, $block_date) {
    global $pdo;
    
    try {
        $sql = "
            DELETE FROM blocked_dates
            WHERE room_id " . ($room_id === null ? "IS NULL" : "= ?") . "
            AND block_date = ?
        ";
        $params = $room_id === null ? [$block_date] : [$room_id, $block_date];
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error unblocking room date: " . $e->getMessage());
        return false;
    }
}

/**
 * Block multiple dates for a room type or all rooms
 * Returns number of dates blocked
 *
 * @param int|null $room_id Room type ID (null for all rooms)
 * @param array $dates Array of dates to block (Y-m-d format)
 * @param string $block_type Type of block (manual, maintenance, event, full)
 * @param string|null $reason Optional reason for the blocks
 * @param int|null $created_by Admin user ID who created the blocks
 * @return int Number of dates successfully blocked
 */
function blockRoomDates($room_id, $dates, $block_type = 'manual', $reason = null, $created_by = null) {
    $blocked_count = 0;
    
    foreach ($dates as $date) {
        if (blockRoomDate($room_id, $date, $block_type, $reason, $created_by)) {
            $blocked_count++;
        }
    }
    
    return $blocked_count;
}

/**
 * Unblock multiple dates for a room type or all rooms
 * Returns number of dates unblocked
 *
 * @param int|null $room_id Room type ID (null for all rooms)
 * @param array $dates Array of dates to unblock (Y-m-d format)
 * @return int Number of dates successfully unblocked
 */
function unblockRoomDates($room_id, $dates) {
    $unblocked_count = 0;
    
    foreach ($dates as $date) {
        if (unblockRoomDate($room_id, $date)) {
            $unblocked_count++;
        }
    }
    
    return $unblocked_count;
}

/**
 * Block a specific date for an individual room
 * Returns true on success, false on failure
 *
 * @param int $individual_room_id Individual room ID
 * @param string $block_date Date to block (Y-m-d format)
 * @param string $block_type Type of block (manual, maintenance, event, full)
 * @param string|null $reason Optional reason for the block
 * @param int|null $created_by Admin user ID who created the block
 * @return bool True on success, false on failure
 */
function blockIndividualRoomDate($individual_room_id, $block_date, $block_type = 'manual', $reason = null, $created_by = null) {
    global $pdo;
    
    try {
        // Check if date is already blocked
        $check_sql = "
            SELECT id FROM individual_room_blocked_dates
            WHERE individual_room_id = ? AND block_date = ?
        ";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$individual_room_id, $block_date]);
        
        if ($check_stmt->fetch()) {
            // Date already blocked, update instead
            $update_sql = "
                UPDATE individual_room_blocked_dates
                SET block_type = ?, reason = ?, blocked_by = ?
                WHERE individual_room_id = ? AND block_date = ?
            ";
            $update_stmt = $pdo->prepare($update_sql);
            return $update_stmt->execute([$block_type, $reason, $created_by, $individual_room_id, $block_date]);
        }
        
        // Insert new blocked date
        $sql = "
            INSERT INTO individual_room_blocked_dates (individual_room_id, block_date, block_type, reason, blocked_by)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$individual_room_id, $block_date, $block_type, $reason, $created_by]);
    } catch (PDOException $e) {
        error_log("Error blocking individual room date: " . $e->getMessage());
        return false;
    }
}

/**
 * Unblock a specific date for an individual room
 * Returns true on success, false on failure
 *
 * @param int $individual_room_id Individual room ID
 * @param string $block_date Date to unblock (Y-m-d format)
 * @return bool True on success, false on failure
 */
function unblockIndividualRoomDate($individual_room_id, $block_date) {
    global $pdo;
    
    try {
        $sql = "
            DELETE FROM individual_room_blocked_dates
            WHERE individual_room_id = ? AND block_date = ?
        ";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$individual_room_id, $block_date]);
    } catch (PDOException $e) {
        error_log("Error unblocking individual room date: " . $e->getMessage());
        return false;
    }
}

/**
 * Block multiple dates for an individual room
 * Returns number of dates blocked
 *
 * @param int $individual_room_id Individual room ID
 * @param array $dates Array of dates to block (Y-m-d format)
 * @param string $block_type Type of block (manual, maintenance, event, full)
 * @param string|null $reason Optional reason for the blocks
 * @param int|null $created_by Admin user ID who created the blocks
 * @return int Number of dates successfully blocked
 */
function blockIndividualRoomDates($individual_room_id, $dates, $block_type = 'manual', $reason = null, $created_by = null) {
    $blocked_count = 0;
    
    foreach ($dates as $date) {
        if (blockIndividualRoomDate($individual_room_id, $date, $block_type, $reason, $created_by)) {
            $blocked_count++;
        }
    }
    
    return $blocked_count;
}

/**
 * Unblock multiple dates for an individual room
 * Returns number of dates unblocked
 *
 * @param int $individual_room_id Individual room ID
 * @param array $dates Array of dates to unblock (Y-m-d format)
 * @return int Number of dates successfully unblocked
 */
function unblockIndividualRoomDates($individual_room_id, $dates) {
    $unblocked_count = 0;
    
    foreach ($dates as $date) {
        if (unblockIndividualRoomDate($individual_room_id, $date)) {
            $unblocked_count++;
        }
    }
    
    return $unblocked_count;
}

/**
 * ============================================
 * TENTATIVE BOOKING SYSTEM HELPER FUNCTIONS
 * ============================================
 */

/**
 * Convert a tentative booking to a standard booking
 * Returns true on success, false on failure
 */
function convertTentativeBooking($booking_id, $admin_user_id = null) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'tentative'");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $pdo->rollBack();
            return false;
        }
        
        // Update booking status to pending
        $update_stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'pending',
                is_tentative = 0,
                tentative_expires_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $update_stmt->execute([$booking_id]);
        
        // Log the action
        logTentativeBookingAction($booking_id, 'converted', [
            'converted_by' => $admin_user_id,
            'previous_status' => 'tentative',
            'new_status' => 'pending',
            'previous_is_tentative' => 1,
            'new_is_tentative' => 0
        ]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error converting tentative booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Cancel a tentative booking
 * Returns true on success, false on failure
 */
function cancelTentativeBooking($booking_id, $admin_user_id = null, $reason = null) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'tentative'");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $pdo->rollBack();
            return false;
        }
        
        // Update booking status to cancelled
        $update_stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $update_stmt->execute([$booking_id]);
        
        // Log the action
        logTentativeBookingAction($booking_id, 'cancelled', [
            'cancelled_by' => $admin_user_id,
            'previous_status' => 'tentative',
            'new_status' => 'cancelled',
            'reason' => $reason
        ]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error cancelling tentative booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Get tentative bookings with optional filters
 * Returns array of tentative bookings
 */
function getTentativeBookings($filters = []) {
    global $pdo;
    
    try {
        $sql = "
            SELECT
                b.*,
                r.name as room_name,
                r.price_per_night,
                au.username as admin_username
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            LEFT JOIN admin_users au ON b.updated_by = au.id
            WHERE b.is_tentative = 1
        ";
        $params = [];
        
        // Filter by status
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }
        
        // Filter by room
        if (!empty($filters['room_id'])) {
            $sql .= " AND b.room_id = ?";
            $params[] = $filters['room_id'];
        }
        
        // Filter by expiration status
        if (!empty($filters['expiration_status'])) {
            $now = date('Y-m-d H:i:s');
            if ($filters['expiration_status'] === 'expired') {
                $sql .= " AND b.tentative_expires_at < ?";
                $params[] = $now;
            } elseif ($filters['expiration_status'] === 'active') {
                $sql .= " AND b.tentative_expires_at >= ?";
                $params[] = $now;
            }
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $sql .= " AND b.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND b.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Search by guest name or email
        if (!empty($filters['search'])) {
            $sql .= " AND (b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.booking_reference LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        // Limit results
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $bookings;
        
    } catch (PDOException $e) {
        error_log("Error fetching tentative bookings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get bookings expiring within X hours
 * Returns array of bookings expiring soon
 */
function getExpiringTentativeBookings($hours = 24) {
    global $pdo;
    
    try {
        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        
        $stmt = $pdo->prepare("
            SELECT
                b.*,
                r.name as room_name,
                TIMESTAMPDIFF(HOUR, NOW(), b.tentative_expires_at) as hours_until_expiration
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.is_tentative = 1
            AND b.status = 'tentative'
            AND b.tentative_expires_at >= ?
            AND b.tentative_expires_at <= ?
            ORDER BY b.tentative_expires_at ASC
        ");
        $stmt->execute([$now, $cutoff]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $bookings;
        
    } catch (PDOException $e) {
        error_log("Error fetching expiring tentative bookings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get expired tentative bookings
 * Returns array of expired bookings
 */
function getExpiredTentativeBookings() {
    global $pdo;
    
    try {
        $now = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("
            SELECT
                b.*,
                r.name as room_name,
                TIMESTAMPDIFF(HOUR, b.tentative_expires_at, NOW()) as hours_since_expiration
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.is_tentative = 1
            AND b.status = 'tentative'
            AND b.tentative_expires_at < ?
            ORDER BY b.tentative_expires_at ASC
        ");
        $stmt->execute([$now]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $bookings;
        
    } catch (PDOException $e) {
        error_log("Error fetching expired tentative bookings: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark a tentative booking as expired
 * Returns true on success, false on failure
 */
function markTentativeBookingExpired($booking_id) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'tentative'");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $pdo->rollBack();
            return false;
        }
        
        // Update booking status to expired
        $update_stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'expired',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $update_stmt->execute([$booking_id]);
        
        // Log the action
        logTentativeBookingAction($booking_id, 'expired', [
            'previous_status' => 'tentative',
            'new_status' => 'expired',
            'expired_at' => date('Y-m-d H:i:s')
        ]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error marking tentative booking as expired: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an action for a tentative booking
 * Returns true on success, false on failure
 */
function logTentativeBookingAction($booking_id, $action, $details = []) {
    global $pdo;
    
    try {
        // Check if tentative_booking_log table exists
        $table_exists = $pdo->query("SHOW TABLES LIKE 'tentative_booking_log'")->rowCount() > 0;
        
        if (!$table_exists) {
            // Table doesn't exist, skip logging
            return true;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tentative_booking_log (booking_id, action, details, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$booking_id, $action, json_encode($details)]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error logging tentative booking action: " . $e->getMessage());
        return false;
    }
}

/**
 * Get tentative booking statistics
 * Returns array with statistics
 */
function getTentativeBookingStatistics() {
    global $pdo;
    
    try {
        $now = date('Y-m-d H:i:s');
        $reminder_cutoff = date('Y-m-d H:i:s', strtotime("+24 hours"));
        
        // Get total tentative bookings
        $stmt = $pdo->query("
            SELECT COUNT(*) as total
            FROM bookings
            WHERE is_tentative = 1
            AND status = 'tentative'
        ");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get expiring soon (within 24 hours)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as expiring_soon
            FROM bookings
            WHERE is_tentative = 1
            AND status = 'tentative'
            AND tentative_expires_at >= ?
            AND tentative_expires_at <= ?
        ");
        $stmt->execute([$now, $reminder_cutoff]);
        $expiring_soon = $stmt->fetch(PDO::FETCH_ASSOC)['expiring_soon'];
        
        // Get expired
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as expired
            FROM bookings
            WHERE is_tentative = 1
            AND status = 'tentative'
            AND tentative_expires_at < ?
        ");
        $stmt->execute([$now]);
        $expired = $stmt->fetch(PDO::FETCH_ASSOC)['expired'];
        
        // Get converted (standard bookings that were tentative)
        $stmt = $pdo->query("
            SELECT COUNT(*) as converted
            FROM bookings
            WHERE is_tentative = 0
            AND status IN ('pending', 'confirmed', 'checked-in', 'checked-out')
            AND tentative_expires_at IS NOT NULL
        ");
        $converted = $stmt->fetch(PDO::FETCH_ASSOC)['converted'];
        
        return [
            'total' => (int)$total,
            'expiring_soon' => (int)$expiring_soon,
            'expired' => (int)$expired,
            'converted' => (int)$converted,
            'active' => (int)($total - $expired)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching tentative booking statistics: " . $e->getMessage());
        return [
            'total' => 0,
            'expiring_soon' => 0,
            'expired' => 0,
            'converted' => 0,
            'active' => 0
        ];
    }
}

/**
 * Check if a booking can be converted (is tentative and not expired)
 * Returns array with status and message
 */
function canConvertTentativeBooking($booking_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return [
                'can_convert' => false,
                'reason' => 'Booking not found'
            ];
        }
        
        if ($booking['is_tentative'] != 1) {
            return [
                'can_convert' => false,
                'reason' => 'This is not a tentative booking'
            ];
        }
        
        if ($booking['status'] === 'expired') {
            return [
                'can_convert' => false,
                'reason' => 'This booking has expired'
            ];
        }
        
        if ($booking['status'] === 'cancelled') {
            return [
                'can_convert' => false,
                'reason' => 'This booking has been cancelled'
            ];
        }
        
        if ($booking['status'] !== 'tentative') {
            return [
                'can_convert' => false,
                'reason' => 'Booking has already been converted'
            ];
        }
        
        // Check if expired
        if ($booking['tentative_expires_at'] && $booking['tentative_expires_at'] < date('Y-m-d H:i:s')) {
            return [
                'can_convert' => false,
                'reason' => 'This booking has expired'
            ];
        }
        
        return [
            'can_convert' => true,
            'expires_at' => $booking['tentative_expires_at']
        ];
        
    } catch (PDOException $e) {
        error_log("Error checking if booking can be converted: " . $e->getMessage());
        return [
            'can_convert' => false,
            'reason' => 'Database error'
        ];
    }
}

/**
 * ============================================================================
 * INDIVIDUAL ROOM MANAGEMENT FUNCTIONS
 * ============================================================================
 */

/**
 * Get available individual rooms for a room type and date range
 *
 * @param int $roomTypeId Room type ID
 * @param string $checkIn Check-in date (YYYY-MM-DD)
 * @param string $checkOut Check-out date (YYYY-MM-DD)
 * @param int $excludeBookingId Optional booking ID to exclude from conflicts
 * @return array Available individual rooms
 */
function getAvailableIndividualRooms($roomTypeId, $checkIn, $checkOut, $excludeBookingId = null) {
    global $pdo;
    
    try {
        // Get all active individual rooms for this type
        $sql = "
            SELECT
                ir.id,
                ir.room_number,
                ir.room_name,
                ir.floor,
                ir.status,
                ir.specific_amenities
            FROM individual_rooms ir
            WHERE ir.room_type_id = ?
            AND ir.is_active = 1
            AND ir.status IN ('available', 'cleaning')
            ORDER BY ir.display_order ASC, ir.room_number ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomTypeId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $availableRooms = [];
        
        foreach ($rooms as $room) {
            // Check for booking conflicts (use standardized status list for individual rooms)
                $blockingStatuses = getBookingStatusesThatBlockAvailability(true);
                $placeholders = str_repeat('?,', count($blockingStatuses) - 1) . '?';
                
                $conflictSql = "
                    SELECT COUNT(*) as count
                    FROM bookings b
                    WHERE b.individual_room_id = ?
                    AND b.status IN ({$placeholders})
                    AND NOT (b.check_out_date <= ? OR b.check_in_date >= ?)
                ";
            
            $params = array_merge([$room['id']], $blockingStatuses, [$checkIn, $checkOut]);
            
            if ($excludeBookingId) {
                $conflictSql .= " AND b.id != ?";
                $params[] = $excludeBookingId;
            }
            
            $conflictStmt = $pdo->prepare($conflictSql);
            $conflictStmt->execute($params);
            $hasConflict = $conflictStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$hasConflict) {
                $availableRooms[] = [
                    'id' => $room['id'],
                    'room_number' => $room['room_number'],
                    'room_name' => $room['room_name'],
                    'floor' => $room['floor'],
                    'status' => $room['status'],
                    'specific_amenities' => $room['specific_amenities'] ? json_decode($room['specific_amenities'], true) : []
                ];
            }
        }
        
        return $availableRooms;
        
    } catch (PDOException $e) {
        error_log("Error getting available individual rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if an individual room is available for specific dates
 *
 * @param int $individualRoomId Individual room ID
 * @param string $checkIn Check-in date (YYYY-MM-DD)
 * @param string $checkOut Check-out date (YYYY-MM-DD)
 * @param int $excludeBookingId Optional booking ID to exclude
 * @return bool True if available, false otherwise
 */
function isIndividualRoomAvailable($individualRoomId, $checkIn, $checkOut, $excludeBookingId = null) {
    $availability = checkIndividualRoomAvailability($individualRoomId, $checkIn, $checkOut, $excludeBookingId);
    return $availability['available'];
}

/**
 * Enhanced availability check for a specific individual room
 */
function checkIndividualRoomAvailability($individualRoomId, $checkIn, $checkOut, $excludeBookingId = null) {
    global $pdo;

    $result = [
        'available' => true,
        'conflicts' => [],
        'maintenance' => [],
        'housekeeping' => [],
        'room' => null,
        'individual_room' => null,
        'room_type_id' => null
    ];

    try {
        // Get room status + room type info
        $stmt = $pdo->prepare("
            SELECT ir.*, r.name as room_type_name, r.max_guests, r.price_per_night
            FROM individual_rooms ir
            JOIN rooms r ON ir.room_type_id = r.id
            WHERE ir.id = ? AND ir.is_active = 1
        ");
        $stmt->execute([$individualRoomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $result['available'] = false;
            $result['error'] = 'Room not found or inactive';
            return $result;
        }

        $result['individual_room'] = $room;
        $result['room_type_id'] = (int)$room['room_type_id'];
        $result['room'] = [
            'id' => (int)$room['room_type_id'],
            'name' => $room['room_type_name'],
            'price_per_night' => (float)$room['price_per_night'],
            'max_guests' => (int)$room['max_guests']
        ];
        $result['max_guests'] = (int)$room['max_guests'];

        // Check if room status allows booking
        if (!in_array($room['status'], ['available', 'cleaning'])) {
            $result['available'] = false;
            $result['error'] = 'Selected room is not available for booking';
            return $result;
        }

        // Check maintenance schedules blocking this room
        $maintenanceStmt = $pdo->prepare("
            SELECT id, title, start_date, end_date, status
            FROM room_maintenance_schedules
            WHERE individual_room_id = ?
            AND block_room = 1
            AND status IN ('planned', 'in_progress')
            AND NOT (end_date <= ? OR start_date >= ?)
        ");
        $maintenanceStmt->execute([$individualRoomId, $checkIn, $checkOut]);
        $maintenance = $maintenanceStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($maintenance)) {
            $result['available'] = false;
            $result['maintenance'] = $maintenance;
            $result['error'] = 'Selected room is blocked for maintenance during these dates';
            return $result;
        }

        // Check housekeeping assignments blocking this room
        $housekeepingStmt = $pdo->prepare("
            SELECT id, due_date, status
            FROM housekeeping_assignments
            WHERE individual_room_id = ?
            AND status IN ('pending', 'in_progress', 'blocked')
            AND due_date >= ?
            AND due_date < ?
        ");
        $housekeepingStmt->execute([$individualRoomId, $checkIn, $checkOut]);
        $housekeeping = $housekeepingStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($housekeeping)) {
            $result['available'] = false;
            $result['housekeeping'] = $housekeeping;
            $result['error'] = 'Selected room has housekeeping blocks during these dates';
            return $result;
        }

        // Check for individual room blocked dates
        $blockedStmt = $pdo->prepare("
            SELECT
                id,
                individual_room_id,
                block_date,
                block_type,
                reason
            FROM individual_room_blocked_dates
            WHERE individual_room_id = ?
            AND block_date >= ? AND block_date < ?
            ORDER BY block_date ASC
        ");
        $blockedStmt->execute([$individualRoomId, $checkIn, $checkOut]);
        $blockedDates = $blockedStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($blockedDates)) {
            $result['available'] = false;
            $result['blocked_dates'] = $blockedDates;
            $result['error'] = 'Selected room is blocked on the selected dates';

            $blocked_details = [];
            foreach ($blockedDates as $blocked) {
                $blocked_date = new DateTime($blocked['block_date']);
                $blocked_details[] = sprintf(
                    "%s on %s (%s)",
                    $room['room_number'],
                    $blocked_date->format('M j, Y'),
                    $blocked['block_type']
                );
            }
            $result['blocked_message'] = implode('; ', $blocked_details);
            return $result;
        }

        // Check for booking conflicts (use standardized status list for individual rooms)
        $blockingStatuses = getBookingStatusesThatBlockAvailability(true);
        $placeholders = str_repeat('?,', count($blockingStatuses) - 1) . '?';
        
        $sql = "
            SELECT
                b.id,
                b.booking_reference,
                b.check_in_date,
                b.check_out_date,
                b.status,
                b.guest_name
            FROM bookings b
            WHERE b.individual_room_id = ?
            AND b.status IN ({$placeholders})
            AND NOT (b.check_out_date <= ? OR b.check_in_date >= ?)
        ";

        $params = array_merge([$individualRoomId], $blockingStatuses, [$checkIn, $checkOut]);
        if ($excludeBookingId) {
            $sql .= " AND b.id != ?";
            $params[] = $excludeBookingId;
        }

        $conflictStmt = $pdo->prepare($sql);
        $conflictStmt->execute($params);
        $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($conflicts)) {
            $result['available'] = false;
            $result['conflicts'] = $conflicts;
            $result['error'] = 'Selected room is not available for the selected dates';

            $conflict_details = [];
            foreach ($conflicts as $conflict) {
                $conflict_check_in = new DateTime($conflict['check_in_date']);
                $conflict_check_out = new DateTime($conflict['check_out_date']);
                $conflict_details[] = sprintf(
                    "Booking %s (%s) from %s to %s",
                    $conflict['booking_reference'],
                    $conflict['guest_name'],
                    $conflict_check_in->format('M j, Y'),
                    $conflict_check_out->format('M j, Y')
                );
            }
            $result['conflict_message'] = implode('; ', $conflict_details);
            return $result;
        }

        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        $result['nights'] = $checkInDate->diff($checkOutDate)->days;

        return $result;
    } catch (PDOException $e) {
        error_log("Error checking individual room availability: " . $e->getMessage());
        $result['available'] = false;
        $result['error'] = 'Database error while checking availability';
        return $result;
    } catch (Exception $e) {
        error_log("Error checking individual room availability: " . $e->getMessage());
        $result['available'] = false;
        $result['error'] = 'Invalid date format';
        return $result;
    }
}

/**
 * Update individual room status with logging
 *
 * @param int $individualRoomId Individual room ID
 * @param string $newStatus New status
 * @param string $reason Optional reason for status change
 * @param int $performedBy User ID who performed the change
 * @return bool True on success, false on failure
 */
function updateIndividualRoomStatus($individualRoomId, $newStatus, $reason = null, $performedBy = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
        $stmt->execute([$individualRoomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $pdo->rollBack();
            return false;
        }
        
        $oldStatus = $room['status'];
        
        // Update status
        $updateStmt = $pdo->prepare("UPDATE individual_rooms SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $individualRoomId]);
        
        // Log the change
        $logStmt = $pdo->prepare("
            INSERT INTO room_maintenance_log (individual_room_id, status_from, status_to, reason, performed_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $individualRoomId,
            $oldStatus,
            $newStatus,
            $reason,
            $performedBy
        ]);
        
        $pdo->commit();
        
        // Clear cache
        require_once __DIR__ . '/cache.php';
        clearRoomCache();
        
        return true;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating individual room status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get room type with individual rooms count
 *
 * @param int $roomTypeId Room type ID
 * @return array Room type data with counts
 */
function getRoomTypeWithCounts($roomTypeId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT
                rt.*,
                COUNT(DISTINCT ir.id) as individual_rooms_count,
                SUM(CASE WHEN ir.status = 'available' THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN ir.status = 'occupied' THEN 1 ELSE 0 END) as occupied_count,
                SUM(CASE WHEN ir.status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_count,
                SUM(CASE WHEN ir.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count
            FROM rooms rt
            LEFT JOIN individual_rooms ir ON rt.id = ir.room_type_id AND ir.is_active = 1
            WHERE rt.id = ?
            GROUP BY rt.id
        ");
        $stmt->execute([$roomTypeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Decode amenities JSON
            if ($result['amenities']) {
                $result['amenities'] = json_decode($result['amenities'], true);
            } else {
                $result['amenities'] = [];
            }
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error getting room type with counts: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all room types with individual room counts
 *
 * @param bool $activeOnly Only return active room types
 * @return array Room types with counts
 */
function getAllRoomTypesWithCounts($activeOnly = true) {
    global $pdo;
    
    try {
        $sql = "
            SELECT
                rt.id,
                rt.name,
                rt.slug,
                rt.price_per_night,
                rt.image_url,
                rt.is_featured,
                rt.is_active,
                rt.display_order,
                COUNT(DISTINCT ir.id) as individual_rooms_count,
                SUM(CASE WHEN ir.status = 'available' THEN 1 ELSE 0 END) as available_count
            FROM rooms rt
            LEFT JOIN individual_rooms ir ON rt.id = ir.room_type_id AND ir.is_active = 1
        ";
        
        if ($activeOnly) {
            $sql .= " WHERE rt.is_active = 1";
        }
        
        $sql .= " GROUP BY rt.id ORDER BY rt.display_order ASC, rt.name ASC";
        
        $stmt = $pdo->query($sql);
        $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process amenities
        foreach ($roomTypes as &$type) {
            $type['amenities'] = [];
            $type['available_count'] = (int)($type['available_count'] ?? 0);
            $type['individual_rooms_count'] = (int)($type['individual_rooms_count'] ?? 0);
        }
        
        return $roomTypes;
        
    } catch (PDOException $e) {
        error_log("Error getting all room types with counts: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign individual room to booking
 *
 * @param int $bookingId Booking ID
 * @param int $individualRoomId Individual room ID
 * @return bool True on success, false on failure
 */
function assignIndividualRoomToBooking($bookingId, $individualRoomId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Verify booking exists
        $bookingStmt = $pdo->prepare("SELECT id, room_id, check_in_date, check_out_date, number_of_nights, number_of_guests, child_guests, occupancy_type, total_amount, status FROM bookings WHERE id = ?");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $pdo->rollBack();
            return false;
        }
        
        // Verify individual room exists and is available
        $roomStmt = $pdo->prepare("SELECT id, room_type_id, status FROM individual_rooms WHERE id = ?");
        $roomStmt->execute([$individualRoomId]);
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $pdo->rollBack();
            return false;
        }
        
        // Check if room is available for booking dates
        if (!isIndividualRoomAvailable($individualRoomId, $booking['check_in_date'], $booking['check_out_date'], $bookingId)) {
            $pdo->rollBack();
            return false;
        }
        
        // Recalculate child pricing based on specific room override (fallback to room type)
        $pricingStmt = $pdo->prepare(" 
            SELECT
                COALESCE(r.price_single_occupancy, r.price_per_night) AS price_single,
                COALESCE(r.price_double_occupancy, r.price_per_night) AS price_double,
                COALESCE(r.price_triple_occupancy, r.price_per_night) AS price_triple,
                COALESCE(ir.child_price_multiplier, r.child_price_multiplier, 50) AS effective_child_multiplier
            FROM rooms r
            LEFT JOIN individual_rooms ir ON ir.id = ?
            WHERE r.id = ?
            LIMIT 1
        ");
        $pricingStmt->execute([$individualRoomId, (int)$booking['room_id']]);
        $pricing = $pricingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $occupancyType = strtolower((string)($booking['occupancy_type'] ?? 'single'));
        $ratePerNight = (float)($pricing['price_single'] ?? 0);
        if ($occupancyType === 'double') {
            $ratePerNight = (float)($pricing['price_double'] ?? $ratePerNight);
        } elseif ($occupancyType === 'triple') {
            $ratePerNight = (float)($pricing['price_triple'] ?? $ratePerNight);
        }

        $nights = max(1, (int)($booking['number_of_nights'] ?? 1));
        $children = max(0, (int)($booking['child_guests'] ?? 0));
        $childMultiplier = max(0, (float)($pricing['effective_child_multiplier'] ?? 50));
        $childSupplement = $children > 0
            ? ($ratePerNight * ($childMultiplier / 100) * $children * $nights)
            : 0.0;
        $baseAmount = $ratePerNight * $nights;
        $newTotal = $baseAmount + $childSupplement;

        // Update booking with individual room and refreshed child pricing totals
        $updateStmt = $pdo->prepare("UPDATE bookings SET individual_room_id = ?, child_price_multiplier = ?, child_supplement_total = ?, total_amount = ? WHERE id = ?");
        $updateStmt->execute([$individualRoomId, $childMultiplier, $childSupplement, $newTotal, $bookingId]);
        
        // Update individual room status based on timeline-aware logic
        // Only set to 'occupied' if check-in date is today or in the past
        // Future confirmed bookings should keep room as 'available' (reserved but not occupied)
        if (in_array($booking['status'], ['confirmed', 'checked-in'])) {
            $today = date('Y-m-d');
            $checkInDate = $booking['check_in_date'];
            
            if ($checkInDate <= $today) {
                // Check-in is today or in the past - room is physically occupied
                updateIndividualRoomStatus(
                    $individualRoomId,
                    'occupied',
                    'Assigned to ' . $booking['status'] . ' booking: ' . $bookingId . ' (check-in: ' . $checkInDate . ')',
                    null
                );
            } else {
                // Future booking - room remains available (reserved but not occupied)
                // Ensure room is in available state
                $currentStatusStmt = $pdo->prepare("SELECT status FROM individual_rooms WHERE id = ?");
                $currentStatusStmt->execute([$individualRoomId]);
                $currentStatus = $currentStatusStmt->fetchColumn();
                
                if ($currentStatus === 'occupied') {
                    // Room was incorrectly marked occupied, reset to available
                    updateIndividualRoomStatus(
                        $individualRoomId,
                        'available',
                        'Future booking assigned (check-in: ' . $checkInDate . ') - room available until then',
                        null
                    );
                }
            }
        }
        
        $pdo->commit();
        
        // Clear cache
        require_once __DIR__ . '/cache.php';
        clearRoomCache();
        
        return true;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error assigning individual room to booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-assign an available individual room to a booking
 * Uses deterministic selection: first available room ordered by room number
 *
 * @param int $bookingId Booking ID
 * @return array Result with success status and message
 */
function autoAssignIndividualRoom($bookingId) {
    global $pdo;
    
    $result = [
        'success' => false,
        'message' => '',
        'assigned_room_id' => null,
        'assigned_room_number' => null
    ];
    
    try {
        // Get booking details with room type information
        $stmt = $pdo->prepare("
            SELECT b.id, b.room_id, b.check_in_date, b.check_out_date, b.status, b.individual_room_id,
                   r.name as room_type_name, r.id as room_type_id
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $result['message'] = 'Booking not found';
            return $result;
        }
        
        // Skip if already has an individual room assigned
        if (!empty($booking['individual_room_id'])) {
            $result['success'] = true;
            $result['message'] = 'Room already assigned';
            $result['assigned_room_id'] = $booking['individual_room_id'];
            return $result;
        }
        
        $roomTypeId = (int)($booking['room_type_id'] ?? 0);
        
        if ($roomTypeId <= 0) {
            $result['message'] = 'Invalid room type for booking';
            return $result;
        }
        
        // Get available individual rooms using existing availability logic
        $availableRooms = getAvailableIndividualRooms(
            $roomTypeId,
            $booking['check_in_date'],
            $booking['check_out_date'],
            $bookingId
        );
        
        if (empty($availableRooms)) {
            $result['message'] = 'No available rooms for the selected dates';
            return $result;
        }
        
        // Deterministic selection: first by room_number ASC, then by id ASC
        usort($availableRooms, function($a, $b) {
            $roomCompare = strnatcmp($a['room_number'] ?? '', $b['room_number'] ?? '');
            if ($roomCompare !== 0) {
                return $roomCompare;
            }
            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        });
        
        $selectedRoom = $availableRooms[0];
        
        // Assign the room using existing assignment logic
        $assigned = assignIndividualRoomToBooking($bookingId, $selectedRoom['id']);
        
        if ($assigned) {
            $result['success'] = true;
            $result['message'] = 'Room ' . $selectedRoom['room_number'] . ' auto-assigned successfully';
            $result['assigned_room_id'] = $selectedRoom['id'];
            $result['assigned_room_number'] = $selectedRoom['room_number'];
        } else {
            $result['message'] = 'Failed to assign room (availability changed)';
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error auto-assigning individual room: " . $e->getMessage());
        $result['message'] = 'Database error during auto-assignment';
        return $result;
    }
}

/**
 * Get individual room details with booking info
 *
 * @param int $individualRoomId Individual room ID
 * @return array Room details with current/upcoming bookings
 */
function getIndividualRoomDetails($individualRoomId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT
                ir.*,
                rt.name as room_type_name,
                rt.slug as room_type_slug,
                rt.price_per_night,
                rt.amenities as room_type_amenities
            FROM individual_rooms ir
            JOIN rooms rt ON ir.room_type_id = rt.id
            WHERE ir.id = ?
        ");
        $stmt->execute([$individualRoomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            return null;
        }
        
        // Decode amenities
        $room['specific_amenities'] = $room['specific_amenities'] ? json_decode($room['specific_amenities'], true) : [];
        $room['room_type_amenities'] = $room['room_type_amenities'] ? json_decode($room['room_type_amenities'], true) : [];
        
        // Get current booking if occupied
        if ($room['status'] === 'occupied') {
            $bookingStmt = $pdo->prepare("
                SELECT id, booking_reference, guest_name, guest_email,
                       guest_phone, check_in_date, check_out_date, status
                FROM bookings
                WHERE individual_room_id = ?
                AND status IN ('confirmed', 'checked-in')
                AND check_out_date >= CURDATE()
                ORDER BY check_in_date DESC
                LIMIT 1
            ");
            $bookingStmt->execute([$individualRoomId]);
            $room['current_booking'] = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get upcoming bookings
        $upcomingStmt = $pdo->prepare("
            SELECT id, booking_reference, guest_name, check_in_date, check_out_date
            FROM bookings
            WHERE individual_room_id = ?
            AND status IN ('confirmed', 'pending')
            AND check_in_date > CURDATE()
            ORDER BY check_in_date ASC
            LIMIT 5
        ");
        $upcomingStmt->execute([$individualRoomId]);
        $room['upcoming_bookings'] = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get maintenance log
        $logStmt = $pdo->prepare("
            SELECT
                rml.*,
                u.username as performed_by_name
            FROM room_maintenance_log rml
            LEFT JOIN admin_users u ON rml.performed_by = u.id
            WHERE rml.individual_room_id = ?
            ORDER BY rml.created_at DESC
            LIMIT 20
        ");
        $logStmt->execute([$individualRoomId]);
        $room['maintenance_log'] = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $room;
        
    } catch (PDOException $e) {
        error_log("Error getting individual room details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get room status summary for a room type
 *
 * @param int $roomTypeId Room type ID
 * @return array Status summary
 */
function getRoomTypeStatusSummary($roomTypeId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT
                status,
                COUNT(*) as count
            FROM individual_rooms
            WHERE room_type_id = ? AND is_active = 1
            GROUP BY status
        ");
        $stmt->execute([$roomTypeId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary = [
            'available' => 0,
            'occupied' => 0,
            'cleaning' => 0,
            'maintenance' => 0,
            'out_of_order' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $summary[$row['status']] = (int)$row['count'];
            $summary['total'] += (int)$row['count'];
        }
        
        
        return $summary;
        
    } catch (PDOException $e) {
        error_log("Error getting room type status summary: " . $e->getMessage());
        return [
            'available' => 0,
            'occupied' => 0,
            'cleaning' => 0,
            'maintenance' => 0,
            'out_of_order' => 0,
            'total' => 0
        ];
    }
}

/**
 * Ensure api_keys table has api_key_plain column for retrievable storage.
 * Stores AES-256 encrypted API key for admin view/copy functionality.
 * The api_key column remains hashed for authentication (password_verify).
 */
function ensureApiKeyRetrievableColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        
        // Check if api_key_plain column exists
        $columnExistsStmt->execute(['api_keys', 'api_key_plain']);
        $exists = (int)$columnExistsStmt->fetchColumn() > 0;
        
        if (!$exists) {
            // Add the column for storing encrypted retrievable key
            $pdo->exec("ALTER TABLE api_keys ADD COLUMN api_key_plain TEXT NULL DEFAULT NULL COMMENT 'AES-256 encrypted retrievable API key for admin view' AFTER api_key");
        }
    } catch (Throwable $e) {
        error_log('ensureApiKeyRetrievableColumn warning: ' . $e->getMessage());
    }
}

/**
 * Encrypt API key for storage using AES-256-CBC.
 *
 * @param string $plainKey The plain API key to encrypt
 * @return string Base64-encoded encrypted data with IV
 */
function encryptApiKey(string $plainKey): string {
    // Use a server-specific encryption key (should be in config/local for production)
    $encryptionKey = hash('sha256', 'ROSALYNS_API_KEY_ENCRYPTION_' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plainKey, 'AES-256-CBC', $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt API key for admin viewing.
 *
 * @param string $encryptedKey Base64-encoded encrypted data with IV
 * @return string|null Plain API key or null on failure
 */
function decryptApiKey(string $encryptedKey): ?string {
    if (empty($encryptedKey)) {
        return null;
    }
    $encryptionKey = hash('sha256', 'ROSALYNS_API_KEY_ENCRYPTION_' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), true);
    $data = base64_decode($encryptedKey);
    if ($data === false || strlen($data) < 16) {
        return null;
    }
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $encryptionKey, 0, $iv);
    return $decrypted !== false ? $decrypted : null;
}

// ============================================================================
// BOOKING CHARGES / FOLIO MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Ensure booking_charges table exists (migration helper)
 */
function ensureBookingChargesTable(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $tableExistsStmt->execute(['booking_charges']);
        $tableExists = (int)$tableExistsStmt->fetchColumn() > 0;

        if (!$tableExists) {
            // Create booking_charges table
            $pdo->exec("CREATE TABLE booking_charges (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id INT UNSIGNED NOT NULL,
                charge_type ENUM('room', 'food', 'drink', 'service', 'minibar', 'custom', 'breakfast', 'room_service', 'laundry', 'other') NOT NULL DEFAULT 'custom',
                source_item_id INT UNSIGNED NULL COMMENT 'FK to menu item ID if applicable',
                description VARCHAR(255) NOT NULL COMMENT 'Snapshot of charge description at time of creation',
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1.000,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Snapshot of unit price at time of creation',
                line_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'quantity * unit_price',
                vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT rate percentage for this line',
                vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT amount for this line',
                line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'line_subtotal + vat_amount',
                posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When charge was posted to folio',
                added_by INT UNSIGNED NULL COMMENT 'Admin user ID who added the charge',
                voided TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether charge is voided/reversed',
                voided_at DATETIME NULL COMMENT 'When charge was voided',
                void_reason VARCHAR(255) NULL COMMENT 'Reason for voiding',
                voided_by INT UNSIGNED NULL COMMENT 'Admin user ID who voided the charge',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_booking_charges_booking_id (booking_id),
                KEY idx_booking_charges_type (charge_type),
                KEY idx_booking_charges_source (source_item_id),
                KEY idx_booking_charges_voided (voided),
                KEY idx_booking_charges_posted_at (posted_at),
                CONSTRAINT fk_booking_charges_booking_id FOREIGN KEY (booking_id)
                    REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Ensure bookings table has final invoice tracking columns
        $ensureColumn = function (string $table, string $column, string $alterSql) use ($columnExistsStmt, $pdo): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        $ensureColumn('bookings', 'final_invoice_generated', "ALTER TABLE bookings ADD COLUMN final_invoice_generated TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether final invoice has been generated at checkout'");
        $ensureColumn('bookings', 'final_invoice_path', "ALTER TABLE bookings ADD COLUMN final_invoice_path VARCHAR(255) NULL COMMENT 'Path to final invoice file'");
        $ensureColumn('bookings', 'final_invoice_number', "ALTER TABLE bookings ADD COLUMN final_invoice_number VARCHAR(50) NULL COMMENT 'Final invoice number'");
        $ensureColumn('bookings', 'final_invoice_sent_at', "ALTER TABLE bookings ADD COLUMN final_invoice_sent_at DATETIME NULL COMMENT 'When final invoice email was sent'");
        $ensureColumn('bookings', 'checkout_completed_at', "ALTER TABLE bookings ADD COLUMN checkout_completed_at DATETIME NULL COMMENT 'When checkout was completed'");
        $ensureColumn('bookings', 'folio_charges_total', "ALTER TABLE bookings ADD COLUMN folio_charges_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total of all folio charge lines (including VAT)'");

    } catch (Throwable $e) {
        error_log('ensureBookingChargesTable warning: ' . $e->getMessage());
    }
}

// Initialize booking charges table on connection
ensureBookingChargesTable($pdo);

/**
 * Add a charge to a booking folio
 *
 * @param int $bookingId Booking ID
 * @param string $chargeType Type of charge (room, food, drink, service, minibar, custom, etc.)
 * @param string $description Charge description
 * @param float $quantity Quantity
 * @param float $unitPrice Unit price (snapshot at time of creation)
 * @param int|null $sourceItemId Source menu item ID if applicable
 * @param int|null $addedBy Admin user ID who added the charge
 * @return array Result with success status and charge ID
 */
function addBookingCharge(int $bookingId, string $chargeType, string $description, float $quantity, float $unitPrice, ?int $sourceItemId = null, ?int $addedBy = null): array {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get VAT settings
        $vatEnabled = getSetting('vat_enabled') === '1';
        $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
        
        // Calculate line totals
        $lineSubtotal = $quantity * $unitPrice;
        $vatAmount = $lineSubtotal * ($vatRate / 100);
        $lineTotal = $lineSubtotal + $vatAmount;
        
        // Insert charge
        $stmt = $pdo->prepare("
            INSERT INTO booking_charges (
                booking_id, charge_type, source_item_id, description,
                quantity, unit_price, line_subtotal, vat_rate, vat_amount, line_total,
                posted_at, added_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $bookingId,
            $chargeType,
            $sourceItemId,
            $description,
            $quantity,
            $unitPrice,
            $lineSubtotal,
            $vatRate,
            $vatAmount,
            $lineTotal,
            $addedBy
        ]);
        
        $chargeId = $pdo->lastInsertId();
        
        // Recalculate booking financials
        recalculateBookingFinancials($bookingId);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'charge_id' => $chargeId,
            'line_total' => $lineTotal
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("addBookingCharge error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to add charge: ' . $e->getMessage()
        ];
    }
}

/**
 * Add a charge from a menu item (food or drink)
 * Snapshots the current item name and price
 *
 * @param int $bookingId Booking ID
 * @param string $menuType 'food' or 'drink'
 * @param int $menuItemId Menu item ID
 * @param float $quantity Quantity
 * @param int|null $addedBy Admin user ID
 * @return array Result with success status
 */
function addBookingChargeFromMenu(int $bookingId, string $menuType, int $menuItemId, float $quantity, ?int $addedBy = null): array {
    global $pdo;
    
    try {
        $table = $menuType === 'food' ? 'food_menu' : 'drink_menu';
        
        // Get menu item details (snapshot current name and price)
        $stmt = $pdo->prepare("SELECT item_name, price, category FROM {$table} WHERE id = ? AND is_available = 1");
        $stmt->execute([$menuItemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            return [
                'success' => false,
                'message' => 'Menu item not found or unavailable'
            ];
        }
        
        $description = $item['item_name'];
        $unitPrice = (float)$item['price'];
        $chargeType = $menuType === 'food' ? 'food' : 'drink';
        
        return addBookingCharge($bookingId, $chargeType, $description, $quantity, $unitPrice, $menuItemId, $addedBy);
        
    } catch (PDOException $e) {
        error_log("addBookingChargeFromMenu error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Void a booking charge (audit-safe reversal)
 *
 * @param int $chargeId Charge ID
 * @param string $voidReason Reason for voiding
 * @param int|null $voidedBy Admin user ID who voided the charge
 * @return array Result with success status
 */
function voidBookingCharge(int $chargeId, string $voidReason, ?int $voidedBy = null): array {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get charge details
        $stmt = $pdo->prepare("SELECT booking_id, voided FROM booking_charges WHERE id = ?");
        $stmt->execute([$chargeId]);
        $charge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$charge) {
            return [
                'success' => false,
                'message' => 'Charge not found'
            ];
        }
        
        if ($charge['voided']) {
            return [
                'success' => false,
                'message' => 'Charge already voided'
            ];
        }
        
        // Mark as voided
        $updateStmt = $pdo->prepare("
            UPDATE booking_charges
            SET voided = 1, voided_at = NOW(), void_reason = ?, voided_by = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$voidReason, $voidedBy, $chargeId]);
        
        // Recalculate booking financials
        recalculateBookingFinancials($charge['booking_id']);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Charge voided successfully'
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("voidBookingCharge error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to void charge: ' . $e->getMessage()
        ];
    }
}

/**
 * Get all charges for a booking (non-voided only by default)
 *
 * @param int $bookingId Booking ID
 * @param bool $includeVoided Include voided charges
 * @return array List of charges
 */
function getBookingCharges(int $bookingId, bool $includeVoided = false): array {
    global $pdo;
    
    try {
        $voidedFilter = $includeVoided ? '' : 'AND bc.voided = 0';
        
        $stmt = $pdo->prepare("
            SELECT
                bc.*,
                CASE
                    WHEN bc.charge_type = 'food' THEN fm.item_name
                    WHEN bc.charge_type = 'drink' THEN dm.item_name
                    ELSE NULL
                END as source_item_name,
                CASE
                    WHEN bc.charge_type = 'food' THEN fm.category
                    WHEN bc.charge_type = 'drink' THEN dm.category
                    ELSE NULL
                END as source_item_category
            FROM booking_charges bc
            LEFT JOIN food_menu fm ON bc.charge_type = 'food' AND bc.source_item_id = fm.id
            LEFT JOIN drink_menu dm ON bc.charge_type = 'drink' AND bc.source_item_id = dm.id
            WHERE bc.booking_id = ? {$voidedFilter}
            ORDER BY bc.posted_at ASC, bc.id ASC
        ");
        
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("getBookingCharges error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get booking folio summary with totals breakdown
 *
 * @param int $bookingId Booking ID
 * @return array Folio summary
 */
function getBookingFolioSummary(int $bookingId): array {
    global $pdo;
    
    try {
        // Get booking base amount
        $bookingStmt = $pdo->prepare("SELECT total_amount, amount_paid, amount_due FROM bookings WHERE id = ?");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return ['error' => 'Booking not found'];
        }
        
        // Get charges summary
        $chargesStmt = $pdo->prepare("
            SELECT
                charge_type,
                COUNT(*) as item_count,
                SUM(line_subtotal) as total_subtotal,
                SUM(vat_amount) as total_vat,
                SUM(line_total) as total_amount
            FROM booking_charges
            WHERE booking_id = ? AND voided = 0
            GROUP BY charge_type
            ORDER BY charge_type
        ");
        $chargesStmt->execute([$bookingId]);
        $chargesByType = $chargesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get totals
        $totalsStmt = $pdo->prepare("
            SELECT
                SUM(line_subtotal) as folio_subtotal,
                SUM(vat_amount) as folio_vat,
                SUM(line_total) as folio_total,
                COUNT(*) as total_items
            FROM booking_charges
            WHERE booking_id = ? AND voided = 0
        ");
        $totalsStmt->execute([$bookingId]);
        $folioTotals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get payments
        $paymentsStmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN total_amount ELSE 0 END) as total_paid
            FROM payments
            WHERE booking_type = 'room' AND booking_id = ? AND deleted_at IS NULL
        ");
        $paymentsStmt->execute([$bookingId]);
        $payments = $paymentsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate final totals
        $baseAmount = (float)$booking['total_amount'];
        $extrasSubtotal = (float)($folioTotals['folio_subtotal'] ?? 0);
        $extrasVat = (float)($folioTotals['folio_vat'] ?? 0);
        $extrasTotal = (float)($folioTotals['folio_total'] ?? 0);
        
        $totalSubtotal = $baseAmount + $extrasSubtotal;
        $totalVat = $extrasVat; // Base amount VAT is calculated separately in bookings
        $grandTotal = $baseAmount + $extrasTotal;
        
        $amountPaid = (float)($payments['total_paid'] ?? 0);
        $balanceDue = max(0, $grandTotal - $amountPaid);
        
        return [
            'booking_base_amount' => $baseAmount,
            'extras_subtotal' => $extrasSubtotal,
            'extras_vat' => $extrasVat,
            'extras_total' => $extrasTotal,
            'total_subtotal' => $totalSubtotal,
            'total_vat' => $totalVat,
            'grand_total' => $grandTotal,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'charges_by_type' => $chargesByType,
            'total_items' => (int)($folioTotals['total_items'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("getBookingFolioSummary error: " . $e->getMessage());
        return ['error' => 'Database error'];
    }
}

/**
 * Recalculate booking financials based on room base + active charges
 * This is called automatically when charges are added/voided
 *
 * @param int $bookingId Booking ID
 * @return bool Success status
 */
function recalculateBookingFinancials(int $bookingId): bool {
    global $pdo;
    
    try {
        // Get current booking
        $stmt = $pdo->prepare("SELECT total_amount, vat_rate, vat_amount FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return false;
        }
        
        // Calculate folio charges total
        $chargesStmt = $pdo->prepare("
            SELECT
                SUM(line_subtotal) as charges_subtotal,
                SUM(vat_amount) as charges_vat,
                SUM(line_total) as charges_total
            FROM booking_charges
            WHERE booking_id = ? AND voided = 0
        ");
        $chargesStmt->execute([$bookingId]);
        $charges = $chargesStmt->fetch(PDO::FETCH_ASSOC);
        
        $baseAmount = (float)$booking['total_amount'];
        $chargesSubtotal = (float)($charges['charges_subtotal'] ?? 0);
        $chargesVat = (float)($charges['charges_vat'] ?? 0);
        $chargesTotal = (float)($charges['charges_total'] ?? 0);
        
        // Get payments
        $paymentsStmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN total_amount ELSE 0 END) as total_paid
            FROM payments
            WHERE booking_type = 'room' AND booking_id = ? AND deleted_at IS NULL
        ");
        $paymentsStmt->execute([$bookingId]);
        $payments = $paymentsStmt->fetch(PDO::FETCH_ASSOC);
        
        $amountPaid = (float)($payments['total_paid'] ?? 0);
        
        // Calculate totals
        // Note: Base amount already includes VAT from bookings table
        // Charges have their own VAT calculated
        $totalAmount = $baseAmount + $chargesSubtotal;
        $totalVat = (float)$booking['vat_amount'] + $chargesVat;
        $totalWithVat = $baseAmount + $chargesTotal; // charges_total already includes VAT
        $amountDue = max(0, $totalWithVat - $amountPaid);
        
        // Update booking
        $updateStmt = $pdo->prepare("
            UPDATE bookings
            SET amount_paid = ?,
                amount_due = ?,
                folio_charges_total = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $amountPaid,
            $amountDue,
            $chargesTotal,
            $bookingId
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("recalculateBookingFinancials error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get menu items for quick-add to booking folio
 *
 * @param string $menuType 'food' or 'drink'
 * @return array Menu items grouped by category
 */
function getMenuItemsForFolio(string $menuType = 'food'): array {
    global $pdo;
    
    try {
        $table = $menuType === 'food' ? 'food_menu' : 'drink_menu';
        
        $stmt = $pdo->query("
            SELECT id, item_name, description, price, category, is_featured
            FROM {$table}
            WHERE is_available = 1
            ORDER BY category ASC, display_order ASC, item_name ASC
        ");
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by category
        $grouped = [];
        foreach ($items as $item) {
            $category = $item['category'] ?? 'Other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $item;
        }
        
        return $grouped;
        
    } catch (PDOException $e) {
        error_log("getMenuItemsForFolio error: " . $e->getMessage());
        return [];
    }
}

/**
 * Ensure booking date adjustments support tables exist
 */
function ensureBookingDateAdjustmentsSupport(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $columnExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");

        $tableExists = function (string $table) use ($tableExistsStmt): bool {
            $tableExistsStmt->execute([$table]);
            return (int)$tableExistsStmt->fetchColumn() > 0;
        };

        $ensureColumn = function (string $table, string $column, string $alterSql) use ($columnExistsStmt, $pdo): void {
            $columnExistsStmt->execute([$table, $column]);
            $exists = (int)$columnExistsStmt->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($alterSql);
            }
        };

        // Create booking_date_adjustments table if not exists
        if (!$tableExists('booking_date_adjustments')) {
            $pdo->exec("CREATE TABLE booking_date_adjustments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id INT UNSIGNED NOT NULL,
                booking_reference VARCHAR(50) NOT NULL,
                old_check_in_date DATE NOT NULL COMMENT 'Previous check-in date',
                new_check_in_date DATE NOT NULL COMMENT 'New check-in date',
                old_check_out_date DATE NOT NULL COMMENT 'Previous check-out date',
                new_check_out_date DATE NOT NULL COMMENT 'New check-out date',
                old_number_of_nights INT NOT NULL COMMENT 'Previous number of nights',
                new_number_of_nights INT NOT NULL COMMENT 'New number of nights',
                old_total_amount DECIMAL(10,2) NOT NULL COMMENT 'Previous booking total amount',
                new_total_amount DECIMAL(10,2) NOT NULL COMMENT 'New booking total amount',
                amount_delta DECIMAL(10,2) NOT NULL COMMENT 'Difference in amount (positive = additional charge, negative = refund)',
                adjustment_reason TEXT NOT NULL COMMENT 'Reason for the adjustment',
                adjusted_by INT UNSIGNED NOT NULL COMMENT 'Admin user ID who made the adjustment',
                adjusted_by_name VARCHAR(255) NOT NULL COMMENT 'Admin user name who made the adjustment',
                adjustment_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the adjustment was made',
                ip_address VARCHAR(45) NULL COMMENT 'IP address of the admin making the adjustment',
                metadata JSON NULL COMMENT 'Additional metadata',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_booking_date_adjustments_booking_id (booking_id),
                KEY idx_booking_date_adjustments_reference (booking_reference),
                KEY idx_booking_date_adjustments_timestamp (adjustment_timestamp),
                KEY idx_booking_date_adjustments_adjusted_by (adjusted_by),
                CONSTRAINT fk_booking_date_adjustments_booking_id FOREIGN KEY (booking_id)
                    REFERENCES bookings(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_booking_date_adjustments_adjusted_by FOREIGN KEY (adjusted_by)
                    REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

    } catch (Throwable $e) {
        error_log('ensureBookingDateAdjustmentsSupport warning: ' . $e->getMessage());
    }
}

// Initialize booking date adjustments support on connection
ensureBookingDateAdjustmentsSupport($pdo);

/**
 * Validate if a booking is eligible for date adjustment
 *
 * @param array $booking Booking data
 * @return array Validation result with 'allowed' and 'reason' keys
 */
function validateDateAdjustment(array $booking, string $newCheckIn = null, string $newCheckOut = null): array {
    // Check if booking exists
    if (empty($booking['id'])) {
        return [
            'allowed' => false,
            'reason' => 'booking_not_found',
            'message' => 'Booking not found.'
        ];
    }
    
    // Cannot adjust dates for certain statuses
    $ineligibleStatuses = ['cancelled', 'checked-out', 'no-show'];
    
    if (in_array($booking['status'] ?? '', $ineligibleStatuses)) {
        return [
            'allowed' => false,
            'reason' => 'status_ineligible',
            'message' => 'Cannot adjust dates for bookings that are cancelled, checked-out, or no-show.'
        ];
    }
    
    // Validate dates if provided
    if ($newCheckIn !== null && $newCheckOut !== null) {
        $checkIn = DateTime::createFromFormat('Y-m-d', $newCheckIn);
        $checkOut = DateTime::createFromFormat('Y-m-d', $newCheckOut);
        
        if (!$checkIn || !$checkOut) {
            return [
                'allowed' => false,
                'reason' => 'invalid_date_format',
                'message' => 'Invalid date format. Use Y-m-d format.'
            ];
        }
        
        if ($checkIn >= $checkOut) {
            return [
                'allowed' => false,
                'reason' => 'invalid_date_range',
                'message' => 'Check-out date must be after check-in date.'
            ];
        }
        
        // Calculate new number of nights
        $newNights = $checkIn->diff($checkOut)->days;
        
        if ($newNights <= 0) {
            return [
                'allowed' => false,
                'reason' => 'invalid_nights',
                'message' => 'Booking must be for at least one night.'
            ];
        }
        
        // Prevent adjusting to past dates (allow today if check-in hasn't happened yet)
        $today = new DateTime('today');
        $currentCheckIn = DateTime::createFromFormat('Y-m-d', $booking['check_in_date'] ?? '');
        
        // If original check-in is in the past, allow adjustments but warn
        // If original check-in is today or future, don't allow past dates
        if ($currentCheckIn && $currentCheckIn >= $today && $checkIn < $today) {
            return [
                'allowed' => false,
                'reason' => 'past_date_not_allowed',
                'message' => 'Cannot adjust dates to the past. The new check-in date must be today or in the future.'
            ];
        }
        
        // Validate maximum stay duration (e.g., 30 nights)
        $maxStayNights = 30;
        if ($newNights > $maxStayNights) {
            return [
                'allowed' => false,
                'reason' => 'max_stay_exceeded',
                'message' => "Booking cannot exceed {$maxStayNights} nights. Please contact management for extended stays."
            ];
        }
    }
    
    return ['allowed' => true];
}

/**
 * Calculate new booking amount based on date changes
 *
 * @param array $booking Current booking data
 * @param string $newCheckIn New check-in date (Y-m-d)
 * @param string $newCheckOut New check-out date (Y-m-d)
 * @return array Calculation result with new amount, nights, and error if any
 */
function calculateDateAdjustmentAmount(array $booking, string $newCheckIn, string $newCheckOut): array {
    global $pdo;
    
    try {
        // Validate dates
        $checkIn = DateTime::createFromFormat('Y-m-d', $newCheckIn);
        $checkOut = DateTime::createFromFormat('Y-m-d', $newCheckOut);
        
        if (!$checkIn || !$checkOut) {
            return ['error' => 'Invalid date format. Use Y-m-d format.'];
        }
        
        if ($checkIn >= $checkOut) {
            return ['error' => 'Check-out date must be after check-in date.'];
        }
        
        // Calculate new number of nights
        $newNights = $checkIn->diff($checkOut)->days;
        
        if ($newNights <= 0) {
            return ['error' => 'Booking must be for at least one night.'];
        }
        
        // Get room rate
        $stmt = $pdo->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
        $stmt->execute([$booking['room_id']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            return ['error' => 'Room not found.'];
        }
        
        $pricePerNight = (float)$room['price_per_night'];
        
        // Get old values
        $oldNights = (int)($booking['number_of_nights'] ?? 0);
        $oldTotalAmount = (float)($booking['total_amount'] ?? 0);
        $oldChildSupplement = (float)($booking['child_supplement_total'] ?? 0);
        
        // Calculate new base room amount
        $newBaseAmount = $pricePerNight * $newNights;
        
        // Calculate child supplement adjustment (proportional to nights change)
        // Preserve the child supplement by adjusting it proportionally based on night ratio
        $newChildSupplement = 0.0;
        if ($oldNights > 0 && $oldChildSupplement > 0) {
            $nightRatio = $newNights / $oldNights;
            $newChildSupplement = $oldChildSupplement * $nightRatio;
        }
        
        // Get VAT settings
        $vatEnabled = getSetting('vat_enabled') === '1';
        $vatRate = $vatEnabled ? (float)getSetting('vat_rate') : 0;
        
        // Calculate VAT on base room amount only (child supplements may have their own VAT treatment)
        $vatAmount = $newBaseAmount * ($vatRate / 100);
        $newTotalAmount = $newBaseAmount + $vatAmount + $newChildSupplement;
        
        // Calculate delta (includes child supplement changes)
        $amountDelta = $newTotalAmount - $oldTotalAmount;
        
        return [
            'success' => true,
            'old_nights' => $oldNights,
            'new_nights' => $newNights,
            'nights_delta' => $newNights - $oldNights,
            'old_total_amount' => $oldTotalAmount,
            'new_total_amount' => $newTotalAmount,
            'old_child_supplement' => $oldChildSupplement,
            'new_child_supplement' => $newChildSupplement,
            'child_supplement_delta' => $newChildSupplement - $oldChildSupplement,
            'amount_delta' => $amountDelta,
            'price_per_night' => $pricePerNight,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount
        ];
        
    } catch (Exception $e) {
        error_log("calculateDateAdjustmentAmount error: " . $e->getMessage());
        return ['error' => 'Failed to calculate adjustment amount.'];
    }
}

/**
 * Process booking date adjustment with full audit trail and financial impact
 *
 * @param int $bookingId Booking ID
 * @param string $newCheckIn New check-in date (Y-m-d)
 * @param string $newCheckOut New check-out date (Y-m-d)
 * @param string $reason Reason for adjustment
 * @param int $adjustedBy Admin user ID
 * @param string $adjustedByName Admin user name
 * @return array Result with success status and details
 */
function processBookingDateAdjustment(int $bookingId, string $newCheckIn, string $newCheckOut, string $reason, int $adjustedBy, string $adjustedByName): array {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current booking with room and individual room info
        $stmt = $pdo->prepare("
            SELECT b.*, r.price_per_night, r.name as room_name, ir.room_number as individual_room_number
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            LEFT JOIN individual_rooms ir ON b.individual_room_id = ir.id
            WHERE b.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Booking not found.'];
        }
        
        // Validate adjustment eligibility (including date validations)
        $validation = validateDateAdjustment($booking, $newCheckIn, $newCheckOut);
        if (!$validation['allowed']) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // Calculate new amount
        $calculation = calculateDateAdjustmentAmount($booking, $newCheckIn, $newCheckOut);
        if (isset($calculation['error'])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $calculation['error']];
        }
        
        // Store old values
        $oldCheckIn = $booking['check_in_date'];
        $oldCheckOut = $booking['check_out_date'];
        $oldNights = (int)$booking['number_of_nights'];
        $oldTotalAmount = (float)$booking['total_amount'];
        $oldChildSupplement = (float)($booking['child_supplement_total'] ?? 0);
        $oldAmountPaid = (float)($booking['amount_paid'] ?? 0);
        
        // Check room availability for new dates (excluding current booking)
        $availabilityCheck = isRoomAvailable($booking['room_id'], $newCheckIn, $newCheckOut, $bookingId);
        if (!$availabilityCheck) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Room is not available for the selected dates.'];
        }
        
        // If individual room is assigned, check its specific availability
        if (!empty($booking['individual_room_id'])) {
            $individualRoomAvailable = isIndividualRoomAvailable($booking['individual_room_id'], $newCheckIn, $newCheckOut, $bookingId);
            if (!$individualRoomAvailable) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'The assigned individual room is not available for the selected dates. Please select different dates or reassign the room.'];
            }
        }
        
        // Get folio charges total (additional charges beyond room rate)
        $folioStmt = $pdo->prepare("
            SELECT COALESCE(SUM(line_total), 0) as folio_total
            FROM booking_charges
            WHERE booking_id = ? AND status != 'voided'
        ");
        $folioStmt->execute([$bookingId]);
        $folioData = $folioStmt->fetch(PDO::FETCH_ASSOC);
        $folioTotal = (float)($folioData['folio_total'] ?? 0);
        
        // Calculate new amount due including folio charges
        // New total = new room total (with VAT) + child supplement + folio charges
        $newRoomTotal = $calculation['new_total_amount'];
        $newTotalWithFolio = $newRoomTotal + $folioTotal;
        $newAmountDue = $newTotalWithFolio - $oldAmountPaid;
        
        // Determine payment status and credit balance
        $newPaymentStatus = $booking['payment_status'];
        $creditBalance = 0.0;
        
        if ($newAmountDue <= 0.01) {
            // Fully paid or overpaid (credit)
            $newPaymentStatus = 'paid';
            $creditBalance = abs($newAmountDue); // Track credit balance separately
        } elseif ($oldAmountPaid > 0) {
            // Partial payment
            $newPaymentStatus = 'partial';
        } else {
            // No payment yet
            $newPaymentStatus = 'unpaid';
        }
        
        // Update booking with all values
        $updateStmt = $pdo->prepare("
            UPDATE bookings
            SET check_in_date = ?,
                check_out_date = ?,
                number_of_nights = ?,
                total_amount = ?,
                vat_amount = ?,
                child_supplement_total = ?,
                amount_due = ?,
                payment_status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $newCheckIn,
            $newCheckOut,
            $calculation['new_nights'],
            $newRoomTotal, // Store room total only (folio charges tracked separately)
            $calculation['vat_amount'],
            $calculation['new_child_supplement'],
            max(0, $newAmountDue), // Don't allow negative amount_due (credit tracked in metadata)
            $newPaymentStatus,
            $bookingId
        ]);
        
        // Record adjustment in audit table
        $adjustmentStmt = $pdo->prepare("
            INSERT INTO booking_date_adjustments (
                booking_id, booking_reference,
                old_check_in_date, new_check_in_date,
                old_check_out_date, new_check_out_date,
                old_number_of_nights, new_number_of_nights,
                old_total_amount, new_total_amount,
                amount_delta, adjustment_reason,
                adjusted_by, adjusted_by_name,
                ip_address, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $adjustmentStmt->execute([
            $bookingId,
            $booking['booking_reference'],
            $oldCheckIn,
            $newCheckIn,
            $oldCheckOut,
            $newCheckOut,
            $oldNights,
            $calculation['new_nights'],
            $oldTotalAmount,
            $newRoomTotal,
            $calculation['amount_delta'],
            $reason,
            $adjustedBy,
            $adjustedByName,
            $_SERVER['REMOTE_ADDR'] ?? null,
            json_encode([
                'room_id' => $booking['room_id'],
                'room_name' => $booking['room_name'],
                'individual_room_number' => $booking['individual_room_number'] ?? null,
                'price_per_night' => $calculation['price_per_night'],
                'vat_rate' => $calculation['vat_rate'],
                'old_child_supplement' => $oldChildSupplement,
                'new_child_supplement' => $calculation['new_child_supplement'],
                'child_supplement_delta' => $calculation['child_supplement_delta'],
                'folio_charges_total' => $folioTotal,
                'credit_balance' => $creditBalance > 0 ? $creditBalance : null
            ])
        ]);
        
        $adjustmentId = $pdo->lastInsertId();
        
        // Log to timeline
        require_once __DIR__ . '/../includes/booking-timeline.php';
        
        $deltaText = $calculation['amount_delta'] >= 0
            ? '+$' . number_format(abs($calculation['amount_delta']), 2) . ' additional charge'
            : '-$' . number_format(abs($calculation['amount_delta']), 2) . ' refund/credit';
        
        // Add credit note if applicable
        $creditNote = '';
        if ($creditBalance > 0.01) {
            $creditNote = ' (Credit balance: $' . number_format($creditBalance, 2) . ')';
        }
        
        $description = sprintf(
            "Stay dates adjusted: %s to %s → %s to %s (%d → %d nights, %s)%s",
            $oldCheckIn,
            $oldCheckOut,
            $newCheckIn,
            $newCheckOut,
            $oldNights,
            $calculation['new_nights'],
            $deltaText,
            $creditNote
        );
        
        logBookingEvent(
            $bookingId,
            $booking['booking_reference'],
            'Stay dates adjusted',
            'date_adjustment',
            $description,
            json_encode(['old' => ['check_in' => $oldCheckIn, 'check_out' => $oldCheckOut, 'nights' => $oldNights, 'total' => $oldTotalAmount]]),
            json_encode(['new' => ['check_in' => $newCheckIn, 'check_out' => $newCheckOut, 'nights' => $calculation['new_nights'], 'total' => $newRoomTotal]]),
            'admin',
            $adjustedBy,
            $adjustedByName,
            [
                'adjustment_id' => $adjustmentId,
                'amount_delta' => $calculation['amount_delta'],
                'child_supplement_delta' => $calculation['child_supplement_delta'],
                'credit_balance' => $creditBalance > 0 ? $creditBalance : null,
                'reason' => $reason
            ]
        );
        
        $pdo->commit();
        
        return [
            'success' => true,
            'adjustment_id' => $adjustmentId,
            'calculation' => $calculation,
            'credit_balance' => $creditBalance > 0 ? $creditBalance : null,
            'message' => 'Booking dates adjusted successfully.' . ($creditBalance > 0 ? " Guest has a credit balance of $" . number_format($creditBalance, 2) . "." : '')
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("processBookingDateAdjustment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process date adjustment: ' . $e->getMessage()];
    }
}

/**
 * Get date adjustment history for a booking
 *
 * @param int $bookingId Booking ID
 * @return array List of adjustments
 */
function getBookingDateAdjustments(int $bookingId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM booking_date_adjustments
            WHERE booking_id = ?
            ORDER BY adjustment_timestamp DESC
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getBookingDateAdjustments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Ensure audit log tables exist for housekeeping and maintenance.
 * This function creates the audit tables if they don't exist.
 */
function ensureAuditLogTables(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $tableExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");

        $tableExists = function (string $table) use ($tableExistsStmt): bool {
            $tableExistsStmt->execute([$table]);
            return (int)$tableExistsStmt->fetchColumn() > 0;
        };

        // Create housekeeping_audit_log table if not exists
        if (!$tableExists('housekeeping_audit_log')) {
            $pdo->exec("CREATE TABLE housekeeping_audit_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                assignment_id INT UNSIGNED NOT NULL COMMENT 'FK to housekeeping_assignments.id',
                action ENUM('created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created') NOT NULL COMMENT 'Type of action performed',
                old_values JSON NULL COMMENT 'Snapshot of data before change',
                new_values JSON NULL COMMENT 'Snapshot of data after change',
                changed_fields JSON NULL COMMENT 'Array of field names that changed',
                performed_by INT UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
                performed_by_name VARCHAR(255) DEFAULT NULL COMMENT 'Username for historical accuracy',
                performed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
                ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
                user_agent VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent (optional, for context)',
                PRIMARY KEY (id),
                KEY idx_housekeeping_audit_assignment (assignment_id),
                KEY idx_housekeeping_audit_action (action),
                KEY idx_housekeeping_audit_performed_by (performed_by),
                KEY idx_housekeeping_audit_performed_at (performed_at),
                CONSTRAINT fk_housekeeping_audit_assignment FOREIGN KEY (assignment_id) REFERENCES housekeeping_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_housekeeping_audit_performed_by FOREIGN KEY (performed_by) REFERENCES admin_users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for housekeeping assignments'");
        }

        // Create maintenance_audit_log table if not exists
        if (!$tableExists('maintenance_audit_log')) {
            $pdo->exec("CREATE TABLE maintenance_audit_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                maintenance_id INT UNSIGNED NOT NULL COMMENT 'FK to room_maintenance_schedules.id',
                action ENUM('created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created', 'type_changed') NOT NULL COMMENT 'Type of action performed',
                old_values JSON NULL COMMENT 'Snapshot of data before change',
                new_values JSON NULL COMMENT 'Snapshot of data after change',
                changed_fields JSON NULL COMMENT 'Array of field names that changed',
                performed_by INT UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
                performed_by_name VARCHAR(255) DEFAULT NULL COMMENT 'Username for historical accuracy',
                performed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
                ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
                user_agent VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent (optional, for context)',
                PRIMARY KEY (id),
                KEY idx_maintenance_audit_maintenance (maintenance_id),
                KEY idx_maintenance_audit_action (action),
                KEY idx_maintenance_audit_performed_by (performed_by),
                KEY idx_maintenance_audit_performed_at (performed_at),
                CONSTRAINT fk_maintenance_audit_maintenance FOREIGN KEY (maintenance_id) REFERENCES room_maintenance_schedules (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_maintenance_audit_performed_by FOREIGN KEY (performed_by) REFERENCES admin_users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for maintenance schedules'");
        }
    } catch (Throwable $e) {
        error_log('ensureAuditLogTables warning: ' . $e->getMessage());
    }
}

/**
 * Calculate changed fields between two arrays.
 *
 * @param array $oldData Old data
 * @param array $newData New data
 * @return array List of field names that changed
 */
function calculateChangedFields(array $oldData, array $newData): array {
    $changedFields = [];
    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    
    foreach ($allKeys as $key) {
        $oldValue = $oldData[$key] ?? null;
        $newValue = $newData[$key] ?? null;
        
        // Compare values (handle JSON encoding for arrays)
        if (is_array($oldValue)) {
            $oldValue = json_encode($oldValue);
        }
        if (is_array($newValue)) {
            $newValue = json_encode($newValue);
        }
        
        if ((string)$oldValue !== (string)$newValue) {
            $changedFields[] = $key;
        }
    }
    
    return $changedFields;
}

/**
 * Log an action for housekeeping assignment.
 *
 * @param int $assignmentId Assignment ID
 * @param string $action Action performed (created, updated, deleted, verified, etc.)
 * @param array|null $oldData Old data (before change)
 * @param array|null $newData New data (after change)
 * @param int|null $performedBy User ID who performed the action
 * @param string|null $performedByName Username for historical accuracy
 * @return bool Success status
 */
if (!function_exists('logHousekeepingAction')) {
    function logHousekeepingAction(int $assignmentId, string $action, ?array $oldData, ?array $newData, ?int $performedBy, ?string $performedByName = null): bool {
    global $pdo;
    
    try {
        // Ensure audit tables exist
        ensureAuditLogTables($pdo);
        
        // Calculate changed fields
        $changedFields = null;
        if ($oldData !== null && $newData !== null) {
            $changedFields = calculateChangedFields($oldData, $newData);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO housekeeping_audit_log (
                assignment_id, action, old_values, new_values, changed_fields,
                performed_by, performed_by_name, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $assignmentId,
            $action,
            $oldData !== null ? json_encode($oldData) : null,
            $newData !== null ? json_encode($newData) : null,
            $changedFields !== null ? json_encode($changedFields) : null,
            $performedBy,
            $performedByName,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
    } catch (Throwable $e) {
        error_log('logHousekeepingAction error: ' . $e->getMessage());
        return false;
    }
    }
}

/**
 * Log an action for maintenance schedule.
 *
 * @param int $maintenanceId Maintenance ID
 * @param string $action Action performed (created, updated, deleted, verified, etc.)
 * @param array|null $oldData Old data (before change)
 * @param array|null $newData New data (after change)
 * @param int|null $performedBy User ID who performed the action
 * @param string|null $performedByName Username for historical accuracy
 * @return bool Success status
 */
if (!function_exists('logMaintenanceAction')) {
    function logMaintenanceAction(int $maintenanceId, string $action, ?array $oldData, ?array $newData, ?int $performedBy, ?string $performedByName = null): bool {
    global $pdo;
    
    try {
        // Ensure audit tables exist
        ensureAuditLogTables($pdo);
        
        // Calculate changed fields
        $changedFields = null;
        if ($oldData !== null && $newData !== null) {
            $changedFields = calculateChangedFields($oldData, $newData);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_audit_log (
                maintenance_id, action, old_values, new_values, changed_fields,
                performed_by, performed_by_name, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $maintenanceId,
            $action,
            $oldData !== null ? json_encode($oldData) : null,
            $newData !== null ? json_encode($newData) : null,
            $changedFields !== null ? json_encode($changedFields) : null,
            $performedBy,
            $performedByName,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
    } catch (Throwable $e) {
        error_log('logMaintenanceAction error: ' . $e->getMessage());
        return false;
    }
    }
}

/**
 * Get audit log history for a housekeeping assignment.
 *
 * @param int $assignmentId Assignment ID
 * @return array List of audit log entries
 */
if (!function_exists('getHousekeepingAuditLog')) {
    function getHousekeepingAuditLog(int $assignmentId): array {
    global $pdo;
    
    try {
        ensureAuditLogTables($pdo);
        
        $stmt = $pdo->prepare("
            SELECT * FROM housekeeping_audit_log
            WHERE assignment_id = ?
            ORDER BY performed_at DESC
        ");
        $stmt->execute([$assignmentId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$row) {
            if ($row['old_values'] !== null) {
                $row['old_values'] = json_decode($row['old_values'], true);
            }
            if ($row['new_values'] !== null) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
            if ($row['changed_fields'] !== null) {
                $row['changed_fields'] = json_decode($row['changed_fields'], true);
            }
        }
        
        return $results;
    } catch (Throwable $e) {
        error_log('getHousekeepingAuditLog error: ' . $e->getMessage());
        return [];
    }
    }
}

/**
 * Get audit log history for a maintenance schedule.
 *
 * @param int $maintenanceId Maintenance ID
 * @return array List of audit log entries
 */
if (!function_exists('getMaintenanceAuditLog')) {
    function getMaintenanceAuditLog(int $maintenanceId): array {
    global $pdo;
    
    try {
        ensureAuditLogTables($pdo);
        
        $stmt = $pdo->prepare("
            SELECT * FROM maintenance_audit_log
            WHERE maintenance_id = ?
            ORDER BY performed_at DESC
        ");
        $stmt->execute([$maintenanceId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$row) {
            if ($row['old_values'] !== null) {
                $row['old_values'] = json_decode($row['old_values'], true);
            }
            if ($row['new_values'] !== null) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
            if ($row['changed_fields'] !== null) {
                $row['changed_fields'] = json_decode($row['changed_fields'], true);
            }
        }
        
        return $results;
    } catch (Throwable $e) {
        error_log('getMaintenanceAuditLog error: ' . $e->getMessage());
        return [];
    }
    }
}

/**
 * Get all audit log entries for housekeeping (admin view).
 *
 * @param int|null $limit Optional limit
 * @param int|null $offset Optional offset
 * @return array List of audit log entries with related data
 */
function getAllHousekeepingAuditLogs(?int $limit = null, ?int $offset = null): array {
    global $pdo;
    
    try {
        ensureAuditLogTables($pdo);
        
        $sql = "
            SELECT hal.*,
                   ha.individual_room_id,
                   ir.room_number,
                   ir.room_name,
                   ha.status as current_status
            FROM housekeeping_audit_log hal
            LEFT JOIN housekeeping_assignments ha ON hal.assignment_id = ha.id
            LEFT JOIN individual_rooms ir ON ha.individual_room_id = ir.id
            ORDER BY hal.performed_at DESC
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$row) {
            if ($row['old_values'] !== null) {
                $row['old_values'] = json_decode($row['old_values'], true);
            }
            if ($row['new_values'] !== null) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
            if ($row['changed_fields'] !== null) {
                $row['changed_fields'] = json_decode($row['changed_fields'], true);
            }
        }
        
        return $results;
    } catch (Throwable $e) {
        error_log('getAllHousekeepingAuditLogs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all audit log entries for maintenance (admin view).
 *
 * @param int|null $limit Optional limit
 * @param int|null $offset Optional offset
 * @return array List of audit log entries with related data
 */
function getAllMaintenanceAuditLogs(?int $limit = null, ?int $offset = null): array {
    global $pdo;
    
    try {
        ensureAuditLogTables($pdo);
        
        $sql = "
            SELECT mal.*,
                   rms.individual_room_id,
                   ir.room_number,
                   ir.room_name,
                   rms.status as current_status,
                   rms.title
            FROM maintenance_audit_log mal
            LEFT JOIN room_maintenance_schedules rms ON mal.maintenance_id = rms.id
            LEFT JOIN individual_rooms ir ON rms.individual_room_id = ir.id
            ORDER BY mal.performed_at DESC
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$row) {
            if ($row['old_values'] !== null) {
                $row['old_values'] = json_decode($row['old_values'], true);
            }
            if ($row['new_values'] !== null) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
            if ($row['changed_fields'] !== null) {
                $row['changed_fields'] = json_decode($row['changed_fields'], true);
            }
        }
        
        return $results;
    } catch (Throwable $e) {
        error_log('getAllMaintenanceAuditLogs error: ' . $e->getMessage());
        return [];
    }
}
