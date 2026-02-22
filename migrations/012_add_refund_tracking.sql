-- Migration: Add Refund Tracking to Payments Table
-- Version: 012
-- Date: 2026-02-22
-- Description: Adds columns to track refunds, their reasons, and links to original payments

-- Add refund tracking columns to payments table
ALTER TABLE `payments`
ADD COLUMN IF NOT EXISTS `original_payment_id` INT UNSIGNED DEFAULT NULL COMMENT 'Reference to original payment being refunded' AFTER `deleted_at`,
ADD COLUMN IF NOT EXISTS `refund_reason` ENUM('early_checkout', 'late_checkout_charge', 'cancellation', 'service_issue', 'overpayment', 'other') DEFAULT NULL COMMENT 'Reason for refund or adjustment' AFTER `original_payment_id`,
ADD COLUMN IF NOT EXISTS `refund_status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT NULL COMMENT 'Status of refund processing' AFTER `refund_reason`,
ADD COLUMN IF NOT EXISTS `refund_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount being refunded (for refund type payments)' AFTER `refund_status`,
ADD COLUMN IF NOT EXISTS `refund_date_processed` TIMESTAMP NULL DEFAULT NULL COMMENT 'When refund was processed' AFTER `refund_amount`,
ADD COLUMN IF NOT EXISTS `refund_notes` TEXT DEFAULT NULL COMMENT 'Additional notes about refund' AFTER `refund_date_processed`;

-- Add index for original_payment_id to improve refund lookup performance
CREATE INDEX IF NOT EXISTS `idx_refund_original_payment` ON `payments` (`original_payment_id`);

-- Add index for refund_status to filter refunds by processing status
CREATE INDEX IF NOT EXISTS `idx_refund_status` ON `payments` (`refund_status`);

-- Add index for refund_reason to analyze refund patterns
CREATE INDEX IF NOT EXISTS `idx_refund_reason` ON `payments` (`refund_reason`);

-- Add foreign key constraint to link refunds to original payments
ALTER TABLE `payments`
ADD CONSTRAINT IF NOT EXISTS `fk_refund_original_payment`
FOREIGN KEY (`original_payment_id`) REFERENCES `payments` (`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Add check constraint to ensure refund_amount is only set for refund payment types
ALTER TABLE `payments`
ADD CONSTRAINT IF NOT EXISTS `chk_refund_amount`
CHECK (
    (`payment_type` = 'refund' AND `refund_amount` > 0) OR
    (`payment_type` != 'refund' AND `refund_amount` = 0)
);

-- Add check constraint to ensure refund_reason is set for refunds
ALTER TABLE `payments`
ADD CONSTRAINT IF NOT EXISTS `chk_refund_reason`
CHECK (
    (`payment_type` = 'refund' AND `refund_reason` IS NOT NULL) OR
    (`payment_type` != 'refund')
);
