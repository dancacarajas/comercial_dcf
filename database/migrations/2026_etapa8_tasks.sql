-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 8
-- Módulo: Tarefas e Follow-ups
--
-- Banco: MariaDB 10.3+ / MySQL 5.7+ (CREATE TABLE IF NOT EXISTS).
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Cria a tabela `tasks` (vínculos opcionais com empresa, contato e
-- oportunidade) e cria/atribui as permissões tasks.create / tasks.edit /
-- tasks.complete. Sem exclusão física (archived_at).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Tabela tasks
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `title`            VARCHAR(180)    NOT NULL,
    `description`      TEXT            NULL DEFAULT NULL,

    `type`             VARCHAR(60)     NOT NULL DEFAULT 'follow_up',

    `company_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`   BIGINT UNSIGNED NULL DEFAULT NULL,

    `assigned_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,

    `due_date`         DATE            NULL DEFAULT NULL,
    `due_time`         TIME            NULL DEFAULT NULL,

    `priority`         VARCHAR(40)     NOT NULL DEFAULT 'normal',
    `status`           VARCHAR(40)     NOT NULL DEFAULT 'pendente',

    `result`           TEXT            NULL DEFAULT NULL,
    `completed_at`     DATETIME        NULL DEFAULT NULL,
    `completed_by`     BIGINT UNSIGNED NULL DEFAULT NULL,

    `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NULL DEFAULT NULL,
    `archived_at`      DATETIME        NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_tasks_title`          (`title`),
    KEY `idx_tasks_type`           (`type`),
    KEY `idx_tasks_company`        (`company_id`),
    KEY `idx_tasks_contact`        (`contact_id`),
    KEY `idx_tasks_opportunity`    (`opportunity_id`),
    KEY `idx_tasks_assigned_user`  (`assigned_user_id`),
    KEY `idx_tasks_due_date_time`  (`due_date`, `due_time`),
    KEY `idx_tasks_priority`       (`priority`),
    KEY `idx_tasks_status`         (`status`),
    KEY `idx_tasks_archived_at`    (`archived_at`),

    CONSTRAINT `fk_tasks_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_assigned_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_completed_by`
        FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) Permissões administrativas de tarefas (Etapa 3 só criou tasks.view)
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar tarefas',    'tasks.create',   'Criar tarefas e follow-ups'),
    ('Editar tarefas',   'tasks.edit',     'Editar, arquivar e restaurar tarefas'),
    ('Concluir tarefas', 'tasks.complete', 'Concluir e reabrir tarefas')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Administrador Geral: garante TODAS as permissões (inclui as novas).
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Captação/Comercial, Produção/Coordenação e Comunicação: view+create+edit+complete.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('tasks.view', 'tasks.create', 'tasks.edit', 'tasks.complete')
WHERE r.`slug` IN ('captacao-comercial', 'producao-coordenacao', 'comunicacao')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Leitura/Consulta: somente tasks.view.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'tasks.view'
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
