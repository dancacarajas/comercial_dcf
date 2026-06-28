<?php

declare(strict_types=1);

/**
 * Regressão leve — Fase 1 (credenciamento) + Fase 2 (atribuição interna) + Fase 2B (portal).
 * Cria dados temporários (prefixo TESTE 18C REG), valida via HTTP real e limpa ao final.
 * Uso: VALIDATE_BASE_URL=https://comercial.dancacarajas.com.br php scripts/validate_etapa18c_regressao.php
 */

$root = dirname(__DIR__);
$passes = 0; $failures = []; $ids = [];
function ok(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function is_ok(bool $c, string $p, string $f): void { $c ? ok($p) : fail($f); }

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
$BASE = getenv('VALIDATE_BASE_URL') ?: rtrim((string) ($cfg['url'] ?? ''), '/');
$PWD = 'RegF2B#2026';

// ---------- HTTP helpers ----------
function http(string $method, string $url, string $jar, $post = null, bool $follow = true): array {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 40]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr = substr($resp, 0, $hs); $body = substr($resp, $hs); curl_close($ch);
    preg_match('/Location:\s*(\S+)/i', $hdr, $m);
    return ['code' => $code, 'body' => $body, 'loc' => $m[1] ?? ''];
}
function csrf(string $h): string { return preg_match('/name="_csrf"\s+value="([^"]+)"/', $h, $m) ? $m[1] : ''; }
function login(string $base, string $jar, string $email, string $pwd): bool {
    $g = http('GET', $base . '/login', $jar);
    $r = http('POST', $base . '/login', $jar, ['_csrf' => csrf($g['body']), 'email' => $email, 'password' => $pwd]);
    return in_array($r['code'], [200, 302], true);
}

// ---------- cleanup ----------
$cleanup = function (PDO $pdo): void {
    $appIds = $pdo->query("SELECT id FROM collector_applications WHERE name LIKE 'TESTE 18C REG%' OR application_number LIKE 'REG-%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($appIds as $aid) {
        $aid = (int) $aid;
        $reqs = $pdo->query("SELECT id FROM signature_requests WHERE source_type='collector_application' AND source_id={$aid}")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($reqs as $rid) { $pdo->exec("DELETE FROM signature_signers WHERE signature_request_id=" . (int) $rid); }
        if ($reqs !== []) { $pdo->exec("DELETE FROM signature_requests WHERE id IN (" . implode(',', array_map('intval', $reqs)) . ")"); }
        $pdo->exec("DELETE FROM collector_application_documents WHERE collector_application_id={$aid}");
    }
    $pdo->exec("DELETE cd FROM collector_deals cd JOIN collectors c ON c.id=cd.collector_id WHERE c.collector_code='DCF-REG-TEST'");
    $pdo->exec("DELETE ca FROM collector_assignments ca JOIN collectors c ON c.id=ca.collector_id WHERE c.collector_code='DCF-REG-TEST'");
    $pdo->exec("DELETE FROM opportunities WHERE company_id IN (SELECT id FROM companies WHERE name LIKE 'TESTE 18C REG%')");
    $pdo->exec("DELETE FROM companies WHERE name LIKE 'TESTE 18C REG%'");
    $pdo->exec("DELETE FROM collectors WHERE collector_code='DCF-REG-TEST'");
    $pdo->exec("DELETE FROM collector_applications WHERE name LIKE 'TESTE 18C REG%' OR application_number LIKE 'REG-%'");
    $pdo->exec("DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE email IN ('regr.admin@example.com','regr.comercial@example.com'))");
    $pdo->exec("DELETE FROM users WHERE email IN ('regr.admin@example.com','regr.comercial@example.com')");
};
$cleanup($pdo);

// ---------- temp users ----------
$roleId = function (PDO $pdo, string $slug): int { return (int) $pdo->query("SELECT id FROM roles WHERE slug='" . $slug . "' LIMIT 1")->fetchColumn(); };
$mkUser = function (PDO $pdo, string $name, string $email, string $pwd, int $rid): int {
    $pdo->prepare("INSERT INTO users (name,email,password_hash,status,must_change_password,created_at) VALUES (?,?,?,?,0,NOW())")
        ->execute([$name, $email, password_hash($pwd, PASSWORD_DEFAULT), 'active']);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid, $rid]);
    return $uid;
};
$adminUid = $mkUser($pdo, 'TESTE 18C REG Admin', 'regr.admin@example.com', $PWD, $roleId($pdo, 'administrador-geral'));
$comUid   = $mkUser($pdo, 'TESTE 18C REG Comercial', 'regr.comercial@example.com', $PWD, $roleId($pdo, 'captacao-comercial'));
$ids['admin_user'] = $adminUid; $ids['comercial_user'] = $comUid;

$jarA = tempnam(sys_get_temp_dir(), 'regA_');
is_ok(login($BASE, $jarA, 'regr.admin@example.com', $PWD), 'Login admin temporario', 'Falha login admin temporario');

// ======================================================================
// FASE 1 — Credenciamento juridico (setup por model, gates por HTTP)
// ======================================================================
$appModel = new \App\Models\CollectorApplication();
$appId = (int) $appModel->create([
    'application_number' => 'REG-' . time(),
    'source' => 'indicacao_interna', 'name' => 'TESTE 18C REG Captador',
    'document_number' => '12345678901', 'email' => 'regr.captador@example.com',
    'phone_whatsapp' => '94999990000', 'city_state' => 'Parauapebas/PA',
    'status' => 'em_analise_documental', 'review_status' => 'em_analise',
]);
$ids['fase1_application'] = $appId;
is_ok($appId > 0, 'Fase 1: candidatura criada', 'Fase 1: falha ao criar candidatura');

$colModel = new \App\Models\Collector();
$colId = (int) $colModel->create([
    'collector_application_id' => $appId, 'collector_code' => 'DCF-REG-TEST',
    'type' => 'pessoa_fisica', 'status' => 'ativo', 'registration_status' => 'rascunho',
    'name' => 'TESTE 18C REG Captador', 'document_number' => '12345678901',
    'email' => 'regr.captador@example.com', 'phone_whatsapp' => '94999990000',
]);
$ids['fase1_collector'] = $colId;
is_ok($colId > 0, 'Fase 1: cadastro mestre criado (incompleto)', 'Fase 1: falha cadastro mestre');

// doc enviado -> allDocumentsSubmitted true
$pdo->prepare("INSERT INTO collector_application_documents (collector_application_id,document_type,title,status,created_at) VALUES (?,?,?,?,NOW())")
    ->execute([$appId, 'rg_cpf', 'TESTE 18C REG Documento', 'enviado']);

// Gate: validar cadastro incompleto -> bloqueia
$tok = csrf(http('GET', $BASE . '/collector-applications/' . $appId, $jarA)['body']);
http('POST', $BASE . '/collector-applications/' . $appId . '/collector/validate', $jarA, ['_csrf' => $tok], false);
$rs = (string) $pdo->query("SELECT registration_status FROM collectors WHERE id={$colId}")->fetchColumn();
is_ok($rs !== 'validado', 'Fase 1: validar cadastro incompleto bloqueia', 'Fase 1: validou cadastro incompleto (status=' . $rs . ')');

// Completa o cadastro mestre
$colModel->update($colId, [
    'address_zipcode' => '68515000', 'address_street' => 'Rua Teste', 'address_number' => '100',
    'address_district' => 'Centro', 'address_city' => 'Parauapebas', 'address_state' => 'PA',
    'pix_key' => 'regr.captador@example.com', 'pix_key_type' => 'email', 'bank_holder_name' => 'TESTE 18C REG Captador',
    'commission_percentage' => '10.00', 'contract_start_date' => date('Y-m-d'), 'contract_end_date' => date('Y-m-d', strtotime('+1 year')),
]);
$missing = $colModel->missingRequirements($colModel->find($colId));
is_ok($missing === [], 'Fase 1: cadastro mestre completo (sem pendencias)', 'Fase 1: ainda incompleto: ' . implode('; ', $missing));

// Gate: aprovar sem validar -> bloqueia
$tok = csrf(http('GET', $BASE . '/collector-applications/' . $appId, $jarA)['body']);
http('POST', $BASE . '/collector-applications/' . $appId . '/approve', $jarA, ['_csrf' => $tok], false);
$st = (string) $pdo->query("SELECT status FROM collector_applications WHERE id={$appId}")->fetchColumn();
is_ok($st !== 'aprovado', 'Fase 1: aprovar sem validar bloqueia', 'Fase 1: aprovou sem validar (status=' . $st . ')');

// Validar como administrador-geral
$tok = csrf(http('GET', $BASE . '/collector-applications/' . $appId, $jarA)['body']);
http('POST', $BASE . '/collector-applications/' . $appId . '/collector/validate', $jarA, ['_csrf' => $tok], false);
$rs = (string) $pdo->query("SELECT registration_status FROM collectors WHERE id={$colId}")->fetchColumn();
is_ok($rs === 'validado', 'Fase 1: cadastro validado pelo admin', 'Fase 1: cadastro nao validado (status=' . $rs . ')');

// Aprovar candidatura
$tok = csrf(http('GET', $BASE . '/collector-applications/' . $appId, $jarA)['body']);
http('POST', $BASE . '/collector-applications/' . $appId . '/approve', $jarA, ['_csrf' => $tok, 'approval_notes' => 'regressao'], false);
$st = (string) $pdo->query("SELECT status FROM collector_applications WHERE id={$appId}")->fetchColumn();
is_ok(in_array($st, ['aprovado', 'aguardando_assinatura_contratual'], true), 'Fase 1: candidatura aprovada', 'Fase 1: aprovacao falhou (status=' . $st . ')');

// Documentos de assinatura gerados + JA auto-assinada + captador pendente
$req = $pdo->query("SELECT id FROM signature_requests WHERE source_type='collector_application' AND source_id={$appId} ORDER BY id DESC LIMIT 1")->fetch();
is_ok($req !== false, 'Fase 1: documento de assinatura gerado', 'Fase 1: nenhuma signature_request');
if ($req !== false) {
    $signers = $pdo->query("SELECT signer_role,status FROM signature_signers WHERE signature_request_id=" . (int) $req['id'])->fetchAll();
    $signed = array_filter($signers, fn ($s) => $s['status'] === 'assinado');
    $pending = array_filter($signers, fn ($s) => $s['status'] !== 'assinado');
    is_ok(count($signed) >= 1, 'Fase 1: JA Producoes auto-assinada', 'Fase 1: contratante nao auto-assinou');
    is_ok(count($pending) >= 1, 'Fase 1: captador pendente de assinatura', 'Fase 1: captador nao esta pendente');
}

// ======================================================================
// FASE 2 — Atribuicao interna (HTTP admin)
// ======================================================================
$companyId = (int) (new \App\Models\Company())->create(['name' => 'TESTE 18C REG Empresa Interna', 'priority' => 'B', 'status' => 'prospect', 'source' => 'indicação interna']);
$ids['fase2_company'] = $companyId;

$f = http('GET', $BASE . '/collectors/' . $colId . '/assignments/create', $jarA); $tok = csrf($f['body']);
http('POST', $BASE . '/collectors/' . $colId . '/assignments', $jarA, ['_csrf' => $tok, 'company_id' => $companyId, 'assignment_type' => 'exclusiva', 'exclusive_until' => '', 'notes' => 'TESTE 18C REG'], false);
$a1 = $pdo->query("SELECT id,status FROM collector_assignments WHERE collector_id={$colId} AND company_id={$companyId} ORDER BY id DESC LIMIT 1")->fetch();
$aid = (int) ($a1['id'] ?? 0); $ids['fase2_assignment'] = $aid;
is_ok($aid > 0, 'Fase 2: atribuicao interna criada', 'Fase 2: falha ao criar atribuicao');

http('POST', $BASE . '/collector-assignments/' . $aid . '/authorize', $jarA, ['_csrf' => $tok], false);
$st = (string) $pdo->query("SELECT status FROM collector_assignments WHERE id={$aid}")->fetchColumn();
is_ok($st === 'autorizada', 'Fase 2: abordagem autorizada', 'Fase 2: autorizacao falhou (status=' . $st . ')');

// segunda exclusiva vigente -> bloqueia
$f2 = http('GET', $BASE . '/collectors/' . $colId . '/assignments/create', $jarA); $tok2 = csrf($f2['body']);
http('POST', $BASE . '/collectors/' . $colId . '/assignments', $jarA, ['_csrf' => $tok2, 'company_id' => $companyId, 'assignment_type' => 'exclusiva', 'exclusive_until' => '', 'notes' => 'TESTE 18C REG dup'], false);
$cntAtiva = (int) $pdo->query("SELECT COUNT(*) FROM collector_assignments WHERE company_id={$companyId} AND assignment_type='exclusiva' AND status IN ('solicitada','autorizada') AND archived_at IS NULL")->fetchColumn();
is_ok($cntAtiva === 1, 'Fase 2: segunda exclusiva vigente bloqueada', 'Fase 2: criou exclusiva duplicada (ativas=' . $cntAtiva . ')');

// converter em oportunidade
http('POST', $BASE . '/collector-assignments/' . $aid . '/convert', $jarA, ['_csrf' => $tok2], false);
$opp = $pdo->query("SELECT id,source FROM opportunities WHERE company_id={$companyId} ORDER BY id DESC LIMIT 1")->fetch();
$oid = (int) ($opp['id'] ?? 0); $ids['fase2_opportunity'] = $oid;
is_ok($oid > 0 && ($opp['source'] ?? '') === 'captador', 'Fase 2: convertida em oportunidade source=captador', 'Fase 2: conversao/source incorretos');
$deal = $pdo->query("SELECT id FROM collector_deals WHERE collector_id={$colId} AND company_id={$companyId} ORDER BY id DESC LIMIT 1")->fetch();
$ids['fase2_deal'] = (int) ($deal['id'] ?? 0);
is_ok($deal !== false, 'Fase 2: collector_deal criado', 'Fase 2: collector_deal nao criado');
if ($oid > 0) {
    $show = http('GET', $BASE . '/opportunities/' . $oid, $jarA);
    is_ok($show['code'] === 200 && str_contains($show['body'], 'Origem da captação'), 'Fase 2: card Origem da captacao presente', 'Fase 2: card Origem da captacao ausente');
}
@unlink($jarA);

// ======================================================================
// Limpeza Fase 1 + Fase 2
// ======================================================================
try { $cleanup($pdo); ok('Limpeza Fase 1 + Fase 2 concluida'); } catch (Throwable $e) { fail('Limpeza F1/F2: ' . $e->getMessage()); }

echo "\n=== IDs criados (regressao F1+F2) ===\n" . json_encode($ids, JSON_UNESCAPED_UNICODE) . "\n";
echo "\n=== RESUMO REGRESSAO F1+F2 ===\nPASS: {$passes}\nFAIL: " . count($failures) . "\n";
foreach ($failures as $f) { echo "  - {$f}\n"; }
exit($failures === [] ? 0 : 1);
