-- =====================================================================
-- Dança Carajás Captação — Etapa 18B
-- Modelos de Contrato + Assinaturas Digitais (base reutilizável)
-- Idempotente
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `contract_templates` (
    `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key`                 VARCHAR(100)    NULL DEFAULT NULL,
    `title`                        VARCHAR(180)    NOT NULL,
    `description`                  TEXT            NULL DEFAULT NULL,
    `template_type`                VARCHAR(80)     NOT NULL DEFAULT 'autorizacao_captador',
    `status`                       VARCHAR(60)     NOT NULL DEFAULT 'rascunho',
    `content_html`                 LONGTEXT        NOT NULL,
    `content_text`                 LONGTEXT        NULL DEFAULT NULL,
    `available_placeholders_json`  LONGTEXT        NULL DEFAULT NULL,
    `default_signer_role`          VARCHAR(80)     NULL DEFAULT NULL,
    `version`                      INT             NOT NULL DEFAULT 1,
    `is_default`                   TINYINT(1)      NOT NULL DEFAULT 0,
    `created_by`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                   DATETIME        NULL DEFAULT NULL,
    `archived_at`                  DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_contract_templates_key` (`template_key`),
    KEY `idx_contract_templates_type` (`template_type`),
    KEY `idx_contract_templates_status` (`status`),
    KEY `idx_contract_templates_default` (`is_default`),
    KEY `idx_contract_templates_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_requests` (
    `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_type`                  VARCHAR(80)     NOT NULL,
    `source_id`                    BIGINT UNSIGNED NOT NULL,
    `contract_template_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `document_id`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `title`                        VARCHAR(180)    NOT NULL,
    `status`                       VARCHAR(60)     NOT NULL DEFAULT 'rascunho',
    `rendered_html`                LONGTEXT        NULL DEFAULT NULL,
    `content_hash`                 VARCHAR(128)    NULL DEFAULT NULL,
    `public_token`                 VARCHAR(180)    NULL DEFAULT NULL,
    `public_token_expires_at`      DATETIME        NULL DEFAULT NULL,
    `public_token_revoked_at`      DATETIME        NULL DEFAULT NULL,
    `sent_at`                      DATETIME        NULL DEFAULT NULL,
    `signed_at`                    DATETIME        NULL DEFAULT NULL,
    `cancelled_at`                 DATETIME        NULL DEFAULT NULL,
    `created_by`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `sent_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `cancelled_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                   DATETIME        NULL DEFAULT NULL,
    `archived_at`                  DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_signature_source` (`source_type`, `source_id`),
    KEY `idx_signature_status` (`status`),
    KEY `idx_signature_token` (`public_token`),
    KEY `idx_signature_template` (`contract_template_id`),
    KEY `idx_signature_document` (`document_id`),
    KEY `idx_signature_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signature_signers` (
    `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `signature_request_id`         BIGINT UNSIGNED NOT NULL,
    `signer_name`                  VARCHAR(180)    NOT NULL,
    `signer_email`                 VARCHAR(180)    NOT NULL,
    `signer_document`              VARCHAR(80)     NULL DEFAULT NULL,
    `signer_role`                  VARCHAR(80)     NOT NULL DEFAULT 'captador',
    `status`                       VARCHAR(60)     NOT NULL DEFAULT 'pendente',
    `public_token`                 VARCHAR(180)    NULL DEFAULT NULL,
    `signed_at`                    DATETIME        NULL DEFAULT NULL,
    `signed_ip`                    VARCHAR(80)     NULL DEFAULT NULL,
    `signed_user_agent`            VARCHAR(255)    NULL DEFAULT NULL,
    `signature_method`             VARCHAR(80)     NOT NULL DEFAULT 'aceite_eletronico',
    `signature_hash`               VARCHAR(128)    NULL DEFAULT NULL,
    `acceptance_text`              TEXT            NULL DEFAULT NULL,
    `created_at`                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                   DATETIME        NULL DEFAULT NULL,
    `archived_at`                  DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_signature_signers_request` (`signature_request_id`),
    KEY `idx_signature_signers_email` (`signer_email`),
    KEY `idx_signature_signers_status` (`status`),
    KEY `idx_signature_signers_token` (`public_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissões — modelos de contrato
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver modelos de contrato', 'contract_templates.view', 'Visualizar modelos de contrato'),
    ('Criar modelos de contrato', 'contract_templates.create', 'Criar modelos de contrato'),
    ('Editar modelos de contrato', 'contract_templates.edit', 'Editar modelos de contrato'),
    ('Arquivar modelos de contrato', 'contract_templates.archive', 'Arquivar modelos de contrato'),
    ('Pré-visualizar modelos de contrato', 'contract_templates.preview', 'Pré-visualizar modelos de contrato'),
    ('Ver assinaturas', 'signature_requests.view', 'Visualizar processos de assinatura'),
    ('Criar assinaturas', 'signature_requests.create', 'Criar processos de assinatura'),
    ('Enviar assinaturas', 'signature_requests.send', 'Enviar processos de assinatura'),
    ('Cancelar assinaturas', 'signature_requests.cancel', 'Cancelar processos de assinatura'),
    ('Arquivar assinaturas', 'signature_requests.archive', 'Arquivar processos de assinatura')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'contract_templates.view','contract_templates.create','contract_templates.edit',
    'contract_templates.archive','contract_templates.preview',
    'signature_requests.view','signature_requests.create','signature_requests.send',
    'signature_requests.cancel','signature_requests.archive'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'contract_templates.view','contract_templates.preview',
    'signature_requests.view','signature_requests.create','signature_requests.send'
)
WHERE r.`slug` = 'captacao-comercial';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('contract_templates.view', 'signature_requests.view')
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');

-- Modelo padrão de autorização de captador
INSERT INTO `contract_templates` (
    `template_key`, `title`, `description`, `template_type`, `status`,
    `content_html`, `default_signer_role`, `is_default`, `created_at`
) SELECT
    'autorizacao_captador_padrao',
    'Autorização de Captação — Captador Externo',
    'Modelo padrão de autorização para credenciamento de captadores.',
    'autorizacao_captador',
    'ativo',
    '<h2>Autorização de Captação</h2><p>Pelo presente instrumento, <strong>{{collector.name}}</strong>, inscrito(a) sob o documento <strong>{{collector.document_number}}</strong>, residente em {{collector.city_state}}, e-mail {{collector.email}}, autoriza o Dança Carajás Festival a credenciá-lo(a) como captador externo de recursos, conforme candidatura <strong>{{application.application_number}}</strong>.</p><p>Data: {{date.today}}</p><p>{{organization.name}}</p>',
    'captador',
    1,
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `contract_templates` WHERE `template_key` = 'autorizacao_captador_padrao' LIMIT 1
);
