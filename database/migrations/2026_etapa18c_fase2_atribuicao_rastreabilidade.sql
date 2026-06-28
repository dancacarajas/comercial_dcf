-- =====================================================================
-- Dança Carajás Captação — Etapa 18C / Fase 2
-- Atribuição comercial e rastreabilidade do captador
--   - collector_assignments: empresas autorizadas/reservadas ao captador
--   - collector_deals:        trilha de origem comercial da captação
-- Idempotente: CREATE TABLE IF NOT EXISTS / INSERT IGNORE / DELETE
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Tabela: collector_assignments
-- Autorização/reserva de abordagem de uma empresa por um captador.
-- Resolve o risco de dois captadores abordarem a mesma empresa.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `collector_assignments` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `collector_id`    BIGINT UNSIGNED NOT NULL,
    `company_id`      BIGINT UNSIGNED NOT NULL,

    `assignment_type` VARCHAR(30)     NOT NULL DEFAULT 'exclusiva',
    `status`          VARCHAR(30)     NOT NULL DEFAULT 'solicitada',
    `exclusive_until` DATE            NULL DEFAULT NULL,

    `authorized_by`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `authorized_at`   DATETIME        NULL DEFAULT NULL,
    `cancelled_at`    DATETIME        NULL DEFAULT NULL,

    `notes`           TEXT            NULL DEFAULT NULL,

    `created_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NULL DEFAULT NULL,
    `archived_at`     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_collector_assignments_collector`   (`collector_id`),
    KEY `idx_collector_assignments_company`     (`company_id`),
    KEY `idx_collector_assignments_status`      (`status`),
    KEY `idx_collector_assignments_type`        (`assignment_type`),
    KEY `idx_collector_assignments_exclusive`   (`company_id`, `status`, `exclusive_until`),
    KEY `idx_collector_assignments_archived_at` (`archived_at`),

    CONSTRAINT `fk_collector_assignments_collector`
        FOREIGN KEY (`collector_id`) REFERENCES `collectors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_assignments_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_assignments_authorized_by`
        FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_assignments_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_assignments_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: collector_deals
-- Trilha oficial da captação: vincula o captador às entidades do funil
-- (empresa, contato, oportunidade, proposta, patrocinador, financeiro).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `collector_deals` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `collector_id`            BIGINT UNSIGNED NOT NULL,
    `collector_assignment_id` BIGINT UNSIGNED NULL DEFAULT NULL,

    `company_id`              BIGINT UNSIGNED NOT NULL,
    `contact_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `sponsor_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `financial_entry_id`      BIGINT UNSIGNED NULL DEFAULT NULL,

    `deal_status`             VARCHAR(30)     NOT NULL DEFAULT 'lead_indicado',
    `attribution_type`        VARCHAR(30)     NOT NULL DEFAULT 'direta',
    `source`                  VARCHAR(120)    NULL DEFAULT NULL,

    `notes`                   TEXT            NULL DEFAULT NULL,

    `created_by`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME        NULL DEFAULT NULL,
    `archived_at`             DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_collector_deals_collector`    (`collector_id`),
    KEY `idx_collector_deals_assignment`   (`collector_assignment_id`),
    KEY `idx_collector_deals_company`      (`company_id`),
    KEY `idx_collector_deals_contact`      (`contact_id`),
    KEY `idx_collector_deals_opportunity`  (`opportunity_id`),
    KEY `idx_collector_deals_proposal`     (`proposal_id`),
    KEY `idx_collector_deals_sponsor`      (`sponsor_id`),
    KEY `idx_collector_deals_financial`    (`financial_entry_id`),
    KEY `idx_collector_deals_status`       (`deal_status`),
    KEY `idx_collector_deals_archived_at`  (`archived_at`),

    CONSTRAINT `fk_collector_deals_collector`
        FOREIGN KEY (`collector_id`) REFERENCES `collectors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_assignment`
        FOREIGN KEY (`collector_assignment_id`) REFERENCES `collector_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_financial`
        FOREIGN KEY (`financial_entry_id`) REFERENCES `financial_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_collector_deals_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Permissões da Fase 2 (atribuição comercial e rastreabilidade)
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver atribuições de captadores',      'collector_assignments.view',   'Visualizar autorizações/reservas de empresas por captador'),
    ('Gerenciar atribuições de captadores','collector_assignments.manage', 'Criar, autorizar e cancelar autorizações de abordagem'),
    ('Ver captações de captadores',        'collector_deals.view',         'Visualizar trilha de origem comercial da captação'),
    ('Gerenciar captações de captadores',  'collector_deals.manage',       'Criar e vincular captações ao funil comercial')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- administrador-geral: view + manage nas duas entidades
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'collector_assignments.view', 'collector_assignments.manage',
    'collector_deals.view', 'collector_deals.manage'
)
WHERE r.`slug` = 'administrador-geral';

-- captacao-comercial: view + manage nas duas entidades
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'collector_assignments.view', 'collector_assignments.manage',
    'collector_deals.view', 'collector_deals.manage'
)
WHERE r.`slug` = 'captacao-comercial';

-- producao-coordenacao / comunicacao / leitura-consulta: somente leitura
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('collector_assignments.view', 'collector_deals.view')
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');

-- O captador externo NÃO gerencia atribuições/deals pelo CRM interno.
DELETE rp
FROM `role_permissions` rp
JOIN `roles` r ON r.`id` = rp.`role_id`
JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captador-externo'
  AND p.`slug` IN (
      'collector_assignments.view', 'collector_assignments.manage',
      'collector_deals.view', 'collector_deals.manage'
  );
