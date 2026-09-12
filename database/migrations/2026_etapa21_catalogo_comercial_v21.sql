-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 21
-- Catálogo comercial V2.1 (pricing_mode / faixa / FULL_PROJECT)
-- FASE A — DDL apenas (MySQL/MariaDB: ALTER pode causar implicit commit)
-- =====================================================================

SET NAMES utf8mb4;

-- Colunas canônicas do catálogo V2.1
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'catalog_ref_id') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `catalog_ref_id` VARCHAR(40) NULL DEFAULT NULL AFTER `commercial_name`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'catalog_version') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `catalog_version` VARCHAR(20) NULL DEFAULT NULL AFTER `catalog_ref_id`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'pricing_mode') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `pricing_mode` VARCHAR(20) NULL DEFAULT NULL AFTER `catalog_version`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'min_amount') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `min_amount` DECIMAL(14,2) NULL DEFAULT NULL AFTER `amount`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'max_amount') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `max_amount` DECIMAL(14,2) NULL DEFAULT NULL AFTER `min_amount`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas' AND COLUMN_NAME = 'inventory_mode') = 0,
        'ALTER TABLE `quotas` ADD COLUMN `inventory_mode` VARCHAR(20) NULL DEFAULT ''TRACKED'' AFTER `closed_quantity`',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Quantidade disponível pode ser indefinida (catálogo V2.1)
ALTER TABLE `quotas`
    MODIFY COLUMN `available_quantity` INT UNSIGNED NULL DEFAULT NULL,
    MODIFY COLUMN `amount` DECIMAL(14,2) NULL DEFAULT NULL;

-- Unicidade lógica do catálogo por projeto
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas'
            AND INDEX_NAME = 'uniq_quotas_project_catalog_ref') = 0,
        'ALTER TABLE `quotas` ADD UNIQUE KEY `uniq_quotas_project_catalog_ref` (`incentive_project_id`, `catalog_ref_id`)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas'
            AND INDEX_NAME = 'idx_quotas_catalog_version') = 0,
        'ALTER TABLE `quotas` ADD KEY `idx_quotas_catalog_version` (`catalog_version`)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas'
            AND INDEX_NAME = 'idx_quotas_pricing_mode') = 0,
        'ALTER TABLE `quotas` ADD KEY `idx_quotas_pricing_mode` (`pricing_mode`)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
