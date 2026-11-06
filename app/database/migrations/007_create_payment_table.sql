CREATE TABLE IF NOT EXISTS `payment` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `method` VARCHAR(50) NOT NULL DEFAULT 'paymongo',
    `reference_no` VARCHAR(100) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_request_unique` (`request_id`),
    KEY `payment_user_status_idx` (`user_id`, `status`),
    CONSTRAINT `payment_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payment_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;