<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20B-1: Governanca de comissoes.
 * Escopo: aprovacao, bloqueio, reabertura e trava de recalculo.
 * Fora do escopo: pagamento efetivo e rateio compartilhado.
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
    'app/Models/CollectorCommission.php',
    'app/Services/CollectorCommissionCalculator.php',
    'app/Controllers/CollectorCommissionController.php',
    'app/Views/collector_commissions/show.php',
    'scripts/run_migration_etapa20b1_commission_governance.php',
    'scripts/validate_etapa20b_commission_workflow.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path)) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$columns = [
    'approval_notes',
    'block_notes',
    'payment_total_amount',
    'payment_balance_amount',
    'payment_started_at',
    'paid_at',
    'reopened_by',
    'reopened_at',
    'reopen_reason',
    'collector_deal_share_id',
];
foreach ($columns as $column) {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $st->execute(['t' => 'collector_commissions', 'c' => $column]);
    is_ok((int) $st->fetchColumn() === 1, "Coluna existe: collector_commissions.{$column}", "Coluna ausente: {$column}");
}

foreach (['commissions.approve', 'commissions.block', 'commissions.reopen'] as $slug) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=:slug');
    $st->execute(['slug' => $slug]);
    is_ok((int) $st->fetchColumn() === 1, "Permissao existe: {$slug}", "Permissao ausente: {$slug}");
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach (['/commissions/{id}/approve', '/commissions/{id}/block', '/commissions/{id}/reopen'] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}

$controller = (string) file_get_contents($root . '/app/Controllers/CollectorCommissionController.php');
foreach (['collector_commission_approved', 'collector_commission_blocked', 'collector_commission_reopened'] as $needle) {
    is_ok(str_contains($controller, $needle), "Log de auditoria presente: {$needle}", "Log ausente: {$needle}");
}

$T = 'TESTE E20B1';
$cleanup = static function (PDO $pdo) use ($T): void {
    $pdo->exec("DELETE FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%')");
    $pdo->exec("DELETE FROM commission_pools WHERE incentive_project_id IN (SELECT id FROM incentive_projects WHERE project_name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM collector_deals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM financial_entries WHERE title LIKE '{$T}%'");
    $pdo->exec("DELETE FROM sponsors WHERE sponsor_display_name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM companies WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code LIKE 'E20B1-%'");
    $pdo->exec("DELETE FROM incentive_projects WHERE project_name LIKE '{$T}%'");
};
$makeCase = static function (PDO $pdo, string $suffix) use ($T): array {
    $pdo->prepare("INSERT INTO incentive_projects
        (project_name, edition_year, project_status, approved_total_amount, authorized_capture_amount, capture_commission_budget, commission_factor, created_at)
        VALUES (?, 2033, 'em_captacao', 470448.00, 470448.00, 42768.00, 0.0909090909, NOW())")
        ->execute(["{$T} Projeto {$suffix}"]);
    $projectId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO collectors
        (collector_code, type, status, registration_status, name, email, commission_percentage, contract_start_date, contract_end_date, created_at)
        VALUES (?, 'pessoa_fisica', 'ativo', 'validado', ?, ?, 10.00, '2026-01-01', '2035-12-31', NOW())")
        ->execute(["E20B1-{$suffix}", "{$T} Captador {$suffix}", "e20b1-{$suffix}@example.com"]);
    $collectorId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO companies (name, priority, status, source, created_at) VALUES (?, 'A', 'prospect', 'teste', NOW())")
        ->execute(["{$T} Empresa {$suffix}"]);
    $companyId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO sponsors
        (incentive_project_id, company_id, sponsor_display_name, status, payment_status, committed_amount, confirmed_amount, created_at)
        VALUES (?, ?, ?, 'confirmado', 'confirmado', 1000.00, 1000.00, NOW())")
        ->execute([$projectId, $companyId, "{$T} Patrocinador {$suffix}"]);
    $sponsorId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO financial_entries
        (incentive_project_id, sponsor_id, company_id, title, entry_type, funding_mechanism, payment_method, status,
         planned_amount, received_amount, remaining_amount, received_at, confirmed_by, created_at)
        VALUES (?, ?, ?, ?, 'parcela_patrocinio', 'lei_rouanet', 'pix', 'recebido', 1000.00, 1000.00, 0.00, NOW(), 1, NOW())")
        ->execute([$projectId, $sponsorId, $companyId, "{$T} Financeiro {$suffix}"]);
    $financialId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO collector_deals
        (incentive_project_id, collector_id, company_id, sponsor_id, financial_entry_id, deal_status, attribution_type, source, created_at)
        VALUES (?, ?, ?, ?, ?, 'fechado', 'direta', 'teste', NOW())")
        ->execute([$projectId, $collectorId, $companyId, $sponsorId, $financialId]);
    $dealId = (int) $pdo->lastInsertId();

    $result = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    $row = $pdo->query("SELECT * FROM collector_commissions WHERE financial_entry_id={$financialId} AND collector_deal_id={$dealId}")->fetch();

    return [$result, $row, $financialId, $dealId];
};

$cleanup($pdo);

try {
    $model = new \App\Models\CollectorCommission();

    [$result, $row, $financialId] = $makeCase($pdo, 'APROVAR');
    is_ok($result['status'] === 'calculated' && $row !== false, 'Comissao base calculada', 'Comissao base nao calculada');
    $commissionId = (int) ($row['id'] ?? 0);
    $model->approve($commissionId, 1, 'Aprovado na validacao 20B-1');
    $approved = $model->findById($commissionId);
    is_ok((string) ($approved['approval_status'] ?? '') === 'aprovada', 'Aprovacao muda status para aprovada', 'Status de aprovacao incorreto');
    is_ok((string) ($approved['payment_status'] ?? '') === 'a_pagar', 'Aprovacao marca como a pagar', 'Pagamento nao ficou a pagar');
    is_ok((float) ($approved['payment_balance_amount'] ?? 0) > 0, 'Aprovacao define saldo a pagar', 'Saldo a pagar ausente');

    $locked = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($locked['status'] === 'locked', 'Comissao aprovada nao recalcula automaticamente', 'Comissao aprovada foi recalculada');

    [$result2, $row2] = $makeCase($pdo, 'BLOQUEAR');
    $blockId = (int) ($row2['id'] ?? 0);
    expect_throw(
        fn () => $model->blockManual($blockId, 1, ''),
        'Bloqueio exige motivo',
        'Bloqueio sem motivo foi aceito'
    );
    $model->blockManual($blockId, 1, 'Pendencia documental');
    $blocked = $model->findById($blockId);
    is_ok((string) ($blocked['approval_status'] ?? '') === 'bloqueada', 'Bloqueio muda status para bloqueada', 'Bloqueio nao alterou status');
    is_ok((string) ($blocked['block_reason'] ?? '') === 'Pendencia documental', 'Motivo de bloqueio gravado', 'Motivo de bloqueio ausente');

    expect_throw(
        fn () => $model->reopen($blockId, 1, ''),
        'Reabertura exige motivo',
        'Reabertura sem motivo foi aceita'
    );
    $model->reopen($blockId, 1, 'Documentacao regularizada');
    $reopened = $model->findById($blockId);
    is_ok((string) ($reopened['approval_status'] ?? '') === 'pendente_aprovacao', 'Reabertura volta para pendente', 'Reabertura nao voltou para pendente');
    is_ok((string) ($reopened['reopen_reason'] ?? '') === 'Documentacao regularizada', 'Motivo de reabertura gravado', 'Motivo de reabertura ausente');

    [$result3, $row3] = $makeCase($pdo, 'PAGO');
    $paidId = (int) ($row3['id'] ?? 0);
    $pdo->prepare("UPDATE collector_commissions SET payment_status='pago', payment_total_amount=10.00 WHERE id=?")->execute([$paidId]);
    expect_throw(
        fn () => $model->blockManual($paidId, 1, 'Nao deve bloquear'),
        'Comissao paga nao pode ser bloqueada',
        'Comissao paga foi bloqueada'
    );
    $paidLocked = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry((int) ($row3['financial_entry_id'] ?? 0), 1);
    is_ok($paidLocked['status'] === 'locked', 'Comissao paga nao recalcula automaticamente', 'Comissao paga foi recalculada');
} finally {
    $cleanup($pdo);
}

echo "\n=== RESUMO ETAPA 20B-1 ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validacao ETAPA 20B-1 COMPLETA - {$passes} PASS / 0 FAIL\n";
exit(0);
