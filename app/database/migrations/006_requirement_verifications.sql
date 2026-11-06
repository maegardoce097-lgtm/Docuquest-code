ALTER TABLE `document_requests`
    ADD COLUMN `requirement_verifications` TEXT DEFAULT NULL AFTER `requirements`;
