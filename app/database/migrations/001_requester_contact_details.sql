-- Run once in Navicat on an existing DocuQuest database.
-- Fresh installations already have these columns in docuquest.sql.
ALTER TABLE `document_requests`
  ADD COLUMN `full_name` varchar(150) NOT NULL DEFAULT '' AFTER `user_id`,
  ADD COLUMN `contact_email` varchar(255) NOT NULL DEFAULT '' AFTER `full_name`,
  ADD COLUMN `phone` varchar(25) NOT NULL DEFAULT '' AFTER `contact_email`,
  ADD COLUMN `address` varchar(500) NOT NULL DEFAULT '' AFTER `phone`;

UPDATE `document_requests` AS r
INNER JOIN `users` AS u ON u.`id` = r.`user_id`
SET r.`full_name` = u.`username`,
    r.`contact_email` = u.`email`
WHERE r.`full_name` = '' OR r.`contact_email` = '';
