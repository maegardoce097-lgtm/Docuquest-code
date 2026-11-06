CREATE TABLE IF NOT EXISTS `request_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(80) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `request_files_request_idx` (`request_id`),
    CONSTRAINT `request_files_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `request_files_uploader_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;