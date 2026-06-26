-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 13
-- Módulo: Contrapartidas dos Patrocinadores
-- Idempotente: CREATE TABLE IF NOT EXISTS, ON DUPLICATE KEY UPDATE
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `counterparts` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `sponsor_id`             BIGINT UNSIGNED NOT NULL,
    `company_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`               BIGINT UNSIGNED NULL DEFAULT NULL,

    `evidence_document_id`   BIGINT UNSIGNED NULL DEFAULT NULL,

    `title`                  VARCHAR(180)    NOT NULL,
    `category`               VARCHAR(80)     NOT NULL DEFAULT 'divulgacao_marca',
    `delivery_type`          VARCHAR(80)     NOT NULL DEFAULT 'entrega_unica',

    `description`            TEXT            NULL DEFAULT NULL,

    `promised_quantity`      DECIMAL(10,2)   NULL DEFAULT NULL,
    `delivered_quantity`     DECIMAL(10,2)   NULL DEFAULT NULL,
    `unit`                   VARCHAR(60)     NULL DEFAULT NULL,

    `priority`               VARCHAR(40)     NOT NULL DEFAULT 'media',
    `status`                 VARCHAR(60)     NOT NULL DEFAULT 'planejada',

    `due_date`               DATE            NULL DEFAULT NULL,
    `started_at`             DATETIME        NULL DEFAULT NULL,
    `delivered_at`           DATETIME        NULL DEFAULT NULL,
    `approved_at`            DATETIME        NULL DEFAULT NULL,

    `evidence_description`   TEXT            NULL DEFAULT NULL,
    `evidence_url`           VARCHAR(255)    NULL DEFAULT NULL,

    `responsible_user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`            BIGINT UNSIGNED NULL DEFAULT NULL,

    `notes`                  TEXT            NULL DEFAULT NULL,
    `internal_notes`         TEXT            NULL DEFAULT NULL,

    `created_by`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivered_by`           BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME        NULL DEFAULT NULL,
    `archived_at`            DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_counterparts_sponsor`            (`sponsor_id`),
    KEY `idx_counterparts_company`            (`company_id`),
    KEY `idx_counterparts_contact`            (`contact_id`),
    KEY `idx_counterparts_opportunity`        (`opportunity_id`),
    KEY `idx_counterparts_proposal`           (`proposal_id`),
    KEY `idx_counterparts_quota`              (`quota_id`),
    KEY `idx_counterparts_evidence_document`  (`evidence_document_id`),
    KEY `idx_counterparts_category`           (`category`),
    KEY `idx_counterparts_delivery_type`      (`delivery_type`),
    KEY `idx_counterparts_priority`           (`priority`),
    KEY `idx_counterparts_status`             (`status`),
    KEY `idx_counterparts_due_date`           (`due_date`),
    KEY `idx_counterparts_responsible`        (`responsible_user_id`),
    KEY `idx_counterparts_archived_at`        (`archived_at`),

    CONSTRAINT `fk_counterparts_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_delivered_by`
        FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK evidence_document_id → documents (após coluna counterpart_id em documents)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'counterpart_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `counterpart_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `sponsor_id`, ADD KEY `idx_documents_counterpart` (`counterpart_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_counterpart'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_counterpart` FOREIGN KEY (`counterpart_id`) REFERENCES `counterparts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'counterparts' AND CONSTRAINT_NAME = 'fk_counterparts_evidence_document'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `counterparts` ADD CONSTRAINT `fk_counterparts_evidence_document` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `permissions`
   SET `name` = 'Ver contrapartidas',
       `description` = 'Visualizar contrapartidas dos patrocinadores'
 WHERE `slug` = 'counterparts.view';

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar contrapartidas',    'counterparts.create',  'Registrar contrapartidas de patrocinadores'),
    ('Editar contrapartidas',   'counterparts.edit',    'Editar contrapartidas'),
    ('Arquivar contrapartidas', 'counterparts.archive', 'Arquivar e restaurar contrapartidas'),
    ('Entregar contrapartidas', 'counterparts.deliver', 'Registrar entrega de contrapartidas'),
    ('Alterar status contrap.', 'counterparts.status',  'Alterar status de contrapartidas')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'counterparts.view', 'counterparts.create', 'counterparts.edit',
    'counterparts.archive', 'counterparts.deliver', 'counterparts.status'
)
WHERE r.`slug` IN ('administrador-geral', 'captacao-comercial')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'counterparts.view', 'counterparts.create', 'counterparts.edit',
    'counterparts.deliver', 'counterparts.status'
)
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'counterparts.view'
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
