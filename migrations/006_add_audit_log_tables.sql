-- Migration 006: Add audit log tables for housekeeping and maintenance
-- This migration creates comprehensive audit trails for tracking all changes
-- to housekeeping_assignments and room_maintenance_schedules tables.
-- Tracks who made changes, what changed, when it changed, and the before/after values.

-- Create housekeeping_audit_log table
-- Tracks all changes to housekeeping_assignments
CREATE TABLE IF NOT EXISTS `housekeeping_audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` INT UNSIGNED NOT NULL COMMENT 'FK to housekeeping_assignments.id',
    `action` ENUM('created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created') NOT NULL COMMENT 'Type of action performed',
    `old_values` JSON NULL COMMENT 'Snapshot of data before change',
    `new_values` JSON NULL COMMENT 'Snapshot of data after change',
    `changed_fields` JSON NULL COMMENT 'Array of field names that changed',
    `performed_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
    `performed_by_name` VARCHAR(255) DEFAULT NULL COMMENT 'Username for historical accuracy',
    `performed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent (optional, for context)',
    PRIMARY KEY (`id`),
    KEY `idx_housekeeping_audit_assignment` (`assignment_id`),
    KEY `idx_housekeeping_audit_action` (`action`),
    KEY `idx_housekeeping_audit_performed_by` (`performed_by`),
    KEY `idx_housekeeping_audit_performed_at` (`performed_at`),
    CONSTRAINT `fk_housekeeping_audit_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `housekeeping_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_housekeeping_audit_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for housekeeping assignments';

-- Create maintenance_audit_log table
-- Tracks all changes to room_maintenance_schedules
CREATE TABLE IF NOT EXISTS `maintenance_audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `maintenance_id` INT UNSIGNED NOT NULL COMMENT 'FK to room_maintenance_schedules.id',
    `action` ENUM('created', 'updated', 'deleted', 'verified', 'status_changed', 'assigned', 'unassigned', 'priority_changed', 'notes_updated', 'recurring_created', 'type_changed') NOT NULL COMMENT 'Type of action performed',
    `old_values` JSON NULL COMMENT 'Snapshot of data before change',
    `new_values` JSON NULL COMMENT 'Snapshot of data after change',
    `changed_fields` JSON NULL COMMENT 'Array of field names that changed',
    `performed_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
    `performed_by_name` VARCHAR(255) DEFAULT NULL COMMENT 'Username for historical accuracy',
    `performed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent (optional, for context)',
    PRIMARY KEY (`id`),
    KEY `idx_maintenance_audit_maintenance` (`maintenance_id`),
    KEY `idx_maintenance_audit_action` (`action`),
    KEY `idx_maintenance_audit_performed_by` (`performed_by`),
    KEY `idx_maintenance_audit_performed_at` (`performed_at`),
    CONSTRAINT `fk_maintenance_audit_maintenance` FOREIGN KEY (`maintenance_id`) REFERENCES `room_maintenance_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_maintenance_audit_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for maintenance schedules';
