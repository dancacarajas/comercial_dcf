<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 20B-1 (aprovacao/bloqueio/reabertura).
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

$hasCol = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 20B-1 - Governanca de Comissao ==\n\n";

$columns = [
    'approval_notes' => "ADD COLUMN `approval_notes` TEXT NULL DEFAULT NULL AFTER `approved_at`",
    'block_notes' => "ADD COLUMN `block_notes` TEXT NULL DEFAULT NULL AFTER `block_reason`",
    'payment_total_amount' => "ADD COLUMN `payment_total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`",
    'payment_balance_amount' => "ADD COLUMN `payment_balance_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `payment_total_amount`",
    'payment_started_at' => "ADD COLUMN `payment_started_at` DATETIME NULL DEFAULT NULL AFTER `payment_balance_amount`",
    'paid_at' => "ADD COLUMN `paid_at` DATETIME NULL DEFAULT NULL AFTER `payment_started_at`",
    'reopened_by' => "ADD COLUMN `reopened_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`",
    'reopened_at' => "ADD COLUMN `reopened_at` DATETIME NULL DEFAULT NULL AFTER `reopened_by`",
    'reopen_reason' => "ADD COLUMN `reopen_reason` TEXT NULL DEFAULT NULL AFTER `reopened_at`",
    'collector_deal_share_id' => "ADD COLUMN `collector_deal_share_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `collector_deal_id`, ADD KEY `idx_collector_commissions_share` (`collector_deal_share_id`)",
];

foreach ($columns as $column => $ddl) {
    if (!$hasCol($pdo, 'collector_commissions', $column)) {
        $pdo->exec("ALTER TABLE `collector_commissions` {$ddl}");
        echo "coluna {$column} adicionada\n";
    } else {
        echo "coluna {$column} ja existe\n";
    }
}

$pdo->exec(
    "UPDATE `collector_commissions`
        SET `payment_balance_amount` = GREATEST(`capped_commission_amount` - `payment_total_amount`, 0)
      WHERE `payment_balance_amount` = 0
        AND `payment_total_amount` = 0"
);
echo "saldos iniciais sincronizados\n";

$permissions = [
    ['Comissoes: aprovar', 'commissions.approve', 'Aprovar comissoes calculadas'],
    ['Comissoes: bloquear', 'commissions.block', 'Bloquear comissoes calculadas'],
    ['Comissoes: reabrir', 'commissions.reopen', 'Reabrir comissoes bloqueadas sem pagamento'],
];
$st = $pdo->prepare(
    'INSERT INTO `permissions` (`name`, `slug`, `description`)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`)'
);
foreach ($permissions as $permission) {
    $st->execute($permission);
}
echo "permissoes de governanca garantidas\n";

$pdo->exec(
    "INSERT INTO `role_permissions` (`role_id`, `permission_id`)
     SELECT r.`id`, p.`id`
       FROM `roles` r
       JOIN `permissions` p ON p.`slug` IN ('commissions.approve','commissions.block','commissions.reopen')
      WHERE r.`slug` = 'administrador-geral'
     ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`"
);
echo "permissoes concedidas ao administrador-geral\n";

echo "\nMigration ETAPA 20B-1 concluida.\n";
