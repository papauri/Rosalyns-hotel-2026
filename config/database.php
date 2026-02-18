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

    // Pricing policy: null/non-positive double or triple price means occupancy is not offered
    if (array_key_exists('price_double_occupancy', $room) && ($room['price_double_occupancy'] === null || (float)$room['price_double_occupancy'] <= 0)) {
        $double = 0;
    }
    if (array_key_exists('price_triple_occupancy', $room) && ($room['price_triple_occupancy'] === null || (float)$room['price_triple_occupancy'] <= 0)) {
        $triple = 0;
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
            FROM room_blocked_dates
            WHERE block_date >= ? AND block_date < ?
            AND (room_id = ? OR room_id IS NULL)
        ";
        $blocked_stmt = $pdo->prepare($blocked_sql);
        $blocked_stmt->execute([$check_in_date, $check_out_date, $room_id]);
        $blocked_result = $blocked_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blocked_result['blocked_dates'] > 0) {
            return false; // Date is blocked
        }
        
        // Then check for overlapping bookings
        $sql = "
            SELECT COUNT(*) as bookings
            FROM bookings
            WHERE room_id = ?
            AND status IN ('pending', 'confirmed', 'checked-in')
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        ";
        $params = [$room_id, $check_in_date, $check_out_date];
        
        // Exclude a specific booking (useful when updating existing bookings)
        if ($exclude_booking_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_booking_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if number of overlapping bookings is less than available rooms
        $overlapping_bookings = $result['bookings'];
        $rooms_available = $room['rooms_available'];
        
        return $overlapping_bookings < $rooms_available;
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
        
        // Check if there are rooms available
        if ($room['rooms_available'] <= 0) {
            $result['available'] = false;
            $result['error'] = 'No rooms of this type are currently available';
            return $result;
        }
        
        // Check for blocked dates (both room-specific and global blocks)
        $blocked_sql = "
            SELECT
                id,
                room_id,
                block_date,
                block_type,
                reason
            FROM room_blocked_dates
            WHERE block_date >= ? AND block_date < ?
            AND (room_id = ? OR room_id IS NULL)
            ORDER BY block_date ASC
        ";
        $blocked_stmt = $pdo->prepare($blocked_sql);
        $blocked_stmt->execute([$check_in_date, $check_out_date, $room_id]);
        $blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($blocked_dates)) {
            $result['available'] = false;
            $result['blocked_dates'] = $blocked_dates;
            $result['error'] = 'Selected dates are not available for booking';
            
            // Build blocked dates message
            $blocked_details = [];
            foreach ($blocked_dates as $blocked) {
                $blocked_date = new DateTime($blocked['block_date']);
                $room_name = $blocked['room_id'] ? $room['name'] : 'All rooms';
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
        
        // Check for overlapping bookings
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
            AND status IN ('pending', 'confirmed', 'checked-in')
            AND NOT (check_out_date <= ? OR check_in_date >= ?)
        ";
        $params = [$room_id, $check_in_date, $check_out_date];
        
        // Exclude specific booking for updates
        if ($exclude_booking_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_booking_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if number of overlapping bookings exceeds available rooms
        $overlapping_bookings = count($conflicts);
        $rooms_available = $room['rooms_available'];
        
        if ($overlapping_bookings >= $rooms_available) {
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
 * Get blocked dates for a specific room or all rooms
 * Returns array of blocked date records
 */
function getBlockedDates($room_id = null, $start_date = null, $end_date = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT
                rbd.id,
                rbd.room_id,
                r.name as room_name,
                rbd.block_date,
                rbd.block_type,
                rbd.reason,
                rbd.created_by,
                au.username as created_by_name,
                rbd.created_at
            FROM room_blocked_dates rbd
            LEFT JOIN rooms r ON rbd.room_id = r.id
            LEFT JOIN admin_users au ON rbd.created_by = au.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($room_id !== null) {
            $sql .= " AND (rbd.room_id = ? OR rbd.room_id IS NULL)";
            $params[] = $room_id;
        }
        
        if ($start_date !== null) {
            $sql .= " AND rbd.block_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date !== null) {
            $sql .= " AND rbd.block_date <= ?";
            $params[] = $end_date;
        }
        
        $sql .= " ORDER BY rbd.block_date ASC, rbd.room_id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blocked_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $blocked_dates;
    } catch (PDOException $e) {
        error_log("Error fetching blocked dates: " . $e->getMessage());
        return [];
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
            FROM room_blocked_dates
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
 * Block a specific date for a room or all rooms
 * Returns true on success, false on failure
 */
function blockRoomDate($room_id, $block_date, $block_type = 'manual', $reason = null, $created_by = null) {
    global $pdo;
    
    try {
        // Validate block type
        $valid_types = ['maintenance', 'event', 'manual', 'full'];
        if (!in_array($block_type, $valid_types)) {
            $block_type = 'manual';
        }
        
        // Check if date is already blocked
        $check_sql = "
            SELECT id FROM room_blocked_dates
            WHERE room_id " . ($room_id === null ? "IS NULL" : "= ?") . "
            AND block_date = ?
        ";
        $check_params = $room_id === null ? [$block_date] : [$room_id, $block_date];
        
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute($check_params);
        
        if ($check_stmt->fetch()) {
            // Date already blocked, update instead
            $update_sql = "
                UPDATE room_blocked_dates
                SET block_type = ?, reason = ?, created_by = ?
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
            INSERT INTO room_blocked_dates (room_id, block_date, block_type, reason, created_by)
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
 * Unblock a specific date for a room or all rooms
 * Returns true on success, false on failure
 */
function unblockRoomDate($room_id, $block_date) {
    global $pdo;
    
    try {
        $sql = "
            DELETE FROM room_blocked_dates
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
 * Block multiple dates for a room or all rooms
 * Returns number of dates blocked
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
 * Unblock multiple dates for a room or all rooms
 * Returns number of dates unblocked
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
            // Check for booking conflicts
            $conflictSql = "
                SELECT COUNT(*) as count
                FROM bookings b
                WHERE b.individual_room_id = ?
                AND b.status IN ('pending', 'confirmed', 'checked-in')
                AND NOT (b.check_out_date <= ? OR b.check_in_date >= ?)
            ";
            
            $params = [$room['id'], $checkIn, $checkOut];
            
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

        // Check for booking conflicts
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
            AND b.status IN ('pending', 'confirmed', 'checked-in')
            AND NOT (b.check_out_date <= ? OR b.check_in_date >= ?)
        ";

        $params = [$individualRoomId, $checkIn, $checkOut];
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
        $bookingStmt = $pdo->prepare("SELECT id, room_id, check_in_date, check_out_date, number_of_nights, number_of_guests, child_guests, occupancy_type, total_amount FROM bookings WHERE id = ?");
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
