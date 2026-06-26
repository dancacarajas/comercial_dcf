-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 4
-- Módulo: Empresas / Prospects
--
-- Banco: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Idempotente: usa CREATE TABLE IF NOT EXISTS.
-- Sem exclusão física — empresas usam arquivamento lógico (archived_at).
-- =====================================================================

SET NAMES utf8mb4;

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

-- As permissões companies.view / companies.create / companies.edit já
-- foram criadas na Etapa 3 (migration 2026_etapa3_roles_permissions.sql).
-- O arquivamento/restauração reutiliza companies.edit (sem nova permissão).
