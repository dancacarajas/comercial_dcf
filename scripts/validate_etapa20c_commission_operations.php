<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20C: Consolidacao operacional de comissoes.
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
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== ETAPA 20C - Consolidacao Operacional de Comissoes ==\n\n";

$files = [
    'app/Models/CollectorCommission.php',
    'app/Models/CommissionPool.php',
    'app/Controllers/CollectorCommissionController.php',
    'app/Views/collector_commissions/index.php',
    'app/Views/collector_commissions/dashboard.php',
    'app/Views/collector_commissions/pools.php',
    'app/Views/incentive_projects/show.php',
    'app/Views/collectors/show.php',
    'routes/web.php',
    'scripts/validate_etapa20c_commission_operations.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach ([
    "/commissions/dashboard",
    "/commissions/export",
    "CollectorCommissionController@dashboard",
    "CollectorCommissionController@export",
] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}
is_ok(
    strpos($routes, "/commissions/export") < strpos($routes, "/commissions/{id}"),
    'Rotas dashboard/export antes da rota dinamica',
    'Rotas dashboard/export podem conflitar com /commissions/{id}'
);

$controller = (string) file_get_contents($root . '/app/Controllers/CollectorCommissionController.php');
foreach ([
    'summary($filters)',
    'reportByProject($filters)',
    'reportByCollector($filters)',
    'reportByFinancial($filters)',
    'reportByStatus($filters)',
    'operationalAlerts($filters)',
    "Content-Type: text/csv",
    "'status_group'",
    "'attribution_type'",
    "'date_from'",
    "'date_to'",
] as $needle) {
    is_ok(str_contains($controller, $needle), "Controller cobre: {$needle}", "Controller nao cobre: {$needle}");
}

$commissionModel = (string) file_get_contents($root . '/app/Models/CollectorCommission.php');
foreach ([
    'public function summary',
    'public function reportByProject',
    'public function reportByCollector',
    'public function reportByFinancial',
    'public function reportByStatus',
    'public function operationalAlerts',
    'financial_cancelled_with_paid_commission',
    'payment_without_valid_proof',
    'locked_shared_deals',
    'status_group_payment',
] as $needle) {
    is_ok(str_contains($commissionModel, $needle), "Model cobre: {$needle}", "Model nao cobre: {$needle}");
}

$indexView = (string) file_get_contents($root . '/app/Views/collector_commissions/index.php');
foreach ([
    '/commissions/dashboard',
    '/commissions/export',
    'payment_status',
    'status_group',
    'attribution_type',
    'date_from',
    'date_to',
] as $needle) {
    is_ok(str_contains($indexView, $needle), "Listagem cobre: {$needle}", "Listagem nao cobre: {$needle}");
}

$dashboardView = (string) file_get_contents($root . '/app/Views/collector_commissions/dashboard.php');
foreach ([
    'Por projeto',
    'Por captador',
    'Por financeiro',
    'Por status',
    'Alertas operacionais',
    'Financeiro cancelado com comissao paga',
    'Pagamento sem comprovante valido',
    'Rateios bloqueados por comissao',
] as $needle) {
    is_ok(str_contains($dashboardView, $needle), "Dashboard cobre: {$needle}", "Dashboard nao cobre: {$needle}");
}

$projectView = (string) file_get_contents($root . '/app/Views/incentive_projects/show.php');
is_ok(str_contains($projectView, "/commissions?incentive_project_id="), 'Atalho do projeto para comissoes', 'Atalho do projeto ausente');
is_ok(str_contains($projectView, "/commissions/dashboard?incentive_project_id="), 'Atalho do projeto para dashboard', 'Atalho de dashboard do projeto ausente');

$collectorView = (string) file_get_contents($root . '/app/Views/collectors/show.php');
is_ok(str_contains($collectorView, "/commissions?collector_id="), 'Atalho do captador para comissoes', 'Atalho do captador ausente');

$model = new \App\Models\CollectorCommission();
try {
    $filters = [
        'status_group' => 'a_pagar',
        'attribution_type' => 'direta',
        'date_from' => '2000-01-01',
        'date_to' => '2999-12-31',
        'q' => 'validacao-20c-sem-obrigar-dados',
    ];
    $summary = $model->summary($filters);
    $model->reportByProject($filters);
    $model->reportByCollector($filters);
    $model->reportByFinancial($filters);
    $model->reportByStatus($filters);
    $alerts = $model->operationalAlerts($filters);
    $model->exportRows($filters, 10);
    is_ok(is_array($summary), 'Consultas agregadas executam sem erro SQL', 'Consultas agregadas falharam');
    is_ok(array_key_exists('financial_cancelled_with_paid_commission', $alerts), 'Alertas retornam chaves esperadas', 'Alertas sem chaves esperadas');
} catch (Throwable $e) {
    fail('Erro nas consultas operacionais: ' . $e->getMessage());
}

$poolRows = (new \App\Models\CommissionPool())->paginate([], 1, 5);
is_ok(is_array($poolRows), 'Consulta de pools com indicadores executa', 'Consulta de pools falhou');

echo "\nResultado: {$passes} PASS, " . count($failures) . " FAIL\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

exit(0);
