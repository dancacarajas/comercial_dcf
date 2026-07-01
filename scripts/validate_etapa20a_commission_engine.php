<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20A: Motor de Comissao dos Captadores.
 * Meta: 0 FAIL. Cria dados temporarios TESTE E20A e limpa ao final.
 */

$root = dirname(__DIR__);
$passes = 0;
$failures = [];

function ok(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function is_ok(bool $c, string $p, string $f): void { $c ? ok($p) : fail($f); }
function lint_path(string $path): int
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    if (function_exists('exec')) { $out = []; exec($cmd, $out, $rc); return (int) $rc; }
    return 0;
}

require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
require_once $root . '/app/Helpers/security.php';
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$files = [
    'app/Models/CommissionPool.php',
    'app/Models/CollectorCommission.php',
    'app/Services/CollectorCommissionCalculator.php',
    'app/Controllers/CollectorCommissionController.php',
    'app/Controllers/FinancialController.php',
    'app/Views/collector_commissions/index.php',
    'app/Views/collector_commissions/pools.php',
    'app/Views/collector_commissions/show.php',
    'app/Views/financials/show.php',
    'scripts/run_migration_etapa20a_commissions.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path)) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

foreach (['commission_pools', 'collector_commissions'] as $table) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
    $st->execute(['t' => $table]);
    is_ok((int) $st->fetchColumn() === 1, "Tabela existe: {$table}", "Tabela ausente: {$table}");
}

foreach (['commissions.view', 'commissions.calculate', 'commissions.approve', 'commissions.block'] as $slug) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=:slug');
    $st->execute(['slug' => $slug]);
    is_ok((int) $st->fetchColumn() === 1, "Permissao existe: {$slug}", "Permissao ausente: {$slug}");
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach (['/commissions', '/commissions/pools', '/financials/{id}/commissions/recalculate'] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}

$T = 'TESTE E20A';
$cleanup = static function (PDO $pdo) use ($T): void {
    $pdo->exec("DELETE FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%')");
    $pdo->exec("DELETE FROM commission_pools WHERE incentive_project_id IN (SELECT id FROM incentive_projects WHERE project_name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM collector_deals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM financial_entries WHERE title LIKE '{$T}%'");
    $pdo->exec("DELETE FROM sponsors WHERE sponsor_display_name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM companies WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code LIKE 'E20A-%'");
    $pdo->exec("DELETE FROM incentive_projects WHERE project_name LIKE '{$T}%'");
};
$cleanup($pdo);

try {
    $pdo->prepare("INSERT INTO incentive_projects
        (project_name, edition_year, project_status, approved_total_amount, authorized_capture_amount, capture_commission_budget, commission_factor, created_at)
        VALUES (?, 2032, 'em_captacao', 470448.00, 470448.00, 42768.00, 0.0909090909, NOW())")
        ->execute(["{$T} Projeto"]);
    $projectId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO collectors
        (collector_code, type, status, registration_status, name, email, commission_percentage, contract_start_date, contract_end_date, created_at)
        VALUES ('E20A-OK', 'pessoa_fisica', 'ativo', 'validado', ?, 'e20a@example.com', 10.00, '2026-01-01', '2035-12-31', NOW())")
        ->execute(["{$T} Captador"]);
    $collectorId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO companies (name, priority, status, source, created_at) VALUES (?, 'A', 'prospect', 'teste', NOW())")
        ->execute(["{$T} Empresa"]);
    $companyId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO sponsors
        (incentive_project_id, company_id, sponsor_display_name, status, payment_status, committed_amount, confirmed_amount, created_at)
        VALUES (?, ?, ?, 'confirmado', 'confirmado', 1000.00, 1000.00, NOW())")
        ->execute([$projectId, $companyId, "{$T} Patrocinador"]);
    $sponsorId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO financial_entries
        (incentive_project_id, sponsor_id, company_id, title, entry_type, funding_mechanism, payment_method, status,
         planned_amount, received_amount, remaining_amount, received_at, confirmed_by, created_at)
        VALUES (?, ?, ?, ?, 'parcela_patrocinio', 'lei_rouanet', 'pix', 'recebido', 1000.00, 1000.00, 0.00, NOW(), 1, NOW())")
        ->execute([$projectId, $sponsorId, $companyId, "{$T} Financeiro"]);
    $financialId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO collector_deals
        (incentive_project_id, collector_id, company_id, sponsor_id, financial_entry_id, deal_status, attribution_type, source, created_at)
        VALUES (?, ?, ?, ?, ?, 'fechado', 'direta', 'teste', NOW())")
        ->execute([$projectId, $collectorId, $companyId, $sponsorId, $financialId]);
    $dealId = (int) $pdo->lastInsertId();

    $result = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($result['status'] === 'calculated', 'Motor calcula financial_entry recebido', 'Motor nao calculou: ' . json_encode($result));
    $row = $pdo->query("SELECT * FROM collector_commissions WHERE financial_entry_id={$financialId} AND collector_deal_id={$dealId}")->fetch();
    is_ok($row !== false, 'Comissao gravada', 'Comissao nao gravada');
    is_ok($row !== false && abs((float) $row['capped_commission_amount'] - 90.91) < 0.01, 'Comissao = 1000 x 9,090909%', 'Valor de comissao incorreto');
    is_ok($row !== false && str_contains((string) $row['calculation_snapshot_json'], 'engine_version'), 'Snapshot do calculo gravado', 'Snapshot ausente');

    (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    $count = (int) $pdo->query("SELECT COUNT(*) FROM collector_commissions WHERE financial_entry_id={$financialId} AND collector_deal_id={$dealId}")->fetchColumn();
    is_ok($count === 1, 'Motor idempotente nao duplica comissao', "Comissao duplicada: {$count}");

    $pool = $pdo->query("SELECT * FROM commission_pools WHERE incentive_project_id={$projectId}")->fetch();
    is_ok($pool !== false && (float) $pool['commission_available_balance'] < 42768.00, 'Pool consome saldo da rubrica', 'Pool nao consumiu saldo');

    $pdo->prepare("INSERT INTO financial_entries
        (incentive_project_id, sponsor_id, company_id, title, entry_type, funding_mechanism, payment_method, status,
         planned_amount, received_amount, remaining_amount, received_at, confirmed_by, created_at)
        VALUES (?, ?, ?, ?, 'parcela_patrocinio', 'lei_rouanet', 'pix', 'recebido', 1000.00, 1000.00, 0.00, NOW(), 1, NOW())")
        ->execute([$projectId, $sponsorId, $companyId, "{$T} Financeiro Compartilhado"]);
    $sharedFinancialId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO collector_deals
        (incentive_project_id, collector_id, company_id, sponsor_id, financial_entry_id, deal_status, attribution_type, source, created_at)
        VALUES (?, ?, ?, ?, ?, 'fechado', 'compartilhada', 'teste', NOW())")
        ->execute([$projectId, $collectorId, $companyId, $sponsorId, $sharedFinancialId]);
    $shared = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($sharedFinancialId, 1);
    is_ok($shared['status'] === 'blocked', 'Captacao compartilhada sem rateio bloqueia', 'Compartilhada calculou indevidamente');
} finally {
    $cleanup($pdo);
}

echo "\n=== RESUMO ETAPA 20A ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validacao ETAPA 20A COMPLETA - {$passes} PASS / 0 FAIL\n";
exit(0);
