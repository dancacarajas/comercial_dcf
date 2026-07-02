<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 20D: Portal do captador / extrato de comissoes.
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

echo "== ETAPA 20D - Portal do Captador / Extrato de Comissoes ==\n\n";

$files = [
    'app/Controllers/CollectorPortalController.php',
    'app/Views/layouts/admin.php',
    'app/Views/portal/dashboard.php',
    'app/Views/portal/commissions.php',
    'app/Views/portal/commission_show.php',
    'routes/web.php',
    'scripts/validate_etapa20d_collector_portal_commissions.php',
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
    "/portal/commissions",
    "/portal/commissions/{id}",
    "CollectorPortalController@commissions",
    "CollectorPortalController@commissionShow",
] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}
is_ok(
    strpos($routes, "/portal/commissions/{id}") < strpos($routes, "/portal/deals/{id}"),
    'Rotas de comissao do portal registradas antes das rotas dinamicas de deals',
    'Ordem das rotas do portal pode causar conflito'
);

$controller = (string) file_get_contents($root . '/app/Controllers/CollectorPortalController.php');
is_ok(!str_contains($controller, 'layouts/portal'), 'Portal usa layout oficial do sistema', 'Portal ainda usa layout proprio');
foreach ([
    'public function commissions',
    'public function commissionShow',
    'portalCommissionFilters',
    "'collector_id' => \$collectorId",
    "(int) (\$commission['collector_id'] ?? 0) !== (int) \$collector['id']",
    "CollectorCommissionPayment",
    "'portal/commissions'",
    "'portal/commission_show'",
] as $needle) {
    is_ok(str_contains($controller, $needle), "Controller cobre: {$needle}", "Controller nao cobre: {$needle}");
}
foreach ([
    'approve(',
    'blockManual(',
    'reopen(',
    'CollectorCommissionPaymentService',
] as $needle) {
    is_ok(!str_contains($controller, $needle), "Portal nao executa acao administrativa: {$needle}", "Portal contem acao administrativa: {$needle}");
}

$layout = (string) file_get_contents($root . '/app/Views/layouts/admin.php');
is_ok(str_contains($layout, 'collector_portal.view') && str_contains($layout, '/portal/commissions'), 'Menu oficial aponta para Minhas comissoes', 'Menu oficial sem Minhas comissoes');

$dashboard = (string) file_get_contents($root . '/app/Views/portal/dashboard.php');
is_ok(str_contains($dashboard, '/portal/commissions'), 'Dashboard do portal tem atalho para extrato', 'Dashboard sem atalho para extrato');

$listView = (string) file_get_contents($root . '/app/Views/portal/commissions.php');
foreach ([
    'Minhas comissões',
    'Extrato',
    'payment_total_amount',
    'payment_balance_amount',
    'incentive_project_id',
    'status_group',
    'payment_status',
] as $needle) {
    is_ok(str_contains($listView, $needle), "Listagem cobre: {$needle}", "Listagem nao cobre: {$needle}");
}
foreach ([
    'method="post"',
    '/commissions/export',
    "app_url('/commissions",
    '/documents/',
] as $needle) {
    is_ok(!str_contains($listView, $needle), "Listagem nao expõe recurso interno: {$needle}", "Listagem expoe recurso interno: {$needle}");
}

$showView = (string) file_get_contents($root . '/app/Views/portal/commission_show.php');
foreach ([
    'Pagamentos realizados',
    'Comissão bruta',
    'Comissão aplicada',
    'payment_total_amount',
    'payment_balance_amount',
    'proof_document_title',
] as $needle) {
    is_ok(str_contains($showView, $needle), "Detalhe cobre: {$needle}", "Detalhe nao cobre: {$needle}");
}
foreach ([
    'method="post"',
    "app_url('/commissions",
    '/documents/',
    '/approve',
    '/block',
    '/reopen',
    '/cancel',
] as $needle) {
    is_ok(!str_contains($showView, $needle), "Detalhe nao expõe acao/link interno: {$needle}", "Detalhe expoe acao/link interno: {$needle}");
}

try {
    $collectorId = (int) $pdo->query('SELECT id FROM collectors ORDER BY id LIMIT 1')->fetchColumn();
    if ($collectorId > 0) {
        $model = new \App\Models\CollectorCommission();
        $filters = ['collector_id' => $collectorId, 'status_group' => 'a_pagar'];
        $model->summary($filters);
        $model->paginate($filters, 1, 5);
        $model->reportByProject(['collector_id' => $collectorId], 5);
        ok('Consultas do extrato do portal executam com collector_id');
    } else {
        ok('Sem captadores cadastrados; consulta com dados reais dispensada');
    }
} catch (Throwable $e) {
    fail('Erro nas consultas do extrato do portal: ' . $e->getMessage());
}

echo "\nResultado: {$passes} PASS, " . count($failures) . " FAIL\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
