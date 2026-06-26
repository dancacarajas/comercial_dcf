-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 14
-- Módulo: Contratos / Instrumentos de Formalização
-- Idempotente: CREATE TABLE IF NOT EXISTS, INSERT IGNORE
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `contracts` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `sponsor_id`                      BIGINT UNSIGNED NOT NULL,
    `company_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                        BIGINT UNSIGNED NULL DEFAULT NULL,

    `draft_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `final_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `signed_document_id`              BIGINT UNSIGNED NULL DEFAULT NULL,

    `contract_number`                 VARCHAR(80)     NULL DEFAULT NULL,
    `title`                           VARCHAR(180)    NOT NULL,
    `contract_type`                   VARCHAR(80)     NOT NULL DEFAULT 'termo_patrocinio',

    `formalized_value`                DECIMAL(12,2)   NULL DEFAULT NULL,
    `funding_mechanism`               VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',

    `status`                          VARCHAR(60)     NOT NULL DEFAULT 'minuta',
    `review_status`                   VARCHAR(60)     NOT NULL DEFAULT 'nao_revisado',
    `signature_status`                VARCHAR(60)     NOT NULL DEFAULT 'nao_enviado',

    `start_date`                      DATE            NULL DEFAULT NULL,
    `end_date`                        DATE            NULL DEFAULT NULL,
    `sent_for_signature_at`           DATETIME        NULL DEFAULT NULL,
    `signed_at`                       DATETIME        NULL DEFAULT NULL,
    `effective_at`                    DATE            NULL DEFAULT NULL,
    `ended_at`                        DATE            NULL DEFAULT NULL,

    `sponsor_signatory_name`          VARCHAR(180)    NULL DEFAULT NULL,
    `sponsor_signatory_email`         VARCHAR(180)    NULL DEFAULT NULL,
    `sponsor_signatory_position`      VARCHAR(120)    NULL DEFAULT NULL,
    `sponsor_signatory_document`      VARCHAR(80)     NULL DEFAULT NULL,

    `organization_signatory_name`     VARCHAR(180)    NULL DEFAULT NULL,
    `organization_signatory_email`    VARCHAR(180)    NULL DEFAULT NULL,
    `organization_signatory_position` VARCHAR(120)    NULL DEFAULT NULL,

    `approval_notes`                  TEXT            NULL DEFAULT NULL,
    `signature_notes`                 TEXT            NULL DEFAULT NULL,
    `legal_notes`                     TEXT            NULL DEFAULT NULL,
    `notes`                           TEXT            NULL DEFAULT NULL,
    `internal_notes`                  TEXT            NULL DEFAULT NULL,

    `responsible_user_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `signed_registered_by`          BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `approved_at`                     DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_contracts_sponsor`            (`sponsor_id`),
    KEY `idx_contracts_company`            (`company_id`),
    KEY `idx_contracts_contact`            (`contact_id`),
    KEY `idx_contracts_opportunity`        (`opportunity_id`),
    KEY `idx_contracts_proposal`           (`proposal_id`),
    KEY `idx_contracts_quota`              (`quota_id`),
    KEY `idx_contracts_draft_document`     (`draft_document_id`),
    KEY `idx_contracts_final_document`     (`final_document_id`),
    KEY `idx_contracts_signed_document`    (`signed_document_id`),
    KEY `idx_contracts_contract_number`    (`contract_number`),
    KEY `idx_contracts_type`               (`contract_type`),
    KEY `idx_contracts_status`             (`status`),
    KEY `idx_contracts_review_status`      (`review_status`),
    KEY `idx_contracts_signature_status`   (`signature_status`),
    KEY `idx_contracts_start_date`         (`start_date`),
    KEY `idx_contracts_end_date`           (`end_date`),
    KEY `idx_contracts_signed_at`          (`signed_at`),
    KEY `idx_contracts_responsible`        (`responsible_user_id`),
    KEY `idx_contracts_archived_at`        (`archived_at`),

    CONSTRAINT `fk_contracts_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_signed_registered_by`
        FOREIGN KEY (`signed_registered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'contract_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `contract_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `counterpart_id`, ADD KEY `idx_documents_contract` (`contract_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_contract'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND CONSTRAINT_NAME = 'fk_contracts_draft_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_draft_document` FOREIGN KEY (`draft_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND CONSTRAINT_NAME = 'fk_contracts_final_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_final_document` FOREIGN KEY (`final_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND CONSTRAINT_NAME = 'fk_contracts_signed_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `contracts` ADD CONSTRAINT `fk_contracts_signed_document` FOREIGN KEY (`signed_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver contratos',              'contracts.view',        'Visualizar contratos e instrumentos de formalização'),
    ('Criar contratos',            'contracts.create',      'Registrar contratos e instrumentos'),
    ('Editar contratos',           'contracts.edit',        'Editar contratos e instrumentos'),
    ('Arquivar contratos',         'contracts.archive',     'Arquivar e restaurar contratos'),
    ('Alterar status contrato',    'contracts.status',      'Alterar status de contratos'),
    ('Marcar contrato assinado',   'contracts.mark_signed', 'Registrar assinatura manual de contratos'),
    ('Aprovar contratos',          'contracts.approve',     'Aprovar contratos internamente')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'contracts.view', 'contracts.create', 'contracts.edit',
    'contracts.archive', 'contracts.status', 'contracts.mark_signed', 'contracts.approve'
)
WHERE r.`slug` IN ('administrador-geral', 'captacao-comercial');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'contracts.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');
