-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 12
-- Módulo: Patrocinadores / Fechamentos Comerciais
-- Idempotente: CREATE TABLE IF NOT EXISTS, ON DUPLICATE KEY UPDATE
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sponsors` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `company_id`                 BIGINT UNSIGNED NOT NULL,
    `contact_id`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `primary_document_id`        BIGINT UNSIGNED NULL DEFAULT NULL,

    `sponsor_display_name`       VARCHAR(180)    NOT NULL,

    `sponsorship_type`           VARCHAR(80)     NOT NULL DEFAULT 'patrocinio_direto',
    `funding_mechanism`          VARCHAR(80)     NOT NULL DEFAULT 'lei_rouanet',

    `project_year`               SMALLINT UNSIGNED NOT NULL DEFAULT 2026,
    `festival_edition`           VARCHAR(120)    NOT NULL DEFAULT 'Dança Carajás Festival 2026',

    `quota_snapshot_name`        VARCHAR(180)    NULL DEFAULT NULL,
    `quota_snapshot_amount`      DECIMAL(12,2)   NULL DEFAULT NULL,

    `committed_amount`           DECIMAL(12,2)   NULL DEFAULT NULL,
    `confirmed_amount`           DECIMAL(12,2)   NULL DEFAULT NULL,

    `in_kind_description`        TEXT            NULL DEFAULT NULL,
    `in_kind_estimated_value`    DECIMAL(12,2)   NULL DEFAULT NULL,

    `status`                     VARCHAR(60)     NOT NULL DEFAULT 'fechamento_registrado',
    `payment_status`             VARCHAR(60)     NOT NULL DEFAULT 'pendente',

    `closed_at`                  DATETIME        NULL DEFAULT NULL,
    `confirmed_at`               DATETIME        NULL DEFAULT NULL,
    `expected_payment_date`      DATE            NULL DEFAULT NULL,
    `received_at`                DATE            NULL DEFAULT NULL,

    `public_announcement_allowed` TINYINT(1)     NOT NULL DEFAULT 0,

    `pronac_number`              VARCHAR(40)     NULL DEFAULT NULL,
    `incentive_law`              VARCHAR(120)    NULL DEFAULT NULL,
    `incentive_notes`            TEXT            NULL DEFAULT NULL,

    `responsible_user_id`        BIGINT UNSIGNED NULL DEFAULT NULL,

    `notes`                      TEXT            NULL DEFAULT NULL,
    `internal_notes`             TEXT            NULL DEFAULT NULL,

    `created_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `confirmed_by`               BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME        NULL DEFAULT NULL,
    `archived_at`                DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_sponsors_company`            (`company_id`),
    KEY `idx_sponsors_contact`            (`contact_id`),
    KEY `idx_sponsors_opportunity`        (`opportunity_id`),
    KEY `idx_sponsors_proposal`           (`proposal_id`),
    KEY `idx_sponsors_quota`              (`quota_id`),
    KEY `idx_sponsors_primary_document`   (`primary_document_id`),
    KEY `idx_sponsors_type`               (`sponsorship_type`),
    KEY `idx_sponsors_funding`            (`funding_mechanism`),
    KEY `idx_sponsors_status`             (`status`),
    KEY `idx_sponsors_payment_status`     (`payment_status`),
    KEY `idx_sponsors_project_year`       (`project_year`),
    KEY `idx_sponsors_closed_at`          (`closed_at`),
    KEY `idx_sponsors_confirmed_at`       (`confirmed_at`),
    KEY `idx_sponsors_expected_payment_date` (`expected_payment_date`),
    KEY `idx_sponsors_responsible`        (`responsible_user_id`),
    KEY `idx_sponsors_archived_at`        (`archived_at`),

    CONSTRAINT `fk_sponsors_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Integração documents.sponsor_id (FK após sponsors existir)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'sponsor_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `sponsor_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `lead_id`, ADD KEY `idx_documents_sponsor` (`sponsor_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_sponsor'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_sponsor` FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK circular: primary_document_id → documents (após documents.sponsor_id)
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsors' AND CONSTRAINT_NAME = 'fk_sponsors_primary_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `sponsors` ADD CONSTRAINT `fk_sponsors_primary_document` FOREIGN KEY (`primary_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `permissions`
   SET `name` = 'Ver patrocinadores',
       `description` = 'Visualizar fechamentos comerciais / patrocinadores'
 WHERE `slug` = 'sponsors.view';

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar patrocinadores',   'sponsors.create',  'Registrar fechamentos comerciais'),
    ('Editar patrocinadores',  'sponsors.edit',    'Editar fechamentos comerciais'),
    ('Arquivar patrocinadores','sponsors.archive', 'Arquivar e restaurar fechamentos'),
    ('Confirmar fechamento',   'sponsors.confirm', 'Confirmar fechamento comercial'),
    ('Alterar status patroc.', 'sponsors.status',  'Alterar status e pagamento de fechamentos')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'sponsors.view', 'sponsors.create', 'sponsors.edit',
    'sponsors.archive', 'sponsors.confirm', 'sponsors.status'
)
WHERE r.`slug` IN ('administrador-geral', 'captacao-comercial')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'sponsors.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
