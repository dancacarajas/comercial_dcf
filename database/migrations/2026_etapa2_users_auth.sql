-- =====================================================================
-- Migração — Etapa 2 (Login/Autenticação)
--
-- Use este arquivo APENAS se você JÁ tinha importado o schema.sql da
-- Etapa 1 e precisa apenas adicionar as novas colunas de autenticação
-- à tabela `users`. Em uma instalação nova, basta importar o schema.sql
-- completo (que já contém estas colunas).
--
-- MySQL 8: execute os ALTER abaixo (remova os que já existirem).
-- MariaDB 10.3+: aceita "IF NOT EXISTS" nas colunas (já incluído).
-- =====================================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(100) NULL DEFAULT NULL AFTER `must_change_password`,
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0 AFTER `remember_token`,
    ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL AFTER `failed_login_attempts`;

-- Marca o admin inicial para troca obrigatória de senha.
UPDATE `users`
SET `must_change_password` = 1
WHERE `email` = 'admin@dancacarajas.com';
