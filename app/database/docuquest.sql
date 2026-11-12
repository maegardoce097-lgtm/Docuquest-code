-- DocuQuest application schema for MySQL / Aiven MySQL.
-- Creates the project database when your MySQL account has permission.
CREATE DATABASE IF NOT EXISTS `docuquest`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `docuquest`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','moderator','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_idx` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `email_hash` char(64) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`email_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `homepage_settings` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `content_json` longtext NOT NULL,
  `hero_background_stored_name` varchar(80) DEFAULT NULL,
  `hero_background_mime_type` varchar(100) DEFAULT NULL,
  `hero_seal_stored_name` varchar(80) DEFAULT NULL,
  `hero_seal_mime_type` varchar(100) DEFAULT NULL,
  `updated_by` int(11) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `homepage_settings_admin_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_requests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `address` varchar(500) NOT NULL,
  `request_number` varchar(24) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'submitted',
  `progress` tinyint unsigned NOT NULL DEFAULT 10,
  `admin_note` varchar(1000) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `requirement_verifications` text DEFAULT NULL,
  `release_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number_unique` (`request_number`),
  KEY `request_user_idx` (`user_id`),
  KEY `request_status_idx` (`status`),
  CONSTRAINT `document_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL DEFAULT 'paymongo',
  `reference_no` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_request_unique` (`request_id`),
  KEY `payment_user_status_idx` (`user_id`, `status`),
  CONSTRAINT `payment_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_updates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `actor_id` int(11) unsigned NOT NULL,
  `status` varchar(32) DEFAULT NULL,
  `note` varchar(1000) NOT NULL,
  `requirements` text DEFAULT NULL,
  `release_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `updates_request_idx` (`request_id`),
  KEY `updates_actor_idx` (`actor_id`),
  CONSTRAINT `request_updates_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_updates_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `sender_id` int(11) unsigned NOT NULL,
  `body` varchar(2000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `messages_request_idx` (`request_id`),
  KEY `messages_sender_idx` (`sender_id`),
  CONSTRAINT `request_messages_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_message_files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(11) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_file_unique` (`message_id`),
  CONSTRAINT `message_file_message_fk` FOREIGN KEY (`message_id`) REFERENCES `request_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `uploaded_by` int(11) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(11) unsigned NOT NULL,
  `requirement_index` smallint unsigned DEFAULT NULL,
  `requirement_text` text DEFAULT NULL,
  `review_status` enum('pending','validated','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `request_files_request_idx` (`request_id`),
  KEY `request_files_requirement_idx` (`request_id`, `requirement_index`),
  KEY `request_files_review_status_idx` (`request_id`, `requirement_index`, `review_status`),
  CONSTRAINT `request_files_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_files_uploader_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_files_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_registry_reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `report_number` varchar(40) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `record_count` int(11) unsigned NOT NULL DEFAULT 0,
  `snapshot_json` longtext NOT NULL,
  `created_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saved_report_number_unique` (`report_number`),
  KEY `saved_report_period_idx` (`start_date`, `end_date`),
  CONSTRAINT `saved_report_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `request_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(500) NOT NULL,
  `is_read` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notifications_user_read_idx` (`user_id`, `is_read`, `id`),
  KEY `notifications_request_idx` (`request_id`),
  CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `staff_service_rating` tinyint unsigned DEFAULT NULL,
  `instructions_clarity_rating` tinyint unsigned DEFAULT NULL,
  `status_updates_rating` tinyint unsigned DEFAULT NULL,
  `processing_time_rating` tinyint unsigned DEFAULT NULL,
  `overall_satisfaction_rating` tinyint unsigned DEFAULT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `survey_json` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_feedback_request_unique` (`request_id`),
  KEY `request_feedback_user_idx` (`user_id`),
  CONSTRAINT `request_feedback_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_feedback_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
