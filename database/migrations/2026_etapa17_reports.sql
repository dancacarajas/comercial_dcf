-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 17
-- Módulo: Relatórios Avançados / Indicadores Gerenciais
-- Idempotente
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `report_snapshots` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `report_key`      VARCHAR(100)    NOT NULL,
    `title`           VARCHAR(180)    NOT NULL,
    `description`     TEXT            NULL DEFAULT NULL,

    `period_start`    DATE            NULL DEFAULT NULL,
    `period_end`      DATE            NULL DEFAULT NULL,

    `filters_json`    LONGTEXT        NULL DEFAULT NULL,
    `metrics_json`    LONGTEXT        NULL DEFAULT NULL,
    `summary_json`    LONGTEXT        NULL DEFAULT NULL,
    `notes`           TEXT            NULL DEFAULT NULL,
    `internal_notes`  TEXT            NULL DEFAULT NULL,

    `status`          VARCHAR(60)     NOT NULL DEFAULT 'gerado',

    `generated_by`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`      BIGINT UNSIGNED NULL DEFAULT NULL,

    `generated_at`    DATETIME        NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NULL DEFAULT NULL,
    `archived_at`     DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),

    KEY `idx_report_snapshots_key`          (`report_key`),
    KEY `idx_report_snapshots_status`       (`status`),
    KEY `idx_report_snapshots_period_start` (`period_start`),
    KEY `idx_report_snapshots_period_end`   (`period_end`),
    KEY `idx_report_snapshots_generated_by` (`generated_by`),
    KEY `idx_report_snapshots_generated_at` (`generated_at`),
    KEY `idx_report_snapshots_archived_at`  (`archived_at`),

    CONSTRAINT `fk_report_snapshots_generated_by`
        FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_report_snapshots_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_report_snapshots_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver relatórios',           'reports.view',      'Visualizar relatórios e indicadores gerenciais'),
    ('Gerar relatórios',         'reports.generate',  'Atualizar consolidação de relatórios internos'),
    ('Criar snapshots',          'reports.snapshots', 'Salvar snapshots manuais de relatórios'),
    ('Arquivar snapshots',       'reports.archive',   'Arquivar e restaurar snapshots de relatórios'),
    ('Imprimir relatórios',      'reports.print',     'Acessar versão de impressão de relatórios')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

UPDATE `permissions` SET `description` = 'Visualizar relatórios e indicadores gerenciais' WHERE `slug` = 'reports.view';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'reports.view', 'reports.generate', 'reports.snapshots', 'reports.archive', 'reports.print'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'reports.view', 'reports.generate', 'reports.snapshots', 'reports.print'
)
WHERE r.`slug` = 'captacao-comercial';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('reports.view', 'reports.generate', 'reports.print')
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('reports.view', 'reports.print')
WHERE r.`slug` = 'leitura-consulta';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` = 'reports.archive';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta')
  AND p.`slug` IN ('reports.snapshots', 'reports.archive');
