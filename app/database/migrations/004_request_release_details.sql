ALTER TABLE `document_requests`
    ADD COLUMN `requirements` TEXT DEFAULT NULL AFTER `admin_note`,
    ADD COLUMN `release_at` DATETIME DEFAULT NULL AFTER `requirements`;

ALTER TABLE `request_updates`
    ADD COLUMN `requirements` TEXT DEFAULT NULL AFTER `note`,
    ADD COLUMN `release_at` DATETIME DEFAULT NULL AFTER `requirements`;