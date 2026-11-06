ALTER TABLE `request_feedback`
    ADD COLUMN `staff_service_rating` TINYINT UNSIGNED DEFAULT NULL AFTER `rating`,
    ADD COLUMN `instructions_clarity_rating` TINYINT UNSIGNED DEFAULT NULL AFTER `staff_service_rating`,
    ADD COLUMN `status_updates_rating` TINYINT UNSIGNED DEFAULT NULL AFTER `instructions_clarity_rating`,
    ADD COLUMN `processing_time_rating` TINYINT UNSIGNED DEFAULT NULL AFTER `status_updates_rating`,
    ADD COLUMN `overall_satisfaction_rating` TINYINT UNSIGNED DEFAULT NULL AFTER `processing_time_rating`;