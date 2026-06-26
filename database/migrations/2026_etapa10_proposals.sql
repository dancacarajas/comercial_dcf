-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 10
-- Módulo: Propostas Comerciais
--
-- Cria tabela `proposals`, permissões complementares e atribuições por perfil.
-- Idempotente: CREATE TABLE IF NOT EXISTS, ON DUPLICATE KEY UPDATE
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Tabela proposals
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proposals` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `company_id`           BIGINT UNSIGNED NOT NULL,
    `contact_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`             BIGINT UNSIGNED NULL DEFAULT NULL,

    `title`                VARCHAR(180)    NOT NULL,
    `type`                 VARCHAR(80)     NOT NULL DEFAULT 'proposta_por_cota',

    `proposed_value`       DECIMAL(12,2)   NULL DEFAULT NULL,

    `version_number`       INT UNSIGNED    NOT NULL DEFAULT 1,
    `parent_proposal_id`   BIGINT UNSIGNED NULL DEFAULT NULL,

    `status`               VARCHAR(60)     NOT NULL DEFAULT 'rascunho',

    `created_on`           DATE            NULL DEFAULT NULL,
    `sent_at`              DATETIME        NULL DEFAULT NULL,
    `valid_until`          DATE            NULL DEFAULT NULL,

    `responsible_user_id`  BIGINT UNSIGNED NULL DEFAULT NULL,

    `pdf_file_path`        VARCHAR(255)    NULL DEFAULT NULL,
    `pdf_original_name`    VARCHAR(180)    NULL DEFAULT NULL,

    `revision_notes`       TEXT            NULL DEFAULT NULL,
    `notes`                TEXT            NULL DEFAULT NULL,

    `created_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `sent_by`              BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NULL DEFAULT NULL,
    `archived_at`          DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_proposals_company`      (`company_id`),
    KEY `idx_proposals_contact`      (`contact_id`),
    KEY `idx_proposals_opportunity`  (`opportunity_id`),
    KEY `idx_proposals_quota`        (`quota_id`),
    KEY `idx_proposals_status`       (`status`),
    KEY `idx_proposals_type`         (`type`),
    KEY `idx_proposals_responsible`  (`responsible_user_id`),
    KEY `idx_proposals_valid_until`  (`valid_until`),
    KEY `idx_proposals_sent_at`      (`sent_at`),
    KEY `idx_proposals_parent`       (`parent_proposal_id`),
    KEY `idx_proposals_archived_at`  (`archived_at`),

    CONSTRAINT `fk_proposals_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_parent`
        FOREIGN KEY (`parent_proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_responsible_user`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_proposals_sent_by`
        FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) Permissões (proposals.view já existe na Etapa 3)
-- ---------------------------------------------------------------------
UPDATE `permissions`
   SET `name` = 'Ver propostas',
       `description` = 'Visualizar propostas comerciais'
 WHERE `slug` = 'proposals.view';

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar propostas',        'proposals.create',  'Cadastrar propostas comerciais'),
    ('Editar propostas',       'proposals.edit',    'Editar propostas e alterar status'),
    ('Arquivar propostas',     'proposals.archive', 'Arquivar e restaurar propostas'),
    ('Registrar envio',        'proposals.send',    'Marcar proposta como enviada'),
    ('Versionar propostas',    'proposals.version', 'Criar nova versão de proposta')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Administrador Geral: todas as permissões.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Captação / Comercial: proposals.*
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'proposals.view', 'proposals.create', 'proposals.edit',
    'proposals.archive', 'proposals.send', 'proposals.version'
)
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Produção, Comunicação e Leitura: somente proposals.view.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'proposals.view'
WHERE r.`slug` IN ('leitura-consulta', 'producao-coordenacao', 'comunicacao')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
