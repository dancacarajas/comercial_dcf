<?php

declare(strict_types=1);

/**
 * Regressão leve — ETAPA 19.
 * Confere que a introdução do Projeto Incentivado não quebrou o núcleo do CRM:
 * sintaxe dos controllers/models, rotas, e integridade das colunas/projeto.
 * Meta: 0 FAIL. Não cria dados.
 *
 * Uso: php scripts/validate_etapa19_regressao.php
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
    if (function_exists('proc_open')) {
        $pr = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pi);
        if (is_resource($pr)) {
            stream_get_contents($pi[1]); stream_get_contents($pi[2]);
            fclose($pi[1]); fclose($pi[2]);
            return (int) proc_close($pr);
        }
    }
    if (function_exists('exec')) { $o = []; exec($cmd, $o, $rc); return (int) $rc; }
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

// 1) Sintaxe dos arquivos tocados/afetados pela Etapa 19
$touched = [
    'app/Services/CollectorProspectIntake.php',
    'app/Controllers/CollectorPortalController.php',
    'app/Controllers/CollectorAssignmentController.php',
    'app/Controllers/OpportunityController.php',
    'app/Controllers/ProposalController.php',
    'app/Controllers/SponsorController.php',
    'app/Controllers/FinancialController.php',
    'app/Controllers/IncentiveProjectController.php',
    'app/Models/Opportunity.php', 'app/Models/Proposal.php', 'app/Models/Sponsor.php',
    'app/Models/FinancialEntry.php', 'app/Models/Quota.php',
    'app/Models/CollectorAssignment.php', 'app/Models/CollectorDeal.php',
    'app/Models/IncentiveProject.php', 'app/Models/IncentiveProjectBudgetItem.php',
    'app/Views/portal/prospect_form.php', 'app/Views/opportunities/_form.php',
    'app/Views/layouts/admin.php',
    'routes/web.php',
];
foreach ($touched as $rel) {
    $p = $root . '/' . $rel;
    is_ok(is_file($p) && lint_path($p) === 0, "Sintaxe OK: {$rel}", "Syntax/arquivo: {$rel}");
}

// 2) Models existentes aceitam incentive_project_id (fillable)
foreach ([
    \App\Models\Opportunity::class, \App\Models\Proposal::class, \App\Models\Sponsor::class,
    \App\Models\FinancialEntry::class, \App\Models\Quota::class,
    \App\Models\CollectorAssignment::class, \App\Models\CollectorDeal::class,
] as $cls) {
    $r = new ReflectionClass($cls);
    $fillable = $r->getConstant('FILLABLE');
    is_ok(is_array($fillable) && in_array('incentive_project_id', $fillable, true),
        "FILLABLE inclui incentive_project_id: {$cls}", "FILLABLE sem incentive_project_id: {$cls}");
}

// 3) Permissões internas essenciais continuam presentes
foreach (['opportunities.view', 'proposals.view', 'sponsors.view', 'financials.view', 'collector_portal.view'] as $slug) {
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE slug='" . $slug . "'")->fetchColumn();
    is_ok($cnt === 1, "Permissão preservada: {$slug}", "Permissão ausente: {$slug}");
}

// 4) Tabelas novas e projeto base presentes
foreach (['incentive_projects', 'incentive_project_budget_items'] as $t) {
    $cnt = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'"
    )->fetchColumn();
    is_ok($cnt === 1, "Tabela existe: {$t}", "Tabela ausente: {$t}");
}

echo "\n=== RESUMO REGRESSÃO E19 ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Regressão ETAPA 19 COMPLETA — {$passes} PASS / 0 FAIL\n";
exit(0);
