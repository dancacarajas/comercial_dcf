<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20B-3: Rateio de comissao compartilhada.
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
function expect_throw(callable $fn, string $pass, string $fail): void
{
    try {
        $fn();
        fail($fail);
    } catch (Throwable) {
        ok($pass);
    }
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
    'app/Models/CollectorDealShare.php',
    'app/Controllers/CollectorDealShareController.php',
    'app/Services/CollectorCommissionCalculator.php',
    'app/Models/CollectorCommission.php',
    'app/Views/collector_deal_shares/index.php',
    'routes/web.php',
    'database/migrations/2026_etapa20b3_commission_shares.sql',
    'scripts/run_migration_etapa20b3_commission_shares.php',
    'scripts/validate_etapa20b3_commission_shares.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
$st->execute(['t' => 'collector_deal_shares']);
is_ok((int) $st->fetchColumn() === 1, 'Tabela existe: collector_deal_shares', 'Tabela collector_deal_shares ausente');

foreach (['collector_deal_id', 'incentive_project_id', 'collector_id', 'share_percent', 'status', 'approved_by', 'approved_at', 'archived_at'] as $column) {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $st->execute(['t' => 'collector_deal_shares', 'c' => $column]);
    is_ok((int) $st->fetchColumn() === 1, "Coluna existe: {$column}", "Coluna ausente: {$column}");
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach (['/collector-deals/{id}/shares', '/collector-deal-shares/{id}/update', '/collector-deals/{id}/shares/approve'] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}

$controller = (string) file_get_contents($root . '/app/Controllers/CollectorDealShareController.php');
foreach (['collector_deal_share_created', 'collector_deal_share_updated', 'collector_deal_share_approved'] as $needle) {
    is_ok(str_contains($controller, $needle), "Log presente: {$needle}", "Log ausente: {$needle}");
}

$T = 'TESTE E20B3';
$cleanup = static function (PDO $pdo) use ($T): void {
    $pdo->exec("DELETE FROM collector_commission_payments WHERE collector_commission_id IN (SELECT id FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%'))");
    $pdo->exec("DELETE FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%')");
    $pdo->exec("DELETE FROM collector_deal_shares WHERE collector_deal_id IN (SELECT id FROM collector_deals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%'))");
    $pdo->exec("DELETE FROM commission_pools WHERE incentive_project_id IN (SELECT id FROM incentive_projects WHERE project_name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM collector_deals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM financial_entries WHERE title LIKE '{$T}%'");
    $pdo->exec("DELETE FROM sponsors WHERE sponsor_display_name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM companies WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code LIKE 'E20B3-%'");
    $pdo->exec("DELETE FROM incentive_projects WHERE project_name LIKE '{$T}%'");
};

$cleanup($pdo);

try {
    $pdo->prepare("INSERT INTO incentive_projects
        (project_name, edition_year, project_status, approved_total_amount, authorized_capture_amount, capture_commission_budget, commission_factor, created_at)
        VALUES (?, 2035, 'em_captacao', 470448.00, 470448.00, 42768.00, 0.0909090909, NOW())")
        ->execute(["{$T} Projeto"]);
    $projectId = (int) $pdo->lastInsertId();

    $collectorIds = [];
    foreach ([1, 2] as $n) {
        $pdo->prepare("INSERT INTO collectors
            (collector_code, type, status, registration_status, name, email, commission_percentage, contract_start_date, contract_end_date, created_at)
            VALUES (?, 'pessoa_fisica', 'ativo', 'validado', ?, ?, 10.00, '2026-01-01', '2035-12-31', NOW())")
            ->execute(["E20B3-{$n}", "{$T} Captador {$n}", "e20b3-{$n}@example.com"]);
        $collectorIds[] = (int) $pdo->lastInsertId();
    }

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
        VALUES (?, ?, ?, ?, ?, 'fechado', 'compartilhada', 'teste', NOW())")
        ->execute([$projectId, $collectorIds[0], $companyId, $sponsorId, $financialId]);
    $dealId = (int) $pdo->lastInsertId();
    $deal = (new \App\Models\CollectorDeal())->findById($dealId);

    $blocked = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($blocked['status'] === 'blocked', 'Compartilhada sem rateio aprovado bloqueia', 'Compartilhada calculou sem rateio');

    $shareModel = new \App\Models\CollectorDealShare();
    $s1 = $shareModel->createForDeal($deal, ['collector_id' => $collectorIds[0], 'share_percent' => '60', 'notes' => 'principal'], 1);
    $s2 = $shareModel->createForDeal($deal, ['collector_id' => $collectorIds[1], 'share_percent' => '30', 'notes' => 'apoio'], 1);
    expect_throw(
        fn () => $shareModel->approveDealShares($dealId, 1),
        'Aprovacao exige soma 100%',
        'Aprovacao aceitou soma diferente de 100%'
    );

    $shareModel->updateShare($s2, ['share_percent' => '40', 'notes' => 'apoio ajustado'], 1);
    $shareModel->approveDealShares($dealId, 1);
    $approved = $shareModel->approvedByDeal($dealId);
    is_ok(count($approved) === 2, 'Rateio aprovado com dois participantes', 'Rateio aprovado nao retornou dois participantes');

    $result = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($result['status'] === 'calculated', 'Motor calcula compartilhada com rateio aprovado', 'Motor nao calculou compartilhada');
    $rows = $pdo->query("SELECT * FROM collector_commissions WHERE financial_entry_id={$financialId} ORDER BY collector_id ASC")->fetchAll();
    is_ok(count($rows) === 2, 'Uma comissao gerada por share', 'Quantidade de comissoes compartilhadas incorreta');
    $sumCapped = array_sum(array_map(static fn (array $r): float => (float) $r['capped_commission_amount'], $rows));
    is_ok(abs($sumCapped - 90.91) < 0.01, 'Soma rateada respeita total limitado', 'Soma rateada incorreta');
    is_ok(str_contains((string) ($rows[0]['calculation_snapshot_json'] ?? ''), 'collector_deal_share_id'), 'Snapshot guarda collector_deal_share_id', 'Snapshot sem share id');
    is_ok(str_contains((string) ($rows[0]['calculation_snapshot_json'] ?? ''), 'shared_total_capped'), 'Snapshot guarda total compartilhado limitado', 'Snapshot sem total limitado');

    (new \App\Models\CollectorCommission())->approve((int) $rows[0]['id'], 1, 'Aprova para travar rateio');
    expect_throw(
        fn () => $shareModel->archiveShare($s1, 1),
        'Rateio bloqueia alteracao com comissao aprovada',
        'Rateio permitiu alteracao com comissao aprovada'
    );

    $locked = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($locked['status'] === 'locked', 'Compartilhada aprovada nao recalcula', 'Compartilhada aprovada recalculou');
} finally {
    $cleanup($pdo);
}

echo "\n=== RESUMO ETAPA 20B-3 ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validacao ETAPA 20B-3 COMPLETA - {$passes} PASS / 0 FAIL\n";
exit(0);
