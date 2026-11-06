ALTER TABLE `request_files`
    ADD COLUMN `review_status` ENUM('pending','validated','rejected') NOT NULL DEFAULT 'pending' AFTER `requirement_text`,
    ADD COLUMN `reviewed_by` INT(11) UNSIGNED DEFAULT NULL AFTER `review_status`,
    ADD COLUMN `reviewed_at` DATETIME DEFAULT NULL AFTER `reviewed_by`,
    ADD KEY `request_files_review_status_idx` (`request_id`, `requirement_index`, `review_status`),
    ADD CONSTRAINT `request_files_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

UPDATE `request_files` f
INNER JOIN `document_requests` r ON r.`id` = f.`request_id`
SET f.`review_status` = 'validated',
        f.`reviewed_at` = f.`created_at`
WHERE f.`requirement_index` IS NOT NULL
    AND JSON_VALID(r.`requirement_verifications`)
    AND JSON_CONTAINS(CAST(r.`requirement_verifications` AS JSON), CAST(f.`requirement_index` AS JSON));