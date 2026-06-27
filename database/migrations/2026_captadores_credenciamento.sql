-- =====================================================================
-- Dança Carajás Captação — Credenciamento de Captadores de Recursos
-- Idempotente: CREATE TABLE IF NOT EXISTS, INSERT IGNORE / ON DUPLICATE KEY
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `collector_applications` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `application_number`              VARCHAR(80)     NULL DEFAULT NULL,
    `source`                          VARCHAR(80)     NOT NULL DEFAULT 'site',
    `source_page`                     VARCHAR(255)    NULL DEFAULT NULL,
    `source_url`                      VARCHAR(255)    NULL DEFAULT NULL,

    `name`                            VARCHAR(180)    NOT NULL,
    `company_or_activity`             VARCHAR(180)    NULL DEFAULT NULL,
    `document_number`                 VARCHAR(80)     NULL DEFAULT NULL,
    `email`                           VARCHAR(180)    NOT NULL,
    `phone_whatsapp`                  VARCHAR(80)     NULL DEFAULT NULL,
    `city_state`                      VARCHAR(120)    NULL DEFAULT NULL,
    `rouanet_experience`              VARCHAR(80)     NULL DEFAULT NULL,
    `segments`                        VARCHAR(255)    NULL DEFAULT NULL,
    `sponsor_network_description`     TEXT            NULL DEFAULT NULL,
    `message`                         TEXT            NULL DEFAULT NULL,

    `status`                          VARCHAR(80)     NOT NULL DEFAULT 'manifestacao_recebida',
    `document_status`                 VARCHAR(80)     NOT NULL DEFAULT 'nao_solicitado',
    `review_status`                   VARCHAR(80)     NOT NULL DEFAULT 'pendente',
    `access_status`                   VARCHAR(80)     NOT NULL DEFAULT 'nao_liberado',

    `public_token`                    VARCHAR(160)    NULL DEFAULT NULL,
    `public_token_expires_at`         DATETIME        NULL DEFAULT NULL,
    `public_token_revoked_at`         DATETIME        NULL DEFAULT NULL,

    `review_notes`                    TEXT            NULL DEFAULT NULL,
    `rejection_reason`                TEXT            NULL DEFAULT NULL,
    `approval_notes`                  TEXT            NULL DEFAULT NULL,
    `internal_notes`                  TEXT            NULL DEFAULT NULL,

    `consent_contact`                 TINYINT(1)      NOT NULL DEFAULT 0,
    `consent_lgpd_at`                 DATETIME        NULL DEFAULT NULL,

    `ip_address`                      VARCHAR(80)     NULL DEFAULT NULL,
    `user_agent`                      VARCHAR(255)    NULL DEFAULT NULL,

    `assigned_user_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `reviewed_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `rejected_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `user_created_id`                 BIGINT UNSIGNED NULL DEFAULT NULL,

    `reviewed_at`                     DATETIME        NULL DEFAULT NULL,
    `approved_at`                     DATETIME        NULL DEFAULT NULL,
    `rejected_at`                     DATETIME        NULL DEFAULT NULL,
    `documents_requested_at`          DATETIME        NULL DEFAULT NULL,
    `documents_submitted_at`          DATETIME        NULL DEFAULT NULL,
    `access_released_at`              DATETIME        NULL DEFAULT NULL,

    `created_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_collector_applications_email`            (`email`),
    KEY `idx_collector_applications_document`         (`document_number`),
    KEY `idx_collector_applications_status`           (`status`),
    KEY `idx_collector_applications_document_status`  (`document_status`),
    KEY `idx_collector_applications_review_status`    (`review_status`),
    KEY `idx_collector_applications_access_status`    (`access_status`),
    KEY `idx_collector_applications_public_token`     (`public_token`),
    KEY `idx_collector_applications_assigned_user`    (`assigned_user_id`),
    KEY `idx_collector_applications_archived_at`      (`archived_at`),

    CONSTRAINT `fk_collector_applications_assigned`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_rejected_by`
        FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_user_created`
        FOREIGN KEY (`user_created_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_applications_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collector_application_documents` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `collector_application_id`        BIGINT UNSIGNED NOT NULL,
    `document_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,

    `document_type`                   VARCHAR(80)     NOT NULL,
    `title`                           VARCHAR(180)    NOT NULL,
    `status`                          VARCHAR(80)     NOT NULL DEFAULT 'pendente',
    `review_notes`                    TEXT            NULL DEFAULT NULL,

    `uploaded_original_name`          VARCHAR(255)    NULL DEFAULT NULL,
    `uploaded_stored_name`            VARCHAR(255)    NULL DEFAULT NULL,
    `file_path`                       VARCHAR(255)    NULL DEFAULT NULL,
    `file_mime`                       VARCHAR(120)    NULL DEFAULT NULL,
    `file_size`                       BIGINT UNSIGNED NULL DEFAULT NULL,
    `file_extension`                  VARCHAR(20)     NULL DEFAULT NULL,
    `checksum`                        VARCHAR(128)    NULL DEFAULT NULL,

    `uploaded_at`                     DATETIME        NULL DEFAULT NULL,
    `reviewed_at`                     DATETIME        NULL DEFAULT NULL,
    `reviewed_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_collector_app_docs_application` (`collector_application_id`),
    KEY `idx_collector_app_docs_document`    (`document_id`),
    KEY `idx_collector_app_docs_status`      (`status`),

    CONSTRAINT `fk_collector_app_docs_application`
        FOREIGN KEY (`collector_application_id`) REFERENCES `collector_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_app_docs_document`
        FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_app_docs_reviewed_by`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'collector_application_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `documents` ADD COLUMN `collector_application_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `sponsor_dossier_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_collector_application'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `documents` ADD KEY `idx_documents_collector_application` (`collector_application_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_collector_application'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_collector_application` FOREIGN KEY (`collector_application_id`) REFERENCES `collector_applications` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
    ('Captador Externo', 'captador-externo', 'Perfil restrito para captadores credenciados — sem acesso amplo ao CRM')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver credenciamentos',              'collector_applications.view',              'Visualizar candidaturas de captadores'),
    ('Criar credenciamentos',            'collector_applications.create',          'Cadastrar candidaturas manualmente'),
    ('Editar credenciamentos',           'collector_applications.edit',            'Editar candidaturas de captadores'),
    ('Arquivar credenciamentos',         'collector_applications.archive',         'Arquivar e restaurar candidaturas'),
    ('Analisar credenciamentos',         'collector_applications.review',          'Triagem e análise documental'),
    ('Aprovar credenciamentos',          'collector_applications.approve',         'Aprovar ou reprovar candidaturas'),
    ('Solicitar documentos captador',    'collector_applications.request_documents','Gerar link e solicitar documentos'),
    ('Liberar acesso captador',          'collector_applications.release_access',  'Preparar e liberar acesso de captador externo'),
    ('Portal captador',                  'collector_portal.view',                    'Acesso ao portal restrito do captador externo')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'collector_applications.view', 'collector_applications.create', 'collector_applications.edit',
    'collector_applications.archive', 'collector_applications.review', 'collector_applications.approve',
    'collector_applications.request_documents', 'collector_applications.release_access'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'collector_applications.view', 'collector_applications.create', 'collector_applications.edit',
    'collector_applications.review', 'collector_applications.request_documents', 'collector_applications.approve'
)
WHERE r.`slug` = 'captacao-comercial';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` = 'collector_applications.release_access';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'collector_applications.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'collector_portal.view'
WHERE r.`slug` = 'captador-externo';
