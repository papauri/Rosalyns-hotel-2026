<?php
/**
 * User Permissions Helper
 * Comprehensive Role-based Access Control (RBAC) for the admin panel
 * 
 * Features:
 * - Multiple roles with customizable permissions
 * - Granular permission control
 * - Permission groups for easier management
 * - Activity logging
 * - Role templates
 * 
 * @version 2.0.0
 */

// ============================================
// ROLE DEFINITIONS
// ============================================

/**
 * Get all available roles with their metadata
 */
function getAllRoles() {
    return [
        'admin' => [
            'label' => 'Administrator',
            'description' => 'Full access to all features and settings',
            'icon' => 'fa-crown',
            'color' => '#d4a843',
            'level' => 100,
            'is_system' => true, // Cannot be deleted
            'permissions' => null // null = all permissions
        ],
        'manager' => [
            'label' => 'Manager',
            'description' => 'Manage operations, bookings, and staff activities',
            'icon' => 'fa-user-tie',
            'color' => '#17a2b8',
            'level' => 75,
            'is_system' => true,
            'permissions' => [
                'dashboard', 'bookings', 'calendar', 'blocked_dates', 
                'rooms', 'room_maintenance', 'housekeeping', 'gallery', 
                'media_management', 'media_create', 'media_edit',
                'conference', 'gym', 'menu', 'events', 'reviews',
                'accounting', 'payments', 'invoices', 'payment_add', 'reports',
                'booking_settings', 'visitor_analytics',
                'create_booking', 'edit_booking', 'cancel_booking',
                'checkin_guest', 'checkout_guest',
                'room_dashboard', 'individual_rooms', 'block_rooms'
            ]
        ],
        'receptionist' => [
            'label' => 'Receptionist',
            'description' => 'Handle front desk operations and check-ins',
            'icon' => 'fa-concierge-bell',
            'color' => '#28a745',
            'level' => 50,
            'is_system' => true,
            'permissions' => [
                'dashboard', 'bookings', 'calendar', 'blocked_dates',
                'rooms', 'room_maintenance', 'housekeeping', 'reviews', 'gym',
                'create_booking', 'checkin_guest', 'checkout_guest',
                'room_dashboard', 'individual_rooms'
            ]
        ],
        'housekeeping' => [
            'label' => 'Housekeeping',
            'description' => 'Manage room cleaning and maintenance tasks',
            'icon' => 'fa-broom',
            'color' => '#6c757d',
            'level' => 25,
            'is_system' => true,
            'permissions' => [
                'dashboard', 'housekeeping', 'room_maintenance', 
                'room_dashboard', 'individual_rooms'
            ]
        ],
        'accountant' => [
            'label' => 'Accountant',
            'description' => 'Manage financial records and invoices',
            'icon' => 'fa-calculator',
            'color' => '#fd7e14',
            'level' => 50,
            'is_system' => true,
            'permissions' => [
                'dashboard', 'accounting', 'payments', 'invoices', 
                'payment_add', 'reports', 'bookings'
            ]
        ],
        'viewer' => [
            'label' => 'Viewer',
            'description' => 'Read-only access to view bookings and reports',
            'icon' => 'fa-eye',
            'color' => '#6c757d',
            'level' => 10,
            'is_system' => true,
            'permissions' => [
                'dashboard', 'bookings', 'calendar', 'rooms', 'reports'
            ]
        ]
    ];
}

// ============================================
// PERMISSION DEFINITIONS
// ============================================

/**
 * Get all available permissions with metadata
 * Organized by category for UI display
 */
function getAllPermissions() {
    return [
        // Core
        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'View the main dashboard',
            'icon' => 'fa-tachometer-alt',
            'category' => 'Core',
            'page' => 'dashboard.php',
            'group' => 'core'
        ],
        
        // Reservations
        'bookings' => [
            'label' => 'View Bookings',
            'description' => 'View booking list and details',
            'icon' => 'fa-calendar-check',
            'category' => 'Reservations',
            'page' => 'bookings.php',
            'group' => 'bookings_read'
        ],
        'create_booking' => [
            'label' => 'Create Bookings',
            'description' => 'Create new bookings manually',
            'icon' => 'fa-plus-circle',
            'category' => 'Reservations',
            'page' => 'create-booking.php',
            'group' => 'bookings_write'
        ],
        'edit_booking' => [
            'label' => 'Edit Bookings',
            'description' => 'Modify existing bookings',
            'icon' => 'fa-edit',
            'category' => 'Reservations',
            'page' => 'booking-details.php',
            'group' => 'bookings_write'
        ],
        'cancel_booking' => [
            'label' => 'Cancel Bookings',
            'description' => 'Cancel confirmed bookings',
            'icon' => 'fa-times-circle',
            'category' => 'Reservations',
            'page' => 'bookings.php',
            'group' => 'bookings_write'
        ],
        'calendar' => [
            'label' => 'Calendar View',
            'description' => 'View booking calendar',
            'icon' => 'fa-calendar',
            'category' => 'Reservations',
            'page' => 'calendar.php',
            'group' => 'bookings_read'
        ],
        'blocked_dates' => [
            'label' => 'Blocked Dates',
            'description' => 'Manage blocked/unavailable dates',
            'icon' => 'fa-ban',
            'category' => 'Reservations',
            'page' => 'blocked-dates.php',
            'group' => 'bookings_write'
        ],
        'block_rooms' => [
            'label' => 'Block Rooms',
            'description' => 'Block individual rooms for maintenance or hold',
            'icon' => 'fa-lock',
            'category' => 'Reservations',
            'page' => 'individual-rooms.php',
            'group' => 'bookings_write'
        ],
        
        // Guest Management
        'checkin_guest' => [
            'label' => 'Check-in Guests',
            'description' => 'Process guest check-ins',
            'icon' => 'fa-sign-in-alt',
            'category' => 'Guest Services',
            'page' => 'bookings.php',
            'group' => 'guest_services'
        ],
        'checkout_guest' => [
            'label' => 'Check-out Guests',
            'description' => 'Process guest check-outs',
            'icon' => 'fa-sign-out-alt',
            'category' => 'Guest Services',
            'page' => 'bookings.php',
            'group' => 'guest_services'
        ],
        
        // Property Management
        'rooms' => [
            'label' => 'Room Management',
            'description' => 'Manage room types, prices, and facilities',
            'icon' => 'fa-bed',
            'category' => 'Property',
            'page' => 'room-management.php',
            'group' => 'rooms_read'
        ],
        'individual_rooms' => [
            'label' => 'Individual Rooms',
            'description' => 'Manage individual room assignments',
            'icon' => 'fa-door-open',
            'category' => 'Property',
            'page' => 'individual-rooms.php',
            'group' => 'rooms_read'
        ],
        'room_dashboard' => [
            'label' => 'Room Dashboard',
            'description' => 'View room status and housekeeping dashboard',
            'icon' => 'fa-th-large',
            'category' => 'Property',
            'page' => 'room-dashboard.php',
            'group' => 'rooms_read'
        ],
        'room_maintenance' => [
            'label' => 'Room Maintenance',
            'description' => 'Manage room maintenance schedules',
            'icon' => 'fa-tools',
            'category' => 'Property',
            'page' => 'room-maintenance.php',
            'group' => 'rooms_write'
        ],
        'housekeeping' => [
            'label' => 'Housekeeping',
            'description' => 'Manage housekeeping assignments',
            'icon' => 'fa-broom',
            'category' => 'Property',
            'page' => 'housekeeping.php',
            'group' => 'housekeeping'
        ],
        'gallery' => [
            'label' => 'Gallery',
            'description' => 'Manage hotel gallery images',
            'icon' => 'fa-images',
            'category' => 'Property',
            'page' => 'gallery-management.php',
            'group' => 'media'
        ],
        'media_management' => [
            'label' => 'Media Portal',
            'description' => 'Access the centralized media portal',
            'icon' => 'fa-photo-video',
            'category' => 'Property',
            'page' => 'media-management.php',
            'group' => 'media'
        ],
        'media_create' => [
            'label' => 'Create Media',
            'description' => 'Upload and create media items',
            'icon' => 'fa-upload',
            'category' => 'Property',
            'page' => 'media-management.php',
            'group' => 'media_write'
        ],
        'media_edit' => [
            'label' => 'Edit Media',
            'description' => 'Edit media items',
            'icon' => 'fa-edit',
            'category' => 'Property',
            'page' => 'media-management.php',
            'group' => 'media_write'
        ],
        'media_delete' => [
            'label' => 'Delete Media',
            'description' => 'Delete media items',
            'icon' => 'fa-trash-alt',
            'category' => 'Property',
            'page' => 'media-management.php',
            'group' => 'media_write'
        ],
        'conference' => [
            'label' => 'Conference Rooms',
            'description' => 'Manage conference facilities',
            'icon' => 'fa-briefcase',
            'category' => 'Property',
            'page' => 'conference-management.php',
            'group' => 'conference'
        ],
        'gym' => [
            'label' => 'Gym Inquiries',
            'description' => 'View gym membership inquiries',
            'icon' => 'fa-dumbbell',
            'category' => 'Property',
            'page' => 'gym-inquiries.php',
            'group' => 'gym'
        ],
        
        // Content Management
        'menu' => [
            'label' => 'Menu',
            'description' => 'Manage restaurant menu',
            'icon' => 'fa-utensils',
            'category' => 'Content',
            'page' => 'menu-management.php',
            'group' => 'content'
        ],
        'events' => [
            'label' => 'Events',
            'description' => 'Manage hotel events',
            'icon' => 'fa-calendar-alt',
            'category' => 'Content',
            'page' => 'events-management.php',
            'group' => 'content'
        ],
        'reviews' => [
            'label' => 'Reviews',
            'description' => 'Manage guest reviews',
            'icon' => 'fa-star',
            'category' => 'Content',
            'page' => 'reviews.php',
            'group' => 'content'
        ],
        
        // Finance
        'accounting' => [
            'label' => 'Accounting',
            'description' => 'View accounting dashboard',
            'icon' => 'fa-calculator',
            'category' => 'Finance',
            'page' => 'accounting-dashboard.php',
            'group' => 'accounting_read'
        ],
        'payments' => [
            'label' => 'View Payments',
            'description' => 'View payment records',
            'icon' => 'fa-money-bill-wave',
            'category' => 'Finance',
            'page' => 'payments.php',
            'group' => 'payments_read'
        ],
        'payment_add' => [
            'label' => 'Add Payments',
            'description' => 'Add new payment records',
            'icon' => 'fa-plus-circle',
            'category' => 'Finance',
            'page' => 'payment-add.php',
            'group' => 'payments_write'
        ],
        'invoices' => [
            'label' => 'Invoices',
            'description' => 'View and manage invoices',
            'icon' => 'fa-file-invoice-dollar',
            'category' => 'Finance',
            'page' => 'invoices.php',
            'group' => 'invoices'
        ],
        'reports' => [
            'label' => 'Reports',
            'description' => 'View financial and booking reports',
            'icon' => 'fa-chart-bar',
            'category' => 'Finance',
            'page' => 'reports.php',
            'group' => 'reports'
        ],
        'visitor_analytics' => [
            'label' => 'Visitor Analytics',
            'description' => 'View website visitor statistics',
            'icon' => 'fa-chart-line',
            'category' => 'Finance',
            'page' => 'visitor-analytics.php',
            'group' => 'analytics'
        ],
        
        // Settings
        'section_headers' => [
            'label' => 'Section Headers',
            'description' => 'Manage page section headers',
            'icon' => 'fa-heading',
            'category' => 'Settings',
            'page' => 'section-headers-management.php',
            'group' => 'settings_advanced'
        ],
        'booking_settings' => [
            'label' => 'Booking Settings',
            'description' => 'Configure booking system settings',
            'icon' => 'fa-cog',
            'category' => 'Settings',
            'page' => 'booking-settings.php',
            'group' => 'settings'
        ],
        'pages' => [
            'label' => 'Page Management',
            'description' => 'Enable, disable and reorder website pages',
            'icon' => 'fa-file-alt',
            'category' => 'Settings',
            'page' => 'page-management.php',
            'group' => 'settings_advanced'
        ],
        'cache' => [
            'label' => 'Cache Management',
            'description' => 'Manage website cache',
            'icon' => 'fa-bolt',
            'category' => 'Settings',
            'page' => 'cache-management.php',
            'group' => 'settings_advanced'
        ],
        'api_keys' => [
            'label' => 'API Keys',
            'description' => 'Manage API access keys',
            'icon' => 'fa-key',
            'category' => 'Settings',
            'page' => 'api-keys.php',
            'group' => 'settings_advanced'
        ],
        'whatsapp_settings' => [
            'label' => 'WhatsApp Settings',
            'description' => 'Configure WhatsApp integration',
            'icon' => 'fa-whatsapp',
            'category' => 'Settings',
            'page' => 'whatsapp-settings.php',
            'group' => 'settings'
        ],
        
        // User Management
        'user_management' => [
            'label' => 'User Management',
            'description' => 'Access user management page',
            'icon' => 'fa-users-cog',
            'category' => 'Administration',
            'page' => 'user-management.php',
            'group' => 'users_read'
        ],
        'user_create' => [
            'label' => 'Create Users',
            'description' => 'Create new admin users',
            'icon' => 'fa-user-plus',
            'category' => 'Administration',
            'page' => 'user-management.php',
            'group' => 'users_write'
        ],
        'user_edit' => [
            'label' => 'Edit Users',
            'description' => 'Edit existing admin users',
            'icon' => 'fa-user-edit',
            'category' => 'Administration',
            'page' => 'user-management.php',
            'group' => 'users_write'
        ],
        'user_delete' => [
            'label' => 'Delete Users',
            'description' => 'Delete admin users',
            'icon' => 'fa-user-minus',
            'category' => 'Administration',
            'page' => 'user-management.php',
            'group' => 'users_write'
        ],
        'user_permissions' => [
            'label' => 'Assign Permissions',
            'description' => 'Grant/revoke permissions for users',
            'icon' => 'fa-shield-alt',
            'category' => 'Administration',
            'page' => 'user-management.php',
            'group' => 'users_permissions'
        ]
    ];
}

/**
 * Get permission groups for organizing permissions in UI
 */
function getPermissionGroups() {
    return [
        'core' => ['label' => 'Core Access', 'description' => 'Basic system access'],
        'bookings_read' => ['label' => 'View Bookings', 'description' => 'Read-only booking access'],
        'bookings_write' => ['label' => 'Manage Bookings', 'description' => 'Create, edit, cancel bookings'],
        'guest_services' => ['label' => 'Guest Services', 'description' => 'Check-in and check-out'],
        'rooms_read' => ['label' => 'View Rooms', 'description' => 'View room information'],
        'rooms_write' => ['label' => 'Manage Rooms', 'description' => 'Edit room settings'],
        'housekeeping' => ['label' => 'Housekeeping', 'description' => 'Cleaning and maintenance'],
        'media' => ['label' => 'View Media', 'description' => 'Access media library'],
        'media_write' => ['label' => 'Manage Media', 'description' => 'Upload, edit, delete media'],
        'content' => ['label' => 'Content Management', 'description' => 'Menu, events, reviews'],
        'accounting_read' => ['label' => 'View Accounting', 'description' => 'Read-only financial access'],
        'payments_read' => ['label' => 'View Payments', 'description' => 'View payment records'],
        'payments_write' => ['label' => 'Manage Payments', 'description' => 'Add and edit payments'],
        'invoices' => ['label' => 'Invoices', 'description' => 'Invoice management'],
        'reports' => ['label' => 'Reports', 'description' => 'Financial and analytics reports'],
        'analytics' => ['label' => 'Analytics', 'description' => 'Visitor statistics'],
        'settings' => ['label' => 'Basic Settings', 'description' => 'Common configuration'],
        'settings_advanced' => ['label' => 'Advanced Settings', 'description' => 'System configuration'],
        'users_read' => ['label' => 'View Users', 'description' => 'View user list'],
        'users_write' => ['label' => 'Manage Users', 'description' => 'Create, edit, delete users'],
        'users_permissions' => ['label' => 'Manage Permissions', 'description' => 'Assign user permissions'],
        'conference' => ['label' => 'Conference', 'description' => 'Conference room management'],
        'gym' => ['label' => 'Gym', 'description' => 'Gym inquiries']
    ];
}

// ============================================
// PERMISSION CHECKING FUNCTIONS
// ============================================

/**
 * Get the default permissions for a specific role
 */
function getDefaultPermissionsForRole($role) {
    $roles = getAllRoles();
    
    if (!isset($roles[$role])) {
        return ['dashboard']; // Minimal access for unknown roles
    }
    
    $roleData = $roles[$role];
    
    // null means all permissions (admin)
    if ($roleData['permissions'] === null) {
        return array_keys(getAllPermissions());
    }
    
    return $roleData['permissions'];
}

/**
 * Check if a user has a specific permission
 * Admin role always has all permissions
 */
function hasPermission($user_id, $permission_key) {
    global $pdo;
    
    // Validate permission key exists
    $all_permissions = getAllPermissions();
    if (!isset($all_permissions[$permission_key])) {
        error_log("Invalid permission key: {$permission_key}");
        return false;
    }
    
    try {
        // Get user role and status
        $stmt = $pdo->prepare("SELECT role, is_active FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['is_active']) {
            return false;
        }
        
        $role = $user['role'];
        
        // Admin always has all permissions
        if ($role === 'admin') {
            return true;
        }
        
        // Check user_permissions table for explicit grant/deny
        try {
            $stmt = $pdo->prepare("
                SELECT is_granted FROM user_permissions 
                WHERE user_id = ? AND permission_key = ?
            ");
            $stmt->execute([$user_id, $permission_key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result !== false) {
                return (bool)$result['is_granted'];
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet - fall through to role defaults
        }
        
        // No explicit permission set - use role defaults
        $defaults = getDefaultPermissionsForRole($role);
        return in_array($permission_key, $defaults);
        
    } catch (PDOException $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has ANY of the specified permissions
 */
function hasAnyPermission($user_id, array $permission_keys) {
    foreach ($permission_keys as $perm) {
        if (hasPermission($user_id, $perm)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has ALL of the specified permissions
 */
function hasAllPermissions($user_id, array $permission_keys) {
    foreach ($permission_keys as $perm) {
        if (!hasPermission($user_id, $perm)) {
            return false;
        }
    }
    return true;
}

/**
 * Get all permissions for a specific user
 * Returns array of permission_key => is_granted
 */
function getUserPermissions($user_id) {
    global $pdo;
    $all_permissions = getAllPermissions();
    $result = [];
    
    try {
        // Get user role
        $stmt = $pdo->prepare("SELECT role, is_active FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['is_active']) {
            // Return empty permissions for inactive/non-existent users
            foreach ($all_permissions as $key => $info) {
                $result[$key] = false;
            }
            return $result;
        }
        
        $role = $user['role'];
        
        // Admin always has everything
        if ($role === 'admin') {
            foreach ($all_permissions as $key => $info) {
                $result[$key] = true;
            }
            return $result;
        }
        
        // Get explicit permissions (table may not exist yet)
        $explicit = [];
        try {
            $stmt = $pdo->prepare("SELECT permission_key, is_granted FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $explicit = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            // Table doesn't exist yet - use role defaults only
        }
        
        // Get role defaults
        $defaults = getDefaultPermissionsForRole($role);
        
        // Merge: explicit overrides defaults
        foreach ($all_permissions as $key => $info) {
            if (isset($explicit[$key])) {
                $result[$key] = (bool)$explicit[$key];
            } else {
                $result[$key] = in_array($key, $defaults);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Get permissions error: " . $e->getMessage());
        $defaults = getDefaultPermissionsForRole('viewer');
        foreach ($all_permissions as $key => $info) {
            $result[$key] = in_array($key, $defaults);
        }
    }
    
    return $result;
}

// ============================================
// PERMISSION MANAGEMENT FUNCTIONS
// ============================================

/**
 * Set permissions for a user
 * $permissions = ['bookings' => true, 'accounting' => false, ...]
 */
function setUserPermissions($user_id, $permissions, $granted_by = null) {
    global $pdo;
    
    try {
        // Don't allow modifying admin permissions
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        
        if ($role === 'admin') {
            return false; // Admin permissions cannot be changed
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, permission_key, is_granted, granted_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), granted_by = VALUES(granted_by), updated_at = NOW()
        ");
        
        foreach ($permissions as $key => $granted) {
            $stmt->execute([$user_id, $key, $granted ? 1 : 0, $granted_by]);
        }
        
        // Log the permission change
        logPermissionChange($user_id, $permissions, $granted_by);
        
        return true;
    } catch (PDOException $e) {
        error_log("Set permissions error: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset user permissions to role defaults
 */
function resetUserPermissionsToDefault($user_id, $reset_by = null) {
    global $pdo;
    
    try {
        // Get user role
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        
        if ($role === 'admin') {
            return false;
        }
        
        // Delete all explicit permissions
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Log the reset
        if ($reset_by) {
            logActivity($reset_by, 'permissions_reset', "Reset permissions for user ID {$user_id} to role defaults");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Reset permissions error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log permission changes for audit trail
 */
function logPermissionChange($user_id, $permissions, $changed_by) {
    if (!$changed_by) return;
    
    $granted = array_keys(array_filter($permissions));
    $revoked = array_keys(array_filter($permissions, function($v) { return !$v; }));
    
    $details = [];
    if (!empty($granted)) $details[] = "Granted: " . implode(', ', $granted);
    if (!empty($revoked)) $details[] = "Revoked: " . implode(', ', $revoked);
    
    logActivity($changed_by, 'permissions_changed', "Updated permissions for user ID {$user_id}: " . implode('; ', $details));
}

/**
 * Log activity to admin_activity_log
 */
function logActivity($user_id, $action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

// ============================================
// ACCESS CONTROL FUNCTIONS
// ============================================

/**
 * Check if current page requires permission and redirect if not allowed
 */
function requirePermission($permission_key) {
    if (!isset($_SESSION['admin_user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    if (!hasPermission($_SESSION['admin_user_id'], $permission_key)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Map a page filename to its permission key
 */
function getPermissionForPage($page) {
    $map = [
        'dashboard.php' => 'dashboard',
        'bookings.php' => 'bookings',
        'booking-details.php' => 'bookings',
        'create-booking.php' => 'create_booking',
        'tentative-bookings.php' => 'bookings',
        'calendar.php' => 'calendar',
        'blocked-dates.php' => 'blocked_dates',
        'room-management.php' => 'rooms',
        'individual-rooms.php' => 'individual_rooms',
        'room-dashboard.php' => 'room_dashboard',
        'room-maintenance.php' => 'room_maintenance',
        'housekeeping.php' => 'housekeeping',
        'gallery-management.php' => 'gallery',
        'media-management.php' => 'media_management',
        'conference-management.php' => 'conference',
        'gym-inquiries.php' => 'gym',
        'menu-management.php' => 'menu',
        'events-management.php' => 'events',
        'reviews.php' => 'reviews',
        'accounting-dashboard.php' => 'accounting',
        'payments.php' => 'payments',
        'payment-details.php' => 'payments',
        'invoices.php' => 'invoices',
        'payment-add.php' => 'payment_add',
        'reports.php' => 'reports',
        'visitor-analytics.php' => 'visitor_analytics',
        'section-headers-management.php' => 'section_headers',
        'booking-settings.php' => 'booking_settings',
        'page-management.php' => 'pages',
        'cache-management.php' => 'cache',
        'api-keys.php' => 'api_keys',
        'whatsapp-settings.php' => 'whatsapp_settings',
        'user-management.php' => 'user_management',
        'process-checkin.php' => 'bookings',
    ];
    
    return $map[$page] ?? null;
}

// ============================================
// NAVIGATION HELPERS
// ============================================

/**
 * Get the allowed navigation items for a user
 * Returns filtered array of nav items the user can access
 */
function getNavItemsForUser($user_id) {
    $permissions = getUserPermissions($user_id);
    $all_permissions = getAllPermissions();
    $nav_items = [];
    
    foreach ($all_permissions as $key => $info) {
        if (isset($permissions[$key]) && $permissions[$key]) {
            $nav_items[] = [
                'key' => $key,
                'label' => $info['label'],
                'icon' => $info['icon'],
                'page' => $info['page'],
                'category' => $info['category']
            ];
        }
    }
    
    return $nav_items;
}

/**
 * Get navigation categories for organizing menu
 */
function getNavCategories() {
    return [
        'Core' => ['order' => 1, 'icon' => 'fa-home'],
        'Reservations' => ['order' => 2, 'icon' => 'fa-calendar-check'],
        'Guest Services' => ['order' => 3, 'icon' => 'fa-concierge-bell'],
        'Property' => ['order' => 4, 'icon' => 'fa-building'],
        'Content' => ['order' => 5, 'icon' => 'fa-file-alt'],
        'Finance' => ['order' => 6, 'icon' => 'fa-dollar-sign'],
        'Settings' => ['order' => 7, 'icon' => 'fa-cog'],
        'Administration' => ['order' => 8, 'icon' => 'fa-shield-alt']
    ];
}

// ============================================
// USER ROLE HELPERS
// ============================================

/**
 * Check if a user's role level is at least a certain level
 */
function hasRoleLevel($user_id, $minimum_level) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        
        $roles = getAllRoles();
        if (!isset($roles[$role])) {
            return false;
        }
        
        return $roles[$role]['level'] >= $minimum_level;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get user's role information
 */
function getUserRole($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        
        $roles = getAllRoles();
        return $roles[$role] ?? null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Check if current user can manage target user
 * (based on role levels)
 */
function canManageUser($manager_id, $target_id) {
    global $pdo;
    
    // Can't manage yourself
    if ($manager_id == $target_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$manager_id]);
        $manager_role = $stmt->fetchColumn();
        
        $stmt->execute([$target_id]);
        $target_role = $stmt->fetchColumn();
        
        $roles = getAllRoles();
        
        // Admins can manage everyone
        if ($manager_role === 'admin') {
            return true;
        }
        
        // Check role levels
        $manager_level = $roles[$manager_role]['level'] ?? 0;
        $target_level = $roles[$target_role]['level'] ?? 0;
        
        return $manager_level > $target_level;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get count of users by role
 */
function getUserCountByRole() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT role, COUNT(*) as count 
            FROM admin_users 
            WHERE is_active = 1 
            GROUP BY role
        ");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}
?>