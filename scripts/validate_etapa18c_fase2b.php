<?php

declare(strict_types=1);

/**
 * Validação — Etapa 18C / Fase 2B: Portal do Captador e Carteira Própria.
 * Meta: 0 FAIL. Cria dados temporários (prefixo TESTE 18C F2B), valida e limpa.
 *
 * Uso: VALIDATE_BASE_URL=https://comercial.dancacarajas.com.br \
 *      php scripts/validate_etapa18c_fase2b.php
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
// Bootstrap leve (autoload + env + helpers + DB)
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
$cfg = require $root . '/config/app.php';
$BASE = getenv('VALIDATE_BASE_URL') ?: rtrim((string) ($cfg['url'] ?? 'https://comercial.dancacarajas.com.br'), '/');
$PWD = 'TesteF2B#2026';

// ----------------------------------------------------------------------
// 1) Arquivos + sintaxe
// ----------------------------------------------------------------------
$files = [
    'app/Services/CollectorProspectIntake.php',
    'app/Controllers/CollectorPortalController.php',
    'app/Views/layouts/admin.php',
    'app/Views/portal/dashboard.php',
    'app/Views/portal/prospect_form.php',
    'app/Views/portal/deal_show.php',
    'app/Views/portal/no_access.php',
];
foreach ($files as $rel) {
    $p = $root . '/' . $rel;
    is_ok(is_file($p), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($p)) { is_ok(lint_path($p) === 0, "php -l {$rel}", "Syntax error: {$rel}"); }
}

// ----------------------------------------------------------------------
// 2) Rotas do portal registradas
// ----------------------------------------------------------------------
$routes = (string) file_get_contents($root . '/routes/web.php');
foreach ([
    "/portal'", "/portal/captador'", "/portal/prospects/create'", "/portal/prospects'",
    '/portal/deals/{id}', '/portal/deals/{id}/note', '/portal/companies/{id}/contacts',
    'CollectorPortalController@dashboard', 'CollectorPortalController@prospectStore',
] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota/handler presente: {$needle}", "Rota ausente: {$needle}");
}

// ----------------------------------------------------------------------
// 3) Permissões do portal + vínculo à role captador-externo
// ----------------------------------------------------------------------
$role = $pdo->query("SELECT id FROM roles WHERE slug='captador-externo' LIMIT 1")->fetch();
is_ok($role !== false, 'Role captador-externo existe', 'Role captador-externo ausente');
$roleId = (int) ($role['id'] ?? 0);
$portalPerms = [
    'collector_portal.view',
    'collector_portal.companies.create',
    'collector_portal.contacts.create',
    'collector_portal.deals.view',
    'collector_portal.deals.note',
];
$operationalPerms = [
    'dashboard.view',
    'incentive_projects.view',
    'companies.view',
    'companies.create',
    'contacts.view',
    'contacts.create',
];
foreach (array_merge($portalPerms, $operationalPerms) as $slug) {
    $cnt = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
          WHERE rp.role_id={$roleId} AND p.slug='" . $slug . "'"
    )->fetchColumn();
    is_ok($cnt === 1, "Permissão concedida: {$slug}", "Permissão ausente para captador-externo: {$slug}");
}

// ----------------------------------------------------------------------
// 4) Código aditivo presente (não quebra fluxo interno)
// ----------------------------------------------------------------------
is_ok(str_contains((string) file_get_contents($root . '/app/Controllers/DashboardController.php'), "collector_portal.view"),
    'Dashboard redireciona captador-externo ao portal', 'Redirect do dashboard ausente');
is_ok(method_exists(new \App\Models\Collector(), 'findByUserId'),
    'Collector::findByUserId existe', 'Collector::findByUserId ausente');
$portalController = (string) file_get_contents($root . '/app/Controllers/CollectorPortalController.php');
$adminLayout = (string) file_get_contents($root . '/app/Views/layouts/admin.php');
$prospectView = (string) file_get_contents($root . '/app/Views/portal/prospect_form.php');
is_ok(!str_contains($portalController, 'layouts/portal'),
    'Portal do captador usa layout oficial do sistema', 'Portal do captador ainda usa layout proprio');
foreach (['collector_portal.view', '/portal/commissions', '/portal/prospects/create'] as $needle) {
    is_ok(str_contains($adminLayout, $needle), "Layout oficial contem acesso do captador: {$needle}", "Layout oficial sem acesso do captador: {$needle}");
}
is_ok(str_contains($prospectView, 'readonly') && str_contains($prospectView, 'Projeto liberado para sua captacao'),
    'Formulario exibe projeto unico do captador como campo visivel', 'Formulario pode esconder projeto unico do captador');

// Permissoes do portal tambem no install_schema.sql (instalacao do zero)
$schemaSql = (string) file_get_contents($root . '/database/install_schema.sql');
foreach ($portalPerms as $slug) {
    is_ok(str_contains($schemaSql, "'" . $slug . "'"), "install_schema define permissao {$slug}", "install_schema sem permissao {$slug}");
}
is_ok(str_contains($schemaSql, "'collector_portal.view', 'collector_portal.companies.create'"),
    'install_schema concede permissoes do portal a captador-externo', 'install_schema sem grant do portal para captador-externo');
is_ok(method_exists(new \App\Models\CollectorDeal(), 'findByCompanyForCollector'),
    'CollectorDeal::findByCompanyForCollector existe', 'método ausente');

// ----------------------------------------------------------------------
// Setup de dados de teste
// ----------------------------------------------------------------------
$T = 'TESTE 18C F2B';
function cleanupF2B(PDO $pdo): void {
    $pdo->exec("DELETE cd FROM collector_deals cd JOIN collectors c ON c.id=cd.collector_id WHERE c.collector_code IN ('DCF-F2B-CAP','DCF-F2B-OTHER','DCF-F2B-BLOCK')");
    $pdo->exec("DELETE ca FROM collector_assignments ca JOIN collectors c ON c.id=ca.collector_id WHERE c.collector_code IN ('DCF-F2B-CAP','DCF-F2B-OTHER','DCF-F2B-BLOCK')");
    $pdo->exec("DELETE FROM sponsors WHERE company_id IN (SELECT id FROM companies WHERE name LIKE 'TESTE 18C F2B%')");
    $pdo->exec("DELETE FROM opportunities WHERE company_id IN (SELECT id FROM companies WHERE name LIKE 'TESTE 18C F2B%')");
    $pdo->exec("DELETE FROM contacts WHERE company_id IN (SELECT id FROM companies WHERE name LIKE 'TESTE 18C F2B%')");
    $pdo->exec("DELETE FROM companies WHERE name LIKE 'TESTE 18C F2B%'");
    $pdo->exec("DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE email IN ('teste18c.f2b.captador@example.com','teste18c.f2b.block@example.com'))");
    $pdo->exec("DELETE FROM collectors WHERE collector_code IN ('DCF-F2B-CAP','DCF-F2B-OTHER','DCF-F2B-BLOCK')");
    $pdo->exec("DELETE FROM users WHERE email IN ('teste18c.f2b.captador@example.com','teste18c.f2b.block@example.com')");
}
cleanupF2B($pdo);

$pdo->prepare("INSERT INTO collectors (name,collector_code,status,registration_status,created_by,created_at) VALUES (?,?,?,?,1,NOW())")
    ->execute(["$T Captador", 'DCF-F2B-CAP', 'ativo', 'validado']);
$capId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO collectors (name,collector_code,status,registration_status,created_by,created_at) VALUES (?,?,?,?,1,NOW())")
    ->execute(["$T Outro Captador", 'DCF-F2B-OTHER', 'ativo', 'validado']);
$otherId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (name,email,password_hash,status,must_change_password,created_at) VALUES (?,?,?,?,0,NOW())")
    ->execute(["$T Captador", 'teste18c.f2b.captador@example.com', password_hash($PWD, PASSWORD_DEFAULT), 'active']);
$uid = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid, $roleId]);
$pdo->prepare('UPDATE collectors SET user_id=? WHERE id=?')->execute([$uid, $capId]);

$mk = function (PDO $pdo, string $name): int {
    $pdo->prepare("INSERT INTO companies (name,priority,status,source,created_at) VALUES (?,?,?,?,NOW())")
        ->execute([$name, 'C', 'prospect', 'indicação interna']);
    return (int) $pdo->lastInsertId();
};
$coExcl = $mk($pdo, "$T Empresa Exclusiva Outro");
$coSpon = $mk($pdo, "$T Empresa Patrocinadora");
$coOpp  = $mk($pdo, "$T Empresa Oportunidade Interna");
$pdo->prepare("INSERT INTO collector_assignments (collector_id,company_id,assignment_type,status,created_by,created_at,notes) VALUES (?,?,'exclusiva','autorizada',1,NOW(),'F2B other')")->execute([$otherId, $coExcl]);
$pdo->prepare("INSERT INTO sponsors (company_id,sponsor_display_name,status,created_by,created_at) VALUES (?,?,?,1,NOW())")->execute([$coSpon, "$T Patroc", 'confirmado']);
$pdo->prepare("INSERT INTO opportunities (company_id,title,status,source,opened_at,created_at) VALUES (?,?,?,?,NOW(),NOW())")->execute([$coOpp, "$T Opp", 'prospect_identificado', 'outbound']);

// ----------------------------------------------------------------------
// 5) Motor de conflito (Service)
// ----------------------------------------------------------------------
$collector = (new \App\Models\Collector())->findByUserId($uid);
is_ok((int) ($collector['id'] ?? 0) === $capId, 'findByUserId resolve o captador', 'findByUserId falhou');
$svc = new \App\Services\CollectorProspectIntake();

$r = $svc->intake($collector, ['name' => "$T Empresa Limpa", 'cnpj' => '', 'notes' => 'contato'], $uid);
is_ok($r['status'] === 'criado', 'Sem conflito → prospect criado', 'Sem conflito falhou: ' . $r['status']);
if ($r['deal_id']) {
    $d = $pdo->query('SELECT source,deal_status FROM collector_deals WHERE id=' . (int) $r['deal_id'])->fetch();
    is_ok(($d['source'] ?? '') === 'portal_captador', 'Deal com origem portal_captador', 'Origem do deal incorreta');
    is_ok(($d['deal_status'] ?? '') === 'lead_indicado', 'Deal status lead_indicado', 'Deal status incorreto');
    $a = $pdo->query('SELECT status,assignment_type FROM collector_assignments WHERE id=' . (int) $r['assignment_id'])->fetch();
    is_ok(($a['status'] ?? '') === 'solicitada' && ($a['assignment_type'] ?? '') === 'exclusiva', 'Assignment exclusiva/solicitada criada', 'Assignment incorreta');
}
$r2 = $svc->intake($collector, ['name' => "$T Empresa Limpa", 'cnpj' => ''], $uid);
is_ok($r2['status'] === 'ja_na_carteira', 'Empresa repetida → já na carteira', 'Repetida não detectada: ' . $r2['status']);
$r3 = $svc->intake($collector, ['name' => "$T Empresa Exclusiva Outro", 'cnpj' => ''], $uid);
is_ok($r3['status'] === 'bloqueado', 'Exclusiva de outro captador → bloqueado', 'Conflito exclusiva não bloqueou: ' . $r3['status']);
$r4 = $svc->intake($collector, ['name' => "$T Empresa Patrocinadora", 'cnpj' => ''], $uid);
is_ok($r4['status'] === 'bloqueado', 'Empresa patrocinadora → bloqueado', 'Conflito patrocinadora não bloqueou: ' . $r4['status']);
$r5 = $svc->intake($collector, ['name' => "$T Empresa Oportunidade Interna", 'cnpj' => ''], $uid);
is_ok($r5['status'] === 'analise_interna', 'Oportunidade interna → análise interna', 'Análise interna falhou: ' . $r5['status']);
if ($r5['deal_id']) {
    $ds = $pdo->query('SELECT deal_status FROM collector_deals WHERE id=' . (int) $r5['deal_id'])->fetchColumn();
    is_ok($ds === 'empresa_em_analise', 'Deal em análise = empresa_em_analise', 'Deal análise incorreto: ' . $ds);
}

// limpa artefatos do service antes do E2E HTTP
$pdo->exec("DELETE FROM collector_deals WHERE collector_id={$capId}");
$pdo->exec("DELETE FROM collector_assignments WHERE collector_id={$capId}");
$pdo->exec("DELETE FROM companies WHERE name='{$T} Empresa Limpa'");

// ----------------------------------------------------------------------
// 6) E2E HTTP do portal
// ----------------------------------------------------------------------
$jar = tempnam(sys_get_temp_dir(), 'f2bval_');
$http = function (string $method, string $url, $post = null, bool $follow = true) use ($jar): array {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 30]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => '', 'loc' => '', 'error' => $err];
    }
    $hdr = substr($resp, 0, $hs); $body = substr($resp, $hs); curl_close($ch);
    preg_match('/Location:\s*(\S+)/i', $hdr, $m);
    return ['code' => $code, 'body' => $body, 'loc' => $m[1] ?? ''];
};
$csrf = static function (string $h): string { return preg_match('/name="_csrf"\s+value="([^"]+)"/', $h, $m) ? $m[1] : ''; };

$tok = $csrf($http('GET', $BASE . '/login')['body']);
$login = $http('POST', $BASE . '/login', ['_csrf' => $tok, 'email' => 'teste18c.f2b.captador@example.com', 'password' => $PWD], false);
is_ok($login['code'] === 302, 'Login captador redireciona apos autenticar', 'Login captador falhou: ' . $login['code']);
$dash = $http('GET', $BASE . '/dashboard');
is_ok($dash['code'] === 200, 'Dashboard operacional do captador abre', 'Dashboard do captador falhou: ' . $dash['code']);
$portal = $http('GET', $BASE . '/portal');
is_ok($portal['code'] === 200, 'GET /portal = 200', 'GET /portal = ' . $portal['code']);
$pf = $http('GET', $BASE . '/portal/prospects/create');
$tok2 = $csrf($pf['body']);
is_ok($pf['code'] === 200 && $tok2 !== '', 'Formulário de prospect carrega', 'Formulário de prospect falhou');
$portalProjectId = (int) $pdo->query("SELECT id FROM incentive_projects WHERE archived_at IS NULL AND project_status IN ('em_captacao','captado_parcial') ORDER BY id LIMIT 1")->fetchColumn();
is_ok($portalProjectId > 0, 'Projeto de captacao disponivel para o portal', 'Nenhum projeto de captacao disponivel para teste HTTP');
$portalProjectName = (string) $pdo->query('SELECT project_name FROM incentive_projects WHERE id=' . $portalProjectId)->fetchColumn();
is_ok($portalProjectName !== '' && str_contains($pf['body'], $portalProjectName),
    'Formulario HTTP exibe o projeto liberado para captacao', 'Formulario HTTP nao exibe o projeto liberado');
$store = $http('POST', $BASE . '/portal/prospects', ['_csrf' => $tok2, 'incentive_project_id' => $portalProjectId, 'name' => "$T Portal HTTP Empresa", 'cnpj' => '', 'segment' => 'tecnologia', 'city' => 'Parauapebas', 'state' => 'PA', 'email' => 'c@f2bhttp.example.com', 'phone' => '94999990000', 'notes' => 'via portal'], false);
is_ok($store['code'] === 302 && str_contains($store['loc'], '/portal/deals/'), 'Cadastro de prospect cria captação', 'Cadastro de prospect falhou: ' . $store['code']);
$deal = $pdo->query("SELECT id,source,deal_status,company_id FROM collector_deals WHERE collector_id={$capId} ORDER BY id DESC LIMIT 1")->fetch();
is_ok($deal && $deal['source'] === 'portal_captador', 'Captação HTTP origem portal_captador', 'Origem HTTP incorreta');
$dealId = (int) ($deal['id'] ?? 0); $companyId = (int) ($deal['company_id'] ?? 0);
$show = $http('GET', $BASE . '/portal/deals/' . $dealId);
is_ok($show['code'] === 200 && str_contains($show['body'], 'Portal do captador'), 'Detalhe da captação exibe origem', 'Detalhe da captação falhou');
$note = $http('POST', $BASE . '/portal/deals/' . $dealId . '/note', ['_csrf' => $tok2, 'note' => 'Primeiro contato realizado.'], false);
$notesDb = (string) $pdo->query('SELECT notes FROM collector_deals WHERE id=' . $dealId)->fetchColumn();
is_ok($note['code'] === 302 && str_contains($notesDb, 'Primeiro contato'), 'Observação registrada na captação', 'Observação falhou');
$contact = $http('POST', $BASE . '/portal/companies/' . $companyId . '/contacts', ['_csrf' => $tok2, 'name' => 'Maria Marketing', 'position_title' => 'Gerente', 'email' => 'maria@f2bhttp.example.com', 'whatsapp' => '94988887777'], false);
$ctCnt = (int) $pdo->query('SELECT COUNT(*) FROM contacts WHERE company_id=' . $companyId)->fetchColumn();
is_ok($contact['code'] === 302 && $ctCnt === 1, 'Contato cadastrado na empresa', 'Contato falhou');

// Escopo de contatos: contato interno de outro usuario nao pode aparecer ao captador
$pdo->prepare("INSERT INTO contacts (company_id,name,status,owner_user_id,created_by,created_at) VALUES (?,?,?,?,?,NOW())")
    ->execute([$companyId, "$T Contato Interno Sigiloso", 'ativo', 1, 1]);
$show2 = $http('GET', $BASE . '/portal/deals/' . $dealId);
is_ok(str_contains($show2['body'], 'Maria Marketing'), 'Captador ve o proprio contato', 'Contato do captador nao apareceu');
is_ok(!str_contains($show2['body'], 'Contato Interno Sigiloso'), 'Captador NAO ve contato interno de outro usuario', 'Vazou contato interno no portal');

// Bloqueio: captador sem credenciamento final nao acessa o portal
$pdo->prepare("INSERT INTO collectors (name,collector_code,status,registration_status,created_by,created_at) VALUES (?,?,?,?,1,NOW())")
    ->execute(["$T Captador Pendente", 'DCF-F2B-BLOCK', 'ativo', 'pendente']);
$blockColId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (name,email,password_hash,status,must_change_password,created_at) VALUES (?,?,?,?,0,NOW())")
    ->execute(["$T Captador Pendente", 'teste18c.f2b.block@example.com', password_hash($PWD, PASSWORD_DEFAULT), 'active']);
$blockUid = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$blockUid, $roleId]);
$pdo->prepare('UPDATE collectors SET user_id=? WHERE id=?')->execute([$blockUid, $blockColId]);
$jarB = tempnam(sys_get_temp_dir(), 'f2bblk_');
$reqB = function (string $method, string $url, $post = null, bool $follow = true) use ($jarB): array {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jarB, CURLOPT_COOKIEFILE => $jarB, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 30]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
        curl_close($ch);
        return ['code' => $code, 'body' => ''];
    }
    $body = substr($resp, $hs); curl_close($ch);
    return ['code' => $code, 'body' => $body];
};
$tokB = $csrf($reqB('GET', $BASE . '/login')['body']);
$reqB('POST', $BASE . '/login', ['_csrf' => $tokB, 'email' => 'teste18c.f2b.block@example.com', 'password' => $PWD], false);
$portalB = $reqB('GET', $BASE . '/portal');
is_ok($portalB['code'] === 403 && str_contains($portalB['body'], 'ainda nao esta liberado'), 'Portal bloqueia captador sem credenciamento final', 'Portal nao bloqueou captador sem credenciamento: ' . $portalB['code']);
@unlink($jarB);

// Escopo operacional permitido: base compartilhada alimentada pelo captador.
foreach (['/projects' => 'Projetos', '/companies' => 'Empresas', '/companies/create' => 'Nova empresa', '/contacts' => 'Contatos', '/contacts/create' => 'Novo contato'] as $path => $label) {
    $r = $http('GET', $BASE . $path, null, false);
    is_ok($r['code'] === 200, "Captador acessa {$label}", "Captador nao acessou {$label}: " . $r['code']);
}

// Escopo de gestao sensivel segue proibido.
foreach (['/collectors' => 'Captadores', '/opportunities' => 'Oportunidades', '/users' => 'Usuarios'] as $path => $label) {
    $r = $http('GET', $BASE . $path, null, false);
    is_ok($r['code'] === 403, "Captador sem acesso interno: {$label} (403)", "Captador acessou {$label}: " . $r['code']);
}
@unlink($jar);

// Admin enxerga a carteira (nível de modelo/dados)
$adminView = (new \App\Models\CollectorDeal())->forCollector($capId);
is_ok(count($adminView) >= 1, 'Carteira do captador visível para gestão interna', 'Carteira não visível internamente');

// ----------------------------------------------------------------------
// 7) Limpeza
// ----------------------------------------------------------------------
try {
    cleanupF2B($pdo);
    ok('Limpeza dos dados de teste concluída');
} catch (Throwable $e) {
    fail('Limpeza: ' . $e->getMessage());
}

echo "\n=== RESUMO FASE 2B ===\n";
echo 'PASS: ' . $passes . "\n";
echo 'FAIL: ' . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) { echo "  - {$f}\n"; }
    exit(1);
}
echo "Validação Etapa 18C / Fase 2B COMPLETA — {$passes} PASS / 0 FAIL\n";
exit(0);
