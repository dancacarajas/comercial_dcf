-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 7
-- Módulo: Cotas de Patrocínio + integração com Oportunidades
--
-- Banco: MariaDB 10.3+ (usa ADD COLUMN/INDEX IF NOT EXISTS e SQL dinâmico
-- para a FK, garantindo idempotência).
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Cria a tabela `quotas`, adiciona `quota_id` e `quota_reserved_until` em
-- `opportunities` (mantendo `quota_interest` por compatibilidade), semeia as
-- cotas oficiais e cria/atribui as permissões quotas.create / quotas.edit.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Tabela quotas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotas` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `name`               VARCHAR(120)    NOT NULL,
    `commercial_name`    VARCHAR(160)    NULL DEFAULT NULL,

    `amount`             DECIMAL(12,2)   NULL DEFAULT NULL,

    `available_quantity` INT UNSIGNED    NOT NULL DEFAULT 0,
    `reserved_quantity`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `closed_quantity`    INT UNSIGNED    NOT NULL DEFAULT 0,

    `description`        TEXT            NULL DEFAULT NULL,
    `ideal_profile`      TEXT            NULL DEFAULT NULL,

    `status`             VARCHAR(40)     NOT NULL DEFAULT 'disponivel',
    `display_order`      INT UNSIGNED    NOT NULL DEFAULT 0,

    `notes`              TEXT            NULL DEFAULT NULL,

    `created_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME        NULL DEFAULT NULL,
    `archived_at`        DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_quotas_name`          (`name`),
    KEY `idx_quotas_status`        (`status`),
    KEY `idx_quotas_amount`        (`amount`),
    KEY `idx_quotas_display_order` (`display_order`),
    KEY `idx_quotas_archived_at`   (`archived_at`),

    CONSTRAINT `fk_quotas_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_quotas_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) Integração em opportunities (idempotente)
-- ---------------------------------------------------------------------
ALTER TABLE `opportunities`
    ADD COLUMN IF NOT EXISTS `quota_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `quota_interest`,
    ADD COLUMN IF NOT EXISTS `quota_reserved_until` DATETIME NULL DEFAULT NULL AFTER `quota_id`;

ALTER TABLE `opportunities`
    ADD INDEX IF NOT EXISTS `idx_opportunities_quota` (`quota_id`),
    ADD INDEX IF NOT EXISTS `idx_opportunities_quota_reserved_until` (`quota_reserved_until`);

-- FK quota_id -> quotas(id): adiciona apenas se ainda não existir.
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'opportunities'
       AND CONSTRAINT_NAME = 'fk_opportunities_quota'
);
SET @fk_sql := IF(@fk_exists = 0,
    'ALTER TABLE `opportunities` ADD CONSTRAINT `fk_opportunities_quota` FOREIGN KEY (`quota_id`) REFERENCES `quotas`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3) Seed das cotas oficiais (idempotente por `name`)
-- ---------------------------------------------------------------------
INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `created_at`)
SELECT * FROM (SELECT 'Cota Apresenta' AS n, 'Cota Apresenta' AS cn, 200000.00 AS a, 1 AS q, 1 AS o, 'disponivel' AS s, 'master_apresentacao' AS ip, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Cota Apresenta');

INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `created_at`)
SELECT * FROM (SELECT 'Cota Carajás' AS n, 'Cota Carajás' AS cn, 100000.00 AS a, 1 AS q, 2 AS o, 'disponivel' AS s, 'grande_patrocinador' AS ip, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Cota Carajás');

INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `created_at`)
SELECT * FROM (SELECT 'Cota Movimento' AS n, 'Cota Movimento' AS cn, 50000.00 AS a, 2 AS q, 3 AS o, 'disponivel' AS s, 'patrocinador_medio' AS ip, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Cota Movimento');

INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `created_at`)
SELECT * FROM (SELECT 'Cota Formação' AS n, 'Cota Formação' AS cn, 25000.00 AS a, 2 AS q, 4 AS o, 'disponivel' AS s, 'formacao_educacao' AS ip, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Cota Formação');

INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `created_at`)
SELECT * FROM (SELECT 'Cota Incentivador' AS n, 'Cota Incentivador' AS cn, 10448.00 AS a, 1 AS q, 5 AS o, 'disponivel' AS s, 'incentivador_final' AS ip, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Cota Incentivador');

INSERT INTO `quotas` (`name`, `commercial_name`, `amount`, `available_quantity`, `display_order`, `status`, `ideal_profile`, `notes`, `created_at`)
SELECT * FROM (SELECT 'Círculo Dança Carajás' AS n, 'Círculo Dança Carajás' AS cn, NULL AS a, 99 AS q, 6 AS o, 'disponivel' AS s, 'flexivel' AS ip, 'Valores flexíveis até completar a captação.' AS nt, NOW() AS ca) t
WHERE NOT EXISTS (SELECT 1 FROM `quotas` WHERE `name` = 'Círculo Dança Carajás');

-- ---------------------------------------------------------------------
-- 4) Permissões administrativas de cotas (Etapa 3 só criou quotas.view)
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar cotas',  'quotas.create', 'Criar cotas de patrocínio'),
    ('Editar cotas', 'quotas.edit',   'Editar, arquivar e restaurar cotas')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Administrador Geral: garante TODAS as permissões (inclui as novas).
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Captação / Comercial: quotas.view, quotas.create, quotas.edit.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('quotas.view', 'quotas.create', 'quotas.edit')
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Leitura/Consulta, Produção/Coordenação e Comunicação: somente quotas.view.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'quotas.view'
WHERE r.`slug` IN ('leitura-consulta', 'producao-coordenacao', 'comunicacao')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
