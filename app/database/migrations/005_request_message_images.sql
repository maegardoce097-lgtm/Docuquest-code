CREATE TABLE IF NOT EXISTS `request_message_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id` INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(80) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `message_file_unique` (`message_id`),
    CONSTRAINT `message_file_message_fk` FOREIGN KEY (`message_id`) REFERENCES `request_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
