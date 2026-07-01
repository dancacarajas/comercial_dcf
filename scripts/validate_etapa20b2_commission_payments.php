<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20B-2: Pagamento de comissao.
 * Escopo: pagamento parcial/integral, comprovante, saldo, cancelamento/estorno.
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
    'app/Models/CollectorCommissionPayment.php',
    'app/Services/CollectorCommissionPaymentService.php',
    'app/Controllers/CollectorCommissionController.php',
    'app/Views/collector_commissions/show.php',
    'app/Views/collector_commissions/_payments_table.php',
    'database/migrations/2026_etapa20b2_commission_payments.sql',
    'scripts/run_migration_etapa20b2_commission_payments.php',
    'scripts/validate_etapa20b2_commission_payments.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
$st->execute(['t' => 'collector_commission_payments']);
is_ok((int) $st->fetchColumn() === 1, 'Tabela existe: collector_commission_payments', 'Tabela de pagamentos ausente');

foreach (['collector_commission_id', 'amount', 'payment_date', 'payment_method', 'proof_document_id', 'status', 'cancel_reason'] as $column) {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $st->execute(['t' => 'collector_commission_payments', 'c' => $column]);
    is_ok((int) $st->fetchColumn() === 1, "Coluna existe: {$column}", "Coluna ausente: {$column}");
}

foreach (['commissions.pay', 'commissions.cancel_payment'] as $slug) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=:slug');
    $st->execute(['slug' => $slug]);
    is_ok((int) $st->fetchColumn() === 1, "Permissao existe: {$slug}", "Permissao ausente: {$slug}");
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach (['/commissions/{id}/payments', '/commissions/payments/{id}/cancel'] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}

$controller = (string) file_get_contents($root . '/app/Controllers/CollectorCommissionController.php');
foreach (['collector_commission_payment_registered', 'collector_commission_payment_cancelled'] as $needle) {
    is_ok(str_contains($controller, $needle), "Log presente: {$needle}", "Log ausente: {$needle}");
}

$T = 'TESTE E20B2';
$cleanup = static function (PDO $pdo) use ($T): void {
    $pdo->exec("DELETE FROM collector_commission_payments WHERE collector_commission_id IN (SELECT id FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%'))");
    $pdo->exec("DELETE FROM collector_commissions WHERE financial_entry_id IN (SELECT id FROM financial_entries WHERE title LIKE '{$T}%')");
    $pdo->exec("DELETE FROM commission_pools WHERE incentive_project_id IN (SELECT id FROM incentive_projects WHERE project_name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM collector_deals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM financial_entries WHERE title LIKE '{$T}%'");
    $pdo->exec("DELETE FROM documents WHERE title LIKE '{$T}%'");
    $pdo->exec("DELETE FROM sponsors WHERE sponsor_display_name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM companies WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code LIKE 'E20B2-%'");
    $pdo->exec("DELETE FROM incentive_projects WHERE project_name LIKE '{$T}%'");
};
$makeDocument = static function (PDO $pdo, string $title, int $projectId): int {
    $pdo->prepare("INSERT INTO documents
        (incentive_project_id, title, category, status, access_level, file_path, original_name, stored_name, extension, mime_type, size_bytes, checksum_sha256, created_by, created_at)
        VALUES (?, ?, 'comprovante_envio', 'ativo', 'restrito', '/tmp/e20b2.pdf', 'e20b2.pdf', 'e20b2.pdf', 'pdf', 'application/pdf', 1, SHA2(?, 256), 1, NOW())")
        ->execute([$projectId, $title, $title]);
    return (int) $pdo->lastInsertId();
};
$makeCase = static function (PDO $pdo) use ($T, $makeDocument): array {
    $pdo->prepare("INSERT INTO incentive_projects
        (project_name, edition_year, project_status, approved_total_amount, authorized_capture_amount, capture_commission_budget, commission_factor, created_at)
        VALUES (?, 2034, 'em_captacao', 470448.00, 470448.00, 42768.00, 0.0909090909, NOW())")
        ->execute(["{$T} Projeto"]);
    $projectId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO collectors
        (collector_code, type, status, registration_status, name, email, commission_percentage, contract_start_date, contract_end_date, created_at)
        VALUES ('E20B2-OK', 'pessoa_fisica', 'ativo', 'validado', ?, 'e20b2@example.com', 10.00, '2026-01-01', '2035-12-31', NOW())")
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

    $result = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    $commission = $pdo->query("SELECT * FROM collector_commissions WHERE financial_entry_id={$financialId}")->fetch();
    $commissionId = (int) ($commission['id'] ?? 0);
    (new \App\Models\CollectorCommission())->approve($commissionId, 1, 'Aprovado para pagamento E20B2');
    $proofA = $makeDocument($pdo, "{$T} Comprovante A", $projectId);
    $proofB = $makeDocument($pdo, "{$T} Comprovante B", $projectId);

    return [$result, $commissionId, $financialId, $proofA, $proofB];
};

$cleanup($pdo);

try {
    [$result, $commissionId, $financialId, $proofA, $proofB] = $makeCase($pdo);
    is_ok($result['status'] === 'calculated' && $commissionId > 0, 'Comissao aprovada para pagamento criada', 'Falha ao criar comissao base');

    $service = new \App\Services\CollectorCommissionPaymentService();
    expect_throw(
        fn () => $service->register($commissionId, ['amount' => '10,00', 'payment_date' => date('Y-m-d'), 'payment_method' => 'pix'], 1),
        'Pagamento exige comprovante',
        'Pagamento sem comprovante foi aceito'
    );
    expect_throw(
        fn () => $service->register($commissionId, ['amount' => '9999,00', 'payment_date' => date('Y-m-d'), 'payment_method' => 'pix', 'proof_document_id' => $proofA], 1),
        'Pagamento acima do saldo bloqueia',
        'Pagamento acima do saldo foi aceito'
    );

    $payment1 = $service->register($commissionId, ['amount' => '40,00', 'payment_date' => date('Y-m-d'), 'payment_method' => 'pix', 'proof_document_id' => $proofA, 'notes' => 'Parcial'], 1);
    $afterPartial = (new \App\Models\CollectorCommission())->findById($commissionId);
    is_ok($payment1 > 0, 'Pagamento parcial registrado', 'Pagamento parcial nao registrado');
    is_ok((string) ($afterPartial['payment_status'] ?? '') === 'parcialmente_pago', 'Status parcialmente_pago apos parcial', 'Status parcial incorreto');
    is_ok(abs((float) ($afterPartial['payment_total_amount'] ?? 0) - 40.00) < 0.01, 'Total pago parcial atualizado', 'Total pago parcial incorreto');

    $locked = (new \App\Services\CollectorCommissionCalculator())->syncForFinancialEntry($financialId, 1);
    is_ok($locked['status'] === 'locked', 'Comissao parcialmente paga nao recalcula', 'Comissao parcialmente paga recalculou');

    $balance = (float) ($afterPartial['payment_balance_amount'] ?? 0);
    $payment2 = $service->register($commissionId, ['amount' => number_format($balance, 2, ',', '.'), 'payment_date' => date('Y-m-d'), 'payment_method' => 'transferencia', 'proof_document_id' => $proofB], 1);
    $afterFull = (new \App\Models\CollectorCommission())->findById($commissionId);
    is_ok($payment2 > 0, 'Pagamento final registrado', 'Pagamento final nao registrado');
    is_ok((string) ($afterFull['payment_status'] ?? '') === 'pago', 'Status pago ao quitar saldo', 'Status final incorreto');
    is_ok((float) ($afterFull['payment_balance_amount'] ?? 1) <= 0.01, 'Saldo zerado ao quitar', 'Saldo nao zerou');
    is_ok(!empty($afterFull['paid_at']), 'paid_at preenchido ao quitar', 'paid_at ausente');

    expect_throw(
        fn () => $service->cancel($payment2, 1, ''),
        'Cancelamento exige motivo',
        'Cancelamento sem motivo foi aceito'
    );
    $service->cancel($payment2, 1, 'Estorno validado', 'estornado');
    $afterCancel = (new \App\Models\CollectorCommission())->findById($commissionId);
    is_ok((string) ($afterCancel['payment_status'] ?? '') === 'parcialmente_pago', 'Estorno recalcula status para parcialmente pago', 'Status apos estorno incorreto');
    is_ok(empty($afterCancel['paid_at']), 'paid_at limpo apos estorno parcial', 'paid_at nao foi limpo');

    $payments = (new \App\Models\CollectorCommissionPayment())->findByCommission($commissionId);
    is_ok(count($payments) === 2, 'Pagamentos nao sao excluidos fisicamente', 'Pagamento foi removido fisicamente');
    $cancelled = array_filter($payments, static fn (array $p): bool => (string) $p['status'] === 'estornado');
    is_ok(count($cancelled) === 1, 'Pagamento estornado preservado com status', 'Pagamento estornado nao encontrado');
} finally {
    $cleanup($pdo);
}

echo "\n=== RESUMO ETAPA 20B-2 ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validacao ETAPA 20B-2 COMPLETA - {$passes} PASS / 0 FAIL\n";
exit(0);
