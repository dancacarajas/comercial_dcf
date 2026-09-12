-- ETAPA 21B / 21.2 — Sponsorship Simulation + Lead/Opportunity fields
-- Preferir: php scripts/run_migration_etapa21b_sponsorship_simulation.php
-- Este arquivo é referência DDL alinhada ao CREATE do script PHP (NOT NULL nos invariantes).

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `submission_type` VARCHAR(40) NOT NULL DEFAULT 'GENERIC_LEAD' AFTER `incentive_project_id`;

ALTER TABLE `opportunities`
  ADD COLUMN IF NOT EXISTS `sponsorship_simulation_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `quota_id`;

CREATE TABLE IF NOT EXISTS `sponsorship_simulations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `incentive_project_id` BIGINT UNSIGNED NOT NULL,
    `submission_uuid` VARCHAR(64) NOT NULL,
    `submission_version` VARCHAR(20) NOT NULL,
    `snapshot_version` VARCHAR(20) NOT NULL,
    `catalog_version` VARCHAR(20) NOT NULL,
    `briefing_schema_version` VARCHAR(20) NOT NULL,
    `policy_version` VARCHAR(20) NOT NULL,
    `engine_version` VARCHAR(20) NOT NULL,
    `presenter_version` VARCHAR(20) NULL DEFAULT NULL,
    `confirmed` TINYINT(1) NOT NULL DEFAULT 1,
    `confirmed_at` DATETIME NOT NULL,
    `investment_status` VARCHAR(40) NOT NULL,
    `investment_min` DECIMAL(14,2) NULL DEFAULT NULL,
    `investment_max` DECIMAL(14,2) NULL DEFAULT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
    `primary_tier_ref` VARCHAR(40) NOT NULL,
    `primary_axis_ref` VARCHAR(80) NULL DEFAULT NULL,
    `primary_activation_ref` VARCHAR(80) NULL DEFAULT NULL,
    `primary_property_ref` VARCHAR(80) NULL DEFAULT NULL,
    `fit_level` VARCHAR(40) NOT NULL,
    `availability_status` VARCHAR(40) NOT NULL,
    `briefing_snapshot` LONGTEXT NOT NULL,
    `interests_snapshot` LONGTEXT NOT NULL,
    `recommendation_snapshot` LONGTEXT NOT NULL,
    `display_snapshot` LONGTEXT NULL DEFAULT NULL,
    `snapshot_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sponsorship_sim_uuid` (`submission_uuid`),
    UNIQUE KEY `uniq_sponsorship_sim_lead` (`lead_id`),
    KEY `idx_sponsorship_sim_project` (`incentive_project_id`),
    KEY `idx_sponsorship_sim_tier` (`primary_tier_ref`),
    KEY `idx_sponsorship_sim_created` (`created_at`),
    CONSTRAINT `fk_sponsorship_sim_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsorship_sim_project`
        FOREIGN KEY (`incentive_project_id`) REFERENCES `incentive_projects` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
