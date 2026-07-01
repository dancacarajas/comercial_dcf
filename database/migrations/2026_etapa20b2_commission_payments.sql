CREATE TABLE IF NOT EXISTS `collector_commission_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collector_commission_id` BIGINT UNSIGNED NOT NULL,
    `incentive_project_id` BIGINT UNSIGNED NOT NULL,
    `collector_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(60) NOT NULL,
    `proof_document_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'confirmado',
    `notes` TEXT NULL DEFAULT NULL,
    `cancel_reason` TEXT NULL DEFAULT NULL,
    `cancelled_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `cancelled_at` DATETIME NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_commission_payments_commission` (`collector_commission_id`),
    KEY `idx_commission_payments_project` (`incentive_project_id`),
    KEY `idx_commission_payments_collector` (`collector_id`),
    KEY `idx_commission_payments_document` (`proof_document_id`),
    KEY `idx_commission_payments_status` (`status`),
    KEY `idx_commission_payments_date` (`payment_date`),
    CONSTRAINT `fk_commission_payments_commission`
        FOREIGN KEY (`collector_commission_id`) REFERENCES `collector_commissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_commission_payments_project`
        FOREIGN KEY (`incentive_project_id`) REFERENCES `incentive_projects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_commission_payments_collector`
        FOREIGN KEY (`collector_id`) REFERENCES `collectors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_commission_payments_document`
        FOREIGN KEY (`proof_document_id`) REFERENCES `documents` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('Comissoes: pagar', 'commissions.pay', 'Registrar pagamento de comissao'),
    ('Comissoes: cancelar pagamento', 'commissions.cancel_payment', 'Cancelar ou estornar pagamento de comissao')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
  FROM `roles` r
  JOIN `permissions` p ON p.`slug` IN ('commissions.pay','commissions.cancel_payment')
 WHERE r.`slug` = 'administrador-geral';
