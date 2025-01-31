CREATE TABLE IF NOT EXISTS `#__formeacustom_forms` (
    `id` int NOT NULL AUTO_INCREMENT,
    `form_id` int NOT NULL DEFAULT 0,
    `user_id` int NOT NULL DEFAULT 0,
    `user_email` varchar(100) NOT NULL DEFAULT '',
    `submission_deadline` datetime DEFAULT NULL,
    `invitation_sent` tinyint NOT NULL DEFAULT 0,
    `invitation_date` datetime DEFAULT NULL,
    `token` varchar(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_formuser` (`form_id`,`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__formeacustom_submissions` (
    `id` int NOT NULL AUTO_INCREMENT,
    `submission_id` int NOT NULL DEFAULT 0,
    `form_id` int NOT NULL DEFAULT 0,
    `form_type` tinyint NOT NULL DEFAULT 0,
    `form_submit` tinyint NOT NULL DEFAULT 0,
    `page_id` int NOT NULL DEFAULT 0,
    `page_title` varchar(255) DEFAULT '',
    `user_id` int NOT NULL DEFAULT 0,
    `user_email` varchar(100) NOT NULL DEFAULT '',
    `modified_date` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_submission_id` (`submission_id`),
    KEY `idx_formuser` (`form_id`,`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

