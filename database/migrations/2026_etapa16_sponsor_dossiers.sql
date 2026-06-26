-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 16
-- Módulo: Prestação de Contas Comercial / Dossiê do Patrocinador
-- Idempotente: CREATE TABLE IF NOT EXISTS, INSERT IGNORE / ON DUPLICATE KEY
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sponsor_dossiers` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `sponsor_id`                      BIGINT UNSIGNED NOT NULL,
    `company_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                        BIGINT UNSIGNED NULL DEFAULT NULL,
    `main_contract_id`                BIGINT UNSIGNED NULL DEFAULT NULL,

    `main_document_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `final_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivery_receipt_document_id`    BIGINT UNSIGNED NULL DEFAULT NULL,

    `dossier_number`                  VARCHAR(80)     NULL DEFAULT NULL,
    `title`                           VARCHAR(180)    NOT NULL,
    `dossier_type`                    VARCHAR(80)     NOT NULL DEFAULT 'prestacao_comercial',

    `status`                          VARCHAR(60)     NOT NULL DEFAULT 'rascunho',
    `delivery_status`                 VARCHAR(60)     NOT NULL DEFAULT 'nao_entregue',

    `period_start`                    DATE            NULL DEFAULT NULL,
    `period_end`                      DATE            NULL DEFAULT NULL,

    `include_contracts`             TINYINT(1)      NOT NULL DEFAULT 1,
    `include_counterparts`            TINYINT(1)      NOT NULL DEFAULT 1,
    `include_financials`              TINYINT(1)      NOT NULL DEFAULT 1,
    `include_documents`               TINYINT(1)      NOT NULL DEFAULT 1,
    `include_evidence`                TINYINT(1)      NOT NULL DEFAULT 1,
    `include_clipping`                TINYINT(1)      NOT NULL DEFAULT 1,
    `include_media`                   TINYINT(1)      NOT NULL DEFAULT 1,

    `contracts_count`                 INT             NOT NULL DEFAULT 0,
    `signed_contracts_count`          INT             NOT NULL DEFAULT 0,
    `counterparts_count`              INT             NOT NULL DEFAULT 0,
    `counterparts_delivered_count`    INT             NOT NULL DEFAULT 0,
    `counterparts_partial_count`      INT             NOT NULL DEFAULT 0,
    `counterparts_pending_count`      INT             NOT NULL DEFAULT 0,
    `counterparts_overdue_count`      INT             NOT NULL DEFAULT 0,

    `financial_entries_count`         INT             NOT NULL DEFAULT 0,
    `financial_planned_amount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_received_amount`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_remaining_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_overdue_count`         INT             NOT NULL DEFAULT 0,

    `documents_count`                 INT             NOT NULL DEFAULT 0,
    `evidence_documents_count`        INT             NOT NULL DEFAULT 0,

    `executive_summary`               TEXT            NULL DEFAULT NULL,
    `commercial_summary`              TEXT            NULL DEFAULT NULL,
    `counterparts_summary`            TEXT            NULL DEFAULT NULL,
    `financial_summary`               TEXT            NULL DEFAULT NULL,
    `documents_summary`               TEXT            NULL DEFAULT NULL,
    `pending_notes`                   TEXT            NULL DEFAULT NULL,
    `approval_notes`                  TEXT            NULL DEFAULT NULL,
    `delivery_notes`                  TEXT            NULL DEFAULT NULL,
    `notes`                           TEXT            NULL DEFAULT NULL,
    `internal_notes`                  TEXT            NULL DEFAULT NULL,

    `generated_at`                    DATETIME        NULL DEFAULT NULL,
    `approved_at`                     DATETIME        NULL DEFAULT NULL,
    `delivered_at`                    DATETIME        NULL DEFAULT NULL,

    `responsible_user_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `generated_by`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivered_by`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_sponsor_dossiers_sponsor`              (`sponsor_id`),
    KEY `idx_sponsor_dossiers_company`              (`company_id`),
    KEY `idx_sponsor_dossiers_contact`              (`contact_id`),
    KEY `idx_sponsor_dossiers_opportunity`          (`opportunity_id`),
    KEY `idx_sponsor_dossiers_proposal`             (`proposal_id`),
    KEY `idx_sponsor_dossiers_quota`                (`quota_id`),
    KEY `idx_sponsor_dossiers_contract`             (`main_contract_id`),
    KEY `idx_sponsor_dossiers_main_document`        (`main_document_id`),
    KEY `idx_sponsor_dossiers_final_document`       (`final_document_id`),
    KEY `idx_sponsor_dossiers_delivery_document`    (`delivery_receipt_document_id`),
    KEY `idx_sponsor_dossiers_number`               (`dossier_number`),
    KEY `idx_sponsor_dossiers_type`                 (`dossier_type`),
    KEY `idx_sponsor_dossiers_status`               (`status`),
    KEY `idx_sponsor_dossiers_delivery_status`    (`delivery_status`),
    KEY `idx_sponsor_dossiers_period_start`       (`period_start`),
    KEY `idx_sponsor_dossiers_period_end`         (`period_end`),
    KEY `idx_sponsor_dossiers_responsible`        (`responsible_user_id`),
    KEY `idx_sponsor_dossiers_archived_at`        (`archived_at`),

    CONSTRAINT `fk_sponsor_dossiers_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_main_contract`
        FOREIGN KEY (`main_contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_generated_by`
        FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_delivered_by`
        FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sponsor_dossier_items` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `dossier_id`          BIGINT UNSIGNED NOT NULL,
    `sponsor_id`          BIGINT UNSIGNED NOT NULL,

    `contract_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `counterpart_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `financial_entry_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `document_id`         BIGINT UNSIGNED NULL DEFAULT NULL,

    `item_type`           VARCHAR(80)     NOT NULL DEFAULT 'manual',
    `source_module`       VARCHAR(80)     NULL DEFAULT NULL,

    `title`               VARCHAR(180)    NOT NULL,
    `description`         TEXT            NULL DEFAULT NULL,
    `status`              VARCHAR(60)     NOT NULL DEFAULT 'ativo',
    `evidence_status`     VARCHAR(60)     NOT NULL DEFAULT 'nao_aplicavel',

    `amount`              DECIMAL(12,2)   NULL DEFAULT NULL,
    `date_ref`            DATE            NULL DEFAULT NULL,
    `sort_order`          INT             NOT NULL DEFAULT 0,

    `created_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NULL DEFAULT NULL,
    `archived_at`         DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_dossier_items_dossier`           (`dossier_id`),
    KEY `idx_dossier_items_sponsor`           (`sponsor_id`),
    KEY `idx_dossier_items_contract`          (`contract_id`),
    KEY `idx_dossier_items_counterpart`       (`counterpart_id`),
    KEY `idx_dossier_items_financial`         (`financial_entry_id`),
    KEY `idx_dossier_items_document`          (`document_id`),
    KEY `idx_dossier_items_type`              (`item_type`),
    KEY `idx_dossier_items_source`            (`source_module`),
    KEY `idx_dossier_items_status`            (`status`),
    KEY `idx_dossier_items_evidence_status`   (`evidence_status`),
    KEY `idx_dossier_items_date_ref`          (`date_ref`),
    KEY `idx_dossier_items_archived_at`       (`archived_at`),

    CONSTRAINT `fk_dossier_items_dossier`
        FOREIGN KEY (`dossier_id`) REFERENCES `sponsor_dossiers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_contract`
        FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_counterpart`
        FOREIGN KEY (`counterpart_id`) REFERENCES `counterparts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_financial`
        FOREIGN KEY (`financial_entry_id`) REFERENCES `financial_entries` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_document`
        FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'sponsor_dossier_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `sponsor_dossier_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `financial_entry_id`, ADD KEY `idx_documents_sponsor_dossier` (`sponsor_dossier_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_sponsor_dossier'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_sponsor_dossier` FOREIGN KEY (`sponsor_dossier_id`) REFERENCES `sponsor_dossiers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsor_dossiers' AND CONSTRAINT_NAME = 'fk_sponsor_dossiers_main_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `sponsor_dossiers` ADD CONSTRAINT `fk_sponsor_dossiers_main_document` FOREIGN KEY (`main_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsor_dossiers' AND CONSTRAINT_NAME = 'fk_sponsor_dossiers_final_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `sponsor_dossiers` ADD CONSTRAINT `fk_sponsor_dossiers_final_document` FOREIGN KEY (`final_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsor_dossiers' AND CONSTRAINT_NAME = 'fk_sponsor_dossiers_delivery_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `sponsor_dossiers` ADD CONSTRAINT `fk_sponsor_dossiers_delivery_document` FOREIGN KEY (`delivery_receipt_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver dossiês',              'dossiers.view',     'Visualizar dossiês de prestação comercial'),
    ('Criar dossiês',            'dossiers.create',   'Criar dossiês de patrocinador'),
    ('Editar dossiês',           'dossiers.edit',     'Editar dossiês e itens manuais'),
    ('Arquivar dossiês',         'dossiers.archive',  'Arquivar e restaurar dossiês'),
    ('Alterar status dossiê',    'dossiers.status',   'Alterar status do dossiê'),
    ('Gerar consolidação',       'dossiers.generate', 'Gerar/atualizar consolidação do dossiê'),
    ('Aprovar dossiê',           'dossiers.approve',  'Aprovar dossiê internamente'),
    ('Entregar dossiê',          'dossiers.deliver',  'Marcar dossiê como entregue ao patrocinador')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.archive',
    'dossiers.status', 'dossiers.generate', 'dossiers.approve', 'dossiers.deliver'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.archive',
    'dossiers.status', 'dossiers.generate', 'dossiers.deliver'
)
WHERE r.`slug` = 'captacao-comercial';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` = 'dossiers.approve';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('dossiers.view', 'dossiers.edit', 'dossiers.generate')
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'dossiers.view'
WHERE r.`slug` = 'leitura-consulta';
