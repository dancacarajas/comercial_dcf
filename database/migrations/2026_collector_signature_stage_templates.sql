-- Etapa 18C — Modelos contratuais configuráveis na Etapa 5 (assinatura) dos captadores
-- Idempotente: seguro para reexecução.

SET NAMES utf8mb4;

SET @db := DATABASE();

-- collector_signature_stage_enabled
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract_templates' AND COLUMN_NAME = 'collector_signature_stage_enabled'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `contract_templates` ADD COLUMN `collector_signature_stage_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_default`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- collector_signature_required
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract_templates' AND COLUMN_NAME = 'collector_signature_required'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `contract_templates` ADD COLUMN `collector_signature_required` TINYINT(1) NOT NULL DEFAULT 1 AFTER `collector_signature_stage_enabled`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- collector_signature_order
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract_templates' AND COLUMN_NAME = 'collector_signature_order'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `contract_templates` ADD COLUMN `collector_signature_order` INT NOT NULL DEFAULT 0 AFTER `collector_signature_required`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice para listagem Etapa 5
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'contract_templates' AND INDEX_NAME = 'idx_contract_templates_collector_stage'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `contract_templates` ADD KEY `idx_contract_templates_collector_stage` (`collector_signature_stage_enabled`, `collector_signature_order`, `status`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Habilita modelo padrão de captador existente na Etapa 5 (somente se ainda não configurado)
UPDATE `contract_templates`
   SET `collector_signature_stage_enabled` = 1,
       `collector_signature_required` = 1,
       `collector_signature_order` = CASE
           WHEN `template_type` = 'contrato_captador' THEN 10
           WHEN `template_type` = 'autorizacao_captador' THEN 20
           WHEN `template_type` = 'termo_confidencialidade' THEN 30
           ELSE `collector_signature_order`
       END
 WHERE `archived_at` IS NULL
   AND `status` = 'ativo'
   AND `template_type` IN ('contrato_captador', 'autorizacao_captador')
   AND `collector_signature_stage_enabled` = 0
   AND (
       `is_default` = 1
       OR `template_key` IN ('captador_externo_padrao', 'autorizacao_captador_padrao')
   );
