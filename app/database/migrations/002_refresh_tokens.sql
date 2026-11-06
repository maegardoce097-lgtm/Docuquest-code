CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token` TEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `jti` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;