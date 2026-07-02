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
-- Tabela: sponsors (Etapa 12 — Patrocinadores / Fechamentos Comerciais)
-- FK primary_document_id adicionada após criação de documents (circular).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sponsors` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`                 BIGINT UNSIGNED NOT NULL,
    `contact_id`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                   BIGINT UNSIGNED NULL DEFAULT NULL,
    `primary_document_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `sponsor_display_name`       VARCHAR(180)    NOT NULL,
    `sponsorship_type`           VARCHAR(80)     NOT NULL DEFAULT 'patrocinio_direto',
    `funding_mechanism`          VARCHAR(80)     NOT NULL DEFAULT 'lei_rouanet',
    `project_year`               SMALLINT UNSIGNED NOT NULL DEFAULT 2026,
    `festival_edition`           VARCHAR(120)    NOT NULL DEFAULT 'Dança Carajás Festival 2026',
    `quota_snapshot_name`        VARCHAR(180)    NULL DEFAULT NULL,
    `quota_snapshot_amount`      DECIMAL(12,2)   NULL DEFAULT NULL,
    `committed_amount`           DECIMAL(12,2)   NULL DEFAULT NULL,
    `confirmed_amount`           DECIMAL(12,2)   NULL DEFAULT NULL,
    `in_kind_description`        TEXT            NULL DEFAULT NULL,
    `in_kind_estimated_value`    DECIMAL(12,2)   NULL DEFAULT NULL,
    `status`                     VARCHAR(60)     NOT NULL DEFAULT 'fechamento_registrado',
    `payment_status`             VARCHAR(60)     NOT NULL DEFAULT 'pendente',
    `closed_at`                  DATETIME        NULL DEFAULT NULL,
    `confirmed_at`               DATETIME        NULL DEFAULT NULL,
    `expected_payment_date`      DATE            NULL DEFAULT NULL,
    `received_at`                DATE            NULL DEFAULT NULL,
    `public_announcement_allowed` TINYINT(1)     NOT NULL DEFAULT 0,
    `pronac_number`              VARCHAR(40)     NULL DEFAULT NULL,
    `incentive_law`              VARCHAR(120)    NULL DEFAULT NULL,
    `incentive_notes`            TEXT            NULL DEFAULT NULL,
    `responsible_user_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes`                      TEXT            NULL DEFAULT NULL,
    `internal_notes`             TEXT            NULL DEFAULT NULL,
    `created_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `confirmed_by`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME        NULL DEFAULT NULL,
    `archived_at`                DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sponsors_company`            (`company_id`),
    KEY `idx_sponsors_contact`            (`contact_id`),
    KEY `idx_sponsors_opportunity`        (`opportunity_id`),
    KEY `idx_sponsors_proposal`           (`proposal_id`),
    KEY `idx_sponsors_quota`              (`quota_id`),
    KEY `idx_sponsors_primary_document`   (`primary_document_id`),
    KEY `idx_sponsors_type`               (`sponsorship_type`),
    KEY `idx_sponsors_funding`            (`funding_mechanism`),
    KEY `idx_sponsors_status`             (`status`),
    KEY `idx_sponsors_payment_status`     (`payment_status`),
    KEY `idx_sponsors_project_year`       (`project_year`),
    KEY `idx_sponsors_closed_at`          (`closed_at`),
    KEY `idx_sponsors_confirmed_at`       (`confirmed_at`),
    KEY `idx_sponsors_expected_payment_date` (`expected_payment_date`),
    KEY `idx_sponsors_responsible`        (`responsible_user_id`),
    KEY `idx_sponsors_archived_at`        (`archived_at`),
    CONSTRAINT `fk_sponsors_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsors_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: counterparts (Etapa 13 — Contrapartidas dos Patrocinadores)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `counterparts` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sponsor_id`             BIGINT UNSIGNED NOT NULL,
    `company_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `evidence_document_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `title`                  VARCHAR(180)    NOT NULL,
    `category`               VARCHAR(80)     NOT NULL DEFAULT 'divulgacao_marca',
    `delivery_type`          VARCHAR(80)     NOT NULL DEFAULT 'entrega_unica',
    `description`            TEXT            NULL DEFAULT NULL,
    `promised_quantity`      DECIMAL(10,2)   NULL DEFAULT NULL,
    `delivered_quantity`     DECIMAL(10,2)   NULL DEFAULT NULL,
    `unit`                   VARCHAR(60)     NULL DEFAULT NULL,
    `priority`               VARCHAR(40)     NOT NULL DEFAULT 'media',
    `status`                 VARCHAR(60)     NOT NULL DEFAULT 'planejada',
    `due_date`               DATE            NULL DEFAULT NULL,
    `started_at`             DATETIME        NULL DEFAULT NULL,
    `delivered_at`           DATETIME        NULL DEFAULT NULL,
    `approved_at`            DATETIME        NULL DEFAULT NULL,
    `evidence_description`   TEXT            NULL DEFAULT NULL,
    `evidence_url`           VARCHAR(255)    NULL DEFAULT NULL,
    `responsible_user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes`                  TEXT            NULL DEFAULT NULL,
    `internal_notes`         TEXT            NULL DEFAULT NULL,
    `created_by`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivered_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME        NULL DEFAULT NULL,
    `archived_at`            DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_counterparts_sponsor`            (`sponsor_id`),
    KEY `idx_counterparts_company`            (`company_id`),
    KEY `idx_counterparts_contact`            (`contact_id`),
    KEY `idx_counterparts_opportunity`        (`opportunity_id`),
    KEY `idx_counterparts_proposal`           (`proposal_id`),
    KEY `idx_counterparts_quota`              (`quota_id`),
    KEY `idx_counterparts_evidence_document`  (`evidence_document_id`),
    KEY `idx_counterparts_category`           (`category`),
    KEY `idx_counterparts_delivery_type`      (`delivery_type`),
    KEY `idx_counterparts_priority`           (`priority`),
    KEY `idx_counterparts_status`             (`status`),
    KEY `idx_counterparts_due_date`           (`due_date`),
    KEY `idx_counterparts_responsible`        (`responsible_user_id`),
    KEY `idx_counterparts_archived_at`        (`archived_at`),
    CONSTRAINT `fk_counterparts_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_counterparts_delivered_by`
        FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: contracts (Etapa 14 — Contratos / Instrumentos de Formalização)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contracts` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sponsor_id`                      BIGINT UNSIGNED NOT NULL,
    `company_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                        BIGINT UNSIGNED NULL DEFAULT NULL,
    `draft_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `final_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `signed_document_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `contract_number`                 VARCHAR(80)     NULL DEFAULT NULL,
    `title`                           VARCHAR(180)    NOT NULL,
    `contract_type`                   VARCHAR(80)     NOT NULL DEFAULT 'termo_patrocinio',
    `formalized_value`                DECIMAL(12,2)   NULL DEFAULT NULL,
    `funding_mechanism`               VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',
    `status`                          VARCHAR(60)     NOT NULL DEFAULT 'minuta',
    `review_status`                   VARCHAR(60)     NOT NULL DEFAULT 'nao_revisado',
    `signature_status`                VARCHAR(60)     NOT NULL DEFAULT 'nao_enviado',
    `start_date`                      DATE            NULL DEFAULT NULL,
    `end_date`                        DATE            NULL DEFAULT NULL,
    `sent_for_signature_at`           DATETIME        NULL DEFAULT NULL,
    `signed_at`                       DATETIME        NULL DEFAULT NULL,
    `effective_at`                    DATE            NULL DEFAULT NULL,
    `ended_at`                        DATE            NULL DEFAULT NULL,
    `sponsor_signatory_name`          VARCHAR(180)    NULL DEFAULT NULL,
    `sponsor_signatory_email`         VARCHAR(180)    NULL DEFAULT NULL,
    `sponsor_signatory_position`      VARCHAR(120)    NULL DEFAULT NULL,
    `sponsor_signatory_document`      VARCHAR(80)     NULL DEFAULT NULL,
    `organization_signatory_name`     VARCHAR(180)    NULL DEFAULT NULL,
    `organization_signatory_email`    VARCHAR(180)    NULL DEFAULT NULL,
    `organization_signatory_position` VARCHAR(120)    NULL DEFAULT NULL,
    `approval_notes`                  TEXT            NULL DEFAULT NULL,
    `signature_notes`                 TEXT            NULL DEFAULT NULL,
    `legal_notes`                     TEXT            NULL DEFAULT NULL,
    `notes`                           TEXT            NULL DEFAULT NULL,
    `internal_notes`                  TEXT            NULL DEFAULT NULL,
    `responsible_user_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `signed_registered_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `approved_at`                     DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_contracts_sponsor`            (`sponsor_id`),
    KEY `idx_contracts_company`            (`company_id`),
    KEY `idx_contracts_contact`            (`contact_id`),
    KEY `idx_contracts_opportunity`        (`opportunity_id`),
    KEY `idx_contracts_proposal`           (`proposal_id`),
    KEY `idx_contracts_quota`              (`quota_id`),
    KEY `idx_contracts_draft_document`     (`draft_document_id`),
    KEY `idx_contracts_final_document`     (`final_document_id`),
    KEY `idx_contracts_signed_document`    (`signed_document_id`),
    KEY `idx_contracts_contract_number`    (`contract_number`),
    KEY `idx_contracts_type`               (`contract_type`),
    KEY `idx_contracts_status`             (`status`),
    KEY `idx_contracts_review_status`      (`review_status`),
    KEY `idx_contracts_signature_status`   (`signature_status`),
    KEY `idx_contracts_start_date`         (`start_date`),
    KEY `idx_contracts_end_date`           (`end_date`),
    KEY `idx_contracts_signed_at`          (`signed_at`),
    KEY `idx_contracts_responsible`        (`responsible_user_id`),
    KEY `idx_contracts_archived_at`        (`archived_at`),
    CONSTRAINT `fk_contracts_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_signed_registered_by`
        FOREIGN KEY (`signed_registered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: documents (Etapa 11 — Documentos e Arquivos)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `lead_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `sponsor_id`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `counterpart_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `contract_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `financial_entry_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `sponsor_dossier_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
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
    KEY `idx_documents_sponsor`      (`sponsor_id`),
    KEY `idx_documents_counterpart`  (`counterpart_id`),
    KEY `idx_documents_contract`     (`contract_id`),
    KEY `idx_documents_financial_entry` (`financial_entry_id`),
    KEY `idx_documents_sponsor_dossier` (`sponsor_dossier_id`),
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
    CONSTRAINT `fk_documents_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_counterpart`
        FOREIGN KEY (`counterpart_id`) REFERENCES `counterparts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_contract`
        FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
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

ALTER TABLE `sponsors`
    ADD CONSTRAINT `fk_sponsors_primary_document`
        FOREIGN KEY (`primary_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `counterparts`
    ADD CONSTRAINT `fk_counterparts_evidence_document`
        FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `contracts`
    ADD CONSTRAINT `fk_contracts_draft_document`
        FOREIGN KEY (`draft_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `contracts`
    ADD CONSTRAINT `fk_contracts_final_document`
        FOREIGN KEY (`final_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `contracts`
    ADD CONSTRAINT `fk_contracts_signed_document`
        FOREIGN KEY (`signed_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

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

-- ---------------------------------------------------------------------
-- Tabela: financial_entries (Etapa 15 — Financeiro Detalhado / Parcelas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `financial_entries` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sponsor_id`              BIGINT UNSIGNED NOT NULL,
    `contract_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `company_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `proof_document_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `receipt_document_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `fiscal_document_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `entry_number`            VARCHAR(80)     NULL DEFAULT NULL,
    `title`                   VARCHAR(180)    NOT NULL,
    `entry_type`              VARCHAR(80)     NOT NULL DEFAULT 'parcela_patrocinio',
    `funding_mechanism`       VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',
    `payment_method`          VARCHAR(80)     NOT NULL DEFAULT 'nao_definido',
    `status`                  VARCHAR(60)     NOT NULL DEFAULT 'previsto',
    `fiscal_document_status`  VARCHAR(60)     NOT NULL DEFAULT 'nao_aplicavel',
    `installment_number`      INT             NULL DEFAULT NULL,
    `installments_total`      INT             NULL DEFAULT NULL,
    `planned_amount`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `received_amount`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `remaining_amount`        DECIMAL(12,2)   NULL DEFAULT NULL,
    `due_date`                DATE            NULL DEFAULT NULL,
    `expected_payment_date`   DATE            NULL DEFAULT NULL,
    `received_at`             DATETIME        NULL DEFAULT NULL,
    `reconciled_at`           DATETIME        NULL DEFAULT NULL,
    `cancelled_at`            DATETIME        NULL DEFAULT NULL,
    `payer_name`              VARCHAR(180)    NULL DEFAULT NULL,
    `payer_document`          VARCHAR(80)     NULL DEFAULT NULL,
    `bank_reference`          VARCHAR(120)    NULL DEFAULT NULL,
    `transaction_reference`   VARCHAR(120)    NULL DEFAULT NULL,
    `proof_notes`             TEXT            NULL DEFAULT NULL,
    `receipt_notes`           TEXT            NULL DEFAULT NULL,
    `fiscal_notes`            TEXT            NULL DEFAULT NULL,
    `reconciliation_notes`    TEXT            NULL DEFAULT NULL,
    `notes`                   TEXT            NULL DEFAULT NULL,
    `internal_notes`          TEXT            NULL DEFAULT NULL,
    `responsible_user_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `confirmed_by`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `reconciled_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `cancelled_by`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME        NULL DEFAULT NULL,
    `archived_at`             DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_financial_entries_sponsor`              (`sponsor_id`),
    KEY `idx_financial_entries_contract`             (`contract_id`),
    KEY `idx_financial_entries_company`              (`company_id`),
    KEY `idx_financial_entries_contact`              (`contact_id`),
    KEY `idx_financial_entries_opportunity`          (`opportunity_id`),
    KEY `idx_financial_entries_proposal`             (`proposal_id`),
    KEY `idx_financial_entries_quota`                (`quota_id`),
    KEY `idx_financial_entries_proof_document`       (`proof_document_id`),
    KEY `idx_financial_entries_receipt_document`     (`receipt_document_id`),
    KEY `idx_financial_entries_fiscal_document`      (`fiscal_document_id`),
    KEY `idx_financial_entries_entry_number`         (`entry_number`),
    KEY `idx_financial_entries_type`                 (`entry_type`),
    KEY `idx_financial_entries_funding`              (`funding_mechanism`),
    KEY `idx_financial_entries_method`               (`payment_method`),
    KEY `idx_financial_entries_status`               (`status`),
    KEY `idx_financial_entries_fiscal_status`        (`fiscal_document_status`),
    KEY `idx_financial_entries_due_date`             (`due_date`),
    KEY `idx_financial_entries_expected_payment_date` (`expected_payment_date`),
    KEY `idx_financial_entries_received_at`          (`received_at`),
    KEY `idx_financial_entries_reconciled_at`        (`reconciled_at`),
    KEY `idx_financial_entries_responsible`          (`responsible_user_id`),
    KEY `idx_financial_entries_archived_at`          (`archived_at`),
    CONSTRAINT `fk_financial_entries_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_contract`
        FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_reconciled_by`
        FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_cancelled_by`
        FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_financial_entries_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `documents`
    ADD CONSTRAINT `fk_documents_financial_entry`
        FOREIGN KEY (`financial_entry_id`) REFERENCES `financial_entries` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `financial_entries`
    ADD CONSTRAINT `fk_financial_entries_proof_document`
        FOREIGN KEY (`proof_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `financial_entries`
    ADD CONSTRAINT `fk_financial_entries_receipt_document`
        FOREIGN KEY (`receipt_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `financial_entries`
    ADD CONSTRAINT `fk_financial_entries_fiscal_document`
        FOREIGN KEY (`fiscal_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- Tabelas: sponsor_dossiers / sponsor_dossier_items (Etapa 16)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sponsor_dossiers` (
    `id`                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sponsor_id`                      BIGINT UNSIGNED NOT NULL,
    `company_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_id`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `opportunity_id`                  BIGINT UNSIGNED NULL DEFAULT NULL,
    `proposal_id`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `quota_id`                        BIGINT UNSIGNED NULL DEFAULT NULL,
    `main_contract_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `main_document_id`                BIGINT UNSIGNED NULL DEFAULT NULL,
    `final_document_id`               BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivery_receipt_document_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `dossier_number`                  VARCHAR(80)     NULL DEFAULT NULL,
    `title`                           VARCHAR(180)    NOT NULL,
    `dossier_type`                    VARCHAR(80)     NOT NULL DEFAULT 'prestacao_comercial',
    `status`                          VARCHAR(60)     NOT NULL DEFAULT 'rascunho',
    `delivery_status`                 VARCHAR(60)     NOT NULL DEFAULT 'nao_entregue',
    `period_start`                    DATE            NULL DEFAULT NULL,
    `period_end`                      DATE            NULL DEFAULT NULL,
    `include_contracts`               TINYINT(1)      NOT NULL DEFAULT 1,
    `include_counterparts`            TINYINT(1)      NOT NULL DEFAULT 1,
    `include_financials`              TINYINT(1)      NOT NULL DEFAULT 1,
    `include_documents`               TINYINT(1)      NOT NULL DEFAULT 1,
    `include_evidence`                TINYINT(1)      NOT NULL DEFAULT 1,
    `include_clipping`                TINYINT(1)      NOT NULL DEFAULT 1,
    `include_media`                   TINYINT(1)      NOT NULL DEFAULT 1,
    `contracts_count`                 INT             NOT NULL DEFAULT 0,
    `signed_contracts_count`          INT             NOT NULL DEFAULT 0,
    `counterparts_count`              INT             NOT NULL DEFAULT 0,
    `counterparts_delivered_count`    INT             NOT NULL DEFAULT 0,
    `counterparts_partial_count`      INT             NOT NULL DEFAULT 0,
    `counterparts_pending_count`      INT             NOT NULL DEFAULT 0,
    `counterparts_overdue_count`      INT             NOT NULL DEFAULT 0,
    `financial_entries_count`         INT             NOT NULL DEFAULT 0,
    `financial_planned_amount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_received_amount`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_remaining_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `financial_overdue_count`         INT             NOT NULL DEFAULT 0,
    `documents_count`                 INT             NOT NULL DEFAULT 0,
    `evidence_documents_count`        INT             NOT NULL DEFAULT 0,
    `executive_summary`               TEXT            NULL DEFAULT NULL,
    `commercial_summary`              TEXT            NULL DEFAULT NULL,
    `counterparts_summary`            TEXT            NULL DEFAULT NULL,
    `financial_summary`               TEXT            NULL DEFAULT NULL,
    `documents_summary`               TEXT            NULL DEFAULT NULL,
    `pending_notes`                   TEXT            NULL DEFAULT NULL,
    `approval_notes`                  TEXT            NULL DEFAULT NULL,
    `delivery_notes`                  TEXT            NULL DEFAULT NULL,
    `notes`                           TEXT            NULL DEFAULT NULL,
    `internal_notes`                  TEXT            NULL DEFAULT NULL,
    `generated_at`                    DATETIME        NULL DEFAULT NULL,
    `approved_at`                     DATETIME        NULL DEFAULT NULL,
    `delivered_at`                    DATETIME        NULL DEFAULT NULL,
    `responsible_user_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `generated_by`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by`                     BIGINT UNSIGNED NULL DEFAULT NULL,
    `delivered_by`                    BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                      BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME        NULL DEFAULT NULL,
    `archived_at`                     DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sponsor_dossiers_sponsor`              (`sponsor_id`),
    KEY `idx_sponsor_dossiers_company`              (`company_id`),
    KEY `idx_sponsor_dossiers_contact`              (`contact_id`),
    KEY `idx_sponsor_dossiers_opportunity`          (`opportunity_id`),
    KEY `idx_sponsor_dossiers_proposal`             (`proposal_id`),
    KEY `idx_sponsor_dossiers_quota`                (`quota_id`),
    KEY `idx_sponsor_dossiers_contract`             (`main_contract_id`),
    KEY `idx_sponsor_dossiers_main_document`        (`main_document_id`),
    KEY `idx_sponsor_dossiers_final_document`       (`final_document_id`),
    KEY `idx_sponsor_dossiers_delivery_document`    (`delivery_receipt_document_id`),
    KEY `idx_sponsor_dossiers_number`               (`dossier_number`),
    KEY `idx_sponsor_dossiers_type`                 (`dossier_type`),
    KEY `idx_sponsor_dossiers_status`               (`status`),
    KEY `idx_sponsor_dossiers_delivery_status`      (`delivery_status`),
    KEY `idx_sponsor_dossiers_period_start`         (`period_start`),
    KEY `idx_sponsor_dossiers_period_end`           (`period_end`),
    KEY `idx_sponsor_dossiers_responsible`          (`responsible_user_id`),
    KEY `idx_sponsor_dossiers_archived_at`          (`archived_at`),
    CONSTRAINT `fk_sponsor_dossiers_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_opportunity`
        FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_proposal`
        FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_quota`
        FOREIGN KEY (`quota_id`) REFERENCES `quotas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_main_contract`
        FOREIGN KEY (`main_contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_generated_by`
        FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_delivered_by`
        FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsor_dossiers_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sponsor_dossier_items` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dossier_id`          BIGINT UNSIGNED NOT NULL,
    `sponsor_id`          BIGINT UNSIGNED NOT NULL,
    `contract_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `counterpart_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `financial_entry_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `document_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `item_type`           VARCHAR(80)     NOT NULL DEFAULT 'manual',
    `source_module`       VARCHAR(80)     NULL DEFAULT NULL,
    `title`               VARCHAR(180)    NOT NULL,
    `description`         TEXT            NULL DEFAULT NULL,
    `status`              VARCHAR(60)     NOT NULL DEFAULT 'ativo',
    `evidence_status`     VARCHAR(60)     NOT NULL DEFAULT 'nao_aplicavel',
    `amount`              DECIMAL(12,2)   NULL DEFAULT NULL,
    `date_ref`            DATE            NULL DEFAULT NULL,
    `sort_order`          INT             NOT NULL DEFAULT 0,
    `created_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NULL DEFAULT NULL,
    `archived_at`         DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_dossier_items_dossier`           (`dossier_id`),
    KEY `idx_dossier_items_sponsor`           (`sponsor_id`),
    KEY `idx_dossier_items_contract`          (`contract_id`),
    KEY `idx_dossier_items_counterpart`       (`counterpart_id`),
    KEY `idx_dossier_items_financial`         (`financial_entry_id`),
    KEY `idx_dossier_items_document`          (`document_id`),
    KEY `idx_dossier_items_type`              (`item_type`),
    KEY `idx_dossier_items_source`            (`source_module`),
    KEY `idx_dossier_items_status`            (`status`),
    KEY `idx_dossier_items_evidence_status`   (`evidence_status`),
    KEY `idx_dossier_items_date_ref`          (`date_ref`),
    KEY `idx_dossier_items_archived_at`       (`archived_at`),
    CONSTRAINT `fk_dossier_items_dossier`
        FOREIGN KEY (`dossier_id`) REFERENCES `sponsor_dossiers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_sponsor`
        FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_contract`
        FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_counterpart`
        FOREIGN KEY (`counterpart_id`) REFERENCES `counterparts` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_financial`
        FOREIGN KEY (`financial_entry_id`) REFERENCES `financial_entries` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_document`
        FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dossier_items_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

ALTER TABLE `documents`
    ADD CONSTRAINT `fk_documents_sponsor_dossier`
        FOREIGN KEY (`sponsor_dossier_id`) REFERENCES `sponsor_dossiers` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `sponsor_dossiers`
    ADD CONSTRAINT `fk_sponsor_dossiers_main_document`
        FOREIGN KEY (`main_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `sponsor_dossiers`
    ADD CONSTRAINT `fk_sponsor_dossiers_final_document`
        FOREIGN KEY (`final_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `sponsor_dossiers`
    ADD CONSTRAINT `fk_sponsor_dossiers_delivery_document`
        FOREIGN KEY (`delivery_receipt_document_id`) REFERENCES `documents` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

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
    ('Ver documentos',        'documents.view',        'Visualizar documentos e arquivos'),
    ('Criar documentos',      'documents.create',      'Cadastrar documentos e arquivos'),
    ('Editar documentos',     'documents.edit',        'Editar documentos e alterar status'),
    ('Arquivar documentos',   'documents.archive',     'Arquivar e restaurar documentos'),
    ('Baixar documentos',     'documents.download',    'Download protegido de arquivos'),
    ('Versionar documentos',  'documents.version',     'Criar nova versão de documento'),
    ('Ver patrocinadores',    'sponsors.view',         'Visualizar fechamentos comerciais / patrocinadores'),
    ('Criar patrocinadores',  'sponsors.create',       'Registrar fechamentos comerciais'),
    ('Editar patrocinadores', 'sponsors.edit',         'Editar fechamentos comerciais'),
    ('Arquivar patrocinadores','sponsors.archive',     'Arquivar e restaurar fechamentos'),
    ('Confirmar fechamento',  'sponsors.confirm',      'Confirmar fechamento comercial'),
    ('Alterar status patroc.','sponsors.status',       'Alterar status e pagamento de fechamentos'),
    ('Ver contrapartidas',    'counterparts.view',     'Visualizar contrapartidas dos patrocinadores'),
    ('Criar contrapartidas',  'counterparts.create',   'Registrar contrapartidas de patrocinadores'),
    ('Editar contrapartidas', 'counterparts.edit',     'Editar contrapartidas'),
    ('Arquivar contrapartidas','counterparts.archive', 'Arquivar e restaurar contrapartidas'),
    ('Entregar contrapartidas','counterparts.deliver', 'Registrar entrega de contrapartidas'),
    ('Alterar status contrap.','counterparts.status',  'Alterar status de contrapartidas'),
    ('Ver contratos',              'contracts.view',        'Visualizar contratos e instrumentos de formalização'),
    ('Criar contratos',            'contracts.create',      'Registrar contratos e instrumentos'),
    ('Editar contratos',           'contracts.edit',        'Editar contratos e instrumentos'),
    ('Arquivar contratos',         'contracts.archive',     'Arquivar e restaurar contratos'),
    ('Alterar status contrato',    'contracts.status',      'Alterar status de contratos'),
    ('Marcar contrato assinado',   'contracts.mark_signed', 'Registrar assinatura manual de contratos'),
    ('Aprovar contratos',          'contracts.approve',     'Aprovar contratos internamente'),
    ('Ver financeiro',              'financials.view',      'Visualizar registros financeiros e parcelas'),
    ('Criar financeiro',            'financials.create',    'Registrar lançamentos financeiros'),
    ('Editar financeiro',           'financials.edit',      'Editar lançamentos financeiros'),
    ('Arquivar financeiro',         'financials.archive',   'Arquivar e restaurar lançamentos financeiros'),
    ('Alterar status financeiro',   'financials.status',    'Alterar status de lançamentos financeiros'),
    ('Confirmar recebimento',       'financials.confirm',   'Confirmar recebimento de pagamentos'),
    ('Conciliar financeiro',        'financials.reconcile', 'Conciliar manualmente lançamentos financeiros'),
    ('Ver dossiês',                 'dossiers.view',        'Visualizar dossiês de prestação comercial'),
    ('Criar dossiês',               'dossiers.create',      'Criar dossiês de patrocinador'),
    ('Editar dossiês',              'dossiers.edit',        'Editar dossiês e itens manuais'),
    ('Arquivar dossiês',            'dossiers.archive',     'Arquivar e restaurar dossiês'),
    ('Alterar status dossiê',       'dossiers.status',      'Alterar status do dossiê'),
    ('Gerar consolidação',          'dossiers.generate',    'Gerar/atualizar consolidação do dossiê'),
    ('Aprovar dossiê',              'dossiers.approve',     'Aprovar dossiê internamente'),
    ('Entregar dossiê',             'dossiers.deliver',     'Marcar dossiê como entregue ao patrocinador'),
    ('Ver relatórios',              'reports.view',         'Visualizar relatórios e indicadores gerenciais'),
    ('Gerar relatórios',            'reports.generate',     'Atualizar consolidação de relatórios internos'),
    ('Criar snapshots',             'reports.snapshots',    'Salvar snapshots manuais de relatórios'),
    ('Arquivar snapshots',          'reports.archive',      'Arquivar e restaurar snapshots de relatórios'),
    ('Imprimir relatórios',         'reports.print',        'Acessar versão de impressão de relatórios')
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
    'documents.view', 'documents.create', 'documents.edit',
    'documents.archive', 'documents.download', 'documents.version',
    'sponsors.view', 'sponsors.create', 'sponsors.edit',
    'sponsors.archive', 'sponsors.confirm', 'sponsors.status',
    'counterparts.view', 'counterparts.create', 'counterparts.edit',
    'counterparts.archive', 'counterparts.deliver', 'counterparts.status',
    'contracts.view', 'contracts.create', 'contracts.edit',
    'contracts.archive', 'contracts.status', 'contracts.mark_signed',
    'financials.view', 'financials.create', 'financials.edit',
    'financials.status', 'financials.confirm',
    'dossiers.view', 'dossiers.create', 'dossiers.edit',
    'dossiers.archive', 'dossiers.status', 'dossiers.generate', 'dossiers.deliver',
    'reports.view', 'reports.generate', 'reports.snapshots', 'reports.print'
)
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Produção / Coordenação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'counterparts.create',
    'counterparts.edit', 'counterparts.deliver', 'counterparts.status',
    'contracts.view', 'reports.view', 'reports.generate', 'reports.print',
    'financials.view',
    'dossiers.view', 'dossiers.edit', 'dossiers.generate',
    'proposals.view',
    'documents.view', 'documents.create', 'documents.edit',
    'documents.download', 'documents.version'
)
WHERE r.`slug` = 'producao-coordenacao'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Comunicação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'counterparts.create',
    'counterparts.edit', 'counterparts.deliver', 'counterparts.status',
    'contracts.view', 'reports.view', 'reports.generate', 'reports.print',
    'financials.view',
    'dossiers.view', 'dossiers.edit', 'dossiers.generate',
    'proposals.view',
    'documents.view', 'documents.create', 'documents.edit',
    'documents.download', 'documents.version'
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
    'documents.view', 'documents.download', 'sponsors.view', 'counterparts.view',
    'contracts.view', 'reports.view', 'reports.print',
    'financials.view',
    'dossiers.view'
)
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Garantia idempotente (Etapa 13): Leitura / Consulta com counterparts.view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`slug` = 'counterparts.view'
WHERE r.`slug` = 'leitura-consulta';

-- Garantia idempotente (Etapa 14): Leitura / Consulta com contracts.view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
INNER JOIN `permissions` p ON p.`slug` = 'contracts.view'
WHERE r.`slug` = 'leitura-consulta';

-- Etapa 15 — Financeiro Detalhado / Parcelas
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver financeiro',              'financials.view',      'Visualizar registros financeiros e parcelas'),
    ('Criar financeiro',            'financials.create',    'Registrar lançamentos financeiros'),
    ('Editar financeiro',           'financials.edit',      'Editar lançamentos financeiros'),
    ('Arquivar financeiro',         'financials.archive',   'Arquivar e restaurar lançamentos financeiros'),
    ('Alterar status financeiro',   'financials.status',    'Alterar status de lançamentos financeiros'),
    ('Confirmar recebimento',       'financials.confirm',   'Confirmar recebimento de pagamentos'),
    ('Conciliar financeiro',        'financials.reconcile', 'Conciliar manualmente lançamentos financeiros')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'financials.view', 'financials.create', 'financials.edit',
    'financials.archive', 'financials.status', 'financials.confirm', 'financials.reconcile'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'financials.view', 'financials.create', 'financials.edit',
    'financials.status', 'financials.confirm'
)
WHERE r.`slug` = 'captacao-comercial';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` IN ('financials.archive', 'financials.reconcile');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'financials.view'
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao', 'leitura-consulta');

-- Etapa 16 — Dossiê do Patrocinador / Prestação Comercial
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver dossiês',              'dossiers.view',     'Visualizar dossiês de prestação comercial'),
    ('Criar dossiês',            'dossiers.create',   'Criar dossiês de patrocinador'),
    ('Editar dossiês',           'dossiers.edit',     'Editar dossiês e itens manuais'),
    ('Arquivar dossiês',         'dossiers.archive',  'Arquivar e restaurar dossiês'),
    ('Alterar status dossiê',    'dossiers.status',   'Alterar status do dossiê'),
    ('Gerar consolidação',       'dossiers.generate', 'Gerar/atualizar consolidação do dossiê'),
    ('Aprovar dossiê',           'dossiers.approve',  'Aprovar dossiê internamente'),
    ('Entregar dossiê',          'dossiers.deliver',  'Marcar dossiê como entregue ao patrocinador')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.archive',
    'dossiers.status', 'dossiers.generate', 'dossiers.approve', 'dossiers.deliver'
)
WHERE r.`slug` = 'administrador-geral';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dossiers.view', 'dossiers.create', 'dossiers.edit', 'dossiers.archive',
    'dossiers.status', 'dossiers.generate', 'dossiers.deliver'
)
WHERE r.`slug` = 'captacao-comercial';

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'captacao-comercial'
  AND p.`slug` = 'dossiers.approve';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN ('dossiers.view', 'dossiers.edit', 'dossiers.generate')
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'dossiers.view'
WHERE r.`slug` = 'leitura-consulta';

-- Etapa 17 — Relatórios / Indicadores Gerenciais
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Ver relatórios',              'reports.view',         'Visualizar relatórios e indicadores gerenciais'),
    ('Gerar relatórios',            'reports.generate',     'Atualizar consolidação de relatórios internos'),
    ('Criar snapshots',             'reports.snapshots',    'Salvar snapshots manuais de relatórios'),
    ('Arquivar snapshots',          'reports.archive',      'Arquivar e restaurar snapshots de relatórios'),
    ('Imprimir relatórios',         'reports.print',        'Acessar versão de impressão de relatórios')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

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
WHERE r.`slug` IN ('producao-coordenacao', 'comunicacao')
  AND p.`slug` IN ('reports.snapshots', 'reports.archive');

DELETE rp FROM `role_permissions` rp
INNER JOIN `roles` r ON r.`id` = rp.`role_id`
INNER JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE r.`slug` = 'leitura-consulta'
  AND p.`slug` IN ('reports.snapshots', 'reports.archive', 'reports.generate');

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

-- ---------------------------------------------------------------------
-- ETAPA 21A - E-mail transacional
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mail_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(40) NOT NULL DEFAULT 'gmail',
    `smtp_host` VARCHAR(190) NULL DEFAULT NULL,
    `smtp_port` INT NOT NULL DEFAULT 587,
    `smtp_encryption` VARCHAR(20) NOT NULL DEFAULT 'tls',
    `smtp_username` VARCHAR(190) NULL DEFAULT NULL,
    `smtp_password_encrypted` TEXT NULL DEFAULT NULL,
    `from_name` VARCHAR(190) NULL DEFAULT NULL,
    `from_email` VARCHAR(190) NULL DEFAULT NULL,
    `reply_to_name` VARCHAR(190) NULL DEFAULT NULL,
    `reply_to_email` VARCHAR(190) NULL DEFAULT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `dry_run` TINYINT(1) NOT NULL DEFAULT 1,
    `hourly_limit` INT NOT NULL DEFAULT 20,
    `daily_limit` INT NOT NULL DEFAULT 100,
    `last_tested_at` DATETIME NULL DEFAULT NULL,
    `last_test_status` VARCHAR(40) NULL DEFAULT NULL,
    `last_test_message` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(120) NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body_text` MEDIUMTEXT NULL DEFAULT NULL,
    `body_html` MEDIUMTEXT NULL DEFAULT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_email_templates_event` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_outbox` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(120) NOT NULL,
    `entity_type` VARCHAR(80) NULL DEFAULT NULL,
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `recipient_type` VARCHAR(40) NULL DEFAULT NULL,
    `recipient_email` VARCHAR(190) NOT NULL,
    `recipient_name` VARCHAR(190) NULL DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body_text` MEDIUMTEXT NULL DEFAULT NULL,
    `body_html` MEDIUMTEXT NULL DEFAULT NULL,
    `payload_json` JSON NULL DEFAULT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL DEFAULT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_email_outbox_event` (`event_key`),
    KEY `idx_email_outbox_entity` (`entity_type`, `entity_id`, `event_key`),
    KEY `idx_email_outbox_status` (`status`),
    KEY `idx_email_outbox_recipient` (`recipient_email`),
    KEY `idx_email_outbox_event_entity_recipient` (`event_key`, `entity_type`, `entity_id`, `recipient_type`, `recipient_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(120) NOT NULL,
    `entity_type` VARCHAR(80) NULL DEFAULT NULL,
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `recipient_type` VARCHAR(40) NULL DEFAULT NULL,
    `recipient_email` VARCHAR(190) NOT NULL,
    `recipient_name` VARCHAR(190) NULL DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` VARCHAR(40) NOT NULL,
    `error_message` TEXT NULL DEFAULT NULL,
    `payload_json` JSON NULL DEFAULT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_logs_event` (`event_key`),
    KEY `idx_email_logs_entity` (`entity_type`, `entity_id`, `event_key`),
    KEY `idx_email_logs_status` (`status`),
    KEY `idx_email_logs_recipient` (`recipient_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_event_rules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(120) NOT NULL,
    `template_event_key` VARCHAR(120) NOT NULL,
    `recipient_type` VARCHAR(40) NOT NULL DEFAULT 'captador',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_email_event_rules` (`event_key`, `recipient_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mail_settings`
    (`provider`, `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`,
     `from_name`, `from_email`, `reply_to_name`, `reply_to_email`, `enabled`, `dry_run`, `hourly_limit`, `daily_limit`, `created_at`, `updated_at`)
SELECT 'gmail', 'smtp.gmail.com', 587, 'tls', 'dancacarajas@gmail.com',
       'Danca Carajas Captacao', 'dancacarajas@gmail.com', 'Equipe Danca Carajas', 'dancacarajas@gmail.com',
       0, 1, 20, 100, NOW(), NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `mail_settings`);

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('E-mail: visualizar configuracao', 'email_settings.view', 'Visualizar configuracao SMTP transacional'),
    ('E-mail: editar configuracao', 'email_settings.edit', 'Editar configuracao SMTP transacional'),
    ('E-mail: testar envio', 'email_settings.test', 'Enviar teste controlado de e-mail'),
    ('Templates de e-mail: visualizar', 'email_templates.view', 'Visualizar templates transacionais'),
    ('Templates de e-mail: editar', 'email_templates.edit', 'Editar templates transacionais'),
    ('Logs de e-mail: visualizar', 'email_logs.view', 'Visualizar logs transacionais de e-mail'),
    ('Logs de e-mail: reenviar', 'email_logs.resend', 'Reenviar e-mails transacionais registrados')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
  FROM `roles` r
  JOIN `permissions` p ON p.`slug` IN (
       'email_settings.view','email_settings.edit','email_settings.test',
       'email_templates.view','email_templates.edit','email_logs.view','email_logs.resend'
  )
 WHERE r.`slug` = 'administrador-geral';
