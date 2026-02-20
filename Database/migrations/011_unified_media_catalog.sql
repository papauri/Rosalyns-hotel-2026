-- Unified Media Catalog Migration
-- Creates centralized media management tables for the hotel website

-- Main catalog table for all media assets
CREATE TABLE IF NOT EXISTS `managed_media_catalog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `media_type` ENUM('image', 'video', 'document') NOT NULL DEFAULT 'image',
    `source_type` ENUM('upload', 'url', 'external') NOT NULL DEFAULT 'upload',
    `media_url` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NULL,
    `alt_text` VARCHAR(255) NULL,
    `caption` VARCHAR(500) NULL,
    `placement_key` VARCHAR(100) NULL COMMENT 'Logical placement identifier e.g. index_hero, room_1_gallery',
    `page_slug` VARCHAR(100) NULL COMMENT 'Page identifier e.g. index, rooms, restaurant',
    `section_key` VARCHAR(100) NULL COMMENT 'Section within page e.g. hero, gallery, featured',
    `entity_type` VARCHAR(50) NULL COMMENT 'Related entity type e.g. room, event, conference_room',
    `entity_id` INT UNSIGNED NULL COMMENT 'Related entity ID',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    INDEX `idx_placement_key` (`placement_key`),
    INDEX `idx_page_slug` (`page_slug`),
    INDEX `idx_section_key` (`section_key`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_media_type` (`media_type`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links table connecting catalog items to source tables
CREATE TABLE IF NOT EXISTS `managed_media_links` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `media_catalog_id` INT UNSIGNED NOT NULL,
    `source_table` VARCHAR(64) NOT NULL COMMENT 'Original source table name',
    `source_record_id` VARCHAR(64) NOT NULL COMMENT 'Original record ID (string to support slugs)',
    `source_column` VARCHAR(64) NOT NULL COMMENT 'Column name in source table',
    `source_context` VARCHAR(100) NULL COMMENT 'Additional context for the link',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_source_link` (`source_table`, `source_record_id`, `source_column`),
    INDEX `idx_media_catalog_id` (`media_catalog_id`),
    FOREIGN KEY (`media_catalog_id`) REFERENCES `managed_media_catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views for easy querying
CREATE OR REPLACE VIEW `v_media_by_page` AS
SELECT 
    c.*,
    GROUP_CONCAT(CONCAT(l.source_table, '.', l.source_column) SEPARATOR ', ') AS source_columns
FROM managed_media_catalog c
LEFT JOIN managed_media_links l ON l.media_catalog_id = c.id
WHERE c.is_active = 1
GROUP BY c.id
ORDER BY c.page_slug, c.section_key, c.display_order;

-- Create view for room media
CREATE OR REPLACE VIEW `v_room_media` AS
SELECT 
    c.*,
    l.source_record_id AS room_id
FROM managed_media_catalog c
INNER JOIN managed_media_links l ON l.media_catalog_id = c.id
WHERE l.source_table = 'rooms' AND c.is_active = 1
ORDER BY c.display_order;

-- Insert default media management permissions
INSERT IGNORE INTO `permissions` (`name`, `description`, `category`) VALUES
('media_create', 'Create new media items in the unified catalog', 'media'),
('media_edit', 'Edit existing media items', 'media'),
('media_delete', 'Delete media items from the catalog', 'media'),
('media_view', 'View the media management portal', 'media');

-- Get admin role ID and assign permissions
SET @admin_role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1);
IF @admin_role_id IS NOT NULL THEN
    INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT @admin_role_id, id FROM permissions WHERE name IN ('media_create', 'media_edit', 'media_delete', 'media_view');
END IF;