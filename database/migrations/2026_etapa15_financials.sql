-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 15
-- Módulo: Financeiro Detalhado / Parcelas / Comprovantes
-- Idempotente: CREATE TABLE IF NOT EXISTS, INSERT IGNORE / ON DUPLICATE KEY
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `financial_entries` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `sponsor_id`              BIGINT UNSIGNED NOT NULL,
    `contract_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `company_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                BIGINT UNSIGNED NULL DEFAULT NULL,

    `proof_document_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `receipt_document_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `fiscal_document_id`      BIGINT UNSIGNED NULL DEFAULT NULL,

    `entry_number`            VARCHAR(80)     NULL DEFAULT NULL,
    `title`                   VARCHAR(180)    NOT NULL,
    `entry_type`              VARCHAR(80)     NOT NULL DEFAULT 'parcela_patrocinio',
    `funding_mechanism`       VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',
    `payment_method`          VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',

    `status`                  VARCHAR(60)     NOT NULL DEFAULT 'previsto',
    `fiscal_document_status`  VARCHAR(60)     NOT NULL DEFAULT 'nao_aplicavel',

    `installment_number`      INT             NULL DEFAULT NULL,
    `installments_total`      INT             NULL DEFAULT NULL,

    `planned_amount`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `received_amount`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `remaining_amount`        DECIMAL(12,2)   NULL DEFAULT NULL,

    `due_date`                DATE            NULL DEFAULT NULL,
    `expected_payment_date`   DATE            NULL DEFAULT NULL,
    `received_at`             DATETIME        NULL DEFAULT NULL,
    `reconciled_at`           DATETIME        NULL DEFAULT NULL,
    `cancelled_at`            DATETIME        NULL DEFAULT NULL,

    `payer_name`              VARCHAR(180)    NULL DEFAULT NULL,
    `payer_document`          VARCHAR(80)     NULL DEFAULT NULL,
    `bank_reference`          VARCHAR(120)    NULL DEFAULT NULL,
    `transaction_reference`   VARCHAR(120)    NULL DEFAULT NULL,

    `proof_notes`             TEXT            NULL DEFAULT NULL,
    `receipt_notes`           TEXT            NULL DEFAULT NULL,
    `fiscal_notes`            TEXT            NULL DEFAULT NULL,
    `reconciliation_notes`    TEXT            NULL DEFAULT NULL,
    `notes`                   TEXT            NULL DEFAULT NULL,
    `internal_notes`          TEXT            NULL DEFAULT NULL,

    `responsible_user_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `confirmed_by`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `reconciled_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `cancelled_by`            BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_by`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`              BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME        NULL DEFAULT NULL,
    `archived_at`             DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_financial_entries_sponsor`              (`sponsor_id`),
    KEY `idx_financial_entries_contract`             (`contract_id`),
    KEY `idx_financial_entries_company`              (`company_id`),
    KEY `idx_financial_entries_contact`              (`contact_id`),
    KEY `idx_financial_entries_opportunity`          (`opportunity_id`),
    KEY `idx_financial_entries_proposal`             (`proposal_id`),
    KEY `idx_financial_entries_quota`                (`quota_id`),
    KEY `idx_financial_entries_proof_document`       (`proof_document_id`),
    KEY `idx_financial_entries_receipt_document`     (`receipt_document_id`),
    KEY `idx_financial_entries_fiscal_document`      (`fiscal_document_id`),
    KEY `idx_financial_entries_entry_number`         (`entry_number`),
    KEY `idx_financial_entries_type`                 (`entry_type`),
    KEY `idx_financial_entries_funding`              (`funding_mechanism`),
    KEY `idx_financial_entries_method`               (`payment_method`),
    KEY `idx_financial_entries_status`               (`status`),
    KEY `idx_financial_entries_fiscal_status`        (`fiscal_document_status`),
    KEY `idx_financial_entries_due_date`             (`due_date`),
    KEY `idx_financial_entries_expected_payment_date` (`expected_payment_date`),
    KEY `idx_financial_entries_received_at`          (`received_at`),
    KEY `idx_financial_entries_reconciled_at`        (`reconciled_at`),
    KEY `idx_financial_entries_responsible`          (`responsible_user_id`),
    KEY `idx_financial_entries_archived_at`          (`archived_at`),

    CONSTRAINT `fk_financial_entries_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_contract`
        FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_reconciled_by`
        FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_cancelled_by`
        FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'financial_entry_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `financial_entry_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `contract_id`, ADD KEY `idx_documents_financial_entry` (`financial_entry_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_financial_entry'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_financial_entry` FOREIGN KEY (`financial_entry_id`) REFERENCES `financial_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_entries' AND CONSTRAINT_NAME = 'fk_financial_entries_proof_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `financial_entries` ADD CONSTRAINT `fk_financial_entries_proof_document` FOREIGN KEY (`proof_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_entries' AND CONSTRAINT_NAME = 'fk_financial_entries_receipt_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `financial_entries` ADD CONSTRAINT `fk_financial_entries_receipt_document` FOREIGN KEY (`receipt_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_entries' AND CONSTRAINT_NAME = 'fk_financial_entries_fiscal_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `financial_entries` ADD CONSTRAINT `fk_financial_entries_fiscal_document` FOREIGN KEY (`fiscal_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver financeiro',              'financials.view',      'Visualizar registros financeiros e parcelas'),
    ('Criar financeiro',            'financials.create',    'Registrar lançamentos financeiros'),
    ('Editar financeiro',           'financials.edit',      'Editar lançamentos financeiros'),
    ('Arquivar financeiro',         'financials.archive',   'Arquivar e restaurar lançamentos financeiros'),
    ('Alterar status financeiro',   'financials.status',    'Alterar status de lançamentos financeiros'),
    ('Confirmar recebimento',       'financials.confirm',   'Confirmar recebimento de pagamentos'),
    ('Conciliar financeiro',        'financials.reconcile', 'Conciliar manualmente lançamentos financeiros')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'financials.view', 'financials.create', 'financials.edit',
    'financials.archive', 'financials.status', 'financials.confirm', 'financials.reconcile'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'financials.view', 'financials.create', 'financials.edit',
    'financials.status', 'financials.confirm'
)
WHERE r.`slug` = 'captacao-comercial';

-- Garante que captacao nao herde archive/reconcile de execucoes anteriores
DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` IN ('financials.archive', 'financials.reconcile');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'financials.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');
