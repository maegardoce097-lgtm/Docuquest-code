CREATE TABLE IF NOT EXISTS `request_feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_feedback_request_unique` (`request_id`),
  KEY `request_feedback_user_idx` (`user_id`),
  CONSTRAINT `request_feedback_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_feedback_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
