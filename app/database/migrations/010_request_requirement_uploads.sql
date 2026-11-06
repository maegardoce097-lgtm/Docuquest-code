ALTER TABLE `request_files`
    ADD COLUMN `requirement_index` SMALLINT UNSIGNED DEFAULT NULL AFTER `size_bytes`,
    ADD COLUMN `requirement_text` TEXT DEFAULT NULL AFTER `requirement_index`,
    ADD KEY `request_files_requirement_idx` (`request_id`, `requirement_index`);
