<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 20B-2 (pagamento de comissao).
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

echo "== ETAPA 20B-2 - Pagamento de Comissao ==\n\n";

$sql = (string) file_get_contents($root . '/database/migrations/2026_etapa20b2_commission_payments.sql');
$parts = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($parts as $part) {
    if ($part === '') {
        continue;
    }
    $pdo->exec($part);
}

$pdo->exec(
    "UPDATE `collector_commissions`
        SET `payment_balance_amount` = GREATEST(`capped_commission_amount` - `payment_total_amount`, 0)
      WHERE `approval_status` = 'aprovada'
        AND `payment_balance_amount` = 0
        AND `payment_total_amount` = 0
        AND `payment_status` IN ('a_pagar','parcialmente_pago','pago')"
);

echo "tabela collector_commission_payments garantida\n";
echo "permissoes de pagamento garantidas\n";
echo "saldos de comissoes aprovadas sincronizados\n";
echo "\nMigration ETAPA 20B-2 concluida.\n";
