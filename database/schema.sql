-- =====================================================================
-- Dança Carajás Captação — Schema inicial (Etapa 1: base administrativa)
--
-- Banco: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Contem APENAS as tabelas fundamentais da base:
--   users, roles, permissions, role_permissions, user_roles,
--   activity_logs, system_settings
--
-- NAO inclui empresas, contatos, oportunidades, leads, cotas ou tarefas.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabela: users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(150)    NOT NULL,
    `email`                 VARCHAR(190)    NOT NULL,
    `password_hash`         VARCHAR(255)    NOT NULL,
    `status`                ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    `must_change_password`  TINYINT(1)      NOT NULL DEFAULT 0,
    `remember_token`        VARCHAR(100)    NULL DEFAULT NULL,
    `failed_login_attempts` INT             NOT NULL DEFAULT 0,
    `locked_until`          DATETIME        NULL DEFAULT NULL,
    `last_login_at`         DATETIME        NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: roles (perfis)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(100)    NOT NULL,
    `description` VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: permissions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)    NOT NULL,
    `slug`        VARCHAR(100)    NOT NULL,
    `description` VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: role_permissions (N:N entre roles e permissions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_rp_permission` (`permission_id`),
    CONSTRAINT `fk_rp_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rp_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: user_roles (N:N entre users e roles)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `role_id`    BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_ur_role` (`role_id`),
    CONSTRAINT `fk_ur_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ur_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: activity_logs (auditoria de acoes)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `action`      VARCHAR(150)    NOT NULL,
    `entity_type` VARCHAR(100)    NULL DEFAULT NULL,
    `entity_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`  VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`  VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_logs_action` (`action`),
    CONSTRAINT `fk_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: system_settings (configuracoes gerais do sistema)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(150)    NOT NULL,
    `setting_value` TEXT            NULL DEFAULT NULL,
    `setting_type`  ENUM('string','integer','boolean','json','text') NOT NULL DEFAULT 'string',
    `description`   VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: companies (Etapa 4 — Empresas / Prospects)
-- Sem exclusao fisica: arquivamento logico via archived_at.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
    `id`                               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                             VARCHAR(180)    NOT NULL,
    `trade_name`                       VARCHAR(180)    NULL DEFAULT NULL,
    `cnpj`                             VARCHAR(20)     NULL DEFAULT NULL,
    `segment`                          VARCHAR(80)     NULL DEFAULT NULL,
    `city`                             VARCHAR(120)    NULL DEFAULT NULL,
    `state`                            CHAR(2)         NULL DEFAULT NULL,
    `website`                          VARCHAR(255)    NULL DEFAULT NULL,
    `linkedin`                         VARCHAR(255)    NULL DEFAULT NULL,
    `general_email`                    VARCHAR(180)    NULL DEFAULT NULL,
    `general_phone`                    VARCHAR(40)     NULL DEFAULT NULL,
    `operates_para`                    TINYINT(1)      NOT NULL DEFAULT 0,
    `operates_carajas`                 TINYINT(1)      NOT NULL DEFAULT 0,
    `operates_parauapebas`             TINYINT(1)      NOT NULL DEFAULT 0,
    `tax_regime_guess`                 VARCHAR(80)     NULL DEFAULT NULL,
    `has_cultural_sponsorship_history` TINYINT(1)      NOT NULL DEFAULT 0,
    `has_rouanet_history`              TINYINT(1)      NOT NULL DEFAULT 0,
    `has_esg_alignment`                TINYINT(1)      NOT NULL DEFAULT 0,
    `priority`                         CHAR(1)         NOT NULL DEFAULT 'C',
    `source`                           VARCHAR(120)    NULL DEFAULT NULL,
    `status`                           VARCHAR(40)     NOT NULL DEFAULT 'prospect',
    `owner_user_id`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes`                            TEXT            NULL DEFAULT NULL,
    `created_by`                       BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                       DATETIME        NULL DEFAULT NULL,
    `archived_at`                      DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_companies_name`       (`name`),
    KEY `idx_companies_cnpj`       (`cnpj`),
    KEY `idx_companies_segment`    (`segment`),
    KEY `idx_companies_city_state` (`city`, `state`),
    KEY `idx_companies_priority`   (`priority`),
    KEY `idx_companies_status`     (`status`),
    KEY `idx_companies_owner`      (`owner_user_id`),
    CONSTRAINT `fk_companies_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_companies_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_companies_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: contacts (Etapa 5 — pessoas vinculadas a empresas)
-- Vinculo obrigatorio a companies; sem exclusao fisica (archived_at).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `name`                VARCHAR(180)    NOT NULL,
    `position_title`      VARCHAR(160)    NULL DEFAULT NULL,
    `department`          VARCHAR(100)    NULL DEFAULT NULL,
    `email`               VARCHAR(180)    NULL DEFAULT NULL,
    `whatsapp`            VARCHAR(40)     NULL DEFAULT NULL,
    `phone`               VARCHAR(40)     NULL DEFAULT NULL,
    `linkedin`            VARCHAR(255)    NULL DEFAULT NULL,
    `decision_level`      VARCHAR(40)     NOT NULL DEFAULT 'nao_informado',
    `influence_level`     VARCHAR(40)     NOT NULL DEFAULT 'media',
    `preferred_channel`   VARCHAR(40)     NOT NULL DEFAULT 'nao_informado',
    `last_interaction_at` DATETIME        NULL DEFAULT NULL,
    `next_contact_at`     DATETIME        NULL DEFAULT NULL,
    `status`              VARCHAR(40)     NOT NULL DEFAULT 'ativo',
    `notes`               TEXT            NULL DEFAULT NULL,
    `owner_user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NULL DEFAULT NULL,
    `archived_at`         DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_contacts_company`         (`company_id`),
    KEY `idx_contacts_name`            (`name`),
    KEY `idx_contacts_email`           (`email`),
    KEY `idx_contacts_department`      (`department`),
    KEY `idx_contacts_decision_level`  (`decision_level`),
    KEY `idx_contacts_influence_level` (`influence_level`),
    KEY `idx_contacts_status`          (`status`),
    KEY `idx_contacts_next_contact_at` (`next_contact_at`),
    KEY `idx_contacts_owner`           (`owner_user_id`),
    CONSTRAINT `fk_contacts_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contacts_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contacts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contacts_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: opportunities (Etapa 6 — CRM de Captação / funil comercial)
-- Vínculo obrigatório a companies; contato opcional; sem exclusão física.
-- quota_interest é texto provisório (tabela de cotas só na Etapa 7).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `opportunities` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `contact_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `title`               VARCHAR(180)    NOT NULL,
    `quota_interest`      VARCHAR(80)     NULL DEFAULT NULL,
    `quota_id`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_reserved_until` DATETIME       NULL DEFAULT NULL,
    `estimated_value`     DECIMAL(12,2)   NULL DEFAULT NULL,
    `probability`         TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `status`              VARCHAR(60)     NOT NULL DEFAULT 'prospect_identificado',
    `source`              VARCHAR(120)    NULL DEFAULT NULL,
    `owner_user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `opened_at`           DATETIME        NOT NULL,
    `last_interaction_at` DATETIME        NULL DEFAULT NULL,
    `next_action_at`      DATETIME        NULL DEFAULT NULL,
    `urgency_level`       VARCHAR(40)     NOT NULL DEFAULT 'normal',
    `lost_reason`         VARCHAR(180)    NULL DEFAULT NULL,
    `notes`               TEXT            NULL DEFAULT NULL,
    `created_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NULL DEFAULT NULL,
    `archived_at`         DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_opportunities_company`     (`company_id`),
    KEY `idx_opportunities_contact`     (`contact_id`),
    KEY `idx_opportunities_status`      (`status`),
    KEY `idx_opportunities_probability` (`probability`),
    KEY `idx_opportunities_owner`       (`owner_user_id`),
    KEY `idx_opportunities_next_action` (`next_action_at`),
    KEY `idx_opportunities_opened_at`   (`opened_at`),
    KEY `idx_opportunities_archived_at` (`archived_at`),
    KEY `idx_opportunities_quota`       (`quota_id`),
    KEY `idx_opportunities_quota_reserved_until` (`quota_reserved_until`),
    CONSTRAINT `fk_opportunities_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_opportunities_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_opportunities_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_opportunities_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_opportunities_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_opportunities_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: quotas (Etapa 7 — Cotas de Patrocínio)
-- Sem exclusão física (archived_at). Quantidades manuais + resumo calculado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotas` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(120)    NOT NULL,
    `commercial_name`    VARCHAR(160)    NULL DEFAULT NULL,
    `amount`             DECIMAL(12,2)   NULL DEFAULT NULL,
    `available_quantity` INT UNSIGNED    NOT NULL DEFAULT 0,
    `reserved_quantity`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `closed_quantity`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `description`        TEXT            NULL DEFAULT NULL,
    `ideal_profile`      TEXT            NULL DEFAULT NULL,
    `status`             VARCHAR(40)     NOT NULL DEFAULT 'disponivel',
    `display_order`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `notes`              TEXT            NULL DEFAULT NULL,
    `created_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME        NULL DEFAULT NULL,
    `archived_at`        DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_quotas_name`          (`name`),
    KEY `idx_quotas_status`        (`status`),
    KEY `idx_quotas_amount`        (`amount`),
    KEY `idx_quotas_display_order` (`display_order`),
    KEY `idx_quotas_archived_at`   (`archived_at`),
    CONSTRAINT `fk_quotas_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_quotas_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: tasks (Etapa 8 — Tarefas e Follow-ups)
-- Vínculos opcionais com empresa, contato e oportunidade. Sem exclusão física.
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
-- Tabela: proposals (Etapa 10 — Propostas Comerciais)
-- Vínculos com empresa (obrigatório), contato, oportunidade e cota.
-- PDF opcional em storage/uploads/proposals (fora da pasta pública).
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
-- Tabela: leads (Etapa 9 — Leads do Site)
-- integration_payload em LONGTEXT (compatível MariaDB Hostinger).
-- ---------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DADOS INICIAIS (seed minimo)
-- =====================================================================

-- Perfis obrigatorios do sistema (Etapa 3)
INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
    ('Administrador Geral',     'administrador-geral',  'Acesso total ao sistema'),
    ('Captação / Comercial',    'captacao-comercial',   'Operação comercial (preparado para CRM)'),
    ('Produção / Coordenação',  'producao-coordenacao', 'Patrocinadores fechados e contrapartidas'),
    ('Comunicação',             'comunicacao',          'Entregas de marca e comprovação de comunicação'),
    ('Leitura / Consulta',      'leitura-consulta',     'Somente leitura')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Permissoes administrativas + reservadas (modulos futuros)
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Acessar painel',        'dashboard.view',        'Visualizar o painel'),
    ('Ver usuários',          'users.view',            'Listar e visualizar usuários'),
    ('Criar usuários',        'users.create',          'Criar usuários'),
    ('Editar usuários',       'users.edit',            'Editar usuários'),
    ('Excluir usuários',      'users.delete',          'Excluir usuários (reservado)'),
    ('Ativar usuários',       'users.activate',        'Ativar usuários'),
    ('Inativar usuários',     'users.deactivate',      'Inativar usuários'),
    ('Redefinir senha',       'users.reset_password',  'Redefinir senha provisória'),
    ('Ver perfis',            'roles.view',            'Listar e visualizar perfis'),
    ('Criar perfis',          'roles.create',          'Criar perfis'),
    ('Editar perfis',         'roles.edit',            'Editar permissões de perfis'),
    ('Excluir perfis',        'roles.delete',          'Excluir perfis (reservado)'),
    ('Ver permissões',        'permissions.view',      'Listar permissões'),
    ('Atribuir permissões',   'permissions.assign',    'Atribuir permissões a perfis'),
    ('Ver logs',              'logs.view',             'Visualizar logs de atividade'),
    ('Ver configurações',     'settings.view',         'Visualizar configurações'),
    ('Ver empresas',          'companies.view',        'Reservada para módulo futuro'),
    ('Criar empresas',        'companies.create',      'Reservada para módulo futuro'),
    ('Editar empresas',       'companies.edit',        'Reservada para módulo futuro'),
    ('Ver contatos',          'contacts.view',         'Reservada para módulo futuro'),
    ('Criar contatos',        'contacts.create',       'Reservada para módulo futuro'),
    ('Editar contatos',       'contacts.edit',         'Reservada para módulo futuro'),
    ('Ver oportunidades',     'opportunities.view',    'Reservada para módulo futuro'),
    ('Criar oportunidades',   'opportunities.create',  'Reservada para módulo futuro'),
    ('Editar oportunidades',  'opportunities.edit',    'Reservada para módulo futuro'),
    ('Ver cotas',             'quotas.view',           'Reservada para módulo futuro'),
    ('Ver tarefas',           'tasks.view',            'Reservada para módulo futuro'),
    ('Ver leads',             'leads.view',            'Reservada para módulo futuro'),
    ('Ver propostas',         'proposals.view',        'Visualizar propostas comerciais'),
    ('Criar propostas',       'proposals.create',      'Cadastrar propostas comerciais'),
    ('Editar propostas',      'proposals.edit',        'Editar propostas e alterar status'),
    ('Arquivar propostas',    'proposals.archive',     'Arquivar e restaurar propostas'),
    ('Registrar envio',       'proposals.send',        'Marcar proposta como enviada'),
    ('Versionar propostas',   'proposals.version',     'Criar nova versão de proposta'),
    ('Ver documentos',        'documents.view',        'Reservada para módulo futuro'),
    ('Ver patrocinadores',    'sponsors.view',         'Reservada para módulo futuro'),
    ('Ver contrapartidas',    'counterparts.view',     'Reservada para módulo futuro'),
    ('Ver relatórios',        'reports.view',          'Reservada para módulo futuro')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Administrador Geral: TODAS as permissoes
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Captação / Comercial
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view',
    'companies.view', 'companies.create', 'companies.edit',
    'contacts.view', 'contacts.create', 'contacts.edit',
    'opportunities.view', 'opportunities.create', 'opportunities.edit',
    'quotas.view', 'tasks.view', 'leads.view',
    'proposals.view', 'proposals.create', 'proposals.edit',
    'proposals.archive', 'proposals.send', 'proposals.version',
    'documents.view'
)
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Produção / Coordenação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'documents.view', 'reports.view',
    'proposals.view'
)
WHERE r.`slug` = 'producao-coordenacao'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Comunicação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'documents.view', 'reports.view',
    'proposals.view'
)
WHERE r.`slug` = 'comunicacao'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Leitura / Consulta
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'companies.view', 'contacts.view', 'opportunities.view',
    'quotas.view', 'tasks.view', 'leads.view', 'proposals.view',
    'documents.view', 'sponsors.view', 'counterparts.view', 'reports.view'
)
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Configuracoes iniciais do sistema
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
    ('app_name',     'Dança Carajás Captação', 'string',  'Nome do sistema'),
    ('festival_year','2026',                   'integer', 'Ano do festival'),
    ('maintenance',  '0',                      'boolean', 'Modo manutencao (1=ativo)')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ---------------------------------------------------------------------
-- USUARIO ADMINISTRADOR INICIAL
-- ---------------------------------------------------------------------
-- IMPORTANTE: o hash abaixo corresponde a senha "Mudar@123".
-- Gere o seu proprio hash com:
--   php -r "echo password_hash('SuaSenhaForte', PASSWORD_DEFAULT);"
-- e substitua o valor antes de usar em producao.
INSERT INTO `users` (`name`, `email`, `password_hash`, `status`, `must_change_password`) VALUES
    ('Administrador', 'admin@dancacarajas.com',
     '$2y$10$j.CQvNsL98U/0CtZosecYuZs.QulbIRe3oMhtctrczuOyaSpbKRIO',
     'active', 1)
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Vincula o admin ao perfil Administrador Geral
INSERT INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id`
FROM `users` u
JOIN `roles` r ON r.`slug` = 'administrador-geral'
WHERE u.`email` = 'admin@dancacarajas.com'
ON DUPLICATE KEY UPDATE `user_id` = `user_roles`.`user_id`;
