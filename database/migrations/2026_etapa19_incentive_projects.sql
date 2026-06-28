-- =====================================================================
-- Migração ETAPA 19 — Projetos Incentivados / PRONACs / Plano de Captação
-- Referência declarativa do schema. Em produção, use o script idempotente
-- scripts/run_migration_etapa19_projects.php (cria tabelas, colunas, permissões,
-- projeto 2026, rubrica item 41 e backfill dos dados existentes).
--
-- ATENÇÃO: as instruções ALTER TABLE abaixo assumem que a coluna ainda não
-- existe (instalação nova). Para bancos já em produção, prefira o script PHP,
-- que verifica a existência antes de alterar.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `incentive_projects` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_name`               VARCHAR(200)    NOT NULL,
    `edition_year`               SMALLINT UNSIGNED NULL DEFAULT NULL,
    `pronac_number`              VARCHAR(60)     NULL DEFAULT NULL,
    `salic_proposal_number`      VARCHAR(60)     NULL DEFAULT NULL,
    `law_framework`              VARCHAR(120)    NULL DEFAULT 'Lei Rouanet',
    `proponent_name`             VARCHAR(200)    NULL DEFAULT NULL,
    `proponent_document`         VARCHAR(40)     NULL DEFAULT NULL,
    `project_status`             VARCHAR(40)     NOT NULL DEFAULT 'em_elaboracao',
    `approved_total_amount`      DECIMAL(14,2)   NULL DEFAULT NULL,
    `authorized_capture_amount`  DECIMAL(14,2)   NULL DEFAULT NULL,
    `capture_commission_budget`  DECIMAL(14,2)   NULL DEFAULT NULL,
    `commission_factor`          DECIMAL(12,10)  NULL DEFAULT NULL,
    `capture_start_date`         DATE            NULL DEFAULT NULL,
    `capture_end_date`           DATE            NULL DEFAULT NULL,
    `bank_name`                  VARCHAR(120)    NULL DEFAULT NULL,
    `bank_agency`                VARCHAR(30)     NULL DEFAULT NULL,
    `bank_account`               VARCHAR(40)     NULL DEFAULT NULL,
    `bank_account_digit`         VARCHAR(10)     NULL DEFAULT NULL,
    `bank_account_type`          VARCHAR(30)     NULL DEFAULT NULL,
    `notes`                      TEXT            NULL DEFAULT NULL,
    `created_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME        NULL DEFAULT NULL,
    `archived_at`                DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_incentive_projects_status` (`project_status`),
    KEY `idx_incentive_projects_year` (`edition_year`),
    KEY `idx_incentive_projects_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `incentive_project_budget_items` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `incentive_project_id`        BIGINT UNSIGNED NOT NULL,
    `item_number`                 INT             NULL DEFAULT NULL,
    `source`                      VARCHAR(120)    NULL DEFAULT NULL,
    `product`                     VARCHAR(200)    NULL DEFAULT NULL,
    `stage`                       VARCHAR(120)    NULL DEFAULT NULL,
    `uf`                          VARCHAR(2)      NULL DEFAULT NULL,
    `city`                        VARCHAR(120)    NULL DEFAULT NULL,
    `budget_item_name`            VARCHAR(255)    NOT NULL,
    `unit`                        VARCHAR(40)     NULL DEFAULT NULL,
    `quantity`                    DECIMAL(12,2)   NULL DEFAULT NULL,
    `occurrence`                  DECIMAL(12,2)   NULL DEFAULT NULL,
    `unit_amount`                 DECIMAL(14,2)   NULL DEFAULT NULL,
    `requested_amount`            DECIMAL(14,2)   NULL DEFAULT NULL,
    `is_capture_commission_item`  TINYINT(1)      NOT NULL DEFAULT 0,
    `notes`                       TEXT            NULL DEFAULT NULL,
    `created_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME        NULL DEFAULT NULL,
    `archived_at`                 DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ipbi_project` (`incentive_project_id`),
    KEY `idx_ipbi_commission` (`is_capture_commission_item`),
    KEY `idx_ipbi_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `quotas`                ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_quotas_incentive_project` (`incentive_project_id`);
ALTER TABLE `opportunities`         ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_opportunities_incentive_project` (`incentive_project_id`);
ALTER TABLE `proposals`             ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_proposals_incentive_project` (`incentive_project_id`);
ALTER TABLE `sponsors`              ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_sponsors_incentive_project` (`incentive_project_id`);
ALTER TABLE `financial_entries`     ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_financial_entries_incentive_project` (`incentive_project_id`);
ALTER TABLE `collector_assignments` ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_collector_assignments_incentive_project` (`incentive_project_id`);
ALTER TABLE `collector_deals`       ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_collector_deals_incentive_project` (`incentive_project_id`);

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Projetos incentivados: visualizar',         'incentive_projects.view',             'Visualizar projetos incentivados / PRONACs'),
    ('Projetos incentivados: criar',              'incentive_projects.create',           'Criar projetos incentivados'),
    ('Projetos incentivados: editar',             'incentive_projects.edit',             'Editar projetos incentivados'),
    ('Projetos incentivados: arquivar/restaurar', 'incentive_projects.archive',          'Arquivar e restaurar projetos incentivados'),
    ('Projetos incentivados: orçamento',          'incentive_projects.budget',           'Gerir rubricas/plano orçamentário do projeto'),
    ('Projetos incentivados: liberar captação',   'incentive_projects.activate_capture', 'Liberar o projeto para captação')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` IN (
    'incentive_projects.view','incentive_projects.create','incentive_projects.edit',
    'incentive_projects.archive','incentive_projects.budget','incentive_projects.activate_capture'
) WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` IN (
    'incentive_projects.view','incentive_projects.create','incentive_projects.edit','incentive_projects.budget'
) WHERE r.`slug` = 'captacao-comercial';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` = 'incentive_projects.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');
