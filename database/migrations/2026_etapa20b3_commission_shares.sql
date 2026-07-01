CREATE TABLE IF NOT EXISTS `collector_deal_shares` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collector_deal_id` BIGINT UNSIGNED NOT NULL,
    `incentive_project_id` BIGINT UNSIGNED NOT NULL,
    `collector_id` BIGINT UNSIGNED NOT NULL,
    `share_percent` DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    `status` VARCHAR(30) NOT NULL DEFAULT 'rascunho',
    `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    `archived_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_collector_deal_shares_deal` (`collector_deal_id`),
    KEY `idx_collector_deal_shares_project` (`incentive_project_id`),
    KEY `idx_collector_deal_shares_collector` (`collector_id`),
    KEY `idx_collector_deal_shares_status` (`status`),
    CONSTRAINT `fk_collector_deal_shares_deal`
        FOREIGN KEY (`collector_deal_id`) REFERENCES `collector_deals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deal_shares_project`
        FOREIGN KEY (`incentive_project_id`) REFERENCES `incentive_projects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deal_shares_collector`
        FOREIGN KEY (`collector_id`) REFERENCES `collectors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
