-- =====================================================================
-- Dança Carajás Captação — Migration Etapa 6
-- Módulo: Oportunidades / CRM de Captação
--
-- Banco: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
--
-- Idempotente: usa CREATE TABLE IF NOT EXISTS.
-- Núcleo do funil: empresa + contato + status + valor + probabilidade.
-- Sem exclusão física — arquivamento lógico (archived_at).
--
-- IMPORTANTE: nesta etapa NÃO existe tabela de cotas. O interesse de cota
-- é registrado em `quota_interest` (texto controlado provisório), para
-- futura migração quando o módulo Cotas (Etapa 7) for criado.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `opportunities` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `company_id`          BIGINT UNSIGNED NOT NULL,
    `contact_id`          BIGINT UNSIGNED NULL DEFAULT NULL,

    `title`               VARCHAR(180)    NOT NULL,

    `quota_interest`      VARCHAR(80)     NULL DEFAULT NULL,

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
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- As permissões opportunities.view / opportunities.create / opportunities.edit
-- já foram criadas na Etapa 3. Edição cobre mudança de status, arquivar e
-- restaurar (nenhuma permissão nova foi criada nesta etapa).
