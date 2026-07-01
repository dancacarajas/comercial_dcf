<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 20A (motor de comissao dos captadores).
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$tableExists = static function (PDO $pdo, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute(['t' => $table]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 20A - Motor de Comissao ==\n\n";

if (!$tableExists($pdo, 'commission_pools')) {
    $pdo->exec(
        "CREATE TABLE `commission_pools` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `incentive_project_id` BIGINT UNSIGNED NOT NULL,
            `approved_total_amount_snapshot` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `capture_commission_budget_snapshot` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_factor_snapshot` DECIMAL(12,10) NULL DEFAULT NULL,
            `gross_received_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_generated_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_approved_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_blocked_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_available_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(40) NOT NULL DEFAULT 'ativo',
            `calculated_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_commission_pools_project` (`incentive_project_id`),
            KEY `idx_commission_pools_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "tabela commission_pools criada\n";
} else {
    echo "tabela commission_pools ja existe\n";
}

if (!$tableExists($pdo, 'collector_commissions')) {
    $pdo->exec(
        "CREATE TABLE `collector_commissions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `commission_pool_id` BIGINT UNSIGNED NOT NULL,
            `incentive_project_id` BIGINT UNSIGNED NOT NULL,
            `collector_id` BIGINT UNSIGNED NOT NULL,
            `collector_deal_id` BIGINT UNSIGNED NOT NULL,
            `financial_entry_id` BIGINT UNSIGNED NOT NULL,
            `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `sponsor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `contract_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `opportunity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `proposal_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `quota_id` BIGINT UNSIGNED NULL DEFAULT NULL,
            `attribution_type` VARCHAR(40) NOT NULL DEFAULT 'direta',
            `source` VARCHAR(80) NULL DEFAULT NULL,
            `financial_received_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `commission_factor_snapshot` DECIMAL(12,10) NOT NULL DEFAULT 0.0000000000,
            `gross_commission_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `capped_commission_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `available_before` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `available_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `calculation_status` VARCHAR(40) NOT NULL DEFAULT 'calculada',
            `approval_status` VARCHAR(40) NOT NULL DEFAULT 'pendente_aprovacao',
            `payment_status` VARCHAR(40) NOT NULL DEFAULT 'nao_iniciado',
            `block_reason` VARCHAR(255) NULL DEFAULT NULL,
            `calculation_snapshot_json` JSON NULL DEFAULT NULL,
            `calculated_at` DATETIME NULL DEFAULT NULL,
            `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
            `approved_at` DATETIME NULL DEFAULT NULL,
            `blocked_by` BIGINT UNSIGNED NULL DEFAULT NULL,
            `blocked_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL,
            `archived_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_collector_commission_financial_deal` (`financial_entry_id`, `collector_deal_id`),
            KEY `idx_collector_commissions_pool` (`commission_pool_id`),
            KEY `idx_collector_commissions_project` (`incentive_project_id`),
            KEY `idx_collector_commissions_collector` (`collector_id`),
            KEY `idx_collector_commissions_deal` (`collector_deal_id`),
            KEY `idx_collector_commissions_financial` (`financial_entry_id`),
            KEY `idx_collector_commissions_calc_status` (`calculation_status`),
            KEY `idx_collector_commissions_approval` (`approval_status`),
            KEY `idx_collector_commissions_payment` (`payment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "tabela collector_commissions criada\n";
} else {
    echo "tabela collector_commissions ja existe\n";
}

$permissions = [
    ['Comissoes: visualizar', 'commissions.view', 'Visualizar motor de comissoes dos captadores'],
    ['Comissoes: calcular', 'commissions.calculate', 'Calcular/recalcular comissoes dos captadores'],
    ['Comissoes: aprovar', 'commissions.approve', 'Reservado para etapa de aprovacao'],
    ['Comissoes: bloquear', 'commissions.block', 'Reservado para bloqueio interno'],
];
$st = $pdo->prepare(
    'INSERT INTO `permissions` (`name`, `slug`, `description`)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`)'
);
foreach ($permissions as $perm) {
    $st->execute($perm);
}
echo "permissoes de comissao garantidas\n";

$pdo->exec(
    "INSERT INTO `role_permissions` (`role_id`, `permission_id`)
     SELECT r.`id`, p.`id`
       FROM `roles` r
       JOIN `permissions` p ON p.`slug` IN ('commissions.view','commissions.calculate','commissions.approve','commissions.block')
      WHERE r.`slug` = 'administrador-geral'
     ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`"
);
echo "permissoes concedidas ao administrador-geral\n";

$projects = $pdo->query('SELECT * FROM `incentive_projects` WHERE `archived_at` IS NULL')->fetchAll();
$poolModel = new \App\Models\CommissionPool();
foreach ($projects as $project) {
    $poolModel->refreshForProject($project);
}
echo 'pools sincronizados: ' . count($projects) . "\n";

echo "\nMigration ETAPA 20A concluida.\n";
