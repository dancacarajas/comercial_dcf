-- Etapa 18B — PDF do contrato assinado
SET NAMES utf8mb4;

ALTER TABLE `signature_requests`
    ADD COLUMN `signed_pdf_path` VARCHAR(255) NULL DEFAULT NULL AFTER `content_hash`,
    ADD COLUMN `signed_pdf_original_name` VARCHAR(180) NULL DEFAULT NULL AFTER `signed_pdf_path`;
