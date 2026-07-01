<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 19C: Fechamento de escopo por projeto antes da comissao.
 * Meta: 0 FAIL. Nao cria dados de negocio.
 *
 * Uso: php scripts/validate_etapa19c_project_scope.php
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
            stream_get_contents($pi[1]);
            stream_get_contents($pi[2]);
            fclose($pi[1]);
            fclose($pi[2]);
            return (int) proc_close($pr);
        }
    }
    if (function_exists('exec')) { $o = []; exec($cmd, $o, $rc); return (int) $rc; }
    return 0;
}

function contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }
    return true;
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

// ----------------------------------------------------------------------
// 1) Arquivos e sintaxe
// ----------------------------------------------------------------------
$files = [
    'scripts/run_migration_etapa19c_project_scope.php',
    'app/Models/Contract.php',
    'app/Models/FinancialEntry.php',
    'app/Models/Document.php',
    'app/Models/Counterpart.php',
    'app/Models/SponsorDossier.php',
    'app/Models/Report.php',
    'app/Controllers/ContractController.php',
    'app/Controllers/FinancialController.php',
    'app/Controllers/DocumentController.php',
    'app/Controllers/CounterpartController.php',
    'app/Controllers/SponsorDossierController.php',
    'app/Controllers/LeadController.php',
    'app/Controllers/ReportController.php',
    'app/Views/contracts/_form.php',
    'app/Views/financials/_form.php',
    'app/Views/documents/_form.php',
    'app/Views/counterparts/_form.php',
    'app/Views/sponsor_dossiers/_form.php',
    'app/Views/leads/convert.php',
    'app/Views/reports/_filters.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path)) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

// ----------------------------------------------------------------------
// 2) Colunas de escopo por projeto
// ----------------------------------------------------------------------
$projectTables = ['contracts', 'documents', 'counterparts', 'sponsor_dossiers'];
foreach ($projectTables as $table) {
    $has = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='incentive_project_id'"
    )->fetchColumn();
    is_ok($has === 1, "Coluna incentive_project_id em {$table}", "Coluna incentive_project_id ausente em {$table}");
}

// ----------------------------------------------------------------------
// 3) Models bloqueiam registros obrigatorios sem projeto
// ----------------------------------------------------------------------
$modelRequirements = [
    \App\Models\Contract::class       => 'Contrato sem projeto bloqueia',
    \App\Models\FinancialEntry::class => 'Financeiro sem projeto bloqueia',
    \App\Models\Counterpart::class    => 'Contrapartida sem projeto bloqueia',
    \App\Models\SponsorDossier::class => 'Dossie sem projeto bloqueia',
];
foreach ($modelRequirements as $class => $message) {
    $errors = (new $class())->validate([], 'create');
    is_ok(isset($errors['incentive_project_id']), $message, "{$class} permite create sem projeto");
}

foreach ([
    'app/Models/Contract.php',
    'app/Models/FinancialEntry.php',
    'app/Models/Document.php',
    'app/Models/Counterpart.php',
    'app/Models/SponsorDossier.php',
] as $rel) {
    $src = (string) file_get_contents($root . '/' . $rel);
    is_ok(str_contains($src, "'incentive_project_id'") || str_contains($src, '"incentive_project_id"'),
        "Model declara incentive_project_id: {$rel}", "Model sem incentive_project_id: {$rel}");
}

// ----------------------------------------------------------------------
// 4) Controllers propagam projeto e filtram cotas pelo projeto
// ----------------------------------------------------------------------
$controllerExpectations = [
    'app/Controllers/ContractController.php' => [
        "use App\\Models\\IncentiveProject;",
        "'projects'",
        'applyProjectScope',
        'activeOptions($projectId > 0 ? $projectId : null)',
    ],
    'app/Controllers/FinancialController.php' => [
        "use App\\Models\\IncentiveProject;",
        "'projects'",
        'applyProjectScope',
        'activeOptions($projectId > 0 ? $projectId : null)',
    ],
    'app/Controllers/DocumentController.php' => [
        "use App\\Models\\IncentiveProject;",
        "'projects'",
        'activeOptions($projectId > 0 ? $projectId : null)',
    ],
    'app/Controllers/CounterpartController.php' => [
        "use App\\Models\\IncentiveProject;",
        "'projects'",
        'applyProjectScope',
        'activeOptions($projectId > 0 ? $projectId : null)',
    ],
    'app/Controllers/SponsorDossierController.php' => [
        "use App\\Models\\IncentiveProject;",
        "'projects'",
        'applyProjectScope',
        'activeOptions($projectId > 0 ? $projectId : null)',
    ],
];
foreach ($controllerExpectations as $rel => $needles) {
    $src = (string) file_get_contents($root . '/' . $rel);
    is_ok(contains_all($src, $needles), "Controller propaga escopo por projeto: {$rel}", "Controller incompleto para projeto: {$rel}");
}

// ----------------------------------------------------------------------
// 5) Formularios exibem projeto
// ----------------------------------------------------------------------
foreach ([
    'app/Views/contracts/_form.php',
    'app/Views/financials/_form.php',
    'app/Views/documents/_form.php',
    'app/Views/counterparts/_form.php',
    'app/Views/sponsor_dossiers/_form.php',
] as $rel) {
    $src = (string) file_get_contents($root . '/' . $rel);
    is_ok(contains_all($src, ['$projects', 'name="incentive_project_id"']),
        "Formulario com projeto: {$rel}", "Formulario sem projeto: {$rel}");
}

// ----------------------------------------------------------------------
// 6) Conversao de lead para oportunidade exige e grava projeto
// ----------------------------------------------------------------------
$leadController = (string) file_get_contents($root . '/app/Controllers/LeadController.php');
$leadView = (string) file_get_contents($root . '/app/Views/leads/convert.php');
is_ok(contains_all($leadController, [
    'new IncentiveProject()',
    "input('incentive_project_id'",
    "'incentive_project_id' => \$projectId",
    'Selecione o projeto incentivado para converter o lead em oportunidade.',
]), 'Lead->oportunidade exige e grava projeto', 'Conversao de lead nao fecha escopo por projeto');
is_ok(contains_all($leadView, ['$projects', 'name="incentive_project_id"']),
    'Tela de conversao de lead exibe projeto', 'Tela de conversao de lead sem projeto');

// ----------------------------------------------------------------------
// 7) Relatorios aceitam filtro por projeto e propagam aos modulos
// ----------------------------------------------------------------------
$report = new \App\Models\Report();
$normalized = $report->normalizeFilters(['incentive_project_id' => '123']);
is_ok(($normalized['incentive_project_id'] ?? 0) === 123, 'Report normaliza incentive_project_id', 'Report nao normaliza projeto');
$ref = new ReflectionClass($report);
$moduleFilters = $ref->getMethod('moduleFilters');
$moduleFilters->setAccessible(true);
$financialFilters = $moduleFilters->invoke($report, $normalized, 'financial');
is_ok(($financialFilters['incentive_project_id'] ?? 0) === 123, 'Report propaga projeto para financeiro', 'Report nao propaga projeto para financeiro');
$reportFilters = (string) file_get_contents($root . '/app/Views/reports/_filters.php');
is_ok(contains_all($reportFilters, ['projects', 'name="incentive_project_id"']),
    'Filtro de relatorio exibe projeto', 'Filtro de relatorio sem projeto');

// ----------------------------------------------------------------------
// 8) Schema de instalacao inclui projeto base e cotas do Festival 2026
// ----------------------------------------------------------------------
$install = (string) file_get_contents($root . '/database/install_schema.sql');
is_ok(contains_all($install, [
    'Dança Carajás Festival 2026',
    'ALTER TABLE `contracts`',
    'ALTER TABLE `documents`',
    'ALTER TABLE `counterparts`',
    'ALTER TABLE `sponsor_dossiers`',
    'UPDATE `quotas`',
    '`incentive_project_id`',
]), 'install_schema.sql cobre escopo 19C e cotas 2026', 'install_schema.sql incompleto para 19C');

echo "\n=== RESUMO ETAPA 19C ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validacao ETAPA 19C COMPLETA - {$passes} PASS / 0 FAIL\n";
exit(0);
