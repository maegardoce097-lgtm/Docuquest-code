ALTER TABLE `request_feedback`
    ADD COLUMN `survey_json` MEDIUMTEXT DEFAULT NULL AFTER `comment`;