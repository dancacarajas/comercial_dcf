-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 9
-- Módulo: Leads do Site + endpoint público
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `leads` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(180)    NOT NULL,
    `company_name`     VARCHAR(180)    NULL DEFAULT NULL,
    `role_title`       VARCHAR(160)    NULL DEFAULT NULL,
    `email`            VARCHAR(180)    NULL DEFAULT NULL,
    `whatsapp`         VARCHAR(40)     NULL DEFAULT NULL,
    `city`             VARCHAR(120)    NULL DEFAULT NULL,
    `state`            CHAR(2)         NULL DEFAULT NULL,
    `segment`          VARCHAR(80)     NULL DEFAULT NULL,
    `origin_page`      VARCHAR(255)    NULL DEFAULT NULL,
    `source_url`       VARCHAR(255)    NULL DEFAULT NULL,
    `form_id`          VARCHAR(120)    NULL DEFAULT NULL,
    `form_name`        VARCHAR(180)    NULL DEFAULT NULL,
    `interest`         VARCHAR(120)    NULL DEFAULT NULL,
    `message`          TEXT            NULL DEFAULT NULL,
    `contact_consent`  TINYINT(1)      NOT NULL DEFAULT 0,
    `ip_address`       VARCHAR(80)     NULL DEFAULT NULL,
    `user_agent`       VARCHAR(255)    NULL DEFAULT NULL,
    `referrer`         VARCHAR(255)    NULL DEFAULT NULL,
    `utm_source`       VARCHAR(120)    NULL DEFAULT NULL,
    `utm_medium`       VARCHAR(120)    NULL DEFAULT NULL,
    `utm_campaign`     VARCHAR(120)    NULL DEFAULT NULL,
    `utm_content`      VARCHAR(120)    NULL DEFAULT NULL,
    `utm_term`         VARCHAR(120)    NULL DEFAULT NULL,
    `status`           VARCHAR(40)     NOT NULL DEFAULT 'novo',
    `assigned_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `company_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `task_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `integration_payload` LONGTEXT     NULL DEFAULT NULL,
    `converted_at`     DATETIME        NULL DEFAULT NULL,
    `converted_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NULL DEFAULT NULL,
    `archived_at`      DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leads_name`          (`name`),
    KEY `idx_leads_company_name`  (`company_name`),
    KEY `idx_leads_email`         (`email`),
    KEY `idx_leads_whatsapp`      (`whatsapp`),
    KEY `idx_leads_status`        (`status`),
    KEY `idx_leads_origin_page`   (`origin_page`),
    KEY `idx_leads_assigned_user` (`assigned_user_id`),
    KEY `idx_leads_company`       (`company_id`),
    KEY `idx_leads_contact`       (`contact_id`),
    KEY `idx_leads_opportunity`   (`opportunity_id`),
    KEY `idx_leads_task`          (`task_id`),
    KEY `idx_leads_created_at`    (`created_at`),
    KEY `idx_leads_archived_at`   (`archived_at`),
    CONSTRAINT `fk_leads_assigned_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_converted_by`
        FOREIGN KEY (`converted_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Criar leads',    'leads.create',  'Cadastrar leads manualmente'),
    ('Editar leads',   'leads.edit',    'Editar, duplicar, descartar e restaurar leads'),
    ('Converter leads','leads.convert', 'Converter leads em empresa, contato, oportunidade e tarefa'),
    ('Arquivar leads', 'leads.archive', 'Arquivar leads')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('leads.view','leads.create','leads.edit','leads.convert','leads.archive')
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r JOIN `permissions` p ON p.`slug` = 'leads.view'
WHERE r.`slug` IN ('producao-coordenacao','comunicacao','leitura-consulta')
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;
