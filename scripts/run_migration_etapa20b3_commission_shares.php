<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 20B-3 (rateio compartilhado).
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

$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $st->execute(['t' => $table, 'i' => $index]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 20B-3 - Rateio de Comissao Compartilhada ==\n\n";

$sql = (string) file_get_contents($root . '/database/migrations/2026_etapa20b3_commission_shares.sql');
$parts = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($parts as $part) {
    if ($part !== '') {
        $pdo->exec($part);
    }
}
echo "tabela collector_deal_shares garantida\n";

if ($hasIndex($pdo, 'collector_commissions', 'uniq_collector_commission_financial_deal')) {
    $pdo->exec('ALTER TABLE `collector_commissions` DROP INDEX `uniq_collector_commission_financial_deal`');
    echo "indice unico antigo removido\n";
}
if (!$hasIndex($pdo, 'collector_commissions', 'uniq_collector_commission_financial_deal_collector')) {
    $pdo->exec(
        'ALTER TABLE `collector_commissions`
         ADD UNIQUE KEY `uniq_collector_commission_financial_deal_collector`
             (`financial_entry_id`, `collector_deal_id`, `collector_id`)'
    );
    echo "indice unico por captador adicionado\n";
} else {
    echo "indice unico por captador ja existe\n";
}

echo "\nMigration ETAPA 20B-3 concluida.\n";
