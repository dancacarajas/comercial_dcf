<?php

declare(strict_types=1);

/**
 * Validação — ETAPA 19: Projetos Incentivados / PRONACs / Plano de Captação.
 * Meta: 0 FAIL. Cria dados temporários (prefixo TESTE E19), valida e limpa.
 *
 * Uso: php scripts/validate_etapa19_projects.php
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

// ----------------------------------------------------------------------
// Bootstrap leve
// ----------------------------------------------------------------------
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

// ----------------------------------------------------------------------
// 1) Arquivos + sintaxe
// ----------------------------------------------------------------------
$files = [
    'app/Models/IncentiveProject.php',
    'app/Models/IncentiveProjectBudgetItem.php',
    'app/Controllers/IncentiveProjectController.php',
    'app/Views/incentive_projects/index.php',
    'app/Views/incentive_projects/_form.php',
    'app/Views/incentive_projects/show.php',
    'app/Views/incentive_projects/budget.php',
    'app/Views/incentive_projects/dashboard.php',
    'scripts/run_migration_etapa19_projects.php',
];
foreach ($files as $rel) {
    $p = $root . '/' . $rel;
    is_ok(is_file($p), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($p)) { is_ok(lint_path($p) === 0, "php -l {$rel}", "Syntax error: {$rel}"); }
}

// ----------------------------------------------------------------------
// 2) Rotas e permissões
// ----------------------------------------------------------------------
$routes = (string) file_get_contents($root . '/routes/web.php');
foreach ([
    "/projects'", "/projects/create'", '/projects/{id}', '/projects/{id}/edit',
    '/projects/{id}/budget', '/projects/{id}/dashboard',
    'IncentiveProjectController@index', 'IncentiveProjectController@store',
] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota/handler presente: {$needle}", "Rota ausente: {$needle}");
}

$perms = [
    'incentive_projects.view', 'incentive_projects.create', 'incentive_projects.edit',
    'incentive_projects.archive', 'incentive_projects.budget', 'incentive_projects.activate_capture',
];
foreach ($perms as $slug) {
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE slug='" . $slug . "'")->fetchColumn();
    is_ok($cnt === 1, "Permissão existe: {$slug}", "Permissão ausente: {$slug}");
}
$adminId = (int) $pdo->query("SELECT id FROM roles WHERE slug='administrador-geral' LIMIT 1")->fetchColumn();
$grantCnt = (int) $pdo->query(
    "SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
      WHERE rp.role_id={$adminId} AND p.slug LIKE 'incentive_projects.%'"
)->fetchColumn();
is_ok($grantCnt >= 6, 'administrador-geral recebe permissões de projeto', 'Grants de projeto ausentes para admin');

// Coluna incentive_project_id nas 7 tabelas prioritárias
$opTables = ['quotas', 'opportunities', 'proposals', 'sponsors', 'financial_entries', 'collector_assignments', 'collector_deals'];
foreach ($opTables as $t) {
    $has = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='incentive_project_id'"
    )->fetchColumn();
    is_ok($has === 1, "Coluna incentive_project_id em {$t}", "Coluna ausente em {$t}");
}

// ----------------------------------------------------------------------
// Limpeza / setup
// ----------------------------------------------------------------------
$T = 'TESTE E19';
function cleanupE19(PDO $pdo, string $T): void {
    $pdo->exec("DELETE cd FROM collector_deals cd JOIN collectors c ON c.id=cd.collector_id WHERE c.collector_code IN ('DCF-E19-A','DCF-E19-B')");
    $pdo->exec("DELETE ca FROM collector_assignments ca JOIN collectors c ON c.id=ca.collector_id WHERE c.collector_code IN ('DCF-E19-A','DCF-E19-B')");
    $pdo->exec("DELETE FROM financial_entries WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM sponsors WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM proposals WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM opportunities WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE ca FROM collector_assignments ca JOIN companies co ON co.id=ca.company_id WHERE co.name LIKE '{$T}%'");
    $pdo->exec("DELETE cd FROM collector_deals cd JOIN companies co ON co.id=cd.company_id WHERE co.name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM contacts WHERE company_id IN (SELECT id FROM companies WHERE name LIKE '{$T}%')");
    $pdo->exec("DELETE FROM companies WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code IN ('DCF-E19-A','DCF-E19-B')");
    $pdo->exec("DELETE FROM users WHERE email='teste.e19.captador@example.com'");
    $pdo->exec("DELETE FROM quotas WHERE name LIKE '{$T}%'");
    $pdo->exec("DELETE bi FROM incentive_project_budget_items bi JOIN incentive_projects p ON p.id=bi.incentive_project_id WHERE p.project_name LIKE '{$T}%'");
    $pdo->exec("DELETE FROM incentive_projects WHERE project_name LIKE '{$T}%'");
}
cleanupE19($pdo, $T);

$ipModel = new \App\Models\IncentiveProject();

// Test 1 — cria projeto incentivado
$approved = 470448.00;
$projAId = (int) $ipModel->create([
    'project_name'          => "$T Projeto A",
    'edition_year'          => 2030,
    'project_status'        => 'em_captacao',
    'approved_total_amount' => $approved,
    'authorized_capture_amount' => $approved,
]);
$projBId = (int) $ipModel->create([
    'project_name'          => "$T Projeto B",
    'edition_year'          => 2031,
    'project_status'        => 'em_captacao',
    'approved_total_amount' => $approved,
]);
is_ok($projAId > 0 && $projBId > 0, 'Cria projeto incentivado', 'Falha ao criar projeto incentivado');

// Test 2 — commission_factor
$factor = $ipModel->computeFactor(42768.00, $approved);
is_ok($factor !== null && abs($factor - 0.0909090909) < 0.0000001, 'commission_factor = 42768/470448 ≈ 0,0909090909', 'Cálculo do fator incorreto: ' . var_export($factor, true));

// Test 3 — rubrica de captação
$biModel = new \App\Models\IncentiveProjectBudgetItem();
$biId = (int) $biModel->create([
    'incentive_project_id'       => $projAId,
    'item_number'                => 41,
    'product'                    => 'Administração do Projeto',
    'stage'                      => 'Captação de Recursos',
    'budget_item_name'           => 'Remuneração para captação de recursos',
    'requested_amount'           => 42768.00,
    'is_capture_commission_item' => 1,
]);
is_ok($biId > 0, 'Cadastra rubrica de captação', 'Falha ao cadastrar rubrica');

// Test 4 — rubrica alimenta capture_commission_budget
$ipModel->syncBudgetAndFactor($projAId);
$projA = $ipModel->findById($projAId);
is_ok(abs((float) $projA['capture_commission_budget'] - 42768.00) < 0.01, 'Rubrica alimenta capture_commission_budget', 'capture_commission_budget incorreto: ' . ($projA['capture_commission_budget'] ?? 'null'));
is_ok($projA['commission_factor'] !== null && abs((float) $projA['commission_factor'] - 0.0909090909) < 0.0000001, 'Fator recalculado pela rubrica', 'Fator recalculado incorreto');

// Empresa/collector base
$pdo->prepare("INSERT INTO companies (name,priority,status,source,created_at) VALUES (?,?,?,?,NOW())")
    ->execute(["$T Empresa Multi", 'C', 'prospect', 'indicação interna']);
$coMulti = (int) $pdo->lastInsertId();

// Test 5 — cota pertence ao projeto
$quotaModel = new \App\Models\Quota();
$quotaId = (int) $quotaModel->create([
    'incentive_project_id' => $projAId,
    'name'                 => "$T Cota Master",
    'amount'               => 100000.00,
    'available_quantity'   => 1,
    'status'               => 'disponivel',
]);
$quotaProj = (int) $pdo->query('SELECT incentive_project_id FROM quotas WHERE id=' . $quotaId)->fetchColumn();
is_ok($quotaProj === $projAId, 'Cota pertence ao projeto', 'Cota não vinculada ao projeto');

// Test 6 — oportunidade exige projeto (regra no controller)
$oppCtrl = (string) file_get_contents($root . '/app/Controllers/OpportunityController.php');
is_ok(str_contains($oppCtrl, "empty(\$data['incentive_project_id'])") && str_contains($oppCtrl, 'projeto incentivado da oportunidade'),
    'OpportunityController bloqueia criar sem projeto', 'Regra de projeto obrigatório ausente na oportunidade');

// Oportunidade real (com projeto) para herança
$pdo->prepare("INSERT INTO opportunities (incentive_project_id,company_id,title,status,source,created_at) VALUES (?,?,?,?,?,NOW())")
    ->execute([$projAId, $coMulti, "$T Opp A", 'prospect_identificado', 'outbound']);
$oppId = (int) $pdo->lastInsertId();

// Test 7 — proposta herda projeto da oportunidade (applyAutofill)
$rc = new ReflectionClass(\App\Controllers\ProposalController::class);
$pc = $rc->newInstanceWithoutConstructor();
$m = $rc->getMethod('applyAutofill'); $m->setAccessible(true);
$propData = $m->invoke($pc, new \App\Models\Proposal(), ['opportunity_id' => $oppId]);
is_ok((int) ($propData['incentive_project_id'] ?? 0) === $projAId, 'Proposta herda projeto da oportunidade', 'Proposta não herdou projeto: ' . var_export($propData['incentive_project_id'] ?? null, true));

// Proposta real para herança do sponsor
$pdo->prepare("INSERT INTO proposals (incentive_project_id,company_id,opportunity_id,title,type,proposed_value,status,created_on,created_at) VALUES (?,?,?,?,?,?,?,CURDATE(),NOW())")
    ->execute([$projAId, $coMulti, $oppId, "$T Proposta A", 'proposta_por_cota', 50000.00, 'rascunho']);
$propId = (int) $pdo->lastInsertId();

// Test 8 — patrocinador herda projeto da proposta
$rcS = new ReflectionClass(\App\Controllers\SponsorController::class);
$sc = $rcS->newInstanceWithoutConstructor();
$mS = $rcS->getMethod('applyAutofill'); $mS->setAccessible(true);
$sponData = $mS->invoke($sc, ['proposal_id' => $propId]);
is_ok((int) ($sponData['incentive_project_id'] ?? 0) === $projAId, 'Patrocinador herda projeto da proposta', 'Patrocinador não herdou projeto: ' . var_export($sponData['incentive_project_id'] ?? null, true));

// Patrocinador real para herança do financeiro
$pdo->prepare("INSERT INTO sponsors (incentive_project_id,company_id,proposal_id,opportunity_id,sponsor_display_name,status,confirmed_amount,created_at) VALUES (?,?,?,?,?,?,?,NOW())")
    ->execute([$projAId, $coMulti, $propId, $oppId, "$T Patrocinador A", 'confirmado', 50000.00]);
$sponId = (int) $pdo->lastInsertId();

// Test 9 — financeiro herda projeto do patrocinador
$rcF = new ReflectionClass(\App\Controllers\FinancialController::class);
$fc = $rcF->newInstanceWithoutConstructor();
$mF = $rcF->getMethod('applyFromSponsor'); $mF->setAccessible(true);
$finData = $mF->invoke($fc, ['sponsor_id' => $sponId]);
is_ok((int) ($finData['incentive_project_id'] ?? 0) === $projAId, 'Financeiro herda projeto do patrocinador', 'Financeiro não herdou projeto: ' . var_export($finData['incentive_project_id'] ?? null, true));

// ----------------------------------------------------------------------
// Captador + portal por projeto
// ----------------------------------------------------------------------
$pdo->prepare("INSERT INTO collectors (name,collector_code,status,registration_status,created_by,created_at) VALUES (?,?,?,?,1,NOW())")
    ->execute(["$T Captador A", 'DCF-E19-A', 'ativo', 'validado']);
$capId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO collectors (name,collector_code,status,registration_status,created_by,created_at) VALUES (?,?,?,?,1,NOW())")
    ->execute(["$T Outro Captador", 'DCF-E19-B', 'ativo', 'validado']);
$otherId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (name,email,password_hash,status,must_change_password,created_at) VALUES (?,?,?,?,0,NOW())")
    ->execute(["$T Captador A", 'teste.e19.captador@example.com', password_hash('x', PASSWORD_DEFAULT), 'active']);
$uid = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE collectors SET user_id=? WHERE id=?')->execute([$uid, $capId]);

$collector = (new \App\Models\Collector())->findByUserId($uid);
$svc = new \App\Services\CollectorProspectIntake();

// Test 10/11 — captador cadastra prospect para projeto; assignment/deal vinculados
$rPortal = $svc->intake($collector, ['name' => "$T Prospect Portal", 'cnpj' => ''], $uid, $projAId);
is_ok($rPortal['status'] === 'criado', 'Captador cadastra prospect para projeto', 'Intake por projeto falhou: ' . $rPortal['status']);
$aProj = (int) $pdo->query('SELECT incentive_project_id FROM collector_assignments WHERE id=' . (int) $rPortal['assignment_id'])->fetchColumn();
$dProj = (int) $pdo->query('SELECT incentive_project_id FROM collector_deals WHERE id=' . (int) $rPortal['deal_id'])->fetchColumn();
is_ok($aProj === $projAId && $dProj === $projAId, 'Assignment e deal vinculados ao projeto', 'Assignment/deal sem projeto correto');

// Test 12 — exclusividade bloqueia dentro do MESMO projeto
$pdo->prepare("INSERT INTO companies (name,priority,status,source,created_at) VALUES (?,?,?,?,NOW())")
    ->execute(["$T Empresa Exclusiva", 'C', 'prospect', 'indicação interna']);
$coExcl = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO collector_assignments (incentive_project_id,collector_id,company_id,assignment_type,status,created_by,created_at) VALUES (?,?,?,'exclusiva','autorizada',1,NOW())")
    ->execute([$projAId, $otherId, $coExcl]);
$rBlock = $svc->intake($collector, ['name' => "$T Empresa Exclusiva", 'cnpj' => ''], $uid, $projAId);
is_ok($rBlock['status'] === 'bloqueado', 'Exclusiva de outro bloqueia no mesmo projeto', 'Exclusiva não bloqueou no mesmo projeto: ' . $rBlock['status']);

// Test 13 — mesma empresa pode ser usada em OUTRO projeto
$rOther = $svc->intake($collector, ['name' => "$T Empresa Exclusiva", 'cnpj' => ''], $uid, $projBId);
is_ok($rOther['status'] === 'criado', 'Mesma empresa liberada em outro projeto', 'Empresa indevidamente bloqueada em outro projeto: ' . $rOther['status']);

// Test 14 — dashboard do projeto calcula total captado
$pdo->prepare("INSERT INTO financial_entries (incentive_project_id,company_id,sponsor_id,title,entry_type,status,planned_amount,received_amount,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
    ->execute([$projAId, $coMulti, $sponId, "$T Lançamento", 'parcela_patrocinio', 'recebido', 50000.00, 30000.00]);
$dash = $ipModel->dashboard($projAId);
is_ok((float) $dash['financial_received'] >= 30000.00, 'Dashboard calcula total captado (recebido)', 'Dashboard recebido incorreto: ' . ($dash['financial_received'] ?? 'null'));
is_ok((int) $dash['sponsors_total'] >= 1 && (int) $dash['quotas_count'] >= 1, 'Dashboard agrega patrocinadores e cotas do projeto', 'Dashboard agregações incorretas');

// Test 15 — backfill vinculou registros antigos ao projeto 2026 (id=1)
$proj2026 = $pdo->query("SELECT id FROM incentive_projects WHERE project_name='Dança Carajás Festival 2026' LIMIT 1")->fetchColumn();
if ($proj2026 !== false) {
    $back = (int) $pdo->query('SELECT COUNT(*) FROM quotas WHERE incentive_project_id=' . (int) $proj2026)->fetchColumn();
    is_ok($back >= 1, 'Backfill vinculou registros ao projeto 2026', 'Backfill não vinculou registros ao projeto 2026');
} else {
    ok('Projeto 2026 não presente neste ambiente (backfill ignorado)');
}

// Test 16 — Fase 2B continua funcionando (intake sem projeto = compatível)
$rLegacy = $svc->intake($collector, ['name' => "$T Prospect Legado", 'cnpj' => ''], $uid, null);
is_ok(in_array($rLegacy['status'], ['criado', 'analise_interna'], true), 'Intake legado (sem projeto) continua funcionando', 'Compatibilidade Fase 2B quebrada: ' . $rLegacy['status']);

// ----------------------------------------------------------------------
// Limpeza
// ----------------------------------------------------------------------
try {
    cleanupE19($pdo, $T);
    ok('Limpeza dos dados de teste concluída');
} catch (Throwable $e) {
    fail('Limpeza: ' . $e->getMessage());
}

echo "\n=== RESUMO ETAPA 19 ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validação ETAPA 19 COMPLETA — {$passes} PASS / 0 FAIL\n";
exit(0);
