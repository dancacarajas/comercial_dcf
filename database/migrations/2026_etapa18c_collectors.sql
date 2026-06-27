-- =====================================================================
-- Dança Carajás Captação — Etapa 18C
-- Cadastro Mestre de Captadores (collectors)
-- Idempotente: CREATE TABLE IF NOT EXISTS / INSERT IGNORE
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `collectors` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `collector_application_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `user_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,

    `collector_code`              VARCHAR(40)     NULL DEFAULT NULL,
    `type`                        VARCHAR(20)     NOT NULL DEFAULT 'pessoa_fisica',
    `status`                      VARCHAR(20)     NOT NULL DEFAULT 'ativo',
    `registration_status`         VARCHAR(20)     NOT NULL DEFAULT 'incompleto',

    -- Identificação
    `name`                        VARCHAR(180)    NOT NULL,
    `legal_name`                  VARCHAR(180)    NULL DEFAULT NULL,
    `trade_name`                  VARCHAR(180)    NULL DEFAULT NULL,
    `document_number`             VARCHAR(40)     NULL DEFAULT NULL,
    `state_registration`          VARCHAR(40)     NULL DEFAULT NULL,
    `municipal_registration`      VARCHAR(40)     NULL DEFAULT NULL,
    `birth_date`                  DATE            NULL DEFAULT NULL,
    `nationality`                 VARCHAR(80)     NULL DEFAULT NULL,
    `marital_status`              VARCHAR(40)     NULL DEFAULT NULL,
    `profession`                  VARCHAR(120)    NULL DEFAULT NULL,

    -- Contato e endereço
    `email`                       VARCHAR(180)    NULL DEFAULT NULL,
    `phone_whatsapp`              VARCHAR(40)     NULL DEFAULT NULL,
    `secondary_phone`             VARCHAR(40)     NULL DEFAULT NULL,
    `address_zipcode`             VARCHAR(20)     NULL DEFAULT NULL,
    `address_street`              VARCHAR(180)    NULL DEFAULT NULL,
    `address_number`              VARCHAR(40)     NULL DEFAULT NULL,
    `address_complement`          VARCHAR(120)    NULL DEFAULT NULL,
    `address_district`            VARCHAR(120)    NULL DEFAULT NULL,
    `address_city`                VARCHAR(120)    NULL DEFAULT NULL,
    `address_state`               VARCHAR(2)      NULL DEFAULT NULL,

    -- Dados bancários
    `bank_name`                   VARCHAR(120)    NULL DEFAULT NULL,
    `bank_code`                   VARCHAR(10)     NULL DEFAULT NULL,
    `agency`                      VARCHAR(20)     NULL DEFAULT NULL,
    `account`                     VARCHAR(30)     NULL DEFAULT NULL,
    `account_digit`               VARCHAR(5)      NULL DEFAULT NULL,
    `account_type`                VARCHAR(20)     NULL DEFAULT NULL,
    `pix_key`                     VARCHAR(180)    NULL DEFAULT NULL,
    `pix_key_type`                VARCHAR(20)     NULL DEFAULT NULL,
    `bank_holder_name`            VARCHAR(180)    NULL DEFAULT NULL,
    `bank_holder_document`        VARCHAR(40)     NULL DEFAULT NULL,

    -- Representante legal (PJ)
    `representative_name`         VARCHAR(180)    NULL DEFAULT NULL,
    `representative_document`     VARCHAR(40)     NULL DEFAULT NULL,
    `representative_email`        VARCHAR(180)    NULL DEFAULT NULL,
    `representative_phone`        VARCHAR(40)     NULL DEFAULT NULL,
    `representative_role`         VARCHAR(120)    NULL DEFAULT NULL,

    -- Perfil comercial
    `rouanet_experience`          VARCHAR(80)     NULL DEFAULT NULL,
    `segments`                    VARCHAR(255)    NULL DEFAULT NULL,
    `sponsor_network_description` TEXT            NULL DEFAULT NULL,
    `territory_scope`             VARCHAR(255)    NULL DEFAULT NULL,
    `portfolio_summary`           TEXT            NULL DEFAULT NULL,
    `has_rouanet_experience`      TINYINT(1)      NOT NULL DEFAULT 0,

    -- Regras contratuais
    `commission_percentage`       DECIMAL(6,3)    NULL DEFAULT NULL,
    `commission_payment_rule`     VARCHAR(255)    NULL DEFAULT NULL,
    `commission_limit_rule`       VARCHAR(255)    NULL DEFAULT NULL,
    `contract_start_date`         DATE            NULL DEFAULT NULL,
    `contract_end_date`           DATE            NULL DEFAULT NULL,
    `exclusivity_type`            VARCHAR(80)     NULL DEFAULT NULL,
    `exclusivity_scope`           VARCHAR(255)    NULL DEFAULT NULL,
    `confidentiality_required`    TINYINT(1)      NOT NULL DEFAULT 0,

    `internal_notes`              TEXT            NULL DEFAULT NULL,

    -- Auditoria
    `validated_by`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `validated_at`                DATETIME        NULL DEFAULT NULL,
    `created_by`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME        NULL DEFAULT NULL,
    `archived_at`                 DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uniq_collectors_application` (`collector_application_id`),
    UNIQUE KEY `uniq_collectors_code`        (`collector_code`),
    KEY `idx_collectors_user`                (`user_id`),
    KEY `idx_collectors_document`            (`document_number`),
    KEY `idx_collectors_status`              (`status`),
    KEY `idx_collectors_registration_status` (`registration_status`),
    KEY `idx_collectors_archived_at`         (`archived_at`),

    CONSTRAINT `fk_collectors_application`
        FOREIGN KEY (`collector_application_id`) REFERENCES `collector_applications` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collectors_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collectors_validated_by`
        FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collectors_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collectors_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Permissões do cadastro mestre de captadores
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver captadores credenciados', 'collectors.view',     'Visualizar cadastro mestre de captadores'),
    ('Gerenciar captadores',        'collectors.manage',   'Criar e editar cadastro mestre de captadores'),
    ('Validar captadores',          'collectors.validate', 'Validar cadastro mestre antes da geração de documentos')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('collectors.view', 'collectors.manage', 'collectors.validate')
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('collectors.view', 'collectors.manage', 'collectors.validate')
WHERE r.`slug` = 'captacao-comercial';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'collectors.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');
