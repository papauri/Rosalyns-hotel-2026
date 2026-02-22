-- Migration 005: Add enhancements to room_maintenance_schedules table
-- This migration adds features mirroring housekeeping enhancements:
-- - Due date validation support (due_date column)
-- - Maintenance types (repair, replacement, inspection, upgrade, emergency)
-- - Recurring tasks support (is_recurring, recurring_pattern, recurring_end_date)
-- - Staff workload tracking (estimated_duration, actual_duration)
-- - Status progression workflow (completed_at, verified_by, verified_at)
-- - Booking linkage (linked_booking_id)
-- - Auto-creation flag (auto_created)

-- Add due_date column for deadline tracking
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `due_date` date DEFAULT NULL COMMENT 'Due date for maintenance completion (cannot be in the past)' AFTER `end_date`;

-- Add maintenance_type column for categorizing work
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `maintenance_type` enum('repair','replacement','inspection','upgrade','emergency') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'repair' COMMENT 'Type of maintenance work' AFTER `priority`;

-- Add recurring task support
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `is_recurring` tinyint(1) DEFAULT '0' COMMENT 'Whether this is a recurring maintenance task' AFTER `maintenance_type`,
ADD COLUMN `recurring_pattern` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pattern for recurring tasks' AFTER `is_recurring`,
ADD COLUMN `recurring_end_date` date DEFAULT NULL COMMENT 'End date for recurring tasks (NULL = no end)' AFTER `recurring_pattern`;

-- Add duration tracking for workload management
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `estimated_duration` int DEFAULT '60' COMMENT 'Estimated duration in minutes' AFTER `recurring_end_date`,
ADD COLUMN `actual_duration` int DEFAULT NULL COMMENT 'Actual duration in minutes (filled when completed)' AFTER `estimated_duration`;

-- Add status progression workflow support
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When maintenance was marked completed' AFTER `actual_duration`,
ADD COLUMN `verified_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user who verified the work' AFTER `completed_at`,
ADD COLUMN `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When maintenance was verified' AFTER `verified_by`;

-- Add booking linkage for maintenance affecting bookings
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `linked_booking_id` int UNSIGNED DEFAULT NULL COMMENT 'Booking ID affected by this maintenance' AFTER `verified_at`;

-- Add auto-creation flag for system-generated tasks
ALTER TABLE `room_maintenance_schedules`
ADD COLUMN `auto_created` tinyint(1) DEFAULT '0' COMMENT 'Whether this was auto-created by the system' AFTER `linked_booking_id`;

-- Add index for due date queries
ALTER TABLE `room_maintenance_schedules`
ADD INDEX `idx_due_date` (`due_date`);

-- Add index for recurring tasks queries
ALTER TABLE `room_maintenance_schedules`
ADD INDEX `idx_recurring` (`is_recurring`, `recurring_pattern`);

-- Add index for staff workload queries
ALTER TABLE `room_maintenance_schedules`
ADD INDEX `idx_assigned_status` (`assigned_to`, `status`);

-- Add foreign key for verified_by
ALTER TABLE `room_maintenance_schedules`
ADD CONSTRAINT `fk_maintenance_verified_by`
FOREIGN KEY (`verified_by`) REFERENCES `admin_users` (`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Add foreign key for linked_booking_id
ALTER TABLE `room_maintenance_schedules`
ADD CONSTRAINT `fk_maintenance_linked_booking`
FOREIGN KEY (`linked_booking_id`) REFERENCES `bookings` (`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;
