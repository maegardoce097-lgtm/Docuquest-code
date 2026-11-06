CREATE TABLE IF NOT EXISTS `saved_registry_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_number` VARCHAR(40) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `record_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `snapshot_json` LONGTEXT NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `saved_report_number_unique` (`report_number`),
    KEY `saved_report_period_idx` (`start_date`, `end_date`),
    CONSTRAINT `saved_report_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
