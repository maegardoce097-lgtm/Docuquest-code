CREATE TABLE IF NOT EXISTS `homepage_settings` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `content_json` LONGTEXT NOT NULL,
    `hero_background_stored_name` VARCHAR(80) DEFAULT NULL,
    `hero_background_mime_type` VARCHAR(100) DEFAULT NULL,
    `hero_seal_stored_name` VARCHAR(80) DEFAULT NULL,
    `hero_seal_mime_type` VARCHAR(100) DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `homepage_settings_admin_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
