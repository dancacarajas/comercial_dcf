-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 5
-- Módulo: Contatos (pessoas vinculadas a empresas)
--
-- Banco: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Idempotente: usa CREATE TABLE IF NOT EXISTS.
-- Todo contato pertence a uma empresa (company_id, FK ON DELETE CASCADE).
-- Sem exclusão física — contatos usam arquivamento lógico (archived_at).
-- =====================================================================

SET NAMES utf8mb4;

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

-- As permissões contacts.view / contacts.create / contacts.edit já foram
-- criadas na Etapa 3. O arquivamento/restauração reutiliza contacts.edit
-- (nenhuma permissão nova foi criada nesta etapa).
