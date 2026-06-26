-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 11
-- Módulo: Documentos e Arquivos
-- Idempotente: CREATE TABLE IF NOT EXISTS, ON DUPLICATE KEY UPDATE
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `documents` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `company_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `lead_id`              BIGINT UNSIGNED NULL DEFAULT NULL,

    `title`                VARCHAR(180)    NOT NULL,
    `description`          TEXT            NULL DEFAULT NULL,

    `category`             VARCHAR(80)     NOT NULL DEFAULT 'documento_comercial',
    `status`               VARCHAR(60)     NOT NULL DEFAULT 'ativo',
    `access_level`         VARCHAR(60)     NOT NULL DEFAULT 'interno',

    `file_path`            VARCHAR(255)    NOT NULL,
    `original_name`        VARCHAR(180)    NOT NULL,
    `stored_name`          VARCHAR(180)    NOT NULL,
    `extension`            VARCHAR(20)     NOT NULL,
    `mime_type`            VARCHAR(120)    NOT NULL,
    `size_bytes`           BIGINT UNSIGNED NOT NULL,
    `checksum_sha256`      VARCHAR(64)     NULL DEFAULT NULL,

    `version_number`       INT UNSIGNED    NOT NULL DEFAULT 1,
    `parent_document_id`   BIGINT UNSIGNED NULL DEFAULT NULL,

    `document_date`        DATE            NULL DEFAULT NULL,
    `valid_until`          DATE            NULL DEFAULT NULL,

    `responsible_user_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes`                TEXT            NULL DEFAULT NULL,

    `created_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`           BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NULL DEFAULT NULL,
    `archived_at`          DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_documents_company`      (`company_id`),
    KEY `idx_documents_contact`      (`contact_id`),
    KEY `idx_documents_opportunity`  (`opportunity_id`),
    KEY `idx_documents_quota`        (`quota_id`),
    KEY `idx_documents_proposal`     (`proposal_id`),
    KEY `idx_documents_lead`         (`lead_id`),
    KEY `idx_documents_category`     (`category`),
    KEY `idx_documents_status`       (`status`),
    KEY `idx_documents_access_level` (`access_level`),
    KEY `idx_documents_responsible`  (`responsible_user_id`),
    KEY `idx_documents_valid_until`  (`valid_until`),
    KEY `idx_documents_parent`       (`parent_document_id`),
    KEY `idx_documents_archived_at`  (`archived_at`),
    KEY `idx_documents_checksum`     (`checksum_sha256`),

    CONSTRAINT `fk_documents_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_parent`
        FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_responsible_user`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `permissions`
   SET `name` = 'Ver documentos',
       `description` = 'Visualizar documentos e arquivos'
 WHERE `slug` = 'documents.view';

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar documentos',     'documents.create',  'Cadastrar documentos e arquivos'),
    ('Editar documentos',    'documents.edit',    'Editar documentos e alterar status'),
    ('Arquivar documentos',  'documents.archive', 'Arquivar e restaurar documentos'),
    ('Baixar documentos',    'documents.download','Download protegido de arquivos'),
    ('Versionar documentos', 'documents.version', 'Criar nova versão de documento')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'documents.view', 'documents.create', 'documents.edit',
    'documents.archive', 'documents.download', 'documents.version'
)
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'documents.view', 'documents.create', 'documents.edit',
    'documents.download', 'documents.version'
)
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('documents.view', 'documents.download')
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
