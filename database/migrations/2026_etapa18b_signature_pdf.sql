-- Etapa 18B — PDF do contrato assinado (idempotente)
SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'signature_requests' AND COLUMN_NAME = 'signed_pdf_path') = 0,
    'ALTER TABLE `signature_requests` ADD COLUMN `signed_pdf_path` VARCHAR(255) NULL DEFAULT NULL AFTER `content_hash`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'signature_requests' AND COLUMN_NAME = 'signed_pdf_original_name') = 0,
    'ALTER TABLE `signature_requests` ADD COLUMN `signed_pdf_original_name` VARCHAR(180) NULL DEFAULT NULL AFTER `signed_pdf_path`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
